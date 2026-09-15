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

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initGlossaryNav );
} else {
	initGlossaryNav();
}
