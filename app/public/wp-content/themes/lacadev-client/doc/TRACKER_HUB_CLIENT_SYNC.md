# Đồng bộ Hub (lacadev) ⇄ Client (lacadev-client) — Nhật ký & hướng dẫn test

> File này tồn tại giống hệt nhau ở cả 2 repo:
> - `lacadev-client/app/public/wp-content/themes/lacadev-client/doc/TRACKER_HUB_CLIENT_SYNC.md`
> - `lacadev/app/public/wp-content/themes/lacadev/doc/TRACKER_HUB_CLIENT_SYNC.md`
>
> Mục đích: khi cần làm việc tiếp với tracker/cảnh báo giữa 2 site, đọc file này trước — không cần đọc lại toàn bộ code để hiểu bối cảnh.

## Bối cảnh

3 mục tiêu gốc: (1) theo dõi tất cả site `lacadev-client` từ hub `lacadev`, (2) dễ nhận lỗi/update từ plugin/theme, (3) theo dõi thay đổi code trên site khách. Đánh giá ban đầu tìm ra 2 khoảng trống chính:

1. Hub chỉ **ghi log** cảnh báo `critical`/`warning`, không chủ động đẩy qua Email/Zalo/Telegram/Slack (4 kênh này trước đó chỉ được gọi bởi cron kiểm tra hết hạn domain/hosting hằng ngày).
2. Phía client, `LacaDevTrackerClient` (class gửi log/nhận lệnh remote-update) **chưa từng thực sự chạy cron/REST trong production** — do bị bọc trong `if (is_admin())`, mà `wp-cron.php` và REST request không đi qua path đó nên `is_admin()` luôn `false` ở 2 ngữ cảnh này.

Kế hoạch chia 3 giai đoạn (P0/P1/P2). Plan gốc đầy đủ được lưu tại `~/.claude/plans/synchronous-toasting-boole.md` trên máy đã thực hiện — file đó có thể bị dọn dẹp theo thời gian, nên toàn bộ nội dung quan trọng được chép lại ở đây.

---

## GIAI ĐOẠN 1 (P0) — ĐÃ XONG ✅

### Thay đổi ở theme `lacadev-client` (CLIENT)

| File | Method | Thay đổi |
|---|---|---|
| `app/hooks.php` | (top-level) | Tách `new \App\Settings\LacaDevTrackerClient()` ra khỏi khối `if (is_admin())`, đăng ký vô điều kiện trên `add_action('init', ...)`. **Đây là fix quan trọng nhất** — trước đây cron `laca_tracker_hourly_scan`/`laca_tracker_daily_digest` và route REST `/wp-json/laca/v1/remote-update` không bao giờ đăng ký được khi `wp-cron.php` hoặc 1 request REST chạy, vì các request đó không đi qua `wp-admin/admin.php` nên `is_admin()` = `false`. `ThemeUpdater`/`BlockSyncWidget` vẫn giữ nguyên trong gate `is_admin()` vì chỉ hiển thị UI trong wp-admin. |
| `app/src/Settings/LacaDevTrackerClient.php` | `handleRemoteUpdate()` | So sánh secret đổi từ `$secretKey !== self::getSecretKey()` (không an toàn, có thể bị timing attack) sang `hash_equals()`, đồng thời chặn luôn trường hợp secret hub chưa cấu hình (`empty($storedSecret)`) — trước đó nếu secret rỗng ở cả 2 phía, request vẫn được coi là hợp lệ. |

### Thay đổi ở theme `lacadev` (HUB)

