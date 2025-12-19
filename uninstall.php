<?php
/**
 * Uninstall GatherPress Alpha.
 *
 * Removes all plugin data from the database when the plugin is uninstalled.
 *
 * @package GatherPress_Alpha
 */

// Exit if accessed directly or not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options.
 */
delete_option( 'gatherpress_alpha_last_version' );

/**
 * For multisite installations, delete the option from all sites.
 */
if ( is_multisite() ) {
	global $wpdb;

	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( 'gatherpress_alpha_last_version' );
		restore_current_blog();
	}
}
