import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, Button } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container (block-container).
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
};

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps( { className: 'container-fluid' } );

	const { maxWidth, contentAlign, title, description, items } = attributes;

	const maxWidthStyle = {
		maxWidth: `${ maxWidth }%`,
		margin: MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
	};

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Toggle', 'laca' ) }
				title={ __( 'Câu hỏi thường gặp', 'laca' ) }
				columns={ 1 }
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
			items: [ ...items, { question: '', answer: '' } ],
		} );
	};

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
						label={ __( 'Căn lề', 'laca' ) }
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

				<PanelBody title={ __( 'Các mục hỏi đáp', 'laca' ) } initialOpen={ true }>
					<p style={ { fontSize: '11px', color: '#666', margin: '4px 0 8px' } }>
						{ __(
							'Nhập trực tiếp trong khung soạn thảo. Ở frontend, mỗi lần chỉ 1 mục được mở — bấm vào câu hỏi để đóng/mở.',
							'laca'
						) }
					</p>
					{ items.map( ( item, index ) => (
						<Button
							key={ index }
							variant="secondary"
							isDestructive
							style={ { marginBottom: 8, marginRight: 8 } }
							onClick={ () => removeItem( index ) }
						>
							{ __( 'Xóa mục', 'laca' ) } { index + 1 }
						</Button>
					) ) }
					<Button variant="primary" onClick={ addItem }>
						{ __( '+ Thêm mục', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="block-toggle__maxwidth" style={ maxWidthStyle }>
					<RichText
						tagName="h2"
						className="block-toggle__title"
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
						placeholder={ __( 'Tiêu đề…', 'laca' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="p"
						className="block-toggle__description"
						value={ description }
						onChange={ ( v ) => setAttributes( { description: v } ) }
						placeholder={ __( 'Mô tả ngắn…', 'laca' ) }
					/>

					<div className="block-toggle__list">
						{ items.map( ( item, index ) => (
							<div className="block-toggle__item" key={ index }>
								<div className="block-toggle__question">
									<RichText
										tagName="span"
										value={ item.question }
										onChange={ ( v ) => updateItem( index, 'question', v ) }
										placeholder={ __( 'Câu hỏi…', 'laca' ) }
										allowedFormats={ [] }
									/>
									<span className="block-toggle__icon" />
								</div>
								<div className="block-toggle__answer">
									<RichText
										tagName="p"
										value={ item.answer }
										onChange={ ( v ) => updateItem( index, 'answer', v ) }
										placeholder={ __( 'Câu trả lời…', 'laca' ) }
									/>
								</div>
							</div>
						) ) }
					</div>
				</div>
			</section>
		</>
	);
}
