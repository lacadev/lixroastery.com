<?php
use WPEmerge\Facades\WPEmerge;
use WPEmergeTheme\Facades\Theme;

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// SECURITY & PERMISSIONS
// =============================================================================
/**
 * Define super users (Developers) who can see system menus and hidden users.
 */
add_filter('lacadev_super_user_logins', function($logins) {
    return ['lacadev']; // Add your developer username here
});

/**
 * Chốt an toàn khi parent tự cập nhật trước khi child build lại CSS:
 * chỉ in khung cảnh mới nếu login.css đã build (dist của child) có style `.login-scene`,
 * nếu chưa thì giữ nền sao cũ — tránh núi/mây SVG hiện màu đen không style.
 */
function laca_login_scene_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $css   = dirname(get_stylesheet_directory()) . '/dist/styles/login.css';
        $ready = is_readable($css) && str_contains((string) file_get_contents($css), 'login-scene');
    }
    return $ready;
}

/**
 * Nền sao + trăng cũ (dùng khi CSS chưa build lại).
 */
function laca_login_legacy_night_sky(): void
{
    ?>
    <div class="login-night-sky" aria-hidden="true">
        <div class="alp-stars">
            <?php foreach (range(1, 80) as $i) : 
                $size = rand(15, 30) / 10;
                $left = rand(0, 10000) / 100;
                $top = rand(0, 10000) / 100;
                $dur = rand(20, 50) / 10;
                $delay = rand(0, 50) / 10;
            ?>
                <div class="alp-star" style="left:<?php echo $left; ?>%; top:<?php echo $top; ?>%; width:<?php echo $size; ?>px; height:<?php echo $size; ?>px; --d:<?php echo $dur; ?>s; animation-delay:<?php echo $delay; ?>s;"></div>
            <?php endforeach; ?>
        </div>
        <div class="alp-moon"></div>
    </div>
    <?php
}

/**
 * Login page — khung cảnh thiên nhiên theo giờ của site (Cài đặt → Chung → Múi giờ).
 *
 * Mặt trời đi vòng cung từ giờ bắt đầu → giờ kết thúc ban ngày, mặt trăng đi phần còn lại;
 * lặn xuống sau dãy núi. Bốn tông: dawn / day / dusk / night (±1 giờ quanh mốc mọc, lặn).
 * Khung giờ ban ngày đổi qua filter `laca_login_day_hours` → [giờ bắt đầu, giờ kết thúc).
 * JS (resources/scripts/login/sky.js) tính lại mỗi phút cùng công thức để mặt trời di chuyển khi trang mở lâu.
 */
function laca_login_sky_state(): array
{
    [$start, $end] = array_map('floatval', apply_filters('laca_login_day_hours', [6, 18]));
    $now  = current_datetime();
    $hour = (int) $now->format('G') + (int) $now->format('i') / 60;

    $is_day   = $hour >= $start && $hour < $end;
    $day_len  = $end - $start;
    $progress = $is_day
        ? ($hour - $start) / $day_len
        : fmod($hour - $end + 24, 24) / (24 - $day_len);

    if (abs($hour - $start) < 1) {
        $phase = 'dawn';
    } elseif (abs($hour - $end) < 1) {
        $phase = 'dusk';
    } else {
        $phase = $is_day ? 'day' : 'night';
    }

    return [
        'start'  => $start,
        'end'    => $end,
        'is_day' => $is_day,
        'phase'  => $phase,
        'x'      => round(6 + $progress * 88, 2),                 // % từ trái
        'y'      => round(76 - sin(M_PI * $progress) * 69, 2),     // % từ trên — cao nhất giữa buổi
        'offset' => wp_timezone()->getOffset($now),                // giây lệch UTC, cho JS
    ];
}

add_filter('login_body_class', function ($classes) {
    $sky       = laca_login_sky_state();
    $classes[] = $sky['is_day'] ? 'login-is-day' : 'login-is-night';
    $classes[] = 'login-phase-' . $sky['phase'];
    return $classes;
});

