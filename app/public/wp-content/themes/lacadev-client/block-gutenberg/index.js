/**
 * Gutenberg Blocks Entry Point — lacadev-client
 *
 * Trên client theme, các block được receive từ lacadev qua Block Sync Manager.
 * Mỗi block synced có build/ folder riêng với editor_script riêng, nên
 * handle 'lacadev-gutenberg-blocks' build ra từ file này (dist/gutenberg/)
 * chỉ còn dùng cho vài block CŨ chưa có build riêng (xem
 * lacadev_register_custom_blocks() trong theme/setup/gutenberg-blocks.php,
 * nhánh "Backward compatibility").
 *
 * Panel "✨ Dịch bằng AI" ĐÃ CHUYỂN sang
 * resources/scripts/editor/ai-translate-block.js (bundle 'theme-editor-js-
 * bundle', entry 'editor') — bundle đó LUÔN được enqueue trên mọi màn hình
 * Block Editor (enqueue_block_editor_assets), còn handle
 * 'lacadev-gutenberg-blocks' KHÔNG được enqueue khi mọi block hiện tại đều
 * đã có build riêng (đúng trường hợp của site này) — đặt plugin ở đây
 * trước đây khiến nó không bao giờ thực sự tải được trên trình duyệt.
 */

