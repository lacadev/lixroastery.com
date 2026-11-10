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
        $domain = wp_parse_url($siteUrl, PHP_URL_HOST) ?: 'example.com';
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
            'title' => __('0. Nguyên tắc vàng (đọc trước)', 'laca'),
            'icon'  => 'dashicons-star-filled',
            'open'  => true,
            'type'  => 'bullets',
            'items' => [
                __('**Một tài khoản Google duy nhất** cho cả 3 nơi: Search Console, Google Analytics, Google Cloud — nên là **email của khách hàng** (chủ website), không dùng email cá nhân của dev.', 'laca'),
                __('Ở **mọi bước**, nhìn avatar góc phải trên màn hình Google để chắc đang đúng tài khoản. Trình duyệt đăng nhập nhiều tài khoản rất dễ chọn nhầm.', 'laca'),
                __('Mã GA chỉ đặt **một nơi**: hoặc ô Measurement ID ở trang này, hoặc Laca Theme → Scripts. Không đặt cả hai.', 'laca'),
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
            'title' => __('1. Google Search Console — thêm và xác minh website', 'laca'),
            'icon'  => 'dashicons-search',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __('Mở https://search.google.com/search-console (đăng nhập email khách).', 'laca'),
                __("Bấm ô chọn property góc trái → **Thêm sản phẩm (Add property)**:\n- **Miền (Domain)**: nhập `{DOMAIN}` → thêm bản ghi **TXT** Google đưa vào DNS ở nơi quản lý tên miền (gom cả www/http/https). DNS có thể mất vài phút–vài giờ.\n- **Tiền tố URL (URL prefix)**: nhập đúng `{SITE}/` → chọn xác minh **Thẻ HTML** → copy phần `content=\"…\"` → dán vào **Laca SEO → Cài đặt chung → Webmaster Tools → Google** → Lưu → xoá cache (LiteSpeed) → quay lại bấm **Xác minh**.", 'laca'),
                __('Nếu website **đã được xác minh ở tài khoản khác** (vd email dev): đăng nhập tài khoản đó → https://search.google.com/search-console/users (chọn đúng property) → **Thêm người dùng** → email khách, quyền **Chủ sở hữu** (Owner). Khi email khách vào được thì gỡ tài khoản cũ.', 'laca'),
                __('Gửi sitemap: https://search.google.com/search-console/sitemaps → nhập `sitemap_index.xml` → Gửi.', 'laca'),
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
            'title' => __('2. Google Analytics 4', 'laca'),
            'icon'  => 'dashicons-chart-bar',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __('Mở https://analytics.google.com → **Quản trị (Admin)** (biểu tượng bánh răng góc dưới trái).', 'laca'),
                __('Quyền: **Quản lý quyền truy cập tài khoản (Account access management)** → thêm email khách vai trò **Quản trị viên (Administrator)**. Gỡ email cũ khi đã chuyển xong.', 'laca'),
                __("Lấy Measurement ID: **Luồng dữ liệu (Data streams)** → chọn luồng Web của website → copy **Mã đo lường** dạng `G-XXXXXXXXXX`.\n- Nếu mã đã chèn ở **Laca Theme → Scripts** thì để trống ô Measurement ID ở trang này (hệ thống cũng tự bỏ qua nếu trùng).", 'laca'),
                __('Mục tiêu chuyển đổi (tuỳ chọn): **Quản trị → Sự kiện (Events)** → bật **Đánh dấu là sự kiện chính (Mark as key event)** cho sự kiện cần đo (vd `purchase`, `add_to_cart`, `generate_lead`). Sau đó ghi vào ô "Mục tiêu chuyển đổi" mỗi dòng `tên_sự_kiện|Nhãn hiển thị` — tên phải **giống hệt** tên trong GA.', 'laca'),
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
            'title' => __('3. Google Cloud — tạo kết nối OAuth (để xem báo cáo trong WordPress)', 'laca'),
            'icon'  => 'dashicons-cloud',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __("Tạo project (đăng nhập email khách): https://console.cloud.google.com/projectcreate → đặt tên (vd `Tên website của bạn`) → Tạo.\nKiểm tra **tên project đang chọn ở thanh trên cùng** trước mọi bước sau.", 'laca'),
                __("Bật 3 API (bấm **Bật / Enable** ở từng trang):\n- https://console.cloud.google.com/apis/library/searchconsole.googleapis.com — Google Search Console API\n- https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com — Google Analytics Data API\n- https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com — Google Analytics Admin API", 'laca'),
                __("Màn hình đồng ý OAuth: https://console.cloud.google.com/auth/overview → **Bắt đầu (Get started)**:\n- Tên ứng dụng: tên website; Email hỗ trợ: email khách; Đối tượng (Audience): **Bên ngoài (External)**; Thông tin liên hệ: email khách → Tạo.\n- **Đối tượng (Audience)**: https://console.cloud.google.com/auth/audience → bấm **Xuất bản ứng dụng (Publish app)** → Xác nhận.\n(Nếu để chế độ Testing: phải thêm email khách vào **Người dùng thử nghiệm (Test users)**, và **kết nối sẽ tự hết hạn sau 7 ngày**.)", 'laca'),
                [
                    'type'         => 'oauth_client',
                    'text_before'  => __("Tạo Client: https://console.cloud.google.com/auth/clients → **Tạo client (Create client)**:\n- Loại ứng dụng: **Ứng dụng web (Web application)**.\n- **URI chuyển hướng được phép (Authorized redirect URIs)** → Thêm URI → dán **chính xác** (dùng nút Sao chép):", 'laca'),
                    'canonicalUri' => $canonicalUri,
                    'legacyUri'    => $legacyUri,
                    'text_after'   => __("- Tạo → copy **Client ID** và **Client Secret** (Secret chỉ hiện đầy đủ lúc tạo — lưu lại an toàn).", 'laca'),
                ],
                __('Về **Laca Admin → Dịch vụ Google → Google Search Console** → dán Client ID + Client Secret → **Đăng nhập & Cấp quyền với Google** → chọn **đúng email khách** → nếu thấy "Google chưa xác minh ứng dụng này": bấm **Nâng cao → Đi tới … (không an toàn)** (ứng dụng do chính bạn tạo) → **tick tất cả quyền** (Search Console + Analytics) → Tiếp tục.', 'laca'),
                __('Sau khi về trang: kiểm tra badge **"Đã kết nối OAuth: <email khách>"** → chọn property Search Console → Lưu. Trang Google Analytics tự tìm property GA4 theo Measurement ID.', 'laca'),
                __('Mở `wp-admin/index.php` → khối báo cáo → **Làm mới**.', 'laca'),
            ],
        ];
    }

    /**
     * Section 3b: PageSpeed Insights API key (optional but recommended)
     */
    public static function getSection3b(): array
    {
        return [
            'id'    => 'section-3b',
            'title' => __('3b. PageSpeed Insights API key (tuỳ chọn nhưng nên có)', 'laca'),
            'icon'  => 'dashicons-performance',
            'open'  => false,
            'type'  => 'ordered',
            'items' => [
                __('Đăng nhập email khách, chọn đúng project (cùng project OAuth).', 'laca'),
                __('Bật API: https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com → Bật.', 'laca'),
                __('Tạo key: https://console.cloud.google.com/apis/credentials → **Tạo thông tin xác thực (Create credentials)** → **Khoá API (API key)**.', 'laca'),
                __('Bấm vào tên key vừa tạo → **Hạn chế API (API restrictions)**: chọn **Chỉ hạn chế khoá (Restrict key)** → tick chọn duy nhất **PageSpeed Insights API** → Lưu. Không đặt hạn chế IP/referrer (máy chủ hosting gọi từ IP có thể đổi → key bị từ chối).', 'laca'),
                __('Về **Laca Admin → Dịch vụ Google → Search Console** → ô "PageSpeed API Key" (hoặc liên kết `#laca-psi-key`) → dán key → Lưu. Sau đó về `index.php` → Kiểm tra PageSpeed.', 'laca'),
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
                'symptom' => __('Google báo **"Lỗi 403: access_denied — chưa hoàn tất quy trình xác minh"** khi đăng nhập', 'laca'),
                'cause'   => __('App OAuth đang **Testing** và email không có trong Test users', 'laca'),
                'fix'     => __('Publish app (mục 3.3) hoặc thêm email vào Test users', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('**"Tài khoản chưa có property nào"** / **HTTP 403 chưa có quyền với property**', 'laca'),
                'cause'   => __('Kết nối OAuth bằng **tài khoản Google khác** với tài khoản sở hữu website (từng xảy ra: kết nối bằng email khách nhưng property nằm ở email dev)', 'laca'),
                'fix'     => __('Xem badge email đang kết nối; bấm **Đổi tài khoản Google**, hoặc thêm email đang kết nối làm Chủ sở hữu trong Search Console (mục 1.3)', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('**redirect_uri_mismatch**', 'laca'),
                'cause'   => __('URI trong Google Cloud không khớp từng ký tự (http/https, www, thiếu tham số, dư khoảng trắng)', 'laca'),
                'fix'     => __('Dùng nút **Sao chép** trên trang, dán nguyên văn', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Báo cáo báo **"API … has not been used in project … / is disabled"**', 'laca'),
                'cause'   => __('Chưa bật API, hoặc bật ở **project khác** với project chứa Client ID', 'laca'),
                'fix'     => __('Bật đủ 3 API trong **cùng project** (mục 3.2)', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('PageSpeed báo **"Quota exceeded … pagespeedonline"** / Lỗi 429', 'laca'),
                'cause'   => __('Chưa có PageSpeed API key, dùng chung hạn mức miễn phí của Google và thường bị hết trong ngày', 'laca'),
                'fix'     => __('Tạo API key riêng cho PageSpeed Insights theo **mục 3b**', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Kết nối **tự mất sau ~7 ngày**', 'laca'),
                'cause'   => __('App để chế độ Testing (refresh token hết hạn 7 ngày)', 'laca'),
                'fix'     => __('Publish app', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Đổi Client ID/Secret xong báo chưa kết nối', 'laca'),
                'cause'   => __('Token cũ thuộc client cũ, hệ thống tự xoá', 'laca'),
                'fix'     => __('Bấm kết nối lại', 'laca'),
                'scope'   => 'both',
            ],
            [
                'symptom' => __('Search Console hiện **0 / "Chưa có dữ liệu"**', 'laca'),
                'cause'   => __('Property mới; Search Console trễ **2–3 ngày**', 'laca'),
                'fix'     => __('Chờ vài ngày; kiểm tra đúng property đã chọn', 'laca'),
                'scope'   => 'gsc',
            ],
            [
                'symptom' => __('GA4 **không có số** / không tìm thấy property', 'laca'),
                'cause'   => __('Tài khoản kết nối không có quyền với property GA4, hoặc Measurement ID không thuộc tài khoản đó', 'laca'),
                'fix'     => __('Thêm email kết nối vào GA (mục 2.2); kiểm tra Measurement ID (mục 2.3)', 'laca'),
                'scope'   => 'ga',
            ],
            [
                'symptom' => __('GA **đếm gấp đôi** lượt xem', 'laca'),
                'cause'   => __('Mã GA vừa ở Laca Theme → Scripts vừa ở ô Measurement ID', 'laca'),
                'fix'     => __('Chỉ giữ một nơi (hệ thống tự bỏ qua nếu trùng ID, nhưng nên dọn)', 'laca'),
                'scope'   => 'ga',
            ],
            [
                'symptom' => __('Tỷ lệ chuyển đổi / Mục tiêu không hiện', 'laca'),
                'cause'   => __('GA4 chưa đánh dấu sự kiện chính, hoặc tên sự kiện ghi sai', 'laca'),
                'fix'     => __('Mục 2.4 — tên sự kiện phải giống hệt GA', 'laca'),
                'scope'   => 'ga',
            ],
            [
                'symptom' => __('"Bỏ qua quản trị viên" không có tác dụng', 'laca'),
                'cause'   => __('Mã GA đang chèn thủ công qua Laca Theme → Scripts (tuỳ chọn chỉ áp cho mã chèn từ trang này)', 'laca'),
                'fix'     => __('Chuyển mã sang ô Measurement ID ở trang này', 'laca'),
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
            'title' => __('4. Lỗi đã gặp và cách tránh', 'laca'),
            'icon'  => 'dashicons-warning',
            'open'  => false,
            'type'  => 'table',
            'rows'  => $filtered,
        ];
    }

    /**
     * Section 5: API costs & quotas table
     */
    public static function getSection5(): array
    {
        return [
            'id'      => 'section-5',
            'title'   => __('5. Chi phí & hạn mức các API đang dùng', 'laca'),
            'icon'    => 'dashicons-money-alt',
            'open'    => false,
            'type'    => 'custom_table',
            'headers' => [
                __('API', 'laca'),
                __('Dùng cho', 'laca'),
                __('Chi phí', 'laca'),
                __('Hạn mức mặc định (tham khảo, có thể đổi)', 'laca'),
                __('Site này dùng thế nào', 'laca'),
            ],
            'rows'    => [
                [
                    'api'   => 'Search Console API',
                    'usage' => __('Báo cáo GSC, Kiểm tra index', 'laca'),
                    'cost'  => __('Miễn phí', 'laca'),
                    'quota' => __('Search Analytics: theo QPM/QPD mỗi site & project; URL Inspection: **2.000 lượt/ngày, 600/phút mỗi property**', 'laca'),
                    'site'  => __('Báo cáo cache 1 giờ; Kiểm tra Index chỉ khi bấm', 'laca'),
                ],
                [
                    'api'   => 'Google Analytics Data API (GA4)',
                    'usage' => __('Báo cáo GA4, Realtime', 'laca'),
                    'cost'  => __('Miễn phí (property thường)', 'laca'),
                    'quota' => __('Core: **200.000 token/property/ngày, 40.000/giờ**; đa số request < 10 token', 'laca'),
                    'site'  => __('Gom batch, cache 1 giờ; realtime chỉ khi tab đang mở', 'laca'),
                ],
                [
                    'api'   => 'Google Analytics Admin API',
                    'usage' => __('Danh sách property', 'laca'),
                    'cost'  => __('Miễn phí', 'laca'),
                    'quota' => __('Hạn mức riêng, dư dùng', 'laca'),
                    'site'  => __('Cache 12 giờ + nút Tải lại', 'laca'),
                ],
                [
                    'api'   => 'PageSpeed Insights API',
                    'usage' => __('Tốc độ trang', 'laca'),
                    'cost'  => __('Miễn phí, **không cần bật thanh toán**', 'laca'),
                    'quota' => __('Có key: ~**25.000 lượt/ngày** mỗi project; không key: dùng chung hạn mức Google, **thường hết → lỗi 429**', 'laca'),
                    'site'  => __('Chỉ chạy khi bấm, cache 24 giờ/strategy', 'laca'),
                ],
                [
                    'api'   => 'OAuth 2.0',
                    'usage' => __('Đăng nhập Google', 'laca'),
                    'cost'  => __('Miễn phí', 'laca'),
                    'quota' => '—',
                    'site'  => '—',
                ],
            ],
            'note'    => __('**Không cần và không nên bật Billing** cho project này — mọi API trên miễn phí; vượt hạn mức chỉ bị từ chối tạm (429), không phát sinh tiền. Không dùng project này cho API trả phí (Maps, Places, Gemini…). Xem hạn mức thật tại https://console.cloud.google.com/apis/dashboard (chọn API → tab Hạn mức). Nguồn: https://developers.google.com/analytics/devguides/reporting/data/v1/quotas, https://developers.google.com/webmaster-tools/limits.', 'laca'),
        ];
    }

    /**
     * Render the guide accordion details for Search Console page (Sections 0, 1, 3, 3b, 4, 5).
     */
    public static function renderForSearchConsole(string $siteUrl, string $canonicalUri, string $legacyUri): void
    {
        $sections = [
            self::getSection0(),
            self::getSection1(),
            self::getSection3($canonicalUri, $legacyUri),
            self::getSection3b(),
            self::getSection4('all'),
            self::getSection5(),
        ];

        self::renderSections($sections, $siteUrl);
    }

    /**
     * Render the guide accordion details for Google Analytics page (Sections 0, 2, 4-ga, 5).
     */
    public static function renderForAnalytics(string $siteUrl): void
    {
        $sections = [
            self::getSection0(),
            self::getSection2(),
            self::getSection4('ga'),
            self::getSection5(),
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
            <details class="laca-seo-prep-details"<?php echo $isOpen; ?> style="margin-bottom: 12px; border: 1px solid var(--laca-border); border-radius: 6px; background: var(--laca-bg);">
                <summary class="laca-seo-prep-details__summary" style="cursor: pointer; font-weight: 600; padding: 12px 16px; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons <?php echo esc_attr($sec['icon'] ?? 'dashicons-info'); ?>" style="color: var(--laca-fg);"></span>
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
                                            <code style="background: var(--laca-bg-subtle); padding: 5px 10px; border-radius: 4px; font-size: 12px; word-break: break-all;"><?php echo esc_html($item['canonicalUri']); ?></code>
                                            <button type="button" class="button button-small laca-seo-prep-step__copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($item['canonicalUri']); ?>'); alert(window.lacaSeoData?.i18n?.copied || 'Đã sao chép!');">
                                                <span class="dashicons dashicons-admin-page" style="margin-top: 2px;"></span> <?php esc_html_e('Sao chép', 'laca'); ?>
                                            </button>
                                        </div>
                                        <div style="font-size: 12px; color: var(--laca-fg-muted); margin-bottom: 8px;">
                                            <?php esc_html_e('(Giai đoạn chuyển tiếp có thể thêm cả URI cũ:', 'laca'); ?>
                                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                                                <code style="background: var(--laca-bg-subtle); padding: 4px 8px; border-radius: 4px; font-size: 11px; word-break: break-all;"><?php echo esc_html($item['legacyUri']); ?></code>
                                                <button type="button" class="button button-small laca-seo-prep-step__copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($item['legacyUri']); ?>'); alert(window.lacaSeoData?.i18n?.copied || 'Đã sao chép!');">
                                                    <span class="dashicons dashicons-admin-page" style="margin-top: 2px;"></span> <?php esc_html_e('Sao chép', 'laca'); ?>
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
                            <table class="wp-list-table widefat striped" style="margin: 0; border: 1px solid var(--laca-border); border-radius: 4px; border-collapse: collapse; width: 100%;">
                                <thead>
                                    <tr style="background: var(--laca-bg-muted);">
                                        <th style="font-weight: 600; padding: 10px 14px; width: 28%; text-align: left; border-bottom: 1px solid var(--laca-border);"><?php esc_html_e('Triệu chứng', 'laca'); ?></th>
                                        <th style="font-weight: 600; padding: 10px 14px; width: 36%; text-align: left; border-bottom: 1px solid var(--laca-border);"><?php esc_html_e('Nguyên nhân', 'laca'); ?></th>
                                        <th style="font-weight: 600; padding: 10px 14px; width: 36%; text-align: left; border-bottom: 1px solid var(--laca-border);"><?php esc_html_e('Cách xử lý / phòng tránh', 'laca'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sec['rows'] as $r): ?>
                                        <tr>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['symptom'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['cause'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['fix'], $siteUrl)); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($sec['type'] === 'custom_table'): ?>
                        <div class="laca-seo-table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 8px 0;">
                            <table class="wp-list-table widefat striped" style="margin: 0; border: 1px solid var(--laca-border); border-radius: 4px; border-collapse: collapse; width: 100%;">
                                <thead>
                                    <tr style="background: var(--laca-bg-muted);">
                                        <?php foreach ($sec['headers'] as $h): ?>
                                            <th style="font-weight: 600; padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--laca-border);"><?php echo esc_html($h); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sec['rows'] as $r): ?>
                                        <tr>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border); font-weight: 600;">
                                                <?php echo esc_html($r['api']); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['usage'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['cost'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['quota'], $siteUrl)); ?>
                                            </td>
                                            <td style="padding: 10px 14px; vertical-align: top; border-bottom: 1px solid var(--laca-border);">
                                                <?php echo wp_kses_post(self::formatText($r['site'], $siteUrl)); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($sec['note'])): ?>
                            <div style="font-size: 12.5px; color: var(--laca-fg-muted); margin-top: 10px; line-height: 1.5; padding: 8px 12px; background: var(--laca-bg-subtle); border-radius: 4px; border: 1px solid var(--laca-border);">
                                <?php echo wp_kses_post(self::formatText($sec['note'], $siteUrl)); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </details>
            <?php
        }
        echo '</div>';
    }
}
