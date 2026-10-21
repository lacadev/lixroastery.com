# Review Phase 2 — Vòng 1 (Claude)

Tài liệu gốc để port (đã trích từ bundle RM, KHÔNG nằm trong repo, chỉ đọc):
- `/private/tmp/claude-501/-Users-lacadev-Documents-wordpress-lixroastery/e4577ea8-8f21-416f-8a77-913513a06e67/scratchpad/rm-analyzer-reference.txt` — code gốc của 21 test (newResult/getResult/isApplicable/calculateScore/getScore), Paper, Researcher (getWords `C`, wordCount `R` = `wp.wordcount.count(text,'words')`, getParagraphs `Ve`, getLinkStats `K`, getImages), helper `$e`, `Ue`, `Ke`, `Ht`, `St`.
- `.../scratchpad/rm-sentiment-extras.json` — 6.786 mục từ điển `extras` RM truyền vào thư viện `sentiment` (npm `sentiment@5`, AFINN-165).
- Công thức tổng điểm (ResultManager.refreshScore): `round(sum(score) / sum(maxScore) * 100)`; Analyzer.generateResults: test **không applicable vẫn được tính** bằng `newResult()` (score 0, maxScore đầy đủ); chỉ bỏ khi `newResult()` trả `null` (titleSentiment khi locale ≠ en, titleHasPowerWords khi danh sách power words rỗng).

## A. BLOCKER — `analyzer.js` không phải port 1:1 (P2.2 chưa đạt)

Analyzer hiện tại tự chế lại logic, sai lệch so với RM ở hầu hết test. Phải **viết lại theo đúng code gốc** trong file tham chiếu, giữ nguyên tên test. Các sai lệch đã xác nhận:

| Test | RM thật | Laca hiện tại |
|---|---|---|
| Chuẩn hoá chữ | chỉ lowercase + `$e` (chuẩn hoá ngoặc kép/đơn) + các bước strip của `C` — **không bỏ dấu tiếng Việt** | `cleanDiacritics` bỏ dấu → "ca phe" khớp "cà phê" (đậu sai) |
| Locale | `rankMath.localeFull` → site `en_US` ⇒ `en` | mặc định `'vi'` ⇒ keywordInTitle 38 thay vì 36 |
| keywordIn10Percent | `getWords(textLower)`; **chỉ cắt 10% khi > 400 từ**, ngược lại dùng toàn bộ | luôn cắt `ceil(10%)` |
| keywordInContent / Title / MetaDescription | `Ht()`: `C(text).join(' ')` includes `C($e(keyword)).join(' ')` | `includes` trên chuỗi bỏ dấu |
| keywordInPermalink | `cleanForSlug(url.replace(/[-_]/g,'-'))` includes `cleanForSlug(getKeywordPermalink)` hoặc `cleanForSlug(getPermalinkWithStopwords)` (dùng `wp.url.cleanForSlug`) | tự ghép `-` |
| keywordInSubheadings | regex `<h[2-6][^>]*>[\s\S]*?{từ1}[\s\W]+{từ2}…[\s\S]*?</h[2-6]>` trên textLower | regex `.*?` một dòng, bỏ dấu |
| keywordInImageAlt | thumbnailAlt trước; rồi alt trong content với regex từ khoá `' '→'.*'`; `[gallery]` ⇒ đạt | chỉ duyệt mảng `images` (editor truyền `[]` ⇒ luôn fail) |
| keywordDensity | dùng **tất cả keyword** (chính + phụ, `combinations`), regex nối các keyword bằng OR, `wordCount` của `wp.wordcount`; biên: `<.5` low, `>2.5` high, `inRange(.5,.75)`=2, `inRange(.76,1)`=3, còn lại (kể cả 0.75–0.76) = 6 | chỉ keyword chính, `\b` (hỏng với Unicode), biên khác |
| lengthContent | `wp.wordcount.count`, `inRange(e, boundary, prevBoundary)` | `split(/\s+/)` |
| lengthPermalink | `paper.getUrl().length` (slug, không phải path) | path đầy đủ |
| linksHasExternals / linksHasInternal | `getLinkStats`; `total===0` ⇒ không có điểm; internal theo `rankMath_analysis_isInternalLink` / host | regex tự chế |
| linksNotAllExternals | `total===0` ⇒ **fail**; đạt khi `externalDofollow > 0` (tính cả cài đặt nofollow external links + domain list) | `externalCount===0` ⇒ **đạt** (ngược) |
| titleStartWithKeyword | `title.indexOf(kw) < floor(title.length/2)` (theo **ký tự**) | nửa đầu theo **từ** |
| titleSentiment | chỉ locale `en`; `new Sentiment().analyze(words, {extras})`.score ≠ 0; locale khác ⇒ `null` (không tính) | danh sách 46 từ tự nghĩ, luôn tính |
| titleHasPowerWords | `rankMath.assessor.powerWords` = file `RM/assets/vendor/powerwords/{locale}.php` (site `en` ⇒ `en.php`), so từng từ: `w===p` hoặc `startsWith(w,p) && w.length-p.length<=4`; rỗng ⇒ `null` | danh sách tự nghĩ ghép tiếng Việt |
| titleHasNumber | `/\d+/` trên `getTitle()` | đúng |
| contentHasTOC | `hasTOCPlugin` (PHP `has_toc_plugin()` của RM) **hoặc** content chứa `wp-block-rank-math-toc-block` | regex `toc` tuỳ ý |
| contentHasShortParagraphs | `getParagraphs` (autop + `<p>` regex, `wordCount` từng đoạn) `> 120` | regex `<p>` không bắt xuống dòng |
| contentHasAssets | ảnh: `{0:0,1:1,2:2,3:4}` else 6 (đếm qua `getImages`, có thumbnail); video: `{0:0,1:1}` else 2; `min(6, …)`; **không có text nhưng có thumbnail ⇒ 1** | cộng trùng `images.length` + regex |
| keywordNotUsed | maxScore 0; AJAX kiểm tra | editor **hard-code `isKeywordNew: true`**, endpoint `laca_seo_is_keyword_new` không được gọi |
| hasContentAI | RM luôn có test này (max 5) | Laca bỏ Content AI ⇒ **không đưa vào**; khi so điểm thì tính lại điểm RM sau khi loại `hasContentAI` |
| Product (Pro) | `product`: loại bộ test trong `RM-pro/includes/class-common.php` (~dòng 130-155) + thêm `hasProductSchema` (2) và `isReviewEnabled` (2, khi Woo, = option `woocommerce_enable_reviews`) | không có |

