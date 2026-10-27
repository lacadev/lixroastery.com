/**
 * Nghiệm thu báo cáo Google Services (GSC + GA4) trên WordPress Dashboard (index.php) - V2.
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
 *   + #laca-google-report svg[role="img"] >= 2
 *   + Kiểm tra đơn sắc: RGB -> HSL bão hoà S <= 10%
 *   + Dialog 100 từ khoá: click "Xem tất cả" -> dialog[open], >= 10 dòng, Esc đóng dialog, focus quay lại nút
 *   + PSI tab desktop: click "Máy tính" -> aria-pressed="true", footer có "Máy tính"
 *   + PSI hộp lỗi: mock laca_mock_psi_error=1 -> có duy nhất 1 nút data-laca-psi-run
 *   + Chụp ảnh lưu vào /private/tmp/admin-ui/google-v2/ (1440, 1024, 390, dialog, psi)
 * - index.php (không mock, local chưa kết nối):
 *   + Chưa kết nối -> không hiển thị gì trên index.php
 * - admin.php?page=laca-seo&view=search-console:
 *   + Không có chữ "critical error"
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
const IMG_DIR = '/private/tmp/admin-ui/google-v2';

fs.mkdirSync(IMG_DIR, { recursive: true });

const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);";
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

    // Abort slow external tracking pixels that block navigation
    await page.setRequestInterception(true);
    page.on('request', (r) => {
      const u = r.url();
      if (u.includes('pixel.wp.com') || u.includes('stats.wp.com')) {
        return r.abort();
      }
      r.continue();
    });

    // 1. Kiểm tra index.php?laca_mock_google=1 ở 1440, 1024, 390
    for (const w of WIDTHS) {
      errors = [];
      try {
        await page.setViewport({ width: w, height: 900 });
        await page.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1', {
          waitUntil: 'domcontentloaded',
          timeout: 30000,
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
        await page.waitForSelector('#laca-google-report .laca-google-toolbar', { timeout: 15000 });

        // Kiểm tra nội dung chi tiết
        const contentCheck = await page.evaluate(() => {
          const el = document.getElementById('laca-google-report');
          const text = el ? (el.innerText || '') : '';
          const hasGsc = text.includes('Google Search Console');
          const hasGa = text.includes('Google Analytics 4');
          const hasMockBanner = text.includes('DỮ LIỆU MẪU');
          const docOver = document.documentElement.scrollWidth - window.innerWidth;
          const svgs = el ? el.querySelectorAll('svg[role="img"]').length : 0;
          return { hasGsc, hasGa, hasMockBanner, docOver, svgs };
        });

        report(contentCheck.hasGsc && contentCheck.hasGa, 'BothGoogleSectionsPresent', w);
        report(contentCheck.hasMockBanner, 'MockDataBannerPresent', w);
        report(contentCheck.docOver <= 0, 'NoHorizontalPageScroll', w, `docOver=${contentCheck.docOver}px`);
        report(contentCheck.svgs >= 2, 'ServerSvgChartsPresent', w, `svgCount=${contentCheck.svgs}`);
        report(errors.length === 0, 'NoPageErrors', w, errors.join('; '));

        // Chụp ảnh lưu file
        const screenshotPath = path.join(IMG_DIR, `wp-dashboard-mock-${w}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: false });

      } catch (err) {
        report(false, 'MockGoogleReportTestFailed', w, err.message);
      }
    }

    // 1.1 Kiểm tra Đơn sắc (Monochromatic) trên 1440: S <= 10%
    try {
      await page.setViewport({ width: 1440, height: 900 });
      const monoCheck = await page.evaluate(() => {
        function parseRgb(colorStr) {
          if (!colorStr) return null;
          const m = colorStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
          if (!m) return null;
          const a = m[4] !== undefined ? parseFloat(m[4]) : 1;
          if (a <= 0.05) return null; // transparent
          const r = parseInt(m[1], 10) / 255;
          const g = parseInt(m[2], 10) / 255;
          const b = parseInt(m[3], 10) / 255;
          const max = Math.max(r, g, b), min = Math.min(r, g, b);
          let s = 0, l = (max + min) / 2;
          if (max !== min) {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
          }
          return { r: m[1], g: m[2], b: m[3], s: Math.round(s * 100), raw: colorStr };
        }

        const root = document.getElementById('laca-google-report');
        if (!root) return { ok: false, violations: ['root missing'] };

        const all = root.querySelectorAll('*');
        const violations = [];
        all.forEach((node) => {
          // Skip external images, SVG elements, or hidden elements
          if (node.tagName === 'IMG' || node.tagName === 'svg' || node.tagName === 'path' || node.tagName === 'line' || node.tagName === 'circle') return;
          const cs = window.getComputedStyle(node);
          if (cs.display === 'none' || cs.visibility === 'hidden') return;
          const colorParsed = parseRgb(cs.color);
          if (colorParsed && colorParsed.s > 10) {
            violations.push(`${node.tagName}.${node.className} color=${colorParsed.raw} (S=${colorParsed.s}%)`);
          }
          const bgParsed = parseRgb(cs.backgroundColor);
          if (bgParsed && bgParsed.s > 10) {
            violations.push(`${node.tagName}.${node.className} bg=${bgParsed.raw} (S=${bgParsed.s}%)`);
          }
          if (cs.borderWidth && parseFloat(cs.borderWidth) > 0) {
            const bcParsed = parseRgb(cs.borderColor);
            if (bcParsed && bcParsed.s > 10) {
              violations.push(`${node.tagName}.${node.className} border=${bcParsed.raw} (S=${bcParsed.s}%)`);
            }
          }
        });

        return {
          ok: violations.length === 0,
          violations: violations.slice(0, 5),
          totalChecked: all.length,
        };
      });

      report(monoCheck.ok, 'MonochromaticDesignAudit', 1440, monoCheck.ok ? `checked=${monoCheck.totalChecked} elements` : `violations: ${monoCheck.violations.join('; ')}`);
    } catch (err) {
      report(false, 'MonochromaticDesignAuditFailed', 1440, err.message);
    }

    // 1.2 Kiểm tra Dialog 100 từ khoá (GSC modal): click "Xem tất cả" -> dialog[open], >= 10 dòng, Esc đóng
    try {
      await page.waitForSelector('[data-laca-gsc-all]', { timeout: 5000 });
      const openBtn = await page.$('[data-laca-gsc-all]');
      await openBtn.click();

      // Đợi dialog mở và có dữ liệu AJAX
      await page.waitForSelector('#laca-gsc-dialog[open]', { timeout: 5000 });
      await page.waitForFunction(() => {
        const rows = document.querySelectorAll('#laca-gsc-dialog tbody tr');
        return rows.length >= 10;
      }, { timeout: 10000 });

      const dialogCheck = await page.evaluate(() => {
        const dlg = document.getElementById('laca-gsc-dialog');
        const rows = dlg ? dlg.querySelectorAll('tbody tr').length : 0;
        const isOpen = dlg ? dlg.hasAttribute('open') : false;
        return { isOpen, rows };
      });

      report(dialogCheck.isOpen && dialogCheck.rows >= 10, 'GscQueriesDialogOpensWith100Rows', '', `rows=${dialogCheck.rows}`);

      const dialogScreenshot = path.join(IMG_DIR, 'wp-dashboard-gsc-dialog.png');
      await page.screenshot({ path: dialogScreenshot, fullPage: false });

      // Nhấn phím Escape để đóng dialog
      await page.keyboard.press('Escape');

      // Chờ dialog đóng
      await page.waitForFunction(() => {
        const dlg = document.getElementById('laca-gsc-dialog');
        return dlg && !dlg.hasAttribute('open');
      }, { timeout: 3000 });

      const closedCheck = await page.evaluate(() => {
        const dlg = document.getElementById('laca-gsc-dialog');
        const active = document.activeElement;
        const isClosed = dlg ? !dlg.hasAttribute('open') : true;
        const focusReturned = active && active.hasAttribute('data-laca-gsc-all');
        return { isClosed, focusReturned };
      });

      report(closedCheck.isClosed, 'GscQueriesDialogClosesOnEsc');
      report(closedCheck.focusReturned, 'GscQueriesDialogReturnsFocusToButton');

    } catch (err) {
      report(false, 'GscQueriesDialogTestFailed', '', err.message);
    }

    // 1.3 Kiểm tra Tab PSI: click "Máy tính" -> footer đổi thành có "Máy tính"
    try {
      await page.waitForSelector('.laca-google-psi-tab[data-strategy="desktop"]', { timeout: 10000 });
      await page.click('.laca-google-psi-tab[data-strategy="desktop"]');

      await page.waitForFunction(() => {
        const tab = document.querySelector('.laca-google-psi-tab[data-strategy="desktop"]');
        const footer = document.querySelector('.laca-google-psi__strategy-label') || document.querySelector('.laca-google-psi__footer');
        const isPressed = tab && tab.getAttribute('aria-pressed') === 'true';
        const hasDesktopText = footer && footer.innerText.includes('Máy tính');
        return isPressed && hasDesktopText;
      }, { timeout: 10000 });

      const psiDesktopCheck = await page.evaluate(() => {
        const tab = document.querySelector('.laca-google-psi-tab[data-strategy="desktop"]');
        const footer = document.querySelector('.laca-google-psi__strategy-label') || document.querySelector('.laca-google-psi__footer');
        return {
          isPressed: tab ? tab.getAttribute('aria-pressed') === 'true' : false,
          footerText: footer ? footer.innerText : '',
        };
      });

      report(psiDesktopCheck.isPressed && psiDesktopCheck.footerText.includes('Máy tính'), 'PsiDesktopTabSwitchUpdatesFooter', '', psiDesktopCheck.footerText);

      // Chuyển lại mobile
      await page.click('.laca-google-psi-tab[data-strategy="mobile"]');

    } catch (err) {
      report(false, 'PsiDesktopTabSwitchTestFailed', '', err.message);
    }

    // 1.4 Kiểm tra Hộp lỗi PSI (mock laca_mock_psi_error=1): có duy nhất 1 nút data-laca-psi-run
    try {
      await page.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1&laca_mock_psi_error=1', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
      });

      await page.waitForSelector('#laca-google-report [data-laca-psi-run]', { timeout: 10000 });
      await page.click('#laca-google-report [data-laca-psi-run]');

      // Đợi alert lỗi xuất hiện
      await page.waitForSelector('.laca-google-psi .laca-alert--error', { timeout: 10000 });

      const psiErrorCheck = await page.evaluate(() => {
        const card = document.getElementById('laca-google-psi-card') || document.querySelector('.laca-google-psi');
        const alertEl = card ? card.querySelector('.laca-alert--error') : null;
        const retryBtns = card ? card.querySelectorAll('[data-laca-psi-run]') : [];
        const hasKeyLink = alertEl ? !!alertEl.querySelector('a[href*="#laca-psi-key"]') : false;
        return {
          hasAlert: !!alertEl,
          retryBtnCount: retryBtns.length,
          hasKeyLink,
        };
      });

      report(psiErrorCheck.hasAlert, 'PsiErrorAlertRendersOnQuotaExceeded');
      report(psiErrorCheck.retryBtnCount === 1, 'PsiErrorReplacesCardBodyWithSingleButton', '', `btnCount=${psiErrorCheck.retryBtnCount}`);
      report(psiErrorCheck.hasKeyLink, 'PsiErrorAlertContainsLinkToPsiKey');

      const psiErrorScreenshot = path.join(IMG_DIR, 'wp-dashboard-psi-error.png');
      await page.screenshot({ path: psiErrorScreenshot, fullPage: false });

    } catch (err) {
      report(false, 'PsiErrorSimulationTestFailed', '', err.message);
    }

    // 2. Mở index.php không mock (khi local chưa kết nối Google xong)
    errors = [];
    try {
      await page.setViewport({ width: 1440, height: 900 });
      await page.goto('http://lixroastery.local/wp-admin/index.php', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
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
