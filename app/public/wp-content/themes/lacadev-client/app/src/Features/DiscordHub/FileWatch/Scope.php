<?php

namespace App\Features\DiscordHub\FileWatch;

/**
 * Phạm vi giám sát file — đường dẫn luôn TƯƠNG ĐỐI với ABSPATH (giống đường dẫn
 * trong repo git tính từ app/public/, để so khớp được với manifest của GitHub Actions).
 *
 * ⚠️ Danh sách đuôi file phải khớp với bước "Manifest" trong .github/workflows/deploy.yml.
 */
final class Scope
{
    /** File có thể chứa code chạy được — chỉ những file này mới được băm/giám sát. */
    public const CODE_EXTENSIONS = ['php', 'phtml', 'phar', 'pht', 'php3', 'php4', 'php5', 'php7', 'php8', 'inc', 'js', 'ini', 'sh', 'py', 'pl', 'cgi'];

    /** Thư mục code: giám sát mọi file code bên trong. */
    public const CODE_DIRS = ['wp-content/themes', 'wp-content/plugins', 'wp-content/mu-plugins'];

    /** uploads/ chỉ chứa media — BẤT KỲ file thực thi nào xuất hiện ở đây đều là dấu hiệu bị tấn công. */
    public const UPLOADS_DIR = 'wp-content/uploads';
    public const UPLOADS_DANGEROUS = ['php', 'phtml', 'phar', 'pht', 'php3', 'php4', 'php5', 'php7', 'php8', 'inc', 'sh', 'py', 'pl', 'cgi', 'shtml'];

    /** Thư mục bỏ qua ở mọi cấp (build tool, VCS). */
    private const SKIP_DIR_NAMES = ['node_modules', '.git', '.svn'];

    public static function abs(string $relative): string
    {
        return wp_normalize_path(ABSPATH . ltrim($relative, '/'));
    }

    public static function isCodeFile(string $relative): bool
    {
        $name = basename($relative);
        if ('.htaccess' === $name || '.user.ini' === $name) {
            return true;
        }
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::CODE_EXTENSIONS, true);
    }

    /**
     * File này có thuộc phạm vi giám sát không (dùng để lọc manifest deploy, đường dẫn File Editor...).
     */
    public static function covers(string $relative): bool
    {
        $relative = ltrim(wp_normalize_path($relative), '/');
        if ('' === $relative || str_contains($relative, '../')) {
            return false;
        }
        foreach (explode('/', $relative) as $segment) {
            if (in_array($segment, self::SKIP_DIR_NAMES, true)) {
                return false;
            }
        }
        if (str_starts_with($relative, self::UPLOADS_DIR . '/')) {
            return self::isDangerousUpload($relative);
        }
        if (!str_contains($relative, '/') || preg_match('#^wp-content/[^/]+$#', $relative)) {
            return self::isCodeFile($relative); // file gốc (wp-config.php, .htaccess...) và drop-in wp-content/*.php
        }
        foreach (self::CODE_DIRS as $dir) {
            if (str_starts_with($relative, $dir . '/')) {
                return self::isCodeFile($relative);
            }
        }
        return false;
    }

    public static function isDangerousUpload(string $relative): bool
    {
        $name = basename($relative);
        return '.htaccess' === $name || in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::UPLOADS_DANGEROUS, true);
    }

    /**
     * Duyệt mọi file trong phạm vi (hoặc chỉ 1 thư mục con nếu truyền $onlyDir).
     *
     * @return \Generator<string,string> relative => absolute
     */
    public static function walk(?string $onlyDir = null): \Generator
    {
        $root = wp_normalize_path(ABSPATH);

        if (null !== $onlyDir) {
            yield from self::walkDir($root, trim(wp_normalize_path($onlyDir), '/'));
            return;
        }

        // File gốc + drop-in wp-content/*.php (không đệ quy).
        foreach (['', 'wp-content/'] as $prefix) {
            foreach ((array) @scandir($root . $prefix) as $name) {
                $abs = $root . $prefix . $name;
                $rel = $prefix . $name;
                if ('.' !== $name && '..' !== $name && is_file($abs) && !is_link($abs) && self::covers($rel)) {
                    yield $rel => $abs;
                }
            }
        }

        foreach (array_merge(self::CODE_DIRS, [self::UPLOADS_DIR]) as $dir) {
            yield from self::walkDir($root, $dir);
        }
    }

    private static function walkDir(string $root, string $relDir): \Generator
    {
        $absDir = $root . $relDir;
        if (!is_dir($absDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $item) {
                    if ($item->isLink()) {
                        return false; // không đi theo symlink (vòng lặp / ra ngoài site)
                    }
                    return !$item->isDir() || !in_array($item->getFilename(), self::SKIP_DIR_NAMES, true);
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD // thư mục không có quyền đọc → bỏ qua, không ném lỗi
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }
            $abs = wp_normalize_path($item->getPathname());
            $rel = substr($abs, strlen($root));
            if (self::covers($rel)) {
                yield $rel => $abs;
            }
        }
    }
}
