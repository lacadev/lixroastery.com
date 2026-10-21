<?php

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Modules\NotFoundMonitor\DB;

defined('ABSPATH') || exit;

$notice = '';

// Handle row actions: delete, clear
if (!empty($_GET['action'])) {
    $act = sanitize_key($_GET['action']);
    if ($act === 'delete' && !empty($_GET['id'])) {
        check_admin_referer('laca_seo_404_action');
        DB::deleteLog(absint($_GET['id']));
        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã xoá bản ghi 404.', 'lacadev') . '</p></div>';
    } elseif ($act === 'clear_all') {
        check_admin_referer('laca_seo_404_clear');
        DB::clearLogs();
        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã làm trống toàn bộ nhật ký 404.', 'lacadev') . '</p></div>';
    }
}

// Handle Export CSV (Pro)
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    check_admin_referer('laca_seo_404_export');
    $logs = DB::getLogs(['limit' => 10000]);
    $filename = "laca-seo-404-logs-" . date_i18n('Y-m-d-H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'URI', 'Lượt truy cập', 'Thời gian cuối', 'Referer', 'User Agent']);

    foreach ($logs['logs'] as $l) {
        fputcsv($out, [
            $l['id'],
            $l['uri'],
            $l['times_accessed'],
            $l['accessed'],
            $l['referer'],
            $l['user_agent'],
        ]);
    }
    fclose($out);
    exit;
}

$rmActive = Helper::isRankMathActive();

// Query items
$searchQuery = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
$paged       = max(1, absint($_GET['paged'] ?? 1));
$limit       = 20;

$itemsData = DB::getLogs([
    'search'  => $searchQuery,
    'paged'   => $paged,
    'limit'   => $limit,
    'orderby' => 'accessed',
    'order'   => 'DESC',
]);

$items = $itemsData['logs'];
$total = $itemsData['count'];
$totalPages = ceil($total / $limit);

?>
<div class="laca-seo-subview-wrap">
    <?php if ($rmActive): ?>
        <div class="notice notice-warning inline" style="margin-bottom: 20px;">
            <p><strong>Lưu ý:</strong> Rank Math SEO đang chạy trên website. Nhật ký 404 của Laca SEO chỉ ghi nhận khi Rank Math không active hoặc khi module được kích hoạt.</p>
        </div>
    <?php endif; ?>

    <?php echo $notice; // phpcs:ignore ?>

    <div class="laca-seo-subview-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h2 style="margin:0; font-size: 20px;">Theo dõi lỗi 404 (404 Monitor)</h2>
        <div>
            <?php if ($total > 0): ?>
                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'laca-seo', 'view' => '404-monitor', 'action' => 'clear_all'], admin_url('admin.php')), 'laca_seo_404_clear')); ?>" class="button button-link-delete" onclick="return confirm('Bạn có chắc chắn muốn xoá sạch toàn bộ nhật ký 404 không?');">
                    Xoá toàn bộ nhật ký
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'laca-seo', 'view' => '404-monitor', 'export' => 'csv'], admin_url('admin.php')), 'laca_seo_404_export')); ?>" class="button">
                Xuất file CSV
            </a>
        </div>
    </div>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>URL bị lỗi 404 (URI)</th>
                <th style="width: 100px;">Lượt truy cập</th>
                <th style="width: 140px;">Thời gian cuối</th>
                <th>Nguồn giới thiệu (Referer)</th>
                <th style="width: 160px;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 24px; color: #646970;">
                        Nhật ký lỗi 404 hiện đang trống.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong>#<?php echo esc_html((string) $item['id']); ?></strong></td>
                        <td>
                            <code>/<?php echo esc_html(urldecode($item['uri'])); ?></code>
                        </td>
                        <td><strong><?php echo esc_html(number_format_i18n((int) $item['times_accessed'])); ?></strong></td>
                        <td><small><?php echo esc_html($item['accessed']); ?></small></td>
                        <td>
                            <?php if (!empty($item['referer'])): ?>
                                <small><a href="<?php echo esc_url($item['referer']); ?>" target="_blank" rel="noopener"><?php echo esc_html($item['referer']); ?></a></small>
                            <?php else: ?>
                                <em style="color:#8c8f94;">(Trực tiếp / Không có)</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'redirections', 'action' => 'new', 'url_to' => '', 'sources[0][pattern]' => urldecode($item['uri'])], admin_url('admin.php'))); ?>" class="button button-small">
                                Tạo chuyển hướng
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $item['id']]), 'laca_seo_404_action')); ?>" style="color:#b32d2e; margin-left: 6px;" onclick="return confirm('Xoá dòng này?');">Xoá</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="tablenav bottom" style="margin-top: 12px;">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(esc_html__('%d mục', 'lacadev'), $total); ?></span>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="<?php echo esc_url(add_query_arg(['paged' => $p])); ?>" class="button <?php echo $paged === $p ? 'button-primary' : ''; ?>" style="min-width: 28px;"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
