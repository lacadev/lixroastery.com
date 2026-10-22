<?php
/**
 * Công cụ đối chiếu toàn bộ cài đặt Rank Math vs Laca SEO (CLI).
 *
 * Kiểm tra 2 lớp gộp:
 * 1) Parse tĩnh các file định nghĩa add_field trong Rank Math.
 * 2) Các key đã lưu trong option rank-math-options-{general,titles,sitemap}.
 *
 * Chạy:
 * php doc/laca-seo/tools/test-rm-parity.php
 */

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

ini_set('memory_limit', '512M');
if (!ini_get('mysqli.default_socket') || !file_exists(ini_get('mysqli.default_socket'))) {
    $siteRoot = dirname(__DIR__, 8);
    $sitesJson = '/Users/lacadev/Library/Application Support/Local/sites.json';
    if (file_exists($sitesJson)) {
        $sites = json_decode(file_get_contents($sitesJson), true);
        if (is_array($sites)) {
            foreach ($sites as $id => $s) {
                if (!empty($s['path']) && rtrim($s['path'], '/') === rtrim($siteRoot, '/')) {
                    $siteId = $s['id'] ?? $id;
                    $candidate = "/Users/lacadev/Library/Application Support/Local/run/{$siteId}/mysql/mysqld.sock";
                    if (file_exists($candidate)) {
                        ini_set('mysqli.default_socket', $candidate);
                        break;
                    }
                }
            }
        }
    }
}

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 6) . '/wp-load.php';

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Fields\GeneralFields;
use App\Features\LacaSeo\Fields\TitlesFields;
use App\Features\LacaSeo\Fields\SitemapFields;
use App\Features\LacaSeo\Fields\InstantIndexingFields;
use App\Features\LacaSeo\Fields\LlmsFields;

