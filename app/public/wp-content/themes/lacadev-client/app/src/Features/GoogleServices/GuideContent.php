<?php

namespace App\Features\GoogleServices;

defined('ABSPATH') || exit;

/**
 * Structured content provider and renderer for Google Services guides.
 * Source: doc/GOOGLE-SERVICES-GUIDE.md
 */
class GuideContent
{
    /**
     * Convert markdown formatting to safe HTML:
     * - Replaces {SITE} with home_url()
     * - Replaces {DOMAIN} with site domain host
     * - **bold** to <strong>
     * - `code` to <code>
     * - https:// URLs to <a target="_blank" rel="noopener noreferrer">
     */
    public static function formatText(string $text, string $siteUrl): string
    {
        $domain = wp_parse_url($siteUrl, PHP_URL_HOST) ?: 'lixroastery.com';
        $siteEsc = untrailingslashit($siteUrl);

        $text = str_replace('{SITE}', $siteEsc, $text);
        $text = str_replace('{DOMAIN}', $domain, $text);

        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        // Code
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        // Convert URLs to clickable external links
        $text = preg_replace_callback('~https://[^\s\)<>"\',]+~', function ($matches) {
            $url = rtrim($matches[0], '.,;');
            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
        }, $text);

        return $text;
    }

    /**
     * Section 0: Golden rules (read first)
     */
    public static function getSection0(): array
    {
        return [
            'id'    => 'section-0',
            'title' => __('0. Nguyên tắc vàng (đọc trước)', 'lacadev'),
            'icon'  => 'dashicons-star-filled',
            'open'  => true,
            'type'  => 'bullets',
            'items' => [
                __('**Một tài khoản Google duy nhất** cho cả 3 nơi: Search Console, Google Analytics, Google Cloud — nên là **email của khách hàng** (chủ website), không dùng email cá nhân của dev.', 'lacadev'),
                __('Ở **mọi bước**, nhìn avatar góc phải trên màn hình Google để chắc đang đúng tài khoản. Trình duyệt đăng nhập nhiều tài khoản rất dễ chọn nhầm.', 'lacadev'),
                __('Mã GA chỉ đặt **một nơi**: hoặc ô Measurement ID ở trang này, hoặc Laca Theme → Scripts. Không đặt cả hai.', 'lacadev'),
            ],
        ];
    }

