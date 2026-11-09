/* eslint-disable no-console, no-unused-vars, no-alert */
// eslint-disable-next-line no-unused-vars
import '@styles/login';
import { initLoginSky } from './sky';

document.addEventListener( 'DOMContentLoaded', () => {
	initLoginSky();

	const dynamicConfig = window.loginI18n || {};
	const loginHeaderLink = document.querySelector( '#login h1 a' );
	if ( loginHeaderLink ) {
		if ( dynamicConfig.homeUrl ) {
			loginHeaderLink.setAttribute( 'href', dynamicConfig.homeUrl );
		}
		loginHeaderLink.removeAttribute( 'target' );
		if ( dynamicConfig.logoUrl ) {
			loginHeaderLink.style.backgroundImage = `url("${ dynamicConfig.logoUrl }")`;
		}
	}

	// 1. CÂU CHỮ — lấy từ PHP (__() + .po, có thể ghi đè ở Theme Options), không ghi cứng ở JS
	const currentLang = document.documentElement.lang.includes( 'en' )
		? 'en'
		: 'vi';
	// Parent PHP < 3.7 chưa có `strings` → đọc `locales[lang]` kiểu cũ (câu chào dạng HTML có <br />)
	const legacyLocale = ( dynamicConfig.locales || {} )[ currentLang ];
	const i18n = dynamicConfig.strings || { ...( legacyLocale || {} ) };
	if ( ! dynamicConfig.strings && i18n.welcomeText ) {
		i18n.welcomeText = i18n.welcomeText.replace( /<br\s*\/?>/gi, '' );
	}

	const userLabel = document.querySelector( 'label[for="user_login"]' );
	if ( userLabel && i18n.userLabel ) {
		userLabel.childNodes[ 0 ].textContent = i18n.userLabel;
	}
	const userLogin = document.getElementById( 'user_login' );
	if ( userLogin && i18n.userPlaceholder ) {
		userLogin.setAttribute( 'placeholder', i18n.userPlaceholder );
	}

	const passLabel = document.querySelector( 'label[for="user_pass"]' );
	if ( passLabel && i18n.passLabel ) {
		passLabel.childNodes[ 0 ].textContent = i18n.passLabel;
	}
	const userPass = document.getElementById( 'user_pass' );
	if ( userPass && i18n.passPlaceholder ) {
		userPass.setAttribute( 'placeholder', i18n.passPlaceholder );
	}

	// Rename footer links
	const navLink = document.querySelector( '#nav a' );
	if ( navLink && i18n.forgetPwd ) {
		navLink.textContent = i18n.forgetPwd;
	}

	const backLink = document.querySelector( '#backtoblog a' );
	if ( backLink && i18n.backToBlog ) {
		backLink.textContent = i18n.backToBlog;
	}

	// Move links into a horizontal container
	const nav = document.getElementById( 'nav' );
	const back = document.getElementById( 'backtoblog' );
	const loginDiv = document.getElementById( 'login' );

	if ( nav && back && loginDiv ) {
		const footerLinks = document.createElement( 'div' );
		footerLinks.className = 'login-footer-links';
		footerLinks.appendChild( nav );
		footerLinks.appendChild( back );
		loginDiv.appendChild( footerLinks );
	}

	// 2. CUSTOM LANGUAGE TOGGLE
	const switcher = document.querySelector( '.language-switcher' );
	const switcherForm = document.querySelector( '.language-switcher form' );
	const switcherSelect = document.querySelector(
		'.language-switcher select'
	);

	if ( switcher && loginDiv ) {
		// Place toggle ABOVE the form/logo
		loginDiv.prepend( switcher );

		if ( switcherForm && switcherSelect ) {
			const langWrapper = document.createElement( 'div' );
			langWrapper.className = 'alp-lang-toggle';

			[
				[ 'vi', 'vi', i18n.langVi ],
				[ 'en_US', 'en', i18n.langEn ],
			].forEach( ( [ value, code, label ] ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.dataset.lang = value;
				btn.textContent = label || code;
				if ( currentLang === code ) {
					btn.classList.add( 'active' );
				}
				langWrapper.appendChild( btn );
			} );

			switcherForm.appendChild( langWrapper );

			langWrapper.querySelectorAll( 'button' ).forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => {
					const targetLang = e.target.getAttribute( 'data-lang' );
					if ( switcherSelect.value !== targetLang ) {
						switcherSelect.value = targetLang;
						switcherForm.submit();
					}
				} );
			} );
		}
	}

	// 3. TYPEWRITER EFFECT
	const welcomeDiv = document.createElement( 'div' );
	welcomeDiv.className = 'welcome';

	const logo = document.querySelector( '#login h1' );
	if ( logo ) {
		logo.insertAdjacentElement( 'afterend', welcomeDiv );
	}

	const welcomeText = i18n.welcomeText || '';
	let charIndex = 0;

	// Gõ từng ký tự bằng text node; '\n' thành <br> — không dùng innerHTML
	function typeWriter() {
		if ( charIndex < welcomeText.length ) {
			const ch = welcomeText.charAt( charIndex );
			if ( ch === '\n' ) {
				welcomeDiv.appendChild( document.createElement( 'br' ) );
			} else if ( ch !== '\r' ) {
				welcomeDiv.appendChild( document.createTextNode( ch ) );
			}
			charIndex++;

			const speed = Math.random() * ( 150 - 50 ) + 50;
			setTimeout( typeWriter, speed );
		} else {
			welcomeDiv.classList.add( 'typed-done' );
		}
	}

	setTimeout( typeWriter, 600 );
} );
