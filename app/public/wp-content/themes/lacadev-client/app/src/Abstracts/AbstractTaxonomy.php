<?php

namespace App\Abstracts;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

abstract class AbstractTaxonomy
{

	public $taxonomy = 'category';

	public $slug = 'category';

	public $postTypes = ['post'];

	public $singular = 'Chuyên mục';

	public $plural = 'Categories';

	public $hierarchy = true;

	/**
	 * The name of the custom meta box to use on the post editing screen for this taxonomy
	 *
	 * @var string
	 *
	 * 'radio' - a meta box with radio inputs
	 * 'simple' - a meta box with a simplified list of checkboxes
	 * 'dropdown' - a meta box with a dropdown menu
	 * 'custom_callback_function' - pass the name of a callback function, eg my_super_meta_box()
	 * 'false' - Boolean to remove the meta box completely
	 * 'null' (default) - the standard WordPress meta box is used
	 */
	public $metaBox = null;

	/**
	 * Whether to always show checked terms at the top of the meta box. This allows you to override WordPress' default behaviour if necessary.
	 *
	 * @var bool
	 *
	 * 'false' - Default if using a custom_callback_function in the meta_box arguments
	 * 'true' - Default if not using a custom_callback_function in the meta_box arguments
	 */
	public $checkOnTop = true;

	/**
	 * Whether to show this taxonomy on the 'At a Glance' section of the admin dashboard
	 *
	 * @var bool
	 */
	public $dashboardGlance = false;

	public function __construct()
	{
		add_action('init', function () {
			register_extended_taxonomy(
				$this->taxonomy,
				$this->postTypes,
				[
					'meta_box'          => $this->metaBox,
					'checked_ontop'     => $this->checkOnTop,
					'dashboard_glance'  => $this->dashboardGlance,
					'allow_hierarchy'   => $this->hierarchy,
					'labels'            => $this->getLabels(),
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rest_base'         => $this->slug,
					//                    'capabilities'      => [
					//                        'manage_terms' => 'manage_' . $this->taxonomy,
					//                        'edit_terms'   => 'edit_' . $this->taxonomy,
					//                        'delete_terms' => 'delete_' . $this->taxonomy,
					//                        'assign_terms' => 'assign_' . $this->taxonomy,
					//                    ],
				],
				[
					'singular' => $this->singular,
					'plural'   => $this->plural,
					'slug'     => $this->slug,
				]
			);
		});

		add_action('carbon_fields_register_fields', [$this, 'metaFields']);

		//        $this->createRequirePages();

		//        Container::make('term_meta', __('Advanced', 'laca'))
		//                 ->where('term_taxonomy', '=', $this->taxonomy)
		//                 ->add_fields([
		//                                  Field::make('checkbox', 'show_homepage', __('Hiển thị ngoài trang chủ', 'laca')),
		//                                  Field::make('text', 'thu_tu', __('Thứ tự', 'laca'))->set_attribute('type', 'number'),
		//                              ]);
	}

	public function getLabels()
	{
		return [
			'name'              => $this->plural,
			'singular_name'     => $this->singular,
			'menu_name'         => $this->singular,
			'all_items'         => __('All items', 'laca'),
			'edit_item'         => __('Edit item', 'laca'),
			'view_item'         => __('View item', 'laca'),
			'update_item'       => __('Update item', 'laca'),
			'add_new_item'      => __('Add new item', 'laca'),
			'new_item_name'     => __('New item name', 'laca'),
			'parent_item'       => __('Parent item', 'laca'),
			'parent_item_colon' => __('Mục cha:', 'laca'),
			'search_items'      => __('Search', 'laca'),
			'popular_items'     => __('Popular items', 'laca'),
			// 'most_used'         => __('Thường dùng', 'laca'),
			'not_found'         => __('Not found', 'laca'),
		];
	}

	public function createRequirePages()
	{
		$fileTax  = $this->taxonomy === 'category' ? 'category' : 'taxonomy-' . $this->taxonomy;
		$filename = __DIR__ . '/../../../theme/' . $fileTax . '.php';
		if (!file_exists($filename)) {
			file_put_contents($filename, '<?php get_header() ?>
<?php get_template_part(\'breadcrumb\') ?>
<div class="content">
    <div class="container">
        <div class="page-content">
            <div class="content-wrapper">
                <?php if(have_posts()) : ?>
                    <?php while(have_posts()) : $currentPage->the_post() ?>
                        <?php the_content() ?>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
                <?php wp_reset_query(); ?>
            </div>
        </div>
    </div>
</div>
<?php get_footer() ?>');
		}
	}

	/**
	 * Document: https://docs.carbonfields.net/#/containers/term-meta?id=term-meta
	 */
	public function metaFields()
	{
		// Container::make('post_meta', __('Cài đặt chung', 'laca'))
		//          ->set_context('side')// normal, advanced, side or carbon_fields_after_title
		//          ->set_priority('high')// high, core, default or low
		//          ->where('post_type', 'IN', [$this->post_type])
		//          ->add_fields([
		//              Field::make('checkbox', 'noi_bat', __('Đánh dấu nổi bật', 'laca')),
		//              Field::make('text', 'so_luot_xem', __('Số lượt xem', 'laca'))->set_attribute('type', 'number'),
		//          ]);
	}
}
