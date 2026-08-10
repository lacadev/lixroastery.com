/**
 * Archive Gallery – JS
 *
 * Chức năng:
 * 1. Toggle dropdown filter
 * 2. AJAX filter theo gallery-cat
 * 3. AJAX pagination
 * 4. Fancybox popup khi click card/button
 */

import '@fancyapps/ui/dist/fancybox/fancybox.css';
import { Fancybox } from '@fancyapps/ui';

const ROOT_SELECTOR = '#laca-gallery-archive';
const GRID_ID = 'gallery-grid';
const PAGINATION_ID = 'gallery-pagination';
const FILTER_SELECTOR = '.laca-gallery-filter';
const CARD_SELECTOR = '.laca-gallery-card';

/**
 * Pretty permalinks use /page/2/; plain may use ?paged=2. searchParams alone misses /page/N/.
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

/**
 * Align history URL with WP pretty permalinks (/archive/page/N/) so layout stays correct.
 * @param archiveUrl
 * @param queryParam
 * @param catSlug
 * @param paged
 * @param prettyPaged
 */
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

// ─────────────────────────────────────────────────────────────────────────────
//  Fancybox options
// ─────────────────────────────────────────────────────────────────────────────
const FANCYBOX_OPTS = {
	Toolbar: {
		display: {
			left: [ 'infobar' ],
			middle: [],
			right: [ 'slideshow', 'thumbs', 'close' ],
		},
	},
	Thumbs: { type: 'classic' },
};

/**
 * Mở Fancybox cho một card cụ thể.
 * @param {HTMLElement} card
 */
function openGallery( card ) {
	let items = [];
	try {
		items = JSON.parse( card.dataset.galleryItems || '[]' );
	} catch ( err ) {
		console.error( '[Gallery] JSON parse error:', err );
		return;
	}

	if ( ! items.length ) {
		console.warn( '[Gallery] No images for card:', card );
		return;
	}

	// Fancybox v6 item format: { src, thumb, caption }
	// PHP template dùng key 'subHtml' → map thành 'caption'
	const fancyItems = items.map( ( item ) => ( {
		src: item.src,
		thumb: item.thumb || item.src,
		caption: item.caption || item.subHtml || '',
		type: 'image',
	} ) );

	Fancybox.show( fancyItems, FANCYBOX_OPTS );
}

/**
 * Bind click delegation — chỉ bind 1 lần trên root.
 * @param {HTMLElement} root
 */
function bindClickDelegation( root ) {
	root.addEventListener( 'click', ( e ) => {
		const trigger = e.target.closest(
			'.js-open-gallery, .laca-gallery-card__img'
		);
		if ( ! trigger ) {
			return;
		}

		const card = trigger.closest( CARD_SELECTOR );
		if ( ! card ) {
			return;
		}

		e.preventDefault();
		openGallery( card );
	} );
}

/**
 * AJAX request lấy grid mới.
 * @param root0
 * @param root0.config
 * @param root0.catSlug
 * @param root0.paged
 */
async function fetchGallery( { config, catSlug, paged } ) {
	const body = new URLSearchParams( {
		action: 'lacadev_gallery_archive_load',
		nonce: config.nonce,
		cat_slug: catSlug,
		paged,
		posts_per_page: config.posts_per_page,
	} );
	const res = await fetch( config.ajaxurl, { method: 'POST', body } );
	return res.json();
}

/**
 * Update grid + pagination + URL sau AJAX.
 * @param root0
 * @param root0.gridEl
 * @param root0.paginationEl
 * @param root0.filterEl
 * @param root0.html
 * @param root0.pagination
 * @param root0.activeLabel
 * @param root0.catSlug
 * @param root0.paged
 * @param root0.archiveUrl
 * @param root0.queryParam
 * @param root0.prettyPaged
 */
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
	queryParam = 'gallery-cat',
	prettyPaged,
} ) {
	gridEl.innerHTML = html;
	paginationEl.innerHTML = pagination;

	// Update toolbar title (tên danh mục)
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

/**
 * Main init — chạy khi DOM ready.
 */
function init() {
	const root = document.querySelector( ROOT_SELECTOR );
	if ( ! root ) {
		return;
	}

	const gridEl = document.getElementById( GRID_ID );
	if ( ! gridEl ) {
		return;
	}

	// Click delegation — chỉ bind 1 lần mỗi root
	if ( ! root.dataset.galleryBound ) {
		root.dataset.galleryBound = '1';
		bindClickDelegation( root );
	}

	// ── AJAX features ──
	const config = JSON.parse( root.dataset.archiveConfig || '{}' );
	if ( ! config.ajaxurl ) {
		return;
	}

	const paginationEl = document.getElementById( PAGINATION_ID );
	const filterEl = root.querySelector( FILTER_SELECTOR );
	const queryParam = config.query_param || 'gallery-cat';
	const prettyPaged = !! config.pretty_paged;
	let currentCat = config.cat_slug || '';

	// ── Dropdown filter ──
	if ( filterEl && ! filterEl.dataset.bound ) {
		filterEl.dataset.bound = '1';
		const trigger = filterEl.querySelector(
			'.laca-gallery-filter__trigger'
		);
		const list = filterEl.querySelector( '.laca-gallery-filter__list' );

		if ( trigger && list ) {
			trigger.addEventListener( 'click', () => {
				const isOpen = filterEl.classList.toggle( 'is-open' );
				trigger.setAttribute( 'aria-expanded', isOpen );
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
					const res = await fetchGallery( {
						config,
						catSlug,
						paged: 1,
					} );
					if ( res.success ) {
						currentCat = catSlug;
						updatePage( {
							gridEl,
							paginationEl,
							filterEl,
							html: res.data.html,
							pagination: res.data.pagination,
							activeLabel: res.data.active_label,
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

	// ── Pagination ──
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
				const res = await fetchGallery( {
					config,
					catSlug: currentCat,
					paged,
				} );
				if ( res.success ) {
					updatePage( {
						gridEl,
						paginationEl,
						filterEl,
						html: res.data.html,
						pagination: res.data.pagination,
						activeLabel: res.data.active_label,
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

// ── Bootstrap ─────────────────────────────────────────────────────────────────
function bootstrap() {
	init();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
