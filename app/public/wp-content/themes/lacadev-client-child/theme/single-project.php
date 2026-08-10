<?php
	/**
	 * App Layout: layouts/app.php
	 *
	 * This is the template that is used for displaying all posts by default.
	 *
	 * @link    https://codex.wordpress.org/Template_Hierarchy
	 *
	 * @package WPEmergeTheme
	 */

	theBreadcrumb();
    $projectId      = get_the_ID();
    $investor       = carbon_get_post_meta($projectId, 'investor');
    $floors         = carbon_get_post_meta($projectId, 'floors');
    $location       = carbon_get_post_meta($projectId, 'location');
    $total_area     = carbon_get_post_meta($projectId, 'total_area');
    $house_area     = carbon_get_post_meta($projectId, 'house_area');
    $front_area     = carbon_get_post_meta($projectId, 'front_area');
    $execution_year = carbon_get_post_meta($projectId, 'execution_year');
    $design_type    = carbon_get_post_meta($projectId, 'design_type');
    $usage_function = carbon_get_post_meta($projectId, 'usage_function');
    $gallery_raw    = carbon_get_post_meta($projectId, 'project_gallery');

    // Build gallery images array with full data
    $gallery_images = [];
    if (!empty($gallery_raw) && is_array($gallery_raw)) {
        foreach ($gallery_raw as $item) {
            $img_id = isset($item['gallery_image']) ? intval($item['gallery_image']) : 0;
            if (!$img_id) continue;
            $full  = wp_get_attachment_image_src($img_id, 'full');
            $thumb = wp_get_attachment_image_src($img_id, 'large');
            if ($full) {
                $gallery_images[] = [
                    'id'          => $img_id,
                    'src'         => $full[0],
                    'width'       => $full[1],
                    'height'      => $full[2],
                    'thumb'       => $thumb ? $thumb[0] : $full[0],
                    'alt'         => get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: get_the_title(),
                ];
            }
        }
    }
?>

