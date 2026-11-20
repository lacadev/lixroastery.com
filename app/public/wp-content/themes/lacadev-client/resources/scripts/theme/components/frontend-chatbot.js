/**
 * Frontend Chatbot — widget nổi góc dưới phải, gọi REST endpoint công khai
 * POST /wp-json/laca/v1/chatbot (FrontendChatbotHandler::handleMessage()).
 *
 * - Câu gợi ý khi mở chat, nút 👍/👎 dưới mỗi câu trả lời (ghi vào nhật ký).
 * - "Để lại thông tin": form ngắn trong khung chat → /chatbot/lead (lưu
 *   Submission + email admin + Discord, kèm tóm tắt cuộc chat).
 * - Giữ hội thoại khi chuyển trang bằng sessionStorage (chỉ trên trình duyệt
 *   khách, không lưu server); gửi kèm bài đang xem để ưu tiên ngữ cảnh.
 * - "Chat với nhân viên": chuyển sang nhắn trực tiếp qua nhóm Telegram
 *   (/chatbot/live/*). Hỏi server vài giây/lần để nhận tin nhân viên trả lời
 *   (hosting không có websocket); chậm lại khi khung chat đóng/tab ẩn.
 *
 * Vanilla JS, enqueue thẳng file nguồn (không qua webpack). Câu chữ lấy từ
 * window.lacaChatbot.i18n (PHP gettext) — không ghi cứng chữ ở đây.
 * Mọi nội dung chèn vào DOM đều escape trước.
 */
