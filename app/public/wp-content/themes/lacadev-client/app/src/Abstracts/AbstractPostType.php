<?php

namespace App\Abstracts;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

abstract class AbstractPostType
{
	/**
	 * @var null $post_type
	 */
	public $post_type = 'post';

	/**
	 * Name for one object of this post type.
	 *
	 * @var string
	 */
	public $singularName = 'Post';

	/**
	 * Name for one object of this post type in plural
	 *
	 * @var string
	 */
	public $pluralName = 'Posts';

	public $slug = 'post';

	/**
	 * Thay doi tieu de placeholder
	 *
	 * @var string
	 */
	public $titlePlaceHolder = 'Enter title here';

	public $excerptLabel = 'Excerpt';

	/**
	 * A short descriptive summary of what the post type is.
	 *
	 * @var string
	 */
	public $description = 'Default description';

	/**
	 * Whether to exclude posts with this post type from front end search results.
	 * Note: If you want to show the posts's list that are associated to taxonomy's terms, you must set exclude_from_search to false
	 * (ie : for call site_domaine/?taxonomy_slug=term_slug or site_domaine/taxonomy_slug/term_slug).
	 * If you set to true, on the taxonomy page (ex: taxonomy.php) WordPress will not find your posts and/or pagination will make 404 error...
	 * 'false' - site/?s=search-term will include posts of this post type.
	 * 'true' - site/?s=search-term will not include posts of this post type.
	 *
	 * @var bool
	 */
	public $excludeFromSearch = false;

	/**
	 * Whether queries can be performed on the front end as part of parse_request().
	 * Note: The queries affected include the following (also initiated when rewrites are handled). If query_var is empty, null, or a boolean FALSE,
	 * WordPress will still attempt to interpret it (4.2.2) and previews/views of your custom post will return 404s.
	 * ?post_type={post_type_key}
	 * ?{post_type_key}={single_post_slug}
	 * ?{post_type_query_var}={single_post_slug}
	 *
	 * @var bool
	 */
	public $publiclyQueryable = true;

	/**
	 * Whether to generate a default UI for managing this post type in the admin.
	 * Note: _built-in post types, such as post and page, are intentionally set to false.
	 * 'false' - do not display a user-interface for this post type
	 * 'true' - display a user-interface (admin panel) for this post type
	 *
	 * @var bool
	 */
	public $showUi = true;

	/**
	 * Whether post_type is available for selection in navigation menus.
	 *
	 * @var bool
	 */
	public $showInNavMenus = true;

	/**
	 * Where to show the post type in the admin menu. show_ui must be true.
	 * Note: When using 'some string' to show as a submenu of a menu page created by a plugin, this item will become the first submenu item, and replace the location of the top-level link. If this isn't desired, the plugin that creates the menu page needs to set the add_action priority for admin_menu to 9 or lower.
	 * Note: As this one inherits its value from show_ui, which inherits its value from public, it seems to be the most reliable property to determine, if a post type is meant to be publicly useable. At least this works for _builtin post types and only gives back post and page.
	 * 'false' - do not display in the admin menu
	 * 'true' - display as a top level menu
	 * 'some string' - If an existing top level page such as 'tools.php' or 'edit.php?post_type=page', the post type will be placed as a sub menu of that.
	 *
	 * @var bool|string
	 */
	public $showInMenu = true;

	/**
	 * Whether to make this post type available in the WordPress admin bar.
	 *
	 * @var bool
	 */
	public $show_in_admin_bar = true;

	/**
	 * The position in the menu order the post type should appear. show_in_menu must be true.
	 *
	 * @var int
	 * 5 - below Posts
	 * 10 - below Media
	 * 15 - below Links
	 * 20 - below Pages
	 * 25 - below comments
	 * 60 - below first separator
	 * 65 - below Plugins
	 * 70 - below Users
	 * 75 - below Tools
	 * 80 - below Settings
	 * 100 - below second separator
	 */
	public $menuPosition = 5;

	/**
	 * The url to the icon to be used for this menu or the name of the icon from the icon font
	 * Example:
	 * 'dashicons-video-alt' (Uses the video icon from Dashicons[2])
	 * 'get_template_directory_uri() . "/images/cutom-post_type-icon.png"' (Use a image located in the current theme)
	 * More example at https://developer.wordpress.org/resource/dashicons/#format-image
	 *
	 * @var string
	 */
	public $menuIcon = 'dashicons-admin-links';

