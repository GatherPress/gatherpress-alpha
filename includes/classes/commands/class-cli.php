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

	/**
	 * Fixes CSS class names that changed in 0.33.0 of the plugin.
	 *
	 * This command specifically updates CSS class names in stored block content to use
	 * the new naming conventions introduced in GatherPress 0.33.0.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gatherpress alpha fix-css-classes
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function fix_css_classes( array $args = array(), array $assoc_args = array() ): void {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			WP_CLI::error( __( 'You do not have permission to perform this action.', 'gatherpress-alpha' ) );
			return;
		}

		$setup = Setup::get_instance();
		
		// Use reflection to call the private method.
		$reflection = new \ReflectionClass( $setup );
		$method = $reflection->getMethod( 'fix_css_class_names__0_33_0' );
		$method->setAccessible( true );
		$method->invoke( $setup );

		WP_CLI::success( __( 'CSS class names updated successfully for 0.33.0 compatibility.', 'gatherpress-alpha' ) );
	}
}
