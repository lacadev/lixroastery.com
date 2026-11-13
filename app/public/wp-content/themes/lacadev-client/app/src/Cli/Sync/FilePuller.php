<?php

namespace App\Cli\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FilePuller
 *
 * Thực hiện kéo các tập tin thay đổi từ production về local:
 * - Kiểm tra dirty state của git trước khi kéo
 * - Hỗ trợ kéo từng path hoặc toàn bộ danh sách files.paths
 * - Cấm xóa mặc định, chỉ xóa khi có cờ --delete và xác nhận rõ ràng
 * - Cảnh báo nếu thay đổi rơi vào parent theme lacadev-client
 * - Gợi ý commit message theo chuẩn Conventional Commits
 */
class FilePuller
{
    /**
     * Kéo các tập tin từ production về local
     *
     * @param array{
     *   path?: string,
     *   delete?: bool,
     *   force_dirty?: bool,
     *   yes?: bool
     * } $options
     * @return array{
     *   success: bool,
     *   pulled_paths: array<string>,
     *   git_status: string,
     *   suggested_commit: string,
     *   warnings: array<string>,
     *   errors: array<string>
     * }
     */
    public static function pull(array $options = []): array
    {
        $prod = SyncConfig::getProduction();
        $filesCfg = SyncConfig::getFilesConfig();

        $sshTarget = $prod['ssh'] ?? '';
        $remotePath = rtrim($prod['path'] ?? '', '/');

        $warnings = [];
        $errors = [];
        $pulledPaths = [];

        if (empty($sshTarget) || empty($remotePath)) {
            return [
                'success'          => false,
                'pulled_paths'     => [],
                'git_status'       => '',
                'suggested_commit' => '',
                'warnings'         => [],
                'errors'           => [__('Thiếu cấu hình SSH production trong laca-sync.yml.', 'laca')],
            ];
        }

        // 1. Kiểm tra dirty files trong local
        $dirtyFiles = DriftDetector::getLocalDirtyFiles();
        $forceDirty = !empty($options['force_dirty']);

        $targetPaths = [];
        if (!empty($options['path'])) {
            $targetPaths[] = trim($options['path'], '/');
        } else {
            $targetPaths = (array) ($filesCfg['paths'] ?? ['wp-content/themes', 'wp-content/plugins', 'wp-content/mu-plugins']);
        }

        // Kiểm tra xem có file dirty nào nằm trong vùng sắp bị ghi đè không
        if (!empty($dirtyFiles) && !$forceDirty) {
            $conflicting = [];
            foreach ($dirtyFiles as $df) {
                foreach ($targetPaths as $tp) {
                    if (str_contains($df, $tp)) {
                        $conflicting[] = $df;
                        break;
                    }
                }
            }

            if (!empty($conflicting)) {
                return [
                    'success'          => false,
                    'pulled_paths'     => [],
                    'git_status'       => '',
                    'suggested_commit' => '',
                    'warnings'         => [],
                    'errors'           => [
                        sprintf(
                            __("Từ chối chạy vì có %d file local đang sửa đổi chưa commit:\n%s\nHãy commit thay đổi trước hoặc dùng cờ --force-dirty để ép chạy.", 'laca'),
                            count($conflicting),
                            implode("\n", array_map(fn ($f) => '   - ' . $f, array_slice($conflicting, 0, 10)))
                        ),
                    ],
                ];
            }
        }

        // 2. Chuẩn bị exclude và SSH parameters
        $excludes = (array) ($filesCfg['exclude'] ?? ['node_modules', '.git', '*.log', 'cache', 'wflogs', 'upgrade', 'uploads']);
        $tmpExclude = tempnam(sys_get_temp_dir(), 'laca_sync_pull_exc_');
        file_put_contents($tmpExclude, implode("\n", $excludes) . "\n");

        $parsedSsh = SyncConfig::parseSshTarget($sshTarget);
        $sshPortArg = ($parsedSsh['port'] !== 22) ? sprintf('-e "ssh -p %d -o BatchMode=yes"', $parsedSsh['port']) : '-e "ssh -o BatchMode=yes"';
        $remoteHostStr = $parsedSsh['user'] ? $parsedSsh['user'] . '@' . $parsedSsh['host'] : $parsedSsh['host'];

        $deleteFlag = !empty($options['delete']) ? ' --delete' : '';

        // 3. Thực thi rsync từng path
        foreach ($targetPaths as $relPath) {
            $relPath = trim($relPath, '/');
            $localFullPath = ABSPATH . $relPath;

            if (!is_dir($localFullPath)) {
                wp_mkdir_p($localFullPath);
            }

            $cmd = sprintf(
                'rsync -rlc%s --exclude-from=%s %s %s:%s/%s/ %s/',
                $deleteFlag,
                escapeshellarg($tmpExclude),
                $sshPortArg,
                escapeshellarg($remoteHostStr),
                escapeshellarg($remotePath),
                escapeshellarg($relPath),
                escapeshellarg($localFullPath)
            );

            $res = ShellProcess::run(['bash', '-c', $cmd], null, ABSPATH, null, 300);

            if ($res['exit_code'] !== 0) {
                $errors[] = sprintf(__('Lỗi rsync %s: %s', 'laca'), $relPath, trim($res['stderr'] ?: $res['stdout']));
            } else {
                $pulledPaths[] = $relPath;
            }

            // Kiểm tra cảnh báo nếu path là parent theme lacadev-client
            if (str_contains($relPath, 'themes/lacadev-client')) {
                $warnings[] = __('CHÚ Ý: lacadev-client là theme cha dùng chung cho nhiều site. Nếu có thay đổi, hãy sửa tại bản gốc ~/Documents/wordpress/lacadev-client rồi đồng bộ sang các site khác, không nên chỉ sửa ở site này.', 'laca');
            }
        }

        @unlink($tmpExclude);

        // 4. Lấy git status sau khi kéo
        $gitStatusRes = ShellProcess::run(['git', 'status', '-s'], null, ABSPATH, null, 10);
        $gitStatus = trim($gitStatusRes['stdout']);

        // Gợi ý commit message theo chuẩn Conventional Commits
        $suggestedCommit = "chore(sync): sync production updates into local\n\n- Synced paths: " . implode(', ', $pulledPaths);

        return [
            'success'          => empty($errors),
            'pulled_paths'     => $pulledPaths,
            'git_status'       => $gitStatus,
            'suggested_commit' => $suggestedCommit,
            'warnings'         => array_unique($warnings),
            'errors'           => $errors,
        ];
    }
}

