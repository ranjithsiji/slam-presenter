<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editions: a year's set of slams (regional slams + final), eg. "Kerala Science Slam 2026".
 *
 * Each slam event stores its edition as term meta `ssp_edition`. Admin screens work on one edition at a
 * time (the "working edition", remembered per user), so next year's presenters and photos do not mix
 * with this year's. The public website is not filtered: pages and shortcodes are per slam event.
 */
class SSP_Editions {

	const TAXONOMY = 'slam_edition';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate' ) );
		add_action( 'created_' . SSP_TAXONOMY, array( __CLASS__, 'assign_new_event' ), 20 );
		add_action( 'admin_post_ssp_set_edition', array( __CLASS__, 'handle_switch' ) );
		add_action( 'all_admin_notices', array( __CLASS__, 'render_switcher' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_list' ) );
		add_filter( 'get_terms_args', array( __CLASS__, 'filter_event_screen' ), 10, 2 );
		add_filter( 'views_edit-' . SSP_POST_TYPE, array( __CLASS__, 'slam_views' ) );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( __CLASS__, 'column_content' ), 10, 3 );
		add_filter( self::TAXONOMY . '_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_ssp_default_edition', array( __CLASS__, 'handle_default' ) );
		add_action( self::TAXONOMY . '_pre_add_form', array( __CLASS__, 'intro' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_setup' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_setup' ) );
		add_filter( 'wp_insert_term_duplicate_term_check', array( __CLASS__, 'allow_repeat_names' ), 10, 3 );
	}

	public static function register() {
		register_taxonomy(
			self::TAXONOMY,
			SSP_POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Editions', 'slam-presenter-profiles' ),
					'singular_name' => __( 'Edition', 'slam-presenter-profiles' ),
					'add_new_item'  => __( 'Add New Edition', 'slam-presenter-profiles' ),
					'edit_item'     => __( 'Edit Edition', 'slam-presenter-profiles' ),
					'menu_name'     => __( 'Editions', 'slam-presenter-profiles' ),
					'search_items'  => __( 'Search Editions', 'slam-presenter-profiles' ),
					'not_found'     => __( 'No editions yet.', 'slam-presenter-profiles' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => false, // Presenters get their edition through their slam events.
				'hierarchical'       => false,
				'rewrite'            => false,
			)
		);
	}

