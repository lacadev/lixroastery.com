/**
 * Header
 * Ẩn/hiện header khi scroll, thêm class scrolled khi ra khỏi top.
 */

// Module-level để resetHeaderState() có thể sync lại
let lastScrollTop = 0;

export function initHeaderScroll() {
	const header = document.getElementById( 'header' );
	if ( ! header ) return;

	const controller = new AbortController();
	const THRESHOLD = 100;

	window.addEventListener( 'scroll', () => {
		const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

		header.classList.toggle( 'header--scrolled', scrollTop > 50 );

		if ( scrollTop > THRESHOLD ) {
			header.classList.toggle( 'header--hidden', scrollTop > lastScrollTop );
		} else {
			header.classList.remove( 'header--hidden' );
		}

		lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
	}, { passive: true, signal: controller.signal } );

	return () => controller.abort();
}

/**
 * Đồng bộ lại trạng thái header sau khi khởi tạo trang.
 * Giúp tránh header bị kẹt ở trạng thái trang cũ.
 */
export function resetHeaderState() {
	const header = document.getElementById( 'header' );
	if ( ! header ) return;

	const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

	// Sync lastScrollTop về vị trí hiện tại
	lastScrollTop = scrollTop;

	header.classList.toggle( 'header--scrolled', scrollTop > 50 );
	// Luôn show header khi vào trang mới
	header.classList.remove( 'header--hidden' );
}
