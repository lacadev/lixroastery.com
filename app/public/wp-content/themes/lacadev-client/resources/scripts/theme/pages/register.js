/* eslint-disable no-console, no-unused-vars, no-alert */
import Swal from 'sweetalert2/dist/sweetalert2';

window.registerFunction = {
	form: null,

	validateEmail( email ) {
		const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return re.test( email );
	},

	validateField( field, rules, form ) {
		const value = field.value.trim();
		let isValid = true;
		let errorMessage = '';

		if ( rules.required && ! value ) {
			isValid = false;
			errorMessage = 'This field is required.';
		} else if ( rules.email && ! this.validateEmail( value ) ) {
			isValid = false;
			errorMessage = 'Please enter a valid email address.';
		} else if ( rules.maxlength && value.length > rules.maxlength ) {
			isValid = false;
			errorMessage = `Max length is ${ rules.maxlength } characters.`;
		} else if ( rules.equalTo ) {
			const target = form.querySelector( rules.equalTo );
			if ( target && value !== target.value ) {
				isValid = false;
				errorMessage = 'Values do not match.';
			}
		}

		// Simple error display (can be improved)
		const errorDisplay =
			field.parentElement.querySelector( '.error-message' ) ||
			document.createElement( 'div' );
		errorDisplay.className = 'error-message text-danger';
		if ( ! isValid ) {
			errorDisplay.textContent = errorMessage;
			if ( ! field.parentElement.querySelector( '.error-message' ) ) {
				field.parentElement.appendChild( errorDisplay );
			}
			field.classList.add( 'is-invalid' );
		} else {
			if ( field.parentElement.querySelector( '.error-message' ) ) {
				errorDisplay.remove();
			}
			field.classList.remove( 'is-invalid' );
		}

		return isValid;
	},

	initJobSeekerForm( formElement ) {
		const _this = this;
		_this.form = formElement;

		_this.form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			let isValid = true;

			// Define rules (simplified for brevity, extend as needed)
			const rules = {
				username: { required: true, maxlength: 15 },
				email: { required: true, email: true, maxlength: 50 },
				password: { required: true, maxlength: 50 },
				password_confirm: {
					required: true,
					maxlength: 50,
					equalTo: '[name="password"]',
				},
				'extras[last_name]': { required: true },
				'extras[first_name]': { required: true },
				'extras[_phone_number]': { required: true, maxlength: 13 },
				'extras[_identify_number]': { required: true, maxlength: 15 },
			};

			for ( const [ fieldName, rule ] of Object.entries( rules ) ) {
				const field = _this.form.querySelector(
					`[name="${ fieldName }"]`
				);
				if ( field ) {
					if ( ! _this.validateField( field, rule, _this.form ) ) {
						isValid = false;
					}
				}
			}

			if ( isValid ) {
				_this.submitForm();
			}
		} );
	},

	initEmployerForm( formElement ) {
		const _this = this;
		this.form = formElement;

		this.form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			let isValid = true;

			const rules = {
				username: { required: true, maxlength: 15 },
				description: { required: true, maxlength: 500 },
				email: { required: true, email: true, maxlength: 50 },
				password: { required: true, maxlength: 50 },
				password_confirm: {
					required: true,
					maxlength: 50,
					equalTo: '[name="password"]',
				},
				'extras[last_name]': { required: true },
				'extras[first_name]': { required: true },
				'extras[_phone_number]': { required: true, maxlength: 13 },
				'extras[_company_name]': { required: true, maxlength: 100 },
				'extras[_tax_code]': { required: true, maxlength: 15 },
				'extras[_address]': { required: true, maxlength: 255 },
				'extras[_province]': { required: true },
				'extras[_career]': { required: true },
				'extras[_company_size]': { required: true },
			};

			for ( const [ fieldName, rule ] of Object.entries( rules ) ) {
				const field = _this.form.querySelector(
					`[name="${ fieldName }"]`
				);
				if ( field ) {
					if ( ! _this.validateField( field, rule, _this.form ) ) {
						isValid = false;
					}
				}
			}

			if ( isValid ) {
				_this.submitForm();
			}
		} );
	},

	submitForm() {
		const _this = this;
		showPageLoader();

		const formData = new FormData( _this.form );
		const searchParams = new URLSearchParams();
		for ( const pair of formData ) {
			searchParams.append( pair[ 0 ], pair[ 1 ] );
		}

		fetch( _this.form.getAttribute( 'action' ), {
			method: _this.form.getAttribute( 'method' ),
			body: searchParams,
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				hidePageLoader();
				if ( response.success !== true ) {
					let msg = 'Đăng ký tài khoản không thành công!';
					if ( typeof response.data === 'string' ) {
						msg = response.data;
					}
					Swal.fire( 'Lỗi!', msg, 'error' );
				} else {
					Swal.fire(
						'Thành công!',
						'Đăng ký tài khoản thành công!',
						'success'
					).then( () => {
						window.location.href = response.data.redirect_to;
					} );
				}
			} )
			.catch( ( error ) => {
				console.error( error );
				Swal.fire(
					'Lỗi!',
					'Đã có lỗi xảy ra, vui lòng thử lại.',
					'error'
				);
				hidePageLoader();
			} );
	},
};
