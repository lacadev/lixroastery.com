/**
 * Mobile Menu
 * Slide-in overlay menu với accordion submenu cho mobile.
 * NOTE: header.js chỉ xử lý scroll — không gắn listener trùng vào btn-hamburger.
 */

export function initMobileMenu() {
	const burgerBtn = document.getElementById( 'btn-hamburger' );
	const overlay = document.querySelector( '.header__overlay' );
	if ( ! burgerBtn || ! overlay ) {
		return;
	}

	const closeBtn = overlay.querySelector( '.header__overlay-close' );
	const backdrop = overlay.querySelector( '.header__overlay-backdrop' );

	const openMenu = () => {
		burgerBtn.classList.add( 'active' );
		overlay.classList.add( 'active' );
		document.body.classList.add( 'menu-open' );
	};

	const closeMenu = () => {
		burgerBtn.classList.remove( 'active' );
		overlay.classList.remove( 'active' );
		document.body.classList.remove( 'menu-open' );
	};

	// Hamburger toggle
	burgerBtn.addEventListener( 'click', () => {
		burgerBtn.classList.contains( 'active' ) ? closeMenu() : openMenu();
	} );

	// Close button
	closeBtn?.addEventListener( 'click', closeMenu );

	// Backdrop click
	backdrop?.addEventListener( 'click', closeMenu );

	// Escape key
	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && overlay.classList.contains( 'active' ) ) {
			closeMenu();
		}
	} );

	// ── Accordion: toggle submenu khi click vào link có children ──
	overlay
		.querySelectorAll(
			'.menu-item.has-children > a, .menu-item-has-children > a'
		)
		.forEach( ( link ) => {
			link.addEventListener( 'click', ( e ) => {
				e.preventDefault(); // không navigate
				e.stopPropagation();

				const parentLi = link.parentElement;
				const isOpen = parentLi.classList.contains( 'open' );

				// Đóng siblings cùng parent ul
				parentLi.parentElement
					?.querySelectorAll(
						':scope > .menu-item.has-children.open, :scope > .menu-item-has-children.open'
					)
					.forEach( ( sib ) => {
						if ( sib !== parentLi ) {
							sib.classList.remove( 'open' );
						}
					} );

				parentLi.classList.toggle( 'open', ! isOpen );
			} );
		} );

	// Click vào link con (không có children) → đóng overlay
	overlay
		.querySelectorAll(
			'.menu-item:not(.has-children):not(.menu-item-has-children) > a'
		)
		.forEach( ( link ) => {
			link.addEventListener( 'click', closeMenu );
		} );
}
