<?php

namespace App\Settings;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Intervention\Image\ImageManagerStatic as Image;

class AdminSettings
{
	protected $currentUser;

	protected $errorMessage = '';

	public function __construct()
	{
		$this->currentUser = wp_get_current_user();

		// Luôn luôn đăng ký các options (Carbon Fields containers)
		// để front-end có thể đọc được bằng carbon_get_theme_option(),
		// sau đó mới áp dụng các giới hạn hiển thị cho non-super user.
		$this->createAdminOptions();

		if (!$this->isSuperUser()) {
			$this->hideSuperUsers();
			$this->setupErrorMessage();
			$this->checkIsMaintenance();
			$this->disablePluginPage();
			$this->disableOptionsReadPage();
			$this->disableAllUpdate();
			$this->removeUnnecessaryMenus();
		}

		$this->applyAdminColorVariables();
		$this->addDashboardContactWidget();
		$this->removeDefaultWidgets();
		$this->removeDashboardWidgets();
		$this->changeHeaderUrl();
		$this->changeHeaderTitle();
		$this->changeFooterCopyright();
		$this->customizeAdminBar();
		$this->resizeOriginalImageAfterUpload();
		$this->renameUploadFileName();
		$this->addCustomExtensionsInMediaUpload();

		if (get_option('_disable_admin_confirm_email') === 'yes') {
			$this->disableChangeAdminEmailRequireConfirm();
		}

		if (get_option('_disable_use_weak_password') === 'yes') {
			$this->disableCheckboxUseWeakPassword();
		}

		if (get_option('_hide_post_menu_default') === 'yes') {
			$this->hidePostMenuDefault();
		}

		if (get_option('_hide_comment_menu_default') === 'yes') {
			$this->hideCommentMenuDefault();
		}
	}

	public function addCustomExtensionsInMediaUpload()
	{
		add_filter('upload_mimes', static function ($mimes) {
			return array_merge($mimes, [
				'ac3' => 'audio/ac3',
				'mpa' => 'audio/MPA',
				'flv' => 'video/x-flv',
				'svg' => 'image/svg+xml',
			]);
		});

		add_action('wp_ajax_mm_get_attachment_url_thumbnail', static function () {
			$url          = '';
			$attachmentID = isset($_REQUEST['attachmentID']) ? $_REQUEST['attachmentID'] : '';
			if ($attachmentID) {
				$url = wp_get_attachment_url($attachmentID);
			}
			die($url);
		});
	}

	public function applyAdminColorVariables(): void
	{
		$printColors = static function () {
			$primary   = carbon_get_theme_option('primary_color_ad') ?: '#566a7f';
			$secondary = carbon_get_theme_option('secondary_color_ad') ?: '#566a7f';
			$bg        = carbon_get_theme_option('bg_color_ad') ?: '#E6E4FC';
			$text      = carbon_get_theme_option('text_color_ad') ?: '#000';

			echo '<style>:root{'
				. '--primary-color-ad:' . esc_attr($primary) . ';'
				. '--secondary-color-ad:' . esc_attr($secondary) . ';'
				. '--bg-color-ad:' . esc_attr($bg) . ';'
				. '--text-color-ad:' . esc_attr($text) . ';'
				. '}</style>';
		};

		add_action('admin_head', $printColors);
		add_action('login_head', $printColors);
	}

	public function disableCheckboxUseWeakPassword()
	{
		add_action('admin_enqueue_scripts', function () {
			wp_enqueue_script('jquery');
			wp_add_inline_script(
				'jquery',
				'jQuery(document).ready(function($) { $(".pw-weak").remove(); });'
			);
		});

		add_action('login_enqueue_scripts', function () {
			wp_enqueue_script(
				'laca-remove-pw-weak',
				get_template_directory_uri() . '/resources/scripts/login/remove-pw-weak.js',
				[],
				wp_get_theme()->get('Version'),
				true
			);
		});
	}

	public function addDashboardContactWidget()
	{
		add_action('wp_dashboard_setup', static function () {
			wp_add_dashboard_widget('custom_help_widget', 'Giới thiệu', static function () { ?>
				<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0;">
					<a target="_blank" href="<?php echo AUTHOR['website'] ?>" title="<?php echo AUTHOR['name'] ?>" style="opacity: 0.9; transition: opacity 0.2s;">
						<img style="max-width: 160px; height: auto; display: block;" src="<?php echo get_site_url() . '/wp-content/themes/lacadev/resources/images/dev/moomsdev-black.png' ?>" alt="<?php echo AUTHOR['name'] ?>">
					</a>
					<div style="margin-top: 20px; text-align: center;">
						
						<p style="margin: 0 0 15px; font-size: 16px; font-style: italic; color: #b5b5b5; font-family: 'Quicksand', sans-serif; font-weight: 500;">
							"Coding amidst the journeys"
						</p>

						<div style="display: flex; gap: 12px; justify-content: center; align-items: center; font-size: 14px; color: #848383; font-family: 'Quicksand', sans-serif; font-weight: 600;">
							<a style="color: inherit; text-decoration: none;" href="tel:<?php echo str_replace(['.', ',', ' '], '', AUTHOR['phone_number']); ?>" target="_blank">
								<?php echo AUTHOR['phone_number'] ?>
							</a>
							<span style="color: #dcdcde;">|</span>
							<a style="color: inherit; text-decoration: none;" href="mailto:<?php echo AUTHOR['email'] ?>" target="_blank">
								<?php echo AUTHOR['email'] ?>
							</a>
							<span style="color: #dcdcde;">|</span>
							<a style="color: inherit; text-decoration: none;" href="<?php echo AUTHOR['website'] ?>" target="_blank">
								Ghé thăm tôi
							</a>
						</div>
					</div>
				</div>
<?php });
		});
	}

	public function removeDefaultWidgets()
	{
		add_action('widgets_init', static function () {
			unregister_widget('WP_Widget_Pages');
			unregister_widget('WP_Widget_Calendar');
			unregister_widget('WP_Widget_Archives');
			unregister_widget('WP_Widget_Links');
			unregister_widget('WP_Widget_Meta');
			unregister_widget('WP_Widget_Search');
			unregister_widget('WP_Widget_Categories');
			unregister_widget('WP_Widget_Recent_Posts');
			unregister_widget('WP_Widget_Recent_Comments');
			unregister_widget('WP_Widget_RSS');
			unregister_widget('WP_Widget_Tag_Cloud');
			unregister_widget('WP_Nav_Menu_Widget');
		});
	}
	public function removeDashboardWidgets()
	{
		add_action('admin_init', static function () {
			remove_meta_box('dashboard_right_now', 'dashboard', 'normal');       // right now
			remove_meta_box('dashboard_activity', 'dashboard', 'normal');        // WP 3.8
			remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal'); // recent comments
			remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');  // incoming links
			remove_meta_box('dashboard_plugins', 'dashboard', 'normal');         // plugins
			remove_meta_box('dashboard_quick_press', 'dashboard', 'normal');     // quick press
			remove_meta_box('dashboard_recent_drafts', 'dashboard', 'normal');   // recent drafts
			remove_meta_box('dashboard_primary', 'dashboard', 'normal');         // wordpress blog
			remove_meta_box('dashboard_secondary', 'dashboard', 'normal');       // other wordpress news
		});
	}

