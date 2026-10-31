<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$events  = SSP_Post_Types::get_events();
$term_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$term    = $term_id ? get_term( $term_id, SSP_TAXONOMY ) : null;
?>
<div class="wrap ssp-wrap ssp-timetable-wrap">
	<h1 class="wp-heading-inline"><?php echo $term && ! is_wp_error( $term ) ? esc_html__( 'Timetable Builder', 'slam-presenter-profiles' ) : esc_html__( 'Timetables', 'slam-presenter-profiles' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable&new=1' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Timetable', 'slam-presenter-profiles' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['ssp_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ssp_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['ssp_status_changed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php echo 'draft' === $_GET['ssp_status_changed'] ? esc_html__( 'Timetable unpublished: visitors no longer see it.', 'slam-presenter-profiles' ) : esc_html__( 'Timetable published: it is now visible on the website.', 'slam-presenter-profiles' ); // phpcs:ignore WordPress.Security.NonceVerification ?>
		</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php esc_html_e( 'Timetable created. Add sessions, talks and breaks below, then Save.', 'slam-presenter-profiles' ); ?>
			<?php if ( ! empty( $_GET['unmatched'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<?php
				/* translators: %d: number of rows */
				printf( esc_html__( '%d talk(s) from the pasted text could not be matched to a presenter: choose them in the red rows.', 'slam-presenter-profiles' ), absint( $_GET['unmatched'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				?>
			<?php endif; ?>
		</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['new'] ) || ! $events ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<?php require SSP_PLUGIN_DIR . 'includes/views/timetable-new.php'; ?>
	</div>
		<?php
		return;
	endif;
	if ( ! $term || is_wp_error( $term ) ) :
		require SSP_PLUGIN_DIR . 'includes/views/timetable-list.php';
		?>
	</div>
		<?php
		return;
	endif;
	$meta = SSP_Post_Types::get_event_meta( $term_id );
	?>

	<p class="ssp-back"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable' ) ); ?>">← <?php esc_html_e( 'All timetables', 'slam-presenter-profiles' ); ?></a></p>
	<form method="get" class="ssp-event-switch">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( SSP_POST_TYPE ); ?>">
		<input type="hidden" name="page" value="ssp-timetable">
		<select name="event" onchange="this.form.submit()">
			<?php foreach ( $events as $ev ) : ?>
				<option value="<?php echo (int) $ev->term_id; ?>" <?php selected( $term_id, $ev->term_id ); ?>><?php echo esc_html( $ev->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<span class="ssp-muted">
			<?php echo esc_html( trim( $meta['event_date'] . ' · ' . $meta['venue'], ' ·' ) ); ?>
			<a href="<?php echo esc_url( get_edit_term_link( $term_id, SSP_TAXONOMY, SSP_POST_TYPE ) ); ?>"><?php esc_html_e( 'Edit event', 'slam-presenter-profiles' ); ?></a>
		</span>
		<span class="ssp-status-cell">
			<?php if ( 'publish' === $meta['status'] ) : ?>
				<span class="ssp-status-badge is-live"><?php esc_html_e( 'Published', 'slam-presenter-profiles' ); ?></span>
				<a class="button" href="<?php echo esc_url( SSP_Timetable::status_url( $term_id, 'draft' ) ); ?>" onclick="return !document.querySelector('.ssp-tt-dirty:not([hidden])') || confirm('<?php echo esc_js( __( 'You have unsaved changes. Leave without saving?', 'slam-presenter-profiles' ) ); ?>');"><?php esc_html_e( 'Unpublish', 'slam-presenter-profiles' ); ?></a>
			<?php else : ?>
				<span class="ssp-status-badge"><?php esc_html_e( 'Draft: not visible on the website', 'slam-presenter-profiles' ); ?></span>
				<a class="button button-primary" href="<?php echo esc_url( SSP_Timetable::status_url( $term_id, 'publish' ) ); ?>" onclick="return !document.querySelector('.ssp-tt-dirty:not([hidden])') || confirm('<?php echo esc_js( __( 'You have unsaved changes. Leave without saving?', 'slam-presenter-profiles' ) ); ?>');"><?php esc_html_e( 'Publish', 'slam-presenter-profiles' ); ?></a>
			<?php endif; ?>
		</span>
	</form>

	<div class="ssp-tt-layout">
		<div class="ssp-tt-main">
			<div class="ssp-tt-toolbar">
				<label><?php esc_html_e( 'Day starts at', 'slam-presenter-profiles' ); ?> <input type="time" id="ssp-tt-start"></label>
				<label title="<?php esc_attr_e( 'More adds empty slots after the last talk; fewer removes empty slots from the end.', 'slam-presenter-profiles' ); ?>"><?php esc_html_e( 'Talk slots', 'slam-presenter-profiles' ); ?> <input type="number" id="ssp-tt-slots" min="0" max="200" class="small-text"></label>
				<span class="ssp-tt-summary"></span>
				<span class="ssp-tt-dirty" hidden></span>
				<button type="button" class="button button-primary" id="ssp-tt-save"><?php esc_html_e( 'Save timetable', 'slam-presenter-profiles' ); ?></button>
			</div>
			<ol class="ssp-tt-list" id="ssp-tt-list"></ol>
			<p class="ssp-tt-add">
				<button type="button" class="button" data-add="session"><?php esc_html_e( '+ Session heading', 'slam-presenter-profiles' ); ?></button>
				<button type="button" class="button" data-add="talk"><?php esc_html_e( '+ Talk', 'slam-presenter-profiles' ); ?></button>
				<button type="button" class="button" data-add="break"><?php esc_html_e( '+ Break', 'slam-presenter-profiles' ); ?></button>
				<button type="button" class="button" data-add="item"><?php esc_html_e( '+ Programme item', 'slam-presenter-profiles' ); ?></button>
				<button type="button" class="button ssp-open-select"><?php esc_html_e( '+ Select presenters…', 'slam-presenter-profiles' ); ?></button>
				<button type="button" class="button-link ssp-danger" id="ssp-tt-clear"><?php esc_html_e( 'Clear all', 'slam-presenter-profiles' ); ?></button>
			</p>
		</div>

		<aside class="ssp-tt-side">
			<div class="ssp-card ssp-select-card">
				<?php $is_final = 'final' === $meta['event_type']; ?>
				<h2><?php echo $is_final ? esc_html__( 'Finalists', 'slam-presenter-profiles' ) : esc_html__( 'Add from the slams', 'slam-presenter-profiles' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Tick presenters from each regional slam in one list and add them to this timetable.', 'slam-presenter-profiles' ); ?></p>
				<p><button type="button" class="button button-primary ssp-open-select"><?php echo $is_final ? esc_html__( 'Select finalists…', 'slam-presenter-profiles' ) : esc_html__( 'Select presenters from slams…', 'slam-presenter-profiles' ); ?></button></p>
			</div>

			<div class="ssp-card">
				<h2><?php esc_html_e( 'Not yet scheduled', 'slam-presenter-profiles' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Presenters of this event. Click to add at the end, or drag into the timetable.', 'slam-presenter-profiles' ); ?></p>
				<ul class="ssp-tt-pool" id="ssp-tt-pool"></ul>
			</div>

			<details class="ssp-card" open>
				<summary><h2><?php esc_html_e( 'Auto-generate', 'slam-presenter-profiles' ); ?></h2></summary>
				<p class="description"><?php esc_html_e( 'Builds the whole day from this event\'s presenters: inauguration, sessions with tea and lunch breaks, valedictory.', 'slam-presenter-profiles' ); ?></p>
				<div class="ssp-gen-grid">
					<label><?php esc_html_e( 'Start', 'slam-presenter-profiles' ); ?> <input type="time" id="ssp-gen-start" value="09:30"></label>
					<label><?php esc_html_e( 'Talk (min)', 'slam-presenter-profiles' ); ?> <input type="number" id="ssp-gen-talk" value="<?php echo (int) SSP_Settings::get( 'talk_minutes' ); ?>" min="1"></label>
					<label><?php esc_html_e( 'Talks / session', 'slam-presenter-profiles' ); ?> <input type="number" id="ssp-gen-per" value="8" min="1"></label>
					<label><?php esc_html_e( 'Tea (min)', 'slam-presenter-profiles' ); ?> <input type="number" id="ssp-gen-tea" value="15" min="0"></label>
					<label><?php esc_html_e( 'Lunch before session', 'slam-presenter-profiles' ); ?> <input type="number" id="ssp-gen-lunch-at" value="3" min="2"></label>
					<label><?php esc_html_e( 'Lunch (min)', 'slam-presenter-profiles' ); ?> <input type="number" id="ssp-gen-lunch" value="60" min="0"></label>
					<label><?php esc_html_e( 'Order', 'slam-presenter-profiles' ); ?>
						<select id="ssp-gen-order">
							<option value="title"><?php esc_html_e( 'By name', 'slam-presenter-profiles' ); ?></option>
							<option value="random"><?php esc_html_e( 'Random draw', 'slam-presenter-profiles' ); ?></option>
						</select>
					</label>
				</div>
				<p><button type="button" class="button" id="ssp-tt-generate"><?php esc_html_e( 'Generate timetable', 'slam-presenter-profiles' ); ?></button></p>
			</details>

			<details class="ssp-card">
				<summary><h2><?php esc_html_e( 'Import from text', 'slam-presenter-profiles' ); ?></h2></summary>
				<p class="description"><?php esc_html_e( 'Paste a timetable in the usual format. Lines like "10:00 AM – 10:12 AM | Name — Title" become talks; presenters are matched by name or title.', 'slam-presenter-profiles' ); ?></p>
				<textarea id="ssp-tt-text" rows="8" class="large-text" placeholder="10:00 AM – 10:12 AM | മനു മാധവൻ — lncRNA: …"></textarea>
				<p><button type="button" class="button" id="ssp-tt-parse"><?php esc_html_e( 'Import text', 'slam-presenter-profiles' ); ?></button></p>
			</details>

			<div class="ssp-card">
				<h2><?php esc_html_e( 'Show on the website', 'slam-presenter-profiles' ); ?></h2>
				<p><?php esc_html_e( 'Add these shortcodes to any page:', 'slam-presenter-profiles' ); ?></p>
				<p><code>[slam_timetable event="<?php echo esc_html( $term->slug ); ?>"]</code></p>
				<p><code>[slam_presenters event="<?php echo esc_html( $term->slug ); ?>"]</code></p>
			</div>
		</aside>
	</div>
</div>
