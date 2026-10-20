/* eslint-disable no-console */
/**
 * Critical CSS Generator
 * Run via: npm run critical
 */
// Critical module will be imported dynamically
const path = require('path');
const config = require('../../config.json');

// Allow skipping critical CSS generation in environments where Chromium can't launch
// Usage: SKIP_CRITICAL=1 yarn build
if (process.env.SKIP_CRITICAL === '1') {
    console.log('ℹ️  SKIP_CRITICAL=1, skipping Critical CSS generation.');
    process.exit(0);
}

// Get dev URL from config or fallback
const baseUrl = (config.development && config.development.url) ? config.development.url.replace(/\/+$/, '') : 'http://lixroastery.local';

// Mỗi loại trang 1 file critical-{type}.css — khớp filter lacadev_critical_css_path
// trong app/hooks.php. "home" đồng thời ghi ra critical.css làm fallback chung.
// ?lacadev_no_critical=1: lấy CSS gốc dạng blocking; nếu không, link đã bị đổi
// sang media="print" (async) và critical bỏ sót rule → layout nhảy (CLS).
const sampleUrls = {
    home: `${baseUrl}/`,
    shop: `${baseUrl}/shop/`,
    product: `${baseUrl}/product/aconchego-1-masterpieces-auction-2025/`,
    journal: `${baseUrl}/journal/`,
    single: `${baseUrl}/journal/lix-roastery-at-cafe-show-hanoi-2026/`,
    // Trang có Top Hero full màn hình bố cục khác hẳn trang thường (form, văn bản)
    // → 2 file riêng; dùng chung 1 file làm trang Contact nhảy layout (CLS 0.19).
    'page-hero': `${baseUrl}/manifesto/`,
    page: `${baseUrl}/contact/`,
};

console.log(`Starting Critical CSS generation for ${Object.keys(sampleUrls).length} page types...`);

// Resolve paths
// theme/dist is where we want to save
const distPath = path.resolve(__dirname, '../../dist/');

// Dimensions to check
const dimensions = [
    {
        height: 640,
        width: 360, // Mobile
    },
    {
        height: 1024,
        width: 768, // Tablet
    },
    {
        height: 900,
        width: 1200, // Desktop
    },
];

