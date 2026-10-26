/**
 * Nghiệm thu báo cáo Google Services (GSC + GA4) trên WordPress Dashboard (index.php).
 * Chạy: node doc/admin-ui/tools/wp-dashboard-report-audit.js
 *
 * Kiểm tra:
 * - index.php?laca_mock_google=1 ở 1440/1024/390:
 *   + #laca-google-report tồn tại
 *   + getBoundingClientRect().top < 450 ở 1440
 *   + sau tối đa 15s có cả khu GSC và GA ("Google Search Console" và "Google Analytics 4")
 *   + có banner "DỮ LIỆU MẪU"
 *   + không pageerror
 *   + docOver <= 0
 *   + chụp ảnh lưu vào /private/tmp/admin-ui/google/
 * - index.php (không mock, local chưa kết nối):
 *   + 2 trạng thái rỗng "Thiết lập", không có số liệu
 * - admin.php?page=laca-seo&view=search-console:
 *   + không có chữ "critical error"
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const WIDTHS = [1440, 1024, 390];
const IMG_DIR = '/private/tmp/admin-ui/google';

fs.mkdirSync(IMG_DIR, { recursive: true });

const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;$m=WP_Session_Tokens::get_instance($u->ID);$t=$m->create($e);echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in',$t),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth',$t)]);";
const cookies = JSON.parse(spawnSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).stdout.toString());

let pass = 0, fail = 0;
const report = (ok, check, w = '', detail = '') => {
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check}${w ? ' ' + w : ''}${detail ? ' ' + detail : ''}`);
};

(async () => {
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const watchdog = setTimeout(() => {
    console.log('FAIL watchdog timeout (5m)');
    browser.close();
    process.exit(2);
  }, 5 * 60 * 1000);

  try {
    const page = await browser.newPage();
    let errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    // Enable cookies
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({
      name,
      value,
      domain: 'lixroastery.local',
      path: '/',
    })));

    // 1. Kiểm tra index.php?laca_mock_google=1 ở 1440, 1024, 390
    for (const w of WIDTHS) {
      errors = [];
      try {
        await page.setViewport({ width: w, height: 900 });
        await page.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1', {
          waitUntil: 'networkidle2',
          timeout: 45000,
        });

        // Đợi #laca-google-report tồn tại
        await page.waitForSelector('#laca-google-report', { timeout: 10000 });

        // Đo vị trí top ở 1440
        const reportMetrics = await page.evaluate(() => {
          const el = document.getElementById('laca-google-report');
          const rect = el ? el.getBoundingClientRect() : null;
          return {
            exists: !!el,
            top: rect ? rect.top : -1,
            docOver: document.documentElement.scrollWidth - window.innerWidth,
          };
        });

        report(reportMetrics.exists, 'ReportContainerExists', w);

        if (w === 1440) {
          report(reportMetrics.top >= 0 && reportMetrics.top < 450, 'ReportPositionTopUnder450', w, `top=${Math.round(reportMetrics.top)}px`);
        }

        // Đợi nội dung AJAX tải xong (tối đa 15s)
        await page.waitForFunction(() => {
          const body = document.getElementById('laca-google-report');
          if (!body) return false;
          const text = body.innerText || '';
          return text.includes('Google Search Console') && text.includes('Google Analytics 4');
        }, { timeout: 15000 });

        // Kiểm tra nội dung chi tiết
        const contentCheck = await page.evaluate(() => {
          const el = document.getElementById('laca-google-report');
          const text = el ? (el.innerText || '') : '';
          const hasGsc = text.includes('Google Search Console');
          const hasGa = text.includes('Google Analytics 4');
          const hasMockBanner = text.includes('DỮ LIỆU MẪU');
          const docOver = document.documentElement.scrollWidth - window.innerWidth;
          return { hasGsc, hasGa, hasMockBanner, docOver };
        });

        report(contentCheck.hasGsc && contentCheck.hasGa, 'BothGoogleSectionsPresent', w);
        report(contentCheck.hasMockBanner, 'MockDataBannerPresent', w);
        report(contentCheck.docOver <= 0, 'NoHorizontalPageScroll', w, `docOver=${contentCheck.docOver}px`);
        report(errors.length === 0, 'NoPageErrors', w, errors.join('; '));

        // Chụp ảnh lưu file
        const screenshotPath = path.join(IMG_DIR, `wp-dashboard-mock-${w}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: false });

      } catch (err) {
        report(false, 'MockGoogleReportTestFailed', w, err.message);
      }
    }

    // 1.1 Kiểm tra PageSpeed Insights AJAX trên index.php?laca_mock_google=1
    errors = [];
    try {
      await page.setViewport({ width: 1440, height: 900 });
      await page.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1', {
        waitUntil: 'networkidle2',
        timeout: 45000,
      });

      // Đợi báo cáo Google tải xong và có nút kiểm tra PageSpeed
      await page.waitForSelector('#laca-google-report [data-laca-psi-run]', { timeout: 15000 });
      const initialUrl = page.url();

      // Click nút kiểm tra PageSpeed
      await page.click('#laca-google-report [data-laca-psi-run]');

      // Trong 15s khối PSI có số liệu
      await page.waitForFunction(() => {
        const psi = document.getElementById('laca-google-psi-card') || document.querySelector('.laca-google-psi');
        if (!psi) return false;
        const text = psi.innerText || '';
        return text.includes('Nội dung lớn nhất') || text.includes('LCP') || text.includes('Lighthouse');
      }, { timeout: 15000 });

      const psiCheck = await page.evaluate(() => {
        const psi = document.getElementById('laca-google-psi-card') || document.querySelector('.laca-google-psi');
        const detailLink = psi ? psi.querySelector('.laca-google-psi__ext-link, a[href*="pagespeed.web.dev"]') : null;
        const text = psi ? (psi.innerText || '') : '';
        const hasMetrics = text.includes('LCP') || text.includes('Lighthouse');
        const href = detailLink ? detailLink.getAttribute('href') : '';
        return {
          hasPsi: !!psi,
          hasMetrics,
          extHref: href,
          startsWithPsi: href.startsWith('https://pagespeed.web.dev/'),
        };
      });

      const currentUrl = page.url();
      report(psiCheck.hasPsi && psiCheck.hasMetrics, 'PsiAjaxRunsAndRendersMetrics');
      report(psiCheck.startsWithPsi, 'PsiDetailLinkPointsToPageSpeedWebDev', '', psiCheck.extHref);
      report(currentUrl === initialUrl, 'PsiRunDoesNotNavigatePage');
      report(errors.length === 0, 'PsiRunNoPageErrors', '', errors.join('; '));

      const psiScreenshot = path.join(IMG_DIR, 'wp-dashboard-psi-run.png');
      await page.screenshot({ path: psiScreenshot, fullPage: false });

    } catch (err) {
      report(false, 'PsiAjaxRunTestFailed', '', err.message);
    }

    // 2. Mở index.php không mock (khi local chưa kết nối Google xong)
    errors = [];
    try {
      await page.setViewport({ width: 1440, height: 900 });
      await page.goto('http://lixroastery.local/wp-admin/index.php', {
        waitUntil: 'networkidle2',
        timeout: 45000,
      });

      // Theo quy chuẩn Site Kit / Plan §0.2: Chưa setup xong -> không hiển thị gì trên index.php
      const hasReport = await page.evaluate(() => {
        return document.getElementById('laca-google-report') !== null;
      });

      report(!hasReport, 'NonMockUnconfiguredHasNoReport', 1440);
      report(errors.length === 0, 'NonMockNoPageErrors', 1440, errors.join('; '));

      // Chụp ảnh non-mock
      const nonMockScreenshot = path.join(IMG_DIR, 'wp-dashboard-nonmock-1440.png');
      await page.screenshot({ path: nonMockScreenshot, fullPage: false });

    } catch (err) {
      report(false, 'NonMockReportTestFailed', 1440, err.message);
    }

    // 3. Mở admin.php?page=laca-seo&view=search-console không có critical error
    errors = [];
    try {
      await page.goto('http://lixroastery.local/wp-admin/admin.php?page=laca-seo&view=search-console', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
      });

      const scContent = await page.evaluate(() => {
        const text = document.body.innerText || '';
        const hasCritical = text.toLowerCase().includes('critical error') || text.toLowerCase().includes('lỗi nghiêm trọng');
        return { hasCritical };
      });

      report(!scContent.hasCritical, 'SearchConsoleNoCriticalError');
      report(errors.length === 0, 'SearchConsoleNoPageErrors', '', errors.join('; '));

      const scScreenshot = path.join(IMG_DIR, 'search-console-view.png');
      await page.screenshot({ path: scScreenshot, fullPage: false });

    } catch (err) {
      report(false, 'SearchConsoleViewTestFailed', '', err.message);
    }

  } finally {
    clearTimeout(watchdog);
    await browser.close();
  }

  console.log(`SUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail === 0 ? 0 : 1);
})();
