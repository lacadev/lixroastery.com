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
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">

            <!-- Stats bar -->
            <div style="
                display:flex; gap:16px; padding:12px 16px; margin:-12px -12px 12px;
                background:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%);
                border-radius:4px 4px 0 0;
            ">
                <div style="color:#fff; text-align:center">
                    <div style="font-size:22px; font-weight:700; line-height:1"><?php echo esc_html($count); ?></div>
                    <div style="font-size:11px; opacity:.8; margin-top:2px">Blocks đã cài</div>
                </div>
                <div style="color:#fff; text-align:center">
                    <div style="font-size:22px; font-weight:700; line-height:1"><?php echo count($log); ?></div>
                    <div style="font-size:11px; opacity:.8; margin-top:2px">Logs gần đây</div>
                </div>
            </div>

            <!-- Log list -->
            <?php if (empty($log)): ?>
                <p style="color:#999; font-size:13px; text-align:center; padding:16px 0; margin:0">
                    📭 Chưa có block nào được sync.<br>
                    <small>Push blocks từ lacadev.com để bắt đầu.</small>
                </p>
            <?php else: ?>
                <ul style="margin:0; padding:0; list-style:none; max-height:240px; overflow-y:auto">
                    <?php foreach (array_slice($log, 0, 10) as $entry): ?>
                    <li style="
                        padding:8px 0;
                        border-bottom:1px solid #f0f0f0;
                        font-size:12px;
                        display:flex;
                        gap:10px;
                        align-items:flex-start;
                    ">
                        <span style="color:#888; white-space:nowrap; min-width:80px">
                            <?php echo esc_html($this->formatTime($entry['time'])); ?>
                        </span>
                        <span style="line-height:1.5">
                            <?php echo wp_kses($entry['message'], ['strong' => []]); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (count($log) > 10): ?>
                    <p style="text-align:center; color:#888; font-size:11px; margin:8px 0 0">
                        + <?php echo count($log) - 10; ?> entries khác
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Installed blocks summary -->
            <?php if (!empty($installed)): ?>
                <details style="margin-top:12px">
                    <summary style="cursor:pointer; font-size:12px; color:#666; padding:4px 0">
                        Xem tất cả blocks đã cài (<?php echo esc_html($count); ?>)
                    </summary>
                    <ul style="
                        margin:8px 0 0; padding:0; list-style:none;
                        display:flex; flex-wrap:wrap; gap:4px
                    ">
                        <?php foreach ($installed as $name => $version): ?>
                        <li style="
                            background:#f0fdf4; color:#166534;
                            padding:2px 8px; border-radius:12px;
                            font-size:11px; font-family:monospace;
                            border:1px solid #bbf7d0;
                        ">
                            <?php echo esc_html($name); ?> <span style="opacity:.7"><?php echo esc_html($version); ?></span>
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