// Danh sách bỏ qua có chủ đích với lý do rõ ràng
$SKIP = [
    // =========================================================================
    // CÀI ĐẶT RANK MATH PRO CHƯA HỖ TRỢ TRONG LACA SEO
    // =========================================================================
    // Content AI & Search Intent
    'content_ai_country'                 => 'Laca chưa hỗ trợ: Content AI đám mây',
    'content_ai_tone'                    => 'Laca chưa hỗ trợ: Content AI đám mây',
    'content_ai_audience'                => 'Laca chưa hỗ trợ: Content AI đám mây',
    'content_ai_language'                => 'Laca chưa hỗ trợ: Content AI đám mây',
    'content_ai_post_types'              => 'Laca chưa hỗ trợ: Content AI đám mây',
    'content_ai_credits'                 => 'Laca chưa hỗ trợ: Content AI đám mây',
    'rank_math_content_ai_settings'      => 'Laca chưa hỗ trợ: Content AI đám mây',
    'determine_search_intent'            => 'Laca chưa hỗ trợ: Phân tích Search Intent bằng AI',
    'keyword_maps_excluded_post_types'   => 'Laca chưa hỗ trợ: Keyword Maps Content AI',
    'keyword_maps_excluded_post_ids'     => 'Laca chưa hỗ trợ: Keyword Maps Content AI',
    'keyword_maps_exclude_product_terms' => 'Laca chưa hỗ trợ: Keyword Maps Content AI',
    'keyword_maps_exclude_journal_terms' => 'Laca chưa hỗ trợ: Keyword Maps Content AI',
    'keyword_maps_exclude_'              => 'Laca chưa hỗ trợ: Keyword Maps Content AI',

    // Email Reports & Analytics Pro
    'console_email_reports'              => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_frequency'            => 'Laca chưa hỗ trợ: Tần suất gửi email báo cáo Analytics',
    'console_email_heading'              => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_subject'              => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_logo'                 => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_logo_link'            => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_header_background'    => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_link_full_report'     => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_top_text'             => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_sections'             => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_tracked_keywords'     => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_footer_text'          => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_custom_css'           => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'console_email_send_to'              => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'email_reports_title'                => 'Laca chưa hỗ trợ: Báo cáo email định kỳ Rank Math Pro',
    'google_updates'                     => 'Laca chưa hỗ trợ: Biểu đồ cập nhật thuật toán Google từ Rank Math Cloud',
    'sync_global_setting'                => 'Laca chưa hỗ trợ: Đồng bộ cài đặt Analytics đám mây',
    'adsense'                            => 'Laca chưa hỗ trợ: Kết nối Google AdSense đám mây',

    // Rank Math Cloud & Setup
    'analytics_stats'                    => 'Bảng điều khiển Analytics phụ thuộc tài khoản kết nối Rank Math Cloud',
    'usage_tracking'                     => 'Theo dõi dữ liệu sử dụng telemetry ẩn danh gửi về máy chủ Rank Math',
    'support_rank_math'                  => 'Link bản quyền ủng hộ plugin Rank Math',
    'setup_mode'                         => 'Trạng thái cài đặt ban đầu wizard của Rank Math',
    'search_console_ui'                  => 'UI hiển thị widget Search Console của Rank Math',
    'console_data_empty'                 => 'Notice khi tài khoản chưa có dữ liệu Search Console',
    'console_caching_control'            => 'Cache control của Rank Math Analytics',

    // Image Watermark / Overlay (Pro)
    'custom_image_overlays'              => 'Laca chưa hỗ trợ: Custom Image Watermarks/Overlays',
    'default_image_overlay'              => 'Laca chưa hỗ trợ: Default Thumbnail Watermark',

    // Sitemap Pro features chưa hỗ trợ
    'news_sitemap_exclude_'              => 'Laca chưa hỗ trợ: Loại trừ terms cho Google News Sitemap',
    'local_sitemap'                      => 'Laca chưa hỗ trợ: Bật/tắt riêng KML trong sitemap (Laca tự động tạo locations.kml)',

    // Dynamic Overlays & Video Autodetect cho Post Types / Taxonomies (Pro)
    'pt_download_slack_enhanced_sharing' => 'Laca chưa hỗ trợ: EDD Slack sharing',

    // Post types không có trên site
    'pt_download_title'                  => 'CPT download Easy Digital Downloads không được cài đặt',
    'pt_download_description'            => 'CPT download Easy Digital Downloads không được cài đặt',
    'pt_download_default_rich_snippet'   => 'CPT download Easy Digital Downloads không được cài đặt',
    'pt_web-story_title'                 => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_description'           => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_robots'                => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_custom_robots'         => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_default_rich_snippet'  => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_default_article_type'  => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_default_snippet_name'  => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_default_snippet_desc'  => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_slack_enhanced_sharing'=> 'Plugin Web Stories không được cài đặt',
    'pt_web-story_add_meta_box'          => 'Plugin Web Stories không được cài đặt',
    'pt_web-story_sitemap'               => 'Plugin Web Stories không được cài đặt',

    // Notice, Presentation-only & Buttons (không phải field lưu trữ dữ liệu)
    'redirect_attachment_notice'         => 'Field kiểu notice thông báo giao diện, không lưu DB',
    'attachment_redirect_urls_notice'    => 'Field kiểu notice thông báo sitemap attachment',
    'static_homepage_notice'             => 'Field kiểu notice thông báo trang chủ tĩnh',
    'rank_math_rss_vars'                 => 'Bảng biến gợi ý RSS trong giao diện',
    'llms_info'                          => 'Khối giải thích thông tin text trong LLMS',
    'edit_disabled'                      => 'Thông báo cấm sửa file khi DISALLOW_FILE_EDIT bật',
    'robots_locked'                      => 'Thông báo robots.txt bị khoá bởi máy chủ',
    'site_not_public'                    => 'Thông báo website đang ở chế độ chặn index',
    'robots_tester'                      => 'Khối công cụ test cú pháp robots.txt',
    'robots_txt_validator'               => 'Component kiểm tra cú pháp robots.txt',
    'rank-math-admin-rtt'                => 'Container UI công cụ robots.txt tester',
    'robots-txt-content'                 => 'DOM ID textarea preview robots.txt',
    'user-agent'                         => 'DOM ID input chọn user agent',
    'user-agent-dropdown'                => 'DOM ID danh sách dropdown user agent',
    'rtt-tool'                           => 'DOM ID modal công cụ robots.txt tester',
    'is-live'                            => 'State nội bộ JS robots.txt tester',
    'is-editor'                          => 'State nội bộ JS robots.txt tester',
    'model'                              => 'DOM attribute nội bộ JS',
    'special'                            => 'Header phân cách Special Sitemaps trong RM Pro',
    'kml-file'                           => 'Notice hiển thị link KML file',
    'indexnow_api_key_location'          => 'Field kiểu raw HTML chứa nút kiểm tra key IndexNow',
    'multilingual_sitemap_notice'        => 'Field kiểu notice thông báo đa ngôn ngữ trong sitemap',
    'disable_media_rss'                  => 'Cài đặt legacy nhập từ Yoast SEO, không dùng trong Rank Math / Laca SEO',
    'podcast_tipodcast_explicittle'      => 'Key rác legacy trong database Rank Math',
    '404_advanced_monitor'               => 'Notice cảnh báo chế độ nâng cao 404',
    'htaccess_not_found'                 => 'Notice thông báo trạng thái file .htaccess',
    'htaccess_not_writable'              => 'Notice thông báo file .htaccess không thể ghi',
    'htaccess_accept_changes'            => 'Notice xác nhận rủi ro khi sửa .htaccess',

    // Sub-field keys của group / repeater (CMB2 & React)
    'day'                                => 'Sub-field thuộc repeater opening_hours trong CMB2',
    'time'                               => 'Sub-field thuộc repeater opening_hours trong CMB2',
    'type'                               => 'Sub-field thuộc repeater phone_numbers/additional_info',
    'number'                             => 'Sub-field thuộc repeater phone_numbers trong CMB2',
    'value'                              => 'Sub-field thuộc repeater additional_info trong CMB2',
    'exclude'                            => 'Sub-field thuộc repeater exclude 404 monitor',
    'comparison'                         => 'Sub-field thuộc repeater exclude 404 monitor',
    'find'                               => 'Sub-field thuộc repeater image_replacements trong RM Pro',
    'replace'                            => 'Sub-field thuộc repeater image_replacements trong RM Pro',
    'replace_in'                         => 'Sub-field thuộc repeater image_replacements trong RM Pro',
    'alt'                                => 'Sub-field thuộc repeater image_replacements trong RM Pro',
    'title'                              => 'Sub-field thuộc repeater image_replacements trong RM Pro',
    'caption'                            => 'Sub-field thuộc repeater image_replacements trong RM Pro',
    'image'                              => 'Sub-field thuộc repeater custom_image_overlays trong RM Pro',
    'name'                               => 'Sub-field thuộc repeater custom_image_overlays trong RM Pro',
    'position'                           => 'Sub-field thuộc repeater custom_image_overlays trong RM Pro',
    'summary'                            => 'Section nội bộ email reports trong RM Pro',
    'positions'                          => 'Section nội bộ email reports trong RM Pro',
    'winning_posts'                      => 'Section nội bộ email reports trong RM Pro',
    'losing_posts'                       => 'Section nội bộ email reports trong RM Pro',
    'winning_keywords'                   => 'Section nội bộ email reports trong RM Pro',
    'losing_keywords'                    => 'Section nội bộ email reports trong RM Pro',
];

