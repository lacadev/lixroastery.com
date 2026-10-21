<?php

use App\Features\LacaSeo\Admin\ImportExportHandler;
use App\Features\LacaSeo\ImportExport\Importer;
use App\Features\LacaSeo\ImportExport\RankMathImporter;

defined('ABSPATH') || exit;

$backups = get_option(Importer::BACKUPS_OPTION, []);
$has_rm = RankMathImporter::hasRankMathData();

$userId = get_current_user_id();
$pendingImport = get_transient(ImportExportHandler::PENDING_IMPORT_TRANSIENT . $userId);

?>
<div class="laca-seo-import-export-wrap">

    <?php if (!empty($pendingImport) && is_array($pendingImport) && !empty($pendingImport['payload'])): ?>
        <?php
        $payload = $pendingImport['payload'];
        $sections = array_keys($payload['data'] ?? []);
        ?>
        <div class="laca-seo-card" style="border: 2px solid #2271b1; background: #f0f6fc; margin-bottom: 24px;">
            <h2 class="laca-seo-card__title" style="color:#0a4b78;">
                <span class="dashicons dashicons-warning" style="vertical-align:middle;color:#2271b1;"></span>
                Xác nhận ghi đè cấu hình từ tệp JSON
            </h2>
            <p>Tệp tải lên: <strong><?php echo esc_html($pendingImport['filename'] ?? ''); ?></strong> (Phiên bản: <?php echo esc_html($payload['version'] ?? '1.0.0'); ?> - Site nguồn: <code><?php echo esc_html($payload['site_url'] ?? ''); ?></code>)</p>
            
            <p><strong>Các phần cài đặt sẽ được ghi đè:</strong></p>
            <ul style="list-style:disc; margin-left:24px;">
                <?php if (in_array('general', $sections, true)): ?>
                    <li><strong>Cài đặt chung (General):</strong> <?php echo count($payload['data']['general'] ?? []); ?> trường cài đặt</li>
                <?php endif; ?>
                <?php if (in_array('titles', $sections, true)): ?>
                    <li><strong>Tiêu đề & Thẻ Meta (Titles):</strong> <?php echo count($payload['data']['titles'] ?? []); ?> trường cài đặt</li>
                <?php endif; ?>
                <?php if (in_array('sitemap', $sections, true)): ?>
                    <li><strong>Cấu hình Sitemap:</strong> <?php echo count($payload['data']['sitemap'] ?? []); ?> mục</li>
                <?php endif; ?>
                <?php if (in_array('instant_indexing', $sections, true)): ?>
                    <li><strong>Instant Indexing:</strong> <?php echo count($payload['data']['instant_indexing'] ?? []); ?> mục</li>
                <?php endif; ?>
                <?php if (in_array('modules', $sections, true)): ?>
                    <li><strong>Trạng thái Modules:</strong> <?php echo count($payload['data']['modules'] ?? []); ?> module kích hoạt</li>
                <?php endif; ?>
            </ul>

            <div style="background:#fff; border-left:4px solid #72aee6; padding:10px 14px; margin:16px 0;">
                <p style="margin:0;">
                    <?php if (!empty($pendingImport['replace_domain'])): ?>
                        ✓ Tự động đổi domain nguồn cũ sang domain website này: <code><?php echo esc_html(get_home_url()); ?></code><br>
                    <?php endif; ?>
                    <?php if (!empty($pendingImport['preserve_credentials'])): ?>
                        ✓ Giữ nguyên Google Verify ID & GA ID của website hiện tại<br>
                    <?php endif; ?>
                    <em>Hệ thống sẽ tự động tạo một bản sao lưu (backup) trước khi ghi đè để bạn có thể khôi phục lại bất kỳ lúc nào.</em>
                </p>
            </div>

            <div style="display:flex; gap:12px; align-items:center;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('laca_seo_confirm_import_action', 'laca_seo_confirm_import_nonce'); ?>
                    <input type="hidden" name="action" value="laca_seo_confirm_import">
                    <input type="hidden" name="import_token" value="<?php echo esc_attr($pendingImport['token'] ?? ''); ?>">
                    <button type="submit" class="button button-primary button-hero" onclick="return confirm('Bạn có chắc chắn muốn ghi đè cài đặt hiện tại không?');">
                        <span class="dashicons dashicons-yes-alt"></span> Xác nhận nhập & Ghi đè cài đặt
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('laca_seo_cancel_import_action', 'laca_seo_cancel_import_nonce'); ?>
                    <input type="hidden" name="action" value="laca_seo_cancel_import">
                    <button type="submit" class="button button-secondary">Huỷ bỏ</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="laca-seo-grid-2col">
        <!-- Export Box -->
        <div class="laca-seo-card">
            <h2 class="laca-seo-card__title">Xuất cấu hình (Export JSON)</h2>
            <p>Tải xuống toàn bộ cấu hình cài đặt Laca SEO ra tệp tin JSON an toàn để lưu trữ hoặc chuyển sang website khác.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('laca_seo_export_action', 'laca_seo_export_nonce'); ?>
                <input type="hidden" name="action" value="laca_seo_export">

                <fieldset class="laca-seo-fieldset" style="margin: 16px 0;">
                    <legend><strong>Chọn các phần cần xuất:</strong></legend>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" name="export_sections[]" value="general" checked> Cài đặt chung (General)</label>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" name="export_sections[]" value="titles" checked> Tiêu đề & Thẻ Meta (Titles & Meta)</label>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" name="export_sections[]" value="sitemap" checked> Cấu hình Sitemap</label>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" name="export_sections[]" value="instant_indexing" checked> Cấu hình Instant Indexing</label>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" name="export_sections[]" value="modules" checked> Trạng thái các Modules</label>
                </fieldset>

                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-download"></span> Tải tệp JSON về máy
                </button>
            </form>
        </div>

        <!-- Import Box -->
        <div class="laca-seo-card">
            <h2 class="laca-seo-card__title">Nhập cấu hình (Import JSON)</h2>
            <p>Tải lên tệp tin JSON cấu hình Laca SEO từ máy tính để áp dụng cài đặt.</p>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('laca_seo_import_action', 'laca_seo_import_nonce'); ?>
                <input type="hidden" name="action" value="laca_seo_upload_import">

                <div style="margin: 16px 0;">
                    <input type="file" name="import_file" accept=".json" required>
                    <p class="description">Chỉ chấp nhận tệp <code>.json</code> tối đa 2MB.</p>
                </div>

                <fieldset class="laca-seo-fieldset" style="margin: 16px 0;">
                    <legend><strong>Tuỳ chọn an toàn khi nhập:</strong></legend>
                    <label style="display:block;margin:6px 0;">
                        <input type="checkbox" name="replace_domain" value="1" checked>
                        Tự động thay đổi URL trang nguồn cũ thành URL website này (<?php echo esc_html(get_home_url()); ?>)
                    </label>
                    <label style="display:block;margin:6px 0;">
                        <input type="checkbox" name="preserve_credentials" value="1" checked>
                        Giữ nguyên mã xác minh Google Search Console & GA ID của site này
                    </label>
                </fieldset>

                <p class="description" style="margin-bottom:14px;"><em>Hệ thống sẽ kiểm tra tệp và hiển thị màn hình xác nhận trước khi ghi đè.</em></p>

                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span> Tải lên & Xem trước cấu hình
                </button>
            </form>
        </div>
    </div>

    <!-- Rank Math Importer -->
    <div class="laca-seo-card" style="margin-top: 24px;">
        <h2 class="laca-seo-card__title">Chuyển đổi dữ liệu từ Rank Math SEO</h2>
        <p>Tính năng chuyển đổi 1:1 toàn bộ dữ liệu cấu hình, điểm SEO, tiêu đề, mô tả bài viết và term meta từ plugin Rank Math sang Laca SEO.</p>

        <?php if ($has_rm): ?>
            <div class="laca-seo-notice-inline" style="background:#eef3fe;border-left:4px solid #8aadf7;padding:12px;margin:12px 0;">
                <p><strong>Phát hiện dữ liệu Rank Math trong CSDL:</strong> Dữ liệu cài đặt hoặc bài viết của Rank Math sẵn sàng chuyển đổi. Quá trình chuyển đổi diễn ra theo từng đợt (batch AJAX) an toàn và không gây treo server, đồng thời giữ nguyên dữ liệu Rank Math gốc.</p>
            </div>

            <div id="laca-seo-rm-migration-box" style="margin-top:16px;">
                <button type="button" id="laca-seo-rm-start-btn" class="button button-hero button-primary" style="max-width:100%; white-space:normal; height:auto; line-height:1.4; padding:10px 16px; text-align:left;">
                    <span class="dashicons dashicons-migrate"></span> Bắt đầu chuyển dữ liệu từ Rank Math (AJAX Batch)
                </button>

                <div id="laca-seo-rm-progress" style="display:none; margin-top:16px;">
                    <div style="background:#ddd; height:20px; border-radius:4px; overflow:hidden; position:relative;">
                        <div id="laca-seo-rm-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.3s ease;"></div>
                    </div>
                    <p id="laca-seo-rm-status" style="margin-top:8px; font-weight:600;"></p>
                    <ul id="laca-seo-rm-log" style="list-style:disc; margin-left:20px; font-size:13px; color:#555; max-height:160px; overflow-y:auto;"></ul>
                </div>
            </div>

            <script>
            (function() {
                var btn = document.getElementById('laca-seo-rm-start-btn');
                var prog = document.getElementById('laca-seo-rm-progress');
                var bar = document.getElementById('laca-seo-rm-bar');
                var status = document.getElementById('laca-seo-rm-status');
                var log = document.getElementById('laca-seo-rm-log');

                if (!btn) return;

                function addLog(msg) {
                    var li = document.createElement('li');
                    li.textContent = msg;
                    log.appendChild(li);
                    log.scrollTop = log.scrollHeight;
                }

                btn.addEventListener('click', function() {
                    if (!confirm('Bắt đầu chuyển toàn bộ cấu hình và post meta từ Rank Math sang Laca SEO?')) {
                        return;
                    }

                    btn.disabled = true;
                    prog.style.display = 'block';
                    bar.style.width = '5%';
                    status.textContent = 'Đang khởi động quá trình chuyển đổi...';
                    log.innerHTML = '';

                    function runStep(step, offset) {
                        var formData = new FormData();
                        formData.append('action', 'laca_seo_rm_import_step');
                        formData.append('nonce', '<?php echo esc_js(wp_create_nonce('laca_seo_admin_nonce')); ?>');
                        formData.append('step', step);
                        formData.append('offset', offset);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(res) { return res.json(); })
                        .then(function(resp) {
                            if (!resp.success) {
                                status.textContent = 'Lỗi: ' + (resp.data ? resp.data.message : 'Unknown error');
                                btn.disabled = false;
                                return;
                            }

                            var data = resp.data;
                            addLog(data.message);

                            if (step === 'settings') {
                                bar.style.width = '25%';
                                runStep('termmeta', 0);
                            } else if (step === 'termmeta') {
                                bar.style.width = '50%';
                                runStep('usermeta', 0);
                            } else if (step === 'usermeta') {
                                bar.style.width = '60%';
                                runStep('postmeta', 0);
                            } else if (step === 'postmeta') {
                                if (data.done) {
                                    bar.style.width = '100%';
                                    status.textContent = '✅ Đã hoàn tất chuyển đổi toàn bộ dữ liệu Rank Math sang Laca SEO!';
                                    addLog('Hoàn tất 100%.');
                                    btn.style.display = 'none';
                                } else {
                                    var remaining = data.remaining || 0;
                                    var processed = data.next_offset || 0;
                                    var total = processed + remaining;
                                    var pct = total > 0 ? Math.min(95, Math.round(60 + (processed / total) * 35)) : 80;
                                    bar.style.width = pct + '%';
                                    status.textContent = 'Đang xử lý bài viết: ' + processed + ' / ' + total + '...';
                                    runStep('postmeta', data.next_offset);
                                }
                            }
                        })
                        .catch(function(err) {
                            status.textContent = 'Lỗi kết nối mạng hoặc server timeout: ' + err.message;
                            btn.disabled = false;
                        });
                    }

                    runStep('settings', 0);
                });
            })();
            </script>
        <?php else: ?>
            <p><em>Không phát hiện dữ liệu Rank Math nào trong cơ sở dữ liệu.</em></p>
        <?php endif; ?>
    </div>

    <!-- CSV Meta Import & Export (Pro) -->
    <div class="laca-seo-card" style="margin-top: 24px;">
        <h2 class="laca-seo-card__title">
            <span class="dashicons dashicons-media-spreadsheet" style="vertical-align:middle;color:#2563eb;"></span>
            CSV Meta Bài viết & Danh mục
        </h2>
        <p>Xuất và nhập hàng loạt tiêu đề SEO, mô tả, từ khoá chính, robots, canonical URL của bài viết hoặc danh mục thông qua tệp tin CSV (chuẩn UTF-8 có BOM).</p>

        <div class="laca-seo-grid-2col" style="margin-top: 16px;">
            <!-- CSV Export Box -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px;">
                <h3 style="margin-top:0; font-size:14px; font-weight:600;">Xuất tệp CSV</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('laca_seo_csv_export_action', 'laca_seo_csv_export_nonce'); ?>
                    <input type="hidden" name="action" value="laca_seo_csv_export">

                    <p>
                        <label for="csv_object_type"><strong>Chọn loại nội dung:</strong></label><br>
                        <select name="csv_object_type" id="csv_object_type" style="width: 100%; margin-top: 6px;">
                            <optgroup label="Loại bài viết (Post Types)">
                                <?php foreach (\App\Features\LacaSeo\Core\Helper::getAccessiblePostTypes() as $slug => $ptObj): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($ptObj->labels->name ?: $slug); ?> (<?php echo esc_html($slug); ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Phân loại (Taxonomies)">
                                <?php foreach (\App\Features\LacaSeo\Core\Helper::getAccessibleTaxonomies() as $taxSlug => $taxObj): ?>
                                    <option value="<?php echo esc_attr($taxSlug); ?>"><?php echo esc_html($taxObj->labels->name ?: $taxSlug); ?> (<?php echo esc_html($taxSlug); ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </p>

                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-download"></span> Tải CSV về máy
                    </button>
                </form>
            </div>

            <!-- CSV Import Box -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px;">
                <h3 style="margin-top:0; font-size:14px; font-weight:600;">Nhập tệp CSV</h3>
                <p style="font-size:12px; color:#64748b;">Hỗ trợ tệp .csv (≤5MB). Tự động khớp theo cột <code>id</code> (hoặc <code>slug</code> + <code>object_type</code>). Không tạo bài mới.</p>

                <input type="file" id="laca_csv_file_input" accept=".csv" style="margin-bottom: 12px; display:block;">
                <button type="button" class="button button-secondary" id="laca_csv_start_import_btn">
                    <span class="dashicons dashicons-upload"></span> Bắt đầu nhập dữ liệu CSV
                </button>

                <div id="laca_csv_import_progress" style="display:none; margin-top:14px;">
                    <div style="background:#e2e8f0; border-radius:4px; height:10px; overflow:hidden;">
                        <div id="laca_csv_progress_bar" style="background:#2563eb; width:0%; height:100%; transition:width 0.2s;"></div>
                    </div>
                    <p id="laca_csv_progress_text" style="font-size:12px; margin:6px 0 0; color:#334155;">Đang chuẩn bị...</p>
                </div>

                <div id="laca_csv_import_log" style="display:none; margin-top:12px; font-size:12px; max-height:140px; overflow-y:auto; background:#fff; padding:8px; border:1px solid #cbd5e1; border-radius:4px;"></div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var startBtn = document.getElementById('laca_csv_start_import_btn');
        var fileInput = document.getElementById('laca_csv_file_input');
        var progressBox = document.getElementById('laca_csv_import_progress');
        var progressBar = document.getElementById('laca_csv_progress_bar');
        var progressText = document.getElementById('laca_csv_progress_text');
        var logBox = document.getElementById('laca_csv_import_log');

        if (!startBtn || !fileInput) return;

        startBtn.addEventListener('click', function() {
            var file = fileInput.files[0];
            if (!file) {
                alert('Vui lòng chọn một tệp CSV để nhập.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Kích thước tệp CSV không được vượt quá 5MB.');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                var text = e.target.result;
                var rows = parseCSV(text);
                if (rows.length === 0) {
                    alert('Tệp CSV rỗng hoặc không đúng định dạng.');
                    return;
                }

                startBtn.disabled = true;
                progressBox.style.display = 'block';
                logBox.style.display = 'none';
                logBox.innerHTML = '';

                var BATCH_SIZE = 100;
                var totalRows = rows.length;
                var totalUpdated = 0;
                var allErrors = [];
                var currentIndex = 0;

                function sendBatch() {
                    var chunk = rows.slice(currentIndex, currentIndex + BATCH_SIZE);
                    if (chunk.length === 0) {
                        progressBar.style.width = '100%';
                        progressText.textContent = 'Hoàn tất! Đã cập nhật ' + totalUpdated + '/' + totalRows + ' mục.';
                        startBtn.disabled = false;
                        if (allErrors.length > 0) {
                            logBox.style.display = 'block';
                            logBox.innerHTML = '<strong>Chi tiết lỗi:</strong><br>' + allErrors.join('<br>');
                        }
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'laca_seo_csv_import_batch');
                    formData.append('nonce', '<?php echo esc_js(wp_create_nonce('laca_seo_admin_nonce')); ?>');

                    chunk.forEach(function(row, i) {
                        for (var key in row) {
                            if (row.hasOwnProperty(key)) {
                                formData.append('rows[' + i + '][' + key + ']', row[key]);
                            }
                        }
                    });

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(res) {
                        if (res.success) {
                            totalUpdated += (res.data.updated || 0);
                            if (res.data.errors && res.data.errors.length) {
                                allErrors = allErrors.concat(res.data.errors);
                            }
                        }
                        currentIndex += BATCH_SIZE;
                        var pct = Math.min(100, Math.round((currentIndex / totalRows) * 100));
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Đang xử lý: ' + Math.min(currentIndex, totalRows) + ' / ' + totalRows + ' dòng (' + pct + '%)...';
                        sendBatch();
                    })
                    .catch(function(err) {
                        progressText.textContent = 'Lỗi kết nối mạng: ' + err.message;
                        startBtn.disabled = false;
                    });
                }

                sendBatch();
            };
            reader.readAsText(file, 'UTF-8');
        });

        function parseCSV(text) {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.substr(1);
            }
            var lines = text.split(/\r\n|\n|\r/);
            if (lines.length < 2) return [];

            var header = splitCsvLine(lines[0]);
            var results = [];

            for (var i = 1; i < lines.length; i++) {
                var line = lines[i].trim();
                if (!line) continue;
                var values = splitCsvLine(line);
                var row = {};
                for (var j = 0; j < header.length; j++) {
                    var key = header[j].trim();
                    row[key] = values[j] !== undefined ? values[j].trim() : '';
                }
                results.push(row);
            }
            return results;
        }

        function splitCsvLine(line) {
            var re = /(?!\s*$)\s*(?:'([^'\\]*(?:\\[\S\s][^'\\]*)*)'|"([^"\\]*(?:\\[\S\s][^"\\]*)*)"|([^,'"\s\\*]*(?:\s+[^,'"\s\\*]+)*))\s*(?:,|$)/g;
            var a = [];
            line.replace(re, function(m0, m1, m2, m3) {
                if (m1 !== undefined) a.push(m1.replace(/\\'/g, "'"));
                else if (m2 !== undefined) a.push(m2.replace(/\\"/g, '"'));
                else if (m3 !== undefined) a.push(m3);
                return '';
            });
            return a;
        }
    })();
    </script>

    <!-- Backups List -->
    <div class="laca-seo-card" style="margin-top: 24px;">
        <h2 class="laca-seo-card__title">Lịch sử sao lưu tự động (Tối đa 5 bản gần nhất)</h2>
        <p>Mỗi khi nhập dữ liệu hoặc chuyển từ Rank Math, một bản snapshot sẽ được lưu lại để có thể khôi phục tức thì khi cần.</p>

        <?php if (empty($backups)): ?>
            <p><em>Chưa có bản sao lưu nào được ghi nhận.</em></p>
        <?php else: ?>
            <div class="laca-seo-table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%;">
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Thời gian</th>
                        <th style="width: 45%;">Lý do sao lưu</th>
                        <th style="width: 30%;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><?php echo esc_html($b['time'] ?? ''); ?></td>
                            <td><?php echo esc_html($b['reason'] ?? ''); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn khôi phục lại bản sao lưu này?');">
                                    <?php wp_nonce_field('laca_seo_backup_action', 'laca_seo_backup_nonce'); ?>
                                    <input type="hidden" name="action" value="laca_seo_restore_backup">
                                    <input type="hidden" name="backup_id" value="<?php echo esc_attr($b['id'] ?? ''); ?>">
                                    <button type="submit" class="button button-small button-primary">Khôi phục</button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline; margin-left:4px;">
                                    <?php wp_nonce_field('laca_seo_backup_action', 'laca_seo_backup_nonce'); ?>
                                    <input type="hidden" name="action" value="laca_seo_download_backup">
                                    <input type="hidden" name="backup_id" value="<?php echo esc_attr($b['id'] ?? ''); ?>">
                                    <button type="submit" class="button button-small button-secondary">Tải JSON</button>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline; margin-left:4px;" onsubmit="return confirm('Bạn có chắc muốn xoá bản sao lưu này?');">
                                    <?php wp_nonce_field('laca_seo_backup_action', 'laca_seo_backup_nonce'); ?>
                                    <input type="hidden" name="action" value="laca_seo_delete_backup">
                                    <input type="hidden" name="backup_id" value="<?php echo esc_attr($b['id'] ?? ''); ?>">
                                    <button type="submit" class="button button-small button-link-delete">Xoá</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
