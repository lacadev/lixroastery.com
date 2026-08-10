const bindFooterContactForm = () => {
	document.querySelectorAll( '[data-bcf-form]' ).forEach( ( form ) => {
		if ( form.dataset.bcfBound ) {
			return;
		}

		form.dataset.bcfBound = '1';

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();

			const button = form.querySelector( '.bcf__btn' );
			const message = form.querySelector( '.bcf__msg' );

			if ( ! button || ! message ) {
				return;
			}

			button.classList.add( 'bcf__btn--loading' );
			button.disabled = true;

			try {
				const response = await fetch( form.action, {
					method: 'POST',
					body: new FormData( form ),
					credentials: 'same-origin',
				} );

				const result = await response.json();
				message.removeAttribute( 'hidden' );

				if ( result.success ) {
					message.textContent = result.data || 'Gửi thành công!';
					message.className = 'bcf__msg bcf__msg--ok';
					form.reset();
				} else {
					message.textContent =
						result.data || 'Có lỗi, vui lòng thử lại.';
					message.className = 'bcf__msg bcf__msg--err';
				}
			} catch ( error ) {
				message.removeAttribute( 'hidden' );
				message.textContent = 'Lỗi kết nối.';
				message.className = 'bcf__msg bcf__msg--err';
			} finally {
				button.classList.remove( 'bcf__btn--loading' );
				button.disabled = false;
			}
		} );
	} );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bindFooterContactForm );
} else {
	bindFooterContactForm();
}
