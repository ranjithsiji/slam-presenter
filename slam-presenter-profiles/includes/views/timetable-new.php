<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$events = SSP_Post_Types::get_events();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssp-card ssp-new-timetable">
	<input type="hidden" name="action" value="ssp_new_timetable">
	<?php wp_nonce_field( 'ssp_new_timetable' ); ?>
	<h2><?php esc_html_e( 'Add New Timetable', 'slam-presenter-profiles' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Each timetable belongs to a slam event (a regional centre or the final). Fill in the event, choose how to start, then build the day in the timetable builder.', 'slam-presenter-profiles' ); ?></p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="ssp_new_edition"><?php esc_html_e( 'Edition', 'slam-presenter-profiles' ); ?></label></th>
			<td>
				<select id="ssp_new_edition" name="ssp_new[edition]">
					<?php foreach ( SSP_Editions::all() as $edition ) : ?>
						<option value="<?php echo (int) $edition->term_id; ?>" <?php selected( SSP_Editions::current(), $edition->term_id ); ?>><?php echo esc_html( $edition->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . SSP_Editions::TAXONOMY . '&post_type=' . SSP_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add edition', 'slam-presenter-profiles' ); ?></a>
				<p class="description"><?php esc_html_e( 'The year\'s set of slams this one belongs to.', 'slam-presenter-profiles' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Slam type', 'slam-presenter-profiles' ); ?></th>
			<td>
				<fieldset class="ssp-new-type">
					<label><input type="radio" name="ssp_new[event_type]" value="regional" checked> <?php esc_html_e( 'Regional slam', 'slam-presenter-profiles' ); ?></label>
					<label><input type="radio" name="ssp_new[event_type]" value="final"> <?php esc_html_e( 'Final slam', 'slam-presenter-profiles' ); ?></label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Presenters in a final show the final on their profile banner.', 'slam-presenter-profiles' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_name"><?php esc_html_e( 'Event name', 'slam-presenter-profiles' ); ?></label></th>
			<td><input type="text" id="ssp_new_name" name="ssp_new[name]" class="regular-text" required placeholder="<?php esc_attr_e( 'eg. Kerala Science Slam Final 2026', 'slam-presenter-profiles' ); ?>">
				<p class="description"><?php esc_html_e( 'Shown on the timetable and in the admin.', 'slam-presenter-profiles' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_slug"><?php esc_html_e( 'Short name (for pages)', 'slam-presenter-profiles' ); ?></label></th>
			<td>
				<input type="text" id="ssp_new_slug" name="ssp_new[slug]" class="regular-text code" pattern="[a-z0-9-]*" placeholder="final-2026">
				<p class="description">
					<?php esc_html_e( 'Filled in from the event name; lower-case English letters, numbers and dashes. Used in the shortcode:', 'slam-presenter-profiles' ); ?>
					<code class="ssp-slug-preview">[slam_timetable event="…"]</code>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_date"><?php esc_html_e( 'Date', 'slam-presenter-profiles' ); ?></label></th>
			<td><input type="date" id="ssp_new_date" name="ssp_new[event_date]"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_venue"><?php esc_html_e( 'Venue', 'slam-presenter-profiles' ); ?></label></th>
			<td><input type="text" id="ssp_new_venue" name="ssp_new[venue]" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_city"><?php esc_html_e( 'City', 'slam-presenter-profiles' ); ?></label></th>
			<td><input type="text" id="ssp_new_city" name="ssp_new[city]" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_label"><?php esc_html_e( 'Profile banner text', 'slam-presenter-profiles' ); ?></label></th>
			<td>
				<input type="text" id="ssp_new_label" name="ssp_new[label]" class="large-text" placeholder="KERALA SCIENCE SLAM FINAL – DEC 12 – CALICUT">
				<p class="description"><?php esc_html_e( 'Optional. Leave blank to build it from name, date and venue.', 'slam-presenter-profiles' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_folder"><?php esc_html_e( 'Google Drive photo folder', 'slam-presenter-profiles' ); ?></label></th>
			<td>
				<input type="url" id="ssp_new_folder" name="ssp_new[photo_folder]" class="large-text" placeholder="https://drive.google.com/drive/folders/…">
				<p class="description"><?php esc_html_e( 'Optional. Where this slam\'s presenter photos are, eg. for new presenters in the final. Can be changed later in the event details.', 'slam-presenter-profiles' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_start"><?php esc_html_e( 'Day starts at', 'slam-presenter-profiles' ); ?></label></th>
			<td><input type="time" id="ssp_new_start" name="ssp_new[start]" value="09:30"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ssp_new_slots"><?php esc_html_e( 'Number of talk slots', 'slam-presenter-profiles' ); ?></label></th>
			<td>
				<input type="number" id="ssp_new_slots" name="ssp_new[slots]" min="0" max="200" value="24" class="small-text" required>
				<p class="description"><?php esc_html_e( 'How many talks the day has. Copying a layout fills in its number; pasted text sets its own. You can add or remove slots any time later.', 'slam-presenter-profiles' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Start from', 'slam-presenter-profiles' ); ?></th>
			<td>
				<fieldset class="ssp-new-from">
					<label><input type="radio" name="ssp_new[from]" value="blank" checked> <?php esc_html_e( 'Blank timetable: add sessions, talks and breaks in the builder', 'slam-presenter-profiles' ); ?></label>
					<?php if ( $events ) : ?>
						<label>
							<input type="radio" name="ssp_new[from]" value="copy">
							<?php esc_html_e( 'Copy the day layout of', 'slam-presenter-profiles' ); ?>
							<select name="ssp_new[source]">
								<?php foreach ( $events as $ev ) : ?>
									<option value="<?php echo (int) $ev->term_id; ?>" data-slots="<?php echo (int) count( array_filter( SSP_Timetable::get_schedule( $ev->term_id )['items'], function ( $i ) { return 'talk' === $i['type']; } ) ); ?>"><?php echo esc_html( $ev->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( '(sessions, breaks and talk slots; the slots start empty)', 'slam-presenter-profiles' ); ?></span>
						</label>
					<?php endif; ?>
					<label><input type="radio" name="ssp_new[from]" value="text"> <?php esc_html_e( 'Paste timetable text:', 'slam-presenter-profiles' ); ?></label>
					<textarea name="ssp_new[text]" rows="6" class="large-text" placeholder="10:00 AM – 10:12 AM | പേര് (Name) — Title"></textarea>
				</fieldset>
			</td>
		</tr>
	</table>
	<p><label><input type="checkbox" name="ssp_new[publish]" value="1"> <?php esc_html_e( 'Publish now (otherwise it stays a draft that only editors can see)', 'slam-presenter-profiles' ); ?></label></p>
	<p>
		<?php submit_button( __( 'Create timetable', 'slam-presenter-profiles' ), 'primary', 'submit', false ); ?>
		<?php if ( $events ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SSP_POST_TYPE . '&page=ssp-timetable' ) ); ?>"><?php esc_html_e( 'Cancel', 'slam-presenter-profiles' ); ?></a>
		<?php endif; ?>
	</p>
</form>
<script>
	// Short name follows the event name until edited by hand.
	(function () {
		var name = document.getElementById('ssp_new_name');
		var slug = document.getElementById('ssp_new_slug');
		var preview = document.querySelector('.ssp-slug-preview');
		var touched = false;
		function slugify(v) {
			return v.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
		}
		function show() {
			preview.textContent = '[slam_timetable event="' + (slug.value || '…') + '"]';
		}
		name.addEventListener('input', function () {
			if (!touched) {
				slug.value = slugify(name.value);
				show();
			}
		});
		slug.addEventListener('input', function () {
			touched = slug.value !== '';
			slug.value = slugify(slug.value.replace(/-$/, '')) + (slug.value.slice(-1) === '-' ? '-' : '');
			show();
		});
		show();
	})();

	// Number of slots follows the chosen start; pasted text brings its own.
	(function () {
		var slots = document.getElementById('ssp_new_slots');
		var source = document.querySelector('.ssp-new-from select');
		function sync() {
			var from = (document.querySelector('.ssp-new-from input[type=radio]:checked') || {}).value;
			slots.required = from !== 'text';
			slots.disabled = from === 'text';
			if (from === 'copy' && source) {
				slots.value = source.options[source.selectedIndex].getAttribute('data-slots');
			}
		}
		document.querySelectorAll('.ssp-new-from input[type=radio]').forEach(function (r) {
			r.addEventListener('change', sync);
		});
		if (source) {
			source.addEventListener('change', function () {
				setTimeout(sync, 0);
			});
		}
		document.querySelector('.ssp-new-from textarea').addEventListener('input', function () {
			setTimeout(sync, 0);
		});
	})();

	// Typing in the text box picks the "paste" option.
	document.querySelector('.ssp-new-from textarea').addEventListener('input', function () {
		document.querySelector('.ssp-new-from input[value=text]').checked = true;
	});
	document.querySelector('.ssp-new-from select') && document.querySelector('.ssp-new-from select').addEventListener('change', function () {
		document.querySelector('.ssp-new-from input[value=copy]').checked = true;
	});
</script>
