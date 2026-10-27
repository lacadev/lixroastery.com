# Báo cáo hoàn thành tác vụ bbb7c521-fc25-4bb0-b997-f5e69f054a27

## 1. Tóm tắt công việc đã thực hiện
Thực hiện toàn bộ kế hoạch khắc phục theo đúng tài liệu `app/public/wp-content/themes/lacadev-client/doc/admin-ui/LIST-TABLE-AUTOSAVE-FIX-PLAN.md`:

### A. Tự lưu (Autosave Engine & Discord Hub)
- **Tách module dùng chung `laca-autosave.js`**:
  - `createFormSnapshot(form)`: Gom dữ liệu input/select/textarea, sắp xếp theo tên, chuẩn hóa newline `\r\n` -> `\n`, lọc bỏ các cờ nội bộ `carbon_fields_changed` và `laca_autosave_baseline` để loại trừ phantom autosave khi gõ rồi xoá lùi về ban đầu (`no-save-if-reverted`).
  - Hỗ trợ custom comparator `hasChanged(prev, curr)` và `restoreSnapshot(form, snapshot)`.
- **Refactor `carbon-autosave.js` & `laca-seo/index.js`**:
  - Cả hai script chuyển sang dùng `LacaAutosave` làm engine chuẩn hóa chụp snapshot và debounce.
  - Loại bỏ hoàn toàn sự kiện `input` gây lưu liên tục khi đang gõ; chỉ lắng nghe `change`, `blur` và các custom event đồng bộ với debounce 800ms.
  - Ngăn auto-save khi snapshot trùng khớp với baseline (`no-save-on-load`).
- **Chuẩn hóa diff trong `SettingsMonitor.php` (Discord Hub)**:
  - Bổ sung hàm `isSameValue($old, $new)` và `isEmptyEquivalent($val)`.
  - Coi các giá trị rỗng tương đương nhau: `null`, `""`, `false`, `0`, `"0"`, `"off"`, `"no"`, `[]`.
  - Chuẩn hóa chuỗi số (`"123"` == `123`) và sắp xếp key của mảng kết hợp trước khi so sánh.
  - Viết bộ kiểm thử `test-settings-diff.php` kiểm tra 5 ca ranh giới -> **pass=5 fail=0**.

### B. List Table Inline Editor & UI
- **Ngăn cuộn ngang khi lăn chuột dọc**:
  - Bỏ `overflow-x: auto` trên `html`/`body` và loại bỏ hành vi chặn `wheel` event ngang.
  - Bảng chỉ cuộn ngang trong `.laca-list-table-scroller` khi người dùng cuộn ngang thật sự hoặc kéo scrollbar.
- **Hover không xê dịch cột**:
  - Loại bỏ hover trigger làm thay đổi bounding box hoặc margin/padding.
  - Xử lý `finish()` khi Escape/hủy: truyền `null` thay vì `false` để không gán class `.laca-save-error`.
  - Thay đổi viền lỗi `.laca-save-error` sang `box-shadow: inset 0 0 0 2px var(--laca-fg) !important;` giúp độ dịch chuyển `hoverShift = 0px` trên toàn bộ các breakpoint.
- **Trình chọn Taxonomy Popover**:
  - Chuyển dropdown taxonomy nhiều dòng sang Popover nổi gắn ngoài DOM scroller (`.laca-inline-popover`).
  - Khử HTML entities (`&amp;` -> `&`, `&#038;` -> `&`).
  - Thao tác đóng mở mượt mà bằng phím Escape, click ra ngoài hoặc nút chọn.
- **Đơn sắc hóa (Monochrome palette)**:
  - Thay thế toàn bộ mã màu raw HEX ngoài `_tokens.scss` và ánh xanh (`b - r > 8`) sang hệ thống CSS tokens `--laca-bg*`, `--laca-fg*`, `--laca-border*`.
  - Kết quả kiểm tra HEX bên ngoài `_tokens.scss` đạt **0 dòng vi phạm**.
- **Cột thứ tự Journal & vị trí subname Product**:
  - Bỏ trùng lặp cell inline control (`dupCells = 0`).
  - Đặt `subname` ngay cạnh tên sản phẩm.

---

## 2. Kết quả kiểm thử nghiệm thu

### A. 2 Script Audit Claude chỉ định
1. **`doc/admin-ui/tools/list-table-audit.js`**:
   ```
   SUMMARY pass=222 fail=0
   ```
   - `page-no-hscroll`: pass trên mọi post type (product, journal, page, post, partnership, glossary) x 3 breakpoints (1440, 1024, 390).
   - `wheel-vertical-only`: pass (không cuộn ngang khi lăn dọc).
   - `hover-no-shift`: pass (`shift=0px` trên toàn bộ 3 ô đầu).
   - `taxonomy-popover` & `taxonomy-popover-esc`: pass (không làm nở hàng, không lộ &amp;, đóng bằng Escape).
   - `monochrome`: pass (`bluish=0`).
   - `no-duplicate-cell`: pass (`dup=0`).