Yêu cầu port:
1. Viết lại `resources/scripts/admin/laca-seo/analyzer.js` thành các class/hàm tương ứng 1:1 với RM (Paper, Researcher, từng Analysis). Dùng `wp.wordcount`, `wp.url.cleanForSlug`, `wp.autop` qua dependency script (`wp-wordcount`, `wp-url`, `wp-autop`) — không tự viết lại.
2. Thêm package `sentiment@5.0.2` (**chỉ sau khi người dùng đồng ý** — safety rule cấm tự `yarn add`), chép `rm-sentiment-extras.json` vào `resources/scripts/admin/laca-seo/data/sentiment-extras.json` và import.
3. Power words: PHP đọc `get_locale()` ngắn → nạp danh sách từ file trong theme `resources/data/laca-seo/powerwords/{locale}.php` (chép nguyên các file từ `RM/assets/vendor/powerwords/`, giữ license GPL comment nếu có), `array_map('strtolower')`. Bỏ danh sách tự nghĩ trong `PostMetabox::getPowerWordsList()`.
4. `hasTOCPlugin`: port `has_toc_plugin()` của RM (đọc trong `RM/includes/admin/metabox/class-post-screen.php`) + nhận diện block TOC của theme nếu có (ghi chú rõ).
5. Danh sách test theo đối tượng y như RM: post (`Post_Screen::get_analysis()` trừ `hasContentAI`; trang chủ tĩnh/trang blog loại theo `exclude_tests()`), term và user chỉ 5 test (`keywordInTitle, keywordInMetaDescription, keywordInPermalink, keywordNotUsed, titleStartWithKeyword`), product theo Pro.
6. Dữ liệu đầu vào editor phải giống Paper của RM: **title = tiêu đề SEO đã render biến** (trống ⇒ render template `pt_{pt}_title`), **description = mô tả đã render** (trống ⇒ template `pt_{pt}_description`, vd `%excerpt%`), url = slug, permalink theo `permalinkFormat`, thumbnail + thumbnailAlt từ ảnh đại diện, keywords = toàn bộ danh sách (chính dùng cho các test keyword, phụ cho density).
7. `window.lacaSeoAnalysis = { score, results: { [id]: { ok, score, maxScore } } }` với `ok = score > 0` (đúng `hasScore()` của RM).

## B. Lỗi logic khác (bắt buộc sửa)

