<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings: URL slugs, default talk length and the front-end labels.
 */
class SSP_Settings {

	const OPTION = 'ssp_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'maybe_flush' ), 10, 2 );
	}

	public static function defaults() {
		return array(
			'presenter_slug'   => 'presenter',
			'talk_minutes'     => 12,
			'hide_theme_title' => 1,
			'label_about'      => 'അവതാരകരെക്കുറിച്ച്',
			'label_abstract'   => 'സംഗ്രഹം',
			'label_papers'     => 'പ്രസിദ്ധീകരണങ്ങൾ',
			'label_talks'      => 'ഈ സ്ലാമിലെ അവതരണങ്ങൾ',
			'label_schedule'   => 'മുഴുവൻ സമയക്രമം കാണുക',
			'label_slam_page'  => 'സ്ലാം വിവരങ്ങൾ',
			'label_tt_soon'    => 'സമയക്രമം ഉടൻ പ്രസിദ്ധീകരിക്കും',
			'label_no'         => 'No',
			'label_talk'       => 'അവതരണം',
			'label_presenter'  => 'അവതാരക/ൻ',
			'label_time'       => 'സമയം',
		);
	}

	public static function get( $key = null ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $settings;
		}
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	public static function register() {
		register_setting(
			'ssp_settings_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
		register_setting( 'ssp_settings_group', 'ssp_current_edition', array( 'sanitize_callback' => 'absint' ) );
	}

	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();
		foreach ( $defaults as $key => $default ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : '';
			if ( in_array( $key, array( 'hide_theme_title' ), true ) ) {
				$clean[ $key ] = empty( $value ) ? 0 : 1;
			} elseif ( 'talk_minutes' === $key ) {
				$clean[ $key ] = max( 1, (int) $value );
			} elseif ( in_array( $key, array( 'presenter_slug' ), true ) ) {
				$clean[ $key ] = sanitize_title( $value ) ? sanitize_title( $value ) : $default;
			} else {
				$clean[ $key ] = sanitize_text_field( $value );
			}
		}
		return $clean;
	}

	public static function maybe_flush( $old, $new ) {
		$old = wp_parse_args( (array) $old, self::defaults() );
		if ( $old['presenter_slug'] !== $new['presenter_slug'] ) {
			update_option( 'ssp_flush_rewrite', 1 );
		}
	}
}
