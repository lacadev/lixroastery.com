<?php

namespace App\Settings\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Super User Guard
 *
 * Bảo vệ tài khoản admin mặc định (user_login = 'lacadev') — không Super
 * User/Administrator nào khác có thể xoá hoặc đổi quyền (role) của tài khoản
 * này. Login được hardcode ở đây theo đúng yêu cầu ("trong code"), không có
 * UI để tắt/đổi.
 *
 * Chặn ở tầng capability (map_meta_cap) nên áp dụng đồng nhất cho mọi lối
 * vào: màn hình Users trong wp-admin (xoá đơn, bulk action, đổi role), REST
 * API (wp/v2/users), lẫn trang "Super User" tự tạo ở SecurityManager — vì
 * tất cả đều phải đi qua current_user_can('delete_user'|'promote_user', $id)
 * trước khi thực hiện thay đổi.
 */
class SuperUserGuard
{
    public const PROTECTED_LOGIN = 'lacadev';

    public function init(): void
    {
        add_filter('map_meta_cap', [$this, 'blockCapsForProtectedUser'], 10, 4);
        add_filter('user_row_actions', [$this, 'removeDeleteRowAction'], 10, 2);
    }

    /**
     * @param string[]    $caps
     * @param string      $cap
     * @param int         $requestingUserId
     * @param array<int>  $args
     *
     * @return string[]
     */
    public function blockCapsForProtectedUser($caps, $cap, $requestingUserId, $args)
    {
        if (!in_array($cap, ['delete_user', 'delete_users', 'remove_user', 'promote_user'], true)) {
            return $caps;
        }

        $targetUserId = (int) ($args[0] ?? 0);
        if ($targetUserId && self::isProtected($targetUserId)) {
            return ['do_not_allow'];
        }

        return $caps;
    }

    /**
     * @param string[] $actions
     *
     * @return string[]
     */
    public function removeDeleteRowAction($actions, \WP_User $user)
    {
        if (self::isProtected($user->ID)) {
            unset($actions['delete'], $actions['remove']);
        }

        return $actions;
    }

    public static function isProtected(int $userId): bool
    {
        $user = get_userdata($userId);

        return $user instanceof \WP_User && $user->user_login === self::PROTECTED_LOGIN;
    }
}
