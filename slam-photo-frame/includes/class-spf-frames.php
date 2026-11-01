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
		add_action( 'add_meta_boxes_' . SPF_POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . SPF_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_filter( 'manage_' . SPF_POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . SPF_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
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
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-format-image',
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

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || SPF_POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'spf-admin', SPF_PLUGIN_URL . 'assets/css/admin.css', array(), SPF_VERSION );
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
			)
		);
	}

	public static function add_meta_boxes() {
		add_meta_box( 'spf_frame', __( 'Frame', 'slam-photo-frame' ), array( __CLASS__, 'render_frame_box' ), SPF_POST_TYPE, 'normal', 'high' );
		add_meta_box( 'spf_usage', __( 'Usage', 'slam-photo-frame' ), array( __CLASS__, 'render_usage_box' ), SPF_POST_TYPE, 'side', 'default' );
		remove_meta_box( 'postimagediv', SPF_POST_TYPE, 'side' );
	}

	public static function render_frame_box( $post ) {
		wp_nonce_field( 'spf_save_frame', 'spf_nonce' );
		$image_id   = (int) get_post_meta( $post->ID, '_spf_image_id', true );
		$image      = $image_id ? wp_get_attachment_image_src( $image_id, 'large' ) : false;
		$share_text = get_post_meta( $post->ID, '_spf_share_text', true );
		$file_name  = get_post_meta( $post->ID, '_spf_file_name', true );
		?>
		<div class="spf-admin-frame">
			<div class="spf-admin-preview">
				<img src="<?php echo $image ? esc_url( $image[0] ) : ''; ?>" alt=""<?php echo $image ? '' : ' hidden'; ?>>
			</div>
			<div class="spf-admin-fields">
				<input type="hidden" name="spf_image_id" id="spf_image_id" value="<?php echo (int) $image_id; ?>">
				<p>
					<button type="button" class="button spf-choose-image"><?php esc_html_e( 'Choose frame image', 'slam-photo-frame' ); ?></button>
					<button type="button" class="button-link spf-remove-image"<?php echo $image_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'slam-photo-frame' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'A PNG with a transparent window where the photo shows through, eg. 1200 × 1200 px. The picture people download has the same size.', 'slam-photo-frame' ); ?></p>
				<p class="spf-image-note description"></p>

				<p><label for="spf_share_text"><strong><?php esc_html_e( 'Share text', 'slam-photo-frame' ); ?></strong></label></p>
				<textarea name="spf_share_text" id="spf_share_text" rows="3" class="large-text"><?php echo esc_textarea( $share_text ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Sent along with the picture and the frame link, eg. "I am participating in Kerala Science Slam 2026! #KeralaScienceSlam". The link to this page is added automatically.', 'slam-photo-frame' ); ?></p>

				<p><label for="spf_file_name"><strong><?php esc_html_e( 'Download file name', 'slam-photo-frame' ); ?></strong></label></p>
				<input type="text" name="spf_file_name" id="spf_file_name" value="<?php echo esc_attr( $file_name ); ?>" class="regular-text code" placeholder="<?php echo esc_attr( $post->post_name ? $post->post_name : 'kerala-science-slam' ); ?>">
				<span>.jpg</span>
			</div>
		</div>
		<p class="description">
			<?php
			/* translators: %s: shortcode */
			printf( esc_html__( 'The frame maker shows on this frame\'s own page, below the text above. To put it on another page use %s.', 'slam-photo-frame' ), '<code>[photo_frame id="' . (int) $post->ID . '"]</code>' );
			?>
		</p>
		<?php
	}

	public static function render_usage_box( $post ) {
		$uses = (int) get_post_meta( $post->ID, '_spf_uses', true );
		/* translators: %s: number of pictures */
		echo '<p>' . esc_html( sprintf( _n( '%s picture downloaded or shared.', '%s pictures downloaded or shared.', $uses, 'slam-photo-frame' ), number_format_i18n( $uses ) ) ) . '</p>';
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['spf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spf_nonce'] ) ), 'spf_save_frame' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$image_id = isset( $_POST['spf_image_id'] ) ? absint( $_POST['spf_image_id'] ) : 0;
		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			update_post_meta( $post_id, '_spf_image_id', $image_id );
			// The featured image is what SEO plugins and link previews use; the page itself hides it.
			set_post_thumbnail( $post_id, $image_id );
		} else {
			delete_post_meta( $post_id, '_spf_image_id' );
			delete_post_thumbnail( $post_id );
		}

		$share_text = isset( $_POST['spf_share_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spf_share_text'] ) ) : '';
		update_post_meta( $post_id, '_spf_share_text', $share_text );

		$file_name = isset( $_POST['spf_file_name'] ) ? sanitize_title( wp_unslash( $_POST['spf_file_name'] ) ) : '';
		update_post_meta( $post_id, '_spf_file_name', $file_name );
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

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['spf_image'] = '';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['spf_uses']      = __( 'Pictures', 'slam-photo-frame' );
				$new['spf_shortcode'] = __( 'Shortcode', 'slam-photo-frame' );
			}
		}
		return $new;
	}

	public static function column( $column, $post_id ) {
		if ( 'spf_image' === $column ) {
			$image_id = (int) get_post_meta( $post_id, '_spf_image_id', true );
			echo $image_id ? wp_get_attachment_image( $image_id, array( 48, 48 ) ) : '';
		} elseif ( 'spf_uses' === $column ) {
			echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_spf_uses', true ) ) );
		} elseif ( 'spf_shortcode' === $column ) {
			echo '<code>[photo_frame id="' . (int) $post_id . '"]</code>';
		}
	}
}
