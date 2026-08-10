/* eslint-disable no-unused-vars */
import '@images/favicon.ico';
import '@styles/tailwind.css'; // Tailwind v3: PostCSS only, no sass-loader
import '@styles/theme';
import './pages/*.js';
import './ajax-search.js';

import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

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
