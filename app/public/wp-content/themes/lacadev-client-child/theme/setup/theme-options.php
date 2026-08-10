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

		Field::make('color', 'primary_color', __('Primary color', 'laca'))
			->set_width(33.33),
		Field::make('color', 'secondary_color', __('Secondary color', 'laca'))
			->set_width(33.33),
		Field::make('color', 'bg_color', __('Background color', 'laca'))
			->set_width(33.33),

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

	->add_tab(__('Scripts', 'laca'), [
		Field::make('html', 'scripts_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Scripts</p><p style="margin:0;font-size:13px;color:#374151">Chèn mã theo dõi vào đầu (header) hoặc cuối (footer) trang, ví dụ Google Analytics, Facebook Pixel. Lưu ý: nhập sai định dạng mã có thể làm lỗi hiển thị toàn bộ website, chỉ dán mã bạn tin tưởng.</p></div>'),

		Field::make('header_scripts', 'crb_header_script', __('Header Script', 'laca')),
		Field::make('footer_scripts', 'crb_footer_script', __('Footer Script', 'laca')),
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
				'groq'   => 'Groq (Llama 3 - Tốc độ cực nhanh)',
				'deepseek' => 'DeepSeek (Giá rẻ/Chất lượng cao)',
				'openai' => 'OpenAI GPT',
				'anthropic' => 'Anthropic Claude',
			])
			->set_default_value('gemini'),
	])

	->add_tab(__('Tuỳ chỉnh giao diện (Child)', 'laca'), [
		Field::make('textarea', 'footer_contact_budget_options', __('Ngân sách form liên hệ footer', 'laca'))
		->set_width(50)
			->set_help_text(__('Mỗi dòng là một lựa chọn ngân sách.', 'laca'))
			->set_default_value("Dưới 1 tỷ\n1 - 3 tỷ\n3 - 5 tỷ\n5 - 10 tỷ\nTrên 10 tỷ"),
		Field::make('image', 'footer_contact_image', __('Hình ảnh form liên hệ footer', 'laca'))
		->set_width(50)
			->set_help_text(__('Ảnh hiển thị bên phải form liên hệ ở footer.', 'laca')),
	])

	->add_tab(__('Footer menu(Child)', 'laca'), [
		Field::make('html', 'footer_menu_intro', __('', 'laca'))
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Footer menu (Child)</p><p style="margin:0;font-size:13px;color:#374151">Cấu hình 5 cột menu ở footer: Về chúng tôi, Dịch vụ, Chính sách, Showroom & Nhà máy, Dự án tiêu biểu và Đối tác. Nội dung khai báo ở đây sẽ hiển thị ở cuối mọi trang trên website.</p></div>'),

		//Menu Về chúng tôi
		Field::make('html', 'about_footer', __('', 'laca'))
			->set_html('----<i> MENU VỀ CHÚNG TÔI </i>----'),
		Field::make('text', 'about_footer_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Tiêu đề menu'),
		Field::make('text', 'company' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'Company | Công ty'),


		// Menu dịch vụ
		Field::make('html', 'service_footer', __('', 'laca'))
			->set_html('----<i> MENU DỊCH VỤ </i>----'),
		Field::make('text', 'service_footer_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Tiêu đề menu'),
		Field::make('complex', 'service_footer_items' . currentLanguage(), __('', 'laca'))
			->set_layout('tabbed-horizontal')
			->add_fields([
				Field::make('text', 'name', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'Tên dịch vụ'),
				Field::make('text', 'url', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'URL'),
			])->set_header_template('<% if (name) { %><%- name %><% } %>'),

		//Menu chính sách
		Field::make('html', 'policy_footer', __('', 'laca'))
			->set_html('----<i> MENU CHÍNH SÁCH </i>----'),
		Field::make('text', 'policy_footer_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Tiêu đề menu'),
		Field::make('complex', 'policy_footer_items' . currentLanguage(), __('', 'laca'))
			->set_layout('tabbed-horizontal')
			->add_fields([
				Field::make('text', 'name', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'Tên chính sách'),
				Field::make('text', 'url', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'URL'),
			])->set_header_template('<% if (name) { %><%- name %><% } %>'),

		//Menu Showroom & Nhà máy
		Field::make('html', 'showroom_factory_footer', __('', 'laca'))
			->set_html('----<i> MENU SHOWROOM & NHÀ MÁY </i>----'),
		Field::make('text', 'showroom_factory_footer_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Tiêu đề menu'),

		//Menu Dự án tiêu biểu
		Field::make('html', 'project_footer', __('', 'laca'))
			->set_html('----<i> MENU DỰ ÁN TIÊU BIỂU </i>----'),
		Field::make('text', 'project_footer_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Tiêu đề menu'),
		Field::make('complex', 'project_footer_items' . currentLanguage(), __('', 'laca'))
			->set_layout('tabbed-horizontal')
			->add_fields([
				Field::make('text', 'name', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'Tên dự án'),
				Field::make('text', 'url', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'URL'),
			])->set_header_template('<% if (name) { %><%- name %><% } %>'),

		//Menu đối tác
		Field::make('html', 'partner_footer', __('', 'laca'))
			->set_html('----<i> MENU ĐỐI TÁC </i>----'),
		Field::make('text', 'partner_footer_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Tiêu đề menu'),
		Field::make('complex', 'partner_footer_items' . currentLanguage(), __('', 'laca'))
			->set_layout('tabbed-horizontal')
			->add_fields([
				Field::make('text', 'name', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'Tên đối tác'),
				Field::make('text', 'url', __('', 'laca'))->set_width(50)
				->set_attribute('placeholder', 'URL'),
			])->set_header_template('<% if (name) { %><%- name %><% } %>'),
	])

	->add_tab(__('🛒 Block Marketplace', 'laca'), [
		Field::make('html', 'block_marketplace', __('', 'laca'))
			->set_html(static function () {
				return class_exists('\App\Settings\BlockMarketplace')
					? \App\Settings\BlockMarketplace::renderPage()
					: '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;color:#991b1b">Không tìm thấy class BlockMarketplace.</div>';
			}),
	]);
