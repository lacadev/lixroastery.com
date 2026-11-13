<?php

/**
 * Responsive Images Helper Functions
 * 
 * Functions to handle responsive images with multiple sizes and formats
 */

/**
 * Get fallback responsive image markup
 * 
 * @param string $size Image size
 * @param array $attr Additional attributes
 * @return string Image HTML markup
 */
function getFallbackResponsiveImage($size = 'mobile', $attr = []) {

    $img_url = function_exists('lacaResourceUrl') ? lacaResourceUrl('images/default-img.webp') : get_template_directory_uri() . '/../resources/images/default-img.webp';
    $class = isset($attr['class']) ? $attr['class'] : '';
    $alt = isset($attr['alt']) ? $attr['alt'] : 'Default Image';

    $sizes = [
        'mobile' => ['width' => 480, 'height' => 320],
        'tablet' => ['width' => 768, 'height' => 512],
        'desktop' => ['width' => 1200, 'height' => 800],
        'large' => ['width' => 1920, 'height' => 1080],
    ];

    $dimensions = isset($sizes[$size]) ? $sizes[$size] : $sizes['mobile'];

    return sprintf(
        '<img src="%s" class="%s" alt="%s" width="%d" height="%d" loading="lazy" decoding="async">',
        esc_url($img_url),
        esc_attr($class),
        esc_attr($alt),
        $dimensions['width'],
        $dimensions['height']
    );
}

/**
 * Get responsive image markup with modern formats (AVIF, WebP)
 * 
 * @param int $image_id Attachment ID
 * @param string $size Image size (mobile, tablet, desktop, large)
 * @param array $attr Additional attributes
 * @return string Picture HTML markup
 */
function getResponsiveImage($image_id, $size = 'mobile', $attr = []) {
    if (!$image_id) {
        // Return default image if set in Theme Options
        $default_id = function_exists('getDefaultImageId') ? getDefaultImageId() : getOption('default_image');
        if ($default_id) {
            return wp_get_attachment_image($default_id, $size, false, $attr);
        }
        return getFallbackResponsiveImage($size, $attr);
    }

    // Default image sizes mapping
    $sizes = [
        'mobile' => [
            'width' => 480,
            'height' => 320,
            'media' => '(max-width: 480px)'
        ],
        'tablet' => [
            'width' => 768,
            'height' => 512,
            'media' => '(max-width: 768px)'
        ],
        'desktop' => [
            'width' => 1200,
            'height' => 800,
            'media' => '(max-width: 1200px)'
        ],
        'large' => [
            'width' => 1920,
            'height' => 1080,
            'media' => '(min-width: 1201px)'
        ]
    ];

    // Get image metadata
    $metadata = wp_get_attachment_metadata($image_id);
    if (!$metadata) {
        return wp_get_attachment_image($image_id, $size, false, $attr);
    }

    // Extract attributes
    $class = isset($attr['class']) ? $attr['class'] : '';
    $alt = isset($attr['alt']) ? $attr['alt'] : get_post_meta($image_id, '_wp_attachment_image_alt', true);
    if (empty($alt)) {
        $alt = get_the_title($image_id);
    }
    $loading = isset($attr['loading']) ? $attr['loading'] : 'lazy';
    $decoding = isset($attr['decoding']) ? $attr['decoding'] : 'async';

    // Get original image URL
    $img_url = wp_get_attachment_url($image_id);
    
    // Fallback if image URL not found
    if (!$img_url) {
        return getFallbackResponsiveImage($size, $attr);
    }

    // Build picture element
    $html = '<picture class="responsive-image-wrap ' . esc_attr($class) . '">';

    // Add AVIF sources if available
    foreach ($sizes as $size_name => $size_info) {
        $img_src = wp_get_attachment_image_src($image_id, $size_name);
        if ($img_src) {
            $avif_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $img_src[0]);
            // You can add file_exists check if you generate AVIF files
            // $html .= sprintf(
            //     '<source srcset="%s" type="image/avif" media="%s">',
            //     esc_url($avif_url),
            //     $size_info['media']
            // );
        }
    }

    // Add WebP sources if available
    foreach ($sizes as $size_name => $size_info) {
        $img_src = wp_get_attachment_image_src($image_id, $size_name);
        if ($img_src) {
            $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $img_src[0]);
            // You can add file_exists check if you generate WebP files
            // $html .= sprintf(
            //     '<source srcset="%s" type="image/webp" media="%s">',
            //     esc_url($webp_url),
            //     $size_info['media']
            // );
        }
    }

    // Add standard sources for different viewport sizes
    foreach ($sizes as $size_name => $size_info) {
        $img_src = wp_get_attachment_image_src($image_id, $size_name);
        if ($img_src) {
            $html .= sprintf(
                '<source srcset="%s" media="%s">',
                esc_url($img_src[0]),
                $size_info['media']
            );
        }
    }

    // Default fallback img tag
    $default_src = wp_get_attachment_image_src($image_id, $size);
    $dimensions = isset($sizes[$size]) ? $sizes[$size] : $sizes['mobile'];
    
    $html .= sprintf(
        '<img src="%s" class="%s" alt="%s" width="%d" height="%d" loading="%s" decoding="%s">',
        esc_url($default_src ? $default_src[0] : $img_url),
        esc_attr($class),
        esc_attr($alt),
        $default_src ? $default_src[1] : $dimensions['width'],
        $default_src ? $default_src[2] : $dimensions['height'],
        esc_attr($loading),
        esc_attr($decoding)
    );

    $html .= '</picture>';

    return $html;
}

