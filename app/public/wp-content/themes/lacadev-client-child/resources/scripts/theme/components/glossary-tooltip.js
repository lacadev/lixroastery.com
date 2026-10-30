/**
 * Glossary tooltip — hover/focus vào thuật ngữ đã được tự link trong bài
 * (a.glossary-term, xem app/src/Glossary/GlossaryAutoLink.php) sẽ hiện ô nhỏ
 * chứa định nghĩa, đọc từ <template id="glossary-def-{slug}"> in ở footer.
 * Tooltip cuộn dọc được nên rê chuột từ link sang tooltip không bị ẩn.
 * Thiết bị cảm ứng: không có tooltip, chạm là đi thẳng tới trang Glossary.
 */
const HIDE_DELAY = 150;
const EDGE = 8;

function initGlossaryTooltip() {
	if ( ! document.querySelector( 'a.glossary-term' ) ) {
		return;
	}
	if ( ! window.matchMedia( '(hover: hover)' ).matches ) {
		return;
	}

	const tip = document.createElement( 'div' );
	tip.id = 'glossary-tip';
	tip.className = 'glossary-tip';
	tip.setAttribute( 'role', 'tooltip' );
	tip.hidden = true;
	document.body.appendChild( tip );

	let current = null;
	let hideTimer = null;

	const position = ( link ) => {
		const rect = link.getBoundingClientRect();
		const tipRect = tip.getBoundingClientRect();
		const viewportW = document.documentElement.clientWidth;

		let left = rect.left;
		left = Math.min( left, viewportW - tipRect.width - EDGE );
		left = Math.max( left, EDGE );

		let top = rect.bottom + 6;
		if ( top + tipRect.height > window.innerHeight - EDGE ) {
			top = Math.max( EDGE, rect.top - tipRect.height - 6 );
		}

		tip.style.left = `${ left + window.scrollX }px`; /* động */
		tip.style.top = `${ top + window.scrollY }px`; /* động */
	};

	const show = ( link ) => {
		clearTimeout( hideTimer );
		if ( current === link ) {
			return;
		}
		const tpl = document.getElementById(
			`glossary-def-${ link.dataset.glossary }`
		);
		if ( ! tpl ) {
			return;
		}

		if ( current ) {
			current.removeAttribute( 'aria-describedby' );
		}
		current = link;
		tip.replaceChildren( tpl.content.cloneNode( true ) );
		tip.scrollTop = 0;
		tip.hidden = false;
		link.setAttribute( 'aria-describedby', tip.id );
		position( link );
	};

	const hide = () => {
		clearTimeout( hideTimer );
		tip.hidden = true;
		if ( current ) {
			current.removeAttribute( 'aria-describedby' );
		}
		current = null;
	};

	const scheduleHide = () => {
		clearTimeout( hideTimer );
		hideTimer = setTimeout( hide, HIDE_DELAY );
	};

	document.addEventListener( 'mouseover', ( e ) => {
		const link = e.target.closest( 'a.glossary-term' );
		if ( link ) {
			show( link );
		} else if ( tip.contains( e.target ) ) {
			clearTimeout( hideTimer );
		}
	} );

	document.addEventListener( 'mouseout', ( e ) => {
		const from =
			e.target.closest( 'a.glossary-term' ) || tip.contains( e.target );
		if (
			from &&
			! tip.contains( e.relatedTarget ) &&
			! e.relatedTarget?.closest?.( 'a.glossary-term' )
		) {
			scheduleHide();
		}
	} );

	document.addEventListener( 'focusin', ( e ) => {
		const link = e.target.closest( 'a.glossary-term' );
		if ( link ) {
			show( link );
		}
	} );

	document.addEventListener( 'focusout', ( e ) => {
		if ( e.target.closest( 'a.glossary-term' ) ) {
			scheduleHide();
		}
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && ! tip.hidden ) {
			hide();
		}
	} );

	window.addEventListener( 'resize', hide );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initGlossaryTooltip );
} else {
	initGlossaryTooltip();
}
