import Swal from 'sweetalert2';
// Bundle qua webpack (import thật) thay vì enqueue riêng file
// node_modules/sortablejs/Sortable.min.js (ContactFormManager::enqueueAssets()
// cũ) — package đó CHƯA TỪNG được cài thật (không có trong package.json),
// file không tồn tại nên wp_enqueue_script() không bao giờ chạy, kéo/thả
// field trong trình tạo form vì vậy luôn im lặng không hoạt động (code có
// guard "typeof Sortable === 'undefined'" nên không báo lỗi gì cả).
import Sortable from 'sortablejs';

// ── Popup Thông báo (Thành công/Thất bại) — logic DÙNG CHUNG giữa tab
// "Giao diện" của 1 form (state = styles) VÀ panel "Cài đặt chung" ở trang
// danh sách form (state = popupDefaults, xem khối if(window.LacaCfPopupDefaultsVars)
// phía dưới). 2 ngữ cảnh KHÔNG BAO GIỜ cùng tồn tại trên 1 trang nên dùng
// chung HẾT tên hàm window.lcfPopup* không sợ đụng nhau — mỗi trang tự gán
// lại các hàm này trỏ về đúng controller của state riêng nó.
function createPopupController(state, serializeFn, nonDefaultLangs, aiTranslateVars) {
    const ICON_PRESET = ['✓', '✕', '⚠', 'ℹ', '★', '♥', '👍', '👎', '🎉', '🔔', '⏰', '→'];
    const esc = function(s) { return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); };

    function refreshVisibility() {
        ['success', 'error'].forEach(function(st) {
            const wrap = document.getElementById('popup-' + st + '-custom-icon-wrap');
            if (wrap) wrap.hidden = state['popup_' + st + '_icon_mode'] !== 'custom';
        });
        const closeTextWrap = document.getElementById('popup-close-text-wrap');
        const closeIconWrap = document.getElementById('popup-close-icon-wrap');
        if (closeTextWrap) closeTextWrap.hidden = state.popup_close_mode === 'icon';
        if (closeIconWrap) closeIconWrap.hidden = state.popup_close_mode !== 'icon';
        const secWrap = document.getElementById('popup-dismiss-seconds-wrap');
        if (secWrap) secWrap.hidden = state.popup_dismiss_mode !== 'timer';
    }

    function renderIconGrid(fieldKey) {
        const container = document.getElementById(fieldKey + '-icon-grid');
        if (!container) return;
        container.innerHTML = ICON_PRESET.map(function(e) {
            const active = state[fieldKey] === e ? ' is-active' : '';
            return '<button type="button" class="lcf-icon-pick-btn' + active + '" onclick="lcfPopupIconPick(\'' + fieldKey + '\',\'' + e + '\')">' + e + '</button>';
        }).join('');
    }

    function renderI18nBlock(fieldKey, forceOpen) {
        const container = document.getElementById(fieldKey + '-i18n-container');
        if (!container || !nonDefaultLangs || !nonDefaultLangs.length) return;
        const wasOpen = forceOpen || !!container.querySelector('.lcf-i18n-wrap.is-open');
        const groups = nonDefaultLangs.map(function(lang) {
            const val = (state[fieldKey + '_i18n'] && state[fieldKey + '_i18n'][lang.slug]) || '';
            return '<div class="lcf-i18n-lang-group" data-lang="' + esc(lang.slug) + '">'
                + '<div class="lcf-i18n-lang-title"><span>' + esc(lang.name) + '</span>'
                + '<button type="button" class="lcf-i18n-ai-btn" onclick="lcfAiTranslatePopupField(\'' + fieldKey + '\',\'' + esc(lang.slug) + '\',this)">✨ Dịch bằng AI</button></div>'
                + '<div class="lcf-input-row"><input type="text" class="widefat" value="' + esc(val) + '" oninput="lcfPopupI18nUpdate(\'' + fieldKey + '\',\'' + esc(lang.slug) + '\',this.value)"></div>'
                + '</div>';
        }).join('');
        container.innerHTML = '<div class="lcf-i18n-wrap' + (wasOpen ? ' is-open' : '') + '">'
            + '<button type="button" class="lcf-i18n-toggle" onclick="this.closest(\'.lcf-i18n-wrap\').classList.toggle(\'is-open\')">🌐 Dịch sang ngôn ngữ khác (' + nonDefaultLangs.length + ')</button>'
            + '<div class="lcf-i18n-block">' + groups + '</div></div>';
    }

    function update(key, value) {
        if (key === 'popup_border_radius') {
            value = Math.max(0, Math.min(40, parseInt(value, 10) || 0));
        } else if (key === 'popup_dismiss_seconds') {
            value = Math.max(1, Math.min(30, parseInt(value, 10) || 1));
        }
        state[key] = value;
        serializeFn();
        refreshVisibility();
    }

    function i18nUpdate(fieldKey, langSlug, value) {
        state[fieldKey + '_i18n'] = state[fieldKey + '_i18n'] || {};
        state[fieldKey + '_i18n'][langSlug] = value;
        serializeFn();
    }

    function iconPick(fieldKey, emoji) {
        update(fieldKey, emoji);
        renderIconGrid(fieldKey);
    }

    function aiTranslate(fieldKey, langSlug, btnEl) {
        if (!aiTranslateVars) return;
        const body = new URLSearchParams();
        body.set('action', 'laca_cf_ai_translate_field');
        body.set('nonce', aiTranslateVars.nonce);
        body.set('target_lang', langSlug);
        body.set(fieldKey, state[fieldKey] || '');

        const originalText = btnEl.textContent;
        btnEl.disabled = true;
        btnEl.textContent = 'Đang dịch…';

        fetch(aiTranslateVars.ajaxUrl, { method: 'POST', body: body })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) {
                    Swal.fire({ title: 'Lỗi dịch AI', text: (res.data && res.data.message) || 'Không thể dịch.', icon: 'error' });
                    return;
                }
                if (res.data && res.data[fieldKey] !== undefined) {
                    i18nUpdate(fieldKey, langSlug, res.data[fieldKey]);
                    renderI18nBlock(fieldKey, true);
                }
            })
            .catch(function() {
                Swal.fire({ title: 'Lỗi', text: 'Không thể kết nối tới máy chủ.', icon: 'error' });
            })
            .finally(function() {
                btnEl.disabled = false;
                btnEl.textContent = originalText;
            });
    }

    function preview(kind) {
        const title = state['popup_' + kind + '_title'] || (kind === 'success' ? '✓ Thành công!' : '✕ Thất bại');
        const desc = state['popup_' + kind + '_desc'] || '';
        const iconMode = state['popup_' + kind + '_icon_mode'] || 'default';
        const customIcon = state['popup_' + kind + '_custom_icon'] || '';
        const opts = { title: title, text: desc, confirmButtonText: state.popup_close_text || 'Đóng' };
        if (iconMode === 'hidden') {
            // Không set icon — Swal không vẽ icon nào.
        } else if (iconMode === 'custom' && customIcon) {
            opts.icon = kind;
            opts.iconHtml = '<span style="font-size:3.75em;line-height:1">' + customIcon + '</span>';
        } else {
            opts.icon = kind;
        }
        if ((state.popup_dismiss_mode || 'button') === 'timer') {
            opts.timer = Math.max(1, parseInt(state.popup_dismiss_seconds, 10) || 3) * 1000;
            opts.timerProgressBar = true;
            opts.showConfirmButton = false;
        }
        Swal.fire(opts);
    }

    function initAll() {
        const simple = [
            ['popup-success-color', 'popup_success_color'], ['popup-success-color-text', 'popup_success_color'],
            ['popup-error-color', 'popup_error_color'], ['popup-error-color-text', 'popup_error_color'],
            ['popup-radius', 'popup_border_radius'], ['popup-radius-num', 'popup_border_radius'],
            ['popup-custom-css', 'popup_custom_css'],
            ['popup-success-title', 'popup_success_title'], ['popup-success-desc', 'popup_success_desc'],
            ['popup-success-icon-mode', 'popup_success_icon_mode'],
            ['popup-error-title', 'popup_error_title'], ['popup-error-desc', 'popup_error_desc'],
            ['popup-error-icon-mode', 'popup_error_icon_mode'],
            ['popup-close-mode', 'popup_close_mode'], ['popup-close-text', 'popup_close_text'],
            ['popup-dismiss-mode', 'popup_dismiss_mode'], ['popup-dismiss-seconds', 'popup_dismiss_seconds'],
            ['msg_session_expired', 'msg_session_expired'], ['msg_invalid_form', 'msg_invalid_form'],
            ['msg_form_not_found', 'msg_form_not_found'], ['msg_field_required_suffix', 'msg_field_required_suffix'],
            ['msg_invalid_email_suffix', 'msg_invalid_email_suffix'], ['msg_invalid_url_suffix', 'msg_invalid_url_suffix'],
            ['msg_invalid_phone_suffix', 'msg_invalid_phone_suffix'], ['msg_technical_error', 'msg_technical_error'],
            ['msg_email_failed', 'msg_email_failed'], ['msg_network_error', 'msg_network_error'],
            ['msg_submitting_text', 'msg_submitting_text'],
        ];
        simple.forEach(function(pair) {
            const el = document.getElementById(pair[0]);
            if (el && state[pair[1]] !== undefined && state[pair[1]] !== null) el.value = state[pair[1]];
        });
        // Màu nút popup: để trống = fallback primary_color (không áp dụng
        // cho global vì không có primary_color global — vẫn an toàn vì
        // styles.primary_color undefined thì || bỏ qua, input để trống).
        const popupBtn = document.getElementById('popup-button-color');
        const popupBtnText = document.getElementById('popup-button-color-text');
        if (popupBtn) popupBtn.value = state.popup_button_color || state.primary_color || '#2271b1';
        if (popupBtnText) popupBtnText.value = state.popup_button_color || '';

        ['popup_success_custom_icon', 'popup_error_custom_icon', 'popup_close_text'].forEach(renderIconGrid);
        ['popup_success_title', 'popup_success_desc', 'popup_error_title', 'popup_error_desc', 'popup_close_text',
            'msg_session_expired', 'msg_invalid_form', 'msg_form_not_found', 'msg_field_required_suffix',
            'msg_invalid_email_suffix', 'msg_invalid_url_suffix', 'msg_invalid_phone_suffix',
            'msg_technical_error', 'msg_email_failed', 'msg_network_error', 'msg_submitting_text'].forEach(function(k) { renderI18nBlock(k); });

        const overrideToggle = document.getElementById('popup-override-toggle');
        if (overrideToggle) overrideToggle.checked = !!state.popup_override;

        refreshVisibility();
    }

    return { update, i18nUpdate, iconPick, aiTranslate, preview, initAll, renderIconGrid, renderI18nBlock, refreshVisibility };
}

