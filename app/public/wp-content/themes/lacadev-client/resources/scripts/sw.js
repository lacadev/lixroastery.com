/**
 * Service Worker for Progressive Web App Features
 *
 * @package
 * @version 1.0.0
 */

const CACHE_VERSION = 'lacadev-v1.1.0';
const CACHE_NAME = `lacadev-cache-${ CACHE_VERSION }`;

// Assets to cache on install
const STATIC_ASSETS = [
	'/',
	'/dist/styles/theme.css',
	'/dist/theme.js',
	'/offline.php',
];

// Install event - cache static assets
self.addEventListener( 'install', ( event ) => {
	console.log( '[SW] Installing service worker...' );

	event.waitUntil(
		caches
			.open( CACHE_NAME )
			.then( ( cache ) => {
				console.log( '[SW] Caching static assets' );
				return cache.addAll(
					STATIC_ASSETS.map(
						( url ) =>
							new Request( url, {
								cache: 'reload',
							} )
					)
				);
			} )
			.then( () => self.skipWaiting() )
			.catch( ( err ) =>
				console.error( '[SW] Cache installation failed:', err )
			)
	);
} );

// Activate event - clean up old caches
self.addEventListener( 'activate', ( event ) => {
	console.log( '[SW] Activating new service worker...' );

	event.waitUntil(
		caches
			.keys()
			.then( ( cacheNames ) =>
				Promise.all(
					cacheNames
						.filter( ( cacheName ) =>
							cacheName.startsWith( 'lacadev-cache-' )
						)
						.filter( ( cacheName ) => cacheName !== CACHE_NAME )
						.map( ( cacheName ) => {
							console.log(
								'[SW] Deleting old cache:',
								cacheName
							);
							return caches.delete( cacheName );
						} )
				)
			)
			.then( () => self.clients.claim() )
	);
} );

// Fetch event - serve from cache with network fallback
self.addEventListener( 'fetch', ( event ) => {
	const { request } = event;
	const url = new URL( request.url );

	// Skip cross-origin requests
	if ( url.origin !== location.origin ) {
		return;
	}

	// Skip admin and login pages
	if (
		url.pathname.includes( '/wp-admin' ) ||
		url.pathname.includes( '/wp-login' )
	) {
		return;
	}

	// Skip AJAX and REST API requests (always fresh)
	if (
		url.pathname.includes( 'admin-ajax.php' ) ||
		url.pathname.includes( '/wp-json/' )
	) {
		return;
	}

	// Skip non-GET requests (POST forms, etc.)
	if ( request.method !== 'GET' ) {
		return;
	}

	// Determine caching strategy based on request type
	if ( isStaticAsset( request ) ) {
		// Cache-first strategy for static assets
		event.respondWith( cacheFirst( request ) );
	} else {
		// Network-first strategy for HTML pages
		event.respondWith( networkFirst( request ) );
	}
} );

/**
 * Check if request is for a static asset
 * @param request
 */
function isStaticAsset( request ) {
	const url = new URL( request.url );
	const staticExtensions = [
		'.css',
		'.js',
		'.jpg',
		'.jpeg',
		'.png',
		'.gif',
		'.webp',
		'.svg',
		'.woff',
		'.woff2',
		'.ttf',
		'.eot',
	];
	return staticExtensions.some( ( ext ) => url.pathname.endsWith( ext ) );
}

/**
 * Cache-first strategy
 * Try cache first, then network
 * @param request
 */
async function cacheFirst( request ) {
	try {
		const cachedResponse = await caches.match( request );
		if ( cachedResponse ) {
			return cachedResponse;
		}

		const networkResponse = await fetch( request );

		// Cache successful responses
		if ( networkResponse && networkResponse.status === 200 ) {
			const cache = await caches.open( CACHE_NAME );
			cache.put( request, networkResponse.clone() );
		}

		return networkResponse;
	} catch ( error ) {
		console.error( '[SW] Cache-first fetch failed:', error );
		// Return offline fallback if available
		const offlineFallback = await caches.match( '/offline.php' );
		if ( offlineFallback ) {
			return offlineFallback;
		}
		return new Response( 'Offline — Không có kết nối mạng', {
			status: 503,
			statusText: 'Service Unavailable',
		} );
	}
}

/**
 * Network-first strategy
 * Try network first, fallback to cache
 * @param request
 */
async function networkFirst( request ) {
	try {
		const networkResponse = await fetch( request );

		// Cache successful response
		if ( networkResponse && networkResponse.status === 200 ) {
			const cache = await caches.open( CACHE_NAME );
			cache.put( request, networkResponse.clone() );
		}

		return networkResponse;
	} catch ( error ) {
		console.error( '[SW] Network-first fetch failed:', error );

		// Fallback to cache
		const cachedResponse = await caches.match( request );
		if ( cachedResponse ) {
			return cachedResponse;
		}

		// Return offline fallback
		const offlineFallback = await caches.match( '/offline.php' );
		if ( offlineFallback ) {
			return offlineFallback;
		}

		return new Response( 'Offline — Không có kết nối mạng', {
			status: 503,
			statusText: 'Service Unavailable',
		} );
	}
}

// Handle messages from clients
self.addEventListener( 'message', ( event ) => {
	if ( event.data && event.data.type === 'SKIP_WAITING' ) {
		self.skipWaiting();
	}

	if ( event.data && event.data.type === 'CLEAR_CACHE' ) {
		event.waitUntil(
			caches
				.delete( CACHE_NAME )
				.then( () => {
					console.log( '[SW] Cache cleared' );
					return self.clients.matchAll();
				} )
				.then( ( clients ) => {
					clients.forEach( ( client ) => {
						client.postMessage( { type: 'CACHE_CLEARED' } );
					} );
				} )
		);
	}
} );
