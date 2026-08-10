<?php

namespace App\Settings\LacaTools\Management;

/**
 * QuickNotesWidget
 *
 * Dashboard widget: Sticky Notes cho admin.
 * Nhiều note với màu khác nhau, lưu qua AJAX vào wp_options.
 * Không cần DB table riêng.
 */
class QuickNotesWidget
{
    const OPT_KEY = 'laca_quick_notes';
    const NONCE   = 'laca_quick_notes';

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
        add_action('wp_ajax_laca_notes_save', [$this, 'handleSave']);
        add_action('wp_ajax_laca_notes_delete', [$this, 'handleDelete']);
    }

    public function addWidget(): void
    {
        wp_add_dashboard_widget(
            'laca_quick_notes',
            'Ghi chú nhanh',
            [$this, 'renderWidget']
        );
    }

    public function renderWidget(): void
    {
        $notes   = get_option(self::OPT_KEY, []);
        $nonce   = wp_create_nonce(self::NONCE);
        $ajaxUrl = admin_url('admin-ajax.php');
        $colors  = ['#fff9c4', '#c8e6c9', '#bbdefb', '#f8bbd0', '#ffe0b2', '#e1bee7'];
        ?>
        <div id="laca-notes-wrap">
            <?php if (empty($notes)): ?>
                <p id="laca-notes-empty" style="color:#999;text-align:center;padding:10px">Chưa có ghi chú nào. Nhấn "+ Thêm ghi chú" để bắt đầu.</p>
            <?php else: ?>
                <p id="laca-notes-empty" style="display:none;color:#999;text-align:center;padding:10px">Chưa có ghi chú nào.</p>
            <?php endif; ?>

            <div id="laca-notes-list" style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ($notes as $note): ?>
                <div class="laca-note" data-id="<?php echo esc_attr($note['id']); ?>"
                     style="background:<?php echo esc_attr($note['color']); ?>;padding:10px;border-radius:6px;position:relative">
                    <textarea class="laca-note-text" rows="3"
                        style="width:100%;border:none;background:transparent;resize:vertical;font-size:13px;font-family:inherit;box-sizing:border-box;outline:none"
                        placeholder="Nhập ghi chú..."><?php echo esc_textarea($note['text']); ?></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                        <small style="color:#777;font-size:10px"><?php echo esc_html($note['date'] ?? ''); ?></small>
                        <button type="button" class="laca-note-delete"
                            style="background:none;border:none;cursor:pointer;color:#999;font-size:16px;line-height:1;padding:0"
                            title="Xoá ghi chú">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <span style="font-size:12px;color:#666">Màu:</span>
                <?php foreach ($colors as $c): ?>
                    <button type="button" class="laca-note-color-pick"
                        data-color="<?php echo esc_attr($c); ?>"
                        style="width:20px;height:20px;border-radius:50%;background:<?php echo esc_attr($c); ?>;border:2px solid #ccc;cursor:pointer;flex-shrink:0"
                        title="<?php echo esc_attr($c); ?>"></button>
                <?php endforeach; ?>
                <button type="button" id="laca-note-add"
                    style="margin-left:auto;padding:4px 12px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px">
                    + Thêm ghi chú
                </button>
            </div>
            <p id="laca-note-saved" style="display:none;color:#155724;font-size:11px;margin:6px 0 0;text-align:right">✓ Đã lưu</p>
        </div>

        <script>
        (function() {
            const AJAX  = '<?php echo esc_js($ajaxUrl); ?>';
            const NONCE = '<?php echo esc_js($nonce); ?>';
            let activeColor = '<?php echo esc_js($colors[0]); ?>';
            let saveTimer;

            function showSaved() {
                const el = document.getElementById('laca-note-saved');
                el.style.display = 'block';
                clearTimeout(window.__lacaSavedTimer);
                window.__lacaSavedTimer = setTimeout(() => el.style.display = 'none', 2000);
            }

            function gatherNotes() {
                return Array.from(document.querySelectorAll('#laca-notes-list .laca-note')).map(n => ({
                    id:    n.dataset.id,
                    text:  n.querySelector('.laca-note-text').value,
                    color: n.style.background,
                    date:  n.querySelector('small').textContent.trim(),
                }));
            }

            function saveNotes() {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => {
                    fetch(AJAX, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: 'action=laca_notes_save&_nonce=' + NONCE + '&notes=' + encodeURIComponent(JSON.stringify(gatherNotes()))
                    }).then(r => r.json()).then(res => { if (res.success) showSaved(); });
                }, 600);
            }

            function updateEmpty() {
                const list  = document.getElementById('laca-notes-list');
                const empty = document.getElementById('laca-notes-empty');
                if (empty) empty.style.display = list.children.length ? 'none' : '';
            }

            document.getElementById('laca-notes-list').addEventListener('input', saveNotes);

            document.getElementById('laca-notes-list').addEventListener('click', function(e) {
                const btn = e.target.closest('.laca-note-delete');
                if (!btn) return;
                const note = btn.closest('.laca-note');
                if (confirm('Xoá ghi chú này?')) {
                    note.remove();
                    updateEmpty();
                    saveNotes();
                }
            });

            document.querySelectorAll('.laca-note-color-pick').forEach(btn => {
                btn.addEventListener('click', function() {
                    activeColor = this.dataset.color;
                    document.querySelectorAll('.laca-note-color-pick').forEach(b => b.style.borderColor = '#ccc');
                    this.style.borderColor = '#555';
                });
            });

            document.getElementById('laca-note-add').addEventListener('click', function() {
                const id   = 'note_' + Date.now();
                const date = new Date().toLocaleDateString('vi-VN');
                const div  = document.createElement('div');
                div.className   = 'laca-note';
                div.dataset.id  = id;
                div.style.background = activeColor;
                div.style.cssText += 'padding:10px;border-radius:6px;position:relative';
                div.innerHTML = `
                    <textarea class="laca-note-text" rows="3"
                        style="width:100%;border:none;background:transparent;resize:vertical;font-size:13px;font-family:inherit;box-sizing:border-box;outline:none"
                        placeholder="Nhập ghi chú..."></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                        <small style="color:#777;font-size:10px">${date}</small>
                        <button type="button" class="laca-note-delete"
                            style="background:none;border:none;cursor:pointer;color:#999;font-size:16px;line-height:1;padding:0">✕</button>
                    </div>`;
                document.getElementById('laca-notes-list').appendChild(div);
                div.querySelector('textarea').focus();
                updateEmpty();
                saveNotes();
            });
        })();
        </script>
        <?php
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error('Không có quyền.', 403);
        }

        $raw   = stripslashes($_POST['notes'] ?? '[]');
        $notes = json_decode($raw, true) ?: [];

        $clean = array_map(function ($n) {
            return [
                'id'    => sanitize_key($n['id'] ?? ''),
                'text'  => sanitize_textarea_field($n['text'] ?? ''),
                'color' => sanitize_hex_color($n['color'] ?? '') ?: '#fff9c4',
                'date'  => sanitize_text_field($n['date'] ?? ''),
            ];
        }, $notes);

        update_option(self::OPT_KEY, $clean, false);
        wp_send_json_success(['count' => count($clean)]);
    }

    public function handleDelete(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, '_nonce', false)) {
            wp_send_json_error('Không có quyền.', 403);
        }

        $id    = sanitize_key($_POST['note_id'] ?? '');
        $notes = get_option(self::OPT_KEY, []);
        $notes = array_values(array_filter($notes, fn($n) => ($n['id'] ?? '') !== $id));
        update_option(self::OPT_KEY, $notes, false);
        wp_send_json_success();
    }
}

