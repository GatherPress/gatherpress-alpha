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
	 * This function modifies the provided sub-pages array to include a new sub-page
	 * for GatherPress Alpha with specified details such as name, priority, and sections.
	 *
	 * @param array $sub_pages An associative array of existing sub-pages.
	 * @return array Modified array of sub-pages including the GatherPress Alpha sub-page.
	 */
	public function setup_sub_page( $sub_pages ): array {
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
	 * Applies fixes specific to different versions of the plugin.
	 *
	 * This method calls version-specific fix methods to ensure compatibility
	 * and correct issues introduced in various plugin versions.
	 * It checks user capabilities to ensure the user has the appropriate permissions:
	 * - For multisite: the user must have the 'manage_network' capability.
	 * - For single site: the user must have the 'manage_options' capability.
	 * - If run via WP CLI, permission checks are bypassed.
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

				$this->fix__0_29_0();
				$this->fix__0_30_0();
				$this->fix__0_31_0();
				$this->fix__0_32_0();
				$this->fix__0_33_0();

				restore_current_blog();
			}
		} elseif ( $is_cli || current_user_can( 'manage_options' ) ) {
			$this->fix__0_29_0();
			$this->fix__0_30_0();
			$this->fix__0_31_0();
			$this->fix__0_32_0();
			$this->fix__0_33_0();
		} else {
			wp_die( __( 'You do not have permission to perform this action.', 'gatherpress-alpha' ) );
		}
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

		delete_option( 'gatherpress_suppress_site_notification' );
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
			'gatherpress--is-rsvp-modal'     => 'gatherpress-modal--rsvp',
			'gatherpress--is-login-modal'    => 'gatherpress-modal--login',
			
			// Visibility classes (negative to positive form).
			'gatherpress--is-not-visible'    => 'gatherpress--is-hidden',
			
			// Field type classes (type-prefix to modifier pattern).
			'gatherpress-field-type-checkbox' => 'gatherpress-field--checkbox',
			'gatherpress-field-type-radio'    => 'gatherpress-field--radio',
			'gatherpress-field-type-text'     => 'gatherpress-field--text',
			'gatherpress-field-type-email'    => 'gatherpress-field--email',
			'gatherpress-field-type-textarea' => 'gatherpress-field--textarea',
			'gatherpress-field-type-number'   => 'gatherpress-field--number',
			'gatherpress-field-type-url'      => 'gatherpress-field--url',
			'gatherpress-field-type-tel'      => 'gatherpress-field--tel',
			'gatherpress-field-type-select'   => 'gatherpress-field--select',
			'gatherpress-field-type-hidden'   => 'gatherpress-field--hidden',
			
			// RSVP state classes (action-like to state-like).
			'gatherpress--rsvp-attending'     => 'gatherpress--is-attending',
			'gatherpress--rsvp-waiting-list'  => 'gatherpress--is-waiting-list',
			'gatherpress--rsvp-not-attending' => 'gatherpress--is-not-attending',
			
			// RSVP action classes.
			'gatherpress--empty-rsvp'         => 'gatherpress--is-empty',
			'gatherpress--update-rsvp'        => 'gatherpress--has-rsvp-update',
		);

		// Update post content for all post types that might contain GatherPress blocks.
		$post_types = array( 'gatherpress_event', 'page', 'post' );
		
		foreach ( $post_types as $post_type ) {
			foreach ( $class_mappings as $old_class => $new_class ) {
				// Only process exact class name matches to avoid partial replacements.
				// This approach is safer for database content migration.
				$exact_patterns = array(
					// Exact className attribute matches.
					"className=\"{$old_class}\"" => "className=\"{$new_class}\"",
					"className='{$old_class}'" => "className='{$new_class}'",
					
					// Class at start of className with space after.
					"className=\"{$old_class} " => "className=\"{$new_class} ",
					"className='{$old_class} " => "className='{$new_class} ",
					
					// Class at end of className with space before.
					" {$old_class}\"" => " {$new_class}\"",
					" {$old_class}'" => " {$new_class}'",
					
					// Class in middle with spaces on both sides.
					" {$old_class} " => " {$new_class} ",
					
					// Same patterns for rendered HTML class attributes.
					"class=\"{$old_class}\"" => "class=\"{$new_class}\"",
					"class='{$old_class}'" => "class='{$new_class}'",
					"class=\"{$old_class} " => "class=\"{$new_class} ",
					"class='{$old_class} " => "class='{$new_class} ",
				);
				
				foreach ( $exact_patterns as $old_pattern => $new_pattern ) {
					$sql = $wpdb->prepare( "
						UPDATE {$wpdb->posts}
						SET post_content = REPLACE(post_content, %s, %s)
						WHERE post_type = %s
						AND post_content LIKE %s
					", $old_pattern, $new_pattern, $post_type, '%' . $old_pattern . '%' );
					
					$wpdb->query( $sql );
				}
			}
		}

		// Also update any saved block patterns or reusable blocks.
		foreach ( $class_mappings as $old_class => $new_class ) {
			$exact_patterns = array(
				"className=\"{$old_class}\"" => "className=\"{$new_class}\"",
				"className='{$old_class}'" => "className='{$new_class}'",
				"className=\"{$old_class} " => "className=\"{$new_class} ",
				"className='{$old_class} " => "className='{$new_class} ",
				" {$old_class}\"" => " {$new_class}\"",
				" {$old_class}'" => " {$new_class}'",
				" {$old_class} " => " {$new_class} ",
			);
			
			foreach ( $exact_patterns as $old_pattern => $new_pattern ) {
				$sql = $wpdb->prepare( "
					UPDATE {$wpdb->posts}
					SET post_content = REPLACE(post_content, %s, %s)
					WHERE post_type = 'wp_block'
					AND post_content LIKE %s
				", $old_pattern, $new_pattern, '%' . $old_pattern . '%' );
				
				$wpdb->query( $sql );
			}
		}
	}
}
