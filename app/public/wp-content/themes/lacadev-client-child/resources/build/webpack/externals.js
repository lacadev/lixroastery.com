// Map import '@wordpress/x' sang biến global window.wp.x — BẮT BUỘC cho
// mọi file import package @wordpress/* bên ngoài webpack.blocks.js (entry
// 'editor'/'theme'/'admin'/'login'/'child' dùng chung file externals này,
// KHÔNG đi qua wp-scripts nên không tự có DependencyExtractionWebpackPlugin
// như build block). Thiếu mapping này, webpack sẽ đóng gói riêng 1 bản
// React/wp.hooks khác bản mà Block Editor core đang chạy — khiến
// addFilter('editor.BlockEdit', ...) gọi lên instance SAI, filter không
// bao giờ thực thi dù code không báo lỗi gì (xem resources/scripts/editor/
// ai-translate-block.js).
module.exports = {
    '@wordpress/element': ['wp', 'element'],
    '@wordpress/components': ['wp', 'components'],
    '@wordpress/compose': ['wp', 'compose'],
    '@wordpress/block-editor': ['wp', 'blockEditor'],
    '@wordpress/data': ['wp', 'data'],
    '@wordpress/hooks': ['wp', 'hooks'],
    '@wordpress/i18n': ['wp', 'i18n'],
};
