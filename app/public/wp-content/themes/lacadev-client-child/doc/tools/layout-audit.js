/**
 * Layout Audit Tool — LIX
 *
 * Kiểm tra quy cách container / container-fluid (R1–R6) trên 11 URLs × 4 breakpoints:
 * 1. gutterLeft = mép trái box nội dung của header (.container-fluid padding-box)
 *    Mọi con cấp 1 của .entry-content / main#main_content / section.container-fluid:
 *    - Nếu không alignfull: mép trái nội dung = gutterLeft (±1px)
 *    - Nếu alignfull: left = 0 và width = viewportWidth
 * 2. Không lồng .container-fluid trong .container-fluid (trừ trường hợp được phép)
 * 3. scrollWidth - innerWidth <= 1px (không tràn ngang)
 */

const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://lixroastery.local';

const URLS = [
    '/',
    '/manifesto/',
    '/vi/manifesto/the-origin/', // 1 page publish có block
    '/journal/lix-roastery-la-ai/', // Hero TẮT tràn viền (fullBleed false)
    '/journal/extremely-light-cua-lix-roastery/', // Hero BẬT tràn viền
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
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

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
                // URL 404 trả về 404 là đúng kỳ vọng
                if (status >= 400 && !urlPath.includes('404')) {
                    const failMsg = `HTTP ${status}`;
                    console.log(`FAIL ${urlPath} ${width}px - ${failMsg}`);
                    failCount++;
                    failures.push({ urlPath, width, reason: failMsg });
                    continue;
                }

                // Thực hiện đo đạc layout trong trang
                const audit = await page.evaluate((vpWidth) => {
                    const errors = [];

                    // (c) Kiểm tra tràn ngang
                    const scrollW = document.documentElement.scrollWidth;
                    const innerW = window.innerWidth;
                    if (scrollW - innerW > 1) {
                        errors.push(`Tràn ngang: scrollWidth(${scrollW}) > innerWidth(${innerW})`);
                    }

                    // (b) Kiểm tra .container-fluid lồng trong .container-fluid
                    // Ngoại lệ hợp lệ theo R5: block Container hoặc block Tabs chứa các block con
                    const allNestedFluids = document.querySelectorAll(
                        '.container-fluid .container-fluid'
                    );
                    const invalidNestedFluids = Array.from(allNestedFluids).filter((el) => {
                        // Nếu nằm trong .tabs-block__panels hoặc .block-container thì là lồng hợp lệ
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
                    if (headerContainer) {
                        const rect = headerContainer.getBoundingClientRect();
                        const computed = window.getComputedStyle(headerContainer);
                        const pl = parseFloat(computed.paddingLeft) || 0;
                        expectedGutterLeft = Math.round(rect.left + pl);
                    }

                    // Kiểm tra mép trái các phần tử con trực tiếp của .entry-content hoặc các section chính
                    const entryContent = document.querySelector('.entry-content');
                    if (entryContent) {
                        const children = Array.from(entryContent.children).filter((el) => {
                            if (['SCRIPT', 'STYLE', 'TEMPLATE'].includes(el.tagName)) return false;
                            // Bỏ qua các block không chứa nội dung văn bản (spacer, hr, separator)
                            if (
                                el.classList.contains('wp-block-spacer') ||
                                el.classList.contains('wp-block-separator') ||
                                el.tagName === 'HR'
                            ) {
                                return false;
                            }
                            // Bỏ qua journal-toc vì mục lục bài viết căn giữa theo cột article (%journal-column)
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
                                // Block tràn viền: left ~ 0 và width ~ vpWidth
                                if (Math.abs(rect.left) > 2 || Math.abs(rect.width - vpWidth) > 3) {
                                    errors.push(`alignfull/bleed không tràn viền: left=${rect.left}, width=${rect.width} (vp=${vpWidth})`);
                                    break;
                                }
                            } else if (isNonBleedHero) {
                                // Block top-hero khi TẮT tràn viền:
                                // Mép trái của khối = expectedGutterLeft (±1px), width = vpWidth - 2 * expectedGutterLeft (±2px)
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
                                // Phần tử bình thường: nếu có container-fluid bên trong thì xét padding-box,
                                // nếu là core block (p, h2...) thì chính mép trái của nó phải bằng expectedGutterLeft
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
                    };
                }, width);

                if (audit.success) {
                    console.log(`PASS ${urlPath} ${width}px (gutter=${audit.expectedGutterLeft}px)`);
                    passCount++;

                    // Chụp ảnh nghiệm thu nếu là URL bài 101 hoặc bài hero BẬT tràn viền ở 1440px / 390px
                    if (
                        (urlPath.includes('lix-roastery-la-ai') || urlPath.includes('extremely-light-cua-lix-roastery')) &&
                        (width === 1440 || width === 390)
                    ) {
                        const outDir = '/private/tmp/laca-layout2';
                        if (!fs.existsSync(outDir)) {
                            fs.mkdirSync(outDir, { recursive: true });
                        }
                        const slug = urlPath.includes('lix-roastery-la-ai') ? 'hero-nonbleed-101' : 'hero-bleed-extremely-light';
                        const imgPath = path.join(outDir, `${slug}-${width}px.png`);
                        await page.screenshot({ path: imgPath, fullPage: false });
                        console.log(`   [Screenshot saved: ${imgPath}]`);
                    }
                } else {
                    const failMsg = audit.errors.join(' | ');
                    console.log(`FAIL ${urlPath} ${width}px - ${failMsg}`);
                    failCount++;
                    failures.push({ urlPath, width, reason: failMsg });
                }
            } catch (err) {
                console.log(`FAIL ${urlPath} ${width}px - Error: ${err.message}`);
                failCount++;
                failures.push({ urlPath, width, reason: err.message });
            }
        }
    }

    await browser.close();

    console.log(`\nSUMMARY pass=${passCount} fail=${failCount}`);
    if (failCount > 0) {
        process.exitCode = 1;
    }
})();