/**
 * Helper function to output responsive image
 */
function theResponsiveImage($image_id, $size = 'mobile', $attr = []) {
    echo getResponsiveImage($image_id, $size, $attr);
}

// Hub (lacadev) còn gọi các hàm dưới ở template/block — giữ markup <img> gốc của wp_get_attachment_image.
if (!function_exists('getResponsivePostThumbnail')) {
    function getResponsivePostThumbnail($post_id = null, $size = 'mobile', $attr = []) {
        $post_id = $post_id ?: get_the_ID();
        $image_id = get_post_thumbnail_id($post_id);

        if (!$image_id) {
            $default_id = function_exists('getDefaultImageId') ? getDefaultImageId() : getOption('default_image');
            if ($default_id) {
                return wp_get_attachment_image($default_id, $size, false, $attr);
            }
            return getFallbackResponsiveImage($size, $attr);
        }

        return wp_get_attachment_image($image_id, $size, false, $attr);
    }
}

if (!function_exists('theResponsivePostThumbnail')) {
    function theResponsivePostThumbnail($size = 'mobile', $attr = []) {
        echo getResponsivePostThumbnail(null, $size, $attr);
    }
}

if (!function_exists('getResponsivePostMeta')) {
    function getResponsivePostMeta($meta_key, $post_id = null, $size = 'mobile', $attr = []) {
        $post_id = $post_id ?: get_the_ID();
        $image_id = carbon_get_post_meta($post_id, $meta_key);

        if (!$image_id) {
            return getFallbackResponsiveImage($size, $attr);
        }

        return wp_get_attachment_image($image_id, $size, false, $attr);
    }
}

if (!function_exists('theResponsivePostMeta')) {
    function theResponsivePostMeta($meta_key, $size = 'mobile', $attr = []) {
        echo getResponsivePostMeta($meta_key, null, $size, $attr);
    }
}

if (!function_exists('getResponsiveOption')) {
    function getResponsiveOption($option_key, $size = 'mobile', $attr = []) {
        $image_id = function_exists('laca_theme_option') ? laca_theme_option($option_key) : (function_exists('carbon_get_theme_option') ? carbon_get_theme_option($option_key) : 0);

        if (!$image_id) {
            return getFallbackResponsiveImage($size, $attr);
        }

        return wp_get_attachment_image($image_id, $size, false, $attr);
    }
}

if (!function_exists('theResponsiveOption')) {
    function theResponsiveOption($option_key, $size = 'mobile', $attr = []) {
        echo getResponsiveOption($option_key, $size, $attr);
    }
}
