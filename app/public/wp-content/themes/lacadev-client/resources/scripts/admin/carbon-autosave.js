/**
 * Carbon Fields Auto-Save Module
 * Provides seamless AJAX auto-saving for Laca Theme and Laca Admin without page reloads.
 *
 * @package LacaDevClient
 */

import { createAutoSaveEngine } from './laca-autosave.js';

export function initCarbonFieldsAutoSave() {
	const form = document.getElementById( 'theme-options-form' );
	if ( ! form ) {
		return;
	}

	let indicator = document.getElementById( 'laca-cf-autosave-indicator' );
	if ( ! indicator ) {
		indicator = document.createElement( 'div' );
		indicator.id = 'laca-cf-autosave-indicator';
		indicator.className = 'laca-autosave-indicator is-idle';
		indicator.setAttribute( 'aria-live', 'polite' );
		indicator.innerHTML = `
			<span class="laca-autosave-icon" aria-hidden="true"></span>
			<span class="laca-autosave-text"></span>
		`;
		document.body.appendChild( indicator );
	}

	const iconEl = indicator.querySelector( '.laca-autosave-icon' );
	const textEl = indicator.querySelector( '.laca-autosave-text' );
	let hideTimer = null;

	function setStatus( status, message ) {
		clearTimeout( hideTimer );
		indicator.className = `laca-autosave-indicator is-${ status }`;

		if ( textEl ) {
			textEl.textContent = message;
		}

		if ( iconEl ) {
			if ( status === 'saving' ) {
				iconEl.innerHTML = '<svg class="laca-autosave-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/></svg>';
			} else if ( status === 'saved' ) {
				iconEl.innerHTML = '<svg class="laca-autosave-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>';
			} else if ( status === 'error' ) {
				iconEl.innerHTML = '<svg class="laca-autosave-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
			} else {
				iconEl.innerHTML = '';
			}
		}

		const actionSpinner = document.querySelector( '#submitdiv .spinner' );
		if ( actionSpinner ) {
			actionSpinner.classList.toggle( 'is-active', status === 'saving' );
		}

		if ( status === 'saved' ) {
			hideTimer = setTimeout( () => {
				indicator.classList.remove( 'is-saved' );
				indicator.classList.add( 'is-idle' );
			}, 3500 );
		}
	}

	createAutoSaveEngine( {
		form,
		isCarbon: true,
		containerSelector: '.theme-options-container',
		onStatusChange: setStatus,
	} );
}
