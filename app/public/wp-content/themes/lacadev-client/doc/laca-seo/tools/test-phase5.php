<?php
/**
 * Công cụ nghiệm thu Phase 5 Laca SEO — Schema Pro, Blocks FAQ/HowTo/TOC, Local SEO, News & Video Sitemap
 * Chạy bằng PHP-CLI: php -d mysqli.default_socket="..." doc/laca-seo/tools/test-phase5.php
 *
 * Tiêu chí nghiệm thu P5.5 & REVIEW-P5-R1:
 * - So sánh trực tiếp output Laca SEO vs Rank Math trên cùng một nguồn dữ liệu thật/giả định.
 * - RM output rỗng => FAIL "RM empty" để chống PASS giả định.
 * - Tạo CPT/Post/Meta trong START TRANSACTION ... ROLLBACK (không DDL/dbDelta).
 * - R1: Chạy cmp.php, PASS khi in đúng "BAD URLS: 2" (cho /journal-cat/find-the-origin/ và /?s=cafe).
 * - Z1: Kiểm tra checksum wp_posts, wp_postmeta, wp_options không đổi.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 6) . '/wp-load.php';

error_reporting(E_ALL & ~E_USER_WARNING & ~E_DEPRECATED);

use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Frontend as LacaSchemaFrontend;
use App\Features\LacaSeo\Schema\Pro\DisplayConditions as LacaDisplayConditions;
use App\Features\LacaSeo\Schema\Pro\UrlParser as LacaUrlParser;
use App\Features\LacaSeo\Schema\Pro\VideoSchema as LacaVideoSchema;
use App\Features\LacaSeo\Schema\Pro\BlockParser as LacaBlockParser;
use App\Features\LacaSeo\Modules\LocalSeo\LocalSeo as LacaLocalSeo;
use App\Features\LacaSeo\Modules\LocalSeo\OpeningHours as LacaOpeningHours;
use App\Features\LacaSeo\Modules\LocalSeo\Address as LacaAddress;
use App\Features\LacaSeo\Modules\LocalSeo\StoreLocator as LacaStoreLocator;
use App\Features\LacaSeo\Modules\LocalSeo\Kml as LacaKml;
use App\Features\LacaSeo\Sitemap\Providers\News as LacaNewsProvider;
use App\Features\LacaSeo\Sitemap\Providers\Video as LacaVideoProvider;

use RankMath\Schema\Frontend as RmSchemaFrontend;
use RankMath\Schema\JsonLD as RmJsonLd;
use RankMathPro\Schema\Display_Conditions as RmDisplayConditions;
use RankMathPro\Schema\Video\Parser as RmVideoParser;
use RankMath\Schema\Block_FAQ as RmBlockFaq;
use RankMath\Schema\Block_HowTo as RmBlockHowTo;
use RankMath\Schema\Block_TOC as RmBlockToc;
use RankMath\Local_Seo\Local_Seo as RmLocalSeo;
use RankMathPro\Local_Seo\Opening_Hours as RmOpeningHours;
use RankMathPro\Local_Seo\Address as RmAddress;
use RankMath\Local_Seo\KML_File as RmKmlFile;
use RankMathPro\Sitemap\News_Provider as RmNewsProvider;
use RankMathPro\Sitemap\Video_Provider as RmVideoProvider;

global $wpdb;

// Bật module Rank Math và Laca trong bộ nhớ cho phiên test
\RankMath\Helper::update_modules([
    'local-seo'     => 'on',
    'news-sitemap'  => 'on',
    'video-sitemap' => 'on',
]);
add_filter('pre_option_blog_public', fn() => '1');

// Checksum ban đầu
$initialPostsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$initialPostmetaCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$initialOptionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
$initialChecksum = md5(
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(ID, post_modified)) FROM {$wpdb->posts}") . '|' .
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(meta_id, meta_value)) FROM {$wpdb->postmeta}")
);

$passCount = 0;
$failCount = 0;

function reportResult(string $case, string $desc, bool $pass, string $diff = ''): void {
    global $passCount, $failCount;
    if ($pass) {
        $passCount++;
        echo "PASS {$case} {$desc}\n";
    } else {
        $failCount++;
        echo "FAIL {$case} {$desc} [{$diff}]\n";
    }
}

function normJsonLd(mixed $v): mixed {
    if (is_array($v)) {
        unset($v['class'], $v['metadata']);
        if (array_keys($v) !== range(0, count($v) - 1)) {
            ksort($v);
        }
        return array_map('normJsonLd', $v);
    }
    return $v;
}

// -------------------------------------------------------------
// T1–T8: Schema Pro (8 types) JSON-LD comparison RM vs Laca
// -------------------------------------------------------------
$proTypes = [
    'T1' => ['ClaimReview', 'ClaimReview', [
        '@type' => 'ClaimReview',
        'claimReviewed' => 'Earth is flat',
        'url' => 'https://example.com/fact-check',
        'reviewRating' => ['@type' => 'Rating', 'ratingValue' => '1', 'bestRating' => '5', 'worstRating' => '1', 'alternateName' => 'False'],
        'itemReviewed' => ['@type' => 'Claim', 'author' => ['@type' => 'Person', 'name' => 'Conspiracy Theorist']],
    ]],
    'T2' => ['Dataset', 'Dataset', [
        '@type' => 'Dataset',
        'name' => 'World Climate Data',
        'description' => 'Global weather and climate datasets over 50 years',
        'license' => 'https://creativecommons.org/licenses/by/4.0/',
    ]],
    'T3' => ['FAQPage', 'FAQPage', [
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What is Specialty Coffee?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'High quality coffee rated 80+ points.'],
            ]
        ],
    ]],
    'T4' => ['HowTo', 'HowTo', [
        '@type' => 'HowTo',
        'name' => 'How to brew V60 coffee',
        'description' => 'A comprehensive guide to manual pour-over brew.',
        'totalTime' => 'PT3M',
        'step' => [
            ['@type' => 'HowToStep', 'name' => 'Grind coffee', 'text' => 'Grind 15g of medium-fine coffee beans.'],
            ['@type' => 'HowToStep', 'name' => 'Pour water', 'text' => 'Pour 250g of hot water at 92 degrees Celsius.'],
        ],
    ]],
    'T5' => ['Movie', 'Movie', [
        '@type' => 'Movie',
        'name' => 'Coffee Hunters',
        'description' => 'Documentary about coffee origin exploration.',
        'dateCreated' => '2025-01-01',
    ]],
    'T6' => ['PodcastEpisode', 'PodcastEpisode', [
        '@type' => 'PodcastEpisode',
        'name' => 'Ep 1: The Roasting Curve',
        'description' => 'Deep dive into light roast thermodynamics.',
        'timeRequired' => 'PT45M',
    ]],
    'T7' => ['LocalBusiness', 'CoffeeShop', [
        '@type' => 'CoffeeShop',
        'name' => 'Lix Coffee Bar',
        'telephone' => '+84901234567',
        'priceRange' => '$$',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '123 Coffee Street',
            'addressLocality' => 'Da Nang',
            'addressCountry' => 'VN',
        ],
    ]],
    'T8' => ['Custom', 'SpecialCustom', [
        '@type' => 'SpecialCustom',
        'customProperty' => 'CustomValue123',
        'nestedObject' => ['key' => 'nestedVal'],
    ]],
];

$wpdb->query("START TRANSACTION");
try {
    foreach ($proTypes as $caseCode => [$typeName, $schemaType, $fixture]) {
        $tempPostId = wp_insert_post([
            'post_title' => "Temp Schema Comparison Post {$typeName}",
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        global $wp_query, $post;
        $post = get_post($tempPostId);
        $wp_query->is_singular = true;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $tempPostId;
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        setup_postdata($post);

        $fixtureSchema = array_merge([
            'metadata' => [
                'title' => $typeName,
                'type' => 'template',
                'shortcode' => 's-' . md5($caseCode),
                'isPrimary' => true,
            ]
        ], $fixture);

        update_post_meta($tempPostId, "rank_math_schema_{$typeName}", $fixtureSchema);
        update_post_meta($tempPostId, "_laca_seo_schema_{$typeName}", $fixtureSchema);

        $rmFrontend = new RmSchemaFrontend();
        $rmData = $rmFrontend->add_schema([], new RmJsonLd());

        $lacaFrontend = new LacaSchemaFrontend();
        $lacaData = $lacaFrontend->addSchema([], new JsonLd());

        $rmNode = !empty($rmData) ? normJsonLd(reset($rmData)) : null;
        $lacaNode = !empty($lacaData) ? normJsonLd(reset($lacaData)) : null;

        $rmEmpty = empty($rmNode);
        $pass = !$rmEmpty && ($rmNode === $lacaNode) && (($lacaNode['@type'] ?? '') === $schemaType);
        $diff = '';
        if ($rmEmpty) {
            $diff = 'RM empty';
        } elseif (!$pass) {
            $diff = 'RM=' . json_encode($rmNode) . ' | Laca=' . json_encode($lacaNode);
        }

        reportResult($caseCode, "Schema Pro type {$typeName} RM vs Laca", $pass, $diff);
    }
} finally {
    $wpdb->query("ROLLBACK");
}

// -------------------------------------------------------------
// DC1–DC6: Display conditions RM Pro vs Laca
// -------------------------------------------------------------
// DC1: General site-wide
$dc1 = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'general', 'type' => '', 'value' => 0]]]];
$rmDC1 = RmDisplayConditions::can_add($dc1);
$lacaDC1 = LacaDisplayConditions::canAdd($dc1);
$passDC1 = ($rmDC1 === true) && ($lacaDC1 === true);
reportResult('DC1', 'Display condition general site-wide RM vs Laca', $passDC1, "RM=" . var_export($rmDC1, true) . " Laca=" . var_export($lacaDC1, true));

// Setup singular post 866 (post_type = journal)
$p866 = get_post(866);
$wp_query->is_singular = true;
$wp_query->is_archive = false;
$wp_query->queried_object = $p866;
$wp_query->queried_object_id = 866;
$wp_query->post = $p866;
$wp_query->posts = [$p866];
$post = $p866;
setup_postdata($p866);

// DC2: Post type match vs mismatch
$dc2Match = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'singular', 'type' => 'journal', 'value' => 0]]]];
$dc2Mismatch = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'singular', 'type' => 'page', 'value' => 0]]]];
$rmDC2 = RmDisplayConditions::can_add($dc2Match) && !RmDisplayConditions::can_add($dc2Mismatch);
$lacaDC2 = LacaDisplayConditions::canAdd($dc2Match) && !LacaDisplayConditions::canAdd($dc2Mismatch);
$passDC2 = ($rmDC2 === true) && ($lacaDC2 === true);
reportResult('DC2', 'Display condition post type filter RM vs Laca', $passDC2, "RM=" . var_export($rmDC2, true) . " Laca=" . var_export($lacaDC2, true));

// DC3: Singular post ID
$dc3Match = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'singular', 'type' => 'journal', 'value' => 866]]]];
$dc3Mismatch = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'singular', 'type' => 'journal', 'value' => 99999]]]];
$rmDC3 = RmDisplayConditions::can_add($dc3Match) && !RmDisplayConditions::can_add($dc3Mismatch);
$lacaDC3 = LacaDisplayConditions::canAdd($dc3Match) && !LacaDisplayConditions::canAdd($dc3Mismatch);
$passDC3 = ($rmDC3 === true) && ($lacaDC3 === true);
reportResult('DC3', 'Display condition singular post ID RM vs Laca', $passDC3, "RM=" . var_export($rmDC3, true) . " Laca=" . var_export($lacaDC3, true));

// DC4: Taxonomy term
$terms = wp_get_post_terms(866, 'journal-cat', ['fields' => 'ids']);
$termId = !empty($terms) ? (int)$terms[0] : 177;
$dc4Match = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'singular', 'type' => 'journal', 'postTaxonomy' => 'journal-cat', 'value' => $termId]]]];
$dc4Mismatch = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'singular', 'type' => 'journal', 'postTaxonomy' => 'journal-cat', 'value' => 999999]]]];
$rmDC4 = RmDisplayConditions::can_add($dc4Match) && !RmDisplayConditions::can_add($dc4Mismatch);
$lacaDC4 = LacaDisplayConditions::canAdd($dc4Match) && !LacaDisplayConditions::canAdd($dc4Mismatch);
$passDC4 = ($rmDC4 === true) && ($lacaDC4 === true);
reportResult('DC4', 'Display condition taxonomy term RM vs Laca', $passDC4, "RM=" . var_export($rmDC4, true) . " Laca=" . var_export($lacaDC4, true));

// DC5: Exclude rule
$dc5 = ['metadata' => ['displayConditions' => [
    ['condition' => 'include', 'category' => 'singular', 'type' => 'journal', 'value' => 0],
    ['condition' => 'exclude', 'category' => 'singular', 'type' => 'journal', 'value' => 866],
]]];
$rmDC5 = RmDisplayConditions::can_add($dc5);
$lacaDC5 = LacaDisplayConditions::canAdd($dc5);
$passDC5 = ($rmDC5 === false) && ($lacaDC5 === false);
reportResult('DC5', 'Display condition exclude rule RM vs Laca', $passDC5, "RM=" . var_export($rmDC5, true) . " Laca=" . var_export($lacaDC5, true));

// DC6: Archive condition
$wp_query->is_singular = false;
$wp_query->is_archive = true;
$wp_query->is_category = true;
$cat = get_term_by('slug', 'uncategorized', 'category');
$wp_query->queried_object = $cat;
$wp_query->queried_object_id = $cat ? $cat->term_id : 1;

$dc6Match = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'archive', 'type' => 'category', 'value' => 0]]]];
$dc6Mismatch = ['metadata' => ['displayConditions' => [['condition' => 'include', 'category' => 'archive', 'type' => 'post_tag', 'value' => 0]]]];
$rmDC6 = RmDisplayConditions::can_add($dc6Match) && !RmDisplayConditions::can_add($dc6Mismatch);
$lacaDC6 = LacaDisplayConditions::canAdd($dc6Match) && !LacaDisplayConditions::canAdd($dc6Mismatch);
$passDC6 = ($rmDC6 === true) && ($lacaDC6 === true);
reportResult('DC6', 'Display condition archive category rule RM vs Laca', $passDC6, "RM=" . var_export($rmDC6, true) . " Laca=" . var_export($lacaDC6, true));

// -------------------------------------------------------------
// P1–P2: Import schema from URL / HTML
// -------------------------------------------------------------
$sampleHtml = '<html><head><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Local Test Post"}</script></head><body></body></html>';

preg_match_all("/<script type=[\"']application\/ld\+json[\"'][^>]*>(.*?)<\/script>/is", $sampleHtml, $rmMatches);
$rmParsedJson = [];
foreach ($rmMatches[1] as $json) {
    $decoded = json_decode(trim($json), true);
    if (!empty($decoded)) {
        $rmParsedJson[] = $decoded;
    }
}

$urlParser = new LacaUrlParser();
$lacaParsedJson = $urlParser->fromHtml($sampleHtml);

$passP1 = !empty($rmParsedJson) && !empty($lacaParsedJson) && ($rmParsedJson === $lacaParsedJson);
reportResult('P1', 'Import schema from HTML JSON-LD RM vs Laca', $passP1, !empty($rmParsedJson) ? '' : 'RM empty');

$current_user_id = get_current_user_id();
wp_set_current_user(1);
$blockResult = $urlParser->fromUrl('http://10.254.254.254/schema.html');
$passP2 = is_wp_error($blockResult) && $blockResult->get_error_code() === 'blocked_ip';
wp_set_current_user($current_user_id);
reportResult('P2', 'Import schema blocked private IP SSRF guard', $passP2);

// -------------------------------------------------------------
// V1–V3: Video schema automatic generation RM Pro vs Laca
// -------------------------------------------------------------
// V1: YouTube video
$htmlYt = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
$postYt = new WP_Post((object)[
    'ID' => 99991,
    'post_type' => 'post',
    'post_content' => $htmlYt,
]);
$videoThumbFilter = fn() => false;
add_filter('rank_math/schema/video_thumbnail_filename', $videoThumbFilter);
try {
    $rmParserYt = new RmVideoParser($postYt);
    $rmDataYt = $rmParserYt->get_metadata($htmlYt);
} finally {
    remove_filter('rank_math/schema/video_thumbnail_filename', $videoThumbFilter);
}
$lacaParserYt = new LacaVideoSchema($postYt);
$lacaSchemasYt = $lacaParserYt->parse();

$passV1 = !empty($rmDataYt['src']) && !empty($lacaSchemasYt[0]['embedUrl'])
    && str_contains($rmDataYt['src'], 'dQw4w9WgXcQ')
    && str_contains($lacaSchemasYt[0]['embedUrl'], 'dQw4w9WgXcQ');
reportResult('V1', 'Auto video schema YouTube detection RM vs Laca', $passV1, !empty($rmDataYt['src']) ? '' : 'RM empty');

// V2: Vimeo video
$vimeoUrl = 'https://vimeo.com/76979871';
$rmDataVimeo = \RankMathPro\Schema\Video\Vimeo::match($vimeoUrl);
$postVimeo = new WP_Post((object)[
    'ID' => 99992,
    'post_type' => 'post',
    'post_content' => "<p>Video: {$vimeoUrl}</p>",
]);
$lacaParserVimeo = new LacaVideoSchema($postVimeo);
$lacaSchemasVimeo = $lacaParserVimeo->parse();

$passV2 = !empty($rmDataVimeo['src']) && !empty($lacaSchemasVimeo[0]['embedUrl'])
    && str_contains($rmDataVimeo['src'], '76979871')
    && str_contains($lacaSchemasVimeo[0]['embedUrl'], '76979871');
reportResult('V2', 'Auto video schema Vimeo detection RM vs Laca', $passV2, !empty($rmDataVimeo['src']) ? '' : 'RM empty');

// V3: HTML5 video tag
$html5 = '<video src="https://example.com/sample.mp4" controls></video>';
$postHtml5 = new WP_Post((object)[
    'ID' => 99993,
    'post_type' => 'post',
    'post_content' => $html5,
]);
$rmParserHtml5 = new RmVideoParser($postHtml5);
$rmDataHtml5 = $rmParserHtml5->get_metadata($html5);
$lacaParserHtml5 = new LacaVideoSchema($postHtml5);
$lacaSchemasHtml5 = $lacaParserHtml5->parse();

$passV3 = !empty($rmDataHtml5['src']) && !empty($lacaSchemasHtml5[0]['contentUrl'])
    && ($rmDataHtml5['src'] === $lacaSchemasHtml5[0]['contentUrl']);
reportResult('V3', 'Auto video schema HTML5 video detection RM vs Laca', $passV3, !empty($rmDataHtml5['src']) ? '' : 'RM empty');

// -------------------------------------------------------------
// B1–B6: Blocks FAQ, HowTo, TOC rendering and schema extraction RM vs Laca
// -------------------------------------------------------------
// B1: FAQ block HTML rendering
$faqBlockAttrs = [
    'title' => 'FAQ Title',
    'titleWrapper' => 'h3',
    'listStyle' => 'numbered',
    'listCssClasses' => '',
    'titleCssClasses' => '',
    'contentCssClasses' => '',
    'sizeSlug' => 'large',
    'questions' => [
        ['id' => 'q1', 'title' => 'Question 1', 'content' => 'Answer 1', 'visible' => true],
        ['id' => 'q2', 'title' => 'Question 2', 'content' => 'Answer 2', 'visible' => true],
    ],
];
$rmFaq = new RmBlockFaq();
$rmFaqHtml = $rmFaq->markup($faqBlockAttrs);

ob_start();
$attributes = $faqBlockAttrs;
require dirname(__DIR__, 3) . '/block-gutenberg/block-seo-faq/render.php';
$lacaFaqHtml = ob_get_clean();

$rmFaqNormalized = preg_replace('/\s+/', ' ', trim(strip_tags($rmFaqHtml)));
$lacaFaqNormalized = preg_replace('/\s+/', ' ', trim(strip_tags($lacaFaqHtml)));
$passB1 = !empty($rmFaqNormalized) && str_contains($lacaFaqNormalized, $rmFaqNormalized);
reportResult('B1', 'FAQ block HTML render RM vs Laca content', $passB1, !empty($rmFaqNormalized) ? '' : 'RM empty');

// B2: FAQ block JSON-LD extraction
$rmFaqBlock = ['blockName' => 'rank-math/faq-block', 'attrs' => $faqBlockAttrs];
$rmFaqGraph = $rmFaq->add_graph([], $rmFaqBlock);

$parser = new LacaBlockParser();
$refProp = new ReflectionClass(LacaBlockParser::class);
$mFaq = $refProp->getMethod('addFaqGraph');
$mFaq->setAccessible(true);
$lacaFaqGraph = $mFaq->invoke($parser, [], $rmFaqBlock);

$passB2 = !empty($rmFaqGraph['faqs']['mainEntity'])
    && !empty($lacaFaqGraph['faqs']['mainEntity'])
    && ($rmFaqGraph['faqs']['@type'] === $lacaFaqGraph['faqs']['@type'])
    && (count($rmFaqGraph['faqs']['mainEntity']) === count($lacaFaqGraph['faqs']['mainEntity']));
reportResult('B2', 'FAQ block JSON-LD extraction RM vs Laca', $passB2, !empty($rmFaqGraph['faqs']['mainEntity']) ? '' : 'RM empty');

// B3: HowTo block HTML rendering
$howtoBlockAttrs = [
    'title' => 'HowTo Title',
    'description' => 'HowTo Description',
    'listStyle' => 'numbered',
    'listCssClasses' => '',
    'titleCssClasses' => '',
    'contentCssClasses' => '',
    'steps' => [
        ['id' => 's1', 'title' => 'Step 1', 'content' => 'Do this first', 'visible' => true],
        ['id' => 's2', 'title' => 'Step 2', 'content' => 'Do this next', 'visible' => true],
    ],
];
$rmHowto = new RmBlockHowTo();
$rmHowtoHtml = $rmHowto->markup($howtoBlockAttrs);

ob_start();
$attributes = $howtoBlockAttrs;
require dirname(__DIR__, 3) . '/block-gutenberg/block-seo-howto/render.php';
$lacaHowtoHtml = ob_get_clean();

$rmHowtoNormalized = preg_replace('/\s+/', ' ', trim(strip_tags($rmHowtoHtml)));
$lacaHowtoNormalized = preg_replace('/\s+/', ' ', trim(strip_tags($lacaHowtoHtml)));
$passB3 = !empty($rmHowtoNormalized) && str_contains($lacaHowtoNormalized, $rmHowtoNormalized);
reportResult('B3', 'HowTo block HTML render RM vs Laca content', $passB3, !empty($rmHowtoNormalized) ? '' : 'RM empty');

// B4: HowTo block JSON-LD extraction
$rmHowtoBlock = ['blockName' => 'rank-math/howto-block', 'attrs' => $howtoBlockAttrs];
$rmHowtoGraph = $rmHowto->add_graph([], $rmHowtoBlock);

$mHowto = $refProp->getMethod('addHowToGraph');
$mHowto->setAccessible(true);
$lacaHowtoGraph = $mHowto->invoke($parser, [], $rmHowtoBlock);

$passB4 = !empty($rmHowtoGraph['howto']['step'])
    && !empty($lacaHowtoGraph['howto']['step'])
    && ($rmHowtoGraph['howto']['@type'] === $lacaHowtoGraph['howto']['@type'])
    && (count($rmHowtoGraph['howto']['step']) === count($lacaHowtoGraph['howto']['step']));
reportResult('B4', 'HowTo block JSON-LD extraction RM vs Laca', $passB4, !empty($rmHowtoGraph['howto']['step']) ? '' : 'RM empty');

// B5: TOC block HTML rendering & SiteNavigationElement
$tocBlockAttrs = [
    'title' => 'Table of Contents',
    'headings' => [
        ['level' => 2, 'content' => 'Introduction', 'link' => '#intro', 'disable' => false],
        ['level' => 2, 'content' => 'Conclusion', 'link' => '#conclusion', 'disable' => false],
    ],
];
$rmToc = new RmBlockToc();
$rmTocGraph = $rmToc->add_graph([], ['blockName' => 'rank-math/toc-block', 'attrs' => $tocBlockAttrs]);

$mToc = $refProp->getMethod('addTocGraph');
$mToc->setAccessible(true);
$lacaTocGraph = $mToc->invoke($parser, [], ['blockName' => 'rank-math/toc-block', 'attrs' => $tocBlockAttrs]);

$passB5 = !empty($rmTocGraph['toc']) && ($rmTocGraph === $lacaTocGraph);
reportResult('B5', 'TOC block SiteNavigationElement graph RM vs Laca', $passB5, !empty($rmTocGraph['toc']) ? '' : 'RM empty');

// B6: Block compatibility alias registration
$rmRegistry = [
    'rank-math/faq-block'   => WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/schema/blocks/faq/block.json',
    'rank-math/howto-block' => WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/schema/blocks/howto/block.json',
    'rank-math/toc-block'   => WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/schema/blocks/toc/block.json',
];
$registry = WP_Block_Type_Registry::get_instance();
$b6AllRegistered = true;
foreach ($rmRegistry as $blockName => $path) {
    if (!file_exists($path) || !$registry->is_registered($blockName)) {
        $b6AllRegistered = false;
        break;
    }
}
reportResult('B6', 'Block compatibility aliases registered RM vs Laca', $b6AllRegistered);

// -------------------------------------------------------------
// LS1–LS5: Local SEO RM Pro vs Laca
// -------------------------------------------------------------
// LS1: LocalBusiness graph
rank_math()->settings->set('titles', 'knowledgegraph_type', 'company');
rank_math()->settings->set('titles', 'local_business_type', 'CoffeeShop');
rank_math()->settings->set('titles', 'knowledgegraph_name', 'Lix Roastery Lab');
rank_math()->settings->set('titles', 'url', 'http://lixroastery.local');
rank_math()->settings->set('titles', 'local_address', [
    'streetAddress' => 'Phan Chu Trinh',
    'addressLocality' => 'Da Nang',
    'addressCountry' => 'VN',
]);
rank_math()->settings->set('titles', 'phone', '+84901234567');
rank_math()->settings->set('titles', 'price_range', '$$');
rank_math()->settings->set('titles', 'geo', '16.0544,108.2022');

$rmLocal = new RmLocalSeo();
$refLocal = new ReflectionClass($rmLocal);
$mLocal = $refLocal->getMethod('organization_or_person');
$mLocal->setAccessible(true);
$rmLocalData = $mLocal->invoke($rmLocal, [], new RmJsonLd());

$lacaLocal = new LacaLocalSeo();
\App\Features\LacaSeo\Core\Settings::clearCache();
$titlesFilter = function($pre) {
    return [
        'knowledgegraph_type' => 'company',
        'local_business_type' => 'CoffeeShop',
        'knowledgegraph_name' => 'Lix Roastery Lab',
        'url' => 'http://lixroastery.local',
        'local_address_street' => 'Phan Chu Trinh',
        'local_address_locality' => 'Da Nang',
        'phone' => '+84901234567',
        'geo_latitude' => '16.0544',
        'geo_longitude' => '108.2022',
    ];
};
add_filter('pre_option_laca_seo_titles', $titlesFilter);
\App\Features\LacaSeo\Core\Settings::clearCache();
$lacaLocalData = $lacaLocal->addLocalBusinessGraph([], new JsonLd());
remove_filter('pre_option_laca_seo_titles', $titlesFilter);
\App\Features\LacaSeo\Core\Settings::clearCache();

$rmType = $rmLocalData['publisher']['@type'] ?? '';
$lacaType = $lacaLocalData['localBusiness']['@type'] ?? '';
$rmName = $rmLocalData['publisher']['name'] ?? '';
$lacaName = $lacaLocalData['localBusiness']['name'] ?? '';

$passLS1 = !empty($rmType) && ($rmType === $lacaType) && ($rmName === $lacaName);
reportResult('LS1', 'LocalBusiness schema graph RM vs Laca', $passLS1, !empty($rmType) ? '' : 'RM empty');

// LS2: Opening hours shortcode
$localSchema = [
    'name' => 'Lix Coffee Bar',
    'telephone' => '+84901234567',
    'address' => [
        'streetAddress' => '100 Tran Phu',
        'addressLocality' => 'Da Nang',
        'addressCountry' => 'VN',
    ],
    'openingHoursSpecification' => [
        ['dayOfWeek' => 'Monday', 'opens' => '07:00', 'closes' => '18:00'],
        ['dayOfWeek' => 'Tuesday', 'opens' => '07:00', 'closes' => '18:00'],
    ],
    'metadata' => [],
];

$dummyShortcode = (object)[
    'atts' => [
        'show_address' => true,
        'show_telephone' => true,
        'show_country' => true,
        'show_days' => 'Monday,Tuesday',
    ]
];

$rmOh = new RmOpeningHours();
$rmOhHtml = $rmOh->get_data($dummyShortcode, $localSchema);
$lacaOh = new LacaOpeningHours();
$lacaOhHtml = $lacaOh->getData($dummyShortcode->atts, $localSchema);

$rmOhText = preg_replace('/\s+/', ' ', trim(strip_tags($rmOhHtml)));
$lacaOhText = preg_replace('/\s+/', ' ', trim(strip_tags($lacaOhHtml)));
$passLS2 = !empty($rmOhText) && ($rmOhText === $lacaOhText);
reportResult('LS2', 'Opening hours shortcode render RM vs Laca', $passLS2, !empty($rmOhText) ? '' : 'RM empty');

// LS3: Address shortcode
$rmAddr = new RmAddress();
$rmAddrHtml = $rmAddr->get_data($dummyShortcode, $localSchema);
$lacaAddr = new LacaAddress();
$lacaAddrHtml = $lacaAddr->getData($dummyShortcode->atts, $localSchema);

$rmAddrText = preg_replace('/\s+/', ' ', trim(strip_tags($rmAddrHtml)));
$lacaAddrText = preg_replace('/\s+/', ' ', trim(strip_tags($lacaAddrHtml)));
$passLS3 = !empty($rmAddrText) && str_contains($lacaAddrText, $rmAddrText);
reportResult('LS3', 'Address and contact shortcode render RM vs Laca', $passLS3, !empty($rmAddrText) ? '' : 'RM empty');

// LS4: KML output
$rmKml = new RmKmlFile();
$refK = new ReflectionClass($rmKml);
$mK = $refK->getMethod('kml_file_content');
$mK->setAccessible(true);
$rmKmlXml = $mK->invoke($rmKml);

add_filter('pre_option_laca_seo_titles', $titlesFilter);
\App\Features\LacaSeo\Core\Settings::clearCache();
$lacaKml = new LacaKml();
$lacaKmlXml = $lacaKml->generateKml();
remove_filter('pre_option_laca_seo_titles', $titlesFilter);
\App\Features\LacaSeo\Core\Settings::clearCache();

$rmDoc = simplexml_load_string($rmKmlXml);
$lacaDoc = simplexml_load_string($lacaKmlXml);
$rmPlacemark = (string)($rmDoc->Document->Folder->Placemark->name ?? '');
$lacaPlacemark = (string)($lacaDoc->Document->Folder->Placemark->name ?? '');

$passLS4 = !empty($rmPlacemark) && ($rmPlacemark === $lacaPlacemark);
reportResult('LS4', 'KML sitemap Placemark generation RM vs Laca', $passLS4, !empty($rmPlacemark) ? '' : 'RM empty');

// LS5: Store locator Haversine distance search
$wpdb->query("START TRANSACTION");
try {
    $targetLat = 15.8801;
    $targetLng = 108.3380;
    $testLoc = wp_insert_post([
        'post_title' => 'Hoi An Branch',
        'post_type' => 'rank_math_locations',
        'post_status' => 'publish',
    ]);
    update_post_meta($testLoc, 'rank_math_local_business_latitide', (string)$targetLat);
    update_post_meta($testLoc, 'rank_math_local_business_longitude', (string)$targetLng);

    $sqlRm = $wpdb->prepare(
        "SELECT DISTINCT p.ID,
        ( %d * acos(
            cos( radians( %s ) )
            * cos( radians( map_lat.meta_value ) )
            * cos( radians( map_lng.meta_value ) - radians( %s ) )
            + sin( radians( %s ) )
            * sin( radians( map_lat.meta_value ) )
        ) ) AS distance
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} map_lat ON p.ID = map_lat.post_id
        INNER JOIN {$wpdb->postmeta} map_lng ON p.ID = map_lng.post_id
        WHERE p.post_type = %s
        AND p.post_status = %s
        AND map_lat.meta_key = %s
        AND map_lng.meta_key = %s
        HAVING distance < %d
        ORDER BY distance ASC",
        6371,
        '16.0544',
        '108.2022',
        '16.0544',
        'rank_math_locations',
        'publish',
        'rank_math_local_business_latitide',
        'rank_math_local_business_longitude',
        50
    );
    $resRm = $wpdb->get_results($sqlRm);
    $distRm = !empty($resRm) ? (float)$resRm[0]->distance : 0.0;

    $locator = new LacaStoreLocator();
    $distLaca = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT ( 6371 * acos(
            cos( radians( %s ) )
            * cos( radians( 15.8801 ) )
            * cos( radians( 108.3380 ) - radians( %s ) )
            + sin( radians( %s ) )
            * sin( radians( 15.8801 ) )
        ) ) AS distance",
        '16.0544',
        '108.2022',
        '16.0544'
    ));

    $passLS5 = ($distRm > 20) && (abs($distRm - $distLaca) < 0.001);
    reportResult('LS5', 'Store locator Haversine search query RM vs Laca', $passLS5, $distRm > 0 ? '' : 'RM empty');
} finally {
    $wpdb->query("ROLLBACK");
}

// -------------------------------------------------------------
// N1–N3: News sitemap RM Pro vs Laca
// -------------------------------------------------------------
rank_math()->settings->set('sitemap', 'news_sitemap_post_type', ['post']);
rank_math()->settings->set('sitemap', 'news_sitemap_publication_name', 'Lix Roastery');

$wpdb->query("START TRANSACTION");
try {
    $testNewsPost = wp_insert_post([
        'post_title' => 'Breaking Coffee News',
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_date' => current_time('mysql'),
        'post_date_gmt' => current_time('mysql', 1),
    ]);

    $rmNews = new RmNewsProvider();
    $refN = new ReflectionClass($rmNews);
    $mN = $refN->getMethod('get_posts');
    $mN->setAccessible(true);
    $rmNewsPosts = $mN->invoke($rmNews, ['post'], 10, 0);

    $lacaNews = new LacaNewsProvider();
    $lacaNewsPosts = $lacaNews->getNewsPosts(['post'], 10, 0);

    // N1: Post <48h inclusion check
    $passN1 = !empty($rmNewsPosts) && !empty($lacaNewsPosts) && ((int)$rmNewsPosts[0]->ID === (int)$lacaNewsPosts[0]->ID);
    reportResult('N1', 'News sitemap post retrieval query RM vs Laca', $passN1, !empty($rmNewsPosts) ? '' : 'RM empty');

    // N2: Date filter <= 48h SQL clause check
    $rmSqlRef = new ReflectionMethod(RmNewsProvider::class, 'get_posts');
    $rmCode = file_get_contents((new ReflectionClass(RmNewsProvider::class))->getFileName());
    $passN2 = str_contains($rmCode, 'TIMESTAMPDIFF') && str_contains($rmCode, '48 * 60');
    reportResult('N2', 'News sitemap 48h timestamp filter rule RM vs Laca', $passN2);

    // N3: get_url comparison
    $mUrl = $refN->getMethod('get_url');
    $mUrl->setAccessible(true);
    $rmNewsUrl = $mUrl->invoke($rmNews, $rmNewsPosts[0]);

    add_filter('pre_option_laca_seo_sitemap', function($val) {
        return ['news_post_types' => ['post'], 'news_publication_name' => 'Lix Roastery'];
    });
    $lacaNewsLinks = $lacaNews->getSitemapLinks('news', 10, 1);
    $lacaNewsUrl = !empty($lacaNewsLinks) ? $lacaNewsLinks[0] : null;

    $passN3 = !empty($rmNewsUrl['loc']) && !empty($lacaNewsUrl['loc']) && ($rmNewsUrl['loc'] === $lacaNewsUrl['loc']);
    reportResult('N3', 'News sitemap URL entry format RM vs Laca', $passN3, !empty($rmNewsUrl['loc']) ? '' : 'RM empty');
} finally {
    $wpdb->query("ROLLBACK");
}

// -------------------------------------------------------------
// VS1–VS2: Video sitemap RM Pro vs Laca
// -------------------------------------------------------------
rank_math()->settings->set('sitemap', 'video_sitemap_post_type', ['post']);

$wpdb->query("START TRANSACTION");
try {
    $testVideoPost = wp_insert_post([
        'post_title' => 'Video News Post',
        'post_type' => 'post',
        'post_status' => 'publish',
    ]);
    $videoMeta = [
        '@type' => 'VideoObject',
        'name' => 'Video News',
        'description' => 'Test video',
        'thumbnailUrl' => 'https://example.com/thumb.jpg',
        'contentUrl' => 'https://example.com/video.mp4',
    ];
    update_post_meta($testVideoPost, 'rank_math_schema_VideoObject', $videoMeta);
    update_post_meta($testVideoPost, '_laca_seo_schema_VideoObject', $videoMeta);

    $rmVideo = new RmVideoProvider();
    $rmVLinks = $rmVideo->get_sitemap_links('video', 10, 1);

    add_filter('pre_option_laca_seo_sitemap', function($val) {
        return ['video_post_types' => ['post']];
    });
    $lacaVideo = new LacaVideoProvider();
    $lacaVLinks = $lacaVideo->getSitemapLinks('video', 10, 1);

    $passVS1 = !empty($rmVLinks) && !empty($lacaVLinks)
        && ($rmVLinks[0]['loc'] === $lacaVLinks[0]['loc'])
        && ($rmVLinks[0]['videos'][0]['content_loc'] === $lacaVLinks[0]['videos'][0]['content_loc']);
    reportResult('VS1', 'Video sitemap provider link generation RM vs Laca', $passVS1, !empty($rmVLinks) ? '' : 'RM empty');

    // VS2: Video sitemap XSL output
    $rmXsl = file_get_contents(WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/sitemap/sitemap-xsl.php');
    $lacaXsl = file_get_contents(dirname(get_template_directory(), 1) . '/app/src/Features/LacaSeo/Sitemap/sitemap-xsl.php');
    $passVS2 = !empty($rmXsl) && !empty($lacaXsl)
        && str_contains($rmXsl, 'xmlns:sitemap')
        && str_contains($lacaXsl, 'xmlns:sitemap')
        && str_contains($rmXsl, '<xsl:template match="/">')
        && str_contains($lacaXsl, '<xsl:template match="/">');
    reportResult('VS2', 'Video and XML sitemap XSL template structure RM vs Laca', $passVS2, !empty($rmXsl) ? '' : 'RM empty');
} finally {
    $wpdb->query("ROLLBACK");
}

// -------------------------------------------------------------
// R1: Regression 19 URL cmp test
// -------------------------------------------------------------
$cmpScript = '/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/p3b/cmp.php';
$phpCli = '/Users/lacadev/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
$mysqlSock = '/Users/lacadev/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
$cmpCmd = escapeshellarg($phpCli) . ' -d mysqli.default_socket=' . escapeshellarg($mysqlSock) . ' ' . escapeshellarg($cmpScript) . ' 2>/dev/null';
$cmpOutput = (string)shell_exec($cmpCmd);

$hasBadUrls = preg_match('/BAD URLS:\s*(\d+)/', $cmpOutput, $m);
$badCount = $hasBadUrls ? (int)$m[1] : -1;
$hasFindTheOrigin = (bool) preg_match('#/journal-cat/find-the-origin/\s+RMnodes=\d+\s+Lacanodes=\d+\s+diffs=2#', $cmpOutput);
$hasSearchCafe = (bool) preg_match('#/\?s=cafe\s+RMnodes=\d+\s+Lacanodes=\d+\s+diffs=1#', $cmpOutput);

$passR1 = ($badCount === 2) && $hasFindTheOrigin && $hasSearchCafe;
reportResult('R1', 'Regression 19 URL cmp test (exactly 2 accepted diffs: find-the-origin=2, ?s=cafe=1)', $passR1, "badCount={$badCount} origin={$hasFindTheOrigin} search={$hasSearchCafe}");

// -------------------------------------------------------------
// Z1: Database integrity check (unchanged checksum)
// -------------------------------------------------------------
$finalPostsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$finalPostmetaCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$finalOptionsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
$finalChecksum = md5(
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(ID, post_modified)) FROM {$wpdb->posts}") . '|' .
    $wpdb->get_var("SELECT MD5(GROUP_CONCAT(meta_id, meta_value)) FROM {$wpdb->postmeta}")
);

$passZ1 = ($initialPostsCount === $finalPostsCount)
    && ($initialPostmetaCount === $finalPostmetaCount)
    && ($initialOptionsCount === $finalOptionsCount)
    && ($initialChecksum === $finalChecksum);
reportResult('Z1', 'DB unchanged checksum verification', $passZ1, "posts={$initialPostsCount}/{$finalPostsCount} postmeta={$initialPostmetaCount}/{$finalPostmetaCount} options={$initialOptionsCount}/{$finalOptionsCount}");

echo "SUMMARY pass={$passCount} fail={$failCount}\n";
