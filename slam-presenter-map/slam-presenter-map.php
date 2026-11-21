<?php
/**
 * Plugin Name: Science Slam Presenter Map
 * Description: A map of where the Kerala Science Slam presenters come from: one dot per presenter at their institution, coloured by slam, with Kerala's districts and Tamil Nadu. Shortcode [slam_presenter_map]. Uses the presenters and timetables of Science Slam Presenter Profiles.
 * Version: 1.0.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Kerala Science Slam
 * License: GPL-2.0-or-later
 * Text Domain: slam-presenter-map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPM_VERSION', '1.0.2' );
define( 'SPM_URL', plugin_dir_url( __FILE__ ) );
define( 'SPM_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Presenters are the talks in each slam's published timetable (Presenter Profiles); a presenter goes to the first
 * place in data/presenter-places.json whose "match" text is in their institution. The data is cached for 10 minutes
 * and dropped whenever a presenter or a slam event is saved.
 */
final class SPM_Map {

	const CACHE   = 'spm_map_data_v1';
	const COLOURS = array( 'ernakulam' => '#1565c0', 'kollam' => '#2e7d32', 'thrissur' => '#e65100', 'kannur' => '#ad1457', 'final' => '#b8860b', 'kozhikode' => '#b8860b' );

	public static function init() {
		add_shortcode( 'slam_presenter_map', array( __CLASS__, 'shortcode' ) );
		add_action( 'save_post_slam_presenter', array( __CLASS__, 'flush' ) );
		add_action( 'edited_slam_event', array( __CLASS__, 'flush' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'flush' ) );
		add_action( 'updated_term_meta', array( __CLASS__, 'flush' ) );
		add_action( 'added_term_meta', array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		delete_transient( self::CACHE );
	}

	private static function places() {
		$doc = json_decode( (string) file_get_contents( SPM_DIR . 'data/presenter-places.json' ), true );
		return isset( $doc['places'] ) ? $doc['places'] : array();
	}

	private static function place_for( $institution, $places ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $institution ) ) : strtolower( trim( (string) $institution ) );
		if ( '' === $text ) {
			return null;
		}
		foreach ( $places as $i => $p ) {
			foreach ( $p['match'] as $m ) {
				if ( false !== strpos( $text, function_exists( 'mb_strtolower' ) ? mb_strtolower( $m ) : strtolower( $m ) ) ) {
					return $i;
				}
			}
		}
		return null;
	}

	/** Presenter ids of one event: its published timetable's talks, else (no Presenter Profiles timetable) everyone tagged. */
	private static function presenter_ids( $term ) {
		if ( class_exists( 'SSP_Timetable' ) ) {
			if ( ! SSP_Timetable::is_published( $term->term_id ) ) {
				return array();
			}
			$ids = SSP_Timetable::get_talk_ids( $term->term_id );
			if ( $ids ) {
				return array_map( 'intval', $ids );
			}
		}
		return get_posts( array( 'post_type' => 'slam_presenter', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1, 'tax_query' => array( array( 'taxonomy' => 'slam_event', 'terms' => $term->term_id ) ) ) );
	}

	public static function data() {
		$hit = get_transient( self::CACHE );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$places = self::places();
		$terms  = get_terms( array( 'taxonomy' => 'slam_event', 'hide_empty' => false ) );
		$slams  = array();
		$out    = array();
		$unplaced = array();
		$total  = 0;
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		usort( $terms, function ( $a, $b ) {
			return (int) get_term_meta( $a->term_id, 'ssp_sort_order', true ) <=> (int) get_term_meta( $b->term_id, 'ssp_sort_order', true );
		} );
		foreach ( $terms as $term ) {
			$ids = self::presenter_ids( $term );
			if ( ! $ids ) {
				continue;
			}
			$date = (string) get_term_meta( $term->term_id, 'ssp_event_date', true );
			$slams[] = array(
				'slug'   => $term->slug,
				'name'   => 'Science Slam ' . $term->name,
				'date'   => $date ? date_i18n( 'j M Y', strtotime( $date ) ) : '',
				'colour' => isset( self::COLOURS[ $term->slug ] ) ? self::COLOURS[ $term->slug ] : '#555555',
			);
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( ! $post || 'publish' !== $post->post_status ) {
					continue;
				}
				$total++;
				$institution = (string) get_post_meta( $id, '_ssp_institution', true );
				$person      = array(
					'name'        => get_the_title( $id ),
					'name_en'     => (string) get_post_meta( $id, '_ssp_name_en', true ),
					'institution' => $institution,
					'topic'       => (string) get_post_meta( $id, '_ssp_talk_title', true ),
					'thumb'       => (string) get_the_post_thumbnail_url( $id, 'thumbnail' ),
					'url'         => (string) get_permalink( $id ),
					'slam'        => $term->slug,
				);
				$i = self::place_for( $institution, $places );
				if ( null === $i ) {
					$unplaced[] = $person;
					continue;
				}
				if ( ! isset( $out[ $i ] ) ) {
					$out[ $i ] = array( 'name' => $places[ $i ]['name'], 'lat' => $places[ $i ]['lat'], 'lng' => $places[ $i ]['lng'], 'approx' => ! empty( $places[ $i ]['approx'] ), 'people' => array() );
				}
				$out[ $i ]['people'][] = $person;
			}
		}
		$out = array_values( $out );
		usort( $out, function ( $a, $b ) {
			return count( $b['people'] ) - count( $a['people'] ) ?: strcmp( $a['name'], $b['name'] );
		} );
		$data = array( 'slams' => $slams, 'places' => $out, 'unplaced' => $unplaced, 'total' => $total );
		set_transient( self::CACHE, $data, 10 * MINUTE_IN_SECONDS );
		return $data;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'list' => 'yes' ), $atts, 'slam_presenter_map' );
		$data = self::data();
		wp_enqueue_style( 'spm-leaflet', SPM_URL . 'assets/leaflet/leaflet.css', array(), '1.9.4' );
		wp_enqueue_style( 'spm-map', SPM_URL . 'assets/map.css', array( 'spm-leaflet' ), SPM_VERSION );
		wp_enqueue_script( 'spm-leaflet', SPM_URL . 'assets/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_enqueue_script( 'spm-map', SPM_URL . 'assets/map.js', array( 'spm-leaflet' ), SPM_VERSION, true );
		$colour = array();
		foreach ( $data['slams'] as $s ) {
			$colour[ $s['slug'] ] = $s['colour'];
		}
		ob_start();
		?>
		<div class="spm">
			<p class="spm-intro"><?php echo esc_html( sprintf( '%d presenters from %d institutions. One dot for each presenter, coloured by their slam; tap a dot for the talk.', $data['total'], count( $data['places'] ) ) ); ?></p>
			<div class="spm-legend" role="group" aria-label="Show slams">
				<?php foreach ( $data['slams'] as $s ) : ?>
					<label class="spm-chip"><input type="checkbox" checked data-spm-slam="<?php echo esc_attr( $s['slug'] ); ?>"><span class="spm-dot" style="background:<?php echo esc_attr( $s['colour'] ); ?>"></span><?php echo esc_html( $s['name'] ); ?><?php if ( $s['date'] ) : ?> <span class="spm-muted">· <?php echo esc_html( $s['date'] ); ?></span><?php endif; ?></label>
				<?php endforeach; ?>
				<button type="button" class="spm-btn" data-spm-all>Show everyone (India and abroad)</button>
				<button type="button" class="spm-btn" data-spm-kerala hidden>Back to Kerala</button>
			</div>
			<div class="spm-map" data-spm-boundaries="<?php echo esc_url( SPM_URL . 'data/kerala-tamilnadu.geojson' ); ?>" data-spm="<?php echo esc_attr( wp_json_encode( array( 'slams' => $data['slams'], 'places' => $data['places'] ) ) ); ?>" aria-label="Map of presenter institutions"></div>
			<p class="spm-credit">Map © <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a> contributors. State and district boundaries: <a href="https://github.com/datameet/maps" rel="noopener">DataMeet</a> (CC BY 2.5 India), via Wikimedia Commons. Places marked "town" are shown at the town, not the exact campus.</p>
			<?php if ( 'no' !== $atts['list'] ) : ?>
				<h3 class="spm-h">Institutions</h3>
				<div class="spm-grid">
					<?php foreach ( $data['places'] as $p ) : ?>
						<div class="spm-card">
							<div class="spm-card-h"><?php echo esc_html( $p['name'] ); ?> <span class="spm-count"><?php echo (int) count( $p['people'] ); ?></span><?php echo $p['approx'] ? ' <span class="spm-count">town</span>' : ''; ?></div>
							<ul>
								<?php foreach ( $p['people'] as $person ) : ?>
									<li><span class="spm-dot" style="background:<?php echo esc_attr( isset( $colour[ $person['slam'] ] ) ? $colour[ $person['slam'] ] : '#555' ); ?>"></span><span><a href="<?php echo esc_url( $person['url'] ); ?>"><?php echo esc_html( $person['name'] ); ?></a><br><span class="spm-muted"><?php echo esc_html( $person['topic'] ); ?></span></span></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $data['unplaced'] && current_user_can( 'edit_posts' ) ) : ?>
					<p class="spm-muted"><strong>Not on the map yet (only editors see this):</strong>
					<?php echo esc_html( implode( '; ', array_map( function ( $x ) { return $x['name'] . ' · ' . $x['institution']; }, $data['unplaced'] ) ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
SPM_Map::init();
