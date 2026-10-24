/**
 * Universal Auto-Save Engine for Laca Admin
 *
 * Chuẩn hoá cơ chế tự lưu AJAX:
 * - Chỉ lưu khi có thay đổi thật (so sánh snapshot với baseline).
 * - Không lưu khi đang gõ (chỉ lưu khi blur / change).
 * - Checkbox/radio/select: lưu ngay khi change.
 * - MutationObserver chỉ dùng để báo hiệu form có thể đổi (complex, media),
 *   sau 600ms yên tĩnh mới tính lại snapshot và so sánh.
 *
 * @package LacaDevClient
 */

/**
 * Chuẩn hoá giá trị form thành snapshot map { [name]: string }.
 *
 * @param {HTMLFormElement} form
 * @return {Record<string, string>}
 */
export function createFormSnapshot( form ) {
	const snapshot = {};
	if ( ! form || ! form.elements ) {
		return snapshot;
	}

	const elements = form.elements;
	const namesSeen = new Set();

	for ( let i = 0; i < elements.length; i++ ) {
		const el = elements[ i ];
		const name = el.name;
		if ( ! name || el.disabled ) {
			continue;
		}

		// Bỏ qua các trường tìm kiếm hoặc trường tạm thời / cờ nội bộ
		if (
			/search/i.test( name ) ||
			name === 'carbon_fields_auto_save' ||
			name === 'carbon_fields_changed' ||
			name === 'laca_changed_fields' ||
			name === 'is_ajax' ||
			name === '_wp_http_referer' ||
			name === '_wpnonce'
		) {
			continue;
		}

		namesSeen.add( name );
	}

	namesSeen.forEach( ( name ) => {
		const field = form.elements[ name ];
		if ( ! field ) {
			return;
		}

		if (
			field instanceof RadioNodeList ||
			( field.length !== undefined && ! ( field instanceof HTMLSelectElement ) )
		) {
			const values = [];
			for ( let i = 0; i < field.length; i++ ) {
				const item = field[ i ];
				if ( item.type === 'radio' ) {
					if ( item.checked ) {
						values.push( item.value.trim() );
					}
				} else if ( item.type === 'checkbox' ) {
					if ( item.checked ) {
						values.push( item.value.trim() );
					}
				} else {
					values.push( String( item.value || '' ).trim() );
				}
			}
			snapshot[ name ] = values.join( '\u0001' );
		} else {
			const item = field;
			if ( item.type === 'checkbox' ) {
				snapshot[ name ] = item.checked ? ( item.value ? item.value.trim() : '1' ) : '';
			} else if ( item.type === 'radio' ) {
				snapshot[ name ] = item.checked ? item.value.trim() : '';
			} else if ( item instanceof HTMLSelectElement && item.multiple ) {
				const values = Array.from( item.selectedOptions ).map( ( o ) => o.value.trim() );
				snapshot[ name ] = values.join( '\u0001' );
			} else {
				snapshot[ name ] = String( item.value || '' ).trim();
			}
		}
	} );

	return snapshot;
}

/**
 * So sánh 2 snapshot, trả về danh sách các field name bị thay đổi.
 *
 * @param {Record<string, string>} prev
 * @param {Record<string, string>} curr
 * @return {string[]}
 */
export function diffSnapshots( prev, curr ) {
	const changed = new Set();
	for ( const k in curr ) {
		if ( ( prev[ k ] ?? '' ) !== ( curr[ k ] ?? '' ) ) {
			changed.add( k );
		}
	}
	for ( const k in prev ) {
		if ( ( prev[ k ] ?? '' ) !== ( curr[ k ] ?? '' ) ) {
			changed.add( k );
		}
	}
	return Array.from( changed );
}

/**
 * Khởi tạo engine tự lưu cho một form.
 *
 * @param {Object} options
 * @param {HTMLFormElement} options.form
 * @param {string} [options.saveUrl]
 * @param {Function} [options.buildFormData]
 * @param {Function} [options.onStatusChange]
 * @param {boolean} [options.isCarbon=false]
 * @param {string} [options.containerSelector]
 * @return {Object} Controller
 */
