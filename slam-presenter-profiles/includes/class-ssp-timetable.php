<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Timetable storage, time calculation, auto-generation and text import for a slam event.
 *
 * A schedule is stored as term meta `ssp_schedule`:
 *   array( 'start' => '09:30', 'items' => array( item, ... ) )
 * Each item: type (session|talk|break|item), label, note, duration (minutes), presenter_id, fixed (optional "HH:MM").
 * Times are derived: every item starts when the previous one ends, unless it has a fixed start.
 */
class SSP_Timetable {

	const META = 'ssp_schedule';

	public static function init() {
		add_action( 'wp_ajax_ssp_save_timetable', array( __CLASS__, 'ajax_save' ) );
		add_action( 'admin_post_ssp_new_timetable', array( __CLASS__, 'handle_new' ) );
		add_action( 'admin_post_ssp_timetable_status', array( __CLASS__, 'handle_status' ) );
		add_action( 'updated_term_meta', array( __CLASS__, 'maybe_clear_cache' ), 10, 3 );
		add_action( 'added_term_meta', array( __CLASS__, 'maybe_clear_cache' ), 10, 3 );
		add_action( 'wp_ajax_ssp_generate_timetable', array( __CLASS__, 'ajax_generate' ) );
		add_action( 'wp_ajax_ssp_parse_timetable', array( __CLASS__, 'ajax_parse' ) );
	}

	public static function is_published( $term_id ) {
		return 'publish' === SSP_Post_Types::get_event_meta( $term_id )['status'];
	}

