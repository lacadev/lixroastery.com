/**
 * AI Chat — Floating button + window trong Admin.
 * Vanilla JS — không dùng React/jQuery.
 * Giao tiếp với REST: POST /wp-json/laca/v1/ai/chat
 */

( function () {
	'use strict';

	// Chỉ init khi có config (localized script)
	if ( typeof lacaAIChat === 'undefined' ) {
		return;
	}

	const { endpoint, nonce, post_id, context } = lacaAIChat;

	// ── HTML Template ──────────────────────────────────────────

	const ICON_CHAT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
	</svg>`;

	const ICON_CLOSE = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
	</svg>`;

	const ICON_SEND = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
	</svg>`;

	// ── DOM Injection ─────────────────────────────────────────

	function createUI() {
		const contextHTML = context
			? `<span class="laca-ai-context" title="${ escAttr(
					context
			  ) }">${ escHTML( context ) }</span>`
			: '';

		const btn = document.createElement( 'button' );
		btn.className = 'laca-ai-chat-btn';
		btn.setAttribute( 'aria-label', 'LacaDev AI Assistant' );
		btn.setAttribute( 'title', 'AI Assistant' );
		btn.innerHTML = ICON_CHAT;

		const win = document.createElement( 'div' );
		win.className = 'laca-ai-chat-window';
		win.setAttribute( 'role', 'dialog' );
		win.setAttribute( 'aria-label', 'AI Chat' );
		win.innerHTML = `
			<div class="laca-ai-chat-header">
				<div class="laca-ai-avatar">✦</div>
				<div>
					<div class="laca-ai-title">LacaDev AI</div>
					${ contextHTML }
				</div>
				<span class="laca-ai-status" id="laca-ai-status"></span>
			</div>
			<div class="laca-ai-messages" id="laca-ai-messages">
				<div class="laca-ai-empty">
					<div class="laca-ai-empty-icon">✦</div>
					<div>Hỏi bất kỳ điều gì — content, SEO, code…</div>
				</div>
			</div>
			<div class="laca-ai-chat-footer">
				<textarea
					id="laca-ai-input"
					placeholder="Nhập câu hỏi… (⌘ + ↵ để gửi)"
					rows="1"
					aria-label="Nhập tin nhắn"
				></textarea>
				<button id="laca-ai-send" aria-label="Gửi" title="Gửi">
					${ ICON_SEND }
				</button>
			</div>
		`;

		document.body.appendChild( btn );
		document.body.appendChild( win );

		return { btn, win };
	}

	// ── Helpers ───────────────────────────────────────────────

	function escHTML( str ) {
		const d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( str ) );
		return d.innerHTML;
	}

	function escAttr( str ) {
		return str.replace( /"/g, '&quot;' );
	}

	/**
	 * Convert basic markdown → HTML (bold, code, lists)
	 * @param text
	 */
	function markdownToHTML( text ) {
		return (
			text
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				// code blocks
				.replace(
					/```[\w]*\n?([\s\S]*?)```/g,
					'<pre><code>$1</code></pre>'
				)
				// inline code
				.replace( /`([^`]+)`/g, '<code>$1</code>' )
				// bold
				.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
				// italic
				.replace( /\*(.+?)\*/g, '<em>$1</em>' )
				// unordered list
				.replace( /^- (.+)$/gm, '<li>$1</li>' )
				.replace( /(<li>.*<\/li>)/gs, '<ul>$1</ul>' )
				// numbered list
				.replace( /^\d+\. (.+)$/gm, '<li>$1</li>' )
				// line breaks
				.replace( /\n\n/g, '</p><p>' )
				.replace( /\n/g, '<br>' )
		);
	}

	// ── State ─────────────────────────────────────────────────

	const history = []; // { role: 'user'|'ai', content: string }
	let isOpen = false;
	let isBusy = false;

	// ── Chat Logic ────────────────────────────────────────────

	function appendMessage( role, content, isThinking = false ) {
		const msgs = document.getElementById( 'laca-ai-messages' );
		const empty = msgs.querySelector( '.laca-ai-empty' );
		if ( empty ) {
			empty.remove();
		}

		const div = document.createElement( 'div' );

		if ( isThinking ) {
			div.className = 'laca-ai-msg laca-ai-msg--thinking';
			div.id = 'laca-ai-thinking';
			div.innerHTML = '<span></span><span></span><span></span>';
		} else if ( role === 'user' ) {
			div.className = 'laca-ai-msg laca-ai-msg--user';
			div.textContent = content;
		} else {
			div.className = 'laca-ai-msg laca-ai-msg--ai';
			div.innerHTML = `<p>${ markdownToHTML( content ) }</p>`;
		}

		msgs.appendChild( div );
		msgs.scrollTop = msgs.scrollHeight;

		return div;
	}

	async function sendMessage() {
		const input = document.getElementById( 'laca-ai-input' );
		const sendBtn = document.getElementById( 'laca-ai-send' );
		const status = document.getElementById( 'laca-ai-status' );
		const message = input.value.trim();

		if ( ! message || isBusy ) {
			return;
		}

		isBusy = true;
		sendBtn.disabled = true;
		status.classList.add( 'laca-ai-thinking' );
		input.value = '';
		input.style.height = 'auto';

		// Append user message
		appendMessage( 'user', message );
		history.push( { role: 'user', content: message } );

		// Show thinking
		const thinking = appendMessage( '', '', true );

		try {
			const resp = await fetch( endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					message,
					post_id: post_id || 0,
					context: context || '',
				} ),
			} );

			const data = await resp.json();

			thinking.remove();

			if ( resp.ok && data.reply ) {
				appendMessage( 'ai', data.reply );
				history.push( { role: 'ai', content: data.reply } );
			} else {
				const errMsg =
					data.message || 'Có lỗi xảy ra. Vui lòng thử lại.';
				appendMessage( 'ai', `⚠ ${ errMsg }` );
			}
		} catch ( err ) {
			thinking.remove();
			appendMessage(
				'ai',
				'⚠ Không thể kết nối. Kiểm tra API key trong cài đặt theme.'
			);
			console.error( '[LacaAI]', err );
		}

		isBusy = false;
		sendBtn.disabled = false;
		status.classList.remove( 'laca-ai-thinking' );
		input.focus();
	}

	// ── Init ──────────────────────────────────────────────────

	function init() {
		const { btn, win } = createUI();
		const input = document.getElementById( 'laca-ai-input' );
		const sendBtn = document.getElementById( 'laca-ai-send' );

		// Toggle chat window
		btn.addEventListener( 'click', () => {
			isOpen = ! isOpen;
			btn.classList.toggle( 'laca-ai-open', isOpen );
			win.classList.toggle( 'laca-ai-visible', isOpen );
			if ( isOpen ) {
				setTimeout( () => input.focus(), 260 );
			}
		} );

		// Send on button click
		sendBtn.addEventListener( 'click', sendMessage );

		// Send on Cmd+Enter / Ctrl+Enter
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
				e.preventDefault();
				sendMessage();
			}
			// Auto-resize
			input.style.height = 'auto';
			input.style.height = Math.min( input.scrollHeight, 100 ) + 'px';
		} );

		// Close on Escape
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && isOpen ) {
				isOpen = false;
				btn.classList.remove( 'laca-ai-open' );
				win.classList.remove( 'laca-ai-visible' );
			}
		} );

		// Close when clicking outside
		document.addEventListener( 'click', ( e ) => {
			if (
				isOpen &&
				! win.contains( e.target ) &&
				! btn.contains( e.target )
			) {
				isOpen = false;
				btn.classList.remove( 'laca-ai-open' );
				win.classList.remove( 'laca-ai-visible' );
			}
		} );
	}

	// Init on DOM ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
