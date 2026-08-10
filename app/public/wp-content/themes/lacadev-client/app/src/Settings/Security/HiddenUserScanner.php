<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hidden User Scanner
 *
 * Phát hiện tài khoản admin/user ẩn — so sánh DB với kết quả get_users() / WP_User_Query.
 * Port từ Foxblock_Hidden_User_Scanner.
 */
class HiddenUserScanner
{
    private int    $blogId;
    private string $siteHost;

    public function __construct()
    {
        $this->blogId   = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $this->siteHost = (string) parse_url(home_url('/'), PHP_URL_HOST);
    }

    /** Chạy toàn bộ scan, trả về mảng kết quả */
    public function scan(): array
    {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        @set_time_limit(30);

        $keywords         = $this->getSuspiciousKeywords();
        $dbUsers          = $this->getDbUsers();
        $standardIds      = $this->getStandardQueryIds();
        $listIds          = $this->getUsersScreenIds();

        $records = [];
        foreach ($dbUsers as $u) {
            $records[] = $this->buildRecord($u, $standardIds, $listIds, $keywords);
        }

        $baselineDomains = $this->getBaselineDomains($records);
        $baselineReg     = $this->getRegistrationBaseline($records);

        foreach ($records as $i => $r) {
            $records[$i] = $this->applyHeuristics($r, $baselineDomains, $baselineReg);
        }

        usort($records, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        $hiddenAdmins     = array_values(array_filter($records, fn($r) => $r['is_admin']   && $r['hidden_from_list_query']));
        $hiddenSiteUsers  = array_values(array_filter($records, fn($r) => !$r['is_admin']  && $r['site_member'] && $r['hidden_from_list_query']));
        $visibleAdmins    = array_values(array_filter($records, fn($r) => $r['is_admin']   && !$r['hidden_from_list_query']));
        $suspiciousUsers  = array_values(array_slice(array_filter($records, fn($r) => $r['suspicious']), 0, 25));
        $hookFindings     = $this->inspectUserHooks();

        return [
            'summary' => [
                'db_total'              => count($dbUsers),
                'standard_query_total'  => count($standardIds),
                'list_query_total'      => count($listIds),
                'visible_admin_total'   => count($visibleAdmins),
                'hidden_admin_total'    => count($hiddenAdmins),
                'hidden_site_user_total'=> count($hiddenSiteUsers),
                'suspicious_user_total' => count(array_filter($records, fn($r) => $r['suspicious'])),
                'hook_total'            => count($hookFindings),
            ],
            'visible_admins'    => $visibleAdmins,
            'hidden_admins'     => $hiddenAdmins,
            'hidden_site_users' => $hiddenSiteUsers,
            'suspicious_users'  => $suspiciousUsers,
            'hook_findings'     => $hookFindings,
        ];
    }

    // ── DB Users ──────────────────────────────────────────────────────────────

    private function getDbUsers(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT ID, user_login, user_email, user_registered, display_name, user_status FROM {$wpdb->users} ORDER BY ID ASC",
            ARRAY_A
        );
        $rolesMap = $this->getRolesMap();
        $users    = [];

        foreach ((array) $rows as $row) {
            $id    = (int) ($row['ID'] ?? 0);
            $roles = $rolesMap[$id] ?? [];
            $users[] = [
                'id'           => $id,
                'login'        => (string) ($row['user_login']   ?? ''),
                'email'        => (string) ($row['user_email']   ?? ''),
                'registered'   => (string) ($row['user_registered'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'status'       => (int)    ($row['user_status']  ?? 0),
                'roles'        => $roles,
                'site_member'  => !empty($roles),
                'is_admin'     => in_array('administrator', $roles, true)
                                  || (function_exists('is_super_admin') && is_super_admin($id)),
            ];
        }

        return $users;
    }

    private function getRolesMap(): array
    {
        global $wpdb;
        $capKey = $wpdb->get_blog_prefix($this->blogId) . 'capabilities';
        $rows   = $wpdb->get_results(
            $wpdb->prepare("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $capKey),
            ARRAY_A
        );

        $map = [];
        foreach ((array) $rows as $row) {
            $caps  = maybe_unserialize($row['meta_value'] ?? '');
            $caps  = is_array($caps) ? $caps : [];
            $roles = [];
            foreach ($caps as $role => $on) {
                if ($on) $roles[] = (string) $role;
            }
            $map[(int) $row['user_id']] = $roles;
        }
        return $map;
    }

    // ── Query IDs ────────────────────────────────────────────────────────────

    private function getStandardQueryIds(): array
    {
        $users = get_users(['blog_id' => $this->blogId, 'number' => -1, 'fields' => 'all']);
        return $this->extractIds($users);
    }

    private function getUsersScreenIds(): array
    {
        $base = ['blog_id' => $this->blogId, 'number' => -1, 'fields' => 'all'];
        $prev = $GLOBALS['pagenow'] ?? null;
        $GLOBALS['pagenow'] = 'users.php';
        $args   = apply_filters('users_list_table_query_args', $base);
        $query  = new \WP_User_Query($args);
        $GLOBALS['pagenow'] = $prev;
        return $this->extractIds($query->get_results());
    }

    private function extractIds(array $users): array
    {
        $ids = [];
        foreach ($users as $u) {
            if ($u instanceof \WP_User) $ids[(int) $u->ID] = true;
        }
        return $ids;
    }

    // ── Record Builder ───────────────────────────────────────────────────────

    private function buildRecord(array $u, array $stdIds, array $listIds, array $keywords): array
    {
        $loginHits  = $this->findKeywordHits($u['login'], $keywords);
        $emailLocal = strstr($u['email'], '@', true) ?: $u['email'];
        $emailHits  = $this->findKeywordHits($emailLocal, $keywords);
        $emailDom   = str_contains($u['email'], '@') ? substr(strrchr($u['email'], '@'), 1) : '';
        $regTs      = $u['registered'] ? strtotime($u['registered']) : 0;

        $reasons = [];
        if ($loginHits) $reasons[] = 'Login có từ khoá nghi ngờ: ' . implode(', ', $loginHits);
        if ($emailHits) $reasons[] = 'Email có từ khoá nghi ngờ: ' . implode(', ', $emailHits);

        $inStd  = isset($stdIds[$u['id']]);
        $inList = isset($listIds[$u['id']]);

        if ($u['site_member'] && !$inStd)  $reasons[] = 'Tồn tại trong DB nhưng ẩn khỏi get_users() chuẩn.';
        if ($u['site_member'] && !$inList) $reasons[] = 'Tồn tại trong DB nhưng ẩn khỏi màn hình Người dùng wp-admin.';
        if ($u['is_admin']   && !$inList) $reasons[] = 'Admin ẩn khỏi màn hình Người dùng wp-admin.';

        if (!$u['email'] || !is_email($u['email'])) $reasons[] = 'Địa chỉ email trống hoặc không hợp lệ.';
        if ($regTs <= 0) $reasons[] = 'Ngày đăng ký trống hoặc không hợp lệ.';

        return [
            'id'                    => $u['id'],
            'login'                 => $u['login'],
            'email'                 => $u['email'],
            'email_domain'          => $emailDom,
            'display_name'          => $u['display_name'],
            'registered'            => $u['registered'],
            'registered_timestamp'  => (int) $regTs,
            'roles'                 => array_values($u['roles']),
            'roles_label'           => $u['roles'] ? implode(', ', $u['roles']) : 'Không có vai trò',
            'site_member'           => $u['site_member'],
            'is_admin'              => $u['is_admin'],
            'in_standard_query'     => $inStd,
            'in_list_query'         => $inList,
            'hidden_from_list_query'=> !$inList,
            'reasons'               => $reasons,
            'risk_score'            => count($reasons),
            'suspicious'            => !empty($reasons),
        ];
    }

    private function applyHeuristics(array $r, array $baseDomains, array $baseReg): array
    {
        $reasons = $r['reasons'];
        $now     = current_time('timestamp', true);

        if ($r['is_admin'] && $r['email_domain'] && !isset($baseDomains[$r['email_domain']])) {
            $reasons[] = 'Email domain admin có vẻ bất thường: ' . $r['email_domain'];
        }
        if ($r['registered_timestamp'] > 0 && $r['registered_timestamp'] > ($now + HOUR_IN_SECONDS)) {
            $reasons[] = 'Ngày đăng ký trong tương lai.';
        }
        if ($r['is_admin'] && $r['hidden_from_list_query'] && $r['registered_timestamp'] > 0) {
            if (($now - $r['registered_timestamp']) < (90 * DAY_IN_SECONDS)) {
                $reasons[] = 'Tài khoản admin ẩn được đăng ký gần đây.';
            }
        }

        $r['reasons']    = array_values(array_unique($reasons));
        $r['risk_score'] = count($r['reasons']);
        $r['suspicious'] = !empty($r['reasons']);
        return $r;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getSuspiciousKeywords(): array
    {
        return ['admin', 'root', 'superuser', 'test', 'backup', 'support', 'shell',
                'wp', 'wordpress', 'webmaster', 'system', 'ghost', 'backdoor'];
    }

    private function findKeywordHits(string $subject, array $keywords): array
    {
        $subject = strtolower($subject);
        $hits    = [];
        foreach ($keywords as $kw) {
            if (str_contains($subject, strtolower($kw))) $hits[] = $kw;
        }
        return $hits;
    }

    private function getBaselineDomains(array $records): array
    {
        $domains = [];
        $host    = strtolower($this->siteHost);
        $parts   = explode('.', $host);
        if (count($parts) >= 2) {
            $domains[implode('.', array_slice($parts, -2))] = true;
        }
        $adminEmail = (string) get_option('admin_email', '');
        if (str_contains($adminEmail, '@')) {
            $domains[substr(strrchr($adminEmail, '@'), 1)] = true;
        }
        foreach ($records as $r) {
            if ($r['is_admin'] && $r['in_list_query'] && $r['email_domain']) {
                $domains[$r['email_domain']] = true;
            }
        }
        return $domains;
    }

    private function getRegistrationBaseline(array $records): array
    {
        $timestamps = array_filter(array_column(
            array_filter($records, fn($r) => $r['is_admin'] && $r['in_list_query'] && $r['registered_timestamp'] > 0),
            'registered_timestamp'
        ));
        return $timestamps ? ['oldest' => min($timestamps), 'newest' => max($timestamps)] : [];
    }

    private function inspectUserHooks(): array
    {
        $hooks   = ['pre_user_query', 'users_list_table_query_args', 'user_has_cap'];
        $findings = [];
        foreach ($hooks as $hook) {
            global $wp_filter;
            if (empty($wp_filter[$hook])) continue;
            foreach ($wp_filter[$hook]->callbacks ?? [] as $priority => $callbacks) {
                foreach ($callbacks as $cb) {
                    $fn   = $cb['function'];
                    $label = is_string($fn) ? $fn : (is_array($fn) ? (is_object($fn[0]) ? get_class($fn[0]) . '::' . $fn[1] : $fn[0] . '::' . $fn[1]) : '{closure}');
                    $findings[] = ['hook' => $hook, 'priority' => $priority, 'callback' => $label];
                }
            }
        }
        return $findings;
    }
}
