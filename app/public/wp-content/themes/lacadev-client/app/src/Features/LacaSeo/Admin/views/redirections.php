<?php

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Modules\Redirections\DB;
use App\Features\LacaSeo\Modules\Redirections\Redirection;
use App\Features\LacaSeo\Modules\Redirections\Schedule;
use App\Features\LacaSeo\Modules\Redirections\Export;

defined('ABSPATH') || exit;

// Handle Export action
if (isset($_GET['export']) && in_array($_GET['export'], ['apache', 'nginx'], true)) {
    check_admin_referer('laca_seo_export_redirections');
    $server = sanitize_key($_GET['export']);
    $data = DB::getRedirections(['limit' => 10000, 'status' => 'active']);
    $lines = $server === 'apache' ? Export::apache($data['redirections']) : Export::nginx($data['redirections']);
    $filename = "laca-seo-redirections-{$server}-" . date_i18n('Y-m-d-H-i-s') . ($server === 'apache' ? '.htaccess' : '.conf');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo implode(PHP_EOL, $lines) . PHP_EOL;
    exit;
}

// Handle Form Post (Add / Edit / Bulk actions)
$action = sanitize_key($_REQUEST['action'] ?? '');
$notice = '';

if (!empty($_POST['laca_seo_redir_save'])) {
    check_admin_referer('laca_seo_save_redirection');

    $editId = absint($_POST['redirection_id'] ?? 0);
    $urlTo  = sanitize_text_field(wp_unslash($_POST['url_to'] ?? ''));
    $headerCode = absint($_POST['header_code'] ?? 301);
    $sourcesRaw = $_POST['sources'] ?? [];

    $sources = [];
    if (is_array($sourcesRaw)) {
        foreach ($sourcesRaw as $s) {
            if (!empty($s['pattern'])) {
                $sources[] = [
                    'pattern'    => sanitize_text_field(wp_unslash($s['pattern'])),
                    'comparison' => sanitize_key($s['comparison'] ?? 'exact'),
                    'ignore'     => !empty($s['ignore']) ? 'case' : '',
                ];
            }
        }
    }

    $redirData = [
        'id'          => $editId,
        'url_to'      => $urlTo,
        'header_code' => $headerCode,
        'sources'     => $sources,
        'status'      => sanitize_key($_POST['status'] ?? 'active'),
    ];

    $redir = Redirection::from($redirData);
    $savedId = $redir->save();

    if ($savedId) {
        // Save Pro Schedule dates
        $startDate = sanitize_text_field($_POST['start_date'] ?? '');
        $endDate   = sanitize_text_field($_POST['end_date'] ?? '');
        Schedule::setSchedule($savedId, $startDate, $endDate);

        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã lưu quy tắc chuyển hướng thành công.', 'lacadev') . '</p></div>';
    }
}

// Handle row actions: trash, restore, delete, activate, deactivate
if (!empty($_GET['action']) && !empty($_GET['id'])) {
    check_admin_referer('laca_seo_redir_action');
    $id = absint($_GET['id']);
    $act = sanitize_key($_GET['action']);

    if ($act === 'trash') {
        DB::changeStatus($id, 'trashed');
        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã chuyển vào thùng rác.', 'lacadev') . '</p></div>';
    } elseif ($act === 'restore') {
        DB::changeStatus($id, 'active');
        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã khôi phục quy tắc.', 'lacadev') . '</p></div>';
    } elseif ($act === 'delete') {
        DB::delete($id);
        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã xoá vĩnh viễn quy tắc.', 'lacadev') . '</p></div>';
    } elseif ($act === 'activate') {
        DB::changeStatus($id, 'active');
    } elseif ($act === 'deactivate') {
        DB::changeStatus($id, 'inactive');
    }
}

// Check if Rank Math is active and warn
$rmActive = Helper::isRankMathActive();

// Query items
$currentStatus = sanitize_key($_GET['status'] ?? 'any');
$searchQuery   = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
$paged         = max(1, absint($_GET['paged'] ?? 1));
$limit         = 20;

$itemsData = DB::getRedirections([
    'status'  => $currentStatus,
    'search'  => $searchQuery,
    'paged'   => $paged,
    'limit'   => $limit,
    'orderby' => 'id',
    'order'   => 'DESC',
]);

$items = $itemsData['redirections'];
$total = $itemsData['count'];
$counts = DB::getCounts();
$totalPages = ceil($total / $limit);

// Editing item?
$editItem = null;
if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
    $editItem = DB::getRedirectionById(absint($_GET['id']));
}
$isAddNew = isset($_GET['action']) && $_GET['action'] === 'new';

