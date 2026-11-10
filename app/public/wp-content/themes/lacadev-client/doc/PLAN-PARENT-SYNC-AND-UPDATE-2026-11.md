# Plan: Đồng bộ parent `lacadev-client` cho mọi site và sửa luồng cập nhật theme (11/2026)

## Mục tiêu (người dùng đã duyệt, được làm trên nhiều project)
1. **Một nguồn duy nhất:** parent `lacadev-client` ở mọi site client giống hệt project nguồn `~/Documents/wordpress/lacadev-client`. Trước đó phải đưa các sửa đổi riêng có giá trị ở từng site về nguồn, để không mất.
2. **Cập nhật được:** bấm cập nhật ở **Bảng tin → Cập nhật** (cập nhật hàng loạt) và ở **Giao diện → Cập nhật ngay** đều thành công với theme lồng cấp `lacadev-client/theme`.
3. **Hub liên kết client:** hub `lacadev.local` (theme `lacadev`) nhận diện được các site client. Client tải gói cập nhật qua endpoint có ký HMAC.

## Phạm vi project
| Project | Đường dẫn | Git | DB local đang chạy? |
| --- | --- | --- | --- |
| Nguồn | `~/Documents/wordpress/lacadev-client` | có, `client.lacadev.com.git` | có |
| Hub | `~/Documents/wordpress/lacadev` | có, `lacadev.com.git` | có |
| jackacademy | `~/Documents/wordpress/jackacademy` | có | có |
| lixroastery | `~/Documents/wordpress/lixroastery` | có | có |
| phucdainam | `~/Documents/wordpress/phucdainam` | có | **không**, người dùng cần bật trong Local |
| nhakhoathienphuoc | `~/Documents/wordpress/nhakhoathienphuoc` | **không có git** | **không**, người dùng cần bật trong Local |

`P = app/public/wp-content/themes/lacadev-client` (parent trong mỗi project).

Socket MySQL: `~/Library/Application Support/Local/run/<id>/mysql/mysqld.sock`. Các id lấy trong `~/Library/Application Support/Local/sites.json`:

| Site | id |
| --- | --- |
| lacadev | `r_Z9Sldo9` |
| lacadev-client | `RqSXliHDN` |
| jackacademy | `a6_CUzbW8` |
| lixroastery | `nan2TUtPY` |
| phucdainam | `CkNBsbsb9` |
| nhakhoathienphuoc | `4OJhLTw4w` |

PHP CLI: `~/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php`.

## Dữ liệu khảo sát Claude đã chuẩn bị (`/tmp/jea-ui/sync/`)
- `parent-diff-<site>.txt`: `diff -rq` parent site so với nguồn, bỏ qua `node_modules`, `vendor`, `dist`, `.git`.
- `classify-<site>.txt`: từng file khác được phân loại:
  - `old`: nội dung **đã từng có trong lịch sử git nguồn**, tức bản cũ, ghi đè an toàn.
  - `LOCAL`: nội dung **chưa từng có ở nguồn**, tức site sửa riêng, **phải xem xét**.
  - `ONLYSITE`: file chỉ có ở site.

Số lượng:

| Site | old | LOCAL | ONLYSITE |
| --- | --- | --- | --- |
| jackacademy | 94 | 23 | 16 |
| lixroastery | 96 | 16 | 6 |
| nhakhoathienphuoc | 86 | 8 | 0 |
| phucdainam | 49 | 33 | 0 |

Script tạo dữ liệu: `classify.sh` và `parent-diff.sh`. Chạy lại sau mỗi bước để kiểm.

## Quy tắc an toàn (bắt buộc)
- **Backup trước khi động vào bất cứ site nào.** Tar parent của từng site vào `/tmp/jea-ui/sync/backup/<site>-parent-<timestamp>.tar.gz`. nhakhoathienphuoc không có git nên backup là cách khôi phục duy nhất, tar cả child của site này.
- Không đụng các file đang dirty có sẵn trong từng repo (`dist/bundle-report.html`, `.localbackupaddonignore.txt`, `tools/`, `theme-updates/`…). Không commit chúng.
- Không `reset --hard`, `checkout -- .`, `clean`, `rebase`, `push --force`.
- Không xoá file bằng `rm` để "dọn". Đồng bộ parent là **ghi đè đúng thư mục parent** bằng rsync có `--delete` **giới hạn trong `P/`**, sau khi đã backup. Trước khi chạy thật, `--dry-run` và ghi lại danh sách file sẽ xoá.
- Không đổi DB production. Mọi thao tác DB chỉ làm trên **local**.
- Child theme của từng site **không đồng bộ chéo**. Chỉ rebuild `dist/` của child, vì webpack child import source của parent qua `@parent`.
- **Không commit/push ở bước nào**: Claude review xong mới commit và push.

