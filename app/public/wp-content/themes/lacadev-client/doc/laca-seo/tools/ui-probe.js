/**
 * Nghiệm thu UI admin Laca SEO (local). Không ghi DB: abort MỌI request POST, cookie sinh lúc chạy, không lưu file.
 * Chạy: node doc/laca-seo/tools/ui-probe.js [thư-mục-ảnh]   (LOGIN=<user> để đăng nhập user khác, mặc định lacadev)
 * In: PASS|FAIL <check> <view> <width> [chi tiết] + SUMMARY.
 */
const path = require('path');
const { execFileSync } = require('child_process');
const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const LOGIN = process.env.LOGIN || 'lacadev';
const OUT = process.argv[2] || '';
const BASE = 'http://lixroastery.local/wp-admin/admin.php?page=laca-seo';
const VIEWS = ['dashboard', 'general', 'titles', 'sitemap', 'instant-indexing', 'redirections', '404-monitor', 'search-console', 'import-export', 'guide'];
const WIDTHS = [1440, 1024, 390];

const code = `define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login',${JSON.stringify(LOGIN)});if(!$u){exit(1);}$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);`;
const cookies = JSON.parse(execFileSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).toString());

let pass = 0, fail = 0;
const report = (ok, check, view, w, detail = '') => {
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check} ${view} ${w}${detail ? ' ' + detail : ''}`);
};

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const page = await browser.newPage();
  let errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setRequestInterception(true);
  page.on('request', (r) => (r.method() === 'POST' ? r.abort() : r.continue()));
  await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));

  // 1. Menu: Laca SEO là menu cấp 1, nằm giữa Laca Admin và Laca Theme.
  await page.setViewport({ width: 1440, height: 900 });
  await page.goto(BASE, { waitUntil: 'networkidle2', timeout: 180000 });
  const menu = await page.evaluate(() => {
    const ids = [...document.querySelectorAll('#adminmenu > li.menu-top')].map((li) => li.id);
    return { ids, ok: !/không đủ quyền|not allowed/i.test(document.body.innerText.slice(0, 500)) };
  });
  const iA = menu.ids.indexOf('toplevel_page_laca-admin');
  const iS = menu.ids.indexOf('toplevel_page_laca-seo');
  const iT = menu.ids.indexOf('toplevel_page_app-theme-options');
  report(menu.ok, 'access', 'page', 1440, LOGIN);
  report(iS !== -1 && (iA === -1 || iA < iS) && (iT === -1 || iS < iT), 'menu-order', 'adminmenu', 1440, `admin=${iA} seo=${iS} theme=${iT}`);

  for (const w of WIDTHS) {
    await page.setViewport({ width: w, height: 900 });
    for (const v of VIEWS) {
      errors = [];
      await page.goto(`${BASE}&view=${v}`, { waitUntil: 'networkidle2', timeout: 180000 });
      const m = await page.evaluate(() => {
        const wrap = document.querySelector('.laca-seo-wrap');
        if (!wrap) return { missing: true };
        const wide = [...wrap.querySelectorAll('*')]
          .filter((e) => {
            const r = e.getBoundingClientRect();
            if (!r.width || r.right <= innerWidth + 1) return false;
            // Phần tử nằm trong khung cuộn ngang có chủ đích thì không tính.
            for (let p = e.parentElement; p && p !== wrap; p = p.parentElement) {
              const ox = getComputedStyle(p).overflowX;
              if (ox === 'auto' || ox === 'scroll') return false;
            }
            return true;
          })
          .slice(0, 3)
          .map((e) => e.tagName.toLowerCase() + '.' + [...e.classList].slice(0, 2).join('.'));
        // Notice của plugin khác hiển thị trên trang Laca SEO (của Laca SEO thì mang class laca-seo-*).
        const foreign = [...document.querySelectorAll('#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error, #wpbody-content .update-nag')]
          .filter((n) => n.offsetParent !== null && ![...n.classList].some((c) => c.startsWith('laca-seo')) && !n.closest('.laca-seo-wrap')).length;
        return { over: document.documentElement.scrollWidth - innerWidth, wide, foreign };
      });
      if (m.missing) { report(false, 'render', v, w, 'không thấy .laca-seo-wrap'); continue; }
      report(m.over <= 0 && m.wide.length === 0, 'no-overflow', v, w, m.wide.join(','));
      report(m.foreign === 0, 'no-foreign-notice', v, w, String(m.foreign));
      report(errors.length === 0, 'no-js-error', v, w, errors[0] ? errors[0].slice(0, 100) : '');
      if (OUT) await page.screenshot({ path: path.join(OUT, `${v}-${w}.png`) });

      // 2. showIf: mỗi hàng phụ thuộc phải hiện khi điều kiện đúng, ẩn khi sai (chỉ kiểm ở 1440, đi hết tab con).
      if (w !== 1440) continue;
      const subTabs = await page.evaluate(() => [...document.querySelectorAll('.laca-seo-tabs-list__link')].map((a) => a.href));
      for (const url of subTabs.length ? subTabs : [null]) {
      if (url) await page.goto(url, { waitUntil: 'networkidle2', timeout: 180000 });
      const tabName = url ? v + ':' + (new URL(url).searchParams.get('tab') || '') : v;
      const deps = await page.evaluate(async () => {
        const res = [];
        const rows = [...document.querySelectorAll('[data-show-if-field]')];
        const control = (id) =>
          document.querySelector(`input[type="checkbox"][name="laca_seo[${id}]"]`) ||
          document.querySelector(`select[name="laca_seo[${id}]"]`) ||
          document.querySelector(`input[type="radio"][name="laca_seo[${id}]"]`);
        const setVal = (id, want) => {
          const c = control(id);
          if (!c) return false;
          if (c.type === 'checkbox') c.checked = want === 'on';
          else if (c.type === 'radio') {
            const r = document.querySelector(`input[type="radio"][name="laca_seo[${id}]"][value="${want}"]`) ||
              document.querySelector(`input[type="radio"][name="laca_seo[${id}]"]:not([value="${want}"])`);
            if (r) r.checked = true;
          } else {
            const opt = [...c.options].find((o) => (want === null ? true : o.value === want));
            if (!opt) return false;
            c.value = opt.value;
          }
          (c.type === 'radio' ? document.querySelector(`input[type="radio"][name="laca_seo[${id}]"]:checked`) : c)
            .dispatchEvent(new Event('change', { bubbles: true }));
          return true;
        };
        const visible = (el) => getComputedStyle(el).display !== 'none' && el.offsetParent !== null;
        for (const row of rows) {
          const id = row.dataset.showIfField;
          const want = row.dataset.showIfValue;
          const label = row.dataset.fieldId || row.querySelector('[name]')?.getAttribute('name') || '?';
          // Bật chuỗi cha (nếu chính master cũng phụ thuộc field khác) trước khi kiểm.
          const chain = [];
          for (let r = row; r && r.dataset.showIfField; ) {
            chain.unshift([r.dataset.showIfField, r.dataset.showIfValue]);
            const c = control(r.dataset.showIfField);
            r = c ? c.closest('[data-show-if-field]') : null;
          }
          if (!chain.every(([f, v]) => setVal(f, v))) { res.push(`${label}:no-master(${id})`); continue; }
          // Panel tab con có thể đang ẩn: chỉ so trạng thái display của chính hàng.
          const shown = row.style.display !== 'none';
          const offVal = control(id)?.type === 'checkbox' ? (want === 'on' ? 'off' : 'on') : null;
          let hidden = true;
          if (offVal !== null || control(id)?.tagName === 'SELECT') {
            if (control(id).tagName === 'SELECT') {
              const other = [...control(id).options].find((o) => o.value !== want);
              if (other) { control(id).value = other.value; control(id).dispatchEvent(new Event('change', { bubbles: true })); }
            } else setVal(id, offVal);
            hidden = row.style.display === 'none';
          }
          if (!shown || !hidden) res.push(`${label}:shown=${shown},hiddenWhenOff=${hidden}`);
        }
        return { total: rows.length, bad: res };
      });
      report(deps.bad.length === 0, 'show-if', tabName, w, `${deps.total} hàng${deps.bad.length ? ' lỗi: ' + deps.bad.slice(0, 5).join(' | ') : ''}`);
      }
    }
  }
  await browser.close();
  console.log(`SUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail ? 1 : 0);
})();
