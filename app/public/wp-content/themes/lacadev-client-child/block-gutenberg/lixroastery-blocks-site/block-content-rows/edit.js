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
	RadioControl,
	Button,
} from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

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
						<Button variant="secondary" onClick={ open } style={ { fontSize: 11 } }>
							{ imageUrl ? __( 'Đổi ảnh', 'laca' ) : __( 'Chọn ảnh', 'laca' ) }
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

	const { mainTitle, containerType, rows } = attributes;

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
				},
			],
		} );
	};

	const updateAsideImage = ( rowIndex, imgIndex, field, value ) => {
		const row = rows[ rowIndex ];
		const nextImages = [ ...row.asideImages ];
		nextImages[ imgIndex ] = { ...nextImages[ imgIndex ], [ field ]: value };
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
				<PanelBody title={ __( 'Bố cục', 'laca' ) } initialOpen={ true }>
					<RadioControl
						label={ __( 'Chiều rộng khung', 'laca' ) }
						selected={ containerType }
						options={ [
							{ label: __( 'Full width (container-fluid)', 'laca' ), value: 'container-fluid' },
							{ label: __( 'Giới hạn (container)', 'laca' ), value: 'container' },
						] }
						onChange={ ( v ) => setAttributes( { containerType: v } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Các đoạn nội dung', 'laca' ) } initialOpen={ true }>
					{ rows.map( ( row, index ) => (
						<div
							key={ index }
							style={ {
								border: '1px solid #ddd',
								borderRadius: 4,
								padding: 10,
								marginBottom: 12,
							} }
						>
							<p style={ { fontSize: '11px', fontWeight: 600, marginBottom: 6 } }>
								{ __( 'Đoạn', 'laca' ) } { index + 1 }
							</p>
							<SelectControl
								label={ __( 'Đối diện bên phải', 'laca' ) }
								value={ row.asideType }
								options={ [
									{ label: __( 'Không có', 'laca' ), value: 'none' },
									{ label: __( 'Ảnh thẻ', 'laca' ), value: 'image' },
									{ label: __( 'Trích dẫn', 'laca' ), value: 'quote' },
								] }
								onChange={ ( v ) => updateRow( index, 'asideType', v ) }
							/>

							{ row.asideType === 'image' && (
								<>
									<p style={ { fontSize: '11px', color: '#666', margin: '8px 0 4px' } }>
										{ __( 'Ảnh thẻ (có thể thêm nhiều, xếp chồng) — tiêu đề/mô tả sửa trực tiếp trong khung soạn thảo.', 'laca' ) }
									</p>
									{ row.asideImages.map( ( img, imgIndex ) => (
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
												imageUrl={ img.imageUrl }
												imageId={ img.imageId }
												onSelect={ ( media ) => {
													updateAsideImage( index, imgIndex, 'imageId', media.id );
													updateAsideImage( index, imgIndex, 'imageUrl', media.url );
												} }
											/>
											<TextControl
												label={ __( 'Đường dẫn', 'laca' ) }
												value={ img.link }
												onChange={ ( v ) => updateAsideImage( index, imgIndex, 'link', v ) }
												placeholder="https://…"
											/>
											<Button
												variant="secondary"
												isDestructive
												onClick={ () => removeAsideImage( index, imgIndex ) }
											>
												{ __( 'Xóa ảnh này', 'laca' ) }
											</Button>
										</div>
									) ) }
									<Button variant="secondary" onClick={ () => addAsideImage( index ) }>
										{ __( '+ Thêm ảnh', 'laca' ) }
									</Button>
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
						</div>
					) ) }
					<Button variant="primary" onClick={ addRow }>
						{ __( '+ Thêm đoạn', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className={ containerType === 'container' ? 'container' : 'container-fluid' }>
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
									onChange={ ( v ) => updateRow( index, 'subTitle', v ) }
									placeholder={ __( 'Tiêu đề phụ…', 'laca' ) }
									allowedFormats={ [] }
								/>
								<RichText
									tagName="div"
									multiline="p"
									className="block-content-rows__text"
									value={ row.content }
									onChange={ ( v ) => updateRow( index, 'content', v ) }
									placeholder={ __( 'Nhập nội dung…', 'laca' ) }
								/>
							</div>

							<div className="block-content-rows__aside">
								{ row.asideType === 'image' &&
									row.asideImages.map( ( img, imgIndex ) => (
										<div className="block-content-rows__image-card" key={ imgIndex }>
											{ img.imageUrl && <img src={ img.imageUrl } alt="" /> }
											<RichText
												tagName="h4"
												className="block-content-rows__image-title"
												value={ img.title }
												onChange={ ( v ) => updateAsideImage( index, imgIndex, 'title', v ) }
												placeholder={ __( 'Tiêu đề ảnh…', 'laca' ) }
												allowedFormats={ [] }
											/>
											<RichText
												tagName="p"
												className="block-content-rows__image-desc"
												value={ img.desc }
												onChange={ ( v ) => updateAsideImage( index, imgIndex, 'desc', v ) }
												placeholder={ __( 'Mô tả ảnh…', 'laca' ) }
											/>
										</div>
									) ) }

								{ row.asideType === 'quote' && (
									<blockquote className="block-content-rows__quote">
										<RichText
											tagName="p"
											className="block-content-rows__quote-text"
											value={ row.quoteText }
											onChange={ ( v ) => updateRow( index, 'quoteText', v ) }
											placeholder={ __( 'Nhập câu trích dẫn…', 'laca' ) }
										/>
										<RichText
											tagName="cite"
											className="block-content-rows__quote-author"
											value={ row.quoteAuthor }
											onChange={ ( v ) => updateRow( index, 'quoteAuthor', v ) }
											placeholder={ __( 'Tác giả…', 'laca' ) }
											allowedFormats={ [] }
										/>
										<RichText
											tagName="p"
											className="block-content-rows__quote-source"
											value={ row.quoteSource }
											onChange={ ( v ) => updateRow( index, 'quoteSource', v ) }
											placeholder={ __( 'Nguồn trích dẫn…', 'laca' ) }
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