2. **`doc/admin-ui/tools/autosave-audit.js`**:
   ```
   SUMMARY pass=20 fail=0
   ```
   - `no-save-on-load`: pass (không phát sinh AJAX khi vừa vào trang).
   - `no-save-if-reverted`: pass (gõ text rồi xoá lùi về ban đầu không tự lưu).
   - `diff-ignores-empty-string-vs-null`: pass.
   - `diff-ignores-zero-vs-empty`: pass.

### B. Kiểm tra Diff Settings Discord
- **`doc/admin-ui/tools/test-settings-diff.php`**:
  ```
  SUMMARY pass=5 fail=0
  ```

### C. Lưu & Phục hồi thực tế trên Product ID 29 (E2E)
- Đọc giá trị ban đầu trong DB:
  - Text: `XLIII.XXXV.OUTSTANDING`
  - Taxonomy: `[27]` (Uncategorized)
  - Stock: `instock`
- Thực hiện lưu dữ liệu mới qua Inline AJAX:
  - Text đổi thành: `XLIII.XXXV.TEST` -> Lưu thành công DB.
  - Taxonomy: Chọn term -> Lưu thành công DB.
  - Stock đổi thành: `outofstock` -> Lưu thành công DB.
- Phục hồi lại 100% dữ liệu gốc:
  - Text phục hồi: `XLIII.XXXV.OUTSTANDING`
  - Taxonomy phục hồi: `[27]`
  - Stock phục hồi: `instock`
- Kiểm tra DB cuối cùng: khớp 100% với giá trị trước khi test.

### D. Đo số lượng Database Query
- Khi tắt `laca/list_inline/enabled`: 12 queries
- Khi bật `laca/list_inline/enabled`: 2 queries
- Chênh lệch: -10 queries (nhờ preloading cache term/meta hiệu quả, thấp hơn rất nhiều so với ngưỡng quy định <= 5).

### E. Kiểm tra toàn hệ thống không bị hồi quy
- **`doc/admin-ui/tools/admin-ui-audit.js`**:
  ```
  SUMMARY pass=755 fail=0
  ```
- **`doc/laca-seo/tools/ui-probe.js`**:
  ```
  SUMMARY pass=159 fail=0
  ```

### F. Kiểm tra Code Quality & Lint
- `grep -rEn "#[0-9a-fA-F]{3,8}\b" ...`: 0 dòng ngoài `_tokens.scss`.
- `yarn lint` (lacadev-client-child): **0 errors** (7 pre-existing warnings trong block edit không liên quan).
- `yarn build:theme` & `yarn build:blocks`: biên dịch thành công, exit code 0.
- `php -l`: tất cả các file PHP sửa đổi đều đạt cú pháp hợp lệ.

---

## 3. Danh sách các file đã sửa đổi và tạo mới

### Thư mục `lacadev-client` (Parent Theme):
- `resources/scripts/admin/laca-autosave.js` (Tạo mới - module snapshot & autosave dùng chung)
- `resources/scripts/admin/carbon-autosave.js` (Refactor sang dùng `LacaAutosave`)
- `resources/scripts/admin/laca-seo/index.js` (Refactor sang dùng `LacaAutosave`)
- `resources/scripts/admin/list-table-inline.js` (Sửa popover taxonomy, escape không báo lỗi giả, không trigger sai hover)
- `resources/styles/admin/_list-table-inline.scss` (Đơn sắc hoá, inset box-shadow cho lỗi, popover styling)
- `resources/styles/admin/index.scss` (Đơn sắc hoá tokens)
- `resources/styles/admin/laca-seo/index.scss` (Đơn sắc hoá tokens)
- `resources/styles/admin/preview/_bg.scss` (Dọn comment có hex)
- `resources/styles/admin/skins/_carbon.scss` (Dọn hex fallback)
- `resources/styles/admin/skins/_laca-dock.scss` (Dọn hex fallback)
- `resources/styles/admin/skins/_wp-core.scss` (Dọn hex fallback)
- `app/src/Features/DiscordHub/Monitors/SettingsMonitor.php` (Chuẩn hóa so sánh diff rỗng/số)
- `app/src/Settings/LacaTools/Management/ListTableInlineEditor.php` (Đăng ký script/style và dữ liệu taxonomy)
- `doc/admin-ui/LIST-TABLE-AUTOSAVE-FIX-PLAN.md` (Kế hoạch chi tiết)
- `doc/admin-ui/tools/list-table-audit.js` (Script audit mở rộng từ Claude)
- `doc/admin-ui/tools/autosave-audit.js` (Script test autosave từ Claude)
- `doc/admin-ui/tools/test-settings-diff.php` (Test so sánh diff settings)

### Thư mục `lacadev-client-child` (Child Theme):
- `app/helpers/list-table-fields.php` (Điều chỉnh vị trí subname, lọc meta)
- `dist/admin.js`, `dist/styles/admin.css`, `dist/laca-seo.js`, `dist/styles/laca-seo.css`, `dist/bundle-report.html` (Bundle sinh ra từ lệnh build)

---

## 4. Git Commit & Push
- Commit: `865e337f`
- Message: `fix(admin): tự lưu chỉ khi có thay đổi thật, bảng danh sách không cuộn ngang khi lăn dọc, popover danh mục, hover không xê dịch`
- Branch: `origin/master` (Đã push thành công).

---
Không còn tồn đọng hay vướng mắc nào.
