// Link chia sẻ mở popup nhỏ thay vì tab mới (href vẫn là link thật — không có JS vẫn chia sẻ được).
document.addEventListener( 'click', ( event ) => {
	const link = event.target.closest( '[data-share-popup]' );
	if ( ! link ) {
		return;
	}

	// Không truyền "noopener" vào features — khi đó window.open luôn trả null,
	// không biết popup có bị chặn không. Tự cắt opener thay thế.
	const popup = window.open(
		link.href,
		'share-post',
		'width=600,height=600,top=150,left=250'
	);
	if ( popup ) {
		popup.opener = null;
		event.preventDefault();
	}
} );
