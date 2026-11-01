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
		add_filter( 'astra_the_title_enabled', array( __CLASS__, 'astra_title' ) );
		add_shortcode( 'photo_frame', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_assets() {
		wp_register_style( 'spf-frame-maker', SPF_PLUGIN_URL . 'assets/css/frame-maker.css', array(), SPF_VERSION );
		wp_register_script( 'spf-frame-maker', SPF_PLUGIN_URL . 'assets/js/frame-maker.js', array(), SPF_VERSION, true );
		if ( is_singular( SPF_POST_TYPE ) ) {
			wp_enqueue_style( 'spf-frame-maker' ); // In the head, it also hides the theme's title on frame pages.
		}
	}

	/**
	 * The maker shows the title itself (next to the frame on wide screens).
	 */
	public static function astra_title( $enabled ) {
		return is_singular( SPF_POST_TYPE ) ? false : $enabled;
	}

	public static function frame_content( $content ) {
		if ( ! is_singular( SPF_POST_TYPE ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		return self::render( get_the_ID(), true, $content );
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
	 * [photo_frame id="123"] or [photo_frame slug="participate"]. info="no" leaves out the title and intro.
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
				'info' => 'yes',
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
		// Drafts only for people who can edit them, so a frame can be tried on a page before it goes live.
		if ( ! $id || ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'edit_post', $id ) ) ) {
			return '';
		}
		return self::render( $id, 'no' !== $atts['info'] );
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
			'copy'     => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
			'zoom'     => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5M8 11h6M11 8v6"/>',
			'switch'   => '<path d="M4 9h13l-3-3M20 15H7l3 3"/>',
			'rotate-l' => '<path d="M4 11a8 8 0 1 1 2.3 5.7"/><path d="M4 4v7h7"/>',
			'zoom-out' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5M8 11h6"/>',
			'blur'     => '<circle cx="12" cy="12" r="2"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="7" cy="7" r="1"/><circle cx="17" cy="7" r="1"/><circle cx="7" cy="17" r="1"/><circle cx="17" cy="17" r="1"/>',
			'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
			'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2 .7 3.5 2.4 3.5 5.2"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="spf-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * @param int         $post_id Frame.
	 * @param bool        $info    Show the title, count and intro next to the frame.
	 * @param string|null $intro   Intro HTML (the filtered content on the frame's own page).
	 */
	public static function render( $post_id, $info = true, $intro = null ) {
		$frame = SPF_Frames::get( $post_id );
		if ( ! $frame ) {
			return current_user_can( 'edit_post', $post_id ) ? '<p class="spf-missing">' . esc_html__( 'This frame has no image yet.', 'slam-photo-frame' ) . '</p>' : '';
		}
		wp_enqueue_style( 'spf-frame-maker' );
		wp_enqueue_script( 'spf-frame-maker' );
		if ( null === $intro ) {
			$intro = wpautop( wp_kses_post( get_post_field( 'post_content', $post_id ) ) );
		}

		$l      = SPF_Settings::labels();
		$config = array(
			'id'        => $frame['id'],
			'image'     => $frame['image'],
			'url'       => $frame['url'],
			'title'     => $frame['title'],
			'shareText' => $frame['shareText'],
			'fileName'  => $frame['fileName'],
			'uses'      => $frame['uses'],
			'useUrl'    => rest_url( 'spf/v1/frames/' . $frame['id'] . '/use' ),
			'labels'    => $l,
		);
		$caption = trim( $frame['shareText'] . ' ' . $frame['url'] );
		$i      = array( __CLASS__, 'icon' );
		$count  = '<p class="spf-count"' . ( $frame['uses'] && $l['count'] ? '' : ' hidden' ) . '>' . self::icon( 'users' ) . '<span>' . esc_html( str_replace( '%s', number_format_i18n( $frame['uses'] ), $l['count'] ) ) . '</span></p>';

		ob_start();
		?>
		<div class="spf-maker<?php echo $info ? '' : ' spf-no-info'; ?>" data-step="pick" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="spf-layout">
				<?php if ( $info ) : ?>
					<div class="spf-info">
						<h2 class="spf-title"><?php echo esc_html( $frame['title'] ); ?></h2>
						<?php echo $count; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php if ( trim( wp_strip_all_tags( $intro ) ) ) : ?>
							<div class="spf-intro"><?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="spf-info"><?php echo $count; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php endif; ?>

				<div class="spf-stage">
					<img class="spf-frame-preview" src="<?php echo esc_url( $frame['image'] ); ?>" width="<?php echo (int) $frame['width']; ?>" height="<?php echo (int) $frame['height']; ?>" alt="<?php echo esc_attr( $frame['title'] ); ?>">
					<img class="spf-result" alt="<?php echo esc_attr( $frame['title'] ); ?>" hidden>
				</div>

				<div class="spf-step spf-step-pick" data-step="pick">
					<button type="button" class="spf-btn spf-btn-primary spf-btn-block" data-action="start"><?php echo call_user_func( $i, 'camera' ) . esc_html( $l['start'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>

				<div class="spf-step spf-step-share" data-step="share" hidden>
					<h3 class="spf-heading"><?php echo esc_html( $l['post'] ); ?></h3>
					<?php // The image alone through the browser's share menu: every app gets the picture. Shown by the script where files can be shared. ?>
					<button type="button" class="spf-btn spf-btn-primary spf-btn-share" data-action="share" hidden><?php echo call_user_func( $i, 'share' ) . esc_html( $l['share'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-share" data-action="download"><?php echo call_user_func( $i, 'download' ) . esc_html( $l['download'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<p class="spf-note" role="status" hidden></p>

					<?php if ( $caption ) : ?>
						<div class="spf-caption">
							<label class="spf-caption-label" for="spf-caption-<?php echo (int) $frame['id']; ?>"><?php echo esc_html( $l['caption'] ); ?></label>
							<textarea id="spf-caption-<?php echo (int) $frame['id']; ?>" rows="3" readonly><?php echo esc_textarea( $caption ); ?></textarea>
							<button type="button" class="spf-btn spf-btn-small" data-action="copy-caption"><?php echo call_user_func( $i, 'copy' ) . esc_html( $l['copy_cap'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						</div>
					<?php endif; ?>

					<h3 class="spf-heading"><?php echo esc_html( $l['invite'] ); ?></h3>
					<div class="spf-row spf-apps">
						<?php foreach ( self::link_targets( $frame ) as $key => $target ) : ?>
							<a class="spf-app spf-app-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $target[1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $target[0] ); ?></a>
						<?php endforeach; ?>
						<button type="button" class="spf-app spf-app-copy" data-action="share-link" hidden><?php echo call_user_func( $i, 'share' ) . esc_html( $l['more'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						<button type="button" class="spf-app spf-app-copy" data-action="copy"><?php echo call_user_func( $i, 'link' ) . esc_html( $l['copy'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					</div>

					<div class="spf-row">
						<button type="button" class="spf-btn spf-btn-link" data-action="edit"><?php echo call_user_func( $i, 'edit' ) . esc_html( $l['edit'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						<button type="button" class="spf-btn spf-btn-link" data-action="start"><?php echo call_user_func( $i, 'image' ) . esc_html( $l['change'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					</div>
				</div>

				<p class="spf-privacy"><?php echo esc_html( $l['privacy'] ); ?></p>
			</div>

			<input type="file" accept="image/*" class="spf-file" data-input="gallery" hidden>
			<input type="file" accept="image/*" capture="user" class="spf-file" data-input="camera" hidden>

			<?php // Photo source: bottom sheet with Camera / Gallery. ?>
			<dialog class="spf-sheet">
				<div class="spf-sheet-handle" aria-hidden="true"></div>
				<div class="spf-sources">
					<button type="button" class="spf-source" data-action="take"><span class="spf-source-icon"><?php echo call_user_func( $i, 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( $l['take'] ); ?></button>
					<button type="button" class="spf-source" data-action="choose"><span class="spf-source-icon"><?php echo call_user_func( $i, 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( $l['choose'] ); ?></button>
				</div>
			</dialog>

			<?php // Editor: full screen on phones, a window with a side panel on wide screens. ?>
			<dialog class="spf-editor" aria-label="<?php echo esc_attr( $frame['title'] ); ?>">
				<div class="spf-editor-head">
					<span class="spf-editor-title"><?php echo esc_html( $frame['title'] ); ?></span>
					<button type="button" class="spf-round" data-action="close" aria-label="<?php echo esc_attr( $l['close'] ); ?>"><?php echo call_user_func( $i, 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
				<div class="spf-editor-main">
					<div class="spf-editor-canvas">
						<canvas class="spf-canvas" width="<?php echo (int) $frame['width']; ?>" height="<?php echo (int) $frame['height']; ?>" tabindex="0" aria-label="<?php echo esc_attr( $l['hint'] ); ?>"></canvas>
						<div class="spf-zoom">
							<button type="button" class="spf-round spf-round-small" data-action="zoom-out" aria-label="<?php echo esc_attr( $l['zoom_out'] ); ?>" title="<?php echo esc_attr( $l['zoom_out'] ); ?>"><?php echo call_user_func( $i, 'zoom-out' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<input type="range" min="0" max="1" step="0.001" value="0" data-action="zoom" aria-label="<?php echo esc_attr( $l['zoom'] ); ?>">
							<button type="button" class="spf-round spf-round-small" data-action="zoom-in" aria-label="<?php echo esc_attr( $l['zoom_in'] ); ?>" title="<?php echo esc_attr( $l['zoom_in'] ); ?>"><?php echo call_user_func( $i, 'zoom' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<span class="spf-zoom-sep" aria-hidden="true"></span>
							<button type="button" class="spf-round spf-round-small" data-action="rotate-left" aria-label="<?php echo esc_attr( $l['rotate_l'] ); ?>" title="<?php echo esc_attr( $l['rotate_l'] ); ?>"><?php echo call_user_func( $i, 'rotate-l' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<button type="button" class="spf-round spf-round-small" data-action="rotate" aria-label="<?php echo esc_attr( $l['rotate'] ); ?>" title="<?php echo esc_attr( $l['rotate'] ); ?>"><?php echo call_user_func( $i, 'rotate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						</div>
						<p class="spf-hint"><?php echo esc_html( $l['hint'] ); ?></p>
					</div>
					<div class="spf-editor-side">
						<div class="spf-tools">
							<button type="button" class="spf-tool" data-action="flip"><span class="spf-round"><?php echo call_user_func( $i, 'flip' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="spf-tool-label"><?php echo esc_html( $l['flip'] ); ?></span></button>
							<label class="spf-tool spf-toggle">
								<input type="checkbox" data-action="blur" checked>
								<span class="spf-toggle-track" aria-hidden="true"></span>
								<span class="spf-tool-label"><?php echo esc_html( $l['blur'] ); ?></span>
							</label>
							<button type="button" class="spf-tool" data-action="reset"><span class="spf-round"><?php echo call_user_func( $i, 'reset' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="spf-tool-label"><?php echo esc_html( $l['reset'] ); ?></span></button>
						</div>
						<div class="spf-editor-actions">
							<button type="button" class="spf-btn spf-btn-change" data-action="start" aria-label="<?php echo esc_attr( $l['change'] ); ?>"><?php echo call_user_func( $i, 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="spf-tool-label"><?php echo esc_html( $l['change'] ); ?></span></button>
							<button type="button" class="spf-btn spf-btn-primary spf-btn-block" data-action="done"><?php echo esc_html( $l['done'] ); ?></button>
						</div>
					</div>
				</div>
			</dialog>

			<dialog class="spf-camera">
				<video playsinline muted></video>
				<div class="spf-row">
					<button type="button" class="spf-btn" data-action="camera-cancel"><?php echo call_user_func( $i, 'close' ) . esc_html( $l['cancel'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn spf-btn-primary spf-shutter" data-action="camera-capture"><?php echo call_user_func( $i, 'camera' ) . esc_html( $l['capture'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<button type="button" class="spf-btn" data-action="camera-switch"><?php echo call_user_func( $i, 'switch' ) . esc_html( $l['switch'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
			</dialog>
		</div>
		<?php
		return ob_get_clean();
	}
}
