// Hiệu ứng block Top Hero (panel "Hiệu ứng" trong editor):
// - Zoom ảnh / chữ hiện lần lượt: animation CSS tự chạy lúc tải trang. Hero
//   nằm dưới màn hình lúc tải thì tạm dừng (is-effects-pending), cuộn tới mới
//   chạy — JS lỗi thì chữ/ảnh vẫn hiện bình thường, không bị ẩn.
// - Parallax: ghi --parallax-y (CSS ghép với scale đặt sẵn) — chạy trên GPU
//   và cả iOS, khác background-attachment: fixed.
// - Lớp phủ đậm dần: ghi --shade-opacity theo phần hero đã cuộn qua.
const heroes = document.querySelectorAll(
	'.block-top-hero--parallax, .block-top-hero--darken, .block-top-hero--zoom, .block-top-hero--reveal'
);
const reduceMotion = window.matchMedia(
	'(prefers-reduced-motion: reduce)'
).matches;

const clamp = ( value, min, max ) => Math.max( min, Math.min( max, value ) );

if ( heroes.length ) {
	const items = Array.from( heroes ).map( ( section ) => ( {
		section,
		parallax:
			! reduceMotion &&
			section.classList.contains( 'block-top-hero--parallax' )
				? clamp( parseFloat( section.dataset.parallax ) || 0.3, 0, 0.5 )
				: 0,
		darken: section.classList.contains( 'block-top-hero--darken' )
			? clamp( parseFloat( section.dataset.darken ) || 0.6, 0, 0.9 )
			: 0,
		visible: false,
	} ) );

	const viewportHeight = window.innerHeight;
	items.forEach( ( { section } ) => {
		const rect = section.getBoundingClientRect();
		const animated =
			section.classList.contains( 'block-top-hero--zoom' ) ||
			section.classList.contains( 'block-top-hero--reveal' );
		if ( animated && ( rect.top >= viewportHeight || rect.bottom <= 0 ) ) {
			section.classList.add( 'is-effects-pending' );
		}
	} );

	let ticking = false;

	const update = () => {
		ticking = false;
		const height = window.innerHeight;

		items.forEach( ( item ) => {
			if ( ! item.visible || ( ! item.parallax && ! item.darken ) ) {
				return;
			}
			const rect = item.section.getBoundingClientRect();

			if ( item.parallax ) {
				// -1 khi hero ở trên hẳn màn hình, 1 khi ở dưới hẳn, 0 khi giữa.
				const progress = clamp(
					( rect.top + rect.height / 2 - height / 2 ) /
						( height / 2 + rect.height / 2 ),
					-1,
					1
				);
				// Ảnh đã phóng to (1 + strength) → dịch tối đa strength/2 chiều
				// cao mỗi phía mà vẫn phủ kín khung.
				const shift = ( -progress * item.parallax * rect.height ) / 2;
				item.section.style.setProperty(
					'--parallax-y',
					`${ shift.toFixed( 1 ) }px`
				);
			}

			if ( item.darken ) {
				// 0 khi mép trên hero còn ở trong màn hình, tối đa khi đã cuộn
				// qua hết hero.
				const scrolledPast = clamp( -rect.top / rect.height, 0, 1 );
				item.section.style.setProperty(
					'--shade-opacity',
					( scrolledPast * item.darken ).toFixed( 3 )
				);
			}
		} );
	};

	const requestUpdate = () => {
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( update );
		}
	};

	const observer = new window.IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			const item = items.find( ( i ) => i.section === entry.target );
			if ( ! item ) {
				return;
			}
			item.visible = entry.isIntersecting;
			if ( entry.isIntersecting ) {
				entry.target.classList.remove( 'is-effects-pending' );
			}
		} );
		requestUpdate();
	} );
	items.forEach( ( item ) => observer.observe( item.section ) );

	if ( items.some( ( item ) => item.parallax || item.darken ) ) {
		window.addEventListener( 'scroll', requestUpdate, { passive: true } );
		window.addEventListener( 'resize', requestUpdate );
	}
}
