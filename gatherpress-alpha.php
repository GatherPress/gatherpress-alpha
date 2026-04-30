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

/**
 * Returns the list of installed plugin folders that look like duplicate copies
 * of GatherPress Alpha.
 *
 * WordPress's upload-replace flow keys off the plugin folder slug, so a fresh
 * upload of an Alpha build whose folder name doesn't match an existing copy
 * will install into a new sibling folder (`gatherpress-alpha-1`,
 * `gatherpress-alpha-2`, etc.) and leave the older copy in place. This helper
 * scans `get_plugins()` for any `gatherpress-alpha*\/gatherpress-alpha.php`
 * entries so callers can refuse activation when more than one is on disk.
 *
 * @return string[] Plugin basenames of every Alpha-shaped folder found, sorted.
 */
function gatherpress_alpha_find_duplicate_folders(): array {
	$plugins    = get_plugins();
	$duplicates = array();

	foreach ( array_keys( $plugins ) as $plugin_file ) {
		if ( ! is_string( $plugin_file ) ) {
			continue;
		}

		$parts = explode( '/', $plugin_file );

		if ( 2 !== count( $parts ) ) {
			continue;
		}

		list( $folder, $file ) = $parts;

		if ( 'gatherpress-alpha.php' !== $file ) {
			continue;
		}

		if ( 'gatherpress-alpha' !== $folder && 0 !== strpos( $folder, 'gatherpress-alpha-' ) ) {
			continue;
		}

		$duplicates[] = $plugin_file;
	}

	sort( $duplicates );

	return $duplicates;
}

/**
 * Refuses activation when more than one GatherPress Alpha folder is on disk.
 *
 * Belt-and-suspenders against WordPress's upload-replace miss: if the user has
 * uploaded a newer Alpha build into a sibling folder rather than replacing the
 * existing one, both copies show up in `get_plugins()`. Activating either while
 * the other still exists creates two GP Alpha plugin rows, which is the
 * confusing artifact reported by QA. This helper deactivates the plugin and
 * halts with `wp_die()` so the user has to clean up the duplicate folders
 * before activation can succeed.
 *
 * @return void
 */
function gatherpress_alpha_refuse_activation_on_duplicates(): void {
	$duplicates = gatherpress_alpha_find_duplicate_folders();

	if ( count( $duplicates ) <= 1 ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	// WordPress's `activate_plugin()` pre-sends a `Location:` redirect header to a
	// failure URL before running the activation hook, so any output produced by
	// `wp_die()` here would be discarded by the browser as it follows the
	// redirect. Remove the pre-set redirect so the user actually sees this notice.
	if ( ! headers_sent() ) {
		header_remove( 'Location' );
	}

	$folders = array_map(
		static function ( string $plugin_file ): string {
			return dirname( $plugin_file );
		},
		$duplicates
	);

	wp_die(
		sprintf(
			'<h1>%s</h1><p>%s</p><ul><li><code>%s</code></li></ul><p>%s</p>',
			esc_html__( 'Multiple GatherPress Alpha folders detected', 'gatherpress-alpha' ),
			esc_html__(
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'WordPress installed a new copy of GatherPress Alpha into a separate folder instead of replacing the existing one. Activating any of these copies while the others remain on disk causes confusing behavior on the plugins screen.',
				'gatherpress-alpha'
			),
			implode( '</code></li><li><code>', array_map( 'esc_html', $folders ) ),
			esc_html__(
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'Remove all but one of these folders via SFTP or your file manager, then return to the plugins screen and try activating again.',
				'gatherpress-alpha'
			)
		),
		esc_html__( 'GatherPress Alpha activation halted', 'gatherpress-alpha' ),
		array(
			'response'  => 200,
			'back_link' => true,
		)
	);
}
register_activation_hook( __FILE__, 'gatherpress_alpha_refuse_activation_on_duplicates' );

// Also wire the activation guard to every other GatherPress Alpha-shaped folder
// so activating an older copy that lacks this guard still triggers the check
// from the active install. `get_plugins()` is only available in admin context.
if ( is_admin() ) {
	foreach ( gatherpress_alpha_find_duplicate_folders() as $gatherpress_alpha_sibling_plugin_file ) {
		add_action( 'activate_' . $gatherpress_alpha_sibling_plugin_file, 'gatherpress_alpha_refuse_activation_on_duplicates' );
	}
}
