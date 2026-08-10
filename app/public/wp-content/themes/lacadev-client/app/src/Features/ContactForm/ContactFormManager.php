<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * ContactFormManager
 *
 * Admin UI để tạo và quản lý form liên hệ tùy chỉnh.
 * Menu: Appearance > Form Liên Hệ
 *
 * Views:
 *   (default)             → danh sách tất cả forms
 *   ?action=new           → tạo form mới
 *   ?action=edit&id=X     → sửa form
 *   ?action=submissions&id=X → xem submissions
 *
 * Data format (fields column in DB) — row-based:
 *   [ { id, cols: [ { id, span, fields: [ {id, type, name, label, ...} ] } ] } ]
 * Old flat format still supported for display/submissions.
 */
class ContactFormManager
{
    const NONCE_ACTION = 'laca_contact_form_action';
    const NONCE_FIELD = '_laca_cf_nonce';
    const CAP = 'manage_options';
    const MENU_SLUG = 'laca-contact-forms';
    const PARENT_SLUG = 'laca-admin';

    /** Field types được hỗ trợ */
    const FIELD_TYPES = [
        'text' => 'Văn bản (Text)',
        'textarea' => 'Đoạn văn (Textarea)',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
        'number' => 'Số (Number)',
        'select' => 'Dropdown (Select)',
        'multiselect' => 'Chọn nhiều (Multi-select)',
        'radio' => 'Radio button',
        'checkbox' => 'Checkbox',
        'date' => 'Ngày (Date)',
        'datetime' => 'Ngày & Giờ (Datetime)',
        'url' => 'Đường dẫn (URL)',
        'hidden' => 'Ẩn (Hidden)',
    ];

    /** Allowed column spans in 12-col grid */
    const ALLOWED_SPANS = [3, 4, 6, 8, 12];

    public function __construct()
    {
        add_action('admin_init', ['App\Databases\ContactFormTable', 'install']);
        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_laca_cf_save', [$this, 'handleSave']);
        add_action('admin_post_laca_cf_delete', [$this, 'handleDelete']);
        add_action('admin_post_laca_cf_delete_submission', [$this, 'handleDeleteSubmission']);
        add_action('admin_post_laca_cf_mark_read', [$this, 'handleMarkRead']);
        add_action('admin_post_laca_cf_export_csv', [$this, 'handleExportCsv']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, self::MENU_SLUG)) {
            return;
        }

        $action = sanitize_key($_GET['action'] ?? '');
        if (!in_array($action, ['new', 'edit', ''], true)) {
            return;
        }

        $themeRoot = dirname(get_template_directory());
        $themeRootUri = dirname(get_template_directory_uri());
        $sortableFile = $themeRoot . '/node_modules/sortablejs/Sortable.min.js';
        $sortableUrl = $themeRootUri . '/node_modules/sortablejs/Sortable.min.js';

