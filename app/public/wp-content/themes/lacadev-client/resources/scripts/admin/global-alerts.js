import Swal from 'sweetalert2';

/**
 * Global Alerts page – resolve với SweetAlert2
 */
( function () {
	'use strict';

	// Chỉ chạy trên trang global alerts
	if ( ! document.querySelector( '.laca-global-alerts-wrap' ) ) {
		return;
	}

	// ── Chọn tất cả ────────────────────────────────────────────────────
	[ 'laca-check-all', 'laca-check-all-head' ].forEach( ( id ) => {
		const el = document.getElementById( id );
		if ( el ) {
			el.addEventListener( 'change', function () {
				document
					.querySelectorAll( '.laca-alert-check' )
					.forEach( ( cb ) => {
						cb.checked = el.checked;
					} );
			} );
		}
	} );

	// ── Bulk resolve – gửi TẤT CẢ IDs trong 1 request ─────────────────
	function resolveAlerts( alertIds, nonce ) {
		if ( ! alertIds.length ) {
			return;
		}

		Swal.fire( {
			title: 'Đang xử lý...',
			html: `Đang xử lý <b>${ alertIds.length }</b> cảnh báo...`,
			allowOutsideClick: false,
			allowEscapeKey: false,
			showConfirmButton: false,
			didOpen() {
				Swal.showLoading();
			},
		} );

		const fd = new FormData();
		fd.append( 'action', 'laca_global_alerts_bulk_resolve' );
		fd.append( 'nonce', nonce );
		alertIds.forEach( ( id ) => fd.append( 'alert_ids[]', id ) );

		fetch( ajaxurl, { method: 'POST', body: fd } )
			.then( ( r ) => r.json() )
			.then( ( res ) => {
				if ( res.success ) {
					alertIds.forEach( ( id ) => {
						const row = document.querySelector(
							`[data-alert-id="${ id }"]`
						);
						if ( row ) {
							row.style.opacity = '0.3';
						}
					} );
					Swal.fire( {
						icon: 'success',
						title: 'Hoàn tất',
						text: res.data.message,
						confirmButtonText: 'Đóng',
					} ).then( () => location.reload() );
				} else {
					Swal.fire( {
						icon: 'error',
						title: 'Lỗi',
						text: res.data ? res.data.message : 'Có lỗi xảy ra.',
					} );
				}
			} )
			.catch( () => {
				Swal.fire( {
					icon: 'error',
					title: 'Lỗi kết nối',
					text: 'Không thể kết nối server.',
				} );
			} );
	}

	// ── Bulk resolve button ────────────────────────────────────────────
	const bulkBtn = document.getElementById( 'laca-bulk-resolve' );
	if ( bulkBtn ) {
		bulkBtn.addEventListener( 'click', function () {
			const checked = Array.from(
				document.querySelectorAll( '.laca-alert-check:checked' )
			).map( ( cb ) => cb.value );

			if ( ! checked.length ) {
				Swal.fire( {
					icon: 'info',
					title: 'Chưa chọn',
					text: 'Vui lòng chọn ít nhất 1 cảnh báo.',
					confirmButtonText: 'OK',
				} );
				return;
			}

			Swal.fire( {
				title: 'Xác nhận',
				html: `Bạn muốn xử lý <b>${ checked.length }</b> cảnh báo đã chọn?`,
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Xử lý',
				cancelButtonText: 'Huỷ',
			} ).then( ( result ) => {
				if ( result.isConfirmed ) {
					resolveAlerts( checked, bulkBtn.dataset.nonce );
				}
			} );
		} );
	}

	// ── Resolve đơn lẻ ─────────────────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'laca-resolve-btn' ) ) {
			return;
		}

		const btn = e.target;
		const id = btn.dataset.id;
		const nonce = btn.dataset.nonce;

		Swal.fire( {
			title: 'Xử lý cảnh báo?',
			text: `Đánh dấu cảnh báo #${ id } đã xử lý?`,
			icon: 'question',
			showCancelButton: true,
			confirmButtonText: 'Xử lý',
			cancelButtonText: 'Huỷ',
		} ).then( ( result ) => {
			if ( ! result.isConfirmed ) {
				return;
			}

			Swal.fire( {
				title: 'Đang xử lý...',
				allowOutsideClick: false,
				didOpen() {
					Swal.showLoading();
				},
			} );

			const fd = new FormData();
			fd.append( 'action', 'laca_global_alerts_bulk_resolve' );
			fd.append( 'nonce', nonce );
			fd.append( 'alert_ids[]', id );

			fetch( ajaxurl, { method: 'POST', body: fd } )
				.then( ( r ) => r.json() )
				.then( ( res ) => {
					if ( res.success ) {
						const row = document.querySelector(
							`[data-alert-id="${ id }"]`
						);
						if ( row ) {
							row.style.opacity = '0.3';
						}
						Swal.fire( {
							icon: 'success',
							title: 'Đã xử lý',
							text: res.data.message,
							timer: 1500,
							showConfirmButton: false,
						} );
					} else {
						Swal.fire( {
							icon: 'error',
							title: 'Lỗi',
							text: res.data
								? res.data.message
								: 'Có lỗi xảy ra.',
						} );
					}
				} )
				.catch( () => {
					Swal.fire( {
						icon: 'error',
						title: 'Lỗi kết nối',
						text: 'Không thể kết nối server.',
					} );
				} );
		} );
	} );

	// ── Auto-refresh badge mỗi 60s ────────────────────────────────────
	const nonceEl = document.querySelector( '[data-alerts-nonce]' );
	if ( nonceEl ) {
		const alertsNonce = nonceEl.dataset.alertsNonce;
		setInterval( () => {
			const fd = new FormData();
			fd.append( 'action', 'laca_global_alerts_count' );
			fd.append( 'nonce', alertsNonce );
			fetch( ajaxurl, { method: 'POST', body: fd } )
				.then( ( r ) => r.json() )
				.then( ( res ) => {
					if ( res.success ) {
						const badge =
							document.querySelector( '.laca-total-badge' );
						if ( badge ) {
							badge.textContent = res.data.count + ' chưa xử lý';
						}
					}
				} );
		}, 60000 );
	}
} )();
