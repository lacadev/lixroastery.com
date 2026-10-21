<?php

namespace App\Features\LacaSeo\ImportExport;

use App\Features\LacaSeo\Core\Modules;
use App\Features\LacaSeo\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Imports Laca SEO settings from JSON, with safety backup, domain replacement, and site credential preservation.
 */
class Importer
{
    public const BACKUPS_OPTION = 'laca_seo_backups';
    public const MAX_BACKUPS = 5;

    /**
     * Create an automatic snapshot backup of current settings before importing.
     */
    public static function createBackup(string $reason = 'Trước khi nhập JSON'): void
    {
        $current = [
            'general'          => Settings::getGroup('general'),
            'titles'           => Settings::getGroup('titles'),
            'sitemap'          => get_option('laca_seo_sitemap', []),
            'instant_indexing' => get_option('laca_seo_instant_indexing', []),
            'modules'          => Modules::getActive(),
        ];

        $backups = get_option(self::BACKUPS_OPTION, []);
        if (!is_array($backups)) {
            $backups = [];
        }

        $snapshot = [
            'id'        => 'backup_' . time(),
            'time'      => current_time('mysql'),
            'reason'    => $reason,
            'data'      => $current,
        ];

        array_unshift($backups, $snapshot);
        $backups = array_slice($backups, 0, self::MAX_BACKUPS);

        update_option(self::BACKUPS_OPTION, $backups, false);
    }

    /**
     * Restore a specific backup by ID.
     */
    public static function restoreBackup(string $backupId): bool
    {
        $backups = get_option(self::BACKUPS_OPTION, []);
        if (!is_array($backups)) {
            return false;
        }

        foreach ($backups as $b) {
            if (($b['id'] ?? '') === $backupId && !empty($b['data'])) {
                self::createBackup('Trước khi khôi phục bản sao lưu ' . $backupId);

                $data = $b['data'];
                if (isset($data['general'])) {
                    Settings::updateGroup('general', $data['general']);
                }
                if (isset($data['titles'])) {
                    Settings::updateGroup('titles', $data['titles']);
                }
                if (isset($data['sitemap'])) {
                    update_option('laca_seo_sitemap', $data['sitemap'], false);
                }
                if (isset($data['instant_indexing'])) {
                    update_option('laca_seo_instant_indexing', $data['instant_indexing'], false);
                }
                if (isset($data['modules'])) {
                    Modules::setActive($data['modules']);
                }

                Settings::clearCache();
                return true;
            }
        }

        return false;
    }

    /**
     * Process JSON import payload.
     *
     * @param array $payload
     * @param bool $replaceDomain Replace source site_url with current site_url
     * @param bool $preserveSiteCredentials Keep current site Google Verify and GA IDs
     * @return array{success:bool,message:string}
     */
    public static function importJson(array $payload, bool $replaceDomain = true, bool $preserveSiteCredentials = true): array
    {
        if (empty($payload['plugin']) || $payload['plugin'] !== 'laca-seo' || empty($payload['data'])) {
            return [
                'success' => false,
                'message' => 'Tệp JSON không hợp lệ hoặc không phải định dạng của Laca SEO.',
            ];
        }

        // 1. Create safety backup
        self::createBackup('Trước khi nhập tệp JSON');

        $sourceUrl = $payload['site_url'] ?? '';
        $currentUrl = get_home_url();

        $data = $payload['data'];

        // Preserve credentials if requested
        $preserved = [];
        if ($preserveSiteCredentials) {
            $currentGeneral = Settings::getGroup('general');
            $preserved = [
                'google_verify'       => $currentGeneral['google_verify'] ?? '',
                'bing_verify'         => $currentGeneral['bing_verify'] ?? '',
                'baidu_verify'        => $currentGeneral['baidu_verify'] ?? '',
                'yandex_verify'       => $currentGeneral['yandex_verify'] ?? '',
                'pinterest_verify'    => $currentGeneral['pinterest_verify'] ?? '',
                'norton_verify'       => $currentGeneral['norton_verify'] ?? '',
                'ga4_measurement_id'  => $currentGeneral['ga4_measurement_id'] ?? '',
                'gtm_container_id'    => $currentGeneral['gtm_container_id'] ?? '',
            ];
        }

        // Replace old domain strings if requested
        if ($replaceDomain && !empty($sourceUrl) && $sourceUrl !== $currentUrl) {
            $data = self::deepReplaceString($sourceUrl, $currentUrl, $data);
        }

        $registryMap = Settings::getRegistryMap();

        // Import General
        if (!empty($data['general']) && is_array($data['general'])) {
            $cleanGeneral = Settings::getGroup('general');
            foreach ($data['general'] as $k => $v) {
                if (isset($registryMap['general'][$k])) {
                    $cleanGeneral[$k] = $registryMap['general'][$k]->sanitize($v);
                } else {
                    $cleanGeneral[$k] = $v;
                }
            }
            if ($preserveSiteCredentials) {
                foreach ($preserved as $pk => $pv) {
                    if ($pv !== '') {
                        $cleanGeneral[$pk] = $pv;
                    }
                }
            }
            Settings::updateGroup('general', $cleanGeneral);
        }

        // Import Titles
        if (!empty($data['titles']) && is_array($data['titles'])) {
            $cleanTitles = Settings::getGroup('titles');
            foreach ($data['titles'] as $k => $v) {
                if (isset($registryMap['titles'][$k])) {
                    $cleanTitles[$k] = $registryMap['titles'][$k]->sanitize($v);
                } else {
                    $cleanTitles[$k] = $v;
                }
            }
            Settings::updateGroup('titles', $cleanTitles);
        }

        // Import Sitemap & Instant Indexing
        if (!empty($data['sitemap']) && is_array($data['sitemap'])) {
            update_option('laca_seo_sitemap', $data['sitemap'], false);
        }
        if (!empty($data['instant_indexing']) && is_array($data['instant_indexing'])) {
            update_option('laca_seo_instant_indexing', $data['instant_indexing'], false);
        }

        // Import Modules
        if (!empty($data['modules']) && is_array($data['modules'])) {
            Modules::setActive($data['modules']);
        }

        Settings::clearCache();

        return [
            'success' => true,
            'message' => 'Đã nhập cấu hình Laca SEO thành công!',
        ];
    }

    private static function deepReplaceString(string $search, string $replace, mixed $data): mixed
    {
        if (is_string($data)) {
            return str_replace($search, $replace, $data);
        }

        if (is_array($data)) {
            $res = [];
            foreach ($data as $k => $v) {
                $res[$k] = self::deepReplaceString($search, $replace, $v);
            }
            return $res;
        }

        return $data;
    }
}
