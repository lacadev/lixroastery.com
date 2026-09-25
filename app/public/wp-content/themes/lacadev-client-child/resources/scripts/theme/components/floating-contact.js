/**
 * Nút liên hệ nổi (góc dưới phải): bấm nút chính để mở/đóng nhóm nút con
 * (lên đầu trang / gọi điện / Zalo) — tham khảo cách làm của xliiicoffee
 * (theme/footer.php #show_icon_contact/#show_hidden), viết lại theo quy ước
 * component riêng của site này thay vì nhúng <script> thẳng trong footer.php.
 */

const SCROLL_SHOW_THRESHOLD = 600;

function initFloatingContact() {
	const toggleBtn = document.getElementById( 'floating-contact-toggle' );
	const group = document.getElementById( 'floating-contact-group' );
	const backToTopBtn = document.getElementById( 'floating-back-to-top' );

	if ( toggleBtn && group ) {
		toggleBtn.addEventListener( 'click', () => {
			const isHidden = group.classList.toggle( 'is-hidden' );
			toggleBtn.classList.toggle( 'is-active', ! isHidden );
			toggleBtn.setAttribute( 'aria-expanded', isHidden ? 'false' : 'true' );
		} );
	}

	if ( backToTopBtn ) {
		window.addEventListener( 'scroll', () => {
			backToTopBtn.classList.toggle( 'is-visible', window.scrollY > SCROLL_SHOW_THRESHOLD );
		} );

		backToTopBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initFloatingContact );
} else {
	initFloatingContact();
}
