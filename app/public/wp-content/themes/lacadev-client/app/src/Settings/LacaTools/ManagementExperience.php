<?php

namespace App\Settings\LacaTools;

use App\Settings\LacaTools\Management\ContentAuditService;
use App\Settings\LacaTools\Management\MediaService;
use App\Settings\LacaTools\Management\DashboardWidgets;
use App\Settings\LacaTools\Management\ListTableEnhancements;
use App\Settings\LacaTools\Management\ListTableInlineEditor;
use App\Settings\LacaTools\Management\AdminUxService;
use App\Settings\LacaTools\Management\DatabaseCleaner;
use App\Settings\LacaTools\Management\QuickNotesWidget;
use App\Settings\LacaTools\Management\ConfigExportImport;
use App\Settings\LacaTools\Management\ConfigChangeLog;
use App\Settings\LacaTools\Management\ConfigPage;
use App\Settings\LacaTools\Management\SiteConfig\SiteConfigExporter;
use App\Settings\LacaTools\Management\SiteConfig\SiteConfigImporter;
use App\Settings\LacaTools\Management\SystemInfoPage;
use App\Settings\LacaTools\Management\LargeFileScanner;

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

        // 3b. List-table horizontal scroll & inline editing (Product & all CPTs)
        $inlineEditor = new ListTableInlineEditor();
        $inlineEditor->register();

        // 4. Navigation & UX simplification
        $adminUx = new AdminUxService();
        $adminUx->register();

        // 5. Background audit scheduler
        $auditService->register();

        // 6. Media orphan filtering
        $mediaService->register();

        // 7. Quick Notes dashboard widget
        (new QuickNotesWidget())->register();

        // 8. Database Cleaner
        (new DatabaseCleaner())->register();

        // 9. Config Export/Import (Bộ cấu hình site & legacy handler)
        (new ConfigExportImport())->register();
        (new SiteConfigExporter())->register();
        (new SiteConfigImporter())->register();

        // 10. Config Change Log (nhật ký thay đổi cấu hình)
        (new ConfigChangeLog())->register();

        // 10.5. Config Admin Page (Laca Admin → Cấu hình)
        (new ConfigPage())->register();

        // 10.6. Laca Dashboard (Laca Admin → Tổng quan)
        if (class_exists(\App\Settings\LacaAdmin\LacaDashboard::class)) {
            (new \App\Settings\LacaAdmin\LacaDashboard())->register();
        }

        // 10.7. System Info & Storage Page (Laca Admin → Hệ thống)
        (new SystemInfoPage())->register();

        // 10.8. Large File Scanner (quét file lớn theo lô & xoá an toàn)
        (new LargeFileScanner())->register();

        // 9. AI Translation Manager
        add_action('init', function () {
            new AITranslationManager();
        });

        // 9.5. Project Reports — chart.js data provider (Hub-only if present)
        if (class_exists(ProjectReportsManager::class)) {
            new ProjectReportsManager();
        }

        // 10. Dynamic CPT admin page
        if (class_exists(\App\Features\DynamicCPT\DynamicCptAdminPage::class)) {
            new \App\Features\DynamicCPT\DynamicCptAdminPage();
        }

        // 10.5. Thu gọn/mở rộng danh mục con trên edit-tags.php (mọi taxonomy phân cấp)
        if (class_exists(\App\Features\DynamicCPT\CollapsibleTaxonomyTerms::class)) {
            (new \App\Features\DynamicCPT\CollapsibleTaxonomyTerms())->register();
        }

        // 11. Contact Form Manager
        if (class_exists(\App\Features\ContactForm\ContactFormManager::class)) {
            new \App\Features\ContactForm\ContactFormManager();
        }
    }
}
