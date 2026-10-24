/**
 * Nghiệm thu tự lưu AJAX: CHỈ lưu khi có thay đổi thật, đúng thời điểm (checkbox: ngay; input/textarea: khi rời ô; select: khi chọn xong).
 * Chạy: node doc/admin-ui/tools/autosave-audit.js   — KHÔNG sửa logic kiểm.
 * Không ghi DB: mọi request POST lưu bị chặn và trả về JSON thành công giả. Cookie sinh lúc chạy, không lưu file.
 * In: PASS|FAIL <check> <trang> [chi tiết] + SUMMARY.
 */
const path = require('path');
const { spawnSync } = require('child_process');
const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const PAGES = {
  'laca-theme': 'admin.php?page=app-theme-options.php',
  'laca-admin': 'admin.php?page=laca-admin',
  'laca-seo-general': 'admin.php?page=laca-seo&view=general',
};

const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);";
const cookies = JSON.parse(spawnSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).stdout.toString());

let pass = 0, fail = 0;
const report = (ok, check, name, detail = '') => {
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check} ${name}${detail ? ' ' + detail : ''}`);
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const watchdog = setTimeout(() => { console.log('FAIL watchdog'); browser.close(); process.exit(2); }, 8 * 60 * 1000);
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    let saves = 0;
    let errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.setRequestInterception(true);
    page.on('request', (r) => {
      if (r.method() !== 'POST') return r.continue();
      const body = r.postData() || '';
      // Heartbeat / truy vấn đọc (lấy options) không tính là lưu.
      if (/action=heartbeat|action=laca_inline_get_options|action=laca_seo_preview_vars/.test(body)) {
        return r.respond({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{}}' });
      }
      saves++;
      return r.respond({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"timestamp":"00:00"}}' });
    });
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));

    for (const [name, url] of Object.entries(PAGES)) {
      errors = [];
      try {
        saves = 0;
        await page.goto(`http://lixroastery.local/wp-admin/${url}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('#wpbody-content', { timeout: 30000 });
        await sleep(6000);
        report(saves === 0, 'no-save-on-load', name, `saves=${saves}`);

        saves = 0;
        const tabs = await page.$$('.cf-container__tabs-item, .cf-container__tabs-list a, [role="tab"], .laca-seo-tabs-list__link');
        for (const tab of tabs.slice(1, 4)) {
          const href = await tab.evaluate((el) => el.getAttribute('href') || '');
          if (href && !href.startsWith('#')) continue; // tab là link sang trang khác → bỏ qua
          await tab.click().catch(() => {});
          await sleep(1200);
        }
        if (tabs.length) await tabs[0].click().catch(() => {});
        await sleep(2500);
        report(saves === 0, 'no-save-on-tab-switch', name, `saves=${saves}`);

        // Ô text đầu tiên đang hiển thị (không phải ô tìm kiếm); tab hiện tại không có thì lần lượt mở các tab (tab cùng trang).
        const findInput = () => page.evaluateHandle(() => [...document.querySelectorAll('#wpbody-content input[type="text"], #wpbody-content input[type="url"], #wpbody-content input:not([type]), #wpbody-content textarea')]
          .find((el) => el.offsetParent !== null && !el.readOnly && !el.disabled && !/search/i.test(el.name + el.id + el.className)) || null);
        let input = await findInput();
        for (const tab of tabs) {
          if (await input.evaluate((el) => !!el)) break;
          const href = await tab.evaluate((el) => el.getAttribute('href') || '');
          if (href && !href.startsWith('#')) continue;
          await tab.click().catch(() => {});
          await sleep(1000);
          input = await findInput();
        }
        await sleep(2000);
        if (await input.evaluate((el) => !!el)) {
          saves = 0;
          await input.click();
          await page.keyboard.type('abc', { delay: 120 });
          await sleep(2500);
          report(saves === 0, 'no-save-while-typing', name, `saves=${saves}`);
          for (let i = 0; i < 3; i++) await page.keyboard.press('Backspace');
          await input.evaluate((el) => el.blur());
          await sleep(2000);
          report(saves === 0, 'no-save-if-reverted', name, `saves=${saves}`);
          saves = 0;
          await input.click();
          await page.keyboard.type('x');
          await input.evaluate((el) => el.blur());
          await sleep(2500);
          report(saves === 1, 'save-once-on-blur', name, `saves=${saves}`);
          // trả lại (chỉ trên giao diện, request đã bị chặn)
          await input.click();
          await page.keyboard.press('End');
          await page.keyboard.press('Backspace');
          await input.evaluate((el) => el.blur());
          await sleep(1500);
        } else {
          report(false, 'has-text-input', name, 'không tìm thấy ô text hiển thị để kiểm');
        }

        const checkbox = await page.evaluateHandle(() => [...document.querySelectorAll('#wpbody-content input[type="checkbox"]')]
          .find((el) => el.offsetParent !== null && !el.disabled && !el.closest('.wp-list-table')) || null);
        if (await checkbox.evaluate((el) => !!el)) {
          saves = 0;
          await checkbox.click();
          await sleep(1500);
          report(saves === 1, 'save-once-on-checkbox', name, `saves=${saves}`);
          await checkbox.click();
          await sleep(1500);
        }

        const select = await page.evaluateHandle(() => [...document.querySelectorAll('#wpbody-content select:not([multiple])')]
          .find((el) => el.offsetParent !== null && !el.disabled && el.options.length > 1) || null);
        if (await select.evaluate((el) => !!el)) {
          saves = 0;
          const [orig, other] = await select.evaluate((el) => [el.value, [...el.options].find((o) => o.value !== el.value).value]);
          await select.select(other);
          await sleep(1500);
          report(saves === 1, 'save-once-on-select', name, `saves=${saves}`);
          await select.select(orig);
          await sleep(1500);
        }
        report(errors.length === 0, 'no-js-error', name, errors[0] ? errors[0].slice(0, 100) : '');
      } catch (e) {
        report(false, 'load', name, e.message.slice(0, 100));
      }
    }
  } finally {
    clearTimeout(watchdog);
    await browser.close();
  }
  console.log(`SUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail ? 1 : 0);
})();
