// Child theme custom scripts only.
// Parent theme core JS đã được enqueue trước qua handle `theme-js-bundle`.
import './components/footer-contact-form.js';
import './components/aos-global.js';
import './components/single-product-nav.js';
import { initMobileMenu } from './components/mobile-menu.js';

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initMobileMenu );
} else {
	initMobileMenu();
}
