<?php

namespace App\Features\LacaSeo\Admin\Metabox;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * Main SEO Metabox controller for Post/Page/CPT editor screens.
 * Supports Classic Editor & Gutenberg.
 * 4 Tabs: General (SERP preview, Title/Desc, Focus KW, Pillar, Score circle, Lock date, Hide score),
 * Advanced (Robots, Canonical, Breadcrumbs), Schema (placeholder), Social (Facebook, Twitter).
 */
class PostMetabox
{
    public const NONCE_ACTION = 'laca_seo_save_meta';
    public const NONCE_NAME   = 'laca_seo_meta_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'savePostMeta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueEditorAssets']);
        add_action('wp_ajax_laca_seo_is_keyword_new', [$this, 'ajaxIsKeywordNew']);

        (new LockModifiedDate())->register();
    }

    /**
     * Add meta box to enabled post types.
     */
    public function addMetaBoxes(): void
    {
        $postTypes = Helper::getAccessiblePostTypes();

        foreach ($postTypes as $slug => $postTypeObj) {
            $enabled = (string) Settings::get("titles.pt_{$slug}_add_meta_box", 'on');
            if ($enabled === 'on') {
                add_meta_box(
                    'laca_seo_metabox',
                    'Laca SEO',
                    [$this, 'renderMetabox'],
                    $slug,
                    'normal',
                    'high'
                );
            }
        }
    }

    /**
     * Enqueue editor assets on post.php and post-new.php
     */
    public function enqueueEditorAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || empty($screen->post_type)) {
            return;
        }

        $enabled = (string) Settings::get("titles.pt_{$screen->post_type}_add_meta_box", 'on');
        if ($enabled !== 'on') {
            return;
        }

        wp_enqueue_media();

        $theme_root_uri = dirname(get_stylesheet_directory_uri());
        $dist_dir = dirname(get_stylesheet_directory()) . '/dist/';

        if (file_exists($dist_dir . 'styles/laca-seo-editor.css')) {
            wp_enqueue_style(
                'laca-seo-editor',
                $theme_root_uri . '/dist/styles/laca-seo-editor.css',
                ['dashicons'],
                (string) filemtime($dist_dir . 'styles/laca-seo-editor.css')
            );
        }

        if (file_exists($dist_dir . 'laca-seo-editor.js')) {
            wp_enqueue_script(
                'laca-seo-editor',
                $theme_root_uri . '/dist/laca-seo-editor.js',
                ['wp-i18n', 'wp-element', 'wp-data', 'wp-wordcount', 'wp-url', 'wp-autop'],
                (string) filemtime($dist_dir . 'laca-seo-editor.js'),
                true
            );

            $post = get_post();
            $postId = $post ? $post->ID : 0;
            $pt = $post ? $post->post_type : $screen->post_type;
            $locale = substr(get_locale(), 0, 2);

            $isWooActive = class_exists('WooCommerce');
            $isReviewEnabled = $isWooActive && (get_option('woocommerce_enable_reviews', 'yes') === 'yes');
            $hasProductSchema = ($pt === 'product');

            wp_localize_script('laca-seo-editor', 'lacaSeoEditorData', [
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce('laca_seo_admin_nonce'),
                'postId'           => $postId,
                'postType'         => $pt,
                'locale'           => $locale,
                'homeUrl'          => home_url(),
                'permalink'        => $post ? get_permalink($post) : home_url(),
                'defaultTitleTpl'  => (string) Settings::get("titles.pt_{$pt}_title", '%title% %sep% %sitename%'),
                'defaultDescTpl'   => (string) Settings::get("titles.pt_{$pt}_description", '%excerpt%'),
                'primaryTax'       => (string) Settings::get("titles.pt_{$pt}_primary_taxonomy", 'off'),
                'variables'        => Variables::getAvailableList(),
                'powerWords'       => $this->getPowerWordsList($locale),
                'isWooActive'      => $isWooActive,
                'isReviewEnabled'  => $isReviewEnabled,
                'hasProductSchema' => $hasProductSchema,
            ]);
        }
    }

    /**
     * Power words list for analyzer loaded from locale file.
     *
     * @param string $locale
     * @return string[]
     */
    private function getPowerWordsList(string $locale = 'en'): array
    {
        $filePath = dirname(__DIR__, 6) . "/resources/data/laca-seo/powerwords/{$locale}.php";
        if (file_exists($filePath)) {
            $words = require $filePath;
            if (is_array($words)) {
                return array_values(array_map('strtolower', $words));
            }
        }
        return [];
    }

    /**
     * AJAX handler to check if focus keyword is already used elsewhere.
     */
    public function ajaxIsKeywordNew(): void
    {
        check_ajax_referer('laca_seo_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        global $wpdb;
        $keyword = sanitize_text_field(wp_unslash($_POST['keyword'] ?? ''));
        $postId  = (int) ($_POST['post_id'] ?? 0);

        if ($keyword === '') {
            wp_send_json_success(['isNew' => true]);
            return;
        }

        // Cache lookup via transient
        $cacheKey = 'laca_seo_kwnew_' . md5(mb_strtolower($keyword) . '_' . $postId);
        $cached = wp_cache_get($cacheKey, 'laca_seo');
        if ($cached !== false) {
            wp_send_json_success(['isNew' => (bool) $cached]);
            return;
        }

        // Query posts with matching focus keyword
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_laca_seo_focus_keyword' AND meta_value LIKE %s AND post_id != %d LIMIT 1",
            '%' . $wpdb->esc_like($keyword) . '%',
            $postId
        );
        $found = $wpdb->get_var($query);

        $isNew = empty($found);
        wp_cache_set($cacheKey, $isNew ? 1 : 0, 'laca_seo', 300);

        wp_send_json_success(['isNew' => $isNew]);
    }

    /**
     * Render the 4-tab metabox HTML.
     */
    public function renderMetabox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $meta = [
            'title'              => (string) get_post_meta($post->ID, '_laca_seo_title', true),
            'description'        => (string) get_post_meta($post->ID, '_laca_seo_description', true),
            'focus_keyword'      => (string) get_post_meta($post->ID, '_laca_seo_focus_keyword', true),
            'is_pillar_content'  => (string) get_post_meta($post->ID, '_laca_seo_is_pillar_content', true),
            'seo_score'          => (int) get_post_meta($post->ID, '_laca_seo_seo_score', true),
            'lock_modified_date' => (string) get_post_meta($post->ID, '_laca_seo_lock_modified_date', true),
            'dont_show_seo_score'=> (string) get_post_meta($post->ID, '_laca_seo_dont_show_seo_score', true),
            'robots'             => (array) get_post_meta($post->ID, '_laca_seo_robots', true),
            'advanced_robots'    => (array) get_post_meta($post->ID, '_laca_seo_advanced_robots', true),
            'canonical_url'      => (string) get_post_meta($post->ID, '_laca_seo_canonical_url', true),
            'breadcrumb_title'   => (string) get_post_meta($post->ID, '_laca_seo_breadcrumb_title', true),
            'facebook_title'     => (string) get_post_meta($post->ID, '_laca_seo_facebook_title', true),
            'facebook_description' => (string) get_post_meta($post->ID, '_laca_seo_facebook_description', true),
            'facebook_image'     => (string) get_post_meta($post->ID, '_laca_seo_facebook_image', true),
            'facebook_image_id'  => (string) get_post_meta($post->ID, '_laca_seo_facebook_image_id', true),
            'twitter_use_facebook' => (string) get_post_meta($post->ID, '_laca_seo_twitter_use_facebook', true),
            'twitter_card_type'  => (string) get_post_meta($post->ID, '_laca_seo_twitter_card_type', true),
            'twitter_title'      => (string) get_post_meta($post->ID, '_laca_seo_twitter_title', true),
            'twitter_description'=> (string) get_post_meta($post->ID, '_laca_seo_twitter_description', true),
            'twitter_image'      => (string) get_post_meta($post->ID, '_laca_seo_twitter_image', true),
            'twitter_image_id'   => (string) get_post_meta($post->ID, '_laca_seo_twitter_image_id', true),
        ];

        if (empty($meta['twitter_card_type'])) {
            $meta['twitter_card_type'] = 'summary_large_image';
        }
        if ($meta['twitter_use_facebook'] === '') {
            $meta['twitter_use_facebook'] = 'on';
        }

        $pt = $post->post_type;
        $schemaDefault = (string) Settings::get("titles.pt_{$pt}_default_rich_snippet", 'article');

        $primaryTax = (string) Settings::get("titles.pt_{$pt}_primary_taxonomy", 'off');
        $primaryTermId = ($primaryTax !== 'off' && $primaryTax !== '') ? (int) get_post_meta($post->ID, "_laca_seo_primary_{$primaryTax}", true) : 0;

        ?>
        <div class="laca-seo-metabox-wrapper" id="laca_seo_metabox_app" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <!-- Hidden Score Input -->
            <input type="hidden" name="laca_seo[_laca_seo_seo_score]" id="laca_seo_score_input" value="<?php echo esc_attr($meta['seo_score']); ?>">

            <!-- Tab Headers -->
            <div class="laca-seo-tabs-nav" role="tablist">
                <button type="button" role="tab" class="laca-seo-tab-btn active" data-tab="general" aria-selected="true">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Chung', 'lacadev'); ?>
                    <span class="laca-seo-tab-score-badge" id="laca_seo_tab_score"><?php echo esc_html($meta['seo_score'] ?: 0); ?>/100</span>
                </button>
                <button type="button" role="tab" class="laca-seo-tab-btn" data-tab="advanced" aria-selected="false">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Nâng cao', 'lacadev'); ?>
                </button>
                <button type="button" role="tab" class="laca-seo-tab-btn" data-tab="schema" aria-selected="false">
                    <span class="dashicons dashicons-media-code"></span> <?php esc_html_e('Schema', 'lacadev'); ?>
                </button>
                <button type="button" role="tab" class="laca-seo-tab-btn" data-tab="social" aria-selected="false">
                    <span class="dashicons dashicons-share"></span> <?php esc_html_e('Mạng xã hội', 'lacadev'); ?>
                </button>
            </div>

            <!-- Tab 1: General -->
            <div class="laca-seo-tab-content active" id="laca_seo_tab_general" role="tabpanel">
                <!-- SERP Preview -->
                <div class="laca-seo-serp-preview-box">
                    <div class="laca-seo-serp-header">
                        <span class="laca-seo-serp-title-label"><?php esc_html_e('Xem trước kết quả tìm kiếm Google (SERP Preview)', 'lacadev'); ?></span>
                        <div class="laca-seo-serp-devices">
                            <button type="button" class="laca-serp-btn active" data-device="desktop">
                                <span class="dashicons dashicons-desktop"></span> Desktop
                            </button>
                            <button type="button" class="laca-serp-btn" data-device="mobile">
                                <span class="dashicons dashicons-smartphone"></span> Mobile
                            </button>
                        </div>
                    </div>
                    <div class="laca-serp-card laca-serp-card--desktop" id="laca_serp_card">
                        <div class="laca-serp-site-info">
                            <span class="laca-serp-favicon dashicons dashicons-admin-site-alt3"></span>
                            <span class="laca-serp-domain"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></span>
                            <span class="laca-serp-breadcrumb-path" id="laca_serp_path"> › <?php echo esc_html($post->post_name ?: 'post-title'); ?></span>
                        </div>
                        <h4 class="laca-serp-headline" id="laca_serp_title"><?php echo esc_html($meta['title'] ?: ($post->post_title ?: 'Tiêu đề bài viết')); ?></h4>
                        <p class="laca-serp-snippet" id="laca_serp_desc"><?php echo esc_html($meta['description'] ?: 'Nhập đoạn mô tả SEO để xem hiển thị trên kết quả tìm kiếm của Google...'); ?></p>
                    </div>
                </div>

                <!-- Title & Description Fields -->
                <div class="laca-seo-editor-fields">
                    <div class="laca-seo-field-group">
                        <div class="laca-seo-field-header">
                            <label for="laca_seo_title_input"><?php esc_html_e('Tiêu đề SEO (Title Tag)', 'lacadev'); ?></label>
                            <div class="laca-seo-counter" id="laca_seo_title_counter">
                                <span class="laca-counter-val">0</span> / 60 ký tự (<span class="laca-pixel-val">0</span> / 580px)
                            </div>
                        </div>
                        <div class="laca-seo-var-field" data-field-id="post_title">
                            <div class="laca-seo-var-field__input-wrap">
                                <input type="text" id="laca_seo_title_input" name="laca_seo[_laca_seo_title]" value="<?php echo esc_attr($meta['title']); ?>" class="regular-text laca-seo-input laca-seo-var-input" placeholder="<?php echo esc_attr((string) Settings::get("titles.pt_{$pt}_title", '%title% %sep% %sitename%')); ?>">
                                <div class="laca-seo-var-dropdown-wrap">
                                    <button type="button" class="button laca-seo-var-insert-btn" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="dashicons dashicons-plus"></span> <?php esc_html_e('Chèn biến', 'lacadev'); ?>
                                    </button>
                                    <ul class="laca-seo-var-list" role="listbox" hidden>
                                        <?php foreach (Variables::getAvailableList() as $varItem): ?>
                                            <li role="option" data-var="%<?php echo esc_attr($varItem['id']); ?>%">
                                                <strong>%<?php echo esc_html($varItem['id']); ?>%</strong>
                                                <span><?php echo esc_html($varItem['name']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="laca-seo-field-group">
                        <div class="laca-seo-field-header">
                            <label for="laca_seo_desc_input"><?php esc_html_e('Mô tả SEO (Meta Description)', 'lacadev'); ?></label>
                            <div class="laca-seo-counter" id="laca_seo_desc_counter">
                                <span class="laca-counter-val">0</span> / 160 ký tự (<span class="laca-pixel-val">0</span> / 960px)
                            </div>
                        </div>
                        <div class="laca-seo-var-field" data-field-id="post_description">
                            <div class="laca-seo-var-field__input-wrap">
                                <textarea id="laca_seo_desc_input" name="laca_seo[_laca_seo_description]" rows="3" class="large-text laca-seo-input laca-seo-var-input" placeholder="<?php echo esc_attr((string) Settings::get("titles.pt_{$pt}_description", '%excerpt%')); ?>"><?php echo esc_textarea($meta['description']); ?></textarea>
                                <div class="laca-seo-var-dropdown-wrap">
                                    <button type="button" class="button laca-seo-var-insert-btn" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="dashicons dashicons-plus"></span> <?php esc_html_e('Chèn biến', 'lacadev'); ?>
                                    </button>
                                    <ul class="laca-seo-var-list" role="listbox" hidden>
                                        <?php foreach (Variables::getAvailableList() as $varItem): ?>
                                            <li role="option" data-var="%<?php echo esc_attr($varItem['id']); ?>%">
                                                <strong>%<?php echo esc_html($varItem['id']); ?>%</strong>
                                                <span><?php echo esc_html($varItem['name']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Focus Keywords & Pillar Content -->
                    <div class="laca-seo-field-group">
                        <label><?php esc_html_e('Từ khoá chính (Focus Keywords, tối đa 100 từ - Từ đầu tiên là từ khoá chính)', 'lacadev'); ?></label>
                        <div class="laca-seo-tags-input" id="laca_seo_tags_input">
                            <div class="laca-tags-list" id="laca_tags_list"></div>
                            <input type="text" id="laca_kw_type_input" class="laca-kw-input" placeholder="<?php esc_attr_e('Nhập từ khoá rồi nhấn Enter hoặc dấu phẩy...', 'lacadev'); ?>">
                        </div>
                        <input type="hidden" name="laca_seo[_laca_seo_focus_keyword]" id="laca_seo_focus_kw_hidden" value="<?php echo esc_attr($meta['focus_keyword']); ?>">

                        <div class="laca-seo-checkbox-row">
                            <label class="laca-checkbox-inline">
                                <input type="checkbox" name="laca_seo[_laca_seo_is_pillar_content]" value="on" <?php checked($meta['is_pillar_content'], 'on'); ?>>
                                <strong><?php esc_html_e('Bài viết trụ cột (Pillar Content)', 'lacadev'); ?></strong>
                                <span class="description"><?php esc_html_e('Đánh dấu bài viết nền tảng chứa nội dung quan trọng nhất.', 'lacadev'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Primary Term Picker (Hierarchical Taxonomies) -->
                    <?php if ($primaryTax !== 'off' && $primaryTax !== '' && taxonomy_exists($primaryTax)): ?>
                        <?php
                        $taxObj = get_taxonomy($primaryTax);
                        $terms = get_terms(['taxonomy' => $primaryTax, 'hide_empty' => false]);
                        ?>
                        <div class="laca-seo-field-group">
                            <label for="laca_seo_primary_tax_select"><?php printf(esc_html__('Phân loại chính (%s)', 'lacadev'), esc_html($taxObj->labels->singular_name ?: $primaryTax)); ?></label>
                            <select name="laca_seo[_laca_seo_primary_<?php echo esc_attr($primaryTax); ?>]" id="laca_seo_primary_tax_select" class="regular-text">
                                <option value="0"><?php esc_html_e('— Chọn phân loại chính —', 'lacadev'); ?></option>
                                <?php if (!is_wp_error($terms)): ?>
                                    <?php foreach ($terms as $t): ?>
                                        <option value="<?php echo esc_attr($t->term_id); ?>" <?php selected($primaryTermId, $t->term_id); ?>>
                                            <?php echo esc_html($t->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Dùng làm danh mục đại diện trên đường dẫn Breadcrumb và biến %primary_taxonomy_terms%.', 'lacadev'); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Lock modified date & Hide Frontend Score -->
                    <div class="laca-seo-options-grid">
                        <label class="laca-checkbox-inline">
                            <input type="checkbox" name="laca_seo[_laca_seo_lock_modified_date]" value="on" <?php checked($meta['lock_modified_date'], 'on'); ?>>
                            <span><?php esc_html_e('Khoá ngày cập nhật (Không đổi ngày sửa khi bấm Cập nhật)', 'lacadev'); ?></span>
                        </label>
                        <label class="laca-checkbox-inline">
                            <input type="checkbox" name="laca_seo[_laca_seo_dont_show_seo_score]" value="on" <?php checked($meta['dont_show_seo_score'], 'on'); ?>>
                            <span><?php esc_html_e('Ẩn huy hiệu điểm SEO ngoài Frontend cho bài này', 'lacadev'); ?></span>
                        </label>
                    </div>

                    <!-- SEO Analysis Report (4 Groups) -->
                    <div class="laca-seo-analysis-container" id="laca_seo_analysis_box">
                        <div class="laca-analysis-header">
                            <div class="laca-score-circle-wrap">
                                <div class="laca-score-circle laca-score--<?php echo esc_attr($meta['seo_score'] >= 80 ? 'good' : ($meta['seo_score'] >= 50 ? 'ok' : 'bad')); ?>" id="laca_score_circle">
                                    <span class="laca-score-circle__number" id="laca_score_number"><?php echo esc_html($meta['seo_score'] ?: 0); ?></span>
                                    <span class="laca-score-circle__total">/100</span>
                                </div>
                            </div>
                            <div class="laca-score-intro">
                                <h4 class="laca-score-status" id="laca_score_status_text"><?php esc_html_e('Đánh giá tối ưu SEO nội dung', 'lacadev'); ?></h4>
                                <p class="description"><?php esc_html_e('Các chỉ số phân tích sẽ tự động cập nhật trong thời gian thực khi bạn chỉnh sửa tiêu đề, mô tả và nội dung bài viết.', 'lacadev'); ?></p>
                            </div>
                        </div>

                        <!-- 4 Test Groups Accordion -->
                        <div class="laca-test-groups">
                            <div class="laca-test-group" data-group="basic">
                                <div class="laca-group-header">
                                    <span class="dashicons dashicons-arrow-right-alt2 laca-group-arrow"></span>
                                    <strong><?php esc_html_e('SEO cơ bản (Basic SEO)', 'lacadev'); ?></strong>
                                    <span class="laca-group-count" id="count_basic">0/6</span>
                                </div>
                                <div class="laca-group-body" id="group_basic_tests"></div>
                            </div>

                            <div class="laca-test-group" data-group="additional">
                                <div class="laca-group-header">
                                    <span class="dashicons dashicons-arrow-right-alt2 laca-group-arrow"></span>
                                    <strong><?php esc_html_e('SEO bổ sung (Additional SEO)', 'lacadev'); ?></strong>
                                    <span class="laca-group-count" id="count_additional">0/8</span>
                                </div>
                                <div class="laca-group-body" id="group_additional_tests"></div>
                            </div>

                            <div class="laca-test-group" data-group="title">
                                <div class="laca-group-header">
                                    <span class="dashicons dashicons-arrow-right-alt2 laca-group-arrow"></span>
                                    <strong><?php esc_html_e('Độ hấp dẫn tiêu đề (Title Readability)', 'lacadev'); ?></strong>
                                    <span class="laca-group-count" id="count_title">0/4</span>
                                </div>
                                <div class="laca-group-body" id="group_title_tests"></div>
                            </div>

                            <div class="laca-test-group" data-group="content">
                                <div class="laca-group-header">
                                    <span class="dashicons dashicons-arrow-right-alt2 laca-group-arrow"></span>
                                    <strong><?php esc_html_e('Khả năng đọc nội dung (Content Readability)', 'lacadev'); ?></strong>
                                    <span class="laca-group-count" id="count_content">0/3</span>
                                </div>
                                <div class="laca-group-body" id="group_content_tests"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Advanced -->
            <div class="laca-seo-tab-content" id="laca_seo_tab_advanced" role="tabpanel" hidden>
                <input type="hidden" name="laca_seo_advanced_present" value="1">
                <div class="laca-seo-field-group">
                    <label><strong><?php esc_html_e('Chỉ thị Robots Meta', 'lacadev'); ?></strong></label>
                    <div class="laca-checkbox-grid">
                        <?php
                        $robotsList = Helper::choicesRobots();
                        $savedRobots = (array) $meta['robots'];
                        foreach ($robotsList as $rKey => $rLabel):
                            $isChecked = in_array($rKey, $savedRobots, true);
                        ?>
                            <label class="laca-checkbox-item">
                                <input type="checkbox" name="laca_seo[_laca_seo_robots][]" value="<?php echo esc_attr($rKey); ?>" <?php checked($isChecked); ?>>
                                <span><?php echo esc_html($rLabel); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php esc_html_e('Để trống để sử dụng chỉ thị mặc định của loại bài viết.', 'lacadev'); ?></p>
                </div>

                <div class="laca-seo-field-group">
                    <label><strong><?php esc_html_e('Advanced Robots Meta', 'lacadev'); ?></strong></label>
                    <?php
                    $adv = (array) $meta['advanced_robots'];
                    $maxSnippet = isset($adv['max-snippet']) ? (int) $adv['max-snippet'] : -1;
                    $maxVideo   = isset($adv['max-video-preview']) ? (int) $adv['max-video-preview'] : -1;
                    $maxImage   = isset($adv['max-image-preview']) ? (string) $adv['max-image-preview'] : 'large';
                    ?>
                    <div class="laca-adv-robots-grid">
                        <div class="laca-adv-field">
                            <label><?php esc_html_e('Max Snippet (ký tự)', 'lacadev'); ?></label>
                            <input type="number" name="laca_seo[_laca_seo_advanced_robots][max-snippet]" value="<?php echo esc_attr($maxSnippet); ?>" class="small-text">
                        </div>
                        <div class="laca-adv-field">
                            <label><?php esc_html_e('Max Video Preview (giây)', 'lacadev'); ?></label>
                            <input type="number" name="laca_seo[_laca_seo_advanced_robots][max-video-preview]" value="<?php echo esc_attr($maxVideo); ?>" class="small-text">
                        </div>
                        <div class="laca-adv-field">
                            <label><?php esc_html_e('Max Image Preview', 'lacadev'); ?></label>
                            <select name="laca_seo[_laca_seo_advanced_robots][max-image-preview]">
                                <option value="large" <?php selected($maxImage, 'large'); ?>>Large (Mặc định)</option>
                                <option value="standard" <?php selected($maxImage, 'standard'); ?>>Standard</option>
                                <option value="none" <?php selected($maxImage, 'none'); ?>>None</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="laca-seo-field-group">
                    <label for="laca_seo_canonical_url"><strong><?php esc_html_e('Canonical URL (Đường dẫn chuẩn)', 'lacadev'); ?></strong></label>
                    <input type="url" id="laca_seo_canonical_url" name="laca_seo[_laca_seo_canonical_url]" value="<?php echo esc_attr($meta['canonical_url']); ?>" class="large-text" placeholder="<?php echo esc_attr(get_permalink($post)); ?>">
                    <p class="description"><?php esc_html_e('Điền URL này nếu bài viết sao chép từ một nguồn gốc khác hoặc muốn chỉ định trang gốc cho Googlebot.', 'lacadev'); ?></p>
                </div>

                <div class="laca-seo-field-group">
                    <label for="laca_seo_breadcrumb_title"><strong><?php esc_html_e('Tiêu đề Breadcrumb tuỳ biến', 'lacadev'); ?></strong></label>
                    <input type="text" id="laca_seo_breadcrumb_title" name="laca_seo[_laca_seo_breadcrumb_title]" value="<?php echo esc_attr($meta['breadcrumb_title']); ?>" class="regular-text" placeholder="<?php echo esc_attr($post->post_title); ?>">
                    <p class="description"><?php esc_html_e('Tiêu đề ngắn gọn hiển thị trên thanh Breadcrumb thay cho tiêu đề đầy đủ.', 'lacadev'); ?></p>
                </div>
            </div>

            <!-- Tab 3: Schema (Placeholder Phase 3) -->
            <div class="laca-seo-tab-content" id="laca_seo_tab_schema" role="tabpanel" hidden>
                <div class="laca-schema-placeholder">
                    <span class="dashicons dashicons-media-code laca-placeholder-icon"></span>
                    <h3><?php esc_html_e('Trình tạo Schema Markup Generator', 'lacadev'); ?></h3>
                    <p class="description">
                        <?php printf(esc_html__('Loại Schema hiện đang được gán tự động cho bài viết này: %s', 'lacadev'), '<strong>' . esc_html(strtoupper($schemaDefault)) . '</strong>'); ?>
                    </p>
                    <div class="laca-phase-badge">
                        <span><?php esc_html_e('Tính năng chỉnh sửa Schema tùy biến chi tiết sẽ có trong Phase 3.', 'lacadev'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Social (Facebook & Twitter) -->
            <div class="laca-seo-tab-content" id="laca_seo_tab_social" role="tabpanel" hidden>
                <div class="laca-social-accordion">
                    <!-- Facebook Section -->
                    <div class="laca-social-section">
                        <h4><span class="dashicons dashicons-facebook"></span> <?php esc_html_e('Cấu hình chia sẻ Facebook (OpenGraph)', 'lacadev'); ?></h4>
                        <div class="laca-seo-field-group">
                            <label for="laca_seo_fb_title"><?php esc_html_e('Tiêu đề Facebook', 'lacadev'); ?></label>
                            <input type="text" id="laca_seo_fb_title" name="laca_seo[_laca_seo_facebook_title]" value="<?php echo esc_attr($meta['facebook_title']); ?>" class="regular-text" placeholder="<?php echo esc_attr($meta['title'] ?: $post->post_title); ?>">
                        </div>
                        <div class="laca-seo-field-group">
                            <label for="laca_seo_fb_desc"><?php esc_html_e('Mô tả Facebook', 'lacadev'); ?></label>
                            <textarea id="laca_seo_fb_desc" name="laca_seo[_laca_seo_facebook_description]" rows="3" class="large-text" placeholder="<?php echo esc_attr($meta['description']); ?>"><?php echo esc_textarea($meta['facebook_description']); ?></textarea>
                        </div>
                        <div class="laca-seo-field-group">
                            <label><?php esc_html_e('Hình ảnh chia sẻ Facebook (Tối ưu: 1200 x 630px)', 'lacadev'); ?></label>
                            <div class="laca-image-upload-wrap">
                                <input type="hidden" name="laca_seo[_laca_seo_facebook_image]" id="laca_seo_fb_img" value="<?php echo esc_attr($meta['facebook_image']); ?>">
                                <input type="hidden" name="laca_seo[_laca_seo_facebook_image_id]" id="laca_seo_fb_img_id" value="<?php echo esc_attr($meta['facebook_image_id']); ?>">
                                <div class="laca-img-preview" id="laca_fb_img_preview">
                                    <?php if (!empty($meta['facebook_image'])): ?>
                                        <img src="<?php echo esc_url($meta['facebook_image']); ?>" alt="Facebook Image">
                                    <?php endif; ?>
                                </div>
                                <div class="laca-img-actions">
                                    <button type="button" class="button laca-upload-media-btn" data-target="fb"><?php esc_html_e('Chọn ảnh', 'lacadev'); ?></button>
                                    <button type="button" class="button laca-remove-media-btn" data-target="fb" <?php echo empty($meta['facebook_image']) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Gỡ bỏ', 'lacadev'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Twitter Section -->
                    <div class="laca-social-section">
                        <h4><span class="dashicons dashicons-twitter"></span> <?php esc_html_e('Cấu hình chia sẻ Twitter / X', 'lacadev'); ?></h4>
                        <div class="laca-seo-field-group">
                            <label class="laca-checkbox-inline">
                                <input type="checkbox" name="laca_seo[_laca_seo_twitter_use_facebook]" id="laca_twitter_use_fb" value="on" <?php checked($meta['twitter_use_facebook'], 'on'); ?>>
                                <strong><?php esc_html_e('Sử dụng lại dữ liệu của Facebook', 'lacadev'); ?></strong>
                            </label>
                        </div>

                        <div id="laca_twitter_custom_fields" <?php echo $meta['twitter_use_facebook'] === 'on' ? 'style="display:none;"' : ''; ?>>
                            <div class="laca-seo-field-group">
                                <label for="laca_seo_twitter_card"><?php esc_html_e('Loại thẻ Twitter Card', 'lacadev'); ?></label>
                                <select name="laca_seo[_laca_seo_twitter_card_type]" id="laca_seo_twitter_card">
                                    <option value="summary_large_image" <?php selected($meta['twitter_card_type'], 'summary_large_image'); ?>>Summary with Large Image (Ảnh lớn)</option>
                                    <option value="summary_card" <?php selected(in_array($meta['twitter_card_type'], ['summary', 'summary_card'], true), true); ?>>Summary (Ảnh nhỏ)</option>
                                    <option value="app" <?php selected($meta['twitter_card_type'], 'app'); ?>>App</option>
                                    <option value="player" <?php selected($meta['twitter_card_type'], 'player'); ?>>Player</option>
                                </select>
                            </div>
                            <div class="laca-seo-field-group">
                                <label for="laca_seo_tw_title"><?php esc_html_e('Tiêu đề Twitter', 'lacadev'); ?></label>
                                <input type="text" id="laca_seo_tw_title" name="laca_seo[_laca_seo_twitter_title]" value="<?php echo esc_attr($meta['twitter_title']); ?>" class="regular-text">
                            </div>
                            <div class="laca-seo-field-group">
                                <label for="laca_seo_tw_desc"><?php esc_html_e('Mô tả Twitter', 'lacadev'); ?></label>
                                <textarea id="laca_seo_tw_desc" name="laca_seo[_laca_seo_twitter_description]" rows="3" class="large-text"><?php echo esc_textarea($meta['twitter_description']); ?></textarea>
                            </div>
                            <div class="laca-seo-field-group">
                                <label><?php esc_html_e('Hình ảnh Twitter', 'lacadev'); ?></label>
                                <div class="laca-image-upload-wrap">
                                    <input type="hidden" name="laca_seo[_laca_seo_twitter_image]" id="laca_seo_tw_img" value="<?php echo esc_attr($meta['twitter_image']); ?>">
                                    <input type="hidden" name="laca_seo[_laca_seo_twitter_image_id]" id="laca_seo_tw_img_id" value="<?php echo esc_attr($meta['twitter_image_id']); ?>">
                                    <div class="laca-img-preview" id="laca_tw_img_preview">
                                        <?php if (!empty($meta['twitter_image'])): ?>
                                            <img src="<?php echo esc_url($meta['twitter_image']); ?>" alt="Twitter Image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="laca-img-actions">
                                        <button type="button" class="button laca-upload-media-btn" data-target="tw"><?php esc_html_e('Chọn ảnh', 'lacadev'); ?></button>
                                        <button type="button" class="button laca-remove-media-btn" data-target="tw" <?php echo empty($meta['twitter_image']) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Gỡ bỏ', 'lacadev'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save post meta handler.
     */
    public function savePostMeta(int $postId, \WP_Post $post): void
    {
        // 1. Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        // 2. Check Quick Edit Nonce
        $isQuickEdit = isset($_POST['_laca_seo_quick_edit_nonce']) && wp_verify_nonce($_POST['_laca_seo_quick_edit_nonce'], 'laca_seo_quick_edit');
        $isMetabox = isset($_POST[self::NONCE_NAME]) && wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION);

        if (!$isQuickEdit && !$isMetabox) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $input = $_POST['laca_seo'] ?? [];
        if (!is_array($input)) {
            return;
        }

        // Handle Quick Edit exclusively
        if ($isQuickEdit) {
            if (isset($input['_laca_seo_title'])) {
                $val = sanitize_text_field(wp_unslash($input['_laca_seo_title']));
                if ($val !== '') {
                    update_post_meta($postId, '_laca_seo_title', $val);
                } else {
                    delete_post_meta($postId, '_laca_seo_title');
                }
            }
            if (isset($input['_laca_seo_description'])) {
                $val = sanitize_text_field(wp_unslash($input['_laca_seo_description']));
                if ($val !== '') {
                    update_post_meta($postId, '_laca_seo_description', $val);
                } else {
                    delete_post_meta($postId, '_laca_seo_description');
                }
            }
            if (isset($input['_laca_seo_canonical_url'])) {
                $val = esc_url_raw(trim(wp_unslash($input['_laca_seo_canonical_url'])));
                if ($val !== '') {
                    update_post_meta($postId, '_laca_seo_canonical_url', $val);
                } else {
                    delete_post_meta($postId, '_laca_seo_canonical_url');
                }
            }
            if (isset($input['_laca_seo_focus_keyword'])) {
                $rawKw = (string) $input['_laca_seo_focus_keyword'];
                $kwArray = array_map('trim', explode(',', $rawKw));
                $cleanArray = [];
                foreach ($kwArray as $kw) {
                    $kw = sanitize_text_field($kw);
                    if ($kw !== '' && !in_array($kw, $cleanArray, true)) {
                        $cleanArray[] = $kw;
                    }
                    if (count($cleanArray) >= 100) {
                        break;
                    }
                }
                $cleanKw = implode(',', $cleanArray);
                if ($cleanKw !== '') {
                    update_post_meta($postId, '_laca_seo_focus_keyword', $cleanKw);
                } else {
                    delete_post_meta($postId, '_laca_seo_focus_keyword');
                }
            }
            return;
        }

        // Full Metabox Saving (below)
        // 3. String fields
        $textFields = [
            '_laca_seo_title',
            '_laca_seo_description',
            '_laca_seo_breadcrumb_title',
            '_laca_seo_facebook_title',
            '_laca_seo_facebook_description',
            '_laca_seo_twitter_title',
            '_laca_seo_twitter_description',
        ];

        foreach ($textFields as $key) {
            if (isset($input[$key])) {
                $val = sanitize_text_field(wp_unslash($input[$key]));
                if ($val !== '') {
                    update_post_meta($postId, $key, $val);
                } else {
                    delete_post_meta($postId, $key);
                }
            }
        }

        // 4. URL & Image fields
        $urlFields = [
            '_laca_seo_canonical_url',
            '_laca_seo_facebook_image',
            '_laca_seo_twitter_image',
        ];
        foreach ($urlFields as $key) {
            if (isset($input[$key])) {
                $val = esc_url_raw(trim(wp_unslash($input[$key])));
                if ($val !== '') {
                    update_post_meta($postId, $key, $val);
                } else {
                    delete_post_meta($postId, $key);
                }
            }
        }

        // 5. Image IDs
        foreach (['_laca_seo_facebook_image_id', '_laca_seo_twitter_image_id'] as $key) {
            if (isset($input[$key])) {
                $val = absint($input[$key]);
                if ($val > 0) {
                    update_post_meta($postId, $key, $val);
                } else {
                    delete_post_meta($postId, $key);
                }
            }
        }

        // 6. Focus Keywords: max 100, trim, unique (stored comma-separated without space matching RM)
        if (isset($input['_laca_seo_focus_keyword'])) {
            $rawKw = (string) $input['_laca_seo_focus_keyword'];
            $kwArray = array_map('trim', explode(',', $rawKw));
            $cleanArray = [];
            foreach ($kwArray as $kw) {
                $kw = sanitize_text_field($kw);
                if ($kw !== '' && !in_array($kw, $cleanArray, true)) {
                    $cleanArray[] = $kw;
                }
                if (count($cleanArray) >= 100) {
                    break;
                }
            }
            $cleanKw = implode(',', $cleanArray);
            if ($cleanKw !== '') {
                update_post_meta($postId, '_laca_seo_focus_keyword', $cleanKw);
            } else {
                delete_post_meta($postId, '_laca_seo_focus_keyword');
            }
        }

        // 7. Checkboxes / Toggles
        $toggleKeys = [
            '_laca_seo_is_pillar_content',
            '_laca_seo_lock_modified_date',
            '_laca_seo_dont_show_seo_score',
        ];
        foreach ($toggleKeys as $key) {
            if (isset($input[$key]) && ($input[$key] === 'on' || $input[$key] === '1')) {
                update_post_meta($postId, $key, 'on');
            } else {
                delete_post_meta($postId, $key);
            }
        }

        // Explicit on/off for twitter_use_facebook
        if (isset($input['_laca_seo_twitter_use_facebook']) && ($input['_laca_seo_twitter_use_facebook'] === 'on' || $input['_laca_seo_twitter_use_facebook'] === '1')) {
            update_post_meta($postId, '_laca_seo_twitter_use_facebook', 'on');
        } else {
            update_post_meta($postId, '_laca_seo_twitter_use_facebook', 'off');
        }

        // 8. Twitter Card Type
        if (isset($input['_laca_seo_twitter_card_type'])) {
            $cardType = sanitize_key($input['_laca_seo_twitter_card_type']);
            if (in_array($cardType, ['summary', 'summary_card', 'summary_large_image', 'app', 'player'], true)) {
                update_post_meta($postId, '_laca_seo_twitter_card_type', $cardType);
            }
        }

        // 9. SEO Score
        if (isset($input['_laca_seo_seo_score'])) {
            $score = absint($input['_laca_seo_seo_score']);
            if ($score > 0) {
                update_post_meta($postId, '_laca_seo_seo_score', $score);
            } else {
                delete_post_meta($postId, '_laca_seo_seo_score');
            }
        }

        // 10. Robots: whitelist check
        if (isset($input['_laca_seo_robots'])) {
            $allowedRobots = array_keys(Helper::choicesRobots());
            $robots = is_array($input['_laca_seo_robots']) ? $input['_laca_seo_robots'] : [];
            $validRobots = array_values(array_intersect($robots, $allowedRobots));
            if (!empty($validRobots)) {
                update_post_meta($postId, '_laca_seo_robots', $validRobots);
            } else {
                delete_post_meta($postId, '_laca_seo_robots');
            }
        } else {
            // If advanced tab was loaded and checkboxes unselected
            if (isset($_POST['laca_seo_advanced_present'])) {
                delete_post_meta($postId, '_laca_seo_robots');
            }
        }

        // 11. Advanced Robots
        if (isset($input['_laca_seo_advanced_robots']) && is_array($input['_laca_seo_advanced_robots'])) {
            $adv = $input['_laca_seo_advanced_robots'];
            $cleanAdv = [
                'max-snippet'       => isset($adv['max-snippet']) ? (int) $adv['max-snippet'] : -1,
                'max-video-preview' => isset($adv['max-video-preview']) ? (int) $adv['max-video-preview'] : -1,
                'max-image-preview' => in_array($adv['max-image-preview'] ?? '', ['large', 'standard', 'none'], true) ? $adv['max-image-preview'] : 'large',
            ];
            update_post_meta($postId, '_laca_seo_advanced_robots', $cleanAdv);
        }

        // 12. Primary Term
        $pt = $post->post_type;
        $primaryTax = (string) Settings::get("titles.pt_{$pt}_primary_taxonomy", 'off');
        if ($primaryTax !== 'off' && $primaryTax !== '') {
            $primaryKey = "_laca_seo_primary_{$primaryTax}";
            if (isset($input[$primaryKey])) {
                $termId = absint($input[$primaryKey]);
                if ($termId > 0) {
                    update_post_meta($postId, $primaryKey, $termId);
                } else {
                    delete_post_meta($postId, $primaryKey);
                }
            }
        }
    }
}
