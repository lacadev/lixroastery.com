# Plan — Top Hero tôn trọng công tắc "Tràn viền 2 bên màn hình" (sau chuẩn hoá container c082c841)

## Lỗi
`block-top-hero` có attribute `fullBleed` (block.json:54, edit.js:233 "Tràn viền 2 bên màn hình", render.php:13/51 → class `block-top-hero--bleed`).
Nhưng wrapper block luôn có class `alignfull` (block supports align, nội dung đã lưu `align:"full"`). Commit c082c841 thêm R3
`.entry-content > .alignfull { width:100vw; margin calc(50% - 50vw) }` (`C/resources/styles/theme/layout/_general.scss:54`) ⇒ Top Hero LUÔN tràn viền,
bất kể `fullBleed`. VD bài journal 101 (`/journal/lix-roastery-la-ai/`): fullBleed TẮT nhưng ngoài trang vẫn tràn viền.
`doc/tools/layout-audit.js` coi mọi `.block-top-hero` là alignfull nên không phát hiện.

## Quy cách đích (bổ sung cho R2 trong CLAUDE.md mục 6)
- **Tràn viền do setting của block quyết định, KHÔNG do class `alignfull` lưu trong nội dung.** Block có công tắc tràn viền:
  - BẬT: root có `alignfull` (+ class `--bleed`), full viewport; chữ trong `container-fluid`.
  - TẮT: root nằm trong lề chuẩn: mép trái/phải của khối (cả ảnh nền) = gutter của header (dùng mixin `container-gutter` dạng **margin**
    hoặc bọc ngoài 1 lớp container-fluid trong render — chọn 1 cách, không lồng padding), chữ không cần container riêng.
- render.php: loại class `alignfull`/`alignwide` khỏi wrapper khi `fullBleed` tắt (không phụ thuộc nội dung đã lưu ⇒ không phải sửa DB/bài cũ);
  khi bật thì đảm bảo có `alignfull`. edit.js: editor phản ánh đúng 2 trạng thái (không cần full viewport trong editor, chỉ cần khác biệt rõ).
- Mixin: mở rộng `@mixin container-gutter($property: padding)` để dùng được cho margin; không hardcode số.

## Việc cần làm
1. Sửa block-top-hero (render.php, edit.js nếu cần, style.scss) theo quy cách trên. Giữ nguyên parallax/zoom/overlay và rule `margin-top:-90` của hero đầu trang.
2. Rà **mọi block khác** (17 block site + block parent `lacadev-client/block-gutenberg/*`) có `supports.align` hoặc công tắc tương tự (bleed/full width):
   áp cùng nguyên tắc — `alignfull` chỉ khi setting cho phép. Liệt kê block đã rà trong báo cáo.
3. Cập nhật `layout-audit.js`: xác định block tràn viền theo class `--bleed`/setting thực (không theo tên block); thêm kiểm: block có `fullBleed`
   tắt ⇒ mép trái khối = gutterLeft ±1px và width = viewport − 2×gutter ±2px.
   Thêm URL bài 101 `/journal/lix-roastery-la-ai/` (hero TẮT tràn viền) và giữ `/journal/extremely-light-cua-lix-roastery/` (kiểm trạng thái thực của nó).
4. Cập nhật CLAUDE.md mục 6 (R2) + THEME-DEVELOPMENT-GUIDE.md 3.4: thêm câu "tràn viền theo setting, không theo class alignfull đã lưu".

## Nghiệm thu
`node doc/tools/layout-audit.js` — NGUYÊN VĂN output đầy đủ (mọi dòng PASS/FAIL + SUMMARY), kèm 2 ảnh 1440 + 390 của bài 101 và 1 bài có hero BẬT tràn viền
(ảnh để /private/tmp/laca-layout2/). `yarn build`, `yarn build:blocks`, `yarn lint` 0 error; `php -l` file sửa.
Ràng buộc: chỉ sửa 2 theme, không đổi DB/nội dung bài, không xoá file, không cài package, không commit/push, không kết thúc lượt khi đang chờ lệnh;
xong PHẢI gửi báo cáo ngắn qua agent-bridge.
