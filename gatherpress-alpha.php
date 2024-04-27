<?php
/**
 * Plugin Name:  GatherPress Alpha
 * Plugin URI:   https://gatherpress.org/
 * Description:  Powering Communities with WordPress.
 * Author:       The GatherPress Community
 * Author URI:   https://gatherpress.org/
 * Version:      0.0.1
 * Requires PHP: 7.4
 * Text Domain:  gatherpress-alpha
 * Domain Path: /languages
 * License:      GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */

function gatherpress_alpha_sub_page( $sub_pages ) {
	$sub_pages['alpha'] = array(
		'name'     => __( 'Alpha', 'gp-meetup-importer' ),
		'priority' => 10,
		'sections' => array(
			'meetup_importer' => array(
				'name'        => __( 'Alpha', 'gatherpress-alpha' ),
				'description' => __( 'Fix breaking changes to GatherPress', 'gatherpress-alpha' ),
			),
		),
	);

	return $sub_pages;
}
add_filter( 'gatherpress_sub_pages', 'gatherpress_alpha_sub_page');

function gatherpress_alpha_settings_section( $page ) {
	if ( 'gatherpress_alpha' === $page ) {
		remove_action( 'gatherpress_settings_section', array( GatherPress\Core\Settings::get_instance(), 'render_settings_form' ) );
		?>
		<h2><?php esc_html_e( 'Alpha', 'gatherpress-alpha' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Fix breaking changes to GatherPress', 'gatherpress-alpha' ); ?></p>
		<p class="submit">
			<button id="gatherpress-alpha" class="button button-primary"><?php esc_html_e( 'Fix GatherPress!', 'gatherpress-alpha' ); ?></button>
		</p>
		<script>
			const gatherPressAlphaButton = document.getElementById('gatherpress-alpha');

			document.getElementById('gatherpress-alpha').addEventListener('click', function(e) {
				e.preventDefault();
				gatherPressAlphaButton.disabled = true;

				// Define the data to be sent in the request
				const data = { action: 'gatherpress_alpha', nonce: '<?php echo wp_create_nonce( 'gatherpress_alpha_nonce' ); ?>' };

				// Create a configuration object for the fetch call
				const fetchConfig = {
					method: 'POST', // Set HTTP method to POST
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded', // Set appropriate content type
					},
					body: new URLSearchParams(data) // Encode your data as URL-encoded string
				};

				// Perform the fetch call to the specified URL
				fetch(window.ajaxurl, fetchConfig)
					.then(response => {
						if (!response.ok) {
							throw new Error('Network response was not ok'); // Throw an error if response is not ok
						}
						return response.json(); // Parse JSON from the response
					})
					.then(response => {
						// Handle the JSON response
						if (response.success) {
							alert('Success!');
							gatherPressAlphaButton.disabled = false;
						} else {
							alert('Something went wrong!');
							gatherPressAlphaButton.disabled = false;
						}
					})
					.catch(error => {
						console.error('There was a problem with the fetch operation:', error); // Handle any errors
					});
				});
		</script>
		<?php
	}
}
add_action( 'gatherpress_settings_section', 'gatherpress_alpha_settings_section', 9 );

function gatherpress_alpha_ajax() {
	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'gatherpress_alpha_nonce' ) ) {
		wp_send_json_error();
	}
	global $wpdb;

	// Fix custom tables.
	$old_table_events = $wpdb->prefix . 'gp_events';
	$new_table_events = $wpdb->prefix . 'gatherpress_events';
	$old_table_rsvps = $wpdb->prefix . 'gp_rsvps';
	$new_table_rsvps = $wpdb->prefix . 'gatherpress_rsvps';
	$sql = "RENAME TABLE `{$old_table_events}` TO `{$new_table_events}`, `{$old_table_rsvps}` TO `{$new_table_rsvps}`;";
	$wpdb->query( $sql );

	// Fix event post type.
	$old_post_type = 'gp_event';
	$new_post_type = 'gatherpress_event';
	$sql = $wpdb->prepare(
		"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
		$new_post_type,
		$old_post_type
	);
	$wpdb->query( $sql );

	// Fix venue post type.
	$old_post_type = 'gp_venue';
	$new_post_type = 'gatherpress_venue';
	$sql = $wpdb->prepare(
		"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
		$new_post_type,
		$old_post_type
	);
	$wpdb->query( $sql );

	// Fix post meta.
	$meta_keys = [
		'max_guest_limit',
		'enable_anonymous_rsvp',
		'enable_initial_decline',
		'online_event_link',
		'venue_information',
	];
	foreach ($meta_keys as $key) {
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
			'gatherpress_' . $key,
			$key
		);
		$wpdb->query($sql);
	}

	// Fix topic taxonomy.
	$old_taxonomy = 'gp_topic';
	$new_taxonomy = 'gatherpress_topic';
	$sql = $wpdb->prepare(
		"UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s",
		$new_taxonomy,
		$old_taxonomy
	);
	$wpdb->query( $sql );

	// Fix venue taxonomy.
	$old_taxonomy = '_gp_venue';
	$new_taxonomy = '_gatherpress_venue';
	$sql = $wpdb->prepare(
		"UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s",
		$new_taxonomy,
		$old_taxonomy
	);
	$wpdb->query( $sql );

	// Fix options.
	$option_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'gp_%'");
	foreach ($option_names as $option_name) {
		$new_option_name = 'gatherpress_' . substr($option_name, 3); // Remove 'gp_' prefix and prepend 'gatherpress_'
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s",
			$new_option_name,
			$option_name
		);
		$wpdb->query($sql);
	}

	wp_send_json_success();
}

add_action( 'wp_ajax_gatherpress_alpha', 'gatherpress_alpha_ajax' );

