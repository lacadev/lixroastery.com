/**
 * Frontend Chatbot — widget nổi góc dưới phải, gọi REST endpoint công khai
 * POST /wp-json/laca/v1/chatbot (FrontendChatbotHandler::handleMessage()).
 *
 * File này trước đây CHƯA TỪNG TỒN TẠI — enqueueAssets() (PHP) trỏ đúng
 * đường dẫn và đã localize sẵn window.lacaChatbot (endpoint/nonce/name/
 * greeting/color/placeholder), nhưng không có JS nào dựng giao diện/gắn sự
 * kiện cả, nên admin bật switch "Chatbot" trong Settings mà không có gì
 * hiện ra trên frontend.
 *
 * Vanilla JS, không build qua webpack (enqueue thẳng file nguồn này) — tự
 * inject CSS qua <style>, không phụ thuộc file .scss nào khác.
 */
( function () {
	if ( typeof window.lacaChatbot === 'undefined' ) {
		return;
	}

	var cfg = window.lacaChatbot;

	function escHtml( str ) {
		var d = document.createElement( 'div' );
		d.textContent = str || '';
		return d.innerHTML;
	}

	function injectStyles() {
		var css =
			'.laca-cbot-toggle{position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;' +
			'border:0;cursor:pointer;color:#fff;font-size:24px;line-height:56px;text-align:center;z-index:99998;' +
			'box-shadow:0 4px 14px rgba(0,0,0,.25);background:' + cfg.color + '}' +
			'.laca-cbot-panel{position:fixed;right:20px;bottom:88px;width:340px;max-width:calc(100vw - 32px);' +
			'height:460px;max-height:calc(100vh - 120px);background:#fff;border-radius:12px;overflow:hidden;' +
			'box-shadow:0 10px 40px rgba(0,0,0,.2);display:flex;flex-direction:column;z-index:99999;' +
			'font-family:inherit;transform:translateY(12px);opacity:0;pointer-events:none;' +
			'transition:opacity .2s ease,transform .2s ease}' +
			'.laca-cbot-panel.is-open{transform:translateY(0);opacity:1;pointer-events:auto}' +
			'.laca-cbot-head{background:' + cfg.color + ';color:#fff;padding:12px 16px;font-weight:600;' +
			'font-size:14px;display:flex;align-items:center;justify-content:space-between}' +
			'.laca-cbot-close{background:none;border:0;color:#fff;font-size:18px;cursor:pointer;line-height:1;opacity:.85}' +
			'.laca-cbot-close:hover{opacity:1}' +
			'.laca-cbot-body{flex:1;overflow-y:auto;padding:14px;background:#f7f7f8;display:flex;' +
			'flex-direction:column;gap:10px}' +
			'.laca-cbot-msg{max-width:85%;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.6;' +
			'word-wrap:break-word}' +
			'.laca-cbot-msg a{color:inherit;text-decoration:underline}' +
			'.laca-cbot-msg.bot{align-self:flex-start;background:#fff;border:1px solid #e5e5e5;' +
			'border-bottom-left-radius:2px;color:#222}' +
			'.laca-cbot-msg.user{align-self:flex-end;background:' + cfg.color + ';color:#fff;' +
			'border-bottom-right-radius:2px}' +
			'.laca-cbot-msg.error{align-self:flex-start;background:#fdecea;border:1px solid #f5c6cb;color:#842029}' +
			'.laca-cbot-msg.loading{align-self:flex-start;background:#fff;border:1px solid #e5e5e5;color:#888}' +
			'.laca-cbot-sources{margin-top:6px;font-size:11px;opacity:.8}' +
			'.laca-cbot-sources a{display:block;color:inherit}' +
			'.laca-cbot-form{display:flex;gap:8px;padding:10px;background:#fff;border-top:1px solid #eee}' +
			'.laca-cbot-input{flex:1;border:1px solid #ddd;border-radius:20px;padding:8px 14px;font-size:13px;' +
			'outline:none;font-family:inherit}' +
			'.laca-cbot-input:focus{border-color:' + cfg.color + '}' +
			'.laca-cbot-send{background:' + cfg.color + ';border:0;border-radius:50%;width:36px;height:36px;' +
			'color:#fff;cursor:pointer;font-size:14px;flex:0 0 auto}' +
			'.laca-cbot-send:disabled{opacity:.5;cursor:not-allowed}' +
			'@media (max-width:480px){.laca-cbot-panel{right:16px;left:16px;width:auto;bottom:84px}}';

		var styleEl = document.createElement( 'style' );
		styleEl.textContent = css;
		document.head.appendChild( styleEl );
	}

	function buildWidget() {
		var toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'laca-cbot-toggle';
		toggle.setAttribute( 'aria-label', cfg.name || 'Chatbot' );
		toggle.textContent = '✦';

		var panel = document.createElement( 'div' );
		panel.className = 'laca-cbot-panel';
		panel.innerHTML =
			'<div class="laca-cbot-head">' +
				'<span>' + escHtml( cfg.name || 'AI Assistant' ) + '</span>' +
				'<button type="button" class="laca-cbot-close" aria-label="Đóng">✕</button>' +
			'</div>' +
			'<div class="laca-cbot-body" id="laca-cbot-body"></div>' +
			'<form class="laca-cbot-form" id="laca-cbot-form">' +
				'<input type="text" class="laca-cbot-input" id="laca-cbot-input" autocomplete="off" ' +
					'placeholder="' + escHtml( cfg.placeholder || '' ) + '">' +
				'<button type="submit" class="laca-cbot-send" aria-label="Gửi">➤</button>' +
			'</form>';

		document.body.appendChild( toggle );
		document.body.appendChild( panel );

		var body       = panel.querySelector( '#laca-cbot-body' );
		var form       = panel.querySelector( '#laca-cbot-form' );
		var input      = panel.querySelector( '#laca-cbot-input' );
		var closeBtn   = panel.querySelector( '.laca-cbot-close' );
		var greeted    = false;

		function appendMessage( role, html ) {
			var row = document.createElement( 'div' );
			row.className = 'laca-cbot-msg ' + role;
			row.innerHTML = html;
			body.appendChild( row );
			body.scrollTop = body.scrollHeight;
			return row;
		}

		function openPanel() {
			panel.classList.add( 'is-open' );
			if ( ! greeted ) {
				greeted = true;
				appendMessage( 'bot', escHtml( cfg.greeting || '' ) );
			}
			input.focus();
		}

		function closePanel() {
			panel.classList.remove( 'is-open' );
		}

		toggle.addEventListener( 'click', function () {
			panel.classList.contains( 'is-open' ) ? closePanel() : openPanel();
		} );
		closeBtn.addEventListener( 'click', closePanel );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var message = input.value.trim();
			if ( ! message ) {
				return;
			}

			appendMessage( 'user', escHtml( message ) );
			input.value = '';
			input.disabled = true;

			var loadingRow = appendMessage( 'loading', 'Đang trả lời…' );

			fetch( cfg.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: JSON.stringify( { message: message } ),
			} )
				.then( function ( res ) {
					return res.json().then( function ( json ) {
						return { ok: res.ok, json: json };
					} );
				} )
				.then( function ( result ) {
					loadingRow.remove();
					if ( ! result.ok ) {
						var errMsg = ( result.json && result.json.message ) || 'Đã có lỗi xảy ra. Vui lòng thử lại.';
						appendMessage( 'error', escHtml( errMsg ) );
						return;
					}

					var html = escHtml( result.json.reply || '' ).replace( /\n/g, '<br>' );

					if ( result.json.sources && result.json.sources.length ) {
						var links = result.json.sources
							.map( function ( s ) {
								return '<a href="' + escHtml( s.url ) + '" target="_blank" rel="noopener">' + escHtml( s.title ) + '</a>';
							} )
							.join( '' );
						html += '<div class="laca-cbot-sources">' + links + '</div>';
					}

					appendMessage( 'bot', html );
				} )
				.catch( function () {
					loadingRow.remove();
					appendMessage( 'error', 'Không thể kết nối tới máy chủ. Vui lòng kiểm tra kết nối internet.' );
				} )
				.finally( function () {
					input.disabled = false;
					input.focus();
				} );
		} );
	}

	function boot() {
		injectStyles();
		buildWidget();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
