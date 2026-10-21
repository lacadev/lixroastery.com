<?php

namespace App\Features\LacaSeo\Fields;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Registry of llms.txt settings fields.
 */
class LlmsFields
{
    /**
     * @return array<string, Field[]> Map of tab slug => array of Field objects
     */
    public static function get(): array
    {
        $tax_choices = [];
        foreach (Helper::getAccessibleTaxonomies() as $slug => $obj) {
            $tax_choices[$slug] = $obj->labels->name ?: $slug;
        }

        $pt_choices = Helper::choicesPostTypes();
        unset($pt_choices['attachment']);

        return [
            'settings' => [
                Field::make('llms_post_types', 'multicheck', 'Loại bài viết đưa vào llms.txt (Post Types)')
                    ->default([])
                    ->tab('settings')
                    ->group('general')
                    ->choices($pt_choices)
                    ->note(
                        'Chọn các loại nội dung muốn đưa vào tệp llms.txt phục vụ các mô hình ngôn ngữ lớn (LLM/AI).',
                        'Chọn bài viết, trang, tài liệu để AI trích xuất thông tin chính xác nhất.',
                        'Các liên kết bài viết được liệt kê theo chuẩn Markdown trong tệp /llms.txt.'
                    ),

                Field::make('llms_taxonomies', 'multicheck', 'Phân loại đưa vào llms.txt (Taxonomies)')
                    ->default([])
                    ->tab('settings')
                    ->group('general')
                    ->choices($tax_choices)
                    ->note(
                        'Chọn các taxonomy để đưa các term tương ứng vào danh sách /llms.txt.',
                        'Chọn chuyên mục, danh mục sản phẩm chính.',
                        'Đường dẫn term sẽ xuất hiện trong tài liệu llms.txt.'
                    ),

                Field::make('llms_limit', 'text', 'Giới hạn số lượng liên kết mỗi loại (Limit)')
                    ->default('50')
                    ->tab('settings')
                    ->group('general')
                    ->attributes(['type' => 'number', 'min' => '1', 'max' => '500'])
                    ->note(
                        'Số lượng liên kết tối đa cho mỗi post type hoặc taxonomy trong llms.txt.',
                        'Mặc định 50 để tệp gọn gàng, súc tích cho AI xử lý theo ngữ cảnh.',
                        'Hệ thống lấy số bài viết mới nhất theo đúng hạn mức này.'
                    ),

                Field::make('llms_extra_content', 'textarea', 'Nội dung bổ sung thủ công (Additional Content)')
                    ->default('')
                    ->tab('settings')
                    ->group('general')
                    ->note(
                        'Thêm văn bản Markdown hoặc đường dẫn tuỳ biến mà bạn muốn AI đọc thêm.',
                        'Nhập thông tin giới thiệu công ty, quy chuẩn trích dẫn hoặc link tài liệu đặc thù.',
                        'Đoạn văn bản này sẽ được in ở cuối tệp /llms.txt.'
                    ),
            ],
        ];
    }
}
