<?php

namespace App\Settings\Security;

/**
 * Xoá readme.html / license.txt ở gốc site — 2 file tĩnh của WordPress core lộ phiên bản
 * cho bot quét lỗ hổng. File tĩnh do web server trả thẳng (PHP không chặn được), sửa
 * .htaccess thì rủi ro lỗi 500 toàn site → xoá file là cách an toàn nhất.
 *
 * WP tạo lại 2 file mỗi lần cập nhật core → xoá lại sau upgrader_process_complete, và kiểm
 * tra 1 lần/ngày (khi có admin vào wp-admin). Deploy (FTP) đã loại trừ 2 file này nên không
 * tạo lại. Không ảnh hưởng chức năng: WordPress không đọc 2 file này khi chạy.
 */
final class CoreInfoFileCleanup
{
    public const FILES = ['readme.html', 'license.txt'];
    private const CHECK_TRANSIENT = 'laca_core_info_files_checked';

    public function register(): void
    {
        add_action('admin_init', [$this, 'dailyCheck']);
        add_action('upgrader_process_complete', [$this, 'afterCoreUpdate'], 30, 2);
    }

    public function dailyCheck(): void
    {
        if (get_transient(self::CHECK_TRANSIENT)) {
            return;
        }
        set_transient(self::CHECK_TRANSIENT, 1, DAY_IN_SECONDS);
        self::cleanup();
    }

    /**
     * @param \WP_Upgrader $upgrader
     * @param array        $extra
     */
    public function afterCoreUpdate($upgrader, $extra): void
    {
        if ('core' === ($extra['type'] ?? '')) {
            self::cleanup();
        }
    }

    /**
     * @return string[] file đã xoá
     */
    public static function cleanup(): array
    {
        $deleted = [];
        foreach (self::FILES as $file) {
            $path = ABSPATH . $file;
            if (is_file($path) && is_writable($path) && @unlink($path)) {
                $deleted[] = $file;
            }
        }
        return $deleted;
    }

    /**
     * @return string[] file còn tồn tại (vd không có quyền xoá)
     */
    public static function remaining(): array
    {
        return array_values(array_filter(self::FILES, static fn ($f) => is_file(ABSPATH . $f)));
    }
}
