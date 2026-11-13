// Auto-load Media Library items when user scrolls near the bottom.
( function () {
	'use strict';

	const MEDIA_MODAL_SELECTOR = '.media-modal';
	const SCROLL_CONTAINER_SELECTORS = [
		'.media-modal-content .attachments-browser .attachments-wrapper',
		'.media-modal-content .attachments-browser .attachments',
		'.media-modal-content .attachments-browser',
		'.media-frame-content',
		'.media-modal-content',
	];
	const LOAD_MORE_BUTTON_SELECTOR = '.media-modal .load-more-wrapper .button';
	const BOTTOM_THRESHOLD = 220;

	function getScrollContainer( modal ) {
		for ( const selector of SCROLL_CONTAINER_SELECTORS ) {
			const node = modal.querySelector( selector );
			if ( node ) {
				return node;
			}
		}
		return null;
	}

	function isLoadMoreAvailable( button ) {
		if ( ! button ) {
			return false;
		}

		if ( button.disabled ) {
			return false;
		}

		if ( button.getAttribute( 'aria-disabled' ) === 'true' ) {
			return false;
		}

		return button.offsetParent !== null;
	}

	function maybeLoadMore( modal, scrollContainer ) {
		const button = modal.querySelector( LOAD_MORE_BUTTON_SELECTOR );
		if ( ! isLoadMoreAvailable( button ) ) {
			return;
		}

		const distanceToBottom =
			scrollContainer.scrollHeight -
			( scrollContainer.scrollTop + scrollContainer.clientHeight );
		if ( distanceToBottom <= BOTTOM_THRESHOLD ) {
			button.click();
		}
	}

	function attachInfiniteScroll( modal ) {
		if ( ! modal || modal.dataset.lacaMediaInfiniteBound === '1' ) {
			return;
		}

		const primaryScrollContainer = getScrollContainer( modal );
		if ( ! primaryScrollContainer ) {
			return;
		}

		modal.dataset.lacaMediaInfiniteBound = '1';

		const scrollContainers = new Set();
		scrollContainers.add( primaryScrollContainer );
		for ( const selector of SCROLL_CONTAINER_SELECTORS ) {
			const candidate = modal.querySelector( selector );
			if ( candidate ) {
				scrollContainers.add( candidate );
			}
		}

		let ticking = false;
		const onScroll = () => {
			if ( ticking ) {
				return;
			}

			ticking = true;
			window.requestAnimationFrame( () => {
				// Check against the primary scroll container for stable behavior.
				maybeLoadMore( modal, primaryScrollContainer );
				ticking = false;
			} );
		};

		for ( const container of scrollContainers ) {
			container.addEventListener( 'scroll', onScroll, { passive: true } );
		}
		maybeLoadMore( modal, primaryScrollContainer );
	}

	function initMediaLibraryInfiniteScroll() {
		const bindModals = () => {
			const modals = document.querySelectorAll( MEDIA_MODAL_SELECTOR );
			for ( const modal of modals ) {
				attachInfiniteScroll( modal );
			}
		};

		bindModals();

		const observer = new MutationObserver( () => {
			bindModals();
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener(
			'DOMContentLoaded',
			initMediaLibraryInfiniteScroll
		);
	} else {
		initMediaLibraryInfiniteScroll();
	}
} )();