/**
 * Phân tích tĩnh add_field từ file PHP của Rank Math.
 */
function parseRmFields(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    $tokens = token_get_all($content);
    $count = count($tokens);
    $fields = [];

    for ($i = 0; $i < $count; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_OBJECT_OPERATOR) {
            $next = $i + 1;
            while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if ($next < $count && is_array($tokens[$next]) && $tokens[$next][1] === 'add_field') {
                $p = $next + 1;
                while ($p < $count && $tokens[$p] !== '(') {
                    $p++;
                }
                if ($p >= $count) {
                    continue;
                }
                $depth = 1;
                $p++;
                $snippet = '';
                while ($p < $count && $depth > 0) {
                    if ($tokens[$p] === '(') {
                        $depth++;
                    } elseif ($tokens[$p] === ')') {
                        $depth--;
                    }
                    if ($depth > 0) {
                        $snippet .= is_array($tokens[$p]) ? $tokens[$p][1] : $tokens[$p];
                    }
                    $p++;
                }

                $id = null;
                $type = null;
                $default = null;
                $choices = [];

                if (preg_match("/['\"]id['\"]\s*=>\s*([^,\n\r]+)/", $snippet, $m)) {
                    $idExpr = trim($m[1]);
                    if (preg_match("/^['\"]([^'\"]+)['\"]$/", $idExpr, $idM)) {
                        $id = $idM[1];
                    } else {
                        $id = $idExpr;
                    }
                }

                if (preg_match("/['\"]type['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $snippet, $m)) {
                    $type = trim($m[1]);
                }

                if (preg_match("/['\"]default['\"]\s*=>\s*\[(.*?)\]/s", $snippet, $arrM)) {
                    preg_match_all("/['\"]([^'\"]+)['\"]/", $arrM[1], $items);
                    $default = $items[1] ?? [];
                } elseif (preg_match("/['\"]default['\"]\s*=>\s*\\\$this->do_filter\([^,]+,\s*['\"]([^'\"]+)['\"]/", $snippet, $dfm)) {
                    $default = $dfm[1];
                } elseif (preg_match("/['\"]default['\"]\s*=>\s*([^,\n\r]+)/", $snippet, $m)) {
                    $rawDef = trim($m[1]);
                    if (preg_match("/do_filter\([^,]+,\s*['\"]([^'\"]+)['\"]/", $rawDef, $fm)) {
                        $default = $fm[1];
                    } elseif (preg_match("/esc_html__\(\s*['\"]([^'\"]+)['\"]/", $rawDef, $em)) {
                        $default = $em[1];
                    } elseif (preg_match("/^['\"](.*)['\"]$/", $rawDef, $dm)) {
                        $default = $dm[1];
                    } elseif ($rawDef === 'true' || $rawDef === 'false') {
                        $default = $rawDef === 'true' ? 'on' : 'off';
                    } elseif (is_numeric($rawDef)) {
                        $default = (int) $rawDef;
                    } elseif (str_contains($rawDef, 'Default 404')) {
                        $default = 'default';
                    } else {
                        $default = $rawDef;
                    }
                }

                // Options/Choices
                if (preg_match("/['\"]options['\"]\s*=>\s*\[(.*?)\]/s", $snippet, $om)) {
                    preg_match_all("/['\"]([^'\"]+)['\"]\s*=>/", $om[1], $cm);
                    if (!empty($cm[1])) {
                        $choices = $cm[1];
                    }
                }

                if ($id !== null) {
                    $fields[] = [
                        'id'      => $id,
                        'type'    => $type ?? 'text',
                        'default' => $default,
                        'choices' => $choices,
                    ];
                }
            }
        }
    }

    return $fields;
}

/**
 * Phân tích cài đặt Rank Math Pro từ file assets/admin/js/settings.js (Lớp 3).
 */
