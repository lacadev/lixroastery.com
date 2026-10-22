<?php

namespace App\Settings;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class LayoutModeMeta
{
	public function __construct()
	{
		add_action('carbon_fields_register_fields', [$this, 'registerMeta']);
		// Không lưu meta khi chọn inherit (để không sinh rác post_meta)
		add_action('save_post', [$this, 'cleanupInheritMeta'], 99, 1);
	}

	public function registerMeta(): void
	{
		$postTypes = get_post_types(['public' => true, 'show_ui' => true], 'names');
		unset($postTypes['attachment']);

		if (empty($postTypes)) {
			$postTypes = ['page', 'post'];
		}

		Container::make('post_meta', __('Khung trang', 'laca'))
			->where('post_type', 'IN', array_values($postTypes))
			->set_context('side')
			->set_priority('default')
			->add_fields([
				Field::make('select', 'layout_container_mode', __('Khung trang', 'laca'))
					->set_options([
						'inherit' => __('Kế thừa từ site', 'laca'),
						'fluid'   => __('Fluid (Toàn rộng)', 'laca'),
						'boxed'   => __('Boxed (Cố định max-width)', 'laca'),
					])
					->set_default_value('inherit')
					->set_help_text(__('Chọn chế độ khung cho riêng trang này. Mặc định kế thừa từ cài đặt của website.', 'laca')),
			]);
	}

	public function cleanupInheritMeta($postId): void
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		$val = get_post_meta($postId, '_layout_container_mode', true);
		if ($val === 'inherit' || $val === '') {
			delete_post_meta($postId, '_layout_container_mode');
		}
	}
}
