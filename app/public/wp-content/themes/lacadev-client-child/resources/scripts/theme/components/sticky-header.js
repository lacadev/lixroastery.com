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
 *   phải 1 số px cố định.
 *
 * ⚠️ Mốc "cuộn hết hero" PHẢI tính 1 LẦN duy nhất lúc tải trang (quy ra vị
 * trí TUYỆT ĐỐI so với đầu tài liệu — getBoundingClientRect().bottom +
 * scrollY lúc đó), KHÔNG được gọi lại getBoundingClientRect() trong chính
 * updateScrolledState() mỗi lần cuộn — bài học thật (lỗi "nhảy loạn" ngay
 * tại ranh giới): header chuyển fixed -> sticky làm nó bắt đầu CHIẾM CHỖ
 * trong luồng trở lại, đẩy hero dịch xuống đúng bằng chiều cao header NGAY
 * LÚC đang đo — khiến điều kiện vừa bật lại tắt, bật/tắt liên tục thành vòng
 * lặp vô hạn ngay tại điểm ranh giới. scrollY (độ cuộn thật) không bị ảnh
 * hưởng bởi việc CSS position của header đổi, nên so với 1 mốc tuyệt đối đã
 * cố định sẵn mới không dao động qua lại.
 */

function initStickyHeader() {
	const header = document.getElementById( 'header' );
	if ( ! header ) {
		return;
	}

	const heroEl = header.classList.contains( 'header--hero-overlay' )
		? document.querySelector( '.block-top-hero' )
		: null;
	const heroBottomAbsolute = heroEl
		? heroEl.getBoundingClientRect().bottom + window.scrollY
		: null;

	const updateScrolledState = () => {
		const scrolled =
			heroBottomAbsolute !== null
				? window.scrollY >= heroBottomAbsolute
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