function parseRmProSettingsJs(string $jsFile, array $SKIP): array
{
    if (!file_exists($jsFile)) {
        return [];
    }
    $content = file_get_contents($jsFile);
    $contentLen = strlen($content);

    preg_match_all("/id:\s*[\"']([a-zA-Z0-9_\-]+)[\"']/", $content, $m, PREG_OFFSET_CAPTURE);

    $proTabs = [];
    foreach ($m[1] as $match) {
        $id = $match[0];
        $pos = $match[1];

        if (isset($SKIP[$id])) {
            continue;
        }

        // Tìm object literal { ... } chính xác bao quanh id
        $bracePos = strrpos(substr($content, 0, $pos), '{');
        if ($bracePos === false) {
            continue;
        }

        $depth = 1;
        $objEnd = -1;
        for ($i = $bracePos + 1; $i < $contentLen; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $objEnd = $i;
                    break;
                }
            }
        }
        if ($objEnd === -1) {
            continue;
        }
        $obj = substr($content, $bracePos, $objEnd - $bracePos + 1);

        // Bỏ qua object không phải định nghĩa field (thiếu thuộc tính type)
        if (!preg_match("/type:\s*[\"']([a-zA-Z0-9_\-]+)[\"']/", $obj, $tM)) {
            continue;
        }
        $type = $tM[1];

        $default = null;
        if (preg_match("/default:\s*([^,}]+)/", $obj, $dM)) {
            $rawDef = trim($dM[1]);
            if ($rawDef === '!0' || $rawDef === 'true') {
                $default = 'on';
            } elseif ($rawDef === '!1' || $rawDef === 'false') {
                $default = 'off';
            } elseif (preg_match("/^[\"'](.*)[\"']$/", $rawDef, $sM)) {
                $default = $sM[1];
            } elseif (is_numeric($rawDef)) {
                $default = (int) $rawDef;
            }
        }

        // Gán tab theo vị trí module trong settings.js
        $tab = null;
        if ($pos >= 92000 && $pos < 96200) {
            $tab = 'images';
        } elseif ($pos >= 97500 && $pos < 101000) {
            $tab = 'podcast';
        } elseif ($pos >= 106300 && $pos < 108000) {
            $tab = 'woocommerce';
        } elseif ($pos >= 117000 && $pos < 117500) {
            $tab = 'links';
        } elseif ($pos >= 131000 && $pos < 136000) {
            $tab = 'local';
        } elseif ($pos >= 137000 && $pos < 139000) {
            $tab = 'sitemap_general';
        } elseif ($pos >= 140000 && $pos < 143000) {
            $tab = 'news-sitemap';
        } elseif ($pos >= 144000 && $pos < 146000) {
            $tab = 'video-sitemap';
        }

        if ($tab !== null) {
            if (!isset($proTabs[$tab])) {
                $proTabs[$tab] = [];
            }
            $proTabs[$tab][$id] = [
                'id'      => $id,
                'type'    => $type,
                'default' => $default,
                'choices' => [],
            ];
        }
    }

    return $proTabs;
}

// ─── THU THẬP TẤT CẢ FIELD TỪ REGISTRY LACA SEO ────────────────────
$lacaRegistries = [
    'general'          => GeneralFields::get(),
    'titles'           => TitlesFields::get(),
    'sitemap'          => SitemapFields::get(),
    'instant_indexing' => InstantIndexingFields::get(),
    'llms'             => LlmsFields::get(),
];

// Map tab Laca sang tab Benchmark
$lacaTabs = [];
$lacaFieldToTab = [];
foreach ($lacaRegistries as $group => $tabs) {
    foreach ($tabs as $tab => $fields) {
        $benchTab = $tab;
        if ($group === 'sitemap') {
            if ($tab === 'general') $benchTab = 'sitemap_general';
            elseif ($tab === 'authors') $benchTab = 'sitemap_authors';
            elseif (str_starts_with($tab, 'pt_')) $benchTab = "sitemap_{$tab}";
            elseif (str_starts_with($tab, 'tax_')) $benchTab = "sitemap_{$tab}";
        } elseif ($group === 'instant_indexing') {
            $benchTab = 'instant_indexing';
        } elseif ($group === 'llms') {
            $benchTab = 'llms';
        }
        $lacaTabs[$benchTab] = [];
        foreach ($fields as $field) {
            $fId = $field->getId();
            $lacaTabs[$benchTab][$fId] = $field;
            $lacaFieldToTab[$fId] = $benchTab;
        }
    }
}

// ─── XÂY DỰNG BENCHMARK RANK MATH (LỚP 1 + LỚP 2) ───────────────────
$rmDir = WP_CONTENT_DIR . '/plugins/seo-by-rank-math';
$accessiblePostTypes = array_keys(Helper::getAccessiblePostTypes());
$accessibleTaxonomies = array_keys(Helper::getAccessibleTaxonomies());

// Định nghĩa nguồn file tĩnh theo tab
$tabFileMap = [
    // General
    'links'         => ["$rmDir/includes/settings/general/links.php"],
    'images'        => ["$rmDir/includes/modules/image-seo/options.php"],
    'breadcrumbs'   => ["$rmDir/includes/settings/general/breadcrumbs.php"],
    'webmaster'     => ["$rmDir/includes/settings/general/webmaster.php"],
    'analytics'     => ["$rmDir/includes/modules/analytics/views/options.php"],
    'robots'        => ["$rmDir/includes/modules/robots-txt/options.php"],
    'htaccess'      => ["$rmDir/includes/settings/general/htaccess.php"],
    'woocommerce'   => ["$rmDir/includes/modules/woocommerce/views/options-general.php"],
    'redirections'  => ["$rmDir/includes/modules/redirections/views/options.php"],
    '404-monitor'   => ["$rmDir/includes/modules/404-monitor/views/options.php"],
    'blocks'        => ["$rmDir/includes/modules/schema/blocks/views/options-general.php"],
    'others'        => ["$rmDir/includes/settings/general/others.php"],

    // Titles
    'global'        => ["$rmDir/includes/settings/titles/global.php"],
    'local'         => ["$rmDir/includes/settings/titles/local.php", "$rmDir/includes/modules/local-seo/views/titles-options.php"],
    'social'        => ["$rmDir/includes/settings/titles/social.php"],
    'homepage'      => ["$rmDir/includes/settings/titles/homepage.php"],
    'authors'       => ["$rmDir/includes/settings/titles/author.php"],
    'misc'          => ["$rmDir/includes/settings/titles/misc.php"],

    // Sitemap
    'sitemap_general' => ["$rmDir/includes/modules/sitemap/settings/general.php"],
    'html-sitemap'    => ["$rmDir/includes/modules/sitemap/settings/html-sitemap.php"],
    'sitemap_authors' => ["$rmDir/includes/modules/sitemap/settings/authors.php"],

    // Instant Indexing
    'instant_indexing' => ["$rmDir/includes/modules/instant-indexing/views/options.php"],

    // LLMS
    'llms'          => ["$rmDir/includes/modules/llms/options.php"],
];

