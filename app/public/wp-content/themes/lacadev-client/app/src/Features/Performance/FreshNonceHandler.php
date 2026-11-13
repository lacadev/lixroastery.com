<?php

namespace App\Features\Performance;

/**
 * Xử lý Nonce an toàn cho môi trường Full-Page Cache.
 *
 * 1. Cung cấp REST endpoint GET /wp-json/laca/v1/nonce để client lấy nonce mới nhất
 *    cho các thao tác ghi (form liên hệ, newsletter...).
 * 2. Tự động tiêm script nhẹ làm tươi nonce khi người dùng tương tác vào form (focus/click).
 * 3. Cho phép các thao tác CHỈ ĐỌC (Search AJAX, Load More) hoạt động ổn định
 *    ngay cả khi HTML được lưu cache lâu ngày khiến nonce hết hạn.
 */
class FreshNonceHandler
{
    /**
     * Danh sách các action được phép cấp nonce công khai cho khách.
     *
     * @var array<string>
     */
    private const ALLOWED_ACTIONS = [
        'laca_contact_submit_nonce',
        'laca_contact_nonce', // form legacy template-contact.php (field "nonce")
        'laca_footer_contact_nonce',
        'laca_newsletter_nonce',
        'theme_search_nonce',
        'theme_nonce',
    ];

    /**
     * Đăng ký hooks.
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRestRoutes']);
        add_action('wp_footer', [self::class, 'renderNonceRefresherScript'], 100);
    }

    /**
     * Đăng ký REST Route GET /wp-json/laca/v1/nonce.
     */
    public static function registerRestRoutes(): void
    {
        register_rest_route('laca/v1', '/nonce', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handleNonceRequest'],
            'permission_callback' => '__return_true',
            'args'                => [
                'action' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    /**
     * Trả về nonce tươi cho action hợp lệ.
     */
    public static function handleNonceRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $action = (string) $request->get_param('action');

        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Action not allowed',
            ], 403);
        }

        // Rate limit: 60 requests / minute / IP
        $ip = PostViewTracker::getClientIp();
        $rateKey = 'laca_nr_' . md5($ip);
        $count = (int) get_transient($rateKey);
        if ($count > 60) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Too many requests',
            ], 429);
        }
        set_transient($rateKey, $count + 1, 60);

        return new \WP_REST_Response([
            'success' => true,
            'action'  => $action,
            'nonce'   => wp_create_nonce($action),
        ], 200);
    }

    /**
     * Tự động làm tươi nonce của form trên frontend khi người dùng tương tác.
     */
    public static function renderNonceRefresherScript(): void
    {
        if (is_admin()) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('laca/v1/nonce'));
        ?>
        <script>
        (function() {
            var nonceEndpoint = <?php echo wp_json_encode($endpoint); ?>;

            function refreshFormNonce(form) {
                if (!form || form.dataset.lacaNonceRefreshed) return;

                var nonceInput = form.querySelector('input[name="_nonce"], input[name="nonce"]');
                if (!nonceInput) return;

                // Xác định nonce action
                var action = form.dataset.nonceAction;
                if (!action) {
                    var formAction = form.querySelector('input[name="action"]');
                    var actionVal = formAction ? formAction.value : '';
                    if (actionVal === 'laca_contact_submit' && nonceInput.name === 'nonce') {
                        action = 'laca_contact_nonce';
                    } else if (actionVal === 'laca_cf_submit' || nonceInput.name === '_nonce') {
                        action = 'laca_contact_submit_nonce';
                    } else if (actionVal === 'laca_footer_contact_submit') {
                        action = 'laca_footer_contact_nonce';
                    } else if (actionVal === 'laca_newsletter_subscribe' || form.hasAttribute('data-newsletter-form')) {
                        action = 'laca_newsletter_nonce';
                    }
                }

                if (!action) return;

                form.dataset.lacaNonceRefreshed = 'pending';
                fetch(nonceEndpoint + '?action=' + encodeURIComponent(action))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data && data.success && data.nonce) {
                            nonceInput.value = data.nonce;
                            form.dataset.lacaNonceRefreshed = 'done';
                        }
                    })
                    .catch(function() {});
            }

            // Lắng nghe tương tác người dùng vào form (focus/click)
            document.addEventListener('focusin', function(e) {
                var form = e.target.closest && e.target.closest('form');
                if (form) refreshFormNonce(form);
            }, { passive: true });

            document.addEventListener('click', function(e) {
                var form = e.target.closest && e.target.closest('form');
                if (form) refreshFormNonce(form);
            }, { passive: true });
        })();
        </script>
        <?php
    }
}

