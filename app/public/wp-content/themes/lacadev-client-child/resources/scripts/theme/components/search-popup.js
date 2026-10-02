/**
 * Popup tìm kiếm — trượt toàn màn hình từ phải (tham khảo cơ chế mở/đóng
 * đã có ở mobile-menu.js: backdrop click, nút đóng, phím Escape, khóa
 * scroll qua class "menu-open" có sẵn trên body).
 *
 * Live search AJAX: dùng LẠI nguyên handler "ajax_search" (mms_ajax_search(),
 * parent app/helpers/ajax.php) + nonce "theme_search_nonce" (localize sẵn
 * thành window.themeSearch qua handle theme-js-bundle, parent
 * theme/functions.php) — handler này đã tự group kết quả theo post type
 * thật và trả về HTML dựng sẵn (lacadev_render_search_section()), không
 * cần viết lại. Parent cũng có resources/scripts/theme/ajax-search.js gọi
 * cùng action này nhưng bám selector ".header__bottom-search" của 1 bản
 * header cũ không còn dùng — không đụng tới file đó (thay đổi parent ảnh
 * hưởng site khác), tự viết lại phần bind DOM ở đây cho đúng popup mới.
 */

export function initSearchPopup() {
	const openBtn = document.getElementById( 'btn-search-open' );
	const popup = document.getElementById( 'header-search' );
	if ( ! openBtn || ! popup ) {
		return;
	}

	const closeBtn = popup.querySelector( '.header__popup-close' );
	const backdrop = popup.querySelector( '.header__popup-backdrop' );
	const input = popup.querySelector( '#header-search-input' );
	const resultsEl = popup.querySelector( '#header-search-results' );

	const openPopup = () => {
		popup.classList.add( 'active' );
		popup.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'menu-open' );
		input?.focus();
	};

	const closePopup = () => {
		popup.classList.remove( 'active' );
		popup.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'menu-open' );
	};

	openBtn.addEventListener( 'click', openPopup );
	closeBtn?.addEventListener( 'click', closePopup );
	backdrop?.addEventListener( 'click', closePopup );

	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && popup.classList.contains( 'active' ) ) {
			closePopup();
		}
	} );

	const themeSearch = window.themeSearch;
	if ( ! input || ! resultsEl || ! themeSearch ) {
		return;
	}

	let searchTimeout = null;
	let currentController = null;

	const performSearch = ( query ) => {
		currentController?.abort();
		currentController = new AbortController();

		resultsEl.innerHTML =
			'<div class="search-results__loading">Đang tìm kiếm…</div>';
		resultsEl.classList.add( 'active' );

		const params = new URLSearchParams( {
			action: 'ajax_search',
			s: query,
			nonce: themeSearch.nonce,
		} );

		fetch( `${ themeSearch.ajaxurl }?${ params.toString() }`, {
			method: 'GET',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: currentController.signal,
		} )
			.then( ( response ) => response.text() )
			.then( ( html ) => {
				resultsEl.innerHTML = html;
				resultsEl.classList.add( 'active' );
			} )
			.catch( ( error ) => {
				if ( error.name === 'AbortError' ) {
					return;
				}
				resultsEl.innerHTML = '';
				resultsEl.classList.remove( 'active' );
			} );
	};

	input.addEventListener( 'input', ( e ) => {
		const query = e.target.value.trim();
		clearTimeout( searchTimeout );

		if ( query.length < 2 ) {
			currentController?.abort();
			resultsEl.innerHTML = '';
			resultsEl.classList.remove( 'active' );
			return;
		}

		searchTimeout = setTimeout( () => performSearch( query ), 300 );
	} );
}
