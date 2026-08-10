<?php

namespace App\Settings;

class RequirePlugins
{
    public function __construct()
    {
        require_once APP_DIR . 'app/lib/tgm-plugin-activation/class-tgm-plugin-activation.php';
        add_action('tgmpa_register', [$this, 'registerRequirePlugins']);
    }

    public function registerRequirePlugins()
    {
        $plugins = [
            // [
            //     'name' => 'Wordfence Security – Firewall, Malware Scan, and Login Security',
            //     'slug' => 'wordfence',
            //     'required' => true,
            //     'force_activation' => true,
            //     'force_deactivation' => true,
            // ],
            // [
            //     'name' => 'WPS Hide Login',
            //     'slug' => 'wps-hide-login',
            //     'required' => true,
            //     'force_activation' => true,
            //     'force_deactivation' => true,
            // ],
        ];

        $config = [
            'id' => 'laca',
            'default_path' => '',
            'menu' => 'tgmpa-install-plugins',
            'parent_slug' => 'themes.php',
            'capability' => 'edit_theme_options',
            'has_notices' => true,
            'dismissable' => true,
            'dismiss_msg' => '',
            'is_automatic' => false,
            'message' => '',

            'strings' => [
                'page_title' => __('Please install the necessary plugins', 'laca'),
                'menu_title' => __('Plugins', 'laca'),
                'installing' => __('Installing plugins: %s', 'laca'),
                'updating' => __('Updating plugins: %s', 'laca'),
                'oops' => __('An error occurred while communicating with the plugins API.', 'laca'),
                'notice_can_install_required' => _n_noop('This theme set requires the following plugins to be installed: %1$s.', 'This theme set requires the following plugins to be installed: %1$s.', 'laca'),
                'notice_can_install_recommended' => _n_noop('This set of themes recommends installing and using the following plugins: %1$s.', 'This set of themes recommends installing and using the following plugins: %1$s.', 'laca'),
                'notice_ask_to_update' => _n_noop(
                    'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.',
                    'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.',
                    'laca'
                ),
                'notice_ask_to_update_maybe' => _n_noop(
                    'There is an update available for: %1$s.',
                    'There are updates available for the following plugins: %1$s.',
                    'laca'
                ),
                'notice_can_activate_required' => _n_noop(
                    'The following required plugin is currently inactive: %1$s.',
                    'The following required plugins are currently inactive: %1$s.',
                    'laca'
                ),
                'notice_can_activate_recommended' => _n_noop(
                    'The following recommended plugin is currently inactive: %1$s.',
                    'The following recommended plugins are currently inactive: %1$s.',
                    'laca'
                ),
                'install_link' => _n_noop(
                    'Begin installing plugin',
                    'Begin installing plugins',
                    'laca'
                ),
                'update_link' => _n_noop(
                    'Begin updating plugin',
                    'Begin updating plugins',
                    'laca'
                ),
                'activate_link' => _n_noop(
                    'Begin activating plugin',
                    'Begin activating plugins',
                    'laca'
                ),
                'return' => __('Return to Required Plugins Installer', 'laca'),
                'plugin_activated' => __('Plugin activated successfully.', 'laca'),
                'activated_successfully' => __('The following plugin was activated successfully:', 'laca'),
                'plugin_already_active' => __('No action taken. Plugin %1$s was already active.', 'laca'),
                'plugin_needs_higher_version' => __('Plugin not activated. A higher version of %s is needed for this theme. Please update the plugin.', 'laca'),
                'complete' => __('All plugins installed and activated successfully. %1$s', 'laca'),
                'dismiss' => __('Dismiss this notice', 'laca'),
                'notice_cannot_install_activate' => __('There are one or more required or recommended plugins to install, update or activate.', 'laca'),
                'contact_admin' => __('Please contact the administrator of this site for help.', 'laca'),
                'nag_type' => '', // Determines admin notice type - can only be one of the typical WP notice classes, such as 'updated', 'update-nag', 'notice-warning', 'notice-info' or 'error'. Some of which may not work as expected in older WP versions.
            ],
        ];

        tgmpa($plugins, $config);
    }
}

new RequirePlugins();
