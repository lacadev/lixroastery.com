# Plan — Chuẩn hoá container / container-fluid (LIX)

## Hiện trạng (Claude khảo sát 22/10/2026)
- CSS: `C/resources/styles/theme/layout/_general.scss` — `.container{max-width:$xl}`, `.container-fluid{max-width:100%; padding-x 100/50/20/15px theo $lg/$md/$sm}`
  và bản vá `.container-fluid .container-fluid{padding-x:0}` (do template bọc thêm 1 lớp).
- `single.php` (journal/post): `article.single-post > div.container-fluid > div.single-post__content > [blocks]` ⇒ block `alignfull` (vd `block-top-hero`,
  không có class container-fluid) bị kẹp trong padding 100px của lớp ngoài, KHÔNG full-bleed; các block khác dựa vào bản vá để không cộng dồn padding.
- `page.php`: `div.wrapper-content > [blocks]` không container ⇒ cùng 1 block hiển thị khác giữa page và single.
- 16/17 block site có root `section.container-fluid`; `block-top-hero` là ngoại lệ (full-bleed). Core block (paragraph/heading/list/image…) đặt trực tiếp
  trong nội dung không có padding riêng.
- Template khác có dùng container: archive.php, archive-journal.php, archive-glossary.php, search.php, single-product.php, woocommerce/archive-product.php,
  woocommerce/single-product/related.php, template-parts/{page-hero,breadcrumb,newsletter-form,block-stats-info}.php, header.php, footer.php;
  parent `P/theme/page.php`.

## Quy cách đích (sau khi sửa phải đúng 100%)
R1. **Template KHÔNG bọc nội dung block (`the_content()`) trong `.container` / `.container-fluid`.** Bọc bằng `div.entry-content` (không padding ngang).
R2. **Mỗi block custom tự lo lề ngang**: root là `<section class="container-fluid …">` (lề chuẩn). Block cần tràn viền (hero, banner ảnh nền…) dùng root
    `<section class="… alignfull">` (KHÔNG container-fluid) và đặt nội dung chữ bên trong `<div class="container-fluid">`.
R3. **Core block / nội dung soạn tay** nằm trực tiếp trong `.entry-content` được CSS cấp cùng lề với container-fluid:
    `.entry-content > :where(:not(section):not(.alignfull):not(.alignwide))` nhận padding-x giống `.container-fluid` (dùng chung mixin, không lặp số);
    `.alignwide` = max-width $xl + căn giữa; `.alignfull` = full viewport.
R4. Phần ngoài nội dung (header, footer, breadcrumb, page-hero, tiêu đề archive, lưới archive, related…) dùng **đúng 1 lớp** `.container-fluid`
    (hoặc `.container` khi cần max-width $xl) — không lồng container trong container.
R5. Xoá bản vá `.container-fluid .container-fluid` chỉ khi đã hết mọi chỗ lồng (giữ lại nếu block Container/Tabs của parent vẫn lồng hợp lệ — khi đó
    ghi chú WHY). Lề thực tế mọi trang: mép trái nội dung chữ thẳng hàng với logo header ở mọi breakpoint.
R6. Thêm mixin `@mixin container-gutter` (padding-x theo breakpoint) trong abstracts, `.container-fluid` và R3 cùng dùng mixin này.

## Việc cần làm
1. Audit + sửa toàn bộ template liệt kê ở trên (child + parent `theme/page.php`, `single.php` nếu parent có) theo R1/R4.
2. Audit 17 block site + block parent `lacadev-client/block-gutenberg/*` (FAQ/HowTo/TOC Phase 5, block Container/Tabs nếu có) theo R2;
   `block-top-hero`: root alignfull + nội dung trong container-fluid. Block Container/Tabs của parent: xác định cách lồng hợp lệ.
3. SCSS theo R3/R5/R6 (BEM, không nesting quá 3 cấp, không `!important` mới trừ khi cần thắng rule cũ — ghi WHY).
4. Journal TOC (`nav.journal-toc`) và các phần chèn thêm trong single giữ đúng lề.
5. Viết **rule** vào: `C/CLAUDE.md` (mục 4 hoặc mục mới "Layout container") + `P/doc/THEME-DEVELOPMENT-GUIDE.md` (mục layout) — nội dung R1–R6
   ngắn gọn + ví dụ markup đúng/sai cho block thường và block tràn viền. Không sửa CLAUDE.md gốc repo ngoài phần này.

## Nghiệm thu — MỘT script `C/doc/tools/layout-audit.js` (Puppeteer, cookie runtime KHÔNG ghi file, abort mọi POST, ảnh /private/tmp/laca-layout/)
URL: `/`, `/manifesto/`, 1 page có block (vd `/?page_id=1278&preview=true` khi đăng nhập hoặc 1 page publish có top-hero), 2 journal
(`/journal/extremely-light-cua-lix-roastery/`, `/journal/lix-roastery-at-cafe-show-hanoi-2026/`), `/journal/`, `/shop/`, 1 product, `/product-category/ca-phe-specialty/`,
`/?s=cafe`, 1 URL 404 — ở 1440, 1024, 768, 390.
Mỗi URL×width in 1 dòng `PASS|FAIL <url> <w> …` kiểm:
- (a) `gutterLeft` = mép trái box nội dung chữ của header (logo) ; với mọi phần tử con cấp 1 của `.entry-content`/vùng nội dung: nếu không alignfull thì
  mép trái nội dung chữ (padding-box của container-fluid hoặc chính core block) = gutterLeft ±1px; nếu alignfull thì `left=0` và `width=viewport`.
- (b) không có `.container-fluid` nào là con cháu của `.container-fluid` khác (trừ block Container/Tabs nếu R5 cho phép — liệt kê rõ).
- (c) `scrollWidth - innerWidth = 0` (không tràn ngang).
Cuối: `SUMMARY pass=N fail=M`. Chạy trước khi sửa (lưu output "BEFORE") và sau khi sửa ("AFTER").
Thêm: `yarn build`, `yarn build:blocks`, `yarn lint` 0 error; `php -l` file PHP sửa.

## Báo cáo NGẮN
(1) file sửa; (2) NGUYÊN VĂN `SUMMARY` + các dòng FAIL của BEFORE và toàn bộ output AFTER; (3) build/lint dòng cuối; (4) đoạn rule đã thêm vào CLAUDE.md.
Ràng buộc: chỉ sửa trong 2 theme, không xoá file, không cài package, không đổi nội dung bài viết/DB, không commit/push, không kết thúc lượt khi đang
chờ kết quả lệnh, xong PHẢI gửi báo cáo qua agent-bridge.
