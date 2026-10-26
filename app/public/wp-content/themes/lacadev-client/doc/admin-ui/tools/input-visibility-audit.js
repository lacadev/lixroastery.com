/**
 * Nghiệm thu: mọi ô nhập (input text/url/email/number/password, select, textarea) hiển thị trong admin phải NHÌN THẤY ĐƯỢC
 * khi chưa focus — có viền ≥ 1px với độ tương phản viền/nền ≥ 1.4, hoặc nền ô khác nền khung chứa (tương phản ≥ 1.1).
 * Chạy: node doc/admin-ui/tools/input-visibility-audit.js [thêm-url ...]   — KHÔNG sửa logic kiểm. Chỉ đọc (abort POST).
 * In: PASS|FAIL <trang> [ô lỗi] + SUMMARY.
 */
const path = require('path');
const { spawnSync } = require('child_process');
const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const URLS = [
  'admin.php?page=laca-seo&view=general&tab=webmaster',
  'admin.php?page=laca-seo&view=general&tab=breadcrumbs',
  'admin.php?page=laca-seo&view=titles',
  'admin.php?page=laca-seo&view=sitemap',
  'admin.php?page=laca-recaptcha',
  'admin.php?page=laca-login-socials',
  'admin.php?page=laca-google-analytics',
  'admin.php?page=laca-google-search-console',
  'admin.php?page=app-theme-options.php',
  'admin.php?page=laca-admin',
  'options-general.php',
  ...process.argv.slice(2),
];

const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);";
const cookies = JSON.parse(spawnSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).stdout.toString());

let pass = 0, fail = 0;
(async () => {
  const browser = await puppeteer.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const watchdog = setTimeout(() => { console.log('FAIL watchdog'); browser.close(); process.exit(2); }, 6 * 60 * 1000);
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.setRequestInterception(true);
    page.on('request', (r) => (r.method() === 'POST' && !/admin-ajax/.test(r.url()) ? r.abort() : r.continue()));
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));
    for (const url of URLS) {
      try {
        const resp = await page.goto(`http://lixroastery.local/wp-admin/${url}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('#wpbody-content', { timeout: 30000 });
        await new Promise((r) => setTimeout(r, 2500));
        const notAllowed = await page.evaluate(() => /không có quyền|not allowed|Cannot load|Không thể tải/i.test(document.body.innerText.slice(0, 600)));
        if (!resp || resp.status() >= 400 || notAllowed) { fail++; console.log(`FAIL ${url} trang không mở được (status ${resp && resp.status()})`); continue; }
        await page.evaluate(() => document.activeElement && document.activeElement.blur());
        const bad = await page.evaluate(() => {
          const lum = (c) => {
            const m = c.match(/[\d.]+/g); if (!m) return null;
            const [r, g, b, a = 1] = m.map(Number); if (a === 0) return null;
            const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
            return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
          };
          const ratio = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
          const bgOf = (el) => { for (let e = el; e; e = e.parentElement) { const L = lum(getComputedStyle(e).backgroundColor); if (L !== null) return L; } return 1; };
          const out = [];
          document.querySelectorAll('#wpbody-content input[type="text"], #wpbody-content input[type="url"], #wpbody-content input[type="email"], #wpbody-content input[type="number"], #wpbody-content input[type="password"], #wpbody-content input:not([type]), #wpbody-content select, #wpbody-content textarea').forEach((el) => {
            if (el.offsetParent === null || el.getBoundingClientRect().width < 20) return;
            const cs = getComputedStyle(el);
            const parentBg = bgOf(el.parentElement);
            const ownBg = lum(cs.backgroundColor);
            const bw = parseFloat(cs.borderBottomWidth) + parseFloat(cs.borderTopWidth);
            const bc = lum(cs.borderBottomColor);
            const insetShadow = /inset/.test(cs.boxShadow) || (cs.boxShadow !== 'none' && /0px 0px 0px [1-9]/.test(cs.boxShadow));
            const borderOk = bw >= 1 && bc !== null && ratio(bc, ownBg ?? parentBg) >= 1.4;
            const fillOk = ownBg !== null && ratio(ownBg, parentBg) >= 1.1;
            if (!borderOk && !fillOk && !insetShadow) out.push((el.name || el.id || el.tagName).slice(0, 40));
          });
          return out;
        });
        if (bad.length) { fail++; console.log(`FAIL ${url} ${bad.length} ô không thấy viền: ${bad.slice(0, 6).join(', ')}`); }
        else { pass++; console.log(`PASS ${url}`); }
      } catch (e) {
        fail++; console.log(`FAIL ${url} ${e.message.slice(0, 80)}`);
      }
    }
  } finally {
    clearTimeout(watchdog);
    await browser.close();
  }
  console.log(`SUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail ? 1 : 0);
})();
