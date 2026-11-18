/**
 * Carousel điều hướng bằng 2 nút Trước / Sau cho block Image Card Grid.
 *
 * @param {Element} wrap Khung bọc carousel.
 */
const initCarousel = ( wrap ) => {
	const grid = wrap.querySelector( '.block-image-card-grid__grid--carousel' );
	const prevBtn = wrap.querySelector( '[data-icg-prev]' );
	const nextBtn = wrap.querySelector( '[data-icg-next]' );

	if ( ! grid || ( ! prevBtn && ! nextBtn ) ) {
		return;
	}

	const updateButtons = () => {
		const maxScroll = grid.scrollWidth - grid.clientWidth;
		if ( prevBtn ) {
			prevBtn.disabled = grid.scrollLeft <= 4;
		}
		if ( nextBtn ) {
			nextBtn.disabled = grid.scrollLeft >= maxScroll - 4;
		}
	};

	if ( prevBtn ) {
		prevBtn.addEventListener( 'click', () => {
			grid.scrollBy( {
				left: -grid.clientWidth * 0.85,
				behavior: 'smooth',
			} );
		} );
	}

	if ( nextBtn ) {
		nextBtn.addEventListener( 'click', () => {
			grid.scrollBy( {
				left: grid.clientWidth * 0.85,
				behavior: 'smooth',
			} );
		} );
	}

	grid.addEventListener( 'scroll', updateButtons, { passive: true } );
	window.addEventListener( 'resize', updateButtons, { passive: true } );
	updateButtons();
};

document.querySelectorAll( '[data-icg-carousel]' ).forEach( initCarousel );
