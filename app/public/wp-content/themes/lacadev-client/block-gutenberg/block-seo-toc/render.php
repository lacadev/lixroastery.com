<?php
/**
 * Render template for lacadev/seo-toc-block & rank-math/toc-block
 */
defined('ABSPATH') || exit;

use App\Features\LacaSeo\Core\Settings;

$title = !empty($attributes['title']) ? $attributes['title'] : Settings::get('general.toc_block_title', __('Mục lục', 'lacadev'));
$titleWrapper = !empty($attributes['titleWrapper']) ? tag_escape($attributes['titleWrapper']) : 'h2';
$listStyle = !empty($attributes['listStyle']) ? $attributes['listStyle'] : Settings::get('general.toc_block_list_style', 'ul');
$listTag = ($listStyle === 'ol') ? 'ol' : 'ul';

$classes = ['lacadev-seo-toc', 'wp-block-rank-math-toc-block'];
if (!empty($attributes['className'])) {
    $classes[] = esc_attr($attributes['className']);
}

// Generate headings list if available or from post content
$headings = $attributes['headings'] ?? [];
if (empty($headings)) {
    global $post;
    if ($post instanceof \WP_Post) {
        $exclude = Settings::get('general.toc_block_exclude_headings', []);
        if (preg_match_all('~<h([2-6])([^>]*)>(.*?)</h\1>~i', $post->post_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $level = 'h' . $m[1];
                if (in_array($level, $exclude, true)) {
                    continue;
                }
                $headingText = wp_strip_all_tags($m[3]);
                $slug = sanitize_title($headingText);
                $headings[] = [
                    'level'   => (int) $m[1],
                    'content' => $headingText,
                    'link'    => '#' . $slug,
                ];
            }
        }
    }
}
?>
<div id="rank-math-toc" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <<?php echo $titleWrapper; ?> class="lacadev-toc-title"><?php echo esc_html($title); ?></<?php echo $titleWrapper; ?>>
    <nav>
        <<?php echo $listTag; ?>>
            <?php foreach ($headings as $h):
                if (!empty($h['disable'])) {
                    continue;
                }
            ?>
                <li>
                    <a href="<?php echo esc_url($h['link'] ?? '#'); ?>">
                        <?php echo esc_html($h['content'] ?? ''); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </<?php echo $listTag; ?>>
    </nav>
</div>
