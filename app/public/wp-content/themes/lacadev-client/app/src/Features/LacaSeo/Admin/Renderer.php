<?php

namespace App\Features\LacaSeo\Admin;

use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\Field;

defined('ABSPATH') || exit;

/**
 * Renders individual settings fields according to type specifications (§2.4).
 */
class Renderer
{
    /**
     * Render a single field row (label + 3-line collapsible note on left, control on right).
     */
    public static function renderFieldRow(Field $field, mixed $currentValue): void
    {
        $id = $field->getId();
        $type = $field->getType();
        $label = $field->getLabel();
        $note = $field->getNote();
        $showIf = $field->getShowIf();

        // Field nhập chuỗi dài (mẫu tiêu đề, mô tả, code) xếp label trên / ô dưới full width:
        // để 2 cột thì ô chỉ còn ~200px khi có dock Laca Admin + tab dọc, chữ bị cắt.
        $stacked = in_array($type, ['variable_text', 'variable_textarea', 'textarea', 'code'], true);
        $rowAttrs = ' class="laca-seo-field-row' . ($stacked ? ' laca-seo-field-row--stack' : '') . '" data-field-id="' . esc_attr($id) . '"';
        $rowStyle = '';
        if ($showIf !== null) {
            $rowAttrs .= ' data-show-if-field="' . esc_attr($showIf['field']) . '" data-show-if-value="' . esc_attr((string) $showIf['value']) . '"';
            $group = $field->getGroup() ?? 'general';
            $masterVal = Settings::get($group . '.' . $showIf['field']);
            $expectedVal = (string) $showIf['value'];
            $expectedArr = array_map('trim', explode(',', $expectedVal));
            $masterStr = is_bool($masterVal) ? ($masterVal ? 'on' : 'off') : (string) ($masterVal ?? '');
            if (!in_array($masterStr, $expectedArr, true)) {
                $rowStyle = ' style="display:none"';
            }
        }

        echo '<div' . $rowAttrs . $rowStyle . '>';

        // Left column: Label & Note
        echo '<div class="laca-seo-field-row__label-col">';
        echo '<label class="laca-seo-field-label" for="field_' . esc_attr($id) . '">' . esc_html($label) . '</label>';

        if (!empty($note['what']) || !empty($note['how']) || !empty($note['result'])) {
            echo '<div class="laca-seo-note">';
            echo '<button type="button" class="laca-seo-note__toggle" aria-expanded="false" aria-controls="note_' . esc_attr($id) . '">';
            echo '<span class="dashicons dashicons-info" aria-hidden="true"></span> ';
            echo '<span class="laca-seo-note__toggle-text">Hướng dẫn chi tiết</span>';
            echo '</button>';
            echo '<div class="laca-seo-note__content" id="note_' . esc_attr($id) . '" hidden>';
            if (!empty($note['what'])) {
                echo '<p class="laca-seo-note__item"><strong>Tác dụng:</strong> ' . esc_html($note['what']) . '</p>';
            }
            if (!empty($note['how'])) {
                echo '<p class="laca-seo-note__item"><strong>Cách làm:</strong> ' . esc_html($note['how']) . '</p>';
            }
            if (!empty($note['result'])) {
                echo '<p class="laca-seo-note__item"><strong>Kết quả:</strong> ' . esc_html($note['result']) . '</p>';
            }
            if ($field->isTranslatable()) {
                echo '<p class="laca-seo-note__item laca-seo-note__item--i18n"><em>🌐 Chuỗi này có thể dịch qua Polylang (Ngôn ngữ › Dịch chuỗi › nhóm Laca SEO).</em></p>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Right column: Control
        echo '<div class="laca-seo-field-row__control-col">';
        self::renderControl($field, $currentValue);
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render the specific HTML control for a field.
     */
    public static function renderControl(Field $field, mixed $val): void
    {
        $id = $field->getId();
        $name = 'laca_seo[' . $id . ']';
        $type = $field->getType();
        $choices = $field->getChoices();

        switch ($type) {
            case 'toggle':
                $checked = ($val === 'on' || $val === '1' || $val === 1 || $val === true);
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="off">';
                echo '<label class="laca-seo-switch">';
                echo '<input type="checkbox" role="switch" id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="on"' . ($checked ? ' checked' : '') . '>';
                echo '<span class="laca-seo-switch__slider"></span>';
                echo '</label>';
                break;

            case 'text':
                echo '<input type="text" id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" class="regular-text laca-seo-input">';
                break;

            case 'variable_text':
                // Server tự suy ngữ cảnh xem trước (post type / taxonomy / tác giả) từ id field.
                echo '<div class="laca-seo-var-field" data-field-id="' . esc_attr($id) . '">';
                echo '<div class="laca-seo-var-field__input-wrap">';
                echo '<input type="text" id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" class="regular-text laca-seo-input laca-seo-var-input" data-is-title="1">';
                echo '<div class="laca-seo-var-dropdown-wrap">';
                echo '<button type="button" class="button button-secondary laca-seo-var-btn" title="Chèn biến động"><span class="dashicons dashicons-plus-alt2"></span> Chèn biến</button>';
                echo '<div class="laca-seo-var-dropdown" hidden>';
                echo '<input type="text" class="laca-seo-var-search" placeholder="Tìm biến (%title%, %sep%...)">';
                echo '<ul class="laca-seo-var-list"></ul>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="laca-seo-var-counter" data-for="field_' . esc_attr($id) . '" data-min="50" data-max="60">Độ dài: <span class="laca-seo-var-count">0</span> / 60 ký tự</div>';
                echo '<div class="laca-seo-var-preview" data-for="field_' . esc_attr($id) . '"><span class="laca-seo-var-preview__label">Xem trước:</span> <span class="laca-seo-var-preview__text"></span></div>';
                echo '</div>';
                break;

            case 'number':
                echo '<input type="number" id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" class="small-text laca-seo-input">';
                break;

            case 'textarea':
                echo '<textarea id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="4" class="large-text laca-seo-textarea">' . esc_textarea((string) $val) . '</textarea>';
                break;

            case 'variable_textarea':
                // Server tự suy ngữ cảnh xem trước (post type / taxonomy / tác giả) từ id field.
                echo '<div class="laca-seo-var-field" data-field-id="' . esc_attr($id) . '">';
                echo '<div class="laca-seo-var-field__input-wrap">';
                echo '<textarea id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="4" class="large-text laca-seo-textarea laca-seo-var-input" data-is-desc="1">' . esc_textarea((string) $val) . '</textarea>';
                echo '<div class="laca-seo-var-dropdown-wrap">';
                echo '<button type="button" class="button button-secondary laca-seo-var-btn" title="Chèn biến động"><span class="dashicons dashicons-plus-alt2"></span> Chèn biến</button>';
                echo '<div class="laca-seo-var-dropdown" hidden>';
                echo '<input type="text" class="laca-seo-var-search" placeholder="Tìm biến (%title%, %sep%...)">';
                echo '<ul class="laca-seo-var-list"></ul>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="laca-seo-var-counter" data-for="field_' . esc_attr($id) . '" data-min="120" data-max="160">Độ dài: <span class="laca-seo-var-count">0</span> / 160 ký tự</div>';
                echo '<div class="laca-seo-var-preview" data-for="field_' . esc_attr($id) . '"><span class="laca-seo-var-preview__label">Xem trước:</span> <span class="laca-seo-var-preview__text"></span></div>';
                echo '</div>';
                break;

            case 'code':
                echo '<div class="laca-seo-code-box">';
                echo '<textarea id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="10" class="large-text code laca-seo-textarea laca-seo-textarea--code">' . esc_textarea((string) $val) . '</textarea>';
                echo '<button type="button" class="button button-secondary laca-seo-code-reset" data-target="field_' . esc_attr($id) . '" data-default="' . esc_attr((string) $field->getDefault()) . '">Khôi phục mặc định</button>';
                echo '</div>';
                break;

            case 'select':
                echo '<select id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="laca-seo-select">';
                foreach ($choices as $k => $label) {
                    $selected = ((string) $k === (string) $val) ? ' selected' : '';
                    echo '<option value="' . esc_attr($k) . '"' . $selected . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;

            case 'radio':
                echo '<div class="laca-seo-radio-stack">';
                foreach ($choices as $k => $label) {
                    $checked = ((string) $k === (string) $val) ? ' checked' : '';
                    echo '<label class="laca-seo-radio-stack__item">';
                    echo '<input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($k) . '"' . $checked . '>';
                    echo ' <span>' . esc_html($label) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'radio_inline':
                echo '<div class="laca-seo-radio-inline">';
                foreach ($choices as $k => $label) {
                    $checked = ((string) $k === (string) $val) ? ' checked' : '';
                    echo '<label class="laca-seo-radio-inline__item">';
                    echo '<input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($k) . '"' . $checked . '>';
                    echo ' <span>' . esc_html($label) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'html':
                echo '<div class="laca-seo-html-content">' . wp_kses_post($field->getDefault()) . '</div>';
                break;

            case 'notice':
                echo '<div class="notice notice-info inline laca-seo-field-notice"><p>' . esc_html($field->getDefault()) . '</p></div>';
                break;

            case 'multicheck':
            case 'robots':
                $valArr = is_array($val) ? $val : (array) $val;
                echo '<div class="laca-seo-multicheck">';
                foreach ($choices as $k => $label) {
                    $checked = in_array((string) $k, $valArr, true) ? ' checked' : '';
                    echo '<label class="laca-seo-multicheck__item">';
                    echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($k) . '"' . $checked . '>';
                    echo ' <span>' . esc_html($label) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'advanced_robots':
                $adv = is_array($val) ? $val : [];
                $snippet = isset($adv['max-snippet']) ? (int) $adv['max-snippet'] : -1;
                $video = isset($adv['max-video-preview']) ? (int) $adv['max-video-preview'] : -1;
                $img = $adv['max-image-preview'] ?? 'large';

                echo '<div class="laca-seo-advanced-robots">';
                echo '<div class="laca-seo-advanced-robots__row">';
                echo '<label>Snippet: </label>';
                echo '<input type="number" name="' . esc_attr($name) . '[max-snippet]" value="' . esc_attr((string) $snippet) . '" class="small-text"> (ký tự, -1 = vô hạn)';
                echo '</div>';
                echo '<div class="laca-seo-advanced-robots__row">';
                echo '<label>Video Preview: </label>';
                echo '<input type="number" name="' . esc_attr($name) . '[max-video-preview]" value="' . esc_attr((string) $video) . '" class="small-text"> (giây, -1 = vô hạn)';
                echo '</div>';
                echo '<div class="laca-seo-advanced-robots__row">';
                echo '<label>Image Preview: </label>';
                echo '<select name="' . esc_attr($name) . '[max-image-preview]">';
                foreach (['large' => 'Large (Lớn)', 'standard' => 'Standard (Chuẩn)', 'none' => 'None (Không)'] as $optKey => $optLabel) {
                    $sel = ($optKey === $img) ? ' selected' : '';
                    echo '<option value="' . esc_attr($optKey) . '"' . $sel . '>' . esc_html($optLabel) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '</div>';
                break;

            case 'image':
                $imgId = 0;
                $imgUrl = '';
                if (is_array($val)) {
                    $imgId = (int) ($val['id'] ?? 0);
                    $imgUrl = (string) ($val['url'] ?? '');
                } elseif (is_numeric($val)) {
                    $imgId = (int) $val;
                    $imgUrl = wp_get_attachment_url($imgId) ?: '';
                } elseif (is_string($val)) {
                    $imgUrl = $val;
                }

                echo '<div class="laca-seo-media-field" data-field-id="' . esc_attr($id) . '">';
                echo '<input type="hidden" name="' . esc_attr($name) . '[id]" value="' . esc_attr((string) $imgId) . '" class="laca-seo-media-id">';
                echo '<input type="text" name="' . esc_attr($name) . '[url]" value="' . esc_attr($imgUrl) . '" class="regular-text laca-seo-media-url" placeholder="https://..."> ';
                echo '<button type="button" class="button button-secondary laca-seo-media-select">Chọn ảnh</button> ';
                echo '<button type="button" class="button button-link-delete laca-seo-media-clear"' . ($imgUrl ? '' : ' style="display:none"') . '>Xoá</button>';
                echo '<div class="laca-seo-media-preview' . ($imgUrl ? '' : ' is-hidden') . '">';
                if ($imgUrl) {
                    echo '<img src="' . esc_url($imgUrl) . '" class="laca-seo-media-preview__img">';
                }
                echo '</div>';
                echo '</div>';
                break;

            case 'address':
                $addr = is_array($val) ? $val : [];
                $street = $addr['streetAddress'] ?? ($addr['street'] ?? '');
                $locality = $addr['addressLocality'] ?? ($addr['locality'] ?? '');
                $region = $addr['addressRegion'] ?? ($addr['region'] ?? '');
                $postal = $addr['postalCode'] ?? ($addr['postcode'] ?? '');
                $country = $addr['addressCountry'] ?? ($addr['country'] ?? 'VN');

                echo '<div class="laca-seo-address-fields">';
                echo '<div class="laca-seo-address-fields__row"><input type="text" name="' . esc_attr($name) . '[streetAddress]" value="' . esc_attr($street) . '" placeholder="Số nhà, tên đường" class="regular-text"></div>';
                echo '<div class="laca-seo-address-fields__row"><input type="text" name="' . esc_attr($name) . '[addressLocality]" value="' . esc_attr($locality) . '" placeholder="Phường/Xã/Quận" class="regular-text"></div>';
                echo '<div class="laca-seo-address-fields__row"><input type="text" name="' . esc_attr($name) . '[addressRegion]" value="' . esc_attr($region) . '" placeholder="Tỉnh / Thành phố" class="regular-text"></div>';
                echo '<div class="laca-seo-address-fields__row"><input type="text" name="' . esc_attr($name) . '[postalCode]" value="' . esc_attr($postal) . '" placeholder="Mã bưu chính (Postal Code)" class="regular-text"></div>';
                echo '<div class="laca-seo-address-fields__row"><input type="text" name="' . esc_attr($name) . '[addressCountry]" value="' . esc_attr($country) . '" placeholder="Quốc gia (mã 2 chữ cái VN, US...)" class="regular-text"></div>';
                echo '</div>';
                break;

            case 'group':
                $items = is_array($val) ? $val : [];
                if (empty($items) && $id === 'image_replacements') {
                    $items = [['find' => '', 'replace' => '', 'replace_in' => ['alt', 'title']]];
                }
                echo '<div class="laca-seo-group-repeater" data-group-name="' . esc_attr($name) . '">';
                echo '<div class="laca-seo-group-items">';
                foreach ($items as $idx => $item) {
                    self::renderGroupItem($id, $name, (int) $idx, $item);
                }
                echo '</div>';
                echo '<button type="button" class="button button-secondary laca-seo-group-add" data-group-id="' . esc_attr($id) . '">+ Thêm dòng</button>';
                echo '</div>';
                break;

            default:
                echo '<input type="text" id="field_' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '" class="regular-text">';
                break;
        }
    }

    /**
     * Render an individual row inside a group repeater.
     */
    private static function renderGroupItem(string $groupId, string $groupName, int $idx, array $item): void
    {
        echo '<div class="laca-seo-group-item">';

        if ($groupId === 'opening_hours') {
            $days = [
                'Monday' => 'Thứ Hai (Mon)', 'Tuesday' => 'Thứ Ba (Tue)', 'Wednesday' => 'Thứ Tư (Wed)',
                'Thursday' => 'Thứ Năm (Thu)', 'Friday' => 'Thứ Sáu (Fri)', 'Saturday' => 'Thứ Bảy (Sat)', 'Sunday' => 'Chủ Nhật (Sun)'
            ];
            echo '<select name="' . esc_attr($groupName) . '[' . $idx . '][day]">';
            foreach ($days as $k => $lbl) {
                $sel = (($item['day'] ?? '') === $k) ? ' selected' : '';
                echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($lbl) . '</option>';
            }
            echo '</select>';
            echo '<input type="text" name="' . esc_attr($groupName) . '[' . $idx . '][time]" value="' . esc_attr($item['time'] ?? '08:00-17:00') . '" placeholder="08:00-17:00" class="regular-text laca-seo-time-input">';
        } elseif ($groupId === 'phone_numbers') {
            $types = [
                'customer_support' => 'Hỗ trợ khách hàng', 'technical_support' => 'Kỹ thuật', 'sales' => 'Kinh doanh', 'billing_support' => 'Kế toán'
            ];
            echo '<select name="' . esc_attr($groupName) . '[' . $idx . '][type]">';
            foreach ($types as $k => $lbl) {
                $sel = (($item['type'] ?? '') === $k) ? ' selected' : '';
                echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($lbl) . '</option>';
            }
            echo '</select>';
            echo '<input type="text" name="' . esc_attr($groupName) . '[' . $idx . '][number]" value="' . esc_attr($item['number'] ?? '') . '" placeholder="+84-901-234-567" class="regular-text">';
        } elseif ($groupId === 'additional_info') {
            $types = [
                'taxID' => 'Mã số thuế', 'vatID' => 'Mã VAT', 'foundingDate' => 'Ngày thành lập', 'parentOrganization' => 'Công ty mẹ'
            ];
            echo '<select name="' . esc_attr($groupName) . '[' . $idx . '][type]">';
            foreach ($types as $k => $lbl) {
                $sel = (($item['type'] ?? '') === $k) ? ' selected' : '';
                echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($lbl) . '</option>';
            }
            echo '</select>';
            echo '<input type="text" name="' . esc_attr($groupName) . '[' . $idx . '][value]" value="' . esc_attr($item['value'] ?? '') . '" placeholder="Giá trị..." class="regular-text">';
        } elseif ($groupId === 'image_replacements') {
            $find = $item['find'] ?? '';
            $replace = $item['replace'] ?? '';
            $replaceIn = (array) ($item['replace_in'] ?? ['alt', 'title']);

            echo '<input type="text" name="' . esc_attr($groupName) . '[' . $idx . '][find]" value="' . esc_attr($find) . '" placeholder="Tìm kiếm (Find)..." class="regular-text" style="width:180px;margin-right:8px;">';
            echo '<input type="text" name="' . esc_attr($groupName) . '[' . $idx . '][replace]" value="' . esc_attr($replace) . '" placeholder="Thay thế (Replace)..." class="regular-text" style="width:180px;margin-right:12px;">';
            echo '<label style="margin-right:8px;"><input type="checkbox" name="' . esc_attr($groupName) . '[' . $idx . '][replace_in][]" value="alt"' . (in_array('alt', $replaceIn, true) ? ' checked' : '') . '> Alt</label>';
            echo '<label style="margin-right:8px;"><input type="checkbox" name="' . esc_attr($groupName) . '[' . $idx . '][replace_in][]" value="title"' . (in_array('title', $replaceIn, true) ? ' checked' : '') . '> Title</label>';
            echo '<label style="margin-right:12px;"><input type="checkbox" name="' . esc_attr($groupName) . '[' . $idx . '][replace_in][]" value="caption"' . (in_array('caption', $replaceIn, true) ? ' checked' : '') . '> Caption</label>';
        } else {
            foreach ($item as $k => $v) {
                echo '<input type="text" name="' . esc_attr($groupName) . '[' . $idx . '][' . esc_attr($k) . ']" value="' . esc_attr((string) $v) . '" class="regular-text">';
            }
        }

        echo '<button type="button" class="button button-link-delete laca-seo-group-remove">Xoá</button>';
        echo '</div>';
    }
}
