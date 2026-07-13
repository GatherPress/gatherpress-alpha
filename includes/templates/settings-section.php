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

$gatherpress_alpha_last_version = get_option( 'gatherpress_alpha_last_version', null );

/**
 * Whether the fix for a given version is still pending on this site.
 *
 * Mirrors Setup::should_run_fix(): with no recorded run every fix is pending;
 * after an unstable run (e.g. 0.34.0-beta.1) fixes >= the base version are
 * pending; after a stable run only fixes > the base version are pending.
 *
 * @param string $gatherpress_alpha_fix_version The version of the fix to check.
 * @return bool True when the fix has not been applied yet.
 */
$gatherpress_alpha_is_pending = static function ( string $gatherpress_alpha_fix_version ) use ( $gatherpress_alpha_last_version ): bool {
	if ( null === $gatherpress_alpha_last_version ) {
		return true;
	}

	$gatherpress_alpha_base     = preg_replace( '/-.*$/', '', $gatherpress_alpha_last_version );
	$gatherpress_alpha_unstable = false !== strpos( $gatherpress_alpha_last_version, '-' );

	if ( $gatherpress_alpha_unstable ) {
		return version_compare( $gatherpress_alpha_fix_version, $gatherpress_alpha_base, '>=' );
	}

	return version_compare( $gatherpress_alpha_fix_version, $gatherpress_alpha_base, '>' );
};

/**
 * Renders the Applied/Pending badge for a version's fix.
 *
 * @param bool $gatherpress_alpha_pending Whether the fix is pending.
 * @return void
 */
