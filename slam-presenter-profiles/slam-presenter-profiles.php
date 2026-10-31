<?php
/**
 * Plugin Name: Science Slam Presenter Profiles
 * Plugin URI: https://scienceslam.in
 * Description: Presenter profiles, slam events and timetables for Kerala Science Slam. Import presenters from the Google Form CSV, fetch photos from Google Drive and build each centre's timetable.
 * Version: 1.6.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Ranjithsiji
 * Author URI: https://alphafork.com
 * License: GPL v2 or later
 * Text Domain: slam-presenter-profiles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'SSP_VERSION', '1.6.1' );
define( 'SSP_PLUGIN_FILE', __FILE__ );
define( 'SSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SSP_POST_TYPE', 'slam_presenter' );
define( 'SSP_TAXONOMY', 'slam_event' );

require_once SSP_PLUGIN_DIR . 'includes/class-ssp-settings.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-post-types.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-editions.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-presenter.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-timetable.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-photos.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-import.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-admin.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-table.php';
require_once SSP_PLUGIN_DIR . 'includes/class-ssp-frontend.php';

register_activation_hook( __FILE__, 'ssp_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function ssp_activate() {
	SSP_Post_Types::register();
	flush_rewrite_rules();
}

function ssp_init_plugin() {
	SSP_Settings::init();
	SSP_Post_Types::init();
	SSP_Editions::init();
	SSP_Timetable::init();
	SSP_Photos::init();
	SSP_Import::init();
	SSP_Admin::init();
	SSP_Table::init();
	SSP_Frontend::init();
}
add_action( 'plugins_loaded', 'ssp_init_plugin' );
