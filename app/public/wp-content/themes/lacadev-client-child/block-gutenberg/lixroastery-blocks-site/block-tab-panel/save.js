import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const blockProps = useBlockProps.save( {
		className: 'tab-panel',
		'data-tab-title': attributes.tabTitle,
	} );

	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}
