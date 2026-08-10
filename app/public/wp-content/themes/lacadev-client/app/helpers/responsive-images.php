<?php
/**
 * Responsive Image Helper Functions
 * 
 * Wrapper functions for wp_get_attachment_image() to provide
 * automatic responsive images with srcset and sizes attributes.
 */

// =============================================================================
// RESPONSIVE IMAGE FUNCTIONS (NEW - RECOMMENDED)
// =============================================================================

/**
 * Get fallback image HTML
 * 
 * @param string $size Image size name
 * @param array $attr Additional attributes
 * @return string HTML img tag
 */
function getFallbackResponsiveImage($size = 'mobile', $attr = []) {

    $img_url = get_template_directory_uri() . '/../resources/images/default-img.webp';
    $class = isset($attr['class']) ? $attr['class'] : '';
    $alt = isset($attr['alt']) ? $attr['alt'] : 'Default Image';

    // Map size to probable dimensions (optional, for layout stability)
    $style = '';
    // We can add logic here if needed, but for now allow CSS to handle it
    
    // Construct attributes string
    $attr_str = '';
    foreach ($attr as $name => $value) {
        if ($name === 'class' || $name === 'alt') continue;
        $attr_str .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
    }

    return sprintf(
        '<img src="%s" alt="%s" class="attachment-%s size-%s %s"%s loading="lazy">',
        esc_url($img_url),
        esc_attr($alt),
        esc_attr($size),
        esc_attr($size),
        esc_attr($class),
        $attr_str
    );
}

/**
 * Echo responsive post thumbnail
 * 
 * @param string $size Image size name (mobile, tablet, full)
 * @param array $attr Additional attributes
 */
function theResponsivePostThumbnail($size = 'mobile', $attr = []) {
    echo getResponsivePostThumbnail(null, $size, $attr);
}

/**
 * Get responsive post thumbnail HTML
 * 
 * @param int|null $post_id Post ID
 * @param string $size Image size name
 * @param array $attr Additional attributes
 * @return string HTML img tag with srcset
 */
function getResponsivePostThumbnail($post_id = null, $size = 'mobile', $attr = []) {
    $post_id = $post_id ?: get_the_ID();
    $image_id = get_post_thumbnail_id($post_id);
    
    if (!$image_id) {
        // Return default image if set in Theme Options
        $default_id = getOption('default_image');
        if ($default_id) {
            return wp_get_attachment_image($default_id, $size, false, $attr);
        }
        // Fallback to static default image
        return getFallbackResponsiveImage($size, $attr);
    }
    
    return wp_get_attachment_image($image_id, $size, false, $attr);
}

/**
 * Echo responsive image from post meta
 * 
 * @param string $meta_key Carbon Fields meta key
 * @param string $size Image size name
 * @param array $attr Additional attributes
 */
function theResponsivePostMeta($meta_key, $size = 'mobile', $attr = []) {
    echo getResponsivePostMeta($meta_key, null, $size, $attr);
}

/**
 * Get responsive image from post meta
 * 
 * @param string $meta_key Carbon Fields meta key
 * @param int|null $post_id Post ID
 * @param string $size Image size name
 * @param array $attr Additional attributes
 * @return string HTML img tag with srcset
 */
function getResponsivePostMeta($meta_key, $post_id = null, $size = 'mobile', $attr = []) {
    $post_id = $post_id ?: get_the_ID();
    $image_id = carbon_get_post_meta($post_id, $meta_key);
    
    if (!$image_id) {
        // Fallback to static default image
        return getFallbackResponsiveImage($size, $attr);
    }
    
    return wp_get_attachment_image($image_id, $size, false, $attr);
}

/**
 * Echo responsive image from theme option
 * 
 * @param string $option_key Carbon Fields option key
 * @param string $size Image size name
 * @param array $attr Additional attributes
 */
function theResponsiveOption($option_key, $size = 'mobile', $attr = []) {
    echo getResponsiveOption($option_key, $size, $attr);
}

/**
 * Get responsive image from theme option
 * 
 * @param string $option_key Carbon Fields option key
 * @param string $size Image size name
 * @param array $attr Additional attributes
 * @return string HTML img tag with srcset
 */
function getResponsiveOption($option_key, $size = 'mobile', $attr = []) {
    $image_id = carbon_get_theme_option($option_key);
    
    if (!$image_id) {
        // Fallback to static default image
        return getFallbackResponsiveImage($size, $attr);
    }
    
    return wp_get_attachment_image($image_id, $size, false, $attr);
}

/**
 * Echo responsive image by attachment ID
 * 
 * @param int $attachment_id Attachment ID
 * @param string $size Image size name
 * @param array $attr Additional attributes
 */
function theResponsiveImage($attachment_id, $size = 'mobile', $attr = []) {
    echo getResponsiveImage($attachment_id, $size, $attr);
}

/**
 * Get responsive image by attachment ID
 * 
 * @param int $attachment_id Attachment ID
 * @param string $size Image size name
 * @param array $attr Additional attributes
 * @return string HTML img tag with srcset
 */
function getResponsiveImage($attachment_id, $size = 'mobile', $attr = []) {
    if (!$attachment_id) {
        // Fallback to static default image
        return getFallbackResponsiveImage($size, $attr);
    }
    
    return wp_get_attachment_image($attachment_id, $size, false, $attr);
}

// =============================================================================
// USAGE EXAMPLES
// =============================================================================

/*
// Example 1: Post thumbnail
<?php theResponsivePostThumbnail('mobile', ['class' => 'post-thumb', 'loading' => 'lazy']); ?>

// Example 2: Post meta image
<?php theResponsivePostMeta('gallery_image', 'tablet'); ?>

// Example 3: Theme option image
<?php theResponsiveOption('site_logo', 'full'); ?>

// Example 4: Direct attachment ID
<?php theResponsiveImage($image_id, 'mobile', ['alt' => 'Custom alt text']); ?>

// Available sizes:
// - 'mobile' (480px)
// - 'mobile-2x' (960px)
// - 'tablet' (768px)
// - 'tablet-2x' (1536px)
// - 'full' (original)
*/
