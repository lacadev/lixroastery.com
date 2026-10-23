<?php

namespace App\Settings\LacaAdmin;

/**
 * Hệ thống Xem trước Giao diện Admin (UI Preview Switcher).
 * Hoàn toàn điều khiển qua cookie 'laca_ui_preview', 0 ghi database/options.
 */
class UiPreview
{
    public const COOKIE_NAME = 'laca_ui_preview';

    /**
     * Khai báo các khu vực và phương án cho phép.
     */
    public const AREAS = [
        'menu' => [
            'label' => 'Menu trái WP',
            'sample_url' => 'edit.php?post_type=journal',
            'sample_name' => 'Danh sách bài viết (Journal)',
            'desc' => 'Bố cục, màu sắc và mật độ của thanh menu bên trái WordPress admin.',
            'options' => [
                'a' => [
                    'name' => 'Như cũ (sau R1)',
                    'desc' => 'Menu tối đơn sắc như cũ sau R1 (nền đen/xám #18181b / #27272a, mục chọn #3f3f46).',
                    'detail' => 'Kích thước, font 13px, khoảng cách giữ nguyên gốc WordPress. Nền tối đơn sắc, chữ xám sáng, hover sáng.',
                ],
                'b' => [
                    'name' => 'Nền trắng',
                    'desc' => 'Nền trắng #ffffff, chữ xám đậm #27272a, mục chọn nền #f4f4f5 + vạch trái 3px đen #18181b, chữ 14px 1 dòng.',
                    'detail' => 'Nền trắng đồng bộ với trang, viền phải xám nhạt, mục chọn xám nhẹ vạch trái đen dứt khoát, không ngắt dòng.',
                ],
                'c' => [
                    'name' => 'Gọn tối',
                    'desc' => 'Nền xám đậm #27272a, chữ #d4d4d8, mục chọn nền #18181b chữ trắng, mục con nền #18181b, chiều cao 34px gọn hơn.',
                    'detail' => 'Tone màu than chì đậm hiện đại, các mục co gọn 34px tiết kiệm diện tích cho màn hình nhỏ.',
                ],
            ],
        ],
        'field' => [
            'label' => 'Ô nhập form',
            'sample_url' => 'admin.php?page=app-theme-options.php',
            'sample_name' => 'Theme Options (Carbon Fields)',
            'desc' => 'Phong cách hiển thị của các input, textarea, select trong toàn bộ form admin.',
            'options' => [
                'a' => [
                    'name' => 'Sau R1 (Viền xám)',
                    'desc' => 'Viền xám 1px nền trắng tiêu chuẩn.',
                    'detail' => 'Ô nhập nền trắng, viền xám 1px, bo góc nhẹ 4px, focus viền đen rõ nét.',
                ],
                'b' => [
                    'name' => 'Filled',
                    'desc' => 'Nền #f4f4f5 không viền, focus nền trắng + viền đen #18181b.',
                    'detail' => 'Phong cách flat hiện đại, trường nhập có nền xám nhẹ, khi click vào chuyển nền trắng có viền đen.',
                ],
                'c' => [
                    'name' => 'Underline',
                    'desc' => 'Viền dưới 1px, nền trong suốt, nhãn nhỏ phía trên.',
                    'detail' => 'Phong cách tối giản chỉ có 1 đường gạch dưới, nhãn chữ hoa nhỏ gọn phía trên.',
                ],
            ],
        ],
        'tabs' => [
            'label' => 'Tab chuyển mục',
            'sample_url' => 'admin.php?page=app-theme-options.php',
            'sample_name' => 'Theme Options (Carbon Fields)',
            'desc' => 'Kiểu hiển thị tab chuyển phân mục tại Carbon Fields, WP nav-tab và Laca SEO.',
            'options' => [
                'a' => [
                    'name' => 'Gạch chân (Line)',
                    'desc' => 'Tab gạch chân (line) sau R1.',
                    'detail' => 'Hàng tab nằm ngang, có đường gạch chân dưới tab đang chọn, các tab cách nhau 4px.',
                ],
                'b' => [
                    'name' => 'Viên thuốc (Pill)',
                    'desc' => 'Nút viên nền #f4f4f5, tab chọn nền đen #18181b chữ trắng.',
                    'detail' => 'Mỗi tab là một nút bo tròn mềm mại, tab đang chọn nổi bật với nền đen dứt khoát.',
                ],
                'c' => [
                    'name' => 'Tab dọc bên trái',
                    'desc' => 'Tab dọc bên trái trên màn hình >= 1024px; < 1024px quay về dạng gạch chân.',
                    'detail' => 'Cột tab nằm dọc bên trái nội dung cấu hình, cuộn độc lập, cực kỳ tiện cho trang nhiều mục.',
                ],
            ],
        ],
        'bg' => [
            'label' => 'Nền & Card',
            'sample_url' => 'index.php',
            'sample_name' => 'Bảng tin (Dashboard)',
            'desc' => 'Màu nền trang admin và phong cách đóng khung của các Card / Meta Box.',
            'options' => [
                'a' => [
                    'name' => 'Sau R1',
                    'desc' => 'Nền #fafafa, card trắng viền xám #e4e4e7.',
                    'detail' => 'Nền trang xám rất nhẹ #fafafa, các khối card màu trắng có viền xám tinh tế.',
                ],
                'b' => [
                    'name' => 'Nền #f4f4f5',
                    'desc' => 'Nền #f4f4f5, card trắng không viền bóng nhẹ.',
                    'detail' => 'Nền trang tone xám trung tính #f4f4f5, các card trắng nổi lên nhờ bóng đổ êm ái, không viền.',
                ],
                'c' => [
                    'name' => 'Nền trắng',
                    'desc' => 'Nền trắng #ffffff, card trắng viền xám, phân khối bằng khoảng trắng.',
                    'detail' => 'Nền trang và card cùng màu trắng tinh khôi, phân biệt bằng viền xám và khoảng cách thoáng đãng.',
                ],
            ],
        ],
        'dock' => [
            'label' => 'Menu Laca Admin',
            'sample_url' => 'admin.php?page=laca-admin',
            'sample_name' => 'Laca Admin Hub',
            'desc' => 'Bố cục điều hướng trung tâm quản trị Laca Admin.',
            'options' => [
                'a' => [
                    'name' => 'Sau R1',
                    'desc' => 'Menu Laca Admin chuẩn sau R1 (cột dock 240px).',
                    'detail' => 'Cột menu bên trái 240px, phân nhóm rõ ràng, mục đang chọn nền xám nhẹ.',
                ],
                'b' => [
                    'name' => 'Cột hẹp 200px',
                    'desc' => 'Cột hẹp 200px, tiêu đề 11px, mục 13px vạch trái.',
                    'detail' => 'Co hẹp cột lại 200px giúp tăng diện tích làm việc nội dung chính, mục chọn có vạch trái.',
                ],
                'c' => [
                    'name' => 'Tab ngang',
                    'desc' => 'Bỏ cột dock, thay bằng hàng tab nhóm phía trên nội dung.',
                    'detail' => 'Không chiếm cột bên trái, biến menu thành hàng tab ngang gọn gàng trên đỉnh nội dung.',
                ],
            ],
        ],
        'inspector' => [
            'label' => 'Gutenberg Inspector',
            'sample_url' => 'post.php?post=101&action=edit',
            'sample_name' => 'Chỉnh sửa bài viết (Block Editor)',
            'desc' => 'Panel cấu hình thuộc tính block trong Gutenberg Editor.',
            'options' => [
                'a' => [
                    'name' => 'Sau R1',
                    'desc' => 'Panel cấu hình block chuẩn sau R1.',
                    'detail' => 'Nhãn 13px, các control cao 32px chuẩn Chakra v3 monochromatic.',
                ],
                'b' => [
                    'name' => 'Gọn gàng',
                    'desc' => 'Nhãn 12px xám đậm, control cao 32px, khoảng cách 12px.',
                    'detail' => 'Thu nhỏ nhãn 12px và giảm khoảng cách giữa các trường xuống 12px để thấy nhiều thuộc tính hơn.',
                ],
                'c' => [
                    'name' => 'Icon inline',
                    'desc' => 'Gọn gàng + nút chọn thiết bị nhỏ gọn inline.',
                    'detail' => 'Như phương án B kết hợp thanh chọn thiết bị responsive thành nút inline nhỏ cạnh nhãn.',
                ],
            ],
        ],
    ];

