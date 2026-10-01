import { InnerBlocks } from '@wordpress/block-editor';

// Block dynamic (render.php quyết định markup thật ở frontend) — save() chỉ
// cần giữ lại InnerBlocks.Content để các block con serialize đúng vào
// post_content, không cần bọc thêm div nào (render.php sẽ tự bọc lại).
export default function save() {
	return <InnerBlocks.Content />;
}
