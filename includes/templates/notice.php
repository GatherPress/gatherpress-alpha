<?php
/**
 * Error notice template for GatherPress and GatherPress Alpha version mismatch.
 *
 * This template displays an error notice when GatherPress and GatherPress Alpha
 * versions do not match, reminding users to ensure both versions are aligned.
 *
 * @package GatherPress_Alpha
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
?>

<div class="notice notice-error">
	<p>
		<?php esc_html_e( 'GatherPress and GatherPress Alpha must be the same version.', 'gatherpress-alpha' ); ?>
	</p>
</div>
