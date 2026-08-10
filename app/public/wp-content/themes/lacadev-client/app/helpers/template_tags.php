<?php
/**
 * get resources image uri
 *
 * @param string $path
 *
 * @return string
 */
function getImageAsset($path) {
    $my_theme   = wp_get_theme();
    $theme_path = dirname(get_stylesheet_directory_uri());

    if (carbon_get_theme_option('use_short_url') !== true) {
        $siteUrl = $theme_path . "/resources/images/";
    } else {
        $siteUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . "/img/";
    }

    return $siteUrl . $path;
}

function template($name) {
    get_template_part('templates/' . $name);
}

/**
 * Get theme option dạng image
 *
 * @param string $name
 * @param int    $w
 * @param int    $h
 *
 * @return false|string
 */
function getOptionImageUrl($name, $w, $h) {
    return getImageUrlById(getOption($name), $w, $h);
}

function getFieldImageUrl($name, $w, $h) {
    return getImageUrlById(get_field($name,'option'), $w, $h);
}

function getPostThumbnailUrl($postId, $width = null, $height = null) {
    $defaultImage = getImageUrlById(getOption('default_image'), $width, $height);
    
    // If no default_image option is set, use theme default image
    if (empty($defaultImage)) {
        $defaultImage = get_template_directory_uri() . '/resources/images/default-img.webp';
    }
    
    try {
        $imageId = get_post_thumbnail_id($postId);
        if (empty($imageId)) {
            return $defaultImage;
        }

        if ($width === null && $height === null) {
            return wp_get_attachment_image_url($imageId, 'full');
        }

        return getImageUrlById($imageId, $width, $height);
    } catch (\Exception $ex) {
        return $defaultImage;
    }
}

function getPostMetaImageUrl($name, $id = null, $w = null, $h = null) {
    $id = empty($id) ? get_the_ID() : $id;
    return getImageUrlById(carbon_get_post_meta($id, $name), $w, $h);
}

function getPostMeta($name, $id = null) {
	$id = empty($id) ? get_the_ID() : $id;
	return carbon_get_post_meta($id, $name);
}
function thePostMeta($name) {
    echo getPostMeta($name, get_the_ID());
}

function thePostMetaImageUrl($name = '', $w = null, $h = null) {
    echo getPostMetaImageUrl($name, get_the_ID(), $w, $h);
}

/**
 * Echo view count of post
 *
 * @param null $postId
 */
function getViewCount($postId = null) {
    $postId = empty($postId) ? get_the_ID() : $postId;
    $cache_key = "post_{$postId}_view_count";
    $view_count = get_transient($cache_key);

    if ($view_count === false) {
        $count_key = '_gm_view_count';
        $view_count = get_post_meta($postId, $count_key, true);
        if (empty($view_count)) {
            $view_count = 0;
        }
        set_transient($cache_key, $view_count, 12 * HOUR_IN_SECONDS); // Cache for 12 hours
    }

    return $view_count;
}

function updateViewCount($postId = null) {
	$postId = empty($postId) ? get_the_ID() : $postId;

	$count_key = '_gm_view_count';
	$count     = (int)get_post_meta($postId, $count_key, true);
	if (empty($count)) {
		$count = 1;
		delete_post_meta($postId, $count_key);
		add_post_meta($postId, $count_key, $count);
	} else {
		$count++;
		update_post_meta($postId, $count_key, $count);
	}

	return $count;
}
function theViewCount($postId = null) {
    echo getViewCount($postId);
}

function thePostThumbnailUrl($width = null, $height = null) {
    echo getPostThumbnailUrl(get_the_ID(), $width, $height);
}

function theTitle($limit = 999) {
    echo subString(get_the_title(), $limit);
}

function getExcerpt($postId, $limit) {
	return subString(get_the_excerpt($postId), $limit);
}
function theExcerpt($limit = 9999) {
    echo '<p>' . getExcerpt(get_the_ID(), $limit) . '</p>';
}

function theContent() {
    $content = get_the_content();
    $content = apply_filters('the_content', $content);
    $content = str_replace(']]>', ']]&gt;', $content);
    echo !empty($content) ? wp_kses_post($content) : __('Dữ liệu đang được cập nhật', 'laca');
}

function getOption($name) {
	return carbon_get_theme_option($name . currentLanguage());
}
function theOption($name) {
    echo getOption($name);
}

// Option image
function theOptionImage($name, $width = null, $height = null) {
    $imageId = getOption($name);
    if (!empty($imageId)) {
        echo getImageUrlById($imageId, $width, $height);
    }
}

// Load resource
function theAsset($path) {
    echo getImageAsset($path);
}

