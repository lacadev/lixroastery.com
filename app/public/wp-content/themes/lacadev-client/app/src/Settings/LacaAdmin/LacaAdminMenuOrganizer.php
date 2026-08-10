<?php

namespace App\Settings\LacaAdmin;

/**
 * Keeps the Laca Admin submenu grouped in one predictable order.
 *
 * Individual modules still own their page callbacks and capabilities. This
 * class only reorganizes the final WordPress submenu array after all pages are
 * registered, then renders an internal Laca Admin dock. Existing URLs and
 * callbacks remain unchanged.
 */
class LacaAdminMenuOrganizer
{
    private const PARENT_SLUG = 'laca-admin';

    /**
     * @var array<int,array{key:string,label:string,icon:string,items:array<int,array{label:string,slug:string,url:string}>}>
     */
    private array $navigationGroups = [];

    /**
     * @var array<string,array{label:string,tab:string}>
     */
    private const SECURITY_TABS = [
        'laca-security-audit' => [
            'label' => 'Kiểm tra bảo mật',
            'tab' => 'audit',
        ],
        'laca-security-fim' => [
            'label' => 'Giám sát file',
            'tab' => 'fim',
        ],
        'laca-security-malware' => [
            'label' => 'Quét mã độc',
            'tab' => 'malware',
        ],
        'laca-security-users' => [
            'label' => 'User ẩn',
            'tab' => 'users',
        ],
        'laca-security-login' => [
            'label' => 'URL đăng nhập',
            'tab' => 'login',
        ],
        'laca-security-2fa' => [
            'label' => '2FA TOTP',
            'tab' => '2fa',
        ],
        'laca-security-superuser' => [
            'label' => 'Super User',
            'tab' => 'superuser',
        ],
    ];

