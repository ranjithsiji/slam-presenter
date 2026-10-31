<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public output: the profile card on single presenter pages and the shortcodes.
 */
class SSP_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'profile_content' ), 20 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'hide_theme_thumbnail' ), 10, 2 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'astra_the_title_enabled', array( __CLASS__, 'astra_title' ) );
		add_shortcode( 'slam_presenters', array( __CLASS__, 'shortcode_presenters' ) );
		add_shortcode( 'slam_timetable', array( __CLASS__, 'shortcode_timetable' ) );
		add_shortcode( 'slam_talks', array( __CLASS__, 'shortcode_talks' ) );
		add_shortcode( 'slam_presenter', array( __CLASS__, 'shortcode_presenter' ) );
	}

	public static function register_assets() {
		wp_register_style( 'ssp-frontend', SSP_PLUGIN_URL . 'assets/css/frontend.css', array(), SSP_VERSION );
		if ( is_singular( SSP_POST_TYPE ) ) {
			wp_enqueue_style( 'ssp-frontend' );
		}
	}

	private static function hides_theme_title() {
		return is_singular( SSP_POST_TYPE ) && SSP_Settings::get( 'hide_theme_title' );
	}

	public static function body_class( $classes ) {
		if ( self::hides_theme_title() ) {
			$classes[] = 'ssp-hide-theme-title';
		}
		return $classes;
	}

	public static function astra_title( $enabled ) {
		return self::hides_theme_title() ? false : $enabled;
	}

	/**
	 * The profile card shows the photo itself, so the theme's featured image is dropped on profile pages.
	 */
	public static function hide_theme_thumbnail( $html, $post_id ) {
		if ( self::hides_theme_title() && (int) $post_id === (int) get_queried_object_id() && ! doing_filter( 'the_content' ) ) {
			return '';
		}
		return $html;
	}

	public static function profile_content( $content ) {
		if ( ! is_singular( SSP_POST_TYPE ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		return self::render_profile( get_the_ID(), $content );
	}

	public static function icon( $name ) {
		$paths = array(
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
			'link'     => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
			'mic'      => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4M8 22h8"/>',
			'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>',
			'book'     => '<path d="M4 4h6a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4z"/><path d="M20 4h-6a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h7z"/>',
			'play'     => '<circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4z"/>',
			'pin'      => '<path d="M12 22s7-7 7-12a7 7 0 0 0-14 0c0 5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
			'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
			'award'    => '<circle cx="12" cy="9" r="6"/><path d="M9 14l-2 8 5-3 5 3-2-8"/>',
			'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
			'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			'building' => '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M8 8h.01M12 8h.01M16 8h.01M8 12h.01M12 12h.01M16 12h.01"/>',
			'list'     => '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
			'arrow'    => '<path d="M7 17L17 7M9 7h8v8"/>',
			'pencil'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		);
		return '<svg class="ssp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * The event shown in the profile banner: the final if the presenter reached it, else their centre.
	 */
	public static function primary_event( $post_id ) {
		$terms = get_the_terms( $post_id, SSP_TAXONOMY );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}
		foreach ( $terms as $term ) {
			if ( 'final' === SSP_Post_Types::get_event_meta( $term->term_id )['event_type'] ) {
				return $term;
			}
		}
		return $terms[0];
	}

	public static function render_profile( $post_id, $bio_html = null ) {
		wp_enqueue_style( 'ssp-frontend' );
		$s = SSP_Settings::get();

		if ( null === $bio_html ) {
			$bio_html = apply_filters( 'ssp_bio', wpautop( get_post_field( 'post_content', $post_id ) ) );
		}
		$name_ml     = SSP_Presenter::display_name( $post_id );
		$name_en     = SSP_Presenter::get( $post_id, 'name_en' );
		$institution = SSP_Presenter::get( $post_id, 'institution' );
		$designation = SSP_Presenter::get( $post_id, 'designation' );
		$talk_title  = SSP_Presenter::get( $post_id, 'talk_title' );
		$summary     = SSP_Presenter::get( $post_id, 'summary' );
		$abstract    = SSP_Presenter::get( $post_id, 'abstract' );
		$award       = SSP_Presenter::get( $post_id, 'award' );
		$video       = SSP_Presenter::get( $post_id, 'video_url' );
		$email       = SSP_Presenter::get( $post_id, 'email_public' ) ? SSP_Presenter::get( $post_id, 'email' ) : '';
		$social      = SSP_Presenter::get_social( $post_id );
		$papers      = SSP_Presenter::get_papers( $post_id );
		$primary     = self::primary_event( $post_id );
		$terms       = get_the_terms( $post_id, SSP_TAXONOMY );
		$terms       = $terms && ! is_wp_error( $terms ) ? $terms : array();
		$networks    = SSP_Presenter::networks();
		$has_bio     = '' !== trim( wp_strip_all_tags( $bio_html ) );

		ob_start();
		?>
		<div class="ssp-profile">
			<div class="ssp-profile-card">
				<?php if ( current_user_can( 'edit_post', $post_id ) ) : ?>
					<a class="ssp-edit-link" href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo self::icon( 'pencil' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Edit profile', 'slam-presenter-profiles' ); ?></a>
				<?php endif; ?>
				<aside class="ssp-id">
					<div class="ssp-id-photo">
						<?php echo SSP_Presenter::photo_html( $post_id, 'ssp-square', 'ssp-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php if ( $award ) : ?>
							<span class="ssp-award"><?php echo self::icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $award ); ?></span>
						<?php endif; ?>
					</div>
					<div class="ssp-id-panel">
						<?php if ( $primary ) : ?>
							<p class="ssp-eyebrow"><?php echo esc_html( SSP_Post_Types::event_banner( $primary ) ); ?></p>
						<?php endif; ?>
						<h1 class="ssp-name"><?php echo esc_html( $name_ml ); ?></h1>
						<?php if ( $name_en && $name_en !== $name_ml ) : ?>
							<p class="ssp-name-en"><?php echo esc_html( $name_en ); ?></p>
						<?php endif; ?>
						<?php if ( $designation || $institution ) : ?>
							<p class="ssp-affil"><?php echo self::icon( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( implode( ', ', array_filter( array( $designation, $institution ) ) ) ); ?></span></p>
						<?php endif; ?>
						<?php if ( $talk_title ) : ?>
							<div class="ssp-quote-block">
								<p class="ssp-quote-label"><?php echo esc_html( $s['label_talk'] ); ?></p>
								<blockquote class="ssp-quote"><?php echo esc_html( $talk_title ); ?></blockquote>
							</div>
						<?php endif; ?>
					</div>
				</aside>

				<div class="ssp-tiles">
					<?php if ( $terms ) : ?>
						<section class="ssp-tile ssp-tile-slam">
							<h2 class="ssp-tile-title"><?php echo self::icon( 'mic' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Science Slam', 'slam-presenter-profiles' ); ?></h2>
							<?php foreach ( $terms as $term ) : ?>
								<?php
								$meta     = SSP_Post_Types::get_event_meta( $term->term_id );
								$tt_shown = SSP_Timetable::is_visible( $term->term_id );
								$tt_draft = 'publish' !== $meta['status'];
								$slot     = $tt_shown ? SSP_Timetable::find_slot( $term->term_id, $post_id ) : null;
								?>
								<dl class="ssp-facts">
									<?php
									$slam_url  = SSP_Post_Types::page_url( $term );
									$slam_text = '<strong>' . esc_html( $term->name ) . '</strong>' . ( $meta['event_date'] ? ' · ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $meta['event_date'] ) ) ) : '' );
									?>
									<div><dt><?php echo self::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dt><dd><?php echo $slam_url ? '<a class="ssp-slam-link" href="' . esc_url( $slam_url ) . '">' . $slam_text . '</a>' : $slam_text; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></dd></div>
									<?php if ( $meta['venue'] || $meta['city'] ) : ?>
										<div><dt><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dt><dd><?php echo esc_html( implode( ', ', array_filter( array( $meta['venue'], $meta['city'] ) ) ) ); ?></dd></div>
									<?php endif; ?>
									<?php if ( $slot ) : ?>
										<div><dt><?php echo self::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dt><dd><?php echo esc_html( SSP_Timetable::format_minutes( $slot['start'] ) . ' – ' . SSP_Timetable::format_minutes( $slot['end'] ) ); ?> <span class="ssp-chip">#<?php echo (int) $slot['no']; ?></span><?php echo $tt_draft ? ' <span class="ssp-draft-chip">' . esc_html__( 'Draft', 'slam-presenter-profiles' ) . '</span>' : ''; ?></dd></div>
									<?php endif; ?>
								</dl>
								<?php $schedule = $tt_shown ? SSP_Post_Types::schedule_url( $term ) : ''; ?>
								<?php if ( $schedule ) : ?>
									<a class="ssp-schedule-link" href="<?php echo esc_url( $schedule . ( $slot ? '#ssp-talk-' . $post_id : '' ) ); ?>"><?php echo esc_html( $s['label_schedule'] ); ?><?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
								<?php endif; ?>
							<?php endforeach; ?>
						</section>
					<?php endif; ?>

					<?php if ( $social || $email ) : ?>
						<section class="ssp-tile ssp-tile-links">
							<h2 class="ssp-tile-title"><?php echo self::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Links', 'slam-presenter-profiles' ); ?></h2>
							<ul class="ssp-links">
								<?php foreach ( $social as $key => $url ) : ?>
									<?php if ( isset( $networks[ $key ] ) ) : ?>
										<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener me" style="--ssp-brand:<?php echo esc_attr( $networks[ $key ][1] ); ?>"><span class="ssp-link-dot"></span><?php echo esc_html( $networks[ $key ][0] ); ?></a></li>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php if ( $email ) : ?>
									<li><a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>" style="--ssp-brand:#FD9800"><?php echo self::icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( antispambot( $email ) ); ?></a></li>
								<?php endif; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( $summary || $abstract ) : ?>
						<section class="ssp-tile ssp-tile-wide ssp-tile-talk">
							<h2 class="ssp-tile-title"><?php echo self::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $s['label_abstract'] ); ?></h2>
							<?php if ( $talk_title ) : ?>
								<h3 class="ssp-talk-title"><?php echo esc_html( $talk_title ); ?></h3>
							<?php endif; ?>
							<?php if ( $summary ) : ?>
								<div class="ssp-summary"><?php echo wp_kses_post( wpautop( $summary ) ); ?></div>
							<?php endif; ?>
							<?php if ( $abstract ) : ?>
								<div class="ssp-abstract"><?php echo wp_kses_post( wpautop( $abstract ) ); ?></div>
							<?php endif; ?>
						</section>
					<?php endif; ?>

					<?php if ( $has_bio ) : ?>
						<section class="ssp-tile ssp-tile-wide ssp-tile-about">
							<h2 class="ssp-tile-title"><?php echo self::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $s['label_about'] ); ?></h2>
							<div class="ssp-bio"><?php echo $bio_html; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content. ?></div>
						</section>
					<?php endif; ?>

					<?php if ( $papers ) : ?>
						<section class="ssp-tile ssp-tile-wide ssp-tile-papers">
							<h2 class="ssp-tile-title"><?php echo self::icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $s['label_papers'] ); ?></h2>
							<ol class="ssp-papers">
								<?php foreach ( $papers as $paper ) : ?>
									<li>
										<?php if ( $paper['url'] ) : ?>
											<a class="ssp-paper-title" href="<?php echo esc_url( $paper['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $paper['title'] ); ?><?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
										<?php else : ?>
											<span class="ssp-paper-title"><?php echo esc_html( $paper['title'] ); ?></span>
										<?php endif; ?>
										<span class="ssp-paper-meta"><?php echo esc_html( implode( ' · ', array_filter( array( $paper['authors'], $paper['venue'], $paper['year'] ) ) ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
						</section>
					<?php endif; ?>

					<?php if ( $video ) : ?>
						<?php $embed = wp_oembed_get( $video ); ?>
						<section class="ssp-tile ssp-tile-wide ssp-tile-video">
							<h2 class="ssp-tile-title"><?php echo self::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Watch the presentation', 'slam-presenter-profiles' ); ?></h2>
							<?php if ( $embed ) : ?>
								<div class="ssp-video"><?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput -- oEmbed HTML from a whitelisted provider. ?></div>
							<?php else : ?>
								<p><a href="<?php echo esc_url( $video ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $video ); ?></a></p>
							<?php endif; ?>
						</section>
					<?php endif; ?>
				</div>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Talk IDs of an event in timetable order; presenters not yet scheduled follow by name.
	 */
	public static function ordered_presenters( $term_id ) {
		$scheduled = SSP_Timetable::get_talk_ids( $term_id );
		$all       = SSP_Presenter::get_event_presenter_ids( $term_id, 'publish' );
		$ordered   = array_values( array_intersect( $scheduled, $all ) );
		return array_merge( $ordered, array_values( array_diff( $all, $ordered ) ) );
	}

	/**
	 * Numbered list of an event's talks, as on last year's profile pages.
	 */
	public static function render_talks( $term_id, $current = 0 ) {
		$s   = SSP_Settings::get();
		$ids = self::ordered_presenters( $term_id );
		if ( ! $ids ) {
			return '';
		}
		$term = get_term( $term_id, SSP_TAXONOMY );
		ob_start();
		?>
		<section class="ssp-talks">
			<h2 class="ssp-section-title"><?php echo self::icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $s['label_talks'] ); ?> <span class="ssp-count"><?php echo esc_html( $term->name ); ?> · <?php echo count( $ids ); ?></span></h2>
			<table class="ssp-talks-table">
				<thead><tr>
					<th><?php echo esc_html( $s['label_no'] ); ?></th>
					<th><?php echo esc_html( $s['label_talk'] ); ?></th>
					<th><?php echo esc_html( $s['label_presenter'] ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $ids as $n => $id ) : ?>
					<tr class="<?php echo (int) $id === (int) $current ? 'is-current' : ''; ?>">
						<td><?php echo (int) $n + 1; ?></td>
						<td><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( SSP_Presenter::get( $id, 'talk_title' ) ); ?></a></td>
						<td class="ssp-talks-who">
							<a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
								<?php echo SSP_Presenter::photo_html( $id, 'ssp-thumb', 'ssp-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php echo esc_html( SSP_Presenter::display_name( $id ) ); ?></span>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function event_from_atts( $atts ) {
		if ( empty( $atts['event'] ) ) {
			return null;
		}
		$term = is_numeric( $atts['event'] ) ? get_term( (int) $atts['event'], SSP_TAXONOMY ) : get_term_by( 'slug', sanitize_title( $atts['event'] ), SSP_TAXONOMY );
		return $term && ! is_wp_error( $term ) ? $term : null;
	}

	public static function shortcode_presenters( $atts ) {
		$atts = shortcode_atts(
			array(
				'event'   => '',
				'columns' => 4,
				'order'   => 'timetable',
				'show'    => 'title',
			),
			$atts,
			'slam_presenters'
		);
		wp_enqueue_style( 'ssp-frontend' );
		$term = self::event_from_atts( $atts );
		if ( $term ) {
			$ids = 'name' === $atts['order'] ? SSP_Presenter::get_event_presenter_ids( $term->term_id, 'publish' ) : self::ordered_presenters( $term->term_id );
		} else {
			$ids = get_posts(
				array(
					'post_type'      => SSP_POST_TYPE,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
		}
		if ( ! $ids ) {
			return '';
		}
		ob_start();
		?>
		<div class="ssp-grid" style="--ssp-cols:<?php echo (int) max( 1, min( 6, (int) $atts['columns'] ) ); ?>">
			<?php foreach ( $ids as $id ) : ?>
				<a class="ssp-grid-card" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
					<span class="ssp-grid-photo"><?php echo SSP_Presenter::photo_html( $id, 'ssp-square', 'ssp-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="ssp-grid-name"><?php echo esc_html( SSP_Presenter::display_name( $id ) ); ?></span>
					<span class="ssp-grid-inst"><?php echo esc_html( SSP_Presenter::get( $id, 'institution' ) ); ?></span>
					<?php if ( 'title' === $atts['show'] && SSP_Presenter::get( $id, 'talk_title' ) ) : ?>
						<span class="ssp-grid-talk"><?php echo esc_html( SSP_Presenter::get( $id, 'talk_title' ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function shortcode_timetable( $atts ) {
		$atts = shortcode_atts(
			array(
				'event'  => '',
				'photos' => 'yes',
			),
			$atts,
			'slam_timetable'
		);
		$term = self::event_from_atts( $atts );
		if ( ! $term ) {
			return '';
		}
		wp_enqueue_style( 'ssp-frontend' );
		if ( ! SSP_Timetable::is_visible( $term->term_id ) ) {
			$soon = SSP_Settings::get( 'label_tt_soon' );
			return $soon ? '<div class="ssp-timetable"><p class="ssp-tt-soon">' . self::icon( 'clock' ) . esc_html( $soon ) . '</p></div>' : '';
		}
		$items = SSP_Timetable::computed( $term->term_id );
		if ( ! $items ) {
			return '';
		}
		$meta = SSP_Post_Types::get_event_meta( $term->term_id );
		ob_start();
		?>
		<div class="ssp-timetable">
			<?php if ( 'publish' !== $meta['status'] ) : ?>
				<p class="ssp-tt-draft-note">
					<?php esc_html_e( 'Draft timetable: only visible to editors.', 'slam-presenter-profiles' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&event=' . $term->term_id ) ); ?>"><?php esc_html_e( 'Edit or publish', 'slam-presenter-profiles' ); ?></a>
				</p>
			<?php endif; ?>
			<header class="ssp-tt-head">
				<p class="ssp-eyebrow"><?php echo esc_html( SSP_Post_Types::event_banner( $term ) ); ?></p>
				<h2><?php echo esc_html( $term->name ); ?></h2>
				<?php if ( $meta['venue'] || $meta['event_date'] ) : ?>
					<?php
					// Link back to the slam's own page when one is set (not to this schedule page itself).
					$slam_url = $meta['page_url'];
					?>
					<?php if ( $slam_url ) : ?>
						<a class="ssp-tt-where ssp-tt-where-link" href="<?php echo esc_url( $slam_url ); ?>">
					<?php else : ?>
						<p class="ssp-tt-where">
					<?php endif; ?>
						<?php if ( $meta['event_date'] ) : ?>
							<span><?php echo self::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $meta['event_date'] ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( $meta['venue'] ) : ?>
							<span><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( implode( ', ', array_filter( array( $meta['venue'], $meta['city'] ) ) ) ); ?></span>
						<?php endif; ?>
					<?php if ( $slam_url ) : ?>
							<span class="ssp-tt-where-more"><?php echo esc_html( SSP_Settings::get( 'label_slam_page' ) ); ?><?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</a>
					<?php else : ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</header>
			<ol class="ssp-tt">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$time = SSP_Timetable::format_minutes( $item['start'] );
					$end  = isset( $item['end'] ) ? SSP_Timetable::format_minutes( $item['end'] ) : '';
					?>
					<?php if ( 'session' === $item['type'] ) : ?>
						<li class="ssp-tt-session">
							<h3><?php echo esc_html( $item['label'] ); ?></h3>
							<span class="ssp-tt-range"><?php echo esc_html( $time . ( $end ? ' – ' . $end : '' ) ); ?></span>
							<?php if ( $item['note'] ) : ?>
								<p class="ssp-tt-note"><?php echo esc_html( $item['note'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php elseif ( 'talk' === $item['type'] && $item['presenter_id'] && 'publish' === get_post_status( $item['presenter_id'] ) ) : ?>
						<?php $pid = (int) $item['presenter_id']; ?>
						<li class="ssp-tt-talk" id="ssp-talk-<?php echo (int) $pid; ?>">
							<span class="ssp-tt-time"><?php echo esc_html( $time ); ?><small><?php echo esc_html( $end ); ?></small></span>
							<a class="ssp-tt-body" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">
								<?php if ( 'no' !== $atts['photos'] ) : ?>
									<?php echo SSP_Presenter::photo_html( $pid, 'ssp-thumb', 'ssp-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php endif; ?>
								<span>
									<strong class="ssp-tt-title"><?php echo esc_html( SSP_Presenter::get( $pid, 'talk_title' ) ); ?></strong>
									<span class="ssp-tt-who"><?php echo esc_html( SSP_Presenter::display_name( $pid ) ); ?><?php echo SSP_Presenter::get( $pid, 'institution' ) ? ' · ' . esc_html( SSP_Presenter::get( $pid, 'institution' ) ) : ''; ?></span>
								</span>
							</a>
							<span class="ssp-tt-no">#<?php echo (int) $item['no']; ?></span>
						</li>
					<?php elseif ( 'talk' !== $item['type'] ) : ?>
						<li class="ssp-tt-<?php echo esc_attr( $item['type'] ); ?>">
							<span class="ssp-tt-time"><?php echo esc_html( $time ); ?><small><?php echo esc_html( $end ); ?></small></span>
							<span class="ssp-tt-body"><?php echo esc_html( $item['label'] ); ?></span>
							<?php if ( 'break' === $item['type'] ) : ?>
								<span class="ssp-tt-no"><?php echo (int) $item['duration']; ?>′</span>
							<?php endif; ?>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function shortcode_talks( $atts ) {
		$atts = shortcode_atts( array( 'event' => '' ), $atts, 'slam_talks' );
		$term = self::event_from_atts( $atts );
		if ( ! $term ) {
			return '';
		}
		wp_enqueue_style( 'ssp-frontend' );
		return self::render_talks( $term->term_id );
	}

	public static function shortcode_presenter( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'slam_presenter' );
		$id   = (int) $atts['id'];
		if ( ! $id || SSP_POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		return self::render_profile( $id );
	}
}
