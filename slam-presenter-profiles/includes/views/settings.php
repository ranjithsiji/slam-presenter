<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = SSP_Settings::get();
$opt      = SSP_Settings::OPTION;
$labels   = array(
	'label_about'     => __( '"About" heading', 'slam-presenter-profiles' ),
	'label_abstract'  => __( '"Abstract" heading', 'slam-presenter-profiles' ),
	'label_papers'    => __( '"Papers" heading', 'slam-presenter-profiles' ),
	'label_schedule'  => __( 'Profile: "full schedule" link', 'slam-presenter-profiles' ),
	'label_slam_page' => __( 'Timetable: link to the slam page', 'slam-presenter-profiles' ),
	'label_tt_soon'   => __( 'Unpublished timetable: note for visitors (blank = show nothing)', 'slam-presenter-profiles' ),
	'label_talks'     => __( '[slam_talks] heading', 'slam-presenter-profiles' ),
	'label_no'        => __( 'Table: number column', 'slam-presenter-profiles' ),
	'label_talk'      => __( 'Table: talk column', 'slam-presenter-profiles' ),
	'label_presenter' => __( 'Table: presenter column', 'slam-presenter-profiles' ),
	'label_time'      => __( 'Table: time column', 'slam-presenter-profiles' ),
);
?>
<div class="wrap ssp-wrap">
	<h1><?php esc_html_e( 'Science Slam Settings', 'slam-presenter-profiles' ); ?></h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'ssp_settings_group' ); ?>

		<h2><?php esc_html_e( 'Addresses', 'slam-presenter-profiles' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ssp_presenter_slug"><?php esc_html_e( 'Profile URL base', 'slam-presenter-profiles' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" id="ssp_presenter_slug" name="<?php echo esc_attr( $opt ); ?>[presenter_slug]" value="<?php echo esc_attr( $settings['presenter_slug'] ); ?>" class="regular-text code"><code>/manu-madhavan/</code>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Edition', 'slam-presenter-profiles' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ssp_current_edition"><?php esc_html_e( 'Current edition', 'slam-presenter-profiles' ); ?></label></th>
				<td>
					<select id="ssp_current_edition" name="ssp_current_edition">
						<?php foreach ( SSP_Editions::all() as $edition ) : ?>
							<option value="<?php echo (int) $edition->term_id; ?>" <?php selected( (int) get_option( 'ssp_current_edition' ), $edition->term_id ); ?>><?php echo esc_html( $edition->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Admin screens open on this edition. Each person can switch with the "Working on" bar.', 'slam-presenter-profiles' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Profiles & timetable', 'slam-presenter-profiles' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ssp_talk_minutes"><?php esc_html_e( 'Default talk length', 'slam-presenter-profiles' ); ?></label></th>
				<td><input type="number" min="1" id="ssp_talk_minutes" name="<?php echo esc_attr( $opt ); ?>[talk_minutes]" value="<?php echo (int) $settings['talk_minutes']; ?>" class="small-text"> <?php esc_html_e( 'minutes (talk + Q&A)', 'slam-presenter-profiles' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Profile page', 'slam-presenter-profiles' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_theme_title]" value="1" <?php checked( $settings['hide_theme_title'] ); ?>> <?php esc_html_e( 'Hide the theme\'s page title and featured image (the profile card shows them)', 'slam-presenter-profiles' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Website labels', 'slam-presenter-profiles' ); ?></h2>
		<table class="form-table">
			<?php foreach ( $labels as $key => $label ) : ?>
				<tr>
					<th scope="row"><label for="ssp_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="text" id="ssp_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $opt . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" class="regular-text"></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php submit_button(); ?>
	</form>

	<div class="ssp-card">
		<h2><?php esc_html_e( 'Shortcodes', 'slam-presenter-profiles' ); ?></h2>
		<table class="widefat striped">
			<tr><td><code>[slam_presenters event="ernakulam"]</code></td><td><?php esc_html_e( 'Photo grid of presenters. Options: columns="4", order="timetable|name", show="title".', 'slam-presenter-profiles' ); ?></td></tr>
			<tr><td><code>[slam_timetable event="ernakulam"]</code></td><td><?php esc_html_e( 'Full day timetable with links to profiles. Option: photos="no".', 'slam-presenter-profiles' ); ?></td></tr>
			<tr><td><code>[slam_talks event="final"]</code></td><td><?php esc_html_e( 'Numbered table of talks and presenters (as on last year\'s profiles).', 'slam-presenter-profiles' ); ?></td></tr>
			<tr><td><code>[slam_presenter id="123"]</code></td><td><?php esc_html_e( 'One presenter\'s profile card, anywhere.', 'slam-presenter-profiles' ); ?></td></tr>
		</table>
	</div>
</div>