| File | Method | Thay đổi |
|---|---|---|
| `app/src/Settings/LacaTools/ProjectNotificationHandler.php` | `init()` + method mới `handleRealtimeAlert()` | Đăng ký `add_action('laca_project_alert_notify', [$this, 'handleRealtimeAlert'], 10, 3)`. Method mới nhận `(int $projectId, string $level, string $message)`, chỉ xử lý khi `$level` là `warning`/`critical`, gọi lại `sendNotifications()` (method có sẵn, không đổi visibility — vì callback chạy trên cùng instance nên gọi `$this->` được dù `sendNotifications()` vẫn `private`). |
| `app/src/Settings/LacaTools/TrackerEndpointHandler.php` | `createAlert()`, `createAlertByType()`, `createClientRequestAlert()` | Sau mỗi lần `ProjectAlert::add([...])` trả về khác `false` (tức tạo alert mới, không bị dedup chặn), gọi thêm `do_action('laca_project_alert_notify', (int) $projectId, $level, $msg)`. |
| `app/src/Features/ProjectManagement/Api/ClientWebhook.php` | `maybeCreateAlert()` | Tương tự — gọi `do_action('laca_project_alert_notify', $projectId, $level, $message)` sau khi `ProjectAlert::add()` thành công. |
| `app/src/Features/ProjectManagement/ProjectFields.php` | `addTabHostingDomain()` | Thêm field mới `ssl_expiry` (date) + `ssl_notify_days` (text, mặc định `14`) vào tab "Hosting & Domain", đặt trước khối FTP. Logic cảnh báo hết hạn SSL đã có sẵn từ trước trong `ProjectNotificationHandler::checkExpirations()` (đọc `_ssl_expiry`/`_ssl_notify_days`) nhưng chưa từng có ô nhập liệu — giờ đã có. |

### Cơ chế mới hoạt động ra sao

`do_action('laca_project_alert_notify', $projectId, $level, $message)` là hook tự tạo, dùng để tách rời (decouple) nơi tạo alert khỏi nơi gửi thông báo — không cần đổi visibility của `sendNotifications()`. Chỉ bắn khi alert **mới thực sự được tạo** (dedup đã chạy trước đó ở tất cả các call site), nên không lo bị spam trùng.

**Sẽ báo ngay qua Email/Zalo/Telegram/Slack** (nếu kênh đó được bật + cấu hình đúng ở hub): file khả nghi, file bị đổi ngoài baseline, xoá plugin, có plugin/theme/SSL/domain/hosting chờ update hoặc sắp hết hạn, yêu cầu khẩn dạng "bug" từ client portal.

**Chỉ ghi log, KHÔNG báo** (xem trên dashboard hub): cập nhật plugin/theme/core thành công, kích hoạt/vô hiệu hoá plugin, đổi theme — vì đây là việc bình thường xảy ra hằng ngày, báo mỗi lần sẽ gây spam.

### Điều kiện để thực sự nhận được thông báo (không phải code, là cấu hình)

1. **Phía client** — `wp-admin → Laca Admin → 📡 Tracker`: điền **Tracker Endpoint URL** (trỏ về `https://<domain-hub>/wp-json/laca/v1/tracker/log`) và **Secret Key** (khớp đúng `_tracker_secret_key` của project tương ứng bên hub). Trống 1 trong 2 ô này thì `sendLogs()` tự no-op.
2. **Phía hub** — `wp-admin → Laca Admin → LacaDev PM & Bots`: bật ít nhất 1 trong 4 tab **Email / Zalo OA / Telegram / Slack**, điền đúng thông tin (email nhận, hoặc bot token + chat id, hoặc webhook URL...).

---

## GIAI ĐOẠN 2 (P1) — ĐÃ XONG ✅ — Chống mất dữ liệu khi hub gián đoạn

Chỉ áp dụng cho CLIENT, file `app/src/Settings/LacaDevTrackerClient.php`. Vấn đề gốc: mọi lần gửi dùng `wp_remote_post(..., blocking: false)`, không kiểm tra kết quả, nhưng vẫn cập nhật state cục bộ (`OPT_KNOWN_UPDATES`, `OPT_BASELINE`) ngay sau đó — nếu hub tạm thời không phản hồi được, dữ liệu vòng đó mất vĩnh viễn.

