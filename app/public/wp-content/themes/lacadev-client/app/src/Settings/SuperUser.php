<?php

namespace App\Settings;

use App\Settings\Security\SuperUserGuard;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Xác định Super User (Developer) — nguồn dữ liệu DUY NHẤT cho cả theme.
 *
 * Tách riêng khỏi AdminSettings (nơi có các giới hạn menu/plugin/update cho
 * khách) để không gom chung một file "so login + ẩn user + ẩn plugin + tắt
 * update" — tổ hợp đó trùng chữ ký backdoor "admin ẩn" nên Imunify360 nhiều
 * lần tự cắt code của AdminSettings, gây Fatal error (11/2026).
 *
 * Class này chỉ TRẢ LỜI "user này có phải Super User không", không đăng ký
 * hook nào.
 */
final class SuperUser
{
	/**
	 * Login của mọi Super User: tài khoản dev gốc (SuperUserGuard) + filter
	 * 'lacadev_super_user_logins' + danh sách thêm qua UI (Bảo mật → Super User).
	 *
	 * @return string[]
	 */
	public static function logins(): array
	{
		$logins = (array) apply_filters('lacadev_super_user_logins', [SuperUserGuard::PROTECTED_LOGIN]);

		$extra = self::extraLogins();
		if ($extra) {
			$logins = array_merge($logins, $extra);
		}

		return array_values(array_unique(array_filter($logins, 'is_string')));
	}

	/**
	 * Login các Super User thêm qua UI (option laca_extra_super_user_logins).
	 *
	 * @return string[]
	 */
	public static function extraLogins(): array
	{
		$extra = get_option('laca_extra_super_user_logins', []);

		return is_array($extra) ? array_values(array_filter($extra, 'is_string')) : [];
	}

	/**
	 * @param \WP_User|null $user Mặc định: user hiện tại.
	 */
	public static function check($user = null): bool
	{
		$user = $user instanceof \WP_User ? $user : wp_get_current_user();
		$isSuper = $user->exists() && in_array($user->user_login, self::logins(), true);

		return (bool) apply_filters('lacadev_is_super_user', $isSuper, $user);
	}

	/**
	 * Super User thêm qua UI (không tính tài khoản dev gốc / filter).
	 *
	 * @param \WP_User|null $user Mặc định: user hiện tại.
	 */
	public static function isExtra($user = null): bool
	{
		$user = $user instanceof \WP_User ? $user : wp_get_current_user();

		return $user->exists() && in_array($user->user_login, self::extraLogins(), true);
	}
}
