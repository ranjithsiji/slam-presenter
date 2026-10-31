<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$events   = SSP_Post_Types::get_events();
$args     = array(
	'post_type'      => SSP_POST_TYPE,
	'post_status'    => 'any',
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
$presenters = get_posts( $args );
$missing    = 0;
foreach ( $presenters as $p ) {
	if ( ! has_post_thumbnail( $p ) ) {
		$missing++;
	}
}

// Presenters queued by the CSV import.
$queue = get_transient( 'ssp_photo_queue_' . get_current_user_id() );
if ( false !== $queue ) {
	delete_transient( 'ssp_photo_queue_' . get_current_user_id() );
}

$zip_report = get_transient( 'ssp_zip_report_' . get_current_user_id() );
if ( false !== $zip_report ) {
	delete_transient( 'ssp_zip_report_' . get_current_user_id() );
}
$import_result = get_transient( 'ssp_import_result_' . get_current_user_id() );
if ( false !== $import_result ) {
	delete_transient( 'ssp_import_result_' . get_current_user_id() );
}
?>
<div class="wrap ssp-wrap">
	<h1><?php esc_html_e( 'Presenter Photos', 'slam-presenter-profiles' ); ?></h1>

	<?php if ( isset( $_GET['ssp_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ssp_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
	<?php endif; ?>

	<?php if ( $import_result ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			/* translators: 1: created, 2: updated */
			printf( esc_html__( 'Import complete: %1$d created, %2$d updated. Fetching their photos from Google Drive below…', 'slam-presenter-profiles' ), (int) $import_result['created'], (int) $import_result['updated'] );
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( $zip_report ) : ?>
		<div class="notice notice-<?php echo $zip_report['unmatched'] ? 'warning' : 'success'; ?> is-dismissible">
			<p>
				<?php
				/* translators: 1: matched, 2: unmatched, 3: skipped */
				printf( esc_html__( 'ZIP processed: %1$d matched, %2$d not matched, %3$d skipped.', 'slam-presenter-profiles' ), count( $zip_report['matched'] ), count( $zip_report['unmatched'] ), count( $zip_report['skipped'] ) );
				?>
			</p>
			<?php foreach ( array( 'matched', 'unmatched', 'skipped' ) as $group ) : ?>
				<?php if ( $zip_report[ $group ] ) : ?>
					<details><summary><?php echo esc_html( ucfirst( $group ) ); ?></summary>
						<ul class="ssp-scroll-list">
							<?php foreach ( $zip_report[ $group ] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="ssp-photo-toolbar">
		<form method="get">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( SSP_POST_TYPE ); ?>">
			<input type="hidden" name="page" value="ssp-photos">
			<select name="event" onchange="this.form.submit()">
				<option value="0"><?php esc_html_e( 'All events', 'slam-presenter-profiles' ); ?></option>
				<?php foreach ( $events as $ev ) : ?>
					<option value="<?php echo (int) $ev->term_id; ?>" <?php selected( $event_id, $ev->term_id ); ?>><?php echo esc_html( $ev->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>
		<span>
			<?php
			/* translators: 1: presenters, 2: without photo */
			printf( esc_html__( '%1$d presenters · %2$d without photo', 'slam-presenter-profiles' ), count( $presenters ), (int) $missing );
			?>
		</span>
	</div>

	<?php
	$current = $event_id ? get_term( $event_id, SSP_TAXONOMY ) : null;
	$folder  = $current && ! is_wp_error( $current ) ? SSP_Post_Types::get_event_meta( $event_id )['photo_folder'] : '';
	?>
	<?php if ( $current && ! is_wp_error( $current ) ) : ?>
		<div class="ssp-callout ssp-folder-card">
			<?php if ( $folder ) : ?>
				<strong><?php echo esc_html( $current->name ); ?>:</strong>
				<?php esc_html_e( 'photos are in this Drive folder.', 'slam-presenter-profiles' ); ?>
				<a class="button button-small" href="<?php echo esc_url( $folder ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Drive folder ↗', 'slam-presenter-profiles' ); ?></a>
			<?php else : ?>
				<strong><?php echo esc_html( $current->name ); ?>:</strong>
				<?php esc_html_e( 'no Drive photo folder saved for this slam yet.', 'slam-presenter-profiles' ); ?>
			<?php endif; ?>
			<a href="<?php echo esc_url( get_edit_term_link( $event_id, SSP_TAXONOMY, SSP_POST_TYPE ) ); ?>"><?php echo $folder ? esc_html__( 'Change folder', 'slam-presenter-profiles' ) : esc_html__( 'Add folder link', 'slam-presenter-profiles' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="ssp-columns">
		<div class="ssp-card">
			<h2><?php esc_html_e( 'Fetch from Google Drive', 'slam-presenter-profiles' ); ?></h2>
			<p><?php esc_html_e( 'Downloads each presenter\'s photo from the Drive link in the form response, adds it to the Media Library and sets it as the profile photo.', 'slam-presenter-profiles' ); ?></p>
			<p class="ssp-callout">
				<?php esc_html_e( 'Google Form uploads are private by default. In Drive, open the form\'s upload folder → Share → General access → "Anyone with the link" (Viewer). Fetching works after that.', 'slam-presenter-profiles' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary ssp-fetch-all" data-mode="missing">
					<?php
					echo $current && ! is_wp_error( $current )
						/* translators: %s: slam name */
						? esc_html( sprintf( __( 'Fetch missing photos: %s', 'slam-presenter-profiles' ), $current->name ) )
						: esc_html__( 'Fetch missing photos: all slams', 'slam-presenter-profiles' );
					?>
				</button>
				<button type="button" class="button ssp-fetch-all" data-mode="all"><?php esc_html_e( 'Re-fetch all shown', 'slam-presenter-profiles' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Only the presenters listed below are fetched; choose a slam above to fetch just its presenters (eg. new people in the final).', 'slam-presenter-profiles' ); ?>
			</p>
			<div class="ssp-progress" hidden><div class="ssp-progress-bar"></div><span class="ssp-progress-text"></span></div>
		</div>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-card">
			<input type="hidden" name="action" value="ssp_photos_zip">
			<?php wp_nonce_field( 'ssp_photos_zip' ); ?>
			<h2><?php esc_html_e( 'Upload a ZIP of photos', 'slam-presenter-profiles' ); ?></h2>
			<p><?php esc_html_e( 'If the Drive folder cannot be shared: in Drive, right-click the upload folder → Download. Upload that ZIP here. Files are matched to presenters by the English name in the file name (Google Forms names them "photo - Presenter Name.jpg").', 'slam-presenter-profiles' ); ?></p>
			<p><input type="file" name="ssp_zip" accept=".zip,application/zip" required></p>
			<p><label><input type="checkbox" name="ssp_replace" value="1"> <?php esc_html_e( 'Replace existing photos', 'slam-presenter-profiles' ); ?></label></p>
			<?php submit_button( __( 'Upload and match', 'slam-presenter-profiles' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>

	<div class="ssp-photo-grid" data-queue="<?php echo esc_attr( wp_json_encode( $queue ? array_map( 'intval', $queue ) : array() ) ); ?>" data-autostart="<?php echo isset( $_GET['autostart'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification ?>">
		<?php foreach ( $presenters as $p ) : ?>
			<?php
			$has   = has_post_thumbnail( $p );
			$drive = SSP_Presenter::get( $p->ID, 'photo_drive_url' );
			$error = SSP_Presenter::get( $p->ID, 'photo_error' );
			?>
			<?php $crop = SSP_Photos::crop_data( $p->ID ); ?>
			<div class="ssp-photo-card <?php echo $has ? 'has-photo' : 'no-photo'; ?>" data-post="<?php echo (int) $p->ID; ?>" data-drive="<?php echo $drive ? '1' : '0'; ?>" data-crop="<?php echo esc_attr( $crop ? wp_json_encode( $crop ) : '' ); ?>" data-name="<?php echo esc_attr( SSP_Presenter::display_name( $p->ID ) ); ?>">
				<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" class="ssp-photo-frame">
					<?php echo SSP_Presenter::photo_html( $p->ID, 'ssp-square', 'ssp-photo-card-img' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
				<strong><?php echo esc_html( SSP_Presenter::display_name( $p->ID ) ); ?></strong>
				<span class="ssp-muted"><?php echo esc_html( SSP_Presenter::get( $p->ID, 'name_en' ) ); ?></span>
				<span class="ssp-photo-status <?php echo $error ? 'is-error' : ''; ?>">
					<?php
					if ( $error ) {
						echo esc_html( $error );
					} elseif ( $has ) {
						esc_html_e( 'Photo set', 'slam-presenter-profiles' );
					} elseif ( $drive ) {
						esc_html_e( 'Drive link, not fetched', 'slam-presenter-profiles' );
					} else {
						esc_html_e( 'No photo', 'slam-presenter-profiles' );
					}
					?>
				</span>
				<span class="ssp-photo-actions">
					<button type="button" class="button-link ssp-adjust"<?php echo $crop ? '' : ' hidden'; ?>><?php esc_html_e( 'Adjust', 'slam-presenter-profiles' ); ?></button><?php echo $crop ? ' · ' : ''; ?>
					<?php if ( $drive ) : ?>
						<a href="<?php echo esc_url( $drive ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Drive', 'slam-presenter-profiles' ); ?></a> ·
						<button type="button" class="button-link ssp-fetch-one"><?php esc_html_e( 'Fetch', 'slam-presenter-profiles' ); ?></button> ·
					<?php endif; ?>
					<button type="button" class="button-link ssp-edit-drive"><?php echo $drive ? esc_html__( 'Edit link', 'slam-presenter-profiles' ) : esc_html__( 'Add Drive link', 'slam-presenter-profiles' ); ?></button> ·
					<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php esc_html_e( 'Edit / upload', 'slam-presenter-profiles' ); ?></a>
				</span>
				<span class="ssp-drive-edit" hidden>
					<input type="url" class="ssp-drive-input" value="<?php echo esc_attr( $drive ); ?>" placeholder="https://drive.google.com/file/d/…" aria-label="<?php esc_attr_e( 'Google Drive link', 'slam-presenter-profiles' ); ?>">
					<button type="button" class="button button-small button-primary ssp-drive-save"><?php esc_html_e( 'Save & fetch', 'slam-presenter-profiles' ); ?></button>
					<button type="button" class="button-link ssp-drive-cancel"><?php esc_html_e( 'Cancel', 'slam-presenter-profiles' ); ?></button>
				</span>
			</div>
		<?php endforeach; ?>
	</div>

	<dialog class="ssp-crop-dialog" aria-labelledby="ssp-crop-title">
		<form method="dialog">
			<h2 id="ssp-crop-title"></h2>
			<div class="ssp-crop-dialog-host"></div>
			<p class="ssp-crop-dialog-actions">
				<span class="ssp-crop-dialog-status"></span>
				<button type="button" class="button ssp-crop-cancel"><?php esc_html_e( 'Cancel', 'slam-presenter-profiles' ); ?></button>
				<button type="button" class="button button-primary ssp-crop-save"><?php esc_html_e( 'Save', 'slam-presenter-profiles' ); ?></button>
			</p>
		</form>
	</dialog>
</div>
