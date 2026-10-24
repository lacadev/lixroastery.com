/**
 * List Table Horizontal Scroll & Inline Editing
 *
 * Tự động bọc bảng danh sách admin vào container cuộn ngang và cho phép
 * click để sửa trực tiếp các trường tùy biến ngoài danh sách.
 */

export function initListTableInlineEditor() {
	const table = document.querySelector( '#posts-filter .wp-list-table, table.wp-list-table' );
	if ( ! table ) {
		finishLoading();
		return;
	}

	// 1. Khởi tạo cuộn ngang và cố định cột
	setupTableScroll( table );

	// 2. Khởi tạo inline editor cho các ô dữ liệu
	setupInlineEditing( table );

	// 3. Hoàn tất loading: gỡ skeleton và hiển thị bảng thật mượt mà
	finishLoading();
}

/**
 * Xóa trạng thái loading và dọn dẹp skeleton placeholder.
 */
function finishLoading() {
	document.documentElement.classList.remove( 'laca-table-loading' );
	document.documentElement.classList.add( 'laca-table-ready' );
	const skeleton = document.getElementById( 'laca-table-skeleton' );
	if ( skeleton ) {
		skeleton.style.display = 'none';
		skeleton.remove();
	}
}

/**
 * Bọc bảng vào container .laca-table-scroll và thiết lập vị trí sticky cho các cột cố định.
 *
 * @param {HTMLTableElement} table
 */