1. **Quick Edit xoá dữ liệu**: `savePostMeta` chấp nhận nonce quick edit, rồi vòng toggles `delete_post_meta` mọi key không có trong POST ⇒ quick edit xoá `is_pillar_content`, `lock_modified_date`, `dont_show_seo_score`, `twitter_use_facebook`. Tách xử lý quick edit chỉ cho 4 field nó có (title/description/focus_keyword/canonical) — không chạy các bước khác.
2. **Không tắt được "Twitter dùng dữ liệu Facebook"**: bỏ tick ⇒ delete meta ⇒ lúc render `'' → 'on'`. RM lưu tường minh `'on'`/`'off'`; lưu `'off'` khi bỏ tick (đối chiếu Paper/OpenGraph Phase 1 đọc giá trị này).
3. **Bỏ hết robots không xoá được meta**: nhánh else dựa vào `$_POST['laca_seo_advanced_present']` nhưng form không có field này. Thêm hidden `laca_seo_advanced_present=1` trong tab Nâng cao.
4. **LockModifiedDate** đọc `$_POST['_laca_seo_lock_modified_date']` nhưng form gửi `laca_seo[_laca_seo_lock_modified_date]` ⇒ lần tick/bỏ tick đầu không có hiệu lực. Đọc đúng key (kèm `wp_unslash`) và chỉ tin POST khi có nonce metabox hợp lệ.
5. **Bulk edit "Chỉ thị Robots"** (`laca_seo_bulk_robots`) không có handler ⇒ UI chết. Xử lý trong `bulk_edit_posts` (hoặc `save_post` có kiểm `bulk_edit` + nonce `bulk-posts`) hoặc bỏ field.
6. **Fallback `rank_math_*`** (đã cấm từ Phase 1): bỏ ở `PostList.php:155`, `TermList.php:56`, `FrontendSeoScore.php:119`. Shortcode `[rank_math_seo_score]` chỉ đăng ký khi RM không active **sau** khi plugin load (`init` priority muộn) — giữ được nhưng ghi chú.
7. **SERP preview** hiển thị template thô (`%title% %sep%…`) và đếm ký tự trên chuỗi thô: spec P2.1 yêu cầu render qua AJAX `laca_seo_preview_vars` với `post_id` (debounce 400ms), counter đếm chuỗi đã render. Mặc định trống phải hiện kết quả của template loại bài.
8. **focus_keyword** lưu `implode(', ')` — RM lưu `implode(',')`; giữ đúng định dạng RM để CSV/so sánh/importer đồng nhất; khi đọc thì `trim` từng phần.
9. **Twitter card type**: RM dùng `summary_card` (không phải `summary`). Sửa option + whitelist + đảm bảo frontend (Phase 1 `Twitter.php`) map đúng giá trị.
10. **Gutenberg**: `wp.data.subscribe` gọi `getEditedPostContent()` (serialize toàn bộ block) mỗi lần store đổi ⇒ chậm editor. So sánh tham chiếu `getBlocks()` / `getEditedPostAttribute('content')` trước, chỉ serialize khi đổi.
11. Media preview: `preview.innerHTML = \`<img src="${attachment.url}">\`` — tạo element bằng DOM API (`img.src = …`) thay vì nội suy HTML.
12. Term metabox thiếu advanced robots, Twitter, `facebook_image_id` so với RM taxonomy metabox — bổ sung cho đủ các key RM lưu cho term (đối chiếu `rank_math_*` trong `wp_termmeta` + RM `includes/admin/metabox/`).
13. CSV import: `canonical_url`, `*_thumbnail` phải `esc_url_raw`; thiếu import `advanced_robots` (JSON) và `primary_term`; `is_pillar_content` chỉ nhận `on`/rỗng.

## C. Acceptance cần làm lại sau khi sửa (P2.7)

- `doc/laca-seo/tools/compare-analysis.js` (Puppeteer, cookie sinh lúc chạy, KHÔNG lưu file cookie, KHÔNG bấm lưu): với ≥ 6 bài (journal, product, page, 1 bài không keyword, 1 bài có ảnh+link ngoài nofollow, 1 term product_cat) đọc kết quả RM từ `wp.data.select('rank-math')` / `rankMathEditor.assessor` và `window.lacaSeoAnalysis`; **từng test ok/fail phải khớp 100%**, điểm khớp tuyệt đối sau khi bỏ `hasContentAI` khỏi phía RM. In bảng chênh lệch. Đưa nguyên output vào báo cáo.
- `compare-meta.php`: chạy lại, dán output thật.
- Quick edit 1 bài có pillar + lock date ⇒ kiểm tra 2 meta còn nguyên.
- Lưu Gutenberg thật 1 bài nháp tạo bởi script rồi xoá hẳn (hoặc dùng bài có sẵn và khôi phục meta + `post_modified`).
- `php -l`, `yarn build`, `yarn lint` (0 error).
- Khôi phục toàn bộ dữ liệu; không commit.
