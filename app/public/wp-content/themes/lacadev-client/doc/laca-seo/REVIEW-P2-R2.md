# Review Phase 2 — Vòng 2 (Claude)

Báo cáo vòng trước ghi "compare-analysis PASS 100%" nhưng **công cụ đó không đo đúng**: chạy analyzer RM trong Node sandbox với `wp.wordcount`, `wp.url.cleanForSlug`, `wp.autop` bị thay bằng hàm giả; keyword = chữ đầu tiêu đề (vd "From"); title/description không render template; không mở editor thật. Kết quả "khớp" vô giá trị.

Claude đã kiểm trong **wp-admin thật** (cùng trang edit có cả metabox RM và Laca, nhập keyword, không lưu) bằng:
`/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/p2/cmp.js`
(`node cmp.js '[[866,"extremely light"],[29,"aconchego"]]'` — cookie sinh lúc chạy, đọc `rankMathEditor.resultManager.getResult(kw).results` vs `window.lacaSeoAnalysis`).

Kết quả thật:

```
# post 908 kw="cafe show"        RM(noAI)=52 Laca=53  diffs=1   keywordInTitle RM [36,36] Laca [38,38]
# post 866 kw="extremely light"  diffs=3  keywordInTitle 36≠38 · keywordInMetaDescription RM 2 Laca 0 · titleHasPowerWords RM 1 Laca 0
# post 29  kw="masterpieces auction" RM(noAI)=65 Laca=9  diffs=13
   Laca SERP = "Tiêu đề bài viết" / placeholder → không đọc được title/content ở Classic editor (product)
   thiếu hasProductSchema(2), isReviewEnabled(2); RM product KHÔNG có keywordInSubheadings, links*, titleSentiment, titleHasNumber, contentHasTOC (Pro loại bỏ)
SERP (mọi bài): RM "… - LIX ROASTERY" + mô tả từ template; Laca tiêu đề thô + chữ placeholder
```

## A. Analyzer / editor — bắt buộc

1. **Locale**: truyền `locale` từ PHP (`get_locale()` → phần trước `_`, site hiện tại `en`) vào `lacaSeoEditorData` và vào `new LacaSeoAnalyzer(...)`. Bỏ mặc định `'vi'` (`analyzer.js:46`).
2. **Classic editor (product, các CPT không dùng block editor)**: `getEditorContent()` coi có store `core/editor` là Gutenberg — sai vì `wp-edit-post` được enqueue cả ở classic screen. Nhận diện bằng `document.body.classList.contains('block-editor-page')` (hoặc `wp.data.select('core/editor').getCurrentPostId()`), classic thì đọc `#title`, tinymce/`#content`, `#post_name`, `#excerpt`. Bỏ `wp-edit-post`/`wp-plugins` khỏi dependency nếu không dùng; thêm `wp-wordcount`, `wp-url`, `wp-autop`.
3. **Title/description đưa vào analyzer và SERP = bản đã render** như RM (`getSerpTitle`/`getSerpDescription`): ô trống → template `pt_{pt}_title` / `pt_{pt}_description`; render qua AJAX `laca_seo_preview_vars` (có sẵn ở `AdminPage`, nhận `post_id`) với debounce 400ms, kèm title/excerpt/content đang sửa (chưa lưu) để biến `%title%`, `%excerpt%` ra giá trị hiện tại. Bỏ chữ placeholder khỏi SERP khi template có nội dung. Counter đếm chuỗi đã render.
4. **Power words**: dùng đúng file RM — chép nguyên `wp-content/plugins/seo-by-rank-math/assets/vendor/powerwords/*.php` vào `lacadev-client/resources/data/laca-seo/powerwords/` (giữ header license), PHP nạp theo locale, `array_map('strtolower')`; locale không có file → `[]` ⇒ test `titleHasPowerWords` **không có trong results** (giống RM `newResult()` trả null). Xoá danh sách tự chế ở `PostMetabox::getPowerWordsList()` (sửa nội dung hàm, không xoá file). So khớp: tách title lower theo `' '`, đạt khi `w === p || (w.startsWith(p) && w.length - p.length <= 4)`.
5. **titleSentiment**: KHÔNG cài package. Dùng dữ liệu đã trích từ bundle RM: `/private/tmp/.../scratchpad/rm-sentiment-data.json` = `{ labels (AFINN-165 + emoji, 4149), negators (12), extras (6786) }` → chép vào `resources/scripts/admin/laca-seo/data/sentiment.json`. Thuật toán (port từ module `sentiment` RM bundle): `tokens = text.toLowerCase().replace(/\n/g,' ').replace(/[.,\/#!?$%\^&\*;:{}=_\`"~()]/g,' ').replace(/\s\s+/g,' ').trim().split(' ')`; duyệt từ cuối, `dict = {...labels, ...extras}`, có token → `score = dict[t]`, nếu token trước nằm trong `negators` thì đảo dấu; cộng dồn. Input = `getWords(titleLower).join(' ')`. Đạt khi tổng ≠ 0. Chỉ chạy khi locale `en`, locale khác → không đưa test vào results.
6. **Bộ test theo loại**: product (khi `RankMathPro` logic: Woo active) = bộ post trừ danh sách `exclude_tests` trong `seo-by-rank-math-pro/includes/class-common.php` (~dòng 125-148) + `hasProductSchema` (2, đạt khi schema mặc định/riêng của bài là Product — đọc cách RM Pro tính) + `isReviewEnabled` (2, `get_option('woocommerce_enable_reviews','yes')==='yes'`). Trang chủ tĩnh/trang blog loại theo `Post_Screen::exclude_tests()`.
7. **keywordNotUsed**: gọi AJAX `laca_seo_is_keyword_new` thật (cache theo keyword như RM `keywordsChecked`), không hard-code.
8. **Thumbnail**: truyền ảnh đại diện + alt (Gutenberg `getEditedPostAttribute('featured_media')` → `core` media; classic `#_thumbnail_id`) cho `keywordInImageAlt`, `contentHasAssets` (RM: không có text nhưng có thumbnail → 1).
9. Port lại các phần còn tự chế cho đúng file tham chiếu `rm-analyzer-reference.txt` (getWords `C`, `Ht`, getParagraphs, getLinkStats, keywordDensity dùng mọi keyword). Mỗi hàm ghi comment 1 dòng tên hàm RM tương ứng.

