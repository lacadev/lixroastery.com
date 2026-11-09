/**
 * Login nature scene: cập nhật vị trí mặt trời / mặt trăng và tông màu mỗi phút.
 * Cùng công thức với laca_login_sky_state() (theme/functions.php); giờ lấy theo
 * múi giờ của site (data-tz-offset), không theo đồng hồ máy người xem.
 */
const PHASES = [ 'dawn', 'day', 'dusk', 'night' ];

function skyState( start, end, offsetSec ) {
	const now = new Date( Date.now() + offsetSec * 1000 );
	const hour = now.getUTCHours() + now.getUTCMinutes() / 60;
	const isDay = hour >= start && hour < end;
	const dayLen = end - start;
	const progress = isDay
		? ( hour - start ) / dayLen
		: ( ( hour - end + 24 ) % 24 ) / ( 24 - dayLen );

	let phase = isDay ? 'day' : 'night';
	if ( Math.abs( hour - start ) < 1 ) {
		phase = 'dawn';
	} else if ( Math.abs( hour - end ) < 1 ) {
		phase = 'dusk';
	}

	return {
		isDay,
		phase,
		x: 6 + progress * 88,
		y: 76 - Math.sin( Math.PI * progress ) * 69,
	};
}

export function initLoginSky() {
	const scene = document.querySelector( '.login-scene' );
	if ( ! scene ) {
		return;
	}

	const start = parseFloat( scene.dataset.dayStart );
	const end = parseFloat( scene.dataset.dayEnd );
	const offset = parseInt( scene.dataset.tzOffset, 10 ) || 0;
	if ( Number.isNaN( start ) || Number.isNaN( end ) ) {
		return;
	}

	const orb = scene.querySelector( '.ls-orb' );
	const body = document.body;

	const update = () => {
		const s = skyState( start, end, offset );
		scene.style.setProperty( '--orb-x', `${ s.x.toFixed( 2 ) }%` );
		scene.style.setProperty( '--orb-y', `${ s.y.toFixed( 2 ) }%` );

		if ( orb ) {
			orb.classList.toggle( 'is-sun', s.isDay );
			orb.classList.toggle( 'is-moon', ! s.isDay );
		}

		body.classList.toggle( 'login-is-day', s.isDay );
		body.classList.toggle( 'login-is-night', ! s.isDay );
		PHASES.forEach( ( p ) =>
			body.classList.toggle( `login-phase-${ p }`, p === s.phase )
		);
	};

	setInterval( update, 60 * 1000 );
}
