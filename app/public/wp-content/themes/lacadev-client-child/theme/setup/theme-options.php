<?php
/**
 * Theme Options.
 *
 * Here, you can register Theme Options using the Carbon Fields library.
 *
 * @link    https://carbonfields.net/docs/containers-theme-options/
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Field;

// Câu chữ mặc định của tab Newsletter THEO NGÔN NGỮ đang sửa — dùng chung với lúc hiển thị (NewsletterTexts).
$nlDefaults = NewsletterTexts::defaults(currentLanguage());

// Code Apps Script mẫu (hiện trong tab Newsletter) — xem NewsletterSync::pushSheet()/pushSheetBatch().
$nlAppsScript = <<<'JS'
const SECRET = 'doi-thanh-chuoi-bi-mat';

// Mỗi email 1 dòng: đã có thì cập nhật trạng thái, chưa có thì thêm mới,
// "deleted" (admin xoá trên website) thì xoá dòng.
function doPost(e) {
  const data = JSON.parse(e.postData.contents);
  if ((data.secret || data.batch_secret) !== SECRET) {
    return ContentService.createTextOutput('forbidden');
  }
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
    (data.rows || [data]).forEach(function (item) { saveRow(sheet, item); });
  } finally {
    lock.releaseLock();
  }
  return ContentService.createTextOutput('ok');
}

function saveRow(sheet, d) {
  const last = sheet.getLastRow();
  const emails = last > 0 ? sheet.getRange(1, 2, last, 1).getValues().flat() : [];
  const index = emails.indexOf(d.email);
  if (d.status === 'deleted') {
    if (index >= 0) sheet.deleteRow(index + 1);
    return;
  }
  const row = [d.created_at, d.email, d.lang, d.source, d.status, d.updated_at];
  if (index >= 0) {
    sheet.getRange(index + 1, 1, 1, row.length).setValues([row]);
  } else {
    sheet.appendRow(row);
  }
}
JS;

$optionsPage = Container::make('theme_options', __('Laca Theme', 'laca'))
	->set_page_file('app-theme-options.php')
	->set_page_menu_position(3.1)
	->add_tab(__('Branding | Thương hiệu', 'laca'), [
		Field::make('html', 'branding_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Thương hiệu</p><p style="margin:0;font-size:13px;color:#374151">Thiết lập màu sắc và logo dùng chung cho toàn bộ website. Các màu và logo ở đây sẽ hiển thị đồng bộ trên mọi trang, mọi giao diện của site.</p></div>'),

		Field::make('image', 'logo', __('Logo', 'laca'))
			->set_width(33.33),
		Field::make('image', 'logo_footer', __('Logo Footer', 'laca'))
			->set_width(33.33),
		Field::make('image', 'default_image', __('Default image | Hình ảnh mặc định', 'laca'))
			->set_width(33.33),
	])

	->add_tab(__('Contact | Liên hệ', 'laca'), [
		Field::make('html', 'contact_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Liên hệ</p><p style="margin:0;font-size:13px;color:#374151">Nhập thông tin liên hệ của công ty (địa chỉ, số điện thoại, hotline, giờ làm việc, mạng xã hội). Các thông tin này sẽ tự động hiển thị ở footer và các khối liên hệ trên site.</p></div>'),

		Field::make('html', 'info', __('', 'laca'))
			->set_html('----<i> Information | Thông tin </i>----'),

		Field::make('text', 'address' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'Address | Địa chỉ'),
		Field::make('textarea', 'googlemap' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Google map'),

		Field::make('complex', 'phone_numbers' . currentLanguage(), __('Số hotline', 'laca'))->set_width(50)
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'name', __('', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'Tên hotline'),
				Field::make('text', 'phone', __('', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'Số điện thoại'),
			])->set_header_template('<% if (name) { %><%- name %><% } %>'),

		Field::make('complex', 'address_locations' . currentLanguage(), __('Địa điểm', 'laca'))->set_width(50)
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'branch', __('', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'Branch | Chi nhánh'),
				Field::make('textarea', 'address', __('', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'Address | Địa chỉ'),
			])->set_header_template('<% if (branch) { %><%- branch %><% } %>'),

		Field::make('text', 'email' . currentLanguage(), __('', 'laca'))->set_width(33.33)
			->set_attribute('placeholder', 'Email'),
		Field::make('text', 'phone_number' . currentLanguage(), __('', 'laca'))->set_width(33.33)
			->set_attribute('placeholder', 'Phone number | Số điện thoại'),
		Field::make('text', 'hour_working' . currentLanguage(), __('', 'laca'))->set_width(33.33)
			->set_attribute('placeholder', 'Hour working | Giờ làm việc'),
		Field::make('html', 'socials', __('', 'laca'))
			->set_html('----<i> Socials | Mạng xã hội </i>----'),
		Field::make('text', 'facebook' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'facebook'),
		Field::make('text', 'linkedin' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'linkedin'),
		Field::make('text', 'instagram' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'instagram'),
		Field::make('text', 'tiktok' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'tiktok'),
		Field::make('text', 'youtube' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'youtube'),
		Field::make('text', 'zalo' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'zalo'),
	])

	->add_tab(__('🦶 Footer', 'laca'), [
		Field::make('html', 'footer_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Footer</p><p style="margin:0;font-size:13px;color:#374151">Thông tin công ty và các cột liên kết hiển thị ở footer cuối trang. Địa chỉ, email, mạng xã hội đã nhập ở tab <strong>Contact | Liên hệ</strong> — footer tự lấy lại, không cần nhập lại ở đây.</p></div>'),

		Field::make('text', 'footer_company_name' . currentLanguage(), __('Tên công ty', 'laca'))
			->set_width(50)
			->set_attribute('placeholder', 'VD: Công ty TNHH ABC'),
		Field::make('text', 'footer_business_reg' . currentLanguage(), __('Giấy CNĐKKD số', 'laca'))
			->set_width(50)
			->set_attribute('placeholder', 'VD: 0123456789 do Sở KH&ĐT ... cấp ngày ...'),

		Field::make('complex', 'footer_links_col1' . currentLanguage(), __('Cột liên kết 1', 'laca'))
			->set_width(50)
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'name', __('Tên hiển thị', 'laca'))->set_width(50),
				Field::make('text', 'url', __('Đường dẫn', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'https://…'),
			])
			->set_header_template('<% if (name) { %><%- name %><% } %>')
			->set_default_value([
				['name' => 'Điều khoản sử dụng', 'url' => '#'],
				['name' => 'Chính sách bảo mật', 'url' => '#'],
				['name' => 'Chính sách bán hàng', 'url' => '#'],
				['name' => 'Chính sách vận chuyển', 'url' => '#'],
			]),

		Field::make('complex', 'footer_links_col2' . currentLanguage(), __('Cột liên kết 2', 'laca'))
			->set_width(50)
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'name', __('Tên hiển thị', 'laca'))->set_width(50),
				Field::make('text', 'url', __('Đường dẫn', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'https://…'),
			])
			->set_header_template('<% if (name) { %><%- name %><% } %>')
			->set_default_value([
				['name' => 'Tuyển dụng', 'url' => '#'],
				['name' => 'Liên hệ', 'url' => '#'],
				['name' => 'Câu hỏi thường gặp', 'url' => '#'],
				['name' => 'Sơ đồ trang web', 'url' => '#'],
			]),

		Field::make('complex', 'footer_badges' . currentLanguage(), __('Đăng ký / Chứng nhận', 'laca'))
			->set_width(50)
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'name', __('Tên hiển thị', 'laca'))->set_width(50),
				Field::make('text', 'url', __('Đường dẫn', 'laca'))->set_width(50)
					->set_attribute('placeholder', 'https://…'),
			])
			->set_header_template('<% if (name) { %><%- name %><% } %>'),

		Field::make('textarea', 'footer_dmca_badge', __('Huy hiệu DMCA / Mã nhúng chứng nhận (HTML)', 'laca'))
			->set_width(50)
			->set_rows(6)
			->set_help_text(__('Mã nhúng HTML huy hiệu DMCA hoặc chứng nhận', 'laca')),

		Field::make('html', 'footer_stats_section_heading', __('', 'laca'))
			->set_html('<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:14px 16px;margin:24px 0 12px"><p style="margin:0 0 6px;font-weight:600;color:#166534">📊 Stats Info | Khối chỉ số cố định ở Footer</p><p style="margin:0;font-size:13px;color:#374151">Khối thông tin chỉ số (Stats Info) hiển thị cố định phía trên footer ở tất cả các trang, tương tự XLIII Coffee.</p></div>'),

		Field::make('checkbox', 'footer_stats_enable' . currentLanguage(), __('Hiển thị Stats Info ở footer', 'laca'))
			->set_option_value('yes')
			->set_default_value(true)
			->set_width(50),

		Field::make('select', 'footer_stats_columns' . currentLanguage(), __('Số lượng cột / hàng', 'laca'))
			->set_width(50)
			->set_options([
				'4' => '4 cột',
				'3' => '3 cột',
				'2' => '2 cột',
			])
			->set_default_value('4'),

		Field::make('select', 'footer_stats_max_width' . currentLanguage(), __('Kích thước tối đa khung (%)', 'laca'))
			->set_width(50)
			->set_options([
				'100' => '100% (Mặc định full-width)',
				'90' => '90%',
				'80' => '80%',
				'75' => '75%',
				'70' => '70%',
				'60' => '60%',
				'50' => '50%',
			])
			->set_default_value('100'),

		Field::make('select', 'footer_stats_content_align' . currentLanguage(), __('Vị trí khung', 'laca'))
			->set_width(50)
			->set_options([
				'center' => __('Giữa (Center)', 'laca'),
				'left' => __('Trái (Left)', 'laca'),
				'right' => __('Phải (Right)', 'laca'),
			])
			->set_default_value('center'),

		Field::make('select', 'footer_stats_title_align' . currentLanguage(), __('Căn lề tiêu đề (số + nhãn)', 'laca'))
			->set_width(50)
			->set_options([
				'left' => __('Trái (Left)', 'laca'),
				'center' => __('Giữa (Center)', 'laca'),
				'right' => __('Phải (Right)', 'laca'),
				'justify' => __('Căn đều (Justify)', 'laca'),
			])
			->set_default_value('left'),

		Field::make('select', 'footer_stats_description_align' . currentLanguage(), __('Căn lề mô tả', 'laca'))
			->set_width(50)
			->set_options([
				'left' => __('Trái (Left)', 'laca'),
				'center' => __('Giữa (Center)', 'laca'),
				'right' => __('Phải (Right)', 'laca'),
				'justify' => __('Căn đều (Justify)', 'laca'),
			])
			->set_default_value('justify'),

		Field::make('textarea', 'footer_stats_intro_content' . currentLanguage(), __('Đoạn văn mở đầu (không bắt buộc)', 'laca'))
			->set_attribute('placeholder', 'VD: Biến đổi khí hậu, sự đứt gãy chuỗi cung ứng...')
			->set_help_text(__('Để trống nếu chỉ muốn hiển thị 4 cột chỉ số.', 'laca')),

		Field::make('text', 'footer_stats_intro_btn_text' . currentLanguage(), __('Chữ nút bấm (không bắt buộc)', 'laca'))
			->set_width(50)
			->set_attribute('placeholder', 'VD: MANIFESTO'),

		Field::make('text', 'footer_stats_intro_btn_url' . currentLanguage(), __('Link nút bấm', 'laca'))
			->set_width(50)
			->set_attribute('placeholder', 'VD: /vi/manifesto/'),

		Field::make('complex', 'footer_stats_items' . currentLanguage(), __('Danh sách chỉ số', 'laca'))
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'number', __('Chỉ số (Số)', 'laca'))
					->set_width(30)
					->set_attribute('placeholder', 'VD: +08, +20, 03...'),
				Field::make('text', 'label', __('Tiêu đề / Nhãn', 'laca'))
					->set_width(35)
					->set_attribute('placeholder', 'VD: NĂM KINH NGHIỆM TRONG NGÀNH CÀ PHÊ'),
				Field::make('text', 'link', __('Đường dẫn (URL)', 'laca'))
					->set_width(35)
					->set_attribute('placeholder', 'https://… (không bắt buộc)'),
				Field::make('textarea', 'description', __('Mô tả chi tiết', 'laca'))
					->set_attribute('placeholder', 'VD: Tám năm tích lũy kinh nghiệm...'),
			])
			->set_header_template('<% if (number) { %><%- number %> - <%- label %><% } else if (label) { %><%- label %><% } %>')
			->set_default_value([
				[
					'number' => '+08',
					'label' => 'NĂM KINH NGHIỆM TRONG NGÀNH CÀ PHÊ',
					'description' => 'Tám năm tích lũy kinh nghiệm trong tuyển chọn nguồn cà phê, cảm quan và dịch vụ đã trở thành nền tảng vận hành của LIX.',
					'link' => '',
				],
				[
					'number' => '+20',
					'label' => 'MẠNG LƯỚI ĐỐI TÁC TOÀN CẦU',
					'description' => 'Được xây dựng từ mạng lưới tám năm của XLIII Coffee, kết nối những mối quan hệ tin cậy xuyên suốt vùng trồng, hoạt động tìm nguồn và hợp tác trong ngành cà phê.',
					'link' => '',
				],
				[
					'number' => '03',
					'label' => 'HỆ THỐNG RANG CÀ PHÊ CỐT LÕI',
					'description' => 'Origin, Lab và Roastery vận hành như một hệ thống thống nhất, xuyên suốt từ khâu đánh giá cà phê nhân xanh đến rang sản xuất.',
					'link' => '',
				],
				[
					'number' => '04',
					'label' => 'ĐIỂM KIỂM SOÁT CHẤT LƯỢNG',
					'description' => 'Đánh giá cà phê nhân xanh, giám sát quá trình rang, phân loại sau rang và kiểm định cảm quan giúp duy trì tính nhất quán trong từng chu kỳ sản xuất.',
					'link' => '',
				],
			]),
	])

	->add_tab(__('📧 Newsletter', 'laca'), [
		Field::make('html', 'newsletter_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Newsletter ở footer</p><p style="margin:0;font-size:13px;color:#374151">Khách nhập email ở footer và bấm Subscribe là <strong>đăng ký luôn</strong>, không cần xác nhận. Website gửi 1 <strong>email cảm ơn</strong> (qua SMTP ở Laca Admin → SMTP), cuối email có link <strong>huỷ đăng ký</strong>. Xem danh sách theo trạng thái và xuất CSV tại menu <strong>Newsletter</strong> bên trái.</p></div>'),

		Field::make('checkbox', 'newsletter_enable' . currentLanguage(), __('Hiển thị form Newsletter ở footer', 'laca'))
			->set_option_value('yes')
			->set_default_value(true),

		Field::make('separator', 'newsletter_sep_form', __('Form ở footer', 'laca')),
		Field::make('text', 'newsletter_title' . currentLanguage(), __('Tiêu đề', 'laca'))
			->set_default_value($nlDefaults['newsletter_title']),
		Field::make('text', 'newsletter_placeholder' . currentLanguage(), __('Chữ gợi ý trong ô email', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_placeholder']),
		Field::make('text', 'newsletter_button' . currentLanguage(), __('Chữ nút bấm', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_button']),
		Field::make('textarea', 'newsletter_consent' . currentLanguage(), __('Dòng đồng ý nhận email', 'laca'))
			->set_rows(2)
			->set_default_value($nlDefaults['newsletter_consent'])
			->set_help_text(__('Hiện nhỏ dưới form — cần có theo Nghị định 13/2023 về dữ liệu cá nhân. Để trống để ẩn.', 'laca')),
		Field::make('text', 'newsletter_privacy_url' . currentLanguage(), __('Link chính sách bảo mật (không bắt buộc)', 'laca'))
			->set_attribute('placeholder', 'VD: /chinh-sach-bao-mat/'),

		Field::make('text', 'newsletter_msg_success' . currentLanguage(), __('Thông báo: đăng ký thành công', 'laca'))
			->set_default_value($nlDefaults['newsletter_msg_success']),
		Field::make('text', 'newsletter_msg_exists' . currentLanguage(), __('Thông báo: email đã đăng ký', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_msg_exists']),
		Field::make('text', 'newsletter_msg_invalid' . currentLanguage(), __('Thông báo: email không hợp lệ', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_msg_invalid']),
		Field::make('text', 'newsletter_msg_error' . currentLanguage(), __('Thông báo: lỗi chung', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_msg_error']),
		Field::make('text', 'newsletter_msg_limit' . currentLanguage(), __('Thông báo: gửi quá nhiều lần', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_msg_limit'])
			->set_help_text(__('Mỗi IP được gửi tối đa 5 lần trong 10 phút.', 'laca')),
		Field::make('text', 'newsletter_msg_captcha' . currentLanguage(), __('Thông báo: không qua được reCAPTCHA', 'laca'))
			->set_default_value($nlDefaults['newsletter_msg_captcha'])
			->set_help_text(__('reCAPTCHA v3 tự bật cho form này khi đã nhập đủ Site Key + Secret Key ở Laca Admin → Google reCAPTCHA.', 'laca')),

		Field::make('separator', 'newsletter_sep_mail', __('Email cảm ơn sau khi đăng ký', 'laca')),
		Field::make('text', 'newsletter_mail_subject' . currentLanguage(), __('Tiêu đề email', 'laca'))
			->set_default_value($nlDefaults['newsletter_mail_subject']),
		Field::make('textarea', 'newsletter_mail_body' . currentLanguage(), __('Nội dung email', 'laca'))
			->set_rows(4)
			->set_default_value($nlDefaults['newsletter_mail_body'])
			->set_help_text(__('Mỗi dòng là 1 đoạn. Link huỷ đăng ký được tự thêm ở cuối email.', 'laca')),

		Field::make('separator', 'newsletter_sep_pages', __('Trang sau khi bấm link huỷ đăng ký trong email', 'laca')),
		Field::make('text', 'newsletter_page_unsubscribe' . currentLanguage(), __('Hỏi lại trước khi huỷ', 'laca'))
			->set_width(70)
			->set_default_value($nlDefaults['newsletter_page_unsubscribe']),
		Field::make('text', 'newsletter_page_unsubscribe_button' . currentLanguage(), __('Chữ nút / link huỷ đăng ký', 'laca'))
			->set_width(30)
			->set_default_value($nlDefaults['newsletter_page_unsubscribe_button']),
		Field::make('text', 'newsletter_page_unsubscribed' . currentLanguage(), __('Đã huỷ đăng ký', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_page_unsubscribed']),
		Field::make('text', 'newsletter_page_invalid' . currentLanguage(), __('Link không hợp lệ', 'laca'))
			->set_width(50)
			->set_default_value($nlDefaults['newsletter_page_invalid']),

		Field::make('html', 'newsletter_sheet_heading', __('', 'laca'))
			->set_html(
				'<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:14px 16px;margin:24px 0 12px">'
				. '<p style="margin:0 0 6px;font-weight:600;color:#166534">📊 Đồng bộ Google Sheet (không bắt buộc)</p>'
				. '<ol style="margin:0 0 8px 18px;font-size:13px;color:#374151">'
				. '<li>Tạo 1 Google Sheet, dòng 1 đặt tiêu đề: <code>Thời gian đăng ký | Email | Ngôn ngữ | Trang đăng ký | Trạng thái | Cập nhật lúc</code>.</li>'
				. '<li>Vào <strong>Tiện ích mở rộng → Apps Script</strong>, xoá code cũ, dán đoạn code bên dưới, đổi <code>SECRET</code> thành 1 chuỗi bí mật bất kỳ (tự đặt, KHÔNG phải ID triển khai).</li>'
				. '<li>Bấm <strong>Triển khai → Tùy chọn triển khai mới → Ứng dụng web</strong>, "Thực thi dưới dạng": <em>Tôi</em>, "Ai có quyền truy cập": <em>Bất kỳ ai</em>. Sửa code sau này: <strong>Triển khai → Quản lý triển khai → ✏️ → Phiên bản mới</strong>.</li>'
				. '<li>Dán URL ứng dụng web (dạng <code>https://script.google.com/macros/s/…/exec</code>) và đúng chuỗi <code>SECRET</code> vào 2 ô bên dưới, lưu lại.</li>'
				. '</ol>'
				. '<pre style="background:#fff;border:1px solid #d1d5db;border-radius:4px;padding:10px;font-size:12px;overflow:auto;margin:0">'
				. esc_html($nlAppsScript)
				. '</pre>'
				. '<p style="margin:8px 0 0;font-size:12px;color:#6b7280">Trạng thái: <code>subscribed</code> = đã đăng ký, <code>unsubscribed</code> = đã huỷ; email bị xoá trong menu Newsletter thì dòng cũng bị xoá. Sheet lệch với website (vd đăng ký trước khi kết nối) → bấm <strong>Đồng bộ lại Google Sheet</strong> ở menu Newsletter. Sheet chỉ là bản đồng bộ — nếu Sheet lỗi, email vẫn được lưu trong website.</p>'
				. '</div>'
			),
		Field::make('text', 'newsletter_sheet_webhook', __('URL Apps Script Web App', 'laca'))
			->set_width(60)
			->set_attribute('placeholder', 'https://script.google.com/macros/s/…/exec')
			->set_help_text(__('Chỉ chấp nhận địa chỉ https://script.google.com/…', 'laca')),
		Field::make('text', 'newsletter_sheet_secret', __('Chuỗi bí mật (trùng SECRET trong Apps Script)', 'laca'))
			->set_width(40),

		Field::make('html', 'newsletter_provider_heading', __('', 'laca'))
			->set_html('<div style="background:#fefce8;border:1px solid #fde68a;border-radius:6px;padding:14px 16px;margin:24px 0 12px"><p style="margin:0 0 6px;font-weight:600;color:#854d0e">✉️ Dịch vụ gửi newsletter (không bắt buộc)</p><p style="margin:0;font-size:13px;color:#374151">Email đăng ký mới sẽ tự được thêm vào danh sách trên Mailchimp hoặc Brevo để bạn gửi newsletter từ đó; khách huỷ trên website thì tự gỡ khỏi danh sách. Có thể dùng song song với Google Sheet. Kết quả đồng bộ gần nhất hiện ở menu <strong>Newsletter</strong>.</p></div>'),

		Field::make('select', 'newsletter_provider', __('Dịch vụ', 'laca'))
			->set_options([
				'' => __('Không dùng', 'laca'),
				'mailchimp' => 'Mailchimp',
				'brevo' => 'Brevo (Sendinblue)',
			])
			->set_default_value(''),

		Field::make('text', 'newsletter_mailchimp_key', __('Mailchimp API key', 'laca'))
			->set_width(50)
			->set_attribute('type', 'password')
			->set_attribute('placeholder', 'xxxxxxxxxxxxxxxx-us21')
			->set_help_text(__('Mailchimp → Profile → Extras → API keys. Key phải có đuôi dạng "-us21".', 'laca'))
			->set_conditional_logic([['field' => 'newsletter_provider', 'value' => 'mailchimp']]),
		Field::make('text', 'newsletter_mailchimp_list', __('Audience ID', 'laca'))
			->set_width(50)
			->set_attribute('placeholder', 'VD: a1b2c3d4e5')
			->set_help_text(__('Audience → Settings → Audience name and defaults → Audience ID.', 'laca'))
			->set_conditional_logic([['field' => 'newsletter_provider', 'value' => 'mailchimp']]),
		Field::make('html', 'newsletter_mailchimp_webhook', __('', 'laca'))
			->set_html(
				'<p style="margin:0 0 6px;font-size:13px"><strong>Đồng bộ ngược khi khách bấm "Unsubscribe" trong newsletter gửi từ Mailchimp:</strong> Audience → Settings → Webhooks → Create new webhook, dán URL dưới đây, chỉ tick <em>Unsubscribes</em> và <em>Cleaned address</em>.</p>'
				. '<input type="text" readonly onclick="this.select()" style="width:100%;font-family:monospace;font-size:12px" value="' . esc_attr(NewsletterEndpoints::webhookUrl('mailchimp')) . '">'
			)
			->set_conditional_logic([['field' => 'newsletter_provider', 'value' => 'mailchimp']]),

		Field::make('text', 'newsletter_brevo_key', __('Brevo API key', 'laca'))
			->set_width(50)
			->set_attribute('type', 'password')
			->set_attribute('placeholder', 'xkeysib-…')
			->set_help_text(__('Brevo → SMTP & API → API Keys.', 'laca'))
			->set_conditional_logic([['field' => 'newsletter_provider', 'value' => 'brevo']]),
		Field::make('text', 'newsletter_brevo_list', __('List ID', 'laca'))
			->set_width(50)
			->set_attribute('type', 'number')
			->set_attribute('placeholder', 'VD: 3')
			->set_help_text(__('Contacts → Lists → số ID của danh sách.', 'laca'))
			->set_conditional_logic([['field' => 'newsletter_provider', 'value' => 'brevo']]),
		Field::make('html', 'newsletter_brevo_webhook', __('', 'laca'))
			->set_html(
				'<p style="margin:0 0 6px;font-size:13px"><strong>Đồng bộ ngược khi khách bấm "Unsubscribe" trong newsletter gửi từ Brevo:</strong> Contacts → Settings → Webhooks (Marketing) → Add a new webhook, dán URL dưới đây, chọn sự kiện <em>Unsubscribed</em>.</p>'
				. '<input type="text" readonly onclick="this.select()" style="width:100%;font-family:monospace;font-size:12px" value="' . esc_attr(NewsletterEndpoints::webhookUrl('brevo')) . '">'
			)
			->set_conditional_logic([['field' => 'newsletter_provider', 'value' => 'brevo']]),
	])

	->add_tab(__('☕ Trang sản phẩm | Product Page', 'laca'), [
		Field::make('html', 'product_page_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Trang chi tiết sản phẩm</p><p style="margin:0;font-size:13px;color:#374151">Các nội dung dưới đây dùng chung cho <strong>mọi sản phẩm</strong> (mục "How to use" và "Reviews" ở trang chi tiết sản phẩm) — chỉ cần nhập 1 lần ở đây, không cần lặp lại cho từng sản phẩm.</p></div>'),

		Field::make('html', 'how_to_use_label', __('', 'laca'))
			->set_html('----<i> How to use | Cách sử dụng & bảo quản </i>----'),
		Field::make('text', 'how_to_use_title' . currentLanguage(), __('Tiêu đề', 'laca'))
			->set_attribute('placeholder', 'Recommendations for storage and use'),
		Field::make('rich_text', 'how_to_use_content' . currentLanguage(), __('Nội dung hướng dẫn', 'laca')),

		Field::make('html', 'water_quality_label', __('', 'laca'))
			->set_html('----<i> Water quality | Chất lượng nước pha cà phê </i>----'),
		Field::make('text', 'water_quality_title' . currentLanguage(), __('Tiêu đề', 'laca'))
			->set_attribute('placeholder', 'The optimum water quality for brewing coffee'),
		Field::make('text', 'water_quality_calcium' . currentLanguage(), __('Calcium Hardness', 'laca'))
			->set_width(25)
			->set_attribute('placeholder', '10 ppm CaCO3'),
		Field::make('text', 'water_quality_magnesium' . currentLanguage(), __('Magnesium Hardness', 'laca'))
			->set_width(25)
			->set_attribute('placeholder', '58 ppm CaCO3'),
		Field::make('text', 'water_quality_alkalinity' . currentLanguage(), __('Total Alkalinity', 'laca'))
			->set_width(25)
			->set_attribute('placeholder', '10 ppm CaCO3'),
		Field::make('text', 'water_quality_sodium' . currentLanguage(), __('Sodium', 'laca'))
			->set_width(25)
			->set_attribute('placeholder', '10 ppm CaCO3'),

		Field::make('html', 'reviews_label', __('', 'laca'))
			->set_html('----<i> Reviews | Đánh giá khách hàng </i>----'),
		Field::make('complex', 'reviews_list' . currentLanguage(), __('Danh sách đánh giá', 'laca'))
			->set_layout('tabbed-vertical')
			->add_fields([
				Field::make('text', 'name', __('Tên khách hàng', 'laca'))
					->set_attribute('placeholder', 'Mr.A'),
				Field::make('textarea', 'content', __('Nội dung đánh giá', 'laca')),
				Field::make('text', 'link', __('Đường dẫn (không bắt buộc)', 'laca'))
					->set_attribute('placeholder', 'https://…'),
			])
			->set_header_template('<% if (name) { %><%- name %><% } %>'),
	])

	->add_tab(__('AI Translation | Dịch thuật AI', 'laca'), [
		Field::make('html', 'ai_intro', __('', 'laca'))
			->set_html('Cấu hình API Key để kích hoạt tính năng tự động dịch nội dung bằng trí tuệ nhân tạo. Có thể nhập NHIỀU key cùng lúc — hệ thống sẽ tự động chuyển sang key tiếp theo (theo thứ tự ở mục "Bộ xử lý ưu tiên" bên dưới) nếu key đang dùng bị lỗi hoặc hết quota miễn phí, không cần tự đổi tay.'),

		Field::make('text', 'ai_gemini_key', __('Gemini API Key', 'laca'))
			->set_help_text('Model: Gemini 1.5 Flash. ~15-30 request/phút, hàng trăm-1.500 request/ngày miễn phí. Lấy tại: <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>'),

		Field::make('text', 'ai_groq_key', __('Groq API Key', 'laca'))
			->set_help_text('Model: Llama 3.1 / 3.3. Nhanh nhất (hàng trăm-nghìn token/giây), 30 request/phút, 1.000 request/ngày miễn phí. Lấy tại: <a href="https://console.groq.com/keys" target="_blank">Groq Console</a>'),

		Field::make('text', 'ai_openrouter_key', __('OpenRouter API Key', 'laca'))
			->set_help_text('1 key dùng được rất nhiều model free (DeepSeek, Qwen, Llama, Gemma...). 20 request/phút, 50 request/ngày (lên 1.000/ngày nếu nạp $10 một lần). Lấy tại: <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>'),

		Field::make('text', 'ai_cloudflare_account_id', __('Cloudflare Account ID', 'laca'))
			->set_help_text('Lấy tại Cloudflare Dashboard, nằm ở URL hoặc sidebar phải trang chủ Dashboard. Cần nhập CÙNG với API Token bên dưới mới dùng được.'),

		Field::make('text', 'ai_cloudflare_api_token', __('Cloudflare API Token', 'laca'))
			->set_help_text('Model: Llama 3.1/Mistral/DeepSeek chạy trên edge, 10.000 Neurons/ngày miễn phí. Tạo token tại Cloudflare Dashboard > My Profile > API Tokens (quyền "Workers AI").'),

		Field::make('text', 'ai_mistral_key', __('Mistral API Key', 'laca'))
			->set_help_text('Model: Mistral Small/Codestral. Rate-limit theo gói Experiment (miễn phí). Lấy tại: <a href="https://console.mistral.ai/" target="_blank">console.mistral.ai</a>'),

		Field::make('text', 'ai_cohere_key', __('Cohere API Key', 'laca'))
			->set_help_text('Model: Command R, tốt cho dịch/RAG. ~1.000 call/tháng (trial). Lấy tại: <a href="https://dashboard.cohere.com/" target="_blank">dashboard.cohere.com</a>'),

		Field::make('text', 'ai_nvidia_key', __('NVIDIA NIM API Key', 'laca'))
			->set_help_text('Nhiều model open-source mạnh (Llama 3.1...), rate-limit + cần xác minh số điện thoại. Lấy tại: <a href="https://build.nvidia.com/" target="_blank">build.nvidia.com</a>'),

		Field::make('text', 'ai_deepseek_key', __('DeepSeek API Key', 'laca'))
			->set_help_text('Model: DeepSeek Chat. Lấy tại: <a href="https://platform.deepseek.com/" target="_blank">DeepSeek Platform</a>'),

		Field::make('text', 'ai_openai_key', __('OpenAI API Key', 'laca'))
			->set_help_text('Model: GPT-4o, GPT-4o-mini. Lấy tại: <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a>'),

		Field::make('text', 'ai_anthropic_key', __('Anthropic API Key', 'laca'))
			->set_help_text('Model: Claude 3.5 Sonnet/Haiku. Lấy tại: <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>'),

		Field::make('select', 'ai_default_provider', __('Bộ xử lý ưu tiên', 'laca'))
			->set_help_text('Nhà cung cấp được thử ĐẦU TIÊN. Nếu lỗi/hết quota, hệ thống tự động chuyển qua các nhà cung cấp khác (đã nhập key) theo thứ tự: Gemini → Groq → OpenRouter → Cloudflare Workers AI → Mistral → Cohere → NVIDIA NIM → DeepSeek → OpenAI → Anthropic.')
			->set_options([
				'gemini' => 'Google Gemini (Khuyên dùng)',
				'groq' => 'Groq (Llama 3 - Tốc độ cực nhanh)',
				'openrouter' => 'OpenRouter (Nhiều model free)',
				'cloudflare' => 'Cloudflare Workers AI',
				'mistral' => 'Mistral',
				'cohere' => 'Cohere',
				'nvidia' => 'NVIDIA NIM',
				'deepseek' => 'DeepSeek (Giá rẻ/Chất lượng cao)',
				'openai' => 'OpenAI GPT',
				'anthropic' => 'Anthropic Claude',
			])
			->set_default_value('gemini'),
	])

	->add_tab(__('🛒 Block Marketplace', 'laca'), [
		Field::make('html', 'block_marketplace', __('', 'laca'))
			->set_html(static function () {
				return class_exists('\App\Settings\BlockMarketplace')
					? \App\Settings\BlockMarketplace::renderPage()
					: '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;color:#991b1b">Không tìm thấy class BlockMarketplace.</div>';
			}),
	])

	->add_tab(__('Scripts', 'laca'), [
		Field::make('html', 'scripts_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Scripts</p><p style="margin:0;font-size:13px;color:#374151">Chèn mã theo dõi vào đầu (header) hoặc cuối (footer) trang, ví dụ Google Analytics, Facebook Pixel. Lưu ý: nhập sai định dạng mã có thể làm lỗi hiển thị toàn bộ website, chỉ dán mã bạn tin tưởng.</p></div>'),

		Field::make('header_scripts', 'crb_header_script', __('Header Script', 'laca')),
		Field::make('footer_scripts', 'crb_footer_script', __('Footer Script', 'laca')),
	]);