function setupTableScroll( table ) {
	let wrapper = table.closest( '.laca-table-scroll' );

	if ( ! wrapper ) {
		wrapper = document.createElement( 'div' );
		wrapper.className = 'laca-table-scroll';
		wrapper.setAttribute( 'aria-label', 'Bảng dữ liệu cuộn ngang' );
		table.parentNode.insertBefore( wrapper, table );
		wrapper.appendChild( table );
		document.body.classList.add( 'has-laca-table-scroll' );

		// Hỗ trợ cuộn chuột ngang mượt mà khi di chuột trên bảng
		wrapper.addEventListener(
			'wheel',
			( e ) => {
				if ( Math.abs( e.deltaX ) > 0 || e.shiftKey ) {
					return;
				}
				if ( wrapper.scrollWidth > wrapper.clientWidth ) {
					if (
						( e.deltaY > 0 &&
							wrapper.scrollLeft <
								wrapper.scrollWidth - wrapper.clientWidth ) ||
						( e.deltaY < 0 && wrapper.scrollLeft > 0 )
					) {
						wrapper.scrollLeft += e.deltaY;
					}
				}
			},
			{ passive: true }
		);
	}

	// Thêm thanh cuộn phụ phía trên bảng
	let topScroll = wrapper.previousElementSibling;
	if ( ! topScroll || ! topScroll.classList.contains( 'laca-table-scroll-top' ) ) {
		topScroll = document.createElement( 'div' );
		topScroll.className = 'laca-table-scroll-top';
		topScroll.setAttribute( 'aria-hidden', 'true' );
		const inner = document.createElement( 'div' );
		inner.className = 'laca-table-scroll-top-inner';
		topScroll.appendChild( inner );
		wrapper.parentNode.insertBefore( topScroll, wrapper );
	}

	let isSyncingTop = false;
	let isSyncingBottom = false;

	topScroll.addEventListener(
		'scroll',
		() => {
			if ( isSyncingTop ) {
				isSyncingTop = false;
				return;
			}
			isSyncingBottom = true;
			wrapper.scrollLeft = topScroll.scrollLeft;
		},
		{ passive: true }
	);

	wrapper.addEventListener(
		'scroll',
		() => {
			if ( wrapper.scrollLeft > 0 ) {
				wrapper.classList.add( 'is-scrolled' );
			} else {
				wrapper.classList.remove( 'is-scrolled' );
			}
			if ( isSyncingBottom ) {
				isSyncingBottom = false;
				return;
			}
			isSyncingTop = true;
			topScroll.scrollLeft = wrapper.scrollLeft;
		},
		{ passive: true }
	);

	function updateStickyMetrics() {
		const thumbCol = table.querySelector(
			'thead .column-thumb, thead .column-lacadev_thumb, thead .column-featured_image, thead .column-image, tbody td.column-thumb, tbody td.column-lacadev_thumb, tbody td.column-featured_image, tbody td.column-image'
		);
		const hasThumb = !! thumbCol;

		if ( hasThumb ) {
			table.classList.add( 'has-col-thumb' );
			wrapper.classList.add( 'has-col-thumb' );
			table.classList.remove( 'no-col-thumb' );
			wrapper.classList.remove( 'no-col-thumb' );
		} else {
			table.classList.add( 'no-col-thumb' );
			wrapper.classList.add( 'no-col-thumb' );
			table.classList.remove( 'has-col-thumb' );
			wrapper.classList.remove( 'has-col-thumb' );
		}

		const cb = table.querySelector( 'thead th.check-column, thead td.check-column, thead .column-cb' );
		let cbWidth = 44;
		if ( cb ) {
			const rect = cb.getBoundingClientRect();
			cbWidth = Math.round( rect.width ) || 44;
		}

		let thumbWidth = 0;
		if ( hasThumb ) {
			const thumbEl = table.querySelector(
				'thead .column-thumb, thead .column-lacadev_thumb, thead .column-featured_image, thead .column-image'
			);
			if ( thumbEl ) {
				const rect = thumbEl.getBoundingClientRect();
				thumbWidth = Math.round( rect.width ) || 76;
			} else {
				thumbWidth = 76;
			}
			if ( thumbWidth < 60 ) {
				thumbWidth = 76;
			}
		}

		const inner = topScroll ? topScroll.querySelector( '.laca-table-scroll-top-inner' ) : null;
		if ( topScroll && inner ) {
			if ( window.innerWidth > 782 && wrapper.scrollWidth > wrapper.clientWidth + 2 ) {
				topScroll.style.display = 'block';
				inner.style.width = `${ table.scrollWidth }px`;
			} else {
				topScroll.style.display = 'none';
			}
		}

		if ( window.innerWidth <= 782 ) {
			table.querySelectorAll( 'th.check-column, td.check-column, td.column-cb, th.column-thumb, td.column-thumb, td.thumb, th.column-lacadev_thumb, td.column-lacadev_thumb, th.column-featured_image, td.column-featured_image, th.column-image, td.column-image, th.column-name, td.column-name, td.name, th.column-title, td.column-title' ).forEach( ( el ) => {
				el.style.position = 'static';
				el.style.left = 'auto';
			} );
			return;
		}

		const titleLeft = cbWidth + thumbWidth;

		table.style.setProperty( '--laca-col-cb-width', `${ cbWidth }px` );
		table.style.setProperty( '--laca-col-thumb-width', `${ thumbWidth }px` );
		table.style.setProperty( '--laca-col-title-left', `${ titleLeft }px` );

		// Áp dụng inline style trực tiếp cho các cell sticky để bảo đảm tương thích 100%
		table.querySelectorAll( 'thead th.check-column, tbody th.check-column, tfoot th.check-column, .column-cb' ).forEach( ( el ) => {
			el.style.position = 'sticky';
			el.style.left = '0px';
		} );

		if ( hasThumb ) {
			table.querySelectorAll(
				'th.column-thumb, td.column-thumb, th.column-lacadev_thumb, td.column-lacadev_thumb, th.column-featured_image, td.column-featured_image, th.column-image, td.column-image'
			).forEach( ( el ) => {
				el.style.position = 'sticky';
				el.style.left = `${ cbWidth }px`;
			} );
		}

		table.querySelectorAll( 'th.column-name, td.column-name, th.column-title, td.column-title' ).forEach( ( el ) => {
			el.style.position = 'sticky';
			el.style.left = `${ titleLeft }px`;
		} );

		// Đảm bảo nền đục 100% cho thead/tfoot và từng ô sticky trên mỗi hàng
		table.querySelectorAll( 'thead th, tfoot th' ).forEach( ( th ) => {
			th.style.backgroundColor = '#f1f5f9';
		} );

		table.querySelectorAll( 'tbody tr' ).forEach( ( tr, idx ) => {
			const isAlt = tr.classList.contains( 'alternate' ) || idx % 2 === 1;
			const rowBg = isAlt ? '#f8fafc' : '#ffffff';
			tr.style.backgroundColor = rowBg;

			tr.querySelectorAll(
				'th.check-column, td.check-column, td.column-cb, th.column-thumb, td.column-thumb, td.thumb, th.column-lacadev_thumb, td.column-lacadev_thumb, th.column-featured_image, td.column-featured_image, th.column-image, td.column-image, th.column-name, td.column-name, td.name, th.column-title, td.column-title'
			).forEach( ( cell ) => {
				cell.style.backgroundColor = rowBg;
			} );
		} );
	}

	// Gọi ngay và gọi sau khi render hoàn tất
	updateStickyMetrics();
	setTimeout( updateStickyMetrics, 100 );
	setTimeout( updateStickyMetrics, 500 );

	window.addEventListener( 'resize', updateStickyMetrics, { passive: true } );
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( updateStickyMetrics );
	}
}

/**
 * Thiết lập cơ chế click-to-edit và lưu AJAX cho các ô .laca-inline-cell.
 *
 * @param {HTMLTableElement} table
 */
