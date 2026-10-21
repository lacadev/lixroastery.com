<?php

namespace App\Features\LacaSeo\Admin\Metabox;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * Metabox and custom fields for Taxonomy terms (edit term & add term).
 * Enabled for taxonomies with tax_{tax}_add_meta_box = 'on'.
 * 3 Tabs: General (SERP preview, Title/Desc, Focus KW, 5 tests), Advanced (Robots, Canonical, Breadcrumb), Social.
 * Includes rank_math_description_editor support if term description editor is needed.
 */
class TermMetabox
{
    public const NONCE_ACTION = 'laca_seo_save_term_meta';
    public const NONCE_NAME   = 'laca_seo_term_nonce';

    public function register(): void
    {
        add_action('init', function () {
            $taxonomies = Helper::getAccessibleTaxonomies();

            foreach ($taxonomies as $slug => $taxObj) {
                $enabled = (string) Settings::get("titles.tax_{$slug}_add_meta_box", 'on');
                if ($enabled === 'on') {
                    add_action("{$slug}_edit_form_fields", [$this, 'renderEditFields'], 10, 2);
                    add_action("edited_{$slug}", [$this, 'saveTermMeta'], 10, 2);
                    add_action("created_{$slug}", [$this, 'saveTermMeta'], 10, 2);
                }
            }
        }, 99);

        add_action('admin_enqueue_scripts', [$this, 'enqueueTermAssets']);
    }

    /**
     * Enqueue assets on term.php and edit-tags.php
     */
    public function enqueueTermAssets(string $hook): void
    {
        if (!in_array($hook, ['term.php', 'edit-tags.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || empty($screen->taxonomy)) {
            return;
        }

        $tax = $screen->taxonomy;
        $enabled = (string) Settings::get("titles.tax_{$tax}_add_meta_box", 'on');
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
                ['wp-i18n', 'wp-element', 'wp-wordcount', 'wp-url', 'wp-autop'],
                (string) filemtime($dist_dir . 'laca-seo-editor.js'),
                true
            );

            $termId = (int) ($_GET['tag_ID'] ?? 0);
            $term = $termId > 0 ? get_term($termId, $tax) : null;
            $termLink = ($term && !is_wp_error($term)) ? get_term_link($term) : home_url();
            $locale = substr(get_locale(), 0, 2);

            wp_localize_script('laca-seo-editor', 'lacaSeoEditorData', [
                'ajaxUrl'         => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('laca_seo_admin_nonce'),
                'termId'          => $termId,
                'taxonomy'        => $tax,
                'locale'          => $locale,
                'homeUrl'         => home_url(),
                'permalink'       => !is_wp_error($termLink) ? $termLink : home_url(),
                'defaultTitleTpl' => (string) Settings::get("titles.tax_{$tax}_title", '%term% %page% %sep% %sitename%'),
                'defaultDescTpl'  => (string) Settings::get("titles.tax_{$tax}_description", '%term_description%'),
                'variables'       => Variables::getAvailableList(),
                'powerWords'      => [],
            ]);
        }
    }

    /**
     * Render term fields table row in term edit screen.
     */
    public function renderEditFields(\WP_Term $term, string $taxonomy): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $termId = $term->term_id;
        $meta = [
            'title'               => (string) get_term_meta($termId, '_laca_seo_title', true),
            'description'         => (string) get_term_meta($termId, '_laca_seo_description', true),
            'focus_keyword'       => (string) get_term_meta($termId, '_laca_seo_focus_keyword', true),
            'seo_score'           => (int) get_term_meta($termId, '_laca_seo_seo_score', true),
            'robots'              => (array) get_term_meta($termId, '_laca_seo_robots', true),
            'advanced_robots'     => (array) get_term_meta($termId, '_laca_seo_advanced_robots', true),
            'canonical_url'       => (string) get_term_meta($termId, '_laca_seo_canonical_url', true),
            'breadcrumb_title'    => (string) get_term_meta($termId, '_laca_seo_breadcrumb_title', true),
            'facebook_title'      => (string) get_term_meta($termId, '_laca_seo_facebook_title', true),
            'facebook_description'=> (string) get_term_meta($termId, '_laca_seo_facebook_description', true),
            'facebook_image'      => (string) get_term_meta($termId, '_laca_seo_facebook_image', true),
            'facebook_image_id'   => (string) get_term_meta($termId, '_laca_seo_facebook_image_id', true),
            'twitter_use_facebook'=> (string) get_term_meta($termId, '_laca_seo_twitter_use_facebook', true),
            'twitter_card_type'   => (string) get_term_meta($termId, '_laca_seo_twitter_card_type', true),
            'twitter_title'       => (string) get_term_meta($termId, '_laca_seo_twitter_title', true),
            'twitter_description' => (string) get_term_meta($termId, '_laca_seo_twitter_description', true),
            'twitter_image'       => (string) get_term_meta($termId, '_laca_seo_twitter_image', true),
            'twitter_image_id'    => (string) get_term_meta($termId, '_laca_seo_twitter_image_id', true),
        ];

