<?php
/**
 * List presenter photos as JSON for face-center.py.
 *   wp eval-file tools/photo-list.php > photos.json
 */
$out = array();
foreach ( get_posts( array( 'post_type' => SSP_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) {
	$thumb = get_post_thumbnail_id( $p->ID );
	if ( ! $thumb ) {
		continue;
	}
	$out[] = array(
		'id'    => $p->ID,
		'name'  => SSP_Presenter::get( $p->ID, 'name_en' ),
		'url'   => wp_get_attachment_image_url( $thumb, 'large' ),
		'focus' => SSP_Presenter::get( $p->ID, 'photo_focus' ),
	);
}
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ), "\n";
