/**
 * Header cố định (sticky) khi cuộn xuống — tham khảo hành vi của xliiicoffee
 * (setupMenuFixedBehavior() trong theme cũ, toggle class khi cuộn qua 1
 * ngưỡng) nhưng dùng position: sticky (CSS, xem _header.scss) thay vì
 * position: fixed + JS bù khoảng trống — sticky tự nhiên không làm nội dung
 * bên dưới bị nhảy vị trí khi header đổi trạng thái, đơn giản và an toàn hơn.
 *
 * JS ở đây CHỈ thêm class "is-scrolled":
 * - Trang thường: chỉ để hiện đổ bóng nhẹ khi đã cuộn xuống một chút
 *   (ngưỡng cố định 4px) — việc "dính" ở đầu trang đã do CSS lo hoàn toàn.
 * - Trang có header overlay (class "header--hero-overlay", xem
 *   theme/header.php + _header.scss) — "is-scrolled" còn quyết định cả việc
 *   header chuyển từ fixed/trong suốt sang sticky/nền trắng bình thường, nên
 *   ngưỡng ở đây phải là CUỘN HẾT khối Top Hero (.block-top-hero), không
 *   phải 1 số px cố định — đọc getBoundingClientRect() MỖI lần cuộn (không
 *   cache offsetHeight 1 lần lúc tải trang) để luôn đúng dù ảnh hero tải
 *   chậm làm đổi chiều cao sau đó.
 */

function initStickyHeader() {
	const header = document.getElementById( 'header' );
	if ( ! header ) {
		return;
	}

	const heroEl = header.classList.contains( 'header--hero-overlay' )
		? document.querySelector( '.block-top-hero' )
		: null;

	const updateScrolledState = () => {
		const scrolled = heroEl
			? heroEl.getBoundingClientRect().bottom <= 0
			: window.scrollY > 4;
		header.classList.toggle( 'is-scrolled', scrolled );
	};

	updateScrolledState();
	window.addEventListener( 'scroll', updateScrolledState, { passive: true } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initStickyHeader );
} else {
	initStickyHeader();
}
