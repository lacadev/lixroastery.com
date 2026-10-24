# Plan — Vòng 2: bảng danh sách (Journal…) + tự lưu AJAX chỉ khi có thay đổi thật

> Người thực hiện: Antigravity. Nối tiếp commit `c9575a04`. P = `themes/lacadev-client`, C = `themes/lacadev-client-child`. Build trong C.
> Công cụ nghiệm thu (Claude viết — **KHÔNG sửa logic kiểm**; thấy check sai thì báo lý do):
> - `P/doc/admin-ui/tools/list-table-audit.js` — baseline hiện tại: FAIL `wheel-vertical-only` 13, `no-duplicate-cell` 6, `taxonomy-popover` 6, `hover-no-shift` 5.
> - `P/doc/admin-ui/tools/autosave-audit.js` — baseline: `SUMMARY pass=13 fail=7` (tự lưu khi mở Laca Theme; lưu khi đang gõ; gõ rồi xoá về như cũ vẫn lưu).

## Người dùng báo (nguyên ý)
1. Journal: cột "Thứ tự" hiện 2 số trùng; sửa danh mục: lăn chuột xuống thì bảng lại chạy ngang; biểu tượng ✎ chỉ hiện khi hover làm ô nở ra, đẩy các cột
   sau → muốn hover không làm xê dịch gì (hoặc hiện sẵn).
2. Mọi trang có cuộn ngang: lăn chuột xuống thì vừa xuống vừa chạy ngang → tách riêng: lăn dọc = cuộn trang dọc, cuộn ngang là thao tác riêng.
3. Tự lưu AJAX: vừa vào trang chưa sửa gì đã tự lưu (Discord nhận "Lưu cài đặt (20 thay đổi)" toàn `— → —`). Muốn: chỉ lưu khi THẬT SỰ thay đổi;
   checkbox: tick là lưu; input/select: nhập/chọn xong, click ra ngoài mới lưu, đang gõ/đang chọn thêm thì không lưu; mượt, nhẹ.

## A. Bảng danh sách

### A1. Ô bị in trùng (cột "Thứ tự" hiện "3 3") — `no-duplicate-cell`
Nguyên nhân: Journal là post type phân cấp → WP gọi cả `manage_pages_custom_column` lẫn `manage_journal_posts_custom_column`; renderer đăng ký cho
`page` cũng in vào hàng Journal (DOM thấy 2 `.laca-inline-cell` với `data-post-type="page"` và `"journal"`). Sửa trong
`ListTableInlineEditor::setupListTableColumns()`: render chỉ khi `get_post_type($post_id) === $post_type` (hoặc chỉ dùng `manage_{$post_type}_posts_custom_column`).
Kiểm cả cột khác của AbstractPostType/DynamicCPT không bị trùng tương tự.

### A2. Lăn chuột — `wheel-vertical-only`
Xoá hẳn đoạn chặn `wheel` đổi `deltaY` → `scrollLeft` trong `P/resources/scripts/admin/list-table-inline.js` (dòng ~56–70). Không `preventDefault` sự kiện wheel
dọc ở bất kỳ đâu. Cuộn ngang chỉ bằng: thanh cuộn trên/dưới, trackpad vuốt ngang (deltaX, trình duyệt tự xử lý), Shift + lăn (trình duyệt tự xử lý).
Thêm `overscroll-behavior-x: contain` cho `.laca-table-scroll`; danh sách trong popover (A4) dùng `overscroll-behavior: contain` để lăn trong danh sách
không kéo trang/bảng.

### A3. Hover không xê dịch — `hover-no-shift` (đo bằng hover thật, ≤ 1px)
- Biểu tượng ✎ **luôn hiện mờ** (màu `#a1a1aa`, 12px) ở vùng dành sẵn bên phải ô: `.laca-inline-cell { position: relative; padding-right: 20px; }`
  + icon `position: absolute; right: 4px; top: 50%; transform: translateY(-50%)`. Hover chỉ đổi màu icon (`#18181b`) và nền ô (`#f4f4f5`) — không đổi
  padding/border/width/font-weight/display. Viền khi hover dùng `box-shadow: inset 0 0 0 1px #d4d4d8` (không dùng `border`).
- Không còn kiểu "pill" nền xám hiện ra khi hover làm ô to ra (ảnh người dùng).
- Ô đang mở editor: control nằm gọn trong kích thước ô hiện tại (input `width:100%`), hoặc dùng popover (A4) — không làm hàng/cột nở ra.

