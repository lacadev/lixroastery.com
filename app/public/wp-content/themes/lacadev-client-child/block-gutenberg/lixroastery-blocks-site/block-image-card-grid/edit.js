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
	TextControl,
	SelectControl,
	RangeControl,
	RadioControl,
	Button,
	ColorPicker,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';
import previewImage from './preview.png';

// Section ngoài LUÔN container-fluid — độ rộng lưới thẻ điều chỉnh riêng qua
// maxWidth (%) + contentAlign (giống hệt block Container).
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
};

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

const CARD_ALIGN_OPTIONS = [
	{ label: __( 'Căn giữa (mặc định)', 'laca' ), value: 'center' },
	{ label: __( 'Căn trái', 'laca' ), value: 'left' },
	{ label: __( 'Căn phải', 'laca' ), value: 'right' },
	{ label: __( 'Căn đều', 'laca' ), value: 'justify' },
];

function ImagePicker( { imageUrl, imageId, onSelect } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				onSelect={ onSelect }
				allowedTypes={ [ 'image' ] }
				value={ imageId }
				render={ ( { open } ) => (
					<div style={ { marginBottom: 8 } }>
						{ imageUrl && (
							<img
								src={ imageUrl }
								alt=""
								style={ {
									width: '100%',
									maxHeight: 100,
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

	const {
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		columns,
		layoutMode,
		aspectRatio,
		bgColor,
		cardTextAlign = 'center',
		items,
	} = attributes;
	const isCheckerboard = layoutMode === 'checkerboard';
	const maxWidthStyle = {
		'--mw-pc': `${ maxWidth }%`,
		'--mw-tablet': `${ maxWidthTablet }%`,
		'--mw-mobile': `${ maxWidthMobile }%`,
		'--align-margin-pc': MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
		'--align-margin-tablet':
			MARGIN_MAP[ contentAlignTablet ] || MARGIN_MAP.center,
		'--align-margin-mobile':
			MARGIN_MAP[ contentAlignMobile ] || MARGIN_MAP.center,
	};

	// Thu gọn/mở rộng từng thẻ trong panel Inspector — nhiều thẻ xếp dài rất
	// khó quản lý, mặc định mở (false = đang mở) để không đổi hành vi cũ khi
	// mới có 1-2 thẻ (cùng pattern đã dùng ở block-content-rows). Phải đặt
	// TRƯỚC early return isPreview bên dưới — hook không được gọi có điều
	// kiện (react-hooks/rules-of-hooks).
	const [ collapsedItems, setCollapsedItems ] = useState( {} );
	const toggleItemCollapse = ( index ) =>
		setCollapsedItems( ( prev ) => ( {
			...prev,
			[ index ]: ! prev[ index ],
		} ) );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Image Card Grid', 'laca' ) }
				title={ __( 'Lưới thẻ hình ảnh', 'laca' ) }
				columns={ columns || 3 }
				images={ ( items || [] ).map( ( item ) => item.imageUrl ) }
				image={ previewImage }
			/>
		);
	}

	const updateItem = ( index, field, value ) => {
		const next = [ ...items ];
		next[ index ] = { ...next[ index ], [ field ]: value };
		setAttributes( { items: next } );
	};

	const removeItem = ( index ) => {
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
	};

	const addItem = () => {
		setAttributes( {
			items: [
				...items,
				{
					imageId: 0,
					imageUrl: '',
					title: '',
					desc: '',
					link: '',
					linkTarget: '_self',
					textAlign: cardTextAlign || 'center',
				},
			],
		} );
	};

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
							setAttributes( { [ MAX_WIDTH_KEYS[ device ] ]: v } )
						}
					/>
					<RadioControl
						label={ __( 'Kiểu bố cục', 'laca' ) }
						selected={ layoutMode }
						options={ [
							{ label: __( 'Lưới đều', 'laca' ), value: 'grid' },
							{
								label: __(
									'Xen kẽ vuông — chữ nhật (2 cột)',
									'laca'
								),
								value: 'checkerboard',
							},
						] }
						onChange={ ( v ) => setAttributes( { layoutMode: v } ) }
					/>
					{ isCheckerboard ? (
						<p
							style={ {
								fontSize: '11px',
								color: '#666',
								margin: '4px 0 12px',
							} }
						>
							{ __(
								'Kiểu này luôn dùng 2 cột, tự xen kẽ ô vuông (1:1) — ô chữ nhật (4:5) theo vị trí, không cần chọn số cột/tỉ lệ hình. Ô chữ nhật đứng sẽ cao hơn ô vuông cùng chiều rộng — đây là kết quả tất nhiên của việc xen 2 tỉ lệ khác nhau, không phải lỗi.',
								'laca'
							) }
						</p>
					) : (
						<>
							<RangeControl
								label={ __( 'Số cột', 'laca' ) }
								value={ columns }
								min={ 2 }
								max={ 4 }
								onChange={ ( v ) =>
									setAttributes( { columns: v } )
								}
							/>
							<SelectControl
								label={ __( 'Tỉ lệ hình', 'laca' ) }
								value={ aspectRatio }
								options={ [
									{ label: '1:1', value: '1:1' },
									{ label: '4:3', value: '4:3' },
									{ label: '3:4', value: '3:4' },
									{ label: '16:9', value: '16:9' },
									{ label: '9:16', value: '9:16' },
								] }
								onChange={ ( v ) =>
									setAttributes( { aspectRatio: v } )
								}
							/>
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Căn lề', 'laca' ) }
					initialOpen={ true }
				>
					<ResponsiveSelectControl
						label={ __( 'Vị trí khung', 'laca' ) }
						options={ [
							{ label: __( 'Trái', 'laca' ), value: 'left' },
							{ label: __( 'Giữa', 'laca' ), value: 'center' },
							{ label: __( 'Phải', 'laca' ), value: 'right' },
						] }
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
					title={ __( 'Danh sách thẻ', 'laca' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Căn lề chung cho các thẻ', 'laca' ) }
						help={ __(
							'Đổi nhanh căn lề mặc định cho tất cả các thẻ.',
							'laca'
						) }
						value={ cardTextAlign }
						options={ CARD_ALIGN_OPTIONS }
						onChange={ ( v ) => {
							setAttributes( {
								cardTextAlign: v,
								items: items.map( ( it ) => ( {
									...it,
									textAlign: v,
								} ) ),
							} );
						} }
					/>
					{ items.map( ( item, index ) => {
						const isCollapsed = !! collapsedItems[ index ];
						const plainTitle = ( item.title || '' ).replace(
							/<[^>]*>/g,
							''
						);
						return (
							<div
								key={ index }
								style={ {
									border: '1px solid #ddd',
									borderRadius: 4,
									padding: 10,
									marginBottom: 10,
								} }
							>
								<Button
									onClick={ () =>
										toggleItemCollapse( index )
									}
									style={ {
										width: '100%',
										display: 'flex',
										justifyContent: 'space-between',
										alignItems: 'center',
										padding: 0,
										fontSize: '11px',
										fontWeight: 600,
										marginBottom: isCollapsed ? 0 : 6,
									} }
									aria-expanded={ ! isCollapsed }
								>
									<span>
										{ __( 'Thẻ', 'laca' ) } { index + 1 }
										{ plainTitle
											? ` — ${ plainTitle }`
											: '' }
									</span>
									<span aria-hidden="true">
										{ isCollapsed ? '▸' : '▾' }
									</span>
								</Button>

								{ ! isCollapsed && (
									<>
										<ImagePicker
											imageUrl={ item.imageUrl }
											imageId={ item.imageId }
											onSelect={ ( media ) => {
												const next = [ ...items ];
												next[ index ] = {
													...next[ index ],
													imageId: media.id,
													imageUrl: media.url,
												};
												setAttributes( {
													items: next,
												} );
											} }
										/>
										<TextControl
											label={ __( 'Đường dẫn', 'laca' ) }
											value={ item.link }
											onChange={ ( v ) =>
												updateItem( index, 'link', v )
											}
											placeholder="https://…"
										/>
										<SelectControl
											label={ __(
												'Mở liên kết',
												'laca'
											) }
											value={ item.linkTarget || '_self' }
											options={ [
												{
													label: __(
														'Cùng tab (mặc định)',
														'laca'
													),
													value: '_self',
												},
												{
													label: __(
														'Tab mới',
														'laca'
													),
													value: '_blank',
												},
											] }
											onChange={ ( v ) =>
												updateItem(
													index,
													'linkTarget',
													v
												)
											}
										/>
										<SelectControl
											label={ __( 'Căn lề', 'laca' ) }
											value={
												item.textAlign ||
												cardTextAlign ||
												'center'
											}
											options={ CARD_ALIGN_OPTIONS }
											onChange={ ( v ) =>
												updateItem(
													index,
													'textAlign',
													v
												)
											}
										/>
										<Button
											variant="secondary"
											isDestructive
											onClick={ () =>
												removeItem( index )
											}
										>
											{ __( 'Xóa thẻ này', 'laca' ) }
										</Button>
									</>
								) }
							</div>
						);
					} ) }
					<Button variant="primary" onClick={ addItem }>
						{ __( '+ Thêm thẻ', 'laca' ) }
					</Button>
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							marginBottom: '0.5rem',
						} }
					>
						{ __(
							'Màu nền placeholder (khi thẻ chưa có ảnh)',
							'laca'
						) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ false }
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="container-fluid">
					<div
						className="block-image-card-grid__maxwidth"
						style={ maxWidthStyle }
					>
						<div
							className={
								'block-image-card-grid__grid' +
								( isCheckerboard
									? ' block-image-card-grid__grid--checkerboard'
									: '' )
							}
							style={
								isCheckerboard
									? undefined
									: {
											'--icg-columns': columns,
											'--icg-ratio': aspectRatio.replace(
												':',
												'/'
											),
									  }
							}
						>
							{ items.map( ( item, index ) => {
								const isTall =
									isCheckerboard &&
									Boolean(
										( Math.floor( index / 2 ) + index ) % 2
									);
								return (
									<div
										className="block-image-card-grid__card"
										key={ index }
										style={
											isCheckerboard
												? {
														gridColumn: `span ${
															isTall ? 6 : 4
														}`,
												  }
												: undefined
										}
									>
										<div
											className={
												'block-image-card-grid__image' +
												( isTall
													? ' block-image-card-grid__image--tall'
													: '' )
											}
											style={ {
												background: item.imageUrl
													? undefined
													: bgColor,
											} }
										>
											{ item.imageUrl && (
												<img
													src={ item.imageUrl }
													alt=""
												/>
											) }
											<div className="block-image-card-grid__overlay" />
											<div
												className={
													'block-image-card-grid__content block-image-card-grid__content--' +
													( item.textAlign ||
														cardTextAlign ||
														'center' )
												}
											>
												<RichText
													tagName="h3"
													className="block-image-card-grid__title"
													value={ item.title }
													onChange={ ( v ) =>
														updateItem(
															index,
															'title',
															v
														)
													}
													placeholder={ __(
														'Tiêu đề…',
														'laca'
													) }
													allowedFormats={ [] }
												/>
												<RichText
													tagName="p"
													className="block-image-card-grid__desc"
													value={ item.desc }
													onChange={ ( v ) =>
														updateItem(
															index,
															'desc',
															v
														)
													}
													placeholder={ __(
														'Mô tả…',
														'laca'
													) }
												/>
											</div>
										</div>
									</div>
								);
							} ) }
						</div>
					</div>
				</div>
			</section>
		</>
	);
}
