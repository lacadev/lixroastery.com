/**
 * Công cụ nghiệm thu toàn diện giao diện Admin theo ngôn ngữ Chakra UI v3.
 * Không ghi DB: abort MỌI request POST, cookie sinh lúc chạy, không lưu file cookie.
 * 
 * Chạy: node app/public/wp-content/themes/lacadev-client/doc/admin-ui/tools/admin-ui-audit.js [thư-mục-ảnh]
 */
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

const THEMES = path.resolve(__dirname, '../../../..');
const puppeteer = require(path.join(THEMES, 'lacadev-client-child/node_modules/puppeteer'));
const ROOT = path.resolve(THEMES, '../../../..');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const LOGIN = process.env.LOGIN || 'lacadev';
const OUT = process.argv[2] || '';
const WP_ADMIN = 'http://lixroastery.local/wp-admin/';
const WP_LOGIN = 'http://lixroastery.local/wp-login.php';

const WIDTHS = [1440, 1024, 390];

const URLS = [
  { id: 'dashboard', path: 'index.php' },
  { id: 'journal', path: 'edit.php?post_type=journal' },
  { id: 'pages', path: 'edit.php?post_type=page' },
  { id: 'gutenberg', path: 'post.php?post=101&action=edit', isEditor: true },
  { id: 'users', path: 'users.php' },
  { id: 'options', path: 'options-general.php' },
  { id: 'laca-admin', path: 'admin.php?page=laca-admin' },
  { id: 'laca-theme', path: 'admin.php?page=app-theme-options.php' },
  { id: 'laca-ui-kit', path: 'admin.php?page=laca-ui-kit' },
  // Dock Laca Admin pages
  { id: 'dock-security', path: 'admin.php?page=laca-security' },
  { id: 'dock-email-log', path: 'admin.php?page=laca-email-log' },
  { id: 'dock-contact-forms', path: 'admin.php?page=laca-contact-forms' },
  { id: 'dock-dynamic-cpt', path: 'admin.php?page=laca-dynamic-cpt' },
  { id: 'dock-discord-hub', path: 'admin.php?page=laca-discord-hub' },
  { id: 'dock-tools', path: 'admin.php?page=laca-tools' },
  { id: 'dock-db-cleaner', path: 'admin.php?page=laca-db-cleaner' },
  // Laca SEO views
  { id: 'seo-dashboard', path: 'admin.php?page=laca-seo&view=dashboard' },
  { id: 'seo-general', path: 'admin.php?page=laca-seo&view=general' },
  { id: 'seo-titles', path: 'admin.php?page=laca-seo&view=titles' },
  { id: 'seo-sitemap', path: 'admin.php?page=laca-seo&view=sitemap' },
  { id: 'seo-instant-indexing', path: 'admin.php?page=laca-seo&view=instant-indexing' },
  { id: 'seo-redirections', path: 'admin.php?page=laca-seo&view=redirections' },
  { id: 'seo-404-monitor', path: 'admin.php?page=laca-seo&view=404-monitor' },
  { id: 'seo-search-console', path: 'admin.php?page=laca-seo&view=search-console' },
  { id: 'seo-import-export', path: 'admin.php?page=laca-seo&view=import-export' },
  { id: 'seo-guide', path: 'admin.php?page=laca-seo&view=guide' },
];

if (OUT && !fs.existsSync(OUT)) {
  fs.mkdirSync(OUT, { recursive: true });
}

