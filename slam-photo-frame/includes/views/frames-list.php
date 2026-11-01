<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap spf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Photo Frames', 'slam-photo-frame' ); ?></h1>
	<?php if ( current_user_can( 'publish_posts' ) ) : ?>
		<a href="<?php echo esc_url( SPF_Admin::url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Frame', 'slam-photo-frame' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">
	<?php SPF_Admin::notice( $notice ); ?>

	<?php if ( ! $frames ) : ?>
		<div class="spf-empty">
			<p><?php esc_html_e( 'No frames yet. Add one for an event: give it a title and upload the frame PNG with a transparent window for the photo.', 'slam-photo-frame' ); ?></p>
			<p><a href="<?php echo esc_url( SPF_Admin::url( array( 'action' => 'new' ) ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Frame', 'slam-photo-frame' ); ?></a></p>
		</div>
	<?php else : ?>
		<table class="widefat striped spf-frames-table">
			<thead>
				<tr>
					<th class="spf-col-image"><span class="screen-reader-text"><?php esc_html_e( 'Frame', 'slam-photo-frame' ); ?></span></th>
					<th><?php esc_html_e( 'Title', 'slam-photo-frame' ); ?></th>
					<th><?php esc_html_e( 'Status', 'slam-photo-frame' ); ?></th>
					<th><?php esc_html_e( 'Page & shortcode', 'slam-photo-frame' ); ?></th>
					<th class="spf-col-num"><?php esc_html_e( 'Pictures', 'slam-photo-frame' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $frames as $frame ) : ?>
				<?php
				$image_id = (int) get_post_meta( $frame->ID, '_spf_image_id', true );
				$edit     = SPF_Admin::url( array( 'action' => 'edit', 'frame' => $frame->ID ) );
				$live     = 'publish' === $frame->post_status;
				$view     = $live ? get_permalink( $frame ) : get_preview_post_link( $frame );
				?>
				<tr>
					<td class="spf-col-image">
						<a href="<?php echo esc_url( $edit ); ?>" class="spf-thumb">
							<?php echo $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : ''; ?>
						</a>
					</td>
					<td>
						<strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( get_the_title( $frame ) ); ?></a></strong>
						<div class="row-actions">
							<span><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'slam-photo-frame' ); ?></a> | </span>
							<span><a href="<?php echo esc_url( $view ); ?>" target="_blank"><?php echo $live ? esc_html__( 'View', 'slam-photo-frame' ) : esc_html__( 'Preview', 'slam-photo-frame' ); ?></a></span>
							<?php if ( current_user_can( 'delete_post', $frame->ID ) ) : ?>
								<span class="trash"> | <a href="<?php echo esc_url( SPF_Admin::trash_url( $frame->ID ) ); ?>" class="submitdelete"><?php esc_html_e( 'Trash', 'slam-photo-frame' ); ?></a></span>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<span class="spf-badge<?php echo $live ? ' is-live' : ''; ?>"><?php echo $live ? esc_html__( 'Published', 'slam-photo-frame' ) : esc_html__( 'Draft', 'slam-photo-frame' ); ?></span>
						<?php if ( ! $image_id ) : ?>
							<div class="spf-muted"><?php esc_html_e( 'No frame image', 'slam-photo-frame' ); ?></div>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $live ) : ?>
							<a href="<?php echo esc_url( $view ); ?>" target="_blank"><?php echo esc_html( wp_parse_url( $view, PHP_URL_PATH ) ); ?></a><br>
						<?php endif; ?>
						<?php SPF_Admin::shortcode_field( $frame->ID ); ?>
					</td>
					<td class="spf-col-num"><?php echo esc_html( number_format_i18n( (int) get_post_meta( $frame->ID, '_spf_uses', true ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
