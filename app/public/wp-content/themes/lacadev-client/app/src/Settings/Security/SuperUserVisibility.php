<?php

namespace App\Settings\Security;

use App\Settings\SuperUser;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ẩn tài khoản Super User khỏi danh sách Users đối với người không phải
 * Super User. Dùng query var chuẩn login__not_in của WP_User_Query (không
 * chèn SQL ở pre_user_query).
 *
 * Tách khỏi AdminSettings — xem lý do ở App\Settings\SuperUser.
 */
class SuperUserVisibility
{
    public function register(): void
    {
        add_action('pre_get_users', [$this, 'excludeSuperUsers']);
    }

    /**
     * @param \WP_User_Query $query
     */
    public function excludeSuperUsers($query): void
    {
        if (SuperUser::check()) {
            return;
        }

        $excluded = (array) $query->get('login__not_in');
        $query->set('login__not_in', array_values(array_unique(array_merge($excluded, SuperUser::logins()))));
    }
}
