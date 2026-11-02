<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presenter export as CSV or Excel (.xlsx), for one slam or the whole working edition.
 *
 * The .xlsx file is written without libraries: a minimal workbook with inline strings, zipped by
 * zip_bytes(), so it works on servers without the PHP zip extension.
 */
class SSP_Export {

	public static function init() {
		add_action( 'admin_post_ssp_export', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_ssp_export_csv', array( __CLASS__, 'handle' ) ); // Old form action.
		add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'list_buttons' ) );
	}

	/**
	 * Export buttons on the All Presenters list, for the slam it is filtered to (or the whole edition).
	 */
	public static function list_buttons( $which ) {
		$screen = get_current_screen();
		if ( 'top' !== $which || ! $screen || 'edit-' . SSP_POST_TYPE !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$slug  = isset( $_GET[ SSP_TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ SSP_TAXONOMY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$term  = $slug ? get_term_by( 'slug', $slug, SSP_TAXONOMY ) : null;
		$event = $term ? $term->term_id : 0;
		echo '<div class="alignleft actions ssp-list-export">';
		printf( '<a class="button" href="%s">%s</a> ', esc_url( self::url( 'xlsx', $event ) ), esc_html__( 'Export Excel', 'slam-presenter-profiles' ) );
		printf( '<a class="button" href="%s">%s</a>', esc_url( self::url( 'csv', $event ) ), esc_html__( 'Export CSV', 'slam-presenter-profiles' ) );
		echo '</div>';
	}

	public static function url( $format, $event = 0 ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=ssp_export&format=' . $format . '&event=' . (int) $event ), 'ssp_export' );
	}

	/**
	 * Column header => value for one presenter. Columns are the same in CSV and Excel.
	 */
	private static function row( $post, $events ) {
		$slots = array();
		$nos   = array();
		foreach ( $events as $event ) {
			$slot = SSP_Timetable::find_slot( $event->term_id, $post->ID );
			if ( $slot ) {
				$slots[] = SSP_Timetable::format_minutes( $slot['start'] ) . ' – ' . SSP_Timetable::format_minutes( $slot['end'] ) . ( count( $events ) > 1 ? ' (' . $event->name . ')' : '' );
				$nos[]   = $slot['no'];
			}
		}
		$editions = array();
		foreach ( $events as $event ) {
			$edition = SSP_Editions::event_edition( $event->term_id );
			if ( $edition ) {
				$editions[ $edition ] = get_term( $edition )->name;
			}
		}
		$get    = function ( $key ) use ( $post ) {
			return SSP_Presenter::get( $post->ID, $key );
		};
		$social = SSP_Presenter::get_social( $post->ID );
		$papers = array_map(
			function ( $p ) {
				return trim( $p['title'] . ( $p['year'] ? ' (' . $p['year'] . ')' : '' ) . ( $p['url'] ? ' ' . $p['url'] : '' ) );
			},
			SSP_Presenter::get_papers( $post->ID )
		);
		$row = array(
			'ID'                 => $post->ID,
			'Name (Malayalam)'   => $post->post_title,
			'Name (English)'     => $get( 'name_en' ),
			'Institution'        => $get( 'institution' ),
			'Designation'        => $get( 'designation' ),
			'Slam'               => implode( '; ', wp_list_pluck( $events, 'name' ) ),
			'Edition'            => implode( '; ', $editions ),
			'Talk no.'           => implode( '; ', $nos ),
			'Time slot'          => implode( '; ', $slots ),
			'Presentation title' => $get( 'talk_title' ),
			'Summary'            => $get( 'summary' ),
			'Abstract'           => $get( 'abstract' ),
			'Bio'                => trim( wp_strip_all_tags( $post->post_content ) ),
			'Award'              => $get( 'award' ),
			'Email'              => $get( 'email' ),
			'Phone'              => $get( 'phone' ),
			'Video URL'          => $get( 'video_url' ),
			'Photo (Drive link)' => $get( 'photo_drive_url' ),
			'Photo URL'          => (string) get_the_post_thumbnail_url( $post, 'full' ),
			'Profile URL'        => get_permalink( $post ),
			'Status'             => 'publish' === $post->post_status ? 'Published' : ucfirst( $post->post_status ),
		);
		foreach ( SSP_Presenter::networks() as $key => $network ) {
			$row[ $network[0] ] = isset( $social[ $key ] ) ? $social[ $key ] : '';
		}
		$row['Papers'] = implode( "\n", $papers );
		return $row;
	}

	/**
	 * @return array{0: string[], 1: array[]} Headers and rows, in timetable order for a single slam.
	 */
	public static function table( $event_id = 0 ) {
		$args = array(
			'post_type'      => SSP_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $event_id ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => SSP_TAXONOMY,
					'terms'    => $event_id,
				),
			);
		} else {
			$args = SSP_Editions::scope_query( $args );
		}
		$posts = get_posts( $args );
		if ( $event_id ) {
			$order = array_flip( SSP_Timetable::get_talk_ids( $event_id ) );
			usort(
				$posts,
				function ( $a, $b ) use ( $order ) {
					$ia = isset( $order[ $a->ID ] ) ? $order[ $a->ID ] : PHP_INT_MAX;
					$ib = isset( $order[ $b->ID ] ) ? $order[ $b->ID ] : PHP_INT_MAX;
					return $ia <=> $ib ?: strcmp( $a->post_title, $b->post_title );
				}
			);
		}
		$rows = array();
		foreach ( $posts as $post ) {
			$events = wp_get_post_terms( $post->ID, SSP_TAXONOMY );
			$rows[] = self::row( $post, is_wp_error( $events ) ? array() : $events );
		}
		$headers = $rows ? array_keys( $rows[0] ) : array_keys( self::row( (object) array( 'ID' => 0, 'post_title' => '', 'post_content' => '', 'post_status' => 'publish' ), array() ) );
		return array( $headers, array_map( 'array_values', $rows ) );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slam-presenter-profiles' ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ssp_export' ) && ! wp_verify_nonce( $nonce, 'ssp_export_csv' ) ) {
			wp_die( esc_html__( 'The link has expired. Go back and try again.', 'slam-presenter-profiles' ) );
		}
		$event  = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		$format = isset( $_GET['format'] ) && 'xlsx' === $_GET['format'] ? 'xlsx' : 'csv';

		$term = $event ? get_term( $event, SSP_TAXONOMY ) : null;
		if ( $term && ! is_wp_error( $term ) ) {
			$label = $term->name;
		} else {
			$edition = SSP_Editions::current();
			$label   = $edition ? get_term( $edition )->name : 'all';
		}
		$file = sanitize_file_name( 'slam-presenters-' . ( sanitize_title( $label ) ? sanitize_title( $label ) : 'event-' . $event ) . '-' . gmdate( 'Y-m-d' ) ) . '.' . $format;

		list( $headers, $rows ) = self::table( $event );
		nocache_headers();
		if ( 'xlsx' === $format ) {
			$bytes = self::xlsx( $headers, $rows, $label );
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="' . $file . '"' );
			header( 'Content-Length: ' . strlen( $bytes ) );
			echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput -- binary file.
			exit;
		}
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- UTF-8 BOM so Excel shows Malayalam correctly.
		fputcsv( $out, $headers, ',', '"', '\\' );
		foreach ( $rows as $row ) {
			fputcsv( $out, $row, ',', '"', '\\' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	private static function col( $n ) {
		$s = '';
		for ( $n++; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$s = chr( 65 + ( $n - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	private static function xml( $text ) {
		// Drop characters XML 1.0 does not allow.
		$text = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $text );
		return htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * One-sheet workbook: bold, frozen header row with a filter, wrapped long text.
	 */
	public static function xlsx( $headers, $rows, $sheet_name = 'Presenters' ) {
		$wide  = array( 'Summary', 'Abstract', 'Bio', 'Papers', 'Presentation title' );
		$cols  = '';
		foreach ( $headers as $i => $h ) {
			$width = in_array( $h, $wide, true ) ? 60 : ( in_array( $h, array( 'ID', 'Talk no.', 'Status' ), true ) ? 10 : 28 );
			$cols .= sprintf( '<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $i + 1, $width );
		}
		$data = '';
		foreach ( array_merge( array( $headers ), $rows ) as $r => $row ) {
			$data .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( $row ) as $c => $value ) {
				$ref   = self::col( $c ) . ( $r + 1 );
				$style = 0 === $r ? ' s="1"' : ( in_array( $headers[ $c ], $wide, true ) ? ' s="2"' : '' );
				if ( $r > 0 && is_int( $value ) ) {
					$data .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
				} elseif ( '' !== (string) $value ) {
					$data .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::xml( $value ) . '</t></is></c>';
				}
			}
			$data .= '</row>';
		}
		$last  = self::col( count( $headers ) - 1 ) . ( count( $rows ) + 1 );
		$name  = self::xml( mb_substr( trim( str_replace( array( '\\', '/', '?', '*', '[', ']', ':' ), ' ', $sheet_name ) ), 0, 31 ) );
		$name  = '' !== $name ? $name : 'Presenters';
		$ns    = 'http://schemas.openxmlformats.org/';
		$files = array(
			'[Content_Types].xml'        => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="' . $ns . 'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
			'_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'xl/workbook.xml'            => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships"><sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $name . '\'!$A$1:$' . preg_replace( '/\d+$/', '', $last ) . '$' . ( count( $rows ) + 1 ) . '</definedName></definedNames></workbook>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
			'xl/styles.xml'              => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF9E1"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs></styleSheet>',
			'xl/worksheets/sheet1.xml'   => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . 'spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane xSplit="3" ySplit="1" topLeftCell="D2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $data . '</sheetData><autoFilter ref="A1:' . $last . '"/></worksheet>',
		);
		return self::zip_bytes( $files );
	}

	/**
	 * Minimal zip archive (deflate), enough for .xlsx.
	 */
	public static function zip_bytes( $files ) {
		$zip     = '';
		$central = '';
		$time    = ( ( (int) gmdate( 'Y' ) - 1980 ) << 25 ) | ( (int) gmdate( 'n' ) << 21 ) | ( (int) gmdate( 'j' ) << 16 ) | ( (int) gmdate( 'G' ) << 11 ) | ( (int) gmdate( 'i' ) << 5 ) | ( (int) gmdate( 's' ) >> 1 );
		foreach ( $files as $name => $content ) {
			$data    = gzdeflate( $content, 6 );
			$crc     = crc32( $content );
			$common  = pack( 'vvvVVVVvv', 20, 0x0800, 8, $time, $crc, strlen( $data ), strlen( $content ), strlen( $name ), 0 );
			$offset  = strlen( $zip );
			$zip    .= "PK\x03\x04" . $common . $name . $data;
			$central .= "PK\x01\x02" . pack( 'v', 20 ) . $common . pack( 'vvvVV', 0, 0, 0, 0, $offset ) . $name;
		}
		return $zip . $central . "PK\x05\x06" . pack( 'vvvvVVv', 0, 0, count( $files ), count( $files ), strlen( $central ), strlen( $zip ), 0 );
	}
}