| Việc | Chi tiết |
|---|---|
| 2A | `sendLogs(array $logs, bool $blocking = false): bool`. Khi `$blocking = false` (mặc định, dùng cho 5 hook người dùng chủ động kích hoạt: `onUpgraderComplete`, `afterDeletePlugin`, `onActivatePlugin`, `onDeactivatePlugin`, `runHourlyScan`) luôn trả `true` (không thể xác nhận). Khi `$blocking = true`, dùng `timeout = 15`, kiểm tra `!is_wp_error($response) && response_code trong khoảng 2xx`. |
| 2B | `onUpdateTransientSet()` không còn `update_option(OPT_KNOWN_UPDATES, ...)`. Thay bằng transient `_laca_tracker_alerted_{md5(plugin\|version)}` sống 6h để chống lặp thông báo cho cùng 1 plugin/version. `OPT_KNOWN_UPDATES` giờ chỉ được ghi ở `runDailyDigest()`. |
| 2C | `checkFileIntegrity()` đổi return type thành `array{changed: string[], baseline: array, isFirstRun: bool}`, không tự `update_option(OPT_BASELINE)` nữa. `runDailyDigest()` tách 2 lần gửi độc lập: (1) update-pending (plugin gộp theme gộp core) — gửi `blocking: true`, chỉ ghi `OPT_KNOWN_UPDATES = array_column($pluginUpdates, 'slug')` (toàn bộ danh sách đang pending, không chỉ phần mới) nếu gửi thành công; (2) file-integrity — gửi `blocking: true`, chỉ ghi `OPT_BASELINE = $fim['baseline']` nếu gửi thành công, **trừ** lần chạy đầu tiên (`isFirstRun`) thì luôn lưu baseline ngay (không có gì để cảnh báo nên không cần chờ gửi). |

Code đầy đủ nằm trong chính file `LacaDevTrackerClient.php` — đọc trực tiếp 3 method `sendLogs()`, `onUpdateTransientSet()`, `runDailyDigest()`/`checkFileIntegrity()` khi cần tham chiếu, không cần dò lại lịch sử chat.

## GIAI ĐOẠN 3 (P2) — ĐÃ XONG ✅ — Hợp nhất 2 hệ tracker

Mục tiêu: xoá `App\Features\ClientTracker\Tracker` (không có UI cấu hình nên "câm", nhưng vẫn quét file mỗi 6h tốn tài nguyên) mà không mất 2 khả năng còn giá trị của nó — theo dõi đổi theme (`switch_theme`) và FIM sâu (quét mã nguồn theo extension, loại trừ `uploads`) — bằng cách gộp vào `LacaDevTrackerClient`, dùng chung 1 endpoint/1 secret.

### Đợt 1 — thêm trước (file `app/src/Settings/LacaDevTrackerClient.php`)

- Hook mới `add_action('switch_theme', [$this, 'onSwitchTheme'], 10, 3)` + method `onSwitchTheme()` → gửi `log_type = theme_switched`, **level `info`** (giữ đúng hành vi gốc: đổi theme hợp lệ chỉ cần ghi log, không cần cảnh báo — hub route `type` lạ vào `logType = 'note'`, không tạo alert, nên không cần sửa gì bên hub).
- Constant mới: `CF_DEEP_FIM_ENABLED = 'laca_tracker_deep_fim'` (Carbon Fields checkbox), `OPT_DEEP_FIM_BASELINE = '_laca_tracker_deep_fim_baseline'` (baseline riêng, không đụng tới `laca_file_baseline` của trang Security thủ công).
- Method mới `checkDeepFileIntegrity()`: tái dùng `App\Settings\Security\FileIntegrityMonitor::getFileList()` có sẵn (danh sách file đã lọc theo extension `php/js/json/htaccess/sh`, loại trừ `uploads/cache/backups/updraft`) thay vì viết lại logic quét — chỉ thêm phần so sánh md5 + baseline riêng. Phát hiện được cả modified/added/deleted (đầy đủ hơn `checkFileIntegrity()` gốc — chỉ có modified/added qua mtime).
- `runDailyDigest()` thêm "Nhóm 3": chỉ chạy khi `carbon_get_theme_option('laca_tracker_deep_fim')` bật, gửi `blocking: true`, chỉ chốt `OPT_DEEP_FIM_BASELINE` khi gửi thành công — cùng pattern chống mất dữ liệu như Nhóm 1/2 ở Giai đoạn 2.
- File `app/src/Settings/AdminSettings.php`, tab "📡 Tracker": thêm checkbox `laca_tracker_deep_fim` (mặc định tắt) + mô tả, đặt sau ô Secret Key.
- **Không đụng** `ClientTracker\Tracker` hay dòng khởi tạo nó ở đợt này.