	/**
	 * Whether the post type is hierarchical (e.g. page). Allows Parent to be specified.
	 * The 'supports' parameter should contain 'page-attributes' to show the parent select box on the editor page.
	 * Note: this parameter was intended for Pages. Be careful when choosing it for your custom post type.
	 * If you are planning to have very many entries (say - over 2-3 thousand), you will run into load time issues.
	 * With this parameter set to true WordPress will fetch all IDs of that particular post type on each administration page load for your post type.
	 * Servers with limited memory resources may also be challenged by this parameter being set to true.
	 *
	 * @var bool
	 */
	public $hierarchical = false;

	/**
	 * An alias for calling add_post_type_support() directly. As of 3.5, boolean false can be passed as value instead of an array to prevent default
	 * (title and editor) behavior.
	 *
	 * @var array|boolean
	 */
	public $supports = [
		'title',
		'editor',
		'author',
		'thumbnail',
		'excerpt',
		'trackbacks',
		'custom-fields',
		'comments',
		'revisions',
		'page-attributes',
		'post-formats',
	];

	/**
	 * Whether to expose this post type in the REST API.
	 *
	 * @var bool
	 */
	public $showInRest = true;

	public $quickEdit = true;

	public $archiveNoPaging = false;

	public $showThumbnailOnList = false;

	public function __construct()
	{
		add_action('init', function () {
			register_extended_post_type(
				$this->post_type,
				[
					'public'              => true,
					'show_ui'             => $this->showUi,
					'capability_type'     => 'post',
					'show_in_nav_menus'   => $this->showInNavMenus,
					'show_in_menu'        => $this->showInMenu,
					'show_in_feed'        => true,
					'archive'             => [
						'nopaging' => $this->archiveNoPaging,
					],
					'quick_edit'          => $this->quickEdit,
					'labels'              => $this->getLabels(),
					'menu_icon'           => $this->menuIcon,
					'supports'            => $this->supports,
					'description'         => $this->description,
					'exclude_from_search' => $this->excludeFromSearch,
					'publicly_queryable'  => $this->publiclyQueryable,
					'hierarchical'        => $this->hierarchical,
					'show_in_rest'        => $this->showInRest,
					'rest_base'           => $this->post_type,
					'show_in_admin_bar'   => $this->show_in_admin_bar,
					'menu_position'       => 25,
					'has_archive' => true,
					"rewrite" => ["with_front" => true],
				],
				[
					'singular' => $this->singularName,
					'plural'   => $this->pluralName,
					'slug'     => $this->slug,
				]
			);
		});

		add_filter('enter_title_here', function ($title) {
			$screen = get_current_screen();
			if ($this->post_type === $screen->post_type) {
				$title = $this->titlePlaceHolder;
			}

			return $title;
		});

		add_filter('gettext', function ($translation, $original) {
			if ($original === 'Excerpt') {
				return $this->excerptLabel;
			} elseif (false !== strpos($original, 'Excerpts are optional hand-crafted summaries of your')) {
				return '';
			}
			return $translation;
		}, 10, 2);

		add_filter('manage_' . $this->post_type . '_posts_columns', function ($cols) {
			$cols['title'] = $this->titlePlaceHolder;
			return $cols;
		}, 9900, 9900);

		if ($this->showThumbnailOnList) {
			add_filter('manage_' . $this->post_type . '_posts_columns', [$this, 'editAdminColumn'], 9999, 9999);
			add_action('manage_' . $this->post_type . '_posts_custom_column', [$this, 'editAdminColumnData'], 10, 2);
			add_action('admin_head', [$this, 'adminCustomColumnStyle'], 10, 2);
		}

		add_action('carbon_fields_register_fields', [$this, 'metaFields']);

		// add_action('carbon_fields_register_fields', function () {
		//     Container::make('post_meta', __('Cài đặt', 'laca'))
		//         ->set_context('side') // normal, advanced, side or carbon_fields_after_title
		//         ->set_priority('high') // high, core, default or low
		//         ->where('post_type', 'IN', [$this->post_type])
		//         ->add_fields([
		//             Field::make('checkbox', 'is_feature', __('Nổi bật', 'laca'))->set_default_value(false),
		//         ]);
		// });
	}

