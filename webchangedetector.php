<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              webchangedetector.com
 * @since             0.1
 * @package           WebChangeDetector
 *
 * @wordpress-plugin
 * Plugin Name:       WebChangeDetector
 * Plugin URI:        webchangedetector.com
 * Description:       Detect changes on your website visually before and after updating your website. You can also run automatic change detections and get notified on changes of your website.
 * Version:           1.1.3
 * Author:            Mike Miler
 * Author URI:        webchangedetector.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       webchangedetector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WebChangeDetector_VERSION', '1.1.3');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-webchangedetector-activator.php
 */
function activate_webchangedetector()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-webchangedetector-activator.php';
    WebChangeDetector_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-webchangedetector-deactivator.php
 */
function deactivate_webchangedetector()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-webchangedetector-deactivator.php';
    WebChangeDetector_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_webchangedetector');
register_deactivation_hook(__FILE__, 'deactivate_webchangedetector');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-webchangedetector.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_webchangedetector()
{
    $plugin = new WebChangeDetector();
    $plugin->run();
}

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://www.webchangedetector.com/plugin.json',
    __FILE__, //Full path to the main plugin file or functions.php.
    'webchangedetector'
);

run_webchangedetector();
