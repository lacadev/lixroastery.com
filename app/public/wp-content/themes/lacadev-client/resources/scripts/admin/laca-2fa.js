/**
 * 2FA setup trong trang Profile (profile.php/user-edit.php) — gắn sự kiện
 * cho các nút render bởi TwoFactorAuth::renderProfileSection() (PHP),
 * dùng window.laca2faConfig (ajaxUrl/nonce) đã localize sẵn và thư viện
 * QRCode (handle "laca-qrcode", enqueue cùng lúc) để vẽ mã QR.
 *
 * File này trước đây CHƯA TỪNG TỒN TẠI — enqueueAssets() trỏ sai đường dẫn
 * (thiếu dirname()) nên dù có viết cũng không tải được; đã sửa path ở PHP,
 * file JS thật sự viết ở đây.
 */
( function () {
	if ( typeof window.laca2faConfig === 'undefined' ) {
		return;
	}

	var cfg = window.laca2faConfig;

	function post( action, data ) {
		var params = { action: action, nonce: cfg.nonce };
		if ( data ) {
			for ( var k in data ) {
				if ( Object.prototype.hasOwnProperty.call( data, k ) ) {
					params[ k ] = data[ k ];
				}
			}
		}
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams( params ),
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function renderCodes( container, codes ) {
		if ( ! container ) {
			return;
		}
		container.innerHTML = '';
		( codes || [] ).forEach( function ( code ) {
			var el = document.createElement( 'code' );
			el.style.cssText =
				'background:#fff;border:1px solid #fde047;padding:4px 10px;border-radius:4px;' +
				'font-size:13px;letter-spacing:1px;';
			el.textContent = code;
			container.appendChild( el );
		} );
	}

	function renderQr( elId, otpauthText ) {
		var el = document.getElementById( elId );
		if ( ! el || typeof QRCode === 'undefined' ) {
			return;
		}
		el.innerHTML = '';
		// eslint-disable-next-line no-undef
		new QRCode( el, { text: otpauthText, width: 144, height: 144 } );
	}

	function errorText( res, fallback ) {
		if ( ! res || ! res.data ) {
			return fallback;
		}
		return typeof res.data === 'string' ? res.data : ( res.data.message || fallback );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var setupBtn       = document.getElementById( 'laca-2fa-setup-btn' );
		var setupPanel     = document.getElementById( 'laca-2fa-setup-panel' );
		var secretDisplay  = document.getElementById( 'laca-secret-display' );
		var secretVal      = document.getElementById( 'laca-2fa-secret-val' );
		var confirmBtn     = document.getElementById( 'laca-2fa-confirm-btn' );
		var verifyCodeEl   = document.getElementById( 'laca-2fa-verify-code' );
		var verifyMsg      = document.getElementById( 'laca-2fa-verify-msg' );
		var backupFirstWrap = document.getElementById( 'laca-2fa-backup-first' );
		var backupCodesEl  = document.getElementById( 'laca-2fa-backup-codes' );

		var regenBtn       = document.getElementById( 'laca-2fa-regen-backup' );
		var disableBtn     = document.getElementById( 'laca-2fa-disable-btn' );
		var backupDisplay  = document.getElementById( 'laca-2fa-backup-display' );
		var codesListEl    = document.getElementById( 'laca-2fa-codes-list' );

		// ── Bật 2FA / hoàn tất cài đặt ──────────────────────────────────────
		if ( setupBtn && setupPanel ) {
			setupBtn.addEventListener( 'click', function () {
				var isOpen = setupPanel.style.display !== 'none';
				if ( isOpen ) {
					setupPanel.style.display = 'none';
					return;
				}
				setupPanel.style.display = 'block';

				setupBtn.disabled = true;
				post( 'laca_2fa_get_secret' )
					.then( function ( res ) {
						if ( ! res.success ) {
							return;
						}
						if ( secretDisplay ) secretDisplay.textContent = res.data.secret;
						if ( secretVal ) secretVal.value = res.data.secret;
						renderQr( 'laca-qrcode', res.data.otpauth );
					} )
					.finally( function () {
						setupBtn.disabled = false;
					} );
			} );
		}

		// ── Xác nhận mã 6 số để hoàn tất bật 2FA ────────────────────────────
		if ( confirmBtn && verifyCodeEl ) {
			confirmBtn.addEventListener( 'click', function () {
				var code = ( verifyCodeEl.value || '' ).trim();
				if ( ! /^\d{6}$/.test( code ) ) {
					if ( verifyMsg ) {
						verifyMsg.textContent = 'Nhập đúng 6 chữ số từ ứng dụng Authenticator.';
						verifyMsg.style.color = '#dc2626';
					}
					return;
				}

				confirmBtn.disabled = true;
				post( 'laca_2fa_verify_setup', { code: code } )
					.then( function ( res ) {
						if ( ! res.success ) {
							if ( verifyMsg ) {
								verifyMsg.textContent = errorText( res, 'Mã không đúng. Vui lòng thử lại.' );
								verifyMsg.style.color = '#dc2626';
							}
							return;
						}

						if ( verifyMsg ) {
							verifyMsg.textContent = '✓ Xác nhận thành công! Đang tải lại trang…';
							verifyMsg.style.color = '#16a34a';
						}
						if ( backupFirstWrap && backupCodesEl ) {
							renderCodes( backupCodesEl, res.data.backup_codes );
							backupFirstWrap.style.display = 'block';
						}
						setTimeout( function () {
							window.location.reload();
						}, 4000 );
					} )
					.finally( function () {
						confirmBtn.disabled = false;
					} );
			} );
		}

		// ── Tạo lại mã dự phòng ──────────────────────────────────────────────
		if ( regenBtn ) {
			regenBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Các mã dự phòng cũ sẽ không còn dùng được. Tiếp tục?' ) ) {
					return;
				}
				regenBtn.disabled = true;
				post( 'laca_2fa_regen_backup' )
					.then( function ( res ) {
						if ( ! res.success ) {
							return;
						}
						renderCodes( codesListEl, res.data.backup_codes );
						if ( backupDisplay ) backupDisplay.style.display = 'block';
					} )
					.finally( function () {
						regenBtn.disabled = false;
					} );
			} );
		}

		// ── Tắt 2FA ──────────────────────────────────────────────────────────
		if ( disableBtn ) {
			disableBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Tắt xác thực 2 bước cho tài khoản này?' ) ) {
					return;
				}
				disableBtn.disabled = true;
				post( 'laca_2fa_disable' )
					.then( function ( res ) {
						if ( res.success ) {
							window.location.reload();
						} else {
							disableBtn.disabled = false;
						}
					} )
					.catch( function () {
						disableBtn.disabled = false;
					} );
			} );
		}
	} );
} )();
