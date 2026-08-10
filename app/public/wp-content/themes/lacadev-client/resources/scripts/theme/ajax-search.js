/**
 * AJAX Live Search - Vanilla JavaScript
 * No jQuery dependency - Pure JavaScript implementation
 *
 * @package
 * @since 1.0.0
 */

( function () {
	// Debug log
	console.log( 'AJAX Search script loaded!' );

	// Wait for DOM to be ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		console.log( 'AJAX Search: Initializing...' );

		// Find search elements
		const searchInput = document.querySelector(
			'.header__bottom-search input[type="text"]'
		);
		const searchForm = document.querySelector(
			'.header__bottom-search .search-box'
		);

		if ( ! searchInput ) {
			console.error( 'AJAX Search: No search input found!' );
			return;
		}

		console.log( 'Search input found:', searchInput );
		console.log(
			'themeSearch data:',
			typeof themeSearch !== 'undefined' ? themeSearch : 'NOT AVAILABLE'
		);

		// Create search results container if it doesn't exist
		let searchResults = searchForm.querySelector( '.search-results' );
		if ( ! searchResults ) {
			searchResults = document.createElement( 'div' );
			searchResults.className = 'search-results';
			searchForm.appendChild( searchResults );
		}

		console.log( 'Search results container ready' );

		// Debounce timer
		let searchTimeout = null;

		// Input event listener
		searchInput.addEventListener( 'input', ( e ) => {
			const searchQuery = e.target.value.trim();

			// Clear previous timeout
			clearTimeout( searchTimeout );

			// Clear results if query is too short
			if ( searchQuery.length < 2 ) {
				searchResults.innerHTML = '';
				searchResults.classList.remove( 'active' );
				return;
			}

			// Debounce - wait 300ms after user stops typing
			searchTimeout = setTimeout( () => {
				performSearch( searchQuery, searchResults );
			}, 300 );
		} );

		// Click outside to close results
		document.addEventListener( 'click', ( e ) => {
			if ( ! e.target.closest( '.header__bottom-search' ) ) {
				searchResults.classList.remove( 'active' );
			}
		} );

		// Show results when focusing on input (if there are results)
		searchInput.addEventListener( 'focus', () => {
			if ( searchResults.innerHTML.trim() !== '' ) {
				searchResults.classList.add( 'active' );
			}
		} );

		// Clear search on form reset
		if ( searchForm ) {
			searchForm.addEventListener( 'reset', () => {
				setTimeout( () => {
					searchResults.innerHTML = '';
					searchResults.classList.remove( 'active' );
				}, 10 );
			} );
		}
	}

	/**
	 * Perform AJAX search request
	 * @param query
	 * @param resultsContainer
	 */
	function performSearch( query, resultsContainer ) {
		console.log( 'Performing search for:', query );

		// Check if themeSearch is available
		if ( typeof themeSearch === 'undefined' ) {
			console.error( 'themeSearch data not available!' );
			return;
		}

		console.log( 'AJAX URL:', themeSearch.ajaxurl );

		// Show loading indicator
		resultsContainer.innerHTML =
			'<div class="search-results__loading">Đang tìm kiếm...</div>';
		resultsContainer.classList.add( 'active' );

		// Build query string
		const params = new URLSearchParams( {
			action: 'ajax_search',
			s: query,
			nonce: themeSearch.nonce,
		} );

		// Perform fetch request
		fetch( `${ themeSearch.ajaxurl }?${ params.toString() }`, {
			method: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}
				return response.text();
			} )
			.then( ( html ) => {
				console.log( 'AJAX: Success! Response received' );
				if ( html && html.trim() !== '' ) {
					resultsContainer.innerHTML = html;
					resultsContainer.classList.add( 'active' );
				} else {
					resultsContainer.innerHTML =
						'<div class="search-results__empty"><p>Không tìm thấy kết quả</p></div>';
					resultsContainer.classList.add( 'active' );
				}
			} )
			.catch( ( error ) => {
				console.error( 'AJAX: Error!', error );
				resultsContainer.innerHTML =
					'<div class="search-results__error">Có lỗi xảy ra. Vui lòng thử lại.</div>';
				resultsContainer.classList.add( 'active' );
			} );
	}
} )();