// Pagination
function thePagination($query = null) {
    if (empty($query)) {
        global $wp_query;
        $query = $wp_query;
    }

    // Preload post metadata to prevent N+1 queries
    if ($query instanceof WP_Query && !empty($query->posts)) {
        update_post_caches($query->posts);
        
        $post_ids = wp_list_pluck($query->posts, 'ID');
        if (!empty($post_ids)) {
            $post_type = !empty($query->posts[0]) ? $query->posts[0]->post_type : 'post';
            $taxonomies = get_object_taxonomies($post_type);
            foreach ($taxonomies as $taxonomy) {
                wp_get_object_terms($post_ids, $taxonomy, ['fields' => 'all']);
            }
        }
    }

    $paged = (get_query_var('paged') === 0) ? 1 : get_query_var('paged');
    $pages = paginate_links([
        'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
        'format'    => '?paged=%#%',
        'current'   => $paged,
        'total'     => $query->max_num_pages,
        'mid_size'  => 2,
        'type'      => 'array',
        'prev_next' => true,
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ]);
    
    if (is_array($pages)) {
        $pagination = '<nav class="pagination-container" aria-label="Page navigation"><ul class="pagination-list">';

        foreach ($pages as $page) {
            $is_current = strpos($page, 'current') !== false;
            $is_dots = strpos($page, 'dots') !== false;
            $item_class = 'pagination-item';
            if ($is_current) $item_class .= ' is-active';
            if ($is_dots) $item_class .= ' is-dots';

            $pagination .= '<li class="' . esc_attr($item_class) . '">' . $page . '</li>';
        }

        $pagination .= '</ul></nav>';

        echo $pagination;
    }
}

// Pagination for specific post type (used in search results)
function thePostTypePagination($query, $post_type) {
    if (empty($query) || $query->max_num_pages <= 1) {
        return;
    }

    // Preload post metadata to prevent N+1 queries
    if ($query instanceof WP_Query && !empty($query->posts)) {
        // Preload post data, meta, and terms cache
        update_post_caches($query->posts);
        
        // Preload terms for all posts to avoid N+1 queries in get_the_terms()
        $post_ids = wp_list_pluck($query->posts, 'ID');
        if (!empty($post_ids)) {
            $taxonomies = get_object_taxonomies($post_type);
            
            // Preload terms for all posts using wp_get_object_terms
            // This loads all terms in one query instead of N queries
            foreach ($taxonomies as $taxonomy) {
                wp_get_object_terms($post_ids, $taxonomy, ['fields' => 'all']);
            }
        }
    }

    $paged_var = 'paged_' . $post_type;
    $paged = max(1, get_query_var($paged_var, 1));
    
    // Get current URL without pagination params
    $base_url = remove_query_arg([$paged_var, 'paged']);
    
    $pages = paginate_links([
        'base'      => add_query_arg($paged_var, '%#%', $base_url),
        'format'    => '',
        'current'   => $paged,
        'total'     => $query->max_num_pages,
        'mid_size'  => 2,
        'type'      => 'array',
        'prev_next' => true,
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ]);

    if (is_array($pages)) {
        $pagination = '<nav class="pagination-container" aria-label="' . esc_attr($post_type) . ' navigation"><ul class="pagination-list">';

        foreach ($pages as $page) {
            $pagination .= '<li class="pagination-item">' . $page . '</li>';
        }

        $pagination .= '</ul></nav>';

        echo $pagination;
    }
}

// Breadcrumb
function theBreadcrumb() {
    get_template_part('template-parts/breadcrumb');
}

// Share socials
function theShareSocials() {
    get_template_part('template-parts/share_box');
}

// Get page title
function getPageTitle() {
    $obj   = get_queried_object();
    $title = get_bloginfo('name');
    if (is_single() || is_page()) {
        $title = get_the_title();
    } elseif (is_search()) {
        /* translators: search results page title */
        $title = sprintf(__('Kết quả tìm kiếm cho từ khóa: %s', 'laca'), get_search_query());
    } elseif (is_category()) {
        /* translators: category post listing page title */
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        /* translators: tag post listing page title */
        $title = sprintf(__('Tag: %s', 'laca'), single_tag_title('', false));
    } elseif (is_day()) {
        /* translators: day archive post listing page title */
        $title = sprintf(__('Daily Archives: %s', 'laca'), get_the_time('F jS, Y'));
    } elseif (is_month()) {
        /* translators: month archive post listing page title */
        $title = sprintf(__('Monthly Archives: %s', 'laca'), get_the_time('F, Y'));
    } elseif (is_year()) {
        /* translators: year archive post listing page title */
        $title = sprintf(__('Yearly Archives: %s', 'laca'), get_the_time('Y'));
    } elseif (is_author()) {
        /* translators: author archive post listing page title */
        $title = sprintf(__('Posts by %s', 'laca'), get_the_author());
    } elseif (class_exists('WooCommerce') && is_woocommerce()) {
        $title = woocommerce_page_title(false);
    } elseif (is_archive()) {
        if ($obj instanceof WP_Term) {
            $title = $obj->name;
        } elseif ($obj instanceof WP_Post_Type) {
            $title = $obj->label;
        }
    } elseif (is_404()) {
        $title = __('Lỗi 404 - Không tìm thấy trang bạn yêu cầu', 'laca');
    }
    return $title;
}

// Language switcher
function theLanguageSwitcher($showName = true, $showFlag = false) {
  if (function_exists('pll_the_languages')) {
      $languages = pll_the_languages([
          'show_names'    => $showName,
          'show_flags'    => $showFlag,
          'hide_if_empty' => false,
          'hide_current'  => true,
          'raw'           => true,
      ]);

      echo '<ul class="language-switcher">';
      foreach ($languages as $lang) {
          $icon_html = '<span class="iconify" data-icon="ant-design:global-outlined"></span>';
          echo '<li><a href="'. esc_url($lang['url']) .'" class="btn btn-outline" hreflang="'. esc_attr($lang['slug']) .'">' . $icon_html . ' ' . esc_html($lang['name']) . '</a></li>';
      }
      echo '</ul>';
  }
}
