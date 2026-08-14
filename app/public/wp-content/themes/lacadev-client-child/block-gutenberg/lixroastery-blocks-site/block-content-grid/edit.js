import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, RangeControl, RadioControl, Button } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	const { columns, containerType, items } = attributes;

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Content Grid', 'laca' ) }
				title={ __( 'Danh sách nội dung', 'laca' ) }
				columns={ columns || 2 }
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
		setAttributes( { items: [ ...items, { title: '', desc: '' } ] } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bố cục', 'laca' ) } initialOpen={ true }>
					<RangeControl
						label={ __( 'Số cột', 'laca' ) }
						value={ columns }
						min={ 1 }
						max={ 4 }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
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

				<PanelBody title={ __( 'Danh sách mục', 'laca' ) } initialOpen={ true }>
					<p style={ { fontSize: '11px', color: '#666', margin: '4px 0 8px' } }>
						{ __( 'Tiêu đề và mô tả sửa trực tiếp trong khung soạn thảo.', 'laca' ) }
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
				<div className={ containerType === 'container' ? 'container' : 'container-fluid' }>
					<div
						className="block-content-grid__grid"
						style={ { '--ctg-columns': columns } }
					>
						{ items.map( ( item, index ) => (
							<div className="block-content-grid__item" key={ index }>
								<hr className="block-content-grid__rule" />
								<RichText
									tagName="h3"
									className="block-content-grid__title"
									value={ item.title }
									onChange={ ( v ) => updateItem( index, 'title', v ) }
									placeholder={ __( 'Tiêu đề…', 'laca' ) }
									allowedFormats={ [] }
								/>
								<RichText
									tagName="p"
									className="block-content-grid__desc"
									value={ item.desc }
									onChange={ ( v ) => updateItem( index, 'desc', v ) }
									placeholder={ __( 'Mô tả…', 'laca' ) }
								/>
							</div>
						) ) }
					</div>
				</div>
			</section>
		</>
	);
}
