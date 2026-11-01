<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = SPF_Settings::get();
$opt      = SPF_Settings::OPTION;
$labels   = array(
	'label_take'     => __( 'Button: open the camera', 'slam-photo-frame' ),
	'label_choose'   => __( 'Button: pick a photo', 'slam-photo-frame' ),
	'label_change'   => __( 'Button: change photo', 'slam-photo-frame' ),
	'label_hint'     => __( 'Help under the picture', 'slam-photo-frame' ),
	'label_zoom'     => __( 'Zoom slider', 'slam-photo-frame' ),
	'label_rotate'   => __( 'Button: rotate', 'slam-photo-frame' ),
	'label_flip'     => __( 'Button: mirror', 'slam-photo-frame' ),
	'label_reset'    => __( 'Button: reset position', 'slam-photo-frame' ),
	'label_done'     => __( 'Button: finish editing', 'slam-photo-frame' ),
	'label_edit'     => __( 'Button: back to editing', 'slam-photo-frame' ),
	'label_share'    => __( 'Button: share the picture (phone share sheet)', 'slam-photo-frame' ),
	'label_download' => __( 'Button: download', 'slam-photo-frame' ),
	'label_post'     => __( 'Heading: post the picture (Instagram, WhatsApp Status, Facebook)', 'slam-photo-frame' ),
	'label_invite'   => __( 'Heading: share the frame link', 'slam-photo-frame' ),
	'label_copy'     => __( 'Button: copy link', 'slam-photo-frame' ),
	'label_copied'   => __( 'Note: link copied', 'slam-photo-frame' ),
	'label_saved'    => __( 'Note after download (%s = app name)', 'slam-photo-frame' ),
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
