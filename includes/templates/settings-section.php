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
	<h3 style="margin-top: 0;"><?php esc_html_e( 'Version 0.33.0 Breaking Changes', 'gatherpress-alpha' ); ?></h3>

	<p><strong><?php esc_html_e( 'Major CSS Class Naming Convention Updates:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'All CSS classes have been refactored to follow a more consistent BEM-like naming pattern. This affects:', 'gatherpress-alpha' ); ?></p>
	<ul style="margin-left: 20px;">
		<li><?php esc_html_e( 'Modal components - New pattern: gatherpress-modal--[type/action]', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Form fields - New pattern: gatherpress-form-field--[type]', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'RSVP states - Changed to state-based naming: gatherpress--is-[state]', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Visibility states - Renamed for clarity (is-not-visible → is-hidden)', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Action triggers - More descriptive naming for interactive elements', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Block Structure Changes:', 'gatherpress-alpha' ); ?></strong></p>
	<ul style="margin-left: 20px;">
		<li><?php esc_html_e( 'Add to Calendar block - Updated with new rendering structure', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'RSVP blocks - Enhanced with improved templating system', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Settings Changes:', 'gatherpress-alpha' ); ?></strong></p>
	<ul style="margin-left: 20px;">
		<li><?php esc_html_e( 'Removed deprecated site notification suppression option', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Removed deprecated rewrite rules flush flag option', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p style="background: #fff3cd; border-left: 3px solid #ffc107; padding: 8px; margin-top: 12px;">
		<strong><?php esc_html_e( '⚠️ Important:', 'gatherpress-alpha' ); ?></strong> <?php esc_html_e( 'If you have custom CSS or JavaScript that targets GatherPress elements, you will need to update your selectors to match the new class names after running this migration.', 'gatherpress-alpha' ); ?>
	</p>

	<p><em><?php esc_html_e( 'This migration will automatically update all saved block content, templates, and reusable blocks in your database to use the new naming conventions.', 'gatherpress-alpha' ); ?></em></p>
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