/**
 * Một dải núi/đồi: các điểm sống núi + path SVG khép kín xuống đáy khung (viewBox 1600×500).
 * Hình dạng = tổng 3 sóng sin (dài, vừa, ngắn) với tần số/pha ngẫu nhiên theo seed cố định
 * → mỗi lần tải giống nhau, các lớp không trùng nhịp nên đọc ra "trùng điệp".
 *
 * @return array{points: array<int, array{0: float, 1: float}>, d: string}
 */
function laca_login_ridge(float $base, float $amp, float $sharp): array
{
    $f1 = mt_rand(140, 260) / 100;
    $f2 = mt_rand(380, 620) / 100;
    $f3 = mt_rand(1100, 1600) / 100;
    $p1 = mt_rand(0, 628) / 100;
    $p2 = mt_rand(0, 628) / 100;
    $p3 = mt_rand(0, 628) / 100;

    $points = [];
    for ($x = -40; $x <= 1640; $x += 16) {
        $t = $x / 1600 * 2 * M_PI;
        // 1 - |sin| cho đỉnh nhọn (núi), sin thường cho đồi tròn; $sharp trộn hai kiểu
        $peak = (1 - abs(sin($t * $f1 / 2 + $p1))) * 2 - 1;
        $wave = sin($t * $f1 + $p1);
        $y = $base
            - $amp * (($peak * $sharp + $wave * (1 - $sharp)) * 0.65
            + sin($t * $f2 + $p2) * 0.25
            + sin($t * $f3 + $p3) * 0.1);
        $points[] = [$x, $y];
    }

    // Đường cong mượt: Q qua trung điểm giữa các điểm liên tiếp
    $d = sprintf('M%.1f 500 L%.1f %.1f', $points[0][0], $points[0][0], $points[0][1]);
    $n = count($points);
    for ($i = 1; $i < $n - 1; $i++) {
        $mx = ($points[$i][0] + $points[$i + 1][0]) / 2;
        $my = ($points[$i][1] + $points[$i + 1][1]) / 2;
        $d .= sprintf(' Q%.1f %.1f %.1f %.1f', $points[$i][0], $points[$i][1], $mx, $my);
    }
    $d .= sprintf(' L%.1f %.1f L%.1f 500 Z', $points[$n - 1][0], $points[$n - 1][1], $points[$n - 1][0]);

    return ['points' => $points, 'd' => $d];
}

/**
 * Độ cao sống núi tại hoành độ $x (nội suy tuyến tính giữa hai điểm).
 */
function laca_login_ridge_y(array $points, float $x): float
{
    $i = (int) floor(($x + 40) / 16);
    $i = max(0, min(count($points) - 2, $i));
    [$x0, $y0] = $points[$i];
    [$x1, $y1] = $points[$i + 1];
    return $y0 + ($y1 - $y0) * (($x - $x0) / ($x1 - $x0));
}

/**
 * Cây thông nhiều tầng, mép tầng hơi cong xuống như cành rủ.
 */
function laca_login_pine_path(float $x, float $base, float $h, int $tiers = 3): string
{
    $trunk = $h * 0.1;
    $crown = $h - $trunk;
    $d     = sprintf('M%.1f %.1f h%.1f v%.1f h%.1f Z ', $x - $h * 0.025, $base, $h * 0.05, -$trunk - 2, -$h * 0.05);

    for ($i = 0; $i < $tiers; $i++) {
        $top    = $base - $trunk - $crown + $i * $crown * (0.62 / $tiers) * 1.35;
        $bottom = $base - $trunk - ($tiers - 1 - $i) * $crown * 0.16;
        $w      = $h * (0.16 + 0.1 * ($i + 1) / $tiers);
        $sag    = ($bottom - $top) * 0.18;
        $d .= sprintf(
            'M%1$.1f %2$.1f Q%3$.1f %4$.1f %5$.1f %6$.1f Q%1$.1f %7$.1f %8$.1f %6$.1f Q%9$.1f %4$.1f %1$.1f %2$.1f Z ',
            $x, $top,
            $x + $w * 0.35, $bottom - $sag,
            $x + $w, $bottom,
            $bottom - $sag * 0.6,
            $x - $w,
            $x - $w * 0.35
        );
    }
    return $d;
}

/**
 * Cây tán tròn (lá rộng) — điểm xuyết giữa hàng thông.
 */
