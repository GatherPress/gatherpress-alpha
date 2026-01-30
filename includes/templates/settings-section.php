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
	<?php esc_html_e( 'Compatibility Updates', 'gatherpress-alpha' ); ?>
</h2>
<p class="description">
	<?php esc_html_e( 'Automatically update your site to handle breaking changes between GatherPress versions.', 'gatherpress-alpha' ); ?>
</p>
<div style="background: #e8f4f8; border-left: 4px solid #2271b1; padding: 10px; margin: 16px 0;">
	<p style="margin: 0;">
		<strong><?php esc_html_e( 'Developers:', 'gatherpress-alpha' ); ?></strong> <?php esc_html_e( 'You can also run these updates via WP-CLI:', 'gatherpress-alpha' ); ?>
		<code id="cli-command" style="background: #fff; padding: 4px 8px; margin-left: 5px; cursor: pointer; border: 1px solid #ddd; border-radius: 3px;" title="<?php esc_attr_e( 'Click to copy', 'gatherpress-alpha' ); ?>">wp gatherpress alpha fix</code>
		<span id="copy-feedback" style="margin-left: 10px; color: #008a20; display: none; font-weight: bold;"><?php esc_html_e( 'Copied!', 'gatherpress-alpha' ); ?></span>
	</p>
</div>
<div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 12px; margin: 16px 0;">
	<h3 style="margin-top: 0;"><?php esc_html_e( 'Version 0.34.0 Breaking Changes', 'gatherpress-alpha' ); ?></h3>

	<p><strong><?php esc_html_e( 'Event Online Link Meta Field Rename:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'The event online link meta field has been renamed for consistency:', 'gatherpress-alpha' ); ?></p>
	<ul style="margin-left: 20px;">
		<li><?php esc_html_e( 'Old: gatherpress_online_event_link', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'New: gatherpress_event_online_link', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Venue Data Structure Migration:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'Venue information is now stored in individual meta fields instead of a JSON blob for better performance and flexibility:', 'gatherpress-alpha' ); ?></p>
	<ul style="margin-left: 20px;">
		<li><?php esc_html_e( 'Old: gatherpress_venue_information (JSON string)', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'New: Individual fields:', 'gatherpress-alpha' ); ?>
			<ul style="margin-left: 20px; margin-top: 5px;">
				<li><code>gatherpress_venue_address</code> - <?php esc_html_e( 'Full venue address', 'gatherpress-alpha' ); ?></li>
				<li><code>gatherpress_venue_latitude</code> - <?php esc_html_e( 'Geocoded latitude', 'gatherpress-alpha' ); ?></li>
				<li><code>gatherpress_venue_longitude</code> - <?php esc_html_e( 'Geocoded longitude', 'gatherpress-alpha' ); ?></li>
				<li><code>gatherpress_venue_phone</code> - <?php esc_html_e( 'Venue phone number', 'gatherpress-alpha' ); ?></li>
				<li><code>gatherpress_venue_website</code> - <?php esc_html_e( 'Venue website URL', 'gatherpress-alpha' ); ?></li>
				<li><code>gatherpress_venue_online_link</code> - <?php esc_html_e( 'Default online event link for venue', 'gatherpress-alpha' ); ?></li>
			</ul>
		</li>
	</ul>

	<p style="background: #fff3cd; border-left: 3px solid #ffc107; padding: 8px; margin-top: 12px;">
		<strong><?php esc_html_e( '⚠️ Important:', 'gatherpress-alpha' ); ?></strong> <?php esc_html_e( 'If you have custom code that accesses venue information or event online links directly, you will need to update your code to use the new meta field names after running this migration.', 'gatherpress-alpha' ); ?>
	</p>

	<p><em><?php esc_html_e( 'This migration will automatically update all venue and event meta fields in your database to use the new structure.', 'gatherpress-alpha' ); ?></em></p>
</div>
<p id="gatherpress-saving" class="gatherpress-saving">
	<span class="spinner is-active"></span>
	<span class="gatherpress-message">
		<?php esc_html_e( 'This may take a minute. Please be patient and do not close this window.', 'gatherpress-alpha' ); ?>
	</span>
</p>
<p class="submit">
	<button id="gatherpress-alpha" class="button button-primary">
		<?php esc_html_e( 'Apply Updates', 'gatherpress-alpha' ); ?>
	</button>
</p>
<script>
	// Click to copy CLI command functionality
	document.getElementById('cli-command').addEventListener('click', function() {
		const command = this.innerText;

		// Check if clipboard API is available (requires HTTPS)
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(command).then(function() {
				document.getElementById('copy-feedback').style.display = 'inline';
				setTimeout(function() {
					document.getElementById('copy-feedback').style.display = 'none';
				}, 2000);
			}).catch(function(err) {
				// Fallback if clipboard API fails
				fallbackCopy(command);
			});
		} else {
			// Use fallback for older browsers or non-HTTPS
			fallbackCopy(command);
		}

		function fallbackCopy(text) {
			const textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.position = 'fixed';
			textArea.style.left = '-999999px';
			document.body.appendChild(textArea);
			textArea.select();
			try {
				document.execCommand('copy');
				document.getElementById('copy-feedback').style.display = 'inline';
				setTimeout(function() {
					document.getElementById('copy-feedback').style.display = 'none';
				}, 2000);
			} catch (err) {
				console.error('Failed to copy text');
			}
			document.body.removeChild(textArea);
		}
	});

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