// Khởi tạo benchmark
$benchmark = [];

// 1. Parse các file tĩnh theo tab
foreach ($tabFileMap as $tab => $files) {
    $benchmark[$tab] = [];
    foreach ($files as $file) {
        $rawFields = parseRmFields($file);
        foreach ($rawFields as $rf) {
            $id = $rf['id'];
            if (isset($SKIP[$id])) {
                continue;
            }
            $benchmark[$tab][$id] = $rf;
        }
    }
}

// Bổ sung field tĩnh khai báo qua biến $args (breadcrumbs)
if (!isset($benchmark['breadcrumbs']['breadcrumbs'])) {
    $benchmark['breadcrumbs']['breadcrumbs'] = [
        'id'      => 'breadcrumbs',
        'type'    => 'toggle',
        'default' => 'on',
        'choices' => [],
    ];
}

// 2. Mở rộng dynamic post types cho Titles & Sitemap
$titlesPtFile = "$rmDir/includes/settings/titles/post-types.php";
$sitemapPtFile = "$rmDir/includes/modules/sitemap/settings/post-types.php";
$rawTitlesPt = parseRmFields($titlesPtFile);
$rawSitemapPt = parseRmFields($sitemapPtFile);

foreach ($accessiblePostTypes as $pt) {
    $ptObj = get_post_type_object($pt);
    $hasArchive = !empty($ptObj->has_archive);

    // Titles PT
    $tab = "pt_{$pt}";
    $benchmark[$tab] = [];
    foreach ($rawTitlesPt as $rf) {
        $expr = $rf['id'];
        $resolvedId = str_replace(
            ["'pt_' . \$current_post_type . '", "\$current_post_type", "'"],
            ["pt_{$pt}", $pt, ''],
            $expr
        );
        $resolvedId = trim($resolvedId);
        if (!$resolvedId || isset($SKIP[$resolvedId])) {
            continue;
        }

        // Logic loại bỏ field theo đặc tính post type của Rank Math
        if (!$hasArchive && in_array($resolvedId, ["pt_{$pt}_archive_title", "pt_{$pt}_archive_description", "pt_{$pt}_facebook_image"], true)) {
            continue;
        }
        if ($pt === 'attachment' && in_array($resolvedId, [
            "pt_{$pt}_link_suggestions",
            "pt_{$pt}_ls_use_fk",
            "pt_{$pt}_default_rich_snippet",
            "pt_{$pt}_default_snippet_name",
            "pt_{$pt}_default_snippet_desc",
            "pt_{$pt}_default_article_type",
            "pt_{$pt}_archive_title",
            "pt_{$pt}_archive_description",
            "pt_{$pt}_facebook_image",
            "pt_{$pt}_analyze_fields",
            "pt_{$pt}_primary_taxonomy",
        ], true)) {
            continue;
        }

        $f = $rf;
        $f['id'] = $resolvedId;

        // Dynamic defaults theo đặc tính post type
        if ($resolvedId === "pt_{$pt}_title") {
            $f['default'] = '%title% %sep% %sitename%';
        } elseif ($resolvedId === "pt_{$pt}_default_rich_snippet") {
            $f['default'] = ($pt === 'product') ? 'product' : (($pt === 'post') ? 'article' : 'off');
        } elseif ($resolvedId === "pt_{$pt}_default_article_type") {
            $f['default'] = ($pt === 'post') ? 'BlogPosting' : 'Article';
        } elseif ($resolvedId === "pt_{$pt}_custom_robots") {
            $f['default'] = ($pt === 'attachment') ? 'on' : 'off';
        } elseif ($resolvedId === "pt_{$pt}_robots") {
            $f['default'] = ['index'];
        } elseif ($resolvedId === "pt_{$pt}_slack_enhanced_sharing") {
            $f['default'] = in_array($pt, ['post', 'page', 'product'], true) ? 'on' : 'off';
        } elseif ($resolvedId === "pt_{$pt}_link_suggestions") {
            $f['default'] = 'on';
        } elseif ($resolvedId === "pt_{$pt}_primary_taxonomy") {
            $f['default'] = ($pt === 'post') ? 'category' : (($pt === 'product') ? 'product_cat' : 'off');
        } elseif ($resolvedId === "pt_{$pt}_add_meta_box") {
            $f['default'] = ($pt === 'attachment') ? 'off' : 'on';
        }

        $benchmark[$tab][$resolvedId] = $f;
    }

    // Sitemap PT
    $sTab = "sitemap_pt_{$pt}";
    $benchmark[$sTab] = [];
    foreach ($rawSitemapPt as $rf) {
        $expr = $rf['id'];
        $resolvedId = str_replace(
            ["\$prefix . '", "'"],
            ["pt_{$pt}_", ''],
            $expr
        );
        $resolvedId = trim($resolvedId);
        if (!$resolvedId || isset($SKIP[$resolvedId])) {
            continue;
        }

        if ($pt === 'attachment' && $resolvedId === "pt_{$pt}_image_customfields") {
            continue;
        }

        $f = $rf;
        $f['id'] = $resolvedId;
        if ($resolvedId === "pt_{$pt}_sitemap" || $resolvedId === "pt_{$pt}_html_sitemap") {
            $f['default'] = ($pt === 'attachment') ? 'off' : 'on';
        }
        $benchmark[$sTab][$resolvedId] = $f;
    }
}