        if (empty($meta['twitter_card_type'])) {
            $meta['twitter_card_type'] = 'summary_large_image';
        }
        if ($meta['twitter_use_facebook'] === '') {
            $meta['twitter_use_facebook'] = 'on';
        }

        ?>
        <tr class="form-field laca-seo-term-row">
            <th scope="row" valign="top">
                <label><strong><?php esc_html_e('Cấu hình Laca SEO', 'lacadev'); ?></strong></label>
            </th>
            <td>
                <div class="laca-seo-metabox-wrapper" id="laca_seo_metabox_app" data-term-id="<?php echo esc_attr($termId); ?>">
                    <input type="hidden" name="laca_seo[_laca_seo_seo_score]" id="laca_seo_score_input" value="<?php echo esc_attr($meta['seo_score']); ?>">

                    <!-- Tabs Nav -->
                    <div class="laca-seo-tabs-nav" role="tablist">
                        <button type="button" role="tab" class="laca-seo-tab-btn active" data-tab="general" aria-selected="true">
                            <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Chung', 'lacadev'); ?>
                            <span class="laca-seo-tab-score-badge" id="laca_seo_tab_score"><?php echo esc_html($meta['seo_score'] ?: 0); ?>/100</span>
                        </button>
                        <button type="button" role="tab" class="laca-seo-tab-btn" data-tab="advanced" aria-selected="false">
                            <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Nâng cao', 'lacadev'); ?>
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
                                    <span class="laca-serp-breadcrumb-path" id="laca_serp_path"> › <?php echo esc_html($term->slug); ?></span>
                                </div>
                                <h4 class="laca-serp-headline" id="laca_serp_title"><?php echo esc_html($meta['title'] ?: $term->name); ?></h4>
                                <p class="laca-serp-snippet" id="laca_serp_desc"><?php echo esc_html($meta['description'] ?: ($term->description ?: 'Mô tả danh mục...')); ?></p>
                            </div>
                        </div>

                        <div class="laca-seo-editor-fields">
                            <div class="laca-seo-field-group">
                                <div class="laca-seo-field-header">
                                    <label for="laca_seo_title_input"><?php esc_html_e('Tiêu đề SEO Danh mục', 'lacadev'); ?></label>
                                    <div class="laca-seo-counter" id="laca_seo_title_counter">
                                        <span class="laca-counter-val">0</span> / 60
                                    </div>
                                </div>
                                <input type="text" id="laca_seo_title_input" name="laca_seo[_laca_seo_title]" value="<?php echo esc_attr($meta['title']); ?>" class="large-text" placeholder="<?php echo esc_attr((string) Settings::get("titles.tax_{$taxonomy}_title", '%term% %page% %sep% %sitename%')); ?>">
                            </div>

                            <div class="laca-seo-field-group">
                                <div class="laca-seo-field-header">
                                    <label for="laca_seo_desc_input"><?php esc_html_e('Mô tả SEO Danh mục', 'lacadev'); ?></label>
                                    <div class="laca-seo-counter" id="laca_seo_desc_counter">
                                        <span class="laca-counter-val">0</span> / 160
                                    </div>
                                </div>
                                <textarea id="laca_seo_desc_input" name="laca_seo[_laca_seo_description]" rows="3" class="large-text" placeholder="<?php echo esc_attr((string) Settings::get("titles.tax_{$taxonomy}_description", '%term_description%')); ?>"><?php echo esc_textarea($meta['description']); ?></textarea>
                            </div>

                            <div class="laca-seo-field-group">
                                <label><?php esc_html_e('Từ khoá chính Danh mục (Focus Keywords)', 'lacadev'); ?></label>
                                <div class="laca-seo-tags-input" id="laca_seo_tags_input">
                                    <div class="laca-tags-list" id="laca_tags_list"></div>
                                    <input type="text" id="laca_kw_type_input" class="laca-kw-input" placeholder="<?php esc_attr_e('Nhập từ khoá rồi nhấn Enter...', 'lacadev'); ?>">
                                </div>
                                <input type="hidden" name="laca_seo[_laca_seo_focus_keyword]" id="laca_seo_focus_kw_hidden" value="<?php echo esc_attr($meta['focus_keyword']); ?>">
                            </div>

