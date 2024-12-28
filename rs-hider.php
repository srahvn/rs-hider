<?php
/**
 * Plugin Name: RS-Hider
 * Description: Hides Slider Revolution update notifications and admin notices.
 * Version: 1.0
 * Author: Sravan M
 * License: GPL2
 * Update URI: https://github.com/srahvn/rs-hider
 */

// Hide Slider Revolution update notifications
function hide_slider_revolution_update_notice($value) {
    // Check if the update information contains Slider Revolution (revslider)
    if (isset($value->response['revslider/revslider.php'])) {
        unset($value->response['revslider/revslider.php']); // Remove the update notification
    }
    return $value;
}
add_filter('site_transient_update_plugins', 'hide_slider_revolution_update_notice');

// Suppress Slider Revolution admin notices
function disable_slider_revolution_notices() {
    if (is_admin()) {
        remove_all_actions('admin_notices');
    }
}
add_action('admin_init', 'disable_slider_revolution_notices');

// Add support for updates from GitHub
add_filter('pre_set_site_transient_update_plugins', 'rs_hider_github_plugin_update');

function rs_hider_github_plugin_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'rs-hider';
    $repo_url = 'https://api.github.com/repos/srahvn/rs-hider/releases/latest';

    $response = wp_remote_get($repo_url, ['timeout' => 15]);
    if (is_wp_error($response)) {
        return $transient;
    }

    $release_info = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($release_info['tag_name'])) {
        $new_version = $release_info['tag_name'];

        // Compare versions
        $plugin_file = plugin_basename(__FILE__);
        $current_version = $transient->checked[$plugin_file] ?? '';

        if (version_compare($current_version, $new_version, '<')) {
            $transient->response[$plugin_file] = (object) [
                'slug' => $plugin_slug,
                'new_version' => $new_version,
                'package' => $release_info['zipball_url'], // Download zip file
                'url' => $release_info['html_url'], // GitHub release URL
            ];
        }
    }

    return $transient;
}

// Add GitHub plugin information on the plugin details page
add_filter('plugins_api', 'rs_hider_github_plugin_info', 20, 3);

function rs_hider_github_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information') {
        return $res;
    }

    if ($args->slug !== 'rs-hider') {
        return $res;
    }

    $repo_url = 'https://api.github.com/repos/srahvn/rs-hider';

    $response = wp_remote_get($repo_url, ['timeout' => 15]);
    if (is_wp_error($response)) {
        return $res;
    }

    $repo_info = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($repo_info)) {
        $res = (object) [
            'name' => 'RS-Hider',
            'slug' => 'rs-hider',
            'version' => $repo_info['tag_name'] ?? '1.0',
            'author' => '<a href="https://github.com/srahvn">srahvn</a>',
            'homepage' => $repo_info['html_url'] ?? '',
            'sections' => [
                'description' => $repo_info['description'] ?? 'Hides Slider Revolution update notifications and admin notices.',
                'changelog' => 'No changelog available.',
            ],
        ];
    }

    return $res;
}
