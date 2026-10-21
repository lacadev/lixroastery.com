<?php
/**
 * Công cụ nghiệm thu Phase 6 Laca SEO — Image SEO, Thumbnail overlay, Link Counter & Link Genius,
 * Role Manager, SEO Analyzer local, Database Tools, Setup Wizard, Admin bar, Dashboard widget, Podcast.
 *
 * Chạy bằng PHP-CLI:
 * /Users/lacadev/Library/Application\ Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php -d mysqli.default_socket="/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock" doc/laca-seo/tools/test-phase6.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 6) . '/wp-load.php';

error_reporting(E_ALL & ~E_USER_WARNING & ~E_DEPRECATED);

use App\Features\LacaSeo\Modules\ImageSeo\ImageSeo as LacaImageSeo;
use App\Features\LacaSeo\Modules\ImageSeo\ThumbnailOverlay as LacaThumbnailOverlay;
use App\Features\LacaSeo\Modules\Links\ContentProcessor as LacaContentProcessor;
use App\Features\LacaSeo\Modules\Links\Storage as LacaLinksStorage;
use App\Features\LacaSeo\Modules\Links\LinkGenius as LacaLinkGenius;
use App\Features\LacaSeo\Modules\RoleManager\CapabilityManager as LacaCapabilityManager;
use App\Features\LacaSeo\Modules\SeoAnalysis\SeoAnalyzer as LacaSeoAnalyzer;
use App\Features\LacaSeo\Modules\DatabaseTools\Tools as LacaDatabaseTools;
use App\Features\LacaSeo\Modules\SetupWizard\SetupWizard as LacaSetupWizard;
use App\Features\LacaSeo\Modules\AdminBar\AdminBarMenu as LacaAdminBarMenu;
use App\Features\LacaSeo\Modules\DashboardWidget\DashboardWidget as LacaDashboardWidget;
use App\Features\LacaSeo\Modules\Podcast\Podcast as LacaPodcast;
use App\Features\LacaSeo\ImportExport\RankMathImporter;

use RankMath\Image_Seo\Add_Attributes as RmAddAttributes;
use RankMath\Links\ContentProcessor as RmContentProcessor;
use RankMath\Links\Storage as RmLinksStorage;
use RankMath\Role_Manager\Capability_Manager as RmCapabilityManager;
use RankMath\Tools\Yoast_FAQ_Converter as RmYoastFaqConverter;

global $wpdb;

// Bật các module Rank Math trong bộ nhớ cho phiên test
$origRankMathModules = get_option('rank_math_modules', []);
\RankMath\Helper::update_modules([
    'image-seo'    => 'on',
    'link-counter' => 'on',
    'role-manager' => 'on',
    'seo-analysis' => 'on',
    'podcast'      => 'on',
]);
register_shutdown_function(function() use ($origRankMathModules) {
    update_option('rank_math_modules', $origRankMathModules);
});
add_filter('pre_option_blog_public', fn() => '1');

// Checksum ban đầu của database (loại trừ transient động của WordPress như doing_cron)
$initialPostsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$initialPostmetaCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$initialOptionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name NOT LIKE '%_transient_%'");
$initialChecksum = md5(
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(ID, post_modified)) FROM {$wpdb->posts}") . '|' .
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(meta_id, meta_value)) FROM {$wpdb->postmeta}")
);



$passCount = 0;
$lacaonlyCount = 0;
$failCount = 0;

function reportResult(string $case, string $desc, bool $pass, string $diff = '', bool $lacaonly = false): void {
    global $passCount, $lacaonlyCount, $failCount;
    if ($pass && $lacaonly) {
        $lacaonlyCount++;
        echo "PASS-LACAONLY {$case} {$desc}\n";
    } elseif ($pass) {
        $passCount++;
        echo "PASS {$case} {$desc}\n";
    } else {
        $failCount++;
        echo "FAIL {$case} {$desc} [{$diff}]\n";
    }
}

// =============================================================
// IS1–IS4: Image SEO (alt, title, caption, case transforms)
// =============================================================
$rawHtml = '<p>Check out our beans: <img src="https://example.com/wp-content/uploads/2026/10/specialty-coffee-bean.jpg" class="aligncenter" width="600" height="400" /></p>';

// Initialize Rank Math variable replacement manager for test process
if (function_exists('rank_math') && isset(rank_math()->variables)) {
    rank_math()->variables->setup();
}

// IS1: Alt missing attribute addition
add_filter('pre_option_rank-math-options-general', function($val) {
    $arr = is_array($val) ? $val : [];
    $arr['add_img_alt'] = 'on';
    $arr['img_alt_format'] = '%filename% specialty';
    return $arr;
});
add_filter('pre_option_laca_seo_general', function($val) {
    $arr = is_array($val) ? $val : [];
    $arr['add_img_alt'] = 'on';
    $arr['img_alt_format'] = '%filename% specialty';
    return $arr;
});

$rmImg = new RmAddAttributes();
$rmImg->is_alt = '%filename% specialty';
$rmImg->is_title = false;
$rmOutAlt = $rmImg->add_img_attributes($rawHtml);

$lacaImg = new LacaImageSeo();
$lacaImg->setTestConfig(['is_alt' => '%filename% specialty', 'is_title' => false]);
$lacaOutAlt = $lacaImg->addImgAttributes($rawHtml);

$passIS1 = !empty($rmOutAlt) && !empty($lacaOutAlt) && ($rmOutAlt === $lacaOutAlt) && str_contains($lacaOutAlt, 'alt="specialty coffee bean specialty"');
reportResult('IS1', 'Image SEO add missing alt attribute RM == Laca', $passIS1, !empty($rmOutAlt) ? "RM: {$rmOutAlt} vs LACA: {$lacaOutAlt}" : 'RM empty');

// IS2: Title missing attribute addition
$rmImg->is_alt = false;
$rmImg->is_title = 'Image: %filename%';
$rmOutTitle = $rmImg->add_img_attributes($rawHtml);

$lacaImg->setTestConfig(['is_alt' => false, 'is_title' => 'Image: %filename%']);
$lacaOutTitle = $lacaImg->addImgAttributes($rawHtml);

$passIS2 = !empty($rmOutTitle) && !empty($lacaOutTitle) && ($rmOutTitle === $lacaOutTitle) && str_contains($lacaOutTitle, 'title="Image: specialty coffee bean"');
reportResult('IS2', 'Image SEO add missing title attribute RM == Laca', $passIS2, !empty($rmOutTitle) ? "RM: {$rmOutTitle} vs LACA: {$lacaOutTitle}" : 'RM empty');

// IS3: Image Caption addition & caption case comparison
$rmProImg = new \RankMathPro\Image_Seo_Pro();
rank_math()->settings->set('general', 'img_caption_change_case', 'titlecase');
add_filter('pre_option_laca_seo_general', function($val) {
    $val = is_array($val) ? $val : [];
    $val['img_caption_change_case'] = 'titlecase';
    return $val;
});
\App\Features\LacaSeo\Core\Settings::clearCache();

$testCaptionArr = ['caption' => 'specialty coffee arabica blend'];
$rmCaptionOut = $rmProImg->change_caption_case($testCaptionArr);
$lacaCaptionOut = $lacaImg->changeCaptionCase($testCaptionArr);

$htmlWithFigure = '<figure class="wp-block-image"><img src="https://example.com/uploads/arabica-roast.png" alt="Arabica" /></figure>';
$lacaFigureCaptionOut = $lacaImg->contentAddCaption($htmlWithFigure);

$passIS3 = ($rmCaptionOut === $lacaCaptionOut)
    && ($lacaCaptionOut['caption'] === 'Specialty Coffee Arabica Blend')
    && str_contains($lacaFigureCaptionOut, '<figcaption class="wp-element-caption">');
reportResult('IS3', 'Image SEO caption case transform & figure caption addition RM == Laca', $passIS3, "RM: " . json_encode($rmCaptionOut) . " vs LACA: " . json_encode($lacaCaptionOut));

// IS4: Case transformations (titlecase, uppercase, lowercase) RM == Laca
$rmChangeCaseRef = new \ReflectionMethod($rmProImg, 'change_case');
$rmTitleCased = $rmChangeCaseRef->invoke($rmProImg, 'specialty roasted coffee', 'titlecase');
$rmUpperCased = $rmChangeCaseRef->invoke($rmProImg, 'specialty roasted coffee', 'uppercase');
$rmLowerCased = $rmChangeCaseRef->invoke($rmProImg, 'Specialty Roasted Coffee', 'lowercase');

$lacaTitleCased = $lacaImg->changeAttributeCaseString('specialty roasted coffee', 'titlecase');
$lacaUpperCased = $lacaImg->changeAttributeCaseString('specialty roasted coffee', 'uppercase');
$lacaLowerCased = $lacaImg->changeAttributeCaseString('Specialty Roasted Coffee', 'lowercase');

$passIS4 = ($rmTitleCased === $lacaTitleCased) && ($rmUpperCased === $lacaUpperCased) && ($rmLowerCased === $lacaLowerCased)
    && ($lacaTitleCased === 'Specialty Roasted Coffee') && ($lacaUpperCased === 'SPECIALTY ROASTED COFFEE');
reportResult('IS4', 'Image SEO case transformations (titlecase, uppercase, lowercase) RM == Laca', $passIS4, "title={$lacaTitleCased}/{$rmTitleCased} upper={$lacaUpperCased}/{$rmUpperCased}");

// =============================================================
// TO1: Thumbnail Overlay (GD icon stamp) RM == Laca
// =============================================================
$dummyImagePath = '/private/tmp/laca_test_bg.png';
$bgImg = imagecreatetruecolor(400, 300);
$bgCol = imagecolorallocate($bgImg, 30, 40, 50);
imagefilledrectangle($bgImg, 0, 0, 400, 300, $bgCol);
imagepng($bgImg, $dummyImagePath);
imagedestroy($bgImg);

$stampPath = '/private/tmp/laca_test_stamp.png';
$stamp = imagecreatetruecolor(60, 60);
$white = imagecolorallocate($stamp, 255, 255, 255);
imagefilledrectangle($stamp, 0, 0, 60, 60, $white);
imagepng($stamp, $stampPath);
imagedestroy($stamp);

// Laca render
$overlayGdOut = LacaThumbnailOverlay::renderOverlayGd($dummyImagePath, $stampPath, 'middle_center');

// Rank Math render
$rmOverlay = new \RankMath\Thumbnail_Overlay();
$rmGen = new \ReflectionMethod($rmOverlay, 'generate_image_gd');
ob_start();
$rmGen->invoke($rmOverlay, $dummyImagePath, $stampPath, 'middle_center');
$rmOverlayGdOut = ob_get_clean();

$lacaImgSize = getimagesizefromstring($overlayGdOut);
$rmImgSize   = getimagesizefromstring($rmOverlayGdOut);

$passTO1 = !empty($overlayGdOut) && !empty($rmOverlayGdOut)
    && ($lacaImgSize[0] === $rmImgSize[0])
    && ($lacaImgSize[1] === $rmImgSize[1])
    && (md5($overlayGdOut) === md5($rmOverlayGdOut));
reportResult('TO1', 'Thumbnail overlay GD generation dimension and md5 hash RM == Laca', $passTO1, 'Laca md5: ' . md5($overlayGdOut) . ' vs RM md5: ' . md5($rmOverlayGdOut));

// =============================================================
// LC1–LC3: Link Counter (extract, count, import idempotent)
// =============================================================
$siteHostUrl = home_url();
$testContent = '<p>Read our <a href="' . $siteHostUrl . '/journal/coffee-guide/">Coffee Guide</a> and check <a href="https://google.com">Google</a> or <a href="https://partner.com" rel="nofollow" target="_blank">Partner</a>.</p>';

$lacaProc = new LacaContentProcessor();
$lacaExtract = $lacaProc->extract($testContent);
$rmProc = new RmContentProcessor();
$rmExtract = $rmProc->extract($testContent);

$passLC1 = count($rmExtract) === 3 && count($lacaExtract['links']) === 3
    && ($lacaExtract['counts']['internal_link_count'] === 1)
    && ($lacaExtract['counts']['external_link_count'] === 2);
reportResult('LC1', 'Link Counter internal & external count extraction RM == Laca', $passLC1, "internal={$lacaExtract['counts']['internal_link_count']} external={$lacaExtract['counts']['external_link_count']}");

// LC2: Anchor text and rel/target attribute detection RM Pro == Laca
$rmProProc = new \RankMathPro\Link_Genius\Services\Content_Processor();
$rmExtractedEnhanced = $rmProProc->extract_enhanced(null, $testContent, 1);
$rmEnhancedLinks = $rmExtractedEnhanced['links'] ?? [];

$lacaLinks = $lacaExtract['links'];

$passLC2 = count($rmEnhancedLinks) === 3 && count($lacaLinks) === 3
    && ($rmEnhancedLinks[0]->anchor_text === $lacaLinks[0]->getAnchorText())
    && ($lacaLinks[0]->getAnchorText() === 'Coffee Guide')
    && ((int)$rmEnhancedLinks[2]->is_nofollow === $lacaLinks[2]->getIsNofollow())
    && ($lacaLinks[2]->getIsNofollow() === 1)
    && ((int)$rmEnhancedLinks[2]->target_blank === $lacaLinks[2]->getTargetBlank())
    && ($lacaLinks[2]->getTargetBlank() === 1);
reportResult('LC2', 'Link Counter anchor text, rel=nofollow, target=_blank extraction RM Pro == Laca', $passLC2);

// LC3: Importer copies internal_links and internal_meta idempotently matching RM table rows
$rmInternalLinksCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rank_math_internal_links");
$rmInternalMetaCount  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rank_math_internal_meta");

$imported1 = RankMathImporter::importLinksData();
$imported2 = RankMathImporter::importLinksData();

$passLC3 = ($imported1['links'] === $rmInternalLinksCount)
    && ($imported1['meta'] === $rmInternalMetaCount)
    && ($imported2['links'] === $imported1['links'])
    && ($imported2['meta'] === $imported1['meta']);
reportResult('LC3', 'Link data importer copies rows matching RM tables idempotently', $passLC3, "links={$imported1['links']}/{$rmInternalLinksCount} meta={$imported1['meta']}/{$rmInternalMetaCount}");

// =============================================================
// LG1–LG3: Link Genius (mock audit, bulk update, rollback)
// =============================================================
// LG1: Audit URL with mocked HTTP responses RM Pro == Laca
add_filter('pre_http_request', function($preempt, $parsed_args, $url) {
    if ($url === 'https://example.com/ok') {
        return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => 'OK'];
    }
    if ($url === 'https://example.com/missing') {
        return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => '404'];
    }
    if ($url === 'https://example.com/timeout') {
        return new \WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
    }
    return $preempt;
}, 10, 3);

$rmCrawler = \RankMathPro\Link_Genius\Background\Link_Status_Crawler::get();
$rmCrawlerMethod = new \ReflectionMethod($rmCrawler, 'check_url_status');

$rmAudit200 = $rmCrawlerMethod->invoke($rmCrawler, 'https://example.com/ok');
$rmAudit404 = $rmCrawlerMethod->invoke($rmCrawler, 'https://example.com/missing');
$rmAuditTimeout = $rmCrawlerMethod->invoke($rmCrawler, 'https://example.com/timeout');

$lacaAudit200 = LacaLinkGenius::auditUrl('https://example.com/ok');
$lacaAudit404 = LacaLinkGenius::auditUrl('https://example.com/missing');
$lacaAuditTimeout = LacaLinkGenius::auditUrl('https://example.com/timeout');

$passLG1 = ($rmAudit200['status_category'] === $lacaAudit200['status_category'])
    && ($rmAudit404['status_category'] === $lacaAudit404['status_category'])
    && ($rmAuditTimeout['status_category'] === $lacaAuditTimeout['status_category'])
    && ($lacaAudit200['status_category'] === '2xx')
    && ($lacaAudit404['status_category'] === '4xx')
    && ($lacaAuditTimeout['status_category'] === 'timeout');
reportResult('LG1', 'Link Genius audit 3 URLs status categorization RM Pro == Laca', $passLG1, "200={$lacaAudit200['status_category']} 404={$lacaAudit404['status_category']} to={$lacaAuditTimeout['status_category']}");

// LG2 & LG3: Bulk update RM Pro Link_Updater == Laca + rollback
$initialContent = '<p>Visit <a href="https://oldlink.com/page">Old Link Anchor</a> for details.</p>';
$initialMd5 = md5($initialContent);

$rmLinkObj = (object) [
    'url'         => 'https://oldlink.com/page',
    'anchor_text' => 'Old Link Anchor',
    'anchor_type' => 'HPLNK',
    'type'        => 'external',
];
$rmUpdater = new \RankMathPro\Link_Genius\Data\Link_Updater();
$rmUpdatedContent = $rmUpdater->update_single_link_in_content(
    $initialContent,
    $rmLinkObj,
    'https://newlink.com/page',
    'New Link Anchor'
);

$wpdb->query("START TRANSACTION");
try {
    $tempPostId = wp_insert_post([
        'post_title'   => 'Link Genius Temp Test Post',
        'post_content' => $initialContent,
        'post_status'  => 'publish',
        'post_type'    => 'post',
    ]);

    // Bulk update
    $bulkRes = LacaLinkGenius::bulkUpdateLinks(
        [$tempPostId],
        'https://oldlink.com/page',
        'https://newlink.com/page',
        'Old Link Anchor',
        'New Link Anchor'
    );

    $updatedContent = get_post_field('post_content', $tempPostId);
    $passLG2 = ($bulkRes['affected_posts'] === 1)
        && ($rmUpdatedContent === $updatedContent)
        && str_contains($updatedContent, 'https://newlink.com/page')
        && str_contains($updatedContent, 'New Link Anchor');
    reportResult('LG2', 'Link Genius bulk update URL and anchor text replacement RM Pro == Laca', $passLG2);

    // Rollback
    $rolledBack = LacaLinkGenius::rollbackBatch($bulkRes['batch_id']);
    $restoredContent = get_post_field('post_content', $tempPostId);
    $restoredMd5 = md5($restoredContent);

    $passLG3 = $rolledBack && ($restoredMd5 === $initialMd5);
    reportResult('LG3', 'Link Genius rollback restores post content to exact initial md5', $passLG3, "initMd5={$initialMd5} restoredMd5={$restoredMd5}");
} finally {
    $wpdb->query("ROLLBACK");
}

// =============================================================
// RM1–RM3: Role Manager (capabilities map, editor vs admin)
// =============================================================
$rmCapMgr = RmCapabilityManager::get();
$rmCaps = $rmCapMgr->get_capabilities(true);
$lacaCaps = LacaCapabilityManager::getCapabilities();

// RM1: 1:1 Capability list mapping
$mappedRmCaps = array_map(fn($c) => str_replace('rank_math_', 'laca_seo_', $c), $rmCaps);
sort($mappedRmCaps);
$sortedLacaCaps = $lacaCaps;
sort($sortedLacaCaps);

$passRM1 = (count($lacaCaps) === count($rmCaps)) && ($sortedLacaCaps === $mappedRmCaps);
echo "# RM1 caps count: Laca=" . count($lacaCaps) . " RM=" . count($rmCaps) . "\n";
reportResult('RM1', 'Role Manager registered capabilities map 1:1 with Rank Math (count ' . count($lacaCaps) . ' vs ' . count($rmCaps) . ')', $passRM1);

// RM2: Editor capabilities match Rank Math editor defaults
$rmCapRef = new \ReflectionMethod($rmCapMgr, 'get_default_capabilities_by_role');
$rmEditorCaps = $rmCapRef->invoke($rmCapMgr, 'editor');
$lacaEditorCaps = LacaCapabilityManager::getDefaultCapsByRole('editor');

$mappedRmEditorCaps = array_map(fn($c) => str_replace('rank_math_', 'laca_seo_', $c), $rmEditorCaps);
sort($mappedRmEditorCaps);
$sortedLacaEditorCaps = $lacaEditorCaps;
sort($sortedLacaEditorCaps);

$passRM2 = (count($lacaEditorCaps) === count($rmEditorCaps)) && ($sortedLacaEditorCaps === $mappedRmEditorCaps);
reportResult('RM2', 'Role Manager editor role capabilities match Rank Math editor defaults 1:1 (count ' . count($lacaEditorCaps) . ' vs ' . count($rmEditorCaps) . ')', $passRM2);

// RM3: Admin capabilities match Rank Math administrator defaults 1:1
$rmAdminCaps = $rmCapRef->invoke($rmCapMgr, 'administrator');
$lacaAdminCaps = LacaCapabilityManager::getDefaultCapsByRole('administrator');

$mappedRmAdminCaps = array_map(fn($c) => str_replace('rank_math_', 'laca_seo_', $c), $rmAdminCaps);
sort($mappedRmAdminCaps);
$sortedLacaAdminCaps = $lacaAdminCaps;
sort($sortedLacaAdminCaps);

$adminUser = new \WP_User();
$adminUser->roles = ['administrator'];
$adminUser->allcaps = ['manage_options' => true, 'administrator' => true];
$adminCapsFiltered = (new LacaCapabilityManager())->filterUserCaps($adminUser->allcaps, ['laca_seo_general'], [], $adminUser);

$passRM3 = (count($lacaAdminCaps) === count($rmAdminCaps)) && ($sortedLacaAdminCaps === $mappedRmAdminCaps)
    && !empty($adminCapsFiltered['laca_seo_general']) && !empty($adminCapsFiltered['laca_seo_titles']);
reportResult('RM3', 'Role Manager administrator retains all capabilities unconditionally RM == Laca (count ' . count($lacaAdminCaps) . ' vs ' . count($rmAdminCaps) . ')', $passRM3);

// =============================================================
// SA1–SA5: Local SEO Analyzer tests
// =============================================================
require_once WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/seo-analysis/seo-analysis-tests.php';
$mockHtml = '<!DOCTYPE html><html><head><title>Lix Roastery - Specialty Coffee Hanoi Vietnam</title><meta name="description" content="Discover premium specialty roasted coffee beans sourced from the best origins in Vietnam and around the world." /></head><body><h1>Lix Roastery Hanoi</h1><h2>Our Origin Philosophy</h2><img src="https://example.com/logo.png" alt="Lix Logo" /></body></html>';

$analyzer = new LacaSeoAnalyzer();
$results = $analyzer->analyze($mockHtml);

$passSA1 = ($results['title_tag']['status'] === 'ok') && str_contains($results['title_tag']['message'], 'hợp lệ');
reportResult('SA1', 'SEO Analyzer title tag length check passes for good title', $passSA1, $results['title_tag']['message']);

$passSA2 = ($results['meta_description']['status'] === 'ok') && str_contains($results['meta_description']['message'], 'tốt');
reportResult('SA2', 'SEO Analyzer meta description presence and length check', $passSA2, $results['meta_description']['message']);

$passSA3 = ($results['h1_heading']['status'] === 'ok') && ($results['h2_heading']['status'] === 'ok');
reportResult('SA3', 'SEO Analyzer H1 and H2 heading structure check', $passSA3);

$passSA4 = ($results['img_alt']['status'] === 'ok');
reportResult('SA4', 'SEO Analyzer image alt attributes check on homepage HTML', $passSA4);

// SA5: System configuration tests compared directly with RM test functions
$rmSiteDesc = rank_math_analyze_site_description();
$rmBlogPublic = rank_math_analyze_blog_public();
$rmPermalink = rank_math_analyze_permalink_structure();
$rmSitemaps = rank_math_analyze_sitemap();

$passSA5 = ($results['site_description']['status'] === $rmSiteDesc['status'])
    && ($results['permalink_structure']['status'] === $rmPermalink['status'])
    && ($results['sitemaps']['status'] === $rmSitemaps['status'])
    && ($results['blog_public']['status'] === $rmBlogPublic['status']);
reportResult('SA5', 'SEO Analyzer system configuration checks (tagline, permalinks, sitemaps, blog_public) RM == Laca', $passSA5);

// =============================================================
// DT1: Yoast FAQ Converter -> Rank Math / Laca block JSON-LD
// =============================================================
$yoastBlock = [
    'blockName' => 'yoast/faq-block',
    'attrs'     => [
        'questions' => [
            [
                'id'           => 'q1',
                'jsonQuestion' => 'What is Specialty Coffee?',
                'jsonAnswer'   => 'Coffee scoring 80+ points on the SCA scale.',
            ],
            [
                'id'           => 'q2',
                'jsonQuestion' => 'Where is Lix Roastery located?',
                'jsonAnswer'   => 'Hanoi, Vietnam.',
            ]
        ],
        'className' => 'custom-faq',
    ],
];

$rmConverter = new RmYoastFaqConverter();
$rmConverted = $rmConverter->convert($yoastBlock);
$lacaConverted = LacaDatabaseTools::convertYoastFaqBlock($yoastBlock);

$passDT1 = ($rmConverted['blockName'] === $lacaConverted['blockName'])
    && (count($rmConverted['attrs']['questions']) === count($lacaConverted['attrs']['questions']))
    && ($rmConverted['attrs']['questions'][0]['title'] === $lacaConverted['attrs']['questions'][0]['title'])
    && ($rmConverted['attrs']['questions'][0]['content'] === $lacaConverted['attrs']['questions'][0]['content']);
reportResult('DT1', 'Yoast FAQ block converter structure matches Rank Math converter', $passDT1);

// =============================================================
// WZ1: Setup Wizard step saving (LACA-ONLY)
// =============================================================
$origKnowledgeType = \App\Features\LacaSeo\Core\Settings::get('titles.knowledgegraph_type');
$origKnowledgeName = \App\Features\LacaSeo\Core\Settings::get('titles.knowledgegraph_name');

LacaSetupWizard::saveWizardStep('your-site', [
    'knowledgegraph_type' => 'company',
    'knowledgegraph_name' => 'Lix Roastery Test Lab',
]);
$savedKnowledgeType = \App\Features\LacaSeo\Core\Settings::get('titles.knowledgegraph_type');
$savedKnowledgeName = \App\Features\LacaSeo\Core\Settings::get('titles.knowledgegraph_name');

$passWZ1 = ($savedKnowledgeType === 'company') && ($savedKnowledgeName === 'Lix Roastery Test Lab');
reportResult('WZ1', 'Setup Wizard step saving correctly updates registry options (wizard without GSC OAuth)', $passWZ1, "type={$savedKnowledgeType} name={$savedKnowledgeName}", true);

// Restore original setting
$titlesGroup = \App\Features\LacaSeo\Core\Settings::getGroup('titles');
$titlesGroup['knowledgegraph_type'] = $origKnowledgeType;
$titlesGroup['knowledgegraph_name'] = $origKnowledgeName;
\App\Features\LacaSeo\Core\Settings::updateGroup('titles', $titlesGroup);

// Clean up temporary options created during analysis/wizard
delete_option('laca_seo_analysis_results');
delete_option('laca_seo_analysis_date');

// =============================================================
// AB1: Admin Bar Menu (present for admin, omitted for subscriber) (LACA-ONLY)
// =============================================================
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
$adminBarAdmin = new \WP_Admin_Bar();
$adminBarSub   = new \WP_Admin_Bar();

// Admin context
wp_set_current_user(1);
(new LacaAdminBarMenu())->addMenu($adminBarAdmin);
$adminNode = $adminBarAdmin->get_node('laca-seo');

// Subscriber mock context (user id 9999999 without caps)
wp_set_current_user(0);
(new LacaAdminBarMenu())->addMenu($adminBarSub);
$subNode = $adminBarSub->get_node('laca-seo');

// Reset user back to admin
wp_set_current_user(1);

$passAB1 = ($adminNode !== null && $adminNode->id === 'laca-seo') && ($subNode === null);
reportResult('AB1', 'Admin Bar menu rendered for admin and suppressed for guest/subscriber (Laca custom diagnostic node)', $passAB1, '', true);

// =============================================================
// PD1: Podcast Feed XML tags RM Pro == Laca
// =============================================================
rank_math()->settings->set('general', 'podcast_category', 'Technology');
rank_math()->settings->set('general', 'podcast_owner', 'LIX ROASTERY');
rank_math()->settings->set('general', 'podcast_owner_email', 'mooms.dev@gmail.com');

add_filter('pre_option_laca_seo_general', function($v) {
    $v = is_array($v) ? $v : [];
    $v['podcast_category'] = 'Technology';
    $v['podcast_owner'] = 'LIX ROASTERY';
    $v['podcast_owner_email'] = 'mooms.dev@gmail.com';
    return $v;
});
\App\Features\LacaSeo\Core\Settings::clearCache();

$rmRss = new \RankMathPro\Podcast\Podcast_RSS();
ob_start();
$rmRss->add_namespace();
$rmNs = ob_get_clean();

ob_start();
$rmRss->add_channel_data();
$rmChannel = ob_get_clean();

$lacaPodcast = new LacaPodcast();
ob_start();
$lacaPodcast->addNamespace();
$lacaNs = ob_get_clean();

ob_start();
$lacaPodcast->addChannelData();
$lacaChannel = ob_get_clean();

preg_match('/<itunes:category[^>]+>/', $rmChannel, $rmCat);
preg_match('/<itunes:category[^>]+>/', $lacaChannel, $lacaCat);
preg_match('/<itunes:author>[^<]+<\/itunes:author>/', $rmChannel, $rmAuthor);
preg_match('/<itunes:author>[^<]+<\/itunes:author>/', $lacaChannel, $lacaAuthor);
preg_match('/<itunes:name>[^<]+<\/itunes:name>/', $rmChannel, $rmName);
preg_match('/<itunes:name>[^<]+<\/itunes:name>/', $lacaChannel, $lacaName);
preg_match('/<itunes:email>[^<]+<\/itunes:email>/', $rmChannel, $rmEmail);
preg_match('/<itunes:email>[^<]+<\/itunes:email>/', $lacaChannel, $lacaEmail);

$passPD1 = (trim($rmNs) === trim($lacaNs))
    && !empty($rmCat[0]) && ($rmCat[0] === $lacaCat[0])
    && !empty($rmAuthor[0]) && ($rmAuthor[0] === $lacaAuthor[0])
    && !empty($rmName[0]) && ($rmName[0] === $lacaName[0])
    && !empty($rmEmail[0]) && ($rmEmail[0] === $lacaEmail[0]);
reportResult('PD1', 'Podcast feed output valid iTunes namespace and channel tags RM Pro == Laca', $passPD1);

// =============================================================
// R1: Regression 19 URL cmp test
// =============================================================
$cmpScript = '/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/p3b/cmp.php';
$phpCli = '/Users/lacadev/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$mysqlSock = '/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$cmpCmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($cmpScript) . ' 2>/dev/null';
$cmpOutput = (string)shell_exec($cmpCmd);

$hasBadUrls = preg_match('/BAD URLS:\s*(\d+)/', $cmpOutput, $m);
$badCount = $hasBadUrls ? (int)$m[1] : -1;
$hasFindTheOrigin = (bool) preg_match('#/journal-cat/find-the-origin/\s+RMnodes=\d+\s+Lacanodes=\d+\s+diffs=2#', $cmpOutput);
$hasSearchCafe = (bool) preg_match('#/\?s=cafe\s+RMnodes=\d+\s+Lacanodes=\d+\s+diffs=1#', $cmpOutput);

$passR1 = ($badCount === 2) && $hasFindTheOrigin && $hasSearchCafe;
reportResult('R1', 'Regression 19 URL cmp test (exactly 2 accepted diffs: find-the-origin=2, ?s=cafe=1)', $passR1, "badCount={$badCount} origin={$hasFindTheOrigin} search={$hasSearchCafe}");

// =============================================================
// R2: Prior Phases Tests Regression (test-phase5.php)
// =============================================================
$phase5Script = __DIR__ . '/test-phase5.php';
$phase5Cmd = escapeshellarg($phpCli) . ' -d memory_limit=512M -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($phase5Script) . ' 2>/dev/null';
$phase5Output = (string)shell_exec($phase5Cmd);
$passR2 = str_contains($phase5Output, 'SUMMARY pass=') && str_contains($phase5Output, 'fail=0');
reportResult('R2', 'Phase 5 test suite passes with fail=0', $passR2, "output: " . trim(substr($phase5Output, -40)));

// =============================================================
// Z1: Database integrity check (unchanged checksum)
// =============================================================
$finalPostsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$finalPostmetaCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$finalOptionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name NOT LIKE '%_transient_%'");
$finalChecksum = md5(
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(ID, post_modified)) FROM {$wpdb->posts}") . '|' .
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(meta_id, meta_value)) FROM {$wpdb->postmeta}")
);

$passZ1 = ($initialPostsCount === $finalPostsCount)
    && ($initialPostmetaCount === $finalPostmetaCount)
    && ($initialOptionsCount === $finalOptionsCount)
    && ($initialChecksum === $finalChecksum);
reportResult('Z1', 'DB unchanged checksum verification', $passZ1, "posts={$initialPostsCount}/{$finalPostsCount} postmeta={$initialPostmetaCount}/{$finalPostmetaCount} options={$initialOptionsCount}/{$finalOptionsCount}");

echo "SUMMARY pass={$passCount} lacaonly={$lacaonlyCount} fail={$failCount}\n";