	public function changeHeaderUrl()
	{
		add_filter('login_headerurl', static function ($url) {
			return '' . AUTHOR['website'] . '';
		});
	}

	public function changeHeaderTitle()
	{
		add_filter('login_headertext', static function () {
			return get_option('blogname');
		});
	}

	public function changeFooterCopyright()
	{
		add_filter('admin_footer_text', static function () {
			echo '<a href="' . AUTHOR['website'] . '" target="_blank">' . AUTHOR['name'] . '</a> © ' . date('Y') . ' - Coding amidst the journeys';
		});
	}

	public function customizeAdminBar()
	{
		$author = AUTHOR;
		add_action('wp_before_admin_bar_render', static function () use ($author) {
			global $wp_admin_bar;
			$wp_admin_bar->remove_menu('wp-logo');          // Remove the Wordpress logo
			$wp_admin_bar->remove_menu('about');            // Remove the about Wordpress link
			$wp_admin_bar->remove_menu('wporg');            // Remove the Wordpress.org link
			$wp_admin_bar->remove_menu('documentation');    // Remove the Wordpress documentation link
			$wp_admin_bar->remove_menu('support-forums');   // Remove the support forums link
			$wp_admin_bar->remove_menu('feedback');         // Remove the feedback link
			// $wp_admin_bar->remove_menu('site-name');        // Remove the site name menu
			$wp_admin_bar->remove_menu('view-site');        // Remove the view site link
			$wp_admin_bar->remove_menu('updates');          // Remove the updates link
			$wp_admin_bar->remove_menu('comments');         // Remove the comments link
			$wp_admin_bar->remove_menu('new-content');      // Remove the content link
			$wp_admin_bar->remove_menu('w3tc');             // If you use w3 total cache remove the performance link
			// $wp_admin_bar->remove_menu('my-account');       // Remove the user details tab
		}, 7);

		add_action('admin_bar_menu', static function ($wp_admin_bar) use ($author) {
			$args = [
				'id'    => 'logo_author',
				'title' => '<img src="' . get_site_url() . "/wp-content/themes/lacadev/resources/images/dev/moomsdev-white.png" . '" class="logo-admin-bar" alt="' . AUTHOR['name'] . '">',
				'href'  => $author['website'],
				'meta'  => [
					'target' => '_blank',
				],
			];
			$wp_admin_bar->add_node($args);
		}, 10);
	}

	public function renameUploadFileName()
	{
		add_filter('sanitize_file_name', function ($filename) {
			$info        = pathinfo($filename);
			$ext         = empty($info['extension']) ? '' : '.' . $info['extension'];
			$newFileName = str_replace($ext, '', date('YmdHi') . '-' . $filename);
			$unicode     = [
				'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
				'd' => 'đ',
				'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
				'i' => 'í|ì|ỉ|ĩ|ị',
				'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
				'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
				'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
				'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
				'D' => 'Đ',
				'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
				'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
				'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
				'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
				'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
			];
			foreach ($unicode as $nonUnicode => $uni) {
				$newFileName = preg_replace("/($uni)/i", $nonUnicode, $newFileName);
			}
			$newFileName = str_replace(' ', '-', $newFileName);
			$newFileName = preg_replace('/[^A-Za-z0-9\-]/', '', $newFileName);
			$newFileName = preg_replace('/-+/', '-', $newFileName);
			return $newFileName . $ext;
		}, 10);
	}

	public function resizeOriginalImageAfterUpload()
	{
		add_filter('intermediate_image_sizes_advanced', static function ($sizes) {
			$imgSize = [
				'medium',
				'medium_large',
				'large',
				'full',
				'woocommerce_single',
				'woocommerce_gallery_thumbnail',
				'shop_catalog',
				'shop_single',
				'woocommerce_thumbnail',
				'shop_thumbnail',
			];
			foreach ($imgSize as $item) {
				if (array_key_exists($item, $sizes)) {
					unset($sizes[$item]);
				}
			}
			return $sizes;
		});

		add_filter('wp_generate_attachment_metadata', static function ($image_data) {
			try {
				$upload_dir = wp_upload_dir();
				$imgPath    = $upload_dir['basedir'] . '/' . $image_data['file'];
				$image      = Image::make($imgPath);
				$imgWidth   = $image->width();
				$imgHeight  = $image->height();
				$image->resize(null, null, static function ($constraint) {
					$constraint->aspectRatio();
				});
				$image->save($imgPath, 100);
			} catch (\Exception $ex) {
			}
			return $image_data;
		});
	}

	public function disableChangeAdminEmailRequireConfirm()
	{
		remove_action('add_option_new_admin_email', 'update_option_new_admin_email');
		remove_action('update_option_new_admin_email', 'update_option_new_admin_email');

		add_action('add_option_new_admin_email', function ($old_value, $value) {
			update_option('admin_email', $value);
		}, 10, 2);

		add_action('update_option_new_admin_email', function ($old_value, $value) {
			update_option('admin_email', $value);
		}, 10, 2);
	}

	public function hideSuperUsers()
	{
		add_action('pre_user_query', function ($user_search) {
			if ($this->isSuperUser()) {
				return;
			}

			global $wpdb;
			$super_users_str = "('" . implode("','", array_map('esc_sql', self::getSuperUserLogins())) . "')";
			$user_search->query_where = str_replace('WHERE 1=1', "WHERE 1=1 AND {$wpdb->users}.user_login NOT IN " . $super_users_str, $user_search->query_where);
		});
	}

	/**
	 * Danh sách login của mọi Super User hiện tại: mặc định 'lacadev' (không
	 * thể xoá khỏi danh sách này qua UI, chỉ sửa được trong code) + những
	 * user được thêm qua trang quản lý "Super User" (Bảo mật → Super User).
	 * Static để SecurityManager (trang quản lý) và SuperUserGuard dùng chung,
	 * không phải tự tính lại danh sách này ở nơi khác.
	 *
	 * @return string[]
	 */
	public static function getSuperUserLogins()
	{
		$super_logins = apply_filters('lacadev_super_user_logins', ['lacadev']);
		$extra_logins = get_option('laca_extra_super_user_logins', []);

		if (is_array($extra_logins) && $extra_logins) {
			$super_logins = array_merge($super_logins, $extra_logins);
		}

		return array_values(array_unique(array_filter($super_logins)));
	}

	/**
	 * Check if current user is a super user (Developer)
	 *
	 * @return bool
	 */
	protected function isSuperUser()
	{
		$is_super = in_array($this->currentUser->user_login, self::getSuperUserLogins(), true);

		return apply_filters('lacadev_is_super_user', $is_super, $this->currentUser);
	}

