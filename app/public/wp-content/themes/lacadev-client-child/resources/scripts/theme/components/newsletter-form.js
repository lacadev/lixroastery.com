// Form Newsletter ở footer — xem theme/template-parts/newsletter-form.php.
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Token reCAPTCHA v3 chỉ dùng được 1 lần và hết hạn sau 2 phút — lấy mới
// ngay trước mỗi lần gửi thay vì lấy sẵn lúc tải trang.
const getRecaptchaToken = ( form ) => {
	const { recaptchaKey, recaptchaAction } = form.dataset;

	if ( ! recaptchaKey || ! window.grecaptcha ) {
		return Promise.resolve( '' );
	}

	return new Promise( ( resolve ) => {
		window.grecaptcha.ready( () => {
			window.grecaptcha
				.execute( recaptchaKey, { action: recaptchaAction } )
				.then( resolve, () => resolve( '' ) );
		} );
	} );
};

const postForm = async ( form ) => {
	const body = new FormData( form );
	body.set( 'recaptcha_token', await getRecaptchaToken( form ) );

	const response = await fetch( form.action, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} );

	return {
		status: response.status,
		result: await response.json().catch( () => ( {} ) ),
	};
};

// Trang có thể bị cache lâu hơn tuổi thọ nonce — lấy nonce mới rồi gửi lại 1 lần.
const refreshNonce = async ( form ) => {
	const body = new FormData();
	body.append( 'action', 'lix_newsletter_nonce' );

	const response = await fetch( form.action, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} );
	const result = await response.json();

	if ( ! result.success || ! result.data?.nonce ) {
		return false;
	}

	form.querySelector( 'input[name="nonce"]' ).value = result.data.nonce;
	return true;
};

const bindNewsletterForm = ( form ) => {
	const input = form.querySelector( '.newsletter__input' );
	const button = form.querySelector( '.newsletter__btn' );
	const message = form.querySelector( '.newsletter__msg' );

	if ( ! input || ! button || ! message ) {
		return;
	}

	let messages = {};
	try {
		messages = JSON.parse( form.dataset.messages || '{}' );
	} catch ( e ) {
		messages = {};
	}

	const showMessage = ( code ) => {
		const isError = ! [ 'subscribed', 'exists' ].includes( code );
		message.textContent = messages[ code ] || messages.error || '';
		message.classList.toggle( 'newsletter__msg--error', isError );
		message.hidden = false;
		input.setAttribute(
			'aria-invalid',
			code === 'invalid' ? 'true' : 'false'
		);
	};

	input.addEventListener( 'input', () => {
		input.removeAttribute( 'aria-invalid' );
		message.hidden = true;
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		if ( button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		input.value = input.value.trim();
		if ( ! EMAIL_PATTERN.test( input.value ) ) {
			showMessage( 'invalid' );
			input.focus();
			return;
		}

		button.setAttribute( 'aria-busy', 'true' );
		button.disabled = true;

		try {
			let { status, result } = await postForm( form );

			if ( status === 403 && result.data?.code === 'nonce' ) {
				if ( await refreshNonce( form ) ) {
					( { status, result } = await postForm( form ) );
				}
			}

			const code = result.data?.code || 'error';
			showMessage( code );

			if ( result.success ) {
				form.reset();
			}
		} catch ( error ) {
			showMessage( 'error' );
		} finally {
			button.removeAttribute( 'aria-busy' );
			button.disabled = false;
		}
	} );
};

const initNewsletterForms = () => {
	document
		.querySelectorAll( '[data-newsletter-form]' )
		.forEach( bindNewsletterForm );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initNewsletterForms );
} else {
	initNewsletterForms();
}
