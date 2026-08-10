/**
 * Service Worker Registration
 * Injected via wp_enqueue_script in performance.php
 * swConfig is localized via wp_localize_script
 */
if ('serviceWorker' in navigator && !navigator.serviceWorker.controller) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(swConfig.swUrl, {
            scope: '/'
        }).then(function (registration) {
            if (swConfig.debug === 'true') {
                console.log('SW registered:', registration.scope);
            }
        }).catch(function (error) {
            if (swConfig.debug === 'true') {
                console.log('SW registration failed:', error);
            }
        });
    });
}
