<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens: presenter meta boxes, list columns, the Science Slam submenu pages.
 */
class SSP_Admin {

	const CAP = 'edit_posts';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'add_meta_boxes_' . SSP_POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . SSP_POST_TYPE, array( __CLASS__, 'save_presenter' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . SSP_POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . SSP_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'event_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_search_meta' ) );
		add_filter( 'bulk_actions-edit-' . SSP_POST_TYPE, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . SSP_POST_TYPE, array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );
	}

	public static function register_menu() {
		$parent = 'edit.php?post_type=' . SSP_POST_TYPE;

		add_submenu_page( $parent, __( 'Timetable Builder', 'slam-presenter-profiles' ), __( 'Timetable', 'slam-presenter-profiles' ), self::CAP, 'ssp-timetable', array( __CLASS__, 'render_timetable_page' ) );
		add_submenu_page( $parent, __( 'Import Presenters', 'slam-presenter-profiles' ), __( 'Import / Export', 'slam-presenter-profiles' ), 'manage_options', 'ssp-import', array( 'SSP_Import', 'render_page' ) );
		add_submenu_page( $parent, __( 'Presenter Photos', 'slam-presenter-profiles' ), __( 'Photos', 'slam-presenter-profiles' ), self::CAP, 'ssp-photos', array( 'SSP_Photos', 'render_page' ) );
		add_submenu_page( $parent, __( 'Science Slam Settings', 'slam-presenter-profiles' ), __( 'Settings', 'slam-presenter-profiles' ), 'manage_options', 'ssp-settings', array( __CLASS__, 'render_settings_page' ) );
	}

	public static function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || SSP_POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'ssp-admin', SSP_PLUGIN_URL . 'assets/css/admin.css', array(), SSP_VERSION );

		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			wp_enqueue_script( 'ssp-photo-cropper', SSP_PLUGIN_URL . 'assets/js/photo-cropper.js', array(), SSP_VERSION, true );
			wp_enqueue_script( 'ssp-presenter-edit', SSP_PLUGIN_URL . 'assets/js/presenter-edit.js', array( 'jquery', 'jquery-ui-sortable', 'ssp-photo-cropper' ), SSP_VERSION, true );
			wp_localize_script(
				'ssp-presenter-edit',
				'sspEdit',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ssp_photos' ),
					'i18n'    => array(
						'fetching' => __( 'Fetching photo from Google Drive…', 'slam-presenter-profiles' ),
					),
					'cropper' => self::cropper_labels(),
				)
			);
		}

		if ( false !== strpos( $hook, 'ssp-timetable' ) ) {
			SSP_Admin::enqueue_timetable_builder();
		}

		if ( false !== strpos( $hook, 'ssp-photos' ) ) {
			wp_enqueue_script( 'ssp-photo-cropper', SSP_PLUGIN_URL . 'assets/js/photo-cropper.js', array(), SSP_VERSION, true );
			wp_enqueue_script( 'ssp-photos', SSP_PLUGIN_URL . 'assets/js/photos.js', array( 'jquery', 'ssp-photo-cropper' ), SSP_VERSION, true );
			wp_localize_script(
				'ssp-photos',
				'sspPhotos',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ssp_photos' ),
					'cropper' => self::cropper_labels(),
					'i18n'    => array(
						'save'   => __( 'Save', 'slam-presenter-profiles' ),
						'cancel' => __( 'Cancel', 'slam-presenter-profiles' ),
						'saving' => __( 'Saving…', 'slam-presenter-profiles' ),
						'saved'  => __( 'Saved', 'slam-presenter-profiles' ),
					),
				)
			);
		}
	}

	private static function enqueue_timetable_builder() {
		// Only the builder for one timetable needs the script; the list and the new form do not.
		$term_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $term_id || isset( $_GET['new'] ) || ! term_exists( $term_id, SSP_TAXONOMY ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$event_ids = SSP_Presenter::get_event_presenter_ids( $term_id );
		// Picker and "Select presenters" offer the edition of the timetable being edited.
		$all_ids   = get_posts(
			SSP_Editions::scope_query(
				array(
					'post_type'      => SSP_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				),
				SSP_Editions::event_edition( $term_id ) ? SSP_Editions::event_edition( $term_id ) : null
			)
		);
		$presenters = array();
		foreach ( $all_ids as $id ) {
			$thumb        = get_the_post_thumbnail_url( $id, 'ssp-thumb' );
			$terms        = wp_get_post_terms( $id, SSP_TAXONOMY );
			$crop         = SSP_Photos::crop_data( $id );
			if ( $crop ) {
				$crop['src'] = wp_get_attachment_image_url( get_post_thumbnail_id( $id ), 'medium' );
			}
			$presenters[] = array(
				'id'      => $id,
				'name'    => SSP_Presenter::display_name( $id ),
				'nameEn'  => SSP_Presenter::get( $id, 'name_en' ),
				'title'   => SSP_Presenter::get( $id, 'talk_title' ),
				'thumb'   => $thumb ? $thumb : '',
				'inEvent' => in_array( $id, $event_ids, true ),
				'events'   => is_wp_error( $terms ) ? '' : implode( ', ', wp_list_pluck( $terms, 'name' ) ),
				'eventIds' => is_wp_error( $terms ) ? array() : array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ),
				'inst'     => SSP_Presenter::get( $id, 'institution' ),
				'crop'     => $crop,
				'editUrl' => get_edit_post_link( $id, 'raw' ),
			);
		}

		$events = array();
		$tt_edition = SSP_Editions::event_edition( $term_id );
		foreach ( SSP_Post_Types::get_events( $tt_edition ? $tt_edition : 'current' ) as $ev ) {
			$meta     = SSP_Post_Types::get_event_meta( $ev->term_id );
			$events[] = array(
				'id'    => $ev->term_id,
				'name'  => $ev->name,
				'type'  => $meta['event_type'],
				'date'  => $meta['event_date'] ? date_i18n( 'M j', strtotime( $meta['event_date'] ) ) : '',
				'order' => SSP_Timetable::get_talk_ids( $ev->term_id ),
			);
		}

		wp_enqueue_script( 'ssp-photo-cropper', SSP_PLUGIN_URL . 'assets/js/photo-cropper.js', array(), SSP_VERSION, true );
		wp_enqueue_script( 'ssp-timetable', SSP_PLUGIN_URL . 'assets/js/timetable-builder.js', array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'ssp-photo-cropper' ), SSP_VERSION, true );
		wp_localize_script(
			'ssp-timetable',
			'sspTimetable',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ssp_timetable' ),
				'event'       => $term_id,
				'schedule'    => SSP_Timetable::get_schedule( $term_id ),
				'presenters'  => $presenters,
				'events'      => $events,
				'isFinal'     => 'final' === SSP_Post_Types::get_event_meta( $term_id )['event_type'],
				'talkMinutes' => (int) SSP_Settings::get( 'talk_minutes' ),
				'i18n'        => array(
					'session'      => __( 'Session', 'slam-presenter-profiles' ),
					'talk'         => __( 'Talk', 'slam-presenter-profiles' ),
					'break'        => __( 'Break', 'slam-presenter-profiles' ),
					'item'         => __( 'Programme', 'slam-presenter-profiles' ),
					'choose'       => __( '— Choose presenter —', 'slam-presenter-profiles' ),
					'saved'        => __( 'Timetable saved.', 'slam-presenter-profiles' ),
					'unsaved'      => __( 'Unsaved changes', 'slam-presenter-profiles' ),
					'confirmClear' => __( 'Remove every item from this timetable?', 'slam-presenter-profiles' ),
					'confirmGen'   => __( 'Replace the current timetable with a generated one?', 'slam-presenter-profiles' ),
					'unmatched'    => __( 'Could not match these lines to a presenter. Pick the presenter in the highlighted rows:', 'slam-presenter-profiles' ),
					'allIn'        => __( 'Every presenter of this event is scheduled.', 'slam-presenter-profiles' ),
					'noneInEvent'  => __( 'No presenters in this event yet. Click a talk slot and choose from "All presenters"; they join the event when you save.', 'slam-presenter-profiles' ),
					'notInEvent'   => __( 'not assigned to this event', 'slam-presenter-profiles' ),
					'fixedHelp'    => __( 'Fixed start (optional)', 'slam-presenter-profiles' ),
					'min'          => __( 'min', 'slam-presenter-profiles' ),
					'remove'       => __( 'Remove', 'slam-presenter-profiles' ),
					'notePh'       => __( 'Note (eg. 10 min talk + 2 min Q&A)', 'slam-presenter-profiles' ),
					'labelPh'      => __( 'Label', 'slam-presenter-profiles' ),
					'ends'         => __( 'Ends at', 'slam-presenter-profiles' ),
					'talks'        => __( 'talks', 'slam-presenter-profiles' ),
					'search'       => __( 'Search name, institution or title…', 'slam-presenter-profiles' ),
					'unscheduled'  => __( 'Unscheduled', 'slam-presenter-profiles' ),
					'thisEvent'    => __( 'This event', 'slam-presenter-profiles' ),
					'allPresenter' => __( 'All presenters', 'slam-presenter-profiles' ),
					'scheduledAt'  => __( 'in #%d · swap', 'slam-presenter-profiles' ),
					'noMatch'      => __( 'No presenter matches. Try "All presenters".', 'slam-presenter-profiles' ),
					'clear'        => __( 'Clear', 'slam-presenter-profiles' ),
					'swapHelp'     => __( 'Already in another slot: choosing swaps the two presenters.', 'slam-presenter-profiles' ),
					'selectTitle'  => __( 'Select presenters from the slams', 'slam-presenter-profiles' ),
					'finalTitle'   => __( 'Select finalists', 'slam-presenter-profiles' ),
					'selectHelp'   => __( 'Tick the presenters to add. Empty talk slots are filled first, then new slots are added. Save the timetable afterwards.', 'slam-presenter-profiles' ),
					'selectAll'    => __( 'Select all', 'slam-presenter-profiles' ),
					'inTimetable'  => __( 'in timetable #%d', 'slam-presenter-profiles' ),
					'addN'         => __( 'Add %d to timetable', 'slam-presenter-profiles' ),
					'nSelected'    => __( '%d selected', 'slam-presenter-profiles' ),
					'added'        => __( '%d added. Save the timetable to keep them.', 'slam-presenter-profiles' ),
					'noEvent'      => __( 'Not in any slam', 'slam-presenter-profiles' ),
					'cancel'       => __( 'Cancel', 'slam-presenter-profiles' ),
					'slotsFilled'  => __( 'Only empty talk slots are removed. Clear or remove the remaining filled slots by hand.', 'slam-presenter-profiles' ),
				),
			)
		);
	}

	public static function cropper_labels() {
		return array(
			'zoom'  => __( 'Zoom', 'slam-presenter-profiles' ),
			'reset' => __( 'Reset', 'slam-presenter-profiles' ),
			'help'  => __( 'Drag to move, scroll or use the slider to zoom. This square is what the website shows.', 'slam-presenter-profiles' ),
		);
	}

	public static function title_placeholder( $placeholder, $post ) {
		if ( SSP_POST_TYPE === $post->post_type ) {
			return __( 'Name shown on the website (Malayalam)', 'slam-presenter-profiles' );
		}
		return $placeholder;
	}

	public static function add_meta_boxes() {
		add_meta_box( 'ssp_details', __( 'Presenter & Talk', 'slam-presenter-profiles' ), array( __CLASS__, 'render_details_box' ), SSP_POST_TYPE, 'normal', 'high' );
		add_meta_box( 'ssp_links', __( 'Social & Profile Links', 'slam-presenter-profiles' ), array( __CLASS__, 'render_links_box' ), SSP_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'ssp_papers', __( 'Papers & Publications', 'slam-presenter-profiles' ), array( __CLASS__, 'render_papers_box' ), SSP_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'ssp_photo_tools', __( 'Photo & crop', 'slam-presenter-profiles' ), array( __CLASS__, 'render_photo_box' ), SSP_POST_TYPE, 'side', 'default' );
		add_meta_box( 'ssp_schedule', __( 'Timetable Slots', 'slam-presenter-profiles' ), array( __CLASS__, 'render_schedule_box' ), SSP_POST_TYPE, 'side', 'low' );
	}

	private static function field_row( $post_id, $key ) {
		$fields          = SSP_Presenter::fields();
		list( $label, $type ) = $fields[ $key ];
		$value           = SSP_Presenter::get( $post_id, $key );
		$name            = 'ssp[' . $key . ']';
		echo '<tr><th scope="row"><label for="ssp_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			printf( '<textarea name="%s" id="ssp_%s" rows="%d" class="large-text">%s</textarea>', esc_attr( $name ), esc_attr( $key ), 'abstract' === $key ? 8 : 4, esc_textarea( $value ) );
		} else {
			printf( '<input type="%s" name="%s" id="ssp_%s" value="%s" class="large-text">', esc_attr( 'url' === $type || 'email' === $type ? $type : 'text' ), esc_attr( $name ), esc_attr( $key ), esc_attr( $value ) );
		}
		if ( 'phone' === $key ) {
			echo '<p class="description">' . esc_html__( 'For organisers only.', 'slam-presenter-profiles' ) . '</p>';
		}
		if ( 'email' === $key ) {
			printf(
				'<label><input type="checkbox" name="ssp[email_public]" value="1" %s> %s</label>',
				checked( SSP_Presenter::get( $post_id, 'email_public' ), '1', false ),
				esc_html__( 'Show email on the public profile', 'slam-presenter-profiles' )
			);
		}
		echo '</td></tr>';
	}

	public static function render_details_box( $post ) {
		wp_nonce_field( 'ssp_save_presenter', 'ssp_nonce' );
		echo '<p class="description">' . esc_html__( 'The title above is the display name. The editor above is the presenter bio (About).', 'slam-presenter-profiles' ) . '</p>';
		echo '<table class="form-table ssp-form-table"><tbody>';
		foreach ( array( 'name_en', 'institution', 'designation', 'talk_title', 'summary', 'abstract', 'award', 'video_url', 'email', 'phone', 'form_no' ) as $key ) {
			self::field_row( $post->ID, $key );
		}
		echo '</tbody></table>';
	}

	public static function render_links_box( $post ) {
		$social = SSP_Presenter::get_social( $post->ID );
		echo '<div class="ssp-links-grid">';
		foreach ( SSP_Presenter::networks() as $key => $network ) {
			printf(
				'<label><span class="ssp-dot" style="background:%1$s"></span>%2$s<input type="url" name="ssp_social[%3$s]" value="%4$s" placeholder="https://"></label>',
				esc_attr( $network[1] ),
				esc_html( $network[0] ),
				esc_attr( $key ),
				esc_attr( isset( $social[ $key ] ) ? $social[ $key ] : '' )
			);
		}
		echo '</div>';
	}

	public static function render_papers_box( $post ) {
		$papers   = SSP_Presenter::get_papers( $post->ID );
		$papers[] = array(); // Template row for the JS "add" button.
		echo '<div class="ssp-papers" data-next="' . esc_attr( count( $papers ) ) . '">';
		foreach ( $papers as $i => $paper ) {
			$is_template = ( count( $papers ) - 1 === $i );
			$paper       = wp_parse_args( $paper, array_fill_keys( array( 'title', 'authors', 'venue', 'year', 'url' ), '' ) );
			$idx         = $is_template ? '__i__' : $i;
			printf( '<div class="ssp-paper%s">', $is_template ? ' ssp-paper-template' : '' );
			echo '<span class="ssp-handle dashicons dashicons-menu" title="' . esc_attr__( 'Drag to reorder', 'slam-presenter-profiles' ) . '"></span>';
			echo '<div class="ssp-paper-fields">';
			printf( '<input type="text" class="ssp-paper-title" name="ssp_papers[%s][title]" value="%s" placeholder="%s">', esc_attr( $idx ), esc_attr( $paper['title'] ), esc_attr__( 'Paper title', 'slam-presenter-profiles' ) );
			printf( '<input type="text" name="ssp_papers[%s][authors]" value="%s" placeholder="%s">', esc_attr( $idx ), esc_attr( $paper['authors'] ), esc_attr__( 'Authors', 'slam-presenter-profiles' ) );
			printf( '<input type="text" name="ssp_papers[%s][venue]" value="%s" placeholder="%s">', esc_attr( $idx ), esc_attr( $paper['venue'] ), esc_attr__( 'Journal / conference', 'slam-presenter-profiles' ) );
			printf( '<input type="text" class="ssp-paper-year" name="ssp_papers[%s][year]" value="%s" placeholder="%s">', esc_attr( $idx ), esc_attr( $paper['year'] ), esc_attr__( 'Year', 'slam-presenter-profiles' ) );
			printf( '<input type="url" name="ssp_papers[%s][url]" value="%s" placeholder="%s">', esc_attr( $idx ), esc_attr( $paper['url'] ), esc_attr__( 'DOI or link', 'slam-presenter-profiles' ) );
			echo '</div>';
			echo '<button type="button" class="button-link ssp-paper-remove" aria-label="' . esc_attr__( 'Remove paper', 'slam-presenter-profiles' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
			echo '</div>';
		}
		echo '</div>';
		echo '<p><button type="button" class="button ssp-paper-add">' . esc_html__( '+ Add paper', 'slam-presenter-profiles' ) . '</button></p>';
	}

	public static function render_photo_box( $post ) {
		$drive = SSP_Presenter::get( $post->ID, 'photo_drive_url' );
		$focus = SSP_Presenter::get( $post->ID, 'photo_focus' );
		$thumb = get_post_thumbnail_id( $post->ID );

		$crop = SSP_Photos::crop_data( $post->ID );

		echo '<div class="ssp-photo-tools">';
		printf( '<div class="ssp-cropper-host" data-crop="%s"></div>', esc_attr( $crop ? wp_json_encode( $crop ) : '' ) );
		printf( '<input type="hidden" name="ssp[photo_focus]" id="ssp_photo_focus" value="%s">', esc_attr( $focus ) );
		printf( '<input type="hidden" name="ssp[photo_zoom]" id="ssp_photo_zoom" value="%s">', esc_attr( SSP_Presenter::get( $post->ID, 'photo_zoom' ) ) );
		if ( ! $crop ) {
			echo '<p class="description ssp-cropper-empty">' . esc_html__( 'No photo yet. Fetch it from Drive or use "Set presenter photo" below.', 'slam-presenter-profiles' ) . '</p>';
		}
		echo '<p><label for="ssp_photo_drive_url">' . esc_html__( 'Google Drive link', 'slam-presenter-profiles' ) . '</label>';
		printf( '<input type="url" id="ssp_photo_drive_url" name="ssp[photo_drive_url]" value="%s" class="widefat"></p>', esc_attr( $drive ) );
		if ( $drive ) {
			printf( '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url( $drive ), esc_html__( 'Open in Drive ↗', 'slam-presenter-profiles' ) );
		}
		printf(
			'<p><button type="button" class="button ssp-fetch-photo" data-post="%d" %s>%s</button></p><p class="ssp-fetch-status"></p>',
			(int) $post->ID,
			disabled( '' === $drive || 'auto-draft' === $post->post_status, true, false ),
			esc_html__( 'Fetch photo from Drive', 'slam-presenter-profiles' )
		);
		echo '<p class="description">' . esc_html__( 'The Drive file must be shared as "Anyone with the link". Or upload with "Set presenter photo" below.', 'slam-presenter-profiles' ) . '</p>';
		echo '</div>';
	}

	public static function render_schedule_box( $post ) {
		$terms = get_the_terms( $post->ID, SSP_TAXONOMY );
		if ( ! $terms || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'Assign a Slam Event to schedule this presenter.', 'slam-presenter-profiles' ) . '</p>';
			return;
		}
		echo '<ul class="ssp-slot-list">';
		foreach ( $terms as $term ) {
			$slot = SSP_Timetable::find_slot( $term->term_id, $post->ID );
			printf(
				'<li><strong>%s</strong><br>%s <a href="%s">%s</a></li>',
				esc_html( $term->name ),
				$slot ? esc_html( '#' . $slot['no'] . ' · ' . SSP_Timetable::format_minutes( $slot['start'] ) . ' – ' . SSP_Timetable::format_minutes( $slot['end'] ) ) : esc_html__( 'Not in timetable yet.', 'slam-presenter-profiles' ),
				esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&event=' . $term->term_id ) ),
				esc_html__( 'Edit timetable', 'slam-presenter-profiles' )
			);
		}
		echo '</ul>';
	}

	public static function save_presenter( $post_id, $post ) {
		if ( ! isset( $_POST['ssp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ssp_nonce'] ), 'ssp_save_presenter' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['ssp'] ) ? wp_unslash( (array) $_POST['ssp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.
		foreach ( array_keys( SSP_Presenter::fields() ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				SSP_Presenter::set( $post_id, $key, SSP_Presenter::sanitize_field( $key, $input[ $key ] ) );
			}
		}
		SSP_Presenter::set( $post_id, 'email_public', empty( $input['email_public'] ) ? '' : '1' );

		$social = array();
		$raw    = isset( $_POST['ssp_social'] ) ? wp_unslash( (array) $_POST['ssp_social'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( array_keys( SSP_Presenter::networks() ) as $key ) {
			if ( ! empty( $raw[ $key ] ) ) {
				$social[ $key ] = esc_url_raw( trim( $raw[ $key ] ) );
			}
		}
		SSP_Presenter::set( $post_id, 'social', $social );

		$papers = isset( $_POST['ssp_papers'] ) ? wp_unslash( (array) $_POST['ssp_papers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		unset( $papers['__i__'] );
		SSP_Presenter::set( $post_id, 'papers', SSP_Presenter::sanitize_papers( $papers ) );
	}

	public static function columns( $columns ) {
		$new = array(
			'cb'          => $columns['cb'],
			'ssp_photo'   => '<span class="dashicons dashicons-format-image" title="' . esc_attr__( 'Photo', 'slam-presenter-profiles' ) . '"></span>',
			'title'       => __( 'Name', 'slam-presenter-profiles' ),
			'ssp_name_en' => __( 'English name', 'slam-presenter-profiles' ),
			'ssp_talk'    => __( 'Presentation', 'slam-presenter-profiles' ),
			'ssp_inst'    => __( 'Institution', 'slam-presenter-profiles' ),
		);
		foreach ( $columns as $key => $label ) {
			if ( ! isset( $new[ $key ] ) && 'date' !== $key ) {
				$new[ $key ] = $label;
			}
		}
		$new['ssp_slot'] = __( 'Slot', 'slam-presenter-profiles' );
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'ssp_photo':
				echo SSP_Presenter::photo_html( $post_id, 'ssp-thumb', 'ssp-list-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'ssp_name_en':
				echo esc_html( SSP_Presenter::get( $post_id, 'name_en' ) );
				break;
			case 'ssp_talk':
				echo esc_html( wp_trim_words( SSP_Presenter::get( $post_id, 'talk_title' ), 12 ) );
				break;
			case 'ssp_inst':
				echo esc_html( SSP_Presenter::get( $post_id, 'institution' ) );
				break;
			case 'ssp_slot':
				$terms = get_the_terms( $post_id, SSP_TAXONOMY );
				if ( $terms && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$slot = SSP_Timetable::find_slot( $term->term_id, $post_id );
						if ( $slot ) {
							echo '<span class="ssp-slot-badge">' . esc_html( SSP_Timetable::format_minutes( $slot['start'] ) ) . '</span> ';
						}
					}
				}
				break;
		}
	}

	public static function event_filter( $post_type ) {
		if ( SSP_POST_TYPE !== $post_type ) {
			return;
		}
		wp_dropdown_categories(
			array(
				'taxonomy'        => SSP_TAXONOMY,
				'name'            => SSP_TAXONOMY,
				'value_field'     => 'slug',
				'show_option_all' => __( 'All slam events', 'slam-presenter-profiles' ),
				'selected'        => isset( $_GET[ SSP_TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ SSP_TAXONOMY ] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				'hide_empty'      => false,
				'hierarchical'    => true,
				'include'         => wp_list_pluck( SSP_Post_Types::get_events(), 'term_id' ),
			)
		);
	}

	/**
	 * Admin search also matches English name, institution and talk title.
	 */
	public static function admin_search_meta( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || SSP_POST_TYPE !== $query->get( 'post_type' ) || '' === $query->get( 's' ) ) {
			return;
		}
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';
		$ids  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_ssp_name_en','_ssp_institution','_ssp_talk_title') AND meta_value LIKE %s",
				$like
			)
		);
		$ids  = array_merge( $ids, $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", SSP_POST_TYPE, $like ) ) );
		$query->set( 's', '' );
		$query->set( 'post__in', $ids ? array_map( 'intval', $ids ) : array( 0 ) );
	}

	public static function bulk_actions( $actions ) {
		$actions['ssp_fetch_photos'] = __( 'Fetch photos from Drive', 'slam-presenter-profiles' );
		return $actions;
	}

	public static function handle_bulk_actions( $redirect, $action, $ids ) {
		if ( 'ssp_fetch_photos' !== $action ) {
			return $redirect;
		}
		$ok     = 0;
		$failed = 0;
		foreach ( $ids as $id ) {
			$result = SSP_Photos::fetch_from_drive( (int) $id );
			is_wp_error( $result ) ? $failed++ : $ok++;
		}
		return add_query_arg(
			array(
				'ssp_fetched' => $ok,
				'ssp_failed'  => $failed,
			),
			$redirect
		);
	}

	public static function bulk_notice() {
		if ( ! isset( $_GET['ssp_fetched'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$ok     = absint( $_GET['ssp_fetched'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$failed = isset( $_GET['ssp_failed'] ) ? absint( $_GET['ssp_failed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			$failed ? 'warning' : 'success',
			/* translators: 1: photos fetched, 2: failures */
			esc_html( sprintf( __( '%1$d photo(s) fetched, %2$d failed. See Science Slam → Photos for the reasons.', 'slam-presenter-profiles' ), $ok, $failed ) )
		);
	}

	public static function render_timetable_page() {
		require SSP_PLUGIN_DIR . 'includes/views/timetable.php';
	}

	public static function render_settings_page() {
		require SSP_PLUGIN_DIR . 'includes/views/settings.php';
	}
}
