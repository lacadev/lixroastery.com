<?php

namespace App\Features\DiscordHub\Monitors;

use App\Features\DiscordHub\Settings;
use App\Features\DiscordHub\Context;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Notifier;

/**
 * Vòng đời tài khoản: tạo, xoá, đổi quyền.
 *
 * Đổi quyền KHÔNG chỉ bắt `set_user_role` như spec: leo thang đặc quyền còn đi qua
 * WP_User::add_role(), add_cap(), hoặc malware ghi thẳng update_user_meta('wp_capabilities').
 * Vì vậy agent theo dõi chính meta `{prefix}capabilities`: chụp giá trị trước lần sửa đầu
 * tiên trong request, rồi ở shutdown so sánh với giá trị cuối → 1 sự kiện gọn
 * ("editor → administrator") thay vì chuỗi remove_role/add_role/set_role.
 */
final class UserMonitor
{
    /** Capability nguy hiểm nếu được cấp trực tiếp (không qua role). */
    const DANGEROUS_CAPS = [
        'manage_options',
        'edit_users',
        'create_users',
        'promote_users',
        'delete_users',
        'install_plugins',
        'activate_plugins',
        'edit_plugins',
        'edit_themes',
        'unfiltered_html',
        'unfiltered_upload',
        'update_core',
        'manage_network',
    ];

    /** @var array<string,array{user_id:int,key:string,before:array}> */
    private $snapshots = [];

    /** @var array<int,bool|string> User tạo/xoá trong request này — không báo "đổi quyền" trùng. */
    private $skip = [];

    public function register(): void
    {
        add_action('user_register', [$this, 'onRegister'], 10, 1);
        add_action('delete_user', [$this, 'onDelete'], 10, 3);
        add_action('wpmu_delete_user', [$this, 'onDelete'], 10, 1);
        add_action('granted_super_admin', [$this, 'onGrantedSuperAdmin'], 10, 1);

        add_action('add_user_meta', [$this, 'beforeAddMeta'], 10, 2);
        add_action('update_user_meta', [$this, 'beforeUpdateMeta'], 10, 3);
        add_action('delete_user_meta', [$this, 'beforeUpdateMeta'], 10, 3);
        add_action('shutdown', [$this, 'flushRoleChanges'], 5);
    }

    /**
     * @param int $userId
     */
    public function onRegister($userId): void
    {
        $user = get_userdata((int) $userId);
        if (!$user) {
            return;
        }
        $this->skip[$user->ID] = true;

        $isAdmin = $this->isPrivileged($user->roles, array_keys(array_filter((array) $user->caps)));
        $embed   = Embed::make(
            $isAdmin ? '🚨 Tài khoản QUẢN TRỊ mới được tạo' : '👤 Tài khoản mới',
            $isAdmin ? Embed::COLOR_DANGER : Embed::COLOR_INFO
        )
            ->codeField('Username', $user->user_login)
            ->codeField('Email', $user->user_email)
            ->field('Vai trò', $this->roleNames($user->roles))
            ->field('Thực hiện bởi', Context::actor())
            ->field('Nguồn', Context::origin())
            ->codeField('IP', Context::clientIp())
            ->field('Quản trị', Embed::link('Xem user', admin_url('user-edit.php?user_id=' . (int) $user->ID)), true, true);

        Notifier::send($isAdmin ? Settings::CHANNEL_SECURITY : Settings::CHANNEL_AUDIT, 'user.register', $embed);
    }

    /**
     * @param int           $userId
     * @param int|null      $reassign
     * @param \WP_User|null $user (WP 5.5+)
     */
    public function onDelete($userId, $reassign = null, $user = null): void
    {
        $user = $user instanceof \WP_User ? $user : get_userdata((int) $userId);
        if (!$user || (isset($this->skip[$user->ID]) && 'deleted' === $this->skip[$user->ID])) {
            return;
        }
        // Đánh dấu để không báo trùng (multisite gọi cả delete_user lẫn wpmu_delete_user).
        $this->skip[$user->ID] = 'deleted';

        $wasAdmin = $this->isPrivileged($user->roles, []);
        $embed    = Embed::make(
            $wasAdmin ? '🗑️ Tài khoản QUẢN TRỊ bị xoá' : '🗑️ Tài khoản bị xoá',
            $wasAdmin ? Embed::COLOR_DANGER : Embed::COLOR_WARNING
        )
            ->codeField('Username', $user->user_login)
            ->codeField('Email', $user->user_email)
            ->field('Vai trò', $this->roleNames($user->roles))
            ->field('Thực hiện bởi', Context::actor())
            ->field('Nguồn', Context::origin());

        if ($reassign) {
            $target = get_userdata((int) $reassign);
            $embed->field('Chuyển bài cho', $target ? $target->user_login : '#' . (int) $reassign);
        }

        Notifier::send($wasAdmin ? Settings::CHANNEL_SECURITY : Settings::CHANNEL_AUDIT, 'user.delete', $embed);
    }

