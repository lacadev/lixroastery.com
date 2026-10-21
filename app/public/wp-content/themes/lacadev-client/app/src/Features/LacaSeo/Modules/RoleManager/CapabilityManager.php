<?php

namespace App\Features\LacaSeo\Modules\RoleManager;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;

defined('ABSPATH') || exit;

/**
 * Manages SEO capabilities and role permissions.
 * Maps 1:1 with Rank Math capability system.
 * Administrator with 'manage_options' always bypasses and holds all capabilities.
 */
class CapabilityManager
{
    /**
     * Map of Laca SEO capabilities to Rank Math capabilities.
     */
    public const CAPABILITIES = [
        'laca_seo_titles'           => 'rank_math_titles',
        'laca_seo_general'          => 'rank_math_general',
        'laca_seo_sitemap'          => 'rank_math_sitemap',
        'laca_seo_404_monitor'      => 'rank_math_404_monitor',
        'laca_seo_link_builder'     => 'rank_math_link_builder',
        'laca_seo_redirections'     => 'rank_math_redirections',
        'laca_seo_role_manager'     => 'rank_math_role_manager',
        'laca_seo_analytics'        => 'rank_math_analytics',
        'laca_seo_site_analysis'    => 'rank_math_site_analysis',
        'laca_seo_onpage_analysis'  => 'rank_math_onpage_analysis',
        'laca_seo_onpage_general'   => 'rank_math_onpage_general',
        'laca_seo_onpage_advanced'  => 'rank_math_onpage_advanced',
        'laca_seo_onpage_snippet'   => 'rank_math_onpage_snippet',
        'laca_seo_onpage_social'    => 'rank_math_onpage_social',
        'laca_seo_content_ai'       => 'rank_math_content_ai',
        'laca_seo_admin_bar'        => 'rank_math_admin_bar',
    ];

    public function register(): void
    {
        // Intercept user_has_cap so admins always have all capabilities,
        // and check mapped roles or options
        add_filter('user_has_cap', [$this, 'filterUserCaps'], 10, 4);
    }

    /**
     * Get all registered capabilities.
     *
     * @return string[]
     */
    public static function getCapabilities(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    /**
     * Filter user capabilities.
     * Administrators with manage_options always hold all laca_seo_* capabilities.
     */
    public function filterUserCaps(array $allCaps, array $caps, array $args, \WP_User $user): array
    {
        $isAdmin = !empty($allCaps['manage_options']) || in_array('administrator', (array) $user->roles, true);

        foreach (self::getCapabilities() as $cap) {
            if ($isAdmin) {
                $allCaps[$cap] = true;
            }
        }

        return $allCaps;
    }

    /**
     * Check if a user has a specific SEO capability.
     */
    public static function hasCap(string $cap, ?int $userId = null): bool
    {
        $user = $userId ? get_userdata($userId) : wp_get_current_user();
        if (!$user || !$user->exists()) {
            return false;
        }

        if (user_can($user, 'manage_options') || in_array('administrator', (array) $user->roles, true)) {
            return true;
        }

        // Map short capability names (e.g. 'titles', 'admin_bar') to 'laca_seo_*'
        $normalizedCap = str_starts_with($cap, 'laca_seo_') ? $cap : 'laca_seo_' . $cap;

        return user_can($user, $normalizedCap);
    }

    /**
     * Get default capabilities by role name matching Rank Math.
     */
    public static function getDefaultCapsByRole(string $role): array
    {
        if ($role === 'administrator') {
            return self::getCapabilities();
        }

        if ($role === 'editor') {
            return [
                'laca_seo_site_analysis',
                'laca_seo_onpage_analysis',
                'laca_seo_onpage_general',
                'laca_seo_onpage_snippet',
                'laca_seo_onpage_social',
            ];
        }

        if ($role === 'author') {
            return [
                'laca_seo_onpage_analysis',
                'laca_seo_onpage_general',
                'laca_seo_onpage_snippet',
                'laca_seo_onpage_social',
            ];
        }

        return [];
    }

    /**
     * Import capabilities from Rank Math to Laca SEO for all WordPress roles.
     */
    public static function importFromRankMath(): void
    {
        $wpRoles = wp_roles();
        if (!$wpRoles) {
            return;
        }

        foreach ($wpRoles->roles as $roleSlug => $roleData) {
            $roleObj = get_role($roleSlug);
            if (!$roleObj) {
                continue;
            }

            foreach (self::CAPABILITIES as $lacaCap => $rmCap) {
                if (!empty($roleData['capabilities'][$rmCap])) {
                    $roleObj->add_cap($lacaCap);
                }
            }
        }
    }
}
