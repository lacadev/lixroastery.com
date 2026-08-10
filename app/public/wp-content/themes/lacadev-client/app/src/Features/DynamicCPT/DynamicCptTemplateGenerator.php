<?php

namespace App\Features\DynamicCPT;

/**
 * DynamicCptTemplateGenerator
 *
 * Tự động sinh file template archive-{slug}.php và single-{slug}.php
 * bằng cách copy từ archive.php / single.php gốc của theme.
 * Chỉ tạo nếu file chưa tồn tại để tránh ghi đè customization.
 */
class DynamicCptTemplateGenerator
{
    private string $themeDir;

    public function __construct()
    {
        // Dùng __DIR__ để lấy đường dẫn tuyệt đối, tránh get_template_directory()
        // trả sai path trong context admin-post.php (ví dụ Local by Flywheel / Valet).
        // DynamicCPT/ → Features/ → src/ → app/ → lacadev/ (theme root)
        $this->themeDir = realpath(__DIR__ . '/../../../../');
    }

    /**
     * Sinh cả 2 template cho một CPT slug.
     *
     * @return array{archive: bool, single: bool}
     */
    public function generate(string $slug): array
    {
        return [
            'archive' => $this->generateArchive($slug),
            'single'  => $this->generateSingle($slug),
        ];
    }

    /**
     * Xoá template files khi CPT bị xoá.
     */
    public function delete(string $slug): void
    {
        $files = [
            $this->themeDir . '/theme/archive-' . $slug . '.php',
            $this->themeDir . '/theme/single-'  . $slug . '.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                unlink($file);
            }
        }
    }

    /**
     * Kiểm tra template đã tồn tại chưa.
     *
     * @return array{archive: bool, single: bool}
     */
    public function exists(string $slug): array
    {
        return [
            'archive' => file_exists($this->themeDir . '/theme/archive-' . $slug . '.php'),
            'single'  => file_exists($this->themeDir . '/theme/single-'  . $slug . '.php'),
        ];
    }

    private function generateArchive(string $slug): bool
    {
        $dest = $this->themeDir . '/theme/archive-' . $slug . '.php';

        if (file_exists($dest)) {
            return true;
        }

        $source = $this->themeDir . '/theme/archive.php';
        if (!file_exists($source)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents($source);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return file_put_contents($dest, $content) !== false;
    }

    private function generateSingle(string $slug): bool
    {
        $dest = $this->themeDir . '/theme/single-' . $slug . '.php';

        if (file_exists($dest)) {
            return true;
        }

        $source = $this->themeDir . '/theme/single.php';
        if (!file_exists($source)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents($source);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return file_put_contents($dest, $content) !== false;
    }
}
