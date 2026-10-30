/**
 * Glossary A-Z nav — click vào 1 chữ cái sẽ scroll tới thuật ngữ đầu tiên
 * bắt đầu bằng chữ cái đó (theme/archive-glossary.php).
 */
function initGlossaryNav() {
	const nav = document.querySelector( '.glossary-page__nav' );
	if ( ! nav ) {
		return;
	}

	nav.querySelectorAll( 'a[data-target]' ).forEach( ( link ) => {
		if ( link.classList.contains( 'glossary-page__nav-link--disabled' ) ) {
			return;
		}

		link.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const target = document.getElementById( link.dataset.target );
			if ( target ) {
				target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
	} );
}

/**
 * Mở /glossary/#<slug> (link thuật ngữ trong bài viết — GlossaryAutoLink.php)
 * → scroll tới thuật ngữ đó + tô nền khung để dễ nhận ra.
 */
function highlightFromHash() {
	let slug = '';
	try {
		slug = decodeURIComponent( window.location.hash.slice( 1 ) );
	} catch ( e ) {
		return;
	}
	slug = slug.replace( /\/+$/, '' );
	if ( ! slug ) {
		return;
	}

	const item = document.querySelector(
		`.glossary-page__item[data-slug="${ window.CSS.escape( slug ) }"]`
	);
	if ( ! item ) {
		return;
	}

	document
		.querySelectorAll( '.glossary-page__item--active' )
		.forEach( ( el ) =>
			el.classList.remove( 'glossary-page__item--active' )
		);
	item.classList.add( 'glossary-page__item--active' );
	item.scrollIntoView( { block: 'center' } );
}

function initGlossaryHighlight() {
	if ( ! document.querySelector( '.glossary-page' ) ) {
		return;
	}
	highlightFromHash();
	window.addEventListener( 'hashchange', highlightFromHash );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initGlossaryNav );
	document.addEventListener( 'DOMContentLoaded', initGlossaryHighlight );
} else {
	initGlossaryNav();
	initGlossaryHighlight();
}
