/* eslint-disable no-console, no-unused-vars, no-alert */
const loginFunction = {
	init( formElement ) {},
};

document.addEventListener( 'DOMContentLoaded', () => {
	const loginForm = document.getElementById( 'login_form' );
	if ( loginForm ) {
		loginFunction.init( loginForm );
	}
} );
