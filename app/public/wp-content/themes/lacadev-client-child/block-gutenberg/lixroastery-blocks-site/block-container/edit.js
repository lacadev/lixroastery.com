import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';

const ALIGN_OPTIONS = [
	{ label: __( 'Trái', 'laca' ), value: 'left' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Phải', 'laca' ), value: 'right' },
];

// Vị trí của khung (đã giới hạn maxWidth) trong hàng — khớp đúng cách tính ở
// render.php.
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
};

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );

	const { maxWidth, contentAlign } = attributes;

	const blockProps = useBlockProps( {
		className: 'block-container',
		style: {
			maxWidth: `${ maxWidth }%`,
			margin: MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Container', 'laca' ) }
				title={ __( 'Khung nội dung tùy chỉnh', 'laca' ) }
				columns={ 1 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bố cục', 'laca' ) } initialOpen={ true }>
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
						options={ ALIGN_OPTIONS }
						onChange={ ( v ) =>
							setAttributes( { contentAlign: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<InnerBlocks />
			</div>
		</>
	);
}
