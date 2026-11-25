<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [slam_finalists]: everyone selected for the final, gathered from the published result of each
 * regional slam in an edition and grouped by slam. A slam whose result is not published yet shows
 * placeholder cards ("to be announced"), so the page fills itself in as results are declared.
 *
 * Attributes:
 *   edition  Edition term ID or slug. Default: the edition set in Settings (all editions if none).
 *   pending  Placeholder cards for a slam without a published result. Default 5; 0 hides that slam.
 *   title    Heading. Default "ഫൈനലിസ്റ്റുകൾ".
 *   intro    Line under the heading. Default: the final's date and venue, when a final event exists.
 *   columns  Cards per row on wide screens, 2-5. Default 4.
 *   nav      "yes" shows a jump bar with one chip per slam. Default yes.
 */
class SSP_Finalists {

	public static function init() {
		add_shortcode( 'slam_finalists', array( __CLASS__, 'shortcode' ) );
	}

	private static function edition( $value ) {
		if ( '' === $value ) {
			$id = (int) get_option( 'ssp_current_edition' );
			return SSP_Editions::exists( $id ) ? $id : 0;
		}
		if ( is_numeric( $value ) ) {
			return SSP_Editions::exists( (int) $value ) ? (int) $value : 0;
		}
		$term = get_term_by( 'slug', sanitize_title( $value ), SSP_Editions::TAXONOMY );
		return $term && ! is_wp_error( $term ) ? (int) $term->term_id : 0;
	}

	/**
	 * Regional slams of the edition with their finalists; the edition's final event, if any.
	 */
	public static function collect( $edition, $pending ) {
		$groups = array();
		$final  = null;
		$editor = current_user_can( 'edit_posts' );
		foreach ( SSP_Post_Types::get_events( $edition ) as $term ) {
			$meta = SSP_Post_Types::get_event_meta( $term->term_id );
			if ( 'final' === $meta['event_type'] ) {
				if ( ! $final ) {
					$final = $term;
				}
				continue;
			}
			$r   = SSP_Results::get( $term->term_id );
			$ids = array();
			if ( 'publish' === $r['status'] || $editor ) {
				$ids = array_values(
					array_filter(
						$r['ids'],
						function ( $id ) {
							return 'publish' === get_post_status( $id );
						}
					)
				);
			}
			if ( ! $ids && $pending < 1 ) {
				continue;
			}
			$groups[] = array(
				'term'    => $term,
				'meta'    => $meta,
				'ids'     => $ids,
				'draft'   => $ids && 'publish' !== $r['status'],
				'pending' => $ids ? 0 : $pending,
			);
		}
		return array( $groups, $final );
	}

