<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Photo Frames admin screen: a list of frames and one form per frame (title, frame image, texts, status).
 * Frames are posts, but they are edited here instead of in the block editor.
 */
class SPF_Admin {

	const PAGE = 'spf-frames';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_spf_save_frame', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_spf_trash_frame', array( __CLASS__, 'handle_trash' ) );
		add_filter( 'get_edit_post_link', array( __CLASS__, 'edit_link' ), 10, 2 );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_core_editor' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'redirect_core_editor' ) );
	}

	public static function url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Photo Frames', 'slam-photo-frame' ),
			__( 'Photo Frames', 'slam-photo-frame' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-format-image',
			26
		);
		add_submenu_page( self::PAGE, __( 'All Frames', 'slam-photo-frame' ), __( 'All Frames', 'slam-photo-frame' ), 'edit_posts', self::PAGE, array( __CLASS__, 'render' ) );
		add_submenu_page( self::PAGE, __( 'Add New Frame', 'slam-photo-frame' ), __( 'Add New', 'slam-photo-frame' ), 'publish_posts', self::PAGE . '&action=new', '__return_null' );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'spf-admin', SPF_PLUGIN_URL . 'assets/css/admin.css', array(), SPF_VERSION );
		if ( isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_enqueue_media();
		}
		wp_enqueue_script( 'spf-frame-admin', SPF_PLUGIN_URL . 'assets/js/frame-admin.js', array( 'jquery' ), SPF_VERSION, true );
		wp_localize_script(
			'spf-frame-admin',
			'spfAdmin',
			array(
				'choose'   => __( 'Choose frame image', 'slam-photo-frame' ),
				'use'      => __( 'Use as frame', 'slam-photo-frame' ),
				'notPng'   => __( 'This is not a PNG. The photo only shows through transparent parts of the frame, so use a PNG with a transparent window.', 'slam-photo-frame' ),
				'noWindow' => __( 'No transparent area found in this image. The photo will be hidden behind it.', 'slam-photo-frame' ),
				'window'   => __( 'Photo window: %1$s × %2$s px at %3$s, %4$s.', 'slam-photo-frame' ),
				'copied'   => __( 'Copied', 'slam-photo-frame' ),
			)
		);
	}

	/**
	 * The frame's shortcode with a Copy button.
	 */
	public static function shortcode_field( $id ) {
		$code = '[photo_frame id="' . (int) $id . '"]';
		printf(
			'<span class="spf-shortcode"><code>%1$s</code> <button type="button" class="button button-small spf-copy" data-copy="%2$s">%3$s</button></span>',
			esc_html( $code ),
			esc_attr( $code ),
			esc_html__( 'Copy', 'slam-photo-frame' )
		);
	}

	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$id     = isset( $_GET['frame'] ) ? absint( $_GET['frame'] ) : 0;
		$notice = isset( $_GET['spf_notice'] ) ? sanitize_key( $_GET['spf_notice'] ) : '';
		// phpcs:enable

		if ( 'edit' === $action && $id ) {
			$post = get_post( $id );
			if ( ! $post || SPF_POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
				wp_die( esc_html__( 'Frame not found.', 'slam-photo-frame' ) );
			}
			include SPF_PLUGIN_DIR . 'includes/views/frame-edit.php';
		} elseif ( 'new' === $action ) {
			$post = null;
			include SPF_PLUGIN_DIR . 'includes/views/frame-edit.php';
		} else {
			$frames = get_posts(
				array(
					'post_type'      => SPF_POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
				)
			);
			include SPF_PLUGIN_DIR . 'includes/views/frames-list.php';
		}
	}

	public static function notice( $code ) {
		$notices = array(
			'saved'    => array( 'success', __( 'Frame saved.', 'slam-photo-frame' ) ),
			'no_image' => array( 'warning', __( 'Frame saved as a draft: choose a frame image to publish it.', 'slam-photo-frame' ) ),
			'no_title' => array( 'error', __( 'Please enter a title.', 'slam-photo-frame' ) ),
			'trashed'  => array( 'success', __( 'Frame moved to the trash.', 'slam-photo-frame' ) ),
		);
		if ( isset( $notices[ $code ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notices[ $code ][0] ), esc_html( $notices[ $code ][1] ) );
		}
	}

	public static function handle_save() {
		check_admin_referer( 'spf_save_frame' );
		// phpcs:disable WordPress.Security.NonceVerification -- checked above.
		$id = isset( $_POST['frame_id'] ) ? absint( $_POST['frame_id'] ) : 0;
		if ( $id ? ! current_user_can( 'edit_post', $id ) : ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this frame.', 'slam-photo-frame' ) );
		}
		if ( $id && SPF_POST_TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Frame not found.', 'slam-photo-frame' ) );
		}

		$title    = isset( $_POST['spf_title'] ) ? sanitize_text_field( wp_unslash( $_POST['spf_title'] ) ) : '';
		$intro    = isset( $_POST['spf_intro'] ) ? wp_kses_post( wp_unslash( $_POST['spf_intro'] ) ) : '';
		$slug     = isset( $_POST['spf_slug'] ) ? sanitize_title( wp_unslash( $_POST['spf_slug'] ) ) : '';
		$status   = isset( $_POST['spf_status'] ) && 'draft' === $_POST['spf_status'] ? 'draft' : 'publish';
		$image_id = isset( $_POST['spf_image_id'] ) ? absint( $_POST['spf_image_id'] ) : 0;
		$share    = isset( $_POST['spf_share_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_share_text'] ) ) : '';
		$file     = isset( $_POST['spf_file_name'] ) ? sanitize_title( wp_unslash( $_POST['spf_file_name'] ) ) : '';
		// phpcs:enable

		$back = $id ? self::url( array( 'action' => 'edit', 'frame' => $id ) ) : self::url( array( 'action' => 'new' ) );
		if ( '' === $title ) {
			wp_safe_redirect( add_query_arg( 'spf_notice', 'no_title', $back ) );
			exit;
		}
		if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) {
			$image_id = 0;
		}
		$notice = 'saved';
		if ( 'publish' === $status && ! $image_id ) {
			$status = 'draft';
			$notice = 'no_image';
		}

		$postarr = array(
			'post_type'    => SPF_POST_TYPE,
			'post_title'   => $title,
			'post_content' => $intro,
			'post_status'  => $status,
			'post_name'    => $slug,
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		$id = (int) $result;

		if ( $image_id ) {
			update_post_meta( $id, '_spf_image_id', $image_id );
			// The featured image is what SEO plugins and link previews use; the page itself hides it.
			set_post_thumbnail( $id, $image_id );
		} else {
			delete_post_meta( $id, '_spf_image_id' );
			delete_post_thumbnail( $id );
		}
		update_post_meta( $id, '_spf_share_text', $share );
		update_post_meta( $id, '_spf_file_name', $file );

		wp_safe_redirect( self::url( array( 'action' => 'edit', 'frame' => $id, 'spf_notice' => $notice ) ) );
		exit;
	}

	public static function handle_trash() {
		$id = isset( $_GET['frame'] ) ? absint( $_GET['frame'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'spf_trash_frame_' . $id );
		if ( ! $id || SPF_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to delete this frame.', 'slam-photo-frame' ) );
		}
		wp_trash_post( $id );
		wp_safe_redirect( self::url( array( 'spf_notice' => 'trashed' ) ) );
		exit;
	}

	public static function trash_url( $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=spf_trash_frame&frame=' . (int) $id ), 'spf_trash_frame_' . (int) $id );
	}

	/**
	 * "Edit" links everywhere (admin bar, search) open this screen.
	 */
	public static function edit_link( $link, $post_id ) {
		if ( SPF_POST_TYPE === get_post_type( $post_id ) ) {
			return self::url( array( 'action' => 'edit', 'frame' => $post_id ) );
		}
		return $link;
	}

	public static function redirect_core_editor() {
		// phpcs:disable WordPress.Security.NonceVerification
		$type = isset( $_GET['post'] ) ? get_post_type( absint( $_GET['post'] ) ) : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '' );
		if ( SPF_POST_TYPE !== $type ) {
			return;
		}
		$args = isset( $_GET['post'] ) ? array( 'action' => 'edit', 'frame' => absint( $_GET['post'] ) ) : array( 'action' => 'new' );
		// phpcs:enable
		wp_safe_redirect( self::url( $args ) );
		exit;
	}
}
