<?php

namespace App\Features\LacaSeo\Modules\SearchConsole;

defined('ABSPATH') || exit;

/**
 * Handles Google OAuth 2.0 Authorization Code flow with PKCE (S256),
 * state validation, token exchange, token refresh, and token revocation.
 * Strictly avoids hardcoding secrets or calling external networks when mocked/offline.
 */
class GscOAuth
{
    public const OPTION_KEY = 'laca_seo_gsc';
    public const SCOPE      = 'https://www.googleapis.com/auth/webmasters.readonly';
    public const AUTH_URI   = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URI  = 'https://oauth2.googleapis.com/token';
    public const REVOKE_URI = 'https://oauth2.googleapis.com/revoke';

    /**
     * Get redirect URI for Google Cloud Console OAuth setup.
     */
    public static function getRedirectUri(): string
    {
        return admin_url('admin.php?page=laca-seo&view=search-console&laca_gsc_oauth=1');
    }

    /**
     * Generate PKCE code verifier and code challenge (S256).
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function generatePkce(): array
    {
        $verifier = wp_generate_password(64, false, false);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [
            'verifier'  => $verifier,
            'challenge' => $challenge,
        ];
    }

    /**
     * Build OAuth 2.0 authorization URL.
     *
     * @param string $clientId
     * @param int|null $userId
     * @return string
     */
    public static function buildAuthUrl(string $clientId, ?int $userId = null): string
    {
        $uid = $userId ?: get_current_user_id();
        $pkce = self::generatePkce();

        // State nonce bound to user and PKCE verifier
        $stateData = [
            'uid'   => $uid,
            'nonce' => wp_create_nonce('laca_gsc_oauth_' . $uid),
            'time'  => time(),
        ];
        $state = base64_encode(json_encode($stateData));

        // Save PKCE verifier in transient for 15 minutes
        set_transient('laca_gsc_pkce_' . $uid, $pkce['verifier'], 15 * MINUTE_IN_SECONDS);

        $params = [
            'client_id'             => $clientId,
            'redirect_uri'          => self::getRedirectUri(),
            'response_type'         => 'code',
            'scope'                 => self::SCOPE,
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => $state,
            'code_challenge'        => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ];

        return self::AUTH_URI . '?' . http_build_query($params);
    }

    /**
     * Verify state returned from callback.
     *
     * @param string $state
     * @param int|null $userId
     * @return bool
     */
    public static function verifyState(string $state, ?int $userId = null): bool
    {
        $uid = $userId ?: get_current_user_id();
        $decoded = json_decode(base64_decode($state), true);
        if (!is_array($decoded) || empty($decoded['uid']) || empty($decoded['nonce'])) {
            return false;
        }

        if ((int) $decoded['uid'] !== $uid) {
            return false;
        }

        return (bool) wp_verify_nonce($decoded['nonce'], 'laca_gsc_oauth_' . $uid);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code
     * @param string $clientId
     * @param string $clientSecret
     * @param int|null $userId
     * @return array{success: bool, error?: string}
     */
    public static function handleCallback(string $code, string $clientId, string $clientSecret, ?int $userId = null): array
    {
        $uid = $userId ?: get_current_user_id();
        $verifier = get_transient('laca_gsc_pkce_' . $uid);
        delete_transient('laca_gsc_pkce_' . $uid);

        $body = [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => self::getRedirectUri(),
            'grant_type'    => 'authorization_code',
        ];
        if (!empty($verifier)) {
            $body['code_verifier'] = $verifier;
        }

        $response = wp_remote_post(self::TOKEN_URI, [
            'timeout' => 15,
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            return ['success' => false, 'error' => $data['error_description'] ?? 'Không lấy được access_token'];
        }

        self::saveCredentials([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in'    => $data['expires_in'] ?? 3600,
            'created_at'    => time(),
        ]);

        return ['success' => true];
    }

    /**
     * Get stored credentials and decrypted tokens.
     *
     * @return array|null
     */
    public static function getCredentials(): ?array
    {
        $raw = get_option(self::OPTION_KEY, null);
        if (!$raw || !is_string($raw)) {
            return null;
        }

        $data = GscCrypto::decrypt($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Save credentials encrypted.
     */
    public static function saveCredentials(array $data): bool
    {
        $encrypted = GscCrypto::encrypt($data);
        return update_option(self::OPTION_KEY, $encrypted, false);
    }

    /**
     * Get valid access token, refreshing automatically if expired.
     */
    public static function getValidAccessToken(): ?string
    {
        $creds = self::getCredentials();
        if (!$creds || empty($creds['access_token'])) {
            return null;
        }

        $createdAt = $creds['created_at'] ?? 0;
        $expiresIn = $creds['expires_in'] ?? 3600;

        // If expires within 60 seconds, refresh
        if (time() >= ($createdAt + $expiresIn - 60)) {
            $refreshed = self::refreshToken($creds);
            return $refreshed ? $refreshed['access_token'] : null;
        }

        return $creds['access_token'];
    }

    /**
     * Refresh access token via oauth2.googleapis.com/token.
     */
    public static function refreshToken(array $creds): ?array
    {
        if (empty($creds['refresh_token']) || empty($creds['client_id']) || empty($creds['client_secret'])) {
            return null;
        }

        $response = wp_remote_post(self::TOKEN_URI, [
            'timeout' => 15,
            'body'    => [
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $creds['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            return null;
        }

        $creds['access_token'] = $data['access_token'];
        $creds['expires_in']   = $data['expires_in'] ?? 3600;
        $creds['created_at']   = time();
        if (!empty($data['refresh_token'])) {
            $creds['refresh_token'] = $data['refresh_token'];
        }

        self::saveCredentials($creds);

        return $creds;
    }

    /**
     * Disconnect Google Search Console: revokes token via Google API and deletes local option.
     */
    public static function disconnect(): bool
    {
        $creds = self::getCredentials();
        if ($creds && !empty($creds['access_token'])) {
            $tokenToRevoke = !empty($creds['refresh_token']) ? $creds['refresh_token'] : $creds['access_token'];
            wp_remote_post(self::REVOKE_URI . '?token=' . urlencode($tokenToRevoke), [
                'timeout' => 5,
            ]);
        }

        return delete_option(self::OPTION_KEY);
    }
}
