// output.publicPath = '' → chunk import() động sẽ resolve theo URL trang (sai).
// Lấy thư mục dist từ src của chính theme.js lúc chạy; phải import ĐẦU TIÊN
// trong index.js để gán trước khi có import() nào chạy.
/* global __webpack_public_path__:writable */
if ( document.currentScript && document.currentScript.src ) {
	// eslint-disable-next-line no-global-assign, camelcase
	__webpack_public_path__ = document.currentScript.src.replace( /[^/?#]*([?#].*)?$/, '' );
}