<article class="project-detail">
    <div class="container">
        <h1 class="project-detail__title"><?= get_the_title() ?></h1>

        <?php if (!empty($gallery_images)): ?>
        <!-- Gallery -->
        <div class="project-gallery" id="project-gallery">
            <div class="project-gallery__grid">
                <?php foreach ($gallery_images as $index => $img): ?>
                <a
                    href="<?= esc_url($img['src']) ?>"
                    class="project-gallery__item<?= $index === 0 ? ' project-gallery__item--featured' : '' ?>"
                    data-pswp-width="<?= esc_attr($img['width']) ?>"
                    data-pswp-height="<?= esc_attr($img['height']) ?>"
                    target="_blank"
                >
                    <img
                        src="<?= esc_url($img['thumb']) ?>"
                        alt="<?= esc_attr($img['alt']) ?>"
                        class="project-gallery__image"
                        loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                    />
                    <span class="project-gallery__overlay">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="project-gallery__zoom-icon" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="11" y1="8" x2="11" y2="14"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                        </svg>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content + Sidebar -->
        <div class="project-detail__layout">

            <!-- Main content -->
            <div class="project-detail__main">

                <!-- Top info bar: 4 key fields -->
                <div class="project-info-bar">
                    <?php if ($investor): ?>
                    <div class="project-info-bar__item">
                        <span class="project-info-bar__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <span class="project-info-bar__label">Chủ đầu tư</span>
                        <strong class="project-info-bar__value"><?= esc_html($investor) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($total_area): ?>
                    <div class="project-info-bar__item">
                        <span class="project-info-bar__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                        </span>
                        <span class="project-info-bar__label">Tổng diện tích:</span>
                        <strong class="project-info-bar__value"><?= esc_html($total_area) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($floors): ?>
                    <div class="project-info-bar__item">
                        <span class="project-info-bar__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 21h18M4 21V7l8-4 8 4v14"/><path d="M9 21V12h6v9"/></svg>
                        </span>
                        <span class="project-info-bar__label">Số tầng</span>
                        <strong class="project-info-bar__value"><?= esc_html($floors) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($location): ?>
                    <div class="project-info-bar__item">
                        <span class="project-info-bar__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <span class="project-info-bar__label">Địa chỉ</span>
                        <strong class="project-info-bar__value"><?= esc_html($location) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Detail table -->
                <table class="project-info-table">
                    <tbody>
                        <?php if ($execution_year): ?>
                        <tr class="project-info-table__row">
                            <td class="project-info-table__label">
                                <span class="project-info-table__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </span>
                                Năm thực hiện
                            </td>
                            <td class="project-info-table__value"><?= esc_html($execution_year) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($house_area): ?>
                        <tr class="project-info-table__row">
                            <td class="project-info-table__label">
                                <span class="project-info-table__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                                </span>
                                Diện tích nhà
                            </td>
                            <td class="project-info-table__value"><?= esc_html($house_area) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($front_area): ?>
                        <tr class="project-info-table__row">
                            <td class="project-info-table__label">
                                <span class="project-info-table__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 20h20M4 20V10l8-7 8 7v10"/></svg>
                                </span>
                                Mặt tiền
                            </td>
                            <td class="project-info-table__value"><?= esc_html($front_area) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($investor): ?>
                        <tr class="project-info-table__row">
                            <td class="project-info-table__label">
                                <span class="project-info-table__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                </span>
                                Đơn vị thực hiện
                            </td>
                            <td class="project-info-table__value"><?= esc_html($investor) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($usage_function): ?>
                        <tr class="project-info-table__row">
                            <td class="project-info-table__label">
                                <span class="project-info-table__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </span>
                                Công năng sử dụng
                            </td>
                            <td class="project-info-table__value"><?= wp_kses_post($usage_function) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($design_type): ?>
                        <tr class="project-info-table__row">
                            <td class="project-info-table__label">
                                <span class="project-info-table__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5l4 4L7 21l-4 1 1-4 12.5-14.5z"/></svg>
                                </span>
                                Loại thiết kế
                            </td>
                            <td class="project-info-table__value"><?= esc_html($design_type) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Post content (editor) -->
                <div class="project-detail__content">
                    <?php the_content(); ?>
                </div>
            </div>

            <!-- Sidebar -->
            <aside class="project-detail__sidebar">
                <div class="project-contact-box">
                    <h3 class="project-contact-box__title">Liên hệ tư vấn</h3>
                    <p class="project-contact-box__desc">Để lại thông tin liên hệ của bạn hoặc gọi vào số hotline để được tư vấn sớm nhất.</p>
                    <?php
                        // If you have a Contact Form 7 shortcode, replace 123 with your form ID
                        // echo do_shortcode('[contact-form-7 id="123" title="Project Contact"]');
                    ?>
                    <form class="project-contact-form" method="post">
                        <div class="project-contact-form__field">
                            <input type="text" name="contact_name" placeholder="Họ và tên" class="project-contact-form__input" required>
                        </div>
                        <div class="project-contact-form__field">
                            <input type="text" name="contact_address" placeholder="Địa chỉ" class="project-contact-form__input">
                        </div>
                        <div class="project-contact-form__field">
                            <input type="tel" name="contact_phone" placeholder="Số điện thoại" class="project-contact-form__input" required>
                        </div>
                        <button type="submit" class="project-contact-form__submit">Gửi thông tin ngay</button>
                    </form>
                </div>

                <!-- Related projects -->
                <?php
                $related_args = [
                    'post_type'      => 'project',
                    'posts_per_page' => 4,
                    'post__not_in'   => [$projectId],
                    'orderby'        => 'rand',
                ];
                $related = new WP_Query($related_args);
                if ($related->have_posts()):
                ?>
                <div class="project-related">
                    <h3 class="project-related__title">Dự án nổi bật</h3>
                    <ul class="project-related__list">
                    <?php while ($related->have_posts()): $related->the_post(); ?>
                        <li class="project-related__item">
                            <a href="<?= get_the_permalink() ?>" class="project-related__link">
                                <?php if (has_post_thumbnail()): ?>
                                <div class="project-related__thumb">
                                    <?= get_the_post_thumbnail(null, 'thumbnail', ['class' => 'project-related__img']) ?>
                                </div>
                                <?php endif; ?>
                                <div class="project-related__info">
                                    <span class="project-related__name"><?= get_the_title() ?></span>
                                    <?php
                                    $rel_area = carbon_get_post_meta(get_the_ID(), 'total_area');
                                    if ($rel_area): ?>
                                    <span class="project-related__area"><?= esc_html($rel_area) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endwhile; wp_reset_postdata(); ?>
                    </ul>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</article>
