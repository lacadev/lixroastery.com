import Swal from 'sweetalert2';
            document.addEventListener("DOMContentLoaded", function() {
if (window.LacaContactFormVars) {
const FIELD_TYPES = window.LacaContactFormVars.FIELD_TYPES;
            const HAS_OPTIONS = ['select', 'multiselect', 'radio', 'checkbox'];

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

            // ── Styles state ──────────────────────────────────────────────────
            const DEFAULT_STYLES = {
                primary_color: '#2271b1', secondary_color: '#1a5a9e',
                input_border_color: '#cccccc', label_color: '#333333',
                btn_border_radius: 6, input_border_radius: 6,
                btn_text: 'Gửi thông tin',
            };
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

            // ── Build field card HTML ─────────────────────────────────────────
            function buildFieldCard(field) {
                const typeLabel  = FIELD_TYPES[field.type] || field.type;
                const hasOptions = HAS_OPTIONS.includes(field.type);
                const reqMark    = field.required ? ' <span style="color:#d9534f">*</span>' : '';
                const labelPrev  = field.label
                    ? escHtml(field.label)
                    : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>';

                const optHtml = hasOptions ? `
                    <div class="lcf-input-row" style="margin-top:10px">
                        <label class="lcf-label">Các lựa chọn <small style="font-weight:400">(mỗi dòng 1 option)</small></label>
                        <textarea class="widefat" rows="3"
                            placeholder="Lựa chọn 1&#10;Lựa chọn 2"
                            oninput="lcfFieldUpdate('${escAttr(field.id)}','options',this.value.split('\\n').map(function(s){return s.trim();}).filter(Boolean))"
                        >${escHtml((field.options || []).join('\n'))}</textarea>
                    </div>` : '';

                return `<div class="laca-cf-field-card" data-field-id="${escAttr(field.id)}">
                    <div class="laca-cf-field-card-header" onclick="lcfToggleCard(this.closest('.laca-cf-field-card'))">
                        <span class="lcf-field-drag-handle" title="Kéo để di chuyển field">
                            <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor">
                                <circle cx="3" cy="2"  r="1.4"/><circle cx="7" cy="2"  r="1.4"/>
                                <circle cx="3" cy="8"  r="1.4"/><circle cx="7" cy="8"  r="1.4"/>
                                <circle cx="3" cy="14" r="1.4"/><circle cx="7" cy="14" r="1.4"/>
                            </svg>
                        </span>
                        <span class="lcf-type-badge">${escHtml(typeLabel)}</span>
                        <span class="lcf-label-preview">${labelPrev}${reqMark}</span>
                        <span class="lcf-toggle-icon">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 1l4 4 4-4"/></svg>
                        </span>
                        <button type="button" class="lcf-remove-field-btn"
                            onclick="lcfRemoveField(event,'${escAttr(field.id)}')"
                            title="Xoá field">✕</button>
                    </div>
                    <div class="laca-cf-field-card-body">
                        <div class="lcf-field-inputs">
                            <div class="lcf-input-row">
                                <label class="lcf-label">Nhãn (Label) <span class="required">*</span></label>
                                <input type="text" class="widefat" placeholder="VD: Họ và tên"
                                    value="${escAttr(field.label)}"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','label',this.value)">
                            </div>
                            <div class="lcf-input-row">
                                <label class="lcf-label">Tên biến (name) <span class="required">*</span></label>
                                <input type="text" class="widefat lcf-name-input" placeholder="VD: ho_ten"
                                    value="${escAttr(field.name)}"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','name',this.value)"
                                    pattern="[a-z0-9_]+" title="Chỉ dùng chữ thường, số, dấu gạch dưới">
                                <p class="lcf-name-hint">Dùng trong email: $<strong class="lcf-name-strong">${escHtml(field.name || 'ten_bien')}</strong></p>
                            </div>
                            <div class="lcf-input-row">
                                <label class="lcf-label">Placeholder</label>
                                <input type="text" class="widefat"
                                    value="${escAttr(field.placeholder || '')}"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','placeholder',this.value)">
                            </div>
                            <div class="lcf-input-row" style="margin-top:4px">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                    <input type="checkbox" ${field.required ? 'checked' : ''}
                                        onchange="lcfFieldUpdate('${escAttr(field.id)}','required',this.checked)">
                                    Bắt buộc nhập
                                </label>
                            </div>
                            ${optHtml}
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
                                + Field
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
            window.lcfToggleCard = function(cardEl) {
                if (!cardEl) return;
                cardEl.classList.toggle('is-open');
            };

            // ── Public: update field property (no re-render) ──────────────────
            window.lcfFieldUpdate = function(fieldId, key, value) {
                const found = findField(fieldId);
                if (!found) return;
                const { field } = found;
                field[key] = value;

                const cardEl = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');

                if (key === 'label') {
                    const prev      = cardEl ? cardEl.querySelector('.lcf-label-preview') : null;
                    const nameInput = cardEl ? cardEl.querySelector('.lcf-name-input')    : null;
                    if (prev) {
                        const reqMark = field.required ? ' <span style="color:#d9534f">*</span>' : '';
                        prev.innerHTML = (value
                            ? escHtml(value)
                            : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>') + reqMark;
                    }
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

                if (key === 'required') {
                    const prev  = cardEl ? cardEl.querySelector('.lcf-label-preview') : null;
                    if (prev) {
                        const reqMark = value ? ' <span style="color:#d9534f">*</span>' : '';
                        prev.innerHTML = (field.label
                            ? escHtml(field.label)
                            : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>') + reqMark;
                    }
                }

                if (key === 'name') {
                    const strong = cardEl ? cardEl.querySelector('.lcf-name-strong') : null;
                    if (strong) strong.textContent = value || 'ten_bien';
                }

                updateJsonInput();
                updatePreview();
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

                const newField = {
                    id: uid(), type: type, name: '', label: '',
                    placeholder: '', required: false, options: [], _autoName: '',
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

            // ── Form submit validation ────────────────────────────────────────
            document.getElementById('laca-cf-form').addEventListener('submit', function(e) {
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
                    if (!f.label) {
                        e.preventDefault();
                        Swal.fire({ title: 'Lỗi', text: 'Có field chưa điền nhãn (Label).', icon: 'error' });
                        return;
                    }
                    if (!f.name) {
                        e.preventDefault();
                        Swal.fire({ title: 'Lỗi', text: 'Field "' + f.label + '" cần có tên biến (name).', icon: 'error' });
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
                styles[key] = (key === 'btn_border_radius' || key === 'input_border_radius')
                    ? Math.max(0, Math.min(50, parseInt(value) || 0))
                    : value;
                updateStyleInput();
                updatePreview();
                // Sync text <-> color
                const textMap = {
                    primary_color: 's-primary-color-text', secondary_color: 's-secondary-color-text',
                    input_border_color: 's-input-border-text', label_color: 's-label-color-text',
                };
                if (textMap[key]) {
                    const el = document.getElementById(textMap[key]);
                    if (el && el !== document.activeElement) el.value = value;
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
                var btnR = document.getElementById('s-btn-radius');
                var btnN = document.getElementById('s-btn-radius-num');
                var inpR = document.getElementById('s-input-radius');
                var inpN = document.getElementById('s-input-radius-num');
                var btnT = document.getElementById('s-btn-text');
                var inpS = document.getElementById('s-input-spacing');
                var lblS = document.getElementById('s-show-label');
                var cusC = document.getElementById('s-custom-css');
                
                if (btnR) btnR.value = styles.btn_border_radius;
                if (btnN) btnN.value = styles.btn_border_radius;
                if (inpR) inpR.value = styles.input_border_radius;
                if (inpN) inpN.value = styles.input_border_radius;
                if (btnT) btnT.value = styles.btn_text || DEFAULT_STYLES.btn_text;
                if (inpS) inpS.value = styles.input_spacing || '';
                if (lblS) lblS.checked = styles.show_label !== false;
                if (cusC) cusC.value = styles.custom_css || '';
            }

            // ── Build live form preview HTML ───────────────────────────────────
            function buildFieldPreviewHtml(field) {
                var type        = field.type || 'text';
                var label       = field.label || '(chưa đặt nhãn)';
                var placeholder = field.placeholder || '';
                var req         = field.required;
                var html        = '<div class="lcf-pv-field-row">';
                if (type !== 'hidden') {
                    html += '<label class="lcf-pv-label">' + escHtml(label);
                    if (req) html += ' <span style="color:#e53e3e">*</span>';
                    html += '</label>';
                }
                switch (type) {
                    case 'textarea':
                        html += '<textarea class="lcf-pv-input" placeholder="' + escAttr(placeholder) + '" rows="3" disabled></textarea>';
                        break;
                    case 'select':
                        html += '<select class="lcf-pv-input" disabled><option>— Chọn ' + escHtml(label) + ' —</option>';
                        (field.options || []).forEach(function(opt) { html += '<option>' + escHtml(opt) + '</option>'; });
                        html += '</select>';
                        break;
                    case 'radio':
                        html += '<div>';
                        (field.options || []).forEach(function(opt) {
                            html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px"><input type="radio" disabled> ' + escHtml(opt) + '</label>';
                        });
                        html += '</div>';
                        break;
                    case 'checkbox':
                        var opts = field.options || [];
                        if (opts.length <= 1) {
                            html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" disabled> ' + escHtml(opts[0] || 'yes') + '</label>';
                        } else {
                            html += '<div>';
                            opts.forEach(function(opt) {
                                html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px"><input type="checkbox" disabled> ' + escHtml(opt) + '</label>';
                            });
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

                html += '<div style="display:flex;justify-content:flex-end;margin-top:4px">';
                html += '<button type="button" class="lcf-pv-btn">' + escHtml(btnText) + '</button>';
                html += '</div>';
                html += '</form></div>';
                return html;
            }

            function updatePreview() {
                var out = document.getElementById('lcf-form-preview-output');
                if (out) out.innerHTML = buildFormPreviewHtml();
            }

            // ── Email preview ──────────────────────────────────────────────────
            window.lcfUpdateEmailPreview = function(which) {
                var taId  = which === 'admin' ? 'email-admin-body' : 'email-customer-body';
                var outId = which === 'admin' ? 'lcf-email-admin-preview-output' : 'lcf-email-customer-preview-output';
                var ta    = document.getElementById(taId);
                var out   = document.getElementById(outId);
                if (!ta || !out) return;
                var body    = ta.value;
                var isHtml  = body !== body.replace(/<[a-zA-Z]/g, '');
                var content = document.createElement('div');
                content.className = 'lcf-pv-email-content' + (isHtml ? ' is-html' : '');
                if (isHtml) {
                    content.innerHTML = body;
                } else {
                    content.textContent = body;
                }
                out.innerHTML = '';
                out.appendChild(content);
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

            // ── Init ──────────────────────────────────────────────────────────
            initStyleControls();
            renderRows();
            lcfUpdateEmailPreview('admin');
            lcfUpdateEmailPreview('customer');
        }
});

// Global actions for Submissions & List Views
document.addEventListener('DOMContentLoaded', () => {
    
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
