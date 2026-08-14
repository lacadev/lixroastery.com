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

	const { columns, layoutMode, aspectRatio, bgColor, items } = attributes;
	const isCheckerboard = layoutMode === 'checkerboard';

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
				},
			],
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bố cục', 'laca' ) } initialOpen={ true }>
					<RadioControl
						label={ __( 'Kiểu bố cục', 'laca' ) }
						selected={ layoutMode }
						options={ [
							{ label: __( 'Lưới đều', 'laca' ), value: 'grid' },
							{
								label: __( 'Xen kẽ vuông — chữ nhật (2 cột)', 'laca' ),
								value: 'checkerboard',
							},
						] }
						onChange={ ( v ) => setAttributes( { layoutMode: v } ) }
					/>
					{ isCheckerboard ? (
						<p style={ { fontSize: '11px', color: '#666', margin: '4px 0 12px' } }>
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
								onChange={ ( v ) => setAttributes( { columns: v } ) }
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
								onChange={ ( v ) => setAttributes( { aspectRatio: v } ) }
							/>
						</>
					) }
					<p style={ { fontSize: '0.8rem', fontWeight: 600, marginBottom: '0.5rem' } }>
						{ __( 'Màu nền placeholder (khi thẻ chưa có ảnh)', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ false }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Danh sách thẻ', 'laca' ) } initialOpen={ true }>
					{ items.map( ( item, index ) => (
						<div
							key={ index }
							style={ {
								border: '1px solid #ddd',
								borderRadius: 4,
								padding: 10,
								marginBottom: 10,
							} }
						>
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
									setAttributes( { items: next } );
								} }
							/>
							<TextControl
								label={ __( 'Đường dẫn', 'laca' ) }
								value={ item.link }
								onChange={ ( v ) => updateItem( index, 'link', v ) }
								placeholder="https://…"
							/>
							<SelectControl
								label={ __( 'Mở liên kết', 'laca' ) }
								value={ item.linkTarget || '_self' }
								options={ [
									{ label: __( 'Cùng tab (mặc định)', 'laca' ), value: '_self' },
									{ label: __( 'Tab mới', 'laca' ), value: '_blank' },
								] }
								onChange={ ( v ) => updateItem( index, 'linkTarget', v ) }
							/>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => removeItem( index ) }
							>
								{ __( 'Xóa thẻ này', 'laca' ) }
							</Button>
						</div>
					) ) }
					<Button variant="primary" onClick={ addItem }>
						{ __( '+ Thêm thẻ', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="container-fluid">
					<div
						className={
							'block-image-card-grid__grid' +
							( isCheckerboard ? ' block-image-card-grid__grid--checkerboard' : '' )
						}
						style={
							isCheckerboard
								? undefined
								: { '--icg-columns': columns, '--icg-ratio': aspectRatio.replace( ':', '/' ) }
						}
					>
						{ items.map( ( item, index ) => {
							const isTall =
								isCheckerboard &&
								Boolean( ( Math.floor( index / 2 ) + index ) % 2 );
							return (
							<div
								className="block-image-card-grid__card"
								key={ index }
								style={ isCheckerboard ? { gridColumn: `span ${ isTall ? 6 : 4 }` } : undefined }
							>
								<div
									className={
										'block-image-card-grid__image' +
										( isTall ? ' block-image-card-grid__image--tall' : '' )
									}
									style={ { background: item.imageUrl ? undefined : bgColor } }
								>
									{ item.imageUrl && <img src={ item.imageUrl } alt="" /> }
									<div className="block-image-card-grid__overlay" />
									<div className="block-image-card-grid__content">
										<RichText
											tagName="h3"
											className="block-image-card-grid__title"
											value={ item.title }
											onChange={ ( v ) => updateItem( index, 'title', v ) }
											placeholder={ __( 'Tiêu đề…', 'laca' ) }
											allowedFormats={ [] }
										/>
										<RichText
											tagName="p"
											className="block-image-card-grid__desc"
											value={ item.desc }
											onChange={ ( v ) => updateItem( index, 'desc', v ) }
											placeholder={ __( 'Mô tả…', 'laca' ) }
										/>
									</div>
								</div>
							</div>
							);
						} ) }
					</div>
				</div>
			</section>
		</>
	);
}