---

## Phần 1 — Đưa sửa đổi riêng của từng site về nguồn

Với **mỗi file `LOCAL`**:
1. Tìm **bản gốc** (base): commit nguồn gần nhất có nhiều file `old` của site trùng nhất (site đang ở 3.1). Hoặc dò theo từng file: phiên bản trong lịch sử nguồn có diff nhỏ nhất với file site.
2. Gộp 3 chiều: `git merge-file -p <site> <base> <nguồn hiện tại>`.
3. Phân loại thay đổi của site, ghi vào báo cáo:
   - **(a) Cải tiến chung**, ví dụ fix bug hay tính năng dùng được cho mọi site: đưa vào nguồn.
   - **(b) Riêng site**: **không** đưa vào parent. Chuyển sang child của site đó qua hook hoặc filter có sẵn. Thiếu hook thì thêm hook trung tính vào parent, như cách đã làm với `laca_help_page_before_blocks` và `laca_csp_frame_src`.
   - **(c) Đã lỗi thời**, vì nguồn đã có cách khác: bỏ.
4. Xung đột thì tự giải quyết theo logic. Chỗ nào không chắc thì **giữ nguyên code của nguồn** và liệt kê trong báo cáo để Claude quyết.

**Các thay đổi đã biết chắc phải đưa về nguồn** (từ jackacademy):
- `app/src/Settings/ThemeUpdater.php`: 2 hàm `allowNestedThemePackage` (bỏ `check_package` cho theme lồng, tự kiểm `theme/style.css`) và `backupWholeThemeFolder` (slug sao lưu `lacadev-client` thay vì `lacadev-client/theme`).
  - Gộp với `filterSourceSelection` và `filterDownloadRequestArgs` sẵn có ở nguồn.
  - Thứ tự ưu tiên: hàm cho phép package chạy ưu tiên **9**, `filterSourceSelection` giữ ưu tiên 10. Kiểm lại hàm đổi tên thư mục vẫn trả về đúng `$source` mới.
- `app/src/Settings/LacaTools/AITranslationHandler.php`: danh sách model OpenRouter mới, gom lỗi theo model, `setTimeout()`, `hasAnyProvider()`.
- `app/src/Settings/LacaTools/Management/AdminUxService.php`: `do_action('laca_help_page_before_blocks')` và ẩn alert/thẻ mặc định khi có hướng dẫn riêng.
- `theme/setup/security.php`: filter `laca_csp_frame_src`.

**File `ONLYSITE`:**
- `doc/JEA-*.md`, `doc/JACKACADEMY-CLEANUP-PLAN.md` (jackacademy): **chuyển** sang `jackacademy/.../lacadev-client-child/doc/`, giữ nguyên nội dung, vì đây là tài liệu riêng site.
- `UiPreview.php`, `DashboardPreviewPage.php`, `ui-preview.js`, `resources/images/ui-preview`, `resources/styles/admin/preview` (jackacademy, lixroastery): nguồn chưa từng có.
  - Grep chỗ đăng ký và sử dụng. Không còn được gọi thì để rsync gỡ. Còn được gọi và hữu ích thì đưa về nguồn. Ghi rõ quyết định.
- `doc/laca-seo/tools/head-golden` (lixroastery): xem là gì, cùng cách xử lý.

**nhakhoathienphuoc và phucdainam:** làm tương tự với các file `LOCAL` trong `classify-*.txt`. phucdainam có 33 file `LOCAL`, nhiều nhất, xem kỹ ContactForm, DynamicCPT, Security, TwoFactorAuth.

Xong phần 1 trong nguồn:
- `php -l` mọi file sửa.
- `composer install` theo lockfile nếu `composer.lock` đổi.
- Mở `lacadev-client.local` frontend và wp-admin, không có lỗi PHP.

