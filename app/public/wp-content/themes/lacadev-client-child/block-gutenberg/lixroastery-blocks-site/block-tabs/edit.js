import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

const ALLOWED_BLOCKS = [ 'lacadev/tab-panel-block' ];

// Section ngoài LUÔN container-fluid — độ rộng khối tabs điều chỉnh riêng qua
// maxWidth (%) + contentAlign (giống hệt block Container).
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
};

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

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const { maxWidth, contentAlign } = attributes;
	const maxWidthStyle = {
		maxWidth: `${ maxWidth }%`,
		margin: MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
	};
	// Không gộp class "container-fluid" vào cùng div này — ".tabs-block" đã có
	// padding shorthand (padding: 3rem 0) trong style.scss, gộp chung sẽ zero-out
	// padding-left/right của container-fluid (đúng cảnh báo ở skill lacadev-theme
	// mục 2.3). Ngoài trang thật, "container-fluid" nằm ở div con
	// ".tabs-block__inner" riêng (xem render.php) nên không bị lỗi này.
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
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Bố cục', 'laca' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Kích thước tối đa (%)', 'laca' ) }
						help={ __(
							'Áp dụng cho màn hình lớn — tự động full width trên mobile để không quá hẹp.',
							'laca'
						) }
						value={ maxWidth }
						min={ 10 }
						max={ 100 }
						onChange={ ( v ) => setAttributes( { maxWidth: v } ) }
					/>
					<SelectControl
						label={ __( 'Căn lề khung', 'laca' ) }
						value={ contentAlign }
						options={ [
							{ label: __( 'Trái', 'laca' ), value: 'left' },
							{ label: __( 'Giữa', 'laca' ), value: 'center' },
							{ label: __( 'Phải', 'laca' ), value: 'right' },
						] }
						onChange={ ( v ) =>
							setAttributes( { contentAlign: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="tabs-block__maxwidth" style={ maxWidthStyle }>
					<p className="tabs-block__editor-note">
						{ __(
							'Mỗi khối bên dưới là 1 tab — đặt tên tab và thêm nội dung (có thể chèn block khác như CTA Section, Image Card Grid…). Khi xem ngoài trang, khách sẽ thấy thanh chuyển tab + nút Prev/Next.',
							'laca'
						) }
					</p>
					<div { ...innerBlocksProps } />
				</div>
			</div>
		</>
	);
}
