/**
 * Layout Audit Tool — LIX Matrix & Flex Container
 *
 * Kiểm tra quy cách container / container-fluid (R1–R7) theo 3 tầng (site → trang → block):
 * 1. site fluid (mặc định) — 12 URLs × 4 breakpoints (1440, 1024, 768, 390)
 * 2. site boxed — tham số preview ?laca_layout=boxed
 * 3. page override — ?laca_layout=boxed&laca_page_layout=fluid và ?laca_page_layout=boxed
 * 4. block override — ?laca_block_override=boxed (nạp qua render_block_data không sửa DB)
 * 5. block editor — kiểm tra control "Khung" trong Gutenberg inspector (Puppeteer, abort mọi POST)
 */

const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://lixroastery.local';

const URLS = [
    '/',
    '/manifesto/',
    '/vi/manifesto/the-origin/',
    '/journal/lix-roastery-la-ai/',
    '/journal/extremely-light-cua-lix-roastery/',
    '/journal/lix-roastery-at-cafe-show-hanoi-2026/',
    '/journal/',
    '/shop/',
    '/product/aconchego-1-masterpieces-auction-2025/',
    '/product-category/ca-phe-specialty/',
    '/?s=cafe',
    '/url-404-test-layout/',
];

const BREAKPOINTS = [1440, 1024, 768, 390];