## Phần 2 — Sửa luồng cập nhật ở nguồn và tạo bản phát hành
1. **`ThemeUpdater.php`** (nguồn) có đủ:
   - Bỏ `check_package` cho theme lồng.
   - Slug sao lưu là thư mục gốc.
   - Đổi tên thư mục giải nén về `lacadev-client`.
   - Ký HMAC khi tải.
   - Kiểm thêm `current_after()` của core: khi parent là theme đang active, so `get_stylesheet()` với `destination_name`, nhưng `'lacadev-client/theme' !== 'lacadev-client'` nên core sẽ `switch_theme('lacadev-client')` sai. Ở ưu tiên < 10 của `upgrader_post_install`, với đúng theme này thì gỡ `[$upgrader, 'current_after']` và **tự tắt maintenance mode** như core làm. Chỉ cần khi parent active trực tiếp; vẫn phải xử lý cho chắc.
2. **URL cập nhật theo môi trường** (`app/hooks.php`):
   - Mặc định `https://lacadev.com/theme-updates/lacadev-client.json` giữ nguyên cho production.
   - Khi `wp_get_environment_type() === 'local'` thì dùng `http://lacadev.local/theme-updates/lacadev-client.json`.
   - Vẫn qua filter `laca_theme_update_json_url`. Không ghi cứng URL local vào nhánh production.
3. **`build-release.sh`:**
   - Loại file nội bộ khỏi zip: `build-release.sh`, `theme-server/`, `releases/`, `doc/`, `phpstan*.neon`, `composer.json`, `composer.lock`, `.editorconfig`, `.gitignore`, `config.json.dist`, `node_modules/`.
   - **Giữ** `vendor/` (đã `composer install --no-dev` sẵn).
   - Kiểm zip có `lacadev-client/theme/style.css`, `lacadev-client/vendor/autoload.php`, `lacadev-client/app/`.
4. **Bump version 3.8** trong `theme/style.css`, chạy build. Ghi changelog ngắn: sửa cập nhật theme lồng cấp và các thay đổi gộp về ở phần 1.
5. **Đưa gói lên hub local:**
   - Copy zip vào `lacadev/app/public/theme-updates/private/lacadev-client.zip`, tạo thư mục nếu chưa có.
   - Cập nhật `lacadev/app/public/theme-updates/lacadev-client.json`: version 3.8, `download_url` là `http://lacadev.local/wp-json/laca/v1/theme-package/lacadev-client`.
   - **Không commit zip và thư mục `theme-updates/`**, vì đang untracked trong repo hub.

## Phần 3 — Đồng bộ parent 3.8 xuống từng site client
Với jackacademy, lixroastery, phucdainam, nhakhoathienphuoc:
1. Backup như quy tắc.
2. Chạy rsync **dry-run** từ `nguồn/P/` sang `site/P/`:
   - Lệnh: `rsync -a --delete --exclude node_modules --exclude vendor --exclude .git --exclude config.json --exclude releases --exclude theme-server`.
   - Lưu danh sách file sẽ đổi hoặc xoá vào báo cáo. Đối chiếu: không có file nào thuộc nhóm (b) chưa được chuyển sang child.
3. Chạy thật.
4. `composer install` (theo lockfile) trong `site/P/` để `vendor/` khớp.
5. **Rebuild child** của site:
   - Chạy `yarn build` trong `lacadev-client-child`. Nếu child có `build:blocks` thì chạy luôn.
   - Kiểm `yarn lint` không có lỗi mới.
6. Kiểm mỗi site (site nào chưa bật trong Local thì ghi lại để người dùng bật rồi Claude kiểm sau):
   - `php -l` toàn bộ parent.
   - Mở frontend trang chủ cùng 2 trang con và wp-admin (dashboard, Laca Admin, Laca Theme) bằng **Chrome thật**. Không có fatal error, không có lỗi console mới, tràn ngang 375 = 0.
   - Riêng jackacademy: kiểm thêm cổng `/hoc-vien/`, `/giao-vien/`, trang HD Sử dụng (dùng hook `laca_help_page_before_blocks`), bài làm có iframe Quizlet/Wordwall (filter CSP).
