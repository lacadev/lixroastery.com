import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Newsletter', 'laca' ) }
				title={ attributes.heading || __( 'Đăng ký nhận tin', 'laca' ) }
				columns={ 1 }
			/>
		);
	}

	const { heading, placeholder, buttonText } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Nội dung', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Placeholder ô email', 'laca' ) }
						value={ placeholder }
						onChange={ ( v ) =>
							setAttributes( { placeholder: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="container-fluid">
					<hr className="block-newsletter-signup__rule" />
					<div className="block-newsletter-signup__form">
						<RichText
							tagName="p"
							className="block-newsletter-signup__heading"
							value={ heading }
							onChange={ ( v ) =>
								setAttributes( { heading: v } )
							}
							placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
							allowedFormats={ [] }
						/>
						<div className="block-newsletter-signup__field">
							<input
								type="email"
								className="block-newsletter-signup__input"
								placeholder={ placeholder }
								disabled
							/>
							<RichText
								tagName="span"
								className="block-newsletter-signup__submit"
								value={ buttonText }
								onChange={ ( v ) =>
									setAttributes( { buttonText: v } )
								}
								placeholder={ __( 'Subscribe', 'laca' ) }
								allowedFormats={ [] }
							/>
						</div>
					</div>
					<hr className="block-newsletter-signup__rule" />
				</div>
			</section>
		</>
	);
}
