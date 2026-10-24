<?php

namespace App\Settings\LacaAdmin;

/**
 * Hiển thị thanh tab dạng viên thuốc (Pill Navigation Tabs) ở đầu nội dung trang
 * cho các nhóm trang chức năng trong Laca Admin.
 */
class LacaAdminTabBar
{
    /**
     * @var array<string, array{title:string, pages:string[], tabs:array<string, array{label:string, url:string}>}>
     */
    private const TAB_GROUPS = [
        'performance' => [
            'title' => 'Hiệu năng & bảo trì',
            'pages' => ['laca-tools', 'laca-db-cleaner', 'laca-email-log'],
            'tabs'  => [
                'laca-tools' => [
                    'label' => 'Tối ưu hóa',
                    'url'   => 'admin.php?page=laca-tools',
                ],
                'laca-db-cleaner' => [
                    'label' => 'Dọn dẹp Database',
                    'url'   => 'admin.php?page=laca-db-cleaner',
                ],
                'laca-email-log' => [
                    'label' => 'Nhật ký Email',
                    'url'   => 'admin.php?page=laca-email-log',
                ],
            ],
        ],
        'google' => [
            'title' => 'Dịch vụ Google',
            'pages' => ['laca-recaptcha', 'laca-login-socials'],
            'tabs'  => [
                'laca-recaptcha' => [
                    'label' => 'Google reCAPTCHA',
                    'url'   => 'admin.php?page=laca-recaptcha',
                ],
                'laca-login-socials' => [
                    'label' => 'Đăng nhập Google & MXH',
                    'url'   => 'admin.php?page=laca-login-socials',
                ],
                'google-analytics' => [
                    'label' => 'Google Analytics & GTM',
                    'url'   => 'admin.php?page=laca-seo&view=general&tab=analytics',
                ],
                'google-search-console' => [
                    'label' => 'Google Search Console',
                    'url'   => 'admin.php?page=laca-seo&view=search-console',
                ],
            ],
        ],
        'sync' => [
            'title' => 'Kết nối LacaDev',
            'pages' => ['laca-block-sync', 'lacadev-block-categories', 'laca-tracker'],
            'tabs'  => [
                'laca-block-sync' => [
                    'label' => 'Đồng bộ Block',
                    'url'   => 'admin.php?page=laca-block-sync',
                ],
                'lacadev-block-categories' => [
                    'label' => 'Danh mục Block',
                    'url'   => 'admin.php?page=lacadev-block-categories',
                ],
                'laca-tracker' => [
                    'label' => 'Site Tracker',
                    'url'   => 'admin.php?page=laca-tracker',
                ],
            ],
        ],
        'projects' => [
            'title' => 'Dự án & thông báo',
            'pages' => ['laca-project-notifications', 'laca-discord-hub'],
            'tabs'  => [
                'laca-project-notifications' => [
                    'label' => 'Zalo OA (PM & Bots)',
                    'url'   => 'admin.php?page=laca-project-notifications',
                ],
                'laca-discord-hub' => [
                    'label' => 'Discord Hub',
                    'url'   => 'admin.php?page=laca-discord-hub',
                ],
            ],
        ],
        'marketing' => [
            'title' => 'Marketing & AI',
            'pages' => ['laca-exit-popup', 'laca-chatbot'],
            'tabs'  => [
                'laca-exit-popup' => [
                    'label' => 'Exit Popup',
                    'url'   => 'admin.php?page=laca-exit-popup',
                ],
                'laca-chatbot' => [
                    'label' => 'Chatbot',
                    'url'   => 'admin.php?page=laca-chatbot',
                ],
            ],
        ],
    ];

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('all_admin_notices', [$this, 'renderTabBar'], 25);
    }

    public function renderTabBar(): void
    {
        $currentPage = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if ($currentPage === '') {
            return;
        }

        foreach (self::TAB_GROUPS as $group) {
            if (in_array($currentPage, $group['pages'], true)) {
                $this->outputTabs($group['tabs'], $currentPage, $group['title']);
                return;
            }
        }
    }

    /**
     * @param array<string, array{label:string, url:string}> $tabs
     */
    private function outputTabs(array $tabs, string $currentPage, string $title): void
    {
        ?>
        <div class="laca-shared-tabs-wrapper" style="margin: 12px 0 20px 0;">
            <nav class="nav-tab-wrapper laca-shared-tabs" aria-label="<?php echo esc_attr($title); ?>">
                <?php foreach ($tabs as $slug => $tab): ?>
                    <a
                        href="<?php echo esc_url(admin_url($tab['url'])); ?>"
                        class="nav-tab<?php echo $currentPage === $slug ? ' nav-tab-active' : ''; ?>"
                    >
                        <?php echo esc_html($tab['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }
}