                            <!-- 5 Tests Group for Term -->
                            <div class="laca-seo-analysis-container" id="laca_seo_analysis_box">
                                <div class="laca-analysis-header">
                                    <div class="laca-score-circle-wrap">
                                        <div class="laca-score-circle laca-score--<?php echo esc_attr($meta['seo_score'] >= 80 ? 'good' : ($meta['seo_score'] >= 50 ? 'ok' : 'bad')); ?>" id="laca_score_circle">
                                            <span class="laca-score-circle__number" id="laca_score_number"><?php echo esc_html($meta['seo_score'] ?: 0); ?></span>
                                            <span class="laca-score-circle__total">/100</span>
                                        </div>
                                    </div>
                                    <div class="laca-score-intro">
                                        <h4><?php esc_html_e('Kiểm tra SEO cho Danh mục (5 tiêu chí chuẩn)', 'lacadev'); ?></h4>
                                    </div>
                                </div>
                                <div class="laca-test-groups">
                                    <div class="laca-test-group is-open" data-group="basic">
                                        <div class="laca-group-header">
                                            <strong><?php esc_html_e('Tiêu chí kiểm tra', 'lacadev'); ?></strong>
                                            <span class="laca-group-count" id="count_basic">0/5</span>
                                        </div>
                                        <div class="laca-group-body" id="group_basic_tests"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Advanced -->
                    <div class="laca-seo-tab-content" id="laca_seo_tab_advanced" role="tabpanel" hidden>
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
                            <label for="laca_seo_canonical_url"><strong><?php esc_html_e('Canonical URL', 'lacadev'); ?></strong></label>
                            <input type="url" id="laca_seo_canonical_url" name="laca_seo[_laca_seo_canonical_url]" value="<?php echo esc_attr($meta['canonical_url']); ?>" class="large-text">
                        </div>

                        <div class="laca-seo-field-group">
                            <label for="laca_seo_breadcrumb_title"><strong><?php esc_html_e('Tiêu đề Breadcrumb', 'lacadev'); ?></strong></label>
                            <input type="text" id="laca_seo_breadcrumb_title" name="laca_seo[_laca_seo_breadcrumb_title]" value="<?php echo esc_attr($meta['breadcrumb_title']); ?>" class="regular-text">
                        </div>
                    </div>

                    <!-- Tab 3: Social -->
                    <div class="laca-seo-tab-content" id="laca_seo_tab_social" role="tabpanel" hidden>
                        <div class="laca-social-section">
                            <h4><span class="dashicons dashicons-facebook"></span> <?php esc_html_e('Facebook', 'lacadev'); ?></h4>
                            <div class="laca-seo-field-group">
                                <label for="laca_seo_fb_title"><?php esc_html_e('Tiêu đề Facebook', 'lacadev'); ?></label>
                                <input type="text" id="laca_seo_fb_title" name="laca_seo[_laca_seo_facebook_title]" value="<?php echo esc_attr($meta['facebook_title']); ?>" class="regular-text">
                            </div>
                            <div class="laca-seo-field-group">
                                <label for="laca_seo_fb_desc"><?php esc_html_e('Mô tả Facebook', 'lacadev'); ?></label>
                                <textarea id="laca_seo_fb_desc" name="laca_seo[_laca_seo_facebook_description]" rows="3" class="large-text"><?php echo esc_textarea($meta['facebook_description']); ?></textarea>
                            </div>
                            <div class="laca-seo-field-group">
                                <label><?php esc_html_e('Ảnh Facebook', 'lacadev'); ?></label>
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

