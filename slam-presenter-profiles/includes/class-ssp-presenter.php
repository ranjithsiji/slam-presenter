<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presenter field definitions and read/write helpers.
 *
 * The post title is the display name (Malayalam, as on the website) and the post content is the bio.
 * Everything else is post meta with the `_ssp_` prefix.
 */
class SSP_Presenter {

	/**
	 * Simple text/textarea/url fields: key => array( label, type ).
	 */
	public static function fields() {
		return array(
			'name_en'         => array( __( 'Name (English)', 'slam-presenter-profiles' ), 'text' ),
			'institution'     => array( __( 'Institution / Affiliation', 'slam-presenter-profiles' ), 'text' ),
			'designation'     => array( __( 'Designation', 'slam-presenter-profiles' ), 'text' ),
			'talk_title'      => array( __( 'Slam presentation title', 'slam-presenter-profiles' ), 'text' ),
			'summary'         => array( __( 'One paragraph summary', 'slam-presenter-profiles' ), 'textarea' ),
			'abstract'        => array( __( 'Detailed abstract (optional)', 'slam-presenter-profiles' ), 'textarea' ),
			'award'           => array( __( 'Award / result (eg. Winner, Finalist)', 'slam-presenter-profiles' ), 'text' ),
			'video_url'       => array( __( 'Presentation video URL (YouTube etc.)', 'slam-presenter-profiles' ), 'url' ),
			'photo_drive_url' => array( __( 'Photo link (Google Drive)', 'slam-presenter-profiles' ), 'url' ),
			'photo_focus'     => array( __( 'Photo face point', 'slam-presenter-profiles' ), 'hidden' ),
			'photo_zoom'      => array( __( 'Photo zoom', 'slam-presenter-profiles' ), 'zoom' ),
			'email'           => array( __( 'Email', 'slam-presenter-profiles' ), 'email' ),
			'phone'           => array( __( 'Phone (never shown publicly)', 'slam-presenter-profiles' ), 'text' ),
			'form_no'         => array( __( 'Form response No.', 'slam-presenter-profiles' ), 'text' ),
		);
	}

	/**
	 * Social / profile networks shown as buttons on the profile.
	 */
	public static function networks() {
		return array(
			'website'        => array( 'Website', '#0F172A' ),
			'google_scholar' => array( 'Google Scholar', '#4285F4' ),
			'orcid'          => array( 'ORCID', '#A6CE39' ),
			'wikidata'       => array( 'Wikidata', '#006699' ),
			'researchgate'   => array( 'ResearchGate', '#00CCBB' ),
			'linkedin'       => array( 'LinkedIn', '#0A66C2' ),
			'x'              => array( 'X / Twitter', '#000000' ),
			'facebook'       => array( 'Facebook', '#1877F2' ),
			'instagram'      => array( 'Instagram', '#E4405F' ),
			'youtube'        => array( 'YouTube', '#FF0000' ),
			'github'         => array( 'GitHub', '#181717' ),
			'mastodon'       => array( 'Mastodon', '#6364FF' ),
		);
	}

	public static function get( $post_id, $key ) {
		return (string) get_post_meta( $post_id, '_ssp_' . $key, true );
	}

	public static function set( $post_id, $key, $value ) {
		if ( '' === $value || array() === $value ) {
			delete_post_meta( $post_id, '_ssp_' . $key );
			return;
		}
		update_post_meta( $post_id, '_ssp_' . $key, $value );
	}

	public static function get_social( $post_id ) {
		$social = get_post_meta( $post_id, '_ssp_social', true );
		return is_array( $social ) ? array_filter( $social ) : array();
	}

	/**
	 * Papers: list of array( title, authors, venue, year, url ).
	 */
	public static function get_papers( $post_id ) {
		$papers = get_post_meta( $post_id, '_ssp_papers', true );
		return is_array( $papers ) ? $papers : array();
	}

	public static function sanitize_papers( $rows ) {
		$clean = array();
		foreach ( (array) $rows as $row ) {
			$paper = array(
				'title'   => sanitize_text_field( isset( $row['title'] ) ? $row['title'] : '' ),
				'authors' => sanitize_text_field( isset( $row['authors'] ) ? $row['authors'] : '' ),
				'venue'   => sanitize_text_field( isset( $row['venue'] ) ? $row['venue'] : '' ),
				'year'    => sanitize_text_field( isset( $row['year'] ) ? $row['year'] : '' ),
				'url'     => esc_url_raw( isset( $row['url'] ) ? trim( $row['url'] ) : '' ),
			);
			if ( '' !== $paper['title'] ) {
				$clean[] = $paper;
			}
		}
		return $clean;
	}

