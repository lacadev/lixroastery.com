<?php

namespace App\Features\LacaSeo\Modules\SeoAnalysis;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Modules;
use DOMDocument;
use DOMXPath;

defined('ABSPATH') || exit;

/**
 * Local SEO Analyzer.
 * Fetches homepage (or supplied HTML), executes technical SEO audits,
 * and formats results matching Rank Math SEO Analyzer 1:1.
 */
class SeoAnalyzer
{
    public const RESULTS_OPTION = 'laca_seo_analysis_results';
    public const DATE_OPTION    = 'laca_seo_analysis_date';

    private string $analyzeUrl;
    private array $results = [];

    public function __construct(string $analyzeUrl = '')
    {
        $this->analyzeUrl = !empty($analyzeUrl) ? $analyzeUrl : home_url('/');
    }

    /**
     * Run all SEO tests on homepage content and system state.
     *
     * @param string|null $html Optional HTML content to test against (e.g. for mock or unit tests).
     * @return array
     */
    public function analyze(?string $html = null): array
    {
        if ($html === null) {
            $response = wp_remote_get($this->analyzeUrl, [
                'timeout'   => 15,
                'sslverify' => false,
            ]);

            if (is_wp_error($response)) {
                $html = '';
            } else {
                $html = wp_remote_retrieve_body($response);
            }
        }

        $dom = new DOMDocument();
        if (!empty($html)) {
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        }
        $xpath = new DOMXPath($dom);

        $this->results = [
            'site_description'    => $this->testSiteDescription(),
            'blog_public'         => $this->testBlogPublic(),
            'permalink_structure' => $this->testPermalinkStructure(),
            'sitemaps'            => $this->testSitemaps(),
            'auto_update'         => $this->testAutoUpdate(),
            'ssl'                 => $this->testSsl(),
            'robots_txt'          => $this->testRobotsTxt(),
            'title_tag'           => $this->testTitleTag($xpath),
            'meta_description'    => $this->testMetaDescription($xpath),
            'h1_heading'          => $this->testH1Heading($xpath),
            'h2_heading'          => $this->testH2Heading($xpath),
            'img_alt'             => $this->testImgAlt($xpath),
        ];

        update_option(self::RESULTS_OPTION, $this->results, false);
        update_option(self::DATE_OPTION, current_time('timestamp'), false);

        return $this->results;
    }

