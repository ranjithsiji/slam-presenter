<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = SPF_Settings::get();
$opt      = SPF_Settings::OPTION;
$labels   = array(
	'label_count'    => __( 'Number of pictures made (%s = number; blank = hide)', 'slam-photo-frame' ),
	'label_start'    => __( 'Main button: choose a photo', 'slam-photo-frame' ),
	'label_take'     => __( 'Photo source: camera', 'slam-photo-frame' ),
	'label_choose'   => __( 'Photo source: gallery', 'slam-photo-frame' ),
	'label_change'   => __( 'Editor: change photo', 'slam-photo-frame' ),
	'label_hint'     => __( 'Editor: help under the picture', 'slam-photo-frame' ),
	'label_zoom'     => __( 'Editor: zoom slider', 'slam-photo-frame' ),
	'label_zoom_in'  => __( 'Editor: zoom in', 'slam-photo-frame' ),
	'label_zoom_out' => __( 'Editor: zoom out', 'slam-photo-frame' ),
	'label_rotate_l' => __( 'Editor: rotate left', 'slam-photo-frame' ),
	'label_rotate'   => __( 'Editor: rotate right', 'slam-photo-frame' ),
	'label_flip'     => __( 'Editor: mirror', 'slam-photo-frame' ),
	'label_blur'     => __( 'Editor: blurred background switch', 'slam-photo-frame' ),
	'label_reset'    => __( 'Editor: reset position', 'slam-photo-frame' ),
	'label_close'    => __( 'Editor: close', 'slam-photo-frame' ),
	'label_done'     => __( 'Editor: finish', 'slam-photo-frame' ),
	'label_edit'     => __( 'Button: back to editing', 'slam-photo-frame' ),
	'label_post'     => __( 'Heading: share the picture', 'slam-photo-frame' ),
	'label_share'    => __( 'Button: share the picture (phone share menu)', 'slam-photo-frame' ),
	'label_download' => __( 'Button: download', 'slam-photo-frame' ),
	'label_saved'    => __( 'Note after download', 'slam-photo-frame' ),
	'label_caption'  => __( 'Caption box heading', 'slam-photo-frame' ),
	'label_copy_cap' => __( 'Button: copy caption', 'slam-photo-frame' ),
	'label_caption_copied' => __( 'Note: caption copied (also shown after sharing)', 'slam-photo-frame' ),
	'label_invite'   => __( 'Heading: share the frame link', 'slam-photo-frame' ),
	'label_more'     => __( 'Button: share the link with other apps', 'slam-photo-frame' ),
	'label_copy'     => __( 'Button: copy link', 'slam-photo-frame' ),
	'label_copied'   => __( 'Note: link copied', 'slam-photo-frame' ),
	'label_capture'  => __( 'Camera: take the picture', 'slam-photo-frame' ),
	'label_switch'   => __( 'Camera: front / back', 'slam-photo-frame' ),
	'label_cancel'   => __( 'Camera: close', 'slam-photo-frame' ),
	'label_privacy'  => __( 'Privacy note', 'slam-photo-frame' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Photo Frame Settings', 'slam-photo-frame' ); ?></h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'spf_settings_group' ); ?>

		<h2><?php esc_html_e( 'Address', 'slam-photo-frame' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="spf_frame_slug"><?php esc_html_e( 'Frame URL base', 'slam-photo-frame' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" id="spf_frame_slug" name="<?php echo esc_attr( $opt ); ?>[frame_slug]" value="<?php echo esc_attr( $settings['frame_slug'] ); ?>" class="regular-text code"><code>/participate/</code>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Labels', 'slam-photo-frame' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Text shown on the frame page. Leave a field empty to restore the default.', 'slam-photo-frame' ); ?></p>
		<table class="form-table">
			<?php foreach ( $labels as $key => $label ) : ?>
				<tr>
					<th scope="row"><label for="spf_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="text" id="spf_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $opt . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" class="large-text"></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
