/**
 * Parse a video URL and convert supported providers to embed URLs.
 * Returns the original URL when the provider is not recognized.
 *
 * @param {string} url Video URL from block attributes.
 * @return {string} Embed URL (or the original URL if unsupported).
 */
export function getVideoEmbedUrl( url = '' ) {
	const normalizedUrl = ( url || '' ).trim();

	if ( ! normalizedUrl ) {
		return '';
	}

	// YouTube
	const youtubeMatch = normalizedUrl.match(
		/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
	);
	if ( youtubeMatch ) {
		return `https://www.youtube.com/embed/${ youtubeMatch[ 1 ] }`;
	}

	// Vimeo
	const vimeoMatch = normalizedUrl.match( /vimeo\.com\/(\d+)/ );
	if ( vimeoMatch ) {
		return `https://player.vimeo.com/video/${ vimeoMatch[ 1 ] }`;
	}

	return normalizedUrl;
}
