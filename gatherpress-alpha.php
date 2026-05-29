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

// Boot the Alpha runtime once all plugins have loaded — but only when
// GatherPress itself is present at a matching version.
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

		GatherPress_Alpha\Setup::get_instance();
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
