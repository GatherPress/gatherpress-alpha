<?php
/**
 * Manages plugin setup for GatherPress Alpha.
 *
 * @package GatherPress_Alpha
 */

namespace GatherPress_Alpha;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Query;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Settings;
use GatherPress\Core\Utility;
use GatherPress_Alpha\Commands\Cli;
use WP_CLI;
use WP_Query;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;

/**
 * Class Setup.
 *
 * Manages plugin setup and initialization.
 */
class Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * Initializes and sets up various components of the plugin.
	 */
	protected function __construct() {
		$this->setup_cli();
		$this->setup_hooks();
	}

	/**
	 * Sets up WP-CLI commands for the GatherPress Alpha plugin.
	 *
	 * This method checks if WP-CLI is defined and active, and if so, adds the CLI commands
	 * specific to handling breaking changes in the GatherPress Alpha plugin.
	 *
	 * @return void
	 */
	protected function setup_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'gatherpress alpha', Cli::class );
		}
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_sub_pages', array( $this, 'setup_sub_page' ) );
		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
		add_action( 'wp_ajax_gatherpress_alpha', array( $this, 'ajax_fix' ) );
	}

	/**
	 * Adds a sub-page for GatherPress Alpha to the existing sub-pages array.
	 *
	 * On a multisite install Alpha only applies at the network level (the
	 * breaking-change fixes are global to the install, not per-site), so the
	 * sub-page is hidden from individual site Settings and only appears at
	 * Network Admin → Settings → GatherPress. On single-site installs it
	 * continues to appear in the normal per-site Settings UI.
	 *
	 * @param array $sub_pages An associative array of existing sub-pages.
	 * @return array Modified array of sub-pages including the GatherPress Alpha sub-page.
	 */
	public function setup_sub_page( $sub_pages ): array {
		if ( is_multisite() && ! is_network_admin() ) {
			return $sub_pages;
		}

		$sub_pages['alpha'] = array(
			'name'     => __( 'Alpha', 'gatherpress-alpha' ),
			'priority' => 10,
			'sections' => array(
				'fix_gatherpress' => array(
					'name'        => __( 'Alpha', 'gatherpress-alpha' ),
					'description' => __( 'Fix breaking changes to GatherPress', 'gatherpress-alpha' ),
				),
			),
		);

		return $sub_pages;
	}

	/**
	 * Renders a custom settings section for GatherPress Alpha.
	 *
	 * Checks if the current settings page matches 'gatherpress_alpha'. If true,
	 * it removes the default settings section and renders a custom template.
	 *
	 * @param string $page The current settings page being rendered.
	 * @return void
	 */
	public function settings_section( string $page ): void {
		if ( 'gatherpress_alpha' === $page ) {
			remove_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) );
			Utility::render_template(
				sprintf( '%s/includes/templates/settings-section.php', GATHERPRESS_ALPHA_CORE_PATH ),
				array(),
				true
			);
		}
	}

	/**
	 * AJAX handler to fix issues specific to GatherPress Alpha.
	 *
	 * Verifies the nonce for security, performs the necessary fix operation,
	 * and sends a JSON response indicating success or failure.
	 *
	 * @return void
	 */
	public function ajax_fix(): void {
		if (
			! current_user_can( 'manage_options' ) &&
			! wp_verify_nonce( $_REQUEST['nonce'], 'gatherpress_alpha_nonce' )
		) {
			wp_send_json_error();
		}

		$this->fix();

		wp_send_json_success();
	}

	/**
	 * Extracts the base version from a version string with unstable suffix.
	 *
	 * For example: "0.33.0-alpha.1" becomes "0.33.0"
	 *              "0.33.0-beta.2" becomes "0.33.0"
	 *              "0.33.0-rc.1" becomes "0.33.0"
	 *
	 * @param string $version The version string to parse.
	 * @return string The base version without unstable suffix.
	 */
	private function get_base_version( string $version ): string {
		// If version contains a hyphen, extract the base version before it.
		if ( strpos( $version, '-' ) !== false ) {
			return substr( $version, 0, strpos( $version, '-' ) );
		}

		return $version;
	}

	/**
	 * Gets the last version of the plugin that ran fixes.
	 *
	 * @return string|null The last version that ran, or null if never run.
	 */
	private function get_last_run_version(): ?string {
		return get_option( 'gatherpress_alpha_last_version', null );
	}

	/**
	 * Sets the last version of the plugin that ran fixes.
	 *
	 * @param string $version The version to store.
	 * @return void
	 */
	private function set_last_run_version( string $version ): void {
		update_option( 'gatherpress_alpha_last_version', $version );
	}

	/**
	 * Checks if a fix version should be run based on the last run version.
	 *
	 * @param string      $fix_version  The version of the fix to check (e.g., "0.29.0").
	 * @param string|null $last_version The last version that ran, or null if never run.
	 * @return bool True if the fix should run, false otherwise.
	 */
	private function should_run_fix( string $fix_version, ?string $last_version ): bool {
		// If no version has been stored, run all fixes.
		if ( null === $last_version ) {
			return true;
		}

		// Extract base version from last run version (remove unstable suffix).
		$last_base_version = $this->get_base_version( $last_version );

		// Check if the last version was an unstable release (contains a hyphen).
		$is_unstable = strpos( $last_version, '-' ) !== false;

		// If it was an unstable release, run fixes >= base version (e.g., 0.33.0-alpha.1 should run 0.33.0).
		// If it was stable, only run fixes > base version.
		if ( $is_unstable ) {
			return version_compare( $fix_version, $last_base_version, '>=' );
		}

		return version_compare( $fix_version, $last_base_version, '>' );
	}

	/**
	 * Applies fixes specific to different versions of the plugin.
	 *
	 * This method calls version-specific fix methods to ensure compatibility
	 * and correct issues introduced in various plugin versions.
	 * It checks user capabilities to ensure the user has the appropriate permissions:
	 * - For multisite: the user must have the 'manage_network' capability.
	 * - For single site: the user must have the 'manage_options' capability.
	 * - If run via WP CLI, permission checks are bypassed.
	 *
	 * Only runs fixes for versions newer than the last recorded version.
	 *
	 * @return void
	 */
	public function fix(): void {
		// Check if running via WP CLI
		$is_cli = defined( 'WP_CLI' ) && WP_CLI;

		if ( is_multisite() && ( $is_cli || current_user_can( 'manage_network' ) ) ) {
			$sites = get_sites();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				$this->run_fixes();

				restore_current_blog();
			}
		} elseif ( $is_cli || current_user_can( 'manage_options' ) ) {
			$this->run_fixes();
		} else {
			wp_die( __( 'You do not have permission to perform this action.', 'gatherpress-alpha' ) );
		}
	}

	/**
	 * Runs version-specific fixes based on the last recorded version.
	 *
	 * @return void
	 */
	private function run_fixes(): void {
		$last_version = $this->get_last_run_version();

		if ( $this->should_run_fix( '0.29.0', $last_version ) ) {
			$this->fix__0_29_0();
		}

		if ( $this->should_run_fix( '0.30.0', $last_version ) ) {
			$this->fix__0_30_0();
		}

		if ( $this->should_run_fix( '0.31.0', $last_version ) ) {
			$this->fix__0_31_0();
		}

		if ( $this->should_run_fix( '0.32.0', $last_version ) ) {
			$this->fix__0_32_0();
		}

		if ( $this->should_run_fix( '0.33.0', $last_version ) ) {
			$this->fix__0_33_0();
		}

		if ( $this->should_run_fix( '0.34.0', $last_version ) ) {
			$this->fix__0_34_0();
		}

		// Update the stored version to current plugin version.
		$this->set_last_run_version( GATHERPRESS_ALPHA_VERSION );
	}

	/**
	 * Fixes specific data issues that changed in 0.29.0 of the plugin.
	 *
	 * @return void
	 */
	private function fix__0_29_0(): void {
		global $wpdb;

		// Fix custom tables.
		$old_table_events = $wpdb->prefix . 'gp_events';
		$new_table_events = $wpdb->prefix . 'gatherpress_events';
		$old_table_rsvps  = $wpdb->prefix . 'gp_rsvps';
		$new_table_rsvps  = $wpdb->prefix . 'gatherpress_rsvps';

		$tables_to_check = array(
			$old_table_events => $new_table_events,
			$old_table_rsvps  => $new_table_rsvps
		);

		foreach ( $tables_to_check as $old_table => $new_table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );

			if ( $table_exists ) {
				$sql = $wpdb->prepare(
					'RENAME TABLE %i TO %i;',
					$old_table,
					$new_table
				);

				$wpdb->query( $sql );
			}
		}

		// Fix event post type.
		$old_post_type = 'gp_event';
		$new_post_type = 'gatherpress_event';
		$sql           = $wpdb->prepare(
			'UPDATE %i SET post_type = %s WHERE post_type = %s',
			$wpdb->posts,
			$new_post_type,
			$old_post_type
		);

		$wpdb->query( $sql );

		// Fix venue post type.
		$old_post_type = 'gp_venue';
		$new_post_type = 'gatherpress_venue';
		$sql           = $wpdb->prepare(
			'UPDATE %i SET post_type = %s WHERE post_type = %s',
			$wpdb->posts,
			$new_post_type,
			$old_post_type
		);

		$wpdb->query( $sql );

		// Fix post meta.
		$meta_keys = [
			'max_guest_limit',
			'enable_anonymous_rsvp',
			'enable_initial_decline',
			'online_event_link',
			'venue_information',
		];

		foreach ( $meta_keys as $key ) {
			$sql = $wpdb->prepare(
				'UPDATE %i SET meta_key = %s WHERE meta_key = %s',
				$wpdb->postmeta,
				'gatherpress_' . $key,
				$key
			);

			$wpdb->query( $sql );
		}

		// Fix topic taxonomy.
		$old_taxonomy = 'gp_topic';
		$new_taxonomy = 'gatherpress_topic';
		$sql          = $wpdb->prepare(
			'UPDATE %i SET taxonomy = %s WHERE taxonomy = %s',
			$wpdb->term_taxonomy,
			$new_taxonomy,
			$old_taxonomy
		);

		$wpdb->query( $sql );

		// Fix venue taxonomy.
		$old_taxonomy = '_gp_venue';
		$new_taxonomy = '_gatherpress_venue';
		$sql          = $wpdb->prepare(
			'UPDATE %i SET taxonomy = %s WHERE taxonomy = %s',
			$wpdb->term_taxonomy,
			$new_taxonomy,
			$old_taxonomy
		);

		$wpdb->query( $sql );

		// Fix user meta.
		$meta_keys = [
			'gp_date_format'          => 'gatherpress_date_format',
			'gp_event_updates_opt_in' => 'gatherpress_event_updates_opt_in',
			'gp_time_format'          => 'gatherpress_time_format',
			'gp_timezone'             => 'gatherpress_timezone',
		];

		foreach ( $meta_keys as $old_meta_key => $new_meta_key ) {
			$sql = $wpdb->prepare(
				'UPDATE %i SET meta_key = %s WHERE meta_key = %s',
				$wpdb->usermeta,
				$new_meta_key,
				$old_meta_key
			);

			$wpdb->query( $sql );
		}

		// Fix options.
		$option_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'gp_%'");

		foreach ( $option_names as $option_name ) {
			// Remove 'gp_' prefix and prepend 'gatherpress_'.
			$new_option_name = 'gatherpress_' . substr( $option_name, 3 );
			$sql             = $wpdb->prepare(
				'UPDATE %i SET option_name = %s WHERE option_name = %s',
				$wpdb->options,
				$new_option_name,
				$option_name
			);

			$wpdb->query( $sql );
		}
	}

	/**
	 * Fixes specific data issues that changed in 0.30.0 of the plugin.
	 *
	 * @return void
	 */
	private function fix__0_30_0(): void {
		global $wpdb;

		// Fix custom tables.
		$rsvp_table_name = $wpdb->prefix . 'gatherpress_rsvps';
		$sql             = $wpdb->prepare( "SHOW TABLES LIKE %s", $rsvp_table_name );
		$result          = $wpdb->get_var( $sql );

		if ( $result === $rsvp_table_name ) {
			$rsvp_query         = Rsvp_Query::get_instance();
			$rsvps              = (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT post_id, user_id, timestamp, status, guests, anonymous FROM %i',
					$rsvp_table_name
				),
				ARRAY_A
			);
			$grouped_by_post_id = [];

			foreach ( $rsvps as $key => $item ) {
				$post_id = $item['post_id'];

				if ( ! isset ( $grouped_by_post_id[ $post_id ] ) ) {
					$grouped_by_post_id[ $post_id ] = [];
				}

				$grouped_by_post_id[ $post_id ][] = $item;
			}

			foreach ( $grouped_by_post_id as $post_id => $items ) {
				$rsvp_object = new Rsvp( $post_id );

				foreach ( $items as $item ) {
					$rsvp_object->save( $item['user_id'], $item['status'], $item['anonymous'], $item['guests'] );
					$rsvp_comment = $rsvp_query->get_rsvp(
						array(
							'post_id' => $item['post_id'],
							'user_id' => $item['user_id'],
						)
					);

					if ( $rsvp_comment ) {
						wp_update_comment(
							array(
								'comment_ID'       => $rsvp_comment->comment_ID,
								'comment_date'     => $item['timestamp'],
								'comment_date_gmt' => get_gmt_from_date( $item['timestamp'] ),
							)
						);
					}
				}
			}

			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $rsvp_table_name ) );
		}

		// Update venue post meta to include latitude and longitude values.
		$meta_key = 'gatherpress_venue_information';

		$query = $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s",
			$meta_key
		);

		$results = $wpdb->get_results( $query );

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$post_id    = $result->post_id;
				$meta_value = $result->meta_value;
				$meta_value = json_decode( $meta_value );

				if (
					is_object( $meta_value ) &&
					( empty( $meta_value->latitude ) || empty( $meta_value->longitude ) ) &&
					! empty( $meta_value->fullAddress )
				) {
					$geolocation = wp_safe_remote_get(
						'https://nominatim.openstreetmap.org/search?q=' . urlencode( $meta_value->fullAddress ) . '&format=geojson'
					);

					if ( ! is_wp_error( $geolocation ) ) {
						$body = wp_remote_retrieve_body( $geolocation );
						$data = json_decode( $body );

						if ( ! empty( $data ) && isset( $data->features[0]->geometry->coordinates ) ) {
							$coordinates = $data->features[0]->geometry->coordinates;
							$latitude    = $coordinates[1];
							$longitude   = $coordinates[0];

							$meta_value->latitude  = $latitude;
							$meta_value->longitude = $longitude;

							update_post_meta( $post_id, 'gatherpress_venue_information', json_encode( $meta_value ) );
						}
					}
				}
			}
		}

		// Fix options.
		$sql = $wpdb->prepare(
			'UPDATE %i SET option_name = %s WHERE option_name = %s',
			$wpdb->options,
			'gatherpress_suppress_site_notification',
			'gatherpress_suppress_membership_notification'
		);

		$wpdb->query( $sql );
	}

	/**
	 * Fixes specific data issues that changed in 0.31.0 of the plugin.
	 *
	 * @return void
	 */
	private function fix__0_31_0(): void {
		// Add datetime meta and resave.
		$batch_size = 100;
		$paged      = 1;

		do {
			$args  = array(
				'post_type'      => 'gatherpress_event',
				'posts_per_page' => $batch_size,
				'paged'          => $paged,
				'fields'         => 'ids',
			);
			$query = new WP_Query( $args );

			if ( ! $query->have_posts() ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				if ( get_post_meta( $post_id, 'gatherpress_datetime', true ) ) {
					continue;
				}

				$event = new Event( $post_id );
				$data  = $event->get_datetime();
				$meta  = json_encode (
					array(
						'dateTimeStart' => $data['datetime_start'],
						'dateTimeEnd'   => $data['datetime_end'],
						'timezone'      => $data['timezone'],

					)
				);

				update_post_meta( $post_id, 'gatherpress_datetime', $meta );

				$event->save_datetimes( $data );
			}

			wp_reset_postdata();

			$paged++;
		} while ( $query->have_posts() );
	}

	/**
	 * Fixes specific data issues that changed in 0.32.0 of the plugin.
	 *
	 * @return void
	 */
	private function fix__0_32_0(): void {
		global $wpdb;

		$rsvp_template     = file_get_contents( GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/rsvp-0.32.0.html' );
		$response_template = file_get_contents( GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/rsvp-response-0.32.0.html' );

		$wpdb->query( $wpdb->prepare( "
			UPDATE {$wpdb->posts}
			SET post_content = REPLACE(post_content, '<!-- wp:gatherpress/rsvp /-->', %s)
			WHERE post_type = 'gatherpress_event'
		", $rsvp_template ) );

		$wpdb->query( $wpdb->prepare( "
			UPDATE {$wpdb->posts}
			SET post_content = REPLACE(post_content, '<!-- wp:gatherpress/rsvp-response /-->', %s)
			WHERE post_type = 'gatherpress_event'
		", $response_template ) );
	}

	/**
	 * Fixes specific data issues that changed in 0.33.0 of the plugin.
	 *
	 * @return void
	 */
	private function fix__0_33_0(): void {
		global $wpdb;

		$add_to_calendar_template = file_get_contents( GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/add-to-calendar-0.33.0.html' );

		$wpdb->query( $wpdb->prepare( "
			UPDATE {$wpdb->posts}
			SET post_content = REPLACE(post_content, '<!-- wp:gatherpress/add-to-calendar /-->', %s)
			WHERE post_type = 'gatherpress_event'
		", $add_to_calendar_template ) );

		// Fix CSS class names that changed in 0.33.0.
		$this->fix_css_class_names__0_33_0();

		// Replace deprecated blocks using parse/serialize approach (same as class name replacements).
		$this->replace_deprecated_blocks();

		delete_option( 'gatherpress_suppress_site_notification' );
		delete_option( 'gatherpress_flush_rewrite_rules_flag' );
	}

	/**
	 * Fixes CSS class names that changed in 0.33.0 of the plugin.
	 *
	 * Updates stored block content in the database to use the new CSS class naming conventions.
	 * This is necessary because block content is serialized and stored in the database,
	 * so old class names would persist without this migration.
	 *
	 * @return void
	 */
	private function fix_css_class_names__0_33_0(): void {
		global $wpdb;


		// Define class name mappings for safe replacement.
		// Note: Order matters! More specific patterns should come first to avoid conflicts.
		$class_mappings = array(
			// Modal trigger classes (action-like to component pattern).
			'gatherpress--open-modal'        => 'gatherpress-modal--trigger-open',
			'gatherpress--close-modal'       => 'gatherpress-modal--trigger-close',

			// Modal identifier classes (modifier to component pattern).
			'gatherpress--is-rsvp-modal'     => 'gatherpress-modal--type-rsvp',
			'gatherpress--is-login-modal'    => 'gatherpress-modal--type-login',

			// Visibility classes (negative to positive form).
			'gatherpress--is-not-visible'    => 'gatherpress--is-hidden',

			// Field type classes (type-prefix to component pattern).
			'gatherpress-field-type-checkbox' => 'gatherpress-form-field--checkbox',
			'gatherpress-field-type-radio'    => 'gatherpress-form-field--radio',
			'gatherpress-field-type-text'     => 'gatherpress-form-field--text',
			'gatherpress-field-type-email'    => 'gatherpress-form-field--email',
			'gatherpress-field-type-textarea' => 'gatherpress-form-field--textarea',
			'gatherpress-field-type-number'   => 'gatherpress-form-field--number',
			'gatherpress-field-type-url'      => 'gatherpress-form-field--url',
			'gatherpress-field-type-tel'      => 'gatherpress-form-field--tel',
			'gatherpress-field-type-select'   => 'gatherpress-form-field--select',
			'gatherpress-field-type-hidden'   => 'gatherpress-form-field--hidden',

			// RSVP state classes (action-like to state-like).
			'gatherpress--rsvp-attending'     => 'gatherpress--is-attending',
			'gatherpress--rsvp-waiting-list'  => 'gatherpress--is-waiting-list',
			'gatherpress--rsvp-not-attending' => 'gatherpress--is-not-attending',

			// RSVP action classes.
			'gatherpress--empty-rsvp'         => 'gatherpress-rsvp-response--no-responses',
			'gatherpress--update-rsvp'        => 'gatherpress-rsvp--trigger-update',
		);

		// Update post content for all post types that might contain GatherPress blocks.
		$post_types = array( 'gatherpress_event', 'page', 'post', 'wp_template', 'wp_template_part', 'wp_block' );

		// First, handle regular content with string replacement
		foreach ( $class_mappings as $old_class => $new_class ) {
			$replacement_patterns = $this->build_class_replacement_patterns( $old_class, $new_class );

			foreach ( $post_types as $post_type ) {
				foreach ( $replacement_patterns as $old_pattern => $new_pattern ) {
					// Skip serialized patterns - we'll handle those separately
					if ( false !== strpos( $old_pattern, 'u002d' ) ) {
						continue;
					}

					$sql = $wpdb->prepare(
						"UPDATE {$wpdb->posts}
						SET post_content = REPLACE(post_content, %s, %s)
						WHERE post_type = %s
						AND post_content LIKE %s",
						$old_pattern,
						$new_pattern,
						$post_type,
						'%' . $wpdb->esc_like( $old_pattern ) . '%'
					);

					$wpdb->query( $sql );
				}
			}
		}

		// Now handle serializedInnerBlocks using proper WordPress functions
		$this->fix_serialized_inner_blocks( $class_mappings );
	}

	/**
	 * Build replacement patterns for CSS class names.
	 *
	 * @param string $old_class The old class name to replace.
	 * @param string $new_class The new class name to use.
	 * @return array An associative array of old pattern => new pattern replacements.
	 */
	private function build_class_replacement_patterns( $old_class, $new_class ) {
		$patterns = array();

		// Based on exact patterns found in database content:
		// className: \\\u0022className\\\u0022:\\\u0022gatherpress\u002d\u002dopen-modal\\\u0022
		// div class: gatherpress\u002d\u002dopen-modal\\\u0022

		// Pattern 1: className attribute in block comments within serializedInnerBlocks
		// Format: gatherpress\u002d\u002dopen-modal
		$old_escaped = str_replace( '--', '\\u002d\\u002d', $old_class );
		$new_escaped = str_replace( '--', '\\u002d\\u002d', $new_class );
		$patterns["\\\\\\u0022className\\\\\\u0022:\\\\\\u0022{$old_escaped}\\\\\\u0022"] =
		          "\\\\\\u0022className\\\\\\u0022:\\\\\\u0022{$new_escaped}\\\\\\u0022";

		// Pattern 2: div class in rendered HTML within serializedInnerBlocks
		// Format: gatherpress\u002d\u002dopen-modal\\u0022 and gatherpress\u002d\u002dopen-modal\u0022
		$patterns["{$old_escaped}\\\\\\u0022"] = "{$new_escaped}\\\\\\u0022";
		$patterns["{$old_escaped}\\u0022"] = "{$new_escaped}\\u0022";
		$patterns[" {$old_escaped}\\\\\\u0022"] = " {$new_escaped}\\\\\\u0022";
		$patterns[" {$old_escaped}\\u0022"] = " {$new_escaped}\\u0022";

		// Pattern 3: serializedInnerBlocks will be handled separately with proper WordPress functions
		// No need for complex string replacement patterns here

		// Pattern 4: Regular unescaped patterns for rendered content
		$patterns["\"{$old_class}\""] = "\"{$new_class}\"";
		$patterns["'{$old_class}'"] = "'{$new_class}'";
		$patterns[" {$old_class} "] = " {$new_class} ";
		$patterns[" {$old_class}\""] = " {$new_class}\"";
		$patterns[" {$old_class}'"] = " {$new_class}'";

		return $patterns;
	}

	/**
	 * Fix class names in serialized inner blocks using WordPress functions.
	 *
	 * This method properly deserializes the inner blocks data, performs
	 * class name replacements on the actual PHP data structures, then
	 * re-serializes and saves the updated data.
	 *
	 * @param array $class_mappings Array of old_class => new_class mappings.
	 * @return void
	 */
	private function fix_serialized_inner_blocks( array $class_mappings ): void {
		global $wpdb;

		// Find posts with serializedInnerBlocks.
		$posts = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_content LIKE '%serializedInnerBlocks%'
			AND post_content LIKE '%gatherpress%'"
		);

		foreach ( $posts as $post ) {
			$content = $post->post_content;
			$updated = false;

			// Parse the post content as blocks.
			$blocks = parse_blocks( $content );

			// Recursively process blocks to find RSVP blocks.
			$updated_blocks = $this->update_rsvp_blocks_recursive( $blocks, $class_mappings, $updated );

			if ( $updated ) {
				// Re-serialize and save.
				$new_content = serialize_blocks( $updated_blocks );
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Recursively update RSVP blocks and their serialized inner blocks.
	 *
	 * @param array $blocks Array of block data.
	 * @param array $class_mappings Array of old_class => new_class mappings.
	 * @param bool  $updated Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function update_rsvp_blocks_recursive( array $blocks, array $class_mappings, bool &$updated ): array {
		foreach ( $blocks as &$block ) {
			// Process RSVP blocks with serializedInnerBlocks.
			if ( 'gatherpress/rsvp' === $block['blockName'] && ! empty( $block['attrs']['serializedInnerBlocks'] ) ) {
				$serialized_data = $block['attrs']['serializedInnerBlocks'];
				$original_data = $serialized_data;

				// CRITICAL: Replace OLD class names in the raw JSON string BEFORE parsing.
				// The issue is that the HTML content in the JSON has old class names,
				// even though the block attributes may have new ones.
				foreach ( $class_mappings as $old_class => $new_class ) {
					// Replace unescaped versions (in HTML content).
					$serialized_data = str_replace( $old_class, $new_class, $serialized_data );

					// Also replace escaped versions (in block attributes).
					$old_escaped = str_replace( '-', '\\u002d', $old_class );
					$new_escaped = str_replace( '-', '\\u002d', $new_class );
					$serialized_data = str_replace( $old_escaped, $new_escaped, $serialized_data );
				}

				// If we made replacements on the raw JSON string, save it back.
				if ( $serialized_data !== $original_data ) {
					$block['attrs']['serializedInnerBlocks'] = $serialized_data;
					$updated = true;
				}

				// LEGACY: Also process via parse/serialize for block attribute updates.
				// (keeping this for backwards compatibility, though raw replacement should handle most cases).
				$inner_blocks_data = json_decode( $serialized_data, true );

				if ( is_array( $inner_blocks_data ) ) {
					$data_updated = false;

					foreach ( $inner_blocks_data as $status => $serialized_blocks ) {
						// Parse the serialized blocks.
						$status_blocks = parse_blocks( $serialized_blocks );

						// Update class names in these blocks (this handles edge cases).
						$updated_status_blocks = $this->update_class_names_in_blocks( $status_blocks, $class_mappings, $data_updated );

						if ( $data_updated ) {
							// Re-serialize the updated blocks.
							$inner_blocks_data[ $status ] = serialize_blocks( $updated_status_blocks );
							$updated = true;
						}
					}

					if ( $data_updated ) {
						// Update the serializedInnerBlocks attribute.
						$block['attrs']['serializedInnerBlocks'] = wp_json_encode( $inner_blocks_data );
					}
				}
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->update_rsvp_blocks_recursive( $block['innerBlocks'], $class_mappings, $updated );
			}
		}

		return $blocks;
	}

	/**
	 * Update class names in blocks array.
	 *
	 * @param array $blocks Array of block data.
	 * @param array $class_mappings Array of old_class => new_class mappings.
	 * @param bool  $updated Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function update_class_names_in_blocks( array $blocks, array $class_mappings, bool &$updated ): array {
		foreach ( $blocks as &$block ) {
			// Update className attribute.
			if ( ! empty( $block['attrs']['className'] ) ) {
				$old_class = $block['attrs']['className'];
				$new_class = $old_class;

				foreach ( $class_mappings as $old => $new ) {
					if ( strpos( $new_class, $old ) !== false ) {
						$new_class = str_replace( $old, $new, $new_class );
					}
				}

				if ( $new_class !== $old_class ) {
					$block['attrs']['className'] = $new_class;
					$updated = true;
				}
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->update_class_names_in_blocks( $block['innerBlocks'], $class_mappings, $updated );
			}
		}

		return $blocks;
	}

	/**
	 * Replace deprecated blocks with new block configurations.
	 *
	 * Replaces old blocks (rsvp-guest-count-input, rsvp-anonymous-checkbox)
	 * with the new form-field block using parse/serialize approach.
	 *
	 * @return void
	 */
	private function replace_deprecated_blocks(): void {
		global $wpdb;

		// Find posts that might have the old blocks.
		$posts = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_type = 'gatherpress_event'
			AND (post_content LIKE '%rsvp-guest-count-input%' OR post_content LIKE '%rsvp-anonymous-checkbox%')"
		);

		foreach ( $posts as $post ) {
			$content = $post->post_content;
			$updated = false;

			// Parse the post content as blocks.
			$blocks = parse_blocks( $content );

			// Recursively process blocks to replace deprecated ones.
			$updated_blocks = $this->replace_blocks_recursive( $blocks, $updated );

			if ( $updated ) {
				// Re-serialize and save.
				$new_content = serialize_blocks( $updated_blocks );
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Recursively replace deprecated blocks in blocks array and serializedInnerBlocks.
	 *
	 * @param array $blocks  Array of block data.
	 * @param bool  $updated Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function replace_blocks_recursive( array $blocks, bool &$updated ): array {
		foreach ( $blocks as &$block ) {
			// Replace deprecated blocks.
			if ( 'gatherpress/rsvp-guest-count-input' === $block['blockName'] ) {
				$block['blockName'] = 'gatherpress/form-field';
				$block['attrs'] = array(
					'className'     => 'gatherpress-rsvp-field-guests',
					'fieldType'     => 'number',
					'fieldName'     => 'gatherpress_rsvp_guests',
					'label'         => 'Number of guests?',
					'placeholder'   => '0',
					'minValue'      => 0,
					'inlineLayout'  => true,
					'fieldWidth'    => 10,
					'inputPadding'  => 5,
					'autocomplete'  => 'off',
				);
				$updated = true;
			} elseif ( 'gatherpress/rsvp-anonymous-checkbox' === $block['blockName'] ) {
				$block['blockName'] = 'gatherpress/form-field';
				$block['attrs'] = array(
					'className'    => 'gatherpress-rsvp-field-anonymous',
					'fieldType'    => 'checkbox',
					'fieldName'    => 'gatherpress_rsvp_anonymous',
					'label'        => 'List me as anonymous.',
					'autocomplete' => 'off',
				);
				$updated = true;
			}

			// Process RSVP blocks with serializedInnerBlocks (same pattern as class name replacement).
			if ( 'gatherpress/rsvp' === $block['blockName'] && ! empty( $block['attrs']['serializedInnerBlocks'] ) ) {
				$serialized_data = $block['attrs']['serializedInnerBlocks'];
				$inner_blocks_data = json_decode( $serialized_data, true );

				if ( is_array( $inner_blocks_data ) ) {
					$data_updated = false;

					foreach ( $inner_blocks_data as $status => $serialized_blocks ) {
						// Parse the serialized blocks.
						$status_blocks = parse_blocks( $serialized_blocks );

						// Replace deprecated blocks in these blocks.
						$updated_status_blocks = $this->replace_blocks_in_inner( $status_blocks, $data_updated );

						if ( $data_updated ) {
							// Re-serialize the updated blocks.
							$inner_blocks_data[ $status ] = serialize_blocks( $updated_status_blocks );
							$updated = true;
						}
					}

					if ( $data_updated ) {
						// Update the serializedInnerBlocks attribute.
						$block['attrs']['serializedInnerBlocks'] = wp_json_encode( $inner_blocks_data );
					}
				}
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->replace_blocks_recursive( $block['innerBlocks'], $updated );
			}
		}

		return $blocks;
	}

	/**
	 * Replace deprecated blocks in inner blocks array (for serializedInnerBlocks).
	 *
	 * @param array $blocks  Array of block data.
	 * @param bool  $updated Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function replace_blocks_in_inner( array $blocks, bool &$updated ): array {
		foreach ( $blocks as &$block ) {
			// Replace deprecated blocks.
			if ( 'gatherpress/rsvp-guest-count-input' === $block['blockName'] ) {
				$block['blockName'] = 'gatherpress/form-field';
				$block['attrs'] = array(
					'className'     => 'gatherpress-rsvp-field-guests',
					'fieldType'     => 'number',
					'fieldName'     => 'gatherpress_rsvp_guests',
					'label'         => 'Number of guests?',
					'placeholder'   => '0',
					'minValue'      => 0,
					'inlineLayout'  => true,
					'fieldWidth'    => 10,
					'inputPadding'  => 5,
					'autocomplete'  => 'off',
				);
				$updated = true;
			} elseif ( 'gatherpress/rsvp-anonymous-checkbox' === $block['blockName'] ) {
				$block['blockName'] = 'gatherpress/form-field';
				$block['attrs'] = array(
					'className'    => 'gatherpress-rsvp-field-anonymous',
					'fieldType'    => 'checkbox',
					'fieldName'    => 'gatherpress_rsvp_anonymous',
					'label'        => 'List me as anonymous.',
					'autocomplete' => 'off',
				);
				$updated = true;
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->replace_blocks_in_inner( $block['innerBlocks'], $updated );
			}
		}

		return $blocks;
	}

	/**
	 * Fixes specific data issues that changed in 0.34.0 of the plugin.
	 *
	 * Migrates the deprecated `gatherpress/events-list` block to the new
	 * `gatherpress-event-query` variation of `core/query`.
	 *
	 * @return void
	 */
	private function fix__0_34_0(): void {
		$this->migrate_settings_to_flat();
		$this->replace_events_list_block();
		$this->replace_venue_block();
		$this->replace_online_event_block();
		$this->migrate_venue_information_to_flat();
	}

	/**
	 * Migrates the JSON `gatherpress_venue_information` meta blob into individual
	 * venue meta keys.
	 *
	 * Pre-0.34.0 GatherPress stored the venue address, phone, website, latitude,
	 * and longitude as a JSON-encoded string under `gatherpress_venue_information`.
	 *
	 * In 0.34.0 those become individual editor-writable meta keys
	 * (`gatherpress_full_address`, `gatherpress_phone_number`, `gatherpress_website`,
	 * `gatherpress_latitude`, `gatherpress_longitude`) so they can be bound to
	 * blocks via `core/post-meta` block bindings without an intermediate JSON
	 * parse step.
	 *
	 * For each venue post that still carries the JSON blob this method:
	 * - Decodes the JSON,
	 * - Writes any non-empty fields to the new individual meta keys
	 *   (without overwriting values the new editor has already saved),
	 * - Deletes the original JSON blob.
	 *
	 * @return void
	 */
	private function migrate_venue_information_to_flat(): void {
		global $wpdb;

		// Map JSON keys to the new individual meta keys.
		$field_map = array(
			'fullAddress' => 'gatherpress_address',
			'latitude'    => 'gatherpress_latitude',
			'longitude'   => 'gatherpress_longitude',
			'phoneNumber' => 'gatherpress_phone',
			'website'     => 'gatherpress_website',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'gatherpress_venue_information'
			)
		);

		foreach ( $rows as $row ) {
			$post_id = (int) $row->post_id;
			$decoded = json_decode( (string) $row->meta_value, true );

			if ( is_array( $decoded ) ) {
				foreach ( $field_map as $json_key => $meta_key ) {
					if ( ! isset( $decoded[ $json_key ] ) ) {
						continue;
					}

					$value = (string) $decoded[ $json_key ];

					if ( '' === $value ) {
						continue;
					}

					// Don't overwrite values the new editor has already written.
					if ( '' !== (string) get_post_meta( $post_id, $meta_key, true ) ) {
						continue;
					}

					update_post_meta( $post_id, $meta_key, $value );
				}
			}

			delete_post_meta( $post_id, 'gatherpress_venue_information' );
		}
	}

	/**
	 * Migrates settings from nested gatherpress_* options to a single flat
	 * gatherpress_settings option.
	 *
	 * Finds all gatherpress_* options that contain serialized nested arrays
	 * (the old section/option structure) and flattens them into
	 * gatherpress_settings. Also renames URL keys for clarity.
	 *
	 * @return void
	 */
	private function migrate_settings_to_flat(): void {
		global $wpdb;

		$new_settings = get_option( Settings::OPTION_NAME, array() );

		// Key renames: old key => new key.
		$key_renames = array(
			'events' => 'events_url',
			'venues' => 'venues_url',
			'topics' => 'topics_url',
		);

		// Options to skip — these are not legacy settings options.
		$skip_options = array(
			Settings::OPTION_NAME,
			'gatherpress_alpha_last_version',
		);

		// Find all gatherpress_* options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name LIKE %s',
				$wpdb->options,
				'gatherpress\_%'
			)
		);

		$old_options = array();

		foreach ( $option_names as $option_name ) {
			if ( in_array( $option_name, $skip_options, true ) ) {
				continue;
			}

			$value = get_option( $option_name );

			// Only migrate options with nested array data (the old section/option structure).
			if ( ! is_array( $value ) ) {
				continue;
			}

			// Check if it's a nested structure (arrays within arrays).
			$is_nested = false;

			foreach ( $value as $section_value ) {
				if ( is_array( $section_value ) ) {
					$is_nested = true;
					break;
				}
			}

			if ( ! $is_nested ) {
				continue;
			}

			// Flatten the nested sections into the new settings.
			foreach ( $value as $options ) {
				if ( ! is_array( $options ) ) {
					continue;
				}

				foreach ( $options as $key => $val ) {
					$new_key = $key_renames[ $key ] ?? $key;

					// Only migrate if not already set in the new option.
					if ( ! isset( $new_settings[ $new_key ] ) ) {
						$new_settings[ $new_key ] = $val;
					}
				}
			}

			$old_options[] = $option_name;
		}

		if ( ! empty( $old_options ) ) {
			// Strip values that match defaults to keep the option lean.
			$settings_instance = Settings::get_instance();

			foreach ( $new_settings as $key => $value ) {
				if ( $value === $settings_instance->get_flat_default( $key ) ) {
					unset( $new_settings[ $key ] );
				}
			}

			update_option( Settings::OPTION_NAME, $new_settings );

			// Clean up old options.
			foreach ( $old_options as $option_name ) {
				delete_option( $option_name );
			}
		}
	}

	/**
	 * Replaces deprecated self-closing `gatherpress/online-event` blocks.
	 *
	 * The old online event block was self-closing (`<!-- wp:gatherpress/online-event /-->`).
	 * This method replaces it with the new version containing inner blocks
	 * for the online event link and icon.
	 *
	 * @return void
	 */
	private function replace_online_event_block(): void {
		global $wpdb;

		$post_types   = array( 'gatherpress_event' );
		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// Find posts containing the old self-closing online event block.
		$query = $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_type IN ({$placeholders})
			AND post_content LIKE %s",
			array_merge( $post_types, array( '%gatherpress/online-event /-->%' ) )
		);

		$posts = $wpdb->get_results( $query );

		if ( empty( $posts ) ) {
			return;
		}

		// Load the new online event template.
		$template_path = GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/online-event-0.34.0.html';
		$template      = file_get_contents( $template_path );

		if ( empty( $template ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$content = $post->post_content;
			$blocks  = parse_blocks( $content );
			$updated = false;

			$updated_blocks = $this->replace_online_event_block_recursive( $blocks, $template, $updated );

			if ( $updated ) {
				$new_content = serialize_blocks( $updated_blocks );
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Recursively replaces self-closing `gatherpress/online-event` blocks in a block array.
	 *
	 * Only replaces online event blocks that have no inner blocks (self-closing).
	 * Online event blocks that already contain inner blocks are left unchanged.
	 *
	 * @param array  $blocks   Array of parsed block data.
	 * @param string $template The new online event HTML template.
	 * @param bool   $updated  Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function replace_online_event_block_recursive( array $blocks, string $template, bool &$updated ): array {
		$new_blocks = array();

		foreach ( $blocks as $block ) {
			if ( 'gatherpress/online-event' === $block['blockName'] && empty( $block['innerBlocks'] ) ) {
				// Replace self-closing online event block with the new template.
				$replacement_blocks = parse_blocks( $template );

				foreach ( $replacement_blocks as $replacement_block ) {
					if ( ! empty( $replacement_block['blockName'] ) ) {
						$new_blocks[] = $replacement_block;
					}
				}

				$updated = true;
			} else {
				// Recursively process inner blocks.
				if ( ! empty( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = $this->replace_online_event_block_recursive( $block['innerBlocks'], $template, $updated );
				}

				$new_blocks[] = $block;
			}
		}

		return $new_blocks;
	}

	/**
	 * Replaces deprecated self-closing `gatherpress/venue` blocks.
	 *
	 * The old venue block was self-closing (`<!-- wp:gatherpress/venue /-->`).
	 * This method checks whether the post has an online event venue term and
	 * replaces the block with either the online event template or the physical
	 * venue template accordingly.
	 *
	 * @return void
	 */
	private function replace_venue_block(): void {
		global $wpdb;

		$post_types   = array( 'gatherpress_event', 'gatherpress_venue' );
		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// Find posts containing the old self-closing venue block.
		$query = $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_type IN ({$placeholders})
			AND post_content LIKE %s",
			array_merge( $post_types, array( '%gatherpress/venue /-->%' ) )
		);

		$posts = $wpdb->get_results( $query );

		if ( empty( $posts ) ) {
			return;
		}

		// Load the venue, venue (no title), and online event templates.
		$venue_template_path     = GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/venue-0.34.0.html';
		$venue_template          = file_get_contents( $venue_template_path );
		$venue_no_title_path     = GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/venue-no-title-0.34.0.html';
		$venue_no_title_template = file_get_contents( $venue_no_title_path );
		$online_template_path    = GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/online-event-0.34.0.html';
		$online_template         = file_get_contents( $online_template_path );

		if ( empty( $venue_template ) || empty( $venue_no_title_template ) || empty( $online_template ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$content = $post->post_content;
			$blocks  = parse_blocks( $content );
			$updated = false;

			// Check if this post has the online-event venue term.
			$is_online = has_term( 'online-event', Venue::TAXONOMY, $post->ID );

			// Use the no-title venue template for venue CPT posts to avoid redundant title.
			if ( $is_online ) {
				$template = $online_template;
			} elseif ( Venue::POST_TYPE === get_post_type( $post->ID ) ) {
				$template = $venue_no_title_template;
			} else {
				$template = $venue_template;
			}

			$updated_blocks = $this->replace_venue_block_recursive( $blocks, $template, $updated );

			if ( $updated ) {
				$new_content = serialize_blocks( $updated_blocks );
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Recursively replaces self-closing `gatherpress/venue` blocks in a block array.
	 *
	 * Only replaces venue blocks that have no inner blocks (self-closing).
	 * Venue blocks that already contain inner blocks are left unchanged.
	 *
	 * @param array  $blocks   Array of parsed block data.
	 * @param string $template The new venue HTML template.
	 * @param bool   $updated  Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function replace_venue_block_recursive( array $blocks, string $template, bool &$updated ): array {
		$new_blocks = array();

		foreach ( $blocks as $block ) {
			if ( 'gatherpress/venue' === $block['blockName'] && empty( $block['innerBlocks'] ) ) {
				// Replace self-closing venue block with the new template.
				$replacement_blocks = parse_blocks( $template );

				foreach ( $replacement_blocks as $replacement_block ) {
					if ( ! empty( $replacement_block['blockName'] ) ) {
						$new_blocks[] = $replacement_block;
					}
				}

				$updated = true;
			} else {
				// Recursively process inner blocks.
				if ( ! empty( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = $this->replace_venue_block_recursive( $block['innerBlocks'], $template, $updated );
				}

				$new_blocks[] = $block;
			}
		}

		return $new_blocks;
	}

	/**
	 * Replaces deprecated `gatherpress/events-list` blocks with the Event Query variation.
	 *
	 * Searches post content for the old events-list block, extracts its attributes
	 * (type, maxNumberOfEvents), and replaces it with a `core/query` block using
	 * the `gatherpress-event-query` namespace and the default Event Query template.
	 *
	 * @return void
	 */
	private function replace_events_list_block(): void {
		global $wpdb;

		$post_types    = array( 'gatherpress_event', 'gatherpress_venue', 'page', 'post', 'wp_template', 'wp_template_part', 'wp_block' );
		$placeholders  = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// Find posts containing the old events-list block.
		$query = $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_type IN ({$placeholders})
			AND post_content LIKE %s",
			array_merge( $post_types, array( '%gatherpress/events-list%' ) )
		);

		$posts = $wpdb->get_results( $query );

		if ( empty( $posts ) ) {
			return;
		}

		// Load the Event Query template.
		$template_path = GATHERPRESS_ALPHA_CORE_PATH . '/includes/templates/events-list-0.34.0.html';
		$template      = file_get_contents( $template_path );

		if ( empty( $template ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$content = $post->post_content;
			$updated = false;

			// Parse the post content as blocks.
			$blocks = parse_blocks( $content );

			// Recursively replace events-list blocks.
			$updated_blocks = $this->replace_events_list_recursive( $blocks, $template, $updated );

			if ( $updated ) {
				$new_content = serialize_blocks( $updated_blocks );
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Recursively replaces `gatherpress/events-list` blocks in a block array.
	 *
	 * @param array  $blocks   Array of parsed block data.
	 * @param string $template The Event Query HTML template with placeholder tokens.
	 * @param bool   $updated  Reference to track if any updates were made.
	 * @return array Updated blocks array.
	 */
	private function replace_events_list_recursive( array $blocks, string $template, bool &$updated ): array {
		$new_blocks = array();

		foreach ( $blocks as $block ) {
			if ( 'gatherpress/events-list' === $block['blockName'] ) {
				$attrs         = $block['attrs'] ?? array();
				$event_options = $attrs['eventOptions'] ?? array();

				// Extract old attributes with defaults.
				$type              = ! empty( $attrs['type'] ) ? $attrs['type'] : 'upcoming';
				$max_number        = ! empty( $attrs['maxNumberOfEvents'] ) ? (int) $attrs['maxNumberOfEvents'] : 5;
				$date_format       = ! empty( $attrs['datetimeFormat'] ) ? $attrs['datetimeFormat'] : 'D, M j, Y, g:i a T';
				$order             = 'past' === $type ? 'desc' : 'asc';
				$event_query_type  = $type;

				// Extract taxonomy term IDs from full REST API response objects.
				$tax_query = $this->extract_tax_query( $attrs );

				// Extract display options with defaults matching the old block.
				$show_featured_image = isset( $event_options['showFeaturedImage'] ) ? (bool) $event_options['showFeaturedImage'] : true;
				$show_venue          = isset( $event_options['showVenue'] ) ? (bool) $event_options['showVenue'] : true;
				$show_rsvp           = isset( $event_options['showRsvp'] ) ? (bool) $event_options['showRsvp'] : true;
				$show_rsvp_response  = isset( $event_options['showRsvpResponse'] ) ? (bool) $event_options['showRsvpResponse'] : true;

				// Replace placeholder tokens in the template.
				$block_html = str_replace(
					array( '{{PER_PAGE}}', '{{ORDER}}', '{{EVENT_QUERY_TYPE}}', '{{DATE_FORMAT}}' ),
					array( $max_number, $order, $event_query_type, $date_format ),
					$template
				);

				// Parse the template into block structure.
				$replacement_blocks = parse_blocks( $block_html );

				// Inject taxQuery into the core/query block attributes if topics or venues were set.
				if ( ! empty( $tax_query ) ) {
					$replacement_blocks = $this->inject_tax_query( $replacement_blocks, $tax_query );
				}

				// Filter blocks based on display options.
				foreach ( $replacement_blocks as $replacement_block ) {
					// Skip empty/null blocks from parsing whitespace.
					if ( ! empty( $replacement_block['blockName'] ) ) {
						$filtered_block = $this->filter_event_query_blocks(
							$replacement_block,
							$show_featured_image,
							$show_venue,
							$show_rsvp,
							$show_rsvp_response
						);

						if ( is_array( $filtered_block ) && ! isset( $filtered_block['blockName'] ) ) {
							// Multiple blocks returned (unwrapped media-text children).
							foreach ( $filtered_block as $child_block ) {
								$new_blocks[] = $child_block;
							}
						} elseif ( ! empty( $filtered_block ) ) {
							$new_blocks[] = $filtered_block;
						}
					}
				}

				$updated = true;
			} else {
				// Recursively process inner blocks.
				if ( ! empty( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = $this->replace_events_list_recursive( $block['innerBlocks'], $template, $updated );
				}

				$new_blocks[] = $block;
			}
		}

		return $new_blocks;
	}

	/**
	 * Filters Event Query inner blocks based on old events-list display options.
	 *
	 * Removes or restructures blocks to match the display configuration
	 * from the deprecated events-list block. Walks through `innerContent`
	 * in sync with `innerBlocks` to preserve static HTML wrappers while
	 * correctly handling block removal and unwrapping.
	 *
	 * @param array $block               The block to filter.
	 * @param bool  $show_featured_image  Whether to show the featured image.
	 * @param bool  $show_venue           Whether to show the venue.
	 * @param bool  $show_rsvp            Whether to show the RSVP button.
	 * @param bool  $show_rsvp_response   Whether to show RSVP responses.
	 * @return array|null Filtered block, array of blocks (unwrapped), or null to remove.
	 */
	private function filter_event_query_blocks(
		array $block,
		bool $show_featured_image,
		bool $show_venue,
		bool $show_rsvp,
		bool $show_rsvp_response
	) {
		// Remove venue and online-event blocks if venue is not shown.
		// In the old events-list block, venue and online event shared the same display option.
		if ( ! $show_venue && ( 'gatherpress/venue' === $block['blockName'] || 'gatherpress/online-event' === $block['blockName'] ) ) {
			return null;
		}

		// Remove RSVP block if not shown.
		if ( ! $show_rsvp && 'gatherpress/rsvp' === $block['blockName'] ) {
			return null;
		}

		// Remove RSVP response and count blocks if not shown.
		if ( ! $show_rsvp_response ) {
			if ( 'gatherpress/rsvp-response' === $block['blockName'] || 'gatherpress/rsvp-count' === $block['blockName'] ) {
				return null;
			}
		}

		// Remove entire columns section if neither RSVP nor RSVP response is shown.
		if ( ! $show_rsvp && ! $show_rsvp_response && 'core/columns' === $block['blockName'] ) {
			return null;
		}

		// Handle media-text: unwrap to direct children if featured image is hidden.
		if ( ! $show_featured_image && 'core/media-text' === $block['blockName'] ) {
			// Return the filtered content inner blocks directly.
			$content_blocks = array();

			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as $inner_block ) {
					// Filter each unwrapped child individually.
					$filtered = $this->filter_event_query_blocks(
						$inner_block,
						$show_featured_image,
						$show_venue,
						$show_rsvp,
						$show_rsvp_response
					);

					if ( null === $filtered ) {
						continue;
					}

					if ( is_array( $filtered ) && ! isset( $filtered['blockName'] ) ) {
						// Multiple blocks returned (nested unwrap).
						foreach ( $filtered as $child_block ) {
							$content_blocks[] = $child_block;
						}
					} else {
						$content_blocks[] = $filtered;
					}
				}
			}

			return $content_blocks;
		}

		// Recursively filter inner blocks while preserving innerContent structure.
		if ( ! empty( $block['innerBlocks'] ) ) {
			$filtered_inner    = array();
			$new_inner_content = array();
			$inner_block_index = 0;

			foreach ( $block['innerContent'] as $content_item ) {
				if ( null === $content_item ) {
					// This null corresponds to innerBlocks[$inner_block_index].
					if ( ! isset( $block['innerBlocks'][ $inner_block_index ] ) ) {
						++$inner_block_index;
						continue;
					}

					$inner_block = $block['innerBlocks'][ $inner_block_index ];
					++$inner_block_index;

					$result = $this->filter_event_query_blocks(
						$inner_block,
						$show_featured_image,
						$show_venue,
						$show_rsvp,
						$show_rsvp_response
					);

					if ( null === $result ) {
						// Block removed — skip this null entry.
						continue;
					}

					if ( is_array( $result ) && ! isset( $result['blockName'] ) ) {
						// Multiple blocks returned (unwrapped children).
						foreach ( $result as $child_block ) {
							$filtered_inner[]    = $child_block;
							$new_inner_content[] = null;
						}
					} else {
						$filtered_inner[]    = $result;
						$new_inner_content[] = null;
					}
				} else {
					// Static HTML string — preserve it.
					$new_inner_content[] = $content_item;
				}
			}

			$block['innerBlocks']  = $filtered_inner;
			$block['innerContent'] = $new_inner_content;
		}

		return $block;
	}

	/**
	 * Extracts taxonomy term IDs from old events-list block attributes.
	 *
	 * The old events-list block stored full REST API response objects for
	 * topics and venues. This method extracts just the term IDs needed
	 * for the new Event Query Loop's taxQuery attribute.
	 *
	 * @param array $attrs The old block attributes.
	 * @return array Associative array of taxonomy => term IDs, or empty array.
	 */
	private function extract_tax_query( array $attrs ): array {
		$tax_query = array();

		// Extract topic term IDs from full REST objects.
		if ( ! empty( $attrs['topics'] ) && is_array( $attrs['topics'] ) ) {
			$topic_ids = array_filter(
				array_map(
					static function ( $topic ) {
						return isset( $topic['id'] ) ? (int) $topic['id'] : 0;
					},
					$attrs['topics']
				)
			);

			if ( ! empty( $topic_ids ) ) {
				$tax_query[ Topic::TAXONOMY ] = array_values( $topic_ids );
			}
		}

		// Extract venue term IDs from full REST objects.
		if ( ! empty( $attrs['venues'] ) && is_array( $attrs['venues'] ) ) {
			$venue_ids = array_filter(
				array_map(
					static function ( $venue ) {
						return isset( $venue['id'] ) ? (int) $venue['id'] : 0;
					},
					$attrs['venues']
				)
			);

			if ( ! empty( $venue_ids ) ) {
				$tax_query[ Venue::TAXONOMY ] = array_values( $venue_ids );
			}
		}

		return $tax_query;
	}

	/**
	 * Injects taxQuery into the core/query block attributes.
	 *
	 * After parsing the template into blocks, this method finds the
	 * core/query block and adds the taxQuery attribute to its query
	 * configuration, enabling taxonomy-based filtering.
	 *
	 * @param array $blocks    Array of parsed blocks.
	 * @param array $tax_query Associative array of taxonomy => term IDs.
	 * @return array Updated blocks array with taxQuery injected.
	 */
	private function inject_tax_query( array $blocks, array $tax_query ): array {
		foreach ( $blocks as &$block ) {
			if ( 'core/query' === $block['blockName'] ) {
				$block['attrs']['query']['taxQuery'] = $tax_query;
				break;
			}
		}

		return $blocks;
	}
}