    /**
     * Section 1: Google Search Console — add and verify website
     */
    public static function getSection1(): array
    {
        return [
            'id'    => 'section-1',
            'title' => __('1. Google Search Console — thêm và xác minh website', 'lacadev'),
            'icon'  => 'dashicons-search',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __('Mở https://search.google.com/search-console (đăng nhập email khách).', 'lacadev'),
                __("Bấm ô chọn property góc trái → **Thêm sản phẩm (Add property)**:\n- **Miền (Domain)**: nhập `{DOMAIN}` → thêm bản ghi **TXT** Google đưa vào DNS ở nơi quản lý tên miền (gom cả www/http/https). DNS có thể mất vài phút–vài giờ.\n- **Tiền tố URL (URL prefix)**: nhập đúng `{SITE}/` → chọn xác minh **Thẻ HTML** → copy phần `content=\"…\"` → dán vào **Laca SEO → Cài đặt chung → Webmaster Tools → Google** → Lưu → xoá cache (LiteSpeed) → quay lại bấm **Xác minh**.", 'lacadev'),
                __('Nếu website **đã được xác minh ở tài khoản khác** (vd email dev): đăng nhập tài khoản đó → https://search.google.com/search-console/users (chọn đúng property) → **Thêm người dùng** → email khách, quyền **Chủ sở hữu** (Owner). Khi email khách vào được thì gỡ tài khoản cũ.', 'lacadev'),
                __('Gửi sitemap: https://search.google.com/search-console/sitemaps → nhập `sitemap_index.xml` → Gửi.', 'lacadev'),
            ],
        ];
    }

    /**
     * Section 2: Google Analytics 4
     */
    public static function getSection2(): array
    {
        return [
            'id'    => 'section-2',
            'title' => __('2. Google Analytics 4', 'lacadev'),
            'icon'  => 'dashicons-chart-bar',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __('Mở https://analytics.google.com → **Quản trị (Admin)** (biểu tượng bánh răng góc dưới trái).', 'lacadev'),
                __('Quyền: **Quản lý quyền truy cập tài khoản (Account access management)** → thêm email khách vai trò **Quản trị viên (Administrator)**. Gỡ email cũ khi đã chuyển xong.', 'lacadev'),
                __("Lấy Measurement ID: **Luồng dữ liệu (Data streams)** → chọn luồng Web của website → copy **Mã đo lường** dạng `G-XXXXXXXXXX`.\n- Nếu mã đã chèn ở **Laca Theme → Scripts** thì để trống ô Measurement ID ở trang này (hệ thống cũng tự bỏ qua nếu trùng).", 'lacadev'),
                __('Mục tiêu chuyển đổi (tuỳ chọn): **Quản trị → Sự kiện (Events)** → bật **Đánh dấu là sự kiện chính (Mark as key event)** cho sự kiện cần đo (vd `purchase`, `add_to_cart`, `generate_lead`). Sau đó ghi vào ô "Mục tiêu chuyển đổi" mỗi dòng `tên_sự_kiện|Nhãn hiển thị` — tên phải **giống hệt** tên trong GA.', 'lacadev'),
            ],
        ];
    }

    /**
     * Section 3: Google Cloud — create OAuth connection
     */
    public static function getSection3(string $canonicalUri, string $legacyUri): array
    {
        return [
            'id'    => 'section-3',
            'title' => __('3. Google Cloud — tạo kết nối OAuth (để xem báo cáo trong WordPress)', 'lacadev'),
            'icon'  => 'dashicons-cloud',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __("Tạo project (đăng nhập email khách): https://console.cloud.google.com/projectcreate → đặt tên (vd `LIX Roastery Website`) → Tạo.\nKiểm tra **tên project đang chọn ở thanh trên cùng** trước mọi bước sau.", 'lacadev'),
                __("Bật 3 API (bấm **Bật / Enable** ở từng trang):\n- https://console.cloud.google.com/apis/library/searchconsole.googleapis.com — Google Search Console API\n- https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com — Google Analytics Data API\n- https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com — Google Analytics Admin API", 'lacadev'),
                __("Màn hình đồng ý OAuth: https://console.cloud.google.com/auth/overview → **Bắt đầu (Get started)**:\n- Tên ứng dụng: tên website; Email hỗ trợ: email khách; Đối tượng (Audience): **Bên ngoài (External)**; Thông tin liên hệ: email khách → Tạo.\n- **Đối tượng (Audience)**: https://console.cloud.google.com/auth/audience → bấm **Xuất bản ứng dụng (Publish app)** → Xác nhận.\n(Nếu để chế độ Testing: phải thêm email khách vào **Người dùng thử nghiệm (Test users)**, và **kết nối sẽ tự hết hạn sau 7 ngày**.)", 'lacadev'),
                [
                    'type'         => 'oauth_client',
                    'text_before'  => __("Tạo Client: https://console.cloud.google.com/auth/clients → **Tạo client (Create client)**:\n- Loại ứng dụng: **Ứng dụng web (Web application)**.\n- **URI chuyển hướng được phép (Authorized redirect URIs)** → Thêm URI → dán **chính xác** (dùng nút Sao chép):", 'lacadev'),
                    'canonicalUri' => $canonicalUri,
                    'legacyUri'    => $legacyUri,
                    'text_after'   => __("- Tạo → copy **Client ID** và **Client Secret** (Secret chỉ hiện đầy đủ lúc tạo — lưu lại an toàn).", 'lacadev'),
                ],
                __('Về **Laca Admin → Dịch vụ Google → Google Search Console** → dán Client ID + Client Secret → **Đăng nhập & Cấp quyền với Google** → chọn **đúng email khách** → nếu thấy "Google chưa xác minh ứng dụng này": bấm **Nâng cao → Đi tới … (không an toàn)** (ứng dụng do chính bạn tạo) → **tick tất cả quyền** (Search Console + Analytics) → Tiếp tục.', 'lacadev'),
                __('Sau khi về trang: kiểm tra badge **"Đã kết nối OAuth: <email khách>"** → chọn property Search Console → Lưu. Trang Google Analytics tự tìm property GA4 theo Measurement ID.', 'lacadev'),
                __('Mở `wp-admin/index.php` → khối báo cáo → **Làm mới**.', 'lacadev'),
            ],
        ];
    }

    /**
     * Section 4: Common errors and solutions table
     */
    public static function getSection4(string $scope = 'all'): array
    {
        $allRows = [
            [
                'symptom' => __('Google báo **"Lỗi 403: access_denied — chưa hoàn tất quy trình xác minh"** khi đăng nhập', 'lacadev'),
                'cause'   => __('App OAuth đang **Testing** và email không có trong Test users', 'lacadev'),
                'fix'     => __('Publish app (mục 3.3) hoặc thêm email vào Test users', 'lacadev'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('**"Tài khoản chưa có property nào"** / **HTTP 403 chưa có quyền với property**', 'lacadev'),
                'cause'   => __('Kết nối OAuth bằng **tài khoản Google khác** với tài khoản sở hữu website (từng xảy ra: kết nối bằng email khách nhưng property nằm ở email dev)', 'lacadev'),
                'fix'     => __('Xem badge email đang kết nối; bấm **Đổi tài khoản Google**, hoặc thêm email đang kết nối làm Chủ sở hữu trong Search Console (mục 1.3)', 'lacadev'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('**redirect_uri_mismatch**', 'lacadev'),
                'cause'   => __('URI trong Google Cloud không khớp từng ký tự (http/https, www, thiếu tham số, dư khoảng trắng)', 'lacadev'),
                'fix'     => __('Dùng nút **Sao chép** trên trang, dán nguyên văn', 'lacadev'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Báo cáo báo **"API … has not been used in project … / is disabled"**', 'lacadev'),
                'cause'   => __('Chưa bật API, hoặc bật ở **project khác** với project chứa Client ID', 'lacadev'),
                'fix'     => __('Bật đủ 3 API trong **cùng project** (mục 3.2)', 'lacadev'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Kết nối **tự mất sau ~7 ngày**', 'lacadev'),
                'cause'   => __('App để chế độ Testing (refresh token hết hạn 7 ngày)', 'lacadev'),
                'fix'     => __('Publish app', 'lacadev'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Đổi Client ID/Secret xong báo chưa kết nối', 'lacadev'),
                'cause'   => __('Token cũ thuộc client cũ, hệ thống tự xoá', 'lacadev'),
                'fix'     => __('Bấm kết nối lại', 'lacadev'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Search Console hiện **0 / "Chưa có dữ liệu"**', 'lacadev'),
                'cause'   => __('Property mới; Search Console trễ **2–3 ngày**', 'lacadev'),
                'fix'     => __('Chờ vài ngày; kiểm tra đúng property đã chọn', 'lacadev'),
                'scope'   => 'gsc',
            ],
            [
                'symptom' => __('GA4 **không có số** / không tìm thấy property', 'lacadev'),
                'cause'   => __('Tài khoản kết nối không có quyền với property GA4, hoặc Measurement ID không thuộc tài khoản đó', 'lacadev'),
                'fix'     => __('Thêm email kết nối vào GA (mục 2.2); kiểm tra Measurement ID (mục 2.3)', 'lacadev'),
                'scope'   => 'ga',
            ],
            [
                'symptom' => __('GA **đếm gấp đôi** lượt xem', 'lacadev'),
                'cause'   => __('Mã GA vừa ở Laca Theme → Scripts vừa ở ô Measurement ID', 'lacadev'),
                'fix'     => __('Chỉ giữ một nơi (hệ thống tự bỏ qua nếu trùng ID, nhưng nên dọn)', 'lacadev'),
                'scope'   => 'ga',
            ],
            [
                'symptom' => __('Tỷ lệ chuyển đổi / Mục tiêu không hiện', 'lacadev'),
                'cause'   => __('GA4 chưa đánh dấu sự kiện chính, hoặc tên sự kiện ghi sai', 'lacadev'),
                'fix'     => __('Mục 2.4 — tên sự kiện phải giống hệt GA', 'lacadev'),
                'scope'   => 'ga',
            ],
            [
                'symptom' => __('"Bỏ qua quản trị viên" không có tác dụng', 'lacadev'),
                'cause'   => __('Mã GA đang chèn thủ công qua Laca Theme → Scripts (tuỳ chọn chỉ áp cho mã chèn từ trang này)', 'lacadev'),
                'fix'     => __('Chuyển mã sang ô Measurement ID ở trang này', 'lacadev'),
                'scope'   => 'ga',
            ],
        ];

        $filtered = [];
        foreach ($allRows as $row) {
            if ($scope === 'all' || $row['scope'] === 'both' || $row['scope'] === $scope) {
                $filtered[] = $row;
            }
        }

        return [
            'id'    => 'section-4',
            'title' => __('4. Lỗi đã gặp và cách tránh', 'lacadev'),
            'icon'  => 'dashicons-warning',
            'open'  => false,
            'type'  => 'table',
            'rows'  => $filtered,
        ];
    }

    /**
     * Render the guide accordion details for Search Console page (Sections 0, 1, 3, 4).
     */
    public static function renderForSearchConsole(string $siteUrl, string $canonicalUri, string $legacyUri): void
    {
        $sections = [
            self::getSection0(),
            self::getSection1(),
            self::getSection3($canonicalUri, $legacyUri),
            self::getSection4('all'),
        ];

        self::renderSections($sections, $siteUrl);
    }

    /**
     * Render the guide accordion details for Google Analytics page (Sections 0, 2, 4-ga).
     */
    public static function renderForAnalytics(string $siteUrl): void
    {
        $sections = [
            self::getSection0(),
            self::getSection2(),
            self::getSection4('ga'),
        ];

        self::renderSections($sections, $siteUrl);
    }

    /**
     * Render an array of section definitions.
     */
    private static function renderSections(array $sections, string $siteUrl): void
    {
        echo '<div class="laca-google-guide-wrap" style="margin: 20px 0;">';
        foreach ($sections as $sec) {
            $isOpen = !empty($sec['open']) ? ' open' : '';
            ?>
            <details class="laca-seo-prep-details"<?php echo $isOpen; ?> style="margin-bottom: 12px; border: 1px solid var(--laca-border, #e4e4e7); border-radius: 6px; background: var(--laca-bg, #fff);">
                <summary class="laca-seo-prep-details__summary" style="cursor: pointer; font-weight: 600; padding: 12px 16px; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons <?php echo esc_attr($sec['icon'] ?? 'dashicons-info'); ?>" style="color: var(--laca-accent, #2563eb);"></span>
                    <span><?php echo esc_html($sec['title']); ?></span>
                </summary>
                <div class="laca-seo-prep-details__content" style="padding: 16px 20px; font-size: 13.5px; line-height: 1.65;">
                    <?php if ($sec['type'] === 'bullets'): ?>
                        <ul style="margin: 0 0 0 20px; padding: 0; list-style-type: disc;">
                            <?php foreach ($sec['items'] as $item): ?>
                                <li style="margin-bottom: 8px;">
                                    <?php echo wp_kses_post(self::formatText((string) $item, $siteUrl)); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php elseif ($sec['type'] === 'ordered'): ?>
                        <ol style="margin: 0 0 0 20px; padding: 0;">
                            <?php foreach ($sec['items'] as $item): ?>
                                <li style="margin-bottom: 14px;">
                                    <?php if (is_array($item) && ($item['type'] ?? '') === 'oauth_client'): ?>
                                        <div>
                                            <?php echo nl2br(wp_kses_post(self::formatText($item['text_before'], $siteUrl))); ?>
                                        </div>
                                        <div style="display: flex; gap: 8px; align-items: center; margin: 8px 0; flex-wrap: wrap;">
                                            <code style="background: var(--laca-bg-subtle, #f4f4f5); padding: 5px 10px; border-radius: 4px; font-size: 12px; word-break: break-all;"><?php echo esc_html($item['canonicalUri']); ?></code>
                                            <button type="button" class="button button-small laca-seo-prep-step__copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($item['canonicalUri']); ?>'); alert(window.lacaSeoData?.i18n?.copied || 'Đã sao chép!');">
                                                <span class="dashicons dashicons-admin-page" style="margin-top: 2px;"></span> <?php esc_html_e('Sao chép', 'lacadev'); ?>
                                            </button>
                                        </div>
                                        <div style="font-size: 12px; color: var(--laca-fg-muted, #71717a); margin-bottom: 8px;">
                                            <?php esc_html_e('(Giai đoạn chuyển tiếp có thể thêm cả URI cũ:', 'lacadev'); ?>
                                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                                                <code style="background: var(--laca-bg-subtle, #f4f4f5); padding: 4px 8px; border-radius: 4px; font-size: 11px; word-break: break-all;"><?php echo esc_html($item['legacyUri']); ?></code>
                                                <button type="button" class="button button-small laca-seo-prep-step__copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($item['legacyUri']); ?>'); alert(window.lacaSeoData?.i18n?.copied || 'Đã sao chép!');">
                                                    <span class="dashicons dashicons-admin-page" style="margin-top: 2px;"></span> <?php esc_html_e('Sao chép', 'lacadev'); ?>
                                                </button>
                                            </div>
                                            )
                                        </div>
                                        <div>
                                            <?php echo nl2br(wp_kses_post(self::formatText($item['text_after'], $siteUrl))); ?>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <?php echo nl2br(wp_kses_post(self::formatText((string) $item, $siteUrl))); ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                    <?php elseif ($sec['type'] === 'table'): ?>
                        <div class="laca-seo-table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 8px 0;">
                            <table class="wp-list-table widefat striped" style="margin: 0; border: 1px solid var(--laca-border, #e4e4e7); border-radius: 4px; border-collapse: collapse; width: 100%;">
                                <thead>
                                    <tr style="background: var(--laca-bg-muted, #f4f4f5);">
                                        <th style="font-weight: 600; padding: 10px 14px; width: 28%; text-align: left; border-bottom: 1px solid var(--laca-border, #e4e4e7);"><?php esc_html_e('Triệu chứng', 'lacadev'); ?></th>
                                        <th style="font-weight: 600; padding: 10px 14px; width: 36%; text-align: left; border-bottom: 1px solid var(--laca-border, #e4e4e7);"><?php esc_html_e('Nguyên nhân', 'lacadev'); ?></th>
                                        <th style="font-weight: 600; padding: 10px 14px; width: 36%; text-align: left; border-bottom: 1px solid var(--laca-border, #e4e4e7);"><?php esc_html_e('Cách xử lý / phòng tránh', 'lacadev'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sec['rows'] as $r): ?>
                                        <tr>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                                                <?php echo wp_kses_post(self::formatText($r['symptom'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                                                <?php echo wp_kses_post(self::formatText($r['cause'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border, #e4e4e7);">
                                                <?php echo wp_kses_post(self::formatText($r['fix'], $siteUrl)); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
            <?php
        }
        echo '</div>';
    }
}
