# 🛡️ SECURITY & CLEAN CODE STANDARDS

## 1. 100% Output Escaping (Strict XSS Prevention)
Không bao giờ tin tưởng bất kỳ biến nào khi in ra HTML (kể cả dữ liệu từ database):
* In text thuần: `echo esc_html($text);`
* In thuộc tính HTML: `echo '<div class="' . esc_attr($class) . '">';`
* In liên kết URL: `echo '<a href="' . esc_url($url) . '">';`
* In HTML có định dạng an toàn: `echo wp_kses_post($content);`
* In dữ liệu JSON cho JavaScript: `echo '<div data-config="' . esc_attr(wp_json_encode($data)) . '">';`

## 2. Form & AJAX Verification (CSRF Protection)
* Mọi form submit và AJAX handler BẮT BUỘC phải tạo và kiểm tra Nonce:
  * Tạo nonce: `wp_create_nonce('theme_nonce')` hoặc `wp_nonce_field('theme_action', 'theme_nonce')`.
  * Xác thực trong AJAX: `check_ajax_referer('theme_nonce', 'nonce');`
  * Xác thực trong POST handler:
    ```php
    if (!isset($_POST['theme_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['theme_nonce'])), 'theme_action')) {
        wp_die('Security check failed');
    }
    ```

## 3. Input Sanitization
* Luôn làm sạch dữ liệu đầu vào trước khi lưu CSDL hoặc xử lý logic:
  * String text: `sanitize_text_field(wp_unslash($_POST['field_name']))`
  * Số nguyên: `absint($_POST['number_field'])` hoặc `(int) $_POST['id']`
  * Email: `sanitize_email($_POST['email'])`
  * Mảng dữ liệu: Sử dụng `map_deep()` hoặc duyệt vòng lặp sanitize từng phần tử.
  * Tên key/slug: `sanitize_key($_POST['slug'])`

## 4. SQL Injection Prevention & Permissions
* Tuyệt đối không nối chuỗi biến trực tiếp vào câu lệnh SQL. Luôn dùng `$wpdb->prepare()`:
  ```php
  $result = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}custom_table WHERE user_id = %d AND status = %s",
      $user_id,
      $status
  ));
  ```
* Kiểm tra quyền người dùng (`current_user_can('manage_options')`) trước khi cho phép thực thi các hành động trong Admin hoặc endpoint bảo mật.
