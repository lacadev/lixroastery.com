<?php

namespace App\Security;

/**
 * RequestSigner
 *
 * Chuẩn ký HMAC-SHA256 cho giao tiếp Hub ⇄ Client trong hệ sinh thái LacaDev.
 * File này được triển khai GIỐNG HỆT NHAU tại cả Hub (lacadev) và Client (lacadev-client).
 *
 * Header chuẩn:
 *   - X-Laca-Project:   Project Public ID (chuỗi định danh công khai của dự án, không phải ID DB lộ thứ tự)
 *   - X-Laca-Timestamp: Unix timestamp (time() theo UTC) tại thời điểm ký
 *   - X-Laca-Signature: hex(hmac_sha256(METHOD . "\n" . PATH . "\n" . TIMESTAMP . "\n" . sha256(BODY), key))
 *
 * Quy ước chuẩn hoá PATH:
 *   - Mọi route REST API trong hệ thống đều được quy về định dạng canonical: `/wp-json/laca/v1/...`
 *   - Nếu URL sử dụng pretty permalinks (`/wp-json/laca/v1/...`), path là `/wp-json/laca/v1/...`
 *   - Nếu URL sử dụng plain permalinks (`?rest_route=/laca/v1/...`), tham số rest_route được bóc tách và chuẩn hoá thành `/wp-json/laca/v1/...`
 *   - Bỏ toàn bộ query parameters khác (query string không tham gia vào chuỗi ký path)
 *   - Bỏ tiền tố `/index.php` nếu máy chủ cấu hình PATHINFO
 *   - Bỏ dấu gạch chéo cuối (`rtrim($path, '/')`) để tránh lệch giữa client và server
 *
 * Cơ chế bảo vệ:
 *   - Chống lệch giờ (time drift): Từ chối nếu abs(time() - timestamp) > 300 giây (5 phút)
 *   - Chống Replay Attack: Lưu chữ ký vào transient 600 giây (10 phút). Nếu cùng chữ ký xuất hiện lần 2 → 401
 *   - So sánh constant-time bằng hash_equals() để chống timing attack
 */
class RequestSigner
{
    public const HEADER_PROJECT   = 'X-Laca-Project';
    public const HEADER_TIMESTAMP = 'X-Laca-Timestamp';
    public const HEADER_SIGNATURE = 'X-Laca-Signature';

    public const MAX_TIME_DRIFT    = 300; // 5 phút (giây)
    public const REPLAY_TTL        = 600; // 10 phút (giây)
    public const TRANSIENT_PREFIX  = 'laca_sig_';

    /**
     * Chuẩn hoá đường dẫn endpoint về dạng canonical `/wp-json/<route>`
     *
     * Quy ước duy nhất: Bắt đầu bằng `/wp-json/`, không trailing slash.
     * Xử lý được: pretty permalink, query parameter ?rest_route=, thư mục con (/sub/wp-json/...).
     *
     * @param string $pathOrUrl URL đầy đủ hoặc đường dẫn request
     * @return string Đường dẫn canonical không kèm query string, không trailing slash
     */
    public static function normalizePath(string $pathOrUrl): string
    {
        $parsed = parse_url($pathOrUrl);
        $path   = $parsed['path'] ?? '';
        $query  = $parsed['query'] ?? '';

        // 1. Xử lý plain permalink dạng ?rest_route=/laca/v1/... hoặc ?rest_route=/lacadev/v1/...
        if (!empty($query)) {
            parse_str($query, $queryParams);
            if (!empty($queryParams['rest_route'])) {
                $route = (string) $queryParams['rest_route'];
                return rtrim('/wp-json/' . ltrim($route, '/'), '/');
            }
        }

        // 2. Bỏ tiền tố /index.php nếu có
        if (preg_match('#^/index\.php(/.*)?$#', $path, $matches)) {
            $path = $matches[1] ?? '';
        }

        // 3. Nếu chứa /wp-json/ (bao gồm cả site trong thư mục con như /subsite/wp-json/...)
        $pos = strpos($path, '/wp-json/');
        if ($pos !== false) {
            $path = substr($path, $pos);
        } elseif ($path === '/wp-json') {
            $path = '/wp-json';
        } else {
            // 4. Nếu là route trần (không có /wp-json, vd /laca/v1/... hoặc /lacadev/v1/...)
            $path = '/wp-json/' . ltrim($path, '/');
        }

        $path = rtrim($path, '/');

        return $path ?: '/wp-json';
    }

    /**
     * Xây dựng chuỗi dữ liệu gốc (payload) trước khi bọc HMAC
     *
     * Format: METHOD\nPATH\nTIMESTAMP\nBODY_HASH
     */
    public static function buildSignatureString(string $method, string $path, int $timestamp, string $body): string
    {
        $normMethod = strtoupper(trim($method));
        $normPath   = self::normalizePath($path);
        $bodyHash   = hash('sha256', $body);

        return "{$normMethod}\n{$normPath}\n{$timestamp}\n{$bodyHash}";
    }

