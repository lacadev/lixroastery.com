<?php
/**
 * Render template for lacadev/seo-faq-block & rank-math/faq-block
 */
defined('ABSPATH') || exit;

$questions = $attributes['questions'] ?? [];
if (empty($questions) || !is_array($questions)) {
    return '';
}

$titleWrapper = !empty($attributes['titleWrapper']) ? tag_escape($attributes['titleWrapper']) : 'h3';
$listStyle = $attributes['listStyle'] ?? '';
$listTag = ($listStyle === 'numbered') ? 'ol' : (($listStyle === 'unordered') ? 'ul' : 'div');
$itemTag = ($listTag === 'div') ? 'div' : 'li';
$mainTitle = !empty($attributes['title']) ? $attributes['title'] : '';

$classes = ['lacadev-seo-faq', 'rank-math-block'];
if (!empty($attributes['className'])) {
    $classes[] = esc_attr($attributes['className']);
}
?>
<div id="rank-math-faq" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if (!empty($mainTitle)): ?>
        <h2 class="lacadev-faq-main-title"><?php echo esc_html($mainTitle); ?></h2>
    <?php endif; ?>
    <<?php echo $listTag; ?> class="rank-math-list lacadev-faq-list">
        <?php foreach ($questions as $q):
            if (empty($q['title']) || empty($q['content']) || (isset($q['visible']) && !$q['visible'])) {
                continue;
            }
            $id = !empty($q['id']) ? esc_attr($q['id']) : 'faq-' . md5($q['title']);
        ?>
            <<?php echo $itemTag; ?> id="<?php echo $id; ?>" class="rank-math-list-item lacadev-faq-item">
                <<?php echo $titleWrapper; ?> class="rank-math-question lacadev-faq-question"><?php echo wp_kses_post($q['title']); ?></<?php echo $titleWrapper; ?>>
                <div class="rank-math-answer lacadev-faq-answer">
                    <?php if (!empty($q['imageUrl'])): ?>
                        <img src="<?php echo esc_url($q['imageUrl']); ?>" alt="<?php echo esc_attr(wp_strip_all_tags($q['title'])); ?>" />
                    <?php endif; ?>
                    <p><?php echo wp_kses_post($q['content']); ?></p>
                </div>
            </<?php echo $itemTag; ?>>
        <?php endforeach; ?>
    </<?php echo $listTag; ?>>
</div>