### A4. Trình chọn taxonomy dạng popover — `taxonomy-popover`, `taxonomy-popover-esc`
Bỏ `<select multiple>` "Giữ Ctrl/Cmd để chọn nhiều". Thay bằng popover `.laca-inline-popover`:
- Gắn vào `document.body` (KHÔNG nằm trong `.laca-table-scroll`), `position: fixed`, canh theo `getBoundingClientRect()` của ô, tự lật lên trên nếu
  thiếu chỗ phía dưới, đóng khi cuộn trang/khung bảng hoặc resize (đóng = lưu nếu có thay đổi), rộng 280px, cao tối đa 320px.
- Nội dung: ô tìm kiếm (lọc theo tên, bỏ dấu tiếng Việt khi so khớp) + danh sách checkbox phân cấp (thụt 16px mỗi cấp; taxonomy không phân cấp
  thì phẳng; taxonomy chỉ cho 1 term → radio). Tên term **giải mã entity** (`html_entity_decode` phía PHP trước khi `wp_json_encode`, hoặc
  `textContent` phía JS) — không còn "Destoning &amp; Magnetic".
- Lưu khi đóng popover (click ra ngoài / Esc = huỷ, không lưu / nút "Xong" = lưu) và chỉ khi tập term khác ban đầu.
- Danh sách term lấy 1 lần mỗi taxonomy mỗi lần tải trang (cache trong JS), không gọi AJAX mỗi lần mở.

### A5. Đơn sắc
`P/resources/styles/admin/_list-table-inline.scss` đang có hex ánh xanh `#f8fafc`, `#eff6ff` (dòng ~89–977) và hex cứng khác → thay bằng token
`P/resources/styles/admin/_tokens.scss` (`--laca-bg`, `--laca-bg-subtle`, `--laca-bg-muted`…). Lệnh kiểm:
`grep -rEn "#[0-9a-fA-F]{3,8}\b" P/resources/styles/admin --include=*.scss | grep -v _tokens.scss` → 0 dòng.

### A6. Hiệu năng trang danh sách
Báo cáo trước: ~316 query/trang danh sách — kiểm lại thật: đo `$wpdb->num_queries` (comment HTML chỉ khi `WP_DEBUG` + môi trường local) khi TẮT
ListTableInlineEditor (filter tạm `laca/list_inline/enabled` trả false — thêm filter này) và khi BẬT. Chênh lệch phải ≤ 5 query; nếu hơn thì tìm
query trong vòng lặp (`carbon_get_post_meta` từng ô, `get_post()` từng ô cho menu_order, `wc_get_product` từng ô…) và preload. Ghi 2 con số vào báo cáo.
Gỡ comment `LACA_NUM_QUERIES` khỏi bản commit (hoặc chỉ in khi `WP_DEBUG && wp_get_environment_type()==='local'`).

## B. Tự lưu AJAX — một bộ máy chung, chỉ lưu khi có thay đổi thật

### B1. Nguyên nhân hiện tại (đã đọc code `P/resources/scripts/admin/carbon-autosave.js`)
- `MutationObserver` trên `.theme-options-container` gọi `queueSave(true)` mỗi khi DOM thêm/bớt node → React của Carbon vẽ lần đầu / đổi tab / tải preview
  ảnh đều kích hoạt lưu (= "vừa vào đã tự lưu").
- `input` event + debounce 600ms → lưu trong khi đang gõ.
- Không so với trạng thái ban đầu → gõ rồi xoá về như cũ vẫn lưu; mỗi lần lưu gửi cả form → server/Discord thấy 20 field `— → —`.
- Laca SEO (`P/resources/scripts/admin/laca-seo/index.js`) cũng lưu khi đang gõ; trang Laca Admin (Tools…) tương tự.

### B2. Thiết kế mới — module dùng chung `P/resources/scripts/admin/laca-autosave.js`
Dùng cho: Carbon theme options (Laca Theme, Laca Admin và mọi trang Carbon `theme_options`), Laca SEO settings, các trang Laca Admin tự viết có form
cài đặt, và sửa nhanh trong bảng danh sách (A). `carbon-autosave.js` chuyển sang dùng module này (sửa nội dung file, không xoá file).
1. **Ảnh chụp trạng thái (snapshot)**: sau khi form sẵn sàng (Carbon: chờ tới khi không còn mutation trong 800ms kể từ lần mount đầu, tối đa 5s), tuần
   tự hoá form thành map `name → giá trị chuẩn hoá` (FormData; checkbox chưa tick = ''; nhiều giá trị nối bằng `\u0001`; trim). Lưu làm `baseline`.