	public static function sanitize_field( $key, $value ) {
		$fields = self::fields();
		$type   = isset( $fields[ $key ] ) ? $fields[ $key ][1] : 'text';
		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'url':
				return esc_url_raw( trim( $value ) );
			case 'email':
				return sanitize_email( $value );
			case 'zoom':
				return is_numeric( $value ) && (float) $value > 1 ? (string) round( min( 4, (float) $value ), 2 ) : '';
			case 'hidden':
				// Focus point is "x% y%".
				return preg_match( '/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $value ) ? $value : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Malayalam display name is the post title; English name is a field.
	 */
	public static function display_name( $post_id ) {
		$title = get_the_title( $post_id );
		return '' !== $title ? $title : self::get( $post_id, 'name_en' );
	}

	/**
	 * The square window onto the photo: centred on the face point and narrowed by the zoom,
	 * kept inside the photo. Returns the img position as percentages of the square frame.
	 *
	 * @param int    $width  Photo width.
	 * @param int    $height Photo height.
	 * @param string $focus  Face point, "x% y%" of the photo. Default centre.
	 * @param float  $zoom   1 = largest square that fits, 2 = half that size, ...
	 */
	public static function frame( $width, $height, $focus = '', $zoom = 1.0 ) {
		$fx = 0.5;
		$fy = 0.5;
		if ( preg_match( '/^([\d.]+)% ([\d.]+)%$/', (string) $focus, $m ) ) {
			$fx = min( 1, max( 0, $m[1] / 100 ) );
			$fy = min( 1, max( 0, $m[2] / 100 ) );
		}
		$zoom = max( 1, min( 4, (float) $zoom ) );
		$side = min( $width, $height ) / $zoom;
		$wx   = $side / $width;
		$wy   = $side / $height;
		$left = min( 1 - $wx, max( 0, $fx - $wx / 2 ) );
		$top  = min( 1 - $wy, max( 0, $fy - $wy / 2 ) );
		return array(
			'width'  => 100 / $wx,
			'height' => 100 / $wy,
			'left'   => -100 * $left / $wx,
			'top'    => -100 * $top / $wy,
		);
	}

	public static function photo_zoom( $post_id ) {
		$zoom = (float) self::get( $post_id, 'photo_zoom' );
		return $zoom >= 1 ? $zoom : 1.0;
	}

	/**
	 * Square photo framed on the presenter's face, or an initials placeholder.
	 * $size is 'ssp-square' (large display) or 'ssp-thumb' (avatars); the uncropped image is used either way.
	 */
	public static function photo_html( $post_id, $size = 'ssp-square', $class = 'ssp-photo' ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$meta    = wp_get_attachment_metadata( $thumb_id );
			$display = 'ssp-thumb' === $size ? 64 : 400;
			$attrs   = array(
				'class'   => 'ssp-frame-img',
				'alt'     => self::display_name( $post_id ),
				'loading' => 'lazy',
			);
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$f              = self::frame( $meta['width'], $meta['height'], self::get( $post_id, 'photo_focus' ), self::photo_zoom( $post_id ) );
				$attrs['style'] = sprintf( 'width:%.3f%%;height:%.3f%%;left:%.3f%%;top:%.3f%%', $f['width'], $f['height'], $f['left'], $f['top'] );
				$attrs['sizes'] = sprintf( '(max-width: 640px) %dpx, %dpx', round( $display * $f['width'] / 100 * 1.5 ), round( $display * $f['width'] / 100 ) );
			} else {
				$attrs['class'] .= ' is-cover';
			}
			$img = wp_get_attachment_image( $thumb_id, 'ssp-thumb' === $size ? 'medium' : 'large', false, $attrs );
			return sprintf( '<span class="%s ssp-frame">%s</span>', esc_attr( $class ), $img );
		}
		$name     = self::get( $post_id, 'name_en' );
		$name     = $name ? $name : self::display_name( $post_id );
		$name     = preg_replace( '/^(dr|prof)\.?\s+/i', '', $name );
		$initials = '';
		foreach ( array_slice( preg_split( '/\s+/u', trim( $name ) ), 0, 2 ) as $word ) {
			$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}
		return sprintf( '<span class="%s ssp-photo-placeholder" aria-hidden="true">%s</span>', esc_attr( $class ), esc_html( $initials ) );
	}

	/**
	 * Find a presenter by English name, optionally limited to an event.
	 */
	public static function find_by_name_en( $name_en, $event_id = 0 ) {
		$args = array(
			'post_type'      => SSP_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_ssp_name_en',
					'value' => trim( $name_en ),
				),
			),
		);
		if ( $event_id ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => SSP_TAXONOMY,
					'terms'    => (int) $event_id,
				),
			);
		}
		$ids = get_posts( $args );
		return $ids ? (int) $ids[0] : 0;
	}

	public static function get_event_presenter_ids( $event_id, $status = 'any' ) {
		return get_posts(
			array(
				'post_type'      => SSP_POST_TYPE,
				'post_status'    => $status,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => SSP_TAXONOMY,
						'terms'    => (int) $event_id,
					),
				),
			)
		);
	}
}
