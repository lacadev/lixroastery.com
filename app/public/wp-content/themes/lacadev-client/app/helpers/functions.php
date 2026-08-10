<?php

use Carbon\Carbon;
use Intervention\Image\ImageManagerStatic as Image;

// =============================================================================
// ARRAY & STRING MANIPULATION
// =============================================================================

function insertArrayAtPosition($array, $insert, $position)
{
    return array_slice($array, 0, $position, true) + $insert + array_slice($array, $position, null, true);
}

function subString($str, $limit)
{
    return wp_trim_words($str, $limit, '...');
}

// =============================================================================
// LANGUAGE & INTERNATIONALIZATION
// =============================================================================

function currentLanguage()
{
    return defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : '';
}

// =============================================================================
// ASSETS & RESOURCES
// =============================================================================

function adminAsset($path)
{
    return get_stylesheet_directory_uri() . '/../dist/' . $path;
}

function loadStyles($files = [])
{
    add_action('wp_enqueue_scripts', function () use ($files) {
        $theme_version = wp_get_theme()->get('Version');
        foreach ($files as $index => $file) {
            wp_enqueue_style('theme-css-' . $index, $file, [], $theme_version);
        }
        wp_enqueue_style('theme-style', get_stylesheet_directory_uri() . '/style.css', [], $theme_version);
    });
}

function loadScripts($files = [])
{
    add_action('wp_enqueue_scripts', function () use ($files) {
        $theme_version = wp_get_theme()->get('Version');
        foreach ($files as $index => $file) {
            wp_enqueue_script('theme-js-' . $index, $file, [], $theme_version, true);
        }
    });
}

// =============================================================================
// META & POST MANAGEMENT
// =============================================================================

function updatePostMeta($post_id, $field_name, $value = '')
{
    if (empty($value)) {
        return delete_post_meta($post_id, $field_name);
    }
    return update_post_meta($post_id, $field_name, $value) ?: add_post_meta($post_id, $field_name, $value);
}

function updateUserMeta($idUser, $key, $value)
{
    if (empty($value)) {
        return delete_user_meta($idUser, $key);
    }
    return update_user_meta($idUser, $key, $value) ?: add_user_meta($idUser, $key, $value);
}

// =============================================================================
// IMAGE HANDLING
// =============================================================================

function updateAttachmentSize($attachment_id, $fileName, $width, $height, $type)
{
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (is_array($metadata) && array_key_exists('sizes', $metadata)) {
        $size = $metadata['sizes'];
        $sizeName = $width . 'x' . $height;
        if (!array_key_exists($sizeName, $size)) {
            $metadata['sizes'][$sizeName] = [
                'file' => $fileName,
                'width' => $width,
                'height' => $height,
                'mime-type' => $type,
            ];
        }
        wp_update_attachment_metadata($attachment_id, $metadata);
    }
}

function resizeImage($srcPath, $destinationPath, $maxWidth, $maxHeight, $type = 'webp')
{
    try {
        $image = Image::make($srcPath);
        if ($maxWidth || $maxHeight) {
            $image->fit($maxWidth, $maxHeight, static function ($constraint) {
                $constraint->upsize();
            });
        }
        $image->encode($type)->save($destinationPath, 85);
    } catch (\Exception $ex) {
        error_log($ex->getMessage());
    }
}

function getImageUrlById($attachment_id, $width = null, $height = null)
{
    if ($width === null && $height === null) {
        return wp_get_attachment_image_url($attachment_id, 'full');
    }

    $width = $width ? absint($width) : 0;
    $height = $height ? absint($height) : 0;
    $upload_dir = wp_upload_dir();
    $attachment_realpath = crb_normalize_path(get_attached_file($attachment_id));

    if (empty($attachment_realpath)) {
        return "https://via.placeholder.com/{$width}x{$height}";
    }

    $filename = basename($attachment_realpath);
    $fileParts = explode('.', $filename);
    $fileExt = $fileParts[count($fileParts) - 1];
    if (in_array($fileExt, ['gif', 'svg'])) {
        return wp_get_attachment_image_url($attachment_id, 'full');
    }

    $filename = preg_replace('/(\.[^\.]+)$/', '-' . $width . 'x' . $height, $filename);
    $filepath = crb_normalize_path($upload_dir['basedir'] . '/' . $filename);
    $url = trailingslashit($upload_dir['baseurl']) . $filename;

    if (!file_exists($filepath)) {
        return wp_get_attachment_image_url($attachment_id, 'full');
    }

    return $url;
}

