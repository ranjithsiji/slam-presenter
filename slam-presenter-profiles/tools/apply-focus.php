<?php
/**
 * Save face points from face-center.py. Presenters that already have a face point (eg. set by hand
 * in admin) are left alone unless "overwrite" is given.
 *   wp eval-file tools/apply-focus.php focus.json [overwrite]
 */
$data      = json_decode( file_get_contents( $args[0] ), true );
$overwrite = isset( $args[1] ) && 'overwrite' === $args[1];
$saved     = 0;
$kept      = 0;
foreach ( $data as $row ) {
	$id = (int) $row['id'];
	if ( SSP_POST_TYPE !== get_post_type( $id ) ) {
		continue;
	}
	if ( ! $overwrite && '' !== SSP_Presenter::get( $id, 'photo_focus' ) ) {
		$kept++;
		continue;
	}
	SSP_Presenter::set( $id, 'photo_focus', SSP_Presenter::sanitize_field( 'photo_focus', $row['focus'] ) );
	SSP_Presenter::set( $id, 'photo_zoom', SSP_Presenter::sanitize_field( 'photo_zoom', (string) $row['zoom'] ) );
	$saved++;
}
wp_cache_flush();
WP_CLI::success( "Face points saved: $saved, kept existing: $kept." );
