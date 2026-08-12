import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ColorPicker,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { hexToRgba } from '../../utils/style';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'CTA Section', 'laca' ) }
				title={ __( 'Kêu gọi hành động', 'laca' ) }
				columns={ 1 }
				image={ previewImage }
			/>
		);
	}

	const {
		headline,
		description,
		buttonText,
		buttonLink,
		buttonTarget,
		textColor,
		buttonColor,
		bgColor,
		bgOpacity,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Nút bấm', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Đường dẫn', 'laca' ) }
						value={ buttonLink }
						onChange={ ( v ) => setAttributes( { buttonLink: v } ) }
						placeholder="https://…"
					/>
					<SelectControl
						label={ __( 'Mở liên kết', 'laca' ) }
						value={ buttonTarget }
						options={ [
							{
								label: __( 'Cùng tab (mặc định)', 'laca' ),
								value: '_self',
							},
							{ label: __( 'Tab mới', 'laca' ), value: '_blank' },
						] }
						onChange={ ( v ) =>
							setAttributes( { buttonTarget: v } )
						}
					/>
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
						{ __( 'Màu chữ', 'laca' ) }
					</p>
					<ColorPicker
						color={ textColor }
						onChange={ ( v ) => setAttributes( { textColor: v } ) }
						enableAlpha={ false }
					/>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							margin: '1rem 0 0.5rem',
						} }
					>
						{ __( 'Màu nút', 'laca' ) }
					</p>
					<ColorPicker
						color={ buttonColor }
						onChange={ ( v ) =>
							setAttributes( { buttonColor: v } )
						}
						enableAlpha={ false }
					/>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							margin: '1rem 0 0.5rem',
						} }
					>
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ false }
					/>
					<RangeControl
						label={ __( 'Độ mờ nền (%)', 'laca' ) }
						value={ bgOpacity }
						min={ 0 }
						max={ 100 }
						step={ 5 }
						onChange={ ( v ) => setAttributes( { bgOpacity: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<section
				{ ...blockProps }
				style={ {
					...blockProps.style,
					background: hexToRgba( bgColor, bgOpacity ),
					color: textColor,
				} }
			>
				<div className="container block-cta-section__inner">
					<RichText
						tagName="h2"
						className="block-cta-section__headline"
						value={ headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="div"
						className="block-cta-section__desc"
						value={ description }
						onChange={ ( v ) =>
							setAttributes( { description: v } )
						}
						placeholder={ __( 'Nhập mô tả…', 'laca' ) }
					/>
					<div className="block-cta-section__btn">
						<RichText
							tagName="span"
							className="block-cta-section__link"
							style={ { display: 'inline-block' } }
							value={ buttonText }
							onChange={ ( v ) =>
								setAttributes( { buttonText: v } )
							}
							placeholder={ __( 'Text nút…', 'laca' ) }
							allowedFormats={ [] }
						/>
					</div>
				</div>
			</section>
		</>
	);
}
