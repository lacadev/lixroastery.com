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

          // Funnel step 3 check
          const step3ValEl = el ? el.querySelector('.laca-google-funnel__step:nth-child(3) .laca-google-funnel__step-value') : null;
          const funnelStep3Val = step3ValEl ? step3ValEl.innerText.trim() : '';

          // Key metrics values check (6 default cards)
          const kmVals = el ? Array.from(el.querySelectorAll('.laca-google-km__value, .laca-google-keymetrics__value')).map(v => v.innerText.trim()) : [];
          const kmDashes = kmVals.filter(v => v === '—' || v === '-');

          return { hasGsc, hasGa, hasMockBanner, docOver, svgs, funnelStep3Val, kmVals, kmDashes };
        });

        report(contentCheck.hasGsc && contentCheck.hasGa, 'BothGoogleSectionsPresent', w);
        report(contentCheck.hasMockBanner, 'MockDataBannerPresent', w);
        report(contentCheck.docOver <= 0, 'NoHorizontalPageScroll', w, `docOver=${contentCheck.docOver}px`);
        report(contentCheck.svgs >= 2, 'ServerSvgChartsPresent', w, `svgCount=${contentCheck.svgs}`);
        if (w === 1440) {
          report(contentCheck.funnelStep3Val !== '0' && contentCheck.funnelStep3Val !== '', 'SearchFunnelStep3NotZero', w, `val=${contentCheck.funnelStep3Val}`);
          report(contentCheck.kmVals.length >= 6 && contentCheck.kmDashes.length === 0, 'KeyMetricsValuesNotDash', w, `count=${contentCheck.kmVals.length}, dashes=${contentCheck.kmDashes.length}`);
        }
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
        const alertText = alertEl ? (alertEl.innerText || '') : '';
        const hasRawLinkInText = alertText.includes('<a href') || alertText.includes('&lt;a');
        return {
          hasAlert: !!alertEl,
          retryBtnCount: retryBtns.length,
          hasKeyLink,
          hasRawLinkInText,
        };
      });

      report(psiErrorCheck.hasAlert, 'PsiErrorAlertRendersOnQuotaExceeded');
      report(psiErrorCheck.retryBtnCount === 1, 'PsiErrorReplacesCardBodyWithSingleButton', '', `btnCount=${psiErrorCheck.retryBtnCount}`);
      report(psiErrorCheck.hasKeyLink, 'PsiErrorAlertContainsLinkToPsiKey');
      report(!psiErrorCheck.hasRawLinkInText, 'PsiErrorDoesNotPrintRawHtmlAnchor');

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

    // 4. KIỂM TRA LOADING UX & SKELETON (PLAN U1, U2, U3)
    const LOADING_DIR = '/private/tmp/admin-ui/google-loading';
    fs.mkdirSync(LOADING_DIR, { recursive: true });

    // 4.1 U1: Tab Switch instant & Zero AJAX
    try {
      await page.setViewport({ width: 1440, height: 900 });
      await page.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
      });
      await page.waitForSelector('#laca-google-report .laca-google-toolbar', { timeout: 15000 });
      await page.waitForSelector('.laca-google-psi-tab[data-strategy="desktop"]', { timeout: 10000 });

      // Tab Máy tính có panel trong DOM ngay khi tải trang
      const panelsExist = await page.evaluate(() => {
        const desktopPanel = document.getElementById('laca-psi-panel-desktop');
        const mobilePanel = document.getElementById('laca-psi-panel-mobile');
        return {
          desktopInDom: !!desktopPanel,
          mobileInDom: !!mobilePanel,
          desktopInitiallyHidden: desktopPanel ? desktopPanel.hidden : false,
        };
      });
      report(panelsExist.desktopInDom && panelsExist.mobileInDom, 'U1_DesktopAndMobilePanelsRenderedInDom');

      // Bấm tab -> panel desktop hiện trong < 100 ms
      const t0 = performance.now();
      await page.click('.laca-google-psi-tab[data-strategy="desktop"]');
      await page.waitForFunction(() => {
        const p = document.getElementById('laca-psi-panel-desktop');
        return p && !p.hidden;
      }, { timeout: 1000 });
      const switchMs = performance.now() - t0;
      report(switchMs < 100, 'U1_PsiTabSwitchDurationUnder100ms', '', switchMs.toFixed(1) + 'ms');

      // Đổi qua lại 4 lần -> 0 request admin-ajax.php
      let tabAjaxCount = 0;
      const ajaxCountListener = (req) => {
        if (req.url().includes('admin-ajax.php') && req.method() === 'POST') {
          const postData = req.postData() || '';
          if (postData.includes('laca_pagespeed_run') || postData.includes('laca_google_report')) {
            tabAjaxCount++;
          }
        }
      };
      page.on('request', ajaxCountListener);
      await page.click('.laca-google-psi-tab[data-strategy="mobile"]');
      await page.waitForFunction(() => {
        const p = document.getElementById('laca-psi-panel-mobile');
        return p && !p.hidden;
      }, { timeout: 500 });
      await page.click('.laca-google-psi-tab[data-strategy="desktop"]');
      await page.waitForFunction(() => {
        const p = document.getElementById('laca-psi-panel-desktop');
        return p && !p.hidden;
      }, { timeout: 500 });
      await page.click('.laca-google-psi-tab[data-strategy="mobile"]');
      await page.waitForFunction(() => {
        const p = document.getElementById('laca-psi-panel-mobile');
        return p && !p.hidden;
      }, { timeout: 500 });
      await page.click('.laca-google-psi-tab[data-strategy="desktop"]');
      await page.waitForFunction(() => {
        const p = document.getElementById('laca-psi-panel-desktop');
        return p && !p.hidden;
      }, { timeout: 500 });
      page.off('request', ajaxCountListener);

      report(tabAjaxCount === 0, 'U1_PsiTabSwitchZeroAjaxRequests', '', `count=${tabAjaxCount}`);
    } catch (err) {
      report(false, 'U1_PsiTabSwitchTestFailed', '', err.message);
    }

    // 4.2 U2: Skeleton layout, contrast, and responsiveness
    try {
      const skeletonPage = await browser.newPage();
      await skeletonPage.setCookie(...Object.entries(cookies).map(([name, value]) => ({
        name,
        value,
        domain: 'lixroastery.local',
        path: '/',
      })));
      await skeletonPage.setRequestInterception(true);

      // Delay laca_google_report by 3 seconds to test skeleton
      skeletonPage.on('request', (r) => {
        const postData = r.postData() || '';
        if (r.url().includes('admin-ajax.php') && postData.includes('laca_google_report')) {
          setTimeout(() => {
            r.continue().catch(() => {});
          }, 3000);
          return;
        }
        r.continue().catch(() => {});
      });

      // 1440 viewport
      await skeletonPage.setViewport({ width: 1440, height: 900 });
      await skeletonPage.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
      });

      // Đợi skeleton xuất hiện
      await skeletonPage.waitForSelector('#laca-google-report-container [aria-busy="true"]', { timeout: 5000 });

      const skeleton1440Check = await skeletonPage.evaluate(() => {
        const lum = (c) => {
          const m = c.match(/[\d.]+/g); if (!m) return null;
          const [r, g, b, a = 1] = m.map(Number); if (a === 0) return null;
          const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
          return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
        };
        const ratio = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

        const container = document.getElementById('laca-google-report-container');
        const busyEl = container ? container.querySelector('[aria-busy="true"]') : null;
        const boxes = container ? Array.from(container.querySelectorAll('.laca-google-skeleton__box')) : [];
        const card = container ? container.querySelector('.laca-google-skeleton__card') : null;
        const cardLum = card ? lum(getComputedStyle(card).backgroundColor) : 1;

        let contrastPass = true;
        let lowestRatio = 999;
        for (const box of boxes) {
          const bLum = lum(getComputedStyle(box).backgroundColor);
          if (bLum !== null && cardLum !== null) {
            const r = ratio(bLum, cardLum);
            if (r < lowestRatio) lowestRatio = r;
            if (r < 1.15) {
              contrastPass = false;
            }
          }
        }

        const grid = container ? container.querySelector('.laca-google-grid') : null;
        const cols = grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 0;

        return {
          hasBusy: !!busyEl,
          boxCount: boxes.length,
          contrastPass,
          lowestRatio: lowestRatio === 999 ? 0 : lowestRatio,
          cols,
        };
      });

      report(skeleton1440Check.hasBusy, 'U2_SkeletonHasAriaBusy');
      report(skeleton1440Check.boxCount >= 20, 'U2_SkeletonBoxCountAtLeast20', '', `count=${skeleton1440Check.boxCount}`);
      report(skeleton1440Check.contrastPass, 'U2_SkeletonContrastAtLeast1_15', '', `lowestRatio=${skeleton1440Check.lowestRatio.toFixed(2)}`);
      report(skeleton1440Check.cols === 2, 'U2_SkeletonHas2ColumnsAt1440', '', `cols=${skeleton1440Check.cols}`);

      const skeleton1440Shot = path.join(LOADING_DIR, 'google-skeleton-1440.png');
      await skeletonPage.screenshot({ path: skeleton1440Shot, fullPage: false });

      // 390 viewport
      await skeletonPage.setViewport({ width: 390, height: 844 });
      const cols390 = await skeletonPage.evaluate(() => {
        const grid = document.querySelector('#laca-google-report-container .laca-google-grid');
        return grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 0;
      });
      report(cols390 === 1, 'U2_SkeletonHas1ColumnAt390', '', `cols=${cols390}`);

      const skeleton390Shot = path.join(LOADING_DIR, 'google-skeleton-390.png');
      await skeletonPage.screenshot({ path: skeleton390Shot, fullPage: false });

      await skeletonPage.close();
    } catch (err) {
      report(false, 'U2_SkeletonAuditFailed', '', err.message);
    }

    // 4.3 U3: Refresh updating state, dimmed content, progress bar, debounce, AbortController
    try {
      const refreshPage = await browser.newPage();
      await refreshPage.setCookie(...Object.entries(cookies).map(([name, value]) => ({
        name,
        value,
        domain: 'lixroastery.local',
        path: '/',
      })));
      await refreshPage.setRequestInterception(true);

      let refreshDelay = 0;
      let requestCount = 0;
      const abortedRequests = [];

      refreshPage.on('request', (r) => {
        const postData = r.postData() || '';
        if (r.url().includes('admin-ajax.php') && postData.includes('laca_google_report')) {
          requestCount++;
          if (refreshDelay > 0) {
            setTimeout(() => {
              r.continue().catch(() => {});
            }, refreshDelay);
            return;
          }
        }
        r.continue().catch(() => {});
      });

      refreshPage.on('requestfailed', (r) => {
        if (r.url().includes('admin-ajax.php')) {
          abortedRequests.push(r.failure() ? r.failure().errorText : 'failed');
        }
      });

      await refreshPage.setViewport({ width: 1440, height: 900 });
      await refreshPage.goto('http://lixroastery.local/wp-admin/index.php?laca_mock_google=1', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
      });
      await refreshPage.waitForSelector('#laca-google-report .laca-google-refresh-btn', { timeout: 15000 });

      // Bật delay 2000ms cho lần bấm Làm mới
      refreshDelay = 2000;
      requestCount = 0;

      // Click Làm mới
      await refreshPage.click('#laca-google-report .laca-google-refresh-btn');

      // Trong vòng 100ms: nút có disabled, aria-busy="true", text "Đang cập nhật...", nội dung cũ mờ đi, có progress bar
      await new Promise(r => setTimeout(r, 60));

      const updatingStateCheck = await refreshPage.evaluate(() => {
        const btn = document.querySelector('.laca-google-refresh-btn');
        const reportBody = document.querySelector('.laca-google-report-body');
        const progressBar = document.querySelector('.laca-google-progress-bar');
        const kpi = document.querySelector('.laca-google-kpi__value');

        return {
          btnDisabled: btn ? btn.disabled : false,
          btnBusy: btn ? btn.getAttribute('aria-busy') === 'true' : false,
          btnText: btn ? btn.innerText.trim() : '',
          hasProgressBar: !!progressBar,
          hasDimmed: reportBody ? reportBody.classList.contains('laca-google-report--dimmed') : false,
          kpiStillInDom: !!kpi,
        };
      });

      report(updatingStateCheck.btnDisabled && updatingStateCheck.btnBusy, 'U3_RefreshButtonDisabledAndAriaBusy');
      report(updatingStateCheck.btnText.includes('Đang cập nhật'), 'U3_RefreshButtonTextUpdating', '', updatingStateCheck.btnText);
      report(updatingStateCheck.hasDimmed && updatingStateCheck.kpiStillInDom, 'U3_OldContentDimmedAndPreserved');
      report(updatingStateCheck.hasProgressBar, 'U3_ProgressBarPresent');

      // Chụp ảnh trạng thái đang cập nhật
      const refreshShot = path.join(LOADING_DIR, 'google-refresh-loading-1440.png');
      await refreshPage.screenshot({ path: refreshShot, fullPage: false });

      // Thử bấm Làm mới liên tiếp 2 lần nữa -> debounce: requestCount vẫn chỉ là 1
      await refreshPage.click('#laca-google-refresh-btn, .laca-google-refresh-btn').catch(() => {});
      await refreshPage.click('#laca-google-refresh-btn, .laca-google-refresh-btn').catch(() => {});
      report(requestCount === 1, 'U3_DebounceMultipleClicksSingleRequest', '', `requestCount=${requestCount}`);

      // Đợi hoàn thành cập nhật (hết 2s delay + render)
      await refreshPage.waitForFunction(() => {
        const btn = document.querySelector('.laca-google-refresh-btn');
        return btn && !btn.disabled && btn.innerText.includes('Làm mới');
      }, { timeout: 10000 });

      // Kiểm tra có dòng "Cập nhật lúc"
      const updateTimeText = await refreshPage.evaluate(() => {
        const el = document.querySelector('.laca-google-toolbar__updated');
        return el ? el.innerText : '';
      });
      report(updateTimeText.includes('Cập nhật lúc'), 'U3_HasUpdatedTimestamp', '', updateTimeText);

      // Rapid range switch test (7 -> 90 quickly -> request 7 aborted)
      refreshDelay = 1500;
      abortedRequests.length = 0;
      await refreshPage.click('.laca-google-range-btn[data-range="7d"]');
      await new Promise(r => setTimeout(r, 80));
      await refreshPage.click('.laca-google-range-btn[data-range="90d"]');

      // Đợi 90d xong
      await refreshPage.waitForFunction(() => {
        const btn90 = document.querySelector('.laca-google-range-btn[data-range="90d"]');
        const refreshBtn = document.querySelector('.laca-google-refresh-btn');
        return btn90 && btn90.classList.contains('is-active') && refreshBtn && !refreshBtn.disabled;
      }, { timeout: 10000 });

      const hasAborted = abortedRequests.some(e => e.includes('abort') || e.includes('ABORTED'));
      report(hasAborted, 'U3_RapidRangeSwitchAbortsOldRequest', '', abortedRequests.join('; '));

      await refreshPage.close();
    } catch (err) {
      report(false, 'U3_RefreshUpdatingTestFailed', '', err.message);
    }

  } finally {
    clearTimeout(watchdog);
    await browser.close();
  }

  console.log(`SUMMARY pass=${pass} fail=${fail}`);
  process.exit(fail === 0 ? 0 : 1);
})();
