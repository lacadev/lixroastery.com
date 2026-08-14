import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const { tabTitle } = attributes;

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Tab Panel', 'laca' ) }
				title={ tabTitle || __( 'Nội dung 1 tab', 'laca' ) }
				columns={ 1 }
			/>
		);
	}

	const blockProps = useBlockProps( { className: 'tab-panel' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'tab-panel__content' },
		{
			templateLock: false,
			template: [ [ 'core/paragraph', { placeholder: __( 'Nội dung tab…', 'laca' ) } ] ],
		}
	);

	return (
		<div { ...blockProps }>
			<RichText
				tagName="div"
				className="tab-panel__title-editor"
				value={ tabTitle }
				onChange={ ( v ) => setAttributes( { tabTitle: v } ) }
				placeholder={ __( 'Tên tab…', 'laca' ) }
				allowedFormats={ [] }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
