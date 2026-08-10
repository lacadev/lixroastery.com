<?php

namespace App\Settings\LacaTools;

use App\Settings\LacaTools\Management\ContentAuditService;
use App\Settings\LacaTools\Management\MediaService;
use App\Settings\LacaTools\Management\DashboardWidgets;
use App\Settings\LacaTools\Management\ListTableEnhancements;
use App\Settings\LacaTools\Management\AdminUxService;
use App\Settings\LacaTools\Management\DatabaseCleaner;
use App\Settings\LacaTools\Management\QuickNotesWidget;

/**
 * ManagementExperience
 *
 * Bootloader (façade) that wires all Management sub-services together.
 * Business logic lives in the Management/ sub-namespace:
 *   - ContentAuditService  — weekly cron + health audit
 *   - MediaService         — media library stats & orphan detection
 *   - DashboardWidgets     — 6 WordPress dashboard widgets
 *   - ListTableEnhancements— Views column, ID columns, duplication
 *   - AdminUxService       — Help menu, merchant simplification
 */
class ManagementExperience
{
    public function __construct()
    {
        // AI Chat — phải khởi tạo trước is_admin() vì REST request không phải admin context.
        // Constructor chỉ đăng ký hooks (rest_api_init, admin_enqueue_scripts) — an toàn ở mọi context.
        new AIChatHandler();

        if (!is_admin()) {
            return;
        }

        // 1. Shared data services (no WordPress hooks themselves)
        $auditService = new ContentAuditService();
        $mediaService = new MediaService();

        // 2. Dashboard widgets — depends on both data services
        $widgets = new DashboardWidgets($auditService, $mediaService);
        $widgets->register();

        // 3. List-table enhancements (Views column, IDs, duplication)
        $listTable = new ListTableEnhancements($auditService);
        $listTable->register();

        // 4. Navigation & UX simplification
        $adminUx = new AdminUxService();
        $adminUx->register();

        // 5. Background audit scheduler
        $auditService->register();

        // 6. Media orphan filtering
        $mediaService->register();

        // 7. Quick Notes dashboard widget
        (new QuickNotesWidget())->register();

        // 8. Database Cleaner (Appearance > Dọn dẹp DB)
        (new DatabaseCleaner())->register();

        // 9. AI Translation Manager
        add_action('init', function () {
            new AITranslationManager();
        });

        // 10. Dynamic CPT admin page (Appearance > Custom Post Types)
        new \App\Features\DynamicCPT\DynamicCptAdminPage();

        // 11. Contact Form Manager (Appearance > Form Liên Hệ)
        new \App\Features\ContactForm\ContactFormManager();
    }
}
