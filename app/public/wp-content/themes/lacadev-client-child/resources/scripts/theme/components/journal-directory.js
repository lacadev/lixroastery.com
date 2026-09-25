/**
 * archive-journal.php (/journal) — bấm tab danh mục CẤP 1 sẽ đổi AJAX phần
 * "lưới danh mục con" bên dưới (KHÔNG phải bài viết, xem
 * JournalCatDirectoryAjaxHandler.php + laca_journal_render_category_directory()).
 * Tách riêng khỏi cpt-grid.js vì nội dung trả về khác hẳn (lưới danh mục,
 * không phải lưới bài viết).
 */

function initJournalDirectory( root ) {
	let config;
	try {
		config = JSON.parse( root.dataset.journalDirectoryConfig || '{}' );
	} catch ( e ) {
		return;
	}
	if ( ! config.action ) {
		return;
	}

	let isLoading = false;
	const directoryEl = root.querySelector( '.journal-directory' );
	const tabs = root.querySelectorAll( '.block-cpt-grid__tab' );

	if ( ! directoryEl || ! tabs.length ) {
		return;
	}

	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			if ( tab.classList.contains( 'is-active' ) || isLoading ) {
				return;
			}

			isLoading = true;
			root.classList.add( 'is-loading' );

			const body = new URLSearchParams( {
				action: config.action,
				nonce: config.nonce,
				taxonomy: config.taxonomy,
				term_slug: tab.dataset.termSlug || '',
			} );

			fetch( config.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body,
			} )
				.then( ( res ) => res.json() )
				.then( ( json ) => {
					if ( json && json.success && json.data && json.data.html ) {
						tabs.forEach( ( t ) => t.classList.remove( 'is-active' ) );
						tab.classList.add( 'is-active' );
						directoryEl.innerHTML = json.data.html;
					}
				} )
				.catch( () => {} )
				.finally( () => {
					isLoading = false;
					root.classList.remove( 'is-loading' );
				} );
		} );
	} );

	// Nút prev/next cuộn ngang thanh tab (dùng chung class với block CPT Grid).
	const tabsList = root.querySelector( '.block-cpt-grid__tabs' );
	const prevBtn = root.querySelector( '.block-cpt-grid__tabs-nav--prev' );
	const nextBtn = root.querySelector( '.block-cpt-grid__tabs-nav--next' );
	if ( tabsList && prevBtn && nextBtn ) {
		prevBtn.addEventListener( 'click', () => {
			tabsList.scrollBy( { left: -200, behavior: 'smooth' } );
		} );
		nextBtn.addEventListener( 'click', () => {
			tabsList.scrollBy( { left: 200, behavior: 'smooth' } );
		} );
	}
}

function initAllJournalDirectories() {
	document
		.querySelectorAll( '[data-journal-directory-config]' )
		.forEach( initJournalDirectory );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAllJournalDirectories );
} else {
	initAllJournalDirectories();
}
