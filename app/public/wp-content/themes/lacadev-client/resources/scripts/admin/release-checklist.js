/**
 * Release Checklist AJAX handler
 * Allows ticking, skipping, and undoing checklist items without page reload.
 */

export function initReleaseChecklistAjax() {
	document.addEventListener( 'submit', ( e ) => {
		const form = e.target.closest( '.laca-rc__form' );
		if ( ! form ) {
			return;
		}

		e.preventDefault();

		const li = form.closest( '.laca-rc__item' );
		if ( li ) {
			li.classList.add( 'is-saving' );
		}

		const formData = new FormData( form );
		formData.append( 'is_ajax', '1' );

		const ajaxUrl = window.ajaxurl || form.action;

		fetch( ajaxUrl, {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
		} )
			.then( ( res ) => res.json() )
			.then( ( res ) => {
				if ( ! res.success ) {
					if ( li ) {
						li.classList.remove( 'is-saving' );
					}
					// eslint-disable-next-line no-alert
					alert( res.data?.message || 'Có lỗi xảy ra khi lưu trạng thái.' );
					return;
				}

				const data = res.data;

				// 1. Cập nhật thẻ mục li hiện tại
				if ( li && data.item_html ) {
					const wasOpen = li.querySelector( '.laca-rc__detail' )?.hasAttribute( 'open' );
					const temp = document.createElement( 'div' );
					temp.innerHTML = data.item_html.trim();
					const newLi = temp.firstElementChild;
					if ( newLi ) {
						if ( wasOpen ) {
							const newDetail = newLi.querySelector( '.laca-rc__detail' );
							if ( newDetail ) {
								newDetail.setAttribute( 'open', '' );
							}
						}
						li.replaceWith( newLi );
					}
				}

				// 2. Cập nhật header section
				const rcSection = document.querySelector( '.laca-rc' );
				if ( rcSection ) {
					rcSection.classList.toggle( 'is-complete', Boolean( data.all_done ) );
				}

				const titleEl = document.getElementById( 'laca-rc-title' );
				if ( titleEl && data.title_text ) {
					titleEl.textContent = data.title_text;
				}

				const countEl = document.querySelector( '.laca-rc__count' );
				if ( countEl && data.count_text ) {
					countEl.textContent = data.count_text;
				}

				const barEl = document.querySelector( '.laca-rc__bar' );
				if ( barEl && typeof data.percent !== 'undefined' ) {
					barEl.setAttribute( 'aria-valuenow', String( data.percent ) );
					const barFill = barEl.querySelector( 'span' );
					if ( barFill ) {
						barFill.style.width = `${ data.percent }%`;
					}
				}

				const summaryEl = document.querySelector( '.laca-rc__summary' );
				if ( summaryEl && data.summary_html ) {
					summaryEl.innerHTML = data.summary_html;
				}

				// 3. Cập nhật group count
				if ( data.groups ) {
					Object.entries( data.groups ).forEach( ( [ key, gStat ] ) => {
						const groupEl = document.querySelector( `.laca-rc__group[data-group-key="${ key }"]` );
						if ( groupEl ) {
							const countBadge = groupEl.querySelector( '.laca-rc__group-count' );
							if ( countBadge ) {
								countBadge.textContent = gStat.text;
								countBadge.classList.toggle( 'is-complete', Boolean( gStat.is_complete ) );
							}
						}
					} );
				}
			} )
			.catch( ( err ) => {
				console.error( '[laca-rc] AJAX error:', err );
				if ( li ) {
					li.classList.remove( 'is-saving' );
				}
			} );
	} );
}
