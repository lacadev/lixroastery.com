/**
 * Animations
 * GSAP animations, text split, và 404 scene.
 */

import gsap from 'gsap';

export function setupGsap404() {
	gsap.set( 'svg', { visibility: 'visible' } );

	gsap.to( '#spaceman', { y: 5, rotation: 2, yoyo: true, repeat: -1, ease: 'sine.inOut', duration: 1 } );

	gsap.to( '#starsBig line', {
		rotation: 'random(-30,30)', transformOrigin: '50% 50%', yoyo: true, repeat: -1, ease: 'sine.inOut',
	} );

	gsap.fromTo( '#starsSmall g', { scale: 0 }, {
		scale: 1, transformOrigin: '50% 50%', yoyo: true, repeat: -1, stagger: 0.1,
	} );

	gsap.to( '#circlesSmall circle', { y: -4, yoyo: true, duration: 1, ease: 'sine.inOut', repeat: -1 } );
	gsap.to( '#circlesBig circle', { y: -2, yoyo: true, duration: 1, ease: 'sine.inOut', repeat: -1 } );

	gsap.set( '#glassShine', { x: -68 } );
	gsap.to( '#glassShine', {
		x: 80, duration: 2, rotation: -30, ease: 'expo.inOut',
		transformOrigin: '50% 50%', repeat: -1, repeatDelay: 8, delay: 2,
	} );
}
