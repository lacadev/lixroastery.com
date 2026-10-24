import '@styles/admin';
import './custom_thumbnail_support.js';
import './contact-form.js';


import Swal from 'sweetalert2';
window.Swal = Swal;

// ===== Premium Welcome Notification =====
document.addEventListener( 'DOMContentLoaded', function () {
	const showAlert = localStorage.getItem( 'show_alert' );
	if ( showAlert ) {
		try {
			const alertData = JSON.parse( showAlert );
			Swal.fire( {
				html: `
					<div class="alp-swal-content">
						<div class="alp-swal-icon">${ alertData.icon }</div>
						<h2 class="alp-swal-title">${ alertData.title }</h2>
						<p class="alp-swal-msg">${ alertData.message }</p>
					</div>
				`,
				position: 'center',
				showConfirmButton: false,
				timer: 4000,
				timerProgressBar: true,
				background: 'transparent',
				customClass: {
					popup: 'alp-welcome-swal',
				},
				showClass: {
					popup: 'alp-zoom-in',
				},
				hideClass: {
					popup: 'alp-zoom-out',
				},
			} );
			localStorage.removeItem( 'show_alert' );
		} catch ( e ) {
			console.error( 'Error rendering welcome notification:', e );
			localStorage.removeItem( 'show_alert' );
		}
	}
} );

// ===== Vanilla JS - No jQuery dependency =====
const scripts = {
	frame: null,
	init() {
		this.frame = wp.media( {
			title: 'Select image',
			button: {
				text: 'Use this image',
			},
			multiple: false,
		} );
	},
	disableTheGrid() {
		const form = document.querySelector( 'form#posts-filter' );
		if ( ! form ) {
			return;
		}

		form.insertAdjacentHTML(
			'beforeend',
			`
      <div class="gm-loader" style="position:absolute;z-index:99999999;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;background-color:rgba(192,192,192,0.51);color:#000000">
        Updating
      </div>
    `
		);
	},
	enableTheGrid() {
		const loader = document.querySelector( 'form#posts-filter .gm-loader' );
		if ( loader ) {
			loader.remove();
		}
	},
};

// Xử lý khi nhấn vào nút thay đổi ảnh đại diện bài viết
document.addEventListener( 'click', function ( e ) {
	const trigger = e.target.closest( '[data-trigger-change-thumbnail-id]' );
	if ( ! trigger ) {
		return;
	}

	const postId = trigger.dataset.postId;
	const thisButton = trigger;

	const frame = wp.media( {
		title: 'Select image',
		button: {
			text: 'Use this image',
		},
		multiple: false,
	} );

	frame.on( 'select', function () {
		const attachment = frame.state().get( 'selection' ).first().toJSON();
		const attachmentId = attachment.id;
		const originalImageUrl = attachment.url || null;

		scripts.disableTheGrid();

		// Get nonce from data attribute (preferred) or fallback to global
		const nonce =
			trigger.dataset.nonce ||
			( typeof ajaxurl_params !== 'undefined'
				? ajaxurl_params.nonce
				: '' );

		fetch( '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action: 'update_post_thumbnail_id',
				post_id: postId,
				attachment_id: attachmentId,
				nonce, // WordPress nonce for CSRF protection
			} ),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success === true ) {
					// Find the parent TD cell to replace entire content
					const tdCell = thisButton.closest( 'td' );

					if ( tdCell ) {
						// Replace entire cell content with thumbnail + remove button (same as PHP output)
						// Preserve nonce for security
						const preservedNonce =
							nonce ||
							( typeof ajaxurl_params !== 'undefined'
								? ajaxurl_params.nonce
								: '' );
						tdCell.innerHTML = `
              <div style='position:relative;display:inline-block;'>
                <a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='${ postId }' data-nonce='${ preservedNonce }'>
                  <img src='${ originalImageUrl }' style='max-width:80px;max-height:80px;display:block;' alt='Thumbnail'/>
                </a>
                <a class='remove-thumbnail' href='javascript:void(0)' data-trigger-remove-thumbnail data-post-id='${ postId }' data-nonce='${ preservedNonce }' title='Remove thumbnail'>
									<svg viewBox='0 0 12 12'>
                    <path d='M11 1L1 11M1 1l10 10' stroke='currentColor' stroke-width='2' stroke-linecap='round'/>
                	</svg>
								</a>
              </div>
            `;
					}
				} else {
					alert( data.data?.message || 'Failed to update image.' );
				}
				scripts.enableTheGrid();
			} )
			.catch( ( error ) => {
				console.error( 'Error:', error );
				alert( 'Failed to update image.' );
				scripts.enableTheGrid();
			} );
	} );

	frame.open();
} );

