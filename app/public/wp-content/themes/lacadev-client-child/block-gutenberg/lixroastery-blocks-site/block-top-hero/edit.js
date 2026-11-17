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
	ContainerModeControl,
	getBlockLayoutClass,
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

const MEDIA_TYPE_OPTIONS = [
	{ label: __( 'Một ảnh', 'laca' ), value: 'image' },
	{ label: __( 'Slide nhiều ảnh', 'laca' ), value: 'slider' },
	{ label: __( 'Video', 'laca' ), value: 'video' },
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

function SlidesPicker( { slides, onChange } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				multiple
				gallery
				allowedTypes={ [ 'image' ] }
				value={ slides.map( ( slide ) => slide.id ) }
				onSelect={ ( media ) =>
					onChange(
						media.map( ( item ) => ( {
							id: item.id,
							url: item.url,
						} ) )
					)
				}
				render={ ( { open } ) => (
					<div style={ { marginBottom: 12 } }>
						{ slides.length > 0 && (
							<div
								style={ {
									display: 'grid',
									gridTemplateColumns: 'repeat(3, 1fr)',
									gap: 4,
									marginBottom: 8,
								} }
							>
								{ slides.map( ( slide, index ) => (
									<div
										key={ slide.id || slide.url }
										style={ { position: 'relative' } }
									>
										<img
											src={ slide.url }
											alt=""
											style={ {
												width: '100%',
												aspectRatio: '1',
												objectFit: 'cover',
												borderRadius: 4,
												display: 'block',
											} }
										/>
										<Button
											icon="no-alt"
											size="small"
											label={ __( 'Bỏ ảnh này', 'laca' ) }
											onClick={ () =>
												onChange(
													slides.filter(
														( _, i ) => i !== index
													)
												)
											}
											style={ {
												position: 'absolute',
												top: 2,
												right: 2,
												background: '#fff',
												minWidth: 0,
												padding: 0,
												height: 20,
												width: 20,
											} }
										/>
									</div>
								) ) }
							</div>
						) }
						<Button variant="secondary" onClick={ open }>
							{ slides.length
								? __( 'Sửa / sắp xếp ảnh', 'laca' )
								: __( 'Chọn nhiều ảnh', 'laca' ) }
						</Button>
					</div>
				) }
			/>
		</MediaUploadCheck>
	);
}