document.addEventListener("DOMContentLoaded", function() {
if (window.LacaContactFormVars) {
const FIELD_TYPES = window.LacaContactFormVars.FIELD_TYPES;
            const HAS_OPTIONS = ['select', 'multiselect', 'radio', 'checkbox'];
            const CAN_HAVE_OTHER = ['radio', 'checkbox'];
            // Danh sách ngôn ngữ Polylang đang bật (rỗng nếu Polylang tắt
            // hoặc site chỉ có 1 ngôn ngữ) — quyết định có hiện khối "Dịch
            // sang ngôn ngữ khác" trong từng field-card hay không.
            const LANGUAGES = window.LacaContactFormVars.languages || [];
            const NON_DEFAULT_LANGS = LANGUAGES.filter(function(l) { return !l.is_default; });

            // Row layout templates: array of spans (12-col grid)
            const ROW_TEMPLATES = {
                '1':   [12],
                '2':   [6, 6],
                '3':   [4, 4, 4],
                '4':   [3, 3, 3, 3],
                '1-2': [4, 8],
                '2-1': [8, 4],
            };

            // Mutable state
            let rows = window.LacaContactFormVars.rows || [];
            let sortableInstances = [];

            // PHP không phân biệt được mảng kết hợp rỗng với mảng số rỗng —
            // json_encode([]) LUÔN LUÔN ra "[]" dù field['i18n'] rỗng vì
            // chưa từng dịch ngôn ngữ nào (chưa đủ ý nghĩa "mảng kết hợp").
            // JSON.parse("[]") ở đây cho ra 1 JAVASCRIPT ARRAY thay vì
            // object — gán field.i18n['en'] = {...} lên ARRAY vẫn "thành
            // công" (đọc lại thấy đúng) nhưng JSON.stringify() trên array
            // CHỈ xuất phần tử số, ÂM THẦM bỏ qua thuộc tính string khi gửi
            // lưu — field MỚI dịch lần đầu luôn mất bản dịch ngay khi lưu.
            // Ép lại i18n thành object thật NGAY LÚC TẢI DỮ LIỆU (không chỉ
            // sửa PHP cho lần lưu sau) để xử lý luôn các form ĐÃ LỠ lưu sai
            // từ trước khi có fix này.
            (function normalizeI18nShape(fieldList) {
                fieldList.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) {
                            if (!f.i18n || Array.isArray(f.i18n)) f.i18n = {};
                        });
                    });
                });
            })(rows);

            // Mặc định thu gọn TẤT CẢ field khi mới vào trang sửa form — dễ
            // nhìn tổng quan cấu trúc form hơn, nhất là form có nhiều field
            // (yêu cầu người dùng, tránh cuộn dài để tìm field cần sửa).
            // Hàm collapseAllFields() được khai báo (hoisted) phía dưới.
            collapseAllFields();

            // ── Styles state ──────────────────────────────────────────────────
            const DEFAULT_STYLES = {
                primary_color: '#2271b1', secondary_color: '#1a5a9e',
                input_border_color: '#cccccc', label_color: '#333333',
                btn_border_radius: 6, input_border_radius: 6,
                btn_text: 'Gửi thông tin', submit_align: 'right',
                popup_success_color: '#28a745', popup_error_color: '#dc3545',
                popup_border_radius: 12,
                // Nội dung/hành vi popup — KHỚP với ContactFormPopupSettings::DEFAULTS
                // phía PHP (chỉ dùng làm giá trị hiển thị lúc popup_override
                // tắt; giá trị THẬT dùng khi gửi form luôn do PHP resolve()).
                popup_success_title: '✓ Thành công!', popup_success_desc: 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!',
                popup_success_icon_mode: 'default', popup_success_custom_icon: '',
                popup_error_title: '✕ Thất bại', popup_error_desc: 'Đã có lỗi xảy ra. Vui lòng thử lại.',
                popup_error_icon_mode: 'default', popup_error_custom_icon: '',
                popup_close_mode: 'text', popup_close_text: 'Đóng',
                popup_dismiss_mode: 'button', popup_dismiss_seconds: 3,
            };
            const SUBMIT_ALIGN_TO_JUSTIFY = { left: 'flex-start', center: 'center', right: 'flex-end' };
            let styles = Object.assign({}, DEFAULT_STYLES, (function() {
                try { return JSON.parse(document.getElementById('style-json-input').value || '{}'); } catch(e) { return {}; }
            })());

            // ── Escape helpers ────────────────────────────────────────────────
            function escHtml(str) {
                const d = document.createElement('div');
                d.textContent = str || '';
                return d.innerHTML;
            }
            function escAttr(str) {
                return (str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            // ── Unique ID ─────────────────────────────────────────────────────
            function uid() {
                return 'id_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
            }

            // ── Sync hidden JSON input ────────────────────────────────────────
            function updateJsonInput() {
                // Strip internal _autoName before saving
                const clean = rows.map(function(row) {
                    return {
                        id:   row.id,
                        cols: row.cols.map(function(col) {
                            return {
                                id:     col.id,
                                span:   col.span,
                                fields: col.fields.map(function(f) {
                                    const c = Object.assign({}, f);
                                    delete c._autoName;
                                    delete c._isOpen; // trạng thái đóng/mở card — chỉ dùng phía UI, không lưu DB
                                    return c;
                                }),
                            };
                        }),
                    };
                });
                document.getElementById('fields-json-input').value = JSON.stringify(clean);
            }

            // ── Find field by id → { row, col, field } or null ───────────────
            function findField(fieldId) {
                for (const row of rows) {
                    for (const col of row.cols) {
                        for (const field of col.fields) {
                            if (field.id === fieldId) {
                                return { row, col, field };
                            }
                        }
                    }
                }
                return null;
            }

            // ── Thu gọn mọi field (trừ exceptId nếu có) — dùng cho hành vi
            // "accordion": chỉ 1 field mở rộng tại 1 thời điểm, để form dài
            // nhiều field vẫn dễ nhìn tổng quan/dễ sửa.
            function collapseAllFields(exceptId) {
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) {
                            if (f.id !== exceptId) f._isOpen = false;
                        });
                    });
                });
            }

            // ── Khối "Dịch sang ngôn ngữ khác" cho field thường (không áp
            // dụng cho "hidden" — placeholder của hidden là giá trị submit
            // thật, dịch sẽ đổi luôn data, xem applyFieldTranslation() phía
            // ── Khối "Dịch sang ngôn ngữ khác" cho field thường (không áp
            // dụng cho "hidden" — placeholder của hidden là giá trị submit
            // thật, dịch sẽ đổi luôn data, xem applyFieldTranslation() phía
            // PHP) — mỗi ngôn ngữ show lại đúng các input đang có ở field.
            function buildI18nBlock(field) {
                if (!NON_DEFAULT_LANGS.length || field.type === 'hidden') return '';

                const hasOptions   = HAS_OPTIONS.includes(field.type);
                const canHaveOther = CAN_HAVE_OTHER.includes(field.type);

                const groups = NON_DEFAULT_LANGS.map(function(lang) {
                    const i18n = (field.i18n && field.i18n[lang.slug]) || {};
                    const optHtml = hasOptions ? `
                        <div class="lcf-input-row" style="margin-top:8px">
                            <label class="lcf-label">Các lựa chọn <small style="font-weight:400">(đúng thứ tự bản gốc, để trống dòng nào giữ nguyên dòng đó)</small></label>
                            <textarea class="widefat" rows="3" data-i18n-key="options"
                                oninput="lcfFieldI18nUpdate('${escAttr(field.id)}','${escAttr(lang.slug)}','options',this.value)"
                            >${escHtml((i18n.options || []).join('\n'))}</textarea>
                        </div>` : '';
                    const otherHtml = (canHaveOther && field.has_other) ? `
                        <div class="lcf-input-row" style="margin-top:8px">
                            <label class="lcf-label">Nhãn "Khác"</label>
                            <input type="text" class="widefat" data-i18n-key="other_label" placeholder="${escAttr(field.other_label || 'Khác')}"
                                value="${escAttr(i18n.other_label || '')}"
                                oninput="lcfFieldI18nUpdate('${escAttr(field.id)}','${escAttr(lang.slug)}','other_label',this.value)">
                        </div>` : '';

                    return `<div class="lcf-i18n-lang-group" data-lang="${escAttr(lang.slug)}">
                        <div class="lcf-i18n-lang-title">
                            <span>${escHtml(lang.name)}</span>
                            <button type="button" class="lcf-i18n-ai-btn"
                                onclick="lcfAiTranslateField('${escAttr(field.id)}','${escAttr(lang.slug)}',this)">✨ Dịch bằng AI</button>
                        </div>
                        <div class="lcf-input-grid lcf-input-grid--50-50">
                            <div class="lcf-input-row">
                                <label class="lcf-label">Nhãn (Label)</label>
                                <input type="text" class="widefat" data-i18n-key="label" placeholder="${escAttr(field.label || '')}"
                                    value="${escAttr(i18n.label || '')}"
                                    oninput="lcfFieldI18nUpdate('${escAttr(field.id)}','${escAttr(lang.slug)}','label',this.value)">
                            </div>
                            <div class="lcf-input-row">
                                <label class="lcf-label">Placeholder</label>
                                <input type="text" class="widefat" data-i18n-key="placeholder" placeholder="${escAttr(field.placeholder || '')}"
                                    value="${escAttr(i18n.placeholder || '')}"
                                    oninput="lcfFieldI18nUpdate('${escAttr(field.id)}','${escAttr(lang.slug)}','placeholder',this.value)">
                            </div>
                        </div>
                        ${optHtml}
                        ${otherHtml}
                    </div>`;
                }).join('');

                return `<div class="lcf-i18n-wrap">
                    <button type="button" class="lcf-i18n-toggle" onclick="this.closest('.lcf-i18n-wrap').classList.toggle('is-open')">
                        🌐 Dịch sang ngôn ngữ khác (${NON_DEFAULT_LANGS.length})
                    </button>
                    <div class="lcf-i18n-block">${groups}</div>
                </div>`;
            }

            // ── Khối "Dịch sang ngôn ngữ khác" cho Chữ nút Submit (tab Trường)─
            // Không phải field thật (không nằm trong rows), nên lưu riêng ở
            // styles.btn_text_i18n[lang] (object phẳng lang → text) thay vì
            // field.i18n[lang][key] như field thường (chỉ có 1 giá trị cần
            // dịch, không cần lồng thêm 1 cấp key).
            function buildBtnTextI18nBlock() {
                if (!NON_DEFAULT_LANGS.length) return '';

                const groups = NON_DEFAULT_LANGS.map(function(lang) {
                    const val = (styles.btn_text_i18n && styles.btn_text_i18n[lang.slug]) || '';
                    return `<div class="lcf-i18n-lang-group" data-lang="${escAttr(lang.slug)}">
                        <div class="lcf-i18n-lang-title">
                            <span>${escHtml(lang.name)}</span>
                            <button type="button" class="lcf-i18n-ai-btn"
                                onclick="lcfAiTranslateBtnText('${escAttr(lang.slug)}',this)">✨ Dịch bằng AI</button>
                        </div>
                        <div class="lcf-input-row">
                            <input type="text" class="widefat" data-i18n-key="btn_text" placeholder="${escAttr(styles.btn_text || DEFAULT_STYLES.btn_text)}"
                                value="${escAttr(val)}"
                                oninput="lcfBtnTextI18nUpdate('${escAttr(lang.slug)}',this.value)">
                        </div>
                    </div>`;
                }).join('');

                return `<div class="lcf-i18n-wrap">
                    <button type="button" class="lcf-i18n-toggle" onclick="this.closest('.lcf-i18n-wrap').classList.toggle('is-open')">
                        🌐 Dịch sang ngôn ngữ khác (${NON_DEFAULT_LANGS.length})
                    </button>
                    <div class="lcf-i18n-block">${groups}</div>
                </div>`;
            }

            // Vẽ lại khối dịch Chữ nút Submit — giữ trạng thái đóng/mở hiện
            // tại (keepOpen ép mở, dùng ngay sau khi dịch AI xong).
            function renderBtnTextI18n(keepOpen) {
                const container = document.getElementById('btn-text-i18n-container');
                if (!container) return;
                const wasOpen = keepOpen || !!container.querySelector('.lcf-i18n-wrap.is-open');
                container.innerHTML = buildBtnTextI18nBlock();
                if (wasOpen) {
                    const wrap = container.querySelector('.lcf-i18n-wrap');
                    if (wrap) wrap.classList.add('is-open');
                }
            }

            // ── Khối "Dịch sang ngôn ngữ khác" cho Email Khách hàng (tab Email)─
            function buildCustomerEmailI18nBlock() {
                if (!NON_DEFAULT_LANGS.length) return '';

                const defaultSub = document.getElementById('email-customer-subject') ? document.getElementById('email-customer-subject').value : '';
                const defaultBody = document.getElementById('email-customer-body') ? document.getElementById('email-customer-body').value : '';

                const groups = NON_DEFAULT_LANGS.map(function(lang) {
                    const i18n = (styles.email_customer_i18n && styles.email_customer_i18n[lang.slug]) || {};
                    const subVal = i18n.subject || '';
                    const bodyVal = i18n.body || '';

                    return `<div class="lcf-i18n-lang-group" data-lang="${escAttr(lang.slug)}">
                        <div class="lcf-i18n-lang-title">
                            <span>${escHtml(lang.name)}</span>
                            <button type="button" class="lcf-i18n-ai-btn"
                                onclick="lcfAiTranslateCustomerEmail('${escAttr(lang.slug)}',this)">✨ Dịch bằng AI</button>
                        </div>
                        <div class="lcf-input-row">
                            <label class="lcf-label">Tiêu đề (Subject)</label>
                            <input type="text" class="widefat laca-cf-email-input" data-i18n-key="subject"
                                placeholder="${escAttr(defaultSub || 'Tiêu đề email xác nhận')}"
                                value="${escAttr(subVal)}"
                                oninput="lcfCustomerEmailI18nUpdate('${escAttr(lang.slug)}','subject',this.value)">
                        </div>
                        <div class="lcf-email-body-label-row" style="margin-top:8px">
                            <label class="lcf-label">Nội dung (Body)</label>
                            <div class="lcf-email-toolbar">
                                <button type="button" onclick="lcfEmailWrap('email-customer-i18n-${escAttr(lang.slug)}','strong')" title="In đậm"><strong>B</strong></button>
                                <button type="button" onclick="lcfEmailWrap('email-customer-i18n-${escAttr(lang.slug)}','em')" title="In nghiêng"><em>I</em></button>
                                <button type="button" onclick="lcfEmailInsertLink('email-customer-i18n-${escAttr(lang.slug)}')">🔗 Link</button>
                                <button type="button" class="lcf-btn-allfields" onclick="lcfEmailInsertVar('email-customer-i18n-${escAttr(lang.slug)}','$all_fields')">+ $all_fields</button>
                            </div>
                        </div>
                        <textarea id="email-customer-i18n-${escAttr(lang.slug)}" class="widefat laca-cf-email-body laca-cf-email-input" rows="6" data-i18n-key="body"
                            placeholder="${escAttr(defaultBody || 'Nội dung email...')}"
                            oninput="lcfCustomerEmailI18nUpdate('${escAttr(lang.slug)}','body',this.value)"
                        >${escHtml(bodyVal)}</textarea>
                    </div>`;
                }).join('');

                return `<div class="lcf-i18n-wrap" style="margin-top:14px">
                    <button type="button" class="lcf-i18n-toggle" onclick="this.closest('.lcf-i18n-wrap').classList.toggle('is-open'); setTimeout(initEmailInputTracking, 50);">
                        🌐 Dịch email sang ngôn ngữ khác (${NON_DEFAULT_LANGS.length})
                    </button>
                    <div class="lcf-i18n-block">${groups}</div>
                </div>`;
            }

            function renderCustomerEmailI18n(keepOpen) {
                const container = document.getElementById('email-customer-i18n-container');
                if (!container) return;
                const wasOpen = keepOpen || !!container.querySelector('.lcf-i18n-wrap.is-open');
                container.innerHTML = buildCustomerEmailI18nBlock();
                if (wasOpen) {
                    const wrap = container.querySelector('.lcf-i18n-wrap');
                    if (wrap) wrap.classList.add('is-open');
                }
                initEmailInputTracking();
            }

            // ── Khối dịch riêng cho field "content" (chỉ có 1 ô nội dung) ──────
            function buildContentI18nBlock(field) {
                if (!NON_DEFAULT_LANGS.length) return '';

                const groups = NON_DEFAULT_LANGS.map(function(lang) {
                    const i18n = (field.i18n && field.i18n[lang.slug]) || {};
                    return `<div class="lcf-i18n-lang-group" data-lang="${escAttr(lang.slug)}">
                        <div class="lcf-i18n-lang-title">
                            <span>${escHtml(lang.name)}</span>
                            <button type="button" class="lcf-i18n-ai-btn"
                                onclick="lcfAiTranslateField('${escAttr(field.id)}','${escAttr(lang.slug)}',this)">✨ Dịch bằng AI</button>
                        </div>
                        <textarea class="widefat lcf-content-textarea" rows="3" data-i18n-key="content"
                            oninput="lcfFieldI18nUpdate('${escAttr(field.id)}','${escAttr(lang.slug)}','content',this.value)"
                        >${escHtml(i18n.content || '')}</textarea>
                    </div>`;
                }).join('');

                return `<div class="lcf-i18n-wrap">
                    <button type="button" class="lcf-i18n-toggle" onclick="this.closest('.lcf-i18n-wrap').classList.toggle('is-open')">
                        🌐 Dịch sang ngôn ngữ khác (${NON_DEFAULT_LANGS.length})
                    </button>
                    <div class="lcf-i18n-block">${groups}</div>
                </div>`;
            }

            // ── Build field card HTML ─────────────────────────────────────────
            function buildFieldCard(field) {
                // "content" là ghi chú tĩnh (không thu thập dữ liệu) — không
                // có Label/Tên biến/Placeholder/Bắt buộc/Options, chỉ 1 ô
                // soạn nội dung kèm nút chèn đậm/nghiêng/link.
                if (field.type === 'content') {
                    return buildContentFieldCard(field);
                }

                const typeLabel  = FIELD_TYPES[field.type] || field.type;
                const hasOptions = HAS_OPTIONS.includes(field.type);
                const canHaveOther = CAN_HAVE_OTHER.includes(field.type);
                const reqMark    = field.required ? ' <span style="color:#d9534f">*</span>' : '';
                const labelPrev  = field.label
                    ? escHtml(field.label)
                    : (field.placeholder ? escHtml(field.placeholder) : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>');

                const optHtml = hasOptions ? `
                    <div class="lcf-input-row" style="margin-top:8px">
                        <label class="lcf-label">Các lựa chọn <small style="font-weight:400">(mỗi dòng 1 option)</small></label>
                        <textarea class="widefat" rows="3" data-key="options"
                            placeholder="Lựa chọn 1&#10;Lựa chọn 2"
                            oninput="lcfFieldUpdate('${escAttr(field.id)}','options',this.value)"
                        >${escHtml((field.options || []).join('\n'))}</textarea>
                    </div>` : '';

                const otherHtml = canHaveOther ? `
                    <div class="lcf-input-row" style="margin-top:8px">
                        <label class="lcf-checkbox-label">
                            <input type="checkbox" data-key="has_other" ${field.has_other ? 'checked' : ''}
                                onchange="lcfFieldUpdate('${escAttr(field.id)}','has_other',this.checked)">
                            <span>Tự động thêm lựa chọn "Khác" kèm ô nhập chi tiết</span>
                        </label>
                        ${field.has_other ? `
                        <input type="text" class="widefat" data-key="other_label" style="margin-top:4px" placeholder="Nhãn cho lựa chọn Khác (mặc định: Khác)"
                            value="${escAttr(field.other_label || '')}"
                            oninput="lcfFieldUpdate('${escAttr(field.id)}','other_label',this.value)">` : ''}
                    </div>` : '';

                // Checkbox mặc định cho phép tick nhiều lựa chọn — field này
                // bật "chỉ chọn 1" thì render giống radio (dùng chung name,
                // JS tự bỏ tick các ô khác trong nhóm) nhưng vẫn style checkbox.
                // Luôn hiện cho mọi field checkbox (không ẩn/hiện theo số
                // lượng option đang gõ) — rebuild card theo mỗi keystroke của
                // ô "Các lựa chọn" sẽ làm mất focus đang gõ dở của textarea.
                // Chỉ có tác dụng thật khi field có từ 2 lựa chọn trở lên,
                // xem ContactFormAjaxHandler::renderField().
                const singleChoiceHtml = (field.type === 'checkbox') ? `
                    <div class="lcf-input-row" style="margin-top:8px">
                        <label class="lcf-checkbox-label">
                            <input type="checkbox" data-key="single_choice" ${field.single_choice ? 'checked' : ''}
                                onchange="lcfFieldUpdate('${escAttr(field.id)}','single_choice',this.checked)">
                            <span>Chỉ cho phép chọn 1 lựa chọn (giống radio button)</span>
                        </label>
                    </div>` : '';

                const visibilityBadge = field.show_label
                    ? '<span class="lcf-badge-vis is-shown" title="Nhãn hiển thị trên form">Hiện nhãn</span>'
                    : '<span class="lcf-badge-vis is-hidden" title="Nhãn bị ẩn trên form, chỉ dùng trong Email">Ẩn nhãn</span>';

                const isOpenClass = field._isOpen === false ? '' : ' is-open';
                return `<div class="laca-cf-field-card${isOpenClass}" data-field-id="${escAttr(field.id)}">
                    <div class="laca-cf-field-card-header" onclick="lcfToggleCard(this.closest('.laca-cf-field-card'))">
                        <span class="lcf-field-drag-handle" title="Kéo để di chuyển field">
                            <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor">
                                <circle cx="3" cy="2"  r="1.4"/><circle cx="7" cy="2"  r="1.4"/>
                                <circle cx="3" cy="8"  r="1.4"/><circle cx="7" cy="8"  r="1.4"/>
                                <circle cx="3" cy="14" r="1.4"/><circle cx="7" cy="14" r="1.4"/>
                            </svg>
                        </span>
                        <span class="lcf-type-badge">${escHtml(typeLabel)}</span>
                        <span class="lcf-label-preview">${labelPrev}${reqMark}${visibilityBadge}</span>
                        <span class="lcf-toggle-icon">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 1l4 4 4-4"/></svg>
                        </span>
                        <button type="button" class="lcf-duplicate-field-btn"
                            onclick="lcfDuplicateField(event,'${escAttr(field.id)}')"
                            title="Nhân bản field">⧉</button>
                        <button type="button" class="lcf-remove-field-btn"
                            onclick="lcfRemoveField(event,'${escAttr(field.id)}')"
                            title="Xoá field">✕</button>
                    </div>
                    <div class="laca-cf-field-card-body">
                        <div class="lcf-field-inputs">
                            <!-- Row 1: Nhãn (dùng cho Email / Form) + Checkboxes (Hiện nhãn ở form + Bắt buộc) -->
                            <div class="lcf-input-grid lcf-input-grid--70-30">
                                <div class="lcf-input-row">
                                    <label class="lcf-label">Nhãn (Label) <small style="font-weight:400">(dùng cho Email / Bảng dữ liệu)</small></label>
                                    <input type="text" class="widefat" data-key="label" placeholder="VD: Họ và tên"
                                        value="${escAttr(field.label)}"
                                        oninput="lcfFieldUpdate('${escAttr(field.id)}','label',this.value)">
                                </div>
                                <div class="lcf-input-row lcf-checkbox-wrap lcf-checkboxes-col">
                                    <label class="lcf-checkbox-label" title="Tích chọn nếu muốn nhãn này hiển thị trực tiếp trên form. Mặc định tắt để form gọn gàng.">
                                        <input type="checkbox" data-key="show_label" ${field.show_label ? 'checked' : ''}
                                            onchange="lcfFieldUpdate('${escAttr(field.id)}','show_label',this.checked)">
                                        <span>Hiện nhãn ở form</span>
                                    </label>
                                    <label class="lcf-checkbox-label">
                                        <input type="checkbox" data-key="required" ${field.required ? 'checked' : ''}
                                            onchange="lcfFieldUpdate('${escAttr(field.id)}','required',this.checked)">
                                        <span>Bắt buộc nhập</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Row 2: Tên biến (50%) + Placeholder (50%) -->
                            <div class="lcf-input-grid lcf-input-grid--50-50">
                                <div class="lcf-input-row">
                                    <label class="lcf-label">Tên biến (name) <span class="required">*</span></label>
                                    <input type="text" class="widefat lcf-name-input" data-key="name" placeholder="VD: ho_ten"
                                        value="${escAttr(field.name)}"
                                        oninput="lcfFieldUpdate('${escAttr(field.id)}','name',this.value)"
                                        pattern="[a-z0-9_]+" title="Chỉ dùng chữ thường, số, dấu gạch dưới">
                                    <p class="lcf-name-hint">Dùng trong email: $<strong class="lcf-name-strong">${escHtml(field.name || 'ten_bien')}</strong></p>
                                </div>
                                <div class="lcf-input-row">
                                    <label class="lcf-label">Placeholder</label>
                                    <input type="text" class="widefat" data-key="placeholder" placeholder="VD: Nhập họ và tên..."
                                        value="${escAttr(field.placeholder || '')}"
                                        oninput="lcfFieldUpdate('${escAttr(field.id)}','placeholder',this.value)">
                                </div>
                            </div>

                            ${optHtml}
                            ${singleChoiceHtml}
                            ${otherHtml}
                            ${buildI18nBlock(field)}
                        </div>
                    </div>
                </div>`;
            }

            // ── Build field card HTML riêng cho field type "content" ──────────
            function buildContentFieldCard(field) {
                const plainPreview = (field.content || '').replace(/<[^>]+>/g, '').trim();
                const labelPrev = plainPreview
                    ? escHtml(plainPreview.slice(0, 50)) + (plainPreview.length > 50 ? '…' : '')
                    : '<em style="color:#aaa;font-weight:400">Nội dung trống</em>';

                const isOpenClass = field._isOpen === false ? '' : ' is-open';
                return `<div class="laca-cf-field-card${isOpenClass}" data-field-id="${escAttr(field.id)}">
                    <div class="laca-cf-field-card-header" onclick="lcfToggleCard(this.closest('.laca-cf-field-card'))">
                        <span class="lcf-field-drag-handle" title="Kéo để di chuyển field">
                            <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor">
                                <circle cx="3" cy="2"  r="1.4"/><circle cx="7" cy="2"  r="1.4"/>
                                <circle cx="3" cy="8"  r="1.4"/><circle cx="7" cy="8"  r="1.4"/>
                                <circle cx="3" cy="14" r="1.4"/><circle cx="7" cy="14" r="1.4"/>
                            </svg>
                        </span>
                        <span class="lcf-type-badge">${escHtml(FIELD_TYPES.content || 'Nội dung')}</span>
                        <span class="lcf-label-preview">${labelPrev}</span>
                        <span class="lcf-toggle-icon">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 1l4 4 4-4"/></svg>
                        </span>
                        <button type="button" class="lcf-duplicate-field-btn"
                            onclick="lcfDuplicateField(event,'${escAttr(field.id)}')"
                            title="Nhân bản field">⧉</button>
                        <button type="button" class="lcf-remove-field-btn"
                            onclick="lcfRemoveField(event,'${escAttr(field.id)}')"
                            title="Xoá field">✕</button>
                    </div>
                    <div class="laca-cf-field-card-body">
                        <div class="lcf-field-inputs">
                            <div class="lcf-input-row">
                                <label class="lcf-label">Nội dung <small style="font-weight:400">(ghi chú tĩnh, không thu thập dữ liệu — chọn chữ rồi bấm nút để định dạng, hoặc tự gõ HTML)</small></label>
                                <div class="lcf-content-toolbar">
                                    <button type="button" onclick="lcfContentWrap('${escAttr(field.id)}','strong')" title="In đậm"><strong>B</strong></button>
                                    <button type="button" onclick="lcfContentWrap('${escAttr(field.id)}','em')" title="In nghiêng"><em>I</em></button>
                                    <button type="button" onclick="lcfContentInsertLink('${escAttr(field.id)}')" title="Chèn link">🔗 Link</button>
                                </div>
                                <textarea class="widefat lcf-content-textarea" data-key="content" rows="4"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','content',this.value)"
                                >${escHtml(field.content || '')}</textarea>
                            </div>
                            ${buildContentI18nBlock(field)}
                        </div>
                    </div>
                </div>`;
            }

            // ── Build row HTML ────────────────────────────────────────────────
            function buildRowHtml(row) {
                const colInfo = row.cols.map(function(c) {
                    return Math.round((c.span / 12) * 100) + '%';
                }).join(' / ');

                const colsHtml = row.cols.map(function(col, idx) {
                    const fieldsHtml = col.fields.map(buildFieldCard).join('');
                    const pct        = Math.round((col.span / 12) * 100);
                    return `<div class="laca-cf-col-slot" data-col-id="${escAttr(col.id)}" data-span="${col.span}" style="flex:${col.span}">
                        <div class="laca-cf-col-header">Cột ${idx + 1} <span style="opacity:0.6;font-weight:400">${pct}%</span></div>
                        <div class="laca-cf-col-drop" data-row-id="${escAttr(row.id)}" data-col-id="${escAttr(col.id)}">
                            ${fieldsHtml}
                            <div class="lcf-col-empty-hint" style="${col.fields.length ? 'display:none' : ''}">Kéo field vào đây</div>
                        </div>
                        <div class="laca-cf-col-add-field">
                            <select class="laca-cf-add-field-type">
                                ${Object.entries(FIELD_TYPES).map(([k, v]) => `<option value="${escAttr(k)}">${escHtml(v)}</option>`).join('')}
                            </select>
                            <button type="button"
                                onclick="lcfAddField('${escAttr(row.id)}','${escAttr(col.id)}',this.previousElementSibling.value)">
                                <span class="lcf-add-field-plus">+</span> Field
                            </button>
                        </div>
                    </div>`;
                }).join('');

                return `<div class="laca-cf-layout-row" data-row-id="${escAttr(row.id)}">
                    <div class="laca-cf-row-toolbar">
                        <span class="lcf-row-drag-handle" title="Kéo để di chuyển hàng">
                            <svg width="16" height="10" viewBox="0 0 16 10" fill="currentColor">
                                <rect x="0" y="0" width="16" height="1.8" rx="0.9"/>
                                <rect x="0" y="4" width="16" height="1.8" rx="0.9"/>
                                <rect x="0" y="8" width="16" height="1.8" rx="0.9"/>
                            </svg>
                        </span>
                        <span class="lcf-row-label">${escHtml(colInfo)}</span>
                        <button type="button" class="lcf-remove-row-btn"
                            onclick="lcfRemoveRow('${escAttr(row.id)}')">✕ Xoá hàng</button>
                    </div>
                    <div class="laca-cf-row-content">${colsHtml}</div>
                </div>`;
            }

            // ── Render all rows ───────────────────────────────────────────────
            function renderRows() {
                sortableInstances.forEach(function(s) { s.destroy(); });
                sortableInstances = [];

                const builder  = document.getElementById('rows-builder');
                const emptyMsg = document.getElementById('rows-empty-msg');
                builder.innerHTML = '';
                if (emptyMsg) emptyMsg.style.display = rows.length ? 'none' : '';

                rows.forEach(function(row) {
                    builder.insertAdjacentHTML('beforeend', buildRowHtml(row));
                });

                updateJsonInput();
                initSortables();
                updatePreview();
                renderEmailVariablesList();
            }

            // ── Sync data from DOM (called after drag) ────────────────────────
            function syncFromDOM() {
                // Build flat field index
                const fieldIndex = {};
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) { fieldIndex[f.id] = f; });
                    });
                });

                const newRows = [];
                document.querySelectorAll('#rows-builder > .laca-cf-layout-row').forEach(function(rowEl) {
                    const rowId  = rowEl.dataset.rowId;
                    const oldRow = rows.find(function(r) { return r.id === rowId; });
                    if (!oldRow) return;

                    const newCols = [];
                    rowEl.querySelectorAll(':scope > .laca-cf-row-content > .laca-cf-col-slot').forEach(function(slotEl) {
                        const colId = slotEl.dataset.colId;
                        const span  = parseInt(slotEl.dataset.span) || 12;

                        const newFields = [];
                        slotEl.querySelectorAll(':scope > .laca-cf-col-drop > .laca-cf-field-card').forEach(function(cardEl) {
                            const fId = cardEl.dataset.fieldId;
                            if (fieldIndex[fId]) newFields.push(fieldIndex[fId]);
                        });

                        // Update empty hint
                        const hint = slotEl.querySelector('.lcf-col-empty-hint');
                        if (hint) hint.style.display = newFields.length ? 'none' : '';

                        newCols.push({ id: colId, span, fields: newFields });
                    });

                    newRows.push({ id: rowId, cols: newCols });
                });

                rows = newRows;
                updateJsonInput();
                renderEmailVariablesList();
            }

            // ── Init SortableJS ───────────────────────────────────────────────
            function initSortables() {
                if (typeof Sortable === 'undefined') return;

                // Row-level
                sortableInstances.push(Sortable.create(document.getElementById('rows-builder'), {
                    handle:     '.lcf-row-drag-handle',
                    animation:  150,
                    group:      'layout-rows',
                    ghostClass: 'lcf-ghost',
                    onEnd: syncFromDOM,
                }));

                // Field-level per column drop zone
                document.querySelectorAll('.laca-cf-col-drop').forEach(function(colDrop) {
                    sortableInstances.push(Sortable.create(colDrop, {
                        handle:     '.lcf-field-drag-handle',
                        animation:  150,
                        group:      { name: 'form-fields', pull: true, put: true },
                        filter:     '.lcf-col-empty-hint',
                        ghostClass: 'lcf-ghost',
                        dragClass:  'lcf-dragging',
                        onEnd: syncFromDOM,
                    }));
                });
            }

            // ── Public: toggle accordion ──────────────────────────────────────
            // Ghi lại trạng thái đóng/mở vào CHÍNH field._isOpen (không chỉ
            // toggle class DOM) — renderRows() dựng lại TOÀN BỘ HTML từ đầu
            // (builder.innerHTML = '') mỗi khi có thay đổi (thêm/xoá/nhân
            // bản field, kéo thả...), nếu không lưu vào state thì mọi field
            // đang thu gọn sẽ bị bung ra hết ngay khi renderRows() chạy lại.
            window.lcfToggleCard = function(cardEl) {
                if (!cardEl) return;
                const isOpen = cardEl.classList.toggle('is-open');
                const fieldId = cardEl.dataset.fieldId;
                const found = fieldId ? findField(fieldId) : null;
                if (found) found.field._isOpen = isOpen;

                // Accordion: mở field này thì tự đóng mọi field khác (yêu
                // cầu người dùng) — chỉ toggle class DOM + cập nhật state,
                // KHÔNG renderRows() lại để không mất focus/giá trị đang gõ
                // dở ở field khác.
                if (isOpen) {
                    document.querySelectorAll('.laca-cf-field-card.is-open').forEach(function(otherCard) {
                        if (otherCard === cardEl) return;
                        otherCard.classList.remove('is-open');
                        const otherId = otherCard.dataset.fieldId;
                        const otherFound = otherId ? findField(otherId) : null;
                        if (otherFound) otherFound.field._isOpen = false;
                    });
                }
            };

            // ── Public: update field property (no re-render) ──────────────────
            window.lcfFieldUpdate = function(fieldId, key, value) {
                const found = findField(fieldId);
                if (!found) return;
                const { field } = found;

                if (key === 'options' && typeof value === 'string') {
                    field.options = value.split('\n').map(function(s){ return s.trim(); }).filter(Boolean);
                } else {
                    field[key] = value;
                }

                const cardEl = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');

                if (key === 'label' || key === 'required' || key === 'show_label') {
                    const prev = cardEl ? cardEl.querySelector('.lcf-label-preview') : null;
                    if (prev) {
                        const reqMark = field.required ? ' <span style="color:#d9534f">*</span>' : '';
                        const visBadge = field.show_label
                            ? '<span class="lcf-badge-vis is-shown" title="Nhãn hiển thị trên form">Hiện nhãn</span>'
                            : '<span class="lcf-badge-vis is-hidden" title="Nhãn bị ẩn trên form, chỉ dùng trong Email">Ẩn nhãn</span>';
                        const labelText = field.label
                            ? escHtml(field.label)
                            : (field.placeholder ? escHtml(field.placeholder) : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>');
                        prev.innerHTML = labelText + reqMark + visBadge;
                    }
                    if (key === 'label') {
                        const nameInput = cardEl ? cardEl.querySelector('.lcf-name-input') : null;
                        // Auto-slugify name
                        if (nameInput && (!field.name || field.name === field._autoName)) {
                            const slug = value.toLowerCase()
                                .replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g,'a')
                                .replace(/[èéẹẻẽêềếệểễ]/g,'e')
                                .replace(/[ìíịỉĩ]/g,'i')
                                .replace(/[òóọỏõôồốộổỗơờớợởỡ]/g,'o')
                                .replace(/[ùúụủũưừứựửữ]/g,'u')
                                .replace(/[ỳýỵỷỹ]/g,'y')
                                .replace(/đ/g,'d')
                                .replace(/[^a-z0-9]+/g,'_')
                                .replace(/^_+|_+$/g,'');
                            field.name = slug;
                            field._autoName = slug;
                            nameInput.value = slug;
                            const strong = cardEl ? cardEl.querySelector('.lcf-name-strong') : null;
                            if (strong) strong.textContent = slug || 'ten_bien';
                        }
                    }
                }

                if (key === 'name') {
                    const strong = cardEl ? cardEl.querySelector('.lcf-name-strong') : null;
                    if (strong) strong.textContent = value || 'ten_bien';
                }

                if (key === 'has_other' && cardEl) {
                    // Cần rebuild card để hiện/ẩn ô nhập "Nhãn cho lựa chọn
                    // Khác" — thay outerHTML tại chỗ (không renderRows() toàn
                    // bộ) để không làm mất trạng thái mở/đóng của các card khác.
                    const wasOpen = cardEl.classList.contains('is-open');
                    cardEl.outerHTML = buildFieldCard(field);
                    if (wasOpen) {
                        const newCard = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');
                        if (newCard) newCard.classList.add('is-open');
                    }
                }

                updateJsonInput();
                updatePreview();
                if (key === 'label' || key === 'name') {
                    renderEmailVariablesList();
                }
            };

            // ── Public: update per-language translation of a field property ───
            // Builder preview luôn hiển thị theo ngôn ngữ mặc định (xem
            // buildFormPreviewHtml()) nên không cần updatePreview() ở đây —
            // bản dịch chỉ có tác dụng ở render thật ngoài site theo
            // pll_current_language() (ContactFormAjaxHandler::renderField()).
            window.lcfFieldI18nUpdate = function(fieldId, langSlug, key, value) {
                const found = findField(fieldId);
                if (!found) return;
                const { field } = found;
                field.i18n = field.i18n || {};
                field.i18n[langSlug] = field.i18n[langSlug] || {};
                if (key === 'options' && typeof value === 'string') {
                    field.i18n[langSlug].options = value.split('\n').map(function(s){ return s.trim(); });
                } else {
                    field.i18n[langSlug][key] = value;
                }
                updateJsonInput();
            };

            // ── Public: gợi ý dịch bằng AI cho 1 field → 1 ngôn ngữ ────────────
            // Chỉ điền GỢI Ý (từ nội dung mặc định của field) vào field.i18n —
            // admin bấm mới gọi, sửa tay lại được sau đó, không tự động chạy
            // khi lưu form. Cần đã cấu hình API key ở Laca Admin > AI
            // Translation (dùng chung AITranslationHandler với tính năng dịch
            // bài viết), nếu chưa có key thì server trả lỗi rõ ràng.
            window.lcfAiTranslateField = function(fieldId, langSlug, btnEl) {
                const found = findField(fieldId);
                if (!found) return;
                const { field } = found;
                const vars = window.LacaContactFormVars.aiTranslate;
                if (!vars) return;

                const body = new URLSearchParams();
                body.set('action', 'laca_cf_ai_translate_field');
                body.set('nonce', vars.nonce);
                body.set('target_lang', langSlug);
                if (field.type === 'content') {
                    body.set('content', field.content || '');
                } else {
                    body.set('label', field.label || '');
                    body.set('placeholder', field.placeholder || '');
                    if (field.has_other) body.set('other_label', field.other_label || '');
                    if ((field.options || []).length) body.set('options', JSON.stringify(field.options));
                }

                const originalText = btnEl.textContent;
                btnEl.disabled = true;
                btnEl.textContent = 'Đang dịch…';

                fetch(vars.ajaxUrl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) {
                            Swal.fire({ title: 'Lỗi dịch AI', text: (res.data && res.data.message) || 'Không thể dịch.', icon: 'error' });
                            return;
                        }
                        field.i18n = field.i18n || {};
                        field.i18n[langSlug] = Object.assign({}, field.i18n[langSlug], res.data);
                        updateJsonInput();

                        // Vẽ lại card để hiện giá trị mới trong các ô — giữ
                        // trạng thái mở của card lẫn khối "🌐 Dịch".
                        const cardEl = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');
                        if (cardEl) {
                            const wasOpen = cardEl.classList.contains('is-open');
                            cardEl.outerHTML = buildFieldCard(field);
                            const newCard = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');
                            if (newCard) {
                                if (wasOpen) newCard.classList.add('is-open');
                                const i18nWrap = newCard.querySelector('.lcf-i18n-wrap');
                                if (i18nWrap) i18nWrap.classList.add('is-open');
                            }
                        }
                    })
                    .catch(function() {
                        Swal.fire({ title: 'Lỗi', text: 'Không thể kết nối tới máy chủ.', icon: 'error' });
                    })
                    .finally(function() {
                        btnEl.disabled = false;
                        btnEl.textContent = originalText;
                    });
            };

            // ── Public: cập nhật bản dịch Chữ nút Submit theo 1 ngôn ngữ ───────
            window.lcfBtnTextI18nUpdate = function(langSlug, value) {
                styles.btn_text_i18n = styles.btn_text_i18n || {};
                styles.btn_text_i18n[langSlug] = value;
                updateStyleInput();
            };

            // ── Public: gợi ý dịch AI cho Chữ nút Submit → 1 ngôn ngữ ──────────
            window.lcfAiTranslateBtnText = function(langSlug, btnEl) {
                const vars = window.LacaContactFormVars.aiTranslate;
                if (!vars) return;

                const body = new URLSearchParams();
                body.set('action', 'laca_cf_ai_translate_field');
                body.set('nonce', vars.nonce);
                body.set('target_lang', langSlug);
                body.set('btn_text', styles.btn_text || DEFAULT_STYLES.btn_text);

                const originalText = btnEl.textContent;
                btnEl.disabled = true;
                btnEl.textContent = 'Đang dịch…';

                fetch(vars.ajaxUrl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) {
                            Swal.fire({ title: 'Lỗi dịch AI', text: (res.data && res.data.message) || 'Không thể dịch.', icon: 'error' });
                            return;
                        }
                        if (res.data && res.data.btn_text) {
                            styles.btn_text_i18n = styles.btn_text_i18n || {};
                            styles.btn_text_i18n[langSlug] = res.data.btn_text;
                            updateStyleInput();
                            renderBtnTextI18n(true);
                        }
                    })
                    .catch(function() {
                        Swal.fire({ title: 'Lỗi', text: 'Không thể kết nối tới máy chủ.', icon: 'error' });
                    })
                    .finally(function() {
                        btnEl.disabled = false;
                        btnEl.textContent = originalText;
                    });
            };

            // ── Public: cập nhật bản dịch Email Khách hàng theo 1 ngôn ngữ ─────
            window.lcfCustomerEmailI18nUpdate = function(langSlug, key, value) {
                styles.email_customer_i18n = styles.email_customer_i18n || {};
                styles.email_customer_i18n[langSlug] = styles.email_customer_i18n[langSlug] || {};
                styles.email_customer_i18n[langSlug][key] = value;
                updateStyleInput();
            };

            // ── Public: gợi ý dịch AI cho Email Khách hàng → 1 ngôn ngữ ────────
            window.lcfAiTranslateCustomerEmail = function(langSlug, btnEl) {
                const vars = window.LacaContactFormVars.aiTranslate;
                if (!vars) return;

                const defaultSub = document.getElementById('email-customer-subject') ? document.getElementById('email-customer-subject').value : '';
                const defaultBody = document.getElementById('email-customer-body') ? document.getElementById('email-customer-body').value : '';

                const body = new URLSearchParams();
                body.set('action', 'laca_cf_ai_translate_field');
                body.set('nonce', vars.nonce);
                body.set('target_lang', langSlug);
                body.set('email_customer_subject', defaultSub);
                body.set('email_customer_body', defaultBody);

                const originalText = btnEl.textContent;
                btnEl.disabled = true;
                btnEl.textContent = 'Đang dịch…';

                fetch(vars.ajaxUrl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) {
                            Swal.fire({ title: 'Lỗi dịch AI', text: (res.data && res.data.message) || 'Không thể dịch.', icon: 'error' });
                            return;
                        }
                        styles.email_customer_i18n = styles.email_customer_i18n || {};
                        styles.email_customer_i18n[langSlug] = styles.email_customer_i18n[langSlug] || {};
                        if (res.data) {
                            if (res.data.email_customer_subject) {
                                styles.email_customer_i18n[langSlug].subject = res.data.email_customer_subject;
                            }
                            if (res.data.email_customer_body) {
                                styles.email_customer_i18n[langSlug].body = res.data.email_customer_body;
                            }
                        }
                        updateStyleInput();
                        renderCustomerEmailI18n(true);
                    })
                    .catch(function() {
                        Swal.fire({ title: 'Lỗi', text: 'Không thể kết nối tới máy chủ.', icon: 'error' });
                    })
                    .finally(function() {
                        btnEl.disabled = false;
                        btnEl.textContent = originalText;
                    });
            };

            function isHtmlDocument(str) {
                if (!str) return false;
                const s = str.toLowerCase();
                return s.includes('<!doctype') || s.includes('<html') || s.includes('<body');
            }

            // ── Toolbar actions cho soạn thảo Email ───────────────────────────
            window.lcfEmailWrap = function(textareaId, tag) {
                const ta = document.getElementById(textareaId);
                if (!ta) return;
                wrapTextareaSelection(ta, '<' + tag + '>', '</' + tag + '>');
            };

            window.lcfEmailInsertLink = function(textareaId) {
                const ta = document.getElementById(textareaId);
                if (!ta) return;
                const url = window.prompt('Nhập URL liên kết:', 'https://');
                if (!url) return;
                wrapTextareaSelection(ta, '<a href="' + escAttr(url) + '" target="_blank" rel="noopener">', '</a>');
            };

            window.lcfEmailInsertVar = function(textareaId, varName) {
                const ta = document.getElementById(textareaId);
                if (!ta) return;
                const start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
                const end   = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : ta.value.length;
                const val   = ta.value || '';
                ta.value = val.substring(0, start) + varName + val.substring(end);
                ta.focus();
                ta.selectionStart = ta.selectionEnd = start + varName.length;
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            };

            // ── Chuyển đổi chế độ soạn email: template (Mẫu chuẩn) hoặc html (HTML thô)
            window.lcfSetEmailMode = function(target, mode, isManualClick) {
                styles['email_' + target + '_mode'] = mode;
                updateStyleInput();

                // Cập nhật trạng thái nút bấm
                const toggle = document.querySelector('.lcf-email-mode-toggle[data-target="' + target + '"]');
                if (toggle) {
                    toggle.querySelectorAll('.lcf-mode-btn').forEach(function(btn) {
                        btn.classList.toggle('is-active', btn.dataset.mode === mode);
                    });
                }

                // Hiện/ẩn toolbar và hint
                const toolbar = document.getElementById('toolbar-email-' + target);
                const hint    = document.getElementById('hint-email-' + target);
                const label   = document.getElementById('label-email-' + target + '-body');

                if (toolbar) toolbar.style.display = mode === 'template' ? 'flex' : 'none';
                if (hint)    hint.style.display    = mode === 'template' ? 'block' : 'none';
                if (label)   label.textContent     = mode === 'template' ? 'Nội dung thư (Mẫu chuẩn tự đóng khung)' : 'Nội dung HTML (Toàn quyền code)';

                // Nếu chuyển từ HTML thô sang template và đang chứa full document HTML, hỏi đổi sang mẫu văn bản sạch
                const ta = document.getElementById('email-' + target + '-body');
                if (isManualClick && mode === 'template' && ta && isHtmlDocument(ta.value)) {
                    Swal.fire({
                        title: 'Đổi sang Mẫu chuẩn?',
                        text: 'Nội dung hiện tại đang là code HTML thô phức tạp. Bạn có muốn đổi sang nội dung văn bản đơn giản kèm bảng $all_fields tự động không?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Đổi sang mẫu đơn giản',
                        cancelButtonText: 'Giữ nguyên văn bản hiện tại',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (target === 'admin') {
                                ta.value = "Một liên hệ mới vừa được gửi qua website.\n\nDưới đây là thông tin chi tiết:\n$all_fields\n\nIP người gửi: $ip\nThời gian: $time - $date";
                            } else {
                                ta.value = "Chào bạn $name,\n\nCảm ơn bạn đã liên hệ với chúng tôi! Chúng tôi đã nhận được thông tin và sẽ phản hồi trong thời gian sớm nhất.\n\nThông tin bạn đã gửi:\n$all_fields\n\nTrân trọng!";
                            }
                            ta.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                }

                lcfUpdateEmailPreview(target);
            };

            // ── Public: duplicate field ────────────────────────────────────────
            window.lcfDuplicateField = function(event, fieldId) {
                event.stopPropagation(); // prevent accordion toggle
                const found = findField(fieldId);
                if (!found) return;
                const { col, field } = found;

                const clone = JSON.parse(JSON.stringify(field));
                clone.id = uid();
                clone.name = field.name ? field.name + '_copy' : '';
                clone._autoName = '';

                collapseAllFields(); // accordion: đóng mọi field khác trước khi mở thẻ vừa nhân bản
                clone._isOpen = true; // luôn mở thẻ vừa nhân bản, bất kể field gốc đang đóng/mở

                const idx = col.fields.findIndex(function(f) { return f.id === fieldId; });
                col.fields.splice(idx + 1, 0, clone);
                renderRows();

                setTimeout(function() {
                    const card = document.querySelector('.laca-cf-field-card[data-field-id="' + clone.id + '"]');
                    if (!card) return;
                    card.classList.add('is-open');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 60);
            };

            // ── Toolbar cho field "content" — bọc thẻ HTML quanh phần đang chọn
            // trong <textarea> (không dùng execCommand vì đó chỉ áp dụng cho
            // contenteditable, textarea thường phải tự cắt chuỗi theo vị trí
            // con trỏ/vùng chọn).
            function wrapTextareaSelection(textarea, before, after) {
                const start    = textarea.selectionStart;
                const end      = textarea.selectionEnd;
                const value    = textarea.value;
                const selected = value.slice(start, end) || 'text';
                textarea.value = value.slice(0, start) + before + selected + after + value.slice(end);
                textarea.focus();
                textarea.selectionStart = start + before.length;
                textarea.selectionEnd   = start + before.length + selected.length;
                // Bắn "input" thủ công để oninput (lcfFieldUpdate) chạy —
                // gán .value bằng JS không tự kích hoạt sự kiện input.
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            function getContentTextarea(fieldId) {
                const card = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');
                return card ? card.querySelector('.lcf-content-textarea') : null;
            }

            window.lcfContentWrap = function(fieldId, tag) {
                const textarea = getContentTextarea(fieldId);
                if (!textarea) return;
                wrapTextareaSelection(textarea, '<' + tag + '>', '</' + tag + '>');
            };

            window.lcfContentInsertLink = function(fieldId) {
                const textarea = getContentTextarea(fieldId);
                if (!textarea) return;
                const url = window.prompt('Nhập URL:', 'https://');
                if (!url) return;
                wrapTextareaSelection(textarea, '<a href="' + url + '" target="_blank" rel="noopener noreferrer">', '</a>');
            };

            // ── Public: add layout row ────────────────────────────────────────
            window.lcfAddRow = function(template) {
                const spans = ROW_TEMPLATES[template] || [12];
                const cols  = spans.map(function(span) {
                    return { id: uid(), span: span, fields: [] };
                });
                rows.push({ id: uid(), cols: cols });
                renderRows();
                // Scroll to new row
                const builder = document.getElementById('rows-builder');
                const last    = builder.lastElementChild;
                if (last) last.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };

            // ── Public: remove row ────────────────────────────────────────────
            window.lcfRemoveRow = function(rowId) {
                const row         = rows.find(function(r) { return r.id === rowId; });
                const totalFields = (row ? row.cols : []).reduce(function(n, c) { return n + c.fields.length; }, 0);
                if (totalFields > 0) {
                    Swal.fire({
                        title: 'Xoá hàng này?',
                        text: 'Bạn sắp xoá ' + totalFields + ' field bên trong.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Xoá',
                        cancelButtonText: 'Huỷ'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            rows = rows.filter(function(r) { return r.id !== rowId; });
                            renderRows();
                        }
                    });
                } else {
                    rows = rows.filter(function(r) { return r.id !== rowId; });
                    renderRows();
                }
            };

            // ── Public: add field to column ───────────────────────────────────
            window.lcfAddField = function(rowId, colId, type) {
                const row = rows.find(function(r) { return r.id === rowId; });
                if (!row) return;
                const col = row.cols.find(function(c) { return c.id === colId; });
                if (!col) return;

                collapseAllFields(); // accordion: đóng mọi field khác trước khi mở field mới

                const newField = {
                    id: uid(), type: type, name: '', label: '',
                    placeholder: '', required: false, show_label: false, options: [], _autoName: '',
                    has_other: false, other_label: '', content: '', i18n: {},
                    single_choice: false, _isOpen: true,
                };
                col.fields.push(newField);
                renderRows();

                // Open & focus new card
                setTimeout(function() {
                    const card = document.querySelector('.laca-cf-field-card[data-field-id="' + newField.id + '"]');
                    if (!card) return;
                    card.classList.add('is-open');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const firstInput = card.querySelector('input[type=text]');
                    if (firstInput) firstInput.focus();
                }, 60);
            };

            // ── Public: remove field ──────────────────────────────────────────
            window.lcfRemoveField = function(event, fieldId) {
                event.stopPropagation(); // prevent accordion toggle
                const found = findField(fieldId);
                if (!found) return;
                const label = found.field.label || '(chưa đặt tên)';
                
                Swal.fire({
                    title: 'Xoá field?',
                    text: 'Xoá field "' + label + '"?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Xoá',
                    cancelButtonText: 'Huỷ'
                }).then((result) => {
                    if (result.isConfirmed) {
                        rows.forEach(function(row) {
                            row.cols.forEach(function(col) {
                                col.fields = col.fields.filter(function(f) { return f.id !== fieldId; });
                            });
                        });

                        const card = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');
                        if (card) {
                            const drop = card.closest('.laca-cf-col-drop');
                            card.remove();
                            if (drop) {
                                const hint = drop.querySelector('.lcf-col-empty-hint');
                                if (hint) hint.style.display = drop.querySelectorAll('.laca-cf-field-card').length ? 'none' : '';
                            }
                        }

                        updateJsonInput();
                        updatePreview();
                    }
                });
            };

            // ── Sync all fields & translations from DOM directly ─────────────
            function syncAllFromDOM() {
                // Read directly from DOM to ensure 100% data sync before save
                document.querySelectorAll('.laca-cf-field-card').forEach(function(cardEl) {
                    const fieldId = cardEl.dataset.fieldId;
                    const found = findField(fieldId);
                    if (!found) return;
                    const { field } = found;

                    if (field.type === 'content') {
                        const contentTa = cardEl.querySelector('.lcf-field-inputs textarea[data-key="content"]');
                        if (contentTa) field.content = contentTa.value;

                        // CHỈ GHI THÊM (merge), KHÔNG xoá — field.i18n đã được
                        // cập nhật real-time qua oninput/sau khi Dịch bằng AI
                        // (lcfFieldI18nUpdate(), lcfAiTranslateField()); đọc lại
                        // DOM ở đây chỉ là lớp an toàn bổ sung. Trước đây hễ
                        // query DOM không khớp/đọc ra rỗng (vd card build lại
                        // ngay trước đó, selector lệch...) sẽ XOÁ NGAY bản dịch
                        // vừa dịch AI xong dù state JS vẫn đúng — bug thật đã
                        // gặp: dịch xong hiện đúng trên màn hình nhưng bấm Lưu
                        // xong thì rỗng.
                        cardEl.querySelectorAll('.lcf-i18n-lang-group').forEach(function(group) {
                            const langSlug = group.dataset.lang;
                            const ta = group.querySelector('textarea[data-i18n-key="content"]');
                            if (langSlug && ta && ta.value.trim() !== '') {
                                field.i18n = field.i18n || {};
                                field.i18n[langSlug] = field.i18n[langSlug] || {};
                                field.i18n[langSlug].content = ta.value;
                            }
                        });
                    } else {
                        const labelInp = cardEl.querySelector('.lcf-field-inputs input[data-key="label"]');
                        if (labelInp) field.label = labelInp.value;

                        const nameInp = cardEl.querySelector('.lcf-field-inputs input[data-key="name"]');
                        if (nameInp) field.name = nameInp.value;

                        const placeInp = cardEl.querySelector('.lcf-field-inputs input[data-key="placeholder"]');
                        if (placeInp) field.placeholder = placeInp.value;

                        const reqInp = cardEl.querySelector('.lcf-field-inputs input[data-key="required"]');
                        if (reqInp) field.required = reqInp.checked;

                        const optTa = cardEl.querySelector('.lcf-field-inputs textarea[data-key="options"]');
                        if (optTa) {
                            field.options = optTa.value.split('\n').map(function(s){ return s.trim(); }).filter(Boolean);
                        }

                        const hasOtherInp = cardEl.querySelector('.lcf-field-inputs input[data-key="has_other"]');
                        if (hasOtherInp) field.has_other = hasOtherInp.checked;

                        const singleChoiceInp = cardEl.querySelector('.lcf-field-inputs input[data-key="single_choice"]');
                        if (singleChoiceInp) field.single_choice = singleChoiceInp.checked;

                        const otherLabelInp = cardEl.querySelector('.lcf-field-inputs input[data-key="other_label"]');
                        if (otherLabelInp) field.other_label = otherLabelInp.value;

                        // CHỈ GHI THÊM (merge) khi DOM có giá trị thật, KHÔNG
                        // xoá key nào chỉ vì đọc ra rỗng — xem lý do ở nhánh
                        // "content" phía trên (field.i18n đã đúng từ trước,
                        // đây chỉ là lớp an toàn bổ sung, không phải nguồn sự
                        // thật duy nhất).
                        cardEl.querySelectorAll('.lcf-i18n-lang-group').forEach(function(group) {
                            const langSlug = group.dataset.lang;
                            if (!langSlug) return;

                            const i18nLabel = group.querySelector('input[data-i18n-key="label"]');
                            const i18nPlace = group.querySelector('input[data-i18n-key="placeholder"]');
                            const i18nOther = group.querySelector('input[data-i18n-key="other_label"]');
                            const i18nOpt   = group.querySelector('textarea[data-i18n-key="options"]');

                            const hasAnyValue = (i18nLabel && i18nLabel.value.trim() !== '')
                                || (i18nPlace && i18nPlace.value.trim() !== '')
                                || (i18nOther && i18nOther.value.trim() !== '')
                                || (i18nOpt && i18nOpt.value.trim() !== '');

                            if (!hasAnyValue) return;

                            field.i18n = field.i18n || {};
                            field.i18n[langSlug] = field.i18n[langSlug] || {};

                            if (i18nLabel && i18nLabel.value.trim() !== '') {
                                field.i18n[langSlug].label = i18nLabel.value;
                            }
                            if (i18nPlace && i18nPlace.value.trim() !== '') {
                                field.i18n[langSlug].placeholder = i18nPlace.value;
                            }
                            if (i18nOther && i18nOther.value.trim() !== '') {
                                field.i18n[langSlug].other_label = i18nOther.value;
                            }
                            if (i18nOpt) {
                                const lines = i18nOpt.value.split('\n').map(function(s){ return s.trim(); });
                                if (lines.some(function(s){ return s !== ''; })) {
                                    field.i18n[langSlug].options = lines;
                                }
                            }
                        });
                    }
                });

                // Sync btn_text_i18n
                const btnI18nContainer = document.getElementById('btn-text-i18n-container');
                if (btnI18nContainer) {
                    btnI18nContainer.querySelectorAll('.lcf-i18n-lang-group').forEach(function(group) {
                        const langSlug = group.dataset.lang;
                        const input = group.querySelector('input[data-i18n-key="btn_text"]');
                        if (langSlug && input) {
                            styles.btn_text_i18n = styles.btn_text_i18n || {};
                            if (input.value.trim() !== '') {
                                styles.btn_text_i18n[langSlug] = input.value;
                            } else {
                                delete styles.btn_text_i18n[langSlug];
                            }
                        }
                    });
                }

                // Sync email_customer_i18n
                const emailI18nContainer = document.getElementById('email-customer-i18n-container');
                if (emailI18nContainer) {
                    emailI18nContainer.querySelectorAll('.lcf-i18n-lang-group').forEach(function(group) {
                        const langSlug = group.dataset.lang;
                        if (!langSlug) return;
                        const subInp = group.querySelector('input[data-i18n-key="subject"]');
                        const bodyInp = group.querySelector('textarea[data-i18n-key="body"]');
                        styles.email_customer_i18n = styles.email_customer_i18n || {};
                        styles.email_customer_i18n[langSlug] = styles.email_customer_i18n[langSlug] || {};
                        if (subInp && subInp.value.trim() !== '') {
                            styles.email_customer_i18n[langSlug].subject = subInp.value;
                        } else {
                            delete styles.email_customer_i18n[langSlug].subject;
                        }
                        if (bodyInp && bodyInp.value.trim() !== '') {
                            styles.email_customer_i18n[langSlug].body = bodyInp.value;
                        } else {
                            delete styles.email_customer_i18n[langSlug].body;
                        }
                        if (Object.keys(styles.email_customer_i18n[langSlug]).length === 0) {
                            delete styles.email_customer_i18n[langSlug];
                        }
                    });
                }
            }

            // ── Form submit validation ────────────────────────────────────────
            document.getElementById('laca-cf-form').addEventListener('submit', function(e) {
                syncAllFromDOM();

                if (!document.getElementById('cf-name').value.trim()) {
                    e.preventDefault();
                    Swal.fire({ title: 'Lỗi', text: 'Vui lòng nhập tên form.', icon: 'error' });
                    return;
                }
                const allFields = [];
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) { allFields.push(f); });
                    });
                });
                for (let i = 0; i < allFields.length; i++) {
                    const f = allFields[i];
                    // "content" là ghi chú tĩnh, không có tên biến (không thu
                    // thập dữ liệu) nên bỏ qua check này.
                    if (f.type === 'content') {
                        continue;
                    }
                    // Label không bắt buộc (nhiều field chỉ dùng placeholder
                    // làm gợi ý hiển thị) — chỉ "name" (dùng làm key dữ liệu)
                    // mới thực sự bắt buộc.
                    if (!f.name) {
                        e.preventDefault();
                        Swal.fire({ title: 'Lỗi', text: 'Field "' + (f.label || f.type) + '" cần có tên biến (name).', icon: 'error' });
                        return;
                    }
                }
                updateJsonInput();
                // Ensure style state is synced before submit
                if (typeof updateStyleInput === 'function') updateStyleInput();
            });

            // ── Sync styles hidden input ──────────────────────────────────────
            function updateStyleInput() {
                document.getElementById('style-json-input').value = JSON.stringify(styles);
            }

            // ── Style update ──────────────────────────────────────────────────
            window.lcfStyleUpdate = function(key, value) {
                if (key === 'btn_border_radius' || key === 'input_border_radius' || key === 'popup_border_radius') {
                    styles[key] = Math.max(0, Math.min(50, parseInt(value) || 0));
                } else {
                    styles[key] = value;
                }
                updateStyleInput();
                updatePreview();
                // Sync text <-> color
                const textMap = {
                    primary_color: 's-primary-color-text', secondary_color: 's-secondary-color-text',
                    input_border_color: 's-input-border-text', label_color: 's-label-color-text',
                    popup_success_color: 'popup-success-color-text', popup_error_color: 'popup-error-color-text',
                    popup_button_color: 'popup-button-color-text',
                };
                if (textMap[key]) {
                    const el = document.getElementById(textMap[key]);
                    if (el && el !== document.activeElement) el.value = value;
                }
                if (key === 'primary_color') {
                    lcfUpdateEmailPreview('admin');
                    lcfUpdateEmailPreview('customer');
                }
            };

            // ── Init style controls from saved state ──────────────────────────
            function initStyleControls() {
                const map = [
                    ['s-primary-color',    's-primary-color-text',    'primary_color'],
                    ['s-secondary-color',  's-secondary-color-text',  'secondary_color'],
                    ['s-input-border',     's-input-border-text',     'input_border_color'],
                    ['s-label-color',      's-label-color-text',      'label_color'],
                ];
                map.forEach(function(item) {
                    var picker = document.getElementById(item[0]);
                    var text   = document.getElementById(item[1]);
                    var val    = styles[item[2]] || DEFAULT_STYLES[item[2]];
                    if (picker) picker.value = val;
                    if (text)   text.value   = val;
                });
                // Màu/bo góc/CSS popup (popup_success_color, popup_error_color,
                // popup_button_color, popup_border_radius, popup_custom_css) +
                // mọi field popup mới (title/desc/icon/nút đóng/tự ẩn) đều do
                // popupCtl.initAll() xử lý chung (xem createPopupController()).

                var btnR = document.getElementById('s-btn-radius');
                var btnN = document.getElementById('s-btn-radius-num');
                var inpR = document.getElementById('s-input-radius');
                var inpN = document.getElementById('s-input-radius-num');
                var btnT = document.getElementById('s-btn-text');
                var inpS = document.getElementById('s-input-spacing');
                var cusC = document.getElementById('s-custom-css');
                var subA = document.getElementById('s-submit-align');

                if (btnR) btnR.value = styles.btn_border_radius;
                if (btnN) btnN.value = styles.btn_border_radius;
                if (inpR) inpR.value = styles.input_border_radius;
                if (inpN) inpN.value = styles.input_border_radius;
                if (btnT) btnT.value = styles.btn_text || DEFAULT_STYLES.btn_text;
                if (inpS) inpS.value = styles.input_spacing || '';
                if (cusC) cusC.value = styles.custom_css || '';
                if (subA) subA.value = styles.submit_align || DEFAULT_STYLES.submit_align;

                // Khởi tạo chế độ soạn email (Mẫu chuẩn vs HTML thô)
                var adminTa = document.getElementById('email-admin-body');
                var adminMode = styles.email_admin_mode || (adminTa && isHtmlDocument(adminTa.value) ? 'html' : 'template');
                lcfSetEmailMode('admin', adminMode, false);

                var custTa = document.getElementById('email-customer-body');
                var custMode = styles.email_customer_mode || (custTa && isHtmlDocument(custTa.value) ? 'html' : 'template');
                lcfSetEmailMode('customer', custMode, false);
            }

            // ── Build live form preview HTML ───────────────────────────────────
            function buildFieldPreviewHtml(field) {
                var type        = field.type || 'text';
                var label       = field.label || '';
                var placeholder = field.placeholder || '';
                var req         = field.required;
                var html        = '<div class="lcf-pv-field-row">';
                // Chỉ hiển thị nhãn trên form nếu field.show_label được tích chọn
                if (type !== 'hidden' && label && field.show_label) {
                    html += '<label class="lcf-pv-label">' + escHtml(label);
                    if (req) html += ' <span style="color:#e53e3e">*</span>';
                    html += '</label>';
                }
                switch (type) {
                    case 'content':
                        html += '<div class="lcf-pv-content">' + (field.content || '<em style="color:#aaa">(chưa nhập nội dung)</em>') + '</div>';
                        break;
                    case 'textarea':
                        html += '<textarea class="lcf-pv-input" placeholder="' + escAttr(placeholder) + '" rows="3" disabled></textarea>';
                        break;
                    case 'select':
                        html += '<select class="lcf-pv-input" disabled><option>— Chọn ' + escHtml(label || placeholder || '—') + ' —</option>';
                        (field.options || []).forEach(function(opt) { html += '<option>' + escHtml(opt) + '</option>'; });
                        html += '</select>';
                        break;
                    case 'radio':
                        html += '<div>';
                        (field.options || []).forEach(function(opt) {
                            html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px"><input type="radio" disabled> ' + escHtml(opt) + '</label>';
                        });
                        if (field.has_other) {
                            html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px"><input type="radio" disabled> ' + escHtml(field.other_label || 'Khác') + '</label>';
                            html += '<input type="text" class="lcf-pv-input" placeholder="Vui lòng ghi rõ…" disabled style="margin-top:2px">';
                        }
                        html += '</div>';
                        break;
                    case 'checkbox':
                        var opts = field.options || [];
                        if (opts.length <= 1 && !field.has_other) {
                            html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" disabled> ' + escHtml(opts[0] || 'yes') + '</label>';
                        } else {
                            html += '<div>';
                            opts.forEach(function(opt) {
                                html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px"><input type="checkbox" disabled> ' + escHtml(opt) + '</label>';
                            });
                            if (field.has_other) {
                                html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px"><input type="checkbox" disabled> ' + escHtml(field.other_label || 'Khác') + '</label>';
                                html += '<input type="text" class="lcf-pv-input" placeholder="Vui lòng ghi rõ…" disabled style="margin-top:2px">';
                            }
                            html += '</div>';
                        }
                        break;
                    case 'hidden': break;
                    default:
                        var inputType = {email:'email', phone:'tel', number:'number', url:'url'}[type] || 'text';
                        html += '<input type="' + escAttr(inputType) + '" class="lcf-pv-input" placeholder="' + escAttr(placeholder) + '" disabled>';
                }
                html += '</div>';
                return html;
            }

            function buildFormPreviewHtml() {
                var hasAnyField = rows.some(function(r) {
                    return r.cols.some(function(c) { return c.fields.length > 0; });
                });
                if (!hasAnyField) {
                    return '<div class="lcf-pv-empty">Chưa có field nào. Thêm field ở tab Trường để xem preview.</div>';
                }

                var vars = [
                    '--lcf-primary:'       + (styles.primary_color      || DEFAULT_STYLES.primary_color),
                    '--lcf-secondary:'     + (styles.secondary_color     || DEFAULT_STYLES.secondary_color),
                    '--lcf-input-border:'  + (styles.input_border_color  || DEFAULT_STYLES.input_border_color),
                    '--lcf-label-color:'   + (styles.label_color         || DEFAULT_STYLES.label_color),
                    '--lcf-btn-radius:'    + parseInt(styles.btn_border_radius !== undefined ? styles.btn_border_radius : DEFAULT_STYLES.btn_border_radius) + 'px',
                    '--lcf-input-radius:'  + parseInt(styles.input_border_radius !== undefined ? styles.input_border_radius : DEFAULT_STYLES.input_border_radius) + 'px',
                ];
                if (styles.input_spacing) {
                    var sp = styles.input_spacing.replace(/[^0-9px\s]/g, '');
                    if (sp) vars.push('--lcf-input-spacing:' + sp);
                }
                if (styles.show_label === false) {
                    vars.push('--lcf-label-display:none');
                }
                
                var btnText = styles.btn_text || DEFAULT_STYLES.btn_text;
                var html = '<div id="lcf-preview-wrapper" style="' + escAttr(vars.join(';')) + '">';
                
                if (styles.custom_css) {
                    var css = styles.custom_css.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/__FORM__/g, '#lcf-preview-wrapper');
                    html += '<style>' + css + '</style>';
                }
                
                html += '<form class="lcf-pv-form" onsubmit="return false">';

                rows.forEach(function(row) {
                    var hasField = row.cols.some(function(c) { return c.fields.length > 0; });
                    if (!hasField) return;
                    var gridCols = row.cols.map(function(c) { return c.span + 'fr'; }).join(' ');
                    html += '<div class="lcf-pv-row" style="display:grid;grid-template-columns:' + escAttr(gridCols) + ';gap:12px">';
                    row.cols.forEach(function(col) {
                        if (!col.fields.length) { html += '<div></div>'; return; }
                        html += '<div style="display:flex;flex-direction:column;gap:10px">';
                        col.fields.forEach(function(f) { html += buildFieldPreviewHtml(f); });
                        html += '</div>';
                    });
                    html += '</div>';
                });

                var submitJustify = SUBMIT_ALIGN_TO_JUSTIFY[styles.submit_align] || SUBMIT_ALIGN_TO_JUSTIFY[DEFAULT_STYLES.submit_align];
                html += '<div style="display:flex;justify-content:' + submitJustify + ';margin-top:4px">';
                html += '<button type="button" class="lcf-pv-btn">' + escHtml(btnText) + '</button>';
                html += '</div>';
                html += '</form></div>';
                return html;
            }

            function updatePreview() {
                var out = document.getElementById('lcf-form-preview-output');
                if (out) out.innerHTML = buildFormPreviewHtml();
            }

            // ── Dynamic Email Variables List ────────────────────────────────────
            let lastActiveEmailInput = null;

            function onEmailInputFocus(e) {
                lastActiveEmailInput = e.target;
            }

            function initEmailInputTracking() {
                document.querySelectorAll('.laca-cf-email-input').forEach(function(el) {
                    el.removeEventListener('focus', onEmailInputFocus);
                    el.removeEventListener('click', onEmailInputFocus);
                    el.addEventListener('focus', onEmailInputFocus);
                    el.addEventListener('click', onEmailInputFocus);
                });
            }

            function renderEmailVariablesList() {
                const container = document.getElementById('lcf-email-vars-content');
                if (!container) return;

                const formVars = [];
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) {
                            if (f.type !== 'content') {
                                const defaultLabel = f.label || f.placeholder || (f.type === 'email' ? 'Email' : (f.type === 'phone' ? 'Số điện thoại' : (f.type === 'textarea' ? 'Nội dung' : f.type)));
                                formVars.push({
                                    name: f.name ? f.name.trim() : '',
                                    label: defaultLabel.trim(),
                                    type: f.type,
                                });
                            }
                        });
                    });
                });

                let html = '<div class="lcf-email-vars-section">';
                html += '<div class="lcf-email-vars-subhead">Biến từ các trường trong form:</div>';
                html += '<div class="lcf-email-vars-list">';

                // Smart tag: $all_fields
                html += '<button type="button" class="lcf-var-tag is-all-fields" data-orig-badge="Bảng toàn bộ dữ liệu" onclick="lcfClickVar(this, \'$all_fields\')" title="Chèn toàn bộ dữ liệu người dùng gửi vào email dưới dạng bảng đẹp mắt">'
                    + '<code>$all_fields</code>'
                    + '<span class="lcf-var-label lcf-var-badge">Bảng toàn bộ dữ liệu</span>'
                    + '</button>';

                if (formVars.length === 0) {
                    html += '<span class="lcf-email-vars-empty">Chưa có trường nào. Hãy thêm trường ở tab <strong>Trường</strong>.</span>';
                } else {
                    formVars.forEach(function(v) {
                        if (v.name) {
                            const varStr = '$' + v.name;
                            html += '<button type="button" class="lcf-var-tag" data-orig-badge="' + escAttr(v.label) + '" onclick="lcfClickVar(this, \'' + escAttr(varStr) + '\')" title="Click để chèn/copy ' + escAttr(varStr) + '">'
                                + '<code>' + escHtml(varStr) + '</code>'
                                + '<span class="lcf-var-label lcf-var-badge">' + escHtml(v.label) + '</span>'
                                + '</button>';
                        } else {
                            html += '<span class="lcf-var-tag is-missing-name" title="Trường này chưa đặt tên biến! Qua tab Trường để đặt tên biến.">'
                                + '<span class="dashicons dashicons-warning" style="font-size:14px;width:14px;height:14px;line-height:1"></span>'
                                + '<span>' + escHtml(v.label || v.type) + '</span>'
                                + '<em style="font-size:11px;opacity:0.8">(Chưa có tên biến)</em>'
                                + '</span>';
                        }
                    });
                }
                html += '</div></div>';

                // System variables
                html += '<div class="lcf-email-vars-section">';
                html += '<div class="lcf-email-vars-subhead">Biến hệ thống:</div>';
                html += '<div class="lcf-email-vars-list">';
                const sysVars = [
                    { name: '$ip', label: 'IP người gửi' },
                    { name: '$date', label: 'Ngày gửi' },
                    { name: '$time', label: 'Giờ gửi' },
                ];
                sysVars.forEach(function(v) {
                    html += '<button type="button" class="lcf-var-tag is-system" data-orig-badge="' + escAttr(v.label) + '" onclick="lcfClickVar(this, \'' + escAttr(v.name) + '\')" title="Click để chèn/copy ' + escAttr(v.name) + '">'
                        + '<code>' + escHtml(v.name) + '</code>'
                        + '<span class="lcf-var-label lcf-var-badge">' + escHtml(v.label) + '</span>'
                        + '</button>';
                });
                html += '</div></div>';

                container.innerHTML = html;
            }

            window.lcfClickVar = function(btn, varName) {
                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(varName).catch(function() {});
                }

                // Insert into active input if available
                if (lastActiveEmailInput && document.body.contains(lastActiveEmailInput)) {
                    const start = typeof lastActiveEmailInput.selectionStart === 'number' ? lastActiveEmailInput.selectionStart : lastActiveEmailInput.value.length;
                    const end   = typeof lastActiveEmailInput.selectionEnd === 'number' ? lastActiveEmailInput.selectionEnd : lastActiveEmailInput.value.length;
                    const val   = lastActiveEmailInput.value || '';
                    lastActiveEmailInput.value = val.substring(0, start) + varName + val.substring(end);
                    lastActiveEmailInput.focus();
                    lastActiveEmailInput.selectionStart = lastActiveEmailInput.selectionEnd = start + varName.length;

                    // Trigger update preview if email body
                    if (lastActiveEmailInput.id === 'email-admin-body') {
                        lcfUpdateEmailPreview('admin');
                    } else if (lastActiveEmailInput.id === 'email-customer-body') {
                        lcfUpdateEmailPreview('customer');
                    }
                }

                // Visual feedback on button
                btn.classList.add('is-copied');
                const badge = btn.querySelector('.lcf-var-badge');
                const origText = btn.dataset.origBadge || (badge ? badge.textContent : '');
                btn.dataset.origBadge = origText;
                if (badge) {
                    badge.textContent = '✓ Đã chèn/copy';
                }
                setTimeout(function() {
                    btn.classList.remove('is-copied');
                    if (badge) {
                        badge.textContent = btn.dataset.origBadge;
                    }
                }, 1200);
            };

            // ── Email preview ──────────────────────────────────────────────────
            function buildSampleEmailData() {
                var dummy = {
                    ip: '192.168.1.1',
                    date: new Date().toISOString().slice(0, 10),
                    time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                };
                var flat = [];
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) {
                            if (f.type !== 'content' && f.name) {
                                var val = 'Nội dung mẫu';
                                if (f.type === 'email') val = 'nguyenvanan@example.com';
                                else if (f.type === 'phone') val = '0912 345 678';
                                else if (f.type === 'textarea') val = 'Tôi muốn tìm hiểu thêm về sản phẩm và đặt lịch hẹn tư vấn.';
                                else if (f.type === 'select' || f.type === 'radio') {
                                    val = (f.options && f.options[0]) ? (f.options[0].label || f.options[0].value) : 'Tuỳ chọn 1';
                                } else if (f.type === 'checkbox') {
                                    val = 'Đã chọn';
                                } else if (f.label && f.label.toLowerCase().includes('tên')) {
                                    val = 'Nguyễn Văn An';
                                }
                                dummy[f.name] = val;
                                flat.push({
                                    name: f.name,
                                    label: f.label || f.placeholder || (f.type === 'email' ? 'Email' : (f.type === 'phone' ? 'Số điện thoại' : f.name)),
                                    val: val
                                });
                            }
                        });
                    });
                });

                // Build $all_fields sample table
                var tableRows = '';
                flat.forEach(function(item) {
                    tableRows += '<tr>'
                        + '<td style="padding:10px 14px;background:#f8fafc;color:#475569;font-weight:600;width:38%;border-bottom:1px solid #e2e8f0;font-size:13px;vertical-align:top">' + escHtml(item.label) + '</td>'
                        + '<td style="padding:10px 14px;color:#1e293b;border-bottom:1px solid #e2e8f0;font-size:13px;vertical-align:top">' + escHtml(item.val).replace(/\n/g, '<br>') + '</td>'
                        + '</tr>';
                });
                var allFieldsHtml = '<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:16px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden">'
                    + '<tbody>' + (tableRows || '<tr><td style="padding:12px;color:#94a3b8;font-style:italic">Chưa có trường dữ liệu</td></tr>') + '</tbody>'
                    + '</table>';

                return { dummy: dummy, flat: flat, allFieldsHtml: allFieldsHtml };
            }

            window.lcfUpdateEmailPreview = function(which) {
                var taId  = which === 'admin' ? 'email-admin-body' : 'email-customer-body';
                var outId = which === 'admin' ? 'lcf-email-admin-preview-output' : 'lcf-email-customer-preview-output';
                var ta    = document.getElementById(taId);
                var out   = document.getElementById(outId);
                if (!ta || !out) return;

                var body = ta.value;
                if (!body.trim()) {
                    out.innerHTML = '<div style="padding:40px 20px;text-align:center;color:#94a3b8;font-size:13px"><em>(Nội dung thư đang để trống)</em></div>';
                    return;
                }

                var mode = styles['email_' + which + '_mode'] || (isHtmlDocument(body) ? 'html' : 'template');
                var sample = buildSampleEmailData();

                // Thay thế $all_fields
                var rendered = body.split('$all_fields').join(sample.allFieldsHtml);

                // Thay thế các biến fields & system
                var keys = Object.keys(sample.dummy).sort(function(a, b) { return b.length - a.length; });
                keys.forEach(function(k) {
                    rendered = rendered.split('$' + k).join(escHtml(sample.dummy[k]));
                });

                if (mode === 'template' || !isHtmlDocument(rendered)) {
                    // Title/Subject header
                    var subjId = which === 'admin' ? 'email-admin-subject' : 'email-customer-subject';
                    var subjInp = document.getElementById(subjId);
                    var subjText = subjInp ? subjInp.value : '';
                    keys.forEach(function(k) {
                        subjText = subjText.split('$' + k).join(sample.dummy[k]);
                    });
                    if (!subjText.trim()) {
                        subjText = which === 'admin' ? 'Thông báo liên hệ mới' : 'Cảm ơn bạn đã liên hệ';
                    }

                    // Format plain text newlines to <br>
                    var formattedBody = rendered.replace(/\n/g, '<br>');
                    var primaryColor = styles.primary_color || '#2271b1';
                    var siteName = (window.LacaContactFormVars && window.LacaContactFormVars.siteName) ? window.LacaContactFormVars.siteName : 'Lix Roastery';

                    var logoUrl = (window.LacaContactFormVars && window.LacaContactFormVars.logoUrl) || '';
                    var logoHtml = logoUrl
                        ? '<div style="padding:18px 24px;text-align:center;background:#ffffff;border-bottom:1px solid #e2e8f0">'
                            + '<img src="' + escAttr(logoUrl) + '" alt="' + escAttr(siteName) + '" style="max-height:36px;max-width:200px;height:auto;display:inline-block">'
                            + '</div>'
                        : '';

                    var htmlCard = '<div style="background:#f1f5f9;padding:24px 14px;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;line-height:1.6;box-sizing:border-box">'
                        + '<div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);text-align:left">'
                        + logoHtml
                        + '<div style="background:' + escAttr(primaryColor) + ';padding:20px 24px;color:#ffffff">'
                        + '<h3 style="margin:0;font-size:16px;font-weight:700;letter-spacing:-0.2px;color:#ffffff">' + escHtml(subjText) + '</h3>'
                        + '<p style="margin:4px 0 0;font-size:12px;opacity:0.9;color:#ffffff">' + escHtml(siteName) + '</p>'
                        + '</div>'
                        + '<div style="padding:24px;font-size:13.5px;color:#334155;line-height:1.7">'
                        + formattedBody
                        + '</div>'
                        + '<div style="padding:12px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11.5px;color:#94a3b8">'
                        + '<span>' + escHtml(siteName) + ' &bull; Email tự động</span>'
                        + '</div>'
                        + '</div>'
                        + '</div>';

                    out.innerHTML = htmlCard;
                } else {
                    out.innerHTML = '<div class="lcf-pv-email-content is-html">' + rendered + '</div>';
                }
            };

            // ── Tab switching ──────────────────────────────────────────────────
            document.querySelectorAll('.lcf-tab-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tab = btn.dataset.tab;
                    document.querySelectorAll('.lcf-tab-btn').forEach(function(b) { b.classList.remove('is-active'); });
                    document.querySelectorAll('.lcf-tab-panel').forEach(function(p) { p.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    var panel = document.getElementById('lcf-panel-' + tab);
                    if (panel) panel.classList.add('is-active');
                    // Show email preview when switching to email tab
                    if (tab === 'emails') {
                        renderEmailVariablesList();
                        renderCustomerEmailI18n();
                        initEmailInputTracking();
                        lcfUpdateEmailPreview('admin');
                        lcfUpdateEmailPreview('customer');
                    }
                });
            });

            // ── Preview tab switching ──────────────────────────────────────────
            document.querySelectorAll('.lcf-pv-tab').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var pv = btn.dataset.pv;
                    document.querySelectorAll('.lcf-pv-tab').forEach(function(b) { b.classList.remove('is-active'); });
                    document.querySelectorAll('.lcf-pv-panel').forEach(function(p) { p.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    var panel = document.getElementById('lcf-pv-' + pv);
                    if (panel) panel.classList.add('is-active');
                });
            });

            // ── Popup Thông báo (Thành công/Thất bại) — state = styles (per-form) ──
            const popupCtl = createPopupController(
                styles,
                updateStyleInput,
                NON_DEFAULT_LANGS,
                window.LacaContactFormVars.aiTranslate || null
            );
            window.lcfPopupFieldUpdate = popupCtl.update;
            window.lcfPopupI18nUpdate = popupCtl.i18nUpdate;
            window.lcfAiTranslatePopupField = popupCtl.aiTranslate;
            window.lcfPopupIconPick = popupCtl.iconPick;
            window.lcfPopupPreview = popupCtl.preview;

            // Bật/tắt tuỳ chỉnh Popup riêng cho form này (mặc định dùng cài
            // đặt chung) — ẩn mờ + disable toàn bộ input bên trong, KHÔNG
            // xoá khỏi DOM để giữ nguyên giá trị đã nhập khi tích lại sau.
            window.lcfPopupOverrideToggle = function(checked) {
                styles.popup_override = checked;
                updateStyleInput();
                const block = document.getElementById('popup-fields-block');
                if (!block) return;
                block.style.opacity = checked ? '' : '0.45';
                block.querySelectorAll('input, select, textarea, button').forEach(function(el) {
                    el.disabled = !checked;
                });
            };

            // ── Init ──────────────────────────────────────────────────────────
            initStyleControls();
            popupCtl.initAll();
            window.lcfPopupOverrideToggle(!!styles.popup_override);
            renderBtnTextI18n();
            renderCustomerEmailI18n();
            renderRows();
            initEmailInputTracking();
            renderEmailVariablesList();
            lcfUpdateEmailPreview('admin');
            lcfUpdateEmailPreview('customer');
        }
});

