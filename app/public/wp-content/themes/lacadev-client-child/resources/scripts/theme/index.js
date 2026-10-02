// Child theme custom scripts only.
// Parent theme core JS đã được enqueue trước qua handle `theme-js-bundle`.
import './components/sticky-header.js';
import './components/footer-contact-form.js';
import './components/aos-global.js';
import './components/single-product-nav.js';
import './components/glossary-nav.js';
import './components/cpt-grid.js';
import './components/journal-directory.js';
import './components/floating-contact.js';
import { initMobileMenu } from './components/mobile-menu.js';
import { initSearchPopup } from './components/search-popup.js';
import { initCartPopup } from './components/cart-popup.js';

const initHeaderInteractions = () => {
	initMobileMenu();
	initSearchPopup();
	initCartPopup();
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initHeaderInteractions );
} else {
	initHeaderInteractions();
}