                        <div class="laca-social-section" style="margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px;">
                            <h4><span class="dashicons dashicons-twitter"></span> <?php esc_html_e('Twitter / X', 'lacadev'); ?></h4>
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
                                        <option value="summary_large_image" <?php selected($meta['twitter_card_type'], 'summary_large_image'); ?>>Summary with Large Image</option>
                                        <option value="summary_card" <?php selected($meta['twitter_card_type'], 'summary_card'); ?>>Summary (Ảnh nhỏ)</option>
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
                                    <label><?php esc_html_e('Ảnh Twitter', 'lacadev'); ?></label>
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
            </td>
        </tr>
        <?php
    }

    /**
     * Save term meta handler.
     */
    public function saveTermMeta(int $termId, int $ttId = 0): void
    {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_term', $termId)) {
            return;
        }

        $input = $_POST['laca_seo'] ?? [];
        if (!is_array($input)) {
            return;
        }

        // Text fields
        foreach (['_laca_seo_title', '_laca_seo_description', '_laca_seo_breadcrumb_title', '_laca_seo_facebook_title', '_laca_seo_facebook_description'] as $key) {
            if (isset($input[$key])) {
                $val = sanitize_text_field(wp_unslash($input[$key]));
                if ($val !== '') {
                    update_term_meta($termId, $key, $val);
                } else {
                    delete_term_meta($termId, $key);
                }
            }
        }

        // URLs & Images
        foreach (['_laca_seo_canonical_url', '_laca_seo_facebook_image'] as $key) {
            if (isset($input[$key])) {
                $val = esc_url_raw(trim(wp_unslash($input[$key])));
                if ($val !== '') {
                    update_term_meta($termId, $key, $val);
                } else {
                    delete_term_meta($termId, $key);
                }
            }
        }

        // Focus Keywords
        if (isset($input['_laca_seo_focus_keyword'])) {
            $kw = sanitize_text_field(wp_unslash($input['_laca_seo_focus_keyword']));
            if ($kw !== '') {
                update_term_meta($termId, '_laca_seo_focus_keyword', $kw);
            } else {
                delete_term_meta($termId, '_laca_seo_focus_keyword');
            }
        }

        // Image IDs
        foreach (['_laca_seo_facebook_image_id', '_laca_seo_twitter_image_id'] as $key) {
            if (isset($input[$key])) {
                $val = absint($input[$key]);
                if ($val > 0) {
                    update_term_meta($termId, $key, $val);
                } else {
                    delete_term_meta($termId, $key);
                }
            }
        }

        // Twitter fields
        foreach (['_laca_seo_twitter_title', '_laca_seo_twitter_description', '_laca_seo_twitter_image'] as $key) {
            if (isset($input[$key])) {
                $val = ($key === '_laca_seo_twitter_image')
                    ? esc_url_raw(trim(wp_unslash($input[$key])))
                    : sanitize_text_field(wp_unslash($input[$key]));
                if ($val !== '') {
                    update_term_meta($termId, $key, $val);
                } else {
                    delete_term_meta($termId, $key);
                }
            }
        }

        // Explicit twitter_use_facebook
        if (isset($input['_laca_seo_twitter_use_facebook']) && ($input['_laca_seo_twitter_use_facebook'] === 'on' || $input['_laca_seo_twitter_use_facebook'] === '1')) {
            update_term_meta($termId, '_laca_seo_twitter_use_facebook', 'on');
        } else {
            update_term_meta($termId, '_laca_seo_twitter_use_facebook', 'off');
        }

        // Twitter Card Type
        if (isset($input['_laca_seo_twitter_card_type'])) {
            $cardType = sanitize_key($input['_laca_seo_twitter_card_type']);
            if (in_array($cardType, ['summary', 'summary_card', 'summary_large_image', 'app', 'player'], true)) {
                update_term_meta($termId, '_laca_seo_twitter_card_type', $cardType);
            }
        }

        // Advanced Robots
        if (isset($input['_laca_seo_advanced_robots']) && is_array($input['_laca_seo_advanced_robots'])) {
            $adv = $input['_laca_seo_advanced_robots'];
            $cleanAdv = [
                'max-snippet'       => isset($adv['max-snippet']) ? (int) $adv['max-snippet'] : -1,
                'max-video-preview' => isset($adv['max-video-preview']) ? (int) $adv['max-video-preview'] : -1,
                'max-image-preview' => in_array($adv['max-image-preview'] ?? '', ['large', 'standard', 'none'], true) ? $adv['max-image-preview'] : 'large',
            ];
            update_term_meta($termId, '_laca_seo_advanced_robots', $cleanAdv);
        }

        // Robots
        if (isset($input['_laca_seo_robots'])) {
            $allowedRobots = array_keys(Helper::choicesRobots());
            $robots = is_array($input['_laca_seo_robots']) ? $input['_laca_seo_robots'] : [];
            $validRobots = array_values(array_intersect($robots, $allowedRobots));
            if (!empty($validRobots)) {
                update_term_meta($termId, '_laca_seo_robots', $validRobots);
            } else {
                delete_term_meta($termId, '_laca_seo_robots');
            }
        }

        // Score
        if (isset($input['_laca_seo_seo_score'])) {
            $score = absint($input['_laca_seo_seo_score']);
            if ($score > 0) {
                update_term_meta($termId, '_laca_seo_seo_score', $score);
            } else {
                delete_term_meta($termId, '_laca_seo_seo_score');
            }
        }
    }
}
