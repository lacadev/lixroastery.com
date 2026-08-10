/* eslint-disable no-console */
/**
 * Critical CSS Generator
 * Run via: npm run critical
 */
// Critical module will be imported dynamically
const path = require('path');
const config = require('../../config.json');

// Allow skipping critical CSS generation in environments where Chromium can't launch
// Usage: SKIP_CRITICAL=1 yarn build
if (process.env.SKIP_CRITICAL === '1') {
    console.log('ℹ️  SKIP_CRITICAL=1, skipping Critical CSS generation.');
    process.exit(0);
}

// Get dev URL from config or fallback
const targetUrl = (config.development && config.development.url) ? config.development.url : 'http://lacadev.local';

console.log(`Starting Critical CSS generation from: ${targetUrl}`);

// Resolve paths
// theme/dist is where we want to save
const distPath = path.resolve(__dirname, '../../dist/');

// Dimensions to check
const dimensions = [
    {
        height: 640,
        width: 360, // Mobile
    },
    {
        height: 1024,
        width: 768, // Tablet
    },
    {
        height: 900,
        width: 1200, // Desktop
    },
];

(async () => {
    try {
        const { generate } = await import('critical');
        const puppeteer = require('puppeteer');
        // Prefer system Chrome over old bundled Chromium (r722234) which times out on macOS 15+
        const systemChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        const fs = require('fs');
        const browserLaunchOptions = {
            headless: true,
            timeout: 120000,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors'],
        };
        const penthouseArgs = {
            // Local WP pages may include many third-party scripts; block JS requests
            // so Penthouse can focus on CSS and avoid waiting on long-running assets.
            blockJSRequests: true,
            // 30s default is often too short for local environments.
            timeout: 120000,
            // Stop waiting for full page load after a grace period.
            pageLoadSkipTimeout: 15000,
            // Small buffer to let above-the-fold styles settle.
            renderWaitTime: 1000,
            // Penthouse supports custom browser via puppeteer.getBrowser.
            // This avoids fallback to old bundled Chromium revisions.
            puppeteer: {
                getBrowser: () => puppeteer.launch(browserLaunchOptions),
                pageGotoOptions: {
                    waitUntil: 'domcontentloaded',
                    timeout: 60000,
                },
            },
        };
        if (fs.existsSync(systemChrome)) {
            browserLaunchOptions.executablePath = systemChrome;
        }

        const { css, html, uncritical } = await generate({
            base: distPath,
            src: targetUrl,
            target: 'styles/critical.css',
            inline: false,
            extract: false,
            dimensions: dimensions,
            // Ignore strict font loading in critical path to avoid FOUT issues
            ignore: {
                atrule: ['@font-face'],
            },
            penthouse: penthouseArgs,
        });

        console.log('✅ Critical CSS generated successfully at: dist/styles/critical.css');
    } catch (err) {
        console.error('❌ Critical CSS Generation Failed:', err);
        // Default to non-fatal so `yarn build` can succeed even if Puppeteer crashes.
        // Set CRITICAL_STRICT=1 to fail the build on error.
        if (process.env.CRITICAL_STRICT === '1') {
            process.exit(1);
        }
        console.log('ℹ️  Continuing build (set CRITICAL_STRICT=1 to fail on critical errors).');
        process.exit(0);
    }
})();
