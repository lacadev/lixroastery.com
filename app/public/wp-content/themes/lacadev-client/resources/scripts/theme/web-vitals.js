/**
 * Web Vitals & Performance Monitoring (DEBUG only)
 * Injected via wp_enqueue_script in performance.php (khi WP_DEBUG = true)
 */

// Helper function to evaluate and log Web Vitals with detailed feedback
function logWebVital(name, value, unit, thresholds) {
    const { good, poor } = thresholds;
    let rating, color, emoji, rangeInfo;

    if (value <= good) {
        rating = 'TỐT ✓';
        color = '#0cce6b';
        emoji = '✓';
        rangeInfo = `(0 - ${good}${unit})`;
    } else if (value <= poor) {
        rating = 'CẦN CẢI THIỆN ⚠';
        color = '#ffa400';
        emoji = '⚠';
        rangeInfo = `(${good}${unit} - ${poor}${unit})`;
    } else {
        rating = 'KÉM ✗';
        color = '#ff4e42';
        emoji = '✗';
        rangeInfo = `(> ${poor}${unit})`;
    }

    console.log(
        `%c${emoji} ${name}: ${value.toFixed(2)}${unit} - ${rating} ${rangeInfo}`,
        `color: ${color}; font-weight: bold; font-size: 12px;`
    );
}

// Core Web Vitals monitoring with detailed evaluation
if ('PerformanceObserver' in window) {
    // Largest Contentful Paint (LCP)
    // Good: ≤2500ms | Needs Improvement: ≤4000ms | Poor: >4000ms
    new PerformanceObserver((entryList) => {
        for (const entry of entryList.getEntries()) {
            logWebVital('LCP', entry.startTime, 'ms', { good: 2500, poor: 4000 });
        }
    }).observe({ type: 'largest-contentful-paint', buffered: true });

    // Cumulative Layout Shift (CLS)
    // Good: ≤0.1 | Needs Improvement: ≤0.25 | Poor: >0.25
    let clsScore = 0;
    new PerformanceObserver((entryList) => {
        for (const entry of entryList.getEntries()) {
            if (!entry.hadRecentInput) {
                clsScore += entry.value;
                logWebVital('CLS', clsScore, '', { good: 0.1, poor: 0.25 });
            }
        }
    }).observe({ type: 'layout-shift', buffered: true });

    // First Input Delay (FID)
    // Good: ≤100ms | Needs Improvement: ≤300ms | Poor: >300ms
    new PerformanceObserver((entryList) => {
        for (const entry of entryList.getEntries()) {
            const fid = entry.processingStart - entry.startTime;
            logWebVital('FID', fid, 'ms', { good: 100, poor: 300 });
        }
    }).observe({ type: 'first-input', buffered: true });
}

// Performance marks
if ('performance' in window && 'mark' in performance) {
    performance.mark('theme-loaded');

    // Log page load timing
    window.addEventListener('load', () => {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            if (perfData) {
                console.log('%c📊 Page Load Metrics:', 'color: #4285f4; font-weight: bold; font-size: 14px;');
                console.log(`  DOM Content Loaded: ${perfData.domContentLoadedEventEnd.toFixed(2)}ms`);
                console.log(`  Page Load Complete: ${perfData.loadEventEnd.toFixed(2)}ms`);
                console.log(`  DNS Lookup: ${(perfData.domainLookupEnd - perfData.domainLookupStart).toFixed(2)}ms`);
                console.log(`  TCP Connection: ${(perfData.connectEnd - perfData.connectStart).toFixed(2)}ms`);
            }
        }, 0);
    });
}
