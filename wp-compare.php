<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              wp-mike.com
 * @since             0.1
 * @package           Wp_Compare
 *
 * @wordpress-plugin
 * Plugin Name:       WP Compare
 * Plugin URI:        wp-mike.com
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           0.2.4
 * Author:            Mike Miler
 * Author URI:        wp-mike.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-compare
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WP_COMPARE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-compare-activator.php
 */
function activate_wp_compare() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-compare-activator.php';
	Wp_Compare_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-compare-deactivator.php
 */
function deactivate_wp_compare() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-compare-deactivator.php';
	Wp_Compare_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_compare' );
register_deactivation_hook( __FILE__, 'deactivate_wp_compare' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-compare.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_compare() {

	$plugin = new Wp_Compare();
	$plugin->run();

}

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://bitbucket.org/wpmike/compare-plugin',
	__FILE__, //Full path to the main plugin file or functions.php.
	'wp-compare'
);

/*$myUpdateChecker->setAuthentication(array(
	'consumer_key' => 'QawnqxjWe443wnHUCJ',
	'consumer_secret' => 'gZ9SGH3d585aFkWZsh8fhbHjnd9sAMzq',
));*/

//$myUpdateChecker->setBranch('stable-branch');


run_wp_compare();

// Add hook to auto sync posts when they are published
add_action('transition_post_status', 'send_new_post', 10, 3);

// Sync all pages and posts when there is a new page or post published
function sync_urls_on_publish($new_status, $old_status, $post) {
    if('publish' === $new_status && 'publish' !== $old_status && in_array( $post->post_type, array( 'post', 'page') ) ) {
        $wp_compare = new Wp_Compare();
        $wp_compare->sync_posts();
    }
}
