<?php
/**
 * Plugin Name:       Onu Exchange Rates and Converter for BNR
 * Plugin URI:        https://buymeacoffee.com/constantinonu
 * Description:       Exchange rates from the National Bank of Romania (BNR), auto-updated daily, currency converter, widget, shortcode, Gutenberg block, and Elementor widget.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Constantin Onu
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       curs-bnr-convertor-valutar
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants.
define( 'ONUEXRA_VERSION', '1.0.4' );
define( 'ONUEXRA_DB_VERSION', '1.0.0' );
define( 'ONUEXRA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ONUEXRA_URL', plugin_dir_url( __FILE__ ) );
define( 'ONUEXRA_BASENAME', plugin_basename( __FILE__ ) );

// Include necessary files.
require_once ONUEXRA_PATH . 'includes/class-onuexra-db.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-fetcher.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-converter.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-cron.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-rest.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-shortcodes.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-widget.php';
require_once ONUEXRA_PATH . 'includes/class-onuexra-admin.php';

/**
 * Activation Hook.
 */
register_activation_hook( __FILE__, 'onuexra_activate_plugin' );
function onuexra_activate_plugin() {
	OnuExra\DB::create_tables();
	OnuExra\Cron::schedule();
}

/**
 * Deactivation Hook.
 */
register_deactivation_hook( __FILE__, 'onuexra_deactivate_plugin' );
function onuexra_deactivate_plugin() {
	OnuExra\Cron::clear();
}

/**
 * Initialize Plugin Modules.
 */
add_action( 'plugins_loaded', 'onuexra_init_plugin' );
function onuexra_init_plugin() {
	OnuExra\DB::init();
	OnuExra\Fetcher::init();
	OnuExra\Converter::init();
	OnuExra\Cron::init();
	OnuExra\REST::init();
	OnuExra\Shortcodes::init();
	OnuExra\Widget::register();
	OnuExra\Admin::init();

	if ( did_action( 'elementor/loaded' ) ) {
		require_once ONUEXRA_PATH . 'includes/class-onuexra-elementor.php';
		OnuExra\Elementor::init();
	}
}
