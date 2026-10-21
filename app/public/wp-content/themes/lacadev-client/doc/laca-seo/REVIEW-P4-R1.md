# Review Phase 4 — Vòng 1 (Claude)

Đạt: M1–M20 so bằng `RankMath\Redirections\DB::compare_sources()`; E1–E2 dùng `RankMath\Redirections\Export`; UI 1440/390 OK.

## Lỗi bắt buộc sửa
1. **Transaction bị rò (nghiêm trọng):** sau mỗi lần chạy `test-redirections.php` còn lại 1 dòng thật:
   `wp_laca_seo_redirections` id=100003 (`journal/extremely-light-of-lix-roastery/` → `…-wtest/`, 301, active) và `wp_laca_seo_redirections_cache`
   id=6 (object 866). Claude chạy lúc 19:08 ⇒ dòng mới `created 19:08:01`. Nếu bật module, bài 866 bị redirect sang URL 404.
   ⇒ Có câu lệnh gây **implicit commit** giữa transaction (nghi: luồng `wp_update_post` của W1 kích hoạt hook chạy DDL/`dbDelta`/`Installer`,
   hoặc `ALTER/CREATE` ở nơi khác). Tìm đúng nguồn (bật `SAVEQUERIES`, in các câu DDL/`COMMIT` giữa START và ROLLBACK) và loại bỏ.
   Thêm lưới an toàn: script ghi lại mọi id do chính nó tạo trong 3 bảng `laca_seo_*` và **chỉ được** `DELETE` đúng các id đó ở `finally`
   (không đụng id khác/bảng khác); post 866 phải về đúng `post_name` + `post_modified` ban đầu (kiểm bằng so sánh trước/sau).
   **Không tự xoá** dòng 100003 / cache 6 hiện có — chờ người dùng cho phép (Claude xử lý).
2. **Z1 sai:** đang so số dòng trước/sau trong cùng lần chạy nên không phát hiện rò. Sửa: Z1 so **checksum toàn bộ nội dung** 3 bảng
   (`CHECKSUM TABLE` hoặc md5 của `SELECT * ORDER BY id`) + `post_name/post_modified` bài 866 trước và sau; và chạy script **2 lần liên tiếp** —
   lần 2 checksum phải bằng lần 1.
3. **Ca test giả:** `D1` (dòng ~320) và `W2` (dòng ~280) truyền cứng `true`. Viết lại:
   D1 = chạy redirector trong tiến trình con 2 lần (user admin / khách, cookie sinh runtime, không ghi file): admin nhận trang debug (status 200, có
   nội dung debug như RM `class-debugger.php`), khách nhận 301 + Location. W2 = kiểm thật điều mà RM watcher làm (đọc `RM/.../class-watcher.php`), không
   in PASS cố định. Rà toàn bộ script: không ca nào được `reportResult(..., true)` hằng số.
4. K1 chỉ kiểm taxonomy tồn tại — thêm kiểm lọc danh sách redirect theo category trả đúng.

## Nghiệm thu
Dán NGUYÊN VĂN 2 lần chạy liên tiếp `test-redirections.php` (mỗi lần kết thúc `SUMMARY` + dòng checksum), và kết quả truy vấn
`SELECT id FROM wp_laca_seo_redirections` / `_cache` / `_404_logs` sau 2 lần chạy (chỉ được còn đúng 100003 và 6 đang chờ người dùng).
`php -l` các file sửa, `yarn lint` dòng problems. Báo cáo ≤ 30 dòng ngoài output. Không xoá file, không cài package, không commit.
