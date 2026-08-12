import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ColorPicker,
	RangeControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'CTA Section', 'laca' ) }
				title={ attributes.headlineLine1 || __( 'Kêu gọi hành động', 'laca' ) }
				columns={ 1 }
			/>
		);
	}

	const {
		headlineLine1,
		headlineLine2,
		description,
		buttonText,
		buttonLink,
		textColor,
		buttonColor,
		bgColor,
		bgOpacity,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Nội dung', 'laca' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Tiêu đề — dòng 1', 'laca' ) }
						value={ headlineLine1 }
						onChange={ ( v ) => setAttributes( { headlineLine1: v } ) }
					/>
					<TextControl
						label={ __( 'Tiêu đề — dòng 2', 'laca' ) }
						value={ headlineLine2 }
						onChange={ ( v ) => setAttributes( { headlineLine2: v } ) }
					/>
					<TextareaControl
						label={ __( 'Mô tả', 'laca' ) }
						value={ description }
						onChange={ ( v ) => setAttributes( { description: v } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Nút bấm', 'laca' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Text nút', 'laca' ) }
						value={ buttonText }
						onChange={ ( v ) => setAttributes( { buttonText: v } ) }
					/>
					<TextControl
						label={ __( 'Đường dẫn', 'laca' ) }
						value={ buttonLink }
						onChange={ ( v ) => setAttributes( { buttonLink: v } ) }
						placeholder="https://…"
					/>
				</PanelBody>

				<PanelBody title={ __( 'Giao diện', 'laca' ) } initialOpen={ false }>
					<p style={ { fontSize: '0.8rem', fontWeight: 600, marginBottom: '0.5rem' } }>
						{ __( 'Màu chữ', 'laca' ) }
					</p>
					<ColorPicker
						color={ textColor }
						onChange={ ( v ) => setAttributes( { textColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { fontSize: '0.8rem', fontWeight: 600, margin: '1rem 0 0.5rem' } }>
						{ __( 'Màu nút', 'laca' ) }
					</p>
					<ColorPicker
						color={ buttonColor }
						onChange={ ( v ) => setAttributes( { buttonColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { fontSize: '0.8rem', fontWeight: 600, margin: '1rem 0 0.5rem' } }>
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

			<div { ...blockProps }>
				<ServerSideRender block="lacadev/cta-section-block" attributes={ attributes } />
			</div>
		</>
	);
}
