<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Optimization & WebP Support
 * 
 * @package LacaDev
 */

/**
 * Register custom image sizes for responsive images
 * Priority 1 để chạy RẤT SỚM, trước khi helpers/functions.php can thiệp
 */
add_action('init', function() {
    // FORCE register sizes
    add_image_size('mobile', 480, 9999, false);
    add_image_size('mobile-2x', 960, 9999, false);
    add_image_size('tablet', 768, 9999, false);
    add_image_size('tablet-2x', 1536, 9999, false);
}, 1);

/**
 * Control which image sizes to generate
 * Chỉ tạo mobile và tablet, bỏ thumbnail mặc định
 */
add_filter('intermediate_image_sizes_advanced', function($sizes, $metadata) {
    // Xóa thumbnail 150x150
    unset($sizes['thumbnail']);
    unset($sizes['medium']);
    unset($sizes['medium_large']);
    unset($sizes['large']);
    unset($sizes['1536x1536']);
    unset($sizes['2048x2048']);
    
    return $sizes;
}, 999, 2);

/**
 * Enable WebP support in WordPress
 */
add_filter('mime_types', function($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
});

/**
 * Add WebP to allowed upload file types
 */
add_filter('upload_mimes', function($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
});

/**
 * Display WebP images correctly in media library
 */
add_filter('file_is_displayable_image', function($result, $path) {
    if ($result === false) {
        $info = @getimagesize($path);
        if (isset($info['mime']) && $info['mime'] === 'image/webp') {
            $result = true;
        }
    }
    return $result;
}, 10, 2);

/**
 * Auto-generate WebP version and DELETE originals
 * Note: Requires GD or Imagick with WebP support
 */
add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id) {
    $file = get_attached_file($attachment_id);
    
    // Only process images
    if (!wp_attachment_is_image($attachment_id)) {
        return $metadata;
    }
    
    // Check if server supports WebP
    if (!function_exists('imagewebp')) {
        return $metadata;
    }

    // Skip if already WebP
    $file_info = pathinfo($file);
    if (isset($file_info['extension']) && strtolower($file_info['extension']) === 'webp') {
        return $metadata;
    }
    
    // Skip SVG (vector) and GIF (animation) - keep original
    $ext = strtolower($file_info['extension']);
    if (in_array($ext, ['svg', 'gif'])) {
        return $metadata;
    }
    
    // WordPress đã tạo xong TẤT CẢ sizes ở đây (JPG/PNG)
    // Bây giờ convert chúng sang WebP
    
    // 1. Convert ALL intermediate sizes FIRST
    $sizes_to_delete = []; // Track files to delete later
    
    if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
        $base_dir = dirname($file);
        
        foreach ($metadata['sizes'] as $size => $size_data) {
            $size_file = $base_dir . '/' . $size_data['file'];
            $size_file_info = pathinfo($size_file);
            
            // Skip if already WebP
            if (strtolower($size_file_info['extension']) === 'webp') {
                continue;
            }
            
            $size_webp_path = $base_dir . '/' . $size_file_info['filename'] . '.webp';
            
            if (file_exists($size_file)) {
                $size_converted = lacadev_generate_webp_image($size_file, $size_webp_path);
                
                if ($size_converted) {
                    // Track for deletion
                    $sizes_to_delete[] = $size_file;
                    
                    // Update metadata for this size
                    $metadata['sizes'][$size]['file'] = $size_file_info['filename'] . '.webp';
                    $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                    
                    // Update filesize for this size
                    if (file_exists($size_webp_path)) {
                        $metadata['sizes'][$size]['filesize'] = filesize($size_webp_path);
                    }
                }
            }
        }
    }
    
    // 2. Convert Main Image LAST
    $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
    $converted = lacadev_generate_webp_image($file, $webp_path);
    
    if ($converted) {
        // Update metadata file path
        $metadata['file'] = str_replace($file_info['basename'], $file_info['filename'] . '.webp', $metadata['file']);
        
        // Update filesize in metadata
        if (file_exists($webp_path)) {
            $metadata['filesize'] = filesize($webp_path);
        }
        
        // Update attachment file path to WebP
        update_attached_file($attachment_id, $webp_path);
        
        // Update post mime type
        wp_update_post([
            'ID' => $attachment_id,
            'post_mime_type' => 'image/webp',
            'guid' => str_replace($file_info['basename'], $file_info['filename'] . '.webp', get_the_guid($attachment_id))
        ]);
        
        // NOW it's safe to delete originals
        @unlink($file); // Delete main JPG/PNG
    }
    
    // Delete all intermediate size originals
    foreach ($sizes_to_delete as $file_to_delete) {
        @unlink($file_to_delete);
    }
    
    return $metadata;
}, 10, 2);

/**
 * Helper function to generate WebP image
 */
function lacadev_generate_webp_image($source_path, $destination_path) {
    if (!file_exists($source_path)) {
        return false;
    }
    
    // Load image based on type
    $image = false;
    $info = getimagesize($source_path);
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source_path);
            // Preserve transparency
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source_path);
            break;
    }
    
    if ($image === false) {
        return false;
    }
    
    // Generate WebP with 75% quality
    $result = imagewebp($image, $destination_path, 75);
    
    // Free memory
    imagedestroy($image);
    
    return $result;
}

/**
 * Add responsive srcset to images
 */
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    if (!wp_attachment_is_image($attachment->ID)) {
        return $attr;
    }
    
    // Generate srcset for responsive images
    $image_meta = wp_get_attachment_metadata($attachment->ID);
    
    if (!isset($image_meta['sizes']) || empty($image_meta['sizes'])) {
        return $attr;
    }
    
    $srcset = [];
    $sizes_config = [
        'mobile' => '480w',
        'mobile-2x' => '960w',
        'tablet' => '768w',
        'tablet-2x' => '1536w',
        // Desktop/Laptop dùng ảnh gốc (full size)
    ];
    
    foreach ($sizes_config as $size_name => $width) {
        $url = wp_get_attachment_image_url($attachment->ID, $size_name);
        if ($url) {
            $srcset[] = esc_url($url) . ' ' . $width;
        }
    }
    
    // Add full size image for desktop/laptop
    $full_url = wp_get_attachment_image_url($attachment->ID, 'full');
    if ($full_url) {
        $srcset[] = esc_url($full_url) . ' 2048w';
    }
    
    if (!empty($srcset)) {
        $attr['srcset'] = implode(', ', $srcset);
        $attr['sizes'] = '(max-width: 480px) 480px, (max-width: 768px) 768px, 100vw';
    }
    
    return $attr;
}, 10, 3);

/**
 * Lazy load images by default
 */
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    // Add loading="lazy" for better performance
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    
    // Add decoding="async" for non-blocking
    if (!isset($attr['decoding'])) {
        $attr['decoding'] = 'async';
    }
    
    return $attr;
}, 10, 3);

/**
 * Optimize image quality on upload
 */
add_filter('jpeg_quality', function($quality) {
    return 75; // Reduced to 75% as requested
});

add_filter('wp_editor_set_quality', function($quality) {
    return 75;
});
