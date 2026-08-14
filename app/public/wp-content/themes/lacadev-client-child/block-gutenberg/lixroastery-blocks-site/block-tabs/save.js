import { InnerBlocks } from '@wordpress/block-editor';

// Block này là dynamic (có render.php) — wrapper thật (container-fluid,
// id duy nhất, nav, prev/next) được dựng 100% ở PHP qua
// get_block_wrapper_attributes(), không dùng useBlockProps.save() ở đây.
// Chỉ giữ đúng <InnerBlocks.Content /> để các tab-panel con được serialize
// đúng vị trí trong post_content.
export default function save() {
	return <InnerBlocks.Content />;
}
