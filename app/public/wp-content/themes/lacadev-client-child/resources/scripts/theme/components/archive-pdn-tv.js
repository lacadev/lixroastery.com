const ROOT_SELECTOR = '#laca-tv-archive';
const GRID_ID = 'gallery-grid';
const PAGINATION_ID = 'gallery-pagination';
const FILTER_SELECTOR = '.laca-gallery-filter';

/**
 * WordPress archive: /page/N/ or ?paged=N (searchParams misses pretty permalinks).
 * @param {HTMLAnchorElement} link
 * @return {number} Page number (defaults to 1).
 */
function getPagedFromLink( link ) {
	if ( ! link || ! link.href ) {
		return 1;
	}
	let url;
	try {
		url = new URL( link.href );
	} catch {
		return 1;
	}
	const fromQuery = url.searchParams.get( 'paged' );
	if ( fromQuery !== null && fromQuery !== '' ) {
		const n = parseInt( fromQuery, 10 );
		return Number.isFinite( n ) && n > 0 ? n : 1;
	}
	const pathMatch = url.pathname.match( /\/page\/(\d+)\/?$/i );
	if ( pathMatch ) {
		const n = parseInt( pathMatch[ 1 ], 10 );
		return Number.isFinite( n ) && n > 0 ? n : 1;
	}
	return 1;
}

function buildArchiveBrowserUrl(
	archiveUrl,
	queryParam,
	catSlug,
	paged,
	prettyPaged
) {
	const url = new URL( archiveUrl, window.location.origin );
	if ( catSlug ) {
		url.searchParams.set( queryParam, catSlug );
	} else {
		url.searchParams.delete( queryParam );
	}
	if ( prettyPaged ) {
		url.searchParams.delete( 'paged' );
		let path = url.pathname.replace( /\/?page\/\d+\/?$/i, '' );
		if ( ! path.endsWith( '/' ) ) {
			path += '/';
		}
		if ( paged > 1 ) {
			path =
				path.replace( /\/+$/, '' ) + '/page/' + String( paged ) + '/';
		}
		url.pathname = path;
	} else if ( paged > 1 ) {
		url.searchParams.set( 'paged', String( paged ) );
	} else {
		url.searchParams.delete( 'paged' );
	}
	return url.toString();
}

async function fetchArchive( { config, catSlug, paged } ) {
	const body = new URLSearchParams( {
		action: config.action,
		nonce: config.nonce,
		cat_slug: catSlug,
		paged,
		posts_per_page: config.posts_per_page,
	} );

	const response = await fetch( config.ajaxurl, { method: 'POST', body } );
	return response.json();
}

function updatePage( {
	gridEl,
	paginationEl,
	filterEl,
	html,
	pagination,
	activeLabel,
	catSlug,
	paged,
	archiveUrl,
	queryParam,
	prettyPaged,
} ) {
	gridEl.innerHTML = html;
	paginationEl.innerHTML = pagination;

	const titleEl = document.querySelector( '.laca-gallery-toolbar__title' );
	if ( titleEl ) {
		titleEl.textContent = activeLabel;
	}

	if ( filterEl ) {
		const labelEl = filterEl.querySelector( '.laca-gallery-filter__label' );
		if ( labelEl ) {
			labelEl.textContent = activeLabel;
		}

		filterEl.querySelectorAll( '[data-cat-slug]' ).forEach( ( item ) => {
			item.classList.toggle(
				'is-active',
				item.dataset.catSlug === catSlug
			);
		} );
	}

	history.pushState(
		{ catSlug, paged },
		'',
		buildArchiveBrowserUrl(
			archiveUrl,
			queryParam,
			catSlug,
			paged,
			!! prettyPaged
		)
	);
	if ( typeof window.lacadevRefreshAOS === 'function' ) {
		window.lacadevRefreshAOS();
	}
}

function init() {
	const root = document.querySelector( ROOT_SELECTOR );
	if ( ! root ) {
		return;
	}

	const gridEl = document.getElementById( GRID_ID );
	if ( ! gridEl ) {
		return;
	}

	const config = JSON.parse( root.dataset.archiveConfig || '{}' );
	if ( ! config.ajaxurl || ! config.action ) {
		return;
	}

	const paginationEl = document.getElementById( PAGINATION_ID );
	const filterEl = root.querySelector( FILTER_SELECTOR );
	const queryParam = config.query_param || 'tv-cat';
	const prettyPaged = !! config.pretty_paged;
	let currentCat = config.cat_slug || '';

	if ( filterEl && ! filterEl.dataset.bound ) {
		filterEl.dataset.bound = '1';
		const trigger = filterEl.querySelector(
			'.laca-gallery-filter__trigger'
		);
		const list = filterEl.querySelector( '.laca-gallery-filter__list' );

		if ( trigger && list ) {
			trigger.addEventListener( 'click', () => {
				const isOpen = filterEl.classList.toggle( 'is-open' );
				trigger.setAttribute(
					'aria-expanded',
					isOpen ? 'true' : 'false'
				);
			} );

			document.addEventListener( 'click', ( e ) => {
				if ( ! filterEl.contains( e.target ) ) {
					filterEl.classList.remove( 'is-open' );
					trigger.setAttribute( 'aria-expanded', 'false' );
				}
			} );

			list.addEventListener( 'click', async ( e ) => {
				const item = e.target.closest( '[data-cat-slug]' );
				if ( ! item ) {
					return;
				}
				e.preventDefault();

				const catSlug = item.dataset.catSlug;
				if ( catSlug === currentCat ) {
					filterEl.classList.remove( 'is-open' );
					return;
				}

				filterEl.classList.remove( 'is-open' );
				gridEl.classList.add( 'is-loading' );
				try {
					const result = await fetchArchive( {
						config,
						catSlug,
						paged: 1,
					} );
					if ( result.success ) {
						currentCat = catSlug;
						updatePage( {
							gridEl,
							paginationEl,
							filterEl,
							html: result.data.html,
							pagination: result.data.pagination,
							activeLabel: result.data.active_label,
							catSlug,
							paged: 1,
							archiveUrl: config.archive_url,
							queryParam,
							prettyPaged,
						} );
					}
				} finally {
					gridEl.classList.remove( 'is-loading' );
				}
			} );
		}
	}

	if ( paginationEl && ! paginationEl.dataset.bound ) {
		paginationEl.dataset.bound = '1';
		paginationEl.addEventListener( 'click', async ( e ) => {
			const link = e.target.closest( 'a' );
			if ( ! link ) {
				return;
			}
			e.preventDefault();

			const paged = getPagedFromLink( link );
			gridEl.classList.add( 'is-loading' );
			try {
				const result = await fetchArchive( {
					config,
					catSlug: currentCat,
					paged,
				} );
				if ( result.success ) {
					updatePage( {
						gridEl,
						paginationEl,
						filterEl,
						html: result.data.html,
						pagination: result.data.pagination,
						activeLabel: result.data.active_label,
						catSlug: currentCat,
						paged,
						archiveUrl: config.archive_url,
						queryParam,
						prettyPaged,
					} );
					window.scrollTo( {
						top: root.offsetTop - 80,
						behavior: 'smooth',
					} );
				}
			} finally {
				gridEl.classList.remove( 'is-loading' );
			}
		} );
	}
}

function bootstrap() {
	init();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
