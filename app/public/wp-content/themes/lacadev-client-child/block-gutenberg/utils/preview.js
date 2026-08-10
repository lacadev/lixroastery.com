import { useBlockEditContext } from '@wordpress/block-editor';

/**
 * Detect Gutenberg inserter preview mode with fallback attribute.
 *
 * @param {Object} attributes Block attributes object.
 * @return {boolean} True when the block is rendered in inserter preview mode.
 */
export function useInserterPreview( attributes = {} ) {
	const { __unstableIsPreviewMode } = useBlockEditContext();
	return (
		Boolean( __unstableIsPreviewMode ?? false ) ||
		Boolean( attributes.__isPreview ?? false )
	);
}

/**
 * Shared lightweight preview mock for Gutenberg inserter.
 *
 * @param {Object} props         Component props.
 * @param {string} props.kicker  Small label above title.
 * @param {string} props.title   Main preview heading.
 * @param {number} props.columns Number of mock cards.
 * @return {JSX.Element} Inserter preview markup.
 */
export function BlockPreviewMock( { kicker = '', title = '', columns = 3 } ) {
	const safeColumns = Math.max( 1, Math.min( 4, Number( columns ) || 3 ) );

	return (
		<div
			style={ {
				background: '#1a1a1a',
				padding: '4rem 2rem',
				textAlign: 'center',
				color: '#fff',
			} }
		>
			{ kicker ? (
				<span
					style={ {
						display: 'block',
						fontSize: '0.75rem',
						letterSpacing: '0.3em',
						opacity: 0.55,
						marginBottom: '0.75rem',
						textTransform: 'uppercase',
					} }
				>
					{ kicker }
				</span>
			) : null }

			{ title ? (
				<h2
					style={ {
						fontSize: '2rem',
						fontWeight: 400,
						margin: '0 0 2rem',
					} }
				>
					{ title }
				</h2>
			) : null }

			<div style={ { display: 'flex', gap: '1rem', overflow: 'hidden' } }>
				{ Array.from( { length: safeColumns } ).map( ( _, index ) => (
					<div
						key={ `preview-${ index }` }
						style={ {
							flex: '0 0 31%',
							aspectRatio: '4/3',
							background: '#333',
							borderRadius: '4px',
							position: 'relative',
						} }
					>
						<div
							style={ {
								position: 'absolute',
								bottom: 0,
								left: 0,
								right: 0,
								padding: '1.2rem',
								background:
									'linear-gradient(transparent,rgba(0,0,0,.8))',
							} }
						>
							<p
								style={ {
									color: '#fff',
									margin: 0,
									fontSize: '0.9rem',
								} }
							>
								Preview { index + 1 }
							</p>
						</div>
					</div>
				) ) }
			</div>
		</div>
	);
}
