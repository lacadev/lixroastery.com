/**
 * CPT Grid block frontend logic — tab lọc theo taxonomy, phân trang đánh số,
 * và load-more/infinite-scroll. Gọi AJAX action "laca_cpt_grid_load"
 * (xem app/src/Ajax/CptGridAjaxHandler.php + app/helpers/cpt-grid-render.php).
 *
 * Mỗi instance của block tự đọc config riêng từ data-cpt-grid-config trên
 * root của chính nó (wp_unique_id() ở render.php) nên hỗ trợ nhiều block
 * cùng lúc trên 1 trang mà không xung đột state.
 */

const MOBILE_BREAKPOINT = 768;

function initCptGrid( root ) {
	let config;
	try {
		config = JSON.parse( root.dataset.cptGridConfig || '{}' );
	} catch ( e ) {
		return;
	}
	if ( ! config.action ) {
		return;
	}

	// Đọc term đang active từ DOM lúc khởi tạo — với block CPT Grid, tab đầu
	// là "ALL" (data-term-slug rỗng); với template taxonomy-journal-cat.php,
	// tab đầu là 1 danh mục thật nên phải khớp đúng, không được hardcode rỗng.
	let activeTermSlug = '';
	const initialActiveTab = root.querySelector( '.block-cpt-grid__tab.is-active' );
	if ( initialActiveTab ) {
		activeTermSlug = initialActiveTab.dataset.termSlug || '';
	}
	let currentPage = config.currentPage || 1;
	let maxPages = config.maxPages || 1;
	let isLoading = false;
	let observer = null;

	const list = root.querySelector( '.block-cpt-grid__list' );
	const paginationWrap = root.querySelector( '.block-cpt-grid__pagination' );
	const loadMoreWrap = root.querySelector( '.block-cpt-grid__load-more' );

	if ( ! list ) {
		return;
	}

	function effectivePerPage() {
		if ( config.paginationMode === 'load-more' ) {
			return config.minCount;
		}
		const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
		if ( isMobile && config.perPageMobile ) {
			return config.perPageMobile;
		}
		return config.perPagePC;
	}

	function setLoading( loading ) {
		isLoading = loading;
		root.classList.toggle( 'is-loading', loading );
	}

	function fetchGrid( paged, mode ) {
		if ( isLoading ) {
			return Promise.resolve( null );
		}
		setLoading( true );

		const body = new URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
			post_type: config.postType,
			taxonomy: config.taxonomy || '',
			term_slug: activeTermSlug,
			paged: String( paged ),
			posts_per_page: String( effectivePerPage() ),
			mode,
		} );

		return fetch( config.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body,
		} )
			.then( ( res ) => res.json() )
			.then( ( json ) => ( json && json.success ? json.data : null ) )
			.catch( () => null )
			.finally( () => setLoading( false ) );
	}

	function replaceGrid( data, scrollToTop ) {
		if ( ! data ) {
			return;
		}
		list.innerHTML = data.html;
		if ( paginationWrap ) {
			paginationWrap.innerHTML = data.pagination || '';
		}
		maxPages = data.max_pages || 1;
		if ( scrollToTop ) {
			root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	function appendGrid( data ) {
		if ( ! data ) {
			return;
		}
		list.insertAdjacentHTML( 'beforeend', data.html );
		maxPages = data.max_pages || 1;
		if ( ! data.has_more && observer ) {
			observer.disconnect();
		}
	}

	// ── Tabs lọc theo taxonomy ─────────────────────────────────────────
	const tabs = root.querySelectorAll( '.block-cpt-grid__tab' );
	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			if ( tab.classList.contains( 'is-active' ) || isLoading ) {
				return;
			}
			tabs.forEach( ( t ) => t.classList.remove( 'is-active' ) );
			tab.classList.add( 'is-active' );
			activeTermSlug = tab.dataset.termSlug || '';
			currentPage = 1;
			fetchGrid( 1, 'replace' ).then( ( data ) => replaceGrid( data, true ) );
		} );
	} );

	// ── Phân trang đánh số — event delegation vì markup bị thay mới mỗi lần ──
	if ( paginationWrap ) {
		paginationWrap.addEventListener( 'click', ( e ) => {
			const link = e.target.closest( 'a' );
			if ( ! link ) {
				return;
			}
			e.preventDefault();
			if ( isLoading ) {
				return;
			}
			const match = link.getAttribute( 'href' ).match( /cgpage-(\d+)/ );
			if ( ! match ) {
				return;
			}
			const page = parseInt( match[ 1 ], 10 );
			if ( ! page || page === currentPage ) {
				return;
			}
			currentPage = page;
			fetchGrid( page, 'replace' ).then( ( data ) => replaceGrid( data, true ) );
		} );
	}

	// ── Load more / infinite scroll ───────────────────────────────────
	if ( config.paginationMode === 'load-more' && loadMoreWrap ) {
		const sentinel = loadMoreWrap.querySelector( '.block-cpt-grid__sentinel' );
		if ( sentinel && 'IntersectionObserver' in window ) {
			observer = new IntersectionObserver(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting && ! isLoading && currentPage < maxPages ) {
							const nextPage = currentPage + 1;
							fetchGrid( nextPage, 'append' ).then( ( data ) => {
								if ( data ) {
									currentPage = nextPage;
									appendGrid( data );
								}
							} );
						}
					} );
				},
				{ rootMargin: '200px' }
			);
			observer.observe( sentinel );
		}
	}

	// ── Sửa lại số bài/trang cho đúng mobile ngay khi vừa tải trang ────
	// (SSR luôn render theo số PC để an toàn cho SEO/no-JS — nếu đang ở
	// mobile và có cấu hình riêng khác PC thì gọi lại AJAX 1 lần, không
	// cuộn trang vì người dùng chưa tương tác gì).
	if ( config.paginationMode === 'numbered' && config.perPageMobile ) {
		const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
		if ( isMobile && config.perPageMobile !== config.perPagePC ) {
			fetchGrid( 1, 'replace' ).then( ( data ) => replaceGrid( data, false ) );
		}
	}
}

function initAllCptGrids() {
	document
		.querySelectorAll( '.block-cpt-grid__inner[data-cpt-grid-config]' )
		.forEach( initCptGrid );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAllCptGrids );
} else {
	initAllCptGrids();
}