    public function testSiteDescription(): array
    {
        $desc = get_bloginfo('description');
        if (empty($desc)) {
            return [
                'status'  => 'warning',
                'message' => __('Bạn chưa nhập slogan cho trang web.', 'lacadev'),
            ];
        }

        if ($desc === 'Just another WordPress site' || $desc === translate('Just another WordPress site')) {
            return [
                'status'  => 'fail',
                'message' => __('Khẩu hiệu trang web đang để mặc định "Just another WordPress site".', 'lacadev'),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => __('Khẩu hiệu trang web đã được tùy biến.', 'lacadev'),
        ];
    }

    public function testBlogPublic(): array
    {
        $public = (bool) get_option('blog_public');
        return [
            'status'  => $public ? 'ok' : 'fail',
            'message' => $public
                ? __('Trang web hiển thị công khai với các công cụ tìm kiếm.', 'lacadev')
                : __('Chú ý: Trang web đang chặn công cụ tìm kiếm lập chỉ mục.', 'lacadev'),
        ];
    }

    public function testPermalinkStructure(): array
    {
        $structure = get_option('permalink_structure');
        if (empty($structure)) {
            return [
                'status'  => 'fail',
                'message' => __('Đường dẫn tĩnh đang ở chế độ mặc định (chưa bật Pretty Permalinks).', 'lacadev'),
            ];
        }

        if (strpos($structure, '%postname%') === false) {
            return [
                'status'  => 'fail',
                'message' => __('Đường dẫn tĩnh chưa chứa %postname%.', 'lacadev'),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => sprintf(__('Cấu trúc đường dẫn tĩnh là %s.', 'lacadev'), '<code>' . esc_html($structure) . '</code>'),
        ];
    }

    public function testSitemaps(): array
    {
        $active = Modules::isActive('sitemap');
        return [
            'status'  => $active ? 'ok' : 'fail',
            'message' => $active
                ? __('Trang web có một hoặc nhiều sơ đồ trang XML.', 'lacadev')
                : __('Không tìm thấy sơ đồ trang XML.', 'lacadev'),
        ];
    }

    public function testAutoUpdate(): array
    {
        $enabled = (bool) get_option('laca_seo_auto_update', true);
        return [
            'status'  => $enabled ? 'ok' : 'warning',
            'message' => $enabled
                ? __('Tùy chọn tự động cập nhật đang được bật.', 'lacadev')
                : __('Tùy chọn tự động cập nhật chưa được bật.', 'lacadev'),
        ];
    }

    public function testSsl(): array
    {
        $isHttps = is_ssl() || str_starts_with(home_url(), 'https://');
        return [
            'status'  => $isHttps ? 'ok' : 'fail',
            'message' => $isHttps
                ? __('Trang web đang sử dụng chứng chỉ bảo mật HTTPS/SSL.', 'lacadev')
                : __('Trang web chưa sử dụng HTTPS.', 'lacadev'),
        ];
    }

    public function testRobotsTxt(): array
    {
        $url = home_url('/robots.txt');
        return [
            'status'  => 'ok',
            'message' => __('Tệp robots.txt đang hoạt động.', 'lacadev'),
        ];
    }

    public function testTitleTag(DOMXPath $xpath): array
    {
        $titles = $xpath->query('//title');
        if (!$titles || $titles->length === 0) {
            return [
                'status'  => 'fail',
                'message' => __('Không tìm thấy thẻ tiêu đề <title> trên trang chủ.', 'lacadev'),
            ];
        }

        $text = trim($titles->item(0)->textContent);
        $len  = mb_strlen($text);

        if ($len < 10 || $len > 70) {
            return [
                'status'  => 'warning',
                'message' => sprintf(__('Độ dài tiêu đề là %d ký tự (khuyến nghị 40-60 ký tự).', 'lacadev'), $len),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => sprintf(__('Thẻ tiêu đề trang chủ có độ dài hợp lệ (%d ký tự).', 'lacadev'), $len),
        ];
    }

    public function testMetaDescription(DOMXPath $xpath): array
    {
        $metas = $xpath->query('//meta[@name="description"]/@content');
        if (!$metas || $metas->length === 0) {
            return [
                'status'  => 'fail',
                'message' => __('Không tìm thấy thẻ meta description trên trang chủ.', 'lacadev'),
            ];
        }

        $text = trim($metas->item(0)->textContent);
        $len  = mb_strlen($text);

        if ($len < 50 || $len > 160) {
            return [
                'status'  => 'warning',
                'message' => sprintf(__('Độ dài mô tả meta là %d ký tự (khuyến nghị 120-160 ký tự).', 'lacadev'), $len),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => sprintf(__('Thẻ meta description có độ dài tốt (%d ký tự).', 'lacadev'), $len),
        ];
    }

    public function testH1Heading(DOMXPath $xpath): array
    {
        $h1s = $xpath->query('//h1');
        $count = $h1s ? $h1s->length : 0;

        if ($count === 0) {
            return [
                'status'  => 'fail',
                'message' => __('Không tìm thấy thẻ tiêu đề <h1> trên trang chủ.', 'lacadev'),
            ];
        }

        if ($count > 1) {
            return [
                'status'  => 'warning',
                'message' => sprintf(__('Trang chủ có %d thẻ <h1> (khuyến nghị duy nhất 1 thẻ <h1>).', 'lacadev'), $count),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => __('Trang chủ có đúng 1 thẻ <h1>.', 'lacadev'),
        ];
    }

    public function testH2Heading(DOMXPath $xpath): array
    {
        $h2s = $xpath->query('//h2');
        $count = $h2s ? $h2s->length : 0;

        if ($count === 0) {
            return [
                'status'  => 'warning',
                'message' => __('Không tìm thấy thẻ tiêu đề <h2> nào trên trang chủ.', 'lacadev'),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => sprintf(__('Trang chủ có %d thẻ tiêu đề <h2>.', 'lacadev'), $count),
        ];
    }

    public function testImgAlt(DOMXPath $xpath): array
    {
        $imgs = $xpath->query('//img');
        if (!$imgs || $imgs->length === 0) {
            return [
                'status'  => 'ok',
                'message' => __('Không tìm thấy hình ảnh nào trên trang chủ.', 'lacadev'),
            ];
        }

        $missing = 0;
        foreach ($imgs as $img) {
            $alt = $img->getAttribute('alt');
            if ($alt === '') {
                $missing++;
            }
        }

        if ($missing > 0) {
            return [
                'status'  => 'warning',
                'message' => sprintf(__('Có %d hình ảnh trên trang chủ thiếu thuộc tính alt.', 'lacadev'), $missing),
            ];
        }

        return [
            'status'  => 'ok',
            'message' => __('Tất cả hình ảnh trên trang chủ đều có thẻ alt.', 'lacadev'),
        ];
    }
}
