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

				// Câu dự phòng lấy từ data-msg-* của form (PHP in ra qua
				// __(), dịch bằng .po) — không ghi cứng chữ trong JS.
				if ( result.success ) {
					message.textContent =
						result.data || form.dataset.msgSuccess || '';
					message.className = 'bcf__msg bcf__msg--ok';
					form.reset();
				} else {
					message.textContent =
						result.data || form.dataset.msgError || '';
					message.className = 'bcf__msg bcf__msg--err';
				}
			} catch ( error ) {
				message.removeAttribute( 'hidden' );
				message.textContent = form.dataset.msgNetwork || '';
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
