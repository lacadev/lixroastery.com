// Mục lục single Journal: đề mục nằm trong 1 Tab Panel đang ẩn (block Tabs)
// thì bấm link sẽ không cuộn tới được — mở đúng tab đó trước. Handler chạy
// trước hành vi nhảy anchor mặc định nên panel đã hiện khi trình duyệt cuộn.
document.addEventListener( 'click', ( event ) => {
	const link = event.target.closest( '.journal-toc__link' );
	if ( ! link || ! link.hash ) {
		return;
	}

	const target = document.getElementById(
		decodeURIComponent( link.hash.slice( 1 ) )
	);
	const panel = target && target.closest( '.tab-panel' );
	if ( ! panel || panel.classList.contains( 'is-active' ) ) {
		return;
	}

	const panels = Array.from( panel.parentElement.children ).filter( ( el ) =>
		el.classList.contains( 'tab-panel' )
	);
	const nav =
		panel.parentElement.parentElement.querySelector( '.tabs-block__nav' );
	const tabLink = nav
		? nav.querySelectorAll( '.tabs-block__nav-link' )[
				panels.indexOf( panel )
		  ]
		: null;

	if ( tabLink ) {
		tabLink.click();
	}
} );
