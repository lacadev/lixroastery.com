/**
 * Carbon Fields Auto-Save Module
 * Provides seamless AJAX auto-saving for Laca Theme and Laca Admin without page reloads.
 */

export function initCarbonFieldsAutoSave() {
	const form = document.getElementById( 'theme-options-form' );
	if ( ! form ) {
		return;
	}

	// 1. Tạo Indicator hiển thị trạng thái lưu
	let indicator = document.getElementById( 'laca-cf-autosave-indicator' );
	if ( ! indicator ) {
		indicator = document.createElement( 'div' );
		indicator.id = 'laca-cf-autosave-indicator';
		indicator.className = 'laca-autosave-indicator is-idle';
		indicator.setAttribute( 'aria-live', 'polite' );
		indicator.innerHTML = `
			<span class="laca-autosave-icon" aria-hidden="true"></span>
			<span class="laca-autosave-text"></span>
		`;
		document.body.appendChild( indicator );
	}

	const iconEl = indicator.querySelector( '.laca-autosave-icon' );
	const textEl = indicator.querySelector( '.laca-autosave-text' );

	let isSaving = false;
	let hasPendingChanges = false;
	let debounceTimer = null;
	let hideTimer = null;

	function setStatus( status, message ) {
		clearTimeout( hideTimer );
		indicator.className = `laca-autosave-indicator is-${ status }`;

		if ( textEl ) {
			textEl.textContent = message;
		}

		if ( iconEl ) {
			if ( status === 'saving' ) {
				iconEl.innerHTML = '<svg class="laca-autosave-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/></svg>';
			} else if ( status === 'saved' ) {
				iconEl.innerHTML = '<svg class="laca-autosave-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>';
			} else if ( status === 'error' ) {
				iconEl.innerHTML = '<svg class="laca-autosave-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
			} else {
				iconEl.innerHTML = '';
			}
		}

		// Trạng thái bên cạnh nút Save trong metabox Actions
		const actionSpinner = document.querySelector( '#submitdiv .spinner' );
		if ( actionSpinner ) {
			actionSpinner.classList.toggle( 'is-active', status === 'saving' );
		}

		if ( status === 'saved' ) {
			hideTimer = setTimeout( () => {
				indicator.classList.remove( 'is-saved' );
				indicator.classList.add( 'is-idle' );
			}, 3500 );
		}
	}

	function executeSave() {
		if ( isSaving ) {
			hasPendingChanges = true;
			return;
		}

		isSaving = true;
		setStatus( 'saving', 'Đang lưu...' );

		const formData = new FormData( form );
		formData.append( 'carbon_fields_auto_save', '1' );

		fetch( window.location.href, {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
		} )
			.then( ( res ) => res.json() )
			.then( ( res ) => {
				isSaving = false;

				if ( res.success ) {
					const time = res.data?.timestamp || new Date().toLocaleTimeString( 'vi-VN' );
					setStatus( 'saved', `Đã tự động lưu (${ time })` );
				} else {
					setStatus( 'error', res.data?.message || 'Lưu thất bại' );
				}

				if ( hasPendingChanges ) {
					hasPendingChanges = false;
					executeSave();
				}
			} )
			.catch( ( err ) => {
				console.error( '[carbon-autosave] Error:', err );
				isSaving = false;
				setStatus( 'error', 'Lỗi kết nối' );

				if ( hasPendingChanges ) {
					hasPendingChanges = false;
				}
			} );
	}

	function queueSave( immediate = false ) {
		clearTimeout( debounceTimer );
		if ( immediate ) {
			executeSave();
		} else {
			debounceTimer = setTimeout( () => {
				executeSave();
			}, 600 );
		}
	}

	// 2. Lắng nghe các thay đổi trên form
	form.addEventListener( 'input', ( e ) => {
		const target = e.target;
		if ( ! target ) return;
		// Không trigger khi gõ trong search inputs nếu không phải field của Carbon
		if ( target.id === 'admin-menu-search-input' ) return;
		queueSave( false );
	} );

	form.addEventListener( 'change', ( e ) => {
		const target = e.target;
		if ( ! target ) return;
		queueSave( true );
	} );

	// 3. Chặn submit form mặc định (khi nhấn Save Changes hoặc Enter)
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		queueSave( true );
	} );

	const publishBtn = document.getElementById( 'publish' );
	if ( publishBtn && publishBtn.closest( '#theme-options-form' ) ) {
		publishBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			queueSave( true );
		} );
	}

	// 4. MutationObserver lắng nghe các thao tác React của Carbon Fields (Complex fields, Media uploaders, v.v.)
	const containerEl = form.querySelector( '.theme-options-container' );
	if ( containerEl && window.MutationObserver ) {
		let mutationDebounce = null;
		const observer = new MutationObserver( ( mutations ) => {
			let shouldTrigger = false;
			for ( const m of mutations ) {
				// Nếu có thêm hoặc xoá DOM nodes (thêm group, xoá group, load media preview)
				if ( m.type === 'childList' && ( m.addedNodes.length > 0 || m.removedNodes.length > 0 ) ) {
					shouldTrigger = true;
					break;
				}
			}
			if ( shouldTrigger ) {
				clearTimeout( mutationDebounce );
				mutationDebounce = setTimeout( () => {
					queueSave( true );
				}, 400 );
			}
		} );

		observer.observe( containerEl, {
			childList: true,
			subtree: true,
		} );
	}
}