## B. PHP — bắt buộc

1. `LockModifiedDate.php:35-44`: khi nonce metabox hợp lệ thì `locked = isset(laca_seo[_laca_seo_lock_modified_date]) && === 'on'` (bỏ tick ⇒ mở khoá ngay lần lưu đó); chỉ khi KHÔNG có nonce (REST/Gutenberg, quick edit…) mới dùng meta hiện có.
2. Bulk edit: `wp_ajax_save_bulk_edit_posts` **không tồn tại trong WP core**. Bulk edit là submit form `edit.php` → `bulk_edit_posts()` → `wp_update_post()` → `save_post`. Xử lý trong `save_post` khi `isset($_REQUEST['bulk_edit'])` + `check_admin_referer('bulk-posts')` + `edit_post` cap, đọc `laca_seo_bulk_robots`.
3. Twitter: xác nhận bằng `compare-head.php` rằng `twitter:card` của bài có `summary_card` ra đúng như RM (RM xuất `summary`?). Dán output.

## C. Nghiệm thu (dán output thật, không tóm tắt)

1. Viết lại `doc/laca-seo/tools/compare-analysis.js` theo cách của `scratchpad/p2/cmp.js` (editor thật, cookie runtime, không lưu, không dùng sandbox Node). Chạy ít nhất: `[[908,"cafe show"],[908,"lix roastery,cafe"],[866,"extremely light"],[866,"roasting philosophy"],[29,"aconchego"],[29,"masterpieces auction"],[928,"manifesto"]]` + 1 term `product_cat` (trang `term.php`). Yêu cầu: **diffs=0 mọi dòng**, `RM(noAI) == Laca`, SERP title/desc trùng RM (so sau khi trim).
2. Lưu ý site local chậm: dùng `waitUntil:'domcontentloaded'` + `waitForFunction(() => window.rankMathEditor && window.lacaSeoAnalysis)`, timeout 180s, mỗi bài 1 lần chạy nếu cần.
3. Quick edit bài có pillar+lock → 2 meta còn; bỏ tick lock rồi Update → `post_modified` đổi; bulk edit noindex 2 bài → robots có `noindex`; khôi phục.
4. `php -l`, `yarn build` (child theme), `yarn lint` — dán dòng kết quả cuối.
5. Không xoá file (safety rule mới): file thừa thì liệt kê để người dùng tự xoá. Không cài package. Không commit.