function resizeImageFly($url, $width = null, $height = null, $crop = true, $retina = false)
{
    global $wpdb;
    if (empty($url)) {
        return new WP_Error('no_image_url', __('No image URL has been entered.', 'wta'), $url);
    }

    $width = $width ?: get_option('thumbnail_size_w');
    $height = $height ?: get_option('thumbnail_size_h');
    $retina = $retina ? ($retina === true ? 2 : $retina) : 1;

    $file_info = parse_url($url);
    $file_path = ABSPATH . ltrim($file_info['path'], '/');

    if (is_multisite()) {
        global $blog_id;
        $blog_details = get_blog_details($blog_id);
        $file_path = str_replace($blog_details->path . 'files/', '/wp-content/blogs.dir/' . $blog_id . '/files/', $file_path);
    }

    $dest_width = $width * $retina;
    $dest_height = $height * $retina;
    $suffix = "{$dest_width}x{$dest_height}";

    $info = pathinfo($file_path);
    $dir = $info['dirname'];
    $ext = $info['extension'];
    $name = wp_basename($file_path, ".$ext");

    if ('bmp' === $ext) {
        return new WP_Error('bmp_mime_type', __('Image is BMP. Please use either JPG or PNG.', 'wta'), $url);
    }

    $dest_file_name = "{$dir}/{$name}-{$suffix}.{$ext}";

    if (!file_exists($dest_file_name)) {
        $query = $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE guid='%s'", $url);
        $get_attachment = $wpdb->get_results($query);
        if (!$get_attachment) {
            return ['url' => $url, 'width' => $width, 'height' => $height];
        }

        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return ['url' => $url, 'width' => $width, 'height' => $height];
        }

        $size = $editor->get_size();
        $orig_width = $size['width'];
        $orig_height = $size['height'];
        $src_x = $src_y = 0;
        $src_w = $orig_width;
        $src_h = $orig_height;

        if ($crop) {
            $cmp_x = $orig_width / $dest_width;
            $cmp_y = $orig_height / $dest_height;
            if ($cmp_x > $cmp_y) {
                $src_w = round($orig_width / $cmp_x * $cmp_y);
                $src_x = round(($orig_width - ($orig_width / $cmp_x * $cmp_y)) / 2);
            } else if ($cmp_y > $cmp_x) {
                $src_h = round($orig_height / $cmp_y * $cmp_x);
                $src_y = round(($orig_height - ($orig_height / $cmp_y * $cmp_x)) / 2);
            }
        }

        $editor->crop($src_x, $src_y, $src_w, $src_h, $dest_width, $dest_height);
        $saved = $editor->save($dest_file_name);

        $resized_url = str_replace(basename($url), basename($saved['path']), $url);
        $resized_width = $saved['width'];
        $resized_height = $saved['height'];
        $resized_type = $saved['mime-type'];

        $metadata = wp_get_attachment_metadata($get_attachment[0]->ID);
        if (isset($metadata['image_meta'])) {
            $metadata['image_meta']['resized_images'][] = $resized_width . 'x' . $resized_height;
            wp_update_attachment_metadata($get_attachment[0]->ID, $metadata);
        }

        $image_array = [
            'url' => $resized_url,
            'width' => $resized_width,
            'height' => $resized_height,
            'type' => $resized_type,
        ];
    } else {
        $image_array = [
            'url' => str_replace(basename($url), basename($dest_file_name), $url),
            'width' => $dest_width,
            'height' => $dest_height,
            'type' => $ext,
        ];
    }

    return $image_array;
}

