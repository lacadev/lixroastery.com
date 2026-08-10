<?php
/**
 * Login helpers.
 *
 * @package WPEmergeTheme
 */

/**
 * Changes the URL of the logo on the login screen.
 *
 * @return string Link to the Homepage.
 */
function app_filter_login_headerurl() {
	return home_url( '/' );
}

/**
 * Changes the text of the logo on the login Screen.
 *
 * @return string Site Title.
 */
function app_filter_login_headertext() {
	return get_bloginfo( 'name' );
}

/**
 * Hiển thị thông báo lỗi khi đăng nhập bằng Google nhưng tài khoản không có quyền admin.
 *
 * @param string $message Existing login message HTML.
 * @return string
 */
function app_login_google_admin_message( $message ) {
	if ( empty( $_GET['google_admin_error'] ) ) {
		return $message;
	}

	$error_code = sanitize_text_field( wp_unslash( $_GET['google_admin_error'] ) );
	$locale     = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	if ( 'no_admin' === $error_code ) {
		// Chọn thông báo theo ngôn ngữ hiện tại
		if ( strpos( $locale, 'vi' ) === 0 ) {
			$text = 'Tài khoản Google này chưa được liên kết với tài khoản quản trị. Vui lòng đăng nhập bằng tài khoản admin hoặc liên hệ quản trị viên.';
		} else {
			$text = 'Google account is not linked to an administrator user. Please login with your admin account or contact the site owner.';
		}

		$message .= '<div id="login_error" class="notice notice-error"><p>';
		$message .= esc_html( $text );
		$message .= '</p></div>';
	}

	return $message;
}