    /**
     * @var array<string,array{label:string,icon:string,items:string[]}>
     */
    private const GROUPS = [
        'general' => [
            'label' => 'Tổng quan / Cấu hình chung',
            'icon' => 'dashicons-admin-generic',
            'items' => [
                'laca-admin',
                'laca-management-settings',
            ],
        ],
        'maintenance' => [
            'label' => 'Hiệu năng & bảo trì',
            'icon' => 'dashicons-performance',
            'items' => [
                'laca-tools',
                'laca-db-cleaner',
                'laca-email-log',
            ],
        ],
        'security' => [
            'label' => 'Bảo mật & đăng nhập',
            'icon' => 'dashicons-shield-alt',
            'items' => [
                'laca-security-audit',
                'laca-security-fim',
                'laca-security-malware',
                'laca-security-users',
                'laca-security-login',
                'laca-security-2fa',
                'laca-security-superuser',
                'laca-recaptcha',
                'laca-login-socials',
            ],
        ],
        'content' => [
            'label' => 'Nội dung & cấu trúc',
            'icon' => 'dashicons-screenoptions',
            'items' => [
                'laca-dynamic-cpt',
                'laca-contact-forms',
            ],
        ],
        'sync' => [
            'label' => 'Kết nối LacaDev',
            'icon' => 'dashicons-networking',
            'items' => [
                'laca-block-sync',
                'lacadev-block-categories',
                'laca-tracker',
            ],
        ],
        'projects' => [
            'label' => 'Dự án & thông báo',
            'icon' => 'dashicons-portfolio',
            'items' => [
                'laca-project-notifications',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing & AI',
            'icon' => 'dashicons-megaphone',
            'items' => [
                'laca-exit-popup',
                'laca-chatbot',
            ],
        ],
    ];

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'organize'], PHP_INT_MAX);
        add_action('admin_head', [$this, 'printStyles']);
        add_action('all_admin_notices', [$this, 'renderNavigationDock']);
        add_filter('admin_body_class', [$this, 'filterAdminBodyClass']);
    }

    public function organize(): void
    {
        global $submenu;

        if (empty($submenu[self::PARENT_SLUG]) || !is_array($submenu[self::PARENT_SLUG])) {
            return;
        }

        $itemsBySlug = [];
        $unassigned = [];

        foreach ($submenu[self::PARENT_SLUG] as $item) {
            $slug = (string) ($item[2] ?? '');

            if ($slug === '') {
                continue;
            }

            $itemsBySlug[$slug] = $item;
            $unassigned[$slug] = $item;
        }

        $organized = [];
        $navigationGroups = [];

        foreach (self::GROUPS as $groupKey => $group) {
            $groupItems = [];

            foreach ($group['items'] as $slug) {
                if (isset(self::SECURITY_TABS[$slug])) {
                    if (!isset($itemsBySlug['laca-security'])) {
                        continue;
                    }

                    $groupItems[] = $this->buildNavigationItemFromSlug($slug);
                    continue;
                }

                if (!isset($itemsBySlug[$slug])) {
                    continue;
                }

                $organized[] = $itemsBySlug[$slug];
                $groupItems[] = $this->buildNavigationItem($itemsBySlug[$slug]);
                unset($unassigned[$slug]);
            }

            if ($groupItems !== []) {
                $navigationGroups[] = [
                    'key' => $groupKey,
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'items' => $groupItems,
                ];
            }
        }

        if (isset($itemsBySlug['laca-security'])) {
            $organized[] = $itemsBySlug['laca-security'];
            unset($unassigned['laca-security']);
        }

        if ($unassigned !== []) {
            array_push($organized, ...array_values($unassigned));
            $navigationGroups[] = [
                'key' => 'other',
                'label' => 'Khác',
                'icon' => 'dashicons-menu-alt3',
                'items' => array_map([$this, 'buildNavigationItem'], array_values($unassigned)),
            ];
        }

        $submenu[self::PARENT_SLUG] = $organized;
        $this->navigationGroups = $navigationGroups;
    }

    /**
     * @param array<int,mixed> $item
     *
     * @return array{label:string,slug:string,url:string}
     */
    private function buildNavigationItem(array $item): array
    {
        $slug = (string) ($item[2] ?? '');
        $label = wp_strip_all_tags((string) ($item[0] ?? $slug));

        return $this->buildNavigationItemFromSlug($slug, $label);
    }

    /**
     * @return array{label:string,slug:string,url:string}
     */
    private function buildNavigationItemFromSlug(string $slug, ?string $label = null): array
    {
        if (isset(self::SECURITY_TABS[$slug])) {
            $tab = self::SECURITY_TABS[$slug]['tab'];

            return [
                'label' => self::SECURITY_TABS[$slug]['label'],
                'slug' => $slug,
                'url' => add_query_arg(
                    [
                        'page' => 'laca-security',
                        'tab' => $tab,
                    ],
                    admin_url('admin.php')
                ),
            ];
        }

        return [
            'label' => ($label !== null && $label !== '') ? $label : $this->getFallbackItemLabel($slug),
            'slug' => $slug,
            'url' => add_query_arg('page', $slug, admin_url('admin.php')),
        ];
    }

    public function filterAdminBodyClass(string $classes): string
    {
        if (!$this->isLacaAdminRequest()) {
            return $classes;
        }

        return trim($classes . ' laca-admin-dock-active');
    }

    public function printStyles(): void
    {
        ?>
        <style>
            #toplevel_page_laca-admin .wp-submenu {
                display: none !important;
            }

            body.laca-admin-dock-active:not(.folded) #wpcontent {
                padding-left: 304px;
            }

            body.laca-admin-dock-active.folded #wpcontent {
                padding-left: 304px;
            }

            .laca-admin-dock {
                background: #fff;
                border-right: 1px solid #e5e7eb;
                bottom: 0;
                box-shadow: 10px 0 24px rgba(15, 23, 42, .06);
                color: #1f2937;
                left: 160px;
                overflow-y: auto;
                padding: 18px 12px;
                position: fixed;
                top: 32px;
                width: 276px;
                z-index: 99;
            }

            body.folded .laca-admin-dock {
                left: 36px;
            }

            .laca-admin-dock__group {
                border-top: 1px solid #f1f3f5;
                margin-bottom: 0;
                padding: 20px 4px;
            }

            .laca-admin-dock__group:first-of-type {
                border-top: 0;
                padding-top: 0;
            }

            .laca-admin-dock__group-title {
                align-items: center;
                color: #6b7280;
                display: flex;
                font-size: 12px;
                font-weight: 700;
                gap: 0;
                letter-spacing: 0;
                line-height: 1.35;
                margin: 0 0 6px;
            }

            .laca-admin-dock__group-title .dashicons {
                display: none;
            }

            .laca-admin-dock__group-count {
                display: none;
            }

            .laca-admin-dock__items {
                display: grid;
                gap: 4px;
                margin: 0;
            }

            .laca-admin-dock__item {
                border: 1px solid transparent;
                border-radius: 6px;
                color: #374151;
                display: block;
                font-size: 13px;
                line-height: 1.35;
                padding: 7px 10px;
                text-decoration: none;
                transition: background .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
            }

            .laca-admin-dock__item::before {
                content: none;
            }

            .laca-admin-dock__item:hover,
            .laca-admin-dock__item:focus {
                background: #f8fafc;
                border-color: #e5e7eb;
                color: #111827;
                outline: none;
            }

            .laca-admin-dock__item.is-active {
                background: #f3f4f6;
                border-color: #d1d5db;
                box-shadow: none;
                color: #111827;
                font-weight: 700;
            }

            .laca-admin-dock__item.is-active::before {
                content: none;
            }

            @media (max-width: 960px) {
                body.auto-fold .laca-admin-dock {
                    left: 36px;
                }
            }

            @media (max-width: 782px) {
                body.laca-admin-dock-active #wpcontent,
                body.laca-admin-dock-active.folded #wpcontent,
                body.laca-admin-dock-active:not(.folded) #wpcontent {
                    padding-left: 10px;
                }

                .laca-admin-dock {
                    border-radius: 10px;
                    bottom: auto;
                    left: auto;
                    margin: 12px 10px 18px;
                    max-height: none;
                    position: relative;
                    top: auto;
                    width: auto;
                }

                body.folded .laca-admin-dock,
                body.auto-fold .laca-admin-dock {
                    left: auto;
                }
            }
        </style>
        <?php
    }

    public function renderNavigationDock(): void
    {
        if (!$this->isLacaAdminRequest()) {
            return;
        }

        $groups = $this->getNavigationGroups();
        if ($groups === []) {
            return;
        }

        $currentPage = $this->getCurrentPageSlug();
        ?>
        <nav class="laca-admin-dock" aria-label="<?php echo esc_attr__('Laca Admin', 'laca'); ?>">
            <?php foreach ($groups as $group): ?>
                <section class="laca-admin-dock__group" aria-labelledby="laca-admin-dock-group-<?php echo esc_attr($group['key']); ?>">
                    <h2 class="laca-admin-dock__group-title" id="laca-admin-dock-group-<?php echo esc_attr($group['key']); ?>">
                        <span class="dashicons <?php echo esc_attr($group['icon']); ?>" aria-hidden="true"></span>
                        <span><?php echo esc_html($group['label']); ?></span>
                        <span class="laca-admin-dock__group-count"><?php echo esc_html((string) count($group['items'])); ?></span>
                    </h2>
                    <div class="laca-admin-dock__items">
                        <?php foreach ($group['items'] as $item): ?>
                            <a
                                class="laca-admin-dock__item<?php echo $currentPage === $item['slug'] ? ' is-active' : ''; ?>"
                                href="<?php echo esc_url($item['url']); ?>"
                                <?php echo $currentPage === $item['slug'] ? 'aria-current="page"' : ''; ?>
                            >
                                <?php echo esc_html($item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * @return array<int,array{key:string,label:string,icon:string,items:array<int,array{label:string,slug:string,url:string}>}>
     */
    private function getNavigationGroups(): array
    {
        if ($this->navigationGroups !== []) {
            return $this->navigationGroups;
        }

        $groups = [];
        foreach (self::GROUPS as $groupKey => $group) {
            $items = [];

            foreach ($group['items'] as $slug) {
                $items[] = $this->buildNavigationItemFromSlug($slug);
            }

            $groups[] = [
                'key' => $groupKey,
                'label' => $group['label'],
                'icon' => $group['icon'],
                'items' => $items,
            ];
        }

        return $groups;
    }

    private function getFallbackItemLabel(string $slug): string
    {
        $labels = [
            'laca-admin' => 'Laca Admin',
            'laca-management-settings' => 'Quản trị & HD Sử dụng',
            'laca-tools' => 'Tools',
            'laca-db-cleaner' => 'Dọn dẹp DB',
            'laca-email-log' => 'Email Log',
            'laca-security' => 'Bảo mật',
            'laca-security-audit' => 'Kiểm tra bảo mật',
            'laca-security-fim' => 'Giám sát file',
            'laca-security-malware' => 'Quét mã độc',
            'laca-security-users' => 'User ẩn',
            'laca-security-login' => 'URL đăng nhập',
            'laca-security-2fa' => '2FA TOTP',
            'laca-recaptcha' => 'Google reCAPTCHA',
            'laca-login-socials' => 'Login Socials',
            'laca-dynamic-cpt' => 'Custom Post Types',
            'laca-contact-forms' => 'Form Liên Hệ',
            'laca-block-sync' => '🧩 LacaDev',
            'lacadev-block-categories' => 'Block Categories',
            'laca-tracker' => '📡 Tracker',
            'laca-project-notifications' => 'LacaDev PM & Bots',
            'laca-exit-popup' => 'Exit Popup',
            'laca-chatbot' => 'Chatbot',
        ];

        return $labels[$slug] ?? $slug;
    }

    private function isLacaAdminRequest(): bool
    {
        $currentPage = $this->getCurrentPageSlug();
        if ($currentPage === '') {
            return false;
        }

        foreach ($this->getNavigationGroups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['slug'] === $currentPage) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getCurrentPageSlug(): string
    {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));

        if ($page !== 'laca-security') {
            return $page;
        }

        $tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'audit'));

        foreach (self::SECURITY_TABS as $slug => $config) {
            if ($config['tab'] === $tab) {
                return $slug;
            }
        }

        return 'laca-security-audit';
    }
}
