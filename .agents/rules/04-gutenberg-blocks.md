# 🧩 CUSTOM GUTENBERG BLOCKS DEVELOPMENT SPECIFICATIONS

## 1. Directory Structure for Custom Blocks
Mỗi block mới được tạo trong thư mục `block-gutenberg/lixroastery-blocks-site/[block-name]/` (hoặc thư mục bucket tương ứng) với đủ 6 file tiêu chuẩn:
```
block-gutenberg/lixroastery-blocks-site/[block-name]/
├── block.json       # Metadata, icon, category, attributes schema
├── index.js         # Register block type trong React
├── edit.js          # Giao diện trực quan & InspectorControls trong Editor
├── save.js          # Return null (khi dùng server-side render.php)
├── render.php       # Dynamic PHP render ngoài frontend
└── style.scss       # CSS cục bộ của block
```

## 2. Block Naming & Metadata Rules
* **Name**: Đặt tên namespace `lacadev/[block-name]` (ví dụ: `lacadev/block-brew-calculator`).
* **Category**: Khai báo category phù hợp (ví dụ: `site-lixroastery` hoặc category tự động phát hiện từ tên bucket).
* **Render**: Block động luôn dùng `render.php` kết hợp `save.js` trả về `null` hoặc `<InnerBlocks.Content />`.
* **Attributes**: Khai báo kiểu dữ liệu rõ ràng (`string`, `number`, `boolean`, `array`, `object`) và có `default` value hợp lệ.

## 3. UI/UX in Block Editor (`edit.js`)
* Sử dụng các React components chuẩn từ `@wordpress/components` (`PanelBody`, `TextControl`, `TextareaControl`, `SelectControl`, `ToggleControl`, `RangeControl`, `ColorPalette`, `MediaUpload`).
* Cung cấp controls trực quan trong `InspectorControls` (Sidebar) và xem trước trực quan (Preview) ngay trong Editor đồng nhất với `render.php`.
* Khi dùng Repeaters / Danh sách con: Sử dụng component lặp linh hoạt có nút thêm/sửa/xóa trực quan.

## 4. Safe Block Registration & Build
* Đăng ký an toàn qua `lacadev_safe_register_block()` để tránh emit notice duplicate khi build.
* **Quy trình Build & Xác thực**:
  * Chạy `yarn dev:blocks` hoặc `yarn build:blocks` sau khi tạo hoặc sửa block để đảm bảo file `build/index.js` và `build/style-index.css` được compile thành công không có lỗi syntax.
