<?php

/**
 * Theme footer partial.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */
?>
<!-- footer -->
<?php
$logo_footer = getOption('logo_footer');
$logo_footer_url = wp_get_attachment_image_url($logo_footer, 'full');
$footer_contact_heading = __('NHẬN TƯ VẤN NGAY', 'laca');
$footer_contact_button_text = __('GỬI YÊU CẦU', 'laca');
$footer_contact_budget_raw = trim((string) carbon_get_theme_option('footer_contact_budget_options'));
$footer_contact_budget_raw_i18n = trim((string) getOption('footer_contact_budget_options'));
$footer_contact_budget_source = $footer_contact_budget_raw !== '' ? $footer_contact_budget_raw : $footer_contact_budget_raw_i18n;
$footer_contact_budget_options = $footer_contact_budget_source !== ''
  ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $footer_contact_budget_source))))
  : [
    __('Dưới 1 tỷ', 'laca'),
    __('1 - 3 tỷ', 'laca'),
    __('3 - 5 tỷ', 'laca'),
    __('5 - 10 tỷ', 'laca'),
    __('Trên 10 tỷ', 'laca'),
  ];
$footer_contact_image_id = (int) carbon_get_theme_option('footer_contact_image');
if (!$footer_contact_image_id) {
  $footer_contact_image_id = (int) getOption('footer_contact_image');
}
$footer_contact_image_url = $footer_contact_image_id ? wp_get_attachment_image_url($footer_contact_image_id, 'large') : '';
$footer_contact_image_alt = $footer_contact_image_id ? get_post_meta($footer_contact_image_id, '_wp_attachment_image_alt', true) : '';
?>

