/**
 * Nghiệm thu khối hướng dẫn Google Services (GSC & GA4) theo doc/GOOGLE-SERVICES-GUIDE.md:
 * - Link https:// trong khối hướng dẫn mở tab mới target="_blank" rel="noopener noreferrer"
 * - Đủ các URL trích từ doc trên từng trang tương ứng
 * - Form GA4 hiển thị switch chuẩn (.laca-seo-switch), textarea có max-width 560px
 * - Chụp ảnh 1440px và 390px
 *
 * Chạy: node doc/admin-ui/tools/google-services-guide-audit.js
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const IMG_DIR = '/private/tmp/admin-ui/google-guide';

fs.mkdirSync(IMG_DIR, { recursive: true });

// Danh sách URL trích từ doc/GOOGLE-SERVICES-GUIDE.md:
// Mục 1 + Mục 3 xuất hiện trên trang Search Console
const DOC_GSC_URLS = [
  'https://search.google.com/search-console',
  'https://search.google.com/search-console/users',
  'https://search.google.com/search-console/sitemaps',
  'https://console.cloud.google.com/projectcreate',
  'https://console.cloud.google.com/apis/library/searchconsole.googleapis.com',
  'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com',
  'https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com',
  'https://console.cloud.google.com/auth/overview',
  'https://console.cloud.google.com/auth/audience',
  'https://console.cloud.google.com/auth/clients',
];

// Mục 2 xuất hiện trên trang Google Analytics
const DOC_GA_URLS = [
  'https://analytics.google.com',
];

const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;$m=WP_Session_Tokens::get_instance($u->ID);$t=$m->create($e);echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in',$t),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth',$t)]);";
const cookies = JSON.parse(spawnSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).stdout.toString());

let pass = 0, fail = 0;
const report = (ok, check, detail = '') => {
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check}${detail ? ' ' + detail : ''}`);
};

(async () => {
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const watchdog = setTimeout(() => {
    console.log('FAIL watchdog timeout (3m)');
    browser.close();
    process.exit(2);
  }, 3 * 60 * 1000);

  try {
    const page = await browser.newPage();
    let pageErrors = [];
    page.on('pageerror', (e) => pageErrors.push(e.message));

    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({
      name,
      value,
      domain: 'lixroastery.local',
      path: '/',
    })));

    // 1. KIỂM TRA TRANG GOOGLE SEARCH CONSOLE
    for (const width of [1440, 390]) {
      await page.setViewport({ width, height: width === 1440 ? 900 : 844 });
      await page.goto('http://lixroastery.local/wp-admin/admin.php?page=laca-google-search-console', {
        waitUntil: 'networkidle2',
        timeout: 30000,
      });

      if (width === 1440) {
        // Kiểm tra khối hướng dẫn có mặt
        const guideWrapExists = await page.$('.laca-google-guide-wrap');
        report(!!guideWrapExists, 'GSC: Guide wrapper exists');

        // Kiểm tra các link https:// trong guide
        const linkAudit = await page.evaluate(() => {
          const links = Array.from(document.querySelectorAll('.laca-google-guide-wrap a'));
          const httpsLinks = links.filter((a) => (a.getAttribute('href') || '').startsWith('https://'));
          const badLinks = httpsLinks.filter((a) => {
            const target = a.getAttribute('target');
            const rel = a.getAttribute('rel') || '';
            return target !== '_blank' || !rel.includes('noopener') || !rel.includes('noreferrer');
          });
          const hrefs = httpsLinks.map((a) => a.getAttribute('href'));
          return { totalHttps: httpsLinks.length, badCount: badLinks.length, hrefs };
        });

        report(linkAudit.totalHttps >= 10, 'GSC: Found https links count', `total=${linkAudit.totalHttps}`);
        report(linkAudit.badCount === 0, 'GSC: All https links have target="_blank" and rel="noopener noreferrer"', `bad=${linkAudit.badCount}`);

        // So khớp danh sách URL trích từ doc
        const missingGscUrls = DOC_GSC_URLS.filter((u) => !linkAudit.hrefs.includes(u));
        report(missingGscUrls.length === 0, 'GSC: Contains all expected external URLs from doc', missingGscUrls.length ? `missing: ${missingGscUrls.join(', ')}` : `count=${DOC_GSC_URLS.length}/${DOC_GSC_URLS.length}`);

        // Kiểm tra nút sao chép URI chuyển hướng
        const copyBtnExists = await page.$('.laca-seo-prep-step__copy');
        report(!!copyBtnExists, 'GSC: Copy button exists for redirect URI');

        // Kiểm tra bảng mục 4 "Lỗi đã gặp" có container cuộn ngang
        const tableResponsive = await page.$('.laca-seo-prep-details .laca-seo-table-responsive table');
        report(!!tableResponsive, 'GSC: Section 4 table is inside responsive scroll container');
      }

      const ssPath = `${IMG_DIR}/gsc-${width}.png`;
      await page.screenshot({ path: ssPath, fullPage: true });
      report(fs.existsSync(ssPath), `GSC: Screenshot captured at ${width}px`, ssPath);
    }

    // 2. KIỂM TRA TRANG GOOGLE ANALYTICS
    for (const width of [1440, 390]) {
      await page.setViewport({ width, height: width === 1440 ? 900 : 844 });
      await page.goto('http://lixroastery.local/wp-admin/admin.php?page=laca-google-analytics', {
        waitUntil: 'networkidle2',
        timeout: 30000,
      });

      if (width === 1440) {
        // Kiểm tra khối hướng dẫn có mặt
        const guideWrapExists = await page.$('.laca-google-guide-wrap');
        report(!!guideWrapExists, 'GA: Guide wrapper exists');

        // Kiểm tra link https:// trong guide
        const linkAudit = await page.evaluate(() => {
          const links = Array.from(document.querySelectorAll('.laca-google-guide-wrap a'));
          const httpsLinks = links.filter((a) => (a.getAttribute('href') || '').startsWith('https://'));
          const badLinks = httpsLinks.filter((a) => {
            const target = a.getAttribute('target');
            const rel = a.getAttribute('rel') || '';
            return target !== '_blank' || !rel.includes('noopener') || !rel.includes('noreferrer');
          });
          const hrefs = httpsLinks.map((a) => a.getAttribute('href'));
          return { totalHttps: httpsLinks.length, badCount: badLinks.length, hrefs };
        });

        report(linkAudit.totalHttps >= 1, 'GA: Found https links count', `total=${linkAudit.totalHttps}`);
        report(linkAudit.badCount === 0, 'GA: All https links have target="_blank" and rel="noopener noreferrer"', `bad=${linkAudit.badCount}`);

        // So khớp URL trích từ doc
        const missingGaUrls = DOC_GA_URLS.filter((u) => !linkAudit.hrefs.includes(u));
        report(missingGaUrls.length === 0, 'GA: Contains all expected external URLs from doc', missingGaUrls.length ? `missing: ${missingGaUrls.join(', ')}` : `count=${DOC_GA_URLS.length}/${DOC_GA_URLS.length}`);

        // Kiểm tra Component Switch (thay vì checkbox trần)
        const switchAudit = await page.evaluate(() => {
          const anonymizeSwitch = document.querySelector('label.laca-seo-switch input[name="ga_anonymize_ip"]');
          const excludeAdminSwitch = document.querySelector('label.laca-seo-switch input[name="ga_exclude_loggedin"]');
          const sliders = document.querySelectorAll('label.laca-seo-switch .laca-seo-switch__slider');
          return {
            hasAnonymizeSwitch: !!anonymizeSwitch,
            hasExcludeAdminSwitch: !!excludeAdminSwitch,
            sliderCount: sliders.length,
          };
        });

        report(switchAudit.hasAnonymizeSwitch && switchAudit.hasExcludeAdminSwitch, 'GA: Toggles use .laca-seo-switch component');
        report(switchAudit.sliderCount >= 2, 'GA: Switches contain .laca-seo-switch__slider');

        // Kiểm tra kích thước ô textarea "Mục tiêu chuyển đổi" và input
        const inputWidthAudit = await page.evaluate(() => {
          const textarea = document.getElementById('ga4_goal_events');
          const input = document.getElementById('ga4_measurement_id');
          if (!textarea || !input) return { ok: false, reason: 'Elements not found' };
          const taStyle = window.getComputedStyle(textarea);
          const inpStyle = window.getComputedStyle(input);
          return {
            ok: taStyle.maxWidth === '560px' && inpStyle.maxWidth === '560px',
            taMaxWidth: taStyle.maxWidth,
            inpMaxWidth: inpStyle.maxWidth,
          };
        });

        report(inputWidthAudit.ok, 'GA: Textarea and inputs have max-width 560px', `ta=${inputWidthAudit.taMaxWidth} inp=${inputWidthAudit.inpMaxWidth}`);
      }

      const ssPath = `${IMG_DIR}/ga-${width}.png`;
      await page.screenshot({ path: ssPath, fullPage: true });
      report(fs.existsSync(ssPath), `GA: Screenshot captured at ${width}px`, ssPath);
    }

    report(pageErrors.length === 0, 'No JavaScript errors on either page', pageErrors.join(' | '));
  } catch (err) {
    fail++;
    console.error('FAIL Unexpected error:', err);
  } finally {
    clearTimeout(watchdog);
    await browser.close();
  }

  console.log(`\nSUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail === 0 ? 0 : 1);
})();
