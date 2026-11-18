import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ColorPalette,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	GOOGLE_FONT_OPTIONS,
	googleFontCssUrl,
} from '../../../block-gutenberg/utils/google-fonts-vi';

/**
 * Thêm thuộc tính lacaFontFamily, lacaFontSize, lacaTextColor cho core/quote & core/pullquote.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Modified settings.
 */
function addQuoteAttributes( settings, name ) {
	if ( name !== 'core/quote' && name !== 'core/pullquote' ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			lacaFontFamily: {
				type: 'string',
				default: '',
			},
			lacaFontSize: {
				type: 'number',
				default: 0,
			},
			lacaTextColor: {
				type: 'string',
				default: '',
			},
		},
	};
}
addFilter(
	'blocks.registerBlockType',
	'laca/quote-attributes',
	addQuoteAttributes
);

/**
 * Nạp font Google động vào document và iframe editor khi admin chọn.
 *
 * @param {string} fontFamily Tên font.
 */
function loadGoogleFontInEditor( fontFamily ) {
	if ( ! fontFamily ) {
		return;
	}
	const id =
		'laca-gf-' + fontFamily.toLowerCase().replace( /[^a-z0-9]/g, '-' );
	const href = googleFontCssUrl( fontFamily );

	const docs = [ document ];
	const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
	if ( iframe?.contentDocument ) {
		docs.push( iframe.contentDocument );
	}

	docs.forEach( ( doc ) => {
		if ( ! doc?.head || doc.getElementById( id ) ) {
			return;
		}
		const link = doc.createElement( 'link' );
		link.id = id;
		link.rel = 'stylesheet';
		link.href = href;
		doc.head.appendChild( link );
	} );
}

/**
 * Thêm bảng điều khiển InspectorControls cho trích dẫn.
 */
const withQuoteControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== 'core/quote' && props.name !== 'core/pullquote' ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const {
			lacaFontFamily = '',
			lacaFontSize = 0,
			lacaTextColor = '',
		} = attributes;

		if ( lacaFontFamily ) {
			loadGoogleFontInEditor( lacaFontFamily );
		}

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Kiểu chữ trích dẫn', 'laca' ) }
						initialOpen={ true }
					>
						<SelectControl
							label={ __( 'Phông chữ trích dẫn', 'laca' ) }
							value={ lacaFontFamily }
							options={ GOOGLE_FONT_OPTIONS }
							onChange={ ( value ) => {
								setAttributes( { lacaFontFamily: value } );
								if ( value ) {
									loadGoogleFontInEditor( value );
								}
							} }
							help={ __(
								'Chọn phông chữ Google Font hỗ trợ tiếng Việt cho trích dẫn.',
								'laca'
							) }
						/>
						<RangeControl
							label={ __( 'Cỡ chữ trích dẫn (px)', 'laca' ) }
							value={ lacaFontSize || undefined }
							min={ 14 }
							max={ 48 }
							allowReset={ true }
							resetFallbackValue={ 0 }
							onChange={ ( value ) =>
								setAttributes( { lacaFontSize: value || 0 } )
							}
						/>
						<p className="components-base-control__label">
							{ __( 'Màu chữ trích dẫn', 'laca' ) }
						</p>
						<ColorPalette
							value={ lacaTextColor }
							onChange={ ( value ) =>
								setAttributes( {
									lacaTextColor: value || '',
								} )
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withQuoteControls' );

addFilter( 'editor.BlockEdit', 'laca/with-quote-controls', withQuoteControls );

/**
 * Áp dụng style (font, cỡ chữ, màu) vào wrapper block trong editor để xem trước.
 */
const withQuoteStyle = createHigherOrderComponent( ( BlockListBlock ) => {
	return ( props ) => {
		if ( props.name !== 'core/quote' && props.name !== 'core/pullquote' ) {
			return <BlockListBlock { ...props } />;
		}

		const { attributes } = props;
		const {
			lacaFontFamily = '',
			lacaFontSize = 0,
			lacaTextColor = '',
		} = attributes;

		const customStyle = {};
		if ( lacaFontFamily ) {
			customStyle.fontFamily = `"${ lacaFontFamily }", serif`;
		}
		if ( lacaFontSize ) {
			customStyle.fontSize = `${ lacaFontSize }px`;
		}
		if ( lacaTextColor ) {
			customStyle.color = lacaTextColor;
		}

		const wrapperProps = {
			...props.wrapperProps,
			style: {
				...( props.wrapperProps?.style || {} ),
				...customStyle,
			},
		};

		return <BlockListBlock { ...props } wrapperProps={ wrapperProps } />;
	};
}, 'withQuoteStyle' );

addFilter( 'editor.BlockListBlock', 'laca/with-quote-style', withQuoteStyle );
