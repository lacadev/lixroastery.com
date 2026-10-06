/* eslint-disable no-unused-vars */
import '@images/favicon.ico';
import '@styles/tailwind.css'; // Tailwind v3: PostCSS only, no sass-loader
import '@styles/theme';
import './pages/*.js';
import './ajax-search.js';

import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

// Expose window.Swal cho MỌI site dùng theme này — ContactFormAjaxHandler
// (app/src/Features/ContactForm/ContactFormAjaxHandler.php) in ra 1 script
// inline ngay trong shortcode [laca_contact_form] giả định window.Swal đã
// có sẵn ("Swal sẽ available vì theme.js chạy trước") nhưng bundle public
// trước đây KHÔNG tự expose global này (chỉ admin/index.js làm) — mọi form
// liên hệ trên frontend vì vậy luôn rơi vào fallback alert()/banner thay vì
// popup SweetAlert2 thật.
import Swal from 'sweetalert2';
window.Swal = Swal;

import {setupGsap404 } from './components/animations.js';
import { initHeaderScroll, resetHeaderState }           from './components/header.js';
import { initMobileMenu, closeMobileMenu }             from './components/mobile-menu.js';
import { initContactPage }                             from './pages/contact.js';
import { initCommentForm }                             from './pages/comments.js';
import { initScrollReveal, initCounters, initRippleEffect } from './micro-interactions.js';

gsap.registerPlugin( ScrollTrigger );

// ─── Device check ────────────────────────────────────────────────────────────
const isMobile = window.matchMedia && window.matchMedia( '(max-width: 768px)' ).matches;

// Show loader ngay trước DOMContentLoaded để tránh flash of content
if ( ! isMobile && shouldShowLoader() ) {
	document.documentElement.classList.add( 'loading' );
}

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

	gsapCtx = gsap.context( () => {
		if ( ! isMobile ) {
			setupGsap404();
			initAnimations();
			animateText();
		}
		initAboutLacaHero();
	} );

	// Scroll-reveal and counters observe current DOM nodes.
	initScrollReveal();
	initCounters();

	initContactPage();
	initCommentForm();

	setTimeout( () => ScrollTrigger.refresh(), 500 );
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
document.addEventListener( 'DOMContentLoaded', () => {
	// Persistent features: bind to header/nav elements.
	// Called ONCE — safe to call again if needed.
	initHeaderScroll();
	initMobileMenu();
	initRippleEffect(); // document-level delegation — must only run once

	initPageFeatures();
	initPageLoader( isMobile );
	resetHeaderState();
	closeMobileMenu();
} );