export function createAutoSaveEngine( options ) {
	const {
		form,
		saveUrl = window.location.href,
		buildFormData,
		onStatusChange,
		isCarbon = false,
		containerSelector = '.theme-options-container',
	} = options;

	if ( ! form ) {
		return null;
	}

	let baseline = {};
	let isReady = false;
	let isSaving = false;
	let hasPendingChanges = false;
	let mutationDebounce = null;

	function setStatus( status, message ) {
		if ( typeof onStatusChange === 'function' ) {
			onStatusChange( status, message );
		}
	}

	function executeSave( changedFields ) {
		if ( isSaving ) {
			hasPendingChanges = true;
			return;
		}

		isSaving = true;
		setStatus( 'saving', 'Đang lưu...' );

		let formData;
		if ( typeof buildFormData === 'function' ) {
			formData = buildFormData( form, changedFields );
		} else {
			formData = new FormData( form );
			if ( isCarbon ) {
				formData.append( 'carbon_fields_auto_save', '1' );
			}
		}

		if ( changedFields && changedFields.length > 0 ) {
			formData.set( 'laca_changed_fields', changedFields.join( ',' ) );
		}

		const targetUrl = typeof saveUrl === 'function' ? saveUrl() : saveUrl;

		fetch( targetUrl, {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
		} )
			.then( ( res ) => res.json() )
			.then( ( res ) => {
				isSaving = false;

				if ( res && ( res.success || res.status === 'success' ) ) {
					// Cập nhật baseline theo trạng thái hiện tại đã lưu thành công
					baseline = createFormSnapshot( form );
					const time = res.data?.timestamp || new Date().toLocaleTimeString( 'vi-VN', { hour: '2-digit', minute: '2-digit' } );
					setStatus( 'saved', `Đã lưu lúc ${ time }` );
				} else {
					const msg = res?.data?.message || 'Chưa lưu — Thử lại';
					setStatus( 'error', msg );
				}

				if ( hasPendingChanges ) {
					hasPendingChanges = false;
					checkAndSave();
				}
			} )
			.catch( ( err ) => {
				console.error( '[laca-autosave] Save error:', err );
				isSaving = false;
				setStatus( 'error', 'Chưa lưu — Thử lại' );
				if ( hasPendingChanges ) {
					hasPendingChanges = false;
				}
			} );
	}

	function checkAndSave() {
		if ( ! isReady ) {
			return;
		}

		const currentSnapshot = createFormSnapshot( form );
		const changed = diffSnapshots( baseline, currentSnapshot );

		if ( changed.length === 0 ) {
			return;
		}

		executeSave( changed );
	}

	// 1. Lắng nghe change: checkbox, radio, select đơn, hoặc khi input rời focus sau khi đổi
	form.addEventListener( 'change', ( e ) => {
		const target = e.target;
		if ( ! target || /search/i.test( target.name || target.id || target.className ) ) {
			return;
		}
		checkAndSave();
	} );

	// 2. Lắng nghe blur: khi rời khỏi input text, url, number, email, textarea
	form.addEventListener(
		'blur',
		( e ) => {
			const target = e.target;
			if ( ! target ) {
				return;
			}
			if ( [ 'INPUT', 'TEXTAREA' ].includes( target.tagName ) ) {
				if ( /search/i.test( target.name || target.id || target.className ) ) {
					return;
				}
				checkAndSave();
			}
		},
		true
	);

	// 3. Phím Enter trong input đơn dòng: blur để kích hoạt lưu
	form.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' && e.target && e.target.tagName === 'INPUT' && e.target.type !== 'submit' ) {
			e.target.blur();
		}
	} );

	// 4. Chặn submit form mặc định: lưu ngay nếu có thay đổi
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		checkAndSave();
	} );

	// 5. Nút Save thủ công
	const saveBtn = form.querySelector( '#publish, .laca-seo-save-btn, input[type="submit"]' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			checkAndSave();
		} );
	}

	// 6. Thiết lập baseline ban đầu
	function initBaseline() {
		baseline = createFormSnapshot( form );
		isReady = true;
	}

	if ( isCarbon ) {
		// Chờ React của Carbon Fields mount hoàn tất
		const containerEl = form.querySelector( containerSelector ) || form;
		let settleTimer = null;
		let maxTimer = null;

		const observer = new MutationObserver( ( mutations ) => {
			let isDomStructureChanged = false;
			for ( const m of mutations ) {
				if ( m.type === 'childList' && ( m.addedNodes.length > 0 || m.removedNodes.length > 0 ) ) {
					isDomStructureChanged = true;
					break;
				}
			}

			if ( ! isDomStructureChanged ) {
				return;
			}

			if ( ! isReady ) {
				// Đang chờ mount lần đầu: mỗi khi có mutation, reset 800ms
				clearTimeout( settleTimer );
				settleTimer = setTimeout( () => {
					clearTimeout( maxTimer );
					initBaseline();
				}, 800 );
			} else {
				// Đã ready: MutationObserver CHỈ đánh dấu "có thể đã đổi" (thêm/xoá group),
				// chờ 600ms yên tĩnh rồi so sánh snapshot
				clearTimeout( mutationDebounce );
				mutationDebounce = setTimeout( () => {
					checkAndSave();
				}, 600 );
			}
		} );

		observer.observe( containerEl, {
			childList: true,
			subtree: true,
		} );

		// Giới hạn tối đa 4 giây để chốt baseline kể cả khi React vẫn còn render
		maxTimer = setTimeout( () => {
			if ( ! isReady ) {
				initBaseline();
			}
		}, 4000 );
	} else {
		// Form thông thường: chốt baseline sau khi DOM sẵn sàng
		setTimeout( initBaseline, 200 );
	}

	return {
		checkAndSave,
		forceSave: () => executeSave( Object.keys( baseline ) ),
		getBaseline: () => baseline,
		isReady: () => isReady,
	};
}
