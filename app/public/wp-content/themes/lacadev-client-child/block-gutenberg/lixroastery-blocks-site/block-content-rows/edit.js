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
	Button,
} from '@wordpress/components';
import { useState, useMemo, useRef, useEffect } from '@wordpress/element';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';
import {
	GOOGLE_FONT_OPTIONS,
	googleFontCssUrl,
} from '../../utils/google-fonts-vi';
import previewImage from './preview.png';

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container (block-container).
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

const QUOTE_FONT_SIZE_ROW_KEYS = {
	pc: 'quoteFontSize',
	tablet: 'quoteFontSizeTablet',
	mobile: 'quoteFontSizeMobile',
};

const ASIDE_TYPE_LABELS = {
	none: __( 'Không có', 'laca' ),
	image: __( 'Ảnh thẻ', 'laca' ),
	quote: __( 'Trích dẫn', 'laca' ),
};

// prefix id cho <link> font chèn vào iframe editor — mỗi font family active
// (không trùng) có đúng 1 thẻ, xem useEffect nạp font bên dưới.
const QUOTE_FONT_LINK_PREFIX = 'block-content-rows-quote-font-';

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
	const blockProps = useBlockProps( { className: 'container-fluid' } );

	const {
		mainTitle,
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		rows,
	} = attributes;

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

	// Thu gọn/mở rộng từng "Đoạn" trong panel Inspector — nhiều đoạn xếp dài
	// rất khó quản lý, mặc định mở (false = đang mở) để không đổi hành vi
	// cũ khi mới có 1-2 đoạn.
	const [ collapsedRows, setCollapsedRows ] = useState( {} );
	const toggleRowCollapse = ( index ) =>
		setCollapsedRows( ( prev ) => ( {
			...prev,
			[ index ]: ! prev[ index ],
		} ) );

	// Nạp Google Font đã chọn (có thể khác nhau giữa từng đoạn quote) vào
	// ĐÚNG document của iframe editor — xem lý do kỹ thuật ở
	// block-content-text/edit.js (cùng pattern, nhân rộng cho nhiều font).
	const quoteFontFamilies = useMemo( () => {
		const set = new Set();
		rows.forEach( ( row ) => {
			if ( row.asideType === 'quote' && row.quoteFontFamily ) {
				set.add( row.quoteFontFamily );
			}
		} );
		return [ ...set ];
	}, [ rows ] );

	const sectionRef = useRef( null );
	useEffect( () => {
		const ownerDocument = sectionRef.current?.ownerDocument;
		if ( ! ownerDocument ) {
			return;
		}
		ownerDocument
			.querySelectorAll( `link[id^="${ QUOTE_FONT_LINK_PREFIX }"]` )
			.forEach( ( link ) => {
				if ( ! quoteFontFamilies.includes( link.dataset.family ) ) {
					link.remove();
				}
			} );
		quoteFontFamilies.forEach( ( family ) => {
			const id =
				QUOTE_FONT_LINK_PREFIX +
				family.replace( /\s+/g, '-' ).toLowerCase();
			if ( ownerDocument.getElementById( id ) ) {
				return;
			}
			const link = ownerDocument.createElement( 'link' );
			link.id = id;
			link.rel = 'stylesheet';
			link.dataset.family = family;
			link.href = googleFontCssUrl( family );
			ownerDocument.head.appendChild( link );
		} );
	}, [ quoteFontFamilies ] );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Content Rows', 'laca' ) }
				title={ mainTitle || __( 'Nội dung bài viết', 'laca' ) }
				columns={ 2 }
				image={ previewImage }
			/>
		);
	}

	const updateRow = ( index, field, value ) => {
		const next = [ ...rows ];
		next[ index ] = { ...next[ index ], [ field ]: value };
		setAttributes( { rows: next } );
	};

	const removeRow = ( index ) => {
		setAttributes( { rows: rows.filter( ( _, i ) => i !== index ) } );
	};

	const addRow = () => {
		setAttributes( {
			rows: [
				...rows,
				{
					subTitle: '',
					content: '',
					asideType: 'none',
					asideImages: [],
					quoteText: '',
					quoteAuthor: '',
					quoteSource: '',
					quoteAlign: 'left',
					quoteFontFamily: '',
					quoteFontSize: 40,
					quoteFontSizeTablet: 32,
					quoteFontSizeMobile: 24,
				},
			],
		} );
	};

	const updateAsideImage = ( rowIndex, imgIndex, field, value ) => {
		const row = rows[ rowIndex ];
		const nextImages = [ ...row.asideImages ];
		nextImages[ imgIndex ] = {
			...nextImages[ imgIndex ],
			[ field ]: value,
		};
		updateRow( rowIndex, 'asideImages', nextImages );
	};

	const addAsideImage = ( rowIndex ) => {
		const row = rows[ rowIndex ];
		updateRow( rowIndex, 'asideImages', [
			...row.asideImages,
			{ imageId: 0, imageUrl: '', title: '', desc: '', link: '' },
		] );
	};

	const removeAsideImage = ( rowIndex, imgIndex ) => {
		const row = rows[ rowIndex ];
		updateRow(
			rowIndex,
			'asideImages',
			row.asideImages.filter( ( _, i ) => i !== imgIndex )
		);
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
				</PanelBody>

				<PanelBody
					title={ __( 'Vị trí khung', 'laca' ) }
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
					title={ __( 'Các đoạn nội dung', 'laca' ) }
					initialOpen={ true }
				>
					{ rows.map( ( row, index ) => {
						const isCollapsed = !! collapsedRows[ index ];
						return (
							<div
								key={ index }
								style={ {
									border: '1px solid #ddd',
									borderRadius: 4,
									padding: 10,
									marginBottom: 12,
								} }
							>
								<Button
									onClick={ () => toggleRowCollapse( index ) }
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
										{ __( 'Đoạn', 'laca' ) } { index + 1 }
										{ ' — ' }
										{ ASIDE_TYPE_LABELS[ row.asideType ] }
									</span>
									<span aria-hidden="true">
										{ isCollapsed ? '▸' : '▾' }
									</span>
								</Button>

								{ ! isCollapsed && (
									<>
										<SelectControl
											label={ __(
												'Đối diện bên phải',
												'laca'
											) }
											value={ row.asideType }
											options={ [
												{
													label: __(
														'Không có',
														'laca'
													),
													value: 'none',
												},
												{
													label: __(
														'Ảnh thẻ',
														'laca'
													),
													value: 'image',
												},
												{
													label: __(
														'Trích dẫn',
														'laca'
													),
													value: 'quote',
												},
											] }
											onChange={ ( v ) =>
												updateRow(
													index,
													'asideType',
													v
												)
											}
										/>

										{ row.asideType === 'image' && (
											<>
												<p
													style={ {
														fontSize: '11px',
														color: '#666',
														margin: '8px 0 4px',
													} }
												>
													{ __(
														'Ảnh thẻ (có thể thêm nhiều, xếp chồng) — tiêu đề/mô tả sửa trực tiếp trong khung soạn thảo.',
														'laca'
													) }
												</p>
												{ row.asideImages.map(
													( img, imgIndex ) => (
														<div
															key={ imgIndex }
															style={ {
																border: '1px solid #eee',
																borderRadius: 4,
																padding: 8,
																marginBottom: 8,
															} }
														>
															<ImagePicker
																imageUrl={
																	img.imageUrl
																}
																imageId={
																	img.imageId
																}
																onSelect={ (
																	media
																) => {
																	updateAsideImage(
																		index,
																		imgIndex,
																		'imageId',
																		media.id
																	);
																	updateAsideImage(
																		index,
																		imgIndex,
																		'imageUrl',
																		media.url
																	);
																} }
															/>
															<TextControl
																label={ __(
																	'Đường dẫn',
																	'laca'
																) }
																value={
																	img.link
																}
																onChange={ (
																	v
																) =>
																	updateAsideImage(
																		index,
																		imgIndex,
																		'link',
																		v
																	)
																}
																placeholder="https://…"
															/>
															<Button
																variant="secondary"
																isDestructive
																onClick={ () =>
																	removeAsideImage(
																		index,
																		imgIndex
																	)
																}
															>
																{ __(
																	'Xóa ảnh này',
																	'laca'
																) }
															</Button>
														</div>
													)
												) }
												<Button
													variant="secondary"
													onClick={ () =>
														addAsideImage( index )
													}
												>
													{ __(
														'+ Thêm ảnh',
														'laca'
													) }
												</Button>
											</>
										) }

										{ row.asideType === 'quote' && (
											<>
												<SelectControl
													label={ __(
														'Căn lề trích dẫn',
														'laca'
													) }
													value={
														row.quoteAlign || 'left'
													}
													options={ [
														{
															label: __(
																'Trái',
																'laca'
															),
															value: 'left',
														},
														{
															label: __(
																'Giữa',
																'laca'
															),
															value: 'center',
														},
														{
															label: __(
																'Phải',
																'laca'
															),
															value: 'right',
														},
														{
															label: __(
																'Đều hai bên (justify)',
																'laca'
															),
															value: 'justify',
														},
													] }
													onChange={ ( v ) =>
														updateRow(
															index,
															'quoteAlign',
															v
														)
													}
												/>
												<SelectControl
													label={ __(
														'Font chữ trích dẫn (Google Fonts)',
														'laca'
													) }
													help={ __(
														'Chọn khác mặc định sẽ tải thêm 1 font từ Google Fonts CDN (ảnh hưởng nhẹ tốc độ tải trang).',
														'laca'
													) }
													value={
														row.quoteFontFamily ||
														''
													}
													options={
														GOOGLE_FONT_OPTIONS
													}
													onChange={ ( v ) =>
														updateRow(
															index,
															'quoteFontFamily',
															v || ''
														)
													}
												/>
												<ResponsiveRangeControl
													label={ __(
														'Cỡ chữ trích dẫn (px)',
														'laca'
													) }
													min={ 16 }
													max={ 64 }
													valuesByDevice={ {
														pc:
															row.quoteFontSize ??
															40,
														tablet:
															row.quoteFontSizeTablet ??
															32,
														mobile:
															row.quoteFontSizeMobile ??
															24,
													} }
													onChange={ ( device, v ) =>
														updateRow(
															index,
															QUOTE_FONT_SIZE_ROW_KEYS[
																device
															],
															v
														)
													}
												/>
											</>
										) }

										<Button
											variant="secondary"
											isDestructive
											style={ { marginTop: 10 } }
											onClick={ () => removeRow( index ) }
										>
											{ __( 'Xóa đoạn này', 'laca' ) }
										</Button>
									</>
								) }
							</div>
						);
					} ) }
					<Button variant="primary" onClick={ addRow }>
						{ __( '+ Thêm đoạn', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps } ref={ sectionRef }>
				<div
					className="block-content-rows__maxwidth"
					style={ maxWidthStyle }
				>
					<RichText
						tagName="h2"
						className="block-content-rows__main-title"
						value={ mainTitle }
						onChange={ ( v ) => setAttributes( { mainTitle: v } ) }
						placeholder={ __( 'Tiêu đề chính…', 'laca' ) }
						allowedFormats={ [] }
					/>

					{ rows.map( ( row, index ) => (
						<div className="block-content-rows__row" key={ index }>
							<div className="block-content-rows__content">
								<RichText
									tagName="h3"
									className="block-content-rows__sub-title"
									value={ row.subTitle }
									onChange={ ( v ) =>
										updateRow( index, 'subTitle', v )
									}
									placeholder={ __( 'Tiêu đề phụ…', 'laca' ) }
									allowedFormats={ [] }
								/>
								<RichText
									tagName="div"
									multiline="p"
									className="block-content-rows__text"
									value={ row.content }
									onChange={ ( v ) =>
										updateRow( index, 'content', v )
									}
									placeholder={ __(
										'Nhập nội dung…',
										'laca'
									) }
								/>
							</div>

							<div className="block-content-rows__aside">
								{ row.asideType === 'image' &&
									row.asideImages.map( ( img, imgIndex ) => (
										<div
											className="block-content-rows__image-card"
											key={ imgIndex }
										>
											{ img.imageUrl && (
												<img
													src={ img.imageUrl }
													alt=""
												/>
											) }
											<RichText
												tagName="h4"
												className="block-content-rows__image-title"
												value={ img.title }
												onChange={ ( v ) =>
													updateAsideImage(
														index,
														imgIndex,
														'title',
														v
													)
												}
												placeholder={ __(
													'Tiêu đề ảnh…',
													'laca'
												) }
												allowedFormats={ [] }
											/>
											<RichText
												tagName="p"
												className="block-content-rows__image-desc"
												value={ img.desc }
												onChange={ ( v ) =>
													updateAsideImage(
														index,
														imgIndex,
														'desc',
														v
													)
												}
												placeholder={ __(
													'Mô tả ảnh…',
													'laca'
												) }
											/>
										</div>
									) ) }

								{ row.asideType === 'quote' && (
									<blockquote className="block-content-rows__quote">
										<RichText
											tagName="p"
											className="block-content-rows__quote-text"
											style={ {
												textAlign:
													row.quoteAlign || 'left',
												'--quote-font-family':
													row.quoteFontFamily ||
													undefined,
												'--quote-fs-pc': `${
													row.quoteFontSize ?? 40
												}px`,
												'--quote-fs-tablet': `${
													row.quoteFontSizeTablet ??
													32
												}px`,
												'--quote-fs-mobile': `${
													row.quoteFontSizeMobile ??
													24
												}px`,
											} }
											value={ row.quoteText }
											onChange={ ( v ) =>
												updateRow(
													index,
													'quoteText',
													v
												)
											}
											placeholder={ __(
												'Nhập câu trích dẫn…',
												'laca'
											) }
										/>
										<RichText
											tagName="cite"
											className="block-content-rows__quote-author"
											value={ row.quoteAuthor }
											onChange={ ( v ) =>
												updateRow(
													index,
													'quoteAuthor',
													v
												)
											}
											placeholder={ __(
												'Tác giả…',
												'laca'
											) }
											allowedFormats={ [] }
										/>
										<RichText
											tagName="p"
											className="block-content-rows__quote-source"
											value={ row.quoteSource }
											onChange={ ( v ) =>
												updateRow(
													index,
													'quoteSource',
													v
												)
											}
											placeholder={ __(
												'Nguồn trích dẫn…',
												'laca'
											) }
										/>
									</blockquote>
								) }
							</div>
						</div>
					) ) }
				</div>
			</section>
		</>
	);
}