( function () {
	if ( typeof window.lacaChatbot === 'undefined' ) {
		return;
	}

	const cfg = window.lacaChatbot;
	const t = cfg.i18n || {};
	const STORE_KEY = 'lacaCbot';
	const HISTORY_MAX = 6;
	const MESSAGES_MAX = 40;
	const POLL_FAST = 3000;
	const POLL_SLOW = 12000;

	// ── Trạng thái (giữ qua các trang trong cùng phiên trình duyệt) ──────────
	const state = loadState();

	function newSession() {
		const a = new Uint8Array( 16 );
		( window.crypto || window.msCrypto ).getRandomValues( a );
		return Array.prototype.map
			.call( a, function ( b ) {
				return ( '0' + b.toString( 16 ) ).slice( -2 );
			} )
			.join( '' );
	}

	function loadState() {
		let s = null;
		try {
			s = JSON.parse(
				window.sessionStorage.getItem( STORE_KEY ) || 'null'
			);
		} catch ( e ) {}
		if ( ! s || ! s.session || ! Array.isArray( s.messages ) ) {
			s = { session: newSession(), messages: [], open: false };
		}
		if ( ! s.live ) {
			s.live = { active: false, lastId: 0, noReplyShown: false };
		}
		return s;
	}

	function saveState() {
		state.messages = state.messages.slice( -MESSAGES_MAX );
		try {
			window.sessionStorage.setItem( STORE_KEY, JSON.stringify( state ) );
		} catch ( e ) {}
	}

	function historyForApi() {
		return state.messages
			.filter( function ( m ) {
				return (
					( m.role === 'user' || m.role === 'assistant' ) && ! m.live
				);
			} )
			.map( function ( m ) {
				return { role: m.role, content: m.text };
			} )
			.slice( -HISTORY_MAX );
	}

	// ── Tiện ích ─────────────────────────────────────────────────────────────
	function escHtml( str ) {
		const d = document.createElement( 'div' );
		d.textContent = str || '';
		return d.innerHTML;
	}

	// Escape TRƯỚC rồi mới chuyển **đậm** và URL thành thẻ — không chèn HTML
	// thô từ câu trả lời AI. Không cho " ' vào URL (escHtml không escape nháy).
	function formatReply( text ) {
		return escHtml( text )
			.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' )
			.replace(
				/(https?:\/\/[^\s<>)"']+)/g,
				'<a href="$1" target="_blank" rel="noopener">$1</a>'
			)
			.replace( /\n/g, '<br>' );
	}

	function postJson( url, body ) {
		return fetch( url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( body ),
		} ).then( function ( res ) {
			return res.json().then( function ( json ) {
				return { ok: res.ok, json };
			} );
		} );
	}

	function injectStyles() {
		const c = cfg.color;
		const css =
			'.laca-cbot-toggle{position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;' +
			'border:0;cursor:pointer;color:#fff;font-size:24px;line-height:56px;text-align:center;z-index:99998;' +
			'box-shadow:0 4px 14px rgba(0,0,0,.25);background:' +
			c +
			'}' +
			'.laca-cbot-panel{position:fixed;right:20px;bottom:88px;width:360px;max-width:calc(100vw - 32px);' +
			'height:520px;max-height:calc(100vh - 120px);background:#fff;border-radius:12px;overflow:hidden;' +
			'box-shadow:0 10px 40px rgba(0,0,0,.2);display:flex;flex-direction:column;z-index:99999;' +
			'font-family:inherit;transform:translateY(12px);opacity:0;pointer-events:none;' +
			'transition:opacity .2s ease,transform .2s ease}' +
			'.laca-cbot-panel.is-open{transform:translateY(0);opacity:1;pointer-events:auto}' +
			'.laca-cbot-head{background:' +
			c +
			';color:#fff;padding:12px 16px;font-weight:600;' +
			'font-size:14px;display:flex;align-items:center;justify-content:space-between}' +
			'.laca-cbot-close{background:none;border:0;color:#fff;font-size:18px;cursor:pointer;line-height:1;opacity:.85}' +
			'.laca-cbot-head-main{display:flex;flex-direction:column;gap:2px;min-width:0}' +
			'.laca-cbot-status{font-weight:400;font-size:11px;opacity:.9}' +
			'.laca-cbot-head-tools{display:flex;align-items:center;gap:8px}' +
			'.laca-cbot-end{background:rgba(255,255,255,.18);border:0;border-radius:12px;color:#fff;' +
			'padding:3px 10px;font-size:12px;cursor:pointer;font-family:inherit}' +
			'.laca-cbot-toggle.has-new::after{content:"";position:absolute;top:4px;right:4px;width:12px;height:12px;' +
			'border-radius:50%;background:#e11d48;border:2px solid #fff}' +
			'.laca-cbot-msg.agent{align-self:flex-start;background:#fff;border:1px solid #e5e5e5;border-left:3px solid ' +
			c +
			';border-bottom-left-radius:2px;color:#222}' +
			'.laca-cbot-agent{display:block;font-size:11px;font-weight:600;opacity:.7;margin-bottom:2px}' +
			'.laca-cbot-close:hover{opacity:1}' +
			'.laca-cbot-body{flex:1;overflow-y:auto;padding:14px;background:#f7f7f8;display:flex;' +
			'flex-direction:column;gap:10px}' +
			'.laca-cbot-msg{max-width:85%;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.6;' +
			'word-wrap:break-word}' +
			'.laca-cbot-msg a{color:inherit;text-decoration:underline}' +
			'.laca-cbot-msg.assistant,.laca-cbot-msg.bot{align-self:flex-start;background:#fff;border:1px solid #e5e5e5;' +
			'border-bottom-left-radius:2px;color:#222}' +
			'.laca-cbot-msg.user{align-self:flex-end;background:' +
			c +
			';color:#fff;border-bottom-right-radius:2px}' +
			'.laca-cbot-msg.error{align-self:flex-start;background:#fdecea;border:1px solid #f5c6cb;color:#842029}' +
			'.laca-cbot-msg.loading{align-self:flex-start;background:#fff;border:1px solid #e5e5e5;color:#888}' +
			'.laca-cbot-sources{margin-top:6px;font-size:11px;opacity:.8}' +
			'.laca-cbot-sources a{display:block;color:inherit}' +
			'.laca-cbot-actions{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:8px}' +
			'.laca-cbot-rate{background:none;border:1px solid #e5e5e5;border-radius:12px;padding:1px 8px;' +
			'cursor:pointer;font-size:12px;line-height:1.6}' +
			'.laca-cbot-rate:hover,.laca-cbot-rate.is-on{border-color:' +
			c +
			'}' +
			'.laca-cbot-chip{background:#fff;border:1px solid #ddd;border-radius:14px;padding:4px 10px;' +
			'cursor:pointer;font-size:12px;color:#222;font-family:inherit;text-align:left}' +
			'.laca-cbot-chip:hover{border-color:' +
			c +
			'}' +
			'.laca-cbot-chip--lead{background:' +
			c +
			';border-color:' +
			c +
			';color:#fff}' +
			'.laca-cbot-chips{display:flex;flex-wrap:wrap;gap:6px}' +
			'.laca-cbot-lead{align-self:stretch;background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:12px;' +
			'display:flex;flex-direction:column;gap:8px;font-size:13px}' +
			'.laca-cbot-lead input,.laca-cbot-lead textarea{width:100%;box-sizing:border-box;border:1px solid #ddd;' +
			'border-radius:8px;padding:7px 10px;font-size:13px;font-family:inherit}' +
			'.laca-cbot-lead textarea{min-height:60px;resize:vertical}' +
			'.laca-cbot-lead .laca-cbot-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}' +
			'.laca-cbot-lead-row{display:flex;gap:8px;justify-content:flex-end}' +
			'.laca-cbot-btn{border:0;border-radius:16px;padding:6px 14px;cursor:pointer;font-size:13px;font-family:inherit}' +
			'.laca-cbot-btn--primary{background:' +
			c +
			';color:#fff}' +
			'.laca-cbot-btn--ghost{background:#f1f1f1;color:#222}' +
			'.laca-cbot-form{display:flex;gap:8px;padding:10px;background:#fff;border-top:1px solid #eee}' +
			'.laca-cbot-input{flex:1;border:1px solid #ddd;border-radius:20px;padding:8px 14px;font-size:13px;' +
			'outline:none;font-family:inherit}' +
			'.laca-cbot-input:focus{border-color:' +
			c +
			'}' +
			'.laca-cbot-send{background:' +
			c +
			';border:0;border-radius:50%;width:36px;height:36px;' +
			'color:#fff;cursor:pointer;font-size:14px;flex:0 0 auto}' +
			'.laca-cbot-send:disabled{opacity:.5;cursor:not-allowed}' +
			'.laca-cbot-handoff{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:8px 12px;background:#fff;' +
			'border-top:1px solid #eee;font-size:12px;color:#666}' +
			'.laca-cbot-handoff a,.laca-cbot-handoff button{padding:4px 10px;border:1px solid #ddd;border-radius:14px;' +
			'color:#222;background:#fff;text-decoration:none;font-size:12px;cursor:pointer;font-family:inherit}' +
			'.laca-cbot-handoff a:hover,.laca-cbot-handoff button:hover{border-color:' +
			c +
			'}' +
			'@media (max-width:480px){.laca-cbot-panel{right:16px;left:16px;width:auto;bottom:84px}}';

		const styleEl = document.createElement( 'style' );
		styleEl.textContent = css;
		document.head.appendChild( styleEl );
	}

	// ── Giao diện ────────────────────────────────────────────────────────────
	function buildWidget() {
		const toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'laca-cbot-toggle';
		toggle.setAttribute( 'aria-label', cfg.name || 'Chatbot' );
		toggle.textContent = '✦';

		let handoff = '';
		if (
			cfg.leadEnabled ||
			cfg.liveEnabled ||
			( cfg.handoff && cfg.handoff.length )
		) {
			handoff =
				'<div class="laca-cbot-handoff"><span>' +
				escHtml( t.contact || '' ) +
				'</span>';
			if ( cfg.liveEnabled ) {
				handoff +=
					'<button type="button" data-cbot-live>' +
					escHtml( t.liveOpen || '' ) +
					'</button>';
			}
			if ( cfg.leadEnabled ) {
				handoff +=
					'<button type="button" data-cbot-lead>' +
					escHtml( t.leadOpen || '' ) +
					'</button>';
			}
			( cfg.handoff || [] ).forEach( function ( l ) {
				const label =
					l.type === 'zalo' ? t.zalo || 'Zalo' : t.call || 'Call';
				const target =
					l.type === 'zalo' ? ' target="_blank" rel="noopener"' : '';
				handoff +=
					'<a href="' +
					escHtml( l.url ) +
					'"' +
					target +
					'>' +
					escHtml( label ) +
					'</a>';
			} );
			handoff += '</div>';
		}

		const panel = document.createElement( 'div' );
		panel.className = 'laca-cbot-panel';
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-label', cfg.name || 'Chatbot' );
		panel.innerHTML =
			'<div class="laca-cbot-head">' +
			'<div class="laca-cbot-head-main"><span>' +
			escHtml( cfg.name || 'AI Assistant' ) +
			'</span><span class="laca-cbot-status" hidden>' +
			escHtml( t.liveStatus || '' ) +
			'</span></div>' +
			'<div class="laca-cbot-head-tools">' +
			'<button type="button" class="laca-cbot-end" hidden>' +
			escHtml( t.liveEnd || '' ) +
			'</button>' +
			'<button type="button" class="laca-cbot-close" aria-label="' +
			escHtml( t.close || 'Close' ) +
			'">✕</button>' +
			'</div>' +
			'</div>' +
			'<div class="laca-cbot-body" aria-live="polite"></div>' +
			handoff +
			'<form class="laca-cbot-form">' +
			'<input type="text" class="laca-cbot-input" autocomplete="off" maxlength="500" ' +
			'aria-label="' +
			escHtml( cfg.placeholder || '' ) +
			'" placeholder="' +
			escHtml( cfg.placeholder || '' ) +
			'">' +
			'<button type="submit" class="laca-cbot-send" aria-label="' +
			escHtml( t.send || 'Send' ) +
			'">➤</button>' +
			'</form>';

		document.body.appendChild( toggle );
		document.body.appendChild( panel );

		const body = panel.querySelector( '.laca-cbot-body' );
		const form = panel.querySelector( '.laca-cbot-form' );
		const input = panel.querySelector( '.laca-cbot-input' );
		const sendBtn = panel.querySelector( '.laca-cbot-send' );
		const statusEl = panel.querySelector( '.laca-cbot-status' );
		const endBtn = panel.querySelector( '.laca-cbot-end' );
		let pollTimer = null;

		function scrollDown() {
			body.scrollTop = body.scrollHeight;
		}

		function appendRow( cls, html ) {
			const row = document.createElement( 'div' );
			row.className = 'laca-cbot-msg ' + cls;
			row.innerHTML = html;
			body.appendChild( row );
			scrollDown();
			return row;
		}

		function renderMessage( m ) {
			if ( m.role === 'user' ) {
				return appendRow( 'user', escHtml( m.text ) );
			}
			if ( m.role === 'error' ) {
				return appendRow( 'error', escHtml( m.text ) );
			}
			if ( m.role === 'agent' ) {
				return appendRow(
					'agent',
					'<span class="laca-cbot-agent">' +
						escHtml( m.name || t.agent || '' ) +
						'</span>' +
						formatReply( m.text )
				);
			}
			let html =
				m.role === 'assistant'
					? formatReply( m.text )
					: escHtml( m.text );
			if ( m.sources && m.sources.length ) {
				html +=
					'<div class="laca-cbot-sources">' +
					m.sources
						.map( function ( s ) {
							return (
								'<a href="' +
								escHtml( s.url ) +
								'" target="_blank" rel="noopener">' +
								escHtml( s.title ) +
								'</a>'
							);
						} )
						.join( '' ) +
					'</div>';
			}
			let actions = '';
			if ( m.logId ) {
				actions +=
					'<button type="button" class="laca-cbot-rate' +
					( m.rated === 1 ? ' is-on' : '' ) +
					'" data-rate="1" aria-label="' +
					escHtml( t.helpful || '' ) +
					'">👍</button>' +
					'<button type="button" class="laca-cbot-rate' +
					( m.rated === -1 ? ' is-on' : '' ) +
					'" data-rate="-1" aria-label="' +
					escHtml( t.notHelpful || '' ) +
					'">👎</button>';
			}
			if ( m.suggestLive && cfg.liveEnabled && ! state.live.active ) {
				actions += liveChip();
			}
			if ( m.suggestLead && cfg.leadEnabled ) {
				actions +=
					'<button type="button" class="laca-cbot-chip laca-cbot-chip--lead" data-cbot-lead>' +
					escHtml( t.leadOpen || '' ) +
					'</button>';
			}
			if ( actions ) {
				html += '<div class="laca-cbot-actions">' + actions + '</div>';
			}
			const row = appendRow( 'assistant', html );
			row.querySelectorAll( '[data-rate]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					rate(
						m,
						parseInt( btn.getAttribute( 'data-rate' ), 10 ),
						row
					);
				} );
			} );
			return row;
		}

		function liveChip() {
			return (
				'<button type="button" class="laca-cbot-chip laca-cbot-chip--lead" data-cbot-live>' +
				escHtml( t.liveOpen || '' ) +
				'</button>'
			);
		}

		function renderSuggestions() {
			if ( ! cfg.suggestions || ! cfg.suggestions.length ) {
				return;
			}
			const wrap = document.createElement( 'div' );
			wrap.className = 'laca-cbot-chips';
			cfg.suggestions.forEach( function ( q ) {
				const b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'laca-cbot-chip';
				b.textContent = q;
				b.addEventListener( 'click', function () {
					wrap.remove();
					ask( q );
				} );
				wrap.appendChild( b );
			} );
			body.appendChild( wrap );
			scrollDown();
		}

		function renderAll() {
			body.innerHTML = '';
			appendRow( 'bot', escHtml( cfg.greeting || '' ) );
			if ( ! state.messages.length ) {
				renderSuggestions();
			}
			state.messages.forEach( renderMessage );
		}

		function rate( m, value, row ) {
			if ( ! cfg.feedbackEndpoint || ! m.logId ) {
				return;
			}
			m.rated = value;
			saveState();
			row.querySelectorAll( '[data-rate]' ).forEach( function ( b ) {
				b.classList.toggle(
					'is-on',
					parseInt( b.getAttribute( 'data-rate' ), 10 ) === value
				);
			} );
			// Chưa hài lòng → mời chat với nhân viên.
			if ( value === -1 && cfg.liveEnabled && ! state.live.active ) {
				m.suggestLive = true;
				saveState();
				const acts = row.querySelector( '.laca-cbot-actions' );
				if ( acts && ! acts.querySelector( '[data-cbot-live]' ) ) {
					acts.insertAdjacentHTML( 'beforeend', liveChip() );
				}
			}
			postJson( cfg.feedbackEndpoint, {
				log_id: m.logId,
				rating: value,
				session: state.session,
			} ).catch( function () {} );
		}

		function lastQuestion() {
			for ( let i = state.messages.length - 1; i >= 0; i-- ) {
				if ( state.messages[ i ].role === 'user' ) {
					return state.messages[ i ].text;
				}
			}
			return '';
		}

		function openLeadForm() {
			if ( body.querySelector( '.laca-cbot-lead' ) ) {
				body.querySelector( '.laca-cbot-lead input' ).focus();
				return;
			}
			const box = document.createElement( 'form' );
			box.className = 'laca-cbot-lead';
			box.setAttribute( 'novalidate', '' );
			box.innerHTML =
				'<strong>' +
				escHtml( t.leadTitle || '' ) +
				'</strong>' +
				'<input name="name" required maxlength="120" aria-label="' +
				escHtml( t.leadName || '' ) +
				'" placeholder="' +
				escHtml( t.leadName || '' ) +
				'">' +
				'<input name="contact" required maxlength="120" aria-label="' +
				escHtml( t.leadContact || '' ) +
				'" placeholder="' +
				escHtml( t.leadContact || '' ) +
				'">' +
				'<textarea name="message" maxlength="1000" aria-label="' +
				escHtml( t.leadMessage || '' ) +
				'" placeholder="' +
				escHtml( t.leadMessage || '' ) +
				'"></textarea>' +
				'<div class="laca-cbot-hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>' +
				'<div class="laca-cbot-lead-row">' +
				'<button type="button" class="laca-cbot-btn laca-cbot-btn--ghost" data-cancel>' +
				escHtml( t.leadCancel || '' ) +
				'</button>' +
				'<button type="submit" class="laca-cbot-btn laca-cbot-btn--primary">' +
				escHtml( t.leadSubmit || '' ) +
				'</button>' +
				'</div>';
			box.querySelector( 'textarea' ).value = lastQuestion();
			body.appendChild( box );
			scrollDown();
			box.querySelector( 'input[name=name]' ).focus();

			box.querySelector( '[data-cancel]' ).addEventListener(
				'click',
				function () {
					box.remove();
				}
			);
			box.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				const submit = box.querySelector( '[type=submit]' );
				submit.disabled = true;
				postJson( cfg.leadEndpoint, {
					name: box.elements.name.value,
					contact: box.elements.contact.value,
					message: box.elements.message.value,
					website: box.elements.website.value,
					lang: cfg.lang || '',
					page_id: cfg.pageId || 0,
					history: historyForApi(),
				} )
					.then( function ( r ) {
						if ( ! r.ok ) {
							submit.disabled = false;
							appendRow(
								'error',
								escHtml(
									( r.json && r.json.message ) ||
										t.error ||
										''
								)
							);
							return;
						}
						// Nhớ tên/liên hệ để "Chat với nhân viên" không phải hỏi lại.
						state.profile = {
							name: box.elements.name.value.trim(),
							contact: box.elements.contact.value.trim(),
						};
						box.remove();
						const m = {
							role: 'bot',
							text: ( r.json && r.json.message ) || '',
						};
						state.messages.push( m );
						saveState();
						renderMessage( m );
					} )
					.catch( function () {
						submit.disabled = false;
						appendRow( 'error', escHtml( t.network || '' ) );
					} );
			} );
		}

		function ask( message ) {
			if ( state.live.active ) {
				liveSend( message );
				return;
			}
			const m = { role: 'user', text: message };
			state.messages.push( m );
			saveState();
			renderMessage( m );
			input.disabled = true;
			sendBtn.disabled = true;

			const loadingRow = appendRow(
				'loading',
				escHtml( t.loading || '…' )
			);

			postJson( cfg.endpoint, {
				message,
				lang: cfg.lang || '',
				page_id: cfg.pageId || 0,
				session: state.session,
				history: historyForApi().slice( 0, -1 ),
			} )
				.then( function ( r ) {
					loadingRow.remove();
					if ( ! r.ok ) {
						appendRow(
							'error',
							escHtml(
								( r.json && r.json.message ) || t.error || ''
							)
						);
						return;
					}
					const reply = {
						role: 'assistant',
						text: r.json.reply || '',
						sources: r.json.sources || [],
						logId: r.json.log_id || 0,
						suggestLead: !! r.json.suggest_lead,
						suggestLive: !! r.json.suggest_live,
					};
					state.messages.push( reply );
					saveState();
					renderMessage( reply );
				} )
				.catch( function () {
					loadingRow.remove();
					appendRow( 'error', escHtml( t.network || '' ) );
				} )
				.finally( function () {
					input.disabled = false;
					sendBtn.disabled = false;
					input.focus();
				} );
		}

		// ── Chat với nhân viên ───────────────────────────────────────────────
		function pushBot( text, extra ) {
			const m = Object.assign( { role: 'bot', text }, extra || {} );
			state.messages.push( m );
			saveState();
			renderMessage( m );
		}

		function setLiveUi( on ) {
			statusEl.hidden = ! on;
			endBtn.hidden = ! on;
			input.placeholder = on
				? t.livePlaceholder || ''
				: cfg.placeholder || '';
			panel
				.querySelectorAll( '.laca-cbot-handoff [data-cbot-live]' )
				.forEach( function ( b ) {
					b.hidden = on;
				} );
		}

		function liveUrl( path ) {
			return cfg.liveEndpoint + path;
		}

		// Chưa biết tên (chưa đăng nhập, chưa điền form) → hỏi tên, không bắt buộc.
		function startLive() {
			if ( state.live.active || ! cfg.liveEnabled ) {
				return;
			}
			panel
				.querySelectorAll( '.laca-cbot-body [data-cbot-live]' )
				.forEach( function ( b ) {
					b.remove();
				} );
			if ( cfg.knownUser || ( state.profile && state.profile.name ) ) {
				connectLive( ( state.profile && state.profile.name ) || '' );
				return;
			}
			if ( body.querySelector( '.laca-cbot-askname' ) ) {
				body.querySelector( '.laca-cbot-askname input' ).focus();
				return;
			}
			const box = document.createElement( 'form' );
			box.className = 'laca-cbot-lead laca-cbot-askname';
			box.innerHTML =
				'<strong>' +
				escHtml( t.liveAskName || '' ) +
				'</strong>' +
				'<input name="name" maxlength="60" autocomplete="name" aria-label="' +
				escHtml( t.leadName || '' ) +
				'" placeholder="' +
				escHtml( t.liveNameOptional || '' ) +
				'">' +
				'<div class="laca-cbot-lead-row">' +
				'<button type="button" class="laca-cbot-btn laca-cbot-btn--ghost" data-skip>' +
				escHtml( t.liveSkip || '' ) +
				'</button>' +
				'<button type="submit" class="laca-cbot-btn laca-cbot-btn--primary">' +
				escHtml( t.liveStart || '' ) +
				'</button>' +
				'</div>';
			body.appendChild( box );
			scrollDown();
			box.querySelector( 'input' ).focus();
			function go( name ) {
				box.remove();
				if ( name ) {
					state.profile = { name, contact: '' };
					saveState();
				}
				connectLive( name );
			}
			box.querySelector( '[data-skip]' ).addEventListener(
				'click',
				function () {
					go( '' );
				}
			);
			box.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				go( box.elements.name.value.trim() );
			} );
		}

		function connectLive( name ) {
			const loadingRow = appendRow(
				'loading',
				escHtml( t.loading || '…' )
			);
			postJson( liveUrl( 'start' ), {
				session: state.session,
				page_id: cfg.pageId || 0,
				lang: cfg.lang || '',
				history: historyForApi(),
				name: name || '',
				contact: ( state.profile && state.profile.contact ) || '',
			} )
				.then( function ( r ) {
					loadingRow.remove();
					if ( ! r.ok || ! r.json.available ) {
						pushBot(
							( r.json && r.json.message ) || t.error || '',
							{ suggestLead: true }
						);
						return;
					}
					state.live = {
						active: true,
						lastId: 0,
						noReplyShown: false,
					};
					saveState();
					pushBot( t.liveConnecting || '' );
					setLiveUi( true );
					schedulePoll( 500 );
				} )
				.catch( function () {
					loadingRow.remove();
					appendRow( 'error', escHtml( t.network || '' ) );
				} );
		}

		function liveSend( message ) {
			const m = { role: 'user', text: message, live: true };
			state.messages.push( m );
			saveState();
			renderMessage( m );
			postJson( liveUrl( 'send' ), {
				session: state.session,
				message,
			} )
				.then( function ( r ) {
					if ( r.ok ) {
						schedulePoll( 1500 );
						return;
					}
					if ( r.json && r.json.code === 'live_closed' ) {
						endLiveLocal();
						return;
					}
					appendRow(
						'error',
						escHtml( ( r.json && r.json.message ) || t.error || '' )
					);
				} )
				.catch( function () {
					appendRow( 'error', escHtml( t.network || '' ) );
				} );
		}

		function endLiveLocal() {
			if ( ! state.live.active ) {
				return;
			}
			state.live.active = false;
			clearTimeout( pollTimer );
			pushBot( t.liveEnded || '' );
			setLiveUi( false );
		}

		function endLive() {
			postJson( liveUrl( 'end' ), { session: state.session } ).catch(
				function () {}
			);
			endLiveLocal();
		}

		function schedulePoll( delay ) {
			clearTimeout( pollTimer );
			if ( ! state.live.active ) {
				return;
			}
			let wait = delay;
			if ( typeof wait !== 'number' ) {
				const fast =
					panel.classList.contains( 'is-open' ) && ! document.hidden;
				wait = fast ? POLL_FAST : POLL_SLOW;
			}
			pollTimer = setTimeout( poll, wait );
		}

		function poll() {
			postJson( liveUrl( 'poll' ), {
				session: state.session,
				after: state.live.lastId || 0,
			} )
				.then( function ( r ) {
					if ( ! r.ok || ! state.live.active ) {
						return;
					}
					( r.json.messages || [] ).forEach( function ( msg ) {
						state.live.lastId = Math.max(
							state.live.lastId,
							msg.id
						);
						if ( msg.sender === 'admin' ) {
							const m = {
								role: 'agent',
								name: msg.name,
								text: msg.text,
							};
							state.messages.push( m );
							renderMessage( m );
							if ( ! panel.classList.contains( 'is-open' ) ) {
								toggle.classList.add( 'has-new' );
								toggle.setAttribute(
									'aria-label',
									( cfg.name || 'Chatbot' ) +
										' — ' +
										( t.newMessage || '' )
								);
							}
						}
					} );
					saveState();
					const ended =
						r.json.status === 'closed' ||
						r.json.status === 'none' ||
						( r.json.messages || [] ).some( function ( msg ) {
							return (
								msg.sender === 'system' && msg.text === 'ended'
							);
						} );
					if ( ended ) {
						endLiveLocal();
						return;
					}
					if ( r.json.no_reply && ! state.live.noReplyShown ) {
						state.live.noReplyShown = true;
						pushBot( t.liveNoReply || '', { suggestLead: true } );
					}
				} )
				.catch( function () {} )
				.finally( function () {
					schedulePoll();
				} );
		}

		function openPanel() {
			panel.classList.add( 'is-open' );
			toggle.classList.remove( 'has-new' );
			toggle.setAttribute( 'aria-label', cfg.name || 'Chatbot' );
			if ( state.live.active ) {
				schedulePoll( 300 );
			}
			state.open = true;
			saveState();
			input.focus();
		}

		function closePanel() {
			panel.classList.remove( 'is-open' );
			state.open = false;
			saveState();
		}

		toggle.addEventListener( 'click', function () {
			if ( panel.classList.contains( 'is-open' ) ) {
				closePanel();
			} else {
				openPanel();
			}
		} );
		panel
			.querySelector( '.laca-cbot-close' )
			.addEventListener( 'click', closePanel );
		panel.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-cbot-lead]' ) ) {
				openLeadForm();
			}
			if ( e.target.closest( '[data-cbot-live]' ) ) {
				startLive();
			}
		} );
		endBtn.addEventListener( 'click', endLive );
		document.addEventListener( 'visibilitychange', function () {
			if ( state.live.active && ! document.hidden ) {
				schedulePoll( 300 );
			}
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			const message = input.value.trim();
			if ( message ) {
				input.value = '';
				ask( message );
			}
		} );

		renderAll();
		if ( state.open ) {
			panel.classList.add( 'is-open' );
		}
		// Chuyển trang giữa cuộc chat với nhân viên → tiếp tục nhận tin.
		if ( state.live.active ) {
			setLiveUi( true );
			schedulePoll( 300 );
		}
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
