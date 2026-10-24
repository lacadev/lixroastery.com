/**
 * Nghiệm thu trang danh sách admin (cuộn ngang trong bảng, cột tiêu đề cố định, giá trị hiển thị, sửa nhanh).
 * Chạy: node doc/admin-ui/tools/list-table-audit.js   — KHÔNG sửa logic kiểm.
 * Chỉ đọc: abort mọi POST (sửa nhanh chỉ mở/đóng, không lưu). Cookie sinh lúc chạy, không lưu file.
 * In: PASS|FAIL <check> <post_type> <width> [chi tiết] + SUMMARY.
 */
const path = require('path');
const { spawnSync } = require('child_process');
const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const POST_TYPES = ['product', 'journal', 'page', 'post', 'partnership', 'glossary'];
const WIDTHS = [1440, 1024, 390];

const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);";
// PHP CLI đôi khi exit 255 sau khi đã in JSON → đọc stdout, bỏ qua mã thoát.
const cookies = JSON.parse(spawnSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).stdout.toString());

let pass = 0, fail = 0;
const report = (ok, check, pt, w, detail = '') => {
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check} ${pt} ${w}${detail ? ' ' + detail : ''}`);
};

(async () => {
  const browser = await puppeteer.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const watchdog = setTimeout(() => { console.log('FAIL watchdog'); browser.close(); process.exit(2); }, 10 * 60 * 1000);
  try {
    const page = await browser.newPage();
    let errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.setRequestInterception(true);
    page.on('request', (r) => (r.method() === 'POST' ? r.abort() : r.continue()));
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));

    for (const pt of POST_TYPES) {
      for (const w of WIDTHS) {
        errors = [];
        try {
          await page.setViewport({ width: w, height: 900 });
          await page.goto(`http://lixroastery.local/wp-admin/edit.php?post_type=${pt}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
          await page.waitForSelector('.wp-list-table tbody tr', { timeout: 30000 });
          await new Promise((r) => setTimeout(r, 1200));
          const m = await page.evaluate(async () => {
            const t = document.querySelector('.wp-list-table');
            let sc = null;
            for (let e = t.parentElement; e && e !== document.body; e = e.parentElement) {
              const ox = getComputedStyle(e).overflowX;
              if (ox === 'auto' || ox === 'scroll') { sc = e; break; }
            }
            const res = { docOver: document.documentElement.scrollWidth - innerWidth, hasScroller: !!sc };
            res.scrollerFits = sc ? sc.getBoundingClientRect().right <= innerWidth + 1 : false;
            res.tablenavOutside = sc ? ![...document.querySelectorAll('.tablenav')].some((n) => sc.contains(n)) : false;

            const titleTd = t.querySelector('tbody tr:not(.no-items) td.column-title, tbody tr:not(.no-items) td.column-name');
            res.hasTitle = !!titleTd;
            if (titleTd && sc && sc.scrollWidth > sc.clientWidth + 2) {
              const before = titleTd.getBoundingClientRect().left;
              sc.scrollLeft = 400;
              await new Promise((r) => setTimeout(r, 200));
              const after = titleTd.getBoundingClientRect().left;
              const bg = getComputedStyle(titleTd).backgroundColor;
              const alpha = bg.startsWith('rgba') ? parseFloat(bg.split(',')[3]) : 1;
              // Ô kế bên (đã cuộn) không được lộ chữ đè lên ô tiêu đề: tiêu đề phải nằm trên (z-index) và nền đặc.
              res.sticky = Math.abs(after - before) <= 1 && alpha === 1 && bg !== 'transparent';
              res.stickyDetail = `dx=${Math.round(after - before)} bg=${bg}`;
              sc.scrollLeft = 0;
              await new Promise((r) => setTimeout(r, 100));
            } else {
              res.sticky = null; // bảng không cần cuộn ở bề rộng này
            }

            let overlap = 0;
            t.querySelectorAll('tbody td.column-title, tbody td.column-name').forEach((td) => {
              const r = td.getBoundingClientRect();
              td.querySelectorAll('a.row-title, strong').forEach((a) => { if (a.getBoundingClientRect().right > r.right + 1) overlap++; });
            });
            res.overlap = overlap;

            const texts = [...t.querySelectorAll('tbody td')].map((td) => td.innerText.trim());
            res.rawStock = texts.filter((x) => ['instock', 'outofstock', 'onbackorder'].includes(x)).length;
            const taxTds = [...t.querySelectorAll('tbody td')].filter((td) => {
              const cell = td.querySelector('.laca-inline-cell');
              return cell && cell.dataset.type === 'taxonomy';
            });
            res.taxIds = taxTds.filter((td) => /^\d+(\s*,\s*\d+)*$/.test(td.innerText.trim())).length;

            const cell = t.querySelector('.laca-inline-cell');
            res.inline = 'no-cell';
            if (cell) {
              const original = cell.innerText.trim();
              cell.click();
              await new Promise((r) => setTimeout(r, 600));
              const ed = cell.closest('td').querySelector('input:not([type=hidden]), select, textarea') ||
                document.querySelector('.laca-inline-popover input, .laca-inline-popover select');
              if (!ed) res.inline = 'no-editor';
              else {
                ed.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
                await new Promise((r) => setTimeout(r, 400));
                const stillOpen = !!cell.closest('td').querySelector('input:not([type=hidden]), select, textarea');
                res.inline = stillOpen ? 'esc-not-closing' : (cell.innerText.trim() === original ? 'ok' : 'value-changed');
              }
            }
            return res;
          });
          report(m.docOver <= 0, 'page-no-hscroll', pt, w, `docOver=${m.docOver}`);
          report(m.hasScroller && m.scrollerFits, 'table-scroller', pt, w);
          report(m.tablenavOutside, 'tablenav-outside-scroller', pt, w);
          if (m.sticky !== null) report(m.sticky, 'sticky-title', pt, w, m.stickyDetail);
          report(m.overlap === 0, 'title-no-overlap', pt, w, `overlap=${m.overlap}`);
          report(m.rawStock === 0, 'stock-label', pt, w, `raw=${m.rawStock}`);
          report(m.taxIds === 0, 'taxonomy-names', pt, w, `idCells=${m.taxIds}`);
          if (m.inline !== 'no-cell') report(m.inline === 'ok', 'inline-open-esc', pt, w, m.inline);
          report(errors.length === 0, 'no-js-error', pt, w, errors[0] ? errors[0].slice(0, 100) : '');
        } catch (e) {
          report(false, 'load', pt, w, e.message.slice(0, 100));
        }
      }
    }
  } finally {
    clearTimeout(watchdog);
    await browser.close();
  }
  console.log(`SUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail ? 1 : 0);
})();
