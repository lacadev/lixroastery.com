<?php

use App\Features\LacaSeo\Admin\Renderer;
use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Fields\InstantIndexingFields;

defined('ABSPATH') || exit;

$tabs = [
    'settings' => 'Cài đặt IndexNow',
    'submit'   => 'Gửi URL thủ công',
    'history'  => 'Lịch sử gửi (100 lần gần nhất)',
];

$active_tab = sanitize_key($_GET['tab'] ?? 'settings');
if (!array_key_exists($active_tab, $tabs)) {
    $active_tab = 'settings';
}

$all_fields = InstantIndexingFields::get();
$tab_fields = $all_fields['settings'] ?? [];
$current_options = Settings::getGroup('instant_indexing');

// Auto-generate key if empty
if (empty($current_options['indexnow_api_key'])) {
    $current_options['indexnow_api_key'] = str_replace('-', '', wp_generate_uuid4());
    Settings::updateGroup('instant_indexing', $current_options);
}
$api_key = $current_options['indexnow_api_key'];
$key_url = home_url('/' . $api_key . '.txt');

?>
<div class="laca-seo-settings-layout">
    <!-- Notice box pointing to IndexNow key location -->
    <div class="laca-seo-sitemap-banner laca-seo-sitemap-banner--green">
        <div>
            <strong>Tệp xác thực IndexNow API:</strong>
            <a href="<?php echo esc_url($key_url); ?>" target="_blank" class="laca-seo-sitemap-link"><?php echo esc_html($key_url); ?></a>
        </div>
        <div>
            <a href="<?php echo esc_url($key_url); ?>" target="_blank" class="button button-secondary">
                <span class="dashicons dashicons-external" aria-hidden="true"></span> Kiểm tra tệp Key
            </a>
        </div>
    </div>

    <div class="laca-seo-settings-body">
        <!-- Vertical Tab Navigation -->
        <aside class="laca-seo-tabs-sidebar" aria-label="Tabs Instant Indexing">
            <ul class="laca-seo-tabs-list">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <li class="laca-seo-tabs-list__item">
                        <a
                            href="<?php echo esc_url(add_query_arg(['page' => 'laca-seo', 'view' => 'instant-indexing', 'tab' => $tab_key], admin_url('admin.php'))); ?>"
                            class="laca-seo-tabs-list__link<?php echo ($active_tab === $tab_key) ? ' is-active' : ''; ?>"
                        >
                            <?php echo esc_html($tab_label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Tab Content -->
        <section class="laca-seo-tabs-content">
            <?php if ($active_tab === 'settings'): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="laca-seo-form">
                    <?php wp_nonce_field('laca_seo_save_action', 'laca_seo_nonce'); ?>
                    <input type="hidden" name="action" value="laca_seo_save">
                    <input type="hidden" name="option_group" value="instant_indexing">
                    <input type="hidden" name="tab" value="settings">
                    <input type="hidden" name="view" value="instant-indexing">

                    <div class="laca-seo-card">
                        <div class="laca-seo-card__header">
                            <h2 class="laca-seo-card__title">Cài đặt Index tức thì (IndexNow)</h2>
                        </div>

                        <div class="laca-seo-fields-container">
                            <?php foreach ($tab_fields as $field): ?>
                                <?php
                                $val = $current_options[$field->getId()] ?? $field->getDefault();
                                Renderer::renderFieldRow($field, $val);
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sticky bottom actions -->
                    <div class="laca-seo-sticky-footer">
                        <div class="laca-seo-sticky-footer__inner">
                            <button type="submit" class="button button-primary button-hero laca-seo-save-btn">
                                <span class="dashicons dashicons-saved"></span> Lưu thay đổi
                            </button>
                        </div>
                    </div>
                </form>
            <?php elseif ($active_tab === 'submit'): ?>
                <div class="laca-seo-card">
                    <div class="laca-seo-card__header">
                        <h2 class="laca-seo-card__title">Gửi URL thủ công tới IndexNow</h2>
                        <p class="laca-seo-card__subtitle">Nhập một hoặc nhiều URL (mỗi dòng một URL) để gửi thông báo trực tiếp tới các công cụ tìm kiếm.</p>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('laca_seo_manual_indexnow', 'nonce'); ?>
                        <input type="hidden" name="action" value="laca_seo_manual_indexnow">

                        <p>
                            <label for="indexnow_urls"><strong>Danh sách URL:</strong></label><br>
                            <textarea name="urls" id="indexnow_urls" rows="8" class="large-text code" placeholder="https://example.com/bai-viet-moi/"></textarea>
                        </p>

                        <p>
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-upload"></span> Gửi tới IndexNow
                            </button>
                        </p>
                    </form>
                </div>
            <?php elseif ($active_tab === 'history'): ?>
                <div class="laca-seo-card">
                    <div class="laca-seo-card__header">
                        <h2 class="laca-seo-card__title">Lịch sử gửi IndexNow</h2>
                        <p class="laca-seo-card__subtitle">Ghi nhận 100 lần gửi gần nhất.</p>
                    </div>

                    <?php
                    $logs = get_option('laca_seo_indexnow_log', []);
                    ?>
                    <?php if (empty($logs)): ?>
                        <p>Chưa có lịch sử gửi nào.</p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Thời gian</th>
                                    <th>URL</th>
                                    <th>Trạng thái HTTP</th>
                                    <th>Loại gửi</th>
                                    <th>Thông báo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($logs) as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(wp_date('Y-m-d H:i:s', $log['time'] ?? time())); ?></td>
                                        <td><code><?php echo esc_html($log['url'] ?? ''); ?></code></td>
                                        <td>
                                            <?php $st = (int) ($log['status'] ?? 0); ?>
                                            <span class="laca-badge <?php echo in_array($st, [200, 202], true) ? 'laca-badge--success' : ($st === 0 ? 'laca-badge--neutral' : 'laca-badge--danger'); ?>">
                                                <?php echo esc_html((string) $st); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($log['manual_submission']) ? 'Thủ công' : 'Tự động'; ?></td>
                                        <td><?php echo esc_html($log['message'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
