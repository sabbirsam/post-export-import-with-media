<?php
/**
 * Settings Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Handler Class - Manages WordPress settings export/import operations
 */
class PEIWM_Settings_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Settings_Handler|null
	 */
	private static $instance = null;

	/**
	 * WordPress settings to export/import
	 *
	 * @var array
	 */
	private $settings_groups = array(
		'general' => array(
			'blogname',
			'blogdescription',
			'admin_email',
			'users_can_register',
			'default_role',
			'timezone_string',
			'date_format',
			'time_format',
			'week_starts_on',
			'WPLANG',
			'site_icon',
		),
		'writing' => array(
			'default_category',
			'default_post_format',
			'mailserver_url',
			'mailserver_login',
			'mailserver_pass',
			'mailserver_port',
			'default_email_category',
			'use_balanceTags',
		),
		'reading' => array(
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'posts_per_page',
			'posts_per_rss',
			'rss_use_excerpt',
			'blog_public',
		),
		'discussion' => array(
			'default_pingback_flag',
			'default_ping_status',
			'default_comment_status',
			'require_name_email',
			'comment_registration',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'thread_comments',
			'thread_comments_depth',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'comments_notify',
			'moderation_notify',
			'comment_moderation',
			'comment_previously_approved',
			'comment_max_links',
			'moderation_keys',
			'disallowed_keys',
			'show_avatars',
			'avatar_rating',
			'avatar_default',
		),
		'media' => array(
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_size_w',
			'medium_size_h',
			'medium_large_size_w',
			'medium_large_size_h',
			'large_size_w',
			'large_size_h',
			'uploads_use_yearmonth_folders',
		),
		'permalinks' => array(
			'permalink_structure',
			'category_base',
			'tag_base',
		),
		'privacy' => array(
			'wp_page_for_privacy_policy',
		),
	);

	/**
	 * Get instance
	 *
	 * @return PEIWM_Settings_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Deprecated option mappings
	 *
	 * @var array
	 */
	private $deprecated_options = array(
		'comment_whitelist' => 'comment_previously_approved',
		'blacklist_keys' => 'disallowed_keys',
	);

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_ajax_hooks();
	}

	/**
	 * Initialize AJAX hooks
	 */
	private function init_ajax_hooks() {
		add_action( 'wp_ajax_peiwm_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_peiwm_import_settings', array( $this, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_peiwm_get_settings_preview', array( $this, 'ajax_get_settings_preview' ) );
	}

	/**
	 * AJAX: Export settings
	 */
	public function ajax_export_settings() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$selected_groups = isset( $_POST['settings_groups'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['settings_groups'] ) ) : array_keys( $this->settings_groups );
			
			$export_data = array(
				'export_info' => array(
					'site_url' => get_site_url(),
					'site_name' => get_bloginfo( 'name' ),
					'export_date' => current_time( 'mysql' ),
					'wp_version' => get_bloginfo( 'version' ),
					'plugin_version' => '1.2.0',
				),
				'settings' => array(),
			);

			foreach ( $selected_groups as $group ) {
				if ( ! isset( $this->settings_groups[ $group ] ) ) {
					continue;
				}

				$export_data['settings'][ $group ] = array();
				
				foreach ( $this->settings_groups[ $group ] as $option_name ) {
					// Handle deprecated options by checking both old and new names
					$actual_option_name = $option_name;
					if ( isset( $this->deprecated_options[ $option_name ] ) ) {
						// Check if new option exists first
						$new_option_value = get_option( $this->deprecated_options[ $option_name ] );
						if ( $new_option_value !== false ) {
							$actual_option_name = $this->deprecated_options[ $option_name ];
						}
					}
					
					$option_value = get_option( $actual_option_name );
					
					// Only export if option exists and has a value
					if ( $option_value !== false ) {
						// Special handling for site_icon to include URL
						if ( $option_name === 'site_icon' && ! empty( $option_value ) ) {
							$site_icon_url = wp_get_attachment_url( $option_value );
							$export_data['settings'][ $group ][ $option_name ] = array(
								'id' => $option_value,
								'url' => $site_icon_url ? $site_icon_url : '',
							);
						} else {
							$export_data['settings'][ $group ][ $option_name ] = $option_value;
						}
					}
				}
			}

			wp_send_json_success( array(
				'data' => $export_data,
				'groups_count' => count( $selected_groups ),
				'settings_count' => $this->count_exported_settings( $export_data['settings'] ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Settings export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Import settings
	 */
	public function ajax_import_settings() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// settings_data is a JSON string — sanitized structurally after json_decode() below, not via string sanitizer.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload validated and processed via json_decode() on the next line; raw string sanitization would corrupt the JSON structure.
			$settings_data_raw = isset( $_POST['settings_data'] ) ? wp_unslash( $_POST['settings_data'] ) : '';
			$selected_groups = isset( $_POST['settings_groups'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['settings_groups'] ) ) : array();
			
			if ( empty( $settings_data_raw ) ) {
				throw new Exception( esc_html__( 'No settings data provided', 'post-export-import-with-media' ) );
			}
			
			$settings_data = json_decode( $settings_data_raw, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
			}

			if ( ! $settings_data || ! is_array( $settings_data ) || ! isset( $settings_data['settings'] ) ) {
				throw new Exception( esc_html__( 'Invalid settings data format', 'post-export-import-with-media' ) );
			}

			$imported_count = 0;
			$skipped_count = 0;
			$failed_count = 0;
			$details = array(
				'imported' => array(),
				'skipped' => array(),
				'failed' => array(),
			);

			foreach ( $selected_groups as $group ) {
				if ( ! isset( $settings_data['settings'][ $group ] ) ) {
					continue;
				}

				foreach ( $settings_data['settings'][ $group ] as $option_name => $option_value ) {
					// Sanitize option name and handle case variations
					$safe_option_name = $this->normalize_option_name( sanitize_key( $option_name ) );
					
					// Skip if not in our allowed list
					if ( ! isset( $this->settings_groups[ $group ] ) || ! in_array( $safe_option_name, $this->settings_groups[ $group ], true ) ) {
						$skipped_count++;
						$details['skipped'][] = array(
							'option' => $safe_option_name,
							'reason' => 'Not in allowed list',
						);
						continue;
					}

					// Special handling for site_icon
					if ( $safe_option_name === 'site_icon' && is_array( $option_value ) ) {
						// Try to find existing attachment by URL first
						if ( ! empty( $option_value['url'] ) ) {
							$attachment_id = attachment_url_to_postid( $option_value['url'] );
							if ( $attachment_id ) {
								$safe_option_value = $attachment_id;
							} else {
								// Could implement download logic here if needed
								$safe_option_value = isset( $option_value['id'] ) ? absint( $option_value['id'] ) : 0;
							}
						} else {
							$safe_option_value = isset( $option_value['id'] ) ? absint( $option_value['id'] ) : 0;
						}
					} else {
						// Sanitize option value based on type
						$safe_option_value = $this->sanitize_option_value( $safe_option_name, $option_value );
					}
					
					// Get current value to compare
					$current_value = get_option( $safe_option_name );
					
					// Convert both values to same type for comparison
					$current_value_normalized = $this->normalize_option_value( $safe_option_name, $current_value );
					$safe_option_value_normalized = $this->normalize_option_value( $safe_option_name, $safe_option_value );
					
					// Skip if value is the same (no need to update)
					if ( $current_value_normalized === $safe_option_value_normalized ) {
						$imported_count++;
						$details['imported'][] = array(
							'option' => $safe_option_name,
							'status' => 'Already set to same value',
						);
						continue;
					}
					
					// Update option
					$result = update_option( $safe_option_name, $safe_option_value );
					
					// Check if update was successful
					if ( $result ) {
						$imported_count++;
						$details['imported'][] = array(
							'option' => $safe_option_name,
							'status' => 'Updated successfully',
						);
					} else {
						// Verify if the value was actually set (update_option returns false if value didn't change)
						$verify_value = get_option( $safe_option_name );
						if ( $verify_value === $safe_option_value ) {
							$imported_count++;
							$details['imported'][] = array(
								'option' => $safe_option_name,
								'status' => 'Value verified',
							);
						} else {
							$failed_count++;
							$details['failed'][] = array(
								'option' => $safe_option_name,
								'reason' => 'Update failed',
								'expected' => $safe_option_value,
								'actual' => $verify_value,
							);
						}
					}
				}
			}

			// Flush rewrite rules if permalink structure was changed
			if ( in_array( 'permalinks', $selected_groups, true ) ) {
				flush_rewrite_rules();
			}

			$message = sprintf(
				/* translators: 1: number of imported settings, 2: number of skipped settings, 3: number of failed settings */
				esc_html__( 'Settings import completed: %1$d imported, %2$d skipped, %3$d failed', 'post-export-import-with-media' ),
				$imported_count,
				$skipped_count,
				$failed_count
			);

			wp_send_json_success( array(
				'message' => $message,
				'imported_count' => $imported_count,
				'skipped_count' => $skipped_count,
				'failed_count' => $failed_count,
				'details' => $details,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Get settings preview
	 */
	public function ajax_get_settings_preview() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$current_settings = array();
			
			foreach ( $this->settings_groups as $group => $options ) {
				$current_settings[ $group ] = array();
				
				foreach ( $options as $option_name ) {
					$option_value = get_option( $option_name );
					
					if ( $option_value !== false ) {
						$current_settings[ $group ][ $option_name ] = $option_value;
					}
				}
			}

			wp_send_json_success( array(
				'settings' => $current_settings,
				'groups' => $this->get_settings_groups_info(),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to get settings preview', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * Get settings groups information
	 *
	 * @return array Settings groups with descriptions
	 */
	private function get_settings_groups_info() {
		return array(
			'general' => array(
				'label' => esc_html__( 'General Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'Site title, tagline, admin email, timezone, date/time formats', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['general'] ),
			),
			'writing' => array(
				'label' => esc_html__( 'Writing Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'Default category, post format, email posting settings', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['writing'] ),
			),
			'reading' => array(
				'label' => esc_html__( 'Reading Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'Homepage settings, posts per page, RSS settings, search engine visibility', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['reading'] ),
			),
			'discussion' => array(
				'label' => esc_html__( 'Discussion Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'Comments, pingbacks, avatars, moderation settings', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['discussion'] ),
			),
			'media' => array(
				'label' => esc_html__( 'Media Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'Image sizes, upload folder organization', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['media'] ),
			),
			'permalinks' => array(
				'label' => esc_html__( 'Permalink Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'URL structure, category and tag bases', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['permalinks'] ),
			),
			'privacy' => array(
				'label' => esc_html__( 'Privacy Settings', 'post-export-import-with-media' ),
				'description' => esc_html__( 'Privacy policy page', 'post-export-import-with-media' ),
				'count' => count( $this->settings_groups['privacy'] ),
			),
		);
	}

	/**
	 * Count exported settings
	 *
	 * @param array $settings Settings array
	 * @return int Total count
	 */
	private function count_exported_settings( $settings ) {
		$count = 0;
		foreach ( $settings as $group ) {
			$count += count( $group );
		}
		return $count;
	}

	/**
	 * Normalize option name to handle case variations
	 *
	 * @param string $option_name Option name
	 * @return string Normalized option name
	 */
	private function normalize_option_name( $option_name ) {
		$name_mappings = array(
			'wplang' => 'WPLANG',
			'use_balancetags' => 'use_balanceTags',
		);
		
		return isset( $name_mappings[ $option_name ] ) ? $name_mappings[ $option_name ] : $option_name;
	}

	/**
	 * Normalize option value for comparison
	 *
	 * @param string $option_name Option name
	 * @param mixed  $option_value Option value
	 * @return mixed Normalized value
	 */
	private function normalize_option_value( $option_name, $option_value ) {
		switch ( $option_name ) {
			case 'users_can_register':
			case 'default_pingback_flag':
			case 'default_ping_status':
			case 'default_comment_status':
			case 'require_name_email':
			case 'comment_registration':
			case 'close_comments_for_old_posts':
			case 'thread_comments':
			case 'page_comments':
			case 'comments_notify':
			case 'moderation_notify':
			case 'comment_moderation':
			case 'comment_previously_approved':
			case 'show_avatars':
			case 'thumbnail_crop':
			case 'uploads_use_yearmonth_folders':
			case 'rss_use_excerpt':
			case 'blog_public':
			case 'use_balanceTags':
			case 'default_category':
			case 'default_email_category':
			case 'page_on_front':
			case 'page_for_posts':
			case 'posts_per_page':
			case 'posts_per_rss':
			case 'close_comments_days_old':
			case 'thread_comments_depth':
			case 'comments_per_page':
			case 'comment_max_links':
			case 'thumbnail_size_w':
			case 'thumbnail_size_h':
			case 'medium_size_w':
			case 'medium_size_h':
			case 'medium_large_size_w':
			case 'medium_large_size_h':
			case 'large_size_w':
			case 'large_size_h':
			case 'week_starts_on':
			case 'wp_page_for_privacy_policy':
			case 'site_icon':
			case 'mailserver_port':
				return (int) $option_value;
				
			default:
				return (string) $option_value;
		}
	}

	/**
	 * Sanitize option value based on option name
	 *
	 * @param string $option_name Option name
	 * @param mixed  $option_value Option value
	 * @return mixed Sanitized value
	 */
	private function sanitize_option_value( $option_name, $option_value ) {
		switch ( $option_name ) {
			case 'blogname':
			case 'blogdescription':
				return sanitize_text_field( $option_value );
				
			case 'admin_email':
				return sanitize_email( $option_value );
				
			case 'users_can_register':
			case 'default_pingback_flag':
			case 'default_ping_status':
			case 'default_comment_status':
			case 'require_name_email':
			case 'comment_registration':
			case 'close_comments_for_old_posts':
			case 'thread_comments':
			case 'page_comments':
			case 'comments_notify':
			case 'moderation_notify':
			case 'comment_moderation':
			case 'comment_previously_approved':
			case 'show_avatars':
			case 'thumbnail_crop':
			case 'uploads_use_yearmonth_folders':
			case 'rss_use_excerpt':
			case 'blog_public':
			case 'use_balanceTags':
				return absint( $option_value );
				
			case 'default_role':
			case 'timezone_string':
			case 'date_format':
			case 'time_format':
			case 'WPLANG':
			case 'default_post_format':
			case 'show_on_front':
			case 'default_comments_page':
			case 'comment_order':
			case 'avatar_rating':
			case 'avatar_default':
			case 'permalink_structure':
			case 'category_base':
			case 'tag_base':
				return sanitize_text_field( $option_value );
				
			case 'default_category':
			case 'default_email_category':
			case 'page_on_front':
			case 'page_for_posts':
			case 'posts_per_page':
			case 'posts_per_rss':
			case 'close_comments_days_old':
			case 'thread_comments_depth':
			case 'comments_per_page':
			case 'comment_max_links':
			case 'thumbnail_size_w':
			case 'thumbnail_size_h':
			case 'medium_size_w':
			case 'medium_size_h':
			case 'medium_large_size_w':
			case 'medium_large_size_h':
			case 'large_size_w':
			case 'large_size_h':
			case 'week_starts_on':
			case 'wp_page_for_privacy_policy':
				return absint( $option_value );
				
			case 'mailserver_url':
			case 'mailserver_login':
			case 'mailserver_pass':
				return sanitize_text_field( $option_value );
				
			case 'mailserver_port':
				return absint( $option_value );
				
			case 'moderation_keys':
			case 'disallowed_keys':
				return sanitize_textarea_field( $option_value );
				
			case 'site_icon':
				return absint( $option_value );
				
			default:
				return sanitize_text_field( $option_value );
		}
	}
}