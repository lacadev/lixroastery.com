/**
 * Trình phát "Nghe bài viết".
 * - data-mode="mp3": phát file MP3 đã tạo sẵn.
 * - data-mode="speech": đọc bằng Web Speech API; không có giọng hợp ngôn ngữ thì giữ ẩn.
 * Mọi câu chữ lấy từ data-i18n-* do PHP in ra (dịch qua .po).
 */

const SPEEDS = [ 0.75, 1, 1.25, 1.5, 2 ];

function formatTime( seconds ) {
	if ( ! Number.isFinite( seconds ) || seconds < 0 ) {
		return '0:00';
	}
	const m = Math.floor( seconds / 60 );
	const s = Math.floor( seconds % 60 );
	return `${ m }:${ s < 10 ? '0' : '' }${ s }`;
}

function fill( template, ...values ) {
	let i = 0;
	return ( template || '' )
		.replace( /%(\d)\$s/g, ( _, n ) => values[ Number( n ) - 1 ] ?? '' )
		.replace( /%s/g, () => values[ i++ ] ?? '' );
}

function setupControls( container ) {
	const t = ( key ) => container.dataset[ `i18n${ key }` ] || '';
	const playBtn = container.querySelector( '.laca-post-audio__play' );
	const speedBtn = container.querySelector( '.laca-post-audio__speed' );
	let speedIndex = SPEEDS.indexOf( 1 );

	const setPlaying = ( playing ) => {
		container.classList.toggle( 'is-playing', playing );
		playBtn.setAttribute(
			'aria-label',
			playing ? t( 'Pause' ) : t( 'Play' )
		);
	};

	const nextSpeed = () => {
		speedIndex = ( speedIndex + 1 ) % SPEEDS.length;
		const speed = SPEEDS[ speedIndex ];
		speedBtn.textContent = `${ speed }x`;
		speedBtn.dataset.speed = String( speed );
		speedBtn.setAttribute(
			'aria-label',
			fill( t( 'Speed' ), `${ speed }x` )
		);
		return speed;
	};

	return { t, playBtn, speedBtn, setPlaying, nextSpeed };
}

function setupMp3( container ) {
	const audio = container.querySelector( '.laca-post-audio__native' );
	if ( ! audio ) {
		return;
	}

	const { t, playBtn, speedBtn, setPlaying, nextSpeed } =
		setupControls( container );
	const slider = container.querySelector( '.laca-post-audio__progress' );
	const bar = container.querySelector( '.laca-post-audio__progress-bar' );
	const timeCurrent = container.querySelector(
		'.laca-post-audio__time-current'
	);
	const timeTotal = container.querySelector( '.laca-post-audio__time-total' );

	const render = () => {
		const duration = audio.duration || 0;
		const pct = duration ? ( audio.currentTime / duration ) * 100 : 0;
		bar.style.width = `${ pct }%`;
		timeCurrent.textContent = formatTime( audio.currentTime );
		slider.setAttribute( 'aria-valuenow', String( Math.round( pct ) ) );
		slider.setAttribute(
			'aria-valuetext',
			fill(
				t( 'Progress' ),
				formatTime( audio.currentTime ),
				formatTime( duration )
			)
		);
	};

	const seekTo = ( seconds ) => {
		if ( ! audio.duration ) {
			return;
		}
		audio.currentTime = Math.min( audio.duration, Math.max( 0, seconds ) );
		render();
	};

	playBtn.addEventListener( 'click', () => {
		if ( audio.paused ) {
			audio.play().catch( () => setPlaying( false ) );
		} else {
			audio.pause();
		}
	} );

	audio.addEventListener( 'play', () => setPlaying( true ) );
	audio.addEventListener( 'pause', () => setPlaying( false ) );
	audio.addEventListener( 'ended', () => setPlaying( false ) );
	audio.addEventListener( 'timeupdate', render );
	audio.addEventListener( 'loadedmetadata', () => {
		timeTotal.textContent = formatTime( audio.duration );
		render();
	} );

	slider.addEventListener( 'click', ( e ) => {
		const rect = slider.getBoundingClientRect();
		seekTo( ( ( e.clientX - rect.left ) / rect.width ) * audio.duration );
	} );

	slider.addEventListener( 'keydown', ( e ) => {
		const actions = {
			ArrowRight: () => seekTo( audio.currentTime + 5 ),
			ArrowUp: () => seekTo( audio.currentTime + 5 ),
			ArrowLeft: () => seekTo( audio.currentTime - 5 ),
			ArrowDown: () => seekTo( audio.currentTime - 5 ),
			Home: () => seekTo( 0 ),
			End: () => seekTo( audio.duration ),
		};
		if ( actions[ e.key ] ) {
			e.preventDefault();
			actions[ e.key ]();
		}
	} );

	speedBtn.addEventListener( 'click', () => {
		audio.playbackRate = nextSpeed();
	} );

	window.addEventListener( 'pagehide', () => audio.pause() );
}