// =============================================================================
// POST QUERIES & RELATED CONTENT
// =============================================================================

function getRelatePosts($postId = null, $postCount = null)
{
    global $post;
    $postCount = $postCount ?: get_option('posts_per_page');
    $thisPost = $postId ? get_post($postId) : $post;

    if (!$thisPost || !isset($thisPost->ID)) {
        return new WP_Query(['post__in' => []]);
    }

    // Generate cache key
    $cache_key = 'related_posts_' . $thisPost->ID . '_' . $thisPost->post_type . '_' . $postCount;
    $cached_post_ids = get_transient($cache_key);

    // Return cached query if exists
    if (false !== $cached_post_ids && is_array($cached_post_ids)) {
        return new WP_Query([
            'post__in' => $cached_post_ids,
            'post_type' => $thisPost->post_type,
            'post_status' => 'publish',
            'posts_per_page' => $postCount,
            'orderby' => 'post__in',
        ]);
    }

    // Build query if not cached
    $taxonomies = get_post_taxonomies($thisPost->ID);
    $arrTaxQuery = ['relation' => 'OR'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($thisPost->ID, $taxonomy);
        if ($terms && !is_wp_error($terms)) {
            $arrTaxQuery[] = [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => wp_list_pluck($terms, 'term_id'),
            ];
        }
    }

    $query = new WP_Query([
        'post_type' => $thisPost->post_type,
        'post_status' => 'publish',
        'posts_per_page' => $postCount,
        'post__not_in' => [$thisPost->ID],
        'tax_query' => $arrTaxQuery,
        'fields' => 'ids', // Optimization: fetch only IDs for the transient
    ]);

    // Cache post IDs for 1 hour
    $post_ids = $query->posts;
    set_transient($cache_key, $post_ids, HOUR_IN_SECONDS);

    // Now return a full query object for the frontend
    return new WP_Query([
        'post__in' => $post_ids,
        'post_type' => $thisPost->post_type,
        'post_status' => 'publish',
        'posts_per_page' => $postCount,
        'orderby' => 'post__in',
    ]);
}

function getLatestPosts($postType = 'post', $postCount = null)
{
    $postCount = $postCount ?: get_option('posts_per_page');

    // Generate cache key
    $cache_key = 'latest_posts_' . $postType . '_' . $postCount;
    $cached_post_ids = get_transient($cache_key);

    // Return cached query if exists
    if (false !== $cached_post_ids && is_array($cached_post_ids)) {
        return new WP_Query([
            'post__in' => $cached_post_ids,
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $postCount,
            'orderby' => 'post__in',
        ]);
    }

    // Build query if not cached
    $query = new WP_Query([
        'post_type' => $postType,
        'post_status' => 'publish',
        'posts_per_page' => $postCount,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids', // Optimization
    ]);

    $post_ids = $query->posts;
    set_transient($cache_key, $post_ids, 15 * MINUTE_IN_SECONDS);

    return new WP_Query([
        'post__in' => $post_ids ?: [0],
        'post_type' => $postType,
        'post_status' => 'publish',
        'posts_per_page' => $postCount,
        'orderby' => 'post__in',
    ]);
}

function getTopViewPosts($postType = 'post', $postCount = null)
{
    $postCount = $postCount ?: get_option('posts_per_page');

    // Generate cache key
    $cache_key = 'top_view_posts_' . $postType . '_' . $postCount;
    $cached_post_ids = get_transient($cache_key);

    // Return cached query if exists
    if (false !== $cached_post_ids && is_array($cached_post_ids)) {
        return new WP_Query([
            'post__in' => $cached_post_ids,
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $postCount,
            'orderby' => 'post__in',
        ]);
    }

    // Build query if not cached
    $query = new WP_Query([
        'post_type' => $postType,
        'post_status' => 'publish',
        'posts_per_page' => $postCount,
        'meta_key' => '_gm_view_count',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'fields' => 'ids', // Optimization
    ]);

    $post_ids = $query->posts;
    set_transient($cache_key, $post_ids, 30 * MINUTE_IN_SECONDS);

    return new WP_Query([
        'post__in' => $post_ids ?: [0],
        'post_type' => $postType,
        'post_status' => 'publish',
        'posts_per_page' => $postCount,
        'orderby' => 'post__in',
    ]);
}