// Xử lý khi nhấn nút X để xóa thumbnail
document.addEventListener( 'click', function ( e ) {
	const removeBtn = e.target.closest( '[data-trigger-remove-thumbnail]' );
	if ( ! removeBtn ) {
		return;
	}

	const postId = removeBtn.dataset.postId;

	// Use SweetAlert2 for confirmation
	Swal.fire( {
		title: adminI18n.removeThumbnailTitle,
		text: adminI18n.removeThumbnailText,
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#F15D4F',
		cancelButtonColor: '#6c757d',
		confirmButtonText: adminI18n.removeThumbnailConfirm,
		cancelButtonText: adminI18n.removeThumbnailCancel,
	} ).then( ( result ) => {
		if ( ! result.isConfirmed ) {
			return;
		}

		// Get nonce from data attribute (preferred) or fallback to global
		const nonce =
			removeBtn.dataset.nonce ||
			( typeof ajaxurl_params !== 'undefined'
				? ajaxurl_params.nonce
				: '' );

		fetch( '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action: 'remove_post_thumbnail',
				post_id: postId,
				nonce, // WordPress nonce for CSRF protection
			} ),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success === true ) {
					// Replace thumbnail with "Choose image" button
					// Preserve nonce for security
					const preservedNonce =
						nonce ||
						( typeof ajaxurl_params !== 'undefined'
							? ajaxurl_params.nonce
							: '' );
					const container = removeBtn.closest( 'td' );
					if ( container ) {
						container.innerHTML = `<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='${ postId }' data-nonce='${ preservedNonce }'><div class='no-image-text'>${ adminI18n.chooseImage }</div></a>`;
					}

					// Show success message
					Swal.fire( {
						title: adminI18n.removedTitle,
						text: adminI18n.removedText,
						icon: 'success',
						timer: 2000,
						showConfirmButton: false,
					} );
				} else {
					Swal.fire( {
						title: adminI18n.errorTitle,
						text: data.data?.message || adminI18n.failedRemove,
						icon: 'error',
					} );
				}
			} )
			.catch( ( error ) => {
				console.error( 'Error:', error );
				Swal.fire( {
					title: adminI18n.errorTitle,
					text: adminI18n.failedRemove,
					icon: 'error',
				} );
			} );
	} );
} );

// Khi trang tải, kiểm tra ảnh đại diện
document.addEventListener( 'DOMContentLoaded', function () {
	const postIdInput = document.querySelector( 'input#post_ID' );
	if ( postIdInput && postIdInput.value ) {
		fetch( '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action: 'mm_get_attachment_url_thumbnail',
				attachmentID: postIdInput.value,
			} ),
		} );
	}
} );

// Log fields support (Laca Admin)
// My project.js now handles password toggle and copy functionality for all fields
// with .laca-password-input and .laca-copyable-input classes.


