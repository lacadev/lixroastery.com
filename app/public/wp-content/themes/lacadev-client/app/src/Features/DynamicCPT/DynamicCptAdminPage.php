<?php

namespace App\Features\DynamicCPT;

/**
 * DynamicCptAdminPage
 *
 * Tạo trang admin tại Laca Admin > Custom Post Types.
 * Cho phép thêm / sửa / xoá CPT động, hỗ trợ taxonomy category, tag, custom.
 * Khi tạo mới sẽ tự sinh archive-{slug}.php + single-{slug}.php.
 */
class DynamicCptAdminPage
{
    const NONCE_ACTION = 'laca_dynamic_cpt_action';
    const NONCE_FIELD  = '_laca_cpt_nonce';
    const CAP          = 'manage_options';
    const MENU_SLUG    = 'laca-dynamic-cpt';
    const PARENT_SLUG  = 'laca-admin';

    private DynamicCptMetaEditor $metaEditor;

    public function __construct()
    {
        $this->metaEditor = new DynamicCptMetaEditor();

        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_post_laca_cpt_save',   [$this, 'handleSave']);
        add_action('admin_post_laca_cpt_delete', [$this, 'handleDelete']);
        add_action('admin_post_laca_cpt_regen',  [$this, 'handleRegen']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Custom Post Types', 'laca'),
            __('Custom Post Types', 'laca'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // -------------------------------------------------------------------------
    // Page Render
    // -------------------------------------------------------------------------

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'laca'));
        }

        // Route: nếu có ?meta=slug thì hiện meta editor thay vì list
        if (isset($_GET['meta'])) {
            $metaSlug = sanitize_key($_GET['meta']);
            $cpts     = DynamicCptManager::getAll();
            $cptData  = [];
            foreach ($cpts as $cpt) {
                if (($cpt['slug'] ?? '') === $metaSlug) {
                    $cptData = $cpt;
                    break;
                }
            }
            $this->metaEditor->renderMetaEditor($metaSlug, $cptData);
            return;
        }

