<?php
/**
 * Child Theme Hooks
 *
 * Đăng ký các actions và filters riêng của child theme.
 * Parent theme hooks vẫn chạy bình thường — chỉ thêm/ghi đè ở đây.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CHILD HOOKS — thêm hooks của bạn bên dưới
// =============================================================================

// ── Layout Mode helper (laca_block_layout_class) ───────────────────────────
require_once CHILD_APP_DIR . 'helpers/layout-mode.php';

// ── Trang bắt đầu bằng block Top Hero (header overlay trong suốt + bỏ
// breadcrumb) — dùng chung giữa theme/header.php và theme/page.php ─────────
require_once CHILD_APP_DIR . 'helpers/top-hero-helpers.php';

// ── AJAX pagination markup (matches parent thePagination() BEM) ──────────────
require_once CHILD_APP_DIR . 'helpers/ajax-pagination-markup.php';

// ── Admin List Table Inline Editor Fields (LIX Roastery) ─────────────────────
require_once CHILD_APP_DIR . 'helpers/list-table-fields.php';

// ── Gallery/PdnTv/Project Archive AJAX Handler — TẠM TẮT (audit) ─────────────
// Cả 3 handler đăng ký đúng action (lacadev_gallery_archive_load,
// lacadev_pdn_tv_archive_load, lacadev_project_archive_load) và render card
// đầy đủ, nhưng không có template archive-{cpt}.php hay JS nào trong theme
// này gọi tới cả 3 action trên — hoàn toàn mồ côi. Meta field của 3 CPT này
// (investor/floors/location/total_area...) mang đặc trưng site bất động
// sản, không khớp LixRoastery (coffee roastery) — nhiều khả năng bị copy
// nhầm từ 1 site khác trong cùng family lacadev-client-child. Tắt require
// (không xoá file) để dừng đăng ký AJAX action không dùng tới; bật lại nếu
// xác nhận 3 CPT này thực sự cần dùng trên site này.
// require_once CHILD_APP_DIR . 'src/Ajax/GalleryAjaxHandler.php';
// require_once CHILD_APP_DIR . 'src/Ajax/PdnTvAjaxHandler.php';
// require_once CHILD_APP_DIR . 'src/Ajax/ProjectAjaxHandler.php';

// ── Popup giỏ hàng ở header (xóa/đổi số lượng qua AJAX, badge tự đồng bộ
// qua woocommerce_add_to_cart_fragments) — xem theme/header.php ────────────
require_once CHILD_APP_DIR . 'helpers/mini-cart-render.php';
require_once CHILD_APP_DIR . 'src/Ajax/CartAjaxHandler.php';

// ── CPT Grid block AJAX Handler (dùng chung cho MỌI post type, khác các handler cố định CPT ở trên) ──
require_once CHILD_APP_DIR . 'helpers/taxonomy-helpers.php';
require_once CHILD_APP_DIR . 'helpers/cpt-grid-render.php';
require_once CHILD_APP_DIR . 'src/Ajax/CptGridAjaxHandler.php';

// ── Journal archive: tab danh mục cấp 1 (/journal) + lưới danh mục con (render sẵn mọi tab) ──
require_once CHILD_APP_DIR . 'helpers/journal-cat-render.php';

// ── Card sản phẩm dùng chung giữa block Product Grid và "Sản phẩm liên
// quan" tự động cuối mỗi bài Journal (single.php) ────────────────────────────
require_once CHILD_APP_DIR . 'helpers/block-image-render.php';
require_once CHILD_APP_DIR . 'helpers/product-grid-render.php';
require_once CHILD_APP_DIR . 'helpers/journal-related-products-render.php';
require_once CHILD_APP_DIR . 'helpers/single-product-related-render.php';

// ── Chi tiết bài Journal: mục lục tự động + bình chọn/chia sẻ/bài liên quan/tag ──
require_once CHILD_APP_DIR . 'helpers/journal-single-render.php';

// kk-star-ratings không có bản dịch tiếng Việt — legend "{count} {votes}"
// cần hiện "31 bình chọn" giống XLIII.
add_filter('ngettext_kk-star-ratings', function ($translation, $single, $plural) {
    if ($single === 'vote' && currentLanguage() !== 'en') {
        return 'bình chọn';
    }
    return $translation;
}, 10, 3);

// ── Newsletter ở footer: bảng lưu email + AJAX đăng ký (đồng bộ Google Sheet
// / Mailchimp / Brevo nếu đã cấu hình) + trang admin xem/xuất CSV — xem
// theme/footer.php ───────────────────────────────────────────────────────────
require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterTexts.php';
require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterSubscribers.php';
require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterSync.php';
require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterMailer.php';
require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterEndpoints.php';
require_once CHILD_APP_DIR . 'src/Ajax/NewsletterAjaxHandler.php';
require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterAdminPage.php';

// ── Danh sách Google Font (subset tiếng Việt) + helper build URL, dùng cho
// variant "quote" của block Content/Quote (render.php tự enqueue theo lựa
// chọn admin) ─────────────────────────────────────────────────────────────
require_once CHILD_APP_DIR . 'helpers/google-fonts-vi.php';

// Section "Sản phẩm liên quan" ở single.php (Journal) VÀ ở
// woocommerce/single-product/related.php (single Product) đều tái dùng
// class CSS của block Product Grid nhưng gọi thẳng hàm PHP (không qua
// render_block()) nên WP không tự enqueue — phải enqueue tay, giống cách
// archive-journal.php đang làm với block CPT Grid ở dưới.
add_action('wp_enqueue_scripts', function () {
    if (is_singular('journal') || is_product()) {
        wp_enqueue_style('block-block-product-grid');
    }
});

// archive-journal.php và taxonomy-journal-cat.php dùng lại nguyên class CSS
// (.block-cpt-grid__*) của block CPT Grid, nhưng đây là template PHP thuần,
// không đi qua render_block() nên WP không tự biết mà enqueue style của
// block — phải enqueue tay handle đã được lacadev_child_register_synced_blocks()
// đăng ký sẵn (init priority 15, chạy trước wp_enqueue_scripts).
add_action('wp_enqueue_scripts', function () {
    if (is_post_type_archive('journal') || is_tax('journal-cat')) {
        wp_enqueue_style('block-block-cpt-grid');
    }
});

// ── PageSpeed Insights: Tối ưu hiệu năng tải trang ──────────────────────────
// 1. Chuyển các stylesheet không chặn render sang tải bất đồng bộ (khi Critical CSS đã inline)
add_filter('lacadev_async_style_handles', function ($handles) {
    return array_merge($handles, [
        'child-style',
        'child-dist-css',
        'child-theme-css',
        'woocommerce-general',
        'woocommerce-layout',
        'woocommerce-smallscreen',
        'kk-star-ratings',
        'wp-block-library',
    ]);
});

// 1d. Inline CSS của block custom (block-block-*, mỗi file 1-5KB) thay vì <link>:
// tải async thì trang nào có block không nằm trong critical của loại trang đó
// sẽ nhảy layout (vd Privacy Policy CLS 0.45); tải blocking thì thêm request chặn
// render. Gắn 'path' để wp_maybe_inline_styles() (core, wp_head/wp_footer prio 1)
// tự inline + chuẩn hoá url() tương đối.
$laca_inline_block_styles = function () {
    $wp_styles = wp_styles();
    foreach ($wp_styles->registered as $handle => $style) {
        if (strpos($handle, 'block-block-') !== 0 || !is_string($style->src) || $wp_styles->get_data($handle, 'path')) {
            continue;
        }
        $path = str_replace(content_url(), WP_CONTENT_DIR, strtok($style->src, '?'));
        if (is_readable($path)) {
            $wp_styles->add_data($handle, 'path', $path);
        }
    }
};
add_action('wp_enqueue_scripts', $laca_inline_block_styles, 999);
add_action('wp_footer', $laca_inline_block_styles, 0);

// Block Accordion của core render panel KHÔNG có hidden — JS (Interactivity API)
// mới gắn hidden sau khi chạy → mọi panel mở ra rồi thu lại (Privacy Policy CLS
// 0.45). Ẩn sẵn panel có nút aria-expanded="false"; JS đổi sang "true" khi mở.
// Gắn vào chính style inline của block nên chỉ in ra ở trang có Accordion.
add_action('wp_enqueue_scripts', function () {
    wp_add_inline_style(
        'wp-block-accordion-panel',
        '.wp-block-accordion-heading:has(.wp-block-accordion-heading__toggle[aria-expanded="false"])+.wp-block-accordion-panel{display:none}'
    );
}, 20);

// Tổng CSS inline mặc định 20KB/trang — trang nhiều block vượt thì phần còn lại
// quay về <link>. Nâng lên đủ cho các block của site (~80KB nếu dùng hết).
add_filter('styles_inline_size_limit', function () {
    return 100000;
});

// 1b. Critical CSS riêng theo loại trang (sinh bởi resources/build/critical.js) —
// 1 file gộp mọi template từng phình >100KB và vẫn thiếu rule của từng trang.
add_filter('lacadev_critical_css_path', function ($path, $dist_path) {
    if (is_front_page()) {
        $type = 'home';
    } elseif (function_exists('is_product') && is_product()) {
        $type = 'product';
    } elseif (function_exists('is_woocommerce') && is_woocommerce()) {
        $type = 'shop';
    } elseif (is_post_type_archive('journal') || is_tax(['journal-cat', 'journal-tags'])) {
        $type = 'journal';
    } elseif (is_singular(['journal', 'post'])) {
        $type = 'single';
    } elseif (is_singular() && has_block('lacadev/top-hero-block')) {
        $type = 'page-hero';
    } else {
        $type = 'page';
    }

    $typed_path = $dist_path . 'styles/critical-' . $type . '.css';
    return file_exists($typed_path) ? $typed_path : $path;
}, 10, 2);

// 1c. Preload ảnh LCP của block Top Hero đầu trang — <img> nằm sau header + nhiều
// <link> CSS nên trình duyệt phát hiện muộn; preload trong <head> cho tải ngay.
add_action('wp_head', function () {
    if (!is_singular() || !has_block('lacadev/top-hero-block')) {
        return;
    }
    foreach (parse_blocks(get_post()->post_content) as $block) {
        if (($block['blockName'] ?? '') !== 'lacadev/top-hero-block') {
            continue;
        }
        $image_id = (int) ($block['attrs']['heroImageId'] ?? 0);
        if (!$image_id) {
            return;
        }
        // Sinh đúng thẻ <img> như render.php rồi lấy srcset/sizes — filter ảnh của
        // parent có ghi đè sizes, lệch 1 chút là trình duyệt tải ảnh 2 lần.
        $img = wp_get_attachment_image($image_id, 'full', false, ['sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high']);
        preg_match('/\ssrc="([^"]+)"/', $img, $src);
        preg_match('/\ssrcset="([^"]+)"/', $img, $srcset);
        preg_match('/\ssizes="([^"]+)"/', $img, $sizes);
        if (empty($src[1])) {
            return;
        }
        printf(
            '<link rel="preload" as="image" href="%s"%s%s fetchpriority="high">' . "\n",
            esc_url($src[1]),
            !empty($srcset[1]) ? ' imagesrcset="' . esc_attr($srcset[1]) . '"' : '',
            !empty($sizes[1]) ? ' imagesizes="' . esc_attr($sizes[1]) . '"' : ''
        );
        return;
    }
}, 2);

// 2. Preload font hiển thị đầu trang (lấy hash từ manifest.json)
add_filter('lacadev_preload_fonts', function ($fonts) {
    $manifest_path = CHILD_DIST_DIR . 'manifest.json';
    if (!file_exists($manifest_path)) {
        return $fonts;
    }
    $manifest = json_decode(file_get_contents($manifest_path), true);
    if (!is_array($manifest)) {
        return $fonts;
    }

    $preload_keys = [
        'fonts/VNF-Futura.woff2',
        'fonts/Oswald-Regular.woff2',
    ];

    foreach ($preload_keys as $key) {
        if (!empty($manifest[$key])) {
            $fonts[] = $manifest[$key];
        }
    }

    return $fonts;
});

// 3. Tối ưu Resource Hints (bỏ preconnect/dns-prefetch bên ngoài không dùng)
add_filter('lacadev_resource_hints', function () {
    return [];
}, 10, 2);

// 4. Chuyển jQuery xuống footer và loại bỏ jquery-migrate ở frontend
add_action('wp_default_scripts', function ($scripts) {
    if (!is_admin() && !is_customize_preview() && isset($scripts->registered['jquery'])) {
        $jquery = $scripts->registered['jquery'];
        if ($jquery->deps) {
            $jquery->deps = array_diff($jquery->deps, ['jquery-migrate']);
        }
    }
});

add_action('wp_enqueue_scripts', function () {
    if (!is_admin() && !is_customize_preview()) {
        wp_scripts()->add_data('jquery', 'group', 1);
        wp_scripts()->add_data('jquery-core', 'group', 1);
        wp_scripts()->add_data('jquery-migrate', 'group', 1);
        // Script WooCommerce (defer) ở head kéo jQuery lên head và chặn render →
        // defer jQuery. Bỏ qua giỏ hàng/thanh toán/tài khoản: plugin thanh toán
        // (PayPal…) có thể in script jQuery blocking muộn, WP không tự hạ strategy
        // cho script enqueue sau khi head đã in.
        $is_wc_flow = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
        if (!$is_wc_flow) {
            wp_script_add_data('jquery-core', 'strategy', 'defer');
        }
    }
}, 1);

// kk-star-ratings phụ thuộc jQuery nhưng in blocking ở footer — phải defer theo,
// nếu không nó chạy trước jQuery (đã defer) và lỗi "jQuery is not defined".
add_action('wp_enqueue_scripts', function () {
    if (wp_script_is('kk-star-ratings', 'registered')) {
        wp_script_add_data('kk-star-ratings', 'strategy', 'defer');
    }
}, 100);

// 5. Dequeue CSS/JS không cần thiết trên từng trang
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || is_customize_preview()) {
        return;
    }

    // kk-star-ratings: chỉ giữ trên bài Journal / bài viết có tính năng rating
    if (!is_singular(['post', 'journal'])) {
        wp_dequeue_style('kk-star-ratings');
        wp_dequeue_script('kk-star-ratings');
    }

    // comment-reply: chỉ nạp khi trang mở bình luận và có thread comments
    if (!is_singular() || !comments_open() || !get_option('thread_comments')) {
        wp_dequeue_script('comment-reply');
    }

    // WooCommerce CSS + JS add-to-cart: chỉ cần trên trang WooCommerce hoặc trang có
    // block WooCommerce. Nút add-to-cart chỉ có ở template sản phẩm; mini cart
    // (cart-popup.js) gọi AJAX riêng, không dùng jQuery → bỏ các script này thì
    // jQuery cũng không còn dependent nên không bị tải (~120KB JS trên trang chủ).
    // Giữ sourcebuster + order-attribution để vẫn ghi nhận nguồn đơn hàng.
    if (function_exists('is_woocommerce')) {
        $is_woo_page = is_woocommerce() || is_cart() || is_checkout() || is_account_page();
        if (!$is_woo_page) {
            $post = get_post();
            $has_woo_block = $post && has_block('woocommerce/', $post);
            if (!$has_woo_block) {
                wp_dequeue_style('woocommerce-general');
                wp_dequeue_style('woocommerce-layout');
                wp_dequeue_style('woocommerce-smallscreen');
                foreach (['wc-add-to-cart', 'woocommerce', 'wc-jquery-blockui', 'jquery-blockui', 'wc-js-cookie', 'js-cookie'] as $wc_handle) {
                    wp_dequeue_script($wc_handle);
                }
            }
        }
    }
}, 99);

// 6. Tắt Emoji WordPress ở frontend
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
});
