import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import { PanelBody, RadioControl, SelectControl } from '@wordpress/components';
import { useRef, useEffect } from '@wordpress/element';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';
import {
	GOOGLE_FONT_OPTIONS,
	googleFontCssUrl,
} from '../../utils/google-fonts-vi';

// Section ngoài LUÔN container-fluid — độ rộng CỘT nội dung điều chỉnh riêng
// qua maxWidth (%) + contentAlign (giống hệt block Container), KHÁC với
// textAlign (chỉ canh chữ bên trong cột, xem panel "Kiểu hiển thị" bên dưới).
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

const QUOTE_FONT_SIZE_KEYS = {
	pc: 'quoteFontSize',
	tablet: 'quoteFontSizeTablet',
	mobile: 'quoteFontSizeMobile',
};

// id riêng để tìm/update lại đúng thẻ <link> đã chèn, tránh chèn trùng mỗi
// lần re-render.
const QUOTE_FONT_LINK_ID = 'block-content-text-quote-google-font';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps( { className: 'container-fluid' } );

	const {
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		variant,
		textAlign,
		content,
		quoteFontFamily,
		quoteFontSize,
		quoteFontSizeTablet,
		quoteFontSizeMobile,
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

	const quoteStyle = {
		textAlign,
		'--quote-font-family': quoteFontFamily || undefined,
		'--quote-fs-pc': `${ quoteFontSize }px`,
		'--quote-fs-tablet': `${ quoteFontSizeTablet }px`,
		'--quote-fs-mobile': `${ quoteFontSizeMobile }px`,
	};

	// Nạp Google Font đã chọn vào ĐÚNG document của iframe editor (không phải
	// document admin ngoài cùng) — lấy qua ownerDocument của 1 node thật nằm
	// trong canvas block, nếu không iframe sẽ không thấy font và hiển thị
	// fallback thay vì font vừa chọn.
	const sectionRef = useRef( null );
	useEffect( () => {
		if ( variant !== 'quote' ) {
			return;
		}
		const ownerDocument = sectionRef.current?.ownerDocument;
		if ( ! ownerDocument ) {
			return;
		}
		let link = ownerDocument.getElementById( QUOTE_FONT_LINK_ID );
		if ( ! quoteFontFamily ) {
			link?.remove();
			return;
		}
		if ( ! link ) {
			link = ownerDocument.createElement( 'link' );
			link.id = QUOTE_FONT_LINK_ID;
			link.rel = 'stylesheet';
			ownerDocument.head.appendChild( link );
		}
		link.href = googleFontCssUrl( quoteFontFamily );
	}, [ variant, quoteFontFamily ] );

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
					title={ __( 'Căn lề', 'laca' ) }
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
					<RadioControl
						label={ __( 'Căn chữ bên trong', 'laca' ) }
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

					{ variant === 'quote' && (
						<>
							<SelectControl
								label={ __(
									'Font chữ trích dẫn (Google Fonts)',
									'laca'
								) }
								help={ __(
									'Chọn khác mặc định sẽ tải thêm 1 font từ Google Fonts CDN (ảnh hưởng nhẹ tốc độ tải trang). Dùng mũi tên lên/xuống để xem preview đổi ngay.',
									'laca'
								) }
								value={ quoteFontFamily }
								options={ GOOGLE_FONT_OPTIONS }
								onChange={ ( v ) =>
									setAttributes( {
										quoteFontFamily: v || '',
									} )
								}
							/>
							<ResponsiveRangeControl
								label={ __( 'Cỡ chữ trích dẫn (px)', 'laca' ) }
								min={ 16 }
								max={ 64 }
								valuesByDevice={ {
									pc: quoteFontSize,
									tablet: quoteFontSizeTablet,
									mobile: quoteFontSizeMobile,
								} }
								onChange={ ( device, v ) =>
									setAttributes( {
										[ QUOTE_FONT_SIZE_KEYS[ device ] ]: v,
									} )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps } ref={ sectionRef }>
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
						style={
							variant === 'quote' ? quoteStyle : { textAlign }
						}
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
