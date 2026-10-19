import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	ColorPicker,
	RangeControl,
	RadioControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { hexToRgba } from '../../utils/style';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';
import previewImage from './preview.png';

const TIERS = { pc: '', tablet: 'Tablet', mobile: 'Mobile' };

// Default giống hệt block.json/render.php — giữ đúng giao diện cũ.
const VERTICAL_DEFAULTS = { pc: 'bottom', tablet: 'center', mobile: 'center' };
const OFFSET_Y_DEFAULTS = { pc: 20, tablet: 10, mobile: 10 };

const VERTICAL_OPTIONS = [
	{ label: __( 'Trên', 'laca' ), value: 'top' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Dưới', 'laca' ), value: 'bottom' },
];

const HORIZONTAL_OPTIONS = [
	{ label: __( 'Trái', 'laca' ), value: 'left' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Phải', 'laca' ), value: 'right' },
];

const TEXT_ALIGN_OPTIONS = [
	{ label: __( 'Trái', 'laca' ), value: 'left' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Phải', 'laca' ), value: 'right' },
	{ label: __( 'Đều 2 bên', 'laca' ), value: 'justify' },
];

const DIRECTION_OPTIONS = [
	{ label: __( 'Trên → dưới', 'laca' ), value: '180' },
	{ label: __( 'Dưới → trên', 'laca' ), value: '0' },
	{ label: __( 'Trái → phải', 'laca' ), value: '90' },
	{ label: __( 'Phải → trái', 'laca' ), value: '270' },
];

// { pc: attributes.foo, tablet: attributes.fooTablet, mobile: attributes.fooMobile }
function byDevice( attributes, key ) {
	return Object.fromEntries(
		Object.entries( TIERS ).map( ( [ tier, suffix ] ) => [
			tier,
			attributes[ key + suffix ],
		] )
	);
}

function ColorField( { label, color, onChange } ) {
	return (
		<>
			<p
				style={ {
					fontSize: '0.8rem',
					fontWeight: 600,
					marginBottom: '0.5rem',
				} }
			>
				{ label }
			</p>
			<ColorPicker
				color={ color }
				onChange={ onChange }
				enableAlpha={ false }
			/>
		</>
	);
}

function ImagePicker( { imageUrl, imageId, onSelect, label } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				onSelect={ onSelect }
				allowedTypes={ [ 'image' ] }
				value={ imageId }
				render={ ( { open } ) => (
					<div style={ { marginBottom: 8 } }>
						{ label && (
							<p
								style={ {
									fontSize: 11,
									color: '#888',
									marginBottom: 4,
								} }
							>
								{ label }
							</p>
						) }
						{ imageUrl && (
							<img
								src={ imageUrl }
								alt=""
								style={ {
									width: '100%',
									maxHeight: 120,
									objectFit: 'cover',
									marginBottom: 4,
									borderRadius: 4,
								} }
							/>
						) }
						<Button
							variant="secondary"
							onClick={ open }
							style={ { fontSize: 11 } }
						>
							{ imageUrl
								? __( 'Đổi ảnh', 'laca' )
								: __( 'Chọn ảnh', 'laca' ) }
						</Button>
					</div>
				) }
			/>
		</MediaUploadCheck>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Top Hero', 'laca' ) }
				title={ __( 'Hero banner', 'laca' ) }
				columns={ 1 }
				image={ previewImage }
			/>
		);
	}

	const {
		heroImageId,
		heroImageUrl,
		headline,
		description,
		overlayType,
		overlayColor,
		overlayOpacity,
		overlayColor2,
		overlayOpacity2,
		overlayDirection,
		heightMode,
		minHeight,
		fullBleed,
	} = attributes;

	const setByDevice = ( key ) => ( device, value ) =>
		setAttributes( { [ key + TIERS[ device ] ]: value } );

	let sectionStyle;
	if ( heightMode === 'custom' ) {
		sectionStyle = { minHeight: `${ minHeight }px` };
	} else if ( ! heroImageUrl ) {
		sectionStyle = { minHeight: '400px' };
	}

	// Class + biến CSS y hệt render.php để canvas giống frontend.
	const classNames = [
		blockProps.className,
		`block-top-hero--${ heightMode }`,
		fullBleed ? 'block-top-hero--bleed' : '',
	];
	const contentStyle = {};
	Object.entries( TIERS ).forEach( ( [ tier, suffix ] ) => {
		classNames.push(
			`block-top-hero--v-${ tier }-${
				attributes[ 'contentVertical' + suffix ] ||
				VERTICAL_DEFAULTS[ tier ]
			}`,
			`block-top-hero--h-${ tier }-${
				attributes[ 'contentHorizontal' + suffix ] || 'center'
			}`
		);
		contentStyle[ `--oy-${ tier }` ] = `${
			attributes[ 'offsetY' + suffix ] ?? OFFSET_Y_DEFAULTS[ tier ]
		}%`;
		contentStyle[ `--ox-${ tier }` ] = `${
			attributes[ 'offsetX' + suffix ] ?? 5
		}%`;
		contentStyle[ `--ta-${ tier }` ] =
			attributes[ 'textAlign' + suffix ] || 'center';
		if ( attributes[ 'textWidth' + suffix ] !== undefined ) {
			contentStyle[ `--tw-${ tier }` ] = `${
				attributes[ 'textWidth' + suffix ]
			}%`;
		}
	} );

	const overlayFrom = hexToRgba( overlayColor, overlayOpacity );
	const overlayTo =
		overlayType === 'gradient'
			? hexToRgba( overlayColor2, overlayOpacity2 )
			: overlayFrom;
	const overlayAngle =
		overlayType === 'gradient' ? overlayDirection || '180' : '180';

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Bố cục', 'laca' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __( 'Tràn viền 2 bên màn hình', 'laca' ) }
						help={ __(
							'Ảnh sát 2 mép màn hình dù block nằm trong khung container. Chỉ thấy rõ ngoài trang thật.',
							'laca'
						) }
						checked={ !! fullBleed }
						onChange={ ( v ) => setAttributes( { fullBleed: v } ) }
					/>
					<RadioControl
						label={ __( 'Chiều cao ảnh', 'laca' ) }
						selected={ heightMode }
						options={ [
							{
								label: __(
									'Nguyên kích thước (100%, tự động)',
									'laca'
								),
								value: 'full',
							},
							{
								label: __( 'Cố định theo px', 'laca' ),
								value: 'custom',
							},
						] }
						onChange={ ( v ) => setAttributes( { heightMode: v } ) }
					/>
					{ heightMode === 'custom' && (
						<RangeControl
							label={ __( 'Chiều cao tối thiểu (px)', 'laca' ) }
							value={ minHeight }
							min={ 360 }
							max={ 1200 }
							step={ 10 }
							onChange={ ( v ) =>
								setAttributes( { minHeight: v } )
							}
						/>
					) }
					<ResponsiveRangeControl
						label={ __( 'Độ rộng khung chữ (%)', 'laca' ) }
						help={ __(
							'Chưa chỉnh thì dùng mặc định: 60% (màn lớn), 80% (laptop), 90% (tablet/mobile).',
							'laca'
						) }
						min={ 20 }
						max={ 100 }
						valuesByDevice={ byDevice( attributes, 'textWidth' ) }
						onChange={ setByDevice( 'textWidth' ) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Căn lề', 'laca' ) }
					initialOpen={ true }
				>
					<ResponsiveSelectControl
						label={ __( 'Vị trí chữ theo chiều dọc', 'laca' ) }
						options={ VERTICAL_OPTIONS }
						valuesByDevice={ byDevice(
							attributes,
							'contentVertical'
						) }
						onChange={ setByDevice( 'contentVertical' ) }
					/>
					<ResponsiveSelectControl
						label={ __( 'Vị trí chữ theo chiều ngang', 'laca' ) }
						options={ HORIZONTAL_OPTIONS }
						valuesByDevice={ byDevice(
							attributes,
							'contentHorizontal'
						) }
						onChange={ setByDevice( 'contentHorizontal' ) }
					/>
					<ResponsiveRangeControl
						label={ __(
							'Khoảng cách chữ tới mép trên/dưới (%)',
							'laca'
						) }
						help={ __(
							'Tính theo chiều cao ảnh. Không áp dụng khi chữ đặt "Giữa" theo chiều dọc.',
							'laca'
						) }
						min={ 0 }
						max={ 45 }
						valuesByDevice={ byDevice( attributes, 'offsetY' ) }
						onChange={ setByDevice( 'offsetY' ) }
					/>
					<ResponsiveRangeControl
						label={ __(
							'Khoảng cách chữ tới mép trái/phải (%)',
							'laca'
						) }
						help={ __(
							'Tính theo chiều rộng ảnh. Không áp dụng khi chữ đặt "Giữa" theo chiều ngang.',
							'laca'
						) }
						min={ 0 }
						max={ 45 }
						valuesByDevice={ byDevice( attributes, 'offsetX' ) }
						onChange={ setByDevice( 'offsetX' ) }
					/>
					<ResponsiveSelectControl
						label={ __( 'Căn chữ', 'laca' ) }
						options={ TEXT_ALIGN_OPTIONS }
						valuesByDevice={ byDevice( attributes, 'textAlign' ) }
						onChange={ setByDevice( 'textAlign' ) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Ảnh nền', 'laca' ) }
					initialOpen={ true }
				>
					<ImagePicker
						imageUrl={ heroImageUrl }
						imageId={ heroImageId }
						onSelect={ ( media ) =>
							setAttributes( {
								heroImageId: media.id,
								heroImageUrl: media.url,
							} )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Kiểu lớp phủ (overlay)', 'laca' ) }
						value={ overlayType }
						options={ [
							{ label: __( 'Một màu', 'laca' ), value: 'solid' },
							{
								label: __( 'Chuyển màu (gradient)', 'laca' ),
								value: 'gradient',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { overlayType: v } )
						}
					/>
					<ColorField
						label={
							overlayType === 'gradient'
								? __( 'Màu bắt đầu', 'laca' )
								: __( 'Màu lớp phủ', 'laca' )
						}
						color={ overlayColor }
						onChange={ ( v ) =>
							setAttributes( { overlayColor: v } )
						}
					/>
					<RangeControl
						label={
							overlayType === 'gradient'
								? __( 'Độ đậm màu bắt đầu (%)', 'laca' )
								: __( 'Độ đậm lớp phủ (%)', 'laca' )
						}
						value={ overlayOpacity }
						min={ 0 }
						max={ 90 }
						step={ 5 }
						onChange={ ( v ) =>
							setAttributes( { overlayOpacity: v } )
						}
					/>
					{ overlayType === 'gradient' && (
						<>
							<ColorField
								label={ __( 'Màu kết thúc', 'laca' ) }
								color={ overlayColor2 }
								onChange={ ( v ) =>
									setAttributes( { overlayColor2: v } )
								}
							/>
							<RangeControl
								label={ __(
									'Độ đậm màu kết thúc (%)',
									'laca'
								) }
								value={ overlayOpacity2 }
								min={ 0 }
								max={ 90 }
								step={ 5 }
								onChange={ ( v ) =>
									setAttributes( { overlayOpacity2: v } )
								}
							/>
							<SelectControl
								label={ __( 'Hướng chuyển màu', 'laca' ) }
								value={ overlayDirection }
								options={ DIRECTION_OPTIONS }
								onChange={ ( v ) =>
									setAttributes( { overlayDirection: v } )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<section
				{ ...blockProps }
				className={ classNames.filter( Boolean ).join( ' ' ) }
				style={ { ...blockProps.style, ...sectionStyle } }
			>
				{ heroImageUrl && (
					<img
						src={ heroImageUrl }
						alt=""
						className="block-top-hero__bg"
					/>
				) }

				<div
					className="block-top-hero__overlay"
					style={ {
						background: `linear-gradient(${ overlayAngle }deg, ${ overlayFrom } 0%, ${ overlayTo } 100%)`,
					} }
				/>

				<div className="block-top-hero__content" style={ contentStyle }>
					<RichText
						tagName="h1"
						className="block-top-hero__headline"
						value={ headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="div"
						className="block-top-hero__desc"
						value={ description }
						onChange={ ( v ) =>
							setAttributes( { description: v } )
						}
						placeholder={ __( 'Nhập mô tả ngắn…', 'laca' ) }
					/>
				</div>
			</section>
		</>
	);
}