// Global actions for Submissions & List Views
document.addEventListener('DOMContentLoaded', () => {

    // ── Panel "Cài đặt Popup & Thông báo chung" (trang danh sách form) ──
    // state = popupDefaults (KHÁC đối tượng styles của trang sửa form) —
    // dùng CHUNG createPopupController() (định nghĩa ở module scope phía
    // trên) nên window.lcfPopup* ở đây trỏ về đúng state riêng của trang này.
    if (window.LacaCfPopupDefaultsVars) {
        const popupDefaults = Object.assign({}, window.LacaCfPopupDefaultsVars.values || {});
        const nonDefaultLangs = (window.LacaCfPopupDefaultsVars.languages || []).filter(function(l) { return !l.is_default; });

        function updatePopupDefaultsInput() {
            const input = document.getElementById('popup-json-input');
            if (input) input.value = JSON.stringify(popupDefaults);
        }

        const popupCtl = createPopupController(
            popupDefaults,
            updatePopupDefaultsInput,
            nonDefaultLangs,
            window.LacaCfPopupDefaultsVars.aiTranslate || null
        );
        window.lcfPopupFieldUpdate = popupCtl.update;
        window.lcfPopupI18nUpdate = popupCtl.i18nUpdate;
        window.lcfAiTranslatePopupField = popupCtl.aiTranslate;
        window.lcfPopupIconPick = popupCtl.iconPick;
        window.lcfPopupPreview = popupCtl.preview;
        // Trang này không có khái niệm "primary_color" riêng form nào —
        // lcfStyleUpdate (dùng cho popup_success_color/error_color/button_color/
        // border_radius/custom_css) cần tồn tại y hệt trang sửa form.
        window.lcfStyleUpdate = function(key, value) {
            if (key === 'popup_border_radius') {
                value = Math.max(0, Math.min(40, parseInt(value, 10) || 0));
            }
            popupDefaults[key] = value;
            updatePopupDefaultsInput();
            const textMap = {
                popup_success_color: 'popup-success-color-text',
                popup_error_color: 'popup-error-color-text',
                popup_button_color: 'popup-button-color-text',
            };
            if (textMap[key]) {
                const el = document.getElementById(textMap[key]);
                if (el && el !== document.activeElement) el.value = value;
            }
        };

        // popupCtl.initAll() đã tự fill cả popup_success_color/popup_error_color/
        // popup_button_color/popup_border_radius/popup_custom_css (cùng field
        // list "simple" dùng chung với trang sửa form), không cần lặp lại.
        popupCtl.initAll();
        updatePopupDefaultsInput();

        const form = document.getElementById('popup-defaults-form');
        if (form) {
            form.addEventListener('submit', function() {
                updatePopupDefaultsInput();
            });
        }
    }

    // Delete form from list
    document.querySelectorAll('.laca-cf-delete-form').forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            Swal.fire({
                title: 'Xoá form này?',
                text: 'Xoá form này và toàn bộ submissions? Không thể khôi phục.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xoá',
                cancelButtonText: 'Huỷ'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // Delete single submission
    document.querySelectorAll('.laca-cf-delete-sub').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const href = btn.getAttribute('href');
            Swal.fire({
                title: 'Xoá submission?',
                text: 'Hành động này không thể khôi phục!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xoá',
                cancelButtonText: 'Huỷ'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
    });

    // Mark as read click enhancement (optional)
});
