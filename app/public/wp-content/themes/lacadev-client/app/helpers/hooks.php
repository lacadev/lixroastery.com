<?php
/**
 * Thay đổi default order post của WP_Query
 * 
 * DISABLED: This was forcing ALL queries to use menu_order ASC
 * Custom ordering is now handled by PostOrder.php
 */

// add_action('pre_get_posts', static function ($query) {
//     /**
//      * @var \WP_Query $query
//      */
//     if ($query->is_main_query()) {
//         $query->set('orderby', 'menu_order');
//         $query->set('order', 'ASC');
//     }
// });
