import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

const ALLOWED_BLOCKS = [ 'lacadev/tab-panel-block' ];

// Không đặt sẵn heading — nội dung tab thường dùng block đã có tiêu đề
// riêng (CTA Section, Partnership Grid…), thêm heading riêng sẽ bị trùng.
const TEMPLATE = [
	[
		'lacadev/tab-panel-block',
		{ tabTitle: 'THE ORIGIN' },
		[ [ 'core/paragraph', {} ] ],
	],
	[
		'lacadev/tab-panel-block',
		{ tabTitle: 'THE LAB' },
		[ [ 'core/paragraph', {} ] ],
	],
	[
		'lacadev/tab-panel-block',
		{ tabTitle: 'THE ROASTERY' },
		[ [ 'core/paragraph', {} ] ],
	],
];

export default function Edit( { attributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps( { className: 'tabs-block' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'tabs-block__editor-panels' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
			orientation: 'horizontal',
		}
	);

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Tabs', 'laca' ) }
				title={ __( 'The Origin | The Lab | The Roastery', 'laca' ) }
				columns={ 1 }
				image={ previewImage }
			/>
		);
	}

	return (
		<div { ...blockProps }>
			<p className="tabs-block__editor-note">
				{ __(
					'Mỗi khối bên dưới là 1 tab — đặt tên tab và thêm nội dung (có thể chèn block khác như CTA Section, Image Card Grid…). Khi xem ngoài trang, khách sẽ thấy thanh chuyển tab + nút Prev/Next.',
					'laca'
				) }
			</p>
			<div { ...innerBlocksProps } />
		</div>
	);
}
