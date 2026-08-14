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
			->set_header_template('<% if (name) { %><%- name %><% } %>')
			->set_default_value([
				['name' => 'Đăng ký Bộ Công Thương', 'url' => '#'],
				['name' => 'Đăng ký DMCA', 'url' => '#'],
			]),
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
			->set_html('Cấu hình API Key để kích hoạt tính năng tự động dịch nội dung bằng trí tuệ nhân tạo. Bạn nên ưu tiên dùng Gemini hoặc Groq vì có gói miễn phí rất tốt.'),

		Field::make('text', 'ai_gemini_key', __('Gemini API Key', 'laca'))
			->set_help_text('Model: Gemini 1.5 Pro/Flash. Lấy tại: <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>'),

		Field::make('text', 'ai_groq_key', __('Groq API Key', 'laca'))
			->set_help_text('Model: Llama 3/3.1. Lấy tại: <a href="https://console.groq.com/keys" target="_blank">Groq Console</a>'),

		Field::make('text', 'ai_deepseek_key', __('DeepSeek API Key', 'laca'))
			->set_help_text('Model: DeepSeek Chat. Lấy tại: <a href="https://platform.deepseek.com/" target="_blank">DeepSeek Platform</a>'),

		Field::make('text', 'ai_openai_key', __('OpenAI API Key', 'laca'))
			->set_help_text('Model: GPT-4o, GPT-4o-mini. Lấy tại: <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a>'),

		Field::make('text', 'ai_anthropic_key', __('Anthropic API Key', 'laca'))
			->set_help_text('Model: Claude 3.5 Sonnet/Haiku. Lấy tại: <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>'),

		Field::make('select', 'ai_default_provider', __('Bô xử lý ưu tiên', 'laca'))
			->set_options([
				'gemini' => 'Google Gemini (Khuyên dùng)',
				'groq' => 'Groq (Llama 3 - Tốc độ cực nhanh)',
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