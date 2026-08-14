<?php
/**
 * Carbon Fields custom fields cho post type "product" (WooCommerce).
 * Tham khảo cấu trúc từ xliiicoffee (site cà phê khác) — tổ chức lại theo
 * đúng các mục hiển thị ở trang chi tiết sản phẩm: Producer / Flavor Profile
 * / Roasting Profile, để admin nhập dữ liệu tab nào thì biết ngay tab đó
 * hiện ở đâu trên trang.
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

add_action('carbon_fields_register_fields', function () {
    // Không check post_type_exists('product') ở đây — Carbon Fields tự kích
    // hoạt 'carbon_fields_register_fields' trên hook 'init' priority 0,
    // TRƯỚC khi WooCommerce đăng ký post type 'product' (init priority 5).
    // Container chỉ cần biết tên post type dạng string, không cần nó đã
    // được register() vào WP lúc khai báo.
    Container::make('post_meta', __('Tên phụ', 'laca'))
        ->set_context('carbon_fields_after_title')
        ->set_priority('high')
        ->where('post_type', 'IN', ['product'])
        ->add_fields([
            Field::make('text', 'subname', __('', 'laca'))
                ->set_attribute('placeholder', __('VD: Buenos Aires Gesha #0370', 'laca')),
        ]);

    Container::make('post_meta', __('Chi tiết sản phẩm', 'laca'))
        ->set_context('carbon_fields_after_title')
        ->set_priority('default')
        ->where('post_type', 'IN', ['product'])

        // ── Tab 1: hiện ở mục "PRODUCER" (khối truy xuất nguồn gốc) ──────
        ->add_tab(__('📋 Truy xuất nguồn gốc', 'laca'), [
            Field::make('html', 'traceability_intro', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:0 0 12px;">Cũng hiện ở mục <strong>PRODUCER</strong> trên trang chi tiết sản phẩm — tách riêng tab này để nhập cho gọn.</p>'),

            Field::make('html', 'traceability_group_location', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:12px 0;">Vị trí & nguồn gốc</p>'),
            Field::make('text', 'origin', __('Origin | Xuất xứ', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'region', __('Region | Vùng', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'farm', __('Farm | Trang trại', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'cooperative', __('Cooperative | Hợp tác xã', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'wet_mill', __('Wet Mill | Trạm rửa', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'factory', __('Factory | Nhà máy', 'laca'))
                ->set_width(33.33),

            Field::make('html', 'traceability_group_bean', '')
                ->set_html('<hr><p style="font-size:13px;color:#666;margin:12px 0;">Đặc điểm hạt</p>'),
            Field::make('text', 'variety', __('Variety | Giống', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'crop_year', __('Crop year | Năm thu hoạch', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '--/202-'),
            Field::make('text', 'altitude', __('Altitude | Độ cao', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'masl'),
            Field::make('text', 'process', __('Process | Phương pháp sơ chế', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'net_weight', __('Net weight | Trọng lượng', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'gram'),

            Field::make('html', 'traceability_group_price', '')
                ->set_html('<hr><p style="font-size:13px;color:#666;margin:12px 0;">Giá & nguồn cung</p>'),
            Field::make('text', 'sourcing', __('Sourcing | Nguồn cung ứng', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'paid_for_producer', __('Paid for producer | Trả cho nhà sản xuất', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'USD/kg'),
            Field::make('text', 'paid_for_exporter', __('Paid for exporter | Trả cho nhà xuất khẩu', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'USD/kg'),
            Field::make('text', 'fob_price', __('FOB price | Giá FOB', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'USD/kg'),
            Field::make('text', 'ddp_price', __('DDP price | Giá DDP', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'VND/kg'),
        ])

        // ── Tab 2: hiện ở mục "PRODUCER" trên trang chi tiết ──────────────
        ->add_tab(__('👤 Producer', 'laca'), [
            Field::make('html', 'producer_intro', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:0 0 12px;">Hiện ở mục <strong>PRODUCER</strong> trên trang chi tiết sản phẩm — câu chuyện nhà sản xuất, số liệu hợp tác và bản đồ trang trại.</p>'),

            Field::make('textarea', 'producer_story', __('Giới thiệu nhà sản xuất', 'laca'))
                ->set_attribute('placeholder', __('VD: Café Granja La Esperanza, a brand specializing in producing and commercializing high-quality specialty coffees...', 'laca')),

            Field::make('text', 'first_cooperation_year', __('Năm hợp tác đầu tiên', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '2021'),
            Field::make('text', 'cooperation_model', __('Mô hình hợp tác', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Direct Trade'),
            Field::make('text', 'annual_volume', __('Sản lượng theo năm', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '~1 ton/year'),

            Field::make('textarea', 'google_maps_embed', __('Google Maps — link nhúng (embed src)', 'laca'))
                ->set_help_text(__('Vào Google Maps → Chia sẻ → Nhúng bản đồ → copy đường dẫn trong src="...". Chỉ dán đúng đường dẫn đó, không dán cả đoạn mã iframe.', 'laca'))
                ->set_attribute('placeholder', 'https://www.google.com/maps/embed?...'),
        ])

        // ── Tab 3: hiện ở mục "FLAVOR PROFILE" trên trang chi tiết ───────
        ->add_tab(__('☕ Flavor Profile', 'laca'), [
            Field::make('html', 'flavor_intro', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:0 0 12px;">Hiện ở mục <strong>FLAVOR PROFILE</strong> trên trang chi tiết sản phẩm.</p>'),

            Field::make('textarea', 'flavor_profile', __('Mô tả hương vị', 'laca'))
                ->set_attribute('placeholder', __('VD: Sudan Rume #00313 by CGLE remains a testament to the botanical preservation...', 'laca')),
            Field::make('text', 'sca_coffee_score', __('SCA coffee score | Điểm SCA', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '--/100'),

            Field::make('html', 'flavor_grid_intro', '')
                ->set_html('<hr><p style="font-size:13px;color:#666;margin:12px 0;">8 ô hương vị (hiện dạng lưới 4 cột trên trang)</p>'),

            Field::make('text', 'flavor_aroma', __('Aroma', 'laca'))->set_width(25),
            Field::make('text', 'flavor_hot', __('Hot', 'laca'))->set_width(25),
            Field::make('text', 'flavor_warm', __('Warm', 'laca'))->set_width(25),
            Field::make('text', 'flavor_cool', __('Cool', 'laca'))->set_width(25),
            Field::make('text', 'flavor_aftertaste', __('Aftertaste', 'laca'))->set_width(25),
            Field::make('text', 'flavor_acidity', __('Acidity', 'laca'))->set_width(25),
            Field::make('text', 'flavor_sweetness', __('Sweetness', 'laca'))->set_width(25),
            Field::make('text', 'flavor_mouthfeel', __('Mouthfeel', 'laca'))->set_width(25),
        ])

        // ── Tab 4: hiện ở mục "ROASTING PROFILE" trên trang chi tiết ─────
        ->add_tab(__('🔥 Roasting Profile', 'laca'), [
            Field::make('html', 'roasting_intro', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:0 0 12px;">Hiện ở mục <strong>ROASTING PROFILE</strong> trên trang chi tiết sản phẩm.</p>'),

            Field::make('text', 'title_roasting_profile', __('Title | Tiêu đề', 'laca'))
                ->set_width(70)
                ->set_attribute('placeholder', 'Roasting profile'),
            Field::make('image', 'img_roasting_profile', __('Image | Hình ảnh', 'laca'))
                ->set_width(30),

            Field::make('text', 'roast_machine', __('Roast Machine', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Giesen W1A'),
            Field::make('text', 'roast_level', __('Roast Level', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Extremely Light'),
            Field::make('text', 'end_air_temperature', __('End Temperature', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '186°C'),

            Field::make('html', 'roasting_detail_intro', '')
                ->set_html('<hr><p style="font-size:13px;color:#666;margin:12px 0;">Chi tiết profile rang</p>'),

            Field::make('text', 'roasting_profile', __('Roast Profile', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'roast_duration', __('Roast Duration', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'drying_phase', __('Drying Phase', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'maillard_phase', __('Maillard Phase', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'development_phase', __('Development Phase', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'development_ratio', __('Development Ratio', 'laca'))
                ->set_width(33.33),
            Field::make('text', 'charge_temperature', __('Charge Temperature', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Celsius'),
            Field::make('text', 'turning_point', __('Turning Point', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Celsius'),
            Field::make('text', 'dry_end_temperature', __('Dry-end Temperature', 'laca'))
                ->set_width(25)
                ->set_attribute('placeholder', 'Celsius'),
            Field::make('text', 'first_crack_temperature', __('First Crack Temperature', 'laca'))
                ->set_width(25)
                ->set_attribute('placeholder', 'Celsius'),
            Field::make('text', 'end_bean_temperature', __('End Bean Temperature', 'laca'))
                ->set_width(25)
                ->set_attribute('placeholder', 'Celsius'),
        ])

        // ── Tab 5: hiện ở mục "COFFEE JOURNAL" trên trang chi tiết ───────
        ->add_tab(__('📰 Coffee Journal', 'laca'), [
            Field::make('html', 'journal_intro_note', '')
                ->set_html('<p style="font-size:13px;color:#666;margin:0 0 12px;">Hiện ở mục <strong>COFFEE JOURNAL</strong> trên trang chi tiết sản phẩm — thứ tự hiển thị luôn là: nội dung trên → các ô → nội dung dưới, đúng theo thiết kế. Để trống phần nội dung nào không cần dùng. Thứ tự sắp xếp cao — thấp của các ô sẽ tự động xen kẽ, không cần chọn tay.</p>'),

            Field::make('rich_text', 'journal_content_top', __('Nội dung — phía trên các ô', 'laca'))
                ->set_attribute('placeholder', __('VD: Cùng khám phá câu chuyện và trải nghiệm liên quan tới sản phẩm này...', 'laca')),

            Field::make('complex', 'journal_items', __('Các ô Coffee Journal', 'laca'))
                ->set_layout('tabbed-vertical')
                ->add_fields([
                    Field::make('image', 'image', __('Hình ảnh', 'laca'))
                        ->set_width(30),
                    Field::make('text', 'title', __('Tiêu đề', 'laca'))
                        ->set_width(70)
                        ->set_attribute('placeholder', 'VD: The future of Echos: Regenerative Farming'),
                    Field::make('textarea', 'desc', __('Mô tả', 'laca'))
                        ->set_attribute('placeholder', 'VD: How we are working with our partners in Ethiopia to restore soil biodiversity...'),
                    Field::make('text', 'link', __('Đường dẫn "Bài viết chi tiết" (không bắt buộc)', 'laca'))
                        ->set_attribute('placeholder', 'https://…'),
                ])
                ->set_header_template('<% if (title) { %><%- title %><% } %>'),

            Field::make('rich_text', 'journal_content_bottom', __('Nội dung — phía dưới các ô', 'laca'))
                ->set_attribute('placeholder', __('VD: Nội dung bổ sung hiển thị sau các ô Coffee Journal...', 'laca')),
        ]);
});