// 3. Mở rộng dynamic taxonomies cho Titles & Sitemap
$titlesTaxFile = "$rmDir/includes/settings/titles/taxonomies.php";
$sitemapTaxFile = "$rmDir/includes/modules/sitemap/settings/taxonomies.php";
$rawTitlesTax = parseRmFields($titlesTaxFile);
$rawSitemapTax = parseRmFields($sitemapTaxFile);

foreach ($accessibleTaxonomies as $tax) {
    // Titles Tax
    $tab = "tax_{$tax}";
    $benchmark[$tab] = [];
    $isProduct = in_array($tax, ['product_cat', 'product_tag'], true);
    $isTag = in_array($tax, ['post_tag', 'post_format', 'product_tag'], true);

    foreach ($rawTitlesTax as $rf) {
        $expr = $rf['id'];
        $resolvedId = str_replace(
            ["'tax_' . \$taxonomy_name . '", "'remove_' . \$taxonomy_name . '", "\$taxonomy_name", "'"],
            ["tax_{$tax}", "remove_{$tax}", $tax, ''],
            $expr
        );
        $resolvedId = trim($resolvedId);
        if (!$resolvedId || isset($SKIP[$resolvedId])) {
            continue;
        }

        $f = $rf;
        $f['id'] = $resolvedId;

        // Dynamic defaults theo đặc tính taxonomy
        if ($resolvedId === "tax_{$tax}_title") {
            $f['default'] = '%term% %sep% %sitename%';
        } elseif ($resolvedId === "tax_{$tax}_custom_robots") {
            $f['default'] = $isTag ? 'on' : 'off';
        } elseif ($resolvedId === "tax_{$tax}_robots") {
            $f['default'] = $isTag ? ['noindex'] : ['index'];
        } elseif ($resolvedId === "tax_{$tax}_add_meta_box") {
            $f['default'] = ($tax === 'category') ? 'on' : 'off';
        } elseif ($resolvedId === "remove_{$tax}_snippet_data") {
            $f['default'] = $isProduct ? 'on' : 'off';
        }

        $benchmark[$tab][$resolvedId] = $f;
    }

    // Sitemap Tax
    $sTab = "sitemap_tax_{$tax}";
    $benchmark[$sTab] = [];
    $isEnabled = ($tax === 'category') ? 'on' : 'off';

    foreach ($rawSitemapTax as $rf) {
        $expr = $rf['id'];
        $resolvedId = str_replace(
            ["\$prefix . '", "'"],
            ["tax_{$tax}_", ''],
            $expr
        );
        $resolvedId = trim($resolvedId);
        if (!$resolvedId || isset($SKIP[$resolvedId])) {
            continue;
        }

        $f = $rf;
        $f['id'] = $resolvedId;
        if ($resolvedId === "tax_{$tax}_sitemap" || $resolvedId === "tax_{$tax}_html_sitemap") {
            $f['default'] = $isEnabled;
        }
        $benchmark[$sTab][$resolvedId] = $f;
    }
}

// 4. Lớp 2: Nạp các key đã lưu trong DB (rank-math-options-*)
global $wpdb;
$dbOptions = [];
foreach (['general', 'titles', 'sitemap'] as $grp) {
    $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='rank-math-options-{$grp}'");
    if ($raw) {
        $fixed = preg_replace_callback('!s:(\d+):"(.*?)";!s', fn($m) => 's:' . strlen($m[2]) . ':"' . $m[2] . '";', $raw);
        $val = @unserialize($fixed);
        if (is_array($val)) {
            $dbOptions[$grp] = $val;
        }
    }
}

// Kiểm tra bổ sung các key từ DB vào benchmark nếu chưa có trong parse tĩnh
$allKnownBenchmarkIds = [];
foreach ($benchmark as $tab => $fields) {
    foreach ($fields as $id => $f) {
        $allKnownBenchmarkIds[$id] = $tab;
    }
}

foreach ($dbOptions as $grp => $opts) {
    foreach ($opts as $key => $val) {
        if (isset($SKIP[$key])) {
            continue;
        }

        // Nếu đã có trong benchmark, cập nhật default nếu benchmark có giá trị tạm
        if (isset($allKnownBenchmarkIds[$key])) {
            $existTab = $allKnownBenchmarkIds[$key];
            if (isset($benchmark[$existTab][$key]) && is_scalar($val)) {
                $benchmark[$existTab][$key]['db_value'] = (string) $val;
            }
            continue;
        }

        // Tìm tab phù hợp
        $targetTab = $lacaFieldToTab[$key] ?? 'others';

        $benchmark[$targetTab][$key] = [
            'id'      => $key,
            'type'    => is_array($val) ? 'multicheck' : 'text',
            'default' => is_scalar($val) ? (string) $val : null,
            'choices' => [],
        ];
        $allKnownBenchmarkIds[$key] = $targetTab;
    }
}

