import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ColorPicker,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { hexToRgba } from '../../utils/style';
import previewImage from './preview.png';

// Dùng chung cho cả tiêu đề/mô tả (text-align thật) lẫn nút bấm (vị trí
// nút trong hàng — xem cách dùng riêng ở buttonAlign bên dưới).
const ALIGN_OPTIONS = [
	{ label: __( 'Trái', 'laca' ), value: 'left' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Phải', 'laca' ), value: 'right' },
	{ label: __( 'Căn đều (Justify)', 'laca' ), value: 'justify' },
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	// Sinh 1 ID cố định cho block ngay khi tạo — dùng để scope "Custom CSS
	// nút bấm" (mỗi block instance 1 ID riêng, tránh CSS đè lẫn nhau khi có
	// nhiều CTA Section trên cùng 1 trang). Chỉ set 1 lần khi còn rỗng.
	useEffect( () => {
		if ( ! attributes.__isPreview && ! attributes.blockId ) {
			setAttributes( {
				blockId: 'cta-' + clientId.slice( 0, 8 ),
			} );
		}
	}, [] );

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
		headlineAlign,
		descriptionAlign,
		buttonAlign,
		buttonCustomCss,
		textColor,
		buttonColor,
		bgColor,
		bgOpacity,
		blockId,
	} = attributes;

	const scopedButtonCss =
		blockId && buttonCustomCss
			? buttonCustomCss.replace(
					/__BUTTON__/g,
					`#${ blockId } .block-cta-section__link`
			  )
			: '';

	// Nút chỉ hiện khi đã có cả text lẫn đường dẫn — khớp đúng điều kiện ẩn
	// hiện ở render.php ngoài frontend, để canvas soạn thảo phản ánh đúng
	// những gì khách sẽ thấy (không có URL thì nút không tồn tại, không phải
	// chỉ ẩn đi bằng CSS).
	const showButton = !! ( buttonText && buttonLink );

	// "Căn đều" (justify) cho nút bấm không phải text-align thật (nút chỉ có
	// 1 dòng) mà hiểu là "giãn nút full-width" — còn lại dùng flex
	// justify-content để định vị nút trong hàng.
	const BUTTON_JUSTIFY = {
		left: 'flex-start',
		center: 'center',
		right: 'flex-end',
	};
	const btnWrapStyle =
		buttonAlign === 'justify'
			? { display: 'flex' }
			: { display: 'flex', justifyContent: BUTTON_JUSTIFY[ buttonAlign ] || 'center' };
	const btnLinkStyle =
		buttonAlign === 'justify'
			? { display: 'block', width: '100%', textAlign: 'center' }
			: { display: 'inline-block' };

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Căn lề', 'laca' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Căn tiêu đề', 'laca' ) }
						value={ headlineAlign }
						options={ ALIGN_OPTIONS }
						onChange={ ( v ) =>
							setAttributes( { headlineAlign: v } )
						}
					/>
					<SelectControl
						label={ __( 'Căn mô tả', 'laca' ) }
						value={ descriptionAlign }
						options={ ALIGN_OPTIONS }
						onChange={ ( v ) =>
							setAttributes( { descriptionAlign: v } )
						}
					/>
					<SelectControl
						label={ __( 'Căn nút bấm', 'laca' ) }
						value={ buttonAlign }
						options={ ALIGN_OPTIONS }
						onChange={ ( v ) =>
							setAttributes( { buttonAlign: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Nút bấm', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Nội dung nút', 'laca' ) }
						value={ buttonText }
						onChange={ ( v ) => setAttributes( { buttonText: v } ) }
						placeholder={ __( 'VD: Manifesto', 'laca' ) }
					/>
					<TextControl
						label={ __( 'Đường dẫn', 'laca' ) }
						value={ buttonLink }
						onChange={ ( v ) => setAttributes( { buttonLink: v } ) }
						placeholder="https://…"
						help={
							! buttonLink
								? __(
										'Chưa nhập đường dẫn — nút bấm sẽ không hiển thị (cả ở đây lẫn ngoài trang).',
										'laca'
								  )
								: ''
						}
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
					<TextareaControl
						label={ __( 'Custom CSS style nút bấm', 'laca' ) }
						help={ __(
							'Dùng __BUTTON__ để ám chỉ nút này, vd: __BUTTON__ { border-radius: 0; } hoặc __BUTTON__:hover { opacity: 0.7; }',
							'laca'
						) }
						value={ buttonCustomCss }
						onChange={ ( v ) =>
							setAttributes( { buttonCustomCss: v } )
						}
						rows={ 4 }
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
				id={ blockId || undefined }
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
						style={ { textAlign: headlineAlign } }
						value={ headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="div"
						className="block-cta-section__desc"
						style={ { textAlign: descriptionAlign } }
						value={ description }
						onChange={ ( v ) =>
							setAttributes( { description: v } )
						}
						placeholder={ __( 'Nhập mô tả…', 'laca' ) }
					/>
					{ showButton && (
					<>
					{ scopedButtonCss && <style>{ scopedButtonCss }</style> }
					<div className="block-cta-section__btn" style={ btnWrapStyle }>
						<RichText
							tagName="span"
							className="block-cta-section__link"
							style={ btnLinkStyle }
							value={ buttonText }
							onChange={ ( v ) =>
								setAttributes( { buttonText: v } )
							}
							placeholder={ __( 'Text nút…', 'laca' ) }
							allowedFormats={ [] }
						/>
					</div>
					</>
					) }
				</div>
			</section>
		</>
	);
}