function laca_login_round_tree_path(float $x, float $base, float $h): string
{
    $r = $h * 0.3;
    $cy = $base - $h + $r;
    $d  = sprintf('M%.1f %.1f h%.1f v%.1f h%.1f Z ', $x - $h * 0.03, $base, $h * 0.06, -($base - $cy), -$h * 0.06);
    foreach ([[0, 0, 1], [-0.7, 0.45, 0.75], [0.7, 0.45, 0.75], [0, 0.6, 0.8]] as [$dx, $dy, $k]) {
        $rr = $r * $k;
        $cx = $x + $dx * $r;
        $cyy = $cy + $dy * $r;
        $d .= sprintf('M%.1f %.1f a%.1f %.1f 0 1 0 %.1f 0 a%.1f %.1f 0 1 0 %.1f 0 Z ', $cx - $rr, $cyy, $rr, $rr, $rr * 2, $rr, $rr, -$rr * 2);
    }
    return $d;
}

add_action('login_header', function () {
    if (!laca_login_scene_ready()) {
        laca_login_legacy_night_sky();
        return;
    }

    $sky = laca_login_sky_state();

    // Núi, cây cố định giữa các lần tải (seed cố định); sao, mây thì ngẫu nhiên.
    mt_srand(20261109);
    // [độ cao nền, biên độ, độ nhọn] từ xa → gần
    $layers = [
        laca_login_ridge(150, 125, 0.9),
        laca_login_ridge(228, 88, 0.7),
        laca_login_ridge(298, 56, 0.4),
        laca_login_ridge(362, 36, 0.2),
        laca_login_ridge(430, 26, 0.0),
    ];

    // Rừng xa (lớp 4): thông nhỏ một tầng, dày
    $forest_far = '';
    for ($x = -20; $x < 1620; $x += mt_rand(7, 13)) {
        $forest_far .= laca_login_pine_path($x, laca_login_ridge_y($layers[3]['points'], $x) + 4, mt_rand(16, 30), 2);
    }

    // Hàng cây gần (lớp 5): thông nhiều tầng + vài cây tán tròn
    $forest_near = '';
    for ($x = -20; $x < 1620; $x += mt_rand(16, 34)) {
        $base = laca_login_ridge_y($layers[4]['points'], $x) + 8;
        $forest_near .= mt_rand(1, 100) <= 8
            ? laca_login_round_tree_path($x, $base, mt_rand(40, 70))
            : laca_login_pine_path($x, $base, mt_rand(55, 130), mt_rand(3, 4));
    }
    mt_srand();

    $clouds = ['ls-cloud-a', 'ls-cloud-b', 'ls-cloud-c'];
    ?>
    <div class="login-scene" aria-hidden="true"
         data-day-start="<?php echo esc_attr($sky['start']); ?>"
         data-day-end="<?php echo esc_attr($sky['end']); ?>"
         data-tz-offset="<?php echo esc_attr($sky['offset']); ?>"
         style="--orb-x:<?php echo esc_attr($sky['x']); ?>%; --orb-y:<?php echo esc_attr($sky['y']); ?>%;">
        <svg class="ls-defs" width="0" height="0" focusable="false">
            <defs>
                <path id="ls-cloud-a" d="M18 58 C2 58 0 40 16 36 C14 20 34 12 48 22 C54 6 82 0 96 16 C104 6 126 6 134 22 C150 16 170 26 166 42 C184 42 188 58 172 58 Z"/>
                <path id="ls-cloud-b" d="M10 58 C-2 58 2 46 14 44 C16 34 32 30 42 36 C50 24 70 22 80 32 C92 24 112 26 118 38 C134 32 154 38 156 48 C170 46 178 58 166 58 Z"/>
                <path id="ls-cloud-c" d="M24 58 C8 58 6 44 22 40 C20 26 38 18 52 26 C60 12 86 10 98 24 C112 16 132 22 134 36 C150 34 160 46 152 58 Z"/>
                <linearGradient id="ls-mist-grad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" class="ls-mist-stop" stop-opacity="0"/>
                    <stop offset="1" class="ls-mist-stop" stop-opacity="0.75"/>
                </linearGradient>
            </defs>
        </svg>

        <div class="alp-stars">
            <?php foreach (range(1, 80) as $i) :
                $size  = rand(10, 28) / 10;
                $left  = rand(0, 10000) / 100;
                $top   = rand(0, 6000) / 100;
                $dur   = rand(20, 50) / 10;
                $delay = rand(0, 50) / 10;
            ?>
                <div class="alp-star" style="left:<?php echo $left; ?>%; top:<?php echo $top; ?>%; width:<?php echo $size; ?>px; height:<?php echo $size; ?>px; --d:<?php echo $dur; ?>s; animation-delay:<?php echo $delay; ?>s;"></div>
            <?php endforeach; ?>
        </div>

        <div class="ls-orb <?php echo $sky['is_day'] ? 'is-sun' : 'is-moon'; ?>"></div>

        <div class="alp-clouds">
            <?php foreach (range(1, 6) as $i) :
                $top   = rand(5, 40);
                $scale = rand(60, 140) / 100;
                $dur   = rand(90, 170);
                $delay = -rand(0, $dur);
                $op    = rand(65, 95) / 100;
                $x     = rand(0, 85); // vị trí đứng yên khi tắt chuyển động
                $shape = $clouds[array_rand($clouds)];
            ?>
                <svg class="alp-cloud" viewBox="0 0 190 60" focusable="false" style="top:<?php echo $top; ?>%; --x:<?php echo $x; ?>vw; --s:<?php echo $scale; ?>; --o:<?php echo $op; ?>; animation-duration:<?php echo $dur; ?>s; animation-delay:<?php echo $delay; ?>s;"><use href="#<?php echo esc_attr($shape); ?>"/></svg>
            <?php endforeach; ?>
        </div>

        <svg class="ls-land" viewBox="0 0 1600 500" preserveAspectRatio="xMidYMax slice" focusable="false">
            <?php foreach ($layers as $i => $layer) : ?>
                <path class="ls-ridge ls-ridge-<?php echo (int) $i; ?>" d="<?php echo esc_attr($layer['d']); ?>"/>
                <?php if ($i < 3) : // sương mù dâng trong thung lũng giữa các lớp ?>
                    <rect class="ls-mist" x="0" y="<?php echo (int) (240 + $i * 55); ?>" width="1600" height="<?php echo (int) (120 - $i * 20); ?>" fill="url(#ls-mist-grad)"/>
                <?php endif; ?>
                <?php if ($i === 3) : ?>
                    <path class="ls-forest-far" d="<?php echo esc_attr(trim($forest_far)); ?>"/>
                <?php endif; ?>
            <?php endforeach; ?>
            <path class="ls-forest-near" d="<?php echo esc_attr(trim($forest_near)); ?>"/>
        </svg>

        <?php // Khu cắm trại góc dưới trái: lều + lửa trại (ngọn lửa, tia lửa, khói do CSS animate) ?>
        <div class="ls-camp">
            <div class="ls-fire-glow"></div>
            <svg class="ls-camp-art" viewBox="0 0 240 120" focusable="false">
                <g class="ls-grass">
                    <path d="M4 114 l3 -10 l2 10 l3 -13 l2 13 l3 -9 l2 9 Z"/>
                    <path d="M140 114 l2 -8 l2 8 l3 -11 l2 11 l2 -7 l2 7 Z"/>
                    <path d="M214 114 l3 -9 l2 9 l3 -12 l2 12 l3 -8 l2 8 Z"/>
                </g>
                <g class="ls-tent">
                    <line class="ls-rope" x1="75" y1="30" x2="6" y2="112"/>
                    <line class="ls-rope" x1="75" y1="30" x2="146" y2="112"/>
                    <path class="ls-tent-body" d="M75 28 L20 112 L130 112 Z"/>
                    <path class="ls-tent-shade" d="M75 28 L130 112 L98 112 Z"/>
                    <path class="ls-tent-door" d="M75 50 Q70 84 58 112 L92 112 Q80 84 75 50 Z"/>
                    <path class="ls-tent-flap" d="M75 50 Q66 86 50 112 L58 112 Q70 84 75 50 Z"/>
                    <line class="ls-pole" x1="75" y1="20" x2="75" y2="30"/>
                </g>
                <g class="ls-campfire">
                    <ellipse class="ls-stone" cx="166" cy="112" rx="6" ry="4"/>
                    <ellipse class="ls-stone" cx="204" cy="112" rx="6" ry="4"/>
                    <ellipse class="ls-stone" cx="185" cy="114" rx="7" ry="3.5"/>
                    <g class="ls-flames">
                        <path class="ls-flame ls-flame-1" d="M185 62 C175 78 167 86 169 98 C170 106 177 111 185 111 C193 111 200 106 201 98 C203 86 195 78 185 62 Z"/>
                        <path class="ls-flame ls-flame-2" d="M186 74 C179 84 174 90 176 100 C177 106 181 110 186 110 C191 110 195 106 196 100 C197 91 192 84 186 74 Z"/>
                        <path class="ls-flame ls-flame-3" d="M185 86 C181 92 179 96 180 102 C181 106 183 109 185 109 C188 109 190 106 190 102 C191 97 189 92 185 86 Z"/>
                    </g>
                    <rect class="ls-log" x="165" y="104" width="42" height="7" rx="3.5" transform="rotate(-16 186 107.5)"/>
                    <rect class="ls-log" x="163" y="104" width="42" height="7" rx="3.5" transform="rotate(16 184 107.5)"/>
                </g>
            </svg>
            <div class="ls-smoke">
                <?php foreach (range(1, 4) as $i) : ?>
                    <span style="--dx:<?php echo rand(-30, 30); ?>px; animation-delay:<?php echo ($i - 1) * 1.3; ?>s;"></span>
                <?php endforeach; ?>
            </div>
            <div class="ls-sparks">
                <?php foreach (range(1, 8) as $i) : ?>
                    <span style="--dx:<?php echo rand(-26, 26); ?>px; --rise:<?php echo rand(50, 95); ?>px; animation-duration:<?php echo rand(12, 24) / 10; ?>s; animation-delay:<?php echo rand(0, 20) / 10; ?>s;"></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
});

