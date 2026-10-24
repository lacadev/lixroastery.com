/**
 * Laca SEO Admin JavaScript
 * Handles live notes expansion, conditional visibility (showIf),
 * module toggles, search filtering, media uploaders, and code reset.
 */

import { createAutoSaveEngine } from '../laca-autosave.js';

document.addEventListener('DOMContentLoaded', () => {
    initNotesToggle();
    initShowIfDependencies();
    initModuleToggles();
    initSearchFilter();
    initMediaUploaders();
    initCodeReset();
    initGroupRepeaters();
    initVariableFields();
    initAutoDismiss();
    initLacaSeoAutoSave();
});

/**
 * 0. Auto-dismiss save notice after 4 seconds
 */
function initAutoDismiss() {
    const alerts = document.querySelectorAll('.laca-seo-auto-dismiss');
    if (!alerts.length) return;
    setTimeout(() => {
        alerts.forEach((alert) => {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        });
    }, 4000);
}

/**
 * 1. Notes Collapsible Toggle (3-line notes)
 */
function initNotesToggle() {
    document.querySelectorAll('.laca-seo-note__toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const controlsId = btn.getAttribute('aria-controls');
            const target = document.getElementById(controlsId);
            if (!target) return;

            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!isExpanded));
            target.hidden = isExpanded;
        });
    });
}

/**
 * 2. showIf Field Visibility Dependencies
 */
function initShowIfDependencies() {
    const dependentRows = document.querySelectorAll('.laca-seo-field-row[data-show-if-field]');
    if (!dependentRows.length) return;

    function getMasterControl(fieldId) {
        const selectorBase = `[name="laca_seo[${fieldId}]"], [name="laca_seo[${fieldId}][]"]`;

        // 1. Checkbox
        const checkbox = document.querySelector(`input[type="checkbox"]${selectorBase}`);
        if (checkbox) return checkbox;

        // 2. Radio: checked first, else any radio in the group
        const checkedRadio = document.querySelector(`input[type="radio"]${selectorBase}:checked`);
        if (checkedRadio) return checkedRadio;
        const anyRadio = document.querySelector(`input[type="radio"]${selectorBase}`);
        if (anyRadio) return anyRadio;

        // 3. Select
        const select = document.querySelector(`select${selectorBase}`);
        if (select) return select;

        // 4. Other inputs (skip type=hidden)
        const otherInput = document.querySelector(`input:not([type="hidden"])${selectorBase}, textarea${selectorBase}`);
        if (otherInput) return otherInput;

        return null;
    }

    function getControlValue(control, fieldId) {
        if (!control) return '';
        if (control.type === 'checkbox') {
            return control.checked ? 'on' : 'off';
        }
        if (control.type === 'radio') {
            const checked = document.querySelector(`input[type="radio"][name="laca_seo[${fieldId}]"]:checked`);
            return checked ? checked.value : '';
        }
        return control.value || '';
    }

    function evaluateDependencies() {
        let changed = true;
        let iteration = 0;
        while (changed && iteration < 5) {
            changed = false;
            iteration++;

            dependentRows.forEach((row) => {
                const masterFieldId = row.getAttribute('data-show-if-field');
                const expectedVal = row.getAttribute('data-show-if-value') || '';
                const masterControl = getMasterControl(masterFieldId);

                if (!masterControl) {
                    if (row.style.display !== 'none') {
                        row.style.display = 'none';
                        changed = true;
                    }
                    return;
                }

                // If master row itself is inside a dependent row that is hidden
                const masterRow = masterControl.closest('.laca-seo-field-row');
                const masterIsHidden = masterRow ? masterRow.style.display === 'none' : false;

                const currentVal = getControlValue(masterControl, masterFieldId);
                const expectedVals = expectedVal.split(',').map((s) => s.trim());
                const matches = expectedVals.includes(currentVal);

                const shouldShow = !masterIsHidden && matches;
                const newDisplay = shouldShow ? '' : 'none';

                if (row.style.display !== newDisplay) {
                    row.style.display = newDisplay;
                    changed = true;
                }
            });
        }
    }

    // Attach change and input listeners to form inputs
    document.querySelectorAll('.laca-seo-form input, .laca-seo-form select, .laca-seo-form textarea').forEach((input) => {
        input.addEventListener('change', evaluateDependencies);
        input.addEventListener('input', evaluateDependencies);
    });

    const form = document.querySelector('.laca-seo-form');
    if (form) {
        form.addEventListener('change', evaluateDependencies);
        form.addEventListener('input', evaluateDependencies);
    }

    // Initial run
    evaluateDependencies();
}

