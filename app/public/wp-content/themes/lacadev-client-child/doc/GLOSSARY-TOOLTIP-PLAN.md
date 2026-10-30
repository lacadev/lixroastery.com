# GLOSSARY AUTO-LINK + TOOLTIP — PLAN

Theme: `app/public/wp-content/themes/lacadev-client-child` (mọi đường dẫn bên dưới tính từ đây).
Chỉ sửa trong child theme. Không xoá file. Không commit/push.

## Mục tiêu

1. Ở mọi nội dung đi qua `the_content` (bài viết, Journal, page, product, **kể cả output của custom block** vì `do_blocks` chạy ở priority 9), mỗi thuật ngữ Glossary (title của post type `glossary`, **không phân biệt hoa/thường**) được bọc thành link gạch chân chấm (dotted) như ảnh mẫu "Arabica".
2. Hover (hoặc focus bàn phím) → hiện ô tooltip nhỏ chứa định nghĩa (nội dung bài glossary), **font-size 12px**, `max-height` cố định, **scroll dọc** được, không bung full.
3. Click → `get_post_type_archive_link('glossary') . '#<slug>'`, vd `https://lixroastery.com/glossary/#agtron-scale`.
4. Trang `/glossary/` khi có hash khớp: scroll tới thuật ngữ đó và **tô nền xám** cả khung (cột tên + cột định nghĩa) như ảnh 3.

## 1. PHP — `app/src/Glossary/GlossaryAutoLink.php` (class mới)

Nạp trong `app/hooks.php` cùng kiểu các class khác (`require_once CHILD_APP_DIR . 'src/Glossary/GlossaryAutoLink.php'; new GlossaryAutoLink();`). Không namespace (giống `CptGridAjaxHandler`).

### 1.1 Danh sách thuật ngữ — `getTerms(): array`
- `WP_Query` (KHÔNG dùng `get_posts` — nó `suppress_filters` làm hỏng lọc ngôn ngữ Polylang): `post_type=glossary`, `post_status=publish`, `posts_per_page=-1`, `no_found_rows=true`, `update_post_meta_cache=false`, `update_post_term_cache=false`.
- Mỗi phần tử: `['id', 'title', 'slug' => urldecode(post_name), 'definition' => html]`.
- `definition`: render từ `post_content` **không qua `apply_filters('the_content')`** (tránh đệ quy + RelatedPosts/SEO filter của parent): `wpautop(do_blocks(strip_shortcodes($content)))` rồi `wp_kses_post`.
- Sắp theo `mb_strlen(title)` giảm dần (để "Agtron Scale" thắng "Agtron" nếu cả hai tồn tại).
- Cache transient `laca_glossary_terms_<lang>` (lang = `pll_current_language()` nếu hàm tồn tại, ngược lại `get_locale()`), 12h. Xoá mọi transient `laca_glossary_terms_*` khi `save_post_glossary`, `deleted_post`, `trashed_post`, `untrashed_post` (với post type glossary). Có thể giữ danh sách key đã set trong 1 option nhỏ để xoá, hoặc dùng 1 option "version" ghép vào key — chọn cách đơn giản.

### 1.2 Filter `the_content` priority **20**
Bỏ qua khi: `is_admin()`, `is_feed()`, `wp_doing_ajax()` (giữ nguyên nếu cần cho AJAX thì thôi — bỏ qua cho an toàn), REST request (`defined('REST_REQUEST') && REST_REQUEST`), `is_post_type_archive('glossary')`, `is_singular('glossary')`, chuỗi rỗng, không có thuật ngữ.
Gọi `linkify($html, $terms, $currentPostId)` rồi trả về.