function VideoPicker( { videoId, videoUrl, onSelect, onRemove } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				allowedTypes={ [ 'video' ] }
				value={ videoId }
				onSelect={ onSelect }
				render={ ( { open } ) => (
					<div style={ { marginBottom: 12 } }>
						{ videoUrl && (
							<video
								src={ videoUrl }
								muted
								style={ {
									width: '100%',
									maxHeight: 120,
									objectFit: 'cover',
									marginBottom: 4,
									borderRadius: 4,
									display: 'block',
								} }
							/>
						) }
						<Button variant="secondary" onClick={ open }>
							{ videoUrl
								? __( 'Đổi video', 'laca' )
								: __( 'Chọn video', 'laca' ) }
						</Button>
						{ videoUrl && (
							<Button
								variant="link"
								isDestructive
								onClick={ onRemove }
								style={ { marginLeft: 8 } }
							>
								{ __( 'Gỡ video', 'laca' ) }
							</Button>
						) }
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
		containerMode = 'inherit',
		heroImageId,
		heroImageUrl,
		mediaType = 'image',
		slides = [],
		slideInterval,
		slideAutoplay,
		slideShowDots,
		slideShowArrows,
		videoId,
		videoUrl,
		videoLoop,
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
		parallax,
		parallaxStrength,
		zoomIn,
		textReveal,
		scrollDarken,
		scrollDarkenMax,
	} = attributes;

	const setByDevice = ( key ) => ( device, value ) =>
		setAttributes( { [ key + TIERS[ device ] ]: value } );

	// Giống render.php: thiếu dữ liệu cho chế độ đã chọn thì rơi về 1 ảnh.
	const isSlider = mediaType === 'slider' && slides.length > 1;
	const isVideo = mediaType === 'video' && !! videoUrl;
	let bgImageUrl = heroImageUrl;
	if ( mediaType === 'slider' && slides.length ) {
		bgImageUrl = slides[ 0 ].url;
	}
	let activeMedia = 'image';
	if ( isSlider ) {
		activeMedia = 'slider';
	} else if ( isVideo ) {
		activeMedia = 'video';
	}

	let sectionStyle;
	if ( heightMode === 'custom' ) {
		sectionStyle = { minHeight: `${ minHeight }px` };
	} else if ( ! bgImageUrl && ! isVideo ) {
		sectionStyle = { minHeight: '400px' };
	}

	// Class + biến CSS y hệt render.php để canvas giống frontend.
	const layoutClass = getBlockLayoutClass( containerMode );
	const classNames = [
		blockProps.className,
		`block-top-hero--${ heightMode }`,
		fullBleed ? 'block-top-hero--bleed' : '',
		`block-top-hero--media-${ activeMedia }`,
		layoutClass,
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
					<ContainerModeControl
						value={ containerMode }
						onChange={ ( v ) =>
							setAttributes( { containerMode: v } )
						}
					/>
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

				<PanelBody title={ __( 'Nền', 'laca' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Loại nền', 'laca' ) }
						value={ mediaType }
						options={ MEDIA_TYPE_OPTIONS }
						onChange={ ( v ) => setAttributes( { mediaType: v } ) }
					/>

					{ mediaType === 'image' && (
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
					) }

					{ mediaType === 'slider' && (
						<>
							<SlidesPicker
								slides={ slides }
								onChange={ ( v ) =>
									setAttributes( { slides: v } )
								}
							/>
							{ slides.length < 2 && (
								<p
									style={ {
										fontSize: 12,
										color: '#757575',
										marginTop: 0,
									} }
								>
									{ __(
										'Cần ít nhất 2 ảnh để chạy slide. Kéo thả trong cửa sổ chọn ảnh để đổi thứ tự.',
										'laca'
									) }
								</p>
							) }
							<ToggleControl
								label={ __( 'Tự chuyển ảnh', 'laca' ) }
								help={ __(
									'Tạm dừng khi rê chuột vào hero; không tự chạy nếu máy người xem bật "giảm chuyển động".',
									'laca'
								) }
								checked={ !! slideAutoplay }
								onChange={ ( v ) =>
									setAttributes( { slideAutoplay: v } )
								}
							/>
							{ slideAutoplay && (
								<RangeControl
									label={ __(
										'Thời gian mỗi ảnh (giây)',
										'laca'
									) }
									value={ slideInterval }
									min={ 2 }
									max={ 20 }
									step={ 1 }
									onChange={ ( v ) =>
										setAttributes( { slideInterval: v } )
									}
								/>
							) }
							<ToggleControl
								label={ __( 'Hiện chấm chuyển ảnh', 'laca' ) }
								checked={ !! slideShowDots }
								onChange={ ( v ) =>
									setAttributes( { slideShowDots: v } )
								}
							/>
							<ToggleControl
								label={ __( 'Hiện mũi tên trái/phải', 'laca' ) }
								checked={ !! slideShowArrows }
								onChange={ ( v ) =>
									setAttributes( { slideShowArrows: v } )
								}
							/>
						</>
					) }

					{ mediaType === 'video' && (
						<>
							<VideoPicker
								videoId={ videoId }
								videoUrl={ videoUrl }
								onSelect={ ( media ) =>
									setAttributes( {
										videoId: media.id,
										videoUrl: media.url,
									} )
								}
								onRemove={ () =>
									setAttributes( {
										videoId: 0,
										videoUrl: '',
									} )
								}
							/>
							<p
								style={ {
									fontSize: 12,
									color: '#757575',
									marginTop: 0,
								} }
							>
								{ __(
									'Video tự phát, tắt tiếng. Nên dùng MP4 dưới ~10MB, 1920px. Ngoài trang có nút dừng video.',
									'laca'
								) }
							</p>
							<ToggleControl
								label={ __( 'Phát lặp lại', 'laca' ) }
								checked={ !! videoLoop }
								onChange={ ( v ) =>
									setAttributes( { videoLoop: v } )
								}
							/>
							<ImagePicker
								label={ __(
									'Ảnh chờ (hiện khi video đang tải hoặc máy chặn tự phát)',
									'laca'
								) }
								imageUrl={ heroImageUrl }
								imageId={ heroImageId }
								onSelect={ ( media ) =>
									setAttributes( {
										heroImageId: media.id,
										heroImageUrl: media.url,
									} )
								}
							/>
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Hiệu ứng', 'laca' ) }
					initialOpen={ false }
				>
					<p
						style={ {
							fontSize: '12px',
							color: '#757575',
							marginTop: 0,
						} }
					>
						{ __(
							'Bật được nhiều hiệu ứng cùng lúc. Chỉ chạy ngoài trang thật (editor đứng yên); tự tắt chuyển động nếu máy người xem bật "giảm chuyển động".',
							'laca'
						) }
					</p>
					<ToggleControl
						label={ __( 'Ảnh zoom chậm khi tải', 'laca' ) }
						help={ __(
							'Ảnh hơi phóng to rồi thu dần về kích thước thật (~2,4 giây).',
							'laca'
						) }
						checked={ !! zoomIn }
						onChange={ ( v ) => setAttributes( { zoomIn: v } ) }
					/>
					<ToggleControl
						label={ __( 'Chữ hiện lần lượt', 'laca' ) }
						help={ __(
							'Tiêu đề rồi mô tả trượt nhẹ từ dưới lên. Hero nằm dưới trang thì chạy khi cuộn tới.',
							'laca'
						) }
						checked={ !! textReveal }
						onChange={ ( v ) => setAttributes( { textReveal: v } ) }
					/>
					<ToggleControl
						label={ __( 'Lớp phủ đậm dần khi cuộn', 'laca' ) }
						help={ __(
							'Cuộn qua hero thì ảnh tối dần, chuyển êm sang nội dung bên dưới.',
							'laca'
						) }
						checked={ !! scrollDarken }
						onChange={ ( v ) =>
							setAttributes( { scrollDarken: v } )
						}
					/>
					{ scrollDarken && (
						<RangeControl
							label={ __( 'Độ đậm tối đa (%)', 'laca' ) }
							value={ scrollDarkenMax }
							min={ 10 }
							max={ 90 }
							step={ 5 }
							onChange={ ( v ) =>
								setAttributes( { scrollDarkenMax: v } )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Parallax khi cuộn', 'laca' ) }
						help={ __(
							'Ảnh trôi chậm hơn trang khi cuộn.',
							'laca'
						) }
						checked={ !! parallax }
						onChange={ ( v ) => setAttributes( { parallax: v } ) }
					/>
					{ parallax && (
						<RangeControl
							label={ __( 'Độ mạnh (%)', 'laca' ) }
							help={ __(
								'Càng cao ảnh trôi càng nhiều (ảnh được phóng to tương ứng để không lộ mép).',
								'laca'
							) }
							value={ parallaxStrength }
							min={ 10 }
							max={ 50 }
							step={ 5 }
							onChange={ ( v ) =>
								setAttributes( { parallaxStrength: v } )
							}
						/>
					) }
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
				{ isVideo && (
					<video
						key={ videoUrl }
						className="block-top-hero__bg block-top-hero__video"
						src={ videoUrl }
						poster={ heroImageUrl || undefined }
						autoPlay
						muted
						loop
						playsInline
					/>
				) }
				{ ! isVideo && bgImageUrl && (
					<img
						src={ bgImageUrl }
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

				{ /* Editor chỉ hiện ảnh đầu; chấm/mũi tên tĩnh để thấy bố cục. */ }
				{ isSlider && slideShowArrows && (
					<>
						<span
							className="block-top-hero__arrow block-top-hero__arrow--prev"
							aria-hidden="true"
						>
							&#8249;
						</span>
						<span
							className="block-top-hero__arrow block-top-hero__arrow--next"
							aria-hidden="true"
						>
							&#8250;
						</span>
					</>
				) }
				{ isSlider && slideShowDots && (
					<div className="block-top-hero__dots" aria-hidden="true">
						{ slides.map( ( slide, index ) => (
							<span
								key={ slide.id || slide.url }
								className={ `block-top-hero__dot${
									index === 0 ? ' is-active' : ''
								}` }
							/>
						) ) }
					</div>
				) }
			</section>
		</>
	);
}
