/* eslint-disable no-unused-vars */
import './public-path.js';
import '@images/favicon.ico';
import '@styles/tailwind.css'; // Tailwind v3: PostCSS only, no sass-loader
import '@styles/theme';
import './pages/*.js';
import './ajax-search.js';

// window.Swal cho script inline của [laca_contact_form]
// (ContactFormAjaxHandler.php) giờ do swal-lazy.js gán khi tải động —
// trang có form liên hệ thì tải ngay lúc khởi động (initPageFeatures).
import { loadSwal } from './swal-lazy.js';

import { initHeaderScroll, resetHeaderState }           from './components/header.js';
import { initMobileMenu, closeMobileMenu }             from './components/mobile-menu.js';
import { initContactPage }                             from './pages/contact.js';
import { initCommentForm }                             from './pages/comments.js';
import { initScrollReveal, initCounters, initRippleEffect } from './micro-interactions.js';

// ─── Device check ────────────────────────────────────────────────────────────
const isMobile = window.matchMedia && window.matchMedia( '(max-width: 768px)' ).matches;

// ─── GSAP context — reverted on each navigation ───────────────────────────────
let gsapCtx;

// ─── Per-page features ─────────────────────────────────────────────────────────
// Previous GSAP context is reverted before each re-init to prevent stale
// ScrollTriggers and infinite tweens (e.g. 404 spaceman) from leaking.
function initPageFeatures() {
	// Revert previous GSAP context: kills all tweens + ScrollTriggers from last page
	if ( gsapCtx ) {
		gsapCtx.revert();
	}

	// GSAP (~120KB) chỉ phục vụ cảnh 404 → tải động khi trang có #spaceman,
	// không bắt mọi trang tải trước (PageSpeed: unused JS + tranh băng thông LCP).
	if ( ! isMobile && document.querySelector( '#spaceman' ) ) {
		import( /* webpackChunkName: "gsap-404" */ './components/animations.js' ).then(
			( { default: gsap, setupGsap404 } ) => {
				gsapCtx = gsap.context( () => setupGsap404() );
			}
		);
	}

	// Form liên hệ gọi window.Swal ngay khi AJAX trả về (không qua proxy) →
	// trang có form thì tải luôn, không đợi người dùng focus.
	if ( document.querySelector( '.laca-contact-form' ) ) {
		loadSwal();
	}

	// Scroll-reveal and counters observe current DOM nodes.
	initScrollReveal();
	initCounters();

	initContactPage();
	initCommentForm();
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
document.addEventListener( 'DOMContentLoaded', () => {
	// Persistent features: bind to header/nav elements.
	// Called ONCE — safe to call again if needed.
	initHeaderScroll();
	initMobileMenu();
	initRippleEffect(); // document-level delegation — must only run once

	initPageFeatures();
	resetHeaderState();
	closeMobileMenu();
} );