### 1.3 `linkify(string $html, array $terms, int $excludeId = 0): string` — public, thuần (không gọi DB) để test được
- Loại thuật ngữ có `id === $excludeId`.
- Ghép 1 regex: `'/(?<![\p{L}\p{N}_])(' . implode('|', preg_quote từng title, '/') . ')(?![\p{L}\p{N}_])/iu'`.
- Tách HTML bằng `preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE)`; chỉ thay trong đoạn **text** (không phải tag). Giữ stack các thẻ đang mở để **bỏ qua** text nằm trong: `a, button, script, style, textarea, select, option, code, pre, kbd, svg, h1, h2, h3, h4, h5, h6, figcaption, label` và trong phần tử có class `no-glossary`. Không động vào HTML comment `<!-- -->` (tag split phải giữ nguyên comment; comment có thể chứa `>` → xử lý `<!--.*?-->` trước như một token riêng).
- Text node đã là HTML-escaped (`&amp;`…): match trên text gốc là được (title thường không chứa entity); không decode/encode lại text.
- **Mỗi thuật ngữ chỉ link LẦN XUẤT HIỆN ĐẦU TIÊN** trong 1 lần gọi (tránh rối mắt). Các lần sau giữ nguyên chữ.
- Giữ nguyên chữ hoa/thường gốc trong bài (`$m[1]`), không thay bằng title.
- Markup sinh ra (escape đủ):
  ```html
  <a class="glossary-term" href="{archive}#{rawurlencode(slug)}" data-glossary="{esc_attr(slug)}" aria-describedby="glossary-tip">{text}</a>
  ```
  `aria-describedby` chỉ cần đặt bằng JS lúc hiện tooltip — bỏ khỏi PHP cũng được.
- Ghi lại slug đã dùng vào `$this->used` để in định nghĩa ở footer.

### 1.4 In định nghĩa — `wp_footer`
Với mỗi slug trong `$this->used`, in 1 lần:
```html
<template id="glossary-def-{esc_attr(slug)}"><div class="glossary-tip__title">{esc_html(title)}</div>{definition (đã kses)}</template>
```
Không có slug nào thì không in gì.

## 2. JS — `resources/scripts/theme/components/glossary-tooltip.js` (mới) + import trong `resources/scripts/theme/index.js`

Vanilla JS, cùng style file `glossary-nav.js` (tab, space trong ngoặc, khởi tạo theo `readyState`).
- Tạo 1 phần tử duy nhất `<div id="glossary-tip" class="glossary-tip" role="tooltip" hidden>` gắn vào `body`.
- Event delegation trên `document` cho `mouseover`/`focusin` vào `a.glossary-term` → lấy `<template id="glossary-def-{slug}">`, clone nội dung vào tooltip, định vị dưới link (`getBoundingClientRect` + `scrollX/Y`); nếu tràn đáy viewport thì đặt phía trên; kẹp ngang trong viewport với lề 8px. Không có template → không hiện.
- Ẩn sau ~150ms khi rời link **và** rời tooltip (để rê chuột vào tooltip cuộn đọc được). `Escape` ẩn. `focusout` ẩn.
- Chỉ bật hover khi `matchMedia('(hover: hover)')`; thiết bị cảm ứng: click đi thẳng tới trang glossary (hành vi link mặc định).

## 3. JS — trang Glossary: mở rộng `resources/scripts/theme/components/glossary-nav.js`
- Thêm hàm `highlightFromHash()`: `decodeURIComponent(location.hash.slice(1))`, bỏ `/` cuối nếu có (user có thể gõ `#agtron-scale/`). Tìm `.glossary-page__item[data-slug="..."]` (dùng `CSS.escape`). Bỏ class `glossary-page__item--active` ở item khác, thêm vào item tìm được, `scrollIntoView({ block: 'center' })` (không smooth lúc load).
- Gọi khi init và trên `hashchange`.
- Chỉ chạy khi có `.glossary-page`.

## 4. PHP — `theme/archive-glossary.php`
- Mỗi `.glossary-page__item` thêm `id="{esc_attr(urldecode(post_name))}"` và `data-slug="{cùng giá trị}"`.
- **Đang có** `id="glossary-letter-X"` trên item đầu của mỗi chữ cái → không thể có 2 id. Chuyển id chữ cái sang 1 phần tử neo riêng đặt ngay trước item: `<span class="glossary-page__anchor" id="glossary-letter-X"></span>` (nav A-Z vẫn hoạt động y như cũ; neo cần `scroll-margin-top` giống item).
- Không đổi gì khác.

