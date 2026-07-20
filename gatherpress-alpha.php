<?php
/**
 * Plugin Name:      GatherPress Alpha
 * Plugin URI:       https://gatherpress.org/
 * Description:      Powering Communities with WordPress.
 * Author:           The GatherPress Community
 * Author URI:       https://gatherpress.org/
 * Version:          0.34.0
 * Requires PHP:     8.1
 * Requires Plugins: gatherpress
 * Text Domain:      gatherpress-alpha
 * License:          GPLv2 or later (license.txt)
 *
 * @package GatherPress_Alpha
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Bail when a sibling copy is already loaded (e.g., when WordPress includes a
// duplicate folder during activation).
if ( defined( 'GATHERPRESS_ALPHA_VERSION' ) ) {
	return;
}

define( 'GATHERPRESS_ALPHA_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_ALPHA_CORE_PATH', __DIR__ );

// Register the GatherPress_Alpha namespace with GatherPress's class autoloader.
add_filter(
	'gatherpress_autoloader',
	static function ( array $namespace ): array {
		$namespace['GatherPress_Alpha'] = GATHERPRESS_ALPHA_CORE_PATH;

		return $namespace;
	}
);

// Boot the Alpha runtime when GatherPress announces it has finished loading.
//
// Registered at file-load time rather than inside `plugins_loaded`: GatherPress
// fires `gatherpress_loaded` from within its own `plugins_loaded` callback, and
// its callback is registered first, so a listener added inside ours would be
// added after the action had already fired.
//
// Gating on this action rather than on `defined( 'GATHERPRESS_VERSION' )` is
// what keeps a site alive when GatherPress fails its own requirements check.
// The GATHERPRESS_* constants are defined *before* that check, so they mean
// "GatherPress began loading", not "GatherPress loaded successfully" — booting
// on them meant calling into classes whose autoloader was never registered,
// fataling the whole site (GatherPress#1982).
add_action(
	'gatherpress_loaded',
	static function (): void {
		// Re-checked here because `gatherpress_loaded` fires whenever GatherPress
		// loads, including at a version Alpha is not locked to.
		if ( GATHERPRESS_VERSION !== GATHERPRESS_ALPHA_VERSION ) {
			return;
		}

		GatherPress_Alpha\Setup::get_instance();
	}
);

// Surface why Alpha is inert when GatherPress is absent or mismatched. These
// stay on `plugins_loaded` because they must run in the cases where
// `gatherpress_loaded` never fires.
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'GATHERPRESS_VERSION' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					?>
					<div class="notice notice-error">
						<p><?php esc_html_e( 'GatherPress is not installed.', 'gatherpress-alpha' ); ?></p>
					</div>
					<?php
				}
			);

			return;
		}

		if ( GATHERPRESS_VERSION !== GATHERPRESS_ALPHA_VERSION ) {
			add_action(
				'admin_notices',
				static function (): void {
					?>
					<div class="notice notice-error">
						<p>
							<?php esc_html_e( 'GatherPress and GatherPress Alpha must be the same version.', 'gatherpress-alpha' ); ?>
						</p>
					</div>
					<?php
				}
			);

			return;
		}
	}
);

// Announce this plugin to GatherPress's coexistence guard. Fired on
// `plugins_loaded` so the registration runs after every active plugin has
// loaded — GatherPress's listener is then guaranteed to be in place
// regardless of the plugin order in the `active_plugins` option. When
// GatherPress is not active, the action fires into the void — no fatal,
// no side effect.
add_action(
	'plugins_loaded',
	static function (): void {
		do_action( 'gatherpress_register_coexistence_guard', 'gatherpress-alpha', 'GatherPress Alpha', __FILE__ );
	}
);
