<?php

namespace App\Widgets;

/**
 * BlockSyncWidget
 *
 * WordPress Dashboard Widget hiển thị activity log của Block Sync.
 * Hiển thị các block đã được nhận / cập nhật từ lacadev.com.
 */
class BlockSyncWidget
{
    private const LOG_OPTION  = 'laca_block_activity_log';
    private const INST_OPTION = 'laca_blocks_installed';

    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'register']);
    }

    public function register(): void
    {
        wp_add_dashboard_widget(
            'laca_block_sync_widget',
            '📦 LacaDev Block Updates',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $log       = get_option(self::LOG_OPTION, []);
        $installed = get_option(self::INST_OPTION, []);
        $count     = count($installed);
        ?>
        <div>

            <!-- Stats bar -->
            <div class="laca-sync-banner">
                <div class="laca-sync-banner-stat">
                    <div class="laca-sync-banner-number"><?php echo esc_html($count); ?></div>
                    <div class="laca-sync-banner-label">Blocks đã cài</div>
                </div>
                <div class="laca-sync-banner-stat">
                    <div class="laca-sync-banner-number"><?php echo count($log); ?></div>
                    <div class="laca-sync-banner-label">Logs gần đây</div>
                </div>
            </div>

            <!-- Log list -->
            <?php if (empty($log)): ?>
                <p class="laca-log-empty">
                    📭 Chưa có block nào được sync.<br>
                    <small>Push blocks từ lacadev.com để bắt đầu.</small>
                </p>
            <?php else: ?>
                <ul class="laca-sync-list">
                    <?php foreach (array_slice($log, 0, 10) as $entry): ?>
                    <li class="laca-sync-item">
                        <span class="laca-sync-time">
                            <?php echo esc_html($this->formatTime($entry['time'])); ?>
                        </span>
                        <span class="laca-sync-msg">
                            <?php echo wp_kses($entry['message'], ['strong' => []]); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (count($log) > 10): ?>
                    <p class="laca-scan-info">
                        + <?php echo count($log) - 10; ?> entries khác
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Installed blocks summary -->
            <?php if (!empty($installed)): ?>
                <details class="laca-mt-3">
                    <summary class="lcf-cursor-pointer laca-note-date">
                        Xem tất cả blocks đã cài (<?php echo esc_html($count); ?>)
                    </summary>
                    <ul class="laca-sync-tag-list">
                        <?php foreach ($installed as $name => $version): ?>
                        <li class="laca-sync-tag">
                            <?php echo esc_html($name); ?> <span><?php echo esc_html($version); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    private function formatTime(string $mysqlTime): string
    {
        $timestamp = strtotime($mysqlTime);
        if (!$timestamp) {
            return $mysqlTime;
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Vừa xong';
        }
        if ($diff < 3600) {
            return round($diff / 60) . ' phút trước';
        }
        if ($diff < 86400) {
            return round($diff / 3600) . ' giờ trước';
        }

        return date_i18n('d/m H:i', $timestamp);
    }
}
