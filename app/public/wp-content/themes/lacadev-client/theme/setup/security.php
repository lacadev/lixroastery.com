<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Headers & Hardening
 * 
 * @package LacaDev
 */

/**
 * Add HTTP Security Headers
 */
add_action('send_headers', function() {
    // Generate Nonce
    $nonce = base64_encode(random_bytes(16));
    if (!defined('LACA_CSP_NONCE')) {
        define('LACA_CSP_NONCE', $nonce);
    }

    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME-sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // XSS Protection (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');
    
    if ( ! is_admin() ) {
        // Content Security Policy
        $csp  = "default-src 'self'; ";
        $csp .= "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://www.googletagmanager.com https://www.google-analytics.com https://images.dmca.com https://apis.google.com blob:; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
        $csp .= "connect-src 'self' https://www.google.com https://www.gstatic.com https://www.youtube.com https://www.google-analytics.com https://stats.g.doubleclick.net https://apis.google.com ws: wss:; ";
        $csp .= "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://docs.google.com https://www.google.com https://www.gstatic.com; ";
        $csp .= "media-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; ";
        $csp .= "img-src 'self' data: https: http:; ";
        $csp .= "worker-src 'self' blob:; ";
        $csp .= "frame-ancestors 'self';";

        header( "Content-Security-Policy: {$csp}" );

        // Permissions Policy
        header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
    }
});

/**
 * Inject Nonce into script tags
 */
add_filter('script_loader_tag', function($tag, $handle) {
    if ( is_admin() ) {
        return $tag;
    }
    if (defined('LACA_CSP_NONCE')) {
        $nonce = LACA_CSP_NONCE;
        if (strpos($tag, "nonce=") === false) {
            $tag = str_replace('<script ', '<script nonce="' . $nonce . '" ', $tag);
        }
    }
    return $tag;
}, 10, 2);

/**
 * Inject Nonce into INLINE script tags (wp_add_inline_script output)
 * Required for CSP compliance with wp_add_inline_script()
 */
add_filter('wp_inline_script_attributes', function($attributes) {
    if ( is_admin() ) {
        return $attributes;
    }
    if (defined('LACA_CSP_NONCE') && empty($attributes['nonce'])) {
        $attributes['nonce'] = LACA_CSP_NONCE;
    }
    return $attributes;
}, 10, 1);

/**
 * Remove WordPress version from head
 */
remove_action('wp_head', 'wp_generator');

/**
 * Disable XML-RPC if not needed
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Remove WP version from RSS feeds
 */
add_filter('the_generator', '__return_empty_string');

/**
 * Disable file editing in admin
 */
if (get_option('_hide_theme_editor') === 'yes' && !defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/**
 * Limit login attempts (basic implementation)
 */
add_filter('authenticate', function($user, $username, $password) {
    if (empty($username) || empty($password)) {
        return $user;
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5($ip);
    $attempts = get_transient($transient_key);
    
    if ($attempts && $attempts >= 5) {
        return new WP_Error(
            'too_many_attempts',
            __('Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.', 'laca')
        );
    }
    
    // If login fails, increment counter
    if (is_wp_error($user)) {
        $attempts = $attempts ? $attempts + 1 : 1;
        set_transient($transient_key, $attempts, 15 * MINUTE_IN_SECONDS);
    }
    
    return $user;
}, 30, 3);

/**
 * Clear login attempts on successful login
 */
add_action('wp_login', function($username) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5($ip);
    delete_transient($transient_key);
});

/**
 * Sanitize Uploaded Filenames
 * Prevent special characters and ensure safe filenames
 */
add_filter('sanitize_file_name', function($filename) {
    $info = pathinfo($filename);
    $ext  = empty($info['extension']) ? '' : '.' . $info['extension'];
    $name = basename($filename, $ext);

    // Remove accents and special chars
    $name = remove_accents($name);
    
    // Ensure only safe characters remain
    $name = preg_replace('/[^A-Za-z0-9-]/', '-', $name);
    
    // Prevent multiple dashes
    $name = preg_replace('/-+/', '-', $name);
    
    // Trim dashes from beginning and end
    $name = trim($name, '-');

    // Add short unique hash (4 chars) to prevent duplication but keep it short
    // Example: image-name-a1b2.jpg
    $short_hash = substr(md5(uniqid()), 0, 4);
    
    return strtolower($name . '-' . $short_hash . $ext);
});
