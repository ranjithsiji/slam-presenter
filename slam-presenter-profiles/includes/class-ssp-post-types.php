<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Presenter post type, the Slam Event taxonomy and the event term fields.
 */
class SSP_Post_Types {

	/**
	 * Term meta fields stored on each slam event.
	 */
	public static function event_fields() {
		return array(
			'edition'     => __( 'Edition', 'slam-presenter-profiles' ),
			'status'      => __( 'Timetable', 'slam-presenter-profiles' ),
			'event_type'  => __( 'Type', 'slam-presenter-profiles' ),
			'event_date'  => __( 'Date', 'slam-presenter-profiles' ),
			'venue'       => __( 'Venue', 'slam-presenter-profiles' ),
			'city'        => __( 'City', 'slam-presenter-profiles' ),
			'label'       => __( 'Profile banner text', 'slam-presenter-profiles' ),
			'page_url'     => __( 'Slam page', 'slam-presenter-profiles' ),
			'photo_folder' => __( 'Google Drive photo folder', 'slam-presenter-profiles' ),
			'schedule_url' => __( 'Schedule page', 'slam-presenter-profiles' ),
			'sort_order'  => __( 'Order', 'slam-presenter-profiles' ),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_action( SSP_TAXONOMY . '_add_form_fields', array( __CLASS__, 'render_add_fields' ) );
		add_action( SSP_TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_edit_fields' ) );
		add_action( 'created_' . SSP_TAXONOMY, array( __CLASS__, 'save_term_fields' ) );
		add_action( 'edited_' . SSP_TAXONOMY, array( __CLASS__, 'save_term_fields' ) );
		add_filter( 'manage_edit-' . SSP_TAXONOMY . '_columns', array( __CLASS__, 'term_columns' ) );
		add_filter( 'manage_' . SSP_TAXONOMY . '_custom_column', array( __CLASS__, 'term_column_content' ), 10, 3 );
	}

	public static function register() {
		add_image_size( 'ssp-square', 800, 800, true );
		add_image_size( 'ssp-thumb', 160, 160, true );

		register_post_type(
			SSP_POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Presenters', 'slam-presenter-profiles' ),
					'singular_name'      => __( 'Presenter', 'slam-presenter-profiles' ),
					'add_new'            => __( 'Add Presenter', 'slam-presenter-profiles' ),
					'add_new_item'       => __( 'Add New Presenter', 'slam-presenter-profiles' ),
					'edit_item'          => __( 'Edit Presenter', 'slam-presenter-profiles' ),
					'new_item'           => __( 'New Presenter', 'slam-presenter-profiles' ),
					'view_item'          => __( 'View Profile', 'slam-presenter-profiles' ),
					'search_items'       => __( 'Search Presenters', 'slam-presenter-profiles' ),
					'not_found'          => __( 'No presenters found.', 'slam-presenter-profiles' ),
					'all_items'          => __( 'All Presenters', 'slam-presenter-profiles' ),
					'menu_name'          => __( 'Science Slam', 'slam-presenter-profiles' ),
					'featured_image'     => __( 'Presenter Photo', 'slam-presenter-profiles' ),
					'set_featured_image' => __( 'Set presenter photo', 'slam-presenter-profiles' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-microphone',
				'menu_position' => 25,
				'has_archive'   => false,
				'rewrite'       => array(
					'slug'       => SSP_Settings::get( 'presenter_slug' ),
					'with_front' => false,
				),
				// Classic editor screen: the profile is made of structured fields, the content box is the bio.
				'supports'      => array( 'title', 'editor', 'thumbnail', 'revisions' ),
			)
		);
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );

		register_taxonomy(
			SSP_TAXONOMY,
			SSP_POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Slam Events', 'slam-presenter-profiles' ),
					'singular_name' => __( 'Slam Event', 'slam-presenter-profiles' ),
					'add_new_item'  => __( 'Add New Slam Event', 'slam-presenter-profiles' ),
					'edit_item'     => __( 'Edit Slam Event', 'slam-presenter-profiles' ),
					'search_items'  => __( 'Search Events', 'slam-presenter-profiles' ),
					'all_items'     => __( 'All Events', 'slam-presenter-profiles' ),
					'menu_name'     => __( 'Slam Events', 'slam-presenter-profiles' ),
				),
				// Event pages are normal pages using the shortcodes, so no taxonomy archives.
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'hierarchical'       => true, // Checkbox UI on the presenter screen.
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rewrite'            => false,
			)
		);
	}

	public static function disable_block_editor( $use, $post_type ) {
		return SSP_POST_TYPE === $post_type ? false : $use;
	}

	public static function maybe_flush() {
		if ( get_option( 'ssp_flush_rewrite' ) ) {
			delete_option( 'ssp_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	public static function get_event_meta( $term_id ) {
		$meta = array();
		foreach ( array_keys( self::event_fields() ) as $key ) {
			$meta[ $key ] = (string) get_term_meta( $term_id, 'ssp_' . $key, true );
		}
		if ( '' === $meta['event_type'] ) {
			$meta['event_type'] = 'regional';
		}
		// Events from before the status existed are published.
		if ( 'draft' !== $meta['status'] ) {
			$meta['status'] = 'publish';
		}
		return $meta;
	}

	/**
	 * Events ordered by their Order field, then date.
	 *
	 * @param int|string $edition Edition term ID, 0 for all, or 'current': the working edition in admin, all elsewhere.
	 */
	public static function get_events( $edition = 'current' ) {
		if ( 'current' === $edition ) {
			$edition = is_admin() ? SSP_Editions::current() : 0;
		}
		$args = array(
			'taxonomy'   => SSP_TAXONOMY,
			'hide_empty' => false,
		);
		if ( $edition ) {
			$args['include'] = SSP_Editions::event_ids( $edition ) ? SSP_Editions::event_ids( $edition ) : array( -1 );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		usort(
			$terms,
			function ( $a, $b ) {
				$ma = SSP_Post_Types::get_event_meta( $a->term_id );
				$mb = SSP_Post_Types::get_event_meta( $b->term_id );
				$cmp = (int) $ma['sort_order'] <=> (int) $mb['sort_order'];
				return $cmp ? $cmp : strcmp( $ma['event_date'], $mb['event_date'] );
			}
		);
		return $terms;
	}

	/**
	 * Text shown above the presenter name, eg. "KERALA SCIENCE SLAM FINAL – DEC 14 – IIT PALAKKAD".
	 */
	public static function event_banner( $term ) {
		$meta = self::get_event_meta( $term->term_id );
		if ( '' !== $meta['label'] ) {
			return $meta['label'];
		}
		$parts = array( $term->name );
		if ( $meta['event_date'] ) {
			$parts[] = date_i18n( 'M j', strtotime( $meta['event_date'] ) );
		}
		if ( $meta['venue'] ) {
			$parts[] = $meta['venue'];
		}
		return implode( ' – ', $parts );
	}

	/**
	 * Page showing the event's timetable: the event's Schedule page field, else a published page
	 * whose content has [slam_timetable event="<slug or id>"].
	 */
	public static function schedule_url( $term ) {
		$url = self::get_event_meta( $term->term_id )['schedule_url'];
		if ( '' !== $url ) {
			return $url;
		}
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND post_content LIKE %s ORDER BY post_type = 'page' DESC, ID ASC",
				'%' . $wpdb->esc_like( '[slam_timetable' ) . '%'
			)
		);
		foreach ( $ids as $id ) {
			$content = get_post_field( 'post_content', $id );
			if ( preg_match_all( '/\[slam_timetable[^\]]*event=["\']?([^"\'\s\]]+)/', $content, $m ) ) {
				foreach ( $m[1] as $event ) {
					if ( $event === $term->slug || (int) $event === (int) $term->term_id ) {
						return get_permalink( $id );
					}
				}
			}
		}
		return '';
	}

	/**
	 * The slam's own page (profile links point here), falling back to the schedule page.
	 */
	public static function page_url( $term ) {
		$url = self::get_event_meta( $term->term_id )['page_url'];
		return '' !== $url ? $url : self::schedule_url( $term );
	}

	private static function field_input( $key, $value ) {
		$name = 'ssp_event[' . $key . ']';
		switch ( $key ) {
			case 'event_type':
				printf( '<select name="%s" id="ssp_%s">', esc_attr( $name ), esc_attr( $key ) );
				foreach ( array(
					'regional' => __( 'Regional centre', 'slam-presenter-profiles' ),
					'final'    => __( 'Final slam', 'slam-presenter-profiles' ),
				) as $opt => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'edition':
				$value = $value ? (int) $value : SSP_Editions::current();
				printf( '<select name="%s" id="ssp_%s">', esc_attr( $name ), esc_attr( $key ) );
				foreach ( SSP_Editions::all() as $edition ) {
					printf( '<option value="%d" %s>%s</option>', (int) $edition->term_id, selected( $value, $edition->term_id, false ), esc_html( $edition->name ) );
				}
				echo '</select>';
				printf( ' <a href="%s">%s</a>', esc_url( admin_url( 'edit-tags.php?taxonomy=' . SSP_Editions::TAXONOMY . '&post_type=' . SSP_POST_TYPE ) ), esc_html__( 'Add edition', 'slam-presenter-profiles' ) );
				break;
			case 'status':
				printf( '<select name="%s" id="ssp_%s">', esc_attr( $name ), esc_attr( $key ) );
				foreach ( array(
					'publish' => __( 'Published: visible on the website', 'slam-presenter-profiles' ),
					'draft'   => __( 'Draft: only editors can see it', 'slam-presenter-profiles' ),
				) as $opt => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( '' === $value ? 'publish' : $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'event_date':
				printf( '<input type="date" name="%s" id="ssp_%s" value="%s">', esc_attr( $name ), esc_attr( $key ), esc_attr( $value ) );
				break;
			case 'page_url':
			case 'photo_folder':
			case 'schedule_url':
				printf( '<input type="url" name="%s" id="ssp_%s" value="%s" class="regular-text" placeholder="https://">', esc_attr( $name ), esc_attr( $key ), esc_attr( $value ) );
				break;
			case 'sort_order':
				printf( '<input type="number" name="%s" id="ssp_%s" value="%s" class="small-text">', esc_attr( $name ), esc_attr( $key ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="text" name="%s" id="ssp_%s" value="%s" class="regular-text">', esc_attr( $name ), esc_attr( $key ), esc_attr( $value ) );
		}
		if ( 'photo_folder' === $key ) {
			echo '<p class="description">' . esc_html__( 'The Drive folder with this slam\'s presenter photos (eg. the form\'s upload folder). Shown on the Photos page for this slam. Share it as "Anyone with the link" so photos can be fetched.', 'slam-presenter-profiles' ) . '</p>';
		}
		if ( 'page_url' === $key ) {
			echo '<p class="description">' . esc_html__( 'The page about this slam (venue, registration, …). The slam name on each presenter profile links here. Leave blank to use the schedule page.', 'slam-presenter-profiles' ) . '</p>';
		}
		if ( 'schedule_url' === $key ) {
			echo '<p class="description">' . esc_html__( 'Profiles link here for the full timetable. Leave blank to use the published page that contains [slam_timetable] for this event.', 'slam-presenter-profiles' ) . '</p>';
		}
		if ( 'label' === $key ) {
			echo '<p class="description">' . esc_html__( 'Optional. Shown above the presenter name, eg. "KERALA SCIENCE SLAM – NOV 7 – MAHARAJAS COLLEGE". Leave blank to build it from name, date and venue.', 'slam-presenter-profiles' ) . '</p>';
		}
	}

	public static function render_add_fields() {
		wp_nonce_field( 'ssp_event_fields', 'ssp_event_nonce' );
		foreach ( self::event_fields() as $key => $label ) {
			echo '<div class="form-field">';
			printf( '<label for="ssp_%s">%s</label>', esc_attr( $key ), esc_html( $label ) );
			self::field_input( $key, '' );
			echo '</div>';
		}
		echo '<div class="form-field"><label for="ssp_slots">' . esc_html__( 'Talk slots', 'slam-presenter-profiles' ) . '</label>';
		echo '<input type="number" name="ssp_slots" id="ssp_slots" min="0" max="200" value="24" class="small-text">';
		echo '<p class="description">' . esc_html__( 'Number of talks in the timetable. Empty slots are created; fill them in the timetable builder.', 'slam-presenter-profiles' ) . '</p></div>';
	}

	public static function render_edit_fields( $term ) {
		wp_nonce_field( 'ssp_event_fields', 'ssp_event_nonce' );
		$meta = self::get_event_meta( $term->term_id );
		foreach ( self::event_fields() as $key => $label ) {
			printf( '<tr class="form-field"><th scope="row"><label for="ssp_%s">%s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
			self::field_input( $key, $meta[ $key ] );
			echo '</td></tr>';
		}
		$items  = SSP_Timetable::get_schedule( $term->term_id )['items'];
		$slots  = count( array_filter( $items, function ( $i ) { return 'talk' === $i['type']; } ) );
		$filled = count( SSP_Timetable::get_talk_ids( $term->term_id ) );
		printf(
			'<tr class="form-field"><th scope="row"><label for="ssp_slots">%s</label></th><td><input type="number" name="ssp_slots" id="ssp_slots" min="0" max="200" value="%d" class="small-text"><p class="description">%s</p></td></tr>',
			esc_html__( 'Talk slots', 'slam-presenter-profiles' ),
			(int) $slots,
			/* translators: %d: filled slots */
			esc_html( sprintf( __( '%d filled. A higher number adds empty slots after the last talk; a lower number removes empty slots from the end (filled slots are kept).', 'slam-presenter-profiles' ), $filled ) )
		);
		printf(
			'<tr class="form-field"><th scope="row">%s</th><td><a class="button" href="%s">%s</a><p class="description"><code>[slam_timetable event="%s"]</code> <code>[slam_presenters event="%s"]</code></p></td></tr>',
			esc_html__( 'Timetable', 'slam-presenter-profiles' ),
			esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&event=' . $term->term_id ) ),
			esc_html__( 'Open timetable builder', 'slam-presenter-profiles' ),
			esc_attr( $term->slug ),
			esc_attr( $term->slug )
		);
	}

	public static function save_term_fields( $term_id ) {
		if ( ! isset( $_POST['ssp_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ssp_event_nonce'] ), 'ssp_event_fields' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) || empty( $_POST['ssp_event'] ) ) {
			return;
		}
		$input = wp_unslash( (array) $_POST['ssp_event'] );
		foreach ( array_keys( self::event_fields() ) as $key ) {
			$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
			update_term_meta( $term_id, 'ssp_' . $key, $value );
		}
		if ( isset( $_POST['ssp_slots'] ) && '' !== $_POST['ssp_slots'] ) {
			$schedule          = SSP_Timetable::get_schedule( $term_id );
			$schedule['items'] = SSP_Timetable::resize_slots( $schedule['items'], min( 200, absint( $_POST['ssp_slots'] ) ) );
			SSP_Timetable::save_schedule( $term_id, $schedule );
		}
	}

	public static function term_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'description' === $key ) {
				continue;
			}
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['ssp_edition']   = __( 'Edition', 'slam-presenter-profiles' );
				$new['ssp_type']      = __( 'Type', 'slam-presenter-profiles' );
				$new['ssp_date']      = __( 'Date', 'slam-presenter-profiles' );
				$new['ssp_timetable'] = __( 'Timetable', 'slam-presenter-profiles' );
			}
		}
		return $new;
	}

	public static function term_column_content( $content, $column, $term_id ) {
		$meta = self::get_event_meta( $term_id );
		switch ( $column ) {
			case 'ssp_edition':
				$edition = SSP_Editions::event_edition( $term_id );
				return $edition ? esc_html( get_term( $edition )->name ) : '—';
			case 'ssp_type':
				return 'final' === $meta['event_type'] ? esc_html__( 'Final', 'slam-presenter-profiles' ) : esc_html__( 'Regional', 'slam-presenter-profiles' );
			case 'ssp_date':
				return esc_html( $meta['event_date'] );
			case 'ssp_timetable':
				$count = count( SSP_Timetable::get_talk_ids( $term_id ) );
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&event=' . $term_id ) ),
					/* translators: %d: number of talks in timetable */
					esc_html( sprintf( _n( '%d talk scheduled', '%d talks scheduled', $count, 'slam-presenter-profiles' ), $count ) )
				);
		}
		return $content;
	}
}
