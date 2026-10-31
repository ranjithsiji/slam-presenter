<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two step CSV import of Google Form responses.
 *
 * Step 1 uploads the file, step 2 maps columns to presenter fields and the venue values to slam events.
 * Existing presenters (same English name in the same event) are updated rather than duplicated.
 */
class SSP_Import {

	public static function init() {
		add_action( 'admin_post_ssp_import_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_ssp_import_run', array( __CLASS__, 'handle_run' ) );
	}

	/**
	 * Import targets: key => label. `event` maps a column of venue text to events.
	 */
	public static function targets() {
		return array(
			''                => __( '— Ignore —', 'slam-presenter-profiles' ),
			'form_no'         => __( 'Response No.', 'slam-presenter-profiles' ),
			'name_en'         => __( 'Name (English)', 'slam-presenter-profiles' ),
			'title'           => __( 'Name (Malayalam / display)', 'slam-presenter-profiles' ),
			'institution'     => __( 'Institution', 'slam-presenter-profiles' ),
			'designation'     => __( 'Designation', 'slam-presenter-profiles' ),
			'event'           => __( 'Slam event / venue', 'slam-presenter-profiles' ),
			'talk_title'      => __( 'Presentation title', 'slam-presenter-profiles' ),
			'summary'         => __( 'One paragraph summary', 'slam-presenter-profiles' ),
			'abstract'        => __( 'Detailed abstract', 'slam-presenter-profiles' ),
			'bio'             => __( 'Bio (About)', 'slam-presenter-profiles' ),
			'photo_drive_url' => __( 'Photo (Google Drive link)', 'slam-presenter-profiles' ),
			'email'           => __( 'Email', 'slam-presenter-profiles' ),
			'phone'           => __( 'Phone', 'slam-presenter-profiles' ),
			'video_url'       => __( 'Video URL', 'slam-presenter-profiles' ),
		);
	}

	/**
	 * Guess the target for a CSV header (the form headers are long Malayalam questions).
	 */
	public static function guess_target( $header, $sample ) {
		$h = mb_strtolower( trim( $header ) );
		$rules = array(
			'photo_drive_url' => '/ഫോട്ടോ|photo|picture/u',
			'talk_title'      => '/തലക്കെട്ട്|title/u',
			'summary'         => '/summary|പാരഗ്രാഫ്|കുറിപ്പ്/u',
			'abstract'        => '/abstract|സംഗ്രഹം/u',
			'bio'             => '/\bbio\b|about you|നിങ്ങളെക്കുറിച്ച്/u',
			'event'           => '/സ്ഥലം|venue|centre|center|slam/u',
			'email'           => '/e-?mail|ഇമെയിൽ/u',
			'phone'           => '/phone|mobile|ഫോൺ|whatsapp/u',
			'institution'     => '/institution|affiliation|college|സ്ഥാപനം/u',
			'designation'     => '/designation|position|തസ്തിക/u',
			'video_url'       => '/video|youtube/u',
			'title'           => '/^malayalam$|മലയാളം|പേര്/u',
			'name_en'         => '/^english$|^name$|english name|full name/u',
			'form_no'         => '/^no\.?$|^sl\.? ?no|^#$/u',
		);
		foreach ( $rules as $target => $pattern ) {
			if ( preg_match( $pattern, $h ) ) {
				return $target;
			}
		}
		// Unnamed columns ("Column 1"): guess from the sample value.
		if ( preg_match( '/institut|college|university|centre|center|csir|icar|iit|iiser|nit/i', $sample ) ) {
			return 'institution';
		}
		if ( false !== strpos( $sample, 'drive.google.com' ) ) {
			return 'photo_drive_url';
		}
		return '';
	}

