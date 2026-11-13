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
 * Hardening mặc định: luôn tắt XML-RPC và xoá wp_generator
 */
add_filter('xmlrpc_enabled', '__return_false');
remove_action('wp_head', 'wp_generator');

add_filter('wp_xmlrpc_server_class', function ($class) {
    if (!apply_filters('xmlrpc_enabled', true)) {
        if (!class_exists('Laca_XmlRpc_Disabled_Server', false)) {
            class Laca_XmlRpc_Disabled_Server extends \wp_xmlrpc_server
            {
                public function serve_request()
                {
                    $this->error(new \IXR_Error(405, 'XML-RPC services are disabled on this site.'));
                }
            }
        }
        return 'Laca_XmlRpc_Disabled_Server';
    }
    return $class;
});

/**
 * Add HTTP Security Headers
 * Chuyển sang dùng SecurityHeaders class có toggle — giữ nguyên hành vi mặc định.
 */
if (class_exists('\App\Settings\Security\SecurityHeaders')) {
    (new \App\Settings\Security\SecurityHeaders())->register();
}

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