	/**
	 * Draft timetables are shown to people who can edit presenters (with a draft note), hidden from visitors.
	 */
	public static function is_visible( $term_id ) {
		return self::is_published( $term_id ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Empty WP Super Cache so visitors see a timetable change at once.
	 */
	public static function clear_page_cache() {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	public static function maybe_clear_cache( $meta_id, $term_id, $key ) {
		if ( 'ssp_status' === $key ) {
			self::clear_page_cache();
		}
	}

	public static function handle_status() {
		$term_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		$status  = isset( $_GET['status'] ) && 'draft' === $_GET['status'] ? 'draft' : 'publish';
		if ( ! current_user_can( 'manage_categories' ) || ! $term_id ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_timetable_status_' . $term_id );
		update_term_meta( $term_id, 'ssp_status', $status );
		self::clear_page_cache();
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable' );
		wp_safe_redirect( add_query_arg( 'ssp_status_changed', $status, remove_query_arg( array( 'ssp_status_changed', 'created', 'unmatched' ), $back ) ) );
		exit;
	}

	/**
	 * Grow or shrink the number of talk slots: new empty slots go after the last talk (or into a
	 * first session on a blank day); only empty slots are removed, from the end.
	 */
	public static function resize_slots( $items, $count ) {
		$talks = array_keys( array_filter( $items, function ( $i ) { return 'talk' === $i['type']; } ) );
		$have  = count( $talks );
		if ( $count > $have ) {
			$new = array();
			for ( $n = $have; $n < $count; $n++ ) {
				$new[] = array( 'type' => 'talk', 'presenter_id' => 0, 'duration' => (int) SSP_Settings::get( 'talk_minutes' ) );
			}
			if ( $talks ) {
				array_splice( $items, end( $talks ) + 1, 0, $new );
			} else {
				$items = array_merge( $items, array( array( 'type' => 'session', 'label' => 'സെഷൻ 1 (Session 1)' ) ), $new );
			}
		} else {
			foreach ( array_reverse( $talks ) as $index ) {
				if ( $have <= $count ) {
					break;
				}
				if ( empty( $items[ $index ]['presenter_id'] ) ) {
					unset( $items[ $index ] );
					$have--;
				}
			}
			$items = array_values( $items );
		}
		return $items;
	}

	public static function status_url( $term_id, $status ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=ssp_timetable_status&event=' . (int) $term_id . '&status=' . $status ), 'ssp_timetable_status_' . (int) $term_id );
	}

	public static function get_schedule( $term_id ) {
		$schedule = get_term_meta( $term_id, self::META, true );
		if ( ! is_array( $schedule ) ) {
			$schedule = array();
		}
		return wp_parse_args(
			$schedule,
			array(
				'start' => '09:30',
				'items' => array(),
			)
		);
	}

	public static function sanitize_schedule( $schedule ) {
		$clean = array(
			'start' => self::sanitize_time( isset( $schedule['start'] ) ? $schedule['start'] : '' ),
			'items' => array(),
		);
		if ( '' === $clean['start'] ) {
			$clean['start'] = '09:30';
		}
		$items = isset( $schedule['items'] ) && is_array( $schedule['items'] ) ? $schedule['items'] : array();
		foreach ( $items as $item ) {
			$type = isset( $item['type'] ) && in_array( $item['type'], array( 'session', 'talk', 'break', 'item' ), true ) ? $item['type'] : 'item';
			$clean['items'][] = array(
				'type'         => $type,
				'label'        => sanitize_text_field( isset( $item['label'] ) ? $item['label'] : '' ),
				'note'         => sanitize_text_field( isset( $item['note'] ) ? $item['note'] : '' ),
				'duration'     => 'session' === $type ? 0 : max( 0, (int) ( isset( $item['duration'] ) ? $item['duration'] : 0 ) ),
				'presenter_id' => 'talk' === $type ? absint( isset( $item['presenter_id'] ) ? $item['presenter_id'] : 0 ) : 0,
				'fixed'        => self::sanitize_time( isset( $item['fixed'] ) ? $item['fixed'] : '' ),
			);
		}
		return $clean;
	}

	public static function save_schedule( $term_id, $schedule ) {
		update_term_meta( $term_id, self::META, self::sanitize_schedule( $schedule ) );
	}

	private static function sanitize_time( $time ) {
		return preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', trim( (string) $time ) ) ? trim( $time ) : '';
	}

	public static function to_minutes( $time ) {
		list( $h, $m ) = array_map( 'intval', explode( ':', $time ) );
		return $h * 60 + $m;
	}

	public static function format_minutes( $minutes ) {
		$h      = (int) floor( $minutes / 60 ) % 24;
		$m      = $minutes % 60;
		$suffix = $h >= 12 ? 'PM' : 'AM';
		$h12    = $h % 12 ? $h % 12 : 12;
		return sprintf( '%02d:%02d %s', $h12, $m, $suffix );
	}

	/**
	 * Items with computed `start` / `end` minutes and a running talk number `no`.
	 * Session headings span from their first to their last child item.
	 */
	public static function computed( $term_id ) {
		$schedule = self::get_schedule( $term_id );
		$clock    = self::to_minutes( $schedule['start'] );
		$items    = $schedule['items'];
		$no       = 0;
		$session  = null;

		foreach ( $items as $i => $item ) {
			if ( 'session' === $item['type'] ) {
				if ( null !== $session ) {
					$items[ $session ]['end'] = $clock;
				}
				$session                = $i;
				$items[ $i ]['start']   = $item['fixed'] ? self::to_minutes( $item['fixed'] ) : $clock;
				$clock                  = $items[ $i ]['start'];
				continue;
			}
			if ( $item['fixed'] ) {
				$clock = self::to_minutes( $item['fixed'] );
			}
			$items[ $i ]['start'] = $clock;
			$clock               += (int) $item['duration'];
			$items[ $i ]['end']   = $clock;
			if ( 'talk' === $item['type'] ) {
				$items[ $i ]['no'] = ++$no;
			}
			// A break closes the running session.
			if ( 'break' === $item['type'] && null !== $session ) {
				$items[ $session ]['end'] = $items[ $i ]['start'];
				$session                  = null;
			}
		}
		if ( null !== $session ) {
			$items[ $session ]['end'] = $clock;
		}
		return $items;
	}

	public static function get_talk_ids( $term_id ) {
		$ids = array();
		foreach ( self::get_schedule( $term_id )['items'] as $item ) {
			if ( 'talk' === $item['type'] && $item['presenter_id'] ) {
				$ids[] = (int) $item['presenter_id'];
			}
		}
		return $ids;
	}

	/**
	 * Computed timetable row for a presenter in an event, or null.
	 */
	public static function find_slot( $term_id, $presenter_id ) {
		foreach ( self::computed( $term_id ) as $item ) {
			if ( 'talk' === $item['type'] && (int) $item['presenter_id'] === (int) $presenter_id ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Build a full-day schedule from the presenters assigned to the event.
	 * Talks are split into morning (two sessions around tea) and afternoon sessions around lunch.
	 */
	public static function generate( $term_id, $opts = array() ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'start'        => '09:30',
				'talk_minutes' => (int) SSP_Settings::get( 'talk_minutes' ),
				'per_session'  => 8,
				'tea_minutes'  => 15,
				'lunch_at'     => 3,
				'lunch'        => 60,
				'order'        => 'title',
			)
		);

		$ids = SSP_Presenter::get_event_presenter_ids( $term_id, array( 'publish', 'draft', 'pending', 'private' ) );
		if ( 'random' === $opts['order'] ) {
			shuffle( $ids );
		}
		$per_session = max( 1, (int) $opts['per_session'] );
		$chunks      = array_chunk( $ids, $per_session );

		$items   = array();
		$items[] = array( 'type' => 'session', 'label' => 'ഉദ്ഘാടന ചടങ്ങ് (Inaugural Session)' );
		$items[] = array( 'type' => 'item', 'label' => 'സ്വാഗത പ്രസംഗം', 'duration' => 5 );
		$items[] = array( 'type' => 'item', 'label' => 'ഔദ്യോഗിക ഉദ്ഘാടനവും മുഖ്യപ്രസംഗവും', 'duration' => 15 );
		$items[] = array( 'type' => 'item', 'label' => 'സ്ലാം നിയമങ്ങളുടെയും അതിഥികളുടെയും പരിചയപ്പെടുത്തൽ', 'duration' => 10 );

		$lunch_after = max( 1, min( count( $chunks ), (int) $opts['lunch_at'] - 1 ) );
		foreach ( $chunks as $n => $chunk ) {
			$items[] = array(
				'type'  => 'session',
				/* translators: %d: session number */
				'label' => sprintf( 'സെഷൻ %d (Session %d)', $n + 1, $n + 1 ),
			);
			foreach ( $chunk as $id ) {
				$items[] = array(
					'type'         => 'talk',
					'presenter_id' => $id,
					'duration'     => (int) $opts['talk_minutes'],
				);
			}
			if ( $n === count( $chunks ) - 1 ) {
				break;
			}
			if ( $n + 1 === $lunch_after ) {
				$items[] = array( 'type' => 'break', 'label' => '🍲 ഉച്ചഭക്ഷണം (Lunch Break)', 'duration' => (int) $opts['lunch'] );
			} else {
				$items[] = array( 'type' => 'break', 'label' => '☕ ടീ ബ്രേക്ക് (Tea Break)', 'duration' => (int) $opts['tea_minutes'] );
			}
		}

		$items[] = array( 'type' => 'break', 'label' => '☕ ടീ ബ്രേക്ക് (Tea Break)', 'duration' => (int) $opts['tea_minutes'] );
		$items[] = array( 'type' => 'session', 'label' => 'സമാപന സമ്മേളനം & ഫലപ്രഖ്യാപനം (Valedictory Session)' );
		$items[] = array( 'type' => 'item', 'label' => 'വിലയിരുത്തലുകളും അതിഥികളുടെ സംസാരിക്കലും', 'duration' => 30 );
		$items[] = array( 'type' => 'item', 'label' => 'സർട്ടിഫിക്കറ്റ് വിതരണവും വിജയികളെ പ്രഖ്യാപിക്കലും', 'duration' => 30 );
		$items[] = array( 'type' => 'item', 'label' => 'നന്ദിപ്രകാശനം & ഫോട്ടോ സെഷൻ', 'duration' => 15 );

		return self::sanitize_schedule(
			array(
				'start' => $opts['start'],
				'items' => $items,
			)
		);
	}

	/**
	 * Parse a timetable written as text (the format the team already circulates):
	 *   സെഷൻ 1 (Morning Session 1): 10:00 AM - 11:24 AM
	 *   10:00 AM – 10:12 AM | മനു മാധവൻ — lncRNA: ജനിതക കഥയിലെ കാണാക്കണ്ണികൾ
	 *   ☕ 11:24 AM – 11:40 AM | ടീ ബ്രേക്ക് (16 മിനിറ്റ്)
	 * Presenters are matched by name, then by talk title.
	 *
	 * @return array schedule plus 'unmatched' => list of labels that could not be matched.
	 */
	public static function parse_text( $term_id, $text ) {
		$time_re   = '(\d{1,2}):(\d{2})\s*([AaPp][Mm])?';
		$range_re  = '/' . $time_re . '\s*[–—\-]+\s*' . $time_re . '/u';
		$presenter = self::presenter_index( $term_id );
		$items     = array();
		$start     = '';
		$clock     = null;
		$unmatched = array();

		foreach ( preg_split( '/\R/u', $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$has_range = preg_match( $range_re, $line, $m, PREG_OFFSET_CAPTURE );

			if ( ! $has_range ) {
				// A note in brackets belongs to the previous session heading.
				if ( preg_match( '/^\((.+)\)$/u', $line, $note ) && $items ) {
					for ( $i = count( $items ) - 1; $i >= 0; $i-- ) {
						if ( 'session' === $items[ $i ]['type'] ) {
							$items[ $i ]['note'] = $note[1];
							break;
						}
					}
				}
				continue;
			}

			$from = self::clock_from_match( $m[1][0], $m[2][0], $m[3][0] );
			$to   = self::clock_from_match( $m[4][0], $m[5][0], isset( $m[6][0] ) && '' !== $m[6][0] ? $m[6][0] : $m[3][0] );
			$pipe = mb_strpos( $line, '|' );

			if ( false === $pipe ) {
				// "Label: 10:00 AM - 11:24 AM" is a session heading.
				$label   = trim( preg_replace( '/[:\s]+$/u', '', substr( $line, 0, $m[0][1] ) ) );
				$items[] = array(
					'type'  => 'session',
					'label' => $label,
					'fixed' => null !== $clock && $clock !== $from ? self::minutes_to_hm( $from ) : '',
				);
				if ( '' === $start ) {
					$start = self::minutes_to_hm( $from );
					$clock = $from;
				}
				continue;
			}

			$label    = trim( mb_substr( $line, $pipe + 1 ) );
			$prefix   = trim( substr( $line, 0, $m[0][1] ) );
			$duration = max( 0, $to - $from );
			$item     = array(
				'duration' => $duration,
				'fixed'    => null !== $clock && $clock !== $from ? self::minutes_to_hm( $from ) : '',
			);
			if ( '' === $start ) {
				$start = self::minutes_to_hm( $from );
			}
			$clock = $to;

			if ( preg_match( '/^(.+?)\s+[—–]\s+(.+)$/u', $label, $parts ) ) {
				$id = self::match_presenter( $presenter, $parts[1], $parts[2] );
				$item['type']         = 'talk';
				$item['presenter_id'] = $id;
				$item['label']        = $id ? '' : $label;
				if ( ! $id ) {
					$unmatched[] = $label;
				}
			} elseif ( '' !== $prefix || preg_match( '/ബ്രേക്ക്|break|ഭക്ഷണം|lunch/iu', $label ) ) {
				$item['type']  = 'break';
				$item['label'] = trim( $prefix . ' ' . preg_replace( '/\s*\(\d+\s*മിനിറ്റ്\)|\s*\(\d+\s*min(ute)?s?\)/iu', '', $label ) );
			} else {
				$item['type']  = 'item';
				$item['label'] = $label;
			}
			$items[] = $item;
		}

		$schedule              = self::sanitize_schedule(
			array(
				'start' => $start,
				'items' => $items,
			)
		);
		$schedule['unmatched'] = $unmatched;
		return $schedule;
	}

	private static function clock_from_match( $h, $m, $ampm ) {
		$h    = (int) $h;
		$ampm = strtoupper( (string) $ampm );
		if ( 'PM' === $ampm && $h < 12 ) {
			$h += 12;
		} elseif ( 'AM' === $ampm && 12 === $h ) {
			$h = 0;
		}
		return $h * 60 + (int) $m;
	}

	private static function minutes_to_hm( $minutes ) {
		return sprintf( '%02d:%02d', floor( $minutes / 60 ), $minutes % 60 );
	}

	/**
	 * Name/title lookup for the presenters of an event (falls back to all presenters).
	 */
	private static function presenter_index( $term_id ) {
		$ids = SSP_Presenter::get_event_presenter_ids( $term_id );
		if ( ! $ids ) {
			$ids = get_posts(
				array(
					'post_type'      => SSP_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
		}
		$index = array();
		foreach ( $ids as $id ) {
			$index[ $id ] = array(
				'names' => array_filter(
					array(
						self::normalize( get_the_title( $id ) ),
						self::normalize( SSP_Presenter::get( $id, 'name_en' ) ),
					)
				),
				'title' => self::normalize( SSP_Presenter::get( $id, 'talk_title' ) ),
			);
		}
		return $index;
	}

	/**
	 * Lower-case, drop honorifics, punctuation and spaces so "ഡോ. കാർത്തിക ശങ്കർ" matches "കാർത്തിക ശങ്കർ".
	 */
	public static function normalize( $text ) {
		$text = mb_strtolower( (string) $text );
		$text = preg_replace( '/^(dr|prof|ഡോ|പ്രൊഫ)\.?\s*/u', '', trim( $text ) );
		return preg_replace( '/[\s\p{P}\p{S}]+/u', '', $text );
	}

	private static function match_presenter( $index, $name, $title ) {
		// Names may be written "മലയാളം (English)": try the whole string and each part.
		$names = array( self::normalize( $name ) );
		if ( preg_match( '/^(.+?)\s*\((.+)\)\s*$/u', $name, $parts ) ) {
			$names[] = self::normalize( $parts[1] );
			$names[] = self::normalize( $parts[2] );
		}
		$title = self::normalize( preg_replace( '/[.…]+$/u', '', $title ) );
		foreach ( $index as $id => $entry ) {
			if ( array_intersect( $names, $entry['names'] ) ) {
				return $id;
			}
		}
		// Title match: the timetable often shortens titles with "...".
		$best    = 0;
		$best_id = 0;
		foreach ( $index as $id => $entry ) {
			if ( '' === $entry['title'] || '' === $title ) {
				continue;
			}
			if ( 0 === mb_strpos( $entry['title'], $title ) || 0 === mb_strpos( $title, $entry['title'] ) ) {
				return $id;
			}
			similar_text( $entry['title'], $title, $percent );
			if ( $percent > $best ) {
				$best    = $percent;
				$best_id = $id;
			}
		}
		return $best >= 70 ? $best_id : 0;
	}

	private static function check_ajax() {
		check_ajax_referer( 'ssp_timetable', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slam-presenter-profiles' ) ), 403 );
		}
		$term_id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0;
		if ( ! $term_id || ! term_exists( $term_id, SSP_TAXONOMY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown event.', 'slam-presenter-profiles' ) ) );
		}
		return $term_id;
	}

	public static function ajax_save() {
		$term_id  = self::check_ajax();
		$schedule = isset( $_POST['schedule'] ) ? json_decode( wp_unslash( $_POST['schedule'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by sanitize_schedule.
		if ( ! is_array( $schedule ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid timetable data.', 'slam-presenter-profiles' ) ) );
		}
		self::save_schedule( $term_id, $schedule );
		// Scheduled presenters belong to the event (eg. finalists picked from all presenters).
		foreach ( self::get_talk_ids( $term_id ) as $presenter_id ) {
			if ( current_user_can( 'edit_post', $presenter_id ) ) {
				wp_set_object_terms( $presenter_id, $term_id, SSP_TAXONOMY, true );
			}
		}
		if ( self::is_published( $term_id ) ) {
			self::clear_page_cache();
		}
		wp_send_json_success( array( 'schedule' => self::get_schedule( $term_id ) ) );
	}

	/**
	 * "Add New Timetable": create the event, then start its timetable blank, from another event's
	 * day layout (talk slots left empty) or from pasted timetable text.
	 */
	public static function handle_new() {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_new_timetable' );
		$page  = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable' );
		$input = wp_unslash( (array) ( isset( $_POST['ssp_new'] ) ? $_POST['ssp_new'] : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		$name  = sanitize_text_field( isset( $input['name'] ) ? $input['name'] : '' );
		$back  = add_query_arg( 'new', 1, $page );
		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'Please give the event a name.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}

		$slug = sanitize_title( isset( $input['slug'] ) && '' !== trim( $input['slug'] ) ? $input['slug'] : $name );
		if ( false !== strpos( $slug, '%' ) ) {
			$slug = 'slam-' . ( (int) wp_count_terms( array( 'taxonomy' => SSP_TAXONOMY, 'hide_empty' => false ) ) + 1 );
		}
		$term = wp_insert_term( $name, SSP_TAXONOMY, array( 'slug' => $slug ) );
		if ( is_wp_error( $term ) ) {
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( $term->get_error_message() ), $back ) );
			exit;
		}
		$term_id = (int) $term['term_id'];
		// New timetables stay drafts unless "Publish now" is ticked.
		$input['status'] = empty( $input['publish'] ) ? 'draft' : 'publish';
		foreach ( array_keys( SSP_Post_Types::event_fields() ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				update_term_meta( $term_id, 'ssp_' . $key, 'schedule_url' === $key ? esc_url_raw( $input[ $key ] ) : sanitize_text_field( $input[ $key ] ) );
			}
		}

		$start  = sanitize_text_field( isset( $input['start'] ) ? $input['start'] : '09:30' );
		$from   = sanitize_key( isset( $input['from'] ) ? $input['from'] : 'blank' );
		$source = isset( $input['source'] ) ? absint( $input['source'] ) : 0;
		$note   = '';
		if ( 'copy' === $from && $source ) {
			$schedule = self::get_schedule( $source );
			foreach ( $schedule['items'] as &$item ) {
				if ( 'talk' === $item['type'] ) {
					$item['presenter_id'] = 0;
					$item['label']        = '';
				}
			}
			unset( $item );
		} elseif ( 'text' === $from && ! empty( $input['text'] ) ) {
			$schedule = self::parse_text( $term_id, sanitize_textarea_field( $input['text'] ) );
			if ( ! empty( $schedule['unmatched'] ) ) {
				$note = (string) count( $schedule['unmatched'] );
			}
		} else {
			$schedule = array( 'items' => array() );
		}
		if ( 'text' !== $from || empty( $schedule['start'] ) ) {
			$schedule['start'] = $start;
		}
		if ( 'text' !== $from && isset( $input['slots'] ) && '' !== $input['slots'] ) {
			$schedule['items'] = self::resize_slots( $schedule['items'], min( 200, absint( $input['slots'] ) ) );
		}
		self::save_schedule( $term_id, $schedule );

		wp_safe_redirect( add_query_arg( array( 'event' => $term_id, 'created' => 1, 'unmatched' => $note ), $page ) );
		exit;
	}

	public static function ajax_generate() {
		$term_id = self::check_ajax();
		$opts    = array();
		foreach ( array( 'start', 'talk_minutes', 'per_session', 'tea_minutes', 'lunch_at', 'lunch', 'order' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$opts[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		wp_send_json_success( array( 'schedule' => self::generate( $term_id, $opts ) ) );
	}

	public static function ajax_parse() {
		$term_id = self::check_ajax();
		$text    = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		wp_send_json_success( array( 'schedule' => self::parse_text( $term_id, $text ) ) );
	}
}