/**
 * Custom check for super user status
 */
add_filter('lacadev_is_super_user', function($is_super, $current_user) {
    // Developers are always super users regardless of role
    $super_logins = apply_filters('lacadev_super_user_logins', ['lacadev']);
    if (in_array($current_user->user_login, $super_logins, true)) {
        return true;
    }
    return $is_super;
}, 10, 2);

// =============================================================================
// THEME INFORMATION
// =============================================================================
define('AUTHOR', [
    'name' => 'La Cà Dev',
    'email' => 'mooms.dev@gmail.com',
    'phone_number' => '0989.64.67.66',
    'website' => 'https://lacadev.com/',
    'date_started' => get_option('_theme_info_date_started'),
    'date_published' => get_option('_theme_info_date_publish'),
]);

// =============================================================================
// DIRECTORY CONSTANTS
// =============================================================================

// Directory Names
define('APP_APP_DIR_NAME', 'app');
define('APP_APP_HELPERS_DIR_NAME', 'helpers');
define('APP_APP_ROUTES_DIR_NAME', 'routes');
define('APP_APP_SETUP_DIR_NAME', 'setup');
define('APP_DIST_DIR_NAME', 'dist');
define('APP_RESOURCES_DIR_NAME', 'resources');
define('APP_THEME_DIR_NAME', 'theme');
define('APP_VENDOR_DIR_NAME', 'vendor');

