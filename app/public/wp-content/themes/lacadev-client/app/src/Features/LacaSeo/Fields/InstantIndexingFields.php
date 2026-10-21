<?php

namespace App\Features\LacaSeo\Fields;

use App\Features\LacaSeo\Core\Helper;

defined('ABSPATH') || exit;

/**
 * Registry of Instant Indexing (IndexNow) settings fields.
 */
class InstantIndexingFields
{
    /**
     * @return array<string, Field[]> Map of tab slug => array of Field objects
     */
    public static function get(): array
    {
        return [
            'settings' => [
                Field::make('bing_post_types', 'multicheck', 'Tự động gửi các loại nội dung (Auto-Submit Post Types)')
                    ->default(['post', 'page'])
                    ->tab('settings')
                    ->group('instant_indexing')
                    ->choices(Helper::choicesPostTypes())
                    ->note(
                        'Tự động gửi thông báo URL tới giao thức IndexNow (Bing, Yandex, v.v.) ngay khi xuất bản, sửa hoặc xoá bài viết.',
                        'Chọn các post type quan trọng cần lập chỉ mục tức thì (bài viết, trang, sản phẩm).',
                        'Mỗi khi bài viết thuộc loại này được lưu, URL sẽ được chuyển ngay tới IndexNow API.'
                    ),

                Field::make('indexnow_api_key', 'text', 'IndexNow API Key')
                    ->default('')
                    ->tab('settings')
                    ->group('instant_indexing')
                    ->note(
                        'Khoá API chứng minh quyền sở hữu trang web theo chuẩn IndexNow.',
                        'Khoá được tự động tạo ngẫu nhiên. Bạn có thể nhấn đổi khoá nếu cần thay mới.',
                        'Tệp xác thực khoá sẽ phục vụ tại đường dẫn /{key}.txt khi bot tìm kiếm kiểm tra.'
                    ),
            ],
        ];
    }
}
