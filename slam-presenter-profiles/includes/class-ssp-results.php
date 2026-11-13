<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Results of a slam: an ordered list of presenters (eg. those selected for the final), with a heading,
 * a note and a draft/published status. Stored as term meta `ssp_result` on the slam event and shown
 * with [slam_results event="..."].
 */
class SSP_Results {

	const META = 'ssp_result';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_ssp_save_result', array( __CLASS__, 'handle_save' ) );
		add_shortcode( 'slam_results', array( __CLASS__, 'shortcode' ) );
	}

	public static function get( $term_id ) {
		$result = get_term_meta( $term_id, self::META, true );
		$result = wp_parse_args(
			is_array( $result ) ? $result : array(),
			array(
				'title'  => '',
				'note'   => '',
				'ids'    => array(),
				'status' => 'draft',
			)
		);
		$result['ids'] = array_values( array_filter( array_map( 'absint', (array) $result['ids'] ) ) );
		return $result;
	}

	/**
	 * Heading shown when none is typed, eg. "Ernakulam റീജിയണൽ സയൻസ് സ്ലാം: ഫൈനലിലേക്ക് തിരഞ്ഞെടുക്കപ്പെട്ട 6 അവതാരകർ".
	 */
	public static function default_title( $term, $count ) {
		$final = 'final' === SSP_Post_Types::get_event_meta( $term->term_id )['event_type'];
		return $final
			/* translators: 1: slam name, 2: number of presenters */
			? sprintf( __( '%1$s: വിജയികൾ', 'slam-presenter-profiles' ), $term->name )
			/* translators: 1: slam name, 2: number of presenters */
			: sprintf( __( '%1$s റീജിയണൽ സയൻസ് സ്ലാം: ഫൈനലിലേക്ക് തിരഞ്ഞെടുക്കപ്പെട്ട %2$d അവതാരകർ', 'slam-presenter-profiles' ), $term->name, $count );
	}

	public static function title( $term, $result ) {
		$title = '' !== $result['title'] ? $result['title'] : self::default_title( $term, count( $result['ids'] ) );
		return str_replace( '{count}', (string) count( $result['ids'] ), $title );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . SSP_POST_TYPE,
			__( 'Slam Results', 'slam-presenter-profiles' ),
			__( 'Results', 'slam-presenter-profiles' ),
			'edit_posts',
			'ssp-results',
			array( __CLASS__, 'render_page' )
		);
	}

	private static function edit_url( $term_id ) {
		return admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-results&event=' . (int) $term_id );
	}

	/**
	 * Presenters offered in the popup: the slam's own first, then the rest of its edition.
	 */
	private static function pool( $term_id ) {
		$edition = SSP_Editions::event_edition( $term_id );
		$ids     = get_posts(
			SSP_Editions::scope_query(
				array(
					'post_type'      => SSP_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				),
				$edition ? $edition : null
			)
		);
		$own   = SSP_Presenter::get_event_presenter_ids( $term_id );
		$order = array_flip( SSP_Timetable::get_talk_ids( $term_id ) );
		$out   = array();
		foreach ( $ids as $id ) {
			$terms = wp_get_post_terms( $id, SSP_TAXONOMY, array( 'fields' => 'names' ) );
			$thumb = get_the_post_thumbnail_url( $id, 'ssp-thumb' );
			$out[] = array(
				'id'     => (int) $id,
				'name'   => SSP_Presenter::display_name( $id ),
				'nameEn' => SSP_Presenter::get( $id, 'name_en' ),
				'inst'   => SSP_Presenter::get( $id, 'institution' ),
				'title'  => SSP_Presenter::get( $id, 'talk_title' ),
				'thumb'  => $thumb ? $thumb : '',
				'events' => is_wp_error( $terms ) ? '' : implode( ', ', $terms ),
				'own'    => in_array( $id, $own, true ),
				'no'     => isset( $order[ $id ] ) ? $order[ $id ] + 1 : 0,
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return ( $b['own'] <=> $a['own'] ) ?: ( ( $a['no'] ?: 999 ) <=> ( $b['no'] ?: 999 ) ) ?: strcmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'ssp-results' ) ) {
			return;
		}
		wp_enqueue_style( 'ssp-admin', SSP_PLUGIN_URL . 'assets/css/admin.css', array(), SSP_VERSION );
		$term_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $term_id || ! term_exists( $term_id, SSP_TAXONOMY ) ) {
			return;
		}
		wp_enqueue_script( 'ssp-results', SSP_PLUGIN_URL . 'assets/js/results.js', array( 'jquery', 'jquery-ui-sortable' ), SSP_VERSION, true );
		wp_localize_script(
			'ssp-results',
			'sspResults',
			array(
				'pool'     => self::pool( $term_id ),
				'selected' => self::get( $term_id )['ids'],
				'i18n'     => array(
					'title'     => __( 'Select presenters for the result', 'slam-presenter-profiles' ),
					'search'    => __( 'Search name, institution or title…', 'slam-presenter-profiles' ),
					'thisSlam'  => __( 'This slam', 'slam-presenter-profiles' ),
					'edition'   => __( 'Whole edition', 'slam-presenter-profiles' ),
					'add'       => __( 'Add %d', 'slam-presenter-profiles' ),
					'cancel'    => __( 'Cancel', 'slam-presenter-profiles' ),
					'remove'    => __( 'Remove', 'slam-presenter-profiles' ),
					'empty'     => __( 'No presenters selected yet. Click "Select presenters…".', 'slam-presenter-profiles' ),
					'noMatch'   => __( 'No presenter matches.', 'slam-presenter-profiles' ),
					'inResult'  => __( 'in result', 'slam-presenter-profiles' ),
					'talkNo'    => __( 'talk #%d', 'slam-presenter-profiles' ),
					'unsaved'   => __( 'Unsaved changes: click Save result.', 'slam-presenter-profiles' ),
				),
			)
		);
	}

	public static function handle_save() {
		$term_id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0;
		if ( ! current_user_can( 'edit_posts' ) || ! $term_id || ! term_exists( $term_id, SSP_TAXONOMY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_save_result_' . $term_id );
		$ids = isset( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) ) ) : array();
		$ids = array_values(
			array_filter(
				$ids,
				function ( $id ) {
					return SSP_POST_TYPE === get_post_type( $id );
				}
			)
		);
		update_term_meta(
			$term_id,
			self::META,
			array(
				'title'  => sanitize_text_field( wp_unslash( isset( $_POST['title'] ) ? $_POST['title'] : '' ) ),
				'note'   => sanitize_text_field( wp_unslash( isset( $_POST['note'] ) ? $_POST['note'] : '' ) ),
				'ids'    => $ids,
				'status' => isset( $_POST['status'] ) && 'publish' === $_POST['status'] ? 'publish' : 'draft',
			)
		);
		SSP_Timetable::clear_page_cache();
		wp_safe_redirect( add_query_arg( 'saved', 1, self::edit_url( $term_id ) ) );
		exit;
	}

	public static function render_page() {
		$term_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$term    = $term_id ? get_term( $term_id, SSP_TAXONOMY ) : null;
		echo '<div class="wrap ssp-wrap">';
		if ( $term && ! is_wp_error( $term ) ) {
			self::render_edit( $term );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function render_list() {
		?>
		<h1><?php esc_html_e( 'Slam Results', 'slam-presenter-profiles' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Declare the result of each slam: pick the presenters (eg. those selected for the final), publish, and place the shortcode on a page.', 'slam-presenter-profiles' ); ?></p>
		<table class="widefat striped ssp-tt-table">
			<thead><tr>
				<th><?php esc_html_e( 'Slam', 'slam-presenter-profiles' ); ?></th>
				<th><?php esc_html_e( 'Status', 'slam-presenter-profiles' ); ?></th>
				<th><?php esc_html_e( 'Presenters', 'slam-presenter-profiles' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'slam-presenter-profiles' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( SSP_Post_Types::get_events() as $ev ) : ?>
				<?php $r = self::get( $ev->term_id ); ?>
				<tr>
					<td><strong><a class="row-title" href="<?php echo esc_url( self::edit_url( $ev->term_id ) ); ?>"><?php echo esc_html( $ev->name ); ?></a></strong></td>
					<td>
						<?php if ( ! $r['ids'] ) : ?>
							<span class="ssp-muted"><?php esc_html_e( 'Not declared', 'slam-presenter-profiles' ); ?></span>
						<?php elseif ( 'publish' === $r['status'] ) : ?>
							<span class="ssp-status-badge is-live"><?php esc_html_e( 'Published', 'slam-presenter-profiles' ); ?></span>
						<?php else : ?>
							<span class="ssp-status-badge"><?php esc_html_e( 'Draft', 'slam-presenter-profiles' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo (int) count( $r['ids'] ); ?></td>
					<td><code>[slam_results event="<?php echo esc_html( $ev->slug ); ?>"]</code></td>
					<td><a class="button button-primary" href="<?php echo esc_url( self::edit_url( $ev->term_id ) ); ?>"><?php echo $r['ids'] ? esc_html__( 'Edit result', 'slam-presenter-profiles' ) : esc_html__( 'Declare result', 'slam-presenter-profiles' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_edit( $term ) {
		$r = self::get( $term->term_id );
		?>
		<p class="ssp-back"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-results' ) ); ?>">← <?php esc_html_e( 'All results', 'slam-presenter-profiles' ); ?></a></p>
		<h1>
			<?php
			/* translators: %s: slam name */
			echo esc_html( sprintf( __( 'Result: %s', 'slam-presenter-profiles' ), $term->name ) );
			?>
		</h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php echo 'publish' === $r['status'] ? esc_html__( 'Result saved and published.', 'slam-presenter-profiles' ) : esc_html__( 'Result saved as a draft: visitors do not see it yet.', 'slam-presenter-profiles' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-result-form">
			<input type="hidden" name="action" value="ssp_save_result">
			<input type="hidden" name="event" value="<?php echo (int) $term->term_id; ?>">
			<?php wp_nonce_field( 'ssp_save_result_' . $term->term_id ); ?>

			<div class="ssp-card">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ssp_result_title"><?php esc_html_e( 'Heading', 'slam-presenter-profiles' ); ?></label></th>
						<td>
							<input type="text" id="ssp_result_title" name="title" class="large-text" value="<?php echo esc_attr( $r['title'] ); ?>" placeholder="<?php echo esc_attr( self::default_title( $term, count( $r['ids'] ) ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank for the text shown. {count} is replaced by the number of presenters.', 'slam-presenter-profiles' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ssp_result_note"><?php esc_html_e( 'Note', 'slam-presenter-profiles' ); ?></label></th>
						<td>
							<input type="text" id="ssp_result_note" name="note" class="large-text" value="<?php echo esc_attr( $r['note'] ); ?>" placeholder="<?php esc_attr_e( 'selected for the final', 'slam-presenter-profiles' ); ?>">
							<p class="description"><?php esc_html_e( 'Shown after the date and venue. Leave blank for "selected for the final".', 'slam-presenter-profiles' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'slam-presenter-profiles' ); ?></th>
						<td>
							<label><input type="radio" name="status" value="draft" <?php checked( 'publish' !== $r['status'] ); ?>> <?php esc_html_e( 'Draft: only editors see it', 'slam-presenter-profiles' ); ?></label><br>
							<label><input type="radio" name="status" value="publish" <?php checked( 'publish', $r['status'] ); ?>> <?php esc_html_e( 'Published: visible on the website', 'slam-presenter-profiles' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcode', 'slam-presenter-profiles' ); ?></th>
						<td><code>[slam_results event="<?php echo esc_html( $term->slug ); ?>"]</code></td>
					</tr>
				</table>
			</div>

			<div class="ssp-card">
				<h2 class="ssp-result-head">
					<?php esc_html_e( 'Presenters in the result', 'slam-presenter-profiles' ); ?>
					<span class="ssp-result-count"></span>
					<button type="button" class="button button-primary ssp-result-select"><?php esc_html_e( 'Select presenters…', 'slam-presenter-profiles' ); ?></button>
				</h2>
				<p class="description"><?php esc_html_e( 'Drag to change the order shown on the website.', 'slam-presenter-profiles' ); ?></p>
				<ol class="ssp-result-list"></ol>
			</div>

			<p>
				<?php submit_button( __( 'Save result', 'slam-presenter-profiles' ), 'primary', 'submit', false ); ?>
				<span class="ssp-result-dirty" hidden></span>
			</p>
		</form>
		<?php
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'event'   => '',
				'columns' => 3,
			),
			$atts,
			'slam_results'
		);
		$term = is_numeric( $atts['event'] ) ? get_term( (int) $atts['event'], SSP_TAXONOMY ) : get_term_by( 'slug', sanitize_title( $atts['event'] ), SSP_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}
		$r     = self::get( $term->term_id );
		$draft = 'publish' !== $r['status'];
		if ( ! $r['ids'] || ( $draft && ! current_user_can( 'edit_posts' ) ) ) {
			return '';
		}
		$ids = array_values(
			array_filter(
				$r['ids'],
				function ( $id ) {
					return 'publish' === get_post_status( $id );
				}
			)
		);
		if ( ! $ids ) {
			return '';
		}
		wp_enqueue_style( 'ssp-frontend' );
		$meta  = SSP_Post_Types::get_event_meta( $term->term_id );
		$where = array();
		if ( $meta['event_date'] ) {
			$where[] = date_i18n( 'l, d F Y', strtotime( $meta['event_date'] ) );
		}
		if ( $meta['venue'] || $meta['city'] ) {
			$where[] = implode( ', ', array_filter( array( $meta['venue'], $meta['city'] ) ) );
		}
		$where[] = '' !== $r['note'] ? $r['note'] : __( 'selected for the final', 'slam-presenter-profiles' );
		ob_start();
		?>
		<section class="ssp-results" style="--ssp-cols:<?php echo (int) max( 1, min( 4, (int) $atts['columns'] ) ); ?>">
			<?php if ( $draft ) : ?>
				<p class="ssp-tt-draft-note"><?php esc_html_e( 'Draft result: only visible to editors.', 'slam-presenter-profiles' ); ?></p>
			<?php endif; ?>
			<header class="ssp-results-head">
				<p class="ssp-results-eyebrow">🏆 <?php echo esc_html( SSP_Settings::get( 'label_result' ) ); ?></p>
				<h2 class="ssp-results-title"><?php echo esc_html( self::title( $term, array_merge( $r, array( 'ids' => $ids ) ) ) ); ?></h2>
				<p class="ssp-results-where"><?php echo esc_html( implode( ' · ', $where ) ); ?></p>
			</header>
			<ul class="ssp-results-list">
				<?php foreach ( $ids as $id ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
							<?php echo SSP_Presenter::photo_html( $id, 'ssp-thumb', 'ssp-results-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="ssp-results-text">
								<strong><?php echo esc_html( SSP_Presenter::display_name( $id ) ); ?></strong>
								<small><?php echo esc_html( SSP_Presenter::get( $id, 'institution' ) ); ?></small>
								<span class="ssp-results-talk"><?php echo esc_html( SSP_Presenter::get( $id, 'talk_title' ) ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return ob_get_clean();
	}
}