### Đợt 2 — xoá hệ cũ

- `theme/functions.php`: xoá dòng `(new \App\Features\ClientTracker\Tracker())->init();` (và comment liên quan).
- Xoá hẳn file `app/src/Features/ClientTracker/Tracker.php` (đã kiểm tra `grep` toàn theme, không còn nơi nào tham chiếu tới class này trước khi xoá).
- `app/hooks.php`: thêm dọn cron mồ côi `laca_fim_scan` — chỉ chạy 1 lần nhờ cờ option `_laca_tracker_fim_cron_cleaned`, không chạy lại mỗi request:
  ```php
  add_action('init', function () {
      if (!get_option('_laca_tracker_fim_cron_cleaned')) {
          wp_clear_scheduled_hook('laca_fim_scan');
          update_option('_laca_tracker_fim_cron_cleaned', 1, false);
      }
  }, 1);
  ```

Kết quả: chỉ còn **1 hệ tracker duy nhất** (`LacaDevTrackerClient`), 1 endpoint, 1 secret, 1 pipeline gửi có retry-safety (Giai đoạn 2). Không còn option-name collision (`laca_tracker_secret_key` không-prefix vs Carbon Fields) hay 2 cron chạy song song không đồng bộ.

## Cron hệ thống thật cho site ít traffic

WordPress mặc định chỉ chạy cron khi có người truy cập site (pseudo-cron qua `wp-cron.php`) — site khách ít traffic có thể khiến `laca_tracker_hourly_scan`/`laca_tracker_daily_digest` (client) hoặc `laca_project_manager_daily_cron` (hub) không tự chạy đều trong nhiều ngày, âm thầm không ai biết.

**Đã có sẵn (code):**
- Mỗi cron giờ tự ghi lại thời điểm chạy gần nhất (`LacaDevTrackerClient::renderCronHealthNotice()` phía client, `ProjectNotificationHandler::renderCronHealthNotice()` phía hub) — nếu trễ quá ngưỡng (6h cho cron hourly, 3 ngày cho cron daily), wp-admin tự hiện cảnh báo màu vàng ở đầu trang.
- Trang `Laca Admin → 📡 Tracker` (client) có sẵn ô "Cron URL" copy được — chính là URL cần thêm vào Cron Jobs của hosting.

**Cần làm thủ công trên hosting (không phải code):**
1. Vào phần Cron Jobs của hosting (cPanel/DirectAdmin/hosting panel khác), thêm 1 dòng chạy mỗi 15 phút gọi URL đã lấy ở trang Tracker, ví dụ:
   ```bash
   wget -q -O /dev/null "https://domain-site/wp-cron.php?doing_wp_cron"
   ```
   hoặc dùng `curl -s -o /dev/null "..."` nếu hosting không có `wget`.
2. **Chỉ sau khi** xác nhận cron thật đã chạy ổn (không còn thấy cảnh báo "cron trễ" trong wp-admin), mới thêm `define('DISABLE_WP_CRON', true);` vào `wp-config.php` để tắt pseudo-cron — tránh trường hợp chạy 2 lần cùng lúc (pseudo-cron + cron thật) trước khi xác nhận cron thật hoạt động.
3. Nếu hosting có SSH/WP-CLI (không phải mặc định cho mọi client), có thể dùng `wp cron event run --due-now` trong crontab thay cho gọi URL — hiệu quả hơn vì không tốn 1 lượt HTTP request, nhưng đây là lựa chọn nâng cao, không bắt buộc.

## Chủ động bỏ qua trong các giai đoạn trên (đã cân nhắc)

- Dọn code "chết" `BlockSyncSender`/hook CPT `project` còn sót trong theme client (không có Project CPT nào đăng ký ở đó) — rác code, không ảnh hưởng chức năng.
- Đẩy kết quả Security Audit/Malware Scanner/Hidden User Scanner (100% cục bộ trên client) lên hub — là tính năng mới, không phải sửa lỗi.