// Theme Component Names
define('APP_THEME_USER_NAME', 'users');
define('APP_THEME_TAXONOMY_NAME', 'taxonomies');
define('APP_THEME_WIDGET_NAME', 'widgets');
define('APP_THEME_BLOCK_NAME', 'blocks');
define('APP_THEME_WALKER_NAME', 'walkers');

// Directory Paths
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APP_APP_DIR', APP_DIR . APP_APP_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_HELPERS_DIR', APP_APP_DIR . APP_APP_HELPERS_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_ROUTES_DIR', APP_APP_DIR . APP_APP_ROUTES_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_RESOURCES_DIR', APP_DIR . APP_RESOURCES_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_THEME_DIR', APP_DIR . APP_THEME_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_VENDOR_DIR', APP_DIR . APP_VENDOR_DIR_NAME . DIRECTORY_SEPARATOR);
define('APP_DIST_DIR', APP_DIR . APP_DIST_DIR_NAME . DIRECTORY_SEPARATOR);

// Setup Directories
define('APP_APP_SETUP_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR);
// define('APP_APP_SETUP_ECOMMERCE_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_ECOMMERCE_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_USER_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_USER_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_TAXONOMY_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_TAXONOMY_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_WIDGET_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_WIDGET_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_BLOCK_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_BLOCK_NAME . DIRECTORY_SEPARATOR);
define('APP_APP_SETUP_WALKER_DIR', APP_THEME_DIR . APP_APP_SETUP_DIR_NAME . DIRECTORY_SEPARATOR . APP_THEME_WALKER_NAME . DIRECTORY_SEPARATOR);

// =============================================================================
// DEPENDENCIES & AUTOLOADING
// =============================================================================

// Load composer dependencies
if (file_exists(APP_VENDOR_DIR . 'autoload.php')) {
    require_once APP_VENDOR_DIR . 'autoload.php';
    \Carbon_Fields\Carbon_Fields::boot();
}

// Enable Theme shortcut
WPEmerge::alias('Theme', \WPEmergeTheme\Facades\Theme::class);

// Load helpers
require_once APP_APP_DIR . 'helpers.php';

// Load responsive image helpers (NEW - for automatic srcset/sizes)
require_once APP_APP_DIR . 'helpers/responsive-images.php';

// Bootstrap Theme
// phpcs:ignore
Theme::bootstrap(require APP_APP_DIR . 'config.php');

// Register hooks
require_once APP_APP_DIR . 'hooks.php';

// =============================================================================
// THEME SETUP
// =============================================================================

/**
 * Nạp bản dịch của theme (theme/languages/{locale}.mo — câu gốc tiếng Việt).
 *
 * Từ WP 6.7 load_theme_textdomain() chỉ ghi nhận đường dẫn và nạp just-in-time
 * ĐÚNG 1 file (wp-content/languages/themes/laca-{locale}.mo sẽ thắng).
 * load_textdomain() nạp thẳng file của theme + fallback load_theme_textdomain.
 * Hook 'change_locale' để switch_to_locale() và Polylang nạp lại đúng bản dịch.
 */
function laca_load_textdomain($locale = ''): void
{
    $locale = $locale ?: determine_locale();
    $mofile = APP_THEME_DIR . 'languages' . DIRECTORY_SEPARATOR . $locale . '.mo';
    if (is_readable($mofile)) {
        load_textdomain('laca', $mofile, $locale);
    } else {
        load_theme_textdomain('laca', APP_THEME_DIR . 'languages');
    }
}
add_action('change_locale', 'laca_load_textdomain');

add_action('after_setup_theme', function () {
    // Load textdomain
    laca_load_textdomain();

    // Load theme components
    require_once APP_APP_SETUP_DIR . 'theme-support.php';
    require_once APP_APP_SETUP_DIR . 'menus.php';

    // Load security & SEO (Phase 1 improvements)
    require_once APP_APP_SETUP_DIR . 'security.php';
    require_once APP_APP_SETUP_DIR . 'recaptcha.php';
    // contact-recaptcha.php removed - recaptcha.php handles all forms including contact
    // (Legacy seo.php removed - replaced by App\Features\LacaSeo)

    // Load image optimization (Phase 2 improvements)
    require_once APP_APP_SETUP_DIR . 'image-optimization.php';

    // Load advanced optimization modules
    require_once APP_APP_SETUP_DIR . 'assets.php';
    require_once APP_APP_SETUP_DIR . 'performance.php';
    require_once APP_APP_SETUP_DIR . 'pwa.php';

    // Load Gutenberg blocks (Carbon Fields)
    // $blocks_dir = APP_APP_SETUP_DIR . '/blocks';
    // $block_files = glob($blocks_dir . '/*.php');
    // foreach ($block_files as $block_file) {
    //     require_once $block_file;
    // }

    // Load ReactJS Gutenberg blocks
    require_once APP_APP_SETUP_DIR . 'gutenberg-blocks.php';

});

// =============================================================================
// AUTOLOAD COMPONENTS
// =============================================================================
$folders = [
    // APP_APP_SETUP_ECOMMERCE_DIR,
    APP_APP_SETUP_TAXONOMY_DIR,
    APP_APP_SETUP_WALKER_DIR,
];

foreach ($folders as $folder) {
    $filesPath = scandir($folder);
    if ($filesPath !== false) {
        foreach ($filesPath as $item) {
            $file = $folder . $item;
            if (is_file($file)) {
                require_once $folder . $item;
            }
        }
    }
}

/**
 * Localize AJAX search data (script bundled in theme.js)
 */
function custom_ajax_search_script()
{
    wp_localize_script('theme-js-bundle', 'themeSearch', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_search_nonce'),
    ]);
}
add_action('wp_enqueue_scripts', 'custom_ajax_search_script');

