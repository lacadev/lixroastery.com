<?php

namespace App\Features\ClientSupport;

use App\Settings\LacaDevTrackerClient;
use App\Security\RequestSigner;

/**
 * ClientSupportManager
 *
 * Quản trị trung tâm hỗ trợ trên website khách hàng (Client Side):
 * - Nút "Hỗ trợ" trên Admin Bar
 * - Widget Dashboard gửi yêu cầu hỗ trợ & xem lịch sử yêu cầu
 * - Gửi yêu cầu có chữ ký HMAC-SHA256 về Hub (lacadev.com)
 */
class ClientSupportManager
{
    public function init(): void
    {
        add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 100);
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);

        add_action('wp_ajax_laca_client_submit_support_request', [$this, 'ajaxSubmitRequest']);
        add_action('wp_ajax_laca_client_get_support_requests', [$this, 'ajaxGetRequests']);
    }

    /**
     * Thêm nút Hỗ trợ trên Admin Bar
     */
    public function addAdminBarMenu(\WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $adminBar->add_node([
            'id'    => 'laca-client-support',
            'title' => '<span class="ab-icon dashicons dashicons-sos" style="top:2px;"></span><span class="ab-label">' . esc_html__('Hỗ trợ', 'laca') . '</span>',
            'href'  => admin_url('index.php#laca-support-box'),
            'meta'  => [
                'title' => esc_attr__('Gửi yêu cầu hỗ trợ tới đội ngũ kỹ thuật LacaDev', 'laca'),
            ],
        ]);
    }

    /**
     * Thêm Dashboard widget
     */
    public function addDashboardWidget(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        wp_add_dashboard_widget(
            'laca_dashboard_support_widget',
            '🆘 ' . esc_html__('Trung tâm Hỗ trợ LacaDev', 'laca'),
            [$this, 'renderDashboardWidget']
        );
    }

    /**
     * Nạp scripts và styles cần thiết
     */
    public function enqueueScripts(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }

        wp_enqueue_media();
    }

    /**
     * Render giao diện Widget Dashboard
     */
    public function renderDashboardWidget(): void
    {
        $nonce = wp_create_nonce('laca_support_request_nonce');
        ?>
        <div id="laca-support-box" class="laca-support-wrapper" style="font-size:13px;color:#1e293b;">
            <style>
                .laca-support-tabs { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 14px; gap: 4px; }
                .laca-support-tab { padding: 8px 14px; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; }
                .laca-support-tab.active { color: #0284c7; border-bottom-color: #0284c7; }
                .laca-support-pane { display: none; }
                .laca-support-pane.active { display: block; }
                .laca-form-group { margin-bottom: 12px; }
                .laca-form-group label { display: block; font-weight: 600; margin-bottom: 4px; color: #334155; }
                .laca-form-group input[type="text"], .laca-form-group select, .laca-form-group textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; font-size: 13px; }
                .laca-support-badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
                .laca-badge-pending { background: #fee2e2; color: #991b1b; }
                .laca-badge-processing { background: #fef3c7; color: #92400e; }
                .laca-badge-resolved { background: #dcfce7; color: #166534; }
                .laca-req-item { padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; background: #f8fafc; }
            </style>

            <div class="laca-support-tabs">
                <div class="laca-support-tab active" data-tab="create"><?php echo esc_html__('Gửi yêu cầu mới', 'laca'); ?></div>
                <div class="laca-support-tab" data-tab="list" id="laca-tab-list-btn"><?php echo esc_html__('Yêu cầu của tôi', 'laca'); ?></div>
            </div>

            <!-- Tab 1: Form tạo yêu cầu -->
            <div class="laca-support-pane active" id="laca-pane-create">
                <form id="laca-support-form">
                    <input type="hidden" name="action" value="laca_client_submit_support_request">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

                    <div class="laca-form-group">
                        <label><?php echo esc_html__('Tiêu đề yêu cầu', 'laca'); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" required placeholder="<?php echo esc_attr__('Ví dụ: Cần sửa banner trang chủ, lỗi form đặt hàng...', 'laca'); ?>">
                    </div>

                    <div style="display:flex;gap:12px;">
                        <div class="laca-form-group" style="flex:1;">
                            <label><?php echo esc_html__('Loại yêu cầu', 'laca'); ?></label>
                            <select name="category">
                                <option value="bug"><?php echo esc_html__('Báo lỗi website', 'laca'); ?></option>
                                <option value="content"><?php echo esc_html__('Cập nhật nội dung / hình ảnh', 'laca'); ?></option>
                                <option value="feature"><?php echo esc_html__('Đề xuất tính năng mới', 'laca'); ?></option>
                                <option value="other" selected><?php echo esc_html__('Khác', 'laca'); ?></option>
                            </select>
                        </div>
                        <div class="laca-form-group" style="flex:1;">
                            <label><?php echo esc_html__('Mức ưu tiên', 'laca'); ?></label>
                            <select name="priority">
                                <option value="low"><?php echo esc_html__('Thấp (Không gấp)', 'laca'); ?></option>
                                <option value="normal" selected><?php echo esc_html__('Bình thường', 'laca'); ?></option>
                                <option value="high"><?php echo esc_html__('Cao (Cần xử lý sớm)', 'laca'); ?></option>
                                <option value="urgent"><?php echo esc_html__('Khẩn cấp (Website gián đoạn)', 'laca'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="laca-form-group">
                        <label><?php echo esc_html__('Mô tả chi tiết', 'laca'); ?> <span style="color:#ef4444;">*</span></label>
                        <textarea name="description" rows="4" required placeholder="<?php echo esc_attr__('Vui lòng mô tả chi tiết vị trí trang, đường link hoặc các bước gặp lỗi để kỹ thuật hỗ trợ nhanh nhất...', 'laca'); ?>"></textarea>
                    </div>

                    <div class="laca-form-group">
                        <label><?php echo esc_html__('Ảnh đính kèm / Ảnh chụp màn hình', 'laca'); ?></label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="attachment_url" id="laca-attach-url" readonly placeholder="<?php echo esc_attr__('Chưa có ảnh nào được chọn', 'laca'); ?>">
                            <button type="button" class="button" id="laca-upload-btn"><?php echo esc_html__('Chọn từ Media', 'laca'); ?></button>
                            <button type="button" class="button" id="laca-clear-attach-btn" style="display:none;">✕</button>
                        </div>
                        <small style="color:#64748b;font-size:11px;"><?php echo esc_html__('Chỉ gửi liên kết hình ảnh an toàn từ thư viện Media của website.', 'laca'); ?></small>
                    </div>

                    <div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
                        <button type="submit" class="button button-primary" id="laca-submit-btn" style="padding:4px 18px;"><?php echo esc_html__('Gửi yêu cầu hỗ trợ', 'laca'); ?></button>
                        <span id="laca-form-status" style="font-weight:600;"></span>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Danh sách yêu cầu đã gửi -->
            <div class="laca-support-pane" id="laca-pane-list">
                <div id="laca-requests-list"><?php echo esc_html__('Đang tải danh sách...', 'laca'); ?></div>
            </div>
        </div>

        <script>
        (function() {
            var tabs = document.querySelectorAll('.laca-support-tab');
            var panes = document.querySelectorAll('.laca-support-pane');

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    tabs.forEach(function(t) { t.classList.remove('active'); });
                    panes.forEach(function(p) { p.classList.remove('active'); });
                    tab.classList.add('active');
                    var target = document.getElementById('laca-pane-' + tab.dataset.tab);
                    if (target) target.classList.add('active');
                    if (tab.dataset.tab === 'list') {
                        loadMyRequests();
                    }
                });
            });

            // Media uploader
            var uploadBtn = document.getElementById('laca-upload-btn');
            var attachInput = document.getElementById('laca-attach-url');
            var clearBtn = document.getElementById('laca-clear-attach-btn');
            if (uploadBtn) {
                uploadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var mediaUploader = wp.media({
                        title: '<?php echo esc_js(__('Chọn ảnh đính kèm minh hoạ lỗi', 'laca')); ?>',
                        button: { text: '<?php echo esc_js(__('Dùng ảnh này', 'laca')); ?>' },
                        multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        attachInput.value = attachment.url;
                        clearBtn.style.display = 'inline-block';
                    });
                    mediaUploader.open();
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    attachInput.value = '';
                    clearBtn.style.display = 'none';
                });
            }

            // Submit form
            var form = document.getElementById('laca-support-form');
            var statusEl = document.getElementById('laca-form-status');
            var submitBtn = document.getElementById('laca-submit-btn');

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitBtn.disabled = true;
                    statusEl.style.color = '#0284c7';
                    statusEl.innerText = '<?php echo esc_js(__('Đang gửi yêu cầu...', 'laca')); ?>';

                    var formData = new FormData(form);
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        submitBtn.disabled = false;
                        if (data.success) {
                            statusEl.style.color = '#16a34a';
                            statusEl.innerText = '✓ ' + (data.data.message || '<?php echo esc_js(__('Yêu cầu đã được gửi thành công!', 'laca')); ?>');
                            form.reset();
                            clearBtn.style.display = 'none';
                            setTimeout(function() {
                                document.getElementById('laca-tab-list-btn').click();
                            }, 1200);
                        } else {
                            statusEl.style.color = '#dc2626';
                            statusEl.innerText = '✕ ' + (data.data.message || '<?php echo esc_js(__('Có lỗi xảy ra, vui lòng thử lại.', 'laca')); ?>');
                        }
                    })
                    .catch(function(err) {
                        submitBtn.disabled = false;
                        statusEl.style.color = '#dc2626';
                        statusEl.innerText = '✕ ' + err.message;
                    });
                });
            }

            // Load requests
            function loadMyRequests() {
                var listEl = document.getElementById('laca-requests-list');
                listEl.innerHTML = '<p style="color:#64748b;"><?php echo esc_js(__('Đang đồng bộ với trung tâm hỗ trợ...', 'laca')); ?></p>';

                var data = new FormData();
                data.append('action', 'laca_client_get_support_requests');
                data.append('_wpnonce', '<?php echo esc_js($nonce); ?>');

                fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (!res.success || !res.data.requests || res.data.requests.length === 0) {
                        listEl.innerHTML = '<p style="color:#64748b;text-align:center;padding:20px;"><?php echo esc_js(__('Bạn chưa có yêu cầu hỗ trợ nào.', 'laca')); ?></p>';
                        return;
                    }

                    var html = '';
                    res.data.requests.forEach(function(req) {
                        var badgeClass = 'laca-badge-pending';
                        var badgeText = '<?php echo esc_js(__('Chờ xử lý', 'laca')); ?>';
                        if (req.status === 'processing') {
                            badgeClass = 'laca-badge-processing';
                            badgeText = '<?php echo esc_js(__('Đang xử lý', 'laca')); ?>';
                        } else if (req.status === 'resolved') {
                            badgeClass = 'laca-badge-resolved';
                            badgeText = '<?php echo esc_js(__('Đã giải quyết', 'laca')); ?>';
                        }

                        html += '<div class="laca-req-item">';
                        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
                        html += '<strong style="font-size:14px;color:#0f172a;">' + escapeHtml(req.title) + '</strong>';
                        html += '<span class="laca-support-badge ' + badgeClass + '">' + badgeText + '</span>';
                        html += '</div>';
                        html += '<div style="font-size:12px;color:#475569;margin-bottom:6px;">' + escapeHtml(req.description) + '</div>';
                        if (req.attachment_url) {
                            html += '<div style="margin-bottom:6px;"><a href="' + escapeHtml(req.attachment_url) + '" target="_blank" style="font-size:11px;color:#0284c7;">📎 <?php echo esc_js(__('Xem ảnh đính kèm', 'laca')); ?></a></div>';
                        }
                        if (req.admin_reply) {
                            html += '<div style="background:#f0fdf4;border-left:3px solid #16a34a;padding:6px 10px;font-size:12px;color:#166534;margin-top:6px;"><strong><?php echo esc_js(__('Phản hồi từ kỹ thuật: ', 'laca')); ?></strong>' + escapeHtml(req.admin_reply) + '</div>';
                        }
                        html += '<div style="font-size:11px;color:#94a3b8;margin-top:6px;">' + escapeHtml(req.created_at) + '</div>';
                        html += '</div>';
                    });
                    listEl.innerHTML = html;
                })
                .catch(function(err) {
                    listEl.innerHTML = '<p style="color:#dc2626;"><?php echo esc_js(__('Không thể tải danh sách yêu cầu: ', 'laca')); ?>' + err.message + '</p>';
                });
            }

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
        })();
        </script>
        <?php
    }

    /**
     * AJAX Client submit support request
     */
    public function ajaxSubmitRequest(): void
    {
        check_ajax_referer('laca_support_request_nonce', '_wpnonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'laca')], 403);
        }

        $title       = sanitize_text_field((string) ($_POST['title'] ?? ''));
        $description = sanitize_textarea_field((string) ($_POST['description'] ?? ''));
        $category    = sanitize_key((string) ($_POST['category'] ?? 'other'));
        $priority    = sanitize_key((string) ($_POST['priority'] ?? 'normal'));
        $attachUrl   = esc_url_raw((string) ($_POST['attachment_url'] ?? ''));

        if (empty($title) || empty($description)) {
            wp_send_json_error(['message' => __('Vui lòng điền đầy đủ tiêu đề và nội dung mô tả.', 'laca')], 400);
        }

        $currentUser = wp_get_current_user();
        $contactName = $currentUser->display_name ?: $currentUser->user_login;

        $payload = [
            'title'          => $title,
            'description'    => $description,
            'category'       => $category,
            'priority'       => $priority,
            'attachment_url' => $attachUrl,
            'contact_name'   => $contactName,
            'contact_email'  => $currentUser->user_email,
        ];

        $res = $this->sendSignedHubRequest('POST', '/laca/v1/client/requests', $payload);
        if (!$res['success']) {
            wp_send_json_error(['message' => $res['message'] ?: __('Không thể gửi yêu cầu tới máy chủ trung tâm.', 'laca')], 500);
        }

        wp_send_json_success([
            'message'    => $res['data']['message'] ?? __('Yêu cầu hỗ trợ đã được gửi thành công.', 'laca'),
            'request_id' => $res['data']['request_id'] ?? 0,
        ]);
    }

    /**
     * AJAX Client get list of own support requests
     */
    public function ajaxGetRequests(): void
    {
        check_ajax_referer('laca_support_request_nonce', '_wpnonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'laca')], 403);
        }

        $res = $this->sendSignedHubRequest('GET', '/laca/v1/client/requests');
        if (!$res['success']) {
            wp_send_json_error(['message' => $res['message'] ?: __('Không thể tải danh sách yêu cầu.', 'laca')], 500);
        }

        wp_send_json_success([
            'requests' => $res['data']['requests'] ?? [],
        ]);
    }

    /**
     * Gửi request có ký HMAC-SHA256 tới Hub
     *
     * @param string $method GET hoặc POST
     * @param string $path REST API path (ví dụ '/laca/v1/client/requests')
     * @param array $body Dữ liệu gửi kèm
     * @return array{success:bool, message:string, data:array}
     */
    public function sendSignedHubRequest(string $method, string $path, array $body = []): array
    {
        $trackerEndpoint = LacaDevTrackerClient::getEndpoint();
        $trackerKey      = LacaDevTrackerClient::getTrackerKey();
        $projectId       = LacaDevTrackerClient::getProjectId();

        if (empty($trackerEndpoint) || empty($trackerKey)) {
            return [
                'success' => false,
                'message' => __('Website chưa cấu hình kết nối tới LacaDev Hub.', 'laca'),
                'data'    => [],
            ];
        }

        // Bóc tách base URL của Hub từ tracker endpoint
        // Ví dụ: http://lacadev.local/wp-json/laca/v1/tracker/log -> http://lacadev.local
        $parsed = parse_url($trackerEndpoint);
        $scheme = $parsed['scheme'] ?? 'http';
        $host   = $parsed['host'] ?? 'localhost';
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $hubUrl = "{$scheme}://{$host}{$port}";

        $targetUrl = rtrim($hubUrl, '/') . '/wp-json/' . ltrim($path, '/');
        $bodyJson  = ($method === 'POST' && !empty($body))
            ? wp_json_encode(array_merge(['secret_key' => $trackerKey], $body), JSON_UNESCAPED_UNICODE)
            : '';

        $headers = RequestSigner::sign($method, $targetUrl, $bodyJson, $trackerKey, $projectId);
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => $headers,
        ];
        if ($method === 'POST') {
            $args['body'] = $bodyJson;
        }

        $response = wp_remote_request($targetUrl, $args);
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'data'    => [],
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);
        $json = json_decode($rawBody, true) ?: [];

        if ($code < 200 || $code >= 300) {
            return [
                'success' => false,
                'message' => (string) ($json['message'] ?? "HTTP {$code}"),
                'data'    => $json,
            ];
        }

        return [
            'success' => true,
            'message' => (string) ($json['message'] ?? 'OK'),
            'data'    => $json,
        ];
    }
}