2. **Thời điểm kích hoạt** (chỉ các sự kiện này, KHÔNG có `input`, KHÔNG có MutationObserver để lưu):
   - checkbox / radio / toggle / switch: sự kiện `change` → kiểm ngay.
   - input text/url/number/email, textarea, editor: sự kiện `change` (trình duyệt phát khi rời ô) hoặc `blur` → kiểm. Đang focus thì không bao giờ lưu.
   - select đơn: `change` → kiểm. Select nhiều / trình chọn kiểu popover / tag picker / color picker / date picker: khi đóng widget hoặc widget mất focus.
   - Carbon React (thêm/xoá/sắp xếp nhóm complex, chọn/xoá ảnh media, association): MutationObserver CHỈ được dùng để đánh dấu "có thể đã đổi" rồi
     chờ 600ms yên tĩnh → tính lại snapshot và so (bước 3). Đổi tab / render lại không làm snapshot khác → không lưu.
   - Enter trong input đơn dòng = rời ô (lưu nếu đổi). Nút "Save Changes" vẫn hoạt động = lưu ngay nếu có thay đổi (không có thì báo "Không có thay đổi").
3. **So sánh**: tính snapshot hiện tại, diff với `baseline` → nếu không có key nào khác: KHÔNG gửi request. Có khác: gửi lưu (Carbon cần cả form thì gửi
   cả form, kèm thêm `laca_changed_fields` = danh sách key đã đổi). Lưu thành công → `baseline = snapshot đã gửi`.
4. **Hàng đợi**: tối đa 1 request đang chạy; thay đổi phát sinh trong lúc lưu → gộp, lưu 1 lần sau khi xong. Lỗi mạng/server → giữ trạng thái "chưa lưu",
   nút "Thử lại", không lặp vô hạn. `beforeunload` cảnh báo nếu còn thay đổi chưa lưu/lỗi.
5. **Hiệu năng**: không tuần tự hoá form khi gõ phím; chỉ khi có sự kiện ở bước 2. Không gắn listener cho từng field (dùng event delegation trên form).
6. **Trạng thái hiển thị** (đơn sắc, cạnh nút Save/khung Actions, cố định chỗ — không đẩy bố cục): "Đã lưu lúc HH:MM" / "Đang lưu…" / "Chưa lưu — Thử lại".

### B3. Phía server
- Handler tự lưu của Carbon (`P/app/src/Settings/AdminSettings.php` — tìm `carbon_fields_auto_save`) và Laca SEO: nếu có `laca_changed_fields` thì
  chỉ ghi các field đó (Carbon: dùng `carbon_set_theme_option` cho từng field đã đổi thay vì để Carbon lưu cả container — nếu không khả thi, giữ lưu cả
  container nhưng KHÔNG phát log cho field không đổi). Nonce + `current_user_can('manage_options')` giữ nguyên.
- Discord Hub (`P/app/src/Features/DiscordHub/…` — tìm nơi tạo tin "Lưu cài đặt"): so sánh giá trị trước/sau sau khi **chuẩn hoá**
  (`null`, `''`, `false`, `'no'`, `'off'`, `[]` coi là rỗng như nhau; số dạng chuỗi so như số; mảng so theo nội dung) → chỉ liệt kê field thật sự đổi;
  0 field đổi thì KHÔNG gửi tin. Viết test PHP CLI nhỏ `P/doc/admin-ui/tools/test-settings-diff.php` gọi đúng hàm diff đó với các cặp
  (null,''), (false,'no'), ('0',0), (['a'],['a']) → 0 thay đổi; ('a','b') → 1. In PASS/FAIL + SUMMARY.

## C. Nghiệm thu (dán NGUYÊN VĂN)
1. `node P/doc/admin-ui/tools/list-table-audit.js` → `SUMMARY fail=0`.
2. `node P/doc/admin-ui/tools/autosave-audit.js` → `SUMMARY fail=0`.
3. `php P/doc/admin-ui/tools/test-settings-diff.php` (qua PHP CLI của Local) → fail=0.
4. Lưu thật + trả lại như vòng trước (product 29: text, taxonomy qua popover, stock) → PASS và DB về giá trị gốc.
5. Đo query bật/tắt editor (A6) — 2 con số.
6. `admin-ui-audit.js` fail=0, `ui-probe.js` fail=0, `yarn lint` 0 error, `yarn build` OK, `php -l` file sửa; grep hex ngoài tokens = 0.

## D. Ràng buộc
Script Puppeteer: cookie sinh lúc chạy, `try/finally browser.close()`, watchdog, `domcontentloaded`; không cửa sau đăng nhập. Lệnh > 5 phút không ra output
→ tự dừng. Không đổi DB ngoài bước C4 (đổi rồi trả lại), không xoá file, không cài package, không sửa `plugins/`, không sửa tay `dist/`. Đơn sắc, không xanh.
Xong: commit `fix(admin): tự lưu chỉ khi có thay đổi thật, bảng danh sách không cuộn ngang khi lăn dọc, popover danh mục, hover không xê dịch` → push →
`result` qua agent-bridge (file sửa, NGUYÊN VĂN SUMMARY các script, 2 số query).