function setupSpeech( container ) {
	if ( ! ( 'speechSynthesis' in window ) ) {
		return;
	}

	const synth = window.speechSynthesis;
	const lang = ( container.dataset.lang || 'vi' ).toLowerCase();
	const { t, playBtn, speedBtn, setPlaying, nextSpeed } =
		setupControls( container );
	const status = container.querySelector( '.laca-post-audio__status' );

	let voice = null;
	let sentences = [];
	let index = 0;
	let rate = 1;
	let state = 'idle'; // idle | speaking | paused
	// Mỗi lần đọc lại (đổi tốc độ, dừng) tăng runId; sự kiện của utterance cũ bị bỏ qua
	// — Chrome bắn onerror khi cancel(), không được coi là lỗi thật.
	let runId = 0;

	const setStatus = ( text ) => {
		if ( status ) {
			status.textContent = text;
		}
	};

	const pickVoice = () => {
		voice =
			synth
				.getVoices()
				.find( ( v ) =>
					( v.lang || '' ).toLowerCase().startsWith( lang )
				) || null;
		// Chỉ hiện trình phát khi có giọng đúng ngôn ngữ của bài.
		container.hidden = ! voice;
	};

	const getSentences = () => {
		// Trình phát được chèn đầu nội dung bài → phần tử cha chính là vùng nội dung.
		const source = container.parentElement;
		if ( ! source ) {
			return [];
		}
		const clone = source.cloneNode( true );
		clone
			.querySelectorAll(
				'.laca-post-audio, pre, code, table, script, style, noscript, iframe, figure figcaption, .laca-related-posts'
			)
			.forEach( ( el ) => el.remove() );
		const text = ( clone.innerText || clone.textContent || '' )
			.replace( /[ \t]+/g, ' ' )
			.trim();
		return text
			.split( /(?<=[.!?…])\s+|\n+/u )
			.map( ( s ) => s.trim() )
			.filter( Boolean );
	};

	const finish = () => {
		runId++;
		synth.cancel();
		state = 'idle';
		index = 0;
		setPlaying( false );
	};

	const speakFrom = ( i ) => {
		const myRun = ++runId;
		synth.cancel();

		const speakNext = ( n ) => {
			if ( myRun !== runId ) {
				return;
			}
			if ( n >= sentences.length ) {
				finish();
				setStatus( t( 'Done' ) );
				return;
			}
			index = n;
			const utter = new window.SpeechSynthesisUtterance( sentences[ n ] );
			utter.voice = voice;
			utter.lang = voice.lang;
			utter.rate = rate;
			utter.onend = () => speakNext( n + 1 );
			utter.onerror = ( e ) => {
				if (
					myRun !== runId ||
					e.error === 'interrupted' ||
					e.error === 'canceled'
				) {
					return;
				}
				finish();
				setStatus( t( 'Ready' ) );
			};
			synth.speak( utter );
		};

		state = 'speaking';
		setPlaying( true );
		setStatus( t( 'Reading' ) );
		speakNext( i );
	};

	playBtn.addEventListener( 'click', () => {
		if ( ! voice ) {
			return;
		}
		if ( state === 'speaking' ) {
			synth.pause();
			state = 'paused';
			setPlaying( false );
			setStatus( t( 'Paused' ) );
			return;
		}
		if ( state === 'paused' ) {
			synth.resume();
			state = 'speaking';
			setPlaying( true );
			setStatus( t( 'Reading' ) );
			return;
		}
		sentences = getSentences();
		if ( sentences.length ) {
			speakFrom( 0 );
		}
	} );

	speedBtn.addEventListener( 'click', () => {
		rate = nextSpeed();
		// Áp tốc độ mới ngay từ câu đang đọc.
		if ( state === 'speaking' ) {
			speakFrom( index );
		}
	} );

	window.addEventListener( 'pagehide', finish );

	pickVoice();
	synth.addEventListener?.( 'voiceschanged', pickVoice );
}

export function initPostAudio() {
	document.querySelectorAll( '.laca-post-audio' ).forEach( ( container ) => {
		// Chống gắn sự kiện 2 lần nếu init được gọi lại (điều hướng trang không tải lại).
		if ( container.dataset.ready ) {
			return;
		}
		container.dataset.ready = '1';

		if ( container.dataset.mode === 'mp3' ) {
			setupMp3( container );
		} else {
			setupSpeech( container );
		}
	} );
}