        if (file_exists($sortableFile)) {
            wp_enqueue_script('sortablejs', $sortableUrl, [], '1.15.7', false);
        }
    }

    // =========================================================================
    // MENU
    // =========================================================================

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Form Liên Hệ', 'laca'),
            __('Form Liên Hệ', 'laca'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // =========================================================================
    // PAGE ROUTER
    // =========================================================================

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $action = sanitize_key($_GET['action'] ?? '');
        $id = absint($_GET['id'] ?? 0);

        switch ($action) {
            case 'new':
                $this->renderEditPage(null);
                break;
            case 'edit':
                $form = $id ? ContactFormTable::getForm($id) : null;
                if (!$form) {
                    wp_die(esc_html__('Form không tồn tại.', 'laca'));
                }
                $this->renderEditPage($form);
                break;
            case 'submissions':
                $form = $id ? ContactFormTable::getForm($id) : null;
                if (!$form) {
                    wp_die(esc_html__('Form không tồn tại.', 'laca'));
                }
                $this->renderSubmissionsPage($form);
                break;
            default:
                $this->renderListPage();
        }
    }

    // =========================================================================
    // HELPERS — extract flat field list from either DB format
    // =========================================================================

    /**
     * Extract a flat array of field objects from a form row.
     * Handles both old flat format and new row-based format.
     */
    private static function extractFlatFields(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }
        // Old flat format: first item has 'type' and no 'cols'
        if (isset($raw[0]['type']) && !isset($raw[0]['cols'])) {
            return $raw;
        }
        // New row-based format
        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    /**
     * Convert raw DB data to row-based format for the builder JS.
     * Old flat format is auto-converted: each field → single-col row.
     */
    private static function toRowsFormat(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }
        // Already row-based
        if (isset($raw[0]['cols'])) {
            return $raw;
        }
        // Convert old flat format
        return array_map(function ($field) {
            $span = in_array((int) ($field['col_width'] ?? 12), self::ALLOWED_SPANS, true)
                ? (int) $field['col_width']
                : 12;
            unset($field['col_width']);
            return [
                'id' => 'row_' . ($field['id'] ?? uniqid()),
                'cols' => [
                    [
                        'id' => 'col_' . ($field['id'] ?? uniqid()),
                        'span' => $span,
                        'fields' => [$field],
                    ]
                ],
            ];
        }, $raw);
    }

    // =========================================================================
    // DEFAULT CONTENT
    // =========================================================================

    /**
     * Default fields cho form mới — giống template-contact.php
     */
    private static function defaultFormRows(): array
    {
        return [
            [
                'id' => 'row_default_1',
                'cols' => [
                    [
                        'id' => 'col_d1',
                        'span' => 6,
                        'fields' => [
                            ['id' => 'fd_name', 'type' => 'text', 'name' => 'name', 'label' => 'Họ và tên', 'placeholder' => 'Họ và tên của bạn', 'required' => true, 'options' => []],
                        ]
                    ],
                    [
                        'id' => 'col_d2',
                        'span' => 6,
                        'fields' => [
                            ['id' => 'fd_phone', 'type' => 'phone', 'name' => 'phone_number', 'label' => 'Số điện thoại', 'placeholder' => '09xx xxx xxx', 'required' => true, 'options' => []],
                        ]
                    ],
                ],
            ],
            [
                'id' => 'row_default_2',
                'cols' => [
                    [
                        'id' => 'col_d3',
                        'span' => 12,
                        'fields' => [
                            ['id' => 'fd_email', 'type' => 'email', 'name' => 'email', 'label' => 'Email liên hệ', 'placeholder' => 'email@example.com (Không bắt buộc)', 'required' => false, 'options' => []],
                        ]
                    ],
                ],
            ],
            [
                'id' => 'row_default_3',
                'cols' => [
                    [
                        'id' => 'col_d4',
                        'span' => 12,
                        'fields' => [
                            ['id' => 'fd_msg', 'type' => 'textarea', 'name' => 'message', 'label' => 'Nội dung', 'placeholder' => 'Ý tưởng hoặc lời nhắn gửi...', 'required' => true, 'options' => []],
                        ]
                    ],
                ],
            ],
        ];
    }

    /**
     * Default HTML email body gửi Admin
     */
    private static function defaultAdminEmailBody(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:40px 20px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#111111;line-height:1.6">
  <div style="max-width:540px;margin:0 auto;border:1px solid #e5e5e5;padding:40px">
    <div style="margin-bottom:30px">
      <h1 style="margin:0 0 5px;font-size:20px;font-weight:600;letter-spacing:-0.5px">Thông báo liên hệ mới</h1>
      <p style="margin:0;font-size:13px;color:#666666">$time - $date</p>
    </div>
    <div style="margin-bottom:30px;padding-bottom:30px;border-bottom:1px solid #eeeeee">
      <p style="margin:0 0 10px;font-size:14px"><strong>Người gửi:</strong> $name</p>
      <p style="margin:0 0 10px;font-size:14px"><strong>Số điện thoại:</strong> $phone_number</p>
      <p style="margin:0;font-size:14px"><strong>Email:</strong> $email</p>
    </div>
    <div style="margin-bottom:40px">
      <p style="margin:0 0 10px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px">Nội dung</p>
      <p style="margin:0;white-space:pre-wrap;font-size:15px;line-height:1.7;color:#333333">$message</p>
    </div>
    <div style="margin-top:40px;padding-top:20px;border-top:1px solid #eeeeee">
      <p style="margin:0;font-size:12px;color:#999999">IP: $ip</p>
    </div>
  </div>
</body>
</html>';
    }

    /**
     * Default HTML email body xác nhận gửi Khách hàng
     */
    private static function defaultCustomerEmailBody(): string
    {
        $siteName = get_bloginfo('name');
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:40px 20px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#111111;line-height:1.6">
  <div style="max-width:540px;margin:0 auto;border:1px solid #e5e5e5;padding:40px">
    <div style="margin-bottom:30px">
      <h1 style="margin:0 0 5px;font-size:20px;font-weight:600;letter-spacing:-0.5px">Đã nhận lời nhắn</h1>
      <p style="margin:0;font-size:13px;color:#666666">Cảm ơn bạn đã liên hệ với ' . esc_html($siteName) . '</p>
    </div>
    <div style="margin-bottom:30px">
      <p style="margin:0 0 15px;font-size:15px">Chào <strong>$name</strong>,</p>
      <p style="margin:0;font-size:15px;color:#444444">Tôi đã nhận được tin nhắn cùng số điện thoại <strong>$phone_number</strong> của bạn.</p>
      <p style="margin:10px 0 0;font-size:15px;color:#444444">Tôi sẽ xem xét và phản hồi trong vòng 24 giờ.</p>
    </div>
    <div style="margin-bottom:30px;padding:25px;background:#fafafa;border:1px solid #eeeeee">
      <p style="margin:0 0 10px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px">Tóm tắt nội dung</p>
      <p style="margin:0;font-size:14px;color:#555555">"$message"</p>
    </div>
    <div style="margin-top:40px;padding-top:20px;border-top:1px solid #eeeeee">
      <p style="margin:0;font-size:12px;color:#999999">Đây là email xác nhận tự động từ ' . esc_html($siteName) . '.</p>
    </div>
  </div>
</body>
</html>';
    }

    // =========================================================================
    // LIST PAGE
    // =========================================================================

    private function renderListPage(): void
    {
        $forms = ContactFormTable::getAllForms();
        $pageUrl = admin_url('admin.php?page=' . self::MENU_SLUG);
        $message = $this->getFlashMessage();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1>📋 Quản lý Form Liên Hệ</h1>
                    <p class="laca-cf-subtitle">Tạo và quản lý các form liên hệ. Nhúng bằng shortcode <code>[laca_contact_form id="X"]</code></p>
                </div>
                <a href="<?php echo esc_url($pageUrl . '&action=new'); ?>" class="button button-primary laca-cf-btn-new">
                    + Tạo Form Mới
                </a>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($forms)): ?>
                <div class="laca-cf-empty">
                    <p>Chưa có form nào. <a href="<?php echo esc_url($pageUrl . '&action=new'); ?>">Tạo form đầu tiên</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped laca-cf-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th>Tên Form</th>
                            <th style="width:100px">Số Fields</th>
                            <th style="width:120px">Submissions</th>
                            <th style="width:120px">Chưa đọc</th>
                            <th style="width:140px">Shortcode</th>
                            <th style="width:200px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <?php
                            $flatFields = self::extractFlatFields($form);
                            $formId = (int) $form['id'];
                            $shortcode = '[laca_contact_form id="' . $formId . '"]';
                            $editUrl = $pageUrl . '&action=edit&id=' . $formId;
                            $subsUrl = $pageUrl . '&action=submissions&id=' . $formId;
                            $unreadCount = (int) $form['unread_count'];
                            $totalCount = (int) $form['submission_count'];
                            ?>
                            <tr>
                                <td><?php echo esc_html($formId); ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url($editUrl); ?>">
                                            <?php echo esc_html($form['name']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo count($flatFields); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($subsUrl); ?>">
                                        <?php echo esc_html($totalCount); ?> lượt
                                    </a>
                                </td>
                                <td>
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="laca-cf-badge laca-cf-badge--unread"><?php echo esc_html($unreadCount); ?> mới</span>
                                    <?php else: ?>
                                        <span style="color:#999">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="laca-cf-shortcode" title="Click để copy"
                                          onclick="navigator.clipboard.writeText('<?php echo esc_js($shortcode); ?>').then(()=>alert('Đã copy shortcode!'))"
                                          style="cursor:pointer">
                                        <?php echo esc_html($shortcode); ?>
                                    </code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($editUrl); ?>" class="button button-small">Sửa</a>
                                    <a href="<?php echo esc_url($subsUrl); ?>" class="button button-small">Xem Submissions</a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                          style="display:inline"
                                          class="laca-cf-delete-form">
                                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                                        <input type="hidden" name="action" value="laca_cf_delete">
                                        <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // EDIT / NEW FORM PAGE
    // =========================================================================

    private function renderEditPage(?array $form): void
    {
        $isNew = ($form === null);
        $pageUrl = admin_url('admin.php?page=' . self::MENU_SLUG);
        $formId = $isNew ? 0 : (int) $form['id'];
        $rows = $isNew ? self::defaultFormRows() : self::toRowsFormat($form);
        $message = $this->getFlashMessage();

        $defaultAdminSubject = 'Liên hệ mới: [$name - $phone_number]';
        $defaultAdminBody = self::defaultAdminEmailBody();
        $defaultCustomerSubject = 'Cảm ơn bạn đã liên hệ - ' . get_bloginfo('name');
        $defaultCustomerBody = self::defaultCustomerEmailBody();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1><?php echo $isNew ? '+ Tạo Form Mới' : '✏️ Sửa Form: ' . esc_html($form['name']); ?></h1>
                    <p class="laca-cf-subtitle">
                        <a href="<?php echo esc_url($pageUrl); ?>">← Quay lại danh sách</a>
                        <?php if (!$isNew): ?>
                            &nbsp;|&nbsp;
                            Shortcode: <code onclick="navigator.clipboard.writeText('[laca_contact_form id=&quot;<?php echo esc_js($formId); ?>&quot;]').then(()=>alert('Đã copy!'))" style="cursor:pointer" title="Click để copy">[laca_contact_form id="<?php echo esc_html($formId); ?>"]</code>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="laca-cf-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="action"      value="laca_cf_save">
                <input type="hidden" name="form_id"     value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="fields_json"  id="fields-json-input"  value="<?php echo esc_attr(wp_json_encode($rows)); ?>">
                <input type="hidden" name="style_json"   id="style-json-input"   value="<?php echo esc_attr($form['style_settings'] ?? '{}'); ?>">

                <div class="laca-cf-builder-shell">

                    <!-- ===== CONTROLS (Left) ===== -->
                    <div class="laca-cf-builder-controls">

                        <!-- Tab Nav -->
                        <div class="lcf-tabs">
                            <button type="button" class="lcf-tab-btn is-active" data-tab="settings">⚙ Cài đặt</button>
                            <button type="button" class="lcf-tab-btn" data-tab="fields">⊞ Trường</button>
                            <button type="button" class="lcf-tab-btn" data-tab="styles">✦ Giao diện</button>
                            <button type="button" class="lcf-tab-btn" data-tab="emails">✉ Email</button>
                        </div>

                        <!-- Tab: Cài đặt -->
                        <div id="lcf-panel-settings" class="lcf-tab-panel is-active">
                            <div class="lcf-panel-inner">
                                <div class="laca-cf-field-group">
                                    <label for="cf-name" class="lcf-form-label">Tên form <span class="required">*</span></label>
                                    <input type="text" id="cf-name" name="form_name" class="widefat"
                                           value="<?php echo esc_attr($form['name'] ?? ''); ?>"
                                           placeholder="VD: Form Tư Vấn Miễn Phí" required>
                                </div>
                                <div class="laca-cf-field-group">
                                    <label for="cf-notify-email" class="lcf-form-label">Email nhận thông báo</label>
                                    <input type="email" id="cf-notify-email" name="notify_email" class="widefat"
                                           value="<?php echo esc_attr($form['notify_email'] ?? ''); ?>"
                                           placeholder="Để trống = dùng <?php echo esc_attr(get_option('admin_email')); ?>">
                                    <p class="description">Email admin nhận thông báo mỗi khi có submission mới.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Trường nhập liệu -->
                        <div id="lcf-panel-fields" class="lcf-tab-panel">
                            <div class="lcf-panel-inner">
                                <p class="description" style="margin-bottom:12px;color:#888">
                                    Thêm hàng layout, rồi thêm field vào từng cột. Kéo để di chuyển.
                                </p>
                                <div id="rows-builder" class="laca-cf-rows-builder"></div>
                                <p id="rows-empty-msg" class="laca-cf-fields-empty" style="<?php echo empty($rows) ? '' : 'display:none'; ?>">
                                    Chưa có hàng nào. Thêm hàng bên dưới.
                                </p>
                                <div class="laca-cf-add-row-palette">
                                    <span class="lcf-palette-label">+ Thêm hàng:</span>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('1')">
                                        <span class="lcf-row-preview lcf-rp-1"></span>1 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('2')">
                                        <span class="lcf-row-preview lcf-rp-2"></span>2 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('3')">
                                        <span class="lcf-row-preview lcf-rp-3"></span>3 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('4')">
                                        <span class="lcf-row-preview lcf-rp-4"></span>4 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('1-2')">
                                        <span class="lcf-row-preview lcf-rp-1-2"></span>1/3 + 2/3
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('2-1')">
                                        <span class="lcf-row-preview lcf-rp-2-1"></span>2/3 + 1/3
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Giao diện -->
                        <div id="lcf-panel-styles" class="lcf-tab-panel">
                            <div class="lcf-panel-inner">
                                <div class="lcf-style-grid">
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu chính (Nút bấm)</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-primary-color" oninput="lcfStyleUpdate('primary_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-primary-color-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('primary_color',this.value);document.getElementById('s-primary-color').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu phụ (Hover nút)</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-secondary-color" oninput="lcfStyleUpdate('secondary_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-secondary-color-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('secondary_color',this.value);document.getElementById('s-secondary-color').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu viền Input</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-input-border" oninput="lcfStyleUpdate('input_border_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-input-border-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('input_border_color',this.value);document.getElementById('s-input-border').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu chữ Label</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-label-color" oninput="lcfStyleUpdate('label_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-label-color-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('label_color',this.value);document.getElementById('s-label-color').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Bo góc Nút bấm (px)</label>
                                        <div class="lcf-range-row">
                                            <input type="range" min="0" max="50" id="s-btn-radius"
                                                   oninput="lcfStyleUpdate('btn_border_radius',this.value);document.getElementById('s-btn-radius-num').value=this.value">
                                            <input type="number" min="0" max="50" id="s-btn-radius-num" class="lcf-range-num"
                                                   oninput="lcfStyleUpdate('btn_border_radius',this.value);document.getElementById('s-btn-radius').value=this.value">
                                            <span class="lcf-range-unit">px</span>
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Bo góc Input (px)</label>
                                        <div class="lcf-range-row">
                                            <input type="range" min="0" max="50" id="s-input-radius"
                                                   oninput="lcfStyleUpdate('input_border_radius',this.value);document.getElementById('s-input-radius-num').value=this.value">
                                            <input type="number" min="0" max="50" id="s-input-radius-num" class="lcf-range-num"
                                                   oninput="lcfStyleUpdate('input_border_radius',this.value);document.getElementById('s-input-radius').value=this.value">
                                            <span class="lcf-range-unit">px</span>
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Khoảng cách nhập (Padding CSS)</label>
                                        <input type="text" class="widefat" id="s-input-spacing"
                                               oninput="lcfStyleUpdate('input_spacing',this.value)"
                                               placeholder="Ví dụ: 10px 14px">
                                    </div>
                                    <div class="laca-cf-field-group" style="display:flex;align-items:center;">
                                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:13px;margin-top:10px;">
                                            <input type="checkbox" id="s-show-label" onchange="lcfStyleUpdate('show_label',this.checked)">
                                            Hiển thị Label các trường
                                        </label>
                                    </div>
                                    <div class="laca-cf-field-group" style="grid-column:1/-1">
                                        <label class="lcf-form-label">Chữ nút Submit</label>
                                        <input type="text" class="widefat" id="s-btn-text"
                                               oninput="lcfStyleUpdate('btn_text',this.value)"
                                               placeholder="Gửi thông tin">
                                    </div>
                                    <div class="laca-cf-field-group" style="grid-column:1/-1">
                                        <label class="lcf-form-label">Custom CSS</label>
                                        <textarea class="widefat laca-cf-email-body" id="s-custom-css" rows="5"
                                                  oninput="lcfStyleUpdate('custom_css',this.value)"
                                                  placeholder="/* Nhập CSS tuỳ chỉnh...\n Dùng __FORM__ để ám chỉ class chứa form (ví dụ: __FORM__ .laca-cf-input { ... }) */"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Email -->
                        <div id="lcf-panel-emails" class="lcf-tab-panel">
                            <div class="lcf-panel-inner">
                                <p class="description" style="margin-bottom:12px;color:#888">
                                    Dùng <code>$tên_field</code> để chèn giá trị. Hỗ trợ HTML — preview hiển thị bên phải.
                                </p>
                                <div class="lcf-email-section">
                                    <h3 class="lcf-email-section-title">Email Admin</h3>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Tiêu đề (Subject)</label>
                                        <input type="text" name="email_admin_subject" class="widefat"
                                               value="<?php echo esc_attr($form['email_admin_subject'] ?? $defaultAdminSubject); ?>">
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nội dung (Body — hỗ trợ HTML)</label>
                                        <textarea name="email_admin_body" id="email-admin-body" class="widefat laca-cf-email-body" rows="8"
                                                  oninput="lcfUpdateEmailPreview('admin')"><?php echo esc_textarea($form['email_admin_body'] ?? $defaultAdminBody); ?></textarea>
                                    </div>
                                    <div class="laca-cf-var-hint">
                                        <strong>Biến:</strong>
                                        <code>$name</code> <code>$email</code> <code>$phone_number</code>
                                        <code>$message</code> <code>$ip</code> <code>$date</code> <code>$time</code>
                                    </div>
                                </div>
                                <div class="lcf-email-section" style="margin-top:20px">
                                    <h3 class="lcf-email-section-title">Email Khách hàng</h3>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Tiêu đề (Subject) — để trống = không gửi</label>
                                        <input type="text" name="email_customer_subject" class="widefat"
                                               value="<?php echo esc_attr($form['email_customer_subject'] ?? $defaultCustomerSubject); ?>">
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nội dung (Body — hỗ trợ HTML)</label>
                                        <textarea name="email_customer_body" id="email-customer-body" class="widefat laca-cf-email-body" rows="6"
                                                  oninput="lcfUpdateEmailPreview('customer')"><?php echo esc_textarea($form['email_customer_body'] ?? $defaultCustomerBody); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="lcf-actions-bar">
                            <a href="<?php echo esc_url($pageUrl); ?>" class="button">Huỷ</a>
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $isNew ? 'Tạo Form' : 'Lưu Thay Đổi'; ?>
                            </button>
                        </div>

                    </div><!-- .laca-cf-builder-controls -->

                    <!-- ===== PREVIEW (Right) ===== -->
                    <div class="laca-cf-builder-preview">
                        <div class="lcf-preview-switcher">
                            <button type="button" class="lcf-pv-tab is-active" data-pv="form">Form</button>
                            <button type="button" class="lcf-pv-tab" data-pv="email-admin">Email Admin</button>
                            <button type="button" class="lcf-pv-tab" data-pv="email-customer">Email Khách</button>
                        </div>
                        <div class="lcf-preview-viewport">
                            <div id="lcf-pv-form" class="lcf-pv-panel is-active">
                                <div id="lcf-form-preview-output" class="lcf-pv-form-wrap"></div>
                            </div>
                            <div id="lcf-pv-email-admin" class="lcf-pv-panel">
                                <div id="lcf-email-admin-preview-output" class="lcf-pv-email-wrap"></div>
                            </div>
                            <div id="lcf-pv-email-customer" class="lcf-pv-panel">
                                <div id="lcf-email-customer-preview-output" class="lcf-pv-email-wrap"></div>
                            </div>
                        </div>
                    </div><!-- .laca-cf-builder-preview -->

                </div><!-- .laca-cf-builder-shell -->
            </form>
        </div>

        <script>
            window.LacaContactFormVars = {
                FIELD_TYPES: <?php echo wp_json_encode(self::FIELD_TYPES); ?>,
                rows: <?php echo wp_json_encode($rows); ?>
            };
        </script>
        <?php
    }

    // =========================================================================
    // SUBMISSIONS PAGE
    // =========================================================================

    private function renderSubmissionsPage(array $form): void
    {
        $formId  = (int) $form['id'];
        $pageUrl = admin_url('admin.php?page=' . self::MENU_SLUG);
        $page    = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;
        $subs    = ContactFormTable::getSubmissions($formId, $page, $perPage);
        $total   = ContactFormTable::countSubmissions($formId);
        $pages   = (int) ceil($total / $perPage);
        $fields  = self::extractFlatFields($form);
        $message = $this->getFlashMessage();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1>📥 Submissions — <?php echo esc_html($form['name']); ?></h1>
                    <p class="laca-cf-subtitle"><a href="<?php echo esc_url($pageUrl); ?>">← Quay lại danh sách</a></p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span class="laca-cf-badge laca-cf-badge--total"><?php echo esc_html($total); ?> submission</span>
                    <?php if ($total > 0):
                        $exportUrl = wp_nonce_url(
                            admin_url('admin-post.php?action=laca_cf_export_csv&form_id=' . $formId),
                            self::NONCE_ACTION,
                            self::NONCE_FIELD
                        );
                        ?>
                        <a href="<?php echo esc_url($exportUrl); ?>" class="button button-secondary">
                            ⬇ Xuất CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($subs)): ?>
                <div class="laca-cf-empty"><p>Chưa có submission nào.</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped laca-cf-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th style="width:60px">Đọc</th>
                            <?php foreach ($fields as $field): ?>
                                <th><?php echo esc_html($field['label']); ?></th>
                            <?php endforeach; ?>
                            <th style="width:120px">IP</th>
                            <th style="width:150px">Thời gian</th>
                            <th style="width:80px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subs as $sub): ?>
                            <?php
                            $subId = (int) $sub['id'];
                            $data = json_decode($sub['data'] ?? '{}', true) ?: [];
                            $isRead = (bool) $sub['is_read'];
                            $markUrl = admin_url('admin-post.php?action=laca_cf_mark_read&submission_id=' . $subId . '&form_id=' . $formId . '&' . self::NONCE_FIELD . '=' . wp_create_nonce(self::NONCE_ACTION));
                            $delUrl = admin_url('admin-post.php?action=laca_cf_delete_submission&submission_id=' . $subId . '&form_id=' . $formId . '&' . self::NONCE_FIELD . '=' . wp_create_nonce(self::NONCE_ACTION));
                            ?>
                            <tr class="<?php echo $isRead ? '' : 'laca-cf-row-unread'; ?>">
                                <td><?php echo esc_html($subId); ?></td>
                                <td>
                                    <?php if ($isRead): ?>
                                        <span title="Đã đọc" style="color:#5cb85c">✓</span>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($markUrl); ?>" title="Đánh dấu đã đọc" class="laca-cf-mark-read">👁</a>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($fields as $field): ?>
                                    <td>
                                        <?php
                                        $val = $data[$field['name']] ?? '';
                                        if (is_array($val)) {
                                            echo esc_html(implode(', ', $val));
                                        } else {
                                            echo esc_html($val);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?php echo esc_html($sub['ip_address']); ?></td>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($sub['created_at']))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($delUrl); ?>"
                                       class="button button-small button-link-delete laca-cf-delete-sub">Xoá</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <a href="<?php echo esc_url($pageUrl . '&action=submissions&id=' . $formId . '&paged=' . $i); ?>"
                                   class="button button-small <?php echo $i === $page ? 'button-primary' : ''; ?>">
                                    <?php echo esc_html($i); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // ACTION HANDLERS
    // =========================================================================

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_POST['form_id'] ?? 0);
        $formName = sanitize_text_field($_POST['form_name'] ?? '');

        if (!$formName) {
            wp_redirect($this->buildRedirectUrl($formId, 'error_name'));
            exit;
        }

        $fieldsJson = stripslashes($_POST['fields_json'] ?? '[]');
        $rawData = json_decode($fieldsJson, true) ?: [];

        // Sanitize row-based structure
        $cleanRows = [];
        foreach ($rawData as $row) {
            if (!isset($row['cols'])) {
                continue; // skip malformed
            }
            $cleanCols = [];
            foreach ($row['cols'] as $col) {
                $cleanFields = [];
                foreach ($col['fields'] ?? [] as $field) {
                    if (empty($field['name']) || empty($field['label'])) {
                        continue;
                    }
                    $cleanFields[] = [
                        'id' => sanitize_key($field['id'] ?? uniqid('field_', true)),
                        'type' => in_array($field['type'], array_keys(self::FIELD_TYPES), true) ? $field['type'] : 'text',
                        'name' => sanitize_key($field['name']),
                        'label' => sanitize_text_field($field['label']),
                        'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                        'required' => !empty($field['required']),
                        'options' => array_map('sanitize_text_field', (array) ($field['options'] ?? [])),
                    ];
                }
                $span = (int) ($col['span'] ?? 12);
                $cleanCols[] = [
                    'id' => sanitize_key($col['id'] ?? uniqid('col_', true)),
                    'span' => in_array($span, self::ALLOWED_SPANS, true) ? $span : 12,
                    'fields' => $cleanFields,
                ];
            }
            $cleanRows[] = [
                'id' => sanitize_key($row['id'] ?? uniqid('row_', true)),
                'cols' => $cleanCols,
            ];
        }

        // Parse + sanitize style_json
        $styleJson = stripslashes($_POST['style_json'] ?? '{}');
        $rawStyle = json_decode($styleJson, true) ?: [];
        $cleanStyle = [];
        foreach (['primary_color', 'secondary_color', 'input_border_color', 'label_color'] as $colorKey) {
            if (!empty($rawStyle[$colorKey])) {
                $hex = sanitize_hex_color($rawStyle[$colorKey]);
                if ($hex) {
                    $cleanStyle[$colorKey] = $hex;
                }
            }
        }
        foreach (['btn_border_radius', 'input_border_radius'] as $numKey) {
            if (isset($rawStyle[$numKey])) {
                $cleanStyle[$numKey] = max(0, min(50, (int) $rawStyle[$numKey]));
            }
        }
        if (!empty($rawStyle['btn_text'])) {
            $cleanStyle['btn_text'] = sanitize_text_field($rawStyle['btn_text']);
        }
        if (!empty($rawStyle['input_spacing'])) {
            $cleanStyle['input_spacing'] = sanitize_text_field($rawStyle['input_spacing']);
        }
        if (isset($rawStyle['hide_labels'])) {
            $cleanStyle['hide_labels'] = (bool) $rawStyle['hide_labels'];
        }
        if (!empty($rawStyle['custom_css'])) {
            // Strip tags but allow proper CSS syntax, wp_strip_all_tags handles basic sanitization
            $cleanStyle['custom_css'] = wp_strip_all_tags(stripslashes($rawStyle['custom_css']));
        }

        $data = [
            'name' => $formName,
            'fields' => $cleanRows,
            'notify_email' => sanitize_email($_POST['notify_email'] ?? ''),
            'email_admin_subject' => sanitize_text_field($_POST['email_admin_subject'] ?? ''),
            'email_admin_body' => wp_kses_post(stripslashes($_POST['email_admin_body'] ?? '')),
            'email_customer_subject' => sanitize_text_field($_POST['email_customer_subject'] ?? ''),
            'email_customer_body' => wp_kses_post(stripslashes($_POST['email_customer_body'] ?? '')),
            'style_settings' => $cleanStyle,
        ];

        if ($formId > 0) {
            ContactFormTable::updateForm($formId, $data);
            $redirectId = $formId;
        } else {
            $redirectId = ContactFormTable::insertForm($data);
        }

        wp_redirect($this->buildRedirectUrl($redirectId, 'saved'));
        exit;
    }

    public function handleDelete(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_POST['form_id'] ?? 0);
        if ($formId > 0) {
            ContactFormTable::deleteForm($formId);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&laca_msg=deleted'));
        exit;
    }

    public function handleDeleteSubmission(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $subId = absint($_GET['submission_id'] ?? 0);
        $formId = absint($_GET['form_id'] ?? 0);
        if ($subId > 0) {
            ContactFormTable::deleteSubmission($subId);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&action=submissions&id=' . $formId . '&laca_msg=deleted'));
        exit;
    }

    public function handleMarkRead(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $subId = absint($_GET['submission_id'] ?? 0);
        $formId = absint($_GET['form_id'] ?? 0);
        if ($subId > 0) {
            ContactFormTable::markRead($subId);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&action=submissions&id=' . $formId . '&laca_msg=marked_read'));
        exit;
    }

    public function handleExportCsv(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_GET['form_id'] ?? 0);
        $form = $formId ? ContactFormTable::getForm($formId) : null;
        if (!$form) {
            wp_die(esc_html__('Form không tồn tại.', 'laca'));
        }

        $fields = self::extractFlatFields($form);
        $subs = ContactFormTable::getSubmissions($formId, 1, 9999);

        $filename = 'submissions-form-' . $formId . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        $headers = ['#', 'Đọc', 'IP', 'Thời gian'];
        foreach ($fields as $field) {
            $headers[] = $field['label'];
        }
        fputcsv($out, $headers);

        // Data rows
        foreach ($subs as $idx => $sub) {
            $data = json_decode($sub['data'] ?? '{}', true) ?: [];
            $row = [
                $sub['id'],
                $sub['is_read'] ? 'Đã đọc' : 'Chưa đọc',
                $sub['ip_address'],
                date_i18n('d/m/Y H:i', strtotime($sub['created_at'])),
            ];
            foreach ($fields as $field) {
                $val = $data[$field['name']] ?? '';
                $row[] = is_array($val) ? implode(', ', $val) : $val;
            }
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function buildRedirectUrl(int $formId, string $msg): string
    {
        $base = admin_url('admin.php?page=' . self::MENU_SLUG);
        if ($formId > 0) {
            return $base . '&action=edit&id=' . $formId . '&laca_msg=' . $msg;
        }
        return $base . '&laca_msg=' . $msg;
    }

    private function getFlashMessage(): ?array
    {
        $msg = sanitize_key($_GET['laca_msg'] ?? '');
        $map = [
            'saved' => ['type' => 'success', 'text' => 'Đã lưu form thành công.'],
            'deleted' => ['type' => 'success', 'text' => 'Đã xoá thành công.'],
            'marked_read' => ['type' => 'success', 'text' => 'Đã đánh dấu đã đọc.'],
            'error_name' => ['type' => 'error', 'text' => 'Vui lòng nhập tên form.'],
        ];
        return $map[$msg] ?? null;
    }
}