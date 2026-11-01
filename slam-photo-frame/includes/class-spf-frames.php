<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The frame post type: a PNG with a transparent window, the text shared with the picture and a use counter.
 */
class SPF_Frames {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
	}

	public static function register() {
		register_post_type(
			SPF_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Photo Frames', 'slam-photo-frame' ),
					'singular_name' => __( 'Photo Frame', 'slam-photo-frame' ),
					'menu_name'     => __( 'Photo Frames', 'slam-photo-frame' ),
					'add_new_item'  => __( 'Add New Frame', 'slam-photo-frame' ),
					'edit_item'     => __( 'Edit Frame', 'slam-photo-frame' ),
					'view_item'     => __( 'View Frame', 'slam-photo-frame' ),
					'all_items'     => __( 'All Frames', 'slam-photo-frame' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'show_in_menu' => false, // Edited on the Photo Frames screen (SPF_Admin).
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'      => array(
					'slug'       => SPF_Settings::get( 'frame_slug' ),
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Everything the front end needs about one frame, or null when it has no image yet.
	 */
	public static function get( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || SPF_POST_TYPE !== $post->post_type ) {
			return null;
		}
		$image_id = (int) get_post_meta( $post->ID, '_spf_image_id', true );
		$image    = $image_id ? wp_get_attachment_image_src( $image_id, 'full' ) : false;
		if ( ! $image ) {
			return null;
		}
		$file_name = get_post_meta( $post->ID, '_spf_file_name', true );
		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'url'       => get_permalink( $post ),
			'image'     => $image[0],
			'width'     => (int) $image[1],
			'height'    => (int) $image[2],
			'imageId'   => $image_id,
			'shareText' => (string) get_post_meta( $post->ID, '_spf_share_text', true ),
			'fileName'  => $file_name ? $file_name : $post->post_name,
			'uses'      => (int) get_post_meta( $post->ID, '_spf_uses', true ),
		);
	}

	/**
	 * POST /spf/v1/frames/<id>/use: counts a download or share. Public and nonce-free so it works on cached pages.
	 */
	public static function register_rest() {
		register_rest_route(
			'spf/v1',
			'/frames/(?P<id>\d+)/use',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				'callback'            => array( __CLASS__, 'rest_use' ),
			)
		);
	}

	public static function rest_use( $request ) {
		$post = get_post( $request['id'] );
		if ( ! $post || SPF_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'spf_not_found', 'Frame not found', array( 'status' => 404 ) );
		}
		$uses = (int) get_post_meta( $post->ID, '_spf_uses', true ) + 1;
		update_post_meta( $post->ID, '_spf_uses', $uses );
		return array( 'uses' => $uses );
	}
}
