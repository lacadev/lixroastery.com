<?php
/**
 * Render template for lacadev/seo-howto-block & rank-math/howto-block
 */
defined('ABSPATH') || exit;

$steps = $attributes['steps'] ?? [];
if (empty($steps) || !is_array($steps)) {
    return '';
}

$title = !empty($attributes['title']) ? $attributes['title'] : '';
$description = !empty($attributes['description']) ? $attributes['description'] : '';

$classes = ['lacadev-seo-howto', 'rank-math-howto-block'];
if (!empty($attributes['className'])) {
    $classes[] = esc_attr($attributes['className']);
}
?>
<div id="rank-math-howto" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if (!empty($title)): ?>
        <h2 class="rank-math-howto-title lacadev-howto-title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if (!empty($description)): ?>
        <p class="rank-math-howto-description lacadev-howto-desc"><?php echo wp_kses_post($description); ?></p>
    <?php endif; ?>

    <ol class="rank-math-howto-steps lacadev-howto-steps">
        <?php foreach ($steps as $s):
            if (empty($s['title']) || empty($s['content']) || (isset($s['visible']) && !$s['visible'])) {
                continue;
            }
            $id = !empty($s['id']) ? esc_attr($s['id']) : 'step-' . md5($s['title']);
        ?>
            <li id="<?php echo $id; ?>" class="rank-math-howto-step lacadev-howto-step">
                <h3 class="rank-math-howto-step-title lacadev-howto-step-title"><?php echo wp_kses_post($s['title']); ?></h3>
                <div class="rank-math-howto-step-content lacadev-howto-step-content">
                    <p><?php echo wp_kses_post($s['content']); ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
