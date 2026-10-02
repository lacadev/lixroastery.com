/**
 * Popup giỏ hàng — trượt toàn màn hình từ phải, cùng cơ chế mở/đóng với
 * search-popup.js. Thao tác xóa/đổi số lượng gọi AJAX riêng (CartAjaxHandler,
 * app/src/Ajax/CartAjaxHandler.php) rồi thay thẳng HTML trong popup, không
 * reload trang.
 *
 * Dùng EVENT DELEGATION (1 listener duy nhất trên .header__cart-body) vì
 * nội dung bên trong bị ghi đè hoàn toàn (innerHTML) sau mỗi lần AJAX —
 * listener gắn trực tiếp vào từng nút +/-/xóa sẽ mất tác dụng ngay sau lần
 * cập nhật đầu tiên.
 */

export function initCartPopup() {
	const openBtn = document.getElementById( 'btn-cart-open' );
	const popup = document.getElementById( 'header-cart' );
	if ( ! openBtn || ! popup ) {
		return;
	}

	const closeBtn = popup.querySelector( '.header__popup-close' );
	const backdrop = popup.querySelector( '.header__popup-backdrop' );
	const bodyEl = popup.querySelector( '.header__cart-body' );

	let config = { ajaxurl: '', nonce: '' };
	try {
		config = JSON.parse( popup.dataset.ajaxConfig || '{}' );
	} catch ( e ) {
		config = { ajaxurl: '', nonce: '' };
	}

	const openPopup = () => {
		popup.classList.add( 'active' );
		popup.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'menu-open' );
	};

	const closePopup = () => {
		popup.classList.remove( 'active' );
		popup.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'menu-open' );
	};

	openBtn.addEventListener( 'click', openPopup );
	closeBtn?.addEventListener( 'click', closePopup );
	backdrop?.addEventListener( 'click', closePopup );

	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && popup.classList.contains( 'active' ) ) {
			closePopup();
		}
	} );

	if ( ! bodyEl || ! config.ajaxurl ) {
		return;
	}

	const updateCartCount = ( count ) => {
		document.querySelectorAll( '.header__cart-count' ).forEach( ( el ) => {
			el.textContent = count;
		} );
	};

	const postCart = ( action, params ) => {
		const body = new URLSearchParams( {
			action,
			nonce: config.nonce,
			...params,
		} );
		return fetch( config.ajaxurl, { method: 'POST', body } )
			.then( ( res ) => res.json() )
			.then( ( res ) => {
				if ( res?.success ) {
					bodyEl.innerHTML = res.data.html;
					updateCartCount( res.data.count );
				}
			} );
	};

	bodyEl.addEventListener( 'click', ( e ) => {
		const removeBtn = e.target.closest( '[data-cart-action="remove"]' );
		const qtyBtn = e.target.closest(
			'[data-cart-action="increase"], [data-cart-action="decrease"]'
		);

		if ( removeBtn ) {
			const item = removeBtn.closest( '[data-cart-key]' );
			if ( item ) {
				postCart( 'laca_cart_remove', {
					cart_key: item.dataset.cartKey,
				} );
			}
			return;
		}

		if ( qtyBtn ) {
			const item = qtyBtn.closest( '[data-cart-key]' );
			const qtyEl = item?.querySelector( '.header__cart-qty-value' );
			if ( ! item || ! qtyEl ) {
				return;
			}
			const current = parseInt( qtyEl.textContent, 10 ) || 1;
			const next =
				qtyBtn.dataset.cartAction === 'increase'
					? current + 1
					: current - 1;
			postCart( 'laca_cart_update_qty', {
				cart_key: item.dataset.cartKey,
				quantity: Math.max( 0, next ),
			} );
		}
	} );
}
