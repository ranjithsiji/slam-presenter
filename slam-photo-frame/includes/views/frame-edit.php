<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id         = $post ? $post->ID : 0;
$image_id   = $id ? (int) get_post_meta( $id, '_spf_image_id', true ) : 0;
$image      = $image_id ? wp_get_attachment_image_src( $image_id, 'full' ) : false;
$share_text = $id ? get_post_meta( $id, '_spf_share_text', true ) : '';
$file_name  = $id ? get_post_meta( $id, '_spf_file_name', true ) : '';
$status     = $post ? $post->post_status : 'publish';
$live       = 'publish' === $status;
$base       = home_url( '/' . SPF_Settings::get( 'frame_slug' ) . '/' );
?>
<div class="wrap spf-wrap">
	<h1 class="wp-heading-inline"><?php echo $id ? esc_html__( 'Edit Frame', 'slam-photo-frame' ) : esc_html__( 'Add New Frame', 'slam-photo-frame' ); ?></h1>
	<?php if ( $id ) : ?>
		<a href="<?php echo esc_url( $live ? get_permalink( $post ) : get_preview_post_link( $post ) ); ?>" class="page-title-action" target="_blank"><?php echo $live ? esc_html__( 'View page', 'slam-photo-frame' ) : esc_html__( 'Preview', 'slam-photo-frame' ); ?></a>
	<?php endif; ?>
	<a href="<?php echo esc_url( SPF_Admin::url() ); ?>" class="page-title-action"><?php esc_html_e( 'All Frames', 'slam-photo-frame' ); ?></a>
	<hr class="wp-header-end">
	<?php SPF_Admin::notice( $notice ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spf-edit-form">
		<input type="hidden" name="action" value="spf_save_frame">
		<input type="hidden" name="frame_id" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'spf_save_frame' ); ?>

		<div class="spf-admin-frame">
			<div class="spf-admin-image">
				<div class="spf-admin-preview">
					<img src="<?php echo $image ? esc_url( $image[0] ) : ''; ?>" alt=""<?php echo $image ? '' : ' hidden'; ?>>
					<button type="button" class="spf-admin-placeholder spf-choose-image"<?php echo $image ? ' hidden' : ''; ?>>
						<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
						<?php esc_html_e( 'Choose frame image', 'slam-photo-frame' ); ?>
					</button>
				</div>
				<input type="hidden" name="spf_image_id" id="spf_image_id" value="<?php echo (int) $image_id; ?>">
				<p>
					<button type="button" class="button spf-choose-image"><?php echo $image ? esc_html__( 'Replace image', 'slam-photo-frame' ) : esc_html__( 'Choose frame image', 'slam-photo-frame' ); ?></button>
					<button type="button" class="button-link button-link-delete spf-remove-image"<?php echo $image_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'slam-photo-frame' ); ?></button>
				</p>
				<p class="spf-image-note description"></p>
				<p class="description"><?php esc_html_e( 'A PNG with a transparent window where the photo shows through, eg. 1200 × 1200 px. The picture people download has the same size.', 'slam-photo-frame' ); ?></p>
			</div>

			<div class="spf-admin-fields">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="spf_title"><?php esc_html_e( 'Title', 'slam-photo-frame' ); ?></label></th>
						<td>
							<input type="text" name="spf_title" id="spf_title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" class="large-text" required>
							<p class="description"><?php esc_html_e( 'The event or campaign, eg. "Kerala Science Slam 2026: ഞാനും പങ്കെടുക്കുന്നു". Shown as the page title and in link previews.', 'slam-photo-frame' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spf_intro"><?php esc_html_e( 'Intro text', 'slam-photo-frame' ); ?></label></th>
						<td>
							<textarea name="spf_intro" id="spf_intro" rows="3" class="large-text"><?php echo esc_textarea( $post ? $post->post_content : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown above the frame on its page. Optional.', 'slam-photo-frame' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spf_share_text"><?php esc_html_e( 'Share text', 'slam-photo-frame' ); ?></label></th>
						<td>
							<textarea name="spf_share_text" id="spf_share_text" rows="3" class="large-text"><?php echo esc_textarea( $share_text ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Sent along with the picture and the frame link, eg. "I am participating in Kerala Science Slam 2026! #KeralaScienceSlam". The page link is added automatically.', 'slam-photo-frame' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spf_slug"><?php esc_html_e( 'Page address', 'slam-photo-frame' ); ?></label></th>
						<td>
							<span class="spf-inline"><code><?php echo esc_html( $base ); ?></code><input type="text" name="spf_slug" id="spf_slug" value="<?php echo esc_attr( $post ? $post->post_name : '' ); ?>" class="code" placeholder="<?php esc_attr_e( 'from the title', 'slam-photo-frame' ); ?>"><code>/</code></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spf_file_name"><?php esc_html_e( 'Download file name', 'slam-photo-frame' ); ?></label></th>
						<td>
							<input type="text" name="spf_file_name" id="spf_file_name" value="<?php echo esc_attr( $file_name ); ?>" class="regular-text code" placeholder="<?php echo esc_attr( $post && $post->post_name ? $post->post_name : 'kerala-science-slam' ); ?>"><code>.jpg</code>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'slam-photo-frame' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="spf_status" value="publish" <?php checked( $live ); ?>> <?php esc_html_e( 'Published: the page is public', 'slam-photo-frame' ); ?></label><br>
								<label><input type="radio" name="spf_status" value="draft" <?php checked( ! $live ); ?>> <?php esc_html_e( 'Draft: only editors can preview it', 'slam-photo-frame' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<?php if ( $id ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Shortcode', 'slam-photo-frame' ); ?></th>
							<td>
								<?php SPF_Admin::shortcode_field( $id ); ?>
								<p class="description"><?php esc_html_e( 'Paste it into any page or post (a Shortcode block in the block editor) to show this frame maker to visitors there.', 'slam-photo-frame' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Pictures', 'slam-photo-frame' ); ?></th>
							<td><?php echo esc_html( number_format_i18n( (int) get_post_meta( $id, '_spf_uses', true ) ) ); ?> <span class="description"><?php esc_html_e( 'downloaded or shared', 'slam-photo-frame' ); ?></span></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>
		</div>

		<p class="submit">
			<?php submit_button( $id ? __( 'Save Frame', 'slam-photo-frame' ) : __( 'Create Frame', 'slam-photo-frame' ), 'primary', 'submit', false ); ?>
			<?php if ( $id && current_user_can( 'delete_post', $id ) ) : ?>
				<a href="<?php echo esc_url( SPF_Admin::trash_url( $id ) ); ?>" class="submitdelete spf-trash"><?php esc_html_e( 'Move to Trash', 'slam-photo-frame' ); ?></a>
			<?php endif; ?>
		</p>
	</form>
</div>
