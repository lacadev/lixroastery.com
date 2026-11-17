// Nền động của block Top Hero: slide nhiều ảnh (chuyển mờ dần) + nút dừng
// video. Câu chữ (aria-label) do render.php in sẵn qua data-*, JS không ghi
// cứng chữ. Người xem bật "giảm chuyển động" → slide không tự chạy, video
// dừng ở khung đầu.
const reduceMotion = window.matchMedia(
	'(prefers-reduced-motion: reduce)'
).matches;

const initSlider = ( section ) => {
	const slides = Array.from(
		section.querySelectorAll( '.block-top-hero__slide' )
	);
	if ( slides.length < 2 ) {
		return;
	}
	const dots = Array.from( section.querySelectorAll( '[data-hero-dot]' ) );
	const interval = Math.max(
		2000,
		parseInt( section.dataset.interval, 10 ) || 5000
	);
	const autoplay = 'autoplay' in section.dataset && ! reduceMotion;
	let current = 0;
	let timer = null;
	let visible = true;
	let hovered = false;

	const goTo = ( index ) => {
		const next = ( index + slides.length ) % slides.length;
		if ( next === current ) {
			return;
		}
		slides[ current ].classList.remove( 'is-active' );
		slides[ next ].classList.add( 'is-active' );
		// Ảnh lazy chưa tải thì ép tải ngay để không hiện khung trống.
		slides[ next ].loading = 'eager';
		dots.forEach( ( dot, i ) => {
			dot.classList.toggle( 'is-active', i === next );
			if ( i === next ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
		current = next;
	};

	const stop = () => {
		window.clearInterval( timer );
		timer = null;
	};
	const start = () => {
		stop();
		if ( autoplay && visible && ! hovered ) {
			timer = window.setInterval( () => goTo( current + 1 ), interval );
		}
	};

	// Tải sẵn ảnh thứ 2, tránh nháy trống ở lần chuyển đầu tiên.
	slides[ 1 ].loading = 'eager';

	dots.forEach( ( dot ) =>
		dot.addEventListener( 'click', () => {
			goTo( parseInt( dot.dataset.heroDot, 10 ) || 0 );
			start();
		} )
	);
	section
		.querySelector( '[data-hero-prev]' )
		?.addEventListener( 'click', () => {
			goTo( current - 1 );
			start();
		} );
	section
		.querySelector( '[data-hero-next]' )
		?.addEventListener( 'click', () => {
			goTo( current + 1 );
			start();
		} );

	// Dừng khi rê chuột / focus bàn phím vào hero, hoặc hero ra khỏi màn hình.
	const pause = () => {
		hovered = true;
		stop();
	};
	const resume = () => {
		hovered = false;
		start();
	};
	section.addEventListener( 'mouseenter', pause );
	section.addEventListener( 'mouseleave', resume );
	section.addEventListener( 'focusin', pause );
	section.addEventListener( 'focusout', resume );

	new window.IntersectionObserver( ( [ entry ] ) => {
		visible = entry.isIntersecting;
		start();
	} ).observe( section );
};

const initVideo = ( section ) => {
	const video = section.querySelector( '.block-top-hero__video' );
	if ( ! video ) {
		return;
	}
	const toggle = section.querySelector( '[data-hero-video-toggle]' );
	let userPaused = reduceMotion;

	const sync = () => {
		if ( ! toggle ) {
			return;
		}
		const paused = video.paused;
		toggle.setAttribute( 'aria-pressed', paused ? 'true' : 'false' );
		toggle.setAttribute(
			'aria-label',
			paused ? toggle.dataset.labelPlay : toggle.dataset.labelPause
		);
		toggle.classList.toggle( 'is-paused', paused );
	};
	const play = () => {
		const promise = video.play();
		// Trình duyệt chặn tự phát (vd chế độ tiết kiệm pin) → giữ poster.
		if ( promise ) {
			promise.catch( sync );
		}
	};

	if ( reduceMotion ) {
		video.removeAttribute( 'autoplay' );
		video.pause();
	}
	video.addEventListener( 'play', sync );
	video.addEventListener( 'pause', sync );
	sync();

	toggle?.addEventListener( 'click', () => {
		userPaused = ! video.paused;
		if ( userPaused ) {
			video.pause();
		} else {
			play();
		}
	} );

	// Ra khỏi màn hình thì dừng cho đỡ tốn CPU/pin, quay lại thì phát tiếp
	// (trừ khi người xem đã tự bấm dừng).
	new window.IntersectionObserver( ( [ entry ] ) => {
		if ( ! entry.isIntersecting ) {
			video.pause();
		} else if ( ! userPaused ) {
			play();
		}
	} ).observe( section );
};

document.querySelectorAll( '[data-hero-slider]' ).forEach( initSlider );
document
	.querySelectorAll( '.block-top-hero--media-video' )
	.forEach( initVideo );
