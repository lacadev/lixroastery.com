import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';

const ALIGN_OPTIONS = [
	{ label: __( 'Trái', 'laca' ), value: 'left' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Phải', 'laca' ), value: 'right' },
	{ label: __( 'Căn đều', 'laca' ), value: 'justify' },
];

const ALIGN_LABELS = {
	left: __( 'Trái', 'laca' ),
	center: __( 'Giữa', 'laca' ),
	right: __( 'Phải', 'laca' ),
	justify: __( 'Căn đều', 'laca' ),
};

// Vị trí của khung (đã giới hạn maxWidth) trong hàng — khớp đúng cách tính ở
// render.php.
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
	justify: '0 auto',
};

// Map device → tên attribute tương ứng — tránh ternary lồng nhau
// trong onChange (eslint no-nested-ternary).
const MAX_WIDTH_KEYS = {
	pc: 'maxWidth',
	tablet: 'maxWidthTablet',
	mobile: 'maxWidthMobile',
};

const CONTENT_ALIGN_KEYS = {
	pc: 'contentAlign',
	tablet: 'contentAlignTablet',
	mobile: 'contentAlignMobile',
};

const HEADING_FONT_SIZE_KEYS = {
	pc: 'headingFontSize',
	tablet: 'headingFontSizeTablet',
	mobile: 'headingFontSizeMobile',
};

const FONT_SIZE_KEYS = {
	pc: 'fontSize',
	tablet: 'fontSizeTablet',
	mobile: 'fontSizeMobile',
};

const LINE_HEIGHT_KEYS = {
	pc: 'lineHeight',
	tablet: 'lineHeightTablet',
	mobile: 'lineHeightMobile',
};

const BLOCK_SPACING_KEYS = {
	pc: 'blockSpacing',
	tablet: 'blockSpacingTablet',
	mobile: 'blockSpacingMobile',
};

export default function Edit( { attributes, setAttributes, clientId } ) {
	const isPreview = useInserterPreview( attributes );
	const { selectBlock } = useDispatch( 'core/block-editor' );

	const {
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		headingFontSize = 34,
		headingFontSizeTablet = 26,
		headingFontSizeMobile = 18,
		fontSize = 16,
		fontSizeTablet = 15,
		fontSizeMobile = 14,
		lineHeight = 1.7,
		lineHeightTablet = 1.7,
		lineHeightMobile = 1.6,
		blockSpacing = 20,
		blockSpacingTablet = 16,
		blockSpacingMobile = 14,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'block-container block-container--editor',
		style: {
			'--mw-pc': `${ maxWidth }%`,
			'--mw-tablet': `${ maxWidthTablet }%`,
			'--mw-mobile': `${ maxWidthMobile }%`,
			'--align-margin-pc':
				MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
			'--align-margin-tablet':
				MARGIN_MAP[ contentAlignTablet ] || MARGIN_MAP.center,
			'--align-margin-mobile':
				MARGIN_MAP[ contentAlignMobile ] || MARGIN_MAP.center,
			'--text-align-pc':
				contentAlign === 'justify' ? 'justify' : 'inherit',
			'--text-align-tablet':
				contentAlignTablet === 'justify' ? 'justify' : 'inherit',
			'--text-align-mobile':
				contentAlignMobile === 'justify' ? 'justify' : 'inherit',
			'--h-fs-pc': `${ headingFontSize }px`,
			'--h-fs-tablet': `${ headingFontSizeTablet }px`,
			'--h-fs-mobile': `${ headingFontSizeMobile }px`,
			'--fs-pc': `${ fontSize }px`,
			'--fs-tablet': `${ fontSizeTablet }px`,
			'--fs-mobile': `${ fontSizeMobile }px`,
			'--lh-pc': `${ lineHeight }`,
			'--lh-tablet': `${ lineHeightTablet }`,
			'--lh-mobile': `${ lineHeightMobile }`,
			'--spacing-pc': `${ blockSpacing }px`,
			'--spacing-tablet': `${ blockSpacingTablet }px`,
			'--spacing-mobile': `${ blockSpacingMobile }px`,
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
					<ResponsiveSelectControl
						label={ __( 'Vị trí khung', 'laca' ) }
						options={ ALIGN_OPTIONS }
						valuesByDevice={ {
							pc: contentAlign,
							tablet: contentAlignTablet,
							mobile: contentAlignMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ CONTENT_ALIGN_KEYS[ device ] ]: v,
							} )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Typography & Khoảng cách', 'laca' ) }
					initialOpen={ true }
				>
					<ResponsiveRangeControl
						label={ __( 'Cỡ chữ tiêu đề (px)', 'laca' ) }
						help={ __(
							'Mặc định: PC 34px, Tablet 26px, Mobile 18px.',
							'laca'
						) }
						min={ 14 }
						max={ 80 }
						step={ 1 }
						valuesByDevice={ {
							pc: headingFontSize,
							tablet: headingFontSizeTablet,
							mobile: headingFontSizeMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ HEADING_FONT_SIZE_KEYS[ device ] ]: v,
							} )
						}
					/>
					<ResponsiveRangeControl
						label={ __( 'Cỡ chữ nội dung (px)', 'laca' ) }
						help={ __(
							'Mặc định: PC 16px, Tablet 15px, Mobile 14px.',
							'laca'
						) }
						min={ 12 }
						max={ 60 }
						step={ 1 }
						valuesByDevice={ {
							pc: fontSize,
							tablet: fontSizeTablet,
							mobile: fontSizeMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ FONT_SIZE_KEYS[ device ] ]: v,
							} )
						}
					/>
					<ResponsiveRangeControl
						label={ __( 'Chiều cao dòng (line-height)', 'laca' ) }
						min={ 1 }
						max={ 3 }
						step={ 0.1 }
						valuesByDevice={ {
							pc: lineHeight,
							tablet: lineHeightTablet,
							mobile: lineHeightMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ LINE_HEIGHT_KEYS[ device ] ]: v,
							} )
						}
					/>
					<ResponsiveRangeControl
						label={ __( 'Khoảng cách giữa các khối (px)', 'laca' ) }
						help={ __(
							'Khoảng cách dọc giữa các block con bên trong khung.',
							'laca'
						) }
						min={ 0 }
						max={ 80 }
						step={ 2 }
						valuesByDevice={ {
							pc: blockSpacing,
							tablet: blockSpacingTablet,
							mobile: blockSpacingMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ BLOCK_SPACING_KEYS[ device ] ]: v,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div
					className="block-container__editor-bar"
					onClick={ ( e ) => {
						e.stopPropagation();
						selectBlock( clientId );
					} }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' || e.key === ' ' ) {
							e.preventDefault();
							selectBlock( clientId );
						}
					} }
					role="button"
					tabIndex={ 0 }
					title={ __(
						'Nhấp để chọn Khung nội dung (Container)',
						'laca'
					) }
				>
					<span className="block-container__editor-tag">
						<svg
							width="12"
							height="12"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2.5"
							strokeLinecap="round"
							strokeLinejoin="round"
							style={ { marginRight: '6px' } }
						>
							<rect
								x="3"
								y="3"
								width="18"
								height="18"
								rx="2"
								ry="2"
							/>
							<line x1="9" y1="3" x2="9" y2="21" />
						</svg>
						{ __( 'Khung nội dung (Container)', 'laca' ) }
					</span>
					<span className="block-container__editor-info">
						{ maxWidth }% •{ ' ' }
						{ ALIGN_LABELS[ contentAlign ] || contentAlign }
					</span>
				</div>
				<InnerBlocks />
			</div>
		</>
	);
}
