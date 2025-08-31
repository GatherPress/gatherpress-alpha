<?php
/**
 * Template for fixing breaking changes in GatherPress Alpha.
 *
 * This template provides UI elements and JavaScript functionality to fix breaking changes
 * specific to GatherPress when using GatherPress Alpha.
 *
 * @package GatherPress_Alpha
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
?>

<style>
	.gatherpress-saving {
		display: none;
		align-items: center;
	}
	.gatherpress-saving.gatherpress-is-saving {
		display: flex;
	}
	.gatherpress-saving .spinner {
		float: none;
	}
	.gatherpress-message {
		font-weight: bold;
	}
</style>
<h2>
	<?php esc_html_e( 'Alpha', 'gatherpress-alpha' ); ?>
</h2>
<p class="description">
	<?php esc_html_e( 'Fix breaking changes to GatherPress.', 'gatherpress-alpha' ); ?>
</p>
<div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 12px; margin: 16px 0;">
	<h3 style="margin-top: 0;"><?php esc_html_e( 'Version 0.33.0 Changes', 'gatherpress-alpha' ); ?></h3>
	<p><strong><?php esc_html_e( 'CSS Class Name Updates:', 'gatherpress-alpha' ); ?></strong></p>
	<ul style="margin-left: 20px;">
		<li><?php esc_html_e( 'Modal classes: gatherpress--is-rsvp-modal → gatherpress-modal--rsvp', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'State classes: gatherpress--is-not-visible → gatherpress--is-hidden', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Field classes: gatherpress-field-type-* → gatherpress-field--*', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'RSVP status: gatherpress--rsvp-attending → gatherpress--is-attending', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'And more...', 'gatherpress-alpha' ); ?></li>
	</ul>
	<p><em><?php esc_html_e( 'This migration updates saved block content in your database to use the new class naming conventions.', 'gatherpress-alpha' ); ?></em></p>
</div>
<p id="gatherpress-saving" class="gatherpress-saving">
	<span class="spinner is-active"></span>
	<span class="gatherpress-message">
		<?php esc_html_e( 'This may take a minute. Please be patient and do not close this window.', 'gatherpress-alpha' ); ?>
	</span>
</p>
<p class="submit">
	<button id="gatherpress-alpha" class="button button-primary">
		<?php esc_html_e( 'Fix GatherPress!', 'gatherpress-alpha' ); ?>
	</button>
</p>
<script>
	const gatherPressAlphaButton = document.getElementById('gatherpress-alpha');

	document.getElementById('gatherpress-alpha').addEventListener('click', function(e) {
		e.preventDefault();
		gatherPressAlphaButton.disabled = true;
		document.getElementById('gatherpress-saving').classList.add('gatherpress-is-saving');

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
				throw new Error('<?php esc_html_e( 'Network response was not ok.', 'gatherpress-alpha' ); ?>'); // Throw an error if response is not ok.
			}

			return response.json(); // Parse JSON from the response.
		})
		.then(response => {
			// Handle the JSON response.
			if (response.success) {
				alert('<?php esc_html_e( 'Success!', 'gatherpress-alpha' ); ?>');
				gatherPressAlphaButton.disabled = false;
				document.getElementById('gatherpress-saving').classList.remove('gatherpress-is-saving');
			} else {
				alert('<?php esc_html_e( 'Something went wrong!', 'gatherpress-alpha' ); ?>');
				gatherPressAlphaButton.disabled = false;
				document.getElementById('gatherpress-saving').classList.remove('gatherpress-is-saving');
			}
		})
		.catch(error => {
			console.error(error); // Handle any errors.
		});
	});
</script>