$gatherpress_alpha_badge = static function ( bool $gatherpress_alpha_pending ): void {
	if ( $gatherpress_alpha_pending ) {
		echo '<span style="background: #f0b849; color: #1d2327; font-size: 0.75em; font-weight: 600; padding: 2px 8px; border-radius: 10px; margin-left: 8px; vertical-align: middle;">' . esc_html__( 'Pending', 'gatherpress-alpha' ) . '</span>';
	} else {
		echo '<span style="background: #00a32a; color: #fff; font-size: 0.75em; font-weight: 600; padding: 2px 8px; border-radius: 10px; margin-left: 8px; vertical-align: middle;">' . esc_html__( 'Applied', 'gatherpress-alpha' ) . '</span>';
	}
};
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
	.gatherpress-alpha-version {
		background: #f9f9f9;
		border-left: 4px solid #0073aa;
		padding: 12px;
		margin: 16px 0;
	}
	.gatherpress-alpha-version > summary {
		cursor: pointer;
		font-size: 1.05em;
		font-weight: 600;
	}
	.gatherpress-alpha-version ul {
		margin-left: 20px;
		list-style: disc;
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
<p>
	<?php
	if ( null !== $gatherpress_alpha_last_version ) {
		printf(
			/* translators: %s: version number. */
			esc_html__( 'Compatibility updates last ran at version %s. Versions marked Pending will be applied on the next run.', 'gatherpress-alpha' ),
			'<strong>' . esc_html( $gatherpress_alpha_last_version ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	} else {
		esc_html_e( 'Compatibility updates have not been run on this site yet. All versions below are pending and will be applied on the next run.', 'gatherpress-alpha' );
	}
	?>
</p>

<details class="gatherpress-alpha-version" open>
	<summary><?php esc_html_e( 'Version 0.35.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.35.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'Icon Block Replaced:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'The GatherPress Icon block has been replaced with the WordPress core Icon block introduced in WordPress 7.0. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Convert all GatherPress Icon blocks to core Icon blocks across every post type, including templates, template parts, reusable blocks, and block widgets', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Map each icon to its closest equivalent in the WordPress icon library', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Preserve icon size, color, alignment, anchor, custom classes, and margins', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Update icons saved inside RSVP block status templates', 'gatherpress-alpha' ); ?></li>
	</ul>
	<p><em><?php esc_html_e( 'Requires WordPress 7.0 or newer. Icon blocks that are not migrated will show a "missing block" notice in the editor once the GatherPress Icon block is removed.', 'gatherpress-alpha' ); ?></em></p>

	<p><strong><?php esc_html_e( 'Venue Map Dimensions Moved:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'The Venue Map block now uses the WordPress core dimensions support: width and height are stored as CSS values in the block\'s style attribute and edited through the core Dimensions panel. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Convert saved numeric width and height values on Venue Map blocks to the new format across every post type, including templates, template parts, reusable blocks, and block widgets', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Drop stored "auto" (zero) values — an absent dimension now means auto', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Leave aspect ratio, zoom, and all other map settings untouched', 'gatherpress-alpha' ); ?></li>
	</ul>
	<p><em><?php esc_html_e( 'Unmigrated blocks keep rendering at their saved size — GatherPress reads the old attributes as a fallback — but their width and height will not appear in the editor\'s Dimensions panel until they are migrated or edited.', 'gatherpress-alpha' ); ?></em></p>

	<p><strong><?php esc_html_e( 'Venue Inner Layout Changed:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'The Venue block\'s inner layout now defaults to flow, so its contents follow the venue\'s own width in every alignment. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Remove stored constrained inner layouts from Venue blocks (saved when layout or justification controls were used under the old default) across every post type, templates, reusable blocks, and block widgets', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Leave alignment and all other Venue settings untouched', 'gatherpress-alpha' ); ?></li>
	</ul>
	<p><em><?php esc_html_e( 'Older versions never applied the venue\'s inner layout on the site frontend, so removing these keeps pages rendering exactly as they always have. Constrained content can be re-enabled per block with the layout panel\'s content-width toggle, which now works on the frontend as well.', 'gatherpress-alpha' ); ?></em></p>
</details>

<details class="gatherpress-alpha-version">
	<summary><?php esc_html_e( 'Version 0.34.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.34.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'Events List Block Replaced:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'The deprecated Events List block has been replaced with the Event Query Loop, a variation of the core Query Loop block. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Convert all Events List blocks to Event Query Loop blocks', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Preserve event type (upcoming/past), number of events, date format, and display options', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Migrate topic and venue taxonomy filters', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Venue Block Updated:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'The Venue block now uses inner blocks for venue details. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Replace self-closing Venue blocks with the new inner block structure (address, phone, website, map)', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Automatically use the Online Event block for events with an online venue', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Online Event Block Updated:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Replace self-closing Online Event blocks with the new inner block structure (icon and event link)', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Settings Consolidated:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'Plugin settings have been refactored into a single flat option for simplicity. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Merge gatherpress_general and gatherpress_leadership options into gatherpress_settings', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Flatten nested section/option structure into a single key-value store', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Rename URL keys: events → events_url, venues → venues_url, topics → topics_url', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Remove old options after successful migration', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'Venue Information Split Into Individual Meta Keys:', 'gatherpress-alpha' ); ?></strong></p>
	<p><?php esc_html_e( 'Venue address, phone, website, and coordinates are now stored as individual post meta keys instead of a single JSON blob, so they can be bound to blocks via core/post-meta block bindings. This migration will:', 'gatherpress-alpha' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Decode the gatherpress_venue_information JSON for each venue', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Write each non-empty field to its own meta key: gatherpress_address, gatherpress_latitude, gatherpress_longitude, gatherpress_phone, gatherpress_website', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Remove the original JSON blob once the individual fields are written', 'gatherpress-alpha' ); ?></li>
	</ul>
</details>

<details class="gatherpress-alpha-version">
	<summary><?php esc_html_e( 'Version 0.33.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.33.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'Add to Calendar Block Updated:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Replace self-closing Add to Calendar blocks with the new inner block structure', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'RSVP Form Blocks Replaced:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Convert the deprecated RSVP Guest Count Input and RSVP Anonymous Checkbox blocks to the new Form Field block', 'gatherpress-alpha' ); ?></li>
	</ul>

	<p><strong><?php esc_html_e( 'CSS Class Names Updated:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Rename modal, form field, and RSVP state classes in saved block content to the new naming conventions (for example gatherpress--open-modal becomes gatherpress-modal--trigger-open)', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Update class names inside saved RSVP block status templates', 'gatherpress-alpha' ); ?></li>
	</ul>
</details>

<details class="gatherpress-alpha-version">
	<summary><?php esc_html_e( 'Version 0.32.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.32.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'RSVP Blocks Updated:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Replace self-closing RSVP and RSVP Response blocks in event content with the new inner block structure', 'gatherpress-alpha' ); ?></li>
	</ul>
</details>

<details class="gatherpress-alpha-version">
	<summary><?php esc_html_e( 'Version 0.31.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.31.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'Event Datetime Meta Consolidated:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Add the consolidated gatherpress_datetime meta to every event and resave stored datetimes', 'gatherpress-alpha' ); ?></li>
	</ul>
</details>

<details class="gatherpress-alpha-version">
	<summary><?php esc_html_e( 'Version 0.30.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.30.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'RSVP Storage Moved to Comments:', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Migrate all RSVPs from the custom gatherpress_rsvps table to the WordPress comments system, preserving original timestamps', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Drop the custom table after migration', 'gatherpress-alpha' ); ?></li>
	</ul>
</details>

<details class="gatherpress-alpha-version">
	<summary><?php esc_html_e( 'Version 0.29.* Breaking Changes', 'gatherpress-alpha' ); ?><?php $gatherpress_alpha_badge( $gatherpress_alpha_is_pending( '0.29.0' ) ); ?></summary>

	<p><strong><?php esc_html_e( 'Plugin Prefix Renamed (gp_ to gatherpress_):', 'gatherpress-alpha' ); ?></strong></p>
	<ul>
		<li><?php esc_html_e( 'Rename the custom events and RSVPs tables, the event and venue post types, and the topic and venue taxonomies', 'gatherpress-alpha' ); ?></li>
		<li><?php esc_html_e( 'Rename post meta, user meta, and all plugin options to the gatherpress_ prefix', 'gatherpress-alpha' ); ?></li>
	</ul>
</details>

<p><em><?php esc_html_e( 'These migrations automatically update saved block content, settings, templates, and reusable blocks in your database.', 'gatherpress-alpha' ); ?></em></p>
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