function setupInlineEditing( table ) {
	table.addEventListener( 'click', function ( e ) {
		const cell = e.target.closest( '.laca-inline-cell' );
		if ( ! cell || cell.classList.contains( 'is-editing' ) ) {
			return;
		}

		// Không kích hoạt nếu click trực tiếp vào form input/select đã hiển thị
		if ( [ 'INPUT', 'SELECT', 'OPTION', 'BUTTON', 'LABEL' ].includes( e.target.tagName ) ) {
			return;
		}

		e.preventDefault();
		startEditingCell( cell );
	} );

	// Hỗ trợ bấm Enter / Space khi focus bằng bàn phím
	table.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			const cell = e.target.closest( '.laca-inline-cell' );
			if ( cell && ! cell.classList.contains( 'is-editing' ) ) {
				e.preventDefault();
				startEditingCell( cell );
			}
		}
	} );
}

/**
 * Bắt đầu chỉnh sửa một ô dữ liệu.
 *
 * @param {HTMLElement} cell
 */
function startEditingCell( cell ) {
	const config = window.lacaInlineTableConfig || {};
	const ajaxUrl = config.ajaxUrl || window.ajaxurl;
	const nonce = config.nonce || '';
	const fields = config.fields || {};
	const i18n = config.i18n || {
		saving: 'Đang lưu...',
		saved: 'Đã lưu!',
		saveError: 'Lưu thất bại. Vui lòng thử lại.',
		empty: '—',
		multiHelp: 'Giữ Ctrl / Cmd để chọn nhiều',
	};

	const postId = parseInt( cell.getAttribute( 'data-post-id' ), 10 );
	const key = cell.getAttribute( 'data-key' );
	const type = cell.getAttribute( 'data-type' ) || 'meta';

	if ( ! postId || ! key ) {
		return;
	}

	const fieldMeta = fields[ key ] || { type, options: [] };
	const valSpan = cell.querySelector( '.laca-inline-val' );
	const rawSpan = cell.querySelector( '.laca-inline-raw' );

	const originalDisplay = valSpan ? valSpan.textContent.trim() : '';
	const originalRaw = rawSpan ? String( rawSpan.getAttribute( 'data-value' ) || '' ) : '';

	cell.classList.add( 'is-editing' );

	let editor;
	const isSelect = type === 'stock_status' || type === 'taxonomy' || type === 'select';
	const isMulti = type === 'taxonomy';

	if ( isSelect ) {
		const container = document.createElement( 'div' );
		container.className = 'laca-inline-select-wrap';

		editor = document.createElement( 'select' );
		editor.className = 'laca-inline-select';
		if ( isMulti ) {
			editor.multiple = true;
		}

		container.appendChild( editor );

		if ( isMulti ) {
			const hint = document.createElement( 'small' );
			hint.className = 'laca-inline-select-hint';
			hint.textContent = i18n.multiHelp;
			container.appendChild( hint );
		}

		const selectedValues = isMulti
			? originalRaw.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean )
			: [ originalRaw ];

		function renderOptions( optionsList ) {
			editor.innerHTML = '';

			if ( ! isMulti ) {
				const defaultOpt = document.createElement( 'option' );
				defaultOpt.value = '';
				defaultOpt.textContent = `— Chọn ${ fieldMeta.label || key } —`;
				editor.appendChild( defaultOpt );
			}

			if ( ! optionsList || optionsList.length === 0 ) {
				const emptyOpt = document.createElement( 'option' );
				emptyOpt.value = '';
				emptyOpt.textContent = '(Không có mục nào)';
				emptyOpt.disabled = true;
				editor.appendChild( emptyOpt );
				return;
			}

			optionsList.forEach( ( opt ) => {
				const optEl = document.createElement( 'option' );
				optEl.value = opt.value;
				optEl.textContent = opt.label;
				if ( selectedValues.includes( String( opt.value ) ) ) {
					optEl.selected = true;
				}
				editor.appendChild( optEl );
			} );

			if ( isMulti ) {
				editor.size = Math.min( Math.max( optionsList.length, 3 ), 6 );
			}
		}

		// Nếu danh sách options có sẵn
		if ( fieldMeta.options && fieldMeta.options.length > 0 ) {
			renderOptions( fieldMeta.options );
		} else if ( type === 'taxonomy' ) {
			// Dự phòng tải động qua AJAX nếu config chưa nạp kịp
			const taxonomySlug = fieldMeta.taxonomy || key;
			const loadingOpt = document.createElement( 'option' );
			loadingOpt.textContent = 'Đang tải danh mục...';
			loadingOpt.disabled = true;
			editor.appendChild( loadingOpt );

			fetch( `${ ajaxUrl }?action=laca_inline_get_options&taxonomy=${ encodeURIComponent( taxonomySlug ) }&nonce=${ encodeURIComponent( nonce ) }` )
				.then( ( res ) => res.json() )
				.then( ( res ) => {
					if ( res && res.success && res.data && res.data.options ) {
						fieldMeta.options = res.data.options;
						renderOptions( res.data.options );
					} else {
						renderOptions( [] );
					}
				} )
				.catch( () => renderOptions( [] ) );
		} else {
			renderOptions( [] );
		}

		cell.innerHTML = '';
		cell.appendChild( container );
	} else {
		editor = document.createElement( 'input' );
		editor.type = 'text';
		editor.className = 'laca-inline-input';
		editor.value = originalDisplay === i18n.empty ? '' : originalDisplay;

		cell.innerHTML = '';
		cell.appendChild( editor );
	}

	let isSaving = false;

	function finish( displayVal, rawVal, isSuccess = true ) {
		cell.classList.remove( 'is-editing' );
		cell.innerHTML = '';

		const newDisplay = displayVal !== '' ? displayVal : i18n.empty;
		const newEmptyClass = displayVal === '' ? ' laca-inline-empty' : '';

		const newValSpan = document.createElement( 'span' );
		newValSpan.className = `laca-inline-val${ newEmptyClass }`;
		newValSpan.textContent = newDisplay;

		const newRawSpan = document.createElement( 'span' );
		newRawSpan.className = 'hidden laca-inline-raw';
		newRawSpan.setAttribute( 'data-key', key );
		newRawSpan.setAttribute( 'data-value', rawVal );

		cell.appendChild( newValSpan );
		cell.appendChild( newRawSpan );

		if ( isSuccess ) {
			cell.classList.add( 'laca-save-success' );
			setTimeout( () => cell.classList.remove( 'laca-save-success' ), 1200 );
		} else {
			cell.classList.add( 'laca-save-error' );
			setTimeout( () => cell.classList.remove( 'laca-save-error' ), 1200 );
		}
	}

	function save() {
		if ( isSaving ) {
			return;
		}

		let newValue;
		if ( isMulti ) {
			newValue = Array.from( editor.selectedOptions ).map( ( o ) => o.value );
		} else {
			newValue = editor.value.trim();
		}

		const currentRawComparison = isMulti ? newValue.join( ',' ) : newValue;
		if ( currentRawComparison === originalRaw ) {
			finish( originalDisplay, originalRaw, false );
			return;
		}

		isSaving = true;
		cell.innerHTML = `<span class="laca-inline-saving">${ i18n.saving }</span>`;

		const formData = new URLSearchParams();
		formData.append( 'action', 'laca_inline_update_field' );
		formData.append( 'nonce', nonce );
		formData.append( 'post_id', postId );
		formData.append( 'field_key', key );

		if ( Array.isArray( newValue ) ) {
			newValue.forEach( ( val ) => formData.append( 'value[]', val ) );
		} else {
			formData.append( 'value', newValue );
		}

		fetch( ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: formData.toString(),
		} )
			.then( ( res ) => res.json() )
			.then( ( res ) => {
				if ( res && res.success && res.data ) {
					finish( res.data.display, res.data.value, true );
				} else {
					const msg = ( res && res.data && res.data.message ) || i18n.saveError;
					finish( originalDisplay, originalRaw, false );
					if ( window.Swal ) {
						window.Swal.fire( {
							icon: 'error',
							title: 'Lỗi',
							text: msg,
							timer: 2500,
							showConfirmButton: false,
						} );
					} else {
						window.alert( msg );
					}
				}
			} )
			.catch( ( err ) => {
				console.error( 'Inline edit save failed:', err );
				finish( originalDisplay, originalRaw, false );
			} );
	}

	function cancel() {
		if ( isSaving ) {
			return;
		}
		finish( originalDisplay, originalRaw, false );
	}

	editor.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			save();
		} else if ( e.key === 'Escape' ) {
			e.preventDefault();
			cancel();
		} else if ( e.key === 'Tab' ) {
			e.preventDefault();
			save();
			const row = cell.closest( 'tr' );
			if ( row ) {
				const rowCells = Array.from( row.querySelectorAll( '.laca-inline-cell' ) );
				const currentIndex = rowCells.indexOf( cell );
				const nextCell = e.shiftKey ? rowCells[ currentIndex - 1 ] : rowCells[ currentIndex + 1 ];
				if ( nextCell ) {
					setTimeout( () => nextCell.click(), 50 );
				}
			}
		}
	} );

	editor.addEventListener( 'blur', function () {
		if ( ! isSaving ) {
			save();
		}
	} );

	editor.focus();
	if ( editor.select && ! isSelect ) {
		editor.select();
	}
}