7. Chạy lại `classify.sh` và `parent-diff.sh`: parent mọi site phải **giống hệt nguồn** (0 khác, chỉ khác ở các mục bị exclude).

## Phần 4 — Liên kết hub ↔ client (local)
1. Trên hub `lacadev.local`, tạo `project` cho jackacademy, lixroastery, phucdainam, nhakhoathienphuoc. Nếu hub đã có tính năng tạo project hoặc key (`ProjectKeyManager`) thì **dùng đúng API đó**, không tự ghi meta tay.
   - Cần có: public id, tracker key, catalog key, remote key, `_live_url` là domain production của site (jeacademy.vn, lixroastery.com…) hoặc domain `.local`.
   - Đọc `FleetPage.php` và `ProjectKeyManager.php` để biết đúng trường.
2. Trên mỗi client local, điền vào Theme Options (Carbon Fields, đúng tên field trong `LacaDevTrackerClient`): endpoint `http://lacadev.local/wp-json/laca/v1/tracker/log`, project id, tracker key, catalog key, remote key.
   - Dùng `carbon_set_theme_option` qua PHP CLI.
   - Ghi bằng giá trị thật lấy từ hub, **không in key ra báo cáo** (che như `test…(20)`).
3. Kiểm liên kết:
   - Chạy heartbeat của tracker (hàm hoặc cron có sẵn) từ client. Hub ghi nhận `_tracker_last_seen_at` hoặc `_laca_signed_auth_last_seen` mới.
   - Màn Fleet trên hub hiện đủ site.
4. **Production** (không làm, chỉ viết hướng dẫn): làm checklist từng bước cho người dùng gồm tạo project trên lacadev.com, điền key vào từng site thật, upload gói 3.8.

## Phần 5 — Test cập nhật đầu-cuối (local)
Mục tiêu: chứng minh bấm cập nhật ở **Bảng tin → Cập nhật** thành công trên client.
1. Trên hub, tạo gói **3.8.1-test**: cùng code 3.8, chỉ đổi `Version`. Cập nhật json local thành 3.8.1-test.
2. Trên **jackacademy local** (đã ở 3.8 sau phần 3, đã commit-ready):
   - Xoá transient `update_themes` và cache của `ThemeUpdater`, rồi gọi `wp_update_themes()`. Màn Cập nhật phải thấy bản 3.8.1-test.
   - Đăng nhập admin bằng Chrome thật, vào `wp-admin/update-core.php`, tick theme rồi bấm "Cập nhật giao diện". Chụp màn hình kết quả: phải có "Đã cập nhật thành công", không có lỗi style.css hay upgrade-temp-backup.
   - Kiểm:
     - Version parent bằng 3.8.1-test.
     - Site vẫn dùng child, frontend và admin chạy.
     - Thư mục parent đầy đủ (`vendor/`, `app/`, `theme/`).
     - Hub ghi nhận lượt tải có `auth_mode = hmac` trong `_laca_theme_downloaded`.
   - Lặp lại **một lần** qua **Giao diện → Cập nhật ngay**, gói 3.8.2-test.
3. **Khôi phục** sau test:
   - Đưa parent jackacademy về đúng nội dung 3.8 bằng rsync từ nguồn như phần 3, rồi chạy `parent-diff.sh`, phải còn 0 khác.
   - Trả json hub về 3.8.

## Báo cáo
Ghi `/tmp/jea-ui/DONE-PARENT-SYNC.md`:
- Danh sách quyết định cho từng file `LOCAL` và `ONLYSITE` (a/b/c), kèm lý do. Riêng nhóm (b): đã chuyển đi đâu.
- Danh sách file sửa ở nguồn, hub và từng child.
- Kết quả dry-run rsync (số file đổi/xoá theo site) và đường dẫn backup.
- Kết quả `php -l`, `composer install`, `yarn build`/`lint` từng site.
- Ảnh Chrome: frontend và admin từng site, kèm ảnh màn cập nhật thành công ở phần 5.
- Output `parent-diff.sh` sau cùng.
- Trạng thái liên kết hub: bảng project → last seen. Key phải che.
- Checklist production.
- Site nào chưa bật được DB thì ghi rõ các bước còn thiếu.
