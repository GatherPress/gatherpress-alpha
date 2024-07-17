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
 * Displays an error notice if GatherPress and GatherPress Alpha versions do not match.
 *
 * This function outputs an error notice in the WordPress admin area, indicating
 * that the versions of GatherPress and GatherPress Alpha must be the same.
 *
 * @return void
 */
function gatherpress_alpha_version_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'GatherPress and GatherPress Alpha must be the same version.', 'gatherpress-alpha' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Displays an error notice indicating GatherPress is not installed.
 *
 * This function outputs an error notice in the WordPress admin area when
 * GatherPress is not detected as installed, prompting users to install it.
 *
 * @return void
 */
function gatherpress_alpha_not_installed(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'GatherPress is not installed.', 'gatherpress-alpha' ); ?>
		</p>
	</div>
	<?php
}

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
	if ( ! defined( 'GATHERPRESS_VERSION' ) ) {
		add_action( 'admin_notices', 'gatherpress_alpha_not_installed' );
	} elseif ( defined( 'GATHERPRESS_VERSION' ) && GATHERPRESS_VERSION !== GATHERPRESS_ALPHA_VERSION ) {
		add_action( 'admin_notices', 'gatherpress_alpha_version_notice' );
	} else {
		GatherPress_Alpha\Setup::get_instance();
	}

}
add_action( 'plugins_loaded', 'gatherpress_alpha_setup' );
