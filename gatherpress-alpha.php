<?php
/**
 * Plugin Name:      GatherPress Alpha
 * Plugin URI:       https://gatherpress.org/
 * Description:      Powering Communities with WordPress.
 * Author:           The GatherPress Community
 * Author URI:       https://gatherpress.org/
 * Version:          0.34.0-alpha.2
 * Requires PHP:     7.4
 * Requires Plugins: gatherpress
 * Text Domain:      gatherpress-alpha
 * License:          GPLv2 or later (license.txt)
 *
 * @package GatherPress_Alpha
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants. Guarded so a duplicate Alpha folder included via
// `plugin_sandbox_scrape()` during activation doesn't redefine them.
defined( 'GATHERPRESS_ALPHA_VERSION' ) || define(
	'GATHERPRESS_ALPHA_VERSION',
	current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) )
);
defined( 'GATHERPRESS_ALPHA_CORE_PATH' ) || define( 'GATHERPRESS_ALPHA_CORE_PATH', __DIR__ );

if ( ! function_exists( 'gatherpress_alpha_autoloader' ) ) {
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
}
add_filter( 'gatherpress_autoloader', 'gatherpress_alpha_autoloader' );

if ( ! function_exists( 'gatherpress_alpha_version_notice' ) ) {
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
}

if ( ! function_exists( 'gatherpress_alpha_not_installed' ) ) {
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
}

if ( ! function_exists( 'gatherpress_alpha_setup' ) ) {
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
}
add_action( 'plugins_loaded', 'gatherpress_alpha_setup' );

// Register a coexistence activation guard via GatherPress's public API. The
// `function_exists()` guard makes the registration a graceful no-op if the
// helper is removed from a future version of GatherPress.
add_action(
	'gatherpress_register_coexistence_guards',
	static function (): void {
		if ( function_exists( 'gatherpress_register_coexistence_guard' ) ) {
			gatherpress_register_coexistence_guard( 'gatherpress-alpha', 'GatherPress Alpha', __FILE__ );
		}
	}
);