(async () => {
    try {
        const { generate } = await import('critical');
        const puppeteer = require('puppeteer');
        const fs = require('fs');
        // Prefer system Chrome over old bundled Chromium (r722234) which times out on macOS 15+
        const systemChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        const browserLaunchOptions = {
            headless: true,
            timeout: 120000,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors'],
        };
        const penthouseArgs = {
            // Local WP pages may include many third-party scripts; block JS requests
            // so Penthouse can focus on CSS and avoid waiting on long-running assets.
            blockJSRequests: true,
            // 30s default is often too short for local environments.
            timeout: 120000,
            // Stop waiting for full page load after a grace period.
            pageLoadSkipTimeout: 15000,
            // Small buffer to let above-the-fold styles settle.
            renderWaitTime: 1000,
            // Critical trích từ site LOCAL, mà local thiếu dữ liệu header của
            // production (logo dạng ảnh, menu trái) → rule header bị loại vì "không
            // dùng" → trên production logo hiện ảnh gốc 2560px rồi co lại (CLS 0.35).
            // Header luôn ở màn hình đầu nên giữ toàn bộ rule của nó.
            forceInclude: [/\.header/, /\.menu-item/],
            // Penthouse supports custom browser via puppeteer.getBrowser.
            // This avoids fallback to old bundled Chromium revisions.
            puppeteer: {
                getBrowser: () => puppeteer.launch(browserLaunchOptions),
                pageGotoOptions: {
                    waitUntil: 'domcontentloaded',
                    timeout: 60000,
                },
            },
        };
        if (fs.existsSync(systemChrome)) {
            browserLaunchOptions.executablePath = systemChrome;
        }

        const stylesDir = path.join(distPath, 'styles');
        fs.mkdirSync(stylesDir, { recursive: true });

        // @font-face của child (font thật + fallback metric) phải có ngay trong
        // critical: thiếu thì lần render đầu dùng sans-serif mặc định rồi nhảy khi
        // child.css async tải xong. Penthouse bỏ @font-face (ignore bên dưới) vì nó
        // gom cả Quicksand của parent → tự lấy từ child.css, đổi url tương đối
        // ../fonts/ sang đường dẫn từ gốc site (inline <style> resolve theo URL trang).
        const themeDirName = path.basename(path.resolve(__dirname, '../..'));
        const fontBaseUrl = `/wp-content/themes/${themeDirName}/dist/fonts/`;
        const childCssPath = path.join(stylesDir, 'child.css');
        const fontFaceCss = fs.existsSync(childCssPath)
            ? (fs.readFileSync(childCssPath, 'utf8').match(/@font-face\{[^}]*\}/g) || [])
                  .join('')
                  .replace(/url\(\.\.\/fonts\//g, `url(${fontBaseUrl}`)
            : '';

        // Store WooCommerce đang "Coming soon": khách ẩn danh nhận trang chờ của
        // plugin thay vì shop/sản phẩm thật → critical sinh ra sai hoàn toàn (CLS > 1).
        // CRITICAL_COOKIE="wordpress_logged_in_xxx=..." (cookie admin local) để
        // lấy trang thật; không có thì bỏ qua loại trang đó, giữ file cũ.
        // Chỉ dùng cookie cho trang đang bị Coming soon: trang đăng nhập kèm CSS
        // admin bar (~10KB) — không muốn nhồi vào critical của trang công khai.
        const cookie = process.env.CRITICAL_COOKIE || '';

        for (const [type, url] of Object.entries(sampleUrls)) {
            try {
                const src = `${url}${url.includes('?') ? '&' : '?'}lacadev_no_critical=1`;
                let requestHeaders = {};
                const html = await fetch(src).then((r) => r.text());
                // Body class chỉ có ở trang chờ thật; admin đã đăng nhập cũng có chữ
                // "woocommerce-coming-soon" (nút admin bar) nên không dùng chuỗi đó.
                if (html.includes('woocommerce-coming-soon-store-only')) {
                    if (!cookie) {
                        // Giữ file cũ nhưng thay @font-face bằng bản mới: tên font có
                        // hash, build lại là đổi → @font-face cũ trỏ file đã bị xoá (404).
                        const keptFile = path.join(stylesDir, `critical-${type}.css`);
                        if (fs.existsSync(keptFile)) {
                            const kept = fs.readFileSync(keptFile, 'utf8').replace(/@font-face\{[^}]*\}/g, '').replace(/^\n+/, '');
                            fs.writeFileSync(keptFile, fontFaceCss + '\n' + kept);
                        }
                        console.warn(`⚠️ [${type}] đang là trang Coming soon — giữ critical-${type}.css cũ (đã cập nhật @font-face; đặt CRITICAL_COOKIE để sinh lại).`);
                        continue;
                    }
                    requestHeaders = { Cookie: cookie };
                }
                console.log(`Extracting critical CSS [${type}] from: ${src}`);
                const result = await generate({
                    base: distPath,
                    src,
                    inline: false,
                    extract: false,
                    dimensions: dimensions,
                    ignore: {
                        atrule: ['@font-face'],
                    },
                    request: { headers: requestHeaders },
                    penthouse: { ...penthouseArgs, customPageHeaders: requestHeaders },
                });
                let css = result && result.css ? result.css : '';
                if (!css) {
                    console.warn(`⚠️ Empty critical CSS for ${type}, skipped.`);
                    continue;
                }

                // Đảm bảo font mặc định của child có ngay từ đầu, không render Quicksand của parent trước.
                if (!css.includes('VNF-Futura')) {
                    css = 'body:not(.wp-admin){font-family:VNF-Futura,"VNF-Futura Fallback",sans-serif!important}\n' + css;
                }
                css = fontFaceCss + '\n' + css;

                fs.writeFileSync(path.join(stylesDir, `critical-${type}.css`), css);
                if (type === 'home') {
                    fs.writeFileSync(path.join(stylesDir, 'critical.css'), css);
                }
                console.log(`✅ critical-${type}.css (${(css.length / 1024).toFixed(1)} KB)`);
            } catch (pageErr) {
                console.warn(`⚠️ Warning: could not extract critical CSS for ${url}:`, pageErr.message);
            }
        }
    } catch (err) {
        console.error('❌ Critical CSS Generation Failed:', err);
        // Default to non-fatal so `yarn build` can succeed even if Puppeteer crashes.
        // Set CRITICAL_STRICT=1 to fail the build on error.
        if (process.env.CRITICAL_STRICT === '1') {
            process.exit(1);
        }
        console.log('ℹ️  Continuing build (set CRITICAL_STRICT=1 to fail on critical errors).');
        process.exit(0);
    }
})();
