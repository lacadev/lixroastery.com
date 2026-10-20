// SweetAlert2 (~77KB) chỉ dùng khi gửi form (liên hệ, bình luận, đăng ký) →
// tải động thay vì nhét vào theme.js của mọi trang (PageSpeed mobile).
let swalPromise = null;

export function loadSwal() {
	if ( window.Swal ) {
		return Promise.resolve( window.Swal );
	}
	if ( ! swalPromise ) {
		swalPromise = import( /* webpackChunkName: "sweetalert2" */ 'sweetalert2' ).then(
			( mod ) => {
				// Script inline của [laca_contact_form] (ContactFormAjaxHandler)
				// đọc window.Swal, có fallback nếu chưa có.
				window.Swal = mod.default;
				return mod.default;
			}
		);
	}
	return swalPromise;
}

// Proxy giữ nguyên API đang dùng (Swal.fire/close/showLoading): mọi lệnh đều
// chạy sau khi thư viện tải xong và theo đúng thứ tự gọi (cùng 1 promise), nên
// fire(loading) → close() → fire(kết quả) không bị đảo khi mạng chậm.
export const Swal = {
	fire: ( ...args ) => loadSwal().then( ( S ) => S.fire( ...args ) ),
	close: () => loadSwal().then( ( S ) => S.close() ),
	showLoading: () => loadSwal().then( ( S ) => S.showLoading() ),
};

// Tải sẵn khi người dùng bắt đầu nhập 1 form bất kỳ → lúc submit thường đã có.
document.addEventListener(
	'focusin',
	( e ) => {
		if ( e.target && e.target.closest && e.target.closest( 'form' ) ) {
			loadSwal();
		}
	},
	{ passive: true }
);
