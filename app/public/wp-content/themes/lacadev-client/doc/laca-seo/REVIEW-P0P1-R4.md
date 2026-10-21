# Review Phase 0 + 1 — Vòng 4 (Claude Code, 21/10/2026) — chỉnh nhỏ cuối

Đã đạt: compare-head 19/19 URL MATCH mọi cột (khác biệt duy nhất: RM để lọt `<p>` trong og/twitter description — chấp nhận);
test-variables 55/55; dropdown biến đóng mặc định + không còn `undefined`; tab trái xuống dòng; primary taxonomy là select; nút Lưu OK.

Còn 4 việc:

1. **Ô `variable_text` / `variable_textarea` quá hẹp** (~200px ở 1440, chữ bị cắt `%title% %sep% %si…`). Cột control của field kiểu text/textarea/variable/code
   phải rộng ≥ 60% hàng (≥1200px) — hoặc layout field 2 tầng: label + note trên, control full width dưới cho các type này. Nút "Chèn biến" nằm cùng hàng,
   bên phải ô, không chiếm chỗ của ô. Kiểm tra ở 1440/900/390.
2. **Preview luôn rỗng / chỉ ra `- LIX ROASTERY`**: AJAX `laca_seo_preview_vars` phải lấy đối tượng mẫu đúng ngữ cảnh: post type → bài publish mới nhất
   của post type đó (`pt_post` → post, `pt_product` → product…); taxonomy → term có bài nhiều nhất; author → user đăng bài gần nhất; homepage/misc → không object.
   Khi không có bài nào → hiện "Chưa có nội dung mẫu để xem trước" thay vì kết quả rỗng.
3. **Chèn biến khi ô chưa từng focus** đang chèn vào đầu (`%title%%title% %sep%…`): nếu ô chưa có vị trí con trỏ do người dùng đặt → chèn vào cuối,
   tự thêm 1 dấu cách nếu ký tự trước không phải khoảng trắng; sau khi chèn focus lại ô, đặt con trỏ sau biến vừa chèn, cập nhật preview + đếm ký tự.
4. **Notice "Còn N bài có dữ liệu Rank Math chưa chuyển" báo sai** (đang 33 dù đã import đủ): `Admin/Notices.php` phải loại các key importer cố ý bỏ qua
   (`rank_math_internal_links_processed`, `rank_math_og_content_image`, `rank_math_analytic_object_id`, `rank_math_contentai_score`) và chỉ đếm bài có
   key RM mà CHƯA có `_laca_seo_` tương ứng. Kỳ vọng trên DB local hiện tại = 0 → notice không hiện.

Nghiệm thu: screenshot WP admin thật titles/pt_post ở 1440 + 390 (ô template hiện đủ chữ, preview có nội dung thật); `php -l`, `yarn build`, `yarn lint` 0 error;
compare-head + test-variables vẫn như hiện tại. Không commit/push.