	public static function read_csv( $path ) {
		$rows   = array();
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			return $rows;
		}
		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) {
			if ( array( null ) === $row ) {
				continue;
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $rows && isset( $rows[0][0] ) ) {
			$rows[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', $rows[0][0] );
		}
		return $rows;
	}

	private static function state_key() {
		return 'ssp_import_' . get_current_user_id();
	}

	public static function handle_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_import_upload' );
		$back = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-import' );

		if ( empty( $_FILES['ssp_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ssp_csv']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'Please choose a CSV file.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}
		$rows = self::read_csv( $_FILES['ssp_csv']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( count( $rows ) < 2 ) {
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'The CSV has no data rows.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}
		set_transient( self::state_key(), $rows, HOUR_IN_SECONDS );
		// The slam chosen before uploading ("venue" = use the CSV's venue column).
		$target = isset( $_POST['ssp_target_event'] ) ? sanitize_key( wp_unslash( $_POST['ssp_target_event'] ) ) : 'venue';
		set_transient( self::state_key() . '_target', 'venue' === $target ? 'venue' : absint( $target ), HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'step', 'map', $back ) );
		exit;
	}

	public static function handle_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_import_run' );
		$back = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-import' );
		$rows = get_transient( self::state_key() );
		if ( ! is_array( $rows ) ) {
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'Import session expired, please upload again.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}

		$map        = isset( $_POST['ssp_map'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['ssp_map'] ) ) : array();
		$event_map  = isset( $_POST['ssp_event_map'] ) ? wp_unslash( (array) $_POST['ssp_event_map'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- handled per value.
		$default_ev = isset( $_POST['ssp_default_event'] ) ? absint( $_POST['ssp_default_event'] ) : 0;
		$status     = isset( $_POST['ssp_status'] ) && 'draft' === $_POST['ssp_status'] ? 'draft' : 'publish';
		$overwrite  = ! empty( $_POST['ssp_overwrite'] );
		$fetch      = ! empty( $_POST['ssp_fetch_photos'] );

		$result = self::import_rows( $rows, $map, $event_map, $default_ev, $status, $overwrite );
		delete_transient( self::state_key() );

		if ( $fetch && $result['ids'] ) {
			set_transient( 'ssp_photo_queue_' . get_current_user_id(), $result['ids'], HOUR_IN_SECONDS );
		}
		unset( $result['ids'] );
		set_transient( 'ssp_import_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		$dest = $fetch
			? admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-photos&autostart=1' )
			: add_query_arg( 'step', 'done', $back );
		wp_safe_redirect( $dest );
		exit;
	}

	/**
	 * Resolve an event mapping value: a term ID, or "new:<name>" to create the event.
	 */
	private static function resolve_event( $value, &$cache ) {
		if ( isset( $cache[ $value ] ) ) {
			return $cache[ $value ];
		}
		$term_id = 0;
		if ( 0 === strpos( $value, 'new:' ) ) {
			$name = sanitize_text_field( substr( $value, 4 ) );
			$term = term_exists( $name, SSP_TAXONOMY );
			if ( ! $term ) {
				// Malayalam names make percent-encoded slugs; use a readable one for shortcodes instead.
				$slug = sanitize_title( $name );
				if ( false !== strpos( $slug, '%' ) ) {
					$slug = 'slam-' . ( (int) wp_count_terms( array( 'taxonomy' => SSP_TAXONOMY, 'hide_empty' => false ) ) + 1 );
				}
				$term = wp_insert_term( $name, SSP_TAXONOMY, array( 'slug' => $slug ) );
			}
			$term_id = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
		} else {
			$term_id = absint( $value );
		}
		$cache[ $value ] = $term_id;
		return $term_id;
	}

	public static function import_rows( $rows, $map, $event_map, $default_event, $status, $overwrite ) {
		$header  = array_shift( $rows );
		$result  = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => array(),
			'ids'     => array(),
		);
		$cache   = array();
		$columns = array();
		foreach ( $header as $i => $unused ) {
			if ( ! empty( $map[ $i ] ) ) {
				$columns[ $map[ $i ] ] = $i;
			}
		}

		// Name + event => line, to spot people who submitted the form twice.
		$seen = array();

		foreach ( $rows as $n => $row ) {
			$line = $n + 2;
			$get  = function ( $key ) use ( $columns, $row ) {
				return isset( $columns[ $key ], $row[ $columns[ $key ] ] ) ? trim( $row[ $columns[ $key ] ] ) : '';
			};

			$name_en = preg_replace( '/\s+/u', ' ', $get( 'name_en' ) );
			$title   = $get( 'title' );
			if ( '' === $name_en && '' === $title ) {
				/* translators: %d: CSV line number */
				$result['skipped'][] = sprintf( __( 'Line %d: no name.', 'slam-presenter-profiles' ), $line );
				continue;
			}

			$event_ids = array();
			$venue     = $get( 'event' );
			if ( '' !== $venue && isset( $event_map[ md5( $venue ) ] ) && '' !== $event_map[ md5( $venue ) ] ) {
				$event_ids[] = self::resolve_event( (string) $event_map[ md5( $venue ) ], $cache );
			} elseif ( $default_event ) {
				$event_ids[] = $default_event;
			}
			$event_ids = array_filter( $event_ids );

			$existing = $name_en ? SSP_Presenter::find_by_name_en( $name_en, $event_ids ? $event_ids[0] : 0 ) : 0;

			// A repeated submission in the same file is a correction: the later row wins.
			$seen_key      = mb_strtolower( $name_en ? $name_en : $title ) . '|' . implode( ',', $event_ids );
			$row_overwrite = $overwrite;
			if ( isset( $seen[ $seen_key ] ) ) {
				$row_overwrite       = true;
				/* translators: 1: CSV line number, 2: earlier line number */
				$result['skipped'][] = sprintf( __( 'Line %1$d: same person as line %2$d (submitted twice), the later row was used.', 'slam-presenter-profiles' ), $line, $seen[ $seen_key ] );
			}
			$postarr  = array(
				'post_type'   => SSP_POST_TYPE,
				'post_title'  => '' !== $title ? $title : $name_en,
				'post_name'   => sanitize_title( $name_en ? $name_en : $title ),
				'post_status' => $status,
			);
			if ( '' !== $get( 'bio' ) ) {
				$postarr['post_content'] = wp_kses_post( $get( 'bio' ) );
			}

			if ( $existing ) {
				$postarr['ID'] = $existing;
				unset( $postarr['post_status'], $postarr['post_name'] );
				if ( ! $row_overwrite ) {
					unset( $postarr['post_title'], $postarr['post_content'] );
				}
				$post_id = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}
			if ( is_wp_error( $post_id ) ) {
				/* translators: 1: CSV line number, 2: error message */
				$result['skipped'][] = sprintf( __( 'Line %1$d: %2$s', 'slam-presenter-profiles' ), $line, $post_id->get_error_message() );
				continue;
			}

			foreach ( array_keys( SSP_Presenter::fields() ) as $key ) {
				if ( ! isset( $columns[ $key ] ) ) {
					continue;
				}
				$value = 'name_en' === $key ? $name_en : SSP_Presenter::sanitize_field( $key, $get( $key ) );
				if ( $existing && ! $row_overwrite && '' !== SSP_Presenter::get( $post_id, $key ) ) {
					continue;
				}
				if ( '' !== $value ) {
					SSP_Presenter::set( $post_id, $key, $value );
				}
			}
			if ( $event_ids ) {
				wp_set_object_terms( $post_id, array_map( 'intval', $event_ids ), SSP_TAXONOMY, true );
			}

			if ( ! isset( $seen[ $seen_key ] ) ) {
				$existing ? $result['updated']++ : $result['created']++;
				$result['ids'][] = (int) $post_id;
			}
			$seen[ $seen_key ] = $line;
		}
		return $result;
	}

	public static function render_page() {
		require SSP_PLUGIN_DIR . 'includes/views/import.php';
	}
}
