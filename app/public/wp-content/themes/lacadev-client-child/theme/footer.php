<?php

/**
 * Theme footer partial.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */

/**
 * Icon SVG inline dùng riêng cho footer.php (mạng xã hội) — không phụ thuộc
 * font/icon-library ngoài, tránh lặp lại lỗi Material Symbols từng gặp ở
 * site khác (font không tải được, chỉ hiện chữ thô thay vì icon).
 */
if (!function_exists('lix_footer_social_icon')) {
    function lix_footer_social_icon(string $name): string
    {
        $icons = [
            'facebook'  => '<path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"></path>',
            'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" fill="none" stroke="currentColor" stroke-width="2"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke="currentColor" stroke-width="2"></line>',
            'tiktok'    => '<path d="M16.6 5.82A4.28 4.28 0 0 1 15.83 3h-3.3v13.6a2.6 2.6 0 1 1-1.83-2.48V10.7a5.8 5.8 0 1 0 5.13 5.76V9.4a7.5 7.5 0 0 0 4.17 1.27V7.4a4.27 4.27 0 0 1-3.4-1.58z"></path>',
            'youtube'   => '<path d="M22 12s0-3.2-.4-4.7a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.5a2.5 2.5 0 0 0-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.7a2.5 2.5 0 0 0 1.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.5a2.5 2.5 0 0 0 1.8-1.8c.4-1.5.4-4.7.4-4.7z" fill="none" stroke="currentColor" stroke-width="2"></path><polygon points="10 15 15 12 10 9 10 15"></polygon>',
            'linkedin'  => '<path d="M4.98 3.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM3 9h4v12H3zM9 9h3.8v1.64h.05c.53-1 1.83-2.06 3.77-2.06C20.9 8.58 21 11.36 21 14.2V21h-4v-6.14c0-1.46-.03-3.34-2.04-3.34-2.04 0-2.35 1.6-2.35 3.24V21H9z"></path>',
            'zalo'      => '<rect x="2" y="2" width="20" height="20" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"></rect><path d="M6.5 16.5h5M8 16.5V9l-3 4M13 9h4M15 9v7.5M13 13h3.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"></path>',
        ];

        if (!isset($icons[$name])) {
            return '';
        }

        return '<svg class="footer__social-icon" width="1em" height="1em" style="width:1em;height:1em;" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
    }
}

$footer_company_name = getOption('footer_company_name');
$footer_business_reg = getOption('footer_business_reg');
$footer_address       = getOption('address');
$footer_email         = getOption('email');

$footer_links_col1 = getOption('footer_links_col1');
$footer_links_col1 = is_array($footer_links_col1) ? $footer_links_col1 : [];
$footer_links_col2 = getOption('footer_links_col2');
$footer_links_col2 = is_array($footer_links_col2) ? $footer_links_col2 : [];
$footer_badges      = getOption('footer_badges');
$footer_badges      = is_array($footer_badges) ? $footer_badges : [];

$footer_socials = [
    'facebook'  => getOption('facebook'),
    'instagram' => getOption('instagram'),
    'tiktok'    => getOption('tiktok'),
    'youtube'   => getOption('youtube'),
    'linkedin'  => getOption('linkedin'),
    'zalo'      => getOption('zalo'),
];
?>
<footer class="footer" role="contentinfo" data-aos="fade-up">
    <div class="container-fluid">
        <hr class="footer__rule" />

        <div class="footer__grid">
            <div class="footer__col footer__col--company">
                <ul class="footer__list">
                    <?php if ($footer_company_name) : ?>
                        <li><?php echo esc_html($footer_company_name); ?></li>
                    <?php endif; ?>
                    <?php if ($footer_business_reg) : ?>
                        <li><?php echo esc_html($footer_business_reg); ?></li>
                    <?php endif; ?>
                    <?php if ($footer_address) : ?>
                        <li><?php echo esc_html($footer_address); ?></li>
                    <?php endif; ?>
                    <?php if ($footer_email) : ?>
                        <li><a href="mailto:<?php echo esc_attr($footer_email); ?>"><?php echo esc_html($footer_email); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if (!empty($footer_links_col1)) : ?>
                <div class="footer__col">
                    <ul class="footer__list">
                        <?php foreach ($footer_links_col1 as $item) :
                            if (empty($item['name'])) {
                                continue;
                            }
                        ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($footer_links_col2)) : ?>
                <div class="footer__col">
                    <ul class="footer__list">
                        <?php foreach ($footer_links_col2 as $item) :
                            if (empty($item['name'])) {
                                continue;
                            }
                        ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="footer__col footer__col--end">
                <?php if (!empty($footer_badges)) : ?>
                    <ul class="footer__list">
                        <?php foreach ($footer_badges as $item) :
                            if (empty($item['name'])) {
                                continue;
                            }
                        ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php
                $has_socials = array_filter($footer_socials);
                if (!empty($has_socials)) :
                ?>
                    <div class="footer__socials">
                        <?php foreach ($footer_socials as $network => $url) :
                            if (!$url) {
                                continue;
                            }
                        ?>
                            <a href="<?php echo esc_url($url); ?>" class="footer__social-link" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr(ucfirst($network)); ?>">
                                <?php echo lix_footer_social_icon($network); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
<!-- footer end -->

</div>
<!-- container-wrapper end -->


<?php wp_footer(); ?>
</body>

</html>