---

## Hướng dẫn test từng bước

### A. Trên HUB (`lacadev`)

1. **Bật kênh thông báo**: `wp-admin → Laca Admin → LacaDev PM & Bots` → tab **Email** (dễ test cục bộ nhất) → bật "Bật thông báo qua Email" → nhập 1 email test vào "Email nhận thông báo" → Save. Nếu site local dùng Local by Flywheel với Mailpit, mail gửi ra sẽ vào Mailpit thay vì hộp thư thật (kiểm tra qua UI Mailpit của site).
2. **Xác nhận field SSL mới xuất hiện**: mở 1 project (CPT `project`) → tab "Hosting & Domain" → phải thấy khối "Thông tin SSL" với 2 field "Ngày hết hạn SSL" và "Cảnh báo trước (ngày)" (mặc định `14`), nằm trước khối FTP/SFTP.
3. **Test cảnh báo hết hạn (đường cron hằng ngày, không phải real-time)**: điền "Ngày hết hạn SSL" = 3 ngày kể từ hôm nay, Save. Trigger cron thủ công (không có WP-CLI thì cài tạm plugin **WP Crontrol**, tìm hook `laca_project_manager_daily_cron` → "Run now"). Kiểm tra: (a) Alert `ssl_expiry` mới xuất hiện ở `Laca Admin → LacaDev PM & Bots → ... → Tất cả Cảnh báo`, mức `critical` vì còn ≤7 ngày; (b) email test nhận được nội dung cảnh báo.
4. **Test đường real-time (mục tiêu chính của Giai đoạn 1)**: lấy `_tracker_secret_key` của project đó (xem trong meta box "Project Workspace" khi sửa project), rồi gọi:
   ```bash
   curl -X POST "https://<domain-hub>/wp-json/laca/v1/tracker/log" \
     -H "Content-Type: application/json" \
     -d '{
       "secret_key": "<secret_key_của_project>",
       "site_url": "https://site-test.example.com",
       "logs": [{"type": "file_suspicious", "content": "Test: phát hiện file lạ", "level": "critical"}]
     }'
   ```
   Kỳ vọng: response `{"success": true, ...}`, Alert `security` mức `critical` xuất hiện ngay trong Global Alerts, **và email nhận được ngay** (không cần đợi cron) — đây là điểm khác biệt so với trước Giai đoạn 1.
5. **Test không bị spam**: gọi lại đúng request ở bước 4 lần thứ 2 ngay sau đó — kỳ vọng KHÔNG có alert/email thứ 2 (dedup theo nội dung trong 1 giờ).
6. **Test loại KHÔNG báo**: gọi request tương tự với `"type": "plugin_update", "level": "info"` — kỳ vọng có `ProjectLog` mới (xem ở tab Updates) nhưng **không** có email.

### B. Trên CLIENT (`lacadev-client`)