<!-- block contact -->
<section class="footer-contact-form">
  <div class="container">
    <div class="bcf__inner">
      <div class="bcf__left" data-aos="fade-right">
        <?php if ($footer_contact_heading): ?>
          <h2 class="bcf__heading"><?php echo esc_html($footer_contact_heading); ?></h2>
        <?php endif; ?>

        <form class="bcf__form" method="POST" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" novalidate
          data-bcf-form>
          <?php wp_nonce_field('laca_footer_contact_nonce', 'nonce'); ?>
          <input type="hidden" name="action" value="laca_footer_contact_submit">

          <div class="bcf__field">
            <input type="text" name="tf_address" class="bcf__input"
              placeholder="<?php esc_attr_e('Địa chỉ xây dựng', 'laca'); ?>" required>
          </div>

          <div class="bcf__field">
            <input type="text" name="tf_scale" class="bcf__input"
              placeholder="<?php esc_attr_e('Quy mô xây dựng', 'laca'); ?>" required>
          </div>

          <div class="bcf__field">
            <select name="tf_budget" class="bcf__select" required>
              <option value=""><?php esc_html_e('Ngân sách', 'laca'); ?></option>
              <?php foreach ($footer_contact_budget_options as $option): ?>
                <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="bcf__field">
            <input type="text" name="tf_name" class="bcf__input" placeholder="<?php esc_attr_e('Họ và tên', 'laca'); ?>"
              required>
          </div>

          <div class="bcf__field">
            <input type="tel" name="tf_phone" class="bcf__input"
              placeholder="<?php esc_attr_e('Số điện thoại liên hệ', 'laca'); ?>" required>
          </div>

          <div class="bcf__submit-wrap">
            <button type="submit" class="bcf__btn">
              <span class="bcf__btn-text"><?php echo esc_html($footer_contact_button_text); ?></span>
              <span class="bcf__btn-loader" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3"
                    stroke-dasharray="31.42" stroke-dashoffset="10"></circle>
                </svg>
              </span>
            </button>
          </div>

          <div class="bcf__msg" role="alert" hidden></div>
        </form>
      </div>

      <div class="bcf__right" data-aos="fade-left">
        <?php if ($footer_contact_image_url): ?>
          <div class="bcf__img-wrap">
            <img src="<?php echo esc_url($footer_contact_image_url); ?>"
              alt="<?php echo esc_attr($footer_contact_image_alt ?: get_bloginfo('name')); ?>" class="bcf__img"
              loading="lazy">
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<footer class="footer" role="contentinfo" data-aos="fade-up">
  <div class="footer__main">
    <div class="container">

      <div class="footer__grid">
        <!-- Menu về chúng tôi -->
        <?php
        $aboutTitle = getOption('about_footer_title');
        ?>
        <div class="footer__col">
          <h3 class="footer__title"><?php echo esc_html($aboutTitle); ?></h3>

          <ul class="footer__menu-list">
            <!-- Công ty -->
            <?php $company = getOption('company'); ?>
            <?php if (!empty($company)): ?>
              <li class="footer__menu-item">
                <?php echo esc_html($company); ?>
              </li>
            <?php endif; ?>

            <!-- Hotline -->
            <?php
            $ft_phones = getOption('phone_numbers');
            if (!empty($ft_phones)):
              ?>
              <li class="footer__menu-item">
                <span>
                  <strong class="footer__contact-label">Hotline:</strong>
                  <?php
                  $phone_links = [];
                  foreach ($ft_phones as $p) {
                    if (!empty($p['phone'])) {
                      $phone_links[] = '<a href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $p['phone'])) . '" class="footer__contact-link">' . esc_html($p['phone']) . '</a>';
                    }
                  }
                  echo implode(' - ', $phone_links);
                  ?>
                </span>
              </li>
            <?php endif; ?>

            <!-- Email -->
            <?php
            $footer_email = getOption('email');
            if (!empty($footer_email)):
              ?>
              <li class="footer__contact-item">
                <span><strong class="footer__contact-label">Email:</strong> <a
                    href="mailto:<?php echo esc_attr($footer_email); ?>"
                    class="footer__contact-link"><?php echo esc_html($footer_email); ?></a></span>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Menu dịch vụ -->
        <?php
        $serviceTitle = getOption('service_footer_title');
        $serviceItems = getOption('service_footer_items');
        if (!empty($serviceItems)):
          ?>
          <div class="footer__col footer__col--service">
            <h3 class="footer__title"><?php echo esc_html($serviceTitle); ?></h3>
            <ul class="footer__menu-list">
              <?php foreach ($serviceItems as $item): ?>
                <li class="footer__menu-item">
                  <a href="<?php echo esc_url($item['url']); ?>"
                    class="footer__menu-link"><?php echo esc_html($item['name']); ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Menu chính sách -->
        <?php
        $policyTitle = getOption('policy_footer_title');
        $policyItems = getOption('policy_footer_items');
        if (!empty($policyItems)):
          ?>
          <div class="footer__col footer__col--policy">
            <h3 class="footer__title"><?php echo esc_html($policyTitle); ?></h3>
            <ul class="footer__menu-list">
              <?php foreach ($policyItems as $item): ?>
                <li class="footer__menu-item">
                  <a href="<?php echo esc_url($item['url']); ?>"
                    class="footer__menu-link"><?php echo esc_html($item['name']); ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
      <!-- END footer__grid -->

      <div class="footer__grid">
        <!-- Showroom & nhà máy -->
        <div class="footer__col">
          <?php if ($googlemap = carbon_get_theme_option('googlemap' . currentLanguage())): ?>
            <div class="footer__slogan"><?php echo $googlemap; ?></div>
          <?php endif; ?>

          <ul class="footer__contact-list">
            <?php
            $ft_addresses = getOption('address_locations');
            if (!empty($ft_addresses)):
              foreach ($ft_addresses as $addr):
                if (!empty($addr['address'])):
                  ?>
                  <li class="footer__contact-item">
                    <?php echo nl2br(esc_html($addr['address'])); ?>
                    </span>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Dự án tiêu biểu -->
        <?php
        $projectTitle = getOption('project_footer_title');
        $projectItems = getOption('project_footer_items');
        if (!empty($projectItems)):
          ?>
          <div class="footer__col footer__col--project">
            <h3 class="footer__title"><?php echo esc_html($projectTitle); ?></h3>
            <ul class="footer__menu-list">
              <?php foreach ($projectItems as $item): ?>
                <li class="footer__menu-item">
                  <a href="<?php echo esc_url($item['url']); ?>"
                    class="footer__menu-link"><?php echo esc_html($item['name']); ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Đối tác -->
        <?php
        $partnerTitle = getOption('partner_footer_title');
        $partnerItems = getOption('partner_footer_items');
        if (!empty($partnerItems)):
          ?>
          <div class="footer__col footer__col--partner">
            <h3 class="footer__title">
              <?php echo esc_html($partnerTitle); ?>
            </h3>
            <ul class="footer__menu-list">
              <?php foreach ($partnerItems as $item): ?>
                <li class="footer__menu-item">
                  <a href="<?php echo esc_url($item['url']); ?>" class="footer__menu-link">
                    <?php echo esc_html($item['name']); ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
      <!-- END footer__grid -->

    </div>
    <!-- END container -->
  </div>
  <!-- END footer__main -->
</footer>
<!-- footer end -->

</div>
<!-- container-wrapper end -->


<?php wp_footer(); ?>
</body>

</html>