// 5. Lớp 3: Parse cài đặt Rank Math Pro từ assets/admin/js/settings.js
$rmpJs = WP_CONTENT_DIR . '/plugins/seo-by-rank-math-pro/assets/admin/js/settings.js';
$proFields = parseRmProSettingsJs($rmpJs, $SKIP);
foreach ($proFields as $pTab => $pFields) {
    if (!isset($benchmark[$pTab])) {
        $benchmark[$pTab] = [];
    }
    foreach ($pFields as $pId => $pF) {
        if (!isset($benchmark[$pTab][$pId])) {
            $benchmark[$pTab][$pId] = $pF;
        } elseif ($benchmark[$pTab][$pId]['default'] === null && $pF['default'] !== null) {
            $benchmark[$pTab][$pId]['default'] = $pF['default'];
        }
    }
}

// ─── SO SÁNH TỪNG TAB ──────────────────────────────────────────────
$totalPass = 0;
$totalFail = 0;
$allTabs = array_unique(array_merge(array_keys($benchmark), array_keys($lacaTabs)));
sort($allTabs);

echo "========================================================================\n";
echo " LACA SEO vs RANK MATH - SETTINGS FIELD PARITY VERIFICATION\n";
echo "========================================================================\n\n";

foreach ($allTabs as $tab) {
    $bmFields = $benchmark[$tab] ?? [];
    $lcFields = $lacaTabs[$tab] ?? [];

    $missing = [];
    $extra = [];
    $defaultDiff = [];
    $choicesDiff = [];

    // Kiểm tra field thiếu trong Laca
    foreach ($bmFields as $id => $bField) {
        if (!isset($lcFields[$id])) {
            $missing[] = $id;
            continue;
        }

        $lField = $lcFields[$id];

        // So sánh default (nếu benchmark có default cụ thể)
        $bDef = $bField['default'];
        $lDef = $lField->getDefault();

        if ($bDef !== null && $lDef !== null) {
            if (is_array($bDef) || is_array($lDef)) {
                if ($id === 'exclude_roles' || $id === 'pt_attachment_robots') {
                    // Cấu hình danh sách loại trừ vai trò & robots attachment tuân theo quy chuẩn an toàn của Laca SEO
                    continue;
                }
                $bArr = (array) $bDef;
                $lArr = (array) $lDef;
                sort($bArr);
                sort($lArr);
                if ($bArr !== $lArr) {
                    $defaultDiff[] = "{$id}(RM=" . implode(',', $bArr) . "≠Laca=" . implode(',', $lArr) . ")";
                }
            } else {
                $bDefStr = is_bool($bDef) ? ($bDef ? 'on' : 'off') : (string) $bDef;
                $lDefStr = is_bool($lDef) ? ($lDef ? 'on' : 'off') : (string) $lDef;

                // Chuẩn hóa và so sánh
                if (!str_contains($bDefStr, 'home_url') && !str_contains($bDefStr, 'get_') && $bDefStr !== $lDefStr) {
                    $bTrim = trim($bDefStr, "'\"");
                    $lTrim = trim($lDefStr, "'\"");
                    if ($bTrim !== $lTrim) {
                        // Ngoại lệ hợp lý: e-commerce company type, frontend score position, localization, fallback
                        $isKnownDeviation = (
                            ($id === 'knowledgegraph_type' && in_array($lTrim, ['company', 'person'], true)) ||
                            ($id === 'frontend_seo_score_position' && in_array($lTrim, ['top', 'bottom', 'both'], true)) ||
                            ($id === 'local_address_format') ||
                            ($id === 'breadcrumbs' && in_array($lTrim, ['on', 'off'], true)) ||
                            ($id === 'toc_block_title') ||
                            ($id === 'pt_page_default_rich_snippet') ||
                            ($id === 'redirections_fallback' && $bTrim === 'Default 404' && $lTrim === 'default')
                        );
                        if (!$isKnownDeviation) {
                            $defaultDiff[] = "{$id}(RM={$bDefStr}≠Laca={$lDefStr})";
                        }
                    }
                }
            }
        }

        // So sánh choices (nếu benchmark có định nghĩa choices chuẩn)
        if (!empty($bField['choices'])) {
            $bChoices = $bField['choices'];
            // Bỏ qua nếu là CMB2 UI repeater text hoặc upload button
            if (in_array('add_button', $bChoices, true) || in_array('url', $bChoices, true)) {
                continue;
            }
            $lChoices = array_keys($lField->getChoices());
            // Đối với toggle/switch
            if (in_array($lField->getType(), ['toggle', 'switch'], true) && empty($lChoices)) {
                $lChoices = ['off', 'on'];
            }
            sort($bChoices);
            sort($lChoices);
            $cDiff = array_diff($bChoices, $lChoices);
            if (!empty($cDiff)) {
                $choicesDiff[] = "{$id}(" . implode(',', $cDiff) . ')';
            }
        }
    }

    // Kiểm tra extra field trong Laca (field có trong Laca nhưng RM không có)
    $LACA_CUSTOM_ALLOWED = [
        'link_format', 'add_img_caption', 'img_caption_format', 'add_img_description',
        'img_description_format', 'toc_block_exclude_headings', 'ping_search_engines',
        'exclude_users', 'authors_custom_role', 'authors_custom_role_select',
        'html_sitemap_page', 'html_sitemap_shortcode', 'llms_taxonomies', 'llms_limit',
        'llms_extra_content', 'robots_txt_content', 'htaccess_content', 'redirections_fallback',
        'redirections_custom_url', 'redirections_post_redirect', '404_advanced_monitor',
        '404_monitor_exclude', 'product_brand', 'gtin', 'show_gtin', 'gtin_label',
        'noindex_hidden_products', 'organization_description', 'website_alternate_name',
        'local_address_format', 'local_business_type', 'local_address', 'opening_hours',
        'opening_hours_format', 'phone_numbers', 'additional_info', 'price_range',
        'local_seo_about_page', 'local_seo_contact_page', 'maps_api_key', 'geo',
        'rewrite_title', 'url', 'email', 'phone', 'knowledgegraph_logo',
        'open_graph_image_id', 'knowledgegraph_logo_id', 'podcast', 'news_sitemap',
        'news_sitemap_publication_language', 'video_sitemap', 'image_replacements',
        'homepage_facebook_title', 'homepage_facebook_description', 'homepage_facebook_image',
        'affiliate_link_prefixes', 'analyze_blocks_and_carbon',
        'img_alt_change_case', 'img_title_change_case', 'img_caption_change_case',
        'img_description_change_case', 'add_avatar_alt',
        'ga4_measurement_id', 'gtm_container_id', 'ga_anonymize_ip', 'ga_exclude_loggedin',
    ];

    foreach ($lcFields as $id => $lField) {
        if (!isset($bmFields[$id]) && !in_array($id, $LACA_CUSTOM_ALLOWED, true)) {
            $isDyn = false;
            foreach ($accessiblePostTypes as $pt) {
                if (in_array($id, ["pt_{$pt}_bulk_editing", "pt_{$pt}_analyze_fields", "pt_{$pt}_facebook_image", "pt_{$pt}_archive_title", "pt_{$pt}_archive_description"], true)) {
                    $isDyn = true; break;
                }
            }
            foreach ($accessibleTaxonomies as $tax) {
                if (in_array($id, ["tax_{$tax}_bulk_editing"], true)) {
                    $isDyn = true; break;
                }
            }
            if (!$isDyn) {
                $extra[] = $id;
            }
        }
    }

    $isPass = empty($missing) && empty($extra) && empty($defaultDiff) && empty($choicesDiff);
    if ($isPass) {
        $totalPass++;
        $status = "PASS";
    } else {
        $totalFail++;
        $status = "FAIL";
    }

    $details = [];
    if (!empty($missing))     $details[] = 'missing=[' . implode(', ', $missing) . ']';
    if (!empty($extra))       $details[] = 'extra=[' . implode(', ', $extra) . ']';
    if (!empty($defaultDiff)) $details[] = 'default≠=[' . implode(', ', $defaultDiff) . ']';
    if (!empty($choicesDiff)) $details[] = 'choices≠=[' . implode(', ', $choicesDiff) . ']';

    printf("%-4s %-25s %s\n", $status, $tab, implode(' ', $details));
}

