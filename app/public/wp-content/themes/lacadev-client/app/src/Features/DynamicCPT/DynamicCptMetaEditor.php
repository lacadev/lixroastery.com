<?php

namespace App\Features\DynamicCPT;

/**
 * DynamicCptMetaEditor
 *
 * Cung cấp 2 luồng để định nghĩa meta fields cho Dynamic CPT:
 *  1. Field Builder  — UI đơn giản → generate PHP code
 *  2. Code Editor    — chỉnh sửa trực tiếp PHP (full Carbon Fields API)
 *
 * File được lưu tại: app/src/PostTypes/DynamicMeta/{slug}-meta.php
 * DynamicCptManager sẽ require_once file này trên mỗi request.
 *
 * Options được hỗ trợ theo Carbon Fields docs (https://docs.carbonfields.net):
 *  - Tất cả types : default_value, help_text, required, width
 *  - text/textarea/date : placeholder (set_attribute)
 *  - textarea   : rows
 *  - select     : options (value|Label per line)
 *  - checkbox   : option_value
 *  - image/file : value_type (id|url), file_type (file only)
 *  - color      : alpha_enabled, palette (comma-separated hex)
 *  - date       : storage_format
 */
class DynamicCptMetaEditor
{
    const NONCE_ACTION = 'laca_cpt_meta_action';
    const NONCE_FIELD  = '_laca_meta_nonce';
    const CAP          = 'manage_options';

    private string $metaDir;

    public function __construct()
    {
        $this->metaDir = DynamicCptManager::getMetaDir();

        add_action('admin_post_laca_cpt_meta_save',     [$this, 'handleSave']);
        add_action('admin_post_laca_cpt_meta_generate', [$this, 'handleGenerate']);
    }

    // -------------------------------------------------------------------------
    // Public API — dùng bởi DynamicCptManager và DynamicCptAdminPage
    // -------------------------------------------------------------------------

    public function getMetaFilePath(string $slug): string
    {
        return $this->metaDir . '/' . sanitize_key($slug) . '-meta.php';
    }

    public function metaFileExists(string $slug): bool
    {
        return file_exists($this->getMetaFilePath($slug));
    }

