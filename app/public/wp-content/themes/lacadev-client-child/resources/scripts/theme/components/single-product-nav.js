// single-product-nav.js — điều khiển chuyển tab chi tiết sản phẩm
const initSingleProductNav = () => {
	const tablist = document.querySelector(
		'.single-product-page__tabs[role="tablist"]'
	);
	if ( ! tablist ) {
		return;
	}

	const tabs = Array.from( tablist.querySelectorAll( 'button[role="tab"]' ) );
	if ( ! tabs.length ) {
		return;
	}

	const panels = tabs
		.map( ( tab ) => {
			const id = tab.getAttribute( 'aria-controls' );
			return id ? document.getElementById( id ) : null;
		} )
		.filter( Boolean );

	const switchTab = ( targetTab, updateHash = true ) => {
		if ( ! targetTab ) {
			return;
		}

		tabs.forEach( ( tab ) => {
			const isTarget = tab === targetTab;
			tab.setAttribute( 'aria-selected', isTarget ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', isTarget ? '0' : '-1' );
			tab.classList.toggle( 'is-active', isTarget );
		} );

		const activePanelId = targetTab.getAttribute( 'aria-controls' );
		panels.forEach( ( panel ) => {
			const isMatch = panel.id === activePanelId;
			if ( isMatch ) {
				panel.removeAttribute( 'hidden' );
			} else {
				panel.setAttribute( 'hidden', '' );
			}
		} );

		if ( updateHash ) {
			const slug = targetTab.id.replace( /^tab-/, '' );
			if ( slug && window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', `#${ slug }` );
			}
		}
	};

	// Xử lý click
	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			switchTab( tab, true );
		} );
	} );

	// Phím mũi tên / Home / End roving tabindex
	tablist.addEventListener( 'keydown', ( e ) => {
		const doc = tablist.ownerDocument || document;
		const currentIndex = tabs.indexOf( doc.activeElement );
		if ( currentIndex === -1 ) {
			return;
		}

		let nextIndex = null;
		if ( e.key === 'ArrowRight' ) {
			nextIndex = ( currentIndex + 1 ) % tabs.length;
		} else if ( e.key === 'ArrowLeft' ) {
			nextIndex = ( currentIndex - 1 + tabs.length ) % tabs.length;
		} else if ( e.key === 'Home' ) {
			nextIndex = 0;
		} else if ( e.key === 'End' ) {
			nextIndex = tabs.length - 1;
		}

		if ( nextIndex !== null ) {
			e.preventDefault();
			tabs[ nextIndex ].focus();
			switchTab( tabs[ nextIndex ], true );
		}
	} );

	const findTabByHash = () => {
		const slug = ( window.location.hash || '' ).replace( /^#/, '' );
		return slug
			? tabs.find( ( tab ) => tab.id === `tab-${ slug }` ) || null
			: null;
	};

	// Mở tab theo hash lúc load; link "#reviews"… bấm trong trang (hashchange)
	// cũng chuyển đúng tab thay vì không làm gì vì không có id trùng hash.
	switchTab( findTabByHash() || tabs[ 0 ], false );

	window.addEventListener( 'hashchange', () => {
		const target = findTabByHash();
		if ( target ) {
			switchTab( target, false );
			tablist.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	} );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSingleProductNav );
} else {
	initSingleProductNav();
}
