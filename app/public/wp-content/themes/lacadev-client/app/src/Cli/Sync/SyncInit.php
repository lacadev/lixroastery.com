<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SyncInit
 *
 * Khởi tạo cấu hình và mẫu công cụ đồng bộ trong repository:
 * - Sao chép laca-sync.example.yml vào repo root
 * - Sao chép .vscode/tasks.json vào repo root
 * - Sao chép .github/workflows/pull-production.yml và deploy.yml.proposed
 * - Cập nhật .gitignore của repo một cách an toàn (append không ghi đè)
 */
class SyncInit
{
    /**
     * Xác định thư mục repo root
     */
    public static function getRepoRoot(): string
    {
        $candidate = realpath(ABSPATH . '../../');
        if ($candidate && is_dir($candidate . '/.git')) {
            return $candidate;
        }

        $candidate2 = realpath(ABSPATH . '../');
        if ($candidate2 && is_dir($candidate2 . '/.git')) {
            return $candidate2;
        }

        return realpath(ABSPATH . '../../') ?: dirname(ABSPATH, 2);
    }

    /**
     * Đường dẫn thư mục sync-templates trong theme
     */
    public static function getTemplatesDir(): string
    {
        return dirname(__DIR__, 4) . '/tools/sync-templates';
    }

    /**
     * Chạy khởi tạo
     *
     * @return array{copied: array<string>, skipped: array<string>, gitignore: bool, errors: array<string>}
     */
    public static function run(): array
    {
        $repoRoot = self::getRepoRoot();
        $templatesDir = self::getTemplatesDir();

        $copied = [];
        $skipped = [];
        $errors = [];

        $mappings = [
            'laca-sync.example.yml'                  => $repoRoot . '/laca-sync.example.yml',
            '.vscode/tasks.json'                     => $repoRoot . '/.vscode/tasks.json',
            '.github/workflows/pull-production.yml'  => $repoRoot . '/.github/workflows/pull-production.yml',
            '.github/workflows/deploy.yml.proposed' => $repoRoot . '/.github/workflows/deploy.yml.proposed',
        ];

        foreach ($mappings as $srcRel => $destPath) {
            $srcPath = $templatesDir . '/' . $srcRel;
            if (!is_file($srcPath)) {
                $errors[] = sprintf(__('Không tìm thấy file mẫu: %s', 'laca'), $srcRel);
                continue;
            }

            if (is_file($destPath)) {
                $skipped[] = $destPath;
                continue;
            }

            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                wp_mkdir_p($destDir);
            }

            if (@copy($srcPath, $destPath)) {
                $copied[] = $destPath;
            } else {
                $errors[] = sprintf(__('Không thể sao chép tới: %s', 'laca'), $destPath);
            }
        }

        // Cập nhật .gitignore
        $gitignorePath = $repoRoot . '/.gitignore';
        $gitignoreAppended = false;

        $neededLines = [
            'laca-sync.yml',
            'app/sql/pulls/',
            'app/sql/backups/',
        ];

        $currentContent = is_file($gitignorePath) ? (string) file_get_contents($gitignorePath) : '';
        $toAppend = [];

        foreach ($neededLines as $line) {
            if (!str_contains($currentContent, $line)) {
                $toAppend[] = $line;
            }
        }

        if (!empty($toAppend)) {
            $appendPayload = "\n# --- Laca Sync (Production -> Local) ---\n" . implode("\n", $toAppend) . "\n";
            if (@file_put_contents($gitignorePath, $appendPayload, FILE_APPEND)) {
                $gitignoreAppended = true;
            }
        }

        return [
            'copied'    => $copied,
            'skipped'   => $skipped,
            'gitignore' => $gitignoreAppended,
            'errors'    => $errors,
        ];
    }
}
