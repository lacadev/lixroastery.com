/**
 * Search Results - Load More Functionality
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const loadMoreButtons = document.querySelectorAll( '.load-more-btn' );

	if ( ! loadMoreButtons.length ) {
		return;
	}

	loadMoreButtons.forEach( ( button ) => {
		button.addEventListener( 'click', handleLoadMore );
	} );

	async function handleLoadMore( e ) {
		e.preventDefault();

		const button = e.currentTarget;
		const postType = button.dataset.postType;
		const searchQuery = button.dataset.search;
		const currentPage = parseInt( button.dataset.page, 10 );

		button.disabled = true;
		const originalText = button.textContent;
		button.textContent = 'Đang tải...';
		button.classList.add( 'loading' );

		try {
			const formData = new FormData();
			formData.append( 'action', 'load_more_search' );
			formData.append( 'nonce', window.themeSearch.nonce );
			formData.append( 'post_type', postType );
			formData.append( 'search', searchQuery );
			formData.append( 'paged', currentPage + 1 );

			const response = await fetch( window.themeSearch.ajaxurl, {
				method: 'POST',
				body: formData,
			} );

			const data = await response.json();

			if ( data.success ) {
				const section = button.closest( '.search-section' );
				const resultsContainer = section.querySelector( '.list-post' );

				if ( resultsContainer ) {
					const tempDiv = document.createElement( 'div' );
					tempDiv.innerHTML = data.data.html;

					const newItemsCount =
						tempDiv.querySelectorAll( '.loop-service' ).length;

					resultsContainer.insertAdjacentHTML(
						'beforeend',
						data.data.html
					);

					const countSpan = section.querySelector(
						'.search-section__count'
					);
					if ( countSpan ) {
						const currentDisplayed = parseInt(
							countSpan.dataset.displayed,
							10
						);
						const totalCount = parseInt(
							countSpan.dataset.total,
							10
						);
						const newDisplayed = Math.min(
							currentDisplayed + newItemsCount,
							totalCount
						);

						countSpan.dataset.displayed = newDisplayed;

						const countText = `(hiển thị ${ newDisplayed }/${ totalCount })`;
						countSpan.textContent = countText;
					}
				}

				button.dataset.page = data.data.next_page;

				if ( ! data.data.has_more ) {
					button.style.display = 'none';
				}

				button.disabled = false;
				button.textContent = originalText;
				button.classList.remove( 'loading' );
			} else {
				button.style.display = 'none';
			}
		} catch ( error ) {
			button.disabled = false;
			button.textContent = originalText;
			button.classList.remove( 'loading' );
		}
	}
} );
