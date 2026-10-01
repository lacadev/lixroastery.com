import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RadioControl,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';

// Section ngoài LUÔN container-fluid — độ rộng CỘT nội dung điều chỉnh riêng
// qua maxWidth (%) + contentAlign (giống hệt block Container), KHÁC với
// textAlign (chỉ canh chữ bên trong cột, xem panel "Kiểu hiển thị" bên dưới).
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
};

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps( { className: 'container-fluid' } );

	const { maxWidth, contentAlign, variant, textAlign, content } = attributes;

	const maxWidthStyle = {
		maxWidth: `${ maxWidth }%`,
		margin: MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
	};

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
				<PanelBody
					title={ __( 'Bố cục', 'laca' ) }
					initialOpen={ true }
				>
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
						label={ __( 'Căn lề khung', 'laca' ) }
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

				<PanelBody
					title={ __( 'Kiểu hiển thị', 'laca' ) }
					initialOpen={ true }
				>
					<RadioControl
						label={ __( 'Kiểu chữ', 'laca' ) }
						selected={ variant }
						options={ [
							{
								label: __( 'Nội dung thường', 'laca' ),
								value: 'normal',
							},
							{
								label: __( 'Trích dẫn lớn (quote)', 'laca' ),
								value: 'quote',
							},
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
							{
								label: __( 'Đều hai bên (justify)', 'laca' ),
								value: 'justify',
							},
						] }
						onChange={ ( v ) => setAttributes( { textAlign: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div
					className="block-content-text__maxwidth"
					style={ maxWidthStyle }
				>
					<RichText
						tagName="div"
						className={
							'block-content-text__body block-content-text__body--' +
							variant
						}
						style={ { textAlign } }
						value={ content }
						onChange={ ( v ) => setAttributes( { content: v } ) }
						placeholder={ __( 'Nhập nội dung…', 'laca' ) }
						allowedFormats={ [
							'core/bold',
							'core/italic',
							'core/link',
						] }
					/>
				</div>
			</section>
		</>
	);
}
