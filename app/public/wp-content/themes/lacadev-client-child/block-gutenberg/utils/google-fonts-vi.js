// Danh sách Google Font có hỗ trợ subset tiếng Việt, dùng cho ComboboxControl
// chọn font riêng (vd variant "quote" của block-content-text). Danh sách dựa
// trên các font phổ biến đã biết hỗ trợ tiếng Việt — nên đối chiếu lại
// fonts.google.com (filter "Vietnamese") nếu cần thêm font mới.
//
// ⚠️ Có bản PHP mirror CÙNG nội dung ở
// app/helpers/google-fonts-vi.php (dùng whitelist-validate ở render.php) —
// thêm/bớt font thì sửa CẢ 2 file.
export const GOOGLE_FONTS_VI = [
	'Afacad',
	'Archivo',
	'Barlow',
	'Be Vietnam Pro',
	'Fira Sans',
	'IBM Plex Sans',
	'Inter',
	'Josefin Sans',
	'Lexend',
	'Lora',
	'Manrope',
	'Merriweather',
	'Montserrat',
	'Mulish',
	'Noto Sans',
	'Nunito',
	'Open Sans',
	'Oswald',
	'Playfair Display',
	'Plus Jakarta Sans',
	'Poppins',
	'PT Sans',
	'PT Serif',
	'Public Sans',
	'Quicksand',
	'Raleway',
	'Roboto',
	'Roboto Slab',
	'Rubik',
	'Source Sans 3',
	'Work Sans',
];

export const GOOGLE_FONT_OPTIONS = [
	{ value: '', label: '— Mặc định (theo site) —' },
	...GOOGLE_FONTS_VI.map( ( name ) => ( { value: name, label: name } ) ),
];

export function googleFontCssUrl( family ) {
	return (
		'https://fonts.googleapis.com/css2?family=' +
		family.replace( / /g, '+' ) +
		':wght@400&display=swap'
	);
}