## 5. SCSS
### 5.1 `resources/styles/theme/components/_glossary-tooltip.scss` (mới, hoặc đặt cạnh component khác nếu thư mục khác — kiểm `child.scss` để `@use` đúng chỗ)
- `.glossary-term`: `color: inherit; text-decoration: underline dotted; text-underline-offset: 3px; text-decoration-thickness: 1px; cursor: help;` hover: `text-decoration-style: solid`.
- `.glossary-tip`: `position: absolute; z-index: 9999; width: min(320px, calc(100vw - 16px)); max-height: 180px; overflow-y: auto; padding: 12px 14px; background: #fff; color: $darkColor; border: 1px solid rgba(0,0,0,.12); box-shadow: 0 6px 24px rgba(0,0,0,.12); font-family: $mainFont; font-size: 12px; line-height: 1.5;` `p { margin: 0 0 .6em } p:last-child { margin: 0 }`. Ẩn bằng `[hidden]`.
- `.glossary-tip__title`: `font-family: $subFont; text-transform: uppercase; font-size: 12px; margin-bottom: 6px;`
- Dùng biến trong `abstracts/variables.scss` có sẵn; đơn sắc, không màu nhấn.
### 5.2 `resources/styles/theme/layout/_glossary.scss`
- `&__item--active { background: #e8e8e8; }` + padding để nền không dính chữ (ảnh 3: ~`rem(16) rem(12)`), giữ thẳng cột — cách gọn: item luôn có `padding: rem(16) rem(12)` và `margin-left/right: rem(-12)` (âm để chữ vẫn thẳng hàng như hiện tại), chỉ đổi background khi active; thêm `transition: background-color .3s`.
- `&__item { scroll-margin-top: rem(110); }` và `&__anchor { display:block; scroll-margin-top: rem(110); }` (header sticky; giống `_journal-single.scss`).

## 6. Nghiệm thu (Antigravity tự chạy, dán nguyên output)

### 6.1 Script `doc/tools/glossary-autolink-test.php` chạy bằng `wp eval-file` (không render the_content trong CLI — sẽ tràn bộ nhớ, xem ghi chú env)
Khởi tạo class, gọi `linkify()` với `$terms` giả (không dùng DB) và in `PASS/FAIL` từng ca + tổng:
1. `<p>The Agtron scale measures roast.</p>` + term "Agtron Scale" → có đúng 1 `<a class="glossary-term"` bọc chữ `Agtron scale` (giữ hoa thường gốc), href kết thúc `#agtron-scale`.
2. Hai lần xuất hiện → chỉ link lần đầu (đếm `glossary-term` = 1).
3. Text trong `<a href="#">Agtron Scale</a>` và `<h2>Agtron Scale</h2>` và `<code>` → không link.
4. Không link khi là một phần từ khác: term "Arabica" với `Arabicas`/`xArabica` → 0 link.
5. Term dài thắng term ngắn: terms "Agtron" + "Agtron Scale", text "Agtron Scale" → link `#agtron-scale`, không lồng link.
6. Không phá attribute: `<img alt="Agtron Scale" src="x">` → HTML ra giữ nguyên tag img.
7. Comment `<!-- a > Agtron Scale -->` giữ nguyên.
8. Tiếng Việt: term "Cà phê đặc sản" với text `<p>CÀ PHÊ ĐẶC SẢN rất ngon</p>` → 1 link.
9. `$excludeId` = id của term → 0 link.
10. Output của mọi ca: số `<a` mở bằng số `</a>`.
### 6.2 Build/lint
`php -l` mọi file PHP đã sửa/tạo; `yarn lint`; `yarn build`. Dán output (đuôi đủ thấy kết quả).
### 6.3 HTTP thật
`curl -s http://lixroastery.local/<một bài journal có chứa 1 term glossary>/ | grep -o 'class="glossary-term"[^>]*>[^<]*' | head` và `grep -c 'glossary-def-'`; `curl -s http://lixroastery.local/glossary/ | grep -o 'data-slug="[^"]*"' | head -3` và kiểm id `glossary-letter-A` vẫn có. Nếu local không có bài nào chứa term, ghi rõ.

## 7. Báo cáo
Ngắn: danh sách file tạo/sửa, nguyên văn output 6.1–6.3, quyết định nào khác spec (nếu có) và lý do.
