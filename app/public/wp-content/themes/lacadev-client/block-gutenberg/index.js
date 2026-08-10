/**
 * Gutenberg Blocks Entry Point — lacadev-client
 *
 * Trên client theme, các block được receive từ lacadev qua Block Sync Manager.
 * Mỗi block synced có build/ folder riêng với editor_script riêng.
 * Bundle này chỉ cần load AI Translation Plugin một lần cho toàn bộ editor.
 */

// AI Translation Plugin — dùng addFilter('editor.BlockEdit') để thêm
// panel "✨ Dịch bằng AI" vào sidebar của tất cả blocks.
import './ai-translate-plugin';

