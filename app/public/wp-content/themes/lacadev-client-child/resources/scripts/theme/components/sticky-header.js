/**
 * Header cố định (sticky) khi cuộn xuống — tham khảo hành vi của xliiicoffee
 * (setupMenuFixedBehavior() trong theme cũ, toggle class khi cuộn qua 1
 * ngưỡng) nhưng dùng position: sticky (CSS, xem _header.scss) thay vì
 * position: fixed + JS bù khoảng trống — sticky tự nhiên không làm nội dung
 * bên dưới bị nhảy vị trí khi header đổi trạng thái, đơn giản và an toàn hơn.
 *
 * JS ở đây CHỈ thêm class "is-scrolled" để hiện đổ bóng nhẹ khi đã cuộn
 * xuống một chút — việc "dính" ở đầu trang khi cuộn đã do CSS lo hoàn toàn.
 */

function initStickyHeader() {
	const header = document.getElementById( 'header' );
	if ( ! header ) {
		return;
	}

	const updateScrolledState = () => {
		header.classList.toggle( 'is-scrolled', window.scrollY > 4 );
	};

	updateScrolledState();
	window.addEventListener( 'scroll', updateScrolledState, { passive: true } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initStickyHeader );
} else {
	initStickyHeader();
}