    public function register(): void
    {
        add_action('admin_init', [$this, 'handlePreviewActions']);
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_filter('admin_body_class', [$this, 'filterAdminBodyClass']);
        add_action('admin_footer', [$this, 'renderPreviewBar'], 9999);
        add_action('admin_enqueue_scripts', [$this, 'localizePreviewData'], 25);
    }

    /**
     * Xử lý click chuyển phương án trực tiếp qua query params (đặt cookie và redirect).
     */
    public function handlePreviewActions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] !== 'laca-ui-preview') {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';

        if ($action === 'set_preview') {
            $area = isset($_GET['area']) ? sanitize_key($_GET['area']) : '';
            $opt = isset($_GET['opt']) ? sanitize_key($_GET['opt']) : '';
            $redirect = isset($_GET['redirect']) ? esc_url_raw(wp_unslash($_GET['redirect'])) : '';

            if (isset(self::AREAS[$area]) && in_array($opt, ['a', 'b', 'c'], true)) {
                $state = $this->getPreviewState();
                $state[$area] = $opt;
                $this->savePreviewStateCookie($state);
            }

            if ($redirect) {
                wp_safe_redirect($redirect);
                exit;
            }

            wp_safe_redirect(admin_url('admin.php?page=laca-ui-preview'));
            exit;
        }

        if ($action === 'reset_preview') {
            $this->clearPreviewCookie();
            wp_safe_redirect(admin_url('admin.php?page=laca-ui-preview'));
            exit;
        }
    }

    /**
     * Đăng ký trang Gallery ẩn.
     */
    public function addMenuPage(): void
    {
        add_submenu_page(
            null,
            __('Xem trước Giao diện Admin', 'lacadev'),
            __('Xem trước Giao diện Admin', 'lacadev'),
            'manage_options',
            'laca-ui-preview',
            [$this, 'renderGalleryPage']
        );
    }

    /**
     * Đọc trạng thái preview từ cookie.
     */
    public function getPreviewState(): array
    {
        if (empty($_COOKIE[self::COOKIE_NAME])) {
            return [];
        }

        $raw = stripslashes((string)$_COOKIE[self::COOKIE_NAME]);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $sanitized = [];
        foreach (self::AREAS as $areaKey => $areaConfig) {
            if (isset($decoded[$areaKey])) {
                $opt = strtolower(sanitize_key((string)$decoded[$areaKey]));
                if (in_array($opt, ['a', 'b', 'c'], true)) {
                    $sanitized[$areaKey] = $opt;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Kiểm tra có bất kỳ phương án nào đang được preview khác 'a' không.
     */
    public function hasActivePreview(): bool
    {
        $state = $this->getPreviewState();
        if (empty($state)) {
            return false;
        }

        foreach ($state as $area => $opt) {
            if ($opt !== 'a') {
                return true;
            }
        }

        return false;
    }

    /**
     * Lưu cookie laca_ui_preview.
     */
    private function savePreviewStateCookie(array $state): void
    {
        // Cookie session hết hạn khi đóng trình duyệt, path /wp-admin
        setcookie(
            self::COOKIE_NAME,
            wp_json_encode($state),
            0,
            '/wp-admin',
            '',
            is_ssl(),
            false
        );
        $_COOKIE[self::COOKIE_NAME] = wp_json_encode($state);
    }

    /**
     * Xóa cookie preview.
     */
    private function clearPreviewCookie(): void
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            time() - 3600,
            '/wp-admin',
            '',
            is_ssl(),
            false
        );
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Filter admin_body_class: thêm các class laca-pv-<khu-vực>-<chữ>.
     */
    public function filterAdminBodyClass(string $classes): string
    {
        if (!current_user_can('manage_options')) {
            return $classes;
        }

        $state = $this->getPreviewState();
        $isGallery = isset($_GET['page']) && $_GET['page'] === 'laca-ui-preview';

        $added = [];
        foreach ($state as $area => $opt) {
            $added[] = 'laca-pv-' . $area . '-' . $opt;
        }

        if (!empty($added) || $isGallery) {
            $added[] = 'laca-has-ui-preview';
        }

        if (!empty($added)) {
            $classes .= ' ' . implode(' ', $added);
        }

        return $classes;
    }

    /**
     * Localize dữ liệu cho ui-preview.js.
     */
    public function localizePreviewData(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $state = $this->getPreviewState();
        $isGallery = isset($_GET['page']) && $_GET['page'] === 'laca-ui-preview';

        if (empty($state) && !$isGallery) {
            return;
        }

        // Tìm khu vực active đầu tiên
        $activeArea = 'menu';
        foreach (array_keys(self::AREAS) as $a) {
            if (isset($state[$a])) {
                $activeArea = $a;
                break;
            }
        }

        wp_localize_script('theme-admin-js-bundle', 'LacaUiPreviewData', [
            'areas' => self::AREAS,
            'current' => $state,
            'activeArea' => $activeArea,
            'adminUrl' => admin_url(),
        ]);
    }

    /**
     * Render thanh xem trước cố định ở đáy màn hình.
     */
    public function renderPreviewBar(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $state = $this->getPreviewState();
        $isGallery = isset($_GET['page']) && $_GET['page'] === 'laca-ui-preview';

        if (empty($state) && !$isGallery) {
            return;
        }

        $activeArea = 'menu';
        foreach (array_keys(self::AREAS) as $a) {
            if (isset($state[$a])) {
                $activeArea = $a;
                break;
            }
        }

        $activeOpt = isset($state[$activeArea]) ? $state[$activeArea] : 'a';
        $currentDesc = self::AREAS[$activeArea]['options'][$activeOpt]['desc'] ?? '';
        $sampleUrl = admin_url(self::AREAS[$activeArea]['sample_url'] ?? '');
        ?>
        <div id="laca-preview-bar">
            <div class="laca-pv-left">
                <span class="laca-pv-label"><?php esc_html_e('Xem trước UI:', 'lacadev'); ?></span>
                <select id="laca-pv-area-select" class="laca-pv-select">
                    <?php foreach (self::AREAS as $key => $area): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($activeArea, $key); ?>>
                            <?php echo esc_html($area['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <span class="laca-pv-sep">|</span>

                <div class="laca-pv-options">
                    <button type="button" class="laca-pv-opt-btn <?php echo $activeOpt === 'a' ? 'is-active' : ''; ?>" data-opt="a">A</button>
                    <button type="button" class="laca-pv-opt-btn <?php echo $activeOpt === 'b' ? 'is-active' : ''; ?>" data-opt="b">B</button>
                    <button type="button" class="laca-pv-opt-btn <?php echo $activeOpt === 'c' ? 'is-active' : ''; ?>" data-opt="c">C</button>
                </div>

                <span class="laca-pv-sep">·</span>

                <span id="laca-pv-desc" class="laca-pv-desc" title="<?php echo esc_attr($currentDesc); ?>">
                    <?php echo esc_html($currentDesc); ?>
                </span>
            </div>

            <div class="laca-pv-right">
                <a id="laca-pv-sample-link" href="<?php echo esc_url($sampleUrl); ?>" class="laca-pv-link">
                    <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                    <?php esc_html_e('Đi tới màn mẫu', 'lacadev'); ?>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=laca-ui-preview')); ?>" class="laca-pv-link">
                    <?php esc_html_e('Gallery', 'lacadev'); ?>
                </a>

                <button type="button" id="laca-pv-exit-btn" class="laca-pv-btn-exit">
                    <?php esc_html_e('Thoát xem trước', 'lacadev'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render trang Gallery xem trước các phương án.
     */
    public function renderGalleryPage(): void
    {
        $state = $this->getPreviewState();
        $resetUrl = admin_url('admin.php?page=laca-ui-preview&action=reset_preview');
        $hasAnyActive = !empty($state);
        ?>
        <div class="wrap laca-preview-gallery-wrap" style="max-width: 1200px; margin: 24px auto; padding: 0 16px;">
            <div class="laca-page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700; color: var(--laca-fg); margin: 0 0 8px;">
                        <?php esc_html_e('Hệ thống Xem trước Giao diện Admin', 'lacadev'); ?>
                    </h1>
                    <p style="font-size: 14px; color: var(--laca-fg-muted); margin: 0;">
                        <?php esc_html_e('Chọn từng phương án để kiểm tra trực tiếp trên các màn hình thật của WordPress Admin. Mọi lựa chọn chỉ lưu ở Cookie phiên làm việc, không ghi Database.', 'lacadev'); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <?php if ($hasAnyActive): ?>
                        <a href="<?php echo esc_url($resetUrl); ?>" class="button button-secondary" style="border-radius: 4px;">
                            <?php esc_html_e('Đặt lại về mặc định', 'lacadev'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Banner phím tắt -->
            <div style="background: var(--laca-bg); border: 1px solid var(--laca-border); border-radius: 8px; padding: 12px 16px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-info-outline" style="color: var(--laca-fg-muted);"></span>
                    <span style="font-size: 13px; color: var(--laca-fg);">
                        <strong>Mẹo thao tác:</strong> Dùng thanh bar cố định ở đáy màn hình hoặc bấm các phím mũi tên <strong>&larr; &rarr;</strong> trên bàn phím để chuyển đổi nhanh các phương án A / B / C.
                    </span>
                </div>
            </div>

            <!-- Danh sách các khu vực -->
            <div style="display: flex; flex-direction: column; gap: 32px;">
                <?php foreach (self::AREAS as $areaKey => $area):
                    $currentOpt = $state[$areaKey] ?? 'a';
                    $sampleUrl = admin_url($area['sample_url']);
                ?>
                    <div class="laca-card" style="background: var(--laca-bg); border: 1px solid var(--laca-border); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <!-- Header khu vực -->
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--laca-border); background: var(--laca-bg); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px; color: var(--laca-fg);">
                                    <?php echo esc_html($area['label']); ?>
                                    <span style="font-size: 12px; font-weight: 500; color: var(--laca-fg-muted); margin-left: 8px;">
                                        (Mã: <code><?php echo esc_html($areaKey); ?></code>)
                                    </span>
                                </h2>
                                <p style="font-size: 13px; color: var(--laca-fg-muted); margin: 0;">
                                    <?php echo esc_html($area['desc']); ?>
                                    &bull; Màn hình mẫu: <a href="<?php echo esc_url($sampleUrl); ?>" target="_blank" style="color: var(--laca-fg); font-weight: 500; text-decoration: underline;"><?php echo esc_html($area['sample_name']); ?></a>
                                </p>
                            </div>
                            <div>
                                <span style="display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: var(--laca-accent-subtle); color: var(--laca-accent);">
                                    Đang xem: Phương án <?php echo esc_html(strtoupper($currentOpt)); ?>
                                </span>
                            </div>
                        </div>

                        <!-- 3 Phương án A, B, C -->
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 20px;">
                            <?php foreach ($area['options'] as $optKey => $opt):
                                $isActive = ($currentOpt === $optKey);
                                $setUrl = admin_url('admin.php?page=laca-ui-preview&action=set_preview&area=' . $areaKey . '&opt=' . $optKey . '&redirect=' . urlencode($sampleUrl));
                                $imgRelative = 'resources/images/ui-preview/' . $areaKey . '-' . $optKey . '.png';
                                $imgPath = get_template_directory() . '/' . $imgRelative;
                                $imgUrl = get_template_directory_uri() . '/' . $imgRelative;
                            ?>
                                <div style="border: 2px solid <?php echo $isActive ? 'var(--laca-accent)' : 'var(--laca-border)'; ?>; border-radius: 6px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; background: <?php echo $isActive ? 'var(--laca-bg-subtle)' : 'var(--laca-bg)'; ?>;">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <h3 style="font-size: 15px; font-weight: 700; margin: 0; color: var(--laca-fg);">
                                                Phương án <?php echo esc_html(strtoupper($optKey)); ?>: <?php echo esc_html($opt['name']); ?>
                                            </h3>
                                            <?php if ($isActive): ?>
                                                <span style="font-size: 11px; font-weight: 700; background: var(--laca-accent); color: #ffffff; padding: 2px 6px; border-radius: 4px;">CHỌN</span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="font-size: 13px; color: var(--laca-fg); font-weight: 500; margin: 0 0 6px;">
                                            <?php echo esc_html($opt['desc']); ?>
                                        </p>
                                        <p style="font-size: 12px; color: var(--laca-fg-muted); line-height: 1.5; margin: 0 0 16px;">
                                            <?php echo esc_html($opt['detail']); ?>
                                        </p>

                                        <!-- Ảnh xem trước nếu có -->
                                        <?php if (file_exists($imgPath)): ?>
                                            <div style="margin-bottom: 16px; border: 1px solid var(--laca-border); border-radius: 4px; overflow: hidden; background: #ffffff;">
                                                <img src="<?php echo esc_url($imgUrl); ?>" alt="<?php echo esc_attr($opt['name']); ?>" style="width: 100%; height: auto; display: block;" />
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <a href="<?php echo esc_url($setUrl); ?>" class="button <?php echo $isActive ? 'button-primary' : 'button-secondary'; ?>" style="width: 100%; text-align: center; justify-content: center; height: 34px; line-height: 32px; font-weight: 600;">
                                            <span class="dashicons dashicons-visibility" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px; line-height: 32px;"></span>
                                            <?php esc_html_e('Xem trên màn thật', 'lacadev'); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
