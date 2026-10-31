<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Presenter Table" admin screen: an instant-search, sortable, filterable table of every presenter
 * with inline quick edit and bulk actions. Rows are sent as JSON and handled in assets/js/presenter-table.js.
 */
class SSP_Table {

	const QUICK_FIELDS = array( 'name_en', 'institution', 'designation', 'talk_title', 'award' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ssp_quick_save', array( __CLASS__, 'ajax_quick_save' ) );
		add_action( 'wp_ajax_ssp_bulk', array( __CLASS__, 'ajax_bulk' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . SSP_POST_TYPE,
			__( 'Presenter Table', 'slam-presenter-profiles' ),
			__( 'Presenter Table', 'slam-presenter-profiles' ),
			'edit_posts',
			'ssp-table',
			array( __CLASS__, 'render_page' ),
			0
		);
	}

	public static function row( $post ) {
		$terms  = wp_get_post_terms( $post->ID, SSP_TAXONOMY );
		$events = array();
		$slots  = array();
		foreach ( $terms as $term ) {
			$events[] = $term->term_id;
			$slot     = SSP_Timetable::find_slot( $term->term_id, $post->ID );
			if ( $slot ) {
				$slots[] = array(
					'event' => $term->term_id,
					'no'    => $slot['no'],
					'time'  => SSP_Timetable::format_minutes( $slot['start'] ),
					'sort'  => $slot['start'],
				);
			}
		}
		$thumb = get_the_post_thumbnail_url( $post, 'ssp-thumb' );
		$row   = array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'events'     => $events,
			'slots'      => $slots,
			'thumb'      => $thumb ? $thumb : '',
			'drive'      => SSP_Presenter::get( $post->ID, 'photo_drive_url' ),
			'photoError' => SSP_Presenter::get( $post->ID, 'photo_error' ),
			'links'      => count( SSP_Presenter::get_social( $post->ID ) ),
			'papers'     => count( SSP_Presenter::get_papers( $post->ID ) ),
			'hasBio'     => '' !== trim( $post->post_content ),
			'editUrl'    => get_edit_post_link( $post->ID, 'raw' ),
			'viewUrl'    => get_permalink( $post ),
		);
		foreach ( self::QUICK_FIELDS as $key ) {
			$row[ $key ] = SSP_Presenter::get( $post->ID, $key );
		}
		return $row;
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'ssp-table' ) ) {
			return;
		}
		$posts = get_posts(
			SSP_Editions::scope_query(
				array(
					'post_type'      => SSP_POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			)
		);
		$events = array();
		foreach ( SSP_Post_Types::get_events() as $term ) {
			$events[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
			);
		}

		wp_enqueue_style( 'ssp-admin', SSP_PLUGIN_URL . 'assets/css/admin.css', array(), SSP_VERSION );
		wp_enqueue_script( 'ssp-presenter-table', SSP_PLUGIN_URL . 'assets/js/presenter-table.js', array(), SSP_VERSION, true );
		wp_localize_script(
			'ssp-presenter-table',
			'sspTable',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ssp_table' ),
				'rows'    => array_map( array( __CLASS__, 'row' ), $posts ),
				'events'  => $events,
				'i18n'    => array(
					'noResults'   => __( 'No presenters match.', 'slam-presenter-profiles' ),
					'edit'        => __( 'Edit', 'slam-presenter-profiles' ),
					'quickEdit'   => __( 'Quick edit', 'slam-presenter-profiles' ),
					'view'        => __( 'View', 'slam-presenter-profiles' ),
					'save'        => __( 'Save', 'slam-presenter-profiles' ),
					'cancel'      => __( 'Cancel', 'slam-presenter-profiles' ),
					'saving'      => __( 'Saving…', 'slam-presenter-profiles' ),
					'draft'       => __( 'Draft', 'slam-presenter-profiles' ),
					'published'   => __( 'Published', 'slam-presenter-profiles' ),
					'showing'     => __( 'Showing %1$d–%2$d of %3$d', 'slam-presenter-profiles' ),
					'selected'    => __( '%d selected', 'slam-presenter-profiles' ),
					'displayName' => __( 'Display name (Malayalam)', 'slam-presenter-profiles' ),
					'nameEn'      => __( 'Name (English)', 'slam-presenter-profiles' ),
					'institution' => __( 'Institution', 'slam-presenter-profiles' ),
					'designation' => __( 'Designation', 'slam-presenter-profiles' ),
					'talkTitle'   => __( 'Presentation title', 'slam-presenter-profiles' ),
					'award'       => __( 'Award', 'slam-presenter-profiles' ),
					'events'      => __( 'Slam events', 'slam-presenter-profiles' ),
					'status'      => __( 'Status', 'slam-presenter-profiles' ),
					'noPhoto'     => __( 'No photo', 'slam-presenter-profiles' ),
					'driveOnly'   => __( 'Drive link, not fetched', 'slam-presenter-profiles' ),
					'notSched'    => __( 'not scheduled', 'slam-presenter-profiles' ),
					'confirmDel'  => __( 'Move the selected presenters to the trash?', 'slam-presenter-profiles' ),
					'links'       => __( 'links', 'slam-presenter-profiles' ),
					'papers'      => __( 'papers', 'slam-presenter-profiles' ),
					'bio'         => __( 'bio', 'slam-presenter-profiles' ),
					'done'        => __( 'Done.', 'slam-presenter-profiles' ),
				),
			)
		);
	}

	private static function check_ajax() {
		check_ajax_referer( 'ssp_table', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slam-presenter-profiles' ) ), 403 );
		}
	}

	public static function ajax_quick_save() {
		self::check_ajax();
		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post    = get_post( $post_id );
		if ( ! $post || SSP_POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slam-presenter-profiles' ) ), 403 );
		}

		$update = array( 'ID' => $post_id );
		if ( isset( $_POST['title'] ) ) {
			$update['post_title'] = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		}
		if ( isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'publish', 'draft' ), true ) ) {
			$update['post_status'] = sanitize_key( $_POST['status'] );
		}
		wp_update_post( $update );

		foreach ( self::QUICK_FIELDS as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				SSP_Presenter::set( $post_id, $key, SSP_Presenter::sanitize_field( $key, wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
		if ( isset( $_POST['events'] ) ) {
			$events = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['events'] ) ) ) ) );
			wp_set_object_terms( $post_id, $events, SSP_TAXONOMY );
		}
		wp_send_json_success( array( 'row' => self::row( get_post( $post_id ) ) ) );
	}

	public static function ajax_bulk() {
		self::check_ajax();
		$ids    = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['ids'] ) ) ) ) ) : array();
		$action = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
		$event  = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0;
		$rows   = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || SSP_POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			switch ( $action ) {
				case 'add_event':
					wp_set_object_terms( $id, $event, SSP_TAXONOMY, true );
					break;
				case 'remove_event':
					wp_remove_object_terms( $id, $event, SSP_TAXONOMY );
					break;
				case 'publish':
				case 'draft':
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => $action,
						)
					);
					break;
				case 'trash':
					if ( current_user_can( 'delete_post', $id ) ) {
						wp_trash_post( $id );
					}
					continue 2;
			}
			$rows[] = self::row( get_post( $id ) );
		}
		wp_send_json_success( array( 'rows' => $rows ) );
	}

	public static function render_page() {
		require SSP_PLUGIN_DIR . 'includes/views/table.php';
	}
}