        $cpts       = DynamicCptManager::getAll();
        $editing    = null;
        $edit_index = -1;

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $idx = absint($_GET['edit']);
            if (isset($cpts[$idx])) {
                $editing    = $cpts[$idx];
                $edit_index = $idx;
            }
        }

        $message   = $this->getFlashMessage();
        $msg_type  = sanitize_key($_GET['laca_cpt_msg'] ?? '');
        $page_url  = admin_url('admin.php?page=' . self::MENU_SLUG);
        $generator = new DynamicCptTemplateGenerator();

        ?>
        <div class="wrap laca-cpt-wrap">

            <div class="laca-alert laca-alert--info laca-mb-3">
                <p class="laca-alert__title">🔧 Custom Post Types</p>
                <p class="laca-alert__description">Trang này cho phép tạo và quản lý các loại nội dung riêng (ví dụ: Dự án, Sản phẩm, Dịch vụ...) mà không cần biết lập trình. Sau khi tạo, hệ thống sẽ tự sinh giao diện trang danh sách và trang chi tiết cho loại nội dung đó.</p>
            </div>

            <div class="laca-cpt-header">
                <div>
                    <h1><?php esc_html_e('Custom Post Types', 'laca'); ?></h1>
                    <p class="laca-cpt-subtitle"><?php esc_html_e('Tạo và quản lý post type tùy chỉnh. Template archive/single sẽ được sinh tự động.', 'laca'); ?></p>
                </div>
            </div>

            <?php if ($message) : ?>
                <div class="laca-notice laca-notice--<?php echo in_array($msg_type, ['saved', 'regen_ok', 'deleted'], true) ? 'success' : 'error'; ?>">
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>

            <div class="laca-cpt-layout">

                <!-- ============ LIST ============ -->
                <div class="laca-cpt-list-col">

                    <?php if (empty($cpts)) : ?>
                        <div class="laca-empty-state">
                            <span class="dashicons dashicons-archive laca-empty-icon"></span>
                            <p><?php esc_html_e('Chưa có post type nào.', 'laca'); ?></p>
                            <span class="laca-empty-hint"><?php esc_html_e('Điền form bên phải để tạo mới.', 'laca'); ?></span>
                        </div>
                    <?php else : ?>
                        <div class="laca-cpt-cards">
                        <?php foreach ($cpts as $idx => $cpt) :
                            $cpt_slug = sanitize_key($cpt['slug'] ?? '');
                            $tpl      = $generator->exists($cpt_slug);
                            $tax_list = $this->buildTaxonomyLabel($cpt['taxonomies'] ?? []);
                            $icon     = sanitize_text_field($cpt['menu_icon'] ?? 'dashicons-admin-post');
                            $is_active = ($edit_index === $idx);
                        ?>
                            <div class="laca-cpt-card <?php echo $is_active ? 'laca-cpt-card--active' : ''; ?>">
                                <div class="laca-card-icon">
                                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                </div>
                                <div class="laca-card-body">
                                    <div class="laca-card-title">
                                        <?php echo esc_html($cpt['singular'] ?? $cpt_slug); ?>
                                        <code class="laca-card-slug"><?php echo esc_html($cpt_slug); ?></code>
                                    </div>
                                    <div class="laca-card-meta">
                                        <?php if ($tax_list) : ?>
                                            <span class="laca-badge"><?php echo esc_html($tax_list); ?></span>
                                        <?php endif; ?>
                                        <span class="laca-tpl-status <?php echo $tpl['archive'] ? 'laca-tpl-ok' : 'laca-tpl-miss'; ?>"
                                              title="<?php echo $tpl['archive'] ? 'archive-' . esc_attr($cpt_slug) . '.php ✓' : 'archive-' . esc_attr($cpt_slug) . '.php — chưa tồn tại'; ?>">
                                            <?php echo $tpl['archive'] ? '' : ''; ?>archive
                                        </span>
                                        <span class="laca-tpl-status <?php echo $tpl['single'] ? 'laca-tpl-ok' : 'laca-tpl-miss'; ?>"
                                              title="<?php echo $tpl['single'] ? 'single-' . esc_attr($cpt_slug) . '.php ✓' : 'single-' . esc_attr($cpt_slug) . '.php — chưa tồn tại'; ?>">
                                            single
                                        </span>
                                    </div>
                                </div>
                                <div class="laca-card-actions">
                                    <?php if (!$tpl['archive'] || !$tpl['single']) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-contents">
                                            <input type="hidden" name="action"    value="laca_cpt_regen">
                                            <input type="hidden" name="cpt_slug"  value="<?php echo esc_attr($cpt_slug); ?>">
                                            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                                            <button type="submit" class="laca-btn-icon laca-btn-icon--regen"
                                                    title="<?php esc_attr_e('Sinh lại archive & single template', 'laca'); ?>">
                                                <span class="dashicons dashicons-image-rotate"></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php
                                    $meta_url        = add_query_arg(['meta' => $cpt_slug], $page_url);
                                    $has_meta        = $this->metaEditor->metaFileExists($cpt_slug);
                                    $meta_btn_class  = $has_meta ? 'laca-btn-icon laca-btn-icon--meta-ok' : 'laca-btn-icon';
                                    $meta_btn_title  = $has_meta
                                        ? esc_attr__('Meta Fields (đã có file)', 'laca')
                                        : esc_attr__('Meta Fields (chưa cấu hình)', 'laca');
                                    ?>
                                    <a href="<?php echo esc_url($meta_url); ?>"
                                       class="<?php echo $meta_btn_class; ?>"
                                       title="<?php echo $meta_btn_title; ?>">
                                        <span class="dashicons dashicons-database"></span>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg(['edit' => $idx], $page_url)); ?>"
                                       class="laca-btn-icon" title="<?php esc_attr_e('Sửa', 'laca'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                          class="laca-contents"
                                          onsubmit="return confirm('<?php echo esc_js(__('Xoá post type và file template?', 'laca')); ?>')">
                                        <input type="hidden" name="action"    value="laca_cpt_delete">
                                        <input type="hidden" name="cpt_index" value="<?php echo absint($idx); ?>">
                                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                                        <button type="submit" class="laca-btn-icon laca-btn-icon--danger" title="<?php esc_attr_e('Xoá', 'laca'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div><!-- /.laca-cpt-list-col -->

                <!-- ============ FORM ============ -->
                <div class="laca-cpt-form-col">

                    <div class="laca-form-header">
                        <span class="dashicons <?php echo $editing ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>"></span>
                        <span><?php echo $editing ? esc_html__('Chỉnh sửa Post Type', 'laca') : esc_html__('Thêm Post Type mới', 'laca'); ?></span>
                        <?php if ($editing) : ?>
                            <a href="<?php echo esc_url($page_url); ?>" class="laca-cancel-link"><?php esc_html_e('Huỷ', 'laca'); ?></a>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="laca-cpt-form">
                        <input type="hidden" name="action"    value="laca_cpt_save">
                        <input type="hidden" name="cpt_index" value="<?php echo $edit_index >= 0 ? absint($edit_index) : '-1'; ?>">
                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                        <!-- Section: Định danh -->
                        <div class="laca-form-section">
                            <p class="laca-section-label"><?php esc_html_e('Định danh', 'laca'); ?></p>

                            <div class="laca-field-row laca-field-row--2col">
                                <div class="laca-field">
                                    <label for="cpt_slug">
                                        <?php esc_html_e('Slug', 'laca'); ?>
                                        <span class="laca-req">*</span>
                                        <span class="laca-field-hint"><?php esc_html_e('dùng trong code', 'laca'); ?></span>
                                    </label>
                                    <input type="text" id="cpt_slug" name="cpt_slug"
                                           required pattern="[a-z0-9_-]+" maxlength="20"
                                           placeholder="vd: portfolio"
                                           value="<?php echo esc_attr($editing['slug'] ?? ''); ?>"
                                           <?php echo $editing ? 'readonly class="laca-readonly"' : ''; ?>>
                                    <?php if ($editing) : ?>
                                        <span class="laca-field-note"><?php esc_html_e('Không thể đổi slug', 'laca'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="laca-field">
                                    <label for="cpt_url_slug">
                                        <?php esc_html_e('URL Slug', 'laca'); ?>
                                        <span class="laca-field-hint"><?php esc_html_e('xuất hiện trên URL', 'laca'); ?></span>
                                    </label>
                                    <input type="text" id="cpt_url_slug" name="cpt_url_slug"
                                           pattern="[a-z0-9-]+" maxlength="50"
                                           placeholder="vd: portfolios (mặc định = slug)"
                                           value="<?php echo esc_attr($editing['url_slug'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="laca-field-row laca-field-row--2col">
                                <div class="laca-field">
                                    <label for="cpt_singular"><?php esc_html_e('Tên số ít', 'laca'); ?> <span class="laca-req">*</span></label>
                                    <input type="text" id="cpt_singular" name="cpt_singular"
                                           required
                                           placeholder="vd: Portfolio"
                                           value="<?php echo esc_attr($editing['singular'] ?? ''); ?>">
                                </div>
                                <div class="laca-field">
                                    <label for="cpt_plural"><?php esc_html_e('Tên số nhiều', 'laca'); ?> <span class="laca-req">*</span></label>
                                    <input type="text" id="cpt_plural" name="cpt_plural"
                                           required
                                           placeholder="vd: Portfolios"
                                           value="<?php echo esc_attr($editing['plural'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Hiển thị -->
                        <div class="laca-form-section">
                            <p class="laca-section-label"><?php esc_html_e('Hiển thị', 'laca'); ?></p>

                            <div class="laca-field laca-field--icon-row">
                                <label for="cpt_icon">
                                    <?php esc_html_e('Menu Icon', 'laca'); ?>
                                    <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" class="laca-dashicons-link"><?php esc_html_e('Dashicons ↗', 'laca'); ?></a>
                                </label>
                                <div class="laca-icon-input-wrap">
                                    <span class="laca-icon-preview dashicons" id="laca-icon-preview"></span>
                                    <input type="text" id="cpt_icon" name="cpt_icon"
                                           placeholder="vd: dashicons-portfolio"
                                           value="<?php echo esc_attr($editing['menu_icon'] ?? 'dashicons-admin-post'); ?>">
                                </div>
                            </div>

                            <div class="laca-field">
                                <label><?php esc_html_e('Hỗ trợ (Supports)', 'laca'); ?></label>
                                <div class="laca-cb-group">
                                    <?php $this->renderSupportsCheckboxes($editing['supports'] ?? ['title', 'editor', 'thumbnail']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Archive -->
                        <div class="laca-form-section">
                            <p class="laca-section-label"><?php esc_html_e('Archive', 'laca'); ?></p>

                            <div class="laca-field">
                                <label for="cpt_archive_page_id">
                                    <?php esc_html_e('Trang nội dung Archive (tùy chọn)', 'laca'); ?>
                                    <span class="laca-field-hint"><?php esc_html_e('soạn bằng Block Editor, hiện ở đầu trang archive — giống Shop page của WooCommerce', 'laca'); ?></span>
                                </label>
                                <?php
                                wp_dropdown_pages([
                                    'name'              => 'cpt_archive_page_id',
                                    'id'                => 'cpt_archive_page_id',
                                    'selected'          => (int)($editing['archive_content_page_id'] ?? 0),
                                    'show_option_none'  => __('— Không dùng —', 'laca'),
                                    'option_none_value' => '0',
                                ]);
                                ?>
                            </div>
                        </div>

                        <!-- Section: Taxonomy -->
                        <div class="laca-form-section">
                            <p class="laca-section-label"><?php esc_html_e('Taxonomy', 'laca'); ?></p>
                            <?php $this->renderTaxonomySection($editing['taxonomies'] ?? []); ?>
                        </div>

                        <div class="laca-form-footer">
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $editing ? esc_html__('Cập nhật', 'laca') : esc_html__('Tạo Post Type', 'laca'); ?>
                            </button>
                        </div>

                    </form>
                </div><!-- /.laca-cpt-form-col -->

            </div><!-- /.laca-cpt-layout -->
        </div><!-- /.wrap -->

        <?php $this->renderScripts(count($editing['taxonomies']['custom'] ?? [])); ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Partials
    // -------------------------------------------------------------------------

    private function renderSupportsCheckboxes(array $current): void
    {
        $options = [
            'title'           => 'Title',
            'editor'          => 'Editor',
            'thumbnail'       => 'Featured Image',
            'excerpt'         => 'Excerpt',
            'author'          => 'Author',
            'comments'        => 'Comments',
            'revisions'       => 'Revisions',
            'page-attributes' => 'Page Attributes',
        ];

        foreach ($options as $val => $label) {
            $checked = in_array($val, $current, true) ? ' checked' : '';
            printf(
                '<label class="laca-cb-item"><input type="checkbox" name="cpt_supports[]" value="%s"%s><span>%s</span></label>',
                esc_attr($val),
                $checked,
                esc_html($label)
            );
        }
    }

    private function renderTaxonomySection(array $taxonomies): void
    {
        $cat_checked = !empty($taxonomies['category']) ? ' checked' : '';
        $tag_checked = !empty($taxonomies['tag'])      ? ' checked' : '';
        ?>
        <div class="laca-cb-group laca-cb-group--builtin">
            <label class="laca-cb-item">
                <input type="checkbox" name="tax_category" value="1"<?php echo $cat_checked; ?>>
                <span><?php esc_html_e('Category', 'laca'); ?></span>
                <em class="laca-cb-desc"><?php esc_html_e('dùng chung với Posts', 'laca'); ?></em>
            </label>
            <label class="laca-cb-item">
                <input type="checkbox" name="tax_tag" value="1"<?php echo $tag_checked; ?>>
                <span><?php esc_html_e('Tag', 'laca'); ?></span>
                <em class="laca-cb-desc"><?php esc_html_e('dùng chung với Posts', 'laca'); ?></em>
            </label>
        </div>

        <div class="laca-custom-tax-header">
            <span class="laca-section-label laca-section-label--sm"><?php esc_html_e('Custom Taxonomy', 'laca'); ?></span>
            <button type="button" id="laca-add-tax" class="laca-btn-add">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('Thêm', 'laca'); ?>
            </button>
        </div>

        <div id="laca-custom-tax-list">
            <?php foreach (($taxonomies['custom'] ?? []) as $ti => $tax) :
                $this->renderTaxRow($ti, $tax);
            endforeach; ?>
        </div>
        <?php
    }

    private function renderTaxRow(int $index, array $tax): void
    {
        ?>
        <div class="laca-tax-row">
            <div class="laca-tax-fields">
                <input type="text" name="tax_custom[<?php echo $index; ?>][slug]"
                       placeholder="<?php esc_attr_e('slug (vd: portfolio-cat)', 'laca'); ?>"
                       value="<?php echo esc_attr($tax['slug'] ?? ''); ?>"
                       pattern="[a-z0-9_-]+">
                <input type="text" name="tax_custom[<?php echo $index; ?>][singular]"
                       placeholder="<?php esc_attr_e('Tên số ít (vd: Danh mục)', 'laca'); ?>"
                       value="<?php echo esc_attr($tax['singular'] ?? ''); ?>">
                <input type="text" name="tax_custom[<?php echo $index; ?>][plural]"
                       placeholder="<?php esc_attr_e('Tên số nhiều (vd: Danh mục)', 'laca'); ?>"
                       value="<?php echo esc_attr($tax['plural'] ?? ''); ?>">
            </div>
            <div class="laca-tax-controls">
                <label class="laca-cb-item laca-cb-item--sm">
                    <input type="checkbox" name="tax_custom[<?php echo $index; ?>][hierarchical]" value="1"
                        <?php checked(!empty($tax['hierarchical'])); ?>>
                    <span><?php esc_html_e('Phân cấp', 'laca'); ?></span>
                </label>
                <button type="button" class="laca-btn-icon laca-btn-icon--danger laca-remove-tax" title="Xoá taxonomy">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        <?php
    }

    private function renderStyles(): void
    {
        // Styles moved to resources/styles/admin/skins/_self-authored.scss
    }

    private function renderScripts(int $initialTaxCount): void
    {
        ?>
        <script>
        (function () {
            // Icon preview
            const iconInput   = document.getElementById('cpt_icon');
            const iconPreview = document.getElementById('laca-icon-preview');

            function updateIcon() {
                iconPreview.className = 'laca-icon-preview dashicons ' + (iconInput.value.trim() || 'dashicons-admin-post');
            }
            if (iconInput) {
                updateIcon();
                iconInput.addEventListener('input', updateIcon);
            }

            // Custom taxonomy rows
            let taxIndex = <?php echo absint($initialTaxCount); ?>;

            function bindRemove(row) {
                row.querySelector('.laca-remove-tax').addEventListener('click', function () { row.remove(); });
            }

            document.querySelectorAll('.laca-tax-row').forEach(bindRemove);

            const addBtn = document.getElementById('laca-add-tax');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    const list = document.getElementById('laca-custom-tax-list');
                    const row  = document.createElement('div');
                    row.className = 'laca-tax-row';
                    row.innerHTML =
                        '<div class="laca-tax-fields">' +
                            '<input type="text" name="tax_custom[' + taxIndex + '][slug]" placeholder="slug (vd: portfolio-cat)" pattern="[a-z0-9_-]+">' +
                            '<input type="text" name="tax_custom[' + taxIndex + '][singular]" placeholder="Tên số ít (vd: Danh mục)">' +
                            '<input type="text" name="tax_custom[' + taxIndex + '][plural]"   placeholder="Tên số nhiều (vd: Danh mục)">' +
                        '</div>' +
                        '<div class="laca-tax-controls">' +
                            '<label class="laca-cb-item laca-cb-item--sm">' +
                                '<input type="checkbox" name="tax_custom[' + taxIndex + '][hierarchical]" value="1">' +
                                '<span>Phân cấp</span>' +
                            '</label>' +
                            '<button type="button" class="laca-btn-icon laca-btn-icon--danger laca-remove-tax" title="Xoá taxonomy">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                            '</button>' +
                        '</div>';
                    list.appendChild(row);
                    taxIndex++;
                    bindRemove(row);
                });
            }
        })();
        </script>
        <?php
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

        $slug     = sanitize_key($_POST['cpt_slug']     ?? '');
        $url_slug = sanitize_title($_POST['cpt_url_slug'] ?? '');
        $singular = sanitize_text_field($_POST['cpt_singular'] ?? '');
        $plural   = sanitize_text_field($_POST['cpt_plural']   ?? '');
        $icon     = sanitize_text_field($_POST['cpt_icon']     ?? 'dashicons-admin-post');
        $supports = array_map('sanitize_key', (array)($_POST['cpt_supports'] ?? ['title', 'editor']));
        $archive_page_id = (int)($_POST['cpt_archive_page_id'] ?? 0);
        $index    = (int)($_POST['cpt_index'] ?? -1);

        $page_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!$slug || !$singular || !$plural) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        // Bảo vệ reserved slugs
        $reserved = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'order', 'theme'];
        if (in_array($slug, $reserved, true)) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'exists', $page_url));
            exit;
        }

        $taxonomies = [
            'category' => !empty($_POST['tax_category']),
            'tag'      => !empty($_POST['tax_tag']),
            'custom'   => [],
        ];

        foreach ((array)($_POST['tax_custom'] ?? []) as $tax) {
            $tax_slug = sanitize_key($tax['slug'] ?? '');
            if (!$tax_slug) {
                continue;
            }
            $taxonomies['custom'][] = [
                'slug'         => $tax_slug,
                'singular'     => sanitize_text_field($tax['singular'] ?? $tax_slug),
                'plural'       => sanitize_text_field($tax['plural']   ?? $tax_slug),
                'hierarchical' => !empty($tax['hierarchical']),
            ];
        }

        $cpt_data = [
            'slug'                     => $slug,
            'url_slug'                 => $url_slug,
            'singular'                 => $singular,
            'plural'                   => $plural,
            'menu_icon'                => $icon,
            'supports'                 => $supports,
            'archive_content_page_id'  => $archive_page_id,
            'taxonomies'               => $taxonomies,
        ];

        $cpts    = DynamicCptManager::getAll();
        $is_new  = ($index < 0 || !isset($cpts[$index]));

        if (!$is_new) {
            // Giữ nguyên slug gốc khi edit
            $cpt_data['slug'] = sanitize_key($cpts[$index]['slug']);
            $cpts[$index]     = $cpt_data;
        } else {
            // Kiểm tra slug trùng
            foreach ($cpts as $existing) {
                if (($existing['slug'] ?? '') === $slug) {
                    wp_redirect(add_query_arg('laca_cpt_msg', 'exists', $page_url));
                    exit;
                }
            }
            $cpts[] = $cpt_data;
            (new DynamicCptTemplateGenerator())->generate($slug);
        }

        DynamicCptManager::saveAll($cpts);
        flush_rewrite_rules();

        wp_redirect(add_query_arg('laca_cpt_msg', 'saved', $page_url));
        exit;
    }

    public function handleRegen(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $slug     = sanitize_key($_POST['cpt_slug'] ?? '');
        $page_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!$slug) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        // Chỉ sinh nếu slug tồn tại trong danh sách CPT đã đăng ký
        $known = array_column(DynamicCptManager::getAll(), 'slug');
        if (!in_array($slug, $known, true)) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        $result   = (new DynamicCptTemplateGenerator())->generate($slug);
        $all_ok   = $result['archive'] && $result['single'];

        wp_redirect(add_query_arg('laca_cpt_msg', $all_ok ? 'regen_ok' : 'regen_fail', $page_url));
        exit;
    }

    public function handleDelete(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Permission denied.', 'laca'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $index = absint($_POST['cpt_index'] ?? 0);
        $cpts  = DynamicCptManager::getAll();
        $page_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!isset($cpts[$index])) {
            wp_redirect(add_query_arg('laca_cpt_msg', 'error', $page_url));
            exit;
        }

        $slug = sanitize_key($cpts[$index]['slug'] ?? '');
        array_splice($cpts, $index, 1);
        DynamicCptManager::saveAll($cpts);

        if ($slug) {
            (new DynamicCptTemplateGenerator())->delete($slug);
            $this->metaEditor->deleteMetaFile($slug);
        }

        flush_rewrite_rules();

        wp_redirect(add_query_arg('laca_cpt_msg', 'deleted', $page_url));
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getFlashMessage(): string
    {
        if (!isset($_GET['laca_cpt_msg'])) {
            return '';
        }

        $map = [
            'saved'      => __('Post type đã được lưu. Template archive/single được tạo tự động.', 'laca'),
            'deleted'    => __('Post type đã bị xoá.', 'laca'),
            'exists'     => __('Slug này đã tồn tại, vui lòng chọn slug khác.', 'laca'),
            'error'      => __('Có lỗi xảy ra, vui lòng thử lại.', 'laca'),
            'regen_ok'   => __('Template archive & single đã được sinh thành công!', 'laca'),
            'regen_fail' => __('Không thể sinh template — kiểm tra quyền ghi vào thư mục theme.', 'laca'),
        ];

        $key = sanitize_key($_GET['laca_cpt_msg']);
        return $map[$key] ?? '';
    }

    private function buildTaxonomyLabel(array $taxonomies): string
    {
        $parts = [];

        if (!empty($taxonomies['category'])) {
            $parts[] = 'Category';
        }
        if (!empty($taxonomies['tag'])) {
            $parts[] = 'Tag';
        }
        foreach (($taxonomies['custom'] ?? []) as $t) {
            $parts[] = $t['singular'] ?? ($t['slug'] ?? '');
        }

        return implode(', ', array_filter($parts));
    }
}
