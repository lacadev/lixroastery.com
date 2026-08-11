import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextareaControl,
	Button,
	ColorPicker,
	RangeControl,
	RadioControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

function ImagePicker( { imageUrl, imageId, onSelect, label } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				onSelect={ onSelect }
				allowedTypes={ [ 'image' ] }
				value={ imageId }
				render={ ( { open } ) => (
					<div style={ { marginBottom: 8 } }>
						{ label && (
							<p
								style={ {
									fontSize: 11,
									color: '#888',
									marginBottom: 4,
								} }
							>
								{ label }
							</p>
						) }
						{ imageUrl && (
							<img
								src={ imageUrl }
								alt=""
								style={ {
									width: '100%',
									maxHeight: 120,
									objectFit: 'cover',
									marginBottom: 4,
									borderRadius: 4,
								} }
							/>
						) }
						<Button
							variant="secondary"
							onClick={ open }
							style={ { fontSize: 11 } }
						>
							{ imageUrl
								? __( 'Đổi ảnh', 'laca' )
								: __( 'Chọn ảnh', 'laca' ) }
						</Button>
					</div>
				) }
			/>
		</MediaUploadCheck>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Top Hero', 'laca' ) }
				title={ attributes.headline || __( 'Hero banner', 'laca' ) }
				columns={ 1 }
				image={ previewImage }
			/>
		);
	}

	const {
		heroImageId,
		heroImageUrl,
		headline,
		description,
		overlayColor,
		overlayOpacity,
		heightMode,
		minHeight,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Ảnh nền', 'laca' ) }
					initialOpen={ true }
				>
					<ImagePicker
						imageUrl={ heroImageUrl }
						imageId={ heroImageId }
						onSelect={ ( media ) =>
							setAttributes( {
								heroImageId: media.id,
								heroImageUrl: media.url,
							} )
						}
					/>
					<RadioControl
						label={ __( 'Chiều cao ảnh', 'laca' ) }
						selected={ heightMode }
						options={ [
							{
								label: __(
									'Nguyên kích thước (100%, tự động)',
									'laca'
								),
								value: 'full',
							},
							{
								label: __( 'Cố định theo px', 'laca' ),
								value: 'custom',
							},
						] }
						onChange={ ( v ) => setAttributes( { heightMode: v } ) }
					/>
					{ heightMode === 'custom' && (
						<RangeControl
							label={ __( 'Chiều cao tối thiểu (px)', 'laca' ) }
							value={ minHeight }
							min={ 360 }
							max={ 1200 }
							step={ 10 }
							onChange={ ( v ) =>
								setAttributes( { minHeight: v } )
							}
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Nội dung', 'laca' ) }
					initialOpen={ true }
				>
					<TextareaControl
						label={ __(
							'Tiêu đề (Enter để xuống dòng tùy ý)',
							'laca'
						) }
						value={ headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						rows={ 3 }
					/>
					<TextareaControl
						label={ __( 'Mô tả ngắn', 'laca' ) }
						value={ description }
						onChange={ ( v ) =>
							setAttributes( { description: v } )
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
						{ __( 'Màu lớp phủ (overlay)', 'laca' ) }
					</p>
					<ColorPicker
						color={ overlayColor }
						onChange={ ( v ) =>
							setAttributes( { overlayColor: v } )
						}
						enableAlpha={ false }
					/>
					<RangeControl
						label={ __( 'Độ đậm lớp phủ (%)', 'laca' ) }
						value={ overlayOpacity }
						min={ 0 }
						max={ 90 }
						step={ 5 }
						onChange={ ( v ) =>
							setAttributes( { overlayOpacity: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/top-hero-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
