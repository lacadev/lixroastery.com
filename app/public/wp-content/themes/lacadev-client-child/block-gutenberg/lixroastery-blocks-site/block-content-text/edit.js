import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, RadioControl } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	// containerType gắn thẳng vào className của section (KHÔNG bọc thêm 1 div
	// riêng) — giống class Bootstrap thật (.container/.container-fluid tự là
	// khung ngoài cùng), khớp với render.php.
	const blockProps = useBlockProps( {
		className:
			attributes.containerType === 'container-fluid'
				? 'container-fluid'
				: 'container',
	} );

	const { containerType, variant, textAlign, content } = attributes;

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Content', 'laca' ) }
				title={ __( 'Nội dung / Trích dẫn', 'laca' ) }
				columns={ 1 }
			/>
		);
	}

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

				<PanelBody title={ __( 'Kiểu hiển thị', 'laca' ) } initialOpen={ true }>
					<RadioControl
						label={ __( 'Kiểu chữ', 'laca' ) }
						selected={ variant }
						options={ [
							{ label: __( 'Nội dung thường', 'laca' ), value: 'normal' },
							{ label: __( 'Trích dẫn lớn (quote)', 'laca' ), value: 'quote' },
						] }
						onChange={ ( v ) => setAttributes( { variant: v } ) }
					/>
					<RadioControl
						label={ __( 'Căn lề', 'laca' ) }
						selected={ textAlign }
						options={ [
							{ label: __( 'Trái', 'laca' ), value: 'left' },
							{ label: __( 'Giữa', 'laca' ), value: 'center' },
							{ label: __( 'Phải', 'laca' ), value: 'right' },
							{ label: __( 'Đều hai bên (justify)', 'laca' ), value: 'justify' },
						] }
						onChange={ ( v ) => setAttributes( { textAlign: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<RichText
					tagName="div"
					className={
						'block-content-text__body block-content-text__body--' + variant
					}
					style={ { textAlign } }
					value={ content }
					onChange={ ( v ) => setAttributes( { content: v } ) }
					placeholder={ __( 'Nhập nội dung…', 'laca' ) }
					allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
				/>
			</section>
		</>
	);
}