/**
 * 3. Module Cards Toggle AJAX
 */
function initModuleToggles() {
    document.querySelectorAll('.laca-seo-module-toggle').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            const moduleName = toggle.getAttribute('data-module');
            const status = toggle.checked ? 'on' : 'off';
            const card = toggle.closest('.laca-seo-module-card');

            const data = new FormData();
            data.append('action', 'laca_seo_toggle_module');
            data.append('nonce', window.lacaSeoData?.nonce || '');
            data.append('module', moduleName);
            data.append('status', status);

            fetch(window.lacaSeoData?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: data,
            })
                .then((res) => res.json())
                .then((json) => {
                    if (json.success && card) {
                        card.classList.toggle('is-active', toggle.checked);
                    }
                })
                .catch((err) => console.error('[laca-seo] Module toggle error:', err));
        });
    });
}

/**
 * 4. Settings Search Filter
 */
function initSearchFilter() {
    const searchInput = document.getElementById('laca-seo-search-input');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.laca-seo-field-row');

        rows.forEach((row) => {
            if (!query) {
                row.style.display = '';
                return;
            }

            const labelText = row.querySelector('.laca-seo-field-label')?.textContent.toLowerCase() || '';
            const noteText = row.querySelector('.laca-seo-note__content')?.textContent.toLowerCase() || '';

            if (labelText.includes(query) || noteText.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

/**
 * 5. Media Uploaders (WordPress Media Frame)
 */
function initMediaUploaders() {
    document.querySelectorAll('.laca-seo-media-field').forEach((container) => {
        const selectBtn = container.querySelector('.laca-seo-media-select');
        const clearBtn = container.querySelector('.laca-seo-media-clear');
        const urlInput = container.querySelector('.laca-seo-media-url');
        const idInput = container.querySelector('.laca-seo-media-id');
        const preview = container.querySelector('.laca-seo-media-preview');

        if (selectBtn) {
            selectBtn.addEventListener('click', (e) => {
                e.preventDefault();

                const customUploader = wp.media({
                    title: 'Chọn hình ảnh',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false,
                });

                customUploader.on('select', () => {
                    const attachment = customUploader.state().get('selection').first().toJSON();
                    if (urlInput) {
                        urlInput.value = attachment.url;
                        urlInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (idInput) {
                        idInput.value = attachment.id;
                        idInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (clearBtn) clearBtn.style.display = '';
                    if (preview) {
                        preview.innerHTML = `<img src="${attachment.url}" style="max-height:80px;border-radius:4px;border:1px solid #ddd;">`;
                        preview.style.display = '';
                    }
                });

                customUploader.open();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (urlInput) {
                    urlInput.value = '';
                    urlInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (idInput) {
                    idInput.value = '';
                    idInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                clearBtn.style.display = 'none';
                if (preview) {
                    preview.innerHTML = '';
                    preview.style.display = 'none';
                }
            });
        }
    });
}

/**
 * 6. Code Box "Khôi phục mặc định"
 */
function initCodeReset() {
    document.querySelectorAll('.laca-seo-code-reset').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const defaultVal = btn.getAttribute('data-default');
            const textarea = document.getElementById(targetId);
            if (textarea && defaultVal !== null) {
                textarea.value = defaultVal;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
}

/**
 * 7. Group Repeaters (Add/Remove rows)
 */
function initGroupRepeaters() {
    document.querySelectorAll('.laca-seo-group-repeater').forEach((repeater) => {
        const addBtn = repeater.querySelector('.laca-seo-group-add');
        const itemsContainer = repeater.querySelector('.laca-seo-group-items');

        if (addBtn && itemsContainer) {
            addBtn.addEventListener('click', () => {
                const existingItems = itemsContainer.querySelectorAll('.laca-seo-group-item');
                const lastItem = existingItems[existingItems.length - 1];
                if (!lastItem) return;

                const clone = lastItem.cloneNode(true);
                const nextIndex = existingItems.length;

                // Update input indices
                clone.querySelectorAll('input, select').forEach((field) => {
                    const name = field.getAttribute('name');
                    if (name) {
                        const newName = name.replace(/\[\d+\]/, `[${nextIndex}]`);
                        field.setAttribute('name', newName);
                    }
                });

                itemsContainer.appendChild(clone);
                repeater.closest('.laca-seo-form')?.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        repeater.addEventListener('click', (e) => {
            if (e.target && e.target.classList.contains('laca-seo-group-remove')) {
                e.preventDefault();
                const item = e.target.closest('.laca-seo-group-item');
                if (item) {
                    item.remove();
                    repeater.closest('.laca-seo-form')?.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
    });
}

/**
 * 8. Variable Fields: Insert Tag Dropdown, Character Counter, and Live Preview
 */
function initVariableFields() {
    const rawVars = window.lacaSeoData?.variables || {};
    const rawList = Array.isArray(rawVars) ? rawVars : Object.entries(rawVars).map(([key, item]) => ({ key, ...(typeof item === 'object' ? item : { name: String(item) }) }));
    const varList = rawList.map((item) => {
        const id = item.id || item.tag || item.key || '';
        const tag = id ? (id.startsWith('%') ? id : `%${id}%`) : '';
        return {
            id,
            tag,
            name: item.name || id,
            desc: item.desc || '',
            example: item.example || '',
        };
    });

    const previewDebounceTimers = new Map();

    document.querySelectorAll('.laca-seo-var-field').forEach((container) => {
        const input = container.querySelector('.laca-seo-var-input');
        const btn = container.querySelector('.laca-seo-var-btn');
        const dropdown = container.querySelector('.laca-seo-var-dropdown');
        const searchInput = container.querySelector('.laca-seo-var-search');
        const listEl = container.querySelector('.laca-seo-var-list');
        const counterEl = container.querySelector('.laca-seo-var-counter');
        const previewEl = container.querySelector('.laca-seo-var-preview__text');
        const fieldId = container.getAttribute('data-field-id') || '';

        if (!input) return;

        // Populate variable list
        function renderDropdownList(filter = '') {
            if (!listEl) return;
            const query = filter.toLowerCase().trim();
            listEl.innerHTML = '';

            const filtered = varList.filter((v) => {
                if (!query) return true;
                return (
                    (v.tag && v.tag.toLowerCase().includes(query)) ||
                    (v.name && v.name.toLowerCase().includes(query)) ||
                    (v.desc && v.desc.toLowerCase().includes(query))
                );
            });

            if (filtered.length === 0) {
                listEl.innerHTML = '<li class="laca-seo-var-list__empty">Không tìm thấy biến phù hợp</li>';
                return;
            }

            filtered.forEach((v) => {
                const li = document.createElement('li');
                li.className = 'laca-seo-var-list__item';
                li.innerHTML = `
                    <div class="laca-seo-var-list__tag"><code>${v.tag}</code> - <strong>${v.name}</strong></div>
                    ${v.desc ? `<div class="laca-seo-var-list__desc">${v.desc}</div>` : ''}
                    ${v.example ? `<div class="laca-seo-var-list__example">Ví dụ: <em>${v.example}</em></div>` : ''}
                `;
                li.addEventListener('click', () => {
                    insertVariable(input, v.tag);
                    if (dropdown) dropdown.hidden = true;
                    updateCounter();
                    triggerPreview();
                });
                listEl.appendChild(li);
            });
        }

        renderDropdownList();

        // Dropdown toggle
        if (btn && dropdown) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const willOpen = dropdown.hidden;
                // Close any other open dropdowns
                document.querySelectorAll('.laca-seo-var-dropdown').forEach((d) => {
                    d.hidden = true;
                });
                dropdown.hidden = !willOpen;
                if (!dropdown.hidden && searchInput) {
                    searchInput.value = '';
                    renderDropdownList();
                    setTimeout(() => searchInput.focus(), 50);
                }
            });

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    renderDropdownList(e.target.value);
                });
                searchInput.addEventListener('click', (e) => e.stopPropagation());
            }

            dropdown.addEventListener('click', (e) => e.stopPropagation());
        }

        // Live counter
        // Đếm độ dài KẾT QUẢ sau khi thay biến (cái Google hiển thị), không phải chuỗi mẫu;
        // chưa có preview thì tạm đếm chuỗi mẫu.
        function updateCounter(renderedText) {
            if (!counterEl) return;
            const countSpan = counterEl.querySelector('.laca-seo-var-count');
            const val =
                typeof renderedText === 'string' ? renderedText : input.value || '';
            const len = val.length;
            if (countSpan) countSpan.textContent = String(len);

            const min = parseInt(counterEl.getAttribute('data-min') || '0', 10);
            const max = parseInt(counterEl.getAttribute('data-max') || '160', 10);

            counterEl.classList.remove('is-good', 'is-warning', 'is-danger');
            if (len >= min && len <= max) {
                counterEl.classList.add('is-good');
            } else if (len > 0 && len < min) {
                counterEl.classList.add('is-warning');
            } else if (len > max) {
                counterEl.classList.add('is-danger');
            }
        }

        // Live preview via AJAX (debounced 400ms)
        function triggerPreview() {
            if (!previewEl) return;
            const template = input.value || '';

            if (previewDebounceTimers.has(input)) {
                clearTimeout(previewDebounceTimers.get(input));
            }

            const timer = setTimeout(() => {
                if (!template.trim()) {
                    previewEl.textContent = '(trống)';
                    return;
                }

                const data = new FormData();
                data.append('action', 'laca_seo_preview_vars');
                data.append('nonce', window.lacaSeoData?.nonce || '');
                data.append('template', template);
                data.append('field_id', fieldId);

                fetch(window.lacaSeoData?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: data,
                })
                    .then((res) => res.json())
                    .then((json) => {
                        if (!json.success) {
                            return;
                        }
                        if (json.data?.empty) {
                            previewEl.textContent =
                                'Chưa có nội dung mẫu để xem trước';
                            return;
                        }
                        previewEl.textContent = json.data?.preview || '(rỗng)';
                        updateCounter(json.data?.preview || '');
                    })
                    .catch((err) => {
                        console.error('[laca-seo] preview error:', err);
                    });
            }, 400);

            previewDebounceTimers.set(input, timer);
        }

        input.addEventListener('input', () => {
            updateCounter();
            triggerPreview();
        });

        // Initial setup
        updateCounter();
        triggerPreview();
    });

    // Close dropdown on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.laca-seo-var-dropdown').forEach((d) => {
            d.hidden = true;
        });
    });

    // Close dropdown on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
            document.querySelectorAll('.laca-seo-var-dropdown').forEach((d) => {
                d.hidden = true;
            });
        }
    });
}

/**
 * Helper to insert text at cursor position in an input or textarea
 */
function insertAtCursor(field, text) {
    if (document.selection) {
        field.focus();
        const sel = document.selection.createRange();
        sel.text = text;
    } else if (field.selectionStart || field.selectionStart === 0) {
        const startPos = field.selectionStart;
        const endPos = field.selectionEnd;
        const before = field.value.substring(0, startPos);
        const after = field.value.substring(endPos, field.value.length);
        field.value = before + text + after;
        field.selectionStart = startPos + text.length;
        field.selectionEnd = startPos + text.length;
        field.focus();
    } else {
        field.value += text;
        field.focus();
    }
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}


/**
 * Chèn biến: người dùng đã đặt con trỏ trong ô → chèn tại đó; chưa từng focus
 * (selectionStart mặc định = 0) → nối vào cuối, tự thêm khoảng trắng ngăn cách.
 *
 * @param {HTMLInputElement|HTMLTextAreaElement} field
 * @param {string}                               tag
 */
function insertVariable( field, tag ) {
	if ( field.dataset.lacaCaretTouched === '1' ) {
		insertAtCursor( field, tag );
		return;
	}
	const needSpace = field.value.length > 0 && ! /\s$/.test( field.value );
	field.value += ( needSpace ? ' ' : '' ) + tag;
	field.focus();
	field.selectionStart = field.selectionEnd = field.value.length;
	field.dataset.lacaCaretTouched = '1';
	field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

// Đánh dấu ô người dùng đã tự đặt con trỏ (click/gõ/chọn) để insertVariable chèn đúng chỗ.
[ 'mouseup', 'keyup', 'select' ].forEach( ( evt ) => {
	document.addEventListener( evt, ( e ) => {
		if ( e.target && e.target.classList?.contains( 'laca-seo-var-input' ) ) {
			e.target.dataset.lacaCaretTouched = '1';
		}
	} );
} );

/**
 * 9. Laca SEO Form Auto-Save
 */
function initLacaSeoAutoSave() {
	const forms = document.querySelectorAll( '.laca-seo-form' );
	if ( ! forms.length ) return;

	forms.forEach( ( form ) => {
		const footerInner = form.querySelector( '.laca-seo-sticky-footer__inner' );
		let badge = form.querySelector( '.laca-seo-autosave-badge' );
		if ( ! badge ) {
			badge = document.createElement( 'div' );
			badge.className = 'laca-seo-autosave-badge is-idle';
			badge.setAttribute( 'aria-live', 'polite' );
			badge.innerHTML = `
				<span class="laca-seo-autosave-icon" aria-hidden="true"></span>
				<span class="laca-seo-autosave-text"></span>
			`;
			if ( footerInner ) {
				footerInner.appendChild( badge );
			} else {
				form.appendChild( badge );
			}
		}

		const iconEl = badge.querySelector( '.laca-seo-autosave-icon' );
		const textEl = badge.querySelector( '.laca-seo-autosave-text' );
		let hideTimer = null;

		function setStatus( status, message ) {
			clearTimeout( hideTimer );
			badge.className = `laca-seo-autosave-badge is-${ status }`;

			if ( textEl ) {
				textEl.textContent = message;
			}

			if ( iconEl ) {
				if ( status === 'saving' ) {
					iconEl.innerHTML = '<svg class="laca-seo-autosave-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/></svg>';
				} else if ( status === 'saved' ) {
					iconEl.innerHTML = '<svg class="laca-seo-autosave-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>';
				} else if ( status === 'error' ) {
					iconEl.innerHTML = '<svg class="laca-seo-autosave-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
				} else {
					iconEl.innerHTML = '';
				}
			}

			if ( status === 'saved' ) {
				hideTimer = setTimeout( () => {
					badge.classList.remove( 'is-saved' );
					badge.classList.add( 'is-idle' );
				}, 3500 );
			}
		}

		const ajaxUrl = window.ajaxurl || window.lacaSeoData?.ajaxUrl || form.action || '/wp-admin/admin-ajax.php';

		createAutoSaveEngine( {
			form,
			saveUrl: ajaxUrl,
			buildFormData: ( f, changedFields ) => {
				const formData = new FormData( f );
				formData.append( 'is_ajax', '1' );
				formData.set( 'action', 'laca_seo_save' );
				return formData;
			},
			onStatusChange: setStatus,
		} );
	} );
}
