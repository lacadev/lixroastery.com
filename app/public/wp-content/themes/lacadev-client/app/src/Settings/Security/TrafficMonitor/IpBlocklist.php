<?php

namespace App\Settings\Security\TrafficMonitor;

use App\Helpers\ClientIp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quản lý danh sách chặn IP/CIDR (`laca_tm_blocklist`), IP tin cậy (`laca_tm_trusted_ips`),
 * chặn sớm ở tầng PHP (`after_setup_theme` priority 0) và đồng bộ tuỳ chọn sang `.htaccess`.
 */
final class IpBlocklist
{
    public const BLOCKLIST_OPTION = 'laca_tm_blocklist';
    public const TRUSTED_OPTION   = 'laca_tm_trusted_ips';
    public const MAX_BLOCKLIST    = 2000;
    public const MAX_TRUSTED      = 200;
    public const HTACCESS_MARKER  = 'LACA SECURITY';

    /** @var array<string, array{added:int, by:int, note:string}>|null */
    private static ?array $blocklistCache = null;

    /** @var string[]|null */
    private static ?array $trustedCache = null;

    /**
     * @var array{
     *   exact: array<string, string>,
     *   v4_buckets: array<int, string[]>,
     *   v4_wide: string[],
     *   v6_ranges: string[]
     * }|null
     */
    private static ?array $parsedIndex = null;

    public static function resetCache(): void
    {
        self::$blocklistCache = null;
        self::$trustedCache   = null;
        self::$parsedIndex    = null;
    }

    public function register(): void
    {
        if (did_action('after_setup_theme') || doing_action('after_setup_theme')) {
            $this->enforce();
            return;
        }

        add_action('after_setup_theme', [$this, 'enforce'], 0);
    }

    /**
     * Chặn sớm request từ IP nằm trong blocklist (trừ CLI, cron, IP tin cậy).
     */
    public function enforce(): void
    {
        if (
            TrafficSettings::isKillSwitchActive()
            || TrafficSettings::isPluginActive()
            || (defined('WP_CLI') && WP_CLI)
            || wp_doing_cron()
        ) {
            return;
        }

        $blocklist = self::getBlocklist();
        if ($blocklist === []) {
            return;
        }

        $ip = ClientIp::get();
        if ($ip === '' || $ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }

        if (self::isTrusted($ip)) {
            return;
        }

        if (self::matchingCidr($ip) === null) {
            return;
        }

        if (function_exists('laca_load_textdomain')) {
            laca_load_textdomain();
        }

        status_header(403);
        nocache_headers();
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo esc_html__('403 Không được phép truy cập', 'laca');
        exit;
    }

    /**
     * @return array<string, array{added:int, by:int, note:string}>
     */
    public static function getBlocklist(): array
    {
        if (self::$blocklistCache !== null) {
            return self::$blocklistCache;
        }

        $raw = get_option(self::BLOCKLIST_OPTION, []);
        if (!is_array($raw)) {
            self::$blocklistCache = [];
            return [];
        }

        $clean = [];
        foreach ($raw as $cidr => $meta) {
            if (!is_string($cidr) || $cidr === '') {
                continue;
            }
            $clean[$cidr] = [
                'added' => isset($meta['added']) ? (int) $meta['added'] : 0,
                'by'    => isset($meta['by']) ? (int) $meta['by'] : 0,
                'note'  => isset($meta['note']) && is_string($meta['note']) ? substr($meta['note'], 0, 120) : '',
            ];
        }

        self::$blocklistCache = $clean;
        return $clean;
    }

    /**
     * @return string[]
     */
    public static function trustedIps(): array
    {
        if (self::$trustedCache !== null) {
            return self::$trustedCache;
        }

        $raw = get_option(self::TRUSTED_OPTION, []);
        if (!is_array($raw)) {
            self::$trustedCache = [];
            return [];
        }

        $valid = [];
        foreach ($raw as $ip) {
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $valid[] = $ip;
            }
        }