	public function setupErrorMessage()
	{
		$logoUrl = get_site_url() . "/wp-content/themes/lacadev/resources/images/dev/moomsdev-black.png";
		$adminUrl = admin_url();
		$website = AUTHOR['website'];
		$authorName = AUTHOR['name'];

		// Stars
		$starsHtml = '';
		foreach (range(1, 100) as $i) {
			$size = rand(15, 35) / 10;
			$left = rand(0, 10000) / 100;
			$top = rand(0, 8500) / 100;
			$dur = rand(20, 50) / 10;
			$delay = rand(0, 50) / 10;
			$starsHtml .= '<div class="alp-star" style="left:' . $left . '%; top:' . $top . '%; width:' . $size . 'px; height:' . $size . 'px; --d:' . $dur . 's; animation-delay:' . $delay . 's; box-shadow: 0 0 ' . ($size + 1) . 'px #fff;"></div>';
		}

		// Embers
		$embersHtml = '';
		foreach (range(1, 8) as $i) {
			$x = rand(-15, 15);
			$tx = rand(-40, 40);
			$dur = rand(15, 40) / 10;
			$delay = rand(0, 30) / 10;
			$left = rand(42, 58);
			$embersHtml .= '<div class="alp-ember" style="--x:' . $x . 'px; --tx:' . $tx . 'px; --e-dur:' . $dur . 's; animation-delay:' . $delay . 's; left:' . $left . '%;"></div>';
		}

		$this->errorMessage = '
			<style>
				html, body#error-page {
					max-width: 100% !important;
					width: 100vw !important;
					height: 100vh !important;
					margin: 0 !important;
					padding: 0 !important;
					border: none !important;
					box-shadow: none !important;
					-webkit-box-shadow: none !important;
					background: #05050a !important; /* Fallback */
					background: radial-gradient(circle at 50% 40%, #1a2a4e 0%, #0d0d21 60%, #05050a 100%) !important;
					display: flex !important;
					align-items: center !important;
					justify-content: center !important;
					overflow: hidden !important;
					font-family: "Quicksand", sans-serif !important;
				}
				#error-page h1 { display: none !important; }
				.night-field { position: fixed; inset: 0; z-index: 1; pointer-events: none; }
				.alp-star { position: absolute; border-radius: 50%; background: #ffffff; animation: alpTwinkle var(--d, 3s) ease-in-out infinite; opacity: 0.15; }
				@keyframes alpTwinkle { 0%, 100% { opacity: 0.2; transform: scale(0.8); } 50% { opacity: 1; transform: scale(1.2); } }
				.alp-moon { position: absolute; top: 10%; right: 15%; width: 45px; height: 45px; border-radius: 50%; box-shadow: 8px 8px 0 0 #fef9c3; filter: drop-shadow(0 0 15px rgba(254, 249, 195, 0.5)); transform: rotate(-10deg); z-index: 2; }
				.alp-ground { position: absolute; bottom: -20px; left: -10%; right: -10%; height: 160px; background: #020204; border-radius: 50% 50% 0 0; z-index: 4; }
				.alp-trees { position: absolute; bottom: 130px; right: 10%; display: flex; gap: 25px; z-index: 3; }
				.alp-tree { width: 0; height: 0; border-left: 28px solid transparent; border-right: 28px solid transparent; border-bottom: 90px solid #080f08; }
				.alp-tree.small { border-left-width: 20px; border-right-width: 20px; border-bottom-width: 60px; margin-top: 30px; }
				.alp-tent { position: absolute; bottom: 120px; left: 32%; width: 0; height: 0; border-left: 65px solid transparent; border-right: 65px solid transparent; border-bottom: 90px solid #1a3a5f; filter: drop-shadow(0 10px 25px rgba(0,0,0,0.6)); z-index: 5; }
				.alp-tent::after { content: ""; position: absolute; bottom: -90px; left: -22px; width: 0; height: 0; border-left: 22px solid transparent; border-right: 22px solid transparent; border-bottom: 45px solid #05080c; }
				.alp-fire-wrap { position: absolute; bottom: 125px; left: calc(32% + 140px); width: 50px; height: 50px; z-index: 5; }
				.alp-fire-glow { position: absolute; bottom: -20px; left: 50%; width: 250px; height: 100px; margin-left: -125px; background: radial-gradient(ellipse at center, rgba(255, 100, 0, 0.3) 0%, transparent 70%); animation: alpFirePulse 1.2s ease-in-out infinite alternate; }
				@keyframes alpFirePulse { from { opacity: 0.4; transform: scale(0.9); } to { opacity: 0.9; transform: scale(1.1); } }
				.alp-flame { position: absolute; bottom: 4px; left: 50%; width: 28px; height: 50px; background: #ff5e13; border-radius: 50% 50% 20% 20% / 80% 80% 20% 20%; filter: blur(1.5px); transform-origin: bottom center; animation: alpFlameMove 0.6s ease-in-out infinite alternate; margin-left: -14px; mix-blend-mode: screen; }
				.alp-flame:nth-child(2) { width: 22px; height: 40px; background: #ffcc33; animation-delay: 0.1s; filter: blur(1px); margin-left: -11px; }
				.alp-flame:nth-child(3) { width: 15px; height: 25px; background: #fff; animation-delay: 0.2s; filter: blur(0.5px); margin-left: -7.5px; }
				@keyframes alpFlameMove { 0% { transform: scale(1) rotate(-3deg); } 100% { transform: scale(1.1, 1.25) rotate(3deg); } }
				.alp-ember { position: absolute; bottom: 40px; width: 3px; height: 3px; background: #ffcc33; border-radius: 50%; filter: blur(0.5px); animation: alpEmberUp var(--e-dur, 2s) linear infinite; }
				@keyframes alpEmberUp { 0% { transform: translate(var(--x, 0), 0) scale(1); opacity: 1; } 100% { transform: translate(var(--tx, 0), -120px) scale(0); opacity: 0; } }
				.alp-firepit { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center; }
				.alp-logs { display: flex; gap: 4px; margin-bottom: -2px; }
				.alp-log { width: 35px; height: 8px; background: #331a0a; border-radius: 4px; transform: rotate(var(--r, 20deg)); }
				.alp-rocks { display: flex; gap: 2px; }
				.alp-rock { width: 10px; height: 6px; background: #222; border-radius: 40%; }
				
				.denied-card {
					width: 50rem;
					max-width: 92vw;
					background: rgba(255, 255, 255, 0.05) !important;
					backdrop-filter: blur(40px) saturate(150%) !important;
					-webkit-backdrop-filter: blur(40px) saturate(150%) !important;
					border: 1px solid rgba(255, 255, 255, 0.1) !important;
					border-radius: 4rem !important;
					padding: 6rem 4rem !important;
					box-shadow: 0 4rem 10rem rgba(0, 0, 0, 0.4) !important;
					text-align: center !important;
					position: relative !important;
					z-index: 20 !important;
					margin-top: -10vh;
				}
				.denied-logo { display: inline-block; width: 22rem; margin-bottom: 4rem; filter: brightness(0) invert(1); opacity: 0.8; }
				.denied-content h2 { font-size: 3.2rem; font-weight: 800; margin-bottom: 2rem; color: #fff; letter-spacing: -0.01em; }
				.denied-content p { font-size: 1.8rem; line-height: 1.7; margin-bottom: 4.5rem; color: rgba(255, 255, 255, 0.7); }
				.back-link { display: inline-flex; align-items: center; justify-content: center; padding: 0 5rem; height: 6.4rem; background: #fff; color: #05050a !important; text-decoration: none !important; border-radius: 1.6rem; font-weight: 800; font-size: 1.6rem; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
				.back-link:hover { transform: scale(1.05) translateY(-5px); box-shadow: 0 2rem 4rem rgba(0, 0, 0, 0.3); }
				.footer-hint { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; color: rgba(255,255,255,0.2); font-family: monospace; font-size: 10px; letter-spacing: 4px; text-transform: uppercase; z-index: 5; }
			</style>
			<div class="night-field" aria-hidden="true">
				<div class="alp-stars">' . $starsHtml . '</div>
				<div class="alp-moon"></div>
				<div class="alp-trees"><div class="alp-tree"></div><div class="alp-tree small"></div></div>
				<div class="alp-tent"></div>
				<div class="alp-fire-wrap">
					<div class="alp-fire-glow"></div>
					<div class="alp-embers">' . $embersHtml . '</div>
					<div class="alp-flames"><div class="alp-flame"></div><div class="alp-flame"></div><div class="alp-flame"></div></div>
					<div class="alp-firepit">
						<div class="alp-logs">
							<div class="alp-log" style="--r: 25deg"></div>
							<div class="alp-log" style="--r: -25deg; margin-left: -15px"></div>
						</div>
						<div class="alp-rocks"><div class="alp-rock"></div><div class="alp-rock" style="margin-top:2px"></div><div class="alp-rock"></div></div>
					</div>
				</div>
				<div class="alp-ground"></div>
			</div>
			<div class="denied-card">
				<div style="text-align:center">
					<a target="_blank" href="' . esc_url($website) . '">
						<img class="denied-logo" src="' . esc_url($logoUrl) . '" alt="' . esc_attr($authorName) . '">
					</a>
				</div>
				<div class="denied-content">
					<h2>Hết đường rồi, phượt thủ ơi!</h2>
					<p>Đây là vùng cấm không dành cho bạn. <br>Hãy kiểm tra lại quyền hạn hoặc quay về trại chính nhé.</p>
					<a class="back-link" href="' . esc_url($adminUrl) . '">Quay về Dashboard</a>
				</div>
			</div>
			<div class="footer-hint">// Peaceful Night </div>';
	}

	public function checkIsMaintenance()
	{
        // Sử dụng template_redirect để chỉ ảnh hưởng Frontend
        // Không ảnh hưởng wp-admin hoặc wp-login.php
		add_action('template_redirect', static function () {
            // 1. Kiểm tra option có đang bật không
			if (get_option('_is_maintenance') === 'yes') {
                
                // 2. Nếu là Admin hoặc Editor thì CHO PHÉP truy cập để làm việc
                if (current_user_can('edit_theme_options')) {
                    return;
                }

                // 3. Chặn tất cả user khác và load template báo trì
                // Sử dụng status_header + exit thay vì wp_die để render full custom UI
                status_header(503);
                nocache_headers();
                include get_template_directory() . '/maintenance.php';
                exit();
			}
		});
	}

	public function disablePluginPage()
	{
		add_action('admin_menu', static function () {
			global $menu;
			foreach ($menu as $key => $menuItem) {
				switch ($menuItem[2]) {
					case 'plugins.php':
					case 'customize.php':
						// case 'themes.php':
						unset($menu[$key]);
						break;
				}
			}

			global $submenu;
			unset($submenu['themes.php'][5], $submenu['themes.php'][6]);

			if (get_option('_hide_theme_editor') === 'yes') {
				unset($submenu['themes.php'][11]);
				remove_submenu_page('themes.php', 'theme-editor.php');
			}
		}, 999);

		$errorMessage = $this->errorMessage;
		add_action('current_screen', static function () use ($errorMessage) {
			$deniePage      = [
				'plugins',
				'plugin-install',
				'plugin-editor',
				'themes',
				'theme-install',
				'theme-install',
				'customize',
				'customize',
				'tools',
				'import',
				'export',
				'tools_page_action-scheduler',
				'tools_page_export_personal_data',
				'tools_page_export_personal_data',
				'tools_page_remove_personal_data',
			];
			if (get_option('_hide_theme_editor') === 'yes') {
				$deniePage[] = 'theme-editor';
			}
			$current_screen = get_current_screen();

			if ($current_screen !== null && in_array($current_screen->id, $deniePage, true)) {
				wp_die($errorMessage);
			}
		});
	}

	public function disableOptionsReadPage()
	{
		$removePages = [
			'options-reading.php',
			'options-writing.php',
			'options-discussion.php',
			'options-media.php',
			'privacy.php',
			'options-permalink.php',
			'tinymce-advanced',
		];
		add_action('admin_menu', static function () use ($removePages) {
			foreach ($removePages as $page) {
				remove_submenu_page('options-general.php', $page);
			}
		});

		$errorMessage = $this->errorMessage;
		$denyPages    = [
			'options-reading',
			'options-writing',
			'options-discussion',
			'options-media',
			'privacy',
			'options-permalink',
			'settings_page_tinymce-advanced',
			'toplevel_page_wpseo_dashboard',
		];
		add_action('current_screen', static function () use ($errorMessage, $denyPages) {
			$current_screen = get_current_screen();
			if ($current_screen !== null && in_array($current_screen->id, $denyPages, true)) {
				wp_die($errorMessage);
			}
		});
	}

	public function disableAllUpdate()
	{
		remove_action('load-update-core.php', 'wp_update_plugins');
		add_filter('pre_site_transient_update_plugins', function ($a) {
			return null;
		});
	}

	public function removeUnnecessaryMenus()
	{
		add_action('admin_menu', static function () {
			global $menu;
			global $submenu;
			foreach ($menu as $key => $menuItem) {
				if (in_array($menuItem[2], [
					'tools.php',
					'edit-comments.php',
					'wpseo_dashboard',
					'duplicator',
					'yit_plugin_panel',
					'woocommerce-checkout-manager',
				])) {
					unset($menu[$key]);
				}
			}
		});
	}

	public function hidePostMenuDefault()
	{
		add_action('admin_init', function () {
			remove_menu_page('edit.php');
		});
	}

	public function hideCommentMenuDefault()
	{
		add_action('admin_init', function () {
			remove_menu_page('edit-comments.php');
		});
	}

	public function createAdminOptions()
	{
		add_action('carbon_fields_register_fields', static function () {
			$options = Container::make('theme_options', __('Laca Admin', 'laca'))
				->set_page_file(__('laca-admin', 'laca'))
				->set_page_menu_position(3)
				->add_tab(__('ADMIN COLOR', 'laca'), [
					Field::make('color', 'primary_color_ad', __('Primary color', 'laca'))
						->set_width(25),
					Field::make('color', 'secondary_color_ad', __('Secondary color', 'laca'))
						->set_width(25),
					Field::make('color', 'bg_color_ad', __('Background color', 'laca'))
						->set_width(25),
					Field::make('color', 'text_color_ad', __('Text color', 'laca'))
						->set_width(25),
				])
				->add_tab(__('ADMIN', 'laca'), [
					Field::make('checkbox', 'is_maintenance', __('Bật chế độ bảo trì', 'laca')) 
						->set_width(30),
					Field::make( 'html', 'is_maintenance_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ bảo trì, tất cả người dùng sẽ không thể truy cập vào trang web của bạn. Bạn có thể tạm thời đóng băng trang web để tránh việc người dùng truy cập vào trang web của bạn.' ),
					
					// hide theme editor
					Field::make('checkbox', 'hide_theme_editor', __('Tắt chức năng chỉnh sửa code', 'laca'))
					->set_width(30),
					Field::make( 'html', 'hide_theme_editor_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể chỉnh sửa code trong trang admin.' ),

					Field::make('checkbox', 'disable_admin_confirm_email', __('Tắt chức năng xác thực email khi thay đổi email admin', 'laca'))
						->set_width(30),
					Field::make( 'html', 'disable_admin_confirm_email_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không cần phải xác thực email khi thay đổi email admin.' ),
					
					Field::make('checkbox', 'disable_use_weak_password', __('Tắt chức năng sử dụng mật khẩu yếu', 'laca'))
						->set_width(30),
					Field::make( 'html', 'disable_use_weak_password_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể sử dụng mật khẩu yếu.' ),

					Field::make('checkbox', 'hide_post_menu_default', __('Ẩn menu bài viết mặc định', 'laca'))
						->set_width(30),
					Field::make( 'html', 'hide_post_menu_default_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bài viết trong trang admin.' ),

					Field::make('checkbox', 'hide_comment_menu_default', __('Ẩn menu bình luận mặc định', 'laca'))
						->set_width(30),
					Field::make( 'html', 'hide_comment_menu_default_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bình luận trong trang admin.' ),
						
				])
				->add_tab(__('SMTP', 'laca'), [
					Field::make('checkbox', 'use_smtp', __('Sử dụng SMTP để gửi mail', 'laca')),
					
					Field::make('separator', 'smtp_separator_1', __('Thông tin máy chủ SMTP', 'laca')),
					Field::make('text', 'smtp_host', __('Địa chỉ máy chủ', 'laca'))
						->set_width(33.33)
						->set_default_value('smtp.gmail.com'),
					Field::make('text', 'smtp_port', __('Cổng máy chủ', 'laca'))
						->set_width(33.33)
						->set_default_value('587'),
					Field::make('text', 'smtp_secure', __('Phương thức mã hóa', 'laca'))
						->set_width(33.33)
						->set_default_value('TLS'),

					Field::make('separator', 'smtp_separator_2', __('Thông tin email hệ thống', 'laca')),
					Field::make('text', 'smtp_username', __('Địa chỉ email', 'laca'))
						->set_width(50),
					Field::make('text', 'smtp_password', __('Mật khẩu', 'laca'))
						->set_width(50)
						->set_attribute('type', 'password')
						->set_attribute('data-field', 'password-field'),
				])
				->add_tab(__('LOGIN', 'laca'), [
					Field::make('image', 'login_logo', __('Login logo', 'laca'))
						->set_width(20)
						->set_help_text('Nếu để trống sẽ dùng logo mặc định của website'),

					Field::make('textarea', 'login_welcome_text_vi', __('Lời chào (VI)', 'laca'))
						->set_rows(4)
						->set_width(40)
						->set_default_value("Chào mừng về Trạm Laca!\nCắm sạc, pha trà và bắt đầu nào!")
						->set_help_text('Có thể xuống dòng, hệ thống sẽ tự đổi sang <br/>'),
					Field::make('textarea', 'login_welcome_text_en', __('Welcome text (EN)', 'laca'))
						->set_rows(4)
						->set_width(40)
						->set_default_value("Welcome to Laca Station!\nCharge up, brew some tea and let's go!"),

					Field::make('text', 'login_user_label_vi', __('Label user (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Ai đang ghé trạm?'),
					Field::make('text', 'login_user_label_en', __('Label user (EN)', 'laca'))
						->set_width(50)
						->set_default_value("Who's visiting the station?"),

					Field::make('text', 'login_password_label_vi', __('Label password (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Chìa khóa'),
					Field::make('text', 'login_password_label_en', __('Label password (EN)', 'laca'))
						->set_width(50)
						->set_default_value('The Key'),

					Field::make('text', 'login_user_placeholder_vi', __('Placeholder user (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Điền tên hoặc email vào đây nhé'),
					Field::make('text', 'login_user_placeholder_en', __('Placeholder user (EN)', 'laca'))
						->set_width(50)
						->set_default_value('Enter name or email here'),

					Field::make('text', 'login_password_placeholder_vi', __('Placeholder password (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Nhập chìa khóa mở cửa'),
					Field::make('text', 'login_password_placeholder_en', __('Placeholder password (EN)', 'laca'))
						->set_width(50)
						->set_default_value('Enter your key to open'),

					Field::make('text', 'login_forgot_label_vi', __('Label rớt chìa khoá (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Rớt chìa khoá?'),
					Field::make('text', 'login_forgot_label_en', __('Forgot label (EN)', 'laca'))
						->set_width(50)
						->set_default_value('Lost your key?'),

					Field::make('text', 'login_back_label_vi', __('Label rời khỏi trạm (VI)', 'laca'))
						->set_width(50)
						->set_default_value('← Rời khỏi Trạm'),
					Field::make('text', 'login_back_label_en', __('Back label (EN)', 'laca'))
						->set_width(50)
						->set_default_value('← Leave the Station'),
				]);

			Container::make('theme_options', __('Tools', 'laca'))
			->set_page_parent($options)
			->set_page_file(__('laca-tools', 'laca'))
			->add_tab(__('Optimization', 'laca'), [
				// Disable unnecessary items
				Field::make( 'separator', 'title_disable_unnecessary_items', __( 'Disable unnecessary items' ) ),
				Field::make('checkbox', 'disable_use_jquery_migrate', __('Disable jQuery Migrate', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_use_jquery_migrate_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> jQuery Migrate là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
					
				Field::make('checkbox', 'disable_gutenberg_css', __('Disable Gutenberg CSS', 'laca'))
					->set_width(30),
				Field::make( 'html', 'gutenberg_css_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Gutenberg CSS là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
					
				Field::make('checkbox', 'disable_classic_css', __('Disable Classic CSS', 'laca'))
					->set_width(30),
				Field::make( 'html', 'classic_css_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Classic CSS là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
					
				Field::make('checkbox', 'disable_emoji', __('Disable Emoji', 'laca'))
					->set_width(30),
				Field::make( 'html', 'emoji_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Emoji là thư viện được sử dụng để hiển thị các biểu tượng trong trang web. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
				
				// Optimization Library
				Field::make( 'separator', 'title_optimization_library', __( 'Optimization Library' ) ),
				Field::make('checkbox', 'enable_instant_page', __('Enable Instant-page', 'laca'))
					->set_width(30),
				Field::make( 'html', 'instant_page_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Instant-Page là một thư viện cho phép bạn tải trước nội dung của trang được liên kết vào bộ nhớ trình duyệt chỉ bằng cách di chuyển qua liên kết. Khi bạn nhấp vào liên kết, nó cung cấp trải nghiệm tải nhanh đáng kể' ),
					
				Field::make('checkbox', 'enable_smooth_scroll', __('Enable Smooth-scroll', 'laca'))
					->set_width(30),
				Field::make( 'html', 'smooth_scroll_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Smooth-scroll là thư viện cho phép bạn tạo hiệu ứng cuộn mượt mà, cung cấp cho người dùng cảm giác điều hướng trang nhanh hơn.' ),
					
				// The function of lazy loading images
				Field::make( 'separator', 'title_lazy_loading_images', __( 'The function of lazy loading images' ) ),
				Field::make( 'html', 'lazy_loading_images_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Nếu bạn muốn lazy load hình ảnh mỗi khi trang tải, hãy bật tính năng này. Chức năng này giúp trang web của bạn tải nhanh hơn' ),

				Field::make('checkbox', 'remove_comments', __('Remove comments from HTML, JavaScript, and CSS', 'laca')),
				Field::make('checkbox', 'remove_xhtml_closing_tags', __('Remove XHTML closing tags from empty elements in HTML5', 'laca')),
				Field::make('checkbox', 'remove_relative_domain', __('Remove relative domain from internal URLs', 'laca')),
				Field::make('checkbox', 'remove_protocols', __('Remove protocols (HTTP: and HTTPS:) from all URLs', 'laca')),
				Field::make('checkbox', 'support_multi_byte_utf_8', __('Support multi-byte UTF-8 encoding (if you see strange characters)', 'laca')),
				// Thêm các field tối ưu hóa mới
				Field::make('checkbox', 'enable_advanced_resource_hints', __('Bật Advanced Resource Hints', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_advanced_resource_hints_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Bật tính năng thêm resource hint (preload, preconnect,...) giúp tăng tốc tải tài nguyên.'),

				Field::make('checkbox', 'enable_optimize_images', __('Tối ưu hóa thuộc tính ảnh', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_images_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tự động thêm lazy loading, alt, dimension cho ảnh.'),

				Field::make('checkbox', 'enable_optimize_content_images', __('Tối ưu hóa ảnh trong nội dung', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_content_images_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tự động lazy load ảnh trong nội dung bài viết.'),

				Field::make('checkbox', 'enable_register_service_worker', __('Bật Service Worker cache', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_register_service_worker_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Đăng ký service worker để tăng tốc tải trang và cache tài nguyên.'),
			])
			// Security
			->add_tab(__('Security', 'laca'), [
				// Enhance website security
				Field::make( 'separator', 'title_enhance_website_security', __( 'Enhance website security' ) ),
				Field::make('checkbox', 'disable_rest_api', __('Disable REST API', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_rest_api_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> REST API mặc định trong WordPress cho phép ứng dụng bên ngoài giao tiếp với WordPress để lấy dữ liệu hoặc đăng nội dung, bạn nên vô hiệu hóa nó cho mục đích bảo mật.' ),

				Field::make('checkbox', 'disable_xml_rpc', __('Disable XML RPC', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_xml_rpc_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> XML-RPC là giao thức cho phép quản lý website từ xa thông qua ứng dụng như WordPress App hoặc Jetpack.<br> <b>Khuyến cáo:</b> Nên tắt hoàn toàn nếu không dùng tới.' ),

				Field::make('checkbox', 'disable_wp_embed', __('Disable Wp-Embed', 'laca'))
					->set_width(30),	
				Field::make( 'html', 'disable_wp_embed_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> WP-Embed cho phép nội dung của trang WordPress được nhúng vào trang web khác thông qua oEmbed.<br> <b>Khuyến cáo:</b> Nếu không dùng, nên tắt để giảm thiểu tải không cần thiết.' ),

				Field::make('checkbox', 'disable_x_pingback', __('Disable X-Pingback', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_x_pingback_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> X-Pingback là cơ chế thông báo giữa các blog (khi ai đó liên kết đến trang web).<br> <b>Khuyến cáo:</b> Nên tắt hoàn toàn nếu không dùng tới.' ),
					
				// Thêm các field bảo mật mới
				Field::make('checkbox', 'enable_remove_wordpress_bloat', __('Loại bỏ bloat WordPress', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_remove_wordpress_bloat_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Loại bỏ các thành phần không cần thiết của WordPress để tăng bảo mật và hiệu suất.'),

				Field::make('checkbox', 'enable_optimize_database_queries', __('Tối ưu hóa truy vấn database', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_database_queries_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Giới hạn post revision, tăng autosave interval, bật object cache.'),

				Field::make('checkbox', 'enable_optimize_sql_queries', __('Log truy vấn SQL chậm', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_sql_queries_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Log các truy vấn SQL chậm để phát hiện truy vấn bất thường.'),

				Field::make('checkbox', 'enable_optimize_memory_usage', __('Tối ưu hóa bộ nhớ', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_memory_usage_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tăng memory limit, bật garbage collection.'),

				Field::make('checkbox', 'enable_cleanup_memory', __('Dọn dẹp bộ nhớ cuối trang', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_cleanup_memory_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Dọn dẹp bộ nhớ cuối trang để giảm nguy cơ memory leak.'),

				Field::make('checkbox', 'enable_set_cache_headers', __('Đặt cache header nâng cao', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_set_cache_headers_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Đặt cache header bảo vệ trang admin và user login.'),

				Field::make('checkbox', 'enable_compression', __('Bật gzip nén dữ liệu', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_compression_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Bật gzip để bảo vệ dữ liệu truyền tải.'),

				Field::make('checkbox', 'enable_performance_monitoring', __('Giám sát hiệu suất', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_performance_monitoring_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Giám sát hiệu suất, phát hiện bất thường.'),
			]);

			// LacaDev Block Sync
			Container::make('theme_options', __('🧩 LacaDev', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-block-sync', 'laca'))
				->add_fields([
					Field::make('separator', 'sep_block_sync_heading', __('Block Sync — Nhận blocks từ lacadev.com', 'laca')),

					Field::make('html', 'block_sync_api_key_display', __('API Key', 'laca'))
						->set_html(static function () {
							$key = \App\Settings\BlockSyncReceiver::ensureApiKey();
							return '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#166534">🔑 API Key của site này:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#dcfce7;padding:6px 10px;border-radius:4px;display:block">' . esc_html($key) . '</code>'
								. '<p style="margin:8px 0 0;font-size:12px;color:#4b5563">Copy key này và dán vào tab <strong>🧩 Block Sync</strong> trong project trên <strong>lacadev.com</strong>.</p>'
								. '</div>';
						}),

					Field::make('html', 'block_sync_endpoint_info', '')
						->set_html(static function () {
							$endpoint = rest_url('lacadev/v1/sync-block');
							return '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#0369a1">🌐 Endpoint URL của site này:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#e0f2fe;padding:6px 10px;border-radius:4px;display:block">' . esc_html($endpoint) . '</code>'
								. '<p style="margin:8px 0 0;font-size:12px;color:#4b5563">Dán URL này vào trường <strong>Sync Endpoint URL</strong> trong project tương ứng trên lacadev.com.</p>'
								. '</div>';
						}),

					Field::make('separator', 'sep_block_catalog_heading', __('Block Catalog — chỉ dùng trên clients.lacadev.com', 'laca')),

					Field::make('html', 'block_catalog_key_display', __('Catalog Key', 'laca'))
						->set_html(static function () {
							$key = \App\Settings\BlockCatalogProvider::ensureApiKey();
							return '<div style="background:#fefce8;border:1px solid #fde68a;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#854d0e">🔑 Catalog Key của site này:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#fef9c3;padding:6px 10px;border-radius:4px;display:block">' . esc_html($key) . '</code>'
								. '<p style="margin:8px 0 0;font-size:12px;color:#4b5563">Chỉ cấu hình trên <strong>lacadev.com</strong> (hub) để hub đọc danh mục block từ <strong>chính site clients.lacadev.com này</strong> — không liên quan tới project của khách hàng.</p>'
								. '</div>';
						}),

					Field::make('html', 'block_catalog_endpoint_info', '')
						->set_html(static function () {
							$endpoint = rest_url('lacadev/v1/blocks-catalog');
							return '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#0369a1">🌐 Catalog Endpoint URL:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#e0f2fe;padding:6px 10px;border-radius:4px;display:block">' . esc_html($endpoint) . '</code>'
								. '</div>';
						}),
				]);

			// Tracker Settings — kết nối gửi log về lacadev CMS
			Container::make('theme_options', __('📡 Tracker', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-tracker', 'laca'))
				->add_fields([
					Field::make('html', 'tracker_info', '')
						->set_html(
							'<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">'
							. '<p style="margin:0 0 8px;font-weight:600;color:#0369a1">📡 LacaDev Tracker</p>'
							. '<p style="margin:0;font-size:13px;color:#374151">Gửi log tự động (cập nhật plugin/theme/core, xóa plugin, phát hiện file PHP lạ) về hệ thống quản lý dự án lacadev.com. '
							. 'Lấy <strong>Endpoint URL</strong> và <strong>Secret Key</strong> từ trang chi tiết project tương ứng trên lacadev.com.</p>'
							. '</div>'
						),

					Field::make('html', 'tracker_status_html', '')
						->set_html(static function () {
							$configured = \App\Settings\LacaDevTrackerClient::isConfigured();
							if ($configured) {
								return '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 14px;margin:8px 0;color:#166534;font-weight:600">✅ Tracker đã được cấu hình</div>';
							}
							return '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px 14px;margin:8px 0;color:#c2410c;font-weight:600">⚠️ Chưa cấu hình — nhập Endpoint và Secret Key bên dưới để kích hoạt</div>';
						}),

					Field::make('separator', 'sep_tracker', __('Kết nối với lacadev.com', 'laca')),

					Field::make('text', 'laca_tracker_endpoint', __('Tracker Endpoint URL', 'laca'))
						->set_width(60)
						->set_attribute('placeholder', 'https://lacadev.com/wp-json/laca/v1/tracker/log')
						->set_help_text('REST URL của lacadev CMS. Copy từ trang Project → Tracker trên lacadev.com.'),

					Field::make('text', 'laca_tracker_secret_key', __('Secret Key', 'laca'))
						->set_width(40)
						->set_attribute('placeholder', 'sk_xxxxxxxx')
						->set_attribute('type', 'password')
						->set_help_text('Secret key riêng của project. Không chia sẻ key này.'),

					Field::make('separator', 'sep_tracker_deep_fim', __('Kiểm tra sâu (nâng cao)', 'laca')),

					Field::make('checkbox', 'laca_tracker_deep_fim', __('Bật kiểm tra sâu mã nguồn (FIM)', 'laca'))
						->set_width(30),
					Field::make('html', 'tracker_deep_fim_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> So sánh md5 toàn bộ file .php/.js/.json/.htaccess/.sh trong theme và plugin (không quét <code>uploads</code>) mỗi ngày, phát hiện được cả nội dung bị sửa mà không đổi thời gian chỉnh sửa. Nặng hơn kiểm tra mặc định — chỉ bật nếu cần giám sát chặt.'),

					Field::make('html', 'tracker_save_note', '')
						->set_html(
							'<p style="font-size:12px;color:#6b7280;margin-top:4px">'
							. 'Sau khi lưu, tracker sẽ tự động gửi log khi có cập nhật plugin/theme/core hoặc phát hiện file PHP lạ trong <code>wp-content/uploads</code>.'
							. '</p>'
						),

					Field::make('separator', 'sep_tracker_real_cron', __('Cron hệ thống thật (site ít traffic)', 'laca')),

					Field::make('html', 'tracker_cron_url_desc')
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> WordPress mặc định chỉ chạy cron khi có người truy cập site (pseudo-cron). Site ít traffic nên thêm URL dưới đây vào Cron Jobs của hosting, chạy mỗi 15 phút, để tracker luôn chạy đều dù không có traffic.'),

					Field::make('html', 'tracker_cron_url')
						->set_html(static function () {
							$url = esc_url(home_url('wp-cron.php?doing_wp_cron'));
							return '<div class="laca-form-group laca-copyable-input"><label>' . esc_html__('Cron URL', 'laca') . '</label><input type="text" readonly value="' . $url . '"></div>';
						}),
				]);

			// Google reCAPTCHA

			Container::make('theme_options', __('Google reCAPTCHA', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-recaptcha', 'laca'))
				->add_fields([
					Field::make('html', 'recaptcha_info', '')
						->set_html('<div class="carbon-field-description">Bảo vệ website khỏi spam/bot bằng Google reCAPTCHA v3. <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Đăng ký Key tại đây</a>.</div>'),
					
					Field::make('text', 'recaptcha_site_key', __('Site Key', 'laca'))
						->set_width(50)
						->set_attribute('placeholder', '6Le...'),
						
					Field::make('text', 'recaptcha_secret_key', __('Secret Key', 'laca'))
						->set_width(50)
						->set_attribute('type', 'password')
						->set_attribute('placeholder', '6Le...'),
						
					Field::make('separator', 'recaptcha_separator', __('Cấu hình hiển thị', 'laca')),
					
					Field::make('checkbox', 'enable_recaptcha_login', __('Kích hoạt cho Đăng nhập', 'laca'))
						->set_width(25)
						->set_default_value(true),
						
					Field::make('checkbox', 'enable_recaptcha_register', __('Kích hoạt cho Đăng ký', 'laca'))
						->set_width(25)
						->set_default_value(true),
						
					Field::make('checkbox', 'enable_recaptcha_comment', __('Kích hoạt cho Bình luận', 'laca'))
						->set_width(25)
						->set_default_value(true),
						
					Field::make('text', 'recaptcha_score', __('Điểm tối thiểu (0.0 - 1.0)', 'laca'))
						->set_width(25)
						->set_default_value('0.5')
						->set_attribute('type', 'number')
						->set_attribute('step', '0.1')
						->set_attribute('min', '0.0')
						->set_attribute('max', '1.0')
						->set_help_text('Bot thường < 0.5. Người dùng thật thường > 0.5.'),
				]);

			// LacaDev Project Notifications
			Container::make('theme_options', __('LacaDev PM & Bots', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-project-notifications', 'laca'))
				->add_tab(__('Zalo OA (Project Manager)', 'laca'), [
					Field::make('html', 'zalo_oa_info')
						->set_html('<div class="carbon-field-description">Cấu hình Zalo Official Account (OA) để nhận cảnh báo về dự án (hết hạn hosting, domain, lỗi bảo mật).</div>'),

					Field::make('checkbox', 'enable_zalo_notify', __('Bật thông báo Zalo', 'laca')),

					Field::make('text', 'zalo_oa_access_token', __('Access Token', 'laca'))
						->set_width(50),
						
					Field::make('text', 'zalo_oa_refresh_token', __('Refresh Token', 'laca'))
						->set_width(50),

					Field::make('text', 'zalo_default_receiver', __('Zalo User ID nhận mặc định', 'laca'))
						->set_help_text('Nhập danh sách Zalo User ID (cách nhau bằng dấu phẩy) của Admin để nhận các cảnh báo quan trọng.'),
				])
				->add_tab(__('Email (Project Manager)', 'laca'), [
					Field::make('checkbox', 'enable_email_notify', __('Bật thông báo qua Email', 'laca')),

					Field::make('text', 'project_admin_email', __('Email nhận thông báo', 'laca'))
						->set_default_value(get_option('admin_email'))
						->set_help_text('Bạn có thể nhập nhiều email cách nhau bởi dấu phẩy (,).'),
				]);

            Container::make('theme_options', __('Login Socials', 'laca'))
            ->set_page_parent($options)
            ->set_page_file(__('laca-login-socials', 'laca'))
            ->add_tab(__('Google', 'laca'), [
                Field::make('html', 'login_socials_google_info', '')
                    ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Đăng nhập bằng Google</p><p style="margin:0;font-size:13px;color:#374151">Cho phép người dùng đăng nhập vào site bằng tài khoản Google thay vì username/mật khẩu. Bạn cần tạo <strong>Client ID</strong> và <strong>Client Secret</strong> trên Google Cloud Console, dán vào bên dưới, rồi cấu hình <strong>Redirect URI</strong> (đã điền sẵn) trong đó.</p></div>'),

                Field::make('checkbox', 'enable_login_google', __('Bật Login Google', 'laca')),
                Field::make('text', 'google_client_id', __('Client ID', 'laca'))
                    ->set_width(50),
                Field::make('text', 'google_client_secret', __('Client Secret', 'laca'))
                    ->set_width(50),
                Field::make('text', 'google_redirect_uri', __('Redirect URI', 'laca'))
                    ->set_attribute('readOnly', true)
                    ->set_default_value(home_url('/wp-admin/admin-ajax.php?action=social_login_callback&driver=google')),
            ]);

            // Workspace / HD Sử dụng & Dashboard Widgets Settings
            Container::make('theme_options', __('Quản trị & HD Sử dụng', 'laca'))
                ->set_page_parent($options)
                ->set_page_file(__('laca-management-settings', 'laca'))
                ->add_tab(__('Dashboard Widget', 'laca'), [
                    Field::make('html', 'dashboard_widget_desc')
                        ->set_html('<div class="carbon-field-description">Cấu hình hiển thị Widget <b>"Tổng hợp Nội dung"</b> trên màn hình Dashboard chính.</div>'),
                    
                    Field::make('multiselect', 'dashboard_widget_post_types', __('Các Post Type hiển thị', 'laca'))
                        ->set_options(function() {
                            $types = get_post_types(['public' => true, 'show_in_menu' => true], 'objects');
                            $options = [];
                            foreach ($types as $pt) {
                                if (in_array($pt->name, ['attachment', 'wp_block', 'wp_template', 'wp_template_part'])) continue;
                                $options[$pt->name] = $pt->label;
                            }
                            return $options;
                        })
                        ->set_help_text(__('Để trống để tự động hiển thị tất cả các loại nội dung quan trọng (Posts, Services, Projects, Properties...).', 'laca'))
                        ->set_default_value(['post']),

                    Field::make('text', 'dashboard_widget_limit', __('Số lượng bài hiển thị', 'laca'))
                        ->set_attribute('type', 'number')
                        ->set_default_value('5')
                        ->set_width(50),
                ])
                ->add_tab(__('Nội dung HD Sử dụng', 'laca'), [
                    Field::make('html', 'help_page_desc')
                        ->set_html('<div class="carbon-field-description">Nội dung này sẽ hiển thị ở menu <b>"HD Sử dụng"</b> dành cho khách hàng.</div>'),

                    Field::make('text', 'help_page_title', __('Tiêu đề trang', 'laca'))
                        ->set_default_value('Hướng dẫn quản trị Website Professional'),
                        
                    Field::make('textarea', 'help_page_intro', __('Đoạn giới thiệu', 'laca'))
                        ->set_default_value('Chào mừng bạn đến với hệ thống quản trị website nâng cao. Hệ thống đã được tối ưu để bạn quản lý nội dung dễ dàng nhất.'),

                    Field::make('complex', 'help_page_blocks', __('Các khối hướng dẫn (Blog, WooCommerce...)', 'laca'))
                        ->set_layout('tabbed-horizontal')
                        ->add_fields([
                            Field::make('text', 'title', __('Tiêu đề khối', 'laca')),
                            Field::make('color', 'border_color', __('Màu viền (Border top)', 'laca'))->set_default_value('#2271b1'),
                            Field::make('rich_text', 'content', __('Nội dung hướng dẫn (Link, Video, Text)', 'laca')),
                        ]),

                    Field::make('separator', 'help_separator', __('Thông tin hỗ trợ kỹ thuật', 'laca')),
                    Field::make('text', 'help_support_phone', __('Điện thoại/Zalo', 'laca')),
                    Field::make('text', 'help_support_email', __('Email', 'laca')),
                    Field::make('text', 'help_support_website', __('Website', 'laca')),
                ]);
        });
	}
}
