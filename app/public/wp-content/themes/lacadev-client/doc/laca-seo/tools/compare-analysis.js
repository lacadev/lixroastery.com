const puppeteer = require('/Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child/node_modules/puppeteer');
const { execFileSync } = require('child_process');
const PHP = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
const SOCK = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
const code = `define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);`;
const ck = JSON.parse(execFileSync(PHP, ['-d', 'mysqli.default_socket=' + SOCK, '-r', code], { cwd: '/Users/lacadev/Documents/wordpress/lixroastery' }).toString());

const cases = [
  { type: 'post', id: 908, kw: 'cafe show' },
  { type: 'post', id: 908, kw: 'lix roastery,cafe' },
  { type: 'post', id: 866, kw: 'extremely light' },
  { type: 'post', id: 866, kw: 'roasting philosophy' },
  { type: 'post', id: 29, kw: 'aconchego' },
  { type: 'post', id: 29, kw: 'masterpieces auction' },
  { type: 'post', id: 928, kw: 'manifesto' },
  { type: 'term', id: 27, tax: 'product_cat', kw: 'specialty' }
];

(async () => {
  const b = await puppeteer.launch({ headless: 'new', executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const p = await b.newPage();
  p.on('dialog', async dialog => {
    await dialog.accept();
  });
  await p.setViewport({ width: 1440, height: 900 });
  await p.setCookie(...Object.entries(ck).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));
  
  let lastUrl = null;
  for (const c of cases) {
    const editUrl = c.type === 'term'
      ? `http://lixroastery.local/wp-admin/term.php?taxonomy=${c.tax}&tag_ID=${c.id}&post_type=product`
      : `http://lixroastery.local/wp-admin/post.php?post=${c.id}&action=edit`;

    if (editUrl !== lastUrl) {
      await p.goto(editUrl, { waitUntil: 'domcontentloaded', timeout: 180000 });
      await p.waitForFunction(() => (window.rankMathEditor || window.rankMath) && window.lacaSeoAnalysis, { timeout: 120000 });
      await p.evaluate(() => {
        window.onbeforeunload = null;
        if (window.jQuery) {
          window.jQuery(window).off('beforeunload');
        }
      });
      await new Promise(r => setTimeout(r, 4000));
      lastUrl = editUrl;
    }

    await p.evaluate((kw, isTerm) => {
      if (isTerm) {
        const frame = document.querySelector('.rank-math-metabox-frame');
        const rmInp = frame ? frame.querySelector('input[placeholder*="Example:"]') : null;
        if (rmInp) {
          rmInp.value = kw;
          rmInp.dispatchEvent(new Event('input', { bubbles: true }));
          rmInp.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', keyCode: 13, bubbles: true }));
        }
      } else {
        if (window.wp && wp.data && wp.data.dispatch && wp.data.dispatch('rank-math')) {
          wp.data.dispatch('rank-math').updateKeywords(kw);
        }
      }

      const inp = document.getElementById('laca_kw_type_input');
      if (inp) {
        document.querySelectorAll('.laca-kw-remove').forEach(() => {
          const x = document.querySelector('.laca-kw-remove');
          if (x) x.click();
        });
        kw.split(',').forEach(k => {
          inp.value = k.trim();
          inp.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        });
      }
    }, c.kw, c.type === 'term');

    await new Promise(r => setTimeout(r, 5000));

    const out = await p.evaluate((c) => {
      const primary = c.kw.split(',')[0].trim().toLowerCase();
      let res = null;
      let rmSerp = ['', ''];
      
      if (c.type === 'term') {
        const rm = window.rankMathEditor ? rankMathEditor.resultManager : null;
        if (rm) {
          res = rm.getResult(primary) || rm.getResult(c.kw.split(',')[0].trim());
        }
        const rmTitleEl = document.querySelector('.serp-preview .serp-title') || document.getElementById('rank_math_title');
        const rmDescEl = document.querySelector('.serp-preview .serp-description') || document.getElementById('rank_math_description');
        rmSerp = [
          rmTitleEl ? (rmTitleEl.value || rmTitleEl.textContent || '').trim() : '',
          rmDescEl ? (rmDescEl.value || rmDescEl.textContent || '').trim() : ''
        ];
      } else {
        const rm = window.rankMathEditor ? rankMathEditor.resultManager : null;
        if (rm) {
          res = rm.getResult(primary) || rm.getResult(c.kw.split(',')[0].trim());
        }
        if (window.wp && wp.data && wp.data.select && wp.data.select('rank-math')) {
          const s = wp.data.select('rank-math');
          rmSerp = [s.getSerpTitle ? s.getSerpTitle() : '', s.getSerpDescription ? s.getSerpDescription() : ''];
        }
      }

      const rmTests = {};
      if (res && res.results) {
        for (const [k, v] of Object.entries(res.results)) {
          rmTests[k] = [v.score, v.maxScore];
        }
      }

      const l = window.lacaSeoAnalysis || { score: 0, results: {} };
      const lt = {};
      for (const [k, v] of Object.entries(l.results)) {
        lt[k] = [v.score, v.maxScore];
      }

      const titleEl = document.getElementById('laca_serp_title');
      const descEl = document.getElementById('laca_serp_desc');
      const lacaSerp = [
        titleEl ? (titleEl.textContent || '').trim() : '',
        descEl ? (descEl.textContent || '').trim() : ''
      ];

      return {
        rmScore: res ? res.score : null,
        rmTests,
        lacaScore: l.score,
        lt,
        rmSerp: [rmSerp[0].trim(), rmSerp[1].trim()],
        lacaSerp
      };
    }, c);

    const diffs = [];
    for (const k of new Set([...Object.keys(out.rmTests), ...Object.keys(out.lt)])) {
      if (k === 'hasContentAI') continue;
      if (JSON.stringify(out.rmTests[k]) !== JSON.stringify(out.lt[k])) {
        diffs.push(`${k}: RM ${JSON.stringify(out.rmTests[k])} Laca ${JSON.stringify(out.lt[k])}`);
      }
    }

    let a = 0, m = 0;
    for (const [k, v] of Object.entries(out.rmTests)) {
      if (k === 'hasContentAI') continue;
      a += v[0] || 0;
      m += v[1] || 0;
    }
    const rmNoAI = m ? Math.round(a / m * 100) : '-';

    console.log(`\n# ${c.type} ${c.id} kw="${c.kw}" RM=${out.rmScore} RM(noAI)=${rmNoAI} Laca=${out.lacaScore} diffs=${diffs.length}`);
    diffs.forEach(d => console.log('   ' + d));
    console.log('   SERP RM  :', JSON.stringify(out.rmSerp));
    console.log('   SERP Laca:', JSON.stringify(out.lacaSerp));
  }

  await b.close();
})().catch(e => {
  console.error('ERR', e);
  process.exit(1);
});