1. **Cấu hình tracker**: `wp-admin → Laca Admin → 📡 Tracker` → điền **Tracker Endpoint URL** = `https://<domain-hub>/wp-json/laca/v1/tracker/log`, **Secret Key** = đúng giá trị lấy ở bước A.4. Trạng thái trên trang phải chuyển thành "✅ Tracker đã được cấu hình".
2. **Xác nhận cron đã đăng ký được** (đây là điều Giai đoạn 1 sửa, cần xác minh kỹ): cài tạm plugin **WP Crontrol**, vào `Tools → Cron Events`, tìm 2 hook `laca_tracker_hourly_scan` và `laca_tracker_daily_digest` — phải thấy chúng có lịch (Next Run), không bị thiếu. Trước khi sửa `is_admin()` gate, 2 hook này tuy được `wp_schedule_event()` (chạy trong 1 request admin) nhưng **callback không hề được đăng ký** khi `wp-cron.php` thật sự dispatch — WP Crontrol không phân biệt được điều này qua UI, nên bước xác nhận thật sự là bước 3 bên dưới.
3. **Trigger thủ công để xác nhận callback chạy thật**: trong WP Crontrol, bấm "Run Now" trên `laca_tracker_daily_digest`. Nếu có plugin/theme đang chờ update trên site test, phải thấy `ProjectLog`/`ProjectAlert` (`update_pending`) xuất hiện bên hub ngay sau đó — đây là bằng chứng cron đã thực sự thực thi, không chỉ được lên lịch.
4. **Test phát hiện file khả nghi**: tạo tạm 1 file `wp-content/uploads/laca-test-shell.php` với nội dung bất kỳ (vd `<?php echo 'test'; ?>` — **không dùng code độc hại thật**, chỉ cần đúng đuôi `.php` để kích hoạt bộ lọc extension). Trigger "Run Now" cho `laca_tracker_hourly_scan`. Kỳ vọng: alert `file_suspicious` mức `critical` xuất hiện bên hub **và** email nhận được ngay lập tức. **Nhớ xoá file test này ngay sau khi xong** — đừng để sót file `.php` trong `uploads`.
5. **Test xác thực remote-update đã an toàn hơn**: gọi thử với secret sai:
   ```bash
   curl -X POST "https://<domain-client>/wp-json/laca/v1/remote-update" \
     -H "Content-Type: application/json" \
     -d '{"secret_key": "sai-secret", "action": "update_plugin", "slug": "some-plugin/some-plugin.php"}'
   ```
   Kỳ vọng: HTTP 401 `Unauthorized`. Nếu route trả về 404 thay vì 401, nghĩa là bước 1A (bỏ gate `is_admin()`) chưa được áp dụng đúng — route REST chưa đăng ký được.
6. **Test chống mất dữ liệu (Giai đoạn 2)**: tạm sửa **Tracker Endpoint URL** thành 1 URL sai (vd thêm `-broken` vào domain), Save. Trigger "Run Now" cho `laca_tracker_daily_digest` trong lúc có ít nhất 1 plugin đang chờ update. Kiểm tra bằng cách xem giá trị option `_laca_tracker_known_plugin_updates` (cần DB access, vd qua Adminer/phpMyAdmin, bảng `wp_options`) — **không được thay đổi** so với trước khi chạy (vì gửi thất bại). Sửa lại Endpoint URL đúng, "Run Now" lại lần nữa — lần này phải thấy `update_pending` xuất hiện bên hub (dữ liệu không bị mất) và option mới được cập nhật.
7. **Test theo dõi đổi theme (Giai đoạn 3, Đợt 1)**: trên site test, đổi sang 1 theme khác rồi đổi lại. Kiểm tra: `ProjectLog` loại `note` với nội dung "🎨 Đổi theme..." xuất hiện bên hub (tab Updates) — **không** có alert/email (đúng thiết kế, chỉ ghi log).
8. **Test FIM sâu (Giai đoạn 3, Đợt 1, tùy chọn)**: bật checkbox "Bật kiểm tra sâu mã nguồn (FIM)" ở trang 📡 Tracker, Save. "Run Now" cho `laca_tracker_daily_digest` lần đầu (chỉ để tạo baseline, chưa có gì để báo). Sửa nội dung 1 file `.php` bất kỳ trong theme đang active (thêm 1 dòng comment vô hại), "Run Now" lần 2 — kỳ vọng: alert `security` mức `critical` với nội dung "🔎 [FIM sâu]..." xuất hiện bên hub kèm email, và **không** thấy file nào trong `wp-content/uploads` bị quét nhầm vào (kiểm tra nội dung log không liệt kê đường dẫn `uploads/...`).
9. **Xác nhận hệ tracker cũ đã gỡ sạch (Giai đoạn 3, Đợt 2)**: kiểm tra cron không còn `laca_fim_scan` trong WP Crontrol (nếu vẫn còn do site đã chạy trước khi update code, hook sẽ tự dọn ở lần load `init` kế tiếp — refresh lại trang bất kỳ trong wp-admin rồi kiểm tra lại). Kiểm tra file `app/src/Features/ClientTracker/Tracker.php` không còn tồn tại trên server.