	/**
	 * Get array of post type label
	 *
	 * @return array
	 */
	protected function getLabels()
	{
		return [
			'name'                  => $this->pluralName,
			'singular_name'         => $this->singularName,
			'add_new'               => __('Add new ' . $this->singularName, 'laca'),
			'add_new_item'          => __('Add new ' . $this->singularName, 'laca'),
			'edit_item'             => __('Edit ' . $this->singularName, 'laca'),
			'new_item'              => __('New ' . $this->singularName, 'laca'),
			'view_item'             => __('View ' . $this->singularName, 'laca'),
			'search_items'          => __('Search', 'laca'),
			'not_found'             => __('Not found', 'laca'),
			'not_found_in_trash'    => __('Not found in trash', 'laca'),
			'parent_item_colon'     => __('Parent:', 'laca'),
			'all_items'             => __('All ' . $this->pluralName, 'laca'),
			'archives'              => __($this->pluralName, 'laca'),
			'insert_into_item'      => __('Insert ' . $this->pluralName, 'laca'),
			'uploaded_to_this_item' => __('Uploaded to this ' . $this->pluralName, 'laca'),
			'featured_image'        => __('Featured image', 'laca'),
			'set_featured_image'    => __('Set featured image', 'laca'),
			'remove_featured_image' => __('Remove featured image', 'laca'),
			'use_featured_image'    => __('Use featured image', 'laca'),
			'menu_name'             => $this->pluralName,
			'name_admin_bar'        => $this->singularName,
			//'filter_items_list' - String for the table views hidden heading.
			//'items_list_navigation' - String for the table pagination hidden heading.
			//'items_list' - String for the table hidden heading.
			//'name_admin_bar' - String for use in New in Admin menu bar. Default is the same as `singular_name`.
		];
	}


	/**
	 * Custom style – Extracted to resources/styles/admin/_admin-column-style.scss
	 */
	public function adminCustomColumnStyle(): void
	{
		// Styles enqueued via wp_enqueue_style('lacadev-admin') in the theme.
	}

	/**
	 * Hook custom admin columns
	 *
	 * @param $columns
	 *
	 * @return array
	 */
	public function editAdminColumn($columns): array
	{
		$totalColumn = count($columns);
		$columns     = insertArrayAtPosition($columns, ['featured_image' => 'Image'], 1);
		//        $columns     = insertArrayAtPosition($columns, ['is_feature' => __('Nổi bật', 'laca')], $totalColumn - 1);

		return $columns;
	}

	/**
	 * Hook custom admin column data
	 *
	 * @param $column
	 * @param $postId
	 */
	public function editAdminColumnData($column, $postId)
	{
		switch ($column) {
			case 'featured_image':
				// Generate nonce for CSRF protection
				$nonce = wp_create_nonce('update_post_thumbnail');
				$nonce_attr = esc_attr($nonce);
				$post_id_attr = absint($postId);
				
				$thumbnailUrl = get_the_post_thumbnail_url($postId, 'thumbnail');
				
				if ($thumbnailUrl) {
					// Has thumbnail - show image with remove button
					echo "<div class='thumbnail-wrap'>";
					echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
					echo "<img src='" . esc_url($thumbnailUrl) . "' class='thumbnail-preview' alt='Thumbnail'/>";
					echo "</a>";
					// Remove button (X)
					echo "<a class='remove-thumbnail' href='javascript:void(0)' data-trigger-remove-thumbnail data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}' title='Remove thumbnail'>
							<svg viewBox='0 0 12 12'>
								<path d='M11 1L1 11M1 1l10 10' stroke='currentColor' stroke-width='2' stroke-linecap='round'/>
							</svg>
						</a>";
					echo "</div>";
					} else {
					// No thumbnail - show WordPress-style "Set featured image" link
					echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
					echo "<div class='no-image-text'>Choose image</div>";
					echo "</a>";
				}
				break;
		}
	}

	/**
	 * Document: https://docs.carbonfields.net/#/containers/post-meta
	 */
	public function metaFields()
	{
		// To be implemented by child classes
	}
}