function getListAllPages()
{
    $pages = get_posts([
        'post_type' => 'page',
        'posts_per_page' => -1,
        'lang' => function_exists('get_icl_language_code') ? get_icl_language_code() : '',
    ]);

    $list = [];
    foreach ($pages as $page) {
        $list[$page->ID] = $page->post_title;
    }

    return $list;
}

// =============================================================================
// TIME & DATE FORMATTING
// =============================================================================

function formatHumanTime($time)
{
    $diff = Carbon::now()->diffInSeconds(Carbon::parse($time));
    if ($diff < 60)
        return __('Vừa mới đây', 'laca');
    if ($diff < 3600)
        return sprintf(__('Khoảng %d phút trước', 'laca'), round($diff / 60));
    if ($diff < 86400)
        return sprintf(__('Khoảng %d giờ trước', 'laca'), round($diff / 3600));
    if ($diff < 604800)
        return sprintf(__('Khoảng %d ngày trước', 'laca'), round($diff / 86400));
    return sprintf(__('Khoảng %d tuần trước', 'laca'), round($diff / 604800));
}

// =============================================================================
// VIDEO HANDLING
// =============================================================================
function getYoutubeEmbedUrl($url)
{
    $youtube_id = '';
    if (preg_match('/(youtube\.com.*(\?v=|\/embed\/|\/v\/|\/.+\/|youtu\.be\/|\/v\/)|\/shorts\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        $youtube_id = $matches[3];
    }

    if (!empty($youtube_id)) {
        return 'https://www.youtube.com/embed/' . $youtube_id . '?modestbranding=1&showinfo=0&controls=1&frameborder=0&allow=accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture&allowfullscreen';
    }

    return '';
}

function getVideoUrl($video_link)
{
    $video_html = '';

    if (!empty($video_link)) {
        if (strpos($video_link, 'youtube.com') !== false || strpos($video_link, 'youtu.be') !== false) {
            $youtube_embed_url = getYoutubeEmbedUrl($video_link);
            if (!empty($youtube_embed_url)) {
                $video_html = '<div class="video-embed"><iframe title="YouTube video" src="' . $youtube_embed_url . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
            }
        } elseif (strpos($video_link, 'vimeo.com') !== false) {
            $video_ID = substr(parse_url($video_link, PHP_URL_PATH), 1);
            $vimeo_api_url = "https://vimeo.com/api/v2/video/{$video_ID}.json";

            $hash = @file_get_contents($vimeo_api_url);
            if ($hash) {
                $hash_data = json_decode($hash);
                if (isset($hash_data[0])) {
                    $title = $hash_data[0]->title;
                    $video_html = '<div class="video-embed"><iframe title="Video: ' . $title . '" src="https://player.vimeo.com/video/' . $video_ID . '" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe></div>';
                }
            }
        }
    }

    return $video_html;
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================
function crb_normalize_path($path)
{
    return preg_replace('~[/' . preg_quote('\\', '~') . ']~', DIRECTORY_SEPARATOR, $path);
}

// =============================================================================
// PERFORMANCE OPTIMIZATIONS
// =============================================================================

/**
 * Clear query cache when posts are updated/published/deleted
 */
function laca_clear_query_cache($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    $post_type = $post->post_type;
    $posts_per_page = get_option('posts_per_page');

    // Clear related posts cache for this post
    delete_transient('related_posts_' . $post_id . '_' . $post_type . '_' . $posts_per_page);

    // Clear latest posts cache for this post type
    delete_transient('latest_posts_' . $post_type . '_' . $posts_per_page);

    // Clear top view posts cache for this post type
    delete_transient('top_view_posts_' . $post_type . '_' . $posts_per_page);

    // Clear all related posts caches (posts that might relate to this one)
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_related_posts_') . '%'
    ));
}
add_action('save_post', 'laca_clear_query_cache');
add_action('delete_post', 'laca_clear_query_cache');
add_action('wp_trash_post', 'laca_clear_query_cache');