const code = `define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login',${JSON.stringify(LOGIN)});if(!$u){exit(1);}$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);exit(0);`;
const cookiesRaw = execFileSync(PHP, ['-d', 'memory_limit=512M', '-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: ROOT }).toString();
const cookies = JSON.parse(cookiesRaw);

let baselineMenu = {};
try {
  baselineMenu = JSON.parse(fs.readFileSync(path.join(__dirname, '../menu-before.json'), 'utf8'));
} catch (_) {}

let pass = 0, fail = 0;
const report = (ok, check, target, w, detail = '') => {
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check} ${target} ${w}${detail ? ' ' + detail : ''}`);
};

(async () => {
  let browser;
  // 3. Watchdog toàn cục 15 phút
  const watchdog = setTimeout(async () => {
    console.error('FAIL watchdog: quá 15 phút');
    if (browser) {
      try { await browser.close(); } catch (_) {}
    }
    process.exit(2);
  }, 15 * 60 * 1000);

  try {
    browser = await puppeteer.launch({
      headless: 'new',
      executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const setupPage = async () => {
      const p = await browser.newPage();
      await p.setRequestInterception(true);
      p.on('request', (r) => {
        if (r.method() === 'POST') {
          r.respond({ status: 200, contentType: 'application/json', body: '{}' });
        } else {
          r.continue();
        }
      });
      return p;
    };

    let page = await setupPage();

    // 1. Kiểm tra wp-login.php (đã đăng xuất)
    for (const w of WIDTHS) {
      let errors = [];
      const onErr = (e) => errors.push(e.message);
      page.on('pageerror', onErr);

      try {
        await page.setViewport({ width: w, height: 900 });
        const client = await page.target().createCDPSession();
        await client.send('Network.clearBrowserCookies');
        
        await page.goto(WP_LOGIN, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('#loginform, #login', { timeout: 30000 });

        const loginRes = await page.evaluate(() => {
          const body = document.body;
          const computedFont = getComputedStyle(body).fontFamily;
          const fontOk = computedFont.startsWith('-apple-system') || computedFont.includes('-apple-system');
          const accent = getComputedStyle(document.documentElement).getPropertyValue('--laca-accent').trim();
          
          const primaryBtn = document.querySelector('#wp-submit, .button-primary, .laca-btn--solid');
          let btnOk = true;
          if (primaryBtn) {
            const bg = getComputedStyle(primaryBtn).backgroundColor;
            const color = getComputedStyle(primaryBtn).color;
            btnOk = (bg === 'rgb(24, 24, 27)') && (color === 'rgb(255, 255, 255)' || color === 'rgb(250, 250, 250)');
          }

          const isBlue = (rgbStr) => {
            if (!rgbStr || rgbStr === 'transparent') return false;
            const m = rgbStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (!m) return false;
            const r = +m[1], b = +m[3];
            return (b - r > 60 && b > 150);
          };
          let noBlueOk = true;
          let noBlueDetail = '';
          const loginEls = Array.from(document.querySelectorAll('#loginform *, #login *')).filter(el => el.offsetParent !== null);
          for (const el of loginEls) {
            const cs = getComputedStyle(el);
            if (isBlue(cs.color) || isBlue(cs.backgroundColor) || isBlue(cs.borderTopColor) || isBlue(cs.borderRightColor) || isBlue(cs.borderBottomColor) || isBlue(cs.borderLeftColor)) {
              noBlueOk = false;
              noBlueDetail = `el=${el.tagName} c=${cs.color} bg=${cs.backgroundColor}`;
              break;
            }
          }

          const over = document.documentElement.scrollWidth - innerWidth;

          return { fontOk, font: computedFont, accent, btnOk, over, noBlueOk, noBlueDetail };
        });

        report(errors.length === 0, 'no-js-error', 'wp-login.php', w, errors[0] || '');
        report(loginRes.over <= 0, 'no-overflow', 'wp-login.php', w, loginRes.over > 0 ? `over=${loginRes.over}` : '');
        report(loginRes.accent === '#18181b', 'tokens', 'wp-login.php', w, `accent=${loginRes.accent}`);
        report(loginRes.fontOk, 'font', 'wp-login.php', w, loginRes.font.slice(0, 30));
        report(loginRes.btnOk, 'button-primary', 'wp-login.php', w);
        report(loginRes.noBlueOk, 'no-blue', 'wp-login.php', w, loginRes.noBlueDetail);

        if (OUT && (w === 1440 || w === 390)) {
          await page.screenshot({ path: path.join(OUT, `login-${w}.png`), fullPage: false });
        }
      } catch (err) {
        report(false, 'timeout', 'wp-login.php', w, err.message);
      } finally {
        page.off('pageerror', onErr);
      }
    }

    // Set cookies for authenticated tests
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));

    // 2. Chạy kiểm tra các trang admin
    for (const item of URLS) {
      const fullUrl = WP_ADMIN + item.path;
      for (const w of WIDTHS) {
        if (item.isEditor && w !== 1440) {
          continue; // Gutenberg Inspector chỉ mở trên desktop
        }

        let errors = [];
        const onErr = (e) => errors.push(e.message);
        page.on('pageerror', onErr);

        try {
          await page.setViewport({ width: w, height: 900 });

          // 1. page.goto với domcontentloaded & timeout 60s
          await page.goto(fullUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });

          // Chờ selector cụ thể tối đa 30s
          if (item.isEditor) {
            await page.waitForSelector('.block-editor-block-list__layout, #editor, .edit-post-layout', { timeout: 30000 });
          } else {
            await page.waitForSelector('#wpbody-content, #wpwrap', { timeout: 30000 });
          }

          // 4. Inspector Gutenberg
          let inspectorFound = true;
          if (item.isEditor) {
            const selectOk = await page.evaluate(async () => {
              if (window.wp && wp.data && wp.data.select && wp.data.dispatch) {
                let attempts = 0;
                while (attempts < 20) {
                  const blocks = wp.data.select('core/block-editor')?.getBlocks() || [];
                  const cta = blocks.find(b => b.name === 'lacadev/cta-section-block');
                  if (cta) {
                    wp.data.dispatch('core/block-editor').selectBlock(cta.clientId);
                    if (wp.data.dispatch('core/edit-post')?.openGeneralSidebar) {
                      wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
                    }
                    return true;
                  }
                  attempts++;
                  await new Promise(r => setTimeout(r, 200));
                }
              }
              return false;
            });

            if (selectOk) {
              try {
                await page.waitForSelector('.block-editor-block-inspector', { timeout: 20000 });
              } catch (_) {
                inspectorFound = false;
              }
            } else {
              inspectorFound = false;
            }
            report(inspectorFound, 'inspector', item.id, w, inspectorFound ? '' : 'không thấy .block-editor-block-inspector');
          }

          const res = await page.evaluate((isEditor, isLacaAdmin) => {
            const doc = document.documentElement;
            const body = document.body;

            // 1. no-overflow
            let wideElements = [];
            const isWide = (el) => {
              const r = el.getBoundingClientRect();
              if (!r.width || r.right <= window.innerWidth + 1) return false;
              for (let p = el.parentElement; p && p !== body; p = p.parentElement) {
                const ox = getComputedStyle(p).overflowX;
                if (ox === 'auto' || ox === 'scroll' || ox === 'hidden') return false;
              }
              return true;
            };

            const all = [...document.querySelectorAll('body *')];
            for (const el of all) {
              if (isWide(el)) {
                wideElements.push(el.tagName.toLowerCase() + '.' + [...el.classList].slice(0, 2).join('.'));
                if (wideElements.length >= 3) break;
              }
            }
            const over = doc.scrollWidth - window.innerWidth;
            const noOverflow = over <= 0 && wideElements.length === 0;

            // 2. tokens
            const accent = getComputedStyle(doc).getPropertyValue('--laca-accent').trim();

            // 3. font
            const bodyFont = getComputedStyle(body).fontFamily;
            const fontOk = bodyFont.startsWith('-apple-system') || bodyFont.includes('-apple-system');

            // 4. button-primary
            const buttons = [...document.querySelectorAll('.button-primary, .laca-btn--solid')]
              .filter(b => b.offsetParent !== null && !b.closest('.block-editor-writing-flow'));
            let btnOk = true;
            let btnDetail = '';
            for (const btn of buttons) {
              const cs = getComputedStyle(btn);
              const bg = cs.backgroundColor;
              const color = cs.color;
              if (bg !== 'rgb(24, 24, 27)' || (color !== 'rgb(255, 255, 255)' && color !== 'rgb(250, 250, 250)')) {
                btnOk = false;
                btnDetail = `bg=${bg} color=${color}`;
                break;
              }
            }

            const isVisuallyHidden = (el) => {
              const r = el.getBoundingClientRect();
              if (r.width <= 1 || r.height <= 1) return true;
              const cs = getComputedStyle(el);
              if (cs.clip === 'rect(1px, 1px, 1px, 1px)' || cs.clip === 'rect(0px, 0px, 0px, 0px)') return true;
              if (cs.position === 'absolute' && (r.left < -900 || r.top < -900)) return true;
              if (el.closest('.screen-reader-text, .screen-reader-shortcut, .hidden, [aria-hidden="true"], [class*="kksr"], [id*="kksr"], [class*="kk-star"], [id*="kk-star"]')) return true;
              return false;
            };

            // no-blue check: không phần tử hiển thị nào (ngoài canvas editor/iframe) có color, background-color, border-color với kênh B − R > 60 và B > 150
            const isBlue = (rgbStr) => {
              if (!rgbStr || rgbStr === 'transparent') return false;
              const m = rgbStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
              if (!m) return false;
              const r = +m[1], b = +m[3];
              return (b - r > 60 && b > 150);
            };
            let noBlueOk = true;
            let noBlueDetail = '';
            const testEls = [...document.querySelectorAll('#wpwrap *')]
              .filter(el => el.offsetParent !== null && !isVisuallyHidden(el) && !el.closest('.block-editor-writing-flow, iframe, canvas, svg'));
            for (const el of testEls) {
              const cs = getComputedStyle(el);
              if (isBlue(cs.color) || isBlue(cs.backgroundColor) || isBlue(cs.borderTopColor) || isBlue(cs.borderRightColor) || isBlue(cs.borderBottomColor) || isBlue(cs.borderLeftColor)) {
                noBlueOk = false;
                noBlueDetail = `el=${el.tagName} cls=${el.className} c=${cs.color} bg=${cs.backgroundColor}`;
                break;
              }
            }

            // menu-one-line: mọi #adminmenu .wp-menu-name cao <= 36px (1 dòng) ở 1440 và 1024
            let menuOneLineOk = true;
            let menuOneLineDetail = '';
            if (window.innerWidth >= 1024) {
              const menuNames = [...document.querySelectorAll('#adminmenu > li .wp-menu-name')];
              for (const mn of menuNames) {
                const h = mn.getBoundingClientRect().height;
                if (h > 36) {
                  menuOneLineOk = false;
                  menuOneLineDetail = `text="${mn.innerText.trim()}" h=${h}`;
                  break;
                }
              }
            }

            // 5. input-style
            const inputs = [...document.querySelectorAll('input[type="text"], input[type="search"], input[type="email"], input[type="password"], input[type="number"], select')]
              .filter(i => {
                if (i.offsetParent === null) return false;
                if (i.closest('.block-editor-writing-flow')) return false;
                if (i.closest('#adminmenu, #wpadminbar, .screen-reader-text')) return false;
                const h = Math.round(i.getBoundingClientRect().height);
                return h > 0;
              });
            let inputOk = true;
            let inputDetail = '';
            for (const input of inputs.slice(0, 15)) {
              const cs = getComputedStyle(input);
              const br = cs.borderRadius;
              const h = Math.round(input.getBoundingClientRect().height);
              const isRadius4 = br === '4px' || br === '4px 4px 4px 4px';
              const isHeightValid = [32, 36, 40].includes(h);
              if (!isRadius4 || !isHeightValid) {
                inputOk = false;
                inputDetail = `tag=${input.tagName} h=${h} br=${br}`;
                break;
              }
            }

            // 6. label-case (trong Inspector)
            let labelCaseOk = true;
            let labelCaseDetail = '';
            if (isEditor) {
              const labels = [...document.querySelectorAll('.components-base-control__label, .components-input-control__label, .components-toggle-group-control__label')]
                .filter(l => l.offsetParent !== null);
              for (const l of labels) {
                const tt = getComputedStyle(l).textTransform;
                if (tt !== 'none') {
                  labelCaseOk = false;
                  labelCaseDetail = `tt=${tt} text=${l.innerText.slice(0, 20)}`;
                  break;
                }
              }
            }

            // 7. contrast: 20 text elements đầu tiên trong #wpbody-content
            let contrastOk = true;
            let contrastDetail = '';
            const getLuminance = (r, g, b) => {
              const a = [r, g, b].map(v => {
                v /= 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
              });
              return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
            };
            const parseRgb = (str) => {
              const m = str.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
              return m ? { r: +m[1], g: +m[2], b: +m[3], a: m[4] !== undefined ? +m[4] : 1 } : null;
            };


            const contentWrap = document.querySelector('#wpbody-content') || body;
            const textNodes = [...contentWrap.querySelectorAll('p, h1, h2, h3, h4, span, label, th, td, a, button')]
              .filter(el => {
                if (el.offsetParent === null) return false;
                if (isVisuallyHidden(el)) return false;
                if (el.disabled || el.getAttribute('aria-disabled') === 'true' || el.classList.contains('disabled') || el.closest('[aria-disabled="true"], [disabled]')) return false;
                if (!el.innerText || !el.innerText.trim()) return false;
                const r = el.getBoundingClientRect();
                if (r.width === 0 || r.height === 0) return false;
                const hasDirectText = [...el.childNodes].some(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim().length > 0);
                return hasDirectText;
              })
              .slice(0, 20);

            for (const el of textNodes) {
              const cs = getComputedStyle(el);
              const fg = parseRgb(cs.color);
              if (!fg || fg.a < 0.8) continue;
              
              let bg = null;
              for (let p = el; p; p = p.parentElement) {
                const pcs = getComputedStyle(p);
                const c = parseRgb(pcs.backgroundColor);
                if (c && c.a > 0.8) {
                  bg = c;
                  break;
                }
              }
              if (!bg) bg = { r: 255, g: 255, b: 255, a: 1 };

              const l1 = getLuminance(fg.r, fg.g, fg.b);
              const l2 = getLuminance(bg.r, bg.g, bg.b);
              const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);

              const fs = parseFloat(cs.fontSize) || 14;
              const fw = parseInt(cs.fontWeight) || 400;
              const isLarge = fs >= 18 || (fs >= 14 && fw >= 600);
              const minRatio = isLarge ? 3.0 : 4.5;

              if (ratio < minRatio) {
                contrastOk = false;
                contrastDetail = `ratio=${ratio.toFixed(2)} text=${el.innerText.slice(0, 15)} fg=${cs.color}`;
                break;
              }
            }

            // 8. no-admin-color
            const html = document.documentElement.innerHTML;
            const hasInlineAdminColor = html.includes('--primary-color-ad:');
            const hasPrimaryColorField = !!document.querySelector('[name*="primary_color_ad"]');
            const noAdminColor = !hasInlineAdminColor && !hasPrimaryColorField;

            return {
              noOverflow,
              over,
              wideElements,
              accent,
              fontOk,
              bodyFont,
              btnOk,
              btnDetail,
              noBlueOk,
              noBlueDetail,
              menuOneLineOk,
              menuOneLineDetail,
              inputOk,
              inputDetail,
              labelCaseOk,
              labelCaseDetail,
              contrastOk,
              contrastDetail,
              noAdminColor,
              hasInlineAdminColor,
              hasPrimaryColorField
            };
          }, item.isEditor || false, item.id === 'laca-admin');

          report(res.noOverflow, 'no-overflow', item.id, w, res.wideElements.join(', '));
          report(errors.length === 0, 'no-js-error', item.id, w, errors[0] ? errors[0].slice(0, 100) : '');
          report(res.accent === '#18181b', 'tokens', item.id, w, `accent=${res.accent}`);
          report(res.btnOk, 'button-primary', item.id, w, res.btnDetail);
          report(res.noBlueOk, 'no-blue', item.id, w, res.noBlueDetail);
          if (w >= 1024) {
            report(res.menuOneLineOk, 'menu-one-line', item.id, w, res.menuOneLineDetail);
          }
          report(res.inputOk, 'input-style', item.id, w, res.inputDetail);
          if (item.isEditor) {
            report(res.labelCaseOk, 'label-case', item.id, w, res.labelCaseDetail);
          }
          report(res.fontOk, 'font', item.id, w, res.bodyFont.slice(0, 30));
          report(res.contrastOk, 'contrast', item.id, w, res.contrastDetail);
          report(res.noAdminColor, 'no-admin-color', item.id, w, `inline=${res.hasInlineAdminColor} field=${res.hasPrimaryColorField}`);

          if (OUT && (w === 1440 || w === 390)) {
            if (['laca-admin', 'laca-theme', 'seo-general', 'users', 'journal', 'dashboard'].includes(item.id)) {
              await page.screenshot({ path: path.join(OUT, `${item.id}-${w}.png`), fullPage: false });
            }
            if (item.isEditor) {
              await page.screenshot({ path: path.join(OUT, `inspector-cta-${w}.png`), fullPage: false });
            }
          }
        } catch (err) {
          report(false, 'timeout', item.id, w, err.message);
          // Tái tạo page nếu page bị crash/hang
          try { await page.close(); } catch (_) {}
          page = await setupPage();
          await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));
        } finally {
          page.off('pageerror', onErr);
        }
      }
    }
  } finally {
    clearTimeout(watchdog);
    if (browser) {
      try { await browser.close(); } catch (_) {}
    }
    console.log(`\nSUMMARY pass=${pass} fail=${fail}`);
  }
})();
