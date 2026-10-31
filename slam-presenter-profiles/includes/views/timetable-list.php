<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base = admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable' );
?>
<table class="widefat striped ssp-tt-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Slam', 'slam-presenter-profiles' ); ?></th>
			<th><?php esc_html_e( 'Status', 'slam-presenter-profiles' ); ?></th>
			<th><?php esc_html_e( 'Type', 'slam-presenter-profiles' ); ?></th>
			<th><?php esc_html_e( 'Date & venue', 'slam-presenter-profiles' ); ?></th>
			<th><?php esc_html_e( 'Talks', 'slam-presenter-profiles' ); ?></th>
			<th><?php esc_html_e( 'Day', 'slam-presenter-profiles' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'slam-presenter-profiles' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $events as $ev ) : ?>
		<?php
		$meta      = SSP_Post_Types::get_event_meta( $ev->term_id );
		$items     = SSP_Timetable::computed( $ev->term_id );
		$talks     = count( SSP_Timetable::get_talk_ids( $ev->term_id ) );
		$slots     = count( array_filter( $items, function ( $i ) { return 'talk' === $i['type']; } ) );
		$assigned  = count( SSP_Presenter::get_event_presenter_ids( $ev->term_id ) );
		$last      = $items ? end( $items ) : null;
		$edit      = add_query_arg( 'event', $ev->term_id, $base );
		$view      = SSP_Post_Types::schedule_url( $ev );
		$is_final  = 'final' === $meta['event_type'];
		?>
		<tr>
			<td>
				<strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $ev->name ); ?></a></strong>
				<div class="ssp-muted"><code>[slam_timetable event="<?php echo esc_html( $ev->slug ); ?>"]</code></div>
			</td>
			<td class="ssp-status-cell">
				<?php if ( 'publish' === $meta['status'] ) : ?>
					<span class="ssp-status-badge is-live"><?php esc_html_e( 'Published', 'slam-presenter-profiles' ); ?></span>
					<a class="button-link" href="<?php echo esc_url( SSP_Timetable::status_url( $ev->term_id, 'draft' ) ); ?>"><?php esc_html_e( 'Unpublish', 'slam-presenter-profiles' ); ?></a>
				<?php else : ?>
					<span class="ssp-status-badge"><?php esc_html_e( 'Draft', 'slam-presenter-profiles' ); ?></span>
					<a class="button button-small" href="<?php echo esc_url( SSP_Timetable::status_url( $ev->term_id, 'publish' ) ); ?>"><?php esc_html_e( 'Publish', 'slam-presenter-profiles' ); ?></a>
				<?php endif; ?>
			</td>
			<td><span class="ssp-type-badge<?php echo $is_final ? ' is-final' : ''; ?>"><?php echo $is_final ? esc_html__( 'Final', 'slam-presenter-profiles' ) : esc_html__( 'Regional', 'slam-presenter-profiles' ); ?></span></td>
			<td>
				<?php echo $meta['event_date'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $meta['event_date'] ) ) ) : '—'; ?>
				<div class="ssp-muted"><?php echo esc_html( implode( ', ', array_filter( array( $meta['venue'], $meta['city'] ) ) ) ); ?></div>
			</td>
			<td>
				<?php
				/* translators: 1: scheduled talks, 2: talk slots */
				echo esc_html( sprintf( __( '%1$d of %2$d slots filled', 'slam-presenter-profiles' ), $talks, $slots ) );
				?>
				<div class="ssp-muted">
					<?php
					/* translators: %d: presenters in the event */
					echo esc_html( sprintf( _n( '%d presenter in event', '%d presenters in event', $assigned, 'slam-presenter-profiles' ), $assigned ) );
					?>
					<?php if ( $assigned > $talks ) : ?>
						<span class="ssp-warn-text">
							<?php
							/* translators: %d: presenters without a slot */
							echo esc_html( sprintf( __( '· %d without a slot', 'slam-presenter-profiles' ), $assigned - $talks ) );
							?>
						</span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo $items ? esc_html( SSP_Timetable::format_minutes( $items[0]['start'] ) . ' – ' . SSP_Timetable::format_minutes( isset( $last['end'] ) ? $last['end'] : $last['start'] ) ) : '<span class="ssp-muted">' . esc_html__( 'Empty', 'slam-presenter-profiles' ) . '</span>'; ?></td>
			<td class="ssp-tt-actions">
				<a class="button button-primary" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit timetable', 'slam-presenter-profiles' ); ?></a>
				<?php if ( $view ) : ?>
					<a class="button" href="<?php echo esc_url( $view ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'slam-presenter-profiles' ); ?> ↗</a>
				<?php else : ?>
					<span class="button disabled" title="<?php esc_attr_e( 'No page shows this timetable yet. Add the shortcode to a page, or set the Schedule page in the event details.', 'slam-presenter-profiles' ); ?>"><?php esc_html_e( 'View', 'slam-presenter-profiles' ); ?></span>
				<?php endif; ?>
				<a class="button-link" href="<?php echo esc_url( get_edit_term_link( $ev->term_id, SSP_TAXONOMY, SSP_POST_TYPE ) ); ?>"><?php esc_html_e( 'Event details', 'slam-presenter-profiles' ); ?></a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
