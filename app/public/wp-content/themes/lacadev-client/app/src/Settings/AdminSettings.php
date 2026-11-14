<?php

namespace App\Settings;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Intervention\Image\ImageManagerStatic as Image;
use App\Helpers\Crypto;

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

		// Super User thêm qua UI (khác tài khoản dev gốc 'lacadev') chỉ được
		// vào 1 số trang Laca Admin nhất định — tự kiểm tra isExtraSuperUser()
		// bên trong nên gọi không điều kiện ở đây là an toàn.
		$this->restrictExtraSuperUserAccess();

		$this->addTrackerConfigParserScript();
		$this->addDashboardContactWidget();
		$this->addProjectManagerWidget();
		$this->removeDefaultWidgets();
		$this->removeDashboardWidgets();
		$this->changeHeaderUrl();
		$this->changeHeaderTitle();
		$this->changeFooterCopyright();
		$this->customizeAdminBar();
		$this->resizeOriginalImageAfterUpload();
		$this->renameUploadFileName();
		$this->addCustomExtensionsInMediaUpload();
		$this->encryptSmtpPasswordOnSave();

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
			// SVG được quản lý bởi SvgUploadGuard (lọc XSS + phân quyền)
			return array_merge($mimes, [
				'ac3' => 'audio/ac3',
				'mpa' => 'audio/MPA',
				'flv' => 'video/x-flv',
			]);
		});

		add_action('wp_ajax_mm_get_attachment_url_thumbnail', static function () {
			// Dùng lại đúng nonce "update_post_thumbnail" đã localize sẵn
			// (window.ajaxurl_params.nonce, theme/setup/assets.php) thay vì
			// thêm 1 localize riêng — trước đây KHÔNG có check_ajax_referer()/
			// current_user_can() nào cả, bất kỳ role nào đã đăng nhập đều dò
			// được URL file đính kèm theo ID bất kỳ, và không có CSRF token
			// nên cũng dễ bị trang khác điều khiển trình duyệt nạn nhân gọi hộ.
			check_ajax_referer('update_post_thumbnail', 'nonce');
			if (!current_user_can('upload_files')) {
				die();
			}

			$url = '';
			$attachmentID = isset($_REQUEST['attachmentID']) ? absint($_REQUEST['attachmentID']) : 0;
			if ($attachmentID) {
				$url = wp_get_attachment_url($attachmentID);
			}
			die($url ? esc_url_raw($url) : '');
		});
	}

	/**
	 * Mã hoá mật khẩu SMTP khi lưu option — tự động chuyển đổi plaintext → tagged encryption.
	 * Chạy ở `updated_option` thay vì Carbon Fields hook để bắt mọi cách lưu (CLI, direct update).
	 */
	public function encryptSmtpPasswordOnSave(): void
	{
		$encrypt = static function (string $option, $newValue): void {
			if ($option !== '_smtp_password') {
				return;
			}
			if (!is_string($newValue) || $newValue === '') {
				return;
			}
			// Đã ở định dạng laca:v2: → dừng tại đây để tránh vòng lặp hook
			if (Crypto::isTagged($newValue)) {
				if (!Crypto::needsRehash($newValue)) {
					return;
				}
				$plain = Crypto::decryptTagged($newValue);
				if ($plain === null) {
					return;
				}
				$newValue = $plain;
			}
			$encrypted = Crypto::encryptTagged($newValue);
			if (!empty($encrypted)) {
				update_option('_smtp_password', $encrypted, false);
			}
		};
		// Lần lưu đầu tiên đi qua added_option, các lần sau qua updated_option
		add_action('added_option', $encrypt, 10, 2);
		add_action('updated_option', static function (string $option, $oldValue, $newValue) use ($encrypt) {
			$encrypt($option, $newValue);
		}, 10, 3);
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
					<a target="_blank" href="<?php echo AUTHOR['website'] ?>" title="<?php echo AUTHOR['name'] ?>"
						style="opacity: 0.9; transition: opacity 0.2s;">
						<img style="max-width: 160px; height: auto; display: block;"
							src="<?php echo esc_url(get_template_directory_uri() . '/../resources/images/dev/moomsdev-black.png') ?>"
							alt="<?php echo AUTHOR['name'] ?>">
					</a>
					<div style="margin-top: 20px; text-align: center;">

						<p
							style="margin: 0 0 15px; font-size: 16px; font-style: italic; color: #b5b5b5; font-family: 'Quicksand', sans-serif; font-weight: 500;">
							"Coding amidst the journeys"
						</p>

						<div
							style="display: flex; gap: 12px; justify-content: center; align-items: center; font-size: 14px; color: #848383; font-family: 'Quicksand', sans-serif; font-weight: 600;">
							<a style="color: inherit; text-decoration: none;"
								href="tel:<?php echo str_replace(['.', ',', ' '], '', AUTHOR['phone_number']); ?>" target="_blank">
								<?php echo AUTHOR['phone_number'] ?>
							</a>
							<span style="color: #dcdcde;">|</span>
							<a style="color: inherit; text-decoration: none;" href="mailto:<?php echo AUTHOR['email'] ?>"
								target="_blank">
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


	public function addProjectManagerWidget()
	{
		if (!post_type_exists('project') && !class_exists('\\App\\Features\\ProjectManagement\\LacaProjectsHub')) {
			return;
		}

		add_action('wp_dashboard_setup', static function () {
			if (!current_user_can('edit_posts')) {
				return;
			}

			wp_add_dashboard_widget(
				'laca_project_manager_widget',
				__('LacaDev | Quản lý dự án', 'laca'),
				static function () {
					global $wpdb;
					
					// Đếm tổng số projects
					$sqlCount = "SELECT meta_value AS status, COUNT(post_id) AS count 
								 FROM {$wpdb->postmeta} 
								 WHERE meta_key = '_project_status' 
								   AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'project' AND post_status = 'publish')
								 GROUP BY meta_value";
					$statusCounts = $wpdb->get_results($sqlCount, OBJECT_K) ?: [];
					
					$total      = wp_count_posts('project')->publish ?? 0;
					$inProgress = $statusCounts['in_progress']->count ?? 0;
					$done       = $statusCounts['done']->count ?? 0;
					$maintenance= $statusCounts['maintenance']->count ?? 0;

					// Đếm projects sắp hết hạn hosting/domain (trong vòng 30 ngày)
					$expirySql = "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
								  WHERE meta_key IN ('_domain_expiry', '_hosting_expiry', '_ssl_expiry')
								  AND meta_value != ''";
					$expiries = $wpdb->get_results($expirySql) ?: [];
					
					$expiringSoon = 0;
					$expiringCritical = 0;
					$today = new \DateTime();
					
					foreach ($expiries as $exp) {
						try {
							$exDate = new \DateTime($exp->meta_value);
							$diff = $today->diff($exDate);
							$days = (int) $diff->format('%r%a');
							
							if ($days >= 0 && $days <= 7) {
								$expiringCritical++;
							} elseif ($days > 7 && $days <= 30) {
								$expiringSoon++;
							} elseif ($days < 0) {
								$expiringCritical++; // Overdue
							}
						} catch (\Exception $e) {
							// Ignore invalid dates
						}
					}

					$alertsCount = class_exists('\\App\\Models\\ProjectAlert') ? \App\Models\ProjectAlert::countActive() : 0;
					?>
					<div style="display:flex;gap:15px;margin-bottom:15px;">
						<div style="flex:1;background:#fafbfc;padding:15px;border-radius:6px;text-align:center;border:1px solid #e8ecf0;">
							<div style="font-size:24px;font-weight:700;color:#8aadf7;"><?php echo esc_html($total); ?></div>
							<div style="font-size:13px;color:#8b95a5;"><?php esc_html_e('Tổng dự án', 'laca'); ?></div>
						</div>
						<div style="flex:1;background:#fafbfc;padding:15px;border-radius:6px;text-align:center;border:1px solid #e8ecf0;">
							<div style="font-size:24px;font-weight:700;color:#f5c97a;"><?php echo esc_html($inProgress); ?></div>
							<div style="font-size:13px;color:#8b95a5;"><?php esc_html_e('Đang làm', 'laca'); ?></div>
						</div>
						<div style="flex:1;background:#fafbfc;padding:15px;border-radius:6px;text-align:center;border:1px solid #e8ecf0;">
							<div style="font-size:24px;font-weight:700;color:#7dd3a8;"><?php echo esc_html($done); ?></div>
							<div style="font-size:13px;color:#8b95a5;"><?php esc_html_e('Hoàn thành', 'laca'); ?></div>
						</div>
					</div>

					<div style="background:#fff;border:1px solid #ccd0d4;padding:10px 15px;border-radius:8px;margin-bottom:15px;">
						<h4 style="margin-top:0;margin-bottom:10px;"><?php esc_html_e('Cần chú ý', 'laca'); ?></h4>
						<ul style="margin:0;padding-left:20px;">
							<?php if ($expiringCritical > 0): ?>
								<li style="color:#c0392b;font-weight:600;"><?php printf(esc_html__('Có %d dịch vụ sắp/đã hết hạn (≤ 7 ngày)', 'laca'), $expiringCritical); ?></li>
							<?php endif; ?>
							<?php if ($expiringSoon > 0): ?>
								<li style="color:#b7770d;"><?php printf(esc_html__('Có %d dịch vụ sắp gia hạn (≤ 30 ngày)', 'laca'), $expiringSoon); ?></li>
							<?php endif; ?>
							<?php if ($alertsCount > 0): ?>
								<li style="color:#c0392b;"><?php printf(esc_html__('Có %d cảnh báo lỗi/bảo mật chưa xử lý', 'laca'), $alertsCount); ?></li>
							<?php endif; ?>
							<?php if ($expiringCritical == 0 && $expiringSoon == 0 && $alertsCount == 0): ?>
								<li style="color:#2e7d52;"><?php esc_html_e('Mọi thứ đều ổn, không có cảnh báo nào.', 'laca'); ?></li>
							<?php endif; ?>
						</ul>
					</div>

					<div style="text-align:right;">
						<a href="<?php echo esc_url(admin_url('edit.php?post_type=project')); ?>" class="button button-primary"><?php esc_html_e('Xem tất cả dự án', 'laca'); ?></a>
					</div>
					<?php
				}
			);
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
				'id' => 'logo_author',
				'title' => '<img src="' . esc_url(get_template_directory_uri() . '/../resources/images/dev/moomsdev-white.png') . '" class="logo-admin-bar" alt="' . AUTHOR['name'] . '">',
				'href' => $author['website'],
				'meta' => [
					'target' => '_blank',
				],
			];
			$wp_admin_bar->add_node($args);
		}, 10);
	}

	public function renameUploadFileName()
	{
		add_filter('sanitize_file_name', function ($filename) {
			$info = pathinfo($filename);
			$ext = empty($info['extension']) ? '' : '.' . $info['extension'];
			$newFileName = str_replace($ext, '', date('YmdHi') . '-' . $filename);
			$unicode = [
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
				$imgPath = $upload_dir['basedir'] . '/' . $image_data['file'];
				$image = Image::make($imgPath);
				$imgWidth = $image->width();
				$imgHeight = $image->height();
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

	/**
	 * Super User được thêm qua UI (Bảo mật → Super User, option
	 * laca_extra_super_user_logins) — KHÔNG tính login mặc định cố định
	 * trong code (vd 'lacadev', filter lacadev_super_user_logins). Chỉ nhóm
	 * "extra" này mới bị giới hạn chỉ vào được 1 số trang nhất định
	 * (xem restrictExtraSuperUserAccess()) — tài khoản dev gốc vẫn full
	 * quyền như cũ, không ảnh hưởng.
	 *
	 * @return bool
	 */
	protected function isExtraSuperUser()
	{
		$extra_logins = get_option('laca_extra_super_user_logins', []);
		if (!is_array($extra_logins) || empty($extra_logins)) {
			return false;
		}

		return in_array($this->currentUser->user_login, $extra_logins, true);
	}

	/**
	 * Slug các trang trong Laca Admin mà Super User (extra, thêm qua UI)
	 * KHÔNG được vào — 2 nhóm "Bảo mật & đăng nhập" và "Kết nối LacaDev"
	 * (xem LacaAdminMenuOrganizer::GROUPS). 'laca-security' chặn luôn TẤT
	 * CẢ tab con của nó (Kiểm tra bảo mật/Giám sát file/Quét mã độc/User
	 * ẩn/URL đăng nhập/2FA TOTP/Super User) vì các tab này chỉ là
	 * admin.php?page=laca-security&tab=X, không phải slug riêng.
	 *
	 * Lọc qua filter để dễ tuỳ biến riêng từng site mà không cần sửa trực
	 * tiếp file này.
	 *
	 * @return string[]
	 */
	public static function getExtraSuperUserDeniedLacaSlugs()
	{
		return apply_filters('lacadev_extra_super_user_denied_laca_slugs', [
			'laca-security',
			'laca-2fa',
			'laca-super-user',
			'laca-recaptcha',
			'laca-login-socials',
			'laca-block-sync',
			'lacadev-block-categories',
			'laca-tracker',
			'laca-discord-hub',
		]);
	}

	/**
	 * pagenow (WP core, không thuộc Laca Admin) mà Super User (extra) KHÔNG
	 * được vào — Giao diện > Theme (themes.php) và Theme File Editor
	 * (theme-editor.php). KHÔNG chặn cả menu "Giao diện" (Customize/
	 * Widgets/Menus vẫn vào được bình thường).
	 *
	 * @return string[]
	 */
	public static function getExtraSuperUserDeniedPagenow()
	{
		return apply_filters('lacadev_extra_super_user_denied_pagenow', [
			'themes.php',
			'theme-editor.php',
		]);
	}

	/**
	 * Super User thêm qua UI được vào HẦU HẾT mọi menu như administrator
	 * thật, CHỈ trừ: Giao diện > Theme, Theme File Editor, và trong Laca
	 * Admin thì trừ nhóm "Bảo mật & đăng nhập" + "Kết nối LacaDev"
	 * (getExtraSuperUserDeniedLacaSlugs()/getExtraSuperUserDeniedPagenow()).
	 *
	 * Đây là giới hạn ĐIỀU HƯỚNG (ẩn menu + chặn truy cập trực tiếp bằng URL
	 * vào trang xem), KHÔNG đổi role/capability thật của tài khoản —
	 * admin-post.php/admin-ajax.php/options.php (nơi các trang này xử lý
	 * submit form) vẫn luôn cho qua, vì bản thân handler nào cũng đã tự
	 * check capability riêng và nhiều tính năng khác dùng chung các endpoint
	 * này (chặn nhầm sẽ làm hỏng cả những tính năng không liên quan).
	 */
	public function restrictExtraSuperUserAccess()
	{
		add_action('admin_init', [$this, 'maybeBlockDisallowedAdminPage']);
		// Priority 999 — chạy SAU khi Carbon Fields/các feature khác đã tự
		// add_menu_page()/add_submenu_page() xong (vd createAdminOptions()
		// đăng ký 'laca-admin' qua carbon_fields_register_fields, bản thân
		// hook đó cũng add_action('admin_menu') ở priority mặc định) — phải
		// lọc SAU CÙNG mới chắc chắn còn giữ nguyên. LacaAdminMenuOrganizer
		// tự đọc lại $submenu['laca-admin'] ở priority PHP_INT_MAX (sau khi
		// mình lọc xong) nên "navigation dock" đẹp cũng tự động ẩn đúng theo,
		// không cần sửa thêm gì ở đó.
		add_action('admin_menu', [$this, 'filterAdminMenuForExtraSuperUser'], 999);
	}

	/**
	 * Named method (không phải closure ẩn danh) để test được qua Reflection
	 * mà không cần do_action('admin_init') thật — do_action('admin_init')
	 * thật sẽ kéo theo toàn bộ hook của plugin khác (WooCommerce...), không
	 * phù hợp để test cô lập riêng logic này.
	 */
	public function maybeBlockDisallowedAdminPage()
	{
		if (!$this->isExtraSuperUser()) {
			return;
		}

		global $pagenow;

		if (in_array($pagenow, self::getExtraSuperUserDeniedPagenow(), true)) {
			wp_safe_redirect(admin_url('admin.php?page=laca-admin'));
			exit;
		}

		if ($pagenow === 'admin.php' && isset($_GET['page']) && in_array($_GET['page'], self::getExtraSuperUserDeniedLacaSlugs(), true)) {
			wp_safe_redirect(admin_url('admin.php?page=laca-admin'));
			exit;
		}
	}

	/**
	 * Named method — lý do xem maybeBlockDisallowedAdminPage().
	 */
	public function filterAdminMenuForExtraSuperUser()
	{
		if (!$this->isExtraSuperUser()) {
			return;
		}

		global $submenu;

		$denied_laca_slugs = self::getExtraSuperUserDeniedLacaSlugs();
		$denied_theme_slugs = self::getExtraSuperUserDeniedPagenow();

		// Chỉ gỡ ĐÚNG mục con bị cấm — KHÔNG đụng vào $menu (top-level), vì
		// Dashboard/Posts/Media/Pages/Woo/Users/Settings/Appearance/Journal/
		// Partnership/Glossary... và cả "Laca Admin" đều phải giữ nguyên.
		if (isset($submenu['laca-admin']) && is_array($submenu['laca-admin'])) {
			foreach ($submenu['laca-admin'] as $key => $subItem) {
				$slug = $subItem[2] ?? '';
				if (in_array($slug, $denied_laca_slugs, true)) {
					unset($submenu['laca-admin'][$key]);
				}
			}
		}

		// Giao diện > Theme + Theme File Editor — vẫn giữ Customize/Widgets/
		// Menus (các mục khác trong $submenu['themes.php']).
		if (isset($submenu['themes.php']) && is_array($submenu['themes.php'])) {
			foreach ($submenu['themes.php'] as $key => $subItem) {
				$slug = $subItem[2] ?? '';
				if (in_array($slug, $denied_theme_slugs, true)) {
					unset($submenu['themes.php'][$key]);
				}
			}
		}
	}

	public function setupErrorMessage()
	{
		$logoUrl = get_template_directory_uri() . '/../resources/images/dev/moomsdev-black.png';
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
			$deniePage = [
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
		$denyPages = [
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
				if (
					in_array($menuItem[2], [
						'tools.php',
						'edit-comments.php',
						'wpseo_dashboard',
						'duplicator',
						'yit_plugin_panel',
						'woocommerce-checkout-manager',
					])
				) {
					unset($menu[$key]);
				}
			}
		});
	}

	public function hidePostMenuDefault()
	{
		add_action('admin_menu', function () {
			remove_menu_page('edit.php');
		}, 999);
	}

	public function hideCommentMenuDefault()
	{
		add_action('admin_menu', function () {
			remove_menu_page('edit-comments.php');
		}, 999);
	}

	public function createAdminOptions()
	{
		add_action('carbon_fields_register_fields', static function () {
			$options = Container::make('theme_options', __('Laca Admin', 'laca'))
				->set_page_file(__('laca-admin', 'laca'))
				->set_page_menu_position(3)
				->add_tab(__('ADMIN', 'laca'), [
					Field::make('checkbox', 'is_maintenance', __('Bật chế độ bảo trì', 'laca'))
						->set_width(30),
					Field::make('html', 'is_maintenance_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ bảo trì, tất cả người dùng sẽ không thể truy cập vào trang web của bạn. Bạn có thể tạm thời đóng băng trang web để tránh việc người dùng truy cập vào trang web của bạn.'),

					// hide theme editor
					Field::make('checkbox', 'hide_theme_editor', __('Tắt chức năng chỉnh sửa code', 'laca'))
						->set_width(30),
					Field::make('html', 'hide_theme_editor_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể chỉnh sửa code trong trang admin.'),

					Field::make('checkbox', 'disable_admin_confirm_email', __('Tắt chức năng xác thực email khi thay đổi email admin', 'laca'))
						->set_width(30),
					Field::make('html', 'disable_admin_confirm_email_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không cần phải xác thực email khi thay đổi email admin.'),

					Field::make('checkbox', 'disable_use_weak_password', __('Tắt chức năng sử dụng mật khẩu yếu', 'laca'))
						->set_width(30),
					Field::make('html', 'disable_use_weak_password_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể sử dụng mật khẩu yếu.'),

					Field::make('checkbox', 'hide_post_menu_default', __('Ẩn menu bài viết mặc định', 'laca'))
						->set_width(30),
					Field::make('html', 'hide_post_menu_default_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bài viết trong trang admin.'),

					Field::make('checkbox', 'hide_comment_menu_default', __('Ẩn menu bình luận mặc định', 'laca'))
						->set_width(30),
					Field::make('html', 'hide_comment_menu_default_desc')
						->set_width(70)
						->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bình luận trong trang admin.'),

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

			Container::make('theme_options', __('Tối ưu hóa', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-tools', 'laca'))
				->add_tab(__('Tối ưu hóa', 'laca'), [
					Field::make('html', 'opt_security_hardening_notice')
						->set_html(sprintf(
							'<div class="laca-alert laca-alert--info" style="margin-bottom:16px;">
								<span class="dashicons dashicons-info" style="color:#0284c7; margin-right:6px;"></span>
								%s <a href="%s" style="font-weight:600; text-decoration:underline;">%s</a>
							</div>',
							esc_html__('Gỡ oEmbed, ẩn phiên bản WordPress, tắt pingback nằm ở', 'laca'),
							esc_url(admin_url('admin.php?page=laca-security&tab=hardening')),
							esc_html__('Bảo mật → Gia cố →', 'laca')
						)),

					// Disable unnecessary items
					Field::make('separator', 'title_disable_unnecessary_items', __('Tắt thành phần không cần thiết', 'laca')),
					Field::make('checkbox', 'disable_use_jquery_migrate', __('Bỏ thư viện JavaScript cũ (jQuery Migrate)', 'laca'))
						->set_width(35),
					Field::make('html', 'disable_use_jquery_migrate_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Chỉ cần cho theme/plugin rất cũ (~10KB). Bật xong kiểm tra site; nếu gặp lỗi giao diện thì tắt lại.', 'laca')),

					Field::make('checkbox', 'disable_gutenberg_css', __('Tắt CSS Gutenberg ngoài Frontend', 'laca'))
						->set_width(35),
					Field::make('html', 'gutenberg_css_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Bỏ nạp file block-library CSS mặc định nếu theme tự xây dựng style riêng. Giúp giảm tải CSS.', 'laca')),

					Field::make('checkbox', 'disable_classic_css', __('Tắt CSS Classic Theme', 'laca'))
						->set_width(35),
					Field::make('html', 'classic_css_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Bỏ nạp file CSS tương thích theme cổ điển không dùng đến.', 'laca')),

					Field::make('checkbox', 'disable_emoji', __('Tắt Emoji của WordPress', 'laca'))
						->set_width(35),
					Field::make('html', 'emoji_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Bỏ ~15KB script/CSS emoji; biểu tượng cảm xúc vẫn hiển thị tốt bằng font tích hợp sẵn trên thiết bị.', 'laca')),

					// Optimization Library
					Field::make('separator', 'title_optimization_library', __('Thư viện tối ưu trải nghiệm', 'laca')),
					Field::make('checkbox', 'enable_instant_page', __('Tải trước trang khi rê chuột (Instant.page)', 'laca'))
						->set_width(35),
					Field::make('html', 'instant_page_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Tải ngầm dữ liệu trang khi người xem rê chuột lên liên kết. Nhấp chuột trang sẽ hiện gần như tức thì.', 'laca')),

					Field::make('checkbox', 'enable_smooth_scroll', __('Cuộn trang mượt mà (Smooth Scroll)', 'laca'))
						->set_width(35),
					Field::make('html', 'smooth_scroll_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Tạo hiệu ứng cuộn trang mượt mà khi người dùng điều hướng trên website.', 'laca')),

					// The function of lazy loading images
					Field::make('separator', 'title_lazy_loading_images', __('Tối ưu HTML & Hình ảnh', 'laca')),
					Field::make('checkbox', 'remove_comments', __('Xoá chú thích (comment) trong HTML, JS và CSS', 'laca')),
					Field::make('checkbox', 'remove_xhtml_closing_tags', __('Bỏ thẻ đóng XHTML ở phần tử rỗng (HTML5 chuẩn)', 'laca')),
					Field::make('checkbox', 'remove_relative_domain', __('Chuyển URL nội bộ thành đường dẫn tương đối (bỏ domain)', 'laca')),
					Field::make('checkbox', 'remove_protocols', __('Dùng URL trung lập giao thức (bỏ http: và https:)', 'laca')),
					Field::make('checkbox', 'support_multi_byte_utf_8', __('Hỗ trợ mã hoá UTF-8 nhiều byte (khi thấy ký tự lạ)', 'laca')),

					Field::make('checkbox', 'enable_advanced_resource_hints', __('Tối ưu nạp trước tài nguyên (Resource Hints)', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_advanced_resource_hints_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Thêm các chỉ dẫn dns-prefetch và preconnect giúp trình duyệt kết nối sớm tới các tài nguyên bên thứ ba.', 'laca')),

					Field::make('checkbox', 'enable_optimize_images', __('Tải ảnh chậm (Lazy Load) cho ảnh đại diện', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_optimize_images_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Chỉ tải ảnh khi người xem cuộn tới. Trang hiện nhanh hơn, tiết kiệm băng thông đáng kể.', 'laca')),

					Field::make('checkbox', 'enable_optimize_content_images', __('Tải ảnh chậm cho ảnh trong bài viết', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_optimize_content_images_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Tự động tải chậm (lazy load) toàn bộ hình ảnh nằm trong nội dung bài viết.', 'laca')),

					Field::make('checkbox', 'enable_register_service_worker', __('Bật Service Worker Cache', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_register_service_worker_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Lưu bộ nhớ đệm offline qua trình duyệt giúp tải lại trang cực nhanh. Yêu cầu website hoạt động trên giao thức HTTPS.', 'laca')),

					// Tối ưu hóa Database & Bộ nhớ
					Field::make('separator', 'title_database_memory_optimization', __('Tối ưu hóa Database & Bộ nhớ', 'laca')),
					Field::make('checkbox', 'enable_optimize_database_queries', __('Tối ưu hóa truy vấn Database', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_optimize_database_queries_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Tự động giãn cách thời gian lưu nháp tự động và tối ưu hoá bộ đệm đối tượng (object cache).', 'laca')),

					Field::make('checkbox', 'enable_optimize_sql_queries', __('Ghi nhật ký (log) truy vấn SQL chậm', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_optimize_sql_queries_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Ghi nhận các truy vấn cơ sở dữ liệu chạy lâu bất thường vào log để hỗ trợ chẩn đoán hiệu năng.', 'laca')),

					Field::make('checkbox', 'enable_optimize_memory_usage', __('Tối ưu hóa phân bổ bộ nhớ PHP', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_optimize_memory_usage_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Tự động kích hoạt bộ thu gom rác (garbage collection) và tối ưu hoá giới hạn bộ nhớ PHP khi xử lý dữ liệu lớn.', 'laca')),

					Field::make('checkbox', 'enable_cleanup_memory', __('Giải phóng bộ nhớ cuối request', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_cleanup_memory_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Giải phóng các biến tạm cuối chu trình tạo trang để giảm thiểu tối đa hiện tượng rò rỉ bộ nhớ (memory leak).', 'laca')),

					Field::make('checkbox', 'enable_performance_monitoring', __('Theo dõi hiệu năng thực thi (Server-Timing)', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_performance_monitoring_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Đo lường thời gian thực thi PHP và truy vấn DB, xuất ra HTTP header Server-Timing để dễ kiểm tra.', 'laca')),

					Field::make('select', 'revision_limit', __('Số bản sửa (revision) giữ lại', 'laca'))
						->set_options([
							''   => __('Mặc định WordPress', 'laca'),
							'0'  => __('Không lưu', 'laca'),
							'3'  => '3',
							'5'  => '5',
							'10' => '10',
							'20' => '20',
						])
						->set_default_value('')
						->set_width(50),

					Field::make('select', 'heartbeat_mode', __('Kiểm soát WordPress Heartbeat', 'laca'))
						->set_options([
							''            => __('Mặc định (15–60 giây)', 'laca'),
							'slow'        => __('Chậm (60 giây)', 'laca'),
							'editor_only' => __('Chỉ trong trình soạn bài', 'laca'),
						])
						->set_default_value('')
						->set_width(50),

					Field::make('checkbox', 'enable_post_view_counter', __('Đếm lượt xem bài viết', 'laca'))
						->set_width(35),
					Field::make('html', 'enable_post_view_counter_desc')
						->set_width(65)
						->set_html('<span class="dashicons dashicons-lightbulb" style="color:var(--laca-accent); margin-right:4px;"></span> ' . esc_html__('Đếm lượt xem bất đồng bộ bằng Beacon/REST API và gom bộ đệm ghi theo lô mỗi 5 phút, không làm chậm trang và hoàn toàn tương thích bộ nhớ đệm (cache).', 'laca')),
				]);

			// LacaDev Block Sync — chỉ site khách (hub không có BlockSyncReceiver → mở trang sẽ fatal)
			if (class_exists(\App\Settings\BlockSyncReceiver::class)) {
			Container::make('theme_options', __('Đồng bộ Block', 'laca'))
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
							return '<div style="background:#f4f4f5;border:1px solid #e4e4e7;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#18181b">🌐 Endpoint URL của site này:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#e4e4e7;color:#18181b;padding:6px 10px;border-radius:4px;display:block">' . esc_html($endpoint) . '</code>'
								. '<p style="margin:8px 0 0;font-size:12px;color:#71717a">Dán URL này vào trường <strong>Sync Endpoint URL</strong> trong project tương ứng trên lacadev.com.</p>'
								. '</div>';
						}),

					Field::make('separator', 'sep_block_catalog_heading', __('Block Catalog — chỉ dùng trên clients.lacadev.com', 'laca')),

					Field::make('html', 'block_catalog_key_display', __('Catalog Key', 'laca'))
						->set_html(static function () {
							$key = \App\Settings\BlockCatalogProvider::ensureApiKey();
							return '<div style="background:#f4f4f5;border:1px solid #e4e4e7;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#18181b">🔑 Catalog Key của site này:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#e4e4e7;color:#18181b;padding:6px 10px;border-radius:4px;display:block">' . esc_html($key) . '</code>'
								. '<p style="margin:8px 0 0;font-size:12px;color:#71717a">Chỉ cấu hình trên <strong>lacadev.com</strong> (hub) để hub đọc danh mục block từ <strong>chính site clients.lacadev.com này</strong> — không liên quan tới project của khách hàng.</p>'
								. '</div>';
						}),

					Field::make('html', 'block_catalog_endpoint_info', '')
						->set_html(static function () {
							$endpoint = rest_url('lacadev/v1/blocks-catalog');
							return '<div style="background:#f4f4f5;border:1px solid #e4e4e7;border-radius:6px;padding:12px 16px;margin:8px 0">'
								. '<p style="margin:0 0 6px;font-weight:600;color:#18181b">🌐 Catalog Endpoint URL:</p>'
								. '<code style="font-size:13px;word-break:break-all;background:#e4e4e7;color:#18181b;padding:6px 10px;border-radius:4px;display:block">' . esc_html($endpoint) . '</code>'
								. '</div>';
						}),
				]);
			}

			// Tracker Settings — chỉ site khách (hub không có LacaDevTrackerClient)
			if (class_exists(\App\Settings\LacaDevTrackerClient::class)) {
			Container::make('theme_options', __('Site Tracker', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-tracker', 'laca'))
				->add_fields([
					Field::make('html', 'tracker_info', '')
						->set_html(
							'<div style="background:#f4f4f5;border:1px solid #e4e4e7;border-radius:6px;padding:14px 16px;margin:8px 0">'
							. '<p style="margin:0 0 8px;font-weight:600;color:#18181b">📡 LacaDev Tracker</p>'
							. '<p style="margin:0;font-size:13px;color:#71717a">Gửi log tự động (cập nhật plugin/theme/core, xóa plugin, phát hiện file PHP lạ) về hệ thống quản lý dự án lacadev.com. '
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

					Field::make('separator', 'sep_tracker_fast_config', __('⚡ Dán cấu hình nhanh từ Hub (Laca CMS)', 'laca')),

					Field::make('textarea', 'laca_client_config_paste', __('Dán cấu hình JSON từ Hub', 'laca'))
						->set_rows(4)
						->set_attribute('placeholder', '{"hub_url": "...", "endpoint": "...", "project_id": "...", "keys": { ... }}')
						->set_help_text('Dán toàn bộ mã JSON cấu hình sao chép từ Project Workspace trên Hub. Hệ thống sẽ tự động bóc tách và điền vào các trường bên dưới.'),

					Field::make('separator', 'sep_tracker', __('Chi tiết kết nối & Khóa bảo mật (HMAC)', 'laca')),

					Field::make('text', 'laca_tracker_endpoint', __('Tracker Endpoint URL', 'laca'))
						->set_width(60)
						->set_attribute('placeholder', 'https://lacadev.com/wp-json/laca/v1/tracker/log')
						->set_help_text('REST URL của lacadev CMS. Tự động điền khi dán JSON cấu hình.'),

					Field::make('text', 'laca_project_id', __('Public Project ID', 'laca'))
						->set_width(40)
						->set_attribute('placeholder', 'prj_xxxxxxx')
						->set_help_text('Mã định danh dự án trên Hub (truyền trong header X-Laca-Project).'),

					Field::make('text', 'laca_key_tracker', __('Tracker Key', 'laca'))
						->set_width(33.33)
						->set_attribute('type', 'password')
						->set_attribute('placeholder', 'Key ký HMAC gửi log')
						->set_help_text('Dùng để ký chữ ký HMAC khi gửi log và heartbeat lên Hub.'),

					Field::make('text', 'laca_key_catalog', __('Block Catalog Key', 'laca'))
						->set_width(33.33)
						->set_attribute('type', 'password')
						->set_attribute('placeholder', 'Key Block Marketplace')
						->set_help_text('Dùng để xác thực tải block từ Hub hoặc nhận sync block.'),

					Field::make('text', 'laca_key_remote', __('Remote Update Key', 'laca'))
						->set_width(33.33)
						->set_attribute('type', 'password')
						->set_attribute('placeholder', 'Key nhận lệnh cập nhật từ xa')
						->set_help_text('Dùng để xác thực lệnh cập nhật plugin/theme/core gửi từ Hub.'),

					Field::make('text', 'laca_tracker_secret_key', __('Secret Key dự phòng (Legacy)', 'laca'))
						->set_width(100)
						->set_attribute('placeholder', 'sk_xxxxxxxx')
						->set_attribute('type', 'password')
						->set_help_text('Secret key cũ dự phòng nếu không dùng chữ ký HMAC.'),

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
			}

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

					Field::make('checkbox', 'enable_recaptcha_contact', __('Kích hoạt cho Form liên hệ', 'laca'))
						->set_width(25)
						->set_default_value(true)
						->set_help_text('Form tạo ở Laca Admin → Contact Form (shortcode [laca_contact_form]).'),

					Field::make('text', 'recaptcha_score', __('Điểm tối thiểu (0.0 - 1.0)', 'laca'))
						->set_width(50)
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
				])
				->add_tab(__('Telegram (Project Manager)', 'laca'), [
					Field::make('checkbox', 'enable_telegram_notify', __('Bật thông báo Telegram', 'laca')),
					Field::make('text', 'telegram_bot_token', __('Bot Token', 'laca'))
						->set_width(50),
					Field::make('text', 'telegram_chat_id', __('Chat ID', 'laca'))
						->set_width(50)
						->set_help_text('Nhập danh sách Chat ID (cách nhau bằng dấu phẩy) để nhận cảnh báo.'),
				])
				->add_tab(__('Slack (Project Manager)', 'laca'), [
					Field::make('checkbox', 'enable_slack_notify', __('Bật thông báo Slack', 'laca')),
					Field::make('text', 'slack_webhook_url', __('Webhook URL', 'laca'))
						->set_help_text('Nhập Webhook URL của kênh Slack để nhận cảnh báo.'),
				])
				->add_tab(__('Định tuyến thông báo', 'laca'), [
					Field::make('html', 'notify_routing_desc', '')
						->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">'
							. '<p style="margin:0 0 6px;font-weight:600;color:#0369a1">' . esc_html__('📡 Bảng ma trận định tuyến thông báo (Notification Bus)', 'laca') . '</p>'
							. '<p style="margin:0;font-size:13px;color:#374151">' . esc_html__('Tuỳ biến các kênh nhận thông báo (Email, Zalo, Telegram, Slack, Discord) cho từng loại sự kiện hệ thống. Hệ thống tự động khử trùng lặp (dedup) theo loại sự kiện.', 'laca') . '</p>'
							. '</div>'),

					Field::make('set', 'notify_route_project_alert', __('Cảnh báo dự án (project_alert)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['email', 'zalo', 'telegram', 'slack', 'discord']),

					Field::make('set', 'notify_route_uptime_down', __('Website không truy cập được (uptime_down)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['email', 'zalo', 'telegram', 'slack', 'discord']),

					Field::make('set', 'notify_route_uptime_up', __('Website hoạt động trở lại (uptime_up)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['email', 'zalo', 'telegram', 'slack', 'discord']),

					Field::make('set', 'notify_route_cron_late', __('Cron hệ thống bị trễ (cron_late)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['discord']),

					Field::make('set', 'notify_route_backup_outdated', __('Sao lưu quá hạn (backup_outdated)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['email', 'telegram', 'discord']),

					Field::make('set', 'notify_route_deployment', __('Cập nhật từ xa thành công (deployment)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['slack', 'discord']),

					Field::make('set', 'notify_route_client_request', __('Yêu cầu hỗ trợ từ khách hàng (client_request)', 'laca'))
						->set_options([
							'email'    => 'Email',
							'zalo'     => 'Zalo OA',
							'telegram' => 'Telegram',
							'slack'    => 'Slack',
							'discord'  => 'Discord',
						])
						->set_default_value(['email', 'zalo', 'telegram', 'slack', 'discord']),
]);

			
			if (class_exists('\\App\\Settings\\LacaTools\\BlockCatalogHub')) {
				// Block Catalog Source — nơi hub lấy danh mục block để cho site
			// khách hàng duyệt/yêu cầu đồng bộ (khác với sync_api_key/
			// sync_endpoint_url theo từng Project — đây là 1 nguồn DUY NHẤT
			// dùng chung cho toàn hub: clients.lacadev.com).
			Container::make('theme_options', __('🗂️ Block Catalog Source', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-block-catalog-source', 'laca'))
				->add_fields([
					Field::make('html', 'block_catalog_source_info', '')
						->set_html(
							'<div class="laca-alert laca-alert--info" role="status">'
							. '<span class="dashicons dashicons-info laca-alert__icon"></span>'
							. '<div class="laca-alert__content">'
							. '<div class="laca-alert__title">🗂️ ' . esc_html__('Nguồn danh mục Block', 'laca') . '</div>'
							. '<div class="laca-alert__description">' . esc_html__('Cấu hình DUY NHẤT 1 lần để hub đọc danh mục Gutenberg block từ clients.lacadev.com. Lấy Catalog Endpoint URL và Catalog Key từ trang Laca Theme → 🧩 LacaDev trên chính site clients.lacadev.com.', 'laca') . '</div>'
							. '</div></div>'
						),

					Field::make('text', 'laca_catalog_source_url', __('Catalog Endpoint URL', 'laca'))
						->set_width(60)
						->set_attribute('placeholder', 'https://clients.lacadev.com/wp-json/lacadev/v1/blocks-catalog')
						->set_help_text('URL gốc của endpoint blocks-catalog trên clients.lacadev.com.'),

					Field::make('text', 'laca_catalog_source_key', __('Catalog Key', 'laca'))
						->set_width(40)
						->set_attribute('type', 'password')
						->set_help_text('Catalog Key hiển thị ở trang Laca Theme → 🧩 LacaDev trên clients.lacadev.com.'),

					Field::make('html', 'block_catalog_refresh', '')
						->set_html(static function () {
							$nonce = wp_create_nonce('laca_refresh_block_catalog');
							return '<button type="button" id="laca-refresh-catalog-btn" class="laca-btn laca-btn--outline laca-btn--sm button" data-nonce="' . esc_attr($nonce) . '">🔄 ' . esc_html__('Làm mới danh mục ngay', 'laca') . '</button>'
								. ' <span id="laca-refresh-catalog-status" class="laca-stat__help" style="margin-left:8px"></span>'
								. '<p style="margin:8px 0 0;font-size:12px;color:#6b7280">' . esc_html__('Danh mục được cache 6 tiếng để đỡ tải clients.lacadev.com — bấm nút này nếu vừa thêm block mới bên đó và muốn site khách thấy ngay, không cần chờ.', 'laca') . '</p>'
								. '<script>
									document.getElementById("laca-refresh-catalog-btn")?.addEventListener("click", function () {
										var btn = this, statusEl = document.getElementById("laca-refresh-catalog-status");
										btn.disabled = true;
										statusEl.textContent = "⏳ Đang làm mới...";
										var fd = new FormData();
										fd.append("action", "laca_refresh_block_catalog");
										fd.append("nonce", btn.dataset.nonce);
										fetch(ajaxurl, { method: "POST", body: fd })
											.then(function (res) { return res.json(); })
											.then(function (data) {
												statusEl.textContent = data.success ? ("✅ " + data.data.message) : ("❌ " + (data.data?.message || "Lỗi"));
											})
											.catch(function (e) { statusEl.textContent = "❌ Lỗi mạng: " + e.message; })
											.finally(function () { btn.disabled = false; });
									});
								</script>';
						}),
				]);
			}

			Container::make('theme_options', __('Login Socials', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-login-socials', 'laca'))
				->add_tab(__('Google', 'laca'), [
					Field::make('html', 'login_socials_google_info', '')
						->set_html('<div style="background:#f4f4f5;border:1px solid #e4e4e7;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#18181b">🔧 Đăng nhập bằng Google</p><p style="margin:0;font-size:13px;color:#71717a">Cho phép người dùng đăng nhập vào site bằng tài khoản Google thay vì username/mật khẩu. Bạn cần tạo <strong>Client ID</strong> và <strong>Client Secret</strong> trên Google Cloud Console, dán vào bên dưới, rồi cấu hình <strong>Redirect URI</strong> (đã điền sẵn) trong đó.</p></div>'),

					Field::make('checkbox', 'enable_login_google', __('Bật Login Google', 'laca')),
					Field::make('text', 'google_client_id', __('Client ID', 'laca'))
						->set_width(50),
					Field::make('text', 'google_client_secret', __('Client Secret', 'laca'))
						->set_width(50),
					Field::make('text', 'google_redirect_uri', __('Redirect URI', 'laca'))
						->set_default_value(admin_url('admin-ajax.php?action=google_admin_callback')),

					Field::make('html', 'google_login_guide', '')
						->set_html(function () {
							$redirect_uri = admin_url('admin-ajax.php?action=google_admin_callback');
							$origin_uri = home_url();

							return '
							<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;margin:20px 0;box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:13px;line-height:1.6;color:#334155">
								<h3 style="margin:0 0 16px;font-size:15px;color:#0f172a;display:flex;align-items:center;gap:8px">
									📘 Hướng dẫn từng bước cấu hình Google Login
								</h3>

								<div style="display:grid;gap:14px">
									<div style="background:#f8fafc;border-left:4px solid #71717a;padding:12px 16px;border-radius:4px">
										<p style="margin:0 0 6px;font-weight:600;color:#27272a">Bước 1: Tạo Project trên Google Cloud</p>
										<p style="margin:0">Truy cập <a href="https://console.cloud.google.com/" target="_blank" style="color:#27272a;text-decoration:underline">Google Cloud Console</a> ➔ Bấm chọn danh sách Project ở thanh trên cùng ➔ Chọn <strong>New Project</strong> ➔ Đặt tên website (VD: <code>' . esc_html(get_bloginfo('name')) . '</code>) ➔ Bấm <strong>Create</strong>.</p>
									</div>

									<div style="background:#f8fafc;border-left:4px solid #71717a;padding:12px 16px;border-radius:4px">
										<p style="margin:0 0 6px;font-weight:600;color:#27272a">Bước 2: Cấu hình Màn hình đồng thuận (OAuth consent screen / Audience)</p>
										<ul style="margin:0;padding-left:18px">
											<li>Ở menu bên trái, vào <strong>Audience</strong> (hoặc <strong>OAuth consent screen</strong>).</li>
											<li>Chọn loại người dùng là <strong>External</strong> ➔ Bấm <strong>Create</strong> ➔ Điền tên App, Email hỗ trợ và Email nhà phát triển.</li>
											<li><strong>Cấp quyền tài khoản:</strong> Tại mục <strong>Audience</strong>, cuộn xuống <strong>Test users</strong> ➔ Bấm <strong>+ Add users</strong> ➔ Nhập email Google của bạn (email admin) ➔ Bấm <strong>Save</strong> <em>(hoặc bấm nút <strong>Publish app</strong> để công khai cho mọi tài khoản Google).</em></li>
										</ul>
									</div>

									<div style="background:#f8fafc;border-left:4px solid #71717a;padding:12px 16px;border-radius:4px">
										<p style="margin:0 0 6px;font-weight:600;color:#27272a">Bước 3: Tạo OAuth Client ID & Nhập URL chính xác</p>
										<p style="margin:0 0 8px">Vào menu bên trái <strong>Clients</strong> (hoặc <strong>Credentials</strong>) ➔ Bấm <strong>+ Create Credentials</strong> ➔ Chọn <strong>OAuth client ID</strong> ➔ Application type chọn <strong>Web application</strong>.</p>
										<div style="background:#f1f5f9;border:1px solid #cbd5e1;padding:10px 14px;border-radius:6px;margin:8px 0">
											<p style="margin:0 0 6px"><strong>1. Authorized JavaScript origins:</strong></p>
											<code style="background:#ffffff;padding:4px 8px;border-radius:4px;border:1px solid #cbd5e1;color:#0f172a;display:inline-block;font-size:12px">' . esc_html($origin_uri) . '</code>
											<p style="margin:10px 0 6px"><strong>2. Authorized redirect URIs:</strong> (Bắt buộc khớp 100% từng ký tự)</p>
											<code style="background:#ffffff;padding:4px 8px;border-radius:4px;border:1px solid #cbd5e1;color:#0f172a;display:inline-block;font-size:12px">' . esc_html($redirect_uri) . '</code>
										</div>
										<p style="margin:6px 0 0;color:#64748b;font-size:12px"><em>* Lưu ý: Sau khi bấm nút Save trên Google Console, có thể mất từ 1-3 phút để Google kích hoạt URL mới.</em></p>
									</div>

									<div style="background:#f8fafc;border-left:4px solid #10b981;padding:12px 16px;border-radius:4px">
										<p style="margin:0 0 6px;font-weight:600;color:#065f46">Bước 4: Lưu vào Website & Bắt đầu sử dụng</p>
										<p style="margin:0">Copy <strong>Client ID</strong> và <strong>Client Secret</strong> từ Google dán vào 2 ô ở trên ➔ Tích chọn <strong>"Bật Login Google"</strong> ➔ Bấm <strong>Save Changes</strong> (Lưu thay đổi) ở cột bên phải. Ra trang <code>/wp-login.php</code> bấm nút Google để đăng nhập.</p>
									</div>
								</div>

								<div style="margin-top:16px;background:#fefce8;border:1px solid #fef08a;border-radius:6px;padding:12px 16px;color:#854d0e;font-size:12px">
									<strong>💡 Mẹo quản lý nhiều website:</strong> 
									Một tài khoản Google Cloud có thể dùng cho nhiều website. Trong cùng một Client ID, tại ô <em>Authorized redirect URIs</em>, bạn có thể bấm <code>+ Add URI</code> để thêm các link callback của các website khác nhau, sau đó dùng chung Client ID & Secret này cho tất cả các site đó.
								</div>
							</div>';
						}),
				]);

			// Workspace / HD Sử dụng & Dashboard Widgets Settings
			Container::make('theme_options', __('Quản trị & HD Sử dụng', 'laca'))
				->set_page_parent($options)
				->set_page_file(__('laca-management-settings', 'laca'))
				->add_tab(__('Dashboard Widget', 'laca'), [
					Field::make('html', 'dashboard_widget_desc')
						->set_html('<div class="carbon-field-description">Cấu hình hiển thị Widget <b>"Tổng hợp Nội dung"</b> trên màn hình Dashboard chính.</div>'),

					Field::make('multiselect', 'dashboard_widget_post_types', __('Các Post Type hiển thị', 'laca'))
						->set_options(function () {
							$types = get_post_types(['public' => true, 'show_in_menu' => true], 'objects');
							$options = [];
							foreach ($types as $pt) {
								if (in_array($pt->name, ['attachment', 'wp_block', 'wp_template', 'wp_template_part']))
									continue;
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
				])
				->add_tab(__('Vai trò Khách hàng', 'laca'), [
					Field::make('html', 'client_role_desc')
						->set_html('<div class="carbon-field-description">Vai trò <b>"Khách hàng" (laca_client)</b> cho phép người dùng biên tập nội dung (bài viết, trang, media, menu, xem form liên hệ, SEO) nhưng ẩn hoàn toàn các mục kỹ thuật (Plugins, Themes, Users, Tools, Cài đặt hệ thống, Laca Admin).</div>'),

					Field::make('multiselect', 'laca_client_assigned_users', __('Gán vai trò Khách hàng cho người dùng', 'laca'))
						->set_options(function () {
							$users = get_users(['fields' => ['ID', 'user_login', 'display_name']]);
							$options = [];
							$superLogins = \App\Settings\AdminSettings::getSuperUserLogins();
							foreach ($users as $u) {
								if (in_array($u->user_login, $superLogins, true)) {
									continue;
								}
								$options[(string) $u->ID] = "{$u->display_name} ({$u->user_login})";
							}
							return $options;
						})
						->set_help_text(__('Chọn các tài khoản thuộc nhóm khách hàng để áp dụng phân quyền an toàn.', 'laca')),
				]);
		});
	}

	/**
	 * Script tự động phân tích JSON cấu hình khi dán vào ô laca_client_config_paste
	 */
	public function addTrackerConfigParserScript(): void
	{
		add_action('admin_footer', static function () {
			$screen = get_current_screen();
			if (!$screen || strpos((string) $screen->id, 'laca-tracker') === false) {
				return;
			}
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function () {
				function setupParser() {
					var textarea = document.querySelector('textarea[name*="laca_client_config_paste"]');
					if (!textarea || textarea.dataset.parsedAttached) {
						return;
					}
					textarea.dataset.parsedAttached = 'true';

					function parseConfig() {
						var raw = textarea.value.trim();
						if (!raw || raw.charAt(0) !== '{') {
							return;
						}
						try {
							var data = JSON.parse(raw);
							var setVal = function (fieldName, val) {
								if (!val) return;
								var input = document.querySelector('input[name*="' + fieldName + '"]');
								if (input) {
									input.value = val;
									input.dispatchEvent(new Event('input', { bubbles: true }));
									input.dispatchEvent(new Event('change', { bubbles: true }));
								}
							};

							if (data.endpoint) {
								setVal('laca_tracker_endpoint', data.endpoint);
							} else if (data.hub_url) {
								setVal('laca_tracker_endpoint', data.hub_url.replace(/\/+$/, '') + '/wp-json/laca/v1/tracker/log');
							}

							if (data.project_id) {
								setVal('laca_project_id', data.project_id);
							}

							if (data.keys) {
								if (data.keys.tracker) {
									setVal('laca_key_tracker', data.keys.tracker);
									setVal('laca_tracker_secret_key', data.keys.tracker);
								}
								if (data.keys.catalog) {
									setVal('laca_key_catalog', data.keys.catalog);
								}
								if (data.keys.remote) {
									setVal('laca_key_remote', data.keys.remote);
								}
							}
						} catch (e) {
							console.warn('[LacaDev Tracker] Không thể parse JSON cấu hình:', e);
						}
					}

					textarea.addEventListener('input', parseConfig);
					textarea.addEventListener('paste', function () {
						setTimeout(parseConfig, 60);
					});
				}

				setupParser();
				var observer = new MutationObserver(setupParser);
				observer.observe(document.body, { childList: true, subtree: true });
			});
			</script>
			<?php
		});
	}
}
