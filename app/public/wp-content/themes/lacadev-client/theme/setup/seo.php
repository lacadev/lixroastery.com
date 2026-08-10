<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Meta Tags System
 * 
 * @package LacaDev
 */

/**
 * Add Canonical URL
 */
add_action('wp_head', function() {
    if (is_singular()) {
        echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">' . "\n";
    } elseif (is_home() || is_front_page()) {
        echo '<link rel="canonical" href="' . esc_url(home_url('/')) . '">' . "\n";
    } elseif (is_archive()) {
        echo '<link rel="canonical" href="' . esc_url(get_pagenum_link(get_query_var('paged') ? get_query_var('paged') : 1)) . '">' . "\n";
    }
}, 1);

/**
 * Add Open Graph Meta Tags
 */
add_action('wp_head', function() {
    global $post;
    
    // Site name
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    
    // Locale
    echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";
    
    if (is_singular()) {
        // Type
        echo '<meta property="og:type" content="article">' . "\n";
        
        // Title
        echo '<meta property="og:title" content="' . esc_attr(get_the_title()) . '">' . "\n";
        
        // Description
        $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(strip_shortcodes($post->post_content), 30);
        echo '<meta property="og:description" content="' . esc_attr($excerpt) . '">' . "\n";
        
        // URL
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        
        // Image
        if (has_post_thumbnail()) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta property="og:image" content="' . esc_url($image_url) . '">' . "\n";
            
            // Image dimensions
            $image_id = get_post_thumbnail_id($post->ID);
            $image_meta = wp_get_attachment_metadata($image_id);
            if ($image_meta) {
                echo '<meta property="og:image:width" content="' . esc_attr($image_meta['width']) . '">' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr($image_meta['height']) . '">' . "\n";
            }
        }
        
        // Article metadata
        if (is_single()) {
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";
            echo '<meta property="article:author" content="' . esc_attr(get_the_author()) . '">' . "\n";
        }
    } else {
        // Homepage or archive
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr(get_bloginfo('description')) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(home_url('/')) . '">' . "\n";
        
        // Site icon as fallback image
        if (has_site_icon()) {
            echo '<meta property="og:image" content="' . esc_url(get_site_icon_url(512)) . '">' . "\n";
        }
    }
}, 5);

/**
 * Add Twitter Card Meta Tags
 */
add_action('wp_head', function() {
    global $post;
    
    // Card type
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    
    if (is_singular()) {
        // Title
        echo '<meta name="twitter:title" content="' . esc_attr(get_the_title()) . '">' . "\n";
        
        // Description
        $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(strip_shortcodes($post->post_content), 30);
        echo '<meta name="twitter:description" content="' . esc_attr($excerpt) . '">' . "\n";
        
        // Image
        if (has_post_thumbnail()) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '">' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr(get_the_title()) . '">' . "\n";
        }
    } else {
        echo '<meta name="twitter:title" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr(get_bloginfo('description')) . '">' . "\n";
        
        if (has_site_icon()) {
            echo '<meta name="twitter:image" content="' . esc_url(get_site_icon_url(512)) . '">' . "\n";
        }
    }
}, 5);

/**
 * Add Schema.org JSON-LD Markup
 */
add_action('wp_head', function() {
    global $post;
    
    if (is_singular('post')) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => get_author_posts_url(get_the_author_meta('ID'))
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url(512) ?: get_template_directory_uri() . '/resources/images/logo.png'
                ]
            ]
        ];
        
        // Add description
        if (has_excerpt()) {
            $schema['description'] = get_the_excerpt();
        } else {
            $schema['description'] = wp_trim_words(strip_shortcodes($post->post_content), 30);
        }
        
        // Add image
        if (has_post_thumbnail()) {
            $schema['image'] = get_the_post_thumbnail_url($post->ID, 'large');
        }
        
        // Add main entity of page
        $schema['mainEntityOfPage'] = [
            '@type' => 'WebPage',
            '@id' => get_permalink()
        ];
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    // Service schema
    if (is_singular('service')) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => get_the_title(),
            'description' => has_excerpt() ? get_the_excerpt() : wp_trim_words(strip_shortcodes($post->post_content), 30),
            'provider' => [
                '@type' => 'LocalBusiness',
                'name' => get_bloginfo('name'),
                'url' => home_url('/')
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => get_permalink()
            ]
        ];

        if (has_post_thumbnail()) {
            $schema['image'] = get_the_post_thumbnail_url($post->ID, 'large');
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    // Organization schema for homepage
    if (is_front_page()) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
            'logo' => get_site_icon_url(512) ?: get_template_directory_uri() . '/resources/images/logo.png',
            'description' => get_bloginfo('description')
        ];
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
    
    // Breadcrumb schema for single posts/pages
    if (is_singular() && !is_front_page()) {
        $breadcrumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => []
        ];
        
        // Home
        $breadcrumbs['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Trang chủ',
            'item' => home_url('/')
        ];
        
        $position = 2;
        
        // Category (if post)
        if (is_single()) {
            $categories = get_the_category();
            if ($categories) {
                $category = $categories[0];
                $breadcrumbs['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $category->name,
                    'item' => get_category_link($category->term_id)
                ];
            }
        }
        
        // Current page
        $breadcrumbs['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title(),
            'item' => get_permalink()
        ];
        
        echo '<script type="application/ld+json">' . wp_json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}, 5);

/**
 * Add dynamic meta description
 */
add_action('wp_head', function() {
    global $post;
    
    $description = '';
    
    if (is_singular()) {
        if (has_excerpt()) {
            $description = get_the_excerpt();
        } else {
            $description = wp_trim_words(strip_shortcodes($post->post_content), 30);
        }
    } elseif (is_archive()) {
        $description = get_the_archive_description();
    } else {
        $description = get_bloginfo('description');
    }
    
    if ($description) {
        echo '<meta name="description" content="' . esc_attr(strip_tags($description)) . '">' . "\n";
    }
}, 1);