    /**
     * @param int $userId
     */
    public function onGrantedSuperAdmin($userId): void
    {
        $user = get_userdata((int) $userId);
        if (!$user) {
            return;
        }
        $embed = Embed::make('🚨 Cấp quyền SUPER ADMIN (Multisite)', Embed::COLOR_DANGER)
            ->codeField('Username', $user->user_login)
            ->field('Thực hiện bởi', Context::actor())
            ->field('Nguồn', Context::origin())
            ->codeField('IP', Context::clientIp());
        Notifier::send(Settings::CHANNEL_SECURITY, 'user.super_admin', $embed);
    }

    // ------------------------------------------------------------------
    // Theo dõi meta capabilities
    // ------------------------------------------------------------------

    /**
     * Hook `add_user_meta` (trước khi thêm): ($object_id, $meta_key, $meta_value).
     *
     * @param int    $userId
     * @param string $metaKey
     */
    public function beforeAddMeta($userId, $metaKey): void
    {
        $this->snapshot((int) $userId, (string) $metaKey);
    }

    /**
     * Hook `update_user_meta` / `delete_user_meta` (trước khi sửa): ($meta_id(s), $object_id, $meta_key, ...).
     *
     * @param int|int[] $metaId
     * @param int       $userId
     * @param string    $metaKey
     */
    public function beforeUpdateMeta($metaId, $userId, $metaKey): void
    {
        $this->snapshot((int) $userId, (string) $metaKey);
    }

    private function snapshot(int $userId, string $metaKey): void
    {
        global $wpdb;
        if (!$userId || !preg_match('/^' . preg_quote($wpdb->base_prefix, '/') . '(\d+_)?capabilities$/', $metaKey)) {
            return;
        }
        $id = $userId . '|' . $metaKey;
        if (isset($this->snapshots[$id])) {
            return;
        }
        $before                 = get_user_meta($userId, $metaKey, true);
        $this->snapshots[$id] = [
            'user_id' => $userId,
            'key'     => $metaKey,
            'before'  => is_array($before) ? $before : [],
        ];
    }

    public function flushRoleChanges(): void
    {
        foreach ($this->snapshots as $snap) {
            if (isset($this->skip[$snap['user_id']])) {
                continue;
            }

            wp_cache_delete($snap['user_id'], 'user_meta');
            $after = get_user_meta($snap['user_id'], $snap['key'], true);
            $after = is_array($after) ? $after : [];

            $beforeKeys = array_keys(array_filter($snap['before']));
            $afterKeys  = array_keys(array_filter($after));
            $added      = array_values(array_diff($afterKeys, $beforeKeys));
            $removed    = array_values(array_diff($beforeKeys, $afterKeys));
            if (!$added && !$removed) {
                continue;
            }

            $roles      = wp_roles();
            $isRole     = static function ($key) use ($roles) {
                return $roles->is_role($key);
            };
            $addedRoles = array_values(array_filter($added, $isRole));
            $addedCaps  = array_values(array_diff($added, $addedRoles));
            $escalation = $this->isPrivileged($addedRoles, $addedCaps);

            $user  = get_userdata($snap['user_id']);
            $embed = Embed::make(
                $escalation ? '🚨 Leo thang đặc quyền: user được cấp quyền quản trị' : '🔑 Thay đổi vai trò người dùng',
                $escalation ? Embed::COLOR_DANGER : Embed::COLOR_WARNING
            )
                ->codeField('Username', $user ? $user->user_login : '#' . $snap['user_id'])
                ->field('Trước', $this->roleNames(array_values(array_filter($beforeKeys, $isRole))))
                ->field('Sau', $this->roleNames(array_values(array_filter($afterKeys, $isRole))));

            if ($addedCaps) {
                $embed->field('Capability cấp trực tiếp', Embed::code(implode(', ', $addedCaps)), false, true);
            }
            if (is_multisite() && preg_match('/(\d+)_capabilities$/', $snap['key'], $m)) {
                $embed->field('Site ID', $m[1]);
            }

            $embed->field('Thực hiện bởi', Context::actor())
                ->field('Nguồn', Context::origin())
                ->codeField('IP', Context::clientIp());

            Notifier::send($escalation ? Settings::CHANNEL_SECURITY : Settings::CHANNEL_AUDIT, 'user.role_change', $embed);
        }
        $this->snapshots = [];
    }

    /**
     * @param string[] $roles
     * @param string[] $caps
     */
    private function isPrivileged(array $roles, array $caps): bool
    {
        if (in_array('administrator', $roles, true)) {
            return true;
        }
        return (bool) array_intersect($caps, self::DANGEROUS_CAPS);
    }

    /**
     * @param string[] $roles
     */
    private function roleNames(array $roles): string
    {
        if (!$roles) {
            return '(không có)';
        }
        $names = wp_roles()->get_names();
        return implode(
            ', ',
            array_map(
                static function ($role) use ($names) {
                    return isset($names[$role]) ? translate_user_role($names[$role]) . " ({$role})" : $role;
                },
                array_values($roles)
            )
        );
    }
}
