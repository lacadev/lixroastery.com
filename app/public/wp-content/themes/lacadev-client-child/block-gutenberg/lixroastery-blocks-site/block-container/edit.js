import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { ResponsiveRangeControl } from '../../utils/inspector-panels';

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

// Map device → tên attribute maxWidth tương ứng — tránh ternary lồng nhau
// trong onChange (eslint no-nested-ternary).
const MAX_WIDTH_KEYS = {
	pc: 'maxWidth',
	tablet: 'maxWidthTablet',
	mobile: 'maxWidthMobile',
};

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );

	const { maxWidth, maxWidthTablet, maxWidthMobile, contentAlign } =
		attributes;

	const blockProps = useBlockProps( {
		className: 'block-container',
		style: {
			'--mw-pc': `${ maxWidth }%`,
			'--mw-tablet': `${ maxWidthTablet }%`,
			'--mw-mobile': `${ maxWidthMobile }%`,
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
				<PanelBody
					title={ __( 'Bố cục', 'laca' ) }
					initialOpen={ true }
				>
					<ResponsiveRangeControl
						label={ __( 'Kích thước tối đa (%)', 'laca' ) }
						help={ __(
							'Mobile mặc định full width — chỉnh riêng nếu muốn khác.',
							'laca'
						) }
						min={ 10 }
						max={ 100 }
						valuesByDevice={ {
							pc: maxWidth,
							tablet: maxWidthTablet,
							mobile: maxWidthMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ MAX_WIDTH_KEYS[ device ] ]: v,
							} )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Căn lề', 'laca' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Vị trí khung', 'laca' ) }
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
