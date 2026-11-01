<?php
/**
 * Plugin Name: Science Slam Photo Frame
 * Plugin URI: https://scienceslam.in
 * Description: Campaign photo frames ("I'm participating"). Visitors take a selfie or pick a photo, fit it into the frame and share it on WhatsApp, Instagram, Facebook and more. Photos are processed in the browser and never uploaded.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Ranjithsiji
 * Author URI: https://alphafork.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: slam-photo-frame
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'SPF_VERSION', '1.0.0' );
define( 'SPF_PLUGIN_FILE', __FILE__ );
define( 'SPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPF_POST_TYPE', 'spf_frame' );

require_once SPF_PLUGIN_DIR . 'includes/class-spf-settings.php';
require_once SPF_PLUGIN_DIR . 'includes/class-spf-frames.php';
require_once SPF_PLUGIN_DIR . 'includes/class-spf-frontend.php';

register_activation_hook( __FILE__, 'spf_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function spf_activate() {
	SPF_Frames::register();
	flush_rewrite_rules();
}

function spf_init_plugin() {
	SPF_Settings::init();
	SPF_Frames::init();
	SPF_Frontend::init();
}
add_action( 'plugins_loaded', 'spf_init_plugin' );
