const initSingleProductNav = () => {
	const nav = document.querySelector( '.single-product-page__nav' );
	if ( ! nav ) {
		return;
	}

	const links = Array.from( nav.querySelectorAll( 'a[href^="#"]' ) );
	const sections = links
		.map( ( link ) => document.getElementById( link.getAttribute( 'href' ).slice( 1 ) ) )
		.filter( Boolean );

	if ( ! sections.length ) {
		return;
	}

	const setActive = ( id ) => {
		links.forEach( ( link ) => {
			link.classList.toggle( 'is-active', link.getAttribute( 'href' ) === `#${ id }` );
		} );
	};

	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					setActive( entry.target.id );
				}
			} );
		},
		{ rootMargin: '-45% 0px -45% 0px' }
	);

	sections.forEach( ( section ) => observer.observe( section ) );
	setActive( sections[ 0 ].id );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSingleProductNav );
} else {
	initSingleProductNav();
}
