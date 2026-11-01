<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public output: the frame maker on frame pages and the [photo_frame] shortcode, plus link preview tags.
 */
class SPF_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'meta_tags' ), 5 );
		add_filter( 'the_content', array( __CLASS__, 'frame_content' ), 20 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'hide_theme_thumbnail' ), 10, 2 );
		add_shortcode( 'photo_frame', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_assets() {
		wp_register_style( 'spf-frame-maker', SPF_PLUGIN_URL . 'assets/css/frame-maker.css', array(), SPF_VERSION );
		wp_register_script( 'spf-frame-maker', SPF_PLUGIN_URL . 'assets/js/frame-maker.js', array(), SPF_VERSION, true );
	}

	public static function frame_content( $content ) {
		if ( ! is_singular( SPF_POST_TYPE ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		return $content . self::render( get_the_ID() );
	}

	/**
	 * The frame image is the featured image (for link previews), but the maker already shows it.
	 */
	public static function hide_theme_thumbnail( $html, $post_id ) {
		if ( is_singular( SPF_POST_TYPE ) && (int) $post_id === (int) get_queried_object_id() ) {
			return '';
		}
		return $html;
	}

	/**
	 * [photo_frame id="123"] or [photo_frame slug="participate"].
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'photo_frame'
		);
		$id = absint( $atts['id'] );
		if ( ! $id && $atts['slug'] ) {
			$post = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, SPF_POST_TYPE );
			$id   = $post ? $post->ID : 0;
		}
		if ( ! $id ) {
			// The latest published frame, so a bare [photo_frame] works for a single campaign.
			$latest = get_posts(
				array(
					'post_type'      => SPF_POST_TYPE,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			$id     = $latest ? $latest[0] : 0;
		}
		return $id ? self::render( $id ) : '';
	}

	/**
	 * Open Graph tags so a shared frame link previews with the frame. Left to the SEO plugin when one is active.
	 */
	public static function meta_tags() {
		if ( ! is_singular( SPF_POST_TYPE ) || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
			return;
		}
		$frame = SPF_Frames::get( get_queried_object_id() );
		if ( ! $frame ) {
			return;
		}
		$description = $frame['shareText'] ? $frame['shareText'] : wp_strip_all_tags( get_the_excerpt( $frame['id'] ) );
		$tags        = array(
			'og:type'         => 'website',
			'og:title'        => $frame['title'],
			'og:description'  => $description,
			'og:url'          => $frame['url'],
			'og:image'        => $frame['image'],
			'og:image:width'  => $frame['width'],
			'og:image:height' => $frame['height'],
			'og:site_name'    => get_bloginfo( 'name' ),
		);
		foreach ( $tags as $property => $value ) {
			if ( '' !== (string) $value ) {
				printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $value ) );
			}
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	/**
	 * Share links for the frame page itself (the picture can only be shared from the device).
	 */
	private static function link_targets( $frame ) {
		$url  = rawurlencode( $frame['url'] );
		$text = rawurlencode( $frame['shareText'] );
		$both = rawurlencode( trim( $frame['shareText'] . ' ' . $frame['url'] ) );
		return array(
			'whatsapp'  => array( 'WhatsApp', 'https://wa.me/?text=' . $both ),
			'facebook'  => array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $url ),
			'telegram'  => array( 'Telegram', 'https://t.me/share/url?url=' . $url . '&text=' . $text ),
			'linkedin'  => array( 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url ),
			'x'         => array( 'X', 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $text ),
			'pinterest' => array( 'Pinterest', 'https://www.pinterest.com/pin/create/button/?url=' . $url . '&media=' . rawurlencode( $frame['image'] ) . '&description=' . $text ),
			'email'     => array( 'Email', 'mailto:?subject=' . rawurlencode( $frame['title'] ) . '&body=' . $both ),
		);
	}

	public static function icon( $name ) {
		$paths = array(
			'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
			'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
			'rotate'   => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
			'flip'     => '<path d="M12 3v18"/><path d="M8 7L3 12l5 5zM16 7l5 5-5 5z"/>',
			'reset'    => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
			'check'    => '<path d="M5 12l5 5 9-10"/>',
			'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
			'share'    => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>',
			'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
			'link'     => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
			'zoom'     => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5M8 11h6M11 8v6"/>',
			'switch'   => '<path d="M4 9h13l-3-3M20 15H7l3 3"/>',
			'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="spf-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
	}

	public static function render( $post_id ) {
		$frame = SPF_Frames::get( $post_id );
		if ( ! $frame ) {
			return current_user_can( 'edit_post', $post_id ) ? '<p class="spf-missing">' . esc_html__( 'This frame has no image yet.', 'slam-photo-frame' ) . '</p>' : '';
		}
		wp_enqueue_style( 'spf-frame-maker' );
		wp_enqueue_script( 'spf-frame-maker' );

		$l      = SPF_Settings::labels();
		$config = array(
			'id'        => $frame['id'],
			'image'     => $frame['image'],
			'width'     => $frame['width'],
			'height'    => $frame['height'],
			'url'       => $frame['url'],
			'title'     => $frame['title'],
			'shareText' => $frame['shareText'],
			'fileName'  => $frame['fileName'],
			'useUrl'    => rest_url( 'spf/v1/frames/' . $frame['id'] . '/use' ),
			'labels'    => $l,
		);
		$apps   = array(
			'instagram' => 'Instagram',
			'whatsapp'  => 'WhatsApp Status',
			'facebook'  => 'Facebook',
		);

		ob_start();
		?>
		<div class="spf-maker" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="spf-stage">
				<canvas class="spf-canvas" width="<?php echo (int) $frame['width']; ?>" height="<?php echo (int) $frame['height']; ?>" tabindex="0" aria-label="<?php echo esc_attr( $l['hint'] ); ?>"></canvas>
				<img class="spf-frame-preview" src="<?php echo esc_url( $frame['image'] ); ?>" width="<?php echo (int) $frame['width']; ?>" height="<?php echo (int) $frame['height']; ?>" alt="<?php echo esc_attr( $frame['title'] ); ?>">
				<img class="spf-result" alt="<?php echo esc_attr( $frame['title'] ); ?>" hidden>
			</div>

			<div class="spf-step" data-step="pick">
				<div class="spf-row">
					<button type="button" class="spf-btn spf-btn-primary" data-action="take"><?php echo self::icon( 'camera' ) . esc_html( $l['take'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn" data-action="choose"><?php echo self::icon( 'image' ) . esc_html( $l['choose'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
			</div>

			<div class="spf-step" data-step="edit" hidden>
				<p class="spf-hint"><?php echo esc_html( $l['hint'] ); ?></p>
				<label class="spf-zoom">
					<?php echo self::icon( 'zoom' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="screen-reader-text"><?php echo esc_html( $l['zoom'] ); ?></span>
					<input type="range" min="0" max="1" step="0.001" value="0" data-action="zoom">
				</label>
				<div class="spf-row spf-tools">
					<button type="button" class="spf-btn spf-btn-small" data-action="rotate"><?php echo self::icon( 'rotate' ) . esc_html( $l['rotate'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-small" data-action="flip"><?php echo self::icon( 'flip' ) . esc_html( $l['flip'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-small" data-action="reset"><?php echo self::icon( 'reset' ) . esc_html( $l['reset'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-small" data-action="take" aria-label="<?php echo esc_attr( $l['take'] ); ?>"><?php echo self::icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-small" data-action="choose"><?php echo self::icon( 'image' ) . esc_html( $l['change'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
				<div class="spf-row">
					<button type="button" class="spf-btn spf-btn-primary spf-btn-wide" data-action="done"><?php echo self::icon( 'check' ) . esc_html( $l['done'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
			</div>

			<div class="spf-step" data-step="share" hidden>
				<div class="spf-row">
					<button type="button" class="spf-btn spf-btn-primary" data-action="share" hidden><?php echo self::icon( 'share' ) . esc_html( $l['share'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn" data-action="download"><?php echo self::icon( 'download' ) . esc_html( $l['download'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>

				<h3 class="spf-heading"><?php echo esc_html( $l['post'] ); ?></h3>
				<div class="spf-row spf-apps">
					<?php foreach ( $apps as $app => $name ) : ?>
						<button type="button" class="spf-app spf-app-<?php echo esc_attr( $app ); ?>" data-action="app" data-app="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></button>
					<?php endforeach; ?>
				</div>
				<p class="spf-note" role="status" hidden></p>

				<h3 class="spf-heading"><?php echo esc_html( $l['invite'] ); ?></h3>
				<div class="spf-row spf-apps">
					<?php foreach ( self::link_targets( $frame ) as $key => $target ) : ?>
						<a class="spf-app spf-app-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $target[1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $target[0] ); ?></a>
					<?php endforeach; ?>
					<button type="button" class="spf-app spf-app-copy" data-action="copy"><?php echo self::icon( 'link' ) . esc_html( $l['copy'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>

				<div class="spf-row">
					<button type="button" class="spf-btn spf-btn-link" data-action="edit"><?php echo self::icon( 'edit' ) . esc_html( $l['edit'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
			</div>

			<p class="spf-privacy"><?php echo esc_html( $l['privacy'] ); ?></p>

			<input type="file" accept="image/*" class="spf-file" data-input="gallery" hidden>
			<input type="file" accept="image/*" capture="user" class="spf-file" data-input="camera" hidden>

			<dialog class="spf-camera">
				<video playsinline muted></video>
				<div class="spf-row">
					<button type="button" class="spf-btn" data-action="camera-cancel"><?php echo self::icon( 'close' ) . esc_html( $l['cancel'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-primary spf-shutter" data-action="camera-capture"><?php echo self::icon( 'camera' ) . esc_html( $l['capture'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn" data-action="camera-switch"><?php echo self::icon( 'switch' ) . esc_html( $l['switch'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
			</dialog>
		</div>
		<?php
		return ob_get_clean();
	}
}
