import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, RadioControl, Button } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	const { containerType, title, description, items } = attributes;

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
					<RadioControl
						label={ __( 'Chiều rộng khung', 'laca' ) }
						selected={ containerType }
						options={ [
							{ label: __( 'Giới hạn (container)', 'laca' ), value: 'container' },
							{ label: __( 'Full width (container-fluid)', 'laca' ), value: 'container-fluid' },
						] }
						onChange={ ( v ) => setAttributes( { containerType: v } ) }
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
				<div className={ containerType === 'container-fluid' ? 'container-fluid' : 'container' }>
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
