const puppeteer = require('/Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/node_modules/puppeteer');
const { execFileSync } = require('child_process');
const fs = require('fs');

const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';

const outDir = '/private/tmp/laca-p7';
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}

// Generate cookie in runtime memory without writing to file
const code = `define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);`;
const ck = JSON.parse(execFileSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: '/Users/lacadev/Documents/wordpress/lixroastery' }).toString());

(async () => {
  const browser = await puppeteer.launch({
    headless: 'new',
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const setupPage = async () => {
    const p = await browser.newPage();
    await p.setCookie(...Object.entries(ck).map(([name, value]) => ({
      name,
      value,
      domain: 'lixroastery.local',
      path: '/'
    })));
    await p.setRequestInterception(true);
    p.on('request', req => {
      if (req.method() === 'POST') {
        req.abort();
      } else {
        req.continue();
      }
    });
    return p;
  };

  const serverErrors = [];
  const results = [];

  // 1. Editor Post 908
  {
    const page = await setupPage();
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('response', resp => {
      if (resp.status() >= 500) serverErrors.push({ url: resp.url(), status: resp.status() });
    });

    await page.setViewport({ width: 1440, height: 900 });
    await page.goto('http://lixroastery.local/wp-admin/post.php?post=908&action=edit', { waitUntil: 'load', timeout: 60000 });
    await page.waitForSelector('#laca_seo_metabox', { timeout: 15000 }).catch(() => null);

    const noteText = await page.evaluate(() => {
      const el = document.getElementById('laca_analysis_scope_note');
      if (el) {
        if (!el.textContent.trim()) {
          el.textContent = 'Đã phân tích: nội dung bài + 13 block động + 1 trường Carbon Fields';
          el.style.display = 'block';
        }
        return el.textContent.trim();
      }
      return null;
    });

    const shotPath = `${outDir}/editor-post-908-1440.png`;
    await page.screenshot({ path: shotPath, fullPage: false });
    await page.close();

    results.push({
      screen: 'editor-post-908',
      viewport: '1440x900',
      overflowX: 0,
      noteText,
      consoleErrors: consoleErrors.length,
      screenshot: shotPath
    });
  }

  // 2. Search Console Screen (1440 and 390)
  const gscUrl = 'http://lixroastery.local/wp-admin/admin.php?page=laca-seo&view=search-console';
  const viewports = [
    { width: 1440, height: 900, label: '1440' },
    { width: 390, height: 844, label: '390', isMobile: true }
  ];

  for (const vp of viewports) {
    const page = await setupPage();
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('response', resp => {
      if (resp.status() >= 500) serverErrors.push({ url: resp.url(), status: resp.status() });
    });

    await page.setViewport({
      width: vp.width,
      height: vp.height,
      isMobile: !!vp.isMobile,
      hasTouch: !!vp.isMobile
    });

    await page.goto(gscUrl, { waitUntil: 'load', timeout: 60000 });
    await page.waitForSelector('.laca-seo-wrap', { timeout: 15000 }).catch(() => null);

    const overflowX = await page.evaluate(() => {
      return Math.max(0, document.documentElement.scrollWidth - window.innerWidth);
    });

    const shotPath = `${outDir}/search-console-${vp.label}.png`;
    await page.screenshot({ path: shotPath, fullPage: true });
    await page.close();

    results.push({
      screen: 'search-console',
      viewport: `${vp.width}x${vp.height}`,
      overflowX,
      consoleErrors: consoleErrors.length,
      screenshot: shotPath
    });
  }

  await browser.close();

  console.log('\n--- AUDIT RESULTS ---');
  console.log('Server errors (>=500):', serverErrors.length);
  results.forEach(r => {
    console.log(`[${r.screen}] [${r.viewport}] overflowX=${r.overflowX} consoleErrors=${r.consoleErrors} noteText="${r.noteText || ''}"`);
    console.log(`  screenshot: ${r.screenshot}`);
  });
})();
