<?php

namespace App\Settings\LacaAdmin;

/**
 * Trang mẫu (style guide sống) hiển thị mọi component Chakra UI v3.
 * URL ẩn: admin.php?page=laca-ui-kit
 */
class UiKitPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            null, // Trang ẩn không hiện trong sidebar
            __('Laca UI Kit', 'laca'),
            __('Laca UI Kit', 'laca'),
            'manage_options',
            'laca-ui-kit',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        ?>
        <div class="wrap laca-ui-kit-wrap" style="max-width: 1200px; margin: 20px auto; padding: 0 16px;">
            <!-- Page Header -->
            <div class="laca-page-header">
                <div class="laca-page-header__content">
                    <h1 class="laca-page-header__title"><?php esc_html_e('Laca UI Kit (Chakra v3 Design System)', 'laca'); ?></h1>
                    <p class="laca-page-header__description"><?php esc_html_e('Trang mẫu tài liệu sống hiển thị mọi component và trạng thái thiết kế admin.', 'laca'); ?></p>
                </div>
                <div class="laca-page-header__actions">
                    <button type="button" class="laca-btn laca-btn--outline laca-btn--sm">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Xuất tài liệu', 'laca'); ?>
                    </button>
                    <button type="button" class="laca-btn laca-btn--solid laca-btn--sm">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Lưu thay đổi', 'laca'); ?>
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="laca-card" style="margin-bottom: 24px;">
                <div class="laca-card__body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Tổng số component', 'laca'); ?></span>
                            <span class="laca-stat__value">15</span>
                            <span class="laca-stat__help"><?php esc_html_e('Đã chuẩn hoá theo Chakra v3', 'laca'); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Design Tokens', 'laca'); ?></span>
                            <span class="laca-stat__value">32</span>
                            <span class="laca-stat__help"><?php esc_html_e('Nguồn sự thật duy nhất', 'laca'); ?></span>
                        </div>
                        <div class="laca-stat">
                            <span class="laca-stat__label"><?php esc_html_e('Tiến độ chuyển đổi', 'laca'); ?></span>
                            <span class="laca-stat__value">100%</span>
                            <div class="laca-progress" style="margin-top: 8px;">
                                <div class="laca-progress__bar" style="width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div style="margin-bottom: 24px;">
                <nav class="laca-tabs laca-tabs--line" aria-label="UI Kit Tabs">
                    <button type="button" class="laca-tabs__item is-active" aria-selected="true"><?php esc_html_e('Tất cả component', 'laca'); ?></button>
                    <button type="button" class="laca-tabs__item"><?php esc_html_e('Controls & Forms', 'laca'); ?></button>
                    <button type="button" class="laca-tabs__item"><?php esc_html_e('Layout & Feedback', 'laca'); ?></button>
                    <button type="button" class="laca-tabs__item"><?php esc_html_e('Dữ liệu & Bảng', 'laca'); ?></button>
                </nav>
            </div>

            <!-- Section 1: Buttons -->
            <div class="laca-card" style="margin-bottom: 24px;">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php esc_html_e('1. Buttons (.laca-btn)', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php esc_html_e('Đủ các biến thể solid, outline, subtle, ghost, danger cùng kích thước xs, sm, md.', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 16px;">
                        <button type="button" class="laca-btn laca-btn--solid laca-btn--sm"><?php esc_html_e('Solid (Default)', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--outline laca-btn--sm"><?php esc_html_e('Outline', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--subtle laca-btn--sm"><?php esc_html_e('Subtle', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--ghost laca-btn--sm"><?php esc_html_e('Ghost', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--danger laca-btn--sm"><?php esc_html_e('Danger', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--solid laca-btn--sm" disabled><?php esc_html_e('Disabled', 'laca'); ?></button>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <button type="button" class="laca-btn laca-btn--solid laca-btn--xs"><?php esc_html_e('Size XS (32px)', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--solid laca-btn--sm"><?php esc_html_e('Size SM (36px)', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--solid laca-btn--md"><?php esc_html_e('Size MD (40px)', 'laca'); ?></button>
                        <button type="button" class="laca-btn laca-btn--outline laca-btn--sm">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e('With Icon', 'laca'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section 2: Form Controls -->
            <div class="laca-card" style="margin-bottom: 24px;">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php esc_html_e('2. Form Controls (Inputs, Select, Textarea)', 'laca'); ?></h2>
                    <p class="laca-card__header-description"><?php esc_html_e('Các trường nhập liệu chuẩn, bo góc 4px, focus ring xanh dịu.', 'laca'); ?></p>
                </div>
                <div class="laca-card__body">
                    <div class="laca-field-row">
                        <div class="laca-field-row__label">
                            <label for="uikit-text-default"><?php esc_html_e('Văn bản mặc định', 'laca'); ?></label>
                            <div class="laca-field__help"><?php esc_html_e('Mô tả hướng dẫn phụ', 'laca'); ?></div>
                        </div>
                        <div class="laca-field-row__control">
                            <input type="text" id="uikit-text-default" class="laca-input" placeholder="<?php esc_attr_e('Nhập nội dung...', 'laca'); ?>" value="<?php esc_attr_e('Giá trị mẫu', 'laca'); ?>">
                        </div>
                    </div>

                    <div class="laca-field-row">
                        <div class="laca-field-row__label">
                            <label for="uikit-select-sample"><?php esc_html_e('Lựa chọn (Select)', 'laca'); ?></label>
                        </div>
                        <div class="laca-field-row__control">
                            <select id="uikit-select-sample" class="laca-select">
                                <option value="1"><?php esc_html_e('Lựa chọn số 1', 'laca'); ?></option>
                                <option value="2"><?php esc_html_e('Lựa chọn số 2', 'laca'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="laca-field-row">
                        <div class="laca-field-row__label">
                            <label for="uikit-textarea-sample"><?php esc_html_e('Đoạn văn (Textarea)', 'laca'); ?></label>
                        </div>
                        <div class="laca-field-row__control">
                            <textarea id="uikit-textarea-sample" class="laca-textarea" rows="3" placeholder="<?php esc_attr_e('Nội dung dài...', 'laca'); ?>"></textarea>
                        </div>
                    </div>

                    <div class="laca-field-row">
                        <div class="laca-field-row__label">
                            <label for="uikit-input-invalid"><?php esc_html_e('Trạng thái lỗi (Invalid)', 'laca'); ?></label>
                        </div>
                        <div class="laca-field-row__control">
                            <input type="text" id="uikit-input-invalid" class="laca-input is-invalid" aria-invalid="true" value="<?php esc_attr_e('Giá trị không hợp lệ', 'laca'); ?>">
                            <div class="laca-field__error"><?php esc_html_e('Trường này là bắt buộc và phải đúng định dạng.', 'laca'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Selection Controls -->
            <div class="laca-card" style="margin-bottom: 24px;">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php esc_html_e('3. Selection Controls (Checkbox, Radio, Switch, Segmented)', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
                            <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" class="laca-checkbox" checked>
                                <span><?php esc_html_e('Checkbox đã chọn', 'laca'); ?></span>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" class="laca-checkbox">
                                <span><?php esc_html_e('Checkbox chưa chọn', 'laca'); ?></span>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" name="uikit_radio" class="laca-radio" checked>
                                <span><?php esc_html_e('Radio A', 'laca'); ?></span>
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" name="uikit_radio" class="laca-radio">
                                <span><?php esc_html_e('Radio B', 'laca'); ?></span>
                            </label>
                        </div>

                        <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
                            <label class="laca-switch">
                                <input type="checkbox" class="laca-switch__input" checked>
                                <span class="laca-switch__track">
                                    <span class="laca-switch__thumb"></span>
                                </span>
                                <span class="laca-switch__label"><?php esc_html_e('Công tắc bật', 'laca'); ?></span>
                            </label>

                            <label class="laca-switch">
                                <input type="checkbox" class="laca-switch__input">
                                <span class="laca-switch__track">
                                    <span class="laca-switch__thumb"></span>
                                </span>
                                <span class="laca-switch__label"><?php esc_html_e('Công tắc tắt', 'laca'); ?></span>
                            </label>
                        </div>

                        <div>
                            <div class="laca-segmented">
                                <button type="button" class="laca-segmented__item is-active" aria-selected="true"><?php esc_html_e('Máy tính', 'laca'); ?></button>
                                <button type="button" class="laca-segmented__item"><?php esc_html_e('Máy tính bảng', 'laca'); ?></button>
                                <button type="button" class="laca-segmented__item"><?php esc_html_e('Điện thoại', 'laca'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Feedback (Badges & Alerts) -->
            <div class="laca-card" style="margin-bottom: 24px;">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php esc_html_e('4. Badges & Alerts', 'laca'); ?></h2>
                </div>
                <div class="laca-card__body">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;">
                        <span class="laca-badge laca-badge--neutral"><?php esc_html_e('Neutral', 'laca'); ?></span>
                        <span class="laca-badge laca-badge--accent"><?php esc_html_e('Accent', 'laca'); ?></span>
                        <span class="laca-badge laca-badge--success"><?php esc_html_e('Success', 'laca'); ?></span>
                        <span class="laca-badge laca-badge--warning"><?php esc_html_e('Warning', 'laca'); ?></span>
                        <span class="laca-badge laca-badge--danger"><?php esc_html_e('Danger', 'laca'); ?></span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div class="laca-alert laca-alert--info">
                            <span class="dashicons dashicons-info laca-alert__icon"></span>
                            <div class="laca-alert__content">
                                <div class="laca-alert__title"><?php esc_html_e('Thông tin hệ thống', 'laca'); ?></div>
                                <div class="laca-alert__description"><?php esc_html_e('Đã đồng bộ toàn bộ biến màu theo ngôn ngữ thiết kế Chakra UI v3.', 'laca'); ?></div>
                            </div>
                        </div>
                        <div class="laca-alert laca-alert--success">
                            <span class="dashicons dashicons-yes-alt laca-alert__icon"></span>
                            <div class="laca-alert__content">
                                <div class="laca-alert__title"><?php esc_html_e('Thành công', 'laca'); ?></div>
                                <div class="laca-alert__description"><?php esc_html_e('Cài đặt đã được lưu an toàn vào cơ sở dữ liệu.', 'laca'); ?></div>
                            </div>
                        </div>
                        <div class="laca-alert laca-alert--warning">
                            <span class="dashicons dashicons-warning laca-alert__icon"></span>
                            <div class="laca-alert__content">
                                <div class="laca-alert__title"><?php esc_html_e('Cảnh báo', 'laca'); ?></div>
                                <div class="laca-alert__description"><?php esc_html_e('Tính năng admin color đã được gỡ bỏ để đảm bảo tính nhất quán.', 'laca'); ?></div>
                            </div>
                        </div>
                        <div class="laca-alert laca-alert--danger">
                            <span class="dashicons dashicons-dismiss laca-alert__icon"></span>
                            <div class="laca-alert__content">
                                <div class="laca-alert__title"><?php esc_html_e('Lỗi kết nối', 'laca'); ?></div>
                                <div class="laca-alert__description"><?php esc_html_e('Không thể kết nối đến máy chủ xác thực.', 'laca'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Data Table -->
            <div class="laca-card" style="margin-bottom: 24px;">
                <div class="laca-card__header">
                    <h2 class="laca-card__header-title"><?php esc_html_e('5. Data Table (.laca-table-wrap)', 'laca'); ?></h2>
                </div>
                <div class="laca-table-wrap">
                    <table class="laca-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Mô-đun', 'laca'); ?></th>
                                <th><?php esc_html_e('Phiên bản', 'laca'); ?></th>
                                <th><?php esc_html_e('Trạng thái', 'laca'); ?></th>
                                <th style="text-align: right;"><?php esc_html_e('Thao tác', 'laca'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('Laca Admin Core', 'laca'); ?></strong></td>
                                <td>v3.0.0</td>
                                <td><span class="laca-badge laca-badge--success"><?php esc_html_e('Hoạt động', 'laca'); ?></span></td>
                                <td style="text-align: right;">
                                    <button type="button" class="laca-btn laca-btn--ghost laca-btn--xs"><?php esc_html_e('Cấu hình', 'laca'); ?></button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Laca SEO', 'laca'); ?></strong></td>
                                <td>v2.4.0</td>
                                <td><span class="laca-badge laca-badge--accent"><?php esc_html_e('Đã kết nối', 'laca'); ?></span></td>
                                <td style="text-align: right;">
                                    <button type="button" class="laca-btn laca-btn--ghost laca-btn--xs"><?php esc_html_e('Cấu hình', 'laca'); ?></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 6: Accordion & Empty State -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 24px;">
                <div class="laca-card">
                    <div class="laca-card__header">
                        <h2 class="laca-card__header-title"><?php esc_html_e('6. Accordion (.laca-accordion)', 'laca'); ?></h2>
                    </div>
                    <div class="laca-card__body">
                        <div class="laca-accordion">
                            <div class="laca-accordion__item">
                                <button type="button" class="laca-accordion__trigger" aria-expanded="true">
                                    <span><?php esc_html_e('Mục mở rộng 1', 'laca'); ?></span>
                                    <span class="dashicons dashicons-arrow-down-alt2 laca-accordion__chevron"></span>
                                </button>
                                <div class="laca-accordion__content">
                                    <?php esc_html_e('Nội dung chi tiết bên trong accordion panel.', 'laca'); ?>
                                </div>
                            </div>
                            <div class="laca-accordion__item">
                                <button type="button" class="laca-accordion__trigger" aria-expanded="false">
                                    <span><?php esc_html_e('Mục thu gọn 2', 'laca'); ?></span>
                                    <span class="dashicons dashicons-arrow-down-alt2 laca-accordion__chevron"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="laca-card">
                    <div class="laca-card__header">
                        <h2 class="laca-card__header-title"><?php esc_html_e('7. Empty State (.laca-empty)', 'laca'); ?></h2>
                    </div>
                    <div class="laca-card__body">
                        <div class="laca-empty">
                            <span class="dashicons dashicons-inbox laca-empty__icon"></span>
                            <h3 class="laca-empty__title"><?php esc_html_e('Chưa có dữ liệu nào', 'laca'); ?></h3>
                            <p class="laca-empty__description"><?php esc_html_e('Hãy bắt đầu bằng việc tạo mục đầu tiên hoặc nhập dữ liệu từ file sao lưu.', 'laca'); ?></p>
                            <div class="laca-empty__action">
                                <button type="button" class="laca-btn laca-btn--solid laca-btn--sm"><?php esc_html_e('Thêm mới', 'laca'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Action Bar -->
            <div class="laca-actionbar" style="border-radius: var(--laca-radius-l3); margin-top: 32px;">
                <div class="laca-actionbar__status">
                    <span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 4px;"></span>
                    <?php esc_html_e('Có thay đổi chưa lưu', 'laca'); ?>
                </div>
                <div class="laca-actionbar__actions">
                    <button type="button" class="laca-btn laca-btn--outline laca-btn--sm"><?php esc_html_e('Huỷ bỏ', 'laca'); ?></button>
                    <button type="button" class="laca-btn laca-btn--solid laca-btn--sm"><?php esc_html_e('Lưu thay đổi', 'laca'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
