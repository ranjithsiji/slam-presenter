<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presenter photos: download from Google Drive links, or match photos from an uploaded ZIP by file name.
 */
class SSP_Photos {

	public static function init() {
		add_action( 'wp_ajax_ssp_fetch_photo', array( __CLASS__, 'ajax_fetch' ) );
		add_action( 'wp_ajax_ssp_save_crop', array( __CLASS__, 'ajax_save_crop' ) );
		add_action( 'admin_post_ssp_photos_zip', array( __CLASS__, 'handle_zip' ) );
	}

	/**
	 * What the photo cropper needs: the uncropped image, its size and the saved face point + zoom.
	 */
	public static function crop_data( $post_id ) {
		$thumb = get_post_thumbnail_id( $post_id );
		$meta  = $thumb ? wp_get_attachment_metadata( $thumb ) : array();
		if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return null;
		}
		return array(
			'src'    => wp_get_attachment_image_url( $thumb, 'large' ),
			'width'  => (int) $meta['width'],
			'height' => (int) $meta['height'],
			'focus'  => SSP_Presenter::get( $post_id, 'photo_focus' ),
			'zoom'   => SSP_Presenter::photo_zoom( $post_id ),
		);
	}

	public static function ajax_save_crop() {
		check_ajax_referer( 'ssp_photos', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || SSP_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slam-presenter-profiles' ) ), 403 );
		}
		$focus = isset( $_POST['focus'] ) ? sanitize_text_field( wp_unslash( $_POST['focus'] ) ) : '';
		$zoom  = isset( $_POST['zoom'] ) ? sanitize_text_field( wp_unslash( $_POST['zoom'] ) ) : '';
		SSP_Presenter::set( $post_id, 'photo_focus', SSP_Presenter::sanitize_field( 'photo_focus', $focus ) );
		SSP_Presenter::set( $post_id, 'photo_zoom', SSP_Presenter::sanitize_field( 'photo_zoom', $zoom ) );
		clean_post_cache( $post_id );
		wp_send_json_success( array( 'html' => SSP_Presenter::photo_html( $post_id, 'ssp-square', 'ssp-photo-card-img' ) ) );
	}

	/**
	 * Extract the file ID from the usual Drive URL shapes (open?id=, /file/d/<id>/, uc?id=).
	 */
	public static function drive_id( $url ) {
		if ( preg_match( '~[?&]id=([\w-]{20,})~', $url, $m ) || preg_match( '~/d/([\w-]{20,})~', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Download the presenter's Drive photo into the media library and set it as the featured image.
	 *
	 * @return int|WP_Error Attachment ID.
	 */
	public static function fetch_from_drive( $post_id, $force = false ) {
		$url = SSP_Presenter::get( $post_id, 'photo_drive_url' );
		$id  = self::drive_id( $url );
		if ( ! $id ) {
			self::log( $post_id, __( 'No Google Drive link.', 'slam-presenter-profiles' ) );
			return new WP_Error( 'ssp_no_drive', __( 'No Google Drive link.', 'slam-presenter-profiles' ) );
		}
		if ( ! $force && get_post_thumbnail_id( $post_id ) && SSP_Presenter::get( $post_id, 'photo_drive_id' ) === $id ) {
			return (int) get_post_thumbnail_id( $post_id );
		}

		$response = wp_remote_get(
			'https://drive.usercontent.google.com/download?id=' . rawurlencode( $id ) . '&export=download&confirm=t',
			array(
				'timeout'     => 60,
				'redirection' => 5,
			)
		);
		if ( is_wp_error( $response ) ) {
			self::log( $post_id, $response->get_error_message() );
			return $response;
		}
		$body = wp_remote_retrieve_body( $response );
		$type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || 0 === strpos( (string) $type, 'text/html' ) ) {
			$message = __( 'Drive did not return an image. Share the file (or its folder) as "Anyone with the link → Viewer".', 'slam-presenter-profiles' );
			self::log( $post_id, $message );
			return new WP_Error( 'ssp_drive_private', $message );
		}

		$filename = '';
		$disp     = (string) wp_remote_retrieve_header( $response, 'content-disposition' );
		if ( preg_match( "/filename\*=UTF-8''([^;]+)/i", $disp, $m ) ) {
			$filename = rawurldecode( $m[1] );
		} elseif ( preg_match( '/filename="?([^";]+)"?/i', $disp, $m ) ) {
			$filename = $m[1];
		}

		$attachment_id = self::attach_bytes( $post_id, $body, $filename );
		if ( is_wp_error( $attachment_id ) ) {
			self::log( $post_id, $attachment_id->get_error_message() );
			return $attachment_id;
		}
		SSP_Presenter::set( $post_id, 'photo_drive_id', $id );
		self::log( $post_id, '' );
		return $attachment_id;
	}

	/**
	 * Save raw image bytes as an attachment of the presenter and make it the featured image.
	 */
	public static function attach_bytes( $post_id, $bytes, $original_name = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( 'ssp-photo' );
		file_put_contents( $tmp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$mime = wp_get_image_mime( $tmp );
		$exts = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
			'image/avif' => 'avif',
			'image/heic' => 'heic',
		);
		if ( ! $mime || ! isset( $exts[ $mime ] ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'ssp_not_image', __( 'The file is not a supported image (JPG, PNG, WebP, AVIF).', 'slam-presenter-profiles' ) );
		}

		$name = SSP_Presenter::get( $post_id, 'name_en' );
		$name = sanitize_title( $name ? $name : ( $original_name ? pathinfo( $original_name, PATHINFO_FILENAME ) : 'presenter-' . $post_id ) );
		$file = array(
			'name'     => $name . '.' . $exts[ $mime ],
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id, SSP_Presenter::display_name( $post_id ) );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', SSP_Presenter::display_name( $post_id ) );
		set_post_thumbnail( $post_id, $attachment_id );
		// A new photo starts with the default crop.
		SSP_Presenter::set( $post_id, 'photo_focus', '' );
		SSP_Presenter::set( $post_id, 'photo_zoom', '' );
		return $attachment_id;
	}

	private static function log( $post_id, $message ) {
		SSP_Presenter::set( $post_id, 'photo_error', $message );
	}

	public static function ajax_fetch() {
		check_ajax_referer( 'ssp_photos', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slam-presenter-profiles' ) ), 403 );
		}
		if ( isset( $_POST['drive_url'] ) ) {
			SSP_Presenter::set( $post_id, 'photo_drive_url', esc_url_raw( wp_unslash( $_POST['drive_url'] ) ) );
		}
		$result = self::fetch_from_drive( $post_id, ! empty( $_POST['force'] ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'attachment' => $result,
				'thumb'      => wp_get_attachment_image_url( $result, 'ssp-thumb' ),
				'large'      => wp_get_attachment_image_url( $result, 'medium_large' ),
				'crop'       => self::crop_data( $post_id ),
				'html'       => SSP_Presenter::photo_html( $post_id, 'ssp-square', 'ssp-photo-card-img' ),
			)
		);
	}

	/**
	 * Tokens used to match a file name to a presenter: English name words, without honorifics.
	 */
	private static function name_tokens( $name ) {
		$name   = mb_strtolower( preg_replace( '/^(dr|prof)\.?\s+/i', '', trim( $name ) ) );
		$tokens = preg_split( '/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_filter( $tokens, function ( $t ) {
			return mb_strlen( $t ) > 1;
		} ) );
	}

	/**
	 * Best presenter for a file name. Google Form uploads are named "<original> - <Respondent Name>.jpg".
	 */
	public static function match_filename( $filename, $presenters ) {
		$base = mb_strtolower( pathinfo( $filename, PATHINFO_FILENAME ) );
		if ( false !== strpos( $base, ' - ' ) ) {
			$base = substr( $base, strrpos( $base, ' - ' ) + 3 );
		}
		$file_tokens = preg_split( '/[^\p{L}\p{N}]+/u', $base, -1, PREG_SPLIT_NO_EMPTY );
		$best        = 0;
		$best_id     = 0;
		foreach ( $presenters as $id => $tokens ) {
			if ( ! $tokens ) {
				continue;
			}
			$hits  = count( array_intersect( $tokens, $file_tokens ) );
			$score = $hits / count( $tokens );
			if ( $hits && $score > $best ) {
				$best    = $score;
				$best_id = $id;
			}
		}
		return $best >= 0.5 ? $best_id : 0;
	}

	public static function handle_zip() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		check_admin_referer( 'ssp_photos_zip' );
		$back = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-photos' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'The PHP zip extension is not installed on this server.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}
		if ( empty( $_FILES['ssp_zip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ssp_zip']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'Please choose a ZIP file.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}

		$replace    = ! empty( $_POST['ssp_replace'] );
		$presenters = array();
		foreach ( get_posts( array( 'post_type' => SSP_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			$name              = SSP_Presenter::get( $id, 'name_en' );
			$presenters[ $id ] = self::name_tokens( $name ? $name : get_the_title( $id ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $_FILES['ssp_zip']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( add_query_arg( 'ssp_error', rawurlencode( __( 'Could not open the ZIP file.', 'slam-presenter-profiles' ) ), $back ) );
			exit;
		}

		$report = array(
			'matched'   => array(),
			'unmatched' => array(),
			'skipped'   => array(),
		);
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->getNameIndex( $i );
			$file  = basename( $entry );
			if ( '/' === substr( $entry, -1 ) || 0 === strpos( $file, '.' ) || false !== strpos( $entry, '__MACOSX' ) ) {
				continue;
			}
			if ( ! preg_match( '/\.(jpe?g|png|webp|gif|avif|heic)$/i', $file ) ) {
				$report['skipped'][] = $file;
				continue;
			}
			$post_id = self::match_filename( $file, $presenters );
			if ( ! $post_id ) {
				$report['unmatched'][] = $file;
				continue;
			}
			if ( ! $replace && get_post_thumbnail_id( $post_id ) ) {
				/* translators: %s: file name */
				$report['skipped'][] = sprintf( __( '%s (presenter already has a photo)', 'slam-presenter-profiles' ), $file );
				continue;
			}
			$result = self::attach_bytes( $post_id, $zip->getFromIndex( $i ), $file );
			if ( is_wp_error( $result ) ) {
				$report['skipped'][] = $file . ': ' . $result->get_error_message();
				continue;
			}
			self::log( $post_id, '' );
			$report['matched'][] = $file . ' → ' . SSP_Presenter::display_name( $post_id );
			unset( $presenters[ $post_id ] ); // One photo per presenter per upload.
		}
		$zip->close();

		set_transient( 'ssp_zip_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'zip', 'done', $back ) );
		exit;
	}

	public static function render_page() {
		require SSP_PLUGIN_DIR . 'includes/views/photos.php';
	}
}