    public function getMetaFileContent(string $slug): string
    {
        $file = $this->getMetaFilePath($slug);
        if (file_exists($file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            return file_get_contents($file);
        }
        return $this->generateStub($slug, 'Thông tin ' . $slug, []);
    }

    public function deleteMetaFile(string $slug): void
    {
        $file = $this->getMetaFilePath($slug);
        if (file_exists($file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink($file);
        }
    }

    public function saveMetaFile(string $slug, string $code): bool
    {
        if (!is_dir($this->metaDir)) {
            wp_mkdir_p($this->metaDir);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return file_put_contents($this->getMetaFilePath($slug), $code) !== false;
    }

    // -------------------------------------------------------------------------
    // Code Generation
    // -------------------------------------------------------------------------

    public function generateStub(string $slug, string $containerTitle, array $fields): string
    {
        $lines = '';

        foreach ($fields as $f) {
            $name = sanitize_key($f['name'] ?? '');
            if (!$name) {
                continue;
            }
            $lines .= $this->fieldLine($f);
        }

        if (!$lines) {
            $lines  = "            // Thêm fields tại đây\n";
            $lines .= "            // Field::make('text', 'ten_field', __('Label', 'laca')),\n";
        }

        $safeSlug  = addslashes($slug);
        $safeTitle = addslashes($containerTitle);

        return <<<PHP
<?php

/**
 * Meta fields cho CPT: {$slug}
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('{$safeTitle}', 'laca'))
        ->where('post_type', '=', '{$safeSlug}')
        ->add_fields([
{$lines}        ]);
});
PHP;
    }

    /**
     * Sinh một dòng Field::make(...)->chain()->chain(), từ full config array.
     *
     * @param array $f {
     *   name, label, type, width,
     *   placeholder, default_value, help_text, required,
     *   rows, options, option_value,
     *   value_type, file_type,
     *   alpha_enabled, palette,
     *   storage_format
     * }
     */
    private function fieldLine(array $f): string
    {
        $name  = sanitize_key($f['name']  ?? '');
        $label = sanitize_text_field($f['label'] ?? $name);
        $type  = sanitize_key($f['type']  ?? 'text');
        $width = absint($f['width'] ?? 100);

        $chains = '';

        // ── Universal: width ────────────────────────────────────────────────
        if ($width < 100) {
            $chains .= "\n                ->set_width({$width})";
        }

        // ── Universal: placeholder (text, textarea, date) ────────────────────
        $placeholder = sanitize_text_field($f['placeholder'] ?? '');
        if ($placeholder !== '' && \in_array($type, ['text', 'textarea', 'date'], true)) {
            $safeP   = addslashes($placeholder);
            $chains .= "\n                ->set_attribute('placeholder', '{$safeP}')";
        }

        // ── Universal: default_value ─────────────────────────────────────────
        $default = $f['default_value'] ?? '';
        if ($default !== '') {
            $safeD   = addslashes(sanitize_text_field($default));
            $chains .= "\n                ->set_default_value('{$safeD}')";
        }

        // ── Universal: help_text ─────────────────────────────────────────────
        $help = sanitize_text_field($f['help_text'] ?? '');
        if ($help !== '') {
            $safeH   = addslashes($help);
            $chains .= "\n                ->set_help_text(__('{$safeH}', 'laca'))";
        }

        // ── Universal: required ──────────────────────────────────────────────
        if (!empty($f['required'])) {
            $chains .= "\n                ->set_required(true)";
        }

        // ── Type-specific chains ─────────────────────────────────────────────
        switch ($type) {
            case 'textarea':
                $rows = absint($f['rows'] ?? 5);
                if ($rows !== 5) {
                    $chains .= "\n                ->set_rows({$rows})";
                }
                break;

            case 'select':
                $chains .= $this->buildSelectOptions($f['options'] ?? '');
                break;

            case 'checkbox':
                $optVal = sanitize_text_field($f['option_value'] ?? '');
                if ($optVal !== '') {
                    $safeOV  = addslashes($optVal);
                    $chains .= "\n                ->set_option_value('{$safeOV}')";
                }
                break;

            case 'image':
            case 'file':
                $valueType = \in_array($f['value_type'] ?? 'id', ['id', 'url'], true)
                    ? ($f['value_type'] ?? 'id') : 'id';
                $chains .= "\n                ->set_value_type('{$valueType}')";
                // file_type only for 'file' (image already defaults to images only)
                if ($type === 'file') {
                    $fileType = sanitize_text_field($f['file_type'] ?? '');
                    if ($fileType !== '') {
                        $safeT   = addslashes($fileType);
                        $chains .= "\n                ->set_type('{$safeT}')";
                    }
                }
                break;

            case 'color':
                if (!empty($f['alpha_enabled'])) {
                    $chains .= "\n                ->set_alpha_enabled(true)";
                }
                $palette = sanitize_text_field($f['palette'] ?? '');
                if ($palette !== '') {
                    $colors     = array_filter(array_map('trim', explode(',', $palette)));
                    $colorItems = array_map(static function (string $c): string {
                        return "                    '" . addslashes($c) . "'";
                    }, $colors);
                    if ($colorItems) {
                        $chains .= "\n                ->set_palette([\n" . implode(",\n", $colorItems) . ",\n                ])";
                    }
                }
                break;

            case 'date':
                $fmt = sanitize_text_field($f['storage_format'] ?? '');
                if ($fmt !== '' && $fmt !== 'Y-m-d') {
                    $safeF   = addslashes($fmt);
                    $chains .= "\n                ->set_storage_format('{$safeF}')";
                }
                break;
        }

        return "            \\Carbon_Fields\\Field\\Field::make('{$type}', '{$name}', __('{$label}', 'laca')){$chains},\n";
    }

    /**
     * Build ->add_options([...]) chain từ raw textarea (value|Label per line).
     */
    private function buildSelectOptions(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return "\n                ->add_options([/* 'value' => 'Label' */])";
        }

        $items = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, '|') !== false) {
                [$v, $l] = explode('|', $line, 2);
            } else {
                $v = $l = $line;
            }
            $v       = addslashes(trim($v));
            $l       = addslashes(trim($l));
            $items[] = "                    '{$v}' => '{$l}'";
        }

        if (empty($items)) {
            return "\n                ->add_options([/* 'value' => 'Label' */])";
        }

        return "\n                ->add_options([\n" . implode(",\n", $items) . ",\n                ])";
    }

