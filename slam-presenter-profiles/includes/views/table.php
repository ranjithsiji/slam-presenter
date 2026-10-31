<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ssp-wrap ssp-table-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Presenters', 'slam-presenter-profiles' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SSP_POST_TYPE ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Presenter', 'slam-presenter-profiles' ); ?></a>
	<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-import' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import CSV', 'slam-presenter-profiles' ); ?></a>
	<hr class="wp-header-end">

	<div class="ssp-stats" id="ssp-stats"></div>

	<div class="ssp-dt-toolbar">
		<input type="search" id="ssp-dt-search" class="ssp-dt-search" placeholder="<?php esc_attr_e( 'Search name, institution, talk title…', 'slam-presenter-profiles' ); ?>" autofocus>
		<select id="ssp-dt-event"><option value=""><?php esc_html_e( 'All events', 'slam-presenter-profiles' ); ?></option></select>
		<select id="ssp-dt-photo">
			<option value=""><?php esc_html_e( 'Any photo status', 'slam-presenter-profiles' ); ?></option>
			<option value="yes"><?php esc_html_e( 'Has photo', 'slam-presenter-profiles' ); ?></option>
			<option value="no"><?php esc_html_e( 'Missing photo', 'slam-presenter-profiles' ); ?></option>
		</select>
		<select id="ssp-dt-status">
			<option value=""><?php esc_html_e( 'Any status', 'slam-presenter-profiles' ); ?></option>
			<option value="publish"><?php esc_html_e( 'Published', 'slam-presenter-profiles' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Draft', 'slam-presenter-profiles' ); ?></option>
		</select>
		<select id="ssp-dt-per">
			<option value="25">25</option>
			<option value="50" selected>50</option>
			<option value="100">100</option>
			<option value="0"><?php esc_html_e( 'All', 'slam-presenter-profiles' ); ?></option>
		</select>
	</div>

	<div class="ssp-dt-bulk" id="ssp-dt-bulk" hidden>
		<span id="ssp-dt-selected"></span>
		<select id="ssp-dt-bulk-action">
			<option value=""><?php esc_html_e( 'Bulk action…', 'slam-presenter-profiles' ); ?></option>
			<option value="add_event"><?php esc_html_e( 'Add to event', 'slam-presenter-profiles' ); ?></option>
			<option value="remove_event"><?php esc_html_e( 'Remove from event', 'slam-presenter-profiles' ); ?></option>
			<option value="publish"><?php esc_html_e( 'Publish', 'slam-presenter-profiles' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Move to draft', 'slam-presenter-profiles' ); ?></option>
			<option value="trash"><?php esc_html_e( 'Move to trash', 'slam-presenter-profiles' ); ?></option>
		</select>
		<select id="ssp-dt-bulk-event" hidden></select>
		<button type="button" class="button" id="ssp-dt-bulk-apply"><?php esc_html_e( 'Apply', 'slam-presenter-profiles' ); ?></button>
	</div>

	<table class="widefat ssp-dt" id="ssp-dt">
		<thead>
			<tr>
				<td class="check-column"><input type="checkbox" id="ssp-dt-all" aria-label="<?php esc_attr_e( 'Select all', 'slam-presenter-profiles' ); ?>"></td>
				<th class="ssp-dt-photo-col"></th>
				<th data-sort="title" class="is-sortable"><?php esc_html_e( 'Name', 'slam-presenter-profiles' ); ?></th>
				<th data-sort="institution" class="is-sortable"><?php esc_html_e( 'Institution', 'slam-presenter-profiles' ); ?></th>
				<th data-sort="talk_title" class="is-sortable"><?php esc_html_e( 'Presentation', 'slam-presenter-profiles' ); ?></th>
				<th data-sort="events" class="is-sortable"><?php esc_html_e( 'Event', 'slam-presenter-profiles' ); ?></th>
				<th data-sort="slot" class="is-sortable"><?php esc_html_e( 'Slot', 'slam-presenter-profiles' ); ?></th>
				<th data-sort="completeness" class="is-sortable" title="<?php esc_attr_e( 'Profile completeness', 'slam-presenter-profiles' ); ?>"><?php esc_html_e( 'Profile', 'slam-presenter-profiles' ); ?></th>
			</tr>
		</thead>
		<tbody id="ssp-dt-body"></tbody>
	</table>

	<div class="ssp-dt-footer">
		<span id="ssp-dt-info"></span>
		<span class="ssp-dt-pager" id="ssp-dt-pager"></span>
	</div>
</div>
