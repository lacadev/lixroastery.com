<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom menu walker to simplify HTML output and class handling.
 *
 * @since 0.1
 */
class Laca_Menu_Walker extends Walker_Nav_Menu
{
    /**
     * Starts the list before the elements are added.
     *
     * @param string   $output Used to append additional content (passed by reference).
     * @param int      $depth  Depth of menu item. Used for padding.
     * @param stdClass $args   An object of wp_nav_menu() arguments.
     */
    /**
     * Custom method to add a button before the menu
     * This will be called from the walker_nav_menu_start_el filter
     *
     * @param string   $output The menu HTML output
     * @param array    $args   Menu arguments
     * @return string Modified menu HTML
     */
    public static function add_menu_button($output, $args)
    {
        if (is_object($args) && isset($args->menu_class) && $args->menu_class === 'main-menu' && !isset($args->button_added)) {
            // Set a flag to prevent adding the button multiple times
            $args->button_added = true;
            return '<button class="act-menu">menu</button>' . $output;
        }
        return $output;
    }

    /**
     * {@inheritdoc}
     */
    function start_lvl(&$output, $depth = 0, $args = [])
    {
        $output .= '<ul class="sub-menu">';
    }

    /**
     * {@inheritdoc}
     */
    function end_lvl(&$output, $depth = 0, $args = [])
    {
        $output .= '</ul>';
    }

    /**
     * {@inheritdoc}
     */
    function start_el(&$output, $item, $depth = 0, $args = [], $id = 0)
    {
        $classes = empty($item->classes) ? [] : (array)$item->classes;
        
        // Chỉ giữ lại class cần thiết cho trạng thái active/children.
        $allowed_classes = [
            'actived-menu',
            'current-menu-item',
            'current-menu-parent',
            'current-menu-ancestor',
            'current_page_item',
            'current_page_parent',
            'current_page_ancestor',
            'menu-item-has-children',
        ];

        $new_classes = array_values(array_intersect($classes, $allowed_classes));

        // Gom mọi trạng thái current* về 1 class hiển thị chung.
        $active_markers = [
            'actived-menu',
            'current-menu-item',
            'current-menu-parent',
            'current-menu-ancestor',
            'current_page_item',
            'current_page_parent',
            'current_page_ancestor',
        ];
        if (!empty(array_intersect($new_classes, $active_markers))) {
            $new_classes[] = 'actived-menu';
        }

        // Final output keeps only actived state + children state.
        $new_classes = array_values(array_intersect($new_classes, [
            'actived-menu',
            'has-children',
            'menu-item-has-children',
        ]));
        
        // Thêm class nếu có menu con
        if (in_array('menu-item-has-children', $classes, true) || !empty($args->has_children) || $this->has_children) {
            $new_classes[] = 'has-children';
            $new_classes[] = 'menu-item-has-children';
        }

        // Tạo chuỗi class gọn gàng
        $class_names = join(' ', array_filter(array_unique($new_classes)));
        $class_names = $class_names ? ' class="menu-item ' . esc_attr($class_names) . '"' : ' class="menu-item"';

        $output .= '<li' . $class_names . '>';

        // Custom menu icon (optional)
        // $icon = carbon_get_nav_menu_item_meta($item->ID, 'icon');
        // $output .= '<img src="' . wp_get_attachment_image_url($icon) . '" alt="">';

        $attributes = !empty($item->attr_title) ? ' title="' . esc_attr($item->attr_title) . '"' : '';
        $attributes .= !empty($item->target) ? ' target="' . esc_attr($item->target) . '"' : '';
        $attributes .= !empty($item->xfn) ? ' rel="' . esc_attr($item->xfn) . '"' : '';
        $attributes .= !empty($item->url) ? ' href="' . esc_attr($item->url) . '"' : '';

        $before = (is_object($args) && isset($args->before)) ? $args->before : '';
        $link_before = (is_object($args) && isset($args->link_before)) ? $args->link_before : '';
        $link_after = (is_object($args) && isset($args->link_after)) ? $args->link_after : '';
        $after = (is_object($args) && isset($args->after)) ? $args->after : '';

        $item_output = $before;
        $item_output .= '<a' . $attributes . '>';
        // Hiển thị menu_img nếu có
        $menu_img_id = function_exists('carbon_get_nav_menu_item_meta') ? carbon_get_nav_menu_item_meta($item->ID, 'menu_img') : '';
        if ($menu_img_id) {
            $menu_img_url = wp_get_attachment_image_url($menu_img_id, 'thumbnail');
            if ($menu_img_url) {
                $item_output .= '<img src="' . esc_url($menu_img_url) . '" alt="' . esc_attr($item->title) . '" class="menu-img">';
            }
        }
        $item_output .= $link_before . apply_filters('the_title', $item->title, $item->ID) . $link_after;
        // Uncomment if you want a caret for dropdowns:
        // if ($has_children) {
        //     $item_output .= '<span class="caret"></span>';
        // }
        $item_output .= '</a>';
        $item_output .= $after;

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }

    /**
     * {@inheritdoc}
     */
    function end_el(&$output, $item, $depth = 0, $args = [])
    {
        $output .= "</li>";
    }
}