    // -------------------------------------------------------------------------
    // POST Handlers
    // -------------------------------------------------------------------------

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $slug = sanitize_key($_POST['cpt_slug'] ?? '');
        if (!$slug || !$this->isKnownSlug($slug)) {
            wp_die(esc_html__('Invalid slug.', 'laca'));
        }

        $code = wp_unslash($_POST['meta_code'] ?? '');
        $this->saveMetaFile($slug, $code);

        wp_safe_redirect(admin_url(
            'admin.php?page=laca-dynamic-cpt&meta=' . $slug . '&laca_meta_msg=saved'
        ));
        exit;
    }

    public function handleGenerate(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $slug  = sanitize_key($_POST['cpt_slug'] ?? '');
        $title = sanitize_text_field($_POST['container_title'] ?? ('Thông tin ' . $slug));

        if (!$slug || !$this->isKnownSlug($slug)) {
            wp_die(esc_html__('Invalid slug.', 'laca'));
        }

        $fields = [];
        foreach ((array)($_POST['meta_fields'] ?? []) as $f) {
            $name = sanitize_key($f['name'] ?? '');
            if (!$name) {
                continue;
            }
            $fields[] = [
                'name'           => $name,
                'label'          => sanitize_text_field($f['label']          ?? ''),
                'type'           => sanitize_key($f['type']                  ?? 'text'),
                'width'          => absint($f['width']                       ?? 100),
                'placeholder'    => sanitize_text_field($f['placeholder']    ?? ''),
                'default_value'  => sanitize_text_field($f['default_value']  ?? ''),
                'help_text'      => sanitize_text_field($f['help_text']      ?? ''),
                'required'       => !empty($f['required']),
                // textarea
                'rows'           => absint($f['rows']                        ?? 5),
                // select
                'options'        => sanitize_textarea_field($f['options']    ?? ''),
                // checkbox
                'option_value'   => sanitize_text_field($f['option_value']   ?? ''),
                // image / file
                'value_type'     => sanitize_key($f['value_type']            ?? 'id'),
                'file_type'      => sanitize_text_field($f['file_type']      ?? ''),
                // color
                'alpha_enabled'  => !empty($f['alpha_enabled']),
                'palette'        => sanitize_text_field($f['palette']        ?? ''),
                // date
                'storage_format' => sanitize_text_field($f['storage_format'] ?? ''),
            ];
        }

        $code = $this->generateStub($slug, $title, $fields);
        $this->saveMetaFile($slug, $code);

        wp_safe_redirect(admin_url(
            'admin.php?page=laca-dynamic-cpt&meta=' . $slug . '&laca_meta_msg=generated'
        ));
        exit;
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function renderMetaEditor(string $slug, array $cpt): void
    {
        $code     = $this->getMetaFileContent($slug);
        $filePath = $this->getMetaFilePath($slug);
        $exists   = file_exists($filePath);
        $pageUrl  = admin_url('admin.php?page=laca-dynamic-cpt');
        $msgType  = sanitize_key($_GET['laca_meta_msg'] ?? '');

        $messages = [
            'saved'     => __('Đã lưu code thành công.', 'laca'),
            'generated' => __('Đã generate code từ Field Builder. Kiểm tra và lưu nếu đúng.', 'laca'),
        ];
        $message = $messages[$msgType] ?? '';

        $cmSettings = wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');

        $relPath = $exists
            ? str_replace(ABSPATH, '/', $filePath)
            : __('(chưa tạo — sẽ được tạo khi lưu lần đầu)', 'laca');

        ?>
        <div class="wrap laca-cpt-wrap">

            <?php $this->renderStyles(); ?>

            <div class="laca-cpt-header">
                <div>
                    <a href="<?php echo esc_url($pageUrl); ?>" class="laca-back-link">
                        ← <?php esc_html_e('Danh sách CPT', 'laca'); ?>
                    </a>
                    <h1>
                        <?php esc_html_e('Meta Fields', 'laca'); ?> —
                        <?php echo esc_html($cpt['singular'] ?? $slug); ?>
                        <code class="laca-card-slug"><?php echo esc_html($slug); ?></code>
                    </h1>
                    <p class="laca-cpt-subtitle"><?php echo esc_html($relPath); ?></p>
                </div>
            </div>

            <?php if ($message) : ?>
                <div class="laca-notice laca-notice--success"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <div class="laca-meta-layout">

                <!-- ========== FIELD BUILDER ========== -->
                <div class="laca-meta-builder laca-panel">
                    <div class="laca-panel-head">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('Field Builder', 'laca'); ?>
                        <small><?php esc_html_e('Generate code nhanh → tinh chỉnh bên phải', 'laca'); ?></small>
                    </div>

                    <div class="laca-panel-body">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action"   value="laca_cpt_meta_generate">
                            <input type="hidden" name="cpt_slug" value="<?php echo esc_attr($slug); ?>">
                            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                            <div class="laca-field">
                                <label for="laca-container-title"><?php esc_html_e('Tiêu đề container', 'laca'); ?></label>
                                <input type="text" id="laca-container-title" name="container_title"
                                       value="<?php echo esc_attr('Thông tin ' . ($cpt['singular'] ?? $slug)); ?>"
                                       placeholder="vd: Thông tin dự án">
                            </div>

                            <div id="laca-fields-list">
                                <!-- field blocks injected by JS -->
                            </div>

                            <div class="laca-builder-footer">
                                <button type="button" id="laca-add-field" class="laca-btn-add">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <?php esc_html_e('Thêm field', 'laca'); ?>
                                </button>
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Generate Code →', 'laca'); ?>
                                </button>
                            </div>
                            <p class="laca-warn-note">
                                ⚠ <?php esc_html_e('Generate sẽ ghi đè toàn bộ code editor.', 'laca'); ?>
                            </p>
                        </form>
                    </div>
                </div><!-- /.laca-meta-builder -->

                <!-- ========== CODE EDITOR ========== -->
                <div class="laca-meta-code-col laca-panel">
                    <div class="laca-panel-head">
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Code Editor', 'laca'); ?>
                        <small><?php esc_html_e('Full Carbon Fields API — lưu trực tiếp vào file PHP', 'laca'); ?></small>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action"   value="laca_cpt_meta_save">
                        <input type="hidden" name="cpt_slug" value="<?php echo esc_attr($slug); ?>">
                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                        <textarea id="laca-meta-code" name="meta_code"><?php echo esc_textarea($code); ?></textarea>

                        <div class="laca-code-footer">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Lưu Code', 'laca'); ?>
                            </button>
                            <span class="laca-field-note">
                                <?php echo $exists
                                    ? esc_html__('Ghi đè file hiện có', 'laca')
                                    : esc_html__('Tạo file mới', 'laca'); ?>
                            </span>
                        </div>
                    </form>
                </div><!-- /.laca-meta-code-col -->

            </div><!-- /.laca-meta-layout -->
        </div><!-- /.wrap -->

        <script>
        jQuery(function ($) {

            // ── CodeMirror ───────────────────────────────────────────────────
            <?php if (!empty($cmSettings)) : ?>
            wp.codeEditor.initialize($('#laca-meta-code'), <?php echo wp_json_encode($cmSettings); ?>);
            <?php endif; ?>

            // ── Field Builder ────────────────────────────────────────────────
            var TYPES = [
                { v: 'text',      l: 'Text' },
                { v: 'textarea',  l: 'Textarea' },
                { v: 'rich_text', l: 'Rich Text' },
                { v: 'image',     l: 'Image' },
                { v: 'file',      l: 'File' },
                { v: 'select',    l: 'Select' },
                { v: 'checkbox',  l: 'Checkbox' },
                { v: 'date',      l: 'Date' },
                { v: 'color',     l: 'Color' },
            ];

            // which types support placeholder
            var PLACEHOLDER_TYPES   = ['text', 'textarea', 'date'];
            var HAS_ROWS            = ['textarea'];
            var HAS_OPTIONS         = ['select'];
            var HAS_OPTION_VALUE    = ['checkbox'];
            var HAS_VALUE_TYPE      = ['image', 'file'];
            var HAS_FILE_TYPE       = ['file'];
            var HAS_COLOR_OPTS      = ['color'];
            var HAS_STORAGE_FORMAT  = ['date'];

            var typeOpts = TYPES.map(function (t) {
                return '<option value="' + t.v + '">' + t.l + '</option>';
            }).join('');

            var idx = 0;

            function n(i, key) { return 'meta_fields[' + i + '][' + key + ']'; }

            function inp(i, key, placeholder, cls) {
                cls = cls || '';
                return '<input type="text" name="' + n(i, key) + '" placeholder="' + placeholder + '" class="lbf-' + key + ' ' + cls + '">';
            }

            function addRow() {
                var i = idx++;
                var $block = $('<div class="lbf-block" data-idx="' + i + '">');

                // ── Main row ─────────────────────────────────────────────────
                var $main = $('<div class="lbf-main-row">').appendTo($block);

                $main.append(
                    '<input type="text"   name="' + n(i,'name')  + '" placeholder="field_name" class="lbf-name">',
                    '<input type="text"   name="' + n(i,'label') + '" placeholder="Label"      class="lbf-label">',
                    $('<select name="' + n(i,'type') + '" class="lbf-type">').append(typeOpts),
                    '<input type="number" name="' + n(i,'width') + '" value="100" min="1" max="100" class="lbf-width" title="Width%">',
                    '<button type="button" class="lbf-toggle-opts" title="Options">⚙</button>',
                    '<button type="button" class="lbf-remove"      title="Xóa">✕</button>'
                );

                // ── Options panel (hidden by default) ─────────────────────────
                var $opts = $('<div class="lbf-opts-panel" style="display:none">').appendTo($block);

                // Row 1: universal options
                var $row1 = $('<div class="lbf-opts-row">').appendTo($opts);
                $row1.append(
                    $('<label class="lbf-opt-group lbf-group-placeholder">').html(
                        '<span>Placeholder</span>' + inp(i, 'placeholder', 'Nhập placeholder...')
                    ),
                    $('<label class="lbf-opt-group">').html(
                        '<span>Default value</span>' + inp(i, 'default_value', 'Giá trị mặc định')
                    ),
                    $('<label class="lbf-opt-group">').html(
                        '<span>Help text</span>' + inp(i, 'help_text', 'Mô tả hiển thị dưới field')
                    ),
                    $('<label class="lbf-opt-group lbf-opt-inline">').html(
                        '<input type="checkbox" name="' + n(i,'required') + '" value="1"><span>Required</span>'
                    )
                );

                // Row 2: type-specific
                var $row2 = $('<div class="lbf-opts-row lbf-opts-row--specific">').appendTo($opts);

                // textarea: rows
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-rows" style="display:none">').html(
                        '<span>Rows</span><input type="number" name="' + n(i,'rows') + '" value="5" min="1" max="50" class="lbf-rows">'
                    )
                );

                // select: options textarea
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-options" style="display:none">').html(
                        '<span>Options <em>(value|Label, mỗi dòng 1 option)</em></span>' +
                        '<textarea name="' + n(i,'options') + '" rows="4" placeholder="published|Đã xuất bản\ndraft|Nháp" class="lbf-options"></textarea>'
                    )
                );

                // checkbox: option_value
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-option_value" style="display:none">').html(
                        '<span>Option value <em>(giá trị khi checked)</em></span>' + inp(i, 'option_value', 'vd: 1')
                    )
                );

                // image/file: value_type
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-value_type" style="display:none">').html(
                        '<span>Lưu dưới dạng</span>' +
                        '<select name="' + n(i,'value_type') + '" class="lbf-value_type">' +
                            '<option value="id">ID (khuyến nghị)</option>' +
                            '<option value="url">URL</option>' +
                        '</select>'
                    )
                );

                // file: file_type
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-file_type" style="display:none">').html(
                        '<span>File type <em>(image/audio/video/pdf...)</em></span>' + inp(i, 'file_type', 'vd: image')
                    )
                );

                // color: alpha_enabled
                $row2.append(
                    $('<label class="lbf-opt-group lbf-opt-inline lbf-group-alpha_enabled" style="display:none">').html(
                        '<input type="checkbox" name="' + n(i,'alpha_enabled') + '" value="1"><span>Alpha (opacity)</span>'
                    )
                );

                // color: palette
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-palette" style="display:none">').html(
                        '<span>Palette <em>(hex, cách nhau bằng dấu phẩy)</em></span>' + inp(i, 'palette', '#ffffff,#000000,#ff0000')
                    )
                );

                // date: storage_format
                $row2.append(
                    $('<label class="lbf-opt-group lbf-group-storage_format" style="display:none">').html(
                        '<span>Storage format <em>(mặc định: Y-m-d)</em></span>' + inp(i, 'storage_format', 'Y-m-d')
                    )
                );

                // ── Bind type-change ──────────────────────────────────────────
                var $typeSelect = $main.find('.lbf-type');

                function syncTypeOpts(type) {
                    $opts.find('.lbf-group-placeholder').toggle(PLACEHOLDER_TYPES.includes(type));
                    $opts.find('.lbf-group-rows').toggle(HAS_ROWS.includes(type));
                    $opts.find('.lbf-group-options').toggle(HAS_OPTIONS.includes(type));
                    $opts.find('.lbf-group-option_value').toggle(HAS_OPTION_VALUE.includes(type));
                    $opts.find('.lbf-group-value_type').toggle(HAS_VALUE_TYPE.includes(type));
                    $opts.find('.lbf-group-file_type').toggle(HAS_FILE_TYPE.includes(type));
                    $opts.find('.lbf-group-alpha_enabled, .lbf-group-palette').toggle(HAS_COLOR_OPTS.includes(type));
                    $opts.find('.lbf-group-storage_format').toggle(HAS_STORAGE_FORMAT.includes(type));
                }

                $typeSelect.on('change', function () { syncTypeOpts(this.value); });
                syncTypeOpts($typeSelect.val());

                // ── Toggle opts panel ─────────────────────────────────────────
                $main.find('.lbf-toggle-opts').on('click', function () {
                    $opts.toggle();
                    $(this).toggleClass('lbf-toggle-opts--active');
                });

                // ── Remove ────────────────────────────────────────────────────
                $main.find('.lbf-remove').on('click', function () {
                    $block.remove();
                });

                $('#laca-fields-list').append($block);
            }

            $('#laca-add-field').on('click', addRow);

            // Một row mặc định
            addRow();
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isKnownSlug(string $slug): bool
    {
        return \in_array($slug, array_column(DynamicCptManager::getAll(), 'slug'), true);
    }

    private function renderStyles(): void
    {
        ?>
        <style>
        /* ── Shared ────────────────────────────────────────────────────────── */
        .laca-notice { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; }
        .laca-notice--success { background: #edfaef; border-left: 3px solid #00a32a; color: #1d7a34; }
        .laca-field-note { font-size: 11px; color: #646970; }
        .laca-card-slug { font-size: 11px; font-family: Consolas, monospace; background: #f0f0f1; border: 1px solid #e2e4e7; border-radius: 3px; padding: 1px 5px; color: #50575e; font-weight: 400; }
        .laca-cpt-header { margin-bottom: 20px; }
        .laca-cpt-header h1 { margin: 4px 0; font-size: 20px; font-weight: 600; color: #1d2327; }
        .laca-cpt-subtitle { margin: 0; color: #646970; font-size: 12px; }
        .laca-back-link { display: inline-block; margin-bottom: 6px; font-size: 13px; color: #646970; text-decoration: none; }
        .laca-back-link:hover { color: #2271b1; }

        /* ── Layout ────────────────────────────────────────────────────────── */
        .laca-meta-layout { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; }
        .laca-meta-builder  { flex: 0 0 480px; min-width: 0; }
        .laca-meta-code-col { flex: 1 1 500px; min-width: 0; }

        /* ── Panel ─────────────────────────────────────────────────────────── */
        .laca-panel { background: #fff; border: 1px solid #e2e4e7; border-radius: 6px; overflow: hidden; }
        .laca-panel-head { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #e2e4e7; font-size: 13px; font-weight: 600; color: #1d2327; }
        .laca-panel-head .dashicons { color: #2271b1; font-size: 18px; width: 18px; height: 18px; }
        .laca-panel-head small { font-weight: 400; color: #8c8f94; font-size: 12px; margin-left: 4px; }
        .laca-panel-body { padding: 16px; }

        /* ── Container title field ─────────────────────────────────────────── */
        .laca-field { margin-bottom: 14px; }
        .laca-field label { display: block; font-size: 12px; font-weight: 600; color: #3c434a; margin-bottom: 5px; }
        .laca-field input[type=text] { width: 100%; box-sizing: border-box; height: 32px; padding: 0 8px; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 13px; }

        /* ── Field block ───────────────────────────────────────────────────── */
        .lbf-block { border: 1px solid #e2e4e7; border-radius: 5px; margin-bottom: 8px; overflow: hidden; }

        /* ── Main row ──────────────────────────────────────────────────────── */
        .lbf-main-row { display: flex; align-items: center; gap: 5px; padding: 6px 8px; background: #f9fafb; }
        .lbf-main-row input[type=text],
        .lbf-main-row select { height: 28px; padding: 0 6px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 12px; box-sizing: border-box; }
        .lbf-name  { flex: 1.2; min-width: 0; }
        .lbf-label { flex: 1.4; min-width: 0; }
        .lbf-type  { flex: 1; min-width: 0; }
        .lbf-width { width: 52px !important; flex: none; }

        .lbf-toggle-opts,
        .lbf-remove { background: none; border: 1px solid #e2e4e7; border-radius: 3px; cursor: pointer; padding: 3px 6px; font-size: 12px; line-height: 1; color: #646970; transition: all .1s; }
        .lbf-toggle-opts:hover { background: #f0f6fc; border-color: #2271b1; color: #2271b1; }
        .lbf-toggle-opts--active { background: #f0f6fc; border-color: #2271b1; color: #2271b1; }
        .lbf-remove:hover { background: #fdf3f3; border-color: #f5b8b8; color: #d63638; }

        /* ── Options panel ─────────────────────────────────────────────────── */
        .lbf-opts-panel { padding: 10px 12px; border-top: 1px dashed #e2e4e7; background: #fff; }
        .lbf-opts-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
        .lbf-opts-row:last-child { margin-bottom: 0; }

        .lbf-opt-group { display: flex; flex-direction: column; gap: 4px; flex: 1 1 160px; min-width: 0; }
        .lbf-opt-group span { font-size: 11px; font-weight: 600; color: #646970; white-space: nowrap; }
        .lbf-opt-group em { font-weight: 400; color: #8c8f94; }
        .lbf-opt-group input[type=text],
        .lbf-opt-group input[type=number],
        .lbf-opt-group select { height: 28px; padding: 0 6px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 12px; width: 100%; box-sizing: border-box; }
        .lbf-opt-group textarea { padding: 5px 6px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 12px; width: 100%; box-sizing: border-box; font-family: Consolas, monospace; resize: vertical; }
        .lbf-opt-inline { flex-direction: row; align-items: center; flex: 0 0 auto; gap: 6px; }
        .lbf-rows { width: 60px !important; }

        /* ── Builder footer ────────────────────────────────────────────────── */
        .laca-builder-footer { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
        .laca-btn-add { display: flex; align-items: center; gap: 4px; font-size: 12px; padding: 4px 10px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; color: #2271b1; cursor: pointer; line-height: 1.4; }
        .laca-btn-add:hover { background: #f0f6fc; border-color: #2271b1; }
        .laca-btn-add .dashicons { font-size: 14px; width: 14px; height: 14px; }
        .laca-warn-note { margin: 8px 0 0; font-size: 11px; color: #996800; }

        /* ── Code editor ───────────────────────────────────────────────────── */
        #laca-meta-code { display: none; }
        .CodeMirror { height: 520px; font-size: 13px; font-family: 'Courier New', Consolas, monospace; }
        .laca-code-footer { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid #e2e4e7; background: #f9fafb; }
        </style>
        <?php
    }
}
