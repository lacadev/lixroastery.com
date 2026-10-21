const puppeteer = require('/Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/node_modules/puppeteer');
const { execFileSync } = require('child_process');

const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';

// Generate cookie in runtime memory without writing to file
const code = `define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);`;
const ck = JSON.parse(execFileSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: '/Users/lacadev/Documents/wordpress/lixroastery' }).toString());

(async () => {
  const browser = await puppeteer.launch({
    headless: 'new',
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  
  // Set auth cookies
  await page.setCookie(...Object.entries(ck).map(([name, value]) => ({
    name,
    value,
    domain: 'lixroastery.local',
    path: '/'
  })));

  // ABORT ALL POST REQUESTS (including /wp-json/ and admin-ajax)
  await page.setRequestInterception(true);
  page.on('request', req => {
    if (req.method() === 'POST') {
      req.abort();
    } else {
      req.continue();
    }
  });

  const screens = [
    {
      name: 'redirections',
      url: 'http://lixroastery.local/wp-admin/admin.php?page=laca-seo&view=redirections',
    },
    {
      name: '404-monitor',
      url: 'http://lixroastery.local/wp-admin/admin.php?page=laca-seo&view=404-monitor',
    }
  ];

  const viewports = [
    { width: 1440, height: 900, label: '1440' },
    { width: 390, height: 844, label: '390', isMobile: true }
  ];

  const results = [];

  for (const screen of screens) {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    for (const vp of viewports) {
      await page.setViewport({
        width: vp.width,
        height: vp.height,
        isMobile: !!vp.isMobile,
        hasTouch: !!vp.isMobile
      });

      await page.goto(screen.url, { waitUntil: 'networkidle2', timeout: 60000 });

      // Check overflowX
      const overflowX = await page.evaluate(() => {
        return Math.max(0, document.documentElement.scrollWidth - window.innerWidth);
      });

      const outPath = `/private/tmp/laca-p4/${screen.name}-${vp.label}.png`;
      await page.screenshot({ path: outPath, fullPage: true });

      results.push({
        screen: screen.name,
        viewport: vp.label,
        overflowX,
        consoleErrors: consoleErrors.slice()
      });
    }
  }

  await browser.close();

  // Print results
  for (const r of results) {
    console.log(`[Puppeteer] ${r.screen} @ ${r.viewport}px: overflowX=${r.overflowX}, console errors=[${r.consoleErrors.join(', ')}]`);
  }
})();
