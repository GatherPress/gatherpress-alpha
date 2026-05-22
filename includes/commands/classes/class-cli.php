<?php
/**
 * Class responsible for WP-CLI commands related to the GatherPress Alpha plugin.
 *
 * This class handles various WP-CLI commands specific to managing breaking changes within the GatherPress Alpha plugin.
 * Developers can use these commands to interact with and manage different aspects of the plugin to ensure compatibility
 * and apply necessary fixes for breaking changes.
 *
 * @package GatherPress_Alpha
 */

namespace GatherPress_Alpha\Commands;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress_Alpha\Setup;
use WP_CLI;

	/**
	 * Applies fixes to handle breaking changes in the GatherPress Alpha plugin.
	 *
	 * This command calls the fix method from the Setup class, which applies necessary fixes to ensure compatibility
	 * and correct issues due to breaking changes within the GatherPress Alpha plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gatherpress alpha fix
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
class Cli extends WP_CLI {
	/**
	 * Applies fixes to handle breaking changes in the GatherPress Alpha plugin.
	 *
	 * This command calls the fix method from the Setup class, which applies necessary fixes to ensure compatibility
	 * and correct issues due to breaking changes within the GatherPress Alpha plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gatherpress alpha fix
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function fix( array $args = array(), array $assoc_args = array() ): void {
		$setup = Setup::get_instance();

		$setup->fix();
		WP_CLI::success( __( 'Fixes applied successfully.', 'gatherpress-alpha' ) );
	}
}