(async () => {
    const chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    const launchOptions = {
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    };
    if (fs.existsSync(chromePath)) {
        launchOptions.executablePath = chromePath;
    }

    const browser = await puppeteer.launch(launchOptions);

    let passCount = 0;
    let failCount = 0;
    const failures = [];

    const page = await browser.newPage();

    // Abort POST requests để an toàn tuyệt đối
    await page.setRequestInterception(true);
    page.on('request', (req) => {
        if (req.method() === 'POST') {
            req.abort();
        } else {
            req.continue();
        }
    });

    // Helper kiểm tra layout trong trang
    async function evaluateLayout(vpWidth, expectedMode = 'fluid') {
        return await page.evaluate((vpWidth, expectedMode) => {
            const errors = [];

            // Kiểm tra body class
            const bodyClass = document.body.className;
            if (expectedMode === 'boxed' && !bodyClass.includes('laca-layout-boxed')) {
                errors.push('Body thiếu class laca-layout-boxed');
            }
            if (expectedMode === 'fluid' && !bodyClass.includes('laca-layout-fluid')) {
                errors.push('Body thiếu class laca-layout-fluid');
            }

            // (c) Kiểm tra tràn ngang
            const scrollW = document.documentElement.scrollWidth;
            const innerW = window.innerWidth;
            if (scrollW - innerW > 1) {
                errors.push(`Tràn ngang: scrollWidth(${scrollW}) > innerWidth(${innerW})`);
            }

            // (b) Kiểm tra .container-fluid lồng trong .container-fluid (trừ tabs và block-container)
            const allNestedFluids = document.querySelectorAll('.container-fluid .container-fluid');
            const invalidNestedFluids = Array.from(allNestedFluids).filter((el) => {
                if (el.closest('.tabs-block') || el.closest('.block-container')) {
                    return false;
                }
                return true;
            });

            if (invalidNestedFluids.length > 0) {
                const tags = invalidNestedFluids.map((el) => {
                    const parentClass = el.parentElement.className;
                    const currentClass = el.className;
                    return `${parentClass} > ${currentClass}`;
                });
                errors.push(`Lồng container-fluid không hợp lệ (${invalidNestedFluids.length} chỗ): ${tags.slice(0, 3).join('; ')}`);
            }

            // (a) Lấy gutterLeft chuẩn từ header .container-fluid
            const headerContainer = document.querySelector('header .container-fluid');
            let expectedGutterLeft = 0;
            let headerContainerWidth = 0;
            if (headerContainer) {
                const rect = headerContainer.getBoundingClientRect();
                const computed = window.getComputedStyle(headerContainer);
                const pl = parseFloat(computed.paddingLeft) || 0;
                expectedGutterLeft = Math.round(rect.left + pl);
                headerContainerWidth = Math.round(rect.width);
            }

            // Kiểm tra boxed: container không vượt 1440px
            if (expectedMode === 'boxed') {
                if (headerContainerWidth > 1440) {
                    errors.push(`Header container vượt quá 1440px trong boxed mode: ${headerContainerWidth}px`);
                }
            }

            // Kiểm tra mép trái các phần tử con trực tiếp của .entry-content
            const entryContent = document.querySelector('.entry-content');
            if (entryContent) {
                const children = Array.from(entryContent.children).filter((el) => {
                    if (['SCRIPT', 'STYLE', 'TEMPLATE'].includes(el.tagName)) return false;
                    if (el.classList.contains('wp-block-spacer') || el.classList.contains('wp-block-separator') || el.tagName === 'HR') {
                        return false;
                    }
                    if (el.classList.contains('journal-toc') || el.tagName === 'NAV') {
                        return false;
                    }
                    return true;
                });

                for (const child of children) {
                    const rect = child.getBoundingClientRect();
                    if (rect.width === 0 && rect.height === 0) continue;

                    const isBleedBlock = child.classList.contains('block-top-hero--bleed') ||
                        (child.classList.contains('alignfull') && !child.classList.contains('block-top-hero'));
                    const isNonBleedHero = child.classList.contains('block-top-hero') &&
                        !child.classList.contains('block-top-hero--bleed');

                    if (isBleedBlock) {
                        if (Math.abs(rect.left) > 2 || Math.abs(rect.width - vpWidth) > 3) {
                            errors.push(`alignfull/bleed không tràn viền: left=${rect.left}, width=${rect.width} (vp=${vpWidth})`);
                            break;
                        }
                    } else if (isNonBleedHero) {
                        const actualLeft = Math.round(rect.left);
                        const actualWidth = Math.round(rect.width);
                        const expectedWidth = Math.round(vpWidth - 2 * expectedGutterLeft);

                        if (expectedGutterLeft > 0 && Math.abs(actualLeft - expectedGutterLeft) > 2) {
                            errors.push(`Top Hero tắt bleed lệch lề trái: actualLeft=${actualLeft} expected=${expectedGutterLeft}`);
                            break;
                        }
                        if (expectedGutterLeft > 0 && Math.abs(actualWidth - expectedWidth) > 3) {
                            errors.push(`Top Hero tắt bleed sai chiều rộng: actualWidth=${actualWidth} expected=${expectedWidth}`);
                            break;
                        }
                    } else {
                        let actualContentLeft = rect.left;
                        if (child.classList.contains('container-fluid')) {
                            const cStyle = window.getComputedStyle(child);
                            actualContentLeft = rect.left + (parseFloat(cStyle.paddingLeft) || 0);
                        } else {
                            const innerFluid = child.querySelector('.container-fluid');
                            if (innerFluid) {
                                const ifRect = innerFluid.getBoundingClientRect();
                                const ifStyle = window.getComputedStyle(innerFluid);
                                actualContentLeft = ifRect.left + (parseFloat(ifStyle.paddingLeft) || 0);
                            }
                        }

                        actualContentLeft = Math.round(actualContentLeft);
                        if (expectedGutterLeft > 0 && Math.abs(actualContentLeft - expectedGutterLeft) > 3) {
                            errors.push(`Lệch lề trái: ${child.tagName}.${child.className} actual=${actualContentLeft} expected=${expectedGutterLeft}`);
                            break;
                        }
                    }
                }
            }

            return {
                success: errors.length === 0,
                errors,
                expectedGutterLeft,
                headerContainerWidth,
            };
        }, vpWidth, expectedMode);
    }

    // ==========================================
    // CASE 1: Site Fluid (Mặc định — 12 URLs × 4 breakpoints)
    // ==========================================
    for (const urlPath of URLS) {
        const fullUrl = `${BASE_URL}${urlPath}`;

        for (const width of BREAKPOINTS) {
            await page.setViewport({ width, height: 900 });

            try {
                const response = await page.goto(fullUrl, {
                    waitUntil: 'networkidle2',
                    timeout: 25000,
                });

                const status = response ? response.status() : 0;
                if (status >= 400 && !urlPath.includes('404')) {
                    const failMsg = `HTTP ${status}`;
                    console.log(`FAIL site-fluid ${urlPath} ${width}px - ${failMsg}`);
                    failCount++;
                    failures.push({ case: 'site-fluid', urlPath, width, reason: failMsg });
                    continue;
                }

                const audit = await evaluateLayout(width, 'fluid');

                if (audit.success) {
                    console.log(`PASS site-fluid ${urlPath} ${width}px (gutter=${audit.expectedGutterLeft}px)`);
                    passCount++;
                } else {
                    const failMsg = audit.errors.join(' | ');
                    console.log(`FAIL site-fluid ${urlPath} ${width}px - ${failMsg}`);
                    failCount++;
                    failures.push({ case: 'site-fluid', urlPath, width, reason: failMsg });
                }
            } catch (err) {
                console.log(`FAIL site-fluid ${urlPath} ${width}px - Error: ${err.message}`);
                failCount++;
                failures.push({ case: 'site-fluid', urlPath, width, reason: err.message });
            }
        }
    }

    // ==========================================
    // CASE 2: Site Boxed (?laca_layout=boxed)
    // ==========================================
    const BOXED_URLS = ['/', '/manifesto/', '/journal/lix-roastery-la-ai/'];
    // 1920 vì boxed giới hạn $xl (1440) — ở 1440 boxed trùng fluid, không chứng minh được gì.
    const BOXED_BREAKPOINTS = [1920, 1440, 1024, 768, 390];

    for (const urlPath of BOXED_URLS) {
        const fullUrl = `${BASE_URL}${urlPath}?laca_layout=boxed`;

        for (const width of BOXED_BREAKPOINTS) {
            await page.setViewport({ width, height: 900 });

            try {
                const response = await page.goto(fullUrl, {
                    waitUntil: 'networkidle2',
                    timeout: 25000,
                });

                const status = response ? response.status() : 0;
                if (status >= 400) {
                    const failMsg = `HTTP ${status}`;
                    console.log(`FAIL site-boxed ${urlPath} ${width}px - ${failMsg}`);
                    failCount++;
                    failures.push({ case: 'site-boxed', urlPath, width, reason: failMsg });
                    continue;
                }

                const audit = await evaluateLayout(width, 'boxed');

                if (audit.success) {
                    console.log(`PASS site-boxed ${urlPath} ${width}px (gutter=${audit.expectedGutterLeft}px, container=${audit.headerContainerWidth}px)`);
                    passCount++;
                } else {
                    const failMsg = audit.errors.join(' | ');
                    console.log(`FAIL site-boxed ${urlPath} ${width}px - ${failMsg}`);
                    failCount++;
                    failures.push({ case: 'site-boxed', urlPath, width, reason: failMsg });
                }
            } catch (err) {
                console.log(`FAIL site-boxed ${urlPath} ${width}px - Error: ${err.message}`);
                failCount++;
                failures.push({ case: 'site-boxed', urlPath, width, reason: err.message });
            }
        }
    }

    // ==========================================
    // CASE 3: Page Override
    // ==========================================
    // 3.1: Site boxed, page fluid override
    try {
        await page.setViewport({ width: 1440, height: 900 });
        const pUrl1 = `${BASE_URL}/manifesto/?laca_layout=boxed&laca_page_layout=fluid`;
        await page.goto(pUrl1, { waitUntil: 'networkidle2', timeout: 25000 });
        const audit1 = await evaluateLayout(1440, 'fluid');
        if (audit1.success) {
            console.log(`PASS page-override /manifesto/?laca_layout=boxed&laca_page_layout=fluid 1440px (page override to fluid)`);
            passCount++;
        } else {
            console.log(`FAIL page-override /manifesto/?laca_layout=boxed&laca_page_layout=fluid 1440px - ${audit1.errors.join(' | ')}`);
            failCount++;
        }
    } catch (err) {
        console.log(`FAIL page-override /manifesto/ 1440px - ${err.message}`);
        failCount++;
    }

    // 3.2: Site fluid (mặc định), page boxed override
    try {
        await page.setViewport({ width: 1440, height: 900 });
        const pUrl2 = `${BASE_URL}/manifesto/?laca_page_layout=boxed`;
        await page.goto(pUrl2, { waitUntil: 'networkidle2', timeout: 25000 });
        const audit2 = await evaluateLayout(1440, 'boxed');
        if (audit2.success) {
            console.log(`PASS page-override /manifesto/?laca_page_layout=boxed 1440px (page override to boxed, container=${audit2.headerContainerWidth}px)`);
            passCount++;
        } else {
            console.log(`FAIL page-override /manifesto/?laca_page_layout=boxed 1440px - ${audit2.errors.join(' | ')}`);
            failCount++;
        }
    } catch (err) {
        console.log(`FAIL page-override /manifesto/ 1440px - ${err.message}`);
        failCount++;
    }

    // ==========================================
    // CASE 4: Block Override (?laca_block_override=boxed)
    // ==========================================
    try {
        await page.setViewport({ width: 1440, height: 900 });
        const bUrl = `${BASE_URL}/journal/lix-roastery-la-ai/?laca_block_override=boxed`;
        await page.goto(bUrl, { waitUntil: 'networkidle2', timeout: 25000 });

        const blockCheck = await page.evaluate(() => {
            const isSiteFluid = document.body.classList.contains('laca-layout-fluid');
            const boxedBlock = document.querySelector('.is-layout-boxed');
            let blockContainerWidth = 0;
            if (boxedBlock) {
                const container = boxedBlock.classList.contains('container-fluid')
                    ? boxedBlock
                    : boxedBlock.querySelector('.container-fluid');
                if (container) {
                    blockContainerWidth = Math.round(container.getBoundingClientRect().width);
                }
            }
            return {
                isSiteFluid,
                hasBoxedBlock: !!boxedBlock,
                blockContainerWidth,
            };
        });

        if (blockCheck.isSiteFluid && blockCheck.hasBoxedBlock && blockCheck.blockContainerWidth <= 1440) {
            console.log(`PASS block-override /journal/lix-roastery-la-ai/ 1440px (block=is-layout-boxed, width=${blockCheck.blockContainerWidth}px)`);
            passCount++;
        } else {
            const reason = `isSiteFluid=${blockCheck.isSiteFluid}, hasBoxedBlock=${blockCheck.hasBoxedBlock}, width=${blockCheck.blockContainerWidth}`;
            console.log(`FAIL block-override /journal/lix-roastery-la-ai/ 1440px - ${reason}`);
            failCount++;
        }
    } catch (err) {
        console.log(`FAIL block-override /journal/lix-roastery-la-ai/ 1440px - ${err.message}`);
        failCount++;
    }

    // ==========================================
    // CASE 5: Block Editor (Gutenberg Inspector "Khung")
    // ==========================================
    try {
        // Cookie đăng nhập sinh lúc chạy (không lưu file, không cần cửa sau trong theme).
        const { spawnSync } = require('child_process');
        const php = process.env.HOME + '/Library/Application Support/Local/lightning-services/php-8.3.17+1/bin/darwin-arm64/bin/php';
        const sock = process.env.HOME + '/Library/Application Support/Local/run/nan2TUtPY/mysql/mysqld.sock';
        const code = "define('WP_USE_THEMES',false);require 'app/public/wp-load.php';$u=get_user_by('login','lacadev');$e=time()+3600;echo json_encode([LOGGED_IN_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'logged_in'),AUTH_COOKIE=>wp_generate_auth_cookie($u->ID,$e,'auth')]);";
        const cookies = JSON.parse(spawnSync(php, ['-d', 'mysqli.default_socket=' + sock, '-r', code], { cwd: path.resolve(__dirname, '../../../../../../..') }).stdout.toString());
        await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value, domain: 'lixroastery.local', path: '/' })));
        await page.goto(`${BASE_URL}/wp-admin/post.php?post=217&action=edit`, { waitUntil: 'networkidle2', timeout: 35000 });

        const editorResult = await page.evaluate(async () => {
            if (!window.wp || !window.wp.data || !window.wp.data.select('core/block-editor')) {
                return { success: false, reason: 'Gutenberg core/block-editor not initialized' };
            }

            const blocks = window.wp.data.select('core/block-editor').getBlocks();
            if (!blocks || blocks.length === 0) {
                return { success: false, reason: 'No blocks found in editor' };
            }

            const block = blocks[0];
            window.wp.data.dispatch('core/block-editor').selectBlock(block.clientId);

            // Open Settings sidebar
            // Mở thẳng tab Block (bấm nút "Settings" sẽ ĐÓNG sidebar nếu nó đang mở sẵn).
            window.wp.data.dispatch('core/edit-post')?.openGeneralSidebar?.('edit-post/block');

            await new Promise((r) => setTimeout(r, 1000));

            // Click Block tab
            const tabs = Array.from(document.querySelectorAll('button[role="tab"], .components-tab-panel__tabs button'));
            const blockTab = tabs.find((t) => t.innerText.trim() === 'Block');
            if (blockTab) {
                blockTab.click();
            }

            await new Promise((r) => setTimeout(r, 1000));

            // WP nhớ trạng thái đóng/mở panel theo user → mở hết panel đang đóng trước khi đọc.
            document.querySelectorAll('.components-panel__body:not(.is-opened) .components-panel__body-toggle').forEach((b) => b.click());
            await new Promise((r) => setTimeout(r, 500));

            const sidebar = document.querySelector('.interface-interface-skeleton__sidebar, .edit-post-sidebar');
            const text = sidebar ? sidebar.innerText : '';

            const hasKhung = text.includes('KHUNG') || text.includes('Khung');
            const hasFluidOption = text.includes('Fluid');
            const hasBoxedOption = text.includes('Boxed');

            // Cập nhật attribute thử nghiệm
            window.wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { containerMode: 'boxed' });
            await new Promise((r) => setTimeout(r, 500));
            const updated = window.wp.data.select('core/block-editor').getBlock(block.clientId);
            const attributeUpdated = updated && updated.attributes.containerMode === 'boxed';

            return {
                success: hasKhung && hasFluidOption && hasBoxedOption && attributeUpdated,
                blockName: block.name,
                hasKhung,
                hasFluidOption,
                hasBoxedOption,
                attributeUpdated,
            };
        });

        if (editorResult.success) {
            console.log(`PASS block-editor post.php?post=217 1440px (block=${editorResult.blockName}, control=Khung [Fluid|Boxed])`);
            passCount++;
        } else {
            console.log(`FAIL block-editor post.php?post=217 1440px - ${JSON.stringify(editorResult)}`);
            failCount++;
        }
    } catch (err) {
        console.log(`FAIL block-editor post.php?post=217 1440px - ${err.message}`);
        failCount++;
    }

    // ==========================================
    // Chụp ảnh nghiệm thu /private/tmp/laca-flex/
    // ==========================================
    try {
        const outDir = '/private/tmp/laca-flex';
        if (!fs.existsSync(outDir)) {
            fs.mkdirSync(outDir, { recursive: true });
        }
        await page.setViewport({ width: 1440, height: 900 });

        await page.goto(`${BASE_URL}/manifesto/`, { waitUntil: 'networkidle2' });
        await page.screenshot({ path: path.join(outDir, 'manifesto-fluid-1440px.png'), fullPage: false });

        await page.goto(`${BASE_URL}/manifesto/?laca_layout=boxed`, { waitUntil: 'networkidle2' });
        await page.screenshot({ path: path.join(outDir, 'manifesto-boxed-1440px.png'), fullPage: false });
    } catch (err) {
        // Screenshot failure non-fatal
    }

    await browser.close();

    console.log(`\nSUMMARY pass=${passCount} fail=${failCount}`);
    if (failCount > 0) {
        process.exitCode = 1;
    }
})();
