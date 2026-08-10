/**
 * Remove "Confirm use of weak password" checkbox on login page
 * Injected via wp_enqueue_script on login_enqueue_scripts hook
 */
document.addEventListener('DOMContentLoaded', function () {
    var elements = document.getElementsByClassName('pw-weak');
    if (elements.length > 0) {
        elements[0].remove();
    }
});