?>
<div class="laca-seo-subview-wrap">
    <?php if ($rmActive): ?>
        <div class="notice notice-warning inline" style="margin-bottom: 20px;">
            <p><strong>Lưu ý:</strong> Rank Math SEO hiện đang kích hoạt trên trang web. Hệ thống chuyển hướng của Rank Math đang nắm quyền ưu tiên xử lý. Bạn vẫn có thể quản lý và thiết lập dữ liệu tại đây.</p>
        </div>
    <?php endif; ?>

    <?php echo $notice; // phpcs:ignore ?>

    <div class="laca-seo-subview-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h2 style="margin:0; font-size: 20px;">Quản lý Chuyển hướng (Redirections)</h2>
        <div>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'redirections', 'action' => 'new'], admin_url('admin.php'))); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> Thêm chuyển hướng mới
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'laca-seo', 'view' => 'redirections', 'export' => 'apache'], admin_url('admin.php')), 'laca_seo_export_redirections')); ?>" class="button">
                Xuất .htaccess
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'laca-seo', 'view' => 'redirections', 'export' => 'nginx'], admin_url('admin.php')), 'laca_seo_export_redirections')); ?>" class="button">
                Xuất Nginx
            </a>
        </div>
    </div>

    <?php if ($isAddNew || $editItem): ?>
        <?php
        $formSources = $editItem['sources'] ?? [['pattern' => '', 'comparison' => 'exact', 'ignore' => '']];
        $formUrlTo   = $editItem['url_to'] ?? '';
        $formCode    = $editItem['header_code'] ?? 301;
        $formStatus  = $editItem['status'] ?? 'active';
        $formStart   = $editItem ? Schedule::getStartDate($editItem['id']) : '';
        $formEnd     = $editItem ? Schedule::getEndDate($editItem['id']) : '';
        ?>
        <div class="laca-seo-card" style="margin-bottom: 24px;">
            <div class="laca-seo-card__header">
                <h3 class="laca-seo-card__title"><?php echo $editItem ? 'Chỉnh sửa quy tắc chuyển hướng #' . esc_html((string) $editItem['id']) : 'Thêm mới quy tắc chuyển hướng'; ?></h3>
            </div>
            <div class="laca-seo-card__body">
                <form method="post" action="<?php echo esc_url(remove_query_arg(['action', 'id'])); ?>">
                    <?php wp_nonce_field('laca_seo_save_redirection'); ?>
                    <input type="hidden" name="laca_seo_redir_save" value="1">
                    <input type="hidden" name="redirection_id" value="<?php echo esc_attr((string) ($editItem['id'] ?? 0)); ?>">

                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-weight:600; margin-bottom: 6px;">URL nguồn (Source URLs):</label>
                        <?php foreach ($formSources as $sIdx => $sRow): ?>
                            <div style="display:flex; gap: 10px; margin-bottom: 8px; align-items:center;">
                                <input type="text" name="sources[<?php echo $sIdx; ?>][pattern]" value="<?php echo esc_attr($sRow['pattern'] ?? ''); ?>" class="large-text" placeholder="vi-du/duong-dan-cu/" style="flex:1;">
                                <select name="sources[<?php echo $sIdx; ?>][comparison]">
                                    <?php foreach (Helper::choicesComparisonTypes() as $cKey => $cLabel): ?>
                                        <option value="<?php echo esc_attr($cKey); ?>" <?php selected($sRow['comparison'] ?? 'exact', $cKey); ?>><?php echo esc_html($cLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label style="display:inline-flex; align-items:center; gap: 4px; font-size: 13px;">
                                    <input type="checkbox" name="sources[<?php echo $sIdx; ?>][ignore]" value="1" <?php checked(!empty($sRow['ignore'])); ?>> Bỏ qua hoa/thường
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="redir_url_to" style="display:block; font-weight:600; margin-bottom: 6px;">URL đích đến (Destination URL):</label>
                        <input type="text" id="redir_url_to" name="url_to" value="<?php echo esc_attr($formUrlTo); ?>" class="large-text" placeholder="https://example.com/duong-dan-moi/ (hoặc bỏ trống nếu chọn 410/451)">
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label for="redir_header_code" style="display:block; font-weight:600; margin-bottom: 6px;">Loại chuyển hướng:</label>
                            <select id="redir_header_code" name="header_code" style="width:100%;">
                                <?php foreach (Helper::choicesRedirectionTypes() as $code => $label): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected((string) $formCode, (string) $code); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="redir_status" style="display:block; font-weight:600; margin-bottom: 6px;">Trạng thái:</label>
                            <select id="redir_status" name="status" style="width:100%;">
                                <option value="active" <?php selected($formStatus, 'active'); ?>>Đang kích hoạt (Active)</option>
                                <option value="inactive" <?php selected($formStatus, 'inactive'); ?>>Tạm ngừng (Inactive)</option>
                            </select>
                        </div>
                        <div>
                            <label for="redir_start_date" style="display:block; font-weight:600; margin-bottom: 6px;">Kích hoạt từ ngày (Pro):</label>
                            <input type="date" id="redir_start_date" name="start_date" value="<?php echo esc_attr($formStart); ?>" style="width:100%;">
                        </div>
                        <div>
                            <label for="redir_end_date" style="display:block; font-weight:600; margin-bottom: 6px;">Hết hạn vào ngày (Pro):</label>
                            <input type="date" id="redir_end_date" name="end_date" value="<?php echo esc_attr($formEnd); ?>" style="width:100%;">
                        </div>
                    </div>

                    <div style="display:flex; gap: 10px;">
                        <button type="submit" class="button button-primary">Lưu quy tắc chuyển hướng</button>
                        <a href="<?php echo esc_url(remove_query_arg(['action', 'id'])); ?>" class="button button-secondary">Huỷ bỏ</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Status Filter Views -->
    <ul class="subsubsub" style="margin-bottom: 12px;">
        <li><a href="<?php echo esc_url(add_query_arg(['status' => 'any', 'paged' => 1])); ?>" class="<?php echo $currentStatus === 'any' ? 'current' : ''; ?>">Tất cả <span class="count">(<?php echo esc_html((string) $counts['all']); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url(add_query_arg(['status' => 'active', 'paged' => 1])); ?>" class="<?php echo $currentStatus === 'active' ? 'current' : ''; ?>">Đang bật <span class="count">(<?php echo esc_html((string) $counts['active']); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url(add_query_arg(['status' => 'inactive', 'paged' => 1])); ?>" class="<?php echo $currentStatus === 'inactive' ? 'current' : ''; ?>">Tắt <span class="count">(<?php echo esc_html((string) $counts['inactive']); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url(add_query_arg(['status' => 'trashed', 'paged' => 1])); ?>" class="<?php echo $currentStatus === 'trashed' ? 'current' : ''; ?>">Thùng rác <span class="count">(<?php echo esc_html((string) $counts['trashed']); ?>)</span></a></li>
    </ul>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th style="width: 40px;">ID</th>
                <th>URL Nguồn (Sources)</th>
                <th>URL Đích (Destination)</th>
                <th style="width: 90px;">Mã HTTP</th>
                <th style="width: 80px;">Lượt truy cập</th>
                <th style="width: 130px;">Truy cập cuối</th>
                <th style="width: 140px;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 24px; color: #646970;">
                        <?php echo $currentStatus === 'trashed' ? 'Thùng rác trống.' : 'Chưa có quy tắc chuyển hướng nào.'; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $itemSources = $item['sources'] ?? [];
                    ?>
                    <tr>
                        <td><strong>#<?php echo esc_html((string) $item['id']); ?></strong></td>
                        <td>
                            <?php foreach ($itemSources as $src): ?>
                                <div style="margin-bottom: 2px;">
                                    <code><?php echo esc_html($src['pattern'] ?? ''); ?></code>
                                    <span style="font-size:11px; color:#646970;">(<?php echo esc_html($src['comparison'] ?? 'exact'); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php if (!empty($item['url_to'])): ?>
                                <a href="<?php echo esc_url($item['url_to']); ?>" target="_blank" rel="noopener"><?php echo esc_html($item['url_to']); ?></a>
                            <?php else: ?>
                                <em style="color:#8c8f94;">(Không có đích đến)</em>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge" style="background:#e0e0e0; padding:2px 6px; border-radius:3px; font-weight:600;"><?php echo esc_html((string) $item['header_code']); ?></span></td>
                        <td><?php echo esc_html(number_format_i18n((int) $item['hits'])); ?></td>
                        <td><small><?php echo ($item['last_accessed'] && $item['last_accessed'] !== '0000-00-00 00:00:00') ? esc_html($item['last_accessed']) : 'Chưa có'; ?></small></td>
                        <td>
                            <?php if ($item['status'] === 'trashed'): ?>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'restore', 'id' => $item['id']]), 'laca_seo_redir_action')); ?>">Khôi phục</a> |
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $item['id']]), 'laca_seo_redir_action')); ?>" style="color:#b32d2e;">Xoá vĩnh viễn</a>
                            <?php else: ?>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $item['id']])); ?>">Sửa</a> |
                                <?php if ($item['status'] === 'active'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'deactivate', 'id' => $item['id']]), 'laca_seo_redir_action')); ?>">Tắt</a> |
                                <?php else: ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'activate', 'id' => $item['id']]), 'laca_seo_redir_action')); ?>">Bật</a> |
                                <?php endif; ?>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'trash', 'id' => $item['id']]), 'laca_seo_redir_action')); ?>" style="color:#b32d2e;">Xoá</a>
                            <?php endif; ?>
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