	private static function short_date( $date ) {
		return $date ? date_i18n( 'M j', strtotime( $date ) ) : '';
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'edition' => '',
				'pending' => 5,
				'title'   => 'ഫൈനലിസ്റ്റുകൾ',
				'intro'   => '',
				'columns' => 4,
				'nav'     => 'yes',
			),
			$atts,
			'slam_finalists'
		);
		$pending = max( 0, min( 20, (int) $atts['pending'] ) );
		list( $groups, $final ) = self::collect( self::edition( $atts['edition'] ), $pending );
		if ( ! $groups ) {
			return '';
		}
		wp_enqueue_style( 'ssp-frontend' );

		$confirmed = 0;
		$to_come   = 0;
		foreach ( $groups as $g ) {
			$confirmed += count( $g['ids'] );
			$to_come   += $g['pending'];
		}

		$intro = $atts['intro'];
		if ( '' === $intro && $final ) {
			$fm    = SSP_Post_Types::get_event_meta( $final->term_id );
			$parts = array();
			if ( $fm['event_date'] ) {
				$parts[] = date_i18n( 'l, j F Y', strtotime( $fm['event_date'] ) );
			}
			$place = implode( ', ', array_filter( array( $fm['venue'], $fm['city'] ) ) );
			if ( $place ) {
				$parts[] = $place;
			}
			$intro = implode( ' · ', $parts );
		}

		$cols = max( 2, min( 5, (int) $atts['columns'] ) );
		ob_start();
		?>
		<section class="ssp-finalists" style="--ssp-cols:<?php echo (int) $cols; ?>">
			<header class="ssp-fin-head">
				<p class="ssp-fin-eyebrow"><?php echo SSP_Frontend::icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> GRAND FINALE</p>
				<h2 class="ssp-fin-title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<?php if ( '' !== $intro ) : ?>
					<p class="ssp-fin-intro"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
				<dl class="ssp-fin-stats">
					<div><dt><?php echo (int) ( $confirmed + $to_come ); ?></dt><dd>ഫൈനലിസ്റ്റുകൾ</dd></div>
					<div><dt><?php echo (int) count( $groups ); ?></dt><dd>റീജിയണൽ സ്ലാമുകൾ</dd></div>
					<?php if ( $to_come ) : ?>
						<div><dt><?php echo (int) $confirmed; ?></dt><dd>ഉറപ്പായവർ</dd></div>
					<?php endif; ?>
				</dl>
				<?php if ( 'yes' === $atts['nav'] && count( $groups ) > 1 ) : ?>
					<nav class="ssp-fin-nav" aria-label="Regional slams">
						<?php foreach ( $groups as $g ) : ?>
							<a href="#finalists-<?php echo esc_attr( $g['term']->slug ); ?>" class="<?php echo $g['pending'] ? 'is-pending' : ''; ?>">
								<?php echo esc_html( $g['term']->name ); ?>
								<span><?php echo $g['pending'] ? esc_html( self::short_date( $g['meta']['event_date'] ) ) : (int) count( $g['ids'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</header>

			<?php foreach ( $groups as $n => $g ) : ?>
				<?php
				$m     = $g['meta'];
				$where = implode( ' · ', array_filter( array( self::short_date( $m['event_date'] ), $m['venue'] ) ) );
				$url   = SSP_Post_Types::page_url( $g['term'] );
				$count = $g['pending'] ? $g['pending'] : count( $g['ids'] );
				?>
				<div class="ssp-fin-region<?php echo $g['pending'] ? ' is-pending' : ''; ?>" id="finalists-<?php echo esc_attr( $g['term']->slug ); ?>">
					<div class="ssp-fin-region-head">
						<span class="ssp-fin-region-no"><?php echo esc_html( str_pad( (string) ( $n + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<div class="ssp-fin-region-text">
							<h3><?php echo esc_html( $g['term']->name ); ?></h3>
							<?php if ( $where ) : ?>
								<p>
									<?php if ( $url ) : ?>
										<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $where ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $where ); ?>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
						<span class="ssp-fin-region-count">
							<?php
							echo $g['pending']
								/* translators: %d: number of finalists */
								? esc_html( sprintf( __( '%d പേർ · ഫലം കാത്തിരിക്കുന്നു', 'slam-presenter-profiles' ), $count ) )
								/* translators: %d: number of finalists */
								: esc_html( sprintf( __( '%d ഫൈനലിസ്റ്റുകൾ', 'slam-presenter-profiles' ), $count ) );
							?>
						</span>
					</div>
					<?php if ( $g['draft'] ) : ?>
						<p class="ssp-tt-draft-note"><?php esc_html_e( 'Draft result: only visible to editors.', 'slam-presenter-profiles' ); ?></p>
					<?php endif; ?>
					<ul class="ssp-fin-grid">
						<?php if ( $g['pending'] ) : ?>
							<?php for ( $i = 0; $i < $g['pending']; $i++ ) : ?>
								<li class="ssp-fin-card is-tba" aria-hidden="<?php echo $i ? 'true' : 'false'; ?>">
									<span class="ssp-fin-photo"><span class="ssp-fin-tba-mark">?</span></span>
									<span class="ssp-fin-body">
										<strong>പ്രഖ്യാപിക്കാനിരിക്കുന്നു</strong>
										<small>
											<?php
											/* translators: 1: slam name, 2: date */
											echo esc_html( sprintf( __( '%1$s സ്ലാമിന് ശേഷം · %2$s', 'slam-presenter-profiles' ), $g['term']->name, self::short_date( $m['event_date'] ) ) );
											?>
										</small>
									</span>
								</li>
							<?php endfor; ?>
						<?php else : ?>
							<?php foreach ( $g['ids'] as $id ) : ?>
								<?php $talk = SSP_Presenter::get( $id, 'talk_title' ); ?>
								<li class="ssp-fin-card">
									<a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
										<span class="ssp-fin-photo">
											<?php echo SSP_Presenter::photo_html( $id, 'ssp-square', 'ssp-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
											<span class="ssp-fin-badge"><?php echo esc_html( $g['term']->name ); ?></span>
										</span>
										<span class="ssp-fin-body">
											<strong><?php echo esc_html( SSP_Presenter::display_name( $id ) ); ?></strong>
											<small><?php echo esc_html( SSP_Presenter::get( $id, 'institution' ) ); ?></small>
											<?php if ( $talk ) : ?>
												<span class="ssp-fin-talk"><?php echo esc_html( $talk ); ?></span>
											<?php endif; ?>
										</span>
									</a>
								</li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
		return ob_get_clean();
	}
}
