/**
 * Micro-Interactions — Intersection Observer
 *
 * Theo dõi các element có class `.laca-reveal` và thêm `.is-visible`
 * khi chúng enter viewport, kích hoạt CSS transition từ _micro-interactions.scss.
 *
 * Sử dụng:
 *   HTML: <div class="laca-reveal laca-reveal--left laca-reveal--delay-2">...</div>
 *   JS:   import './micro-interactions'; (hoặc đã bundled vào theme.js)
 *
 * @package LacaDev
 */

/**
 * Khởi tạo Intersection Observer cho scroll-reveal animations.
 */
function initScrollReveal() {
	// Browser hỗ trợ Intersection Observer không?
	if ( ! ( 'IntersectionObserver' in window ) ) {
		// Fa llback: show tất cả ngay lập tức
		document.querySelectorAll( '.laca-reveal' ).forEach( ( el ) => {
			el.classList.add( 'is-visible' );
		} );
		return;
	}

	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					// Unobserve sau khi đã animated (animation 1 lần)
					observer.unobserve( entry.target );
				}
			} );
		},
		{
			threshold: 0.12, // Xuất hiện 12% thì trigger
			rootMargin: '0px 0px -40px 0px', // Trigger trước khi tới bottom viewport 40px
		}
	);

	document.querySelectorAll( '.laca-reveal' ).forEach( ( el ) => {
		observer.observe( el );
	} );
}

/**
 * Animated counter khi số xuất hiện trong viewport.
 *
 * HTML: <span class="laca-counter" data-target="1250" data-suffix="+">0</span>
 */
function initCounters() {
	if ( ! ( 'IntersectionObserver' in window ) ) return;

	const counterObserver = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) return;

				const el     = entry.target;
				const target = parseInt( el.dataset.target || '0', 10 );
				const suffix = el.dataset.suffix || '';
				const duration = parseInt( el.dataset.duration || '1500', 10 );

				if ( ! target ) return;

				counterObserver.unobserve( el );

				const startTime = performance.now();

				function updateCounter( now ) {
					const elapsed  = now - startTime;
					const progress = Math.min( elapsed / duration, 1 );
					// Ease out cubic
					const eased  = 1 - Math.pow( 1 - progress, 3 );
					const current = Math.round( eased * target );

					el.textContent = current.toLocaleString( 'vi-VN' ) + suffix;

					if ( progress < 1 ) {
						requestAnimationFrame( updateCounter );
					}
				}

				requestAnimationFrame( updateCounter );
			} );
		},
		{ threshold: 0.5 }
	);

	document.querySelectorAll( '.laca-counter[data-target]' ).forEach( ( el ) => {
		counterObserver.observe( el );
	} );
}

/**
 * Thêm ripple effect khi click button.
 * Hoạt động với bất kỳ button nào có class `.laca-ripple`.
 */
function initRippleEffect() {
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '.laca-ripple' );
		if ( ! btn ) return;

		const rect   = btn.getBoundingClientRect();
		const size   = Math.max( rect.width, rect.height ) * 2;
		const x      = e.clientX - rect.left - size / 2;
		const y      = e.clientY - rect.top - size / 2;

		const ripple = document.createElement( 'span' );
		ripple.style.cssText = `
			position: absolute;
			width: ${ size }px;
			height: ${ size }px;
			left: ${ x }px;
			top: ${ y }px;
			background: rgba(255,255,255,.3);
			border-radius: 50%;
			transform: scale(0);
			animation: laca-ripple-anim .6s linear;
			pointer-events: none;
		`;

		// Inject keyframe nếu chưa có
		if ( ! document.getElementById( 'laca-ripple-style' ) ) {
			const style = document.createElement( 'style' );
			style.id    = 'laca-ripple-style';
			style.textContent = `
				@keyframes laca-ripple-anim {
					to { transform: scale(1); opacity: 0; }
				}
			`;
			document.head.appendChild( style );
		}

		btn.style.position = 'relative';
		btn.style.overflow = 'hidden';
		btn.appendChild( ripple );

		ripple.addEventListener( 'animationend', () => ripple.remove() );
	} );
}

export { initScrollReveal, initCounters, initRippleEffect };