/**
 * Clear cache when post meta (view count) is updated
 */
function laca_clear_query_cache_on_meta_update($meta_id, $post_id, $meta_key, $meta_value)
{
    if ($meta_key === '_gm_view_count') {
        $post = get_post($post_id);
        if ($post) {
            $post_type = $post->post_type;
            $posts_per_page = get_option('posts_per_page');
            delete_transient('top_view_posts_' . $post_type . '_' . $posts_per_page);
        }
    }
}
add_action('updated_post_meta', 'laca_clear_query_cache_on_meta_update', 10, 4);
add_action('added_post_meta', 'laca_clear_query_cache_on_meta_update', 10, 4);

function contactform_dequeue_scripts()
{
    if (!is_singular() || !has_shortcode(get_post()->post_content, 'contact-form-7')) {
        wp_dequeue_script('contact-form-7');
        wp_dequeue_script('google-recaptcha');
        wp_dequeue_style('contact-form-7');
    }
}
add_action('wp_enqueue_scripts', 'contactform_dequeue_scripts', 99);


// =============================================================================
// CONTACT FORM 7 SPAM PROTECTION
// =============================================================================

add_filter('wpcf7_form_elements', 'lacadev_check_spam_form_cf7');
function lacadev_check_spam_form_cf7($html)
{
    $html = '<div style="display: none"><p><span class="wpcf7-form-control-wrap" data-name="lacadev"><input size="40" class="wpcf7-form-control wpcf7-text" aria-invalid="false" value="" type="text" name="lacadev"></span></p></div>' . $html;
    return $html;
}

add_action('wpcf7_posted_data', 'laca_check_spam_form_cf7_vaild');
function laca_check_spam_form_cf7_vaild($posted_data)
{
    if (!class_exists('WPCF7_Submission')) {
        return $posted_data;
    }

    $submission = WPCF7_Submission::get_instance();
    if (!empty($posted_data['lacadev'])) {
        $submission->set_status('spam');
        $submission->set_response('You are Spamer');
    }
    unset($posted_data['lacadev']);
    return $posted_data;
}

// Enable excerpt for pages
add_post_type_support('page', 'excerpt');

// =============================================================================
// REST API CUSTOMIZATIONS
// =============================================================================
/**
 * Registered meta fields for REST API
 */
function lacadev_register_rest_fields()
{
    register_post_meta('project', '_is_real', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ]);

    register_post_meta('project', 'quick_view_img', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ]);
}
add_action('init', 'lacadev_register_rest_fields');

/**
 * Fix REST API taxonomy query for projects
 * Some clients send project_cat as an array which causes 400 error
 */
add_filter('rest_project_query', function ($args, $request) {
    $project_cat = $request->get_param('project_cat');

    // ERROR LOG for debug
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('REST Project Query: project_cat = ' . print_r($project_cat, true));
    }

    if (is_array($project_cat) || (is_string($project_cat) && strpos($project_cat, ',') !== false)) {
        $terms = is_array($project_cat) ? $project_cat : explode(',', $project_cat);
        $args['tax_query'] = [
            [
                'taxonomy' => 'project_cat',
                'field' => 'term_id',
                'terms' => array_map('intval', $terms),
                'operator' => 'IN',
            ]
        ];
        // Remove the original project_cat if it exists to avoid conflicts
        unset($args['project_cat']);
    }

    return $args;
}, 10, 2);

// Disable Gutenberg for all post types
// add_filter('use_block_editor_for_post', '__return_false');