/**
 * Register custom query vars for search pagination
 */
function lacadev_register_search_query_vars($vars)
{
    $vars[] = 'paged_post';
    $vars[] = 'paged_page';
    $vars[] = 'paged_product';
    $vars[] = 'paged_service';
    // Add more custom post types as needed
    return $vars;
}
add_filter('query_vars', 'lacadev_register_search_query_vars');

// =============================================================================
// CUSTOM POST TYPES
// =============================================================================
// Dynamic CPT — đăng ký CPT được tạo qua admin panel (Appearance > Custom Post Types)
new \App\Features\DynamicCPT\DynamicCptManager();

// =============================================================================
// DATABASE TABLES
// =============================================================================
add_action('after_switch_theme', function () {
    \App\Databases\ContactFormTable::install();
    \App\Settings\EmailLog\EmailLogTable::install();
    \App\Settings\LoginLog\LoginLogTable::install();
});

// Đảm bảo bảng luôn tồn tại
\App\Databases\ContactFormTable::install();
\App\Settings\EmailLog\EmailLogTable::install();
\App\Settings\LoginLog\LoginLogTable::install();

// =============================================================================
// COMMENTS CALLBACK
// =============================================================================
function lacadev_custom_comments_callback( $comment, $args, $depth ) {
    $GLOBALS['comment'] = $comment;
    
    $tag = ( isset($args['style']) && 'b' === $args['style'] ) ? 'b' : 'li';
    $add_below = 'div-comment';
    ?>
    <<?php echo $tag; ?> <?php comment_class( empty( $args['has_children'] ) ? 'custom-comment' : 'parent custom-comment' ); ?> id="comment-<?php comment_ID(); ?>">
        <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
            <div class="comment-meta">
                <div class="comment-author vcard">
                    <?php 
                    $avatar_size = $args['avatar_size'] ?? 48;
                    if ( 0 != $avatar_size ) {
                        echo get_avatar( $comment, $avatar_size );
                    }
                    ?>
                    <?php printf( '<span class="author-name">%s</span>', get_comment_author_link( $comment ) ); ?>
                </div><!-- .comment-author -->

                <div class="comment-metadata">
                    <a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
                        <time datetime="<?php comment_time( 'c' ); ?>">
                            <?php printf( __( '%s ago', 'laca' ), human_time_diff( get_comment_time('U'), current_time('timestamp') ) ); ?>
                        </time>
                    </a>
                </div><!-- .comment-metadata -->
            </div><!-- .comment-meta -->

            <?php if ( '0' == $comment->comment_approved ) : ?>
            <p class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.', 'laca' ); ?></p>
            <?php endif; ?>

            <div class="comment-content">
                <?php comment_text(); ?>
            </div><!-- .comment-content -->

            <div class="comment-actions">
                <?php
                comment_reply_link(
                    array_merge(
                        $args,
                        array(
                            'add_below' => $add_below,
                            'depth'     => $depth,
                            'max_depth' => $args['max_depth'] ?? 5,
                            'before'    => '<div class="reply">',
                            'after'     => '</div>',
                        )
                    )
                );
                ?>
            </div>
        </article><!-- .comment-body -->
    <?php
}