        self::$trustedCache = array_values(array_unique($valid));
        return self::$trustedCache;
    }

    public static function isTrusted(string $ip): bool
    {
        return in_array($ip, self::trustedIps(), true);
    }

    /**
     * Tạo CIDR chuẩn hoá từ IP + prefix (IPv4: 32/24/16/8; IPv6: 128/64).
     */
    public static function normalizeFromIpPrefix(string $ip, int $prefix): ?string
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return self::normalizeCidr($ip . '/' . $prefix, true);
    }

    /**
     * Chuẩn hoá chuỗi IP hoặc CIDR về dạng `<network_address>/<prefix>`.
     *
     * @param bool $strictAllowedPrefixes true: chỉ chấp nhận IPv4 /32 /24 /16 /8 và IPv6 /128 /64;
     *                                    false: cho phép mọi prefix >= 8 hợp lệ (dùng khi nhập từ .htaccess).
     */
    public static function normalizeCidr(string $cidr, bool $strictAllowedPrefixes = true): ?string
    {
        $cidr = trim($cidr);
        if ($cidr === '') {
            return null;
        }

        if (str_contains($cidr, '/')) {
            [$ipPart, $prefixStr] = explode('/', $cidr, 2);
            if (!ctype_digit($prefixStr)) {
                return null;
            }
            $prefix = (int) $prefixStr;
        } else {
            $ipPart = $cidr;
            $prefix = str_contains($ipPart, ':') ? 128 : 32;
        }

        if (!filter_var($ipPart, FILTER_VALIDATE_IP)) {
            return null;
        }

        $isV6 = str_contains($ipPart, ':');
        if ($strictAllowedPrefixes) {
            $allowed = $isV6 ? [128, 64] : [32, 24, 16, 8];
            if (!in_array($prefix, $allowed, true)) {
                return null;
            }
        } else {
            $maxBits = $isV6 ? 128 : 32;
            if ($prefix < 8 || $prefix > $maxBits) {
                return null;
            }
        }

        $bin = @inet_pton($ipPart);
        if ($bin === false) {
            return null;
        }

        $len       = strlen($bin);
        $fullBytes = intdiv($prefix, 8);
        $remBits   = $prefix % 8;
        $netBin    = substr($bin, 0, $fullBytes);

        if ($fullBytes < $len) {
            if ($remBits > 0) {
                $mask    = (0xFF << (8 - $remBits)) & 0xFF;
                $netBin .= chr(ord($bin[$fullBytes]) & $mask);
                $netBin .= str_repeat("\x00", $len - $fullBytes - 1);
            } else {
                $netBin .= str_repeat("\x00", $len - $fullBytes);
            }
        }

        $netIp = @inet_ntop($netBin);
        if ($netIp === false) {
            return null;
        }

        return $netIp . '/' . $prefix;
    }

    /**
     * Gộp trùng danh sách CIDR: bỏ CIDR nhỏ nếu đã nằm trong CIDR lớn hơn.
     *
     * @param string[] $cidrs
     * @return string[]
     */
    public static function deduplicateCidrs(array $cidrs): array
    {
        $parsed = [];
        foreach ($cidrs as $raw) {
            $norm = self::normalizeCidr((string) $raw, false);
            if ($norm === null) {
                continue;
            }
            [$net, $pfx] = explode('/', $norm, 2);
            $parsed[$norm] = [
                'cidr'   => $norm,
                'net'    => $net,
                'prefix' => (int) $pfx,
                'v6'     => str_contains($net, ':'),
            ];
        }

        // Sắp xếp từ dải rộng (prefix nhỏ) đến hẹp (prefix lớn)
        uasort($parsed, static fn(array $a, array $b): int => $a['prefix'] <=> $b['prefix']);

        $kept = [];
        foreach ($parsed as $item) {
            $covered = false;
            foreach ($kept as $big) {
                if ($big['v6'] !== $item['v6']) {
                    continue;
                }
                if ($big['prefix'] <= $item['prefix'] && ClientIp::inCidr($item['net'], $big['cidr'])) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $kept[] = $item;
            }
        }

        return array_column($kept, 'cidr');
    }

    /**
     * Trả về CIDR đang chặn `$ip`, hoặc null nếu không khớp.
     * Nhóm theo octet đầu với IPv4 khi danh sách lớn (>200 mục) và tra cứu O(1) cho IP đơn.
     */
    public static function matchingCidr(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $blocklist = self::getBlocklist();
        if ($blocklist === []) {
            return null;
        }

        if (self::$parsedIndex === null) {
            self::$parsedIndex = self::buildParsedIndex(array_keys($blocklist));
        }

        $isV6     = str_contains($ip, ':');
        $canonical = self::normalizeCidr($ip, false);
        if ($canonical !== null && isset(self::$parsedIndex['exact'][$canonical])) {
            return self::$parsedIndex['exact'][$canonical];
        }

        if ($isV6) {
            foreach (self::$parsedIndex['v6_ranges'] as $cidr) {
                if (ClientIp::inCidr($ip, $cidr)) {
                    return $cidr;
                }
            }
            return null;
        }

        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return null;
        }

        $firstOctet = ord($ipBin[0]);
        foreach (self::$parsedIndex['v4_buckets'][$firstOctet] ?? [] as $cidr) {
            if (ClientIp::inCidr($ip, $cidr)) {
                return $cidr;
            }
        }

        foreach (self::$parsedIndex['v4_wide'] as $cidr) {
            if (ClientIp::inCidr($ip, $cidr)) {
                return $cidr;
            }
        }

        return null;
    }

    /**
     * @param string[] $cidrs
     * @return array{
     *   exact: array<string, string>,
     *   v4_buckets: array<int, string[]>,
     *   v4_wide: string[],
     *   v6_ranges: string[]
     * }
     */
    private static function buildParsedIndex(array $cidrs): array
    {
        $index = [
            'exact'      => [],
            'v4_buckets' => [],
            'v4_wide'    => [],
            'v6_ranges'  => [],
        ];

        foreach ($cidrs as $cidr) {
            if (!str_contains($cidr, '/')) {
                $norm = self::normalizeCidr($cidr, false);
                if ($norm !== null) {
                    $index['exact'][$norm] = $cidr;
                }
                continue;
            }

            [$net, $pfxStr] = explode('/', $cidr, 2);
            $pfx  = (int) $pfxStr;
            $isV6 = str_contains($net, ':');

            if (($isV6 && $pfx === 128) || (!$isV6 && $pfx === 32)) {
                $norm = self::normalizeCidr($cidr, false) ?? $cidr;
                $index['exact'][$norm] = $cidr;
                continue;
            }

            if ($isV6) {
                $index['v6_ranges'][] = $cidr;
                continue;
            }

            if ($pfx >= 8) {
                $bin = @inet_pton($net);
                if ($bin !== false) {
                    $octet = ord($bin[0]);
                    $index['v4_buckets'][$octet][] = $cidr;
                    continue;
                }
            }

            $index['v4_wide'][] = $cidr;
        }

        return $index;
    }

    /**
     * Kiểm tra an toàn phía server trước khi chặn CIDR.
     */
    public static function validateSafeToBlock(string $normalizedCidr, bool $confirmWide = false): true|\WP_Error
    {
        if (!str_contains($normalizedCidr, '/')) {
            return new \WP_Error('invalid_cidr', __('Định dạng IP/CIDR không hợp lệ.', 'laca'));
        }

        [$netIp, $pfxStr] = explode('/', $normalizedCidr, 2);
        $prefix = (int) $pfxStr;
        $isV6   = str_contains($netIp, ':');

        // 1. Dải /8 yêu cầu xác nhận rõ ràng
        if (!$isV6 && $prefix === 8 && !$confirmWide) {
            return new \WP_Error(
                'confirm_wide_required',
                __('Dải /8 bao phủ hơn 16 triệu địa chỉ IP. Vui lòng tích xác nhận trước khi chặn.', 'laca')
            );
        }

        // 2. Cấm chặn private / reserved range (kiểm tra cả network address và các mốc nội bộ bên trong dải lớn)
        if (!filter_var($netIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return new \WP_Error(
                'private_or_reserved',
                __('Không được phép chặn địa chỉ IP nội bộ (private) hoặc dải dự trữ (reserved).', 'laca')
            );
        }

        $reservedSentinels = $isV6
            ? ['::1', 'fc00::1', 'fe80::1']
            : ['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.0.1', '169.254.0.1', '100.64.0.1'];
        foreach ($reservedSentinels as $sentinel) {
            if (ClientIp::inCidr($sentinel, $normalizedCidr)) {
                return new \WP_Error(
                    'private_or_reserved',
                    __('Không được phép chặn dải chứa địa chỉ IP nội bộ hoặc dự trữ.', 'laca')
                );
            }
        }

        // 3. Cấm chặn IP hiện tại của quản trị viên & địa chỉ kết nối trực tiếp
        $currentIp = ClientIp::get();
        if ($currentIp !== '' && $currentIp !== 'unknown' && ClientIp::inCidr($currentIp, $normalizedCidr)) {
            return new \WP_Error(
                'contains_current_ip',
                __('Từ chối chặn: dải này chứa địa chỉ IP hiện tại của bạn.', 'laca')
            );
        }

        $remoteAddr = isset($_SERVER['REMOTE_ADDR'])
            ? trim(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])))
            : '';
        if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP) && ClientIp::inCidr($remoteAddr, $normalizedCidr)) {
            return new \WP_Error(
                'contains_current_ip',
                __('Từ chối chặn: dải này chứa địa chỉ IP kết nối hiện tại.', 'laca')
            );
        }

        // 4. Cấm chặn IP của máy chủ
        $serverAddr = isset($_SERVER['SERVER_ADDR'])
            ? trim(sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])))
            : '';
        if ($serverAddr !== '' && filter_var($serverAddr, FILTER_VALIDATE_IP) && ClientIp::inCidr($serverAddr, $normalizedCidr)) {
            return new \WP_Error(
                'contains_server_ip',
                __('Từ chối chặn: dải này chứa địa chỉ IP của máy chủ.', 'laca')
            );
        }

        // 5. Cấm chặn IP tin cậy
        foreach (self::trustedIps() as $trustedIp) {
            if (ClientIp::inCidr($trustedIp, $normalizedCidr)) {
                return new \WP_Error(
                    'contains_trusted_ip',
                    sprintf(
                        /* translators: %s: địa chỉ IP tin cậy */
                        __('Từ chối chặn: dải này chứa IP tin cậy (%s).', 'laca'),
                        $trustedIp
                    )
                );
            }
        }

        return true;
    }

    public static function block(string $cidr, string $note = '', bool $confirmWide = false): true|\WP_Error
    {
        $res = self::blockMany([$cidr], $note, $confirmWide);
        if (is_wp_error($res)) {
            return $res;
        }

        return true;
    }

    /**
     * Chặn hàng loạt (tối đa 50 mục/lần).
     *
     * @param string[] $cidrs
     * @return array{added: string[], skipped: int}|\WP_Error
     */
    public static function blockMany(array $cidrs, string $note = '', bool $confirmWide = false): array|\WP_Error
    {
        if ($cidrs === [] || count($cidrs) > 50) {
            return new \WP_Error(
                'invalid_batch_size',
                __('Vui lòng chọn từ 1 đến 50 mục mỗi lần thao tác.', 'laca')
            );
        }

        $normalizedList = [];
        foreach ($cidrs as $raw) {
            if (!is_string($raw)) {
                return new \WP_Error('invalid_cidr', __('Định dạng IP/CIDR không hợp lệ.', 'laca'));
            }
            $norm = self::normalizeCidr($raw, true);
            if ($norm === null) {
                return new \WP_Error('invalid_cidr', __('Định dạng IP/CIDR hoặc độ dài prefix không được hỗ trợ.', 'laca'));
            }
            $check = self::validateSafeToBlock($norm, $confirmWide);
            if (is_wp_error($check)) {
                return $check;
            }
            $normalizedList[$norm] = true;
        }

        $candidates = self::deduplicateCidrs(array_keys($normalizedList));
        $current    = self::getBlocklist();
        $now        = time();
        $userId     = get_current_user_id();
        $cleanNote  = substr(sanitize_text_field($note), 0, 120);

        $added   = [];
        $skipped = 0;

        foreach ($candidates as $newCidr) {
            [$newNet, $newPfxStr] = explode('/', $newCidr, 2);
            $newPfx               = (int) $newPfxStr;
            $newV6                = str_contains($newNet, ':');

            // Nếu đã được bao phủ bởi quy tắc hiện có -> bỏ qua
            $alreadyCovered = false;
            foreach (array_keys($current) as $existCidr) {
                [$exNet, $exPfxStr] = explode('/', $existCidr, 2);
                $exPfx              = (int) $exPfxStr;
                if ($newV6 !== str_contains($exNet, ':')) {
                    continue;
                }
                if ($exPfx <= $newPfx && ClientIp::inCidr($newNet, $existCidr)) {
                    $alreadyCovered = true;
                    break;
                }
            }
            if ($alreadyCovered) {
                $skipped++;
                continue;
            }

            // Loại các quy tắc nhỏ hơn nằm bên trong quy tắc mới rộng hơn
            foreach (array_keys($current) as $existCidr) {
                [$exNet, $exPfxStr] = explode('/', $existCidr, 2);
                $exPfx              = (int) $exPfxStr;
                if ($newV6 !== str_contains($exNet, ':')) {
                    continue;
                }
                if ($newPfx < $exPfx && ClientIp::inCidr($exNet, $newCidr)) {
                    unset($current[$existCidr]);
                }
            }

            if (count($current) >= self::MAX_BLOCKLIST) {
                return new \WP_Error(
                    'blocklist_full',
                    sprintf(
                        /* translators: %d: giới hạn số mục trong danh sách chặn */
                        __('Danh sách chặn đã đạt giới hạn tối đa %d mục.', 'laca'),
                        self::MAX_BLOCKLIST
                    )
                );
            }

            $current[$newCidr] = [
                'added' => $now,
                'by'    => $userId,
                'note'  => $cleanNote,
            ];
            $added[] = $newCidr;
        }

        if ($added !== []) {
            self::persistBlocklist($current);
            do_action('laca_traffic_ip_blocked', $added, $userId);
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public static function unblock(string $cidr): bool
    {
        return self::unblockMany([$cidr]) > 0;
    }

    /**
     * Bỏ chặn hàng loạt (tối đa 50 mục/lần).
     *
     * @param string[] $cidrs
     */
    public static function unblockMany(array $cidrs): int
    {
        if ($cidrs === [] || count($cidrs) > 50) {
            return 0;
        }

        $current = self::getBlocklist();
        $removed = [];

        foreach ($cidrs as $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $rawTrim = trim($raw);
            if (isset($current[$rawTrim])) {
                unset($current[$rawTrim]);
                $removed[] = $rawTrim;
                continue;
            }
            $norm = self::normalizeCidr($rawTrim, false);
            if ($norm !== null && isset($current[$norm])) {
                unset($current[$norm]);
                $removed[] = $norm;
            }
        }

        if ($removed !== []) {
            self::persistBlocklist($current);
            do_action('laca_traffic_ip_unblocked', $removed, get_current_user_id());
        }

        return count($removed);
    }

    public static function toggleTrusted(string $ip): bool|\WP_Error
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return new \WP_Error('invalid_ip', __('Địa chỉ IP không hợp lệ.', 'laca'));
        }

        $list = self::trustedIps();
        if (in_array($ip, $list, true)) {
            $list    = array_values(array_diff($list, [$ip]));
            $trusted = false;
        } else {
            if (count($list) >= self::MAX_TRUSTED) {
                return new \WP_Error(
                    'trusted_full',
                    sprintf(
                        /* translators: %d: số lượng IP tin cậy tối đa */
                        __('Danh sách IP tin cậy giới hạn tối đa %d địa chỉ.', 'laca'),
                        self::MAX_TRUSTED
                    )
                );
            }
            $list[]  = $ip;
            $trusted = true;
        }

        update_option(self::TRUSTED_OPTION, $list, 'yes');
        self::$trustedCache = $list;

        return $trusted;
    }

    /**
     * Lưu danh sách IP tin cậy từ textarea cài đặt (tối đa 200 IP).
     *
     * @return string[]
     */
    public static function saveTrustedIps(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $valid = [];

        foreach ($lines as $line) {
            $ip = trim(sanitize_text_field($line));
            if ($ip === '') {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $valid[] = $ip;
            }
            if (count($valid) >= self::MAX_TRUSTED) {
                break;
            }
        }

        $valid = array_values(array_unique($valid));
        update_option(self::TRUSTED_OPTION, $valid, 'yes');
        self::$trustedCache = $valid;

        return $valid;
    }

    /**
     * @param array<string, array{added:int, by:int, note:string}> $list
     */
    private static function persistBlocklist(array $list): void
    {
        update_option(self::BLOCKLIST_OPTION, $list, 'yes');
        self::$blocklistCache = $list;
        self::$parsedIndex    = null;

        if ((int) (TrafficSettings::settings()['htaccess_sync'] ?? 0) === 1) {
            self::syncHtaccess();
        }
    }

    /**
     * Kiểm tra môi trường có hỗ trợ đồng bộ `.htaccess` (Apache + file `.htaccess` ghi được).
     */
    public static function canSyncHtaccess(): bool
    {
        $isApache = !empty($GLOBALS['is_apache'])
            || (function_exists('got_mod_rewrite') && got_mod_rewrite());

        if (!$isApache) {
            return false;
        }

        $file = ABSPATH . '.htaccess';

        return is_file($file) && !is_link($file) && is_readable($file) && is_writable($file);
    }

    /**
     * Đồng bộ blocklist vào `.htaccess` qua `insert_with_markers()` kèm sao lưu (giữ tối đa 5 bản) và khôi phục khi lỗi.
     */
    public static function syncHtaccess(): true|\WP_Error
    {
        $file      = ABSPATH . '.htaccess';
        $syncOn    = (int) (TrafficSettings::settings()['htaccess_sync'] ?? 0) === 1;
        $blocklist = self::getBlocklist();

        if (!is_file($file) || is_link($file) || !is_readable($file) || !is_writable($file)) {
            if (!$syncOn) {
                return true;
            }
            return new \WP_Error('htaccess_unwritable', __('Không thể đọc/ghi file .htaccess tại thư mục gốc WordPress.', 'laca'));
        }

        $oldContent = @file_get_contents($file);
        if ($oldContent === false || strlen($oldContent) > 2097152) {
            return new \WP_Error('htaccess_invalid', __('File .htaccess không đọc được hoặc vượt quá giới hạn an toàn 2 MB.', 'laca'));
        }

        // Nếu tắt sync và file vốn không có marker LACA SECURITY -> không cần ghi
        if (!$syncOn && !str_contains($oldContent, '# BEGIN ' . self::HTACCESS_MARKER)) {
            return true;
        }

        $backup = $file . '.laca-backup-' . gmdate('Ymd-His') . '-' . wp_generate_password(8, false, false);
        if (!@copy($file, $backup) || @file_get_contents($backup) !== $oldContent) {
            return new \WP_Error('htaccess_backup_failed', __('Sao lưu .htaccess thất bại; đã huỷ thao tác ghi để đảm bảo an toàn.', 'laca'));
        }

        self::pruneOwnBackups();

        $lines = [];
        if ($syncOn && $blocklist !== []) {
            $lines[] = '<IfModule mod_authz_core.c>';
            $lines[] = '    <RequireAll>';
            $lines[] = '        Require all granted';
            foreach (array_keys($blocklist) as $cidr) {
                $ruleVal = (string) preg_replace('~/(?:32|128)$~', '', $cidr);
                if (preg_match('/^[0-9a-fA-F:.\/]+$/D', $ruleVal)) {
                    $lines[] = '        Require not ip ' . $ruleVal;
                }
            }
            $lines[] = '    </RequireAll>';
            $lines[] = '</IfModule>';
        }

        require_once ABSPATH . 'wp-admin/includes/misc.php';
        $ok    = insert_with_markers($file, self::HTACCESS_MARKER, $lines);
        $after = @file_get_contents($file);

        $verified = $ok
            && is_string($after)
            && str_contains($after, '# BEGIN ' . self::HTACCESS_MARKER)
            && str_contains($after, '# END ' . self::HTACCESS_MARKER)
            && ($lines === [] || (str_contains($after, '<RequireAll>') && str_contains($after, '</RequireAll>')));

        if (!$verified) {
            @copy($backup, $file);
            set_transient(
                'laca_tm_htaccess_error',
                __('Đồng bộ .htaccess thất bại; hệ thống đã tự động khôi phục từ bản sao lưu.', 'laca'),
                5 * MINUTE_IN_SECONDS
            );
            return new \WP_Error('htaccess_verify_failed', __('Không xác minh được nội dung .htaccess sau khi ghi; đã khôi phục từ bản sao lưu.', 'laca'));
        }

        delete_transient('laca_tm_htaccess_error');
        return true;
    }

    /**
     * Giữ tối đa 5 bản sao lưu `.htaccess.laca-backup-*` mới nhất do chính tính năng này tạo.
     */
    private static function pruneOwnBackups(): void
    {
        $root = realpath(ABSPATH);
        if ($root === false) {
            return;
        }

        $paths = glob($root . DIRECTORY_SEPARATOR . '.htaccess.laca-backup-*');
        if (!is_array($paths) || count($paths) <= 5) {
            return;
        }

        $valid = [];
        foreach ($paths as $path) {
            $name = basename($path);
            if (!preg_match('/^\.htaccess\.laca-backup-\d{8}-\d{6}-[A-Za-z0-9]{8}$/D', $name)) {
                continue;
            }
            if (is_link($path) || !is_file($path) || realpath(dirname($path)) !== $root) {
                continue;
            }
            $valid[] = [
                'path' => $path,
                'time' => (int) @filemtime($path),
                'name' => $name,
            ];
        }

        if (count($valid) <= 5) {
            return;
        }

        usort($valid, static fn(array $a, array $b): int => ($b['time'] <=> $a['time']) ?: strcmp($b['name'], $a['name']));

        foreach (array_slice($valid, 5) as $old) {
            if (!is_link($old['path']) && is_file($old['path'])) {
                wp_delete_file($old['path']);
            }
        }
    }

    /**
     * Kiểm tra có dữ liệu từ plugin Lamvt WP Security Monitor để nhập hay không (CHỈ ĐỌC).
     */
    public static function canImportFromPlugin(): bool
    {
        if (get_option('lbm_trusted_ips', null) !== null) {
            return true;
        }

        $file = ABSPATH . '.htaccess';
        if (!is_file($file) || is_link($file) || !is_readable($file)) {
            return false;
        }
        $size = @filesize($file);
        if ($size === false || $size > 2097152) {
            return false;
        }
        $contents = @file_get_contents($file);

        return is_string($contents)
            && (bool) preg_match('/^\s*# BEGIN LAMVT SECURITY\s*$(.*?)^\s*# END LAMVT SECURITY\s*$/ms', $contents);
    }

    /**
     * Nhập danh sách chặn & IP tin cậy từ plugin Lamvt Security (CHỈ ĐỌC, không sửa dữ liệu plugin).
     *
     * @return array{imported_blocks:int, imported_trusted:int, skipped:int}
     */
    public static function importFromPlugin(int $userId = 0): array
    {
        $importedTrusted = 0;
        $importedBlocks  = 0;
        $skipped         = 0;

        // 1. Nhập trusted IPs trước để validateSafeToBlock bảo vệ luôn các IP này
        $lbmTrusted = get_option('lbm_trusted_ips', []);
        if (is_array($lbmTrusted) && $lbmTrusted !== []) {
            $trustedList = self::trustedIps();
            foreach ($lbmTrusted as $ip) {
                if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                    continue;
                }
                if (in_array($ip, $trustedList, true)) {
                    continue;
                }
                if (count($trustedList) >= self::MAX_TRUSTED) {
                    break;
                }
                $trustedList[] = $ip;
                $importedTrusted++;
            }
            if ($importedTrusted > 0) {
                update_option(self::TRUSTED_OPTION, $trustedList, 'yes');
                self::$trustedCache = $trustedList;
            }
        }

        // 2. Đọc khối # BEGIN LAMVT SECURITY trong .htaccess
        $file = ABSPATH . '.htaccess';
        if (is_file($file) && !is_link($file) && is_readable($file)) {
            $size     = @filesize($file);
            $contents = ($size !== false && $size <= 2097152) ? @file_get_contents($file) : false;
            if (
                is_string($contents)
                && preg_match('/^\s*# BEGIN LAMVT SECURITY\s*$(.*?)^\s*# END LAMVT SECURITY\s*$/ms', $contents, $m)
            ) {
                $rawCidrs = [];
                foreach (preg_split('/\R/', $m[1]) ?: [] as $line) {
                    if (preg_match('/^\s*Require\s+not\s+ip\s+([^#\s]+)\s*(?:#.*)?$/i', $line, $hit)) {
                        $norm = self::normalizeCidr($hit[1], false);
                        if ($norm !== null) {
                            $rawCidrs[] = $norm;
                        } else {
                            $skipped++;
                        }
                    }
                }

                $deduped = self::deduplicateCidrs($rawCidrs);
                $current = self::getBlocklist();
                $now     = time();
                $added   = [];

                foreach ($deduped as $cidr) {
                    if (isset($current[$cidr])) {
                        $skipped++;
                        continue;
                    }
                    if (is_wp_error(self::validateSafeToBlock($cidr, true))) {
                        $skipped++;
                        continue;
                    }
                    if (count($current) >= self::MAX_BLOCKLIST) {
                        $skipped++;
                        continue;
                    }
                    $current[$cidr] = [
                        'added' => $now,
                        'by'    => $userId,
                        'note'  => 'lamvt-import',
                    ];
                    $added[] = $cidr;
                    $importedBlocks++;
                }

                if ($added !== []) {
                    self::persistBlocklist($current);
                    do_action('laca_traffic_ip_blocked', $added, $userId);
                }
            }
        }

        return [
            'imported_blocks'  => $importedBlocks,
            'imported_trusted' => $importedTrusted,
            'skipped'          => $skipped,
        ];
    }
}
