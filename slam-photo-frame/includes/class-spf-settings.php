<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings: the frame page URL base and the labels of the frame maker.
 */
class SPF_Settings {

	const OPTION = 'spf_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'maybe_flush' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'flush_if_needed' ), 20 );
	}

	public static function defaults() {
		return array(
			'frame_slug'     => 'frame',
			'label_take'     => 'Take a photo',
			'label_choose'   => 'Choose from gallery',
			'label_change'   => 'Change photo',
			'label_hint'     => 'Drag to move. Pinch, scroll or use the slider to zoom.',
			'label_zoom'     => 'Zoom',
			'label_rotate'   => 'Rotate',
			'label_flip'     => 'Flip',
			'label_reset'    => 'Reset',
			'label_done'     => 'Done',
			'label_edit'     => 'Edit again',
			'label_share'    => 'Share picture',
			'label_download' => 'Download',
			'label_post'     => 'Post your picture',
			'label_invite'   => 'Invite friends to use this frame',
			'label_copy'     => 'Copy link',
			'label_copied'   => 'Link copied',
			'label_saved'    => 'Picture saved. Open %s and add it from your gallery.',
			'label_capture'  => 'Capture',
			'label_switch'   => 'Switch camera',
			'label_cancel'   => 'Cancel',
			'label_privacy'  => 'Your photo stays on your device. Nothing is uploaded.',
		);
	}

	public static function get( $key = null ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $settings;
		}
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Front-end labels keyed without the "label_" prefix.
	 */
	public static function labels() {
		$labels = array();
		foreach ( self::get() as $key => $value ) {
			if ( 0 === strpos( $key, 'label_' ) ) {
				$labels[ substr( $key, 6 ) ] = $value;
			}
		}
		return $labels;
	}

	public static function register() {
		register_setting(
			'spf_settings_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . SPF_POST_TYPE,
			__( 'Photo Frame Settings', 'slam-photo-frame' ),
			__( 'Settings', 'slam-photo-frame' ),
			'manage_options',
			'spf-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		include SPF_PLUGIN_DIR . 'includes/views/settings.php';
	}

	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();
		foreach ( $defaults as $key => $default ) {
			$value = isset( $input[ $key ] ) ? trim( $input[ $key ] ) : '';
			if ( 'frame_slug' === $key ) {
				$clean[ $key ] = sanitize_title( $value ) ? sanitize_title( $value ) : $default;
			} else {
				$clean[ $key ] = '' === $value ? $default : sanitize_text_field( $value );
			}
		}
		return $clean;
	}

	public static function maybe_flush( $old, $new ) {
		$old = wp_parse_args( (array) $old, self::defaults() );
		if ( $old['frame_slug'] !== $new['frame_slug'] ) {
			update_option( 'spf_flush_rewrite', 1 );
		}
	}

	public static function flush_if_needed() {
		if ( get_option( 'spf_flush_rewrite' ) ) {
			delete_option( 'spf_flush_rewrite' );
			flush_rewrite_rules();
		}
	}
}