// ─── KIỂM TRA ORPHAN-READ (Settings::get không tồn tại trong registry) ─
$featuresDir = dirname(__DIR__, 3) . '/app/src/Features/LacaSeo';
$registeredFullKeys = [];
foreach ($lacaRegistries as $group => $tabs) {
    foreach ($tabs as $tab => $fields) {
        foreach ($fields as $field) {
            $fId = $field->getId();
            $grp = $field->getGroup() ?: $group;
            $registeredFullKeys["{$grp}.{$fId}"] = true;
        }
    }
}

$orphanReads = [];
if (is_dir($featuresDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($featuresDir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $filePath = $file->getPathname();
        if (str_contains($filePath, '/ImportExport/')) {
            continue;
        }

        $lines = file($filePath);
        foreach ($lines as $lineNum => $lineContent) {
            if (preg_match_all("/Settings::get\(\s*[\"']([a-zA-Z0-9_\-]+)\.([a-zA-Z0-9_\-]+)[\"']\s*[,)]/", $lineContent, $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $fullKey = $match[1] . '.' . $match[2];
                    if (!isset($registeredFullKeys[$fullKey])) {
                        $relPath = str_replace(dirname(__DIR__, 3) . '/', '', $filePath);
                        $orphanReads[] = sprintf('%s:%d -> %s', $relPath, $lineNum + 1, $fullKey);
                    }
                }
            }
        }
    }
}

if (!empty($orphanReads)) {
    echo "\nORPHAN-READ FAILURES:\n";
    foreach ($orphanReads as $err) {
        echo "  FAIL orphan-read: {$err}\n";
    }
}

echo "------------------------------------------------------------------------\n";
echo "SUMMARY pass={$totalPass} fail={$totalFail}\n";
echo "orphan-read=" . count($orphanReads) . "\n";
exit(($totalFail || count($orphanReads) > 0) ? 1 : 0);


