<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$step   = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$rows   = 'map' === $step ? get_transient( 'ssp_import_' . get_current_user_id() ) : false;
$target = 'map' === $step ? get_transient( 'ssp_import_' . get_current_user_id() . '_target' ) : false;
$target = 'venue' === $target || false === $target ? 0 : (int) $target;
$events = SSP_Post_Types::get_events();

$import_result = get_transient( 'ssp_import_result_' . get_current_user_id() );
if ( false !== $import_result ) {
	delete_transient( 'ssp_import_result_' . get_current_user_id() );
}
?>
<div class="wrap ssp-wrap">
	<h1><?php esc_html_e( 'Import / Export Presenters', 'slam-presenter-profiles' ); ?></h1>

	<?php if ( isset( $_GET['ssp_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ssp_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
	<?php endif; ?>

	<?php if ( $import_result ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				/* translators: 1: created count, 2: updated count */
				printf( esc_html__( 'Import complete: %1$d presenter(s) created, %2$d updated.', 'slam-presenter-profiles' ), (int) $import_result['created'], (int) $import_result['updated'] );
				?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-table' ) ); ?>"><?php esc_html_e( 'View presenters', 'slam-presenter-profiles' ); ?></a>
			</p>
		</div>
		<?php if ( ! empty( $import_result['skipped'] ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Notes on rows:', 'slam-presenter-profiles' ); ?></p>
				<ul class="ssp-scroll-list">
					<?php foreach ( $import_result['skipped'] as $msg ) : ?>
						<li><?php echo esc_html( $msg ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( is_array( $rows ) ) : ?>
		<?php
		$header  = $rows[0];
		$data    = array_slice( $rows, 1 );
		$targets = SSP_Import::targets();
		$guesses = array();
		foreach ( $header as $i => $col ) {
			$sample        = isset( $data[0][ $i ] ) ? $data[0][ $i ] : '';
			$guess         = SSP_Import::guess_target( $col, $sample );
			$guesses[ $i ] = in_array( $guess, $guesses, true ) ? '' : $guess;
		}
		$event_col = array_search( 'event', $guesses, true );
		$venues    = array();
		if ( false !== $event_col ) {
			foreach ( $data as $row ) {
				$v = isset( $row[ $event_col ] ) ? trim( $row[ $event_col ] ) : '';
				if ( '' !== $v ) {
					$venues[ $v ] = isset( $venues[ $v ] ) ? $venues[ $v ] + 1 : 1;
				}
			}
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-card">
			<input type="hidden" name="action" value="ssp_import_run">
			<?php wp_nonce_field( 'ssp_import_run' ); ?>

			<h2><?php esc_html_e( 'Step 2 · Match columns', 'slam-presenter-profiles' ); ?></h2>
			<p>
				<?php
				/* translators: %d: number of rows */
				printf( esc_html__( '%d rows found. Check what each column should be imported as. The first data row is shown as a sample.', 'slam-presenter-profiles' ), count( $data ) );
				?>
			</p>
			<table class="widefat striped ssp-map-table">
				<thead><tr>
					<th><?php esc_html_e( 'CSV column', 'slam-presenter-profiles' ); ?></th>
					<th><?php esc_html_e( 'Sample', 'slam-presenter-profiles' ); ?></th>
					<th><?php esc_html_e( 'Import as', 'slam-presenter-profiles' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $header as $i => $col ) : ?>
					<tr>
						<td><strong><?php echo esc_html( wp_trim_words( $col, 14 ) ); ?></strong></td>
						<td class="ssp-sample"><?php echo esc_html( wp_trim_words( isset( $data[0][ $i ] ) ? $data[0][ $i ] : '', 16 ) ); ?></td>
						<td>
							<select name="ssp_map[<?php echo (int) $i; ?>]" class="ssp-map-select">
								<?php foreach ( $targets as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $guesses[ $i ], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Slam events', 'slam-presenter-profiles' ); ?></h2>
			<?php if ( $venues ) : ?>
				<p><?php esc_html_e( 'Each venue in the CSV becomes a slam event. Pick an existing event or create a new one.', 'slam-presenter-profiles' ); ?></p>
				<table class="widefat striped">
					<tbody>
					<?php foreach ( $venues as $venue => $count ) : ?>
						<?php
						$match = $target;
						foreach ( $target ? array() : $events as $ev ) {
							if ( $ev->name === $venue || false !== mb_stripos( $venue, $ev->name ) ) {
								$match = $ev->term_id;
							}
						}
						$short = trim( preg_replace( '/^.*[–—-]\s*/u', '', $venue ) );
						?>
						<tr>
							<td><?php echo esc_html( $venue ); ?> <span class="count">(<?php echo (int) $count; ?>)</span></td>
							<td>
								<select name="ssp_event_map[<?php echo esc_attr( md5( $venue ) ); ?>]">
									<option value=""><?php esc_html_e( '— Use default event below —', 'slam-presenter-profiles' ); ?></option>
									<?php foreach ( $events as $ev ) : ?>
										<option value="<?php echo (int) $ev->term_id; ?>" <?php selected( $match, $ev->term_id ); ?>><?php echo esc_html( $ev->name ); ?></option>
									<?php endforeach; ?>
									<option value="<?php echo esc_attr( 'new:' . $short ); ?>" <?php selected( $match, 0 ); ?>>
										<?php
										/* translators: %s: new event name */
										printf( esc_html__( '+ Create event "%s"', 'slam-presenter-profiles' ), esc_html( $short ) );
										?>
									</option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="ssp_default_event"><?php esc_html_e( 'Default event', 'slam-presenter-profiles' ); ?></label></th>
					<td>
						<select name="ssp_default_event" id="ssp_default_event">
							<option value="0"><?php esc_html_e( '— None —', 'slam-presenter-profiles' ); ?></option>
							<?php foreach ( $events as $ev ) : ?>
								<option value="<?php echo (int) $ev->term_id; ?>" <?php selected( $target, $ev->term_id ); ?>><?php echo esc_html( $ev->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used for rows without a venue, or venues left on "use default".', 'slam-presenter-profiles' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Publish', 'slam-presenter-profiles' ); ?></th>
					<td>
						<label><input type="radio" name="ssp_status" value="publish" checked> <?php esc_html_e( 'Publish profiles now', 'slam-presenter-profiles' ); ?></label><br>
						<label><input type="radio" name="ssp_status" value="draft"> <?php esc_html_e( 'Save as drafts for review', 'slam-presenter-profiles' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Existing presenters', 'slam-presenter-profiles' ); ?></th>
					<td>
						<label><input type="checkbox" name="ssp_overwrite" value="1"> <?php esc_html_e( 'Overwrite fields that were already edited on the site', 'slam-presenter-profiles' ); ?></label>
						<p class="description"><?php esc_html_e( 'A presenter with the same English name in the same event is updated instead of duplicated. Unticked: only empty fields are filled in.', 'slam-presenter-profiles' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Photos', 'slam-presenter-profiles' ); ?></th>
					<td>
						<label><input type="checkbox" name="ssp_fetch_photos" value="1" checked> <?php esc_html_e( 'Fetch photos from Google Drive after import', 'slam-presenter-profiles' ); ?></label>
					</td>
				</tr>
			</table>

			<p>
				<?php submit_button( __( 'Import presenters', 'slam-presenter-profiles' ), 'primary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-import' ) ); ?>"><?php esc_html_e( 'Cancel', 'slam-presenter-profiles' ); ?></a>
			</p>
		</form>
	<?php else : ?>
		<div class="ssp-columns">
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-card">
				<input type="hidden" name="action" value="ssp_import_upload">
				<?php wp_nonce_field( 'ssp_import_upload' ); ?>
				<h2><?php esc_html_e( 'Import from CSV', 'slam-presenter-profiles' ); ?></h2>
				<p><?php esc_html_e( 'Download the Google Form responses sheet as CSV (File → Download → Comma separated values) and upload it here. You can import each centre\'s sheet separately; re-importing updates presenters instead of duplicating them.', 'slam-presenter-profiles' ); ?></p>
				<p>
					<label for="ssp_target_event"><strong><?php esc_html_e( '1. Import into which slam?', 'slam-presenter-profiles' ); ?></strong></label><br>
					<select name="ssp_target_event" id="ssp_target_event" required>
						<option value=""><?php esc_html_e( '— Choose a slam —', 'slam-presenter-profiles' ); ?></option>
						<?php foreach ( $events as $ev ) : ?>
							<option value="<?php echo (int) $ev->term_id; ?>"><?php echo esc_html( $ev->name ); ?></option>
						<?php endforeach; ?>
						<option value="venue"><?php esc_html_e( 'Use the CSV\'s venue column (match or create slams)', 'slam-presenter-profiles' ); ?></option>
					</select>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&new=1' ) ); ?>"><?php esc_html_e( 'New slam…', 'slam-presenter-profiles' ); ?></a>
				</p>
				<p>
					<label><strong><?php esc_html_e( '2. Choose the CSV file', 'slam-presenter-profiles' ); ?></strong></label><br>
					<input type="file" name="ssp_csv" accept=".csv,text/csv" required>
				</p>
				<?php submit_button( __( 'Upload and match columns', 'slam-presenter-profiles' ), 'primary', 'submit', false ); ?>
			</form>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-card">
				<input type="hidden" name="action" value="ssp_export">
				<?php wp_nonce_field( 'ssp_export', '_wpnonce', false ); ?>
				<h2><?php esc_html_e( 'Export presenters', 'slam-presenter-profiles' ); ?></h2>
				<p><?php esc_html_e( 'Download presenters with their slam, time slot, talk, contact details, links, profile and photo URLs, eg. for the brochure or certificates.', 'slam-presenter-profiles' ); ?></p>
				<p>
					<label for="ssp_export_event"><strong><?php esc_html_e( 'Which presenters', 'slam-presenter-profiles' ); ?></strong></label><br>
					<select name="event" id="ssp_export_event">
						<option value="0"><?php esc_html_e( 'All slams in this edition', 'slam-presenter-profiles' ); ?></option>
						<?php foreach ( $events as $ev ) : ?>
							<option value="<?php echo (int) $ev->term_id; ?>"><?php echo esc_html( $ev->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="ssp-export-formats">
					<strong><?php esc_html_e( 'Format', 'slam-presenter-profiles' ); ?></strong><br>
					<label><input type="radio" name="format" value="xlsx" checked> <?php esc_html_e( 'Excel (.xlsx)', 'slam-presenter-profiles' ); ?></label>
					<label><input type="radio" name="format" value="csv"> <?php esc_html_e( 'CSV (.csv)', 'slam-presenter-profiles' ); ?></label>
				</p>
				<p class="description"><?php esc_html_e( 'A single slam is exported in timetable order. The file includes email and phone numbers: share it with care.', 'slam-presenter-profiles' ); ?></p>
				<?php submit_button( __( 'Download', 'slam-presenter-profiles' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	<?php endif; ?>
</div>
