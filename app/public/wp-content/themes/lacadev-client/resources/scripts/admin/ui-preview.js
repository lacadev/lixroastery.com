/**
 * Laca Admin UI Preview Switcher (Cookie-driven, zero DB writes)
 */
( function () {
	'use strict';

	function getCookie( name ) {
		const matches = document.cookie.match(
			new RegExp( '(?:^|; )' + name.replace( /([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1' ) + '=([^;]*)' )
		);
		return matches ? decodeURIComponent( matches[ 1 ] ) : null;
	}

	function setCookie( name, value ) {
		document.cookie = name + '=' + encodeURIComponent( value ) + '; path=/wp-admin; SameSite=Lax';
	}

	function deleteCookie( name ) {
		document.cookie = name + '=; path=/wp-admin; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
	}

	function getPreviewState() {
		try {
			const raw = getCookie( 'laca_ui_preview' );
			return raw ? JSON.parse( raw ) : {};
		} catch ( e ) {
			return {};
		}
	}

	function savePreviewState( state ) {
		setCookie( 'laca_ui_preview', JSON.stringify( state ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const bar = document.getElementById( 'laca-preview-bar' );
		const data = window.LacaUiPreviewData || null;

		if ( ! bar || ! data ) {
			return;
		}

		const areaSelect = bar.querySelector( '#laca-pv-area-select' );
		const optButtons = bar.querySelectorAll( '.laca-pv-opt-btn' );
		const descEl = bar.querySelector( '#laca-pv-desc' );
		const sampleLink = bar.querySelector( '#laca-pv-sample-link' );
		const exitBtn = bar.querySelector( '#laca-pv-exit-btn' );

		let currentArea = areaSelect ? areaSelect.value : 'menu';
		const state = getPreviewState();

		function updateUiForArea( areaKey ) {
			currentArea = areaKey;
			const areaConfig = data.areas[ areaKey ];
			if ( ! areaConfig ) {
				return;
			}

			const currentOpt = ( state && state[ areaKey ] ) ? state[ areaKey ].toLowerCase() : 'a';

			// Update buttons
			optButtons.forEach( function ( btn ) {
				const opt = btn.getAttribute( 'data-opt' );
				if ( opt === currentOpt ) {
					btn.classList.add( 'is-active' );
				} else {
					btn.classList.remove( 'is-active' );
				}
			} );

			// Update description
			if ( descEl && areaConfig.options[ currentOpt ] ) {
				descEl.textContent = areaConfig.options[ currentOpt ].desc || '';
			}

			// Update sample link
			if ( sampleLink && areaConfig.sampleUrl ) {
				const baseUrl = data.adminUrl || '';
				sampleLink.href = baseUrl + areaConfig.sampleUrl;
			}
		}

		if ( areaSelect ) {
			areaSelect.addEventListener( 'change', function () {
				updateUiForArea( this.value );
			} );
		}

		optButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				const opt = this.getAttribute( 'data-opt' );
				const nextState = Object.assign( {}, getPreviewState() );
				nextState[ currentArea ] = opt;
				savePreviewState( nextState );
				window.location.reload();
			} );
		} );

		if ( exitBtn ) {
			exitBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				deleteCookie( 'laca_ui_preview' );
				window.location.reload();
			} );
		}

		// Keyboard shortcut: ArrowLeft / ArrowRight to cycle options
		document.addEventListener( 'keydown', function ( e ) {
			const tag = ( e.target.tagName || '' ).toLowerCase();
			if ( tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable ) {
				return;
			}

			if ( e.key === 'ArrowLeft' || e.key === 'ArrowRight' ) {
				const opts = [ 'a', 'b', 'c' ];
				const currentOpt = ( state && state[ currentArea ] ) ? state[ currentArea ].toLowerCase() : 'a';
				let idx = opts.indexOf( currentOpt );
				if ( idx === -1 ) {
					idx = 0;
				}

				if ( e.key === 'ArrowLeft' ) {
					idx = ( idx - 1 + opts.length ) % opts.length;
				} else {
					idx = ( idx + 1 ) % opts.length;
				}

				const nextOpt = opts[ idx ];
				const nextState = Object.assign( {}, getPreviewState() );
				nextState[ currentArea ] = nextOpt;
				savePreviewState( nextState );
				window.location.reload();
			}
		} );

		// Initial render for active area
		updateUiForArea( currentArea );
	} );
} )();
