<?php

namespace App\Features\LacaSeo\Admin\Metabox;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;

defined('ABSPATH') || exit;

/**
 * Metabox and custom fields for User Profile / Author screen.
 * Enabled when author_add_meta_box = 'on' and user has edit_users capability.
 */
class UserMetabox
{
    public const NONCE_ACTION = 'laca_seo_save_user_meta';
    public const NONCE_NAME   = 'laca_seo_user_nonce';

    public function register(): void
    {
        $enabled = (string) Settings::get('titles.author_add_meta_box', 'on');
        if ($enabled === 'on') {
            add_action('show_user_profile', [$this, 'renderUserFields'], 20);
            add_action('edit_user_profile', [$this, 'renderUserFields'], 20);
            add_action('personal_options_update', [$this, 'saveUserMeta']);
            add_action('edit_user_profile_update', [$this, 'saveUserMeta']);
        }
    }

    /**
     * Render user fields on profile screen.
     */
    public function renderUserFields(\WP_User $user): void
    {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $userId = $user->ID;
        $meta = [
            'title'               => (string) get_user_meta($userId, '_laca_seo_title', true),
            'description'         => (string) get_user_meta($userId, '_laca_seo_description', true),
            'focus_keyword'       => (string) get_user_meta($userId, '_laca_seo_focus_keyword', true),
            'seo_score'           => (int) get_user_meta($userId, '_laca_seo_seo_score', true),
            'robots'              => (array) get_user_meta($userId, '_laca_seo_robots', true),
            'canonical_url'       => (string) get_user_meta($userId, '_laca_seo_canonical_url', true),
            'facebook_title'      => (string) get_user_meta($userId, '_laca_seo_facebook_title', true),
            'facebook_description'=> (string) get_user_meta($userId, '_laca_seo_facebook_description', true),
            'facebook_image'      => (string) get_user_meta($userId, '_laca_seo_facebook_image', true),
        ];

        ?>
        <h3 id="laca-seo-author-title"><?php esc_html_e('Cấu hình SEO Tác giả (Laca SEO)', 'lacadev'); ?></h3>
        <table class="form-table laca-seo-user-table" role="presentation">
            <tr>
                <th scope="row"><label for="laca_seo_user_title"><?php esc_html_e('Tiêu đề SEO Tác giả', 'lacadev'); ?></label></th>
                <td>
                    <input type="text" id="laca_seo_user_title" name="laca_seo[_laca_seo_title]" value="<?php echo esc_attr($meta['title']); ?>" class="regular-text" placeholder="<?php echo esc_attr((string) Settings::get('titles.author_archive_title', '%name% %sep% %sitename%')); ?>">
                    <p class="description"><?php esc_html_e('Tiêu đề hiển thị trên kết quả tìm kiếm cho trang lưu trữ bài viết của tác giả này.', 'lacadev'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="laca_seo_user_desc"><?php esc_html_e('Mô tả SEO Tác giả', 'lacadev'); ?></label></th>
                <td>
                    <textarea id="laca_seo_user_desc" name="laca_seo[_laca_seo_description]" rows="3" class="regular-text"><?php echo esc_textarea($meta['description']); ?></textarea>
                    <p class="description"><?php esc_html_e('Đoạn mô tả ngắn gọn về tác giả trong thẻ meta description.', 'lacadev'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="laca_seo_user_kw"><?php esc_html_e('Từ khoá chính', 'lacadev'); ?></label></th>
                <td>
                    <input type="text" id="laca_seo_user_kw" name="laca_seo[_laca_seo_focus_keyword]" value="<?php echo esc_attr($meta['focus_keyword']); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="laca_seo_user_canonical"><?php esc_html_e('Canonical URL', 'lacadev'); ?></label></th>
                <td>
                    <input type="url" id="laca_seo_user_canonical" name="laca_seo[_laca_seo_canonical_url]" value="<?php echo esc_attr($meta['canonical_url']); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e('Chỉ thị Robots Meta', 'lacadev'); ?></label></th>
                <td>
                    <?php
                    $robotsList = Helper::choicesRobots();
                    $savedRobots = (array) $meta['robots'];
                    foreach ($robotsList as $rKey => $rLabel):
                        $isChecked = in_array($rKey, $savedRobots, true);
                    ?>
                        <label style="margin-right:12px;">
                            <input type="checkbox" name="laca_seo[_laca_seo_robots][]" value="<?php echo esc_attr($rKey); ?>" <?php checked($isChecked); ?>>
                            <span><?php echo esc_html($rLabel); ?></span>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="laca_seo_user_fb_img"><?php esc_html_e('Ảnh chia sẻ Facebook', 'lacadev'); ?></label></th>
                <td>
                    <input type="url" id="laca_seo_user_fb_img" name="laca_seo[_laca_seo_facebook_image]" value="<?php echo esc_attr($meta['facebook_image']); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user meta handler.
     */
    public function saveUserMeta(int $userId): void
    {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        $input = $_POST['laca_seo'] ?? [];
        if (!is_array($input)) {
            return;
        }

        // Text fields
        foreach (['_laca_seo_title', '_laca_seo_description', '_laca_seo_focus_keyword'] as $key) {
            if (isset($input[$key])) {
                $val = sanitize_text_field(wp_unslash($input[$key]));
                if ($val !== '') {
                    update_user_meta($userId, $key, $val);
                } else {
                    delete_user_meta($userId, $key);
                }
            }
        }

        // URLs
        foreach (['_laca_seo_canonical_url', '_laca_seo_facebook_image'] as $key) {
            if (isset($input[$key])) {
                $val = esc_url_raw(trim(wp_unslash($input[$key])));
                if ($val !== '') {
                    update_user_meta($userId, $key, $val);
                } else {
                    delete_user_meta($userId, $key);
                }
            }
        }

        // Robots
        if (isset($input['_laca_seo_robots'])) {
            $allowedRobots = array_keys(Helper::choicesRobots());
            $robots = is_array($input['_laca_seo_robots']) ? $input['_laca_seo_robots'] : [];
            $validRobots = array_values(array_intersect($robots, $allowedRobots));
            if (!empty($validRobots)) {
                update_user_meta($userId, '_laca_seo_robots', $validRobots);
            } else {
                delete_user_meta($userId, '_laca_seo_robots');
            }
        }
    }
}
