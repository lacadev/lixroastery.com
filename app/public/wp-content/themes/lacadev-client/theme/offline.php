<?php
/**
 * Offline Fallback Page
 *
 * Được Service Worker trả về khi người dùng offline
 * và trang chưa được cache.
 *
 * @package LacaDev
 */

// Không cho WP redirect sang login
define('DONOTREDIRECT', true);

// Load WordPress cơ bản để có get_bloginfo, etc.
// Trang này được serve bởi SW, nên cần headers phù hợp.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$siteName = function_exists('get_bloginfo') ? get_bloginfo('name') : 'LacaDev';
$homeUrl  = function_exists('home_url') ? home_url('/') : '/';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Không có kết nối mạng — <?php echo esc_html($siteName); ?></title>
    <style>
        :root {
            --primary: #2563eb;
            --bg: #f0f2f5;
            --text: #1f2937;
            --muted: #6b7280;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 24px; text-align: center;
        }
        .icon {
            font-size: 72px; margin-bottom: 24px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }
        h1 { font-size: 24px; font-weight: 800; margin-bottom: 12px; }
        p  { color: var(--muted); font-size: 15px; line-height: 1.7; max-width: 380px; margin: 0 auto 32px; }
        .btn {
            display: inline-block; padding: 12px 28px;
            background: var(--primary); color: #fff; border-radius: 8px;
            font-size: 15px; font-weight: 600; text-decoration: none;
            border: none; cursor: pointer; transition: opacity .2s;
        }
        .btn:hover { opacity: .85; }
        .status {
            margin-top: 32px; font-size: 12px; color: var(--muted);
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #ef4444; display: inline-block;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .3; }
        }
    </style>
</head>
<body>
    <div class="icon">📡</div>
    <h1>Không có kết nối Internet</h1>
    <p>
        Trang này chưa được lưu trong bộ nhớ đệm.<br>
        Vui lòng kiểm tra lại kết nối mạng và thử lại.
    </p>
    <button class="btn" onclick="window.location.reload()">Thử lại</button>
    <div class="status">
        <span class="dot"></span>
        <span>Đang chờ kết nối...</span>
    </div>
    <script>
        // Tự động reload khi có mạng trở lại
        window.addEventListener('online', function () {
            window.location.reload();
        });
    </script>
</body>
</html>