	/**
	 * @return WP_Term[] Newest first.
	 */
	public static function all() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'DESC',
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}

	public static function exists( $id ) {
		return $id && term_exists( (int) $id, self::TAXONOMY );
	}

	/**
	 * The edition admin screens work on: the user's choice, else the default from Settings.
	 *
	 * @return int Edition term ID, or 0 for "all editions".
	 */
	public static function current() {
		$choice = get_user_meta( get_current_user_id(), 'ssp_edition', true );
		if ( 'all' === $choice ) {
			return 0;
		}
		if ( self::exists( $choice ) ) {
			return (int) $choice;
		}
		$default = (int) get_option( 'ssp_current_edition' );
		return self::exists( $default ) ? $default : 0;
	}

	public static function event_edition( $term_id ) {
		$edition = (int) get_term_meta( $term_id, 'ssp_edition', true );
		return self::exists( $edition ) ? $edition : 0;
	}

	/**
	 * Slam events of an edition. Events without a valid edition count everywhere, so nothing goes missing.
	 */
	public static function event_ids( $edition ) {
		$ids = array();
		foreach ( get_terms( array( 'taxonomy' => SSP_TAXONOMY, 'hide_empty' => false, 'fields' => 'ids' ) ) as $term_id ) {
			$own = self::event_edition( $term_id );
			if ( ! $edition || ! $own || $own === (int) $edition ) {
				$ids[] = (int) $term_id;
			}
		}
		return $ids;
	}

	/**
	 * Limit a presenter query to the working edition: presenters in its slams, plus those in no slam yet.
	 */
	public static function scope_query( $args, $edition = null ) {
		$edition = null === $edition ? self::current() : $edition;
		if ( ! $edition ) {
			return $args;
		}
		$args['tax_query'] = array(
			'relation' => 'OR',
			array(
				'taxonomy' => SSP_TAXONOMY,
				'terms'    => self::event_ids( $edition ) ? self::event_ids( $edition ) : array( 0 ),
			),
			array(
				'taxonomy' => SSP_TAXONOMY,
				'operator' => 'NOT EXISTS',
			),
		);
		return $args;
	}

	/**
	 * Put events into editions by year ("Kerala Science Slam 2026") the first time the plugin runs with editions.
	 */
	public static function migrate() {
		if ( get_option( 'ssp_editions_migrated' ) ) {
			return;
		}
		foreach ( get_terms( array( 'taxonomy' => SSP_TAXONOMY, 'hide_empty' => false ) ) as $event ) {
			if ( self::event_edition( $event->term_id ) ) {
				continue;
			}
			$date = get_term_meta( $event->term_id, 'ssp_event_date', true );
			$year = $date ? substr( $date, 0, 4 ) : gmdate( 'Y' );
			update_term_meta( $event->term_id, 'ssp_edition', self::ensure( $year ) );
		}
		if ( ! self::exists( get_option( 'ssp_current_edition' ) ) ) {
			$all = self::all();
			if ( $all ) {
				update_option( 'ssp_current_edition', $all[0]->term_id );
			}
		}
		update_option( 'ssp_editions_migrated', 1 );
	}

	private static function ensure( $year ) {
		/* translators: %s: year */
		$name = sprintf( __( 'Kerala Science Slam %s', 'slam-presenter-profiles' ), $year );
		$term = term_exists( $name, self::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( $name, self::TAXONOMY, array( 'slug' => 'kss-' . $year ) );
		}
		return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	}

	/**
	 * New slam events (import, Add New Timetable, Slam Events screen) join the working edition unless one was chosen.
	 */
	public static function assign_new_event( $term_id ) {
		if ( ! self::event_edition( $term_id ) && self::current() ) {
			update_term_meta( $term_id, 'ssp_edition', self::current() );
		}
	}

	public static function handle_switch() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_set_edition' );
		$edition = isset( $_GET['edition'] ) ? sanitize_key( $_GET['edition'] ) : '';
		update_user_meta( get_current_user_id(), 'ssp_edition', 'all' === $edition ? 'all' : absint( $edition ) );
		$back = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : '';
		// The event shown before may belong to another edition.
		$back = remove_query_arg( array( 'event', 'ssp_status_changed', 'created', 'unmatched', SSP_TAXONOMY ), $back ? $back : admin_url( 'edit.php?post_type=' . SSP_POST_TYPE ) );
		wp_safe_redirect( $back );
		exit;
	}

	private static function on_our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || SSP_POST_TYPE !== $screen->post_type ) {
			return false;
		}
		if ( 'edit-tags' === $screen->base ) {
			return SSP_TAXONOMY === $screen->taxonomy;
		}
		return in_array( $screen->base, array( 'edit', SSP_POST_TYPE . '_page_ssp-table', SSP_POST_TYPE . '_page_ssp-timetable', SSP_POST_TYPE . '_page_ssp-photos', SSP_POST_TYPE . '_page_ssp-import' ), true );
	}

	/**
	 * "Working on: <edition>" bar at the top of the presenter, timetable, photo and import screens.
	 */
	public static function render_switcher() {
		if ( ! self::on_our_screen() ) {
			return;
		}
		$editions = self::all();
		if ( ! $editions ) {
			return;
		}
		$current = self::current();
		$here    = set_url_scheme( ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) );
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-edition-bar">
			<input type="hidden" name="action" value="ssp_set_edition">
			<input type="hidden" name="back" value="<?php echo esc_url( $here ); ?>">
			<?php wp_nonce_field( 'ssp_set_edition', '_wpnonce', false ); ?>
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
			<label for="ssp-edition-select"><?php esc_html_e( 'Working on', 'slam-presenter-profiles' ); ?></label>
			<select id="ssp-edition-select" name="edition" onchange="this.form.submit()">
				<?php foreach ( $editions as $edition ) : ?>
					<option value="<?php echo (int) $edition->term_id; ?>" <?php selected( $current, $edition->term_id ); ?>><?php echo esc_html( $edition->name ); ?></option>
				<?php endforeach; ?>
				<option value="all" <?php selected( $current, 0 ); ?>><?php esc_html_e( 'All editions', 'slam-presenter-profiles' ); ?></option>
			</select>
			<noscript><button class="button"><?php esc_html_e( 'Switch', 'slam-presenter-profiles' ); ?></button></noscript>
			<span class="ssp-muted">
				<?php
				$events = SSP_Post_Types::get_events();
				/* translators: %s: slam names */
				echo esc_html( $events ? sprintf( __( 'Slams: %s', 'slam-presenter-profiles' ), implode( ', ', wp_list_pluck( $events, 'name' ) ) ) : __( 'No slams in this edition yet.', 'slam-presenter-profiles' ) );
				?>
			</span>
			<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . SSP_POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage editions', 'slam-presenter-profiles' ); ?></a>
		</form>
		<?php
	}

	/**
	 * The WordPress presenter list follows the working edition too (unless filtered by a slam).
	 */
	public static function filter_admin_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || SSP_POST_TYPE !== $query->get( 'post_type' ) || ! self::on_our_screen() ) {
			return;
		}
		if ( $query->get( SSP_TAXONOMY ) ) {
			return;
		}
		if ( ! empty( $_GET['ssp_no_slam'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$query->set( 'tax_query', array( array( 'taxonomy' => SSP_TAXONOMY, 'operator' => 'NOT EXISTS' ) ) );
			return;
		}
		$scoped = self::scope_query( array() );
		if ( isset( $scoped['tax_query'] ) ) {
			$query->set( 'tax_query', $scoped['tax_query'] );
		}
	}

	/**
	 * Slam Events screen lists the working edition's slams.
	 */
	public static function filter_event_screen( $args, $taxonomies ) {
		if ( ! is_admin() || ! in_array( SSP_TAXONOMY, (array) $taxonomies, true ) || ! empty( $args['include'] ) || ! self::current() ) {
			return $args;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-tags' !== $screen->base || SSP_TAXONOMY !== $screen->taxonomy || doing_filter( 'get_terms_args' ) > 1 ) {
			return $args;
		}
		remove_filter( 'get_terms_args', array( __CLASS__, 'filter_event_screen' ), 10 );
		$ids = self::event_ids( self::current() );
		add_filter( 'get_terms_args', array( __CLASS__, 'filter_event_screen' ), 10, 2 );
		$args['include'] = $ids ? $ids : array( -1 );
		return $args;
	}

	/**
	 * All Presenters: one link per slam of the working edition, with its number of presenters.
	 */
	public static function slam_views( $views ) {
		$base    = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE );
		$current = isset( $_GET[ SSP_TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ SSP_TAXONOMY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( SSP_Post_Types::get_events() as $event ) {
			$count = count( SSP_Presenter::get_event_presenter_ids( $event->term_id ) );
			$views[ 'ssp-slam-' . $event->term_id ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( SSP_TAXONOMY, $event->slug, $base ) ),
				$current === $event->slug ? ' class="current" aria-current="page"' : '',
				esc_html( $event->name ),
				$count
			);
		}
		$loose = get_posts(
			array(
				'post_type'      => SSP_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => SSP_TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		if ( $loose ) {
			$views['ssp-slam-none'] = sprintf( '<a href="%s">%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'ssp_no_slam', 1, $base ) ), esc_html__( 'Not in a slam', 'slam-presenter-profiles' ), count( $loose ) );
		}
		return $views;
	}

	public static function columns( $columns ) {
		unset( $columns['description'], $columns['posts'], $columns['slug'] );
		$columns['ssp_slams']      = __( 'Slams', 'slam-presenter-profiles' );
		$columns['ssp_presenters'] = __( 'Presenters', 'slam-presenter-profiles' );
		$columns['ssp_dates']      = __( 'Dates', 'slam-presenter-profiles' );
		return $columns;
	}

	private static function events_of( $edition ) {
		$events = array();
		foreach ( get_terms( array( 'taxonomy' => SSP_TAXONOMY, 'hide_empty' => false ) ) as $event ) {
			if ( self::event_edition( $event->term_id ) === (int) $edition ) {
				$events[] = $event;
			}
		}
		return $events;
	}

	public static function column_content( $content, $column, $term_id ) {
		$events = self::events_of( $term_id );
		switch ( $column ) {
			case 'ssp_slams':
				if ( ! $events ) {
					return '<span class="ssp-muted">' . esc_html__( 'No slams yet', 'slam-presenter-profiles' ) . '</span>';
				}
				$out = array();
				foreach ( $events as $event ) {
					$final = 'final' === SSP_Post_Types::get_event_meta( $event->term_id )['event_type'];
					$out[] = esc_html( $event->name ) . ( $final ? ' <span class="ssp-type-badge is-final">' . esc_html__( 'Final', 'slam-presenter-profiles' ) . '</span>' : '' );
				}
				return implode( ', ', $out );
			case 'ssp_presenters':
				$ids = array();
				foreach ( $events as $event ) {
					$ids = array_merge( $ids, SSP_Presenter::get_event_presenter_ids( $event->term_id ) );
				}
				return (string) count( array_unique( $ids ) );
			case 'ssp_dates':
				$dates = array_filter(
					array_map(
						function ( $e ) {
							return SSP_Post_Types::get_event_meta( $e->term_id )['event_date'];
						},
						$events
					)
				);
				if ( ! $dates ) {
					return '—';
				}
				sort( $dates );
				$first = date_i18n( get_option( 'date_format' ), strtotime( reset( $dates ) ) );
				$last  = date_i18n( get_option( 'date_format' ), strtotime( end( $dates ) ) );
				return esc_html( $first === $last ? $first : $first . ' – ' . $last );
		}
		return $content;
	}

	public static function row_actions( $actions, $term ) {
		$switch = wp_nonce_url( admin_url( 'admin-post.php?action=ssp_set_edition&edition=' . $term->term_id . '&back=' . rawurlencode( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable' ) ) ), 'ssp_set_edition' );
		$new    = array( 'ssp_work' => '<a href="' . esc_url( $switch ) . '">' . esc_html__( 'Work on this edition', 'slam-presenter-profiles' ) . '</a>' );
		if ( (int) get_option( 'ssp_current_edition' ) === (int) $term->term_id ) {
			$new['ssp_default'] = '<strong>' . esc_html__( 'Default edition', 'slam-presenter-profiles' ) . '</strong>';
		} elseif ( current_user_can( 'manage_options' ) ) {
			$new['ssp_default'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ssp_default_edition&edition=' . $term->term_id ), 'ssp_default_edition' ) ) . '">' . esc_html__( 'Make default', 'slam-presenter-profiles' ) . '</a>';
		}
		return array_merge( $new, $actions );
	}

	public static function handle_default() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_default_edition' );
		$edition = isset( $_GET['edition'] ) ? absint( $_GET['edition'] ) : 0;
		if ( self::exists( $edition ) ) {
			update_option( 'ssp_current_edition', $edition );
		}
		wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . SSP_POST_TYPE ) );
		exit;
	}

	/**
	 * Slams may repeat across editions ("Kollam" every year); their slugs stay unique.
	 */
	public static function allow_repeat_names( $duplicate, $term, $taxonomy ) {
		return SSP_TAXONOMY === $taxonomy ? null : $duplicate;
	}

	private static function year( $edition ) {
		$term = get_term( $edition, self::TAXONOMY );
		return $term && ! is_wp_error( $term ) && preg_match( '/(\d{4})/', $term->name . ' ' . $term->slug, $m ) ? $m[1] : gmdate( 'Y' );
	}

	/**
	 * Edition setup on its edit screen: one card per regional slam and one for the final.
	 */
	public static function render_setup( $edition ) {
		wp_nonce_field( 'ssp_edition_setup', 'ssp_edition_nonce' );
		$regional = array();
		$final    = null;
		foreach ( self::events_of( $edition->term_id ) as $event ) {
			$meta = SSP_Post_Types::get_event_meta( $event->term_id );
			$row  = array(
				'id'     => $event->term_id,
				'name'   => $event->name,
				'date'   => $meta['event_date'],
				'venue'  => $meta['venue'],
				'city'   => $meta['city'],
				'status' => $meta['status'],
				'slots'  => count( array_filter( SSP_Timetable::get_schedule( $event->term_id )['items'], function ( $i ) { return 'talk' === $i['type']; } ) ),
			);
			if ( 'final' === $meta['event_type'] && ! $final ) {
				$final = $row;
			} else {
				$regional[] = $row;
			}
		}
		usort(
			$regional,
			function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);
		$blank = array( 'id' => 0, 'name' => '', 'date' => '', 'venue' => '', 'city' => '', 'slots' => 24, 'status' => 'draft' );
		if ( ! $final ) {
			// Saving the edition creates its final as a draft (hidden from visitors until published).
			$final = array_merge( $blank, array( 'name' => __( 'Final', 'slam-presenter-profiles' ) ) );
		}
		$timetable = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&event=' );

		$card = function ( $r, $index, $type ) use ( $timetable ) {
			$name     = 'ssp_slams[' . $index . ']';
			$is_final = 'final' === $type;
			ob_start();
			?>
			<div class="ssp-slam-card<?php echo $is_final ? ' is-final' : ''; ?><?php echo $r['id'] ? ' is-saved' : ' is-new'; ?>">
				<div class="ssp-slam-card-head">
					<span class="ssp-slam-card-title"><?php echo $is_final ? esc_html__( 'Final slam', 'slam-presenter-profiles' ) : esc_html__( 'Regional slam', 'slam-presenter-profiles' ) . ' <span class="ssp-slam-no"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<?php if ( $r['id'] ) : ?>
						<span class="ssp-status-badge<?php echo 'publish' === $r['status'] ? ' is-live' : ''; ?>"><?php echo 'publish' === $r['status'] ? esc_html__( 'Published', 'slam-presenter-profiles' ) : esc_html__( 'Draft', 'slam-presenter-profiles' ); ?></span>
						<a class="ssp-slam-card-link" href="<?php echo esc_url( $timetable . $r['id'] ); ?>"><?php esc_html_e( 'Open timetable →', 'slam-presenter-profiles' ); ?></a>
					<?php else : ?>
						<span class="ssp-slam-card-new"><?php esc_html_e( 'New: created as a draft when you save', 'slam-presenter-profiles' ); ?></span>
						<?php if ( ! $is_final ) : ?>
							<button type="button" class="ssp-slam-remove" aria-label="<?php esc_attr_e( 'Remove this slam', 'slam-presenter-profiles' ); ?>">×</button>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo (int) $r['id']; ?>">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[type]" value="<?php echo esc_attr( $type ); ?>">
				<div class="ssp-slam-fields">
					<label class="ssp-f-name"><span><?php esc_html_e( 'Name', 'slam-presenter-profiles' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $r['name'] ); ?>" placeholder="<?php echo $is_final ? esc_attr__( 'Final', 'slam-presenter-profiles' ) : esc_attr__( 'eg. Kollam', 'slam-presenter-profiles' ); ?>"></label>
					<label class="ssp-f-date"><span><?php esc_html_e( 'Date', 'slam-presenter-profiles' ); ?></span>
						<input type="date" name="<?php echo esc_attr( $name ); ?>[date]" value="<?php echo esc_attr( $r['date'] ); ?>"></label>
					<label class="ssp-f-slots"><span><?php esc_html_e( 'Talk slots', 'slam-presenter-profiles' ); ?></span>
						<input type="number" min="0" max="200" name="<?php echo esc_attr( $name ); ?>[slots]" value="<?php echo (int) $r['slots']; ?>"></label>
					<label class="ssp-f-venue"><span><?php esc_html_e( 'Venue', 'slam-presenter-profiles' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $name ); ?>[venue]" value="<?php echo esc_attr( $r['venue'] ); ?>" placeholder="<?php esc_attr_e( 'eg. Fatima Mata National College', 'slam-presenter-profiles' ); ?>"></label>
					<label class="ssp-f-city"><span><?php esc_html_e( 'City', 'slam-presenter-profiles' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $name ); ?>[city]" value="<?php echo esc_attr( $r['city'] ); ?>" placeholder="<?php esc_attr_e( 'eg. Kollam', 'slam-presenter-profiles' ); ?>"></label>
				</div>
			</div>
			<?php
			return ob_get_clean();
		};
		?>
		<tr class="ssp-edition-setup">
			<td colspan="2">
				<div class="ssp-setup-head">
					<h2><?php esc_html_e( 'Slams in this edition', 'slam-presenter-profiles' ); ?></h2>
					<span class="ssp-setup-count"></span>
				</div>
				<p class="description"><?php esc_html_e( 'Set up the regional slams and the final. Saving creates new slams as drafts with an empty timetable of that many talk slots, and updates the names, dates and venues of existing ones. Slams are never deleted here; fewer talk slots only removes empty ones.', 'slam-presenter-profiles' ); ?></p>

				<h3 class="ssp-setup-group"><?php esc_html_e( 'Regional slams', 'slam-presenter-profiles' ); ?></h3>
				<div class="ssp-setup-regional">
					<?php
					foreach ( $regional as $i => $r ) {
						echo $card( $r, $i, 'regional' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the closure.
					}
					?>
				</div>
				<p><button type="button" class="button ssp-slam-add"><?php esc_html_e( '+ Add regional slam', 'slam-presenter-profiles' ); ?></button></p>

				<h3 class="ssp-setup-group"><?php esc_html_e( 'Final', 'slam-presenter-profiles' ); ?></h3>
				<?php echo $card( $final, 'final', 'final' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

				<template id="ssp-setup-template"><?php echo $card( $blank, '__i__', 'regional' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
				<script>
					(function () {
						var list = document.querySelector('.ssp-setup-regional');
						var tpl = document.getElementById('ssp-setup-template').innerHTML;
						var count = document.querySelector('.ssp-setup-count');
						var next = list.children.length + 100;
						function renumber() {
							var cards = list.querySelectorAll('.ssp-slam-card');
							cards.forEach(function (c, i) {
								c.querySelector('.ssp-slam-no').textContent = i + 1;
							});
							count.textContent = <?php echo wp_json_encode( __( '%d regional slams + final', 'slam-presenter-profiles' ) ); ?>.replace('%d', cards.length);
						}
						document.querySelector('.ssp-slam-add').addEventListener('click', function () {
							list.insertAdjacentHTML('beforeend', tpl.replace(/__i__/g, next++));
							renumber();
							list.lastElementChild.querySelector('input[type=text]').focus();
						});
						list.addEventListener('click', function (e) {
							if (e.target.classList.contains('ssp-slam-remove')) {
								e.target.closest('.ssp-slam-card').remove();
								renumber();
							}
						});
						// A new edition starts with four empty regional slams.
						if (!list.children.length) {
							for (var i = 0; i < 4; i++) {
								list.insertAdjacentHTML('beforeend', tpl.replace(/__i__/g, next++));
							}
						}
						renumber();
					})();
				</script>
			</td>
		</tr>
		<?php
	}

	public static function save_setup( $edition_id ) {
		if ( ! isset( $_POST['ssp_edition_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ssp_edition_nonce'] ), 'ssp_edition_setup' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$rows  = isset( $_POST['ssp_slams'] ) ? wp_unslash( (array) $_POST['ssp_slams'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		$year  = self::year( $edition_id );
		$order = 0;
		foreach ( $rows as $row ) {
			$name = sanitize_text_field( isset( $row['name'] ) ? $row['name'] : '' );
			if ( '' === $name ) {
				continue;
			}
			$order++;
			$id   = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$type = isset( $row['type'] ) && 'final' === $row['type'] ? 'final' : 'regional';
			if ( $id && term_exists( $id, SSP_TAXONOMY ) ) {
				wp_update_term( $id, SSP_TAXONOMY, array( 'name' => $name ) );
			} else {
				$slug = sanitize_title( $name );
				if ( false !== strpos( $slug, '%' ) || '' === $slug ) {
					$slug = 'slam';
				}
				if ( get_term_by( 'slug', $slug, SSP_TAXONOMY ) ) {
					$slug .= '-' . $year;
				}
				$term = wp_insert_term( $name, SSP_TAXONOMY, array( 'slug' => wp_unique_term_slug( $slug, (object) array( 'taxonomy' => SSP_TAXONOMY, 'parent' => 0 ) ) ) );
				if ( is_wp_error( $term ) ) {
					continue;
				}
				$id = (int) $term['term_id'];
				update_term_meta( $id, 'ssp_status', 'draft' );
			}
			update_term_meta( $id, 'ssp_edition', $edition_id );
			update_term_meta( $id, 'ssp_event_type', $type );
			update_term_meta( $id, 'ssp_sort_order', $order );
			foreach ( array( 'date' => 'event_date', 'venue' => 'venue', 'city' => 'city' ) as $field => $key ) {
				update_term_meta( $id, 'ssp_' . $key, sanitize_text_field( isset( $row[ $field ] ) ? $row[ $field ] : '' ) );
			}
			if ( isset( $row['slots'] ) && '' !== $row['slots'] ) {
				$schedule          = SSP_Timetable::get_schedule( $id );
				$schedule['items'] = SSP_Timetable::resize_slots( $schedule['items'], min( 200, absint( $row['slots'] ) ) );
				SSP_Timetable::save_schedule( $id, $schedule );
			}
		}
	}

	public static function intro() {
		echo '<p class="description">' . esc_html__( 'An edition is one year\'s set of slams: the regional slams and the final, eg. "Kerala Science Slam 2027". Add the new edition here, then click its name to set up its regional slams and final (dates, venues, talk slots).', 'slam-presenter-profiles' ) . '</p>';
	}
}
