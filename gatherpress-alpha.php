<?php
/**
 * Plugin Name:  GatherPress Alpha
 * Plugin URI:   https://gatherpress.org/
 * Description:  Powering Communities with WordPress.
 * Author:       The GatherPress Community
 * Author URI:   https://gatherpress.org/
 * Version:      0.30.0-alpha
 * Requires PHP: 7.4
 * Text Domain:  gatherpress-alpha
 * License:      GPLv2 or later (license.txt)
 *
 * @package GatherPress_Alpha
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_ALPHA_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_ALPHA_CORE_PATH', __DIR__ );

/**
 * Adds the GatherPress_Alpha namespace to the autoloader.
 *
 * This function hooks into the 'gatherpress_autoloader' filter and adds the
 * GatherPress_Alpha namespace to the list of namespaces with its core path.
 *
 * @param array $namespace An associative array of namespaces and their paths.
 * @return array Modified array of namespaces and their paths.
 */
function gatherpress_alpha_autoloader( array $namespace ): array {
	$namespace['GatherPress_Alpha'] = GATHERPRESS_ALPHA_CORE_PATH;

	return $namespace;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_alpha_autoloader' );

/**
 * Initializes the GatherPress Alpha setup.
 *
 * This function hooks into the 'plugins_loaded' action to ensure that
 * the GatherPress_Alpha\Setup instance is created once all plugins are loaded,
 * only if the GatherPress plugin is active.
 *
 * @return void
 */
function gatherpress_alpha_setup(): void {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	if ( ! is_plugin_active( 'gatherpress/gatherpress.php' ) ) {
		return;
	}

	GatherPress_Alpha\Setup::get_instance();
}
add_action( 'plugins_loaded', 'gatherpress_alpha_setup' );
