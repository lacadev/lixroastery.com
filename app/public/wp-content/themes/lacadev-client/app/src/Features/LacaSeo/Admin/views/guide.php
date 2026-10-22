<?php

use App\Features\LacaSeo\Guide\GuideData;

defined('ABSPATH') || exit;

$checklist = GuideData::getChecklist();
$handbook  = GuideData::getHandbook();

?>
<div class="laca-seo-guide-wrap">
    <!-- Live Checklist -->
    <div class="laca-seo-card">
        <h2 class="laca-seo-card__title">Checklist kiểm tra tiêu chuẩn SEO kỹ thuật (Tự động)</h2>
        <p>Hệ thống tự động rà soát trạng thái cấu hình của website để cảnh báo các điểm cần hoàn thiện.</p>

        <div class="laca-seo-checklist-table">
        <div class="laca-seo-table-responsive">
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th class="col-status">Trạng thái</th>
                        <th class="col-title">Tiêu chuẩn kỹ thuật</th>
                        <th>Chi tiết hiện tại</th>
                        <th class="col-action">Thao tác nhanh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checklist as $item): ?>
                        <tr>
                            <td class="col-status">
                                <?php if ($item['status']): ?>
                                    <span class="dashicons dashicons-yes-alt laca-seo-icon-status--ok"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning laca-seo-icon-status--warn"></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($item['title']); ?></strong></td>
                            <td><?php echo esc_html($item['desc']); ?></td>
                            <td class="col-action">
                                <a href="<?php echo esc_url($item['action_url']); ?>" class="button button-small button-secondary">
                                    <?php echo esc_html($item['action_label']); ?> &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <!-- SEO Optimization Handbook Accordions -->
    <div class="laca-seo-card">
        <h2 class="laca-seo-card__title">Cẩm nang hướng dẫn tối ưu SEO toàn diện</h2>
        <p>Bấm vào từng mục để xem chi tiết lý do, các bước thực hiện và cách kiểm tra kết quả thực tế.</p>

        <div class="laca-seo-handbook-list">
            <?php foreach ($handbook as $art): ?>
                <details class="laca-seo-handbook-item">
                    <summary>
                        <?php echo esc_html($art['title']); ?>
                    </summary>
                    <div class="laca-seo-handbook-item__content">
                        <p><strong>🎯 Vì sao quan trọng:</strong> <?php echo esc_html($art['why']); ?></p>
                        <p><strong>🛠 Cần làm gì:</strong> <?php echo esc_html($art['what']); ?></p>
                        <p><strong>🔍 Kiểm tra kết quả ở đâu:</strong> <?php echo esc_html($art['verify']); ?></p>
                        <div class="laca-seo-handbook-item__action">
                            <a href="<?php echo esc_url($art['deep_link']); ?>" class="button button-secondary">
                                Mở cài đặt mục này &rarr;
                            </a>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</div>
