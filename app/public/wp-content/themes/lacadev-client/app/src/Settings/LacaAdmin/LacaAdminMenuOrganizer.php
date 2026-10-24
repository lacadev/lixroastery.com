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
     * @var array<string,array{label:string,items:string[]}>
     */
    private const GROUPS = [
        'general' => [
            'label' => 'Tổng quan',
            'items' => [
                'laca-admin',
                'laca-management-settings',
            ],
        ],
        'performance' => [
            'label' => 'Hiệu năng & bảo trì',
            'items' => [
                'laca-tools',
            ],
        ],
        'security' => [
            'label' => 'Bảo mật',
            'items' => [
                'laca-security',
            ],
        ],
        'google' => [
            'label' => 'Dịch vụ Google',
            'items' => [
                'laca-recaptcha',
            ],
        ],
        'content' => [
            'label' => 'Nội dung & Form',
            'items' => [
                'laca-dynamic-cpt',
                'laca-contact-forms',
            ],
        ],
        'sync' => [
            'label' => 'Kết nối LacaDev',
            'items' => [
                'laca-block-sync',
            ],
        ],
        'projects' => [
            'label' => 'Dự án & thông báo',
            'items' => [
                'laca-project-notifications',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing & AI',
            'items' => [
                'laca-exit-popup',
            ],
        ],
    ];

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        (new LacaAdminTabBar())->register();

        add_action('admin_menu', [$this, 'organize'], PHP_INT_MAX);
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
                    'items' => $groupItems,
                ];
            }
        }

        // Giữ lại các trang con trong WP Submenu để WordPress cho phép truy cập quyền,
        // nhưng không đưa vào Dock bên trái.
        if ($unassigned !== []) {
            array_push($organized, ...array_values($unassigned));
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
        $fallback = $this->getFallbackItemLabel($slug);
        $label = ($fallback !== $slug) ? $fallback : wp_strip_all_tags((string) ($item[0] ?? $slug));

        return $this->buildNavigationItemFromSlug($slug, $label);
    }

    /**
     * @return array{label:string,slug:string,url:string}
     */
    private function buildNavigationItemFromSlug(string $slug, ?string $label = null): array
    {
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
            <!-- Mobile / Tablet select (< 1024px) -->
            <div class="laca-admin-dock__mobile">
                <label class="laca-admin-dock__mobile-label" for="laca-admin-dock-select"><?php echo esc_html__('Menu Laca Admin', 'laca'); ?></label>
                <select id="laca-admin-dock-select" class="laca-admin-dock__select" onchange="if (this.value) window.location.href=this.value;">
                    <?php foreach ($groups as $group): ?>
                        <optgroup label="<?php echo esc_attr($group['label']); ?>">
                            <?php foreach ($group['items'] as $item): ?>
                                <option value="<?php echo esc_url($item['url']); ?>" <?php selected($currentPage, $item['slug']); ?>>
                                    <?php echo esc_html($item['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Desktop columnar dock (>= 1024px) -->
            <div class="laca-admin-dock__desktop">
                <?php foreach ($groups as $group): ?>
                    <section class="laca-admin-dock__group laca-admin-dock__group--<?php echo esc_attr($group['key']); ?>" aria-labelledby="laca-admin-dock-group-<?php echo esc_attr($group['key']); ?>">
                        <h2 class="laca-admin-dock__group-title" id="laca-admin-dock-group-<?php echo esc_attr($group['key']); ?>">
                            <span><?php echo esc_html($group['label']); ?></span>
                        </h2>
                        <div class="laca-admin-dock__items">
                            <?php foreach ($group['items'] as $item): ?>
                                <a
                                    class="laca-admin-dock__item laca-admin-dock__item--<?php echo esc_attr($item['slug']); ?><?php echo $currentPage === $item['slug'] ? ' is-active' : ''; ?>"
                                    href="<?php echo esc_url($item['url']); ?>"
                                    <?php echo $currentPage === $item['slug'] ? 'aria-current="page"' : ''; ?>
                                >
                                    <span class="laca-admin-dock__indicator" aria-hidden="true"></span>
                                    <span class="laca-admin-dock__item-text"><?php echo esc_html($item['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php
    }

    /**
     * @return array<int,array{key:string,label:string,items:array<int,array{label:string,slug:string,url:string}>}>
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
                'items' => $items,
            ];
        }

        return $this->navigationGroups = $groups;
    }

    private function getFallbackItemLabel(string $slug): string
    {
        $labels = [
            'laca-admin'                 => 'Cấu hình chung',
            'laca-management-settings'   => 'Quản trị & HD Sử dụng',
            'laca-tools'                 => 'Hiệu năng & Bảo trì',
            'laca-security'              => 'Bảo mật',
            'laca-recaptcha'             => 'Dịch vụ Google',
            'laca-dynamic-cpt'           => 'Custom Post Types',
            'laca-contact-forms'         => 'Form Liên Hệ',
            'laca-block-sync'            => 'Kết nối LacaDev',
            'laca-project-notifications' => 'Dự án & Thông báo',
            'laca-exit-popup'            => 'Marketing & AI',
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

        $map = [
            'laca-db-cleaner'          => 'laca-tools',
            'laca-email-log'           => 'laca-tools',
            'laca-login-socials'       => 'laca-recaptcha',
            'lacadev-block-categories' => 'laca-block-sync',
            'laca-tracker'             => 'laca-block-sync',
            'laca-discord-hub'         => 'laca-project-notifications',
            'laca-chatbot'             => 'laca-exit-popup',
        ];

        return $map[$page] ?? $page;
    }
}