    /**
     * Tính chữ ký HMAC-SHA256
     */
    public static function computeSignature(string $method, string $path, int $timestamp, string $body, string $key): string
    {
        $payload = self::buildSignatureString($method, $path, $timestamp, $body);

        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Ký request và trả về mảng header HTTP
     *
     * @param string   $method    GET, POST, PUT, DELETE...
     * @param string   $pathOrUrl URL hoặc path của endpoint
     * @param string   $body      Nội dung body (chuỗi thô hoặc rỗng nếu GET)
     * @param string   $key       Secret key tương ứng của luồng
     * @param string   $projectId Public ID của dự án
     * @param int|null $timestamp Unix timestamp tuỳ chọn (mặc định time())
     * @return array<string,string> Mảng các header chuẩn
     */
    public static function sign(
        string $method,
        string $pathOrUrl,
        string $body,
        string $key,
        string $projectId,
        ?int $timestamp = null
    ): array {
        $timestamp = $timestamp ?? time();
        $signature = self::computeSignature($method, $pathOrUrl, $timestamp, $body, $key);

        return [
            self::HEADER_PROJECT   => $projectId,
            self::HEADER_TIMESTAMP => (string) $timestamp,
            self::HEADER_SIGNATURE => $signature,
        ];
    }

    /**
     * Xác thực request nhận được
     *
     * @param \WP_REST_Request|array<string,mixed> $request Request WordPress hoặc mảng context
     * @param string $key Secret key dùng để giải mã / so khớp chữ ký
     * @return array{valid:bool, error:string|null, message:string, project:string|null}
     */
    public static function verify(mixed $request, string $key): array
    {
        if (empty($key)) {
            return [
                'valid'   => false,
                'error'   => 'missing_key',
                'message' => 'Key xác thực chưa được cấu hình hoặc rỗng.',
                'project' => null,
            ];
        }

        // Bóc tách thông tin từ WP_REST_Request hoặc mảng dữ liệu
        if ($request instanceof \WP_REST_Request) {
            $projectId = (string) ($request->get_header('x_laca_project') ?: $request->get_header('x-laca-project') ?: '');
            $timestamp = (int) ($request->get_header('x_laca_timestamp') ?: $request->get_header('x-laca-timestamp') ?: 0);
            $signature = (string) ($request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature') ?: '');
            $method    = $request->get_method();
            // WP_REST_Request::get_route() trả về route REST (vd /laca/v1/tracker/log hoặc /lacadev/v1/sync-block)
            $route     = $request->get_route();
            $path      = '/wp-json/' . ltrim($route, '/');
            $body      = (string) $request->get_body();
        } elseif (is_array($request)) {
            $headers   = $request['headers'] ?? [];
            $projectId = (string) ($headers[self::HEADER_PROJECT] ?? $headers['x-laca-project'] ?? $headers['HTTP_X_LACA_PROJECT'] ?? '');
            $timestamp = (int) ($headers[self::HEADER_TIMESTAMP] ?? $headers['x-laca-timestamp'] ?? $headers['HTTP_X_LACA_TIMESTAMP'] ?? 0);
            $signature = (string) ($headers[self::HEADER_SIGNATURE] ?? $headers['x-laca-signature'] ?? $headers['HTTP_X_LACA_SIGNATURE'] ?? '');
            $method    = (string) ($request['method'] ?? 'POST');
            $path      = (string) ($request['path'] ?? '');
            $body      = (string) ($request['body'] ?? '');
        } else {
            return [
                'valid'   => false,
                'error'   => 'invalid_request',
                'message' => 'Request không đúng định dạng kiểm tra.',
                'project' => null,
            ];
        }

        // 1. Kiểm tra đủ headers
        if (empty($projectId) || empty($timestamp) || empty($signature)) {
            return [
                'valid'   => false,
                'error'   => 'missing_headers',
                'message' => 'Thiếu thông tin chữ ký xác thực (X-Laca-*).',
                'project' => $projectId ?: null,
            ];
        }

        // 2. Kiểm tra lệch giờ (Time Drift)
        $now = time();
        if (abs($now - $timestamp) > self::MAX_TIME_DRIFT) {
            return [
                'valid'   => false,
                'error'   => 'expired_timestamp',
                'message' => 'Timestamp lệch quá giới hạn 300 giây.',
                'project' => $projectId,
            ];
        }

        // 3. Kiểm tra Replay Attack qua transient
        $replayKey = self::TRANSIENT_PREFIX . md5($signature);
        if (get_transient($replayKey)) {
            return [
                'valid'   => false,
                'error'   => 'replay_detected',
                'message' => 'Phát hiện replay request (chữ ký này đã được sử dụng).',
                'project' => $projectId,
            ];
        }

        // 4. So sánh chữ ký HMAC
        $expectedSignature = self::computeSignature($method, $path, $timestamp, $body, $key);
        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'valid'   => false,
                'error'   => 'invalid_signature',
                'message' => 'Chữ ký HMAC không hợp lệ.',
                'project' => $projectId,
            ];
        }

        // Đánh dấu chữ ký đã dùng để chặn replay
        set_transient($replayKey, 1, self::REPLAY_TTL);

        return [
            'valid'   => true,
            'error'   => null,
            'message' => 'Xác thực thành công.',
            'project' => $projectId,
        ];
    }
}