// ===== LacaDashboard - Vanilla JS conversion =====
( function () {
	'use strict';

	const LacaDashboard = {
		init() {
			this.bindEvents();
			this.loadDashboardData();
			this.initTooltips();
		},

		bindEvents() {
			document.addEventListener( 'click', ( e ) => {
				if (
					e.target.matches( '.action-item' ) ||
					e.target.closest( '.action-item' )
				) {
					this.handleQuickAction( e );
				}
				if (
					e.target.matches( '.refresh-stats' ) ||
					e.target.closest( '.refresh-stats' )
				) {
					this.refreshStats( e );
				}
				if (
					e.target.matches( '.health-item' ) ||
					e.target.closest( '.health-item' )
				) {
					this.showHealthDetails( e );
				}
			} );
		},

		loadDashboardData() {
			if ( ! document.body.classList.contains( 'index-php' ) ) {
				return;
			}
			if ( typeof lacaDashboard === 'undefined' ) {
				return;
			}

			fetch( lacaDashboard.ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'laca_get_dashboard_data',
					nonce: lacaDashboard.nonce,
				} ),
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					if ( data && data.success ) {
						LacaDashboard.updateDashboardData( data.data );
					}
				} )
				.catch( ( error ) =>
					console.error( 'Dashboard load error:', error )
				);
		},

		updateDashboardData( data ) {
			if ( data.stats ) {
				this.updateStats( data.stats );
			}
			if ( data.activity ) {
				this.updateActivity( data.activity );
			}
			if ( data.health ) {
				this.updateHealth( data.health );
			}
		},

		updateStats( stats ) {
			const statItems = document.querySelectorAll( '.stat-item' );
			Object.keys( stats ).forEach( ( key, index ) => {
				const statNumber =
					statItems[ index ]?.querySelector( '.stat-number' );
				if ( statNumber ) {
					statNumber.textContent = stats[ key ];
				}
			} );
		},

		updateActivity( activity ) {
			// no-op for now
		},

		updateHealth( health ) {
			// no-op for now
		},

		handleQuickAction( e ) {
			const actionItem = e.target.closest( '.action-item' );
			if ( ! actionItem ) {
				return;
			}

			const action = actionItem.dataset.action;
			if ( ! action ) {
				return;
			}

			e.preventDefault();
			this.performQuickAction( action );
		},

		performQuickAction( action ) {
			if ( typeof lacaDashboard === 'undefined' ) {
				return;
			}

			const actionButton = document.querySelector(
				`.action-item[data-action="${ action }"]`
			);

			fetch( lacaDashboard.ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'laca_quick_action',
					quick_action: action,
					nonce: lacaDashboard.nonce,
				} ),
			} )
				.then( () => {
					if ( actionButton ) {
						actionButton.classList.remove( 'laca-loading' );
					}
				} )
				.catch( ( error ) => {
					console.error( 'Quick action error:', error );
					if ( actionButton ) {
						actionButton.classList.remove( 'laca-loading' );
					}
				} );

			if ( actionButton ) {
				actionButton.classList.add( 'laca-loading' );
			}
		},

		refreshStats( e ) {
			e.preventDefault();
			const button = e.target.closest( '.refresh-stats' );
			if ( ! button ) {
				return;
			}

			button.classList.add( 'laca-loading' );
			this.loadDashboardData();
			setTimeout( () => button.classList.remove( 'laca-loading' ), 1000 );
		},

		showHealthDetails( e ) {
			const healthItem = e.target.closest( '.health-item' );
			if ( ! healthItem ) {
				return;
			}

			const healthType = healthItem.dataset.healthType;
			if ( ! healthType ) {
				return;
			}

			e.preventDefault();
			// Implement health details modal here if needed
		},

		initTooltips() {
			// Tooltips would need a vanilla JS tooltip library or native implementation
			// For now, we can use title attribute which browsers show natively
			const tooltipItems = document.querySelectorAll(
				'.stat-item, .action-item, .health-item'
			);
			tooltipItems.forEach( ( item ) => {
				if ( item.getAttribute( 'title' ) ) {
					// Native browser tooltip will work with title attribute
					// If custom tooltip needed, implement here
				}
			} );
		},
	};

	// Initialize on DOM ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () =>
			LacaDashboard.init()
		);
	} else {
		LacaDashboard.init();
	}

	window.LacaDashboard = LacaDashboard;
} )();
