<?php
/**
 * Widgets and Menus Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.3.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widgets and Menus Handler Class - Manages widgets and navigation menus export/import
 */
class PEIWM_Widgets_Menus_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Widgets_Menus_Handler|null
	 */
	private static $instance = null;

	/**
	 * Saved global post state
	 *
	 * @var object|null
	 */
	private $saved_global_post = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Widgets_Menus_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_ajax_hooks();
		
		// Add action to completely isolate global post state during AJAX operations
		add_action( 'wp_ajax_peiwm_import_widgets_menus', array( $this, 'isolate_global_post_state' ), 1 );
		add_action( 'wp_ajax_peiwm_import_widgets', array( $this, 'isolate_global_post_state' ), 1 );
		add_action( 'wp_ajax_peiwm_import_nav_menus', array( $this, 'isolate_global_post_state' ), 1 );
	}

	/**
	 * Completely isolate global post state during operations
	 */
	public function isolate_global_post_state() {
		global $post, $wp_query;
		
		// Save current state
		$this->saved_global_post = $post;
		$saved_wp_query = $wp_query;
		
		// Set global post to null to prevent any interference
		$post = null;
		
		// Create a clean WP_Query to prevent issues
		$wp_query = new WP_Query();
		
		// Add action to restore state after AJAX response
		add_action( 'wp_die', function() use ( $saved_wp_query ) {
			global $post, $wp_query;
			$post = $this->saved_global_post;
			$wp_query = $saved_wp_query;
		}, 1 );
	}

	/**
	 * Save the current global post state
	 */
	private function save_global_post_state() {
		global $post;
		$this->saved_global_post = $post;
	}

	/**
	 * Restore the saved global post state
	 */
	private function restore_global_post_state() {
		global $post;
		$post = $this->saved_global_post;
		if ( $post ) {
			setup_postdata( $post );
		} else {
			wp_reset_postdata();
		}
	}

	/**
	 * Safely execute a callback while protecting global post state
	 *
	 * @param callable $callback The callback to execute
	 * @param array    $args     Arguments to pass to the callback
	 * @return mixed The callback result
	 */
	private function execute_with_post_protection( $callback, $args = array() ) {
		$this->save_global_post_state();
		
		try {
			$result = call_user_func_array( $callback, $args );
			return $result;
		} finally {
			$this->restore_global_post_state();
		}
	}

	/**
	 * Initialize AJAX hooks
	 */
	private function init_ajax_hooks() {
		// Widgets
		add_action( 'wp_ajax_peiwm_export_widgets', array( $this, 'ajax_export_widgets' ) );
		add_action( 'wp_ajax_peiwm_import_widgets', array( $this, 'ajax_import_widgets' ) );
		
		// Navigation Menus
		add_action( 'wp_ajax_peiwm_export_nav_menus', array( $this, 'ajax_export_nav_menus' ) );
		add_action( 'wp_ajax_peiwm_import_nav_menus', array( $this, 'ajax_import_nav_menus' ) );
		
		// Combined export
		add_action( 'wp_ajax_peiwm_export_widgets_menus', array( $this, 'ajax_export_widgets_menus' ) );
		add_action( 'wp_ajax_peiwm_import_widgets_menus', array( $this, 'ajax_import_widgets_menus' ) );
	}

	/**
	 * AJAX: Export widgets
	 */
	public function ajax_export_widgets() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$this->execute_with_post_protection( function() {
			try {
				$widgets_data = $this->export_widgets();
				
				wp_send_json_success( array(
					'data' => $widgets_data,
					'count' => $this->count_widgets( $widgets_data['widgets'] ),
					'message' => esc_html__( 'Widgets exported successfully', 'post-export-import-with-media' ),
				) );

			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Widgets export failed. Please try again.', 'post-export-import-with-media' ) ) );
			}
		} );
	}

	/**
	 * AJAX: Export navigation menus
	 */
	public function ajax_export_nav_menus() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$menus_data = $this->export_nav_menus();
			
			wp_send_json_success( array(
				'data' => $menus_data,
				'count' => count( $menus_data['menus'] ),
				'message' => esc_html__( 'Navigation menus exported successfully', 'post-export-import-with-media' ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Navigation menus export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Export widgets and menus combined
	 */
	public function ajax_export_widgets_menus() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$widgets_data = $this->export_widgets();
			$menus_data = $this->export_nav_menus();
			
			$combined_data = array(
				'export_info' => array(
					'site_url' => get_site_url(),
					'site_name' => get_bloginfo( 'name' ),
					'export_date' => current_time( 'mysql' ),
					'wp_version' => get_bloginfo( 'version' ),
					'plugin_version' => '1.3.0',
					'export_type' => 'widgets_and_menus',
				),
				'widgets' => $widgets_data['widgets'],
				'widget_positions' => $widgets_data['widget_positions'],
				'menus' => $menus_data['menus'],
				'menu_locations' => $menus_data['menu_locations'],
			);
			
			wp_send_json_success( array(
				'data' => $combined_data,
				'widgets_count' => $this->count_widgets( $widgets_data['widgets'] ),
				'menus_count' => count( $menus_data['menus'] ),
				'message' => esc_html__( 'Widgets and navigation menus exported successfully', 'post-export-import-with-media' ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Widgets and menus export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Import widgets
	 */
	public function ajax_import_widgets() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$this->execute_with_post_protection( function() {
			try {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in outer function before this closure
				$widgets_data_raw = isset( $_POST['widgets_data'] ) ? wp_unslash( $_POST['widgets_data'] ) : '';
				$replace_existing = isset( $_POST['replace_existing'] ) && '1' === $_POST['replace_existing'];
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				
				if ( empty( $widgets_data_raw ) ) {
					throw new Exception( esc_html__( 'No widgets data provided', 'post-export-import-with-media' ) );
				}
				
				$widgets_data = json_decode( $widgets_data_raw, true );
				
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
				}

				// Sanitize the imported data
				$widgets_data = $this->sanitize_import_data( $widgets_data );

				$result = $this->import_widgets( $widgets_data, $replace_existing );
				
				wp_send_json_success( $result );

			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ) );
			}
		} );
	}

	/**
	 * AJAX: Import navigation menus
	 */
	public function ajax_import_nav_menus() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$this->execute_with_post_protection( function() {
			try {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in the outer ajax_import_nav_menus() function before this closure is invoked.
				// JSON payload — sanitized structurally via json_decode() and sanitize_import_data() below; raw string sanitizers would corrupt JSON structure.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$menus_data_raw   = isset( $_POST['menus_data'] ) ? wp_unslash( $_POST['menus_data'] ) : '';
				$replace_existing = isset( $_POST['replace_existing'] ) && '1' === $_POST['replace_existing'];
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				
				if ( empty( $menus_data_raw ) ) {
					throw new Exception( esc_html__( 'No menus data provided', 'post-export-import-with-media' ) );
				}
				
				$menus_data = json_decode( $menus_data_raw, true );
				
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
				}

				// Sanitize the imported data
				$menus_data = $this->sanitize_import_data( $menus_data );

				$result = $this->import_nav_menus( $menus_data, $replace_existing );
				
				wp_send_json_success( $result );

			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ) );
			}
		} );
	}

	/**
	 * AJAX: Import widgets and menus combined
	 */
	public function ajax_import_widgets_menus() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$this->execute_with_post_protection( function() {
			try {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in the outer ajax_import_widgets_menus() function before this closure is invoked.
				// JSON payload — sanitized structurally via json_decode() and sanitize_import_data() below; raw string sanitizers would corrupt JSON structure.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$data_raw         = isset( $_POST['widgets_menus_data'] ) ? wp_unslash( $_POST['widgets_menus_data'] ) : '';
				$replace_existing = isset( $_POST['replace_existing'] ) && '1' === $_POST['replace_existing'];
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				
				if ( empty( $data_raw ) ) {
					throw new Exception( esc_html__( 'No data provided', 'post-export-import-with-media' ) );
				}
				
				$data = json_decode( $data_raw, true );
				
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
				}

				// Sanitize the imported data
				$data = $this->sanitize_import_data( $data );

				$widgets_result = array( 'imported_count' => 0, 'skipped_count' => 0, 'failed_count' => 0 );
				$menus_result = array( 'imported_count' => 0, 'skipped_count' => 0, 'failed_count' => 0 );

				// Import widgets if present
				if ( isset( $data['widgets'] ) ) {
					$widgets_result = $this->import_widgets( $data, $replace_existing );
				}

				// Import menus if present
				if ( isset( $data['menus'] ) ) {
					$menus_result = $this->import_nav_menus( $data, $replace_existing );
				}

				$message = sprintf(
					esc_html__( 'Import completed: %d widgets and %d menus imported', 'post-export-import-with-media' ),
					$widgets_result['imported_count'],
					$menus_result['imported_count']
				);

				wp_send_json_success( array(
					'message' => $message,
					'widgets_result' => $widgets_result,
					'menus_result' => $menus_result,
				) );

			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ) );
			}
		} );
	}

	/**
	 * Export widgets data
	 *
	 * @return array Widgets export data
	 */
	private function export_widgets() {
		$widgets = array();

		// Get all widget settings by checking all registered widget types
		$widget_types = array();
		
		// Get widget types from registered widgets
		foreach ( $GLOBALS['wp_registered_widget_controls'] as $widget ) {
			if ( isset( $widget['callback'] ) && is_array( $widget['callback'] ) ) {
				$widget_class = $widget['callback'][0];
				if ( is_object( $widget_class ) && isset( $widget_class->option_name ) ) {
					$widget_types[] = $widget_class->option_name;
				}
			}
		}

		// Also check for common widget types that might not be in the controls array
		$common_widgets = array(
			'widget_text',
			'widget_search',
			'widget_recent-posts',
			'widget_recent-comments',
			'widget_archives',
			'widget_categories',
			'widget_meta',
			'widget_calendar',
			'widget_pages',
			'widget_links',
			'widget_tag_cloud',
			'widget_nav_menu',
			'widget_custom_html',
			'widget_media_audio',
			'widget_media_image',
			'widget_media_gallery',
			'widget_media_video',
		);

		$widget_types = array_merge( $widget_types, $common_widgets );
		$widget_types = array_unique( $widget_types );

		// Get widget data for each type
		foreach ( $widget_types as $widget_type ) {
			$widget_data = get_option( $widget_type );
			if ( $widget_data && is_array( $widget_data ) ) {
				$widgets[ $widget_type ] = $widget_data;
			}
		}

		return array(
			'export_info' => array(
				'site_url' => get_site_url(),
				'site_name' => get_bloginfo( 'name' ),
				'export_date' => current_time( 'mysql' ),
				'wp_version' => get_bloginfo( 'version' ),
				'plugin_version' => '1.3.0',
				'export_type' => 'widgets',
			),
			'widgets' => $widgets,
			'widget_positions' => get_option( 'sidebars_widgets' ),
		);
	}

	/**
	 * Export navigation menus data
	 *
	 * @return array Navigation menus export data
	 */
	private function export_nav_menus() {
		$menus = wp_get_nav_menus();
		$menu_data = array();

		foreach ( $menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			$menu_data[] = array(
				'menu_id' => $menu->term_id,
				'menu_name' => $menu->name,
				'menu_slug' => $menu->slug,
				'menu_description' => $menu->description,
				'menu_items' => $menu_items,
			);
		}

		// Reset global post data after processing menu items
		wp_reset_postdata();

		return array(
			'export_info' => array(
				'site_url' => get_site_url(),
				'site_name' => get_bloginfo( 'name' ),
				'export_date' => current_time( 'mysql' ),
				'wp_version' => get_bloginfo( 'version' ),
				'plugin_version' => '1.3.0',
				'export_type' => 'nav_menus',
			),
			'menus' => $menu_data,
			'menu_locations' => get_theme_mod( 'nav_menu_locations' ),
		);
	}

	/**
	 * Import widgets data
	 *
	 * @param array $data Import data
	 * @param bool  $replace_existing Whether to replace existing widgets
	 * @return array Import result
	 */
	private function import_widgets( $data, $replace_existing = true ) {
		$imported_count = 0;
		$skipped_count = 0;
		$failed_count = 0;

		if ( ! isset( $data['widgets'] ) ) {
			throw new Exception( esc_html__( 'No widgets data found', 'post-export-import-with-media' ) );
		}

		// Get current sidebars_widgets before clearing
		$current_sidebars = get_option( 'sidebars_widgets', array() );

		// Clear existing widgets if replacing
		if ( $replace_existing ) {
			// Keep wp_inactive_widgets to avoid issues
			$inactive = isset( $current_sidebars['wp_inactive_widgets'] ) ? $current_sidebars['wp_inactive_widgets'] : array();
			update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => $inactive ) );
		}

		// Import widget settings
		foreach ( $data['widgets'] as $widget_type => $widget_settings ) {
			if ( ! is_array( $widget_settings ) ) {
				continue;
			}

			// Count actual widget instances (not empty array elements)
			$widget_instances = array_filter( $widget_settings, function( $instance ) {
				return ! empty( $instance ) && is_array( $instance );
			} );

			if ( $replace_existing || ! get_option( $widget_type ) ) {
				$result = update_option( $widget_type, $widget_settings );
				if ( $result ) {
					$imported_count += count( $widget_instances );
				} else {
					// Check if value is already the same
					$current_value = get_option( $widget_type );
					if ( $current_value === $widget_settings ) {
						$imported_count += count( $widget_instances );
					} else {
						$failed_count += count( $widget_instances );
					}
				}
			} else {
				$skipped_count += count( $widget_instances );
			}
		}

		// Import widget positions
		if ( isset( $data['widget_positions'] ) && is_array( $data['widget_positions'] ) && $replace_existing ) {
			// Merge with current to keep array_version
			$new_sidebars = $data['widget_positions'];
			if ( isset( $current_sidebars['array_version'] ) ) {
				$new_sidebars['array_version'] = $current_sidebars['array_version'];
			}
			update_option( 'sidebars_widgets', $new_sidebars );
		}

		$message = sprintf(
			esc_html__( 'Widgets import completed: %d imported, %d skipped, %d failed', 'post-export-import-with-media' ),
			$imported_count,
			$skipped_count,
			$failed_count
		);

		return array(
			'message' => $message,
			'imported_count' => $imported_count,
			'skipped_count' => $skipped_count,
			'failed_count' => $failed_count,
		);
	}

	/**
	 * Import navigation menus data
	 *
	 * @param array $data Import data
	 * @param bool  $replace_existing Whether to replace existing menus
	 * @return array Import result
	 */
	private function import_nav_menus( $data, $replace_existing = true ) {
		$imported_count = 0;
		$skipped_count = 0;
		$failed_count = 0;
		$menu_id_map = array();
		$import_details = array();

		if ( ! isset( $data['menus'] ) ) {
			throw new Exception( esc_html__( 'No menus data found', 'post-export-import-with-media' ) );
		}

		// Delete existing menus if replacing
		if ( $replace_existing ) {
			$existing_menus = wp_get_nav_menus();
			foreach ( $existing_menus as $menu ) {
				wp_delete_nav_menu( $menu->term_id );
			}
			// Reset after deleting menus
			wp_reset_postdata();
		}

		// Import menus
		foreach ( $data['menus'] as $menu_data ) {
			$menu_name = sanitize_text_field( $menu_data['menu_name'] );
			$old_menu_id = isset( $menu_data['menu_id'] ) ? $menu_data['menu_id'] : 0;
			
			// Check if menu already exists
			$existing_menu = wp_get_nav_menu_object( $menu_name );
			
			if ( $existing_menu && ! $replace_existing ) {
				$skipped_count++;
				continue;
			}

			// Create menu
			$menu_id = wp_create_nav_menu( $menu_name );
			
			if ( is_wp_error( $menu_id ) ) {
				$failed_count++;
				continue;
			}

			// Map old menu ID to new menu ID for location mapping
			if ( $old_menu_id ) {
				$menu_id_map[ $old_menu_id ] = $menu_id;
			}

			// Add menu items and collect import details
			$menu_import_details = array();
			if ( isset( $menu_data['menu_items'] ) && is_array( $menu_data['menu_items'] ) ) {
				$menu_import_details = $this->import_menu_items( $menu_id, $menu_data['menu_items'] );
			}

			$import_details[ $menu_name ] = $menu_import_details;
			$imported_count++;
		}

		// Reset global post data after all menu operations
		wp_reset_postdata();

		// Import menu locations with ID mapping
		if ( isset( $data['menu_locations'] ) && $replace_existing && is_array( $data['menu_locations'] ) ) {
			$new_menu_locations = array();
			
			foreach ( $data['menu_locations'] as $location => $old_menu_id ) {
				if ( isset( $menu_id_map[ $old_menu_id ] ) ) {
					$new_menu_locations[ $location ] = $menu_id_map[ $old_menu_id ];
				}
			}
			
			if ( ! empty( $new_menu_locations ) ) {
				set_theme_mod( 'nav_menu_locations', $new_menu_locations );
			}
		}

		$message = sprintf(
			esc_html__( 'Navigation menus import completed: %d imported, %d skipped, %d failed', 'post-export-import-with-media' ),
			$imported_count,
			$skipped_count,
			$failed_count
		);

		return array(
			'message' => $message,
			'imported_count' => $imported_count,
			'skipped_count' => $skipped_count,
			'failed_count' => $failed_count,
			'import_details' => $import_details,
		);
	}

	/**
	 * Import menu items for a specific menu
	 *
	 * @param int   $menu_id Menu ID
	 * @param array $menu_items Menu items data
	 * @return array Import details
	 */
	private function import_menu_items( $menu_id, $menu_items ) {
		if ( empty( $menu_items ) || ! is_array( $menu_items ) ) {
			return array();
		}

		// Save and isolate global post state completely
		global $post, $wp_query;
		$saved_post = $post;
		$saved_wp_query = $wp_query;
		
		// Set to null to prevent any interference
		$post = null;
		$wp_query = new WP_Query();

		$import_details = array(
			'total_items' => count( $menu_items ),
			'successful_mappings' => 0,
			'converted_to_custom' => 0,
			'failed_items' => 0,
			'mapping_details' => array(),
		);

		try {
			$item_id_map = array();

			foreach ( $menu_items as $item ) {
				// Convert to array if it's an object
				if ( is_object( $item ) ) {
					$item = (array) $item;
				}
				
				// Skip if not an array
				if ( ! is_array( $item ) ) {
					$import_details['failed_items']++;
					continue;
				}
				
				// Helper function to safely get item property
				$get_item_prop = function( $prop, $default = '' ) use ( $item ) {
					if ( isset( $item[ $prop ] ) ) {
						return $item[ $prop ];
					}
					return $default;
				};

				// Get basic menu item data
				$title = $get_item_prop( 'title' );
				$url = $get_item_prop( 'url' );
				
				// Skip items without title
				if ( empty( $title ) ) {
					$import_details['failed_items']++;
					continue;
				}

				$menu_item_data = array(
					'menu-item-title' => sanitize_text_field( $title ),
					'menu-item-url' => esc_url_raw( $url ),
					'menu-item-description' => sanitize_text_field( $get_item_prop( 'description' ) ),
					'menu-item-attr-title' => sanitize_text_field( $get_item_prop( 'attr_title' ) ),
					'menu-item-target' => sanitize_text_field( $get_item_prop( 'target' ) ),
					'menu-item-xfn' => sanitize_text_field( $get_item_prop( 'xfn' ) ),
					'menu-item-status' => 'publish',
				);

				// Handle classes (can be array or string)
				$classes = $get_item_prop( 'classes' );
				if ( is_array( $classes ) ) {
					$classes = implode( ' ', $classes );
				}
				$menu_item_data['menu-item-classes'] = sanitize_text_field( $classes );

				$item_type = $get_item_prop( 'type' );
				$item_object = $get_item_prop( 'object' );
				$item_object_id = $get_item_prop( 'object_id' );

				$mapping_detail = array(
					'title' => $title,
					'original_type' => $item_type,
					'original_object' => $item_object,
					'original_object_id' => $item_object_id,
					'final_type' => 'custom',
					'final_object_id' => null,
					'status' => 'converted_to_custom',
				);

				// Handle different menu item types with intelligent ID mapping
				if ( $item_type === 'post_type' && $item_object && $item_object_id ) {
					// Try to find the correct page/post ID
					$correct_object_id = $this->find_correct_object_id( $item_object_id, $item_object, $title, $url );
					
					if ( $correct_object_id ) {
						$menu_item_data['menu-item-type'] = 'post_type';
						$menu_item_data['menu-item-object'] = sanitize_text_field( $item_object );
						$menu_item_data['menu-item-object-id'] = absint( $correct_object_id );
						
						$mapping_detail['final_type'] = 'post_type';
						$mapping_detail['final_object_id'] = $correct_object_id;
						$mapping_detail['status'] = $correct_object_id === $item_object_id ? 'original_id_found' : 'successfully_mapped';
						$import_details['successful_mappings']++;
					} else {
						// Convert to custom link if page/post not found
						$menu_item_data['menu-item-type'] = 'custom';
						$menu_item_data['menu-item-url'] = esc_url_raw( $url );
						
						$import_details['converted_to_custom']++;
					}
				} elseif ( $item_type === 'taxonomy' && $item_object && $item_object_id ) {
					// Try to find the correct taxonomy term ID
					$correct_term_id = $this->find_correct_term_id( $item_object_id, $item_object, $title );
					
					if ( $correct_term_id ) {
						$menu_item_data['menu-item-type'] = 'taxonomy';
						$menu_item_data['menu-item-object'] = sanitize_text_field( $item_object );
						$menu_item_data['menu-item-object-id'] = absint( $correct_term_id );
						
						$mapping_detail['final_type'] = 'taxonomy';
						$mapping_detail['final_object_id'] = $correct_term_id;
						$mapping_detail['status'] = $correct_term_id === $item_object_id ? 'original_id_found' : 'successfully_mapped';
						$import_details['successful_mappings']++;
					} else {
						// Convert to custom link if term not found
						$menu_item_data['menu-item-type'] = 'custom';
						$menu_item_data['menu-item-url'] = esc_url_raw( $url );
						
						$import_details['converted_to_custom']++;
					}
				} else {
					// Default to custom link
					$menu_item_data['menu-item-type'] = 'custom';
					$import_details['converted_to_custom']++;
				}

				// Handle parent items
				$parent_id = $get_item_prop( 'menu_item_parent' );
				if ( $parent_id && isset( $item_id_map[ $parent_id ] ) ) {
					$menu_item_data['menu-item-parent-id'] = $item_id_map[ $parent_id ];
				}

				// Temporarily clear global post before creating menu item
				$post = null;
				
				// Create the menu item
				$new_item_id = wp_update_nav_menu_item( $menu_id, 0, $menu_item_data );
				
				// Clear global post again after creation
				$post = null;
				
				if ( ! is_wp_error( $new_item_id ) && $new_item_id > 0 ) {
					$original_id = $get_item_prop( 'ID' );
					if ( $original_id ) {
						$item_id_map[ $original_id ] = $new_item_id;
					}
					$mapping_detail['new_menu_item_id'] = $new_item_id;
				} else {
					$import_details['failed_items']++;
					$mapping_detail['status'] = 'failed';
				}

				$import_details['mapping_details'][] = $mapping_detail;
			}
		} finally {
			// Always restore global state
			$post = $saved_post;
			$wp_query = $saved_wp_query;
			
			// Final reset to ensure clean state
			if ( $saved_post ) {
				setup_postdata( $saved_post );
			} else {
				wp_reset_postdata();
			}
		}

		return $import_details;
	}

	/**
	 * Find correct object ID for menu items (handles changed page/post IDs)
	 *
	 * @param int    $old_object_id Original object ID
	 * @param string $object_type   Object type (page, post, etc.)
	 * @param string $title         Menu item title
	 * @param string $url           Menu item URL
	 * @return int|null Correct object ID or null if not found
	 */
	private function find_correct_object_id( $old_object_id, $object_type, $title, $url ) {
		// First, check if the original ID still exists
		$post = get_post( $old_object_id );
		if ( $post && $post->post_type === $object_type && $post->post_status !== 'trash' ) {
			wp_reset_postdata();
			return $old_object_id;
		}
		wp_reset_postdata();

		// Enhanced slug extraction from URL
		$slug = '';
		if ( $url ) {
			$parsed_url = parse_url( $url );
			if ( isset( $parsed_url['path'] ) ) {
				$path = trim( $parsed_url['path'], '/' );
				$slug = basename( $path ); // Get the last part of the path
				
				// Handle empty slug (home page) or common page slugs
				if ( empty( $slug ) || $slug === 'index.php' ) {
					$slug = '';
				}
			}
		}

		// Try to find by slug (most reliable method)
		if ( $slug ) {
			$posts = get_posts( array(
				'post_type' => $object_type,
				'name' => $slug,
				'post_status' => array( 'publish', 'private', 'draft' ),
				'numberposts' => 1,
			) );

			if ( ! empty( $posts ) ) {
				$found_id = $posts[0]->ID;
				wp_reset_postdata();
				return $found_id;
			}
			wp_reset_postdata();
		}

		// Try to find by exact title match
		if ( $title ) {
			$posts = get_posts( array(
				'post_type' => $object_type,
				'title' => $title,
				'post_status' => array( 'publish', 'private', 'draft' ),
				'numberposts' => 1,
			) );

			if ( ! empty( $posts ) ) {
				$found_id = $posts[0]->ID;
				wp_reset_postdata();
				return $found_id;
			}
			wp_reset_postdata();
		}

		// Try fuzzy title matching (case-insensitive, trimmed)
		if ( $title ) {
			$normalized_title = strtolower( trim( $title ) );
			$posts = get_posts( array(
				'post_type' => $object_type,
				'post_status' => array( 'publish', 'private', 'draft' ),
				'numberposts' => -1,
			) );

			foreach ( $posts as $post ) {
				if ( strtolower( trim( $post->post_title ) ) === $normalized_title ) {
					$found_id = $post->ID;
					wp_reset_postdata();
					return $found_id;
				}
			}
			wp_reset_postdata();
		}

		// Last resort: try to extract slug from title and search
		if ( $title && empty( $slug ) ) {
			$title_slug = sanitize_title( $title );
			if ( $title_slug ) {
				$posts = get_posts( array(
					'post_type' => $object_type,
					'name' => $title_slug,
					'post_status' => array( 'publish', 'private', 'draft' ),
					'numberposts' => 1,
				) );

				if ( ! empty( $posts ) ) {
					$found_id = $posts[0]->ID;
					wp_reset_postdata();
					return $found_id;
				}
				wp_reset_postdata();
			}
		}

		// Not found - will be converted to custom link
		return null;
	}

	/**
	 * Find correct term ID for taxonomy menu items
	 *
	 * @param int    $old_term_id Original term ID
	 * @param string $taxonomy    Taxonomy name
	 * @param string $title       Menu item title
	 * @return int|null Correct term ID or null if not found
	 */
	private function find_correct_term_id( $old_term_id, $taxonomy, $title ) {
		// First, check if the original term ID still exists
		$term = get_term( $old_term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return $old_term_id;
		}

		// Try to find by name/title (exact match)
		if ( $title ) {
			$term = get_term_by( 'name', $title, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->term_id;
			}
		}

		// Try to find by slug (generated from title)
		if ( $title ) {
			$slug = sanitize_title( $title );
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->term_id;
			}
		}

		// Try case-insensitive search
		if ( $title ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			) );

			if ( ! is_wp_error( $terms ) ) {
				$normalized_title = strtolower( trim( $title ) );
				foreach ( $terms as $term ) {
					if ( strtolower( trim( $term->name ) ) === $normalized_title ) {
						return $term->term_id;
					}
				}
			}
		}

		// Not found - will be converted to custom link
		return null;
	}

	/**
	 * Count widgets in export data
	 *
	 * @param array $widgets Widgets data
	 * @return int Widget count
	 */
	private function count_widgets( $widgets ) {
		$count = 0;
		foreach ( $widgets as $widget_type => $widget_instances ) {
			if ( is_array( $widget_instances ) ) {
				$count += count( array_filter( $widget_instances ) );
			}
		}
		return $count;
	}


	/**
	 * Sanitize complete import data
	 *
	 * @param mixed $data Complete import data
	 * @return array Sanitized import data
	 */
	private function sanitize_import_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$sanitized = array();

		// Sanitize export info
		if ( isset( $data['export_info'] ) ) {
			$sanitized['export_info'] = $this->sanitize_export_info( $data['export_info'] );
		}

		// Sanitize widgets
		if ( isset( $data['widgets'] ) ) {
			$sanitized['widgets'] = $this->sanitize_widgets_data( $data['widgets'] );
		}

		// Sanitize widget positions
		if ( isset( $data['widget_positions'] ) ) {
			$sanitized['widget_positions'] = $this->sanitize_widget_positions( $data['widget_positions'] );
		}

		// Sanitize menus
		if ( isset( $data['menus'] ) ) {
			$sanitized['menus'] = $this->sanitize_menus_data( $data['menus'] );
		}

		// Sanitize menu locations
		if ( isset( $data['menu_locations'] ) ) {
			$sanitized['menu_locations'] = $this->sanitize_menu_locations( $data['menu_locations'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize export info data
	 *
	 * @param mixed $export_info Export info data
	 * @return array Sanitized export info
	 */
	private function sanitize_export_info( $export_info ) {
		if ( ! is_array( $export_info ) ) {
			return array();
		}

		$sanitized = array();

		if ( isset( $export_info['site_url'] ) ) {
			$sanitized['site_url'] = esc_url_raw( $export_info['site_url'] );
		}

		if ( isset( $export_info['site_name'] ) ) {
			$sanitized['site_name'] = sanitize_text_field( $export_info['site_name'] );
		}

		if ( isset( $export_info['export_date'] ) ) {
			$sanitized['export_date'] = sanitize_text_field( $export_info['export_date'] );
		}

		if ( isset( $export_info['wp_version'] ) ) {
			$sanitized['wp_version'] = sanitize_text_field( $export_info['wp_version'] );
		}

		if ( isset( $export_info['plugin_version'] ) ) {
			$sanitized['plugin_version'] = sanitize_text_field( $export_info['plugin_version'] );
		}

		if ( isset( $export_info['export_type'] ) ) {
			$sanitized['export_type'] = sanitize_key( $export_info['export_type'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize widgets data recursively
	 *
	 * @param mixed $data Data to sanitize
	 * @return mixed Sanitized data
	 */
	private function sanitize_widgets_data( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				$sanitized_key = sanitize_key( $key );
				$sanitized[ $sanitized_key ] = $this->sanitize_widgets_data( $value );
			}
			return $sanitized;
		} elseif ( is_string( $data ) ) {
			// Check if it's HTML content (for widget_block content)
			if ( strpos( $data, '<!--' ) !== false || strpos( $data, '<' ) !== false ) {
				return wp_kses_post( $data );
			}
			return sanitize_text_field( $data );
		} elseif ( is_numeric( $data ) ) {
			return $data;
		} elseif ( is_bool( $data ) ) {
			return $data;
		}
		
		return $data;
	}

	/**
	 * Sanitize widget positions data
	 *
	 * @param mixed $positions Widget positions data
	 * @return array Sanitized widget positions
	 */
	private function sanitize_widget_positions( $positions ) {
		if ( ! is_array( $positions ) ) {
			return array();
		}

		$sanitized = array();
		
		foreach ( $positions as $sidebar => $widgets ) {
			$sanitized_sidebar = sanitize_key( $sidebar );
			
			if ( is_array( $widgets ) ) {
				$sanitized[ $sanitized_sidebar ] = array_map( 'sanitize_text_field', $widgets );
			} elseif ( is_numeric( $widgets ) ) {
				// For array_version
				$sanitized[ $sanitized_sidebar ] = absint( $widgets );
			} else {
				$sanitized[ $sanitized_sidebar ] = sanitize_text_field( $widgets );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize menus data
	 *
	 * @param array $menus_data Menus data to sanitize
	 * @return array Sanitized menus data
	 */
	private function sanitize_menus_data( $menus_data ) {
		if ( ! is_array( $menus_data ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $menus_data as $key => $menu ) {
			if ( ! is_array( $menu ) ) {
				continue;
			}

			$sanitized_menu = array();

			// Sanitize menu properties
			if ( isset( $menu['menu_id'] ) ) {
				$sanitized_menu['menu_id'] = absint( $menu['menu_id'] );
			}

			if ( isset( $menu['menu_name'] ) ) {
				$sanitized_menu['menu_name'] = sanitize_text_field( $menu['menu_name'] );
			}

			if ( isset( $menu['menu_slug'] ) ) {
				$sanitized_menu['menu_slug'] = sanitize_title( $menu['menu_slug'] );
			}

			if ( isset( $menu['menu_description'] ) ) {
				$sanitized_menu['menu_description'] = sanitize_textarea_field( $menu['menu_description'] );
			}

			// Sanitize menu items
			if ( isset( $menu['menu_items'] ) && is_array( $menu['menu_items'] ) ) {
				$sanitized_menu['menu_items'] = array();
				foreach ( $menu['menu_items'] as $item ) {
					$sanitized_menu['menu_items'][] = $this->sanitize_menu_item( $item );
				}
			}

			$sanitized[] = $sanitized_menu;
		}

		return $sanitized;
	}

	/**
	 * Sanitize menu item data
	 *
	 * @param array $item Menu item data
	 * @return array Sanitized menu item
	 */
	private function sanitize_menu_item( $item ) {
		if ( ! is_array( $item ) ) {
			return array();
		}

		$sanitized = array();

		// Text fields
		$text_fields = array(
			'post_title',
			'post_excerpt',
			'post_name',
			'post_password',
			'to_ping',
			'pinged',
			'post_content_filtered',
			'guid',
			'post_mime_type',
			'filter',
			'object',
			'type',
			'type_label',
			'title',
			'target',
			'attr_title',
			'description',
			'xfn',
			'menu_item_parent',
			'object_id',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $item[ $field ] );
			}
		}

		// URL fields
		if ( isset( $item['url'] ) ) {
			$sanitized['url'] = esc_url_raw( $item['url'] );
		}

		// Content fields (can contain HTML)
		if ( isset( $item['post_content'] ) ) {
			$sanitized['post_content'] = wp_kses_post( $item['post_content'] );
		}

		// Integer fields
		$int_fields = array(
			'ID',
			'post_author',
			'post_parent',
			'menu_order',
			'comment_count',
			'db_id',
			'menu_id',
		);

		foreach ( $int_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$sanitized[ $field ] = absint( $item[ $field ] );
			}
		}

		// Date fields
		$date_fields = array(
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
		);

		foreach ( $date_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $item[ $field ] );
			}
		}

		// Status fields
		$status_fields = array(
			'post_status',
			'comment_status',
			'ping_status',
		);

		foreach ( $status_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_key( $item[ $field ] );
			}
		}

		// Array fields
		if ( isset( $item['classes'] ) ) {
			if ( is_array( $item['classes'] ) ) {
				$sanitized['classes'] = array_map( 'sanitize_html_class', $item['classes'] );
			} else {
				$sanitized['classes'] = array( sanitize_html_class( $item['classes'] ) );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize menu locations data
	 *
	 * @param mixed $locations Menu locations data
	 * @return array Sanitized menu locations
	 */
	private function sanitize_menu_locations( $locations ) {
		if ( ! is_array( $locations ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $locations as $location => $menu_id ) {
			$sanitized_location = sanitize_key( $location );
			$sanitized[ $sanitized_location ] = absint( $menu_id );
		}

		return $sanitized;
	}

}