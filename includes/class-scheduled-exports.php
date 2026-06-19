<?php
/**
 * Scheduled Exports Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduled Exports Class - Manages automated export scheduling
 */
class PEIWM_Scheduled_Exports {

	/**
	 * Instance
	 *
	 * @var PEIWM_Scheduled_Exports|null
	 */
	private static $instance = null;

	/**
	 * Default settings
	 *
	 * @var array
	 */
	private $default_settings = array(
		'enable_scheduled_exports' => false,
		'schedule_frequency' => 'daily', // daily, weekly, monthly
		'enable_email_notifications' => false,
		'notification_emails' => '',
		'enable_backup_rotation' => false,
		'keep_backups_count' => 5,
		'storage_mode' => 'local', // local, google_drive
	'export_types' => array( 'posts', 'pages', 'media', 'settings' ),
	);

	/**
	 * Get instance
	 *
	 * @return PEIWM_Scheduled_Exports
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_peiwm_get_scheduled_backups', array( $this, 'ajax_get_scheduled_backups' ) );
		add_action( 'wp_ajax_peiwm_delete_scheduled_backup', array( $this, 'ajax_delete_scheduled_backup' ) );
		add_action( 'wp_ajax_peiwm_download_scheduled_backup', array( $this, 'ajax_download_scheduled_backup' ) );
		
		// Schedule cron events
		add_action( 'peiwm_scheduled_export_event', array( $this, 'run_scheduled_export' ) );
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'peiwm_scheduled_exports',
			'peiwm_scheduled_exports',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input settings
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		
		// Get old settings BEFORE sanitization
		$old_settings = get_option( 'peiwm_scheduled_exports', $this->default_settings );
		$old_settings = wp_parse_args( $old_settings, $this->default_settings );

		$sanitized['enable_scheduled_exports'] = isset( $input['enable_scheduled_exports'] ) ? (bool) $input['enable_scheduled_exports'] : false;
		$sanitized['schedule_frequency'] = isset( $input['schedule_frequency'] ) ? sanitize_text_field( $input['schedule_frequency'] ) : 'daily';
		$sanitized['enable_email_notifications'] = isset( $input['enable_email_notifications'] ) ? (bool) $input['enable_email_notifications'] : false;
		
		// Sanitize emails
		$emails = isset( $input['notification_emails'] ) ? sanitize_textarea_field( $input['notification_emails'] ) : '';
		$email_array = array_map( 'trim', explode( ',', $emails ) );
		$valid_emails = array_filter( $email_array, 'is_email' );
		$sanitized['notification_emails'] = implode( ', ', $valid_emails );

		$sanitized['enable_backup_rotation'] = isset( $input['enable_backup_rotation'] ) ? (bool) $input['enable_backup_rotation'] : false;
		$sanitized['keep_backups_count'] = isset( $input['keep_backups_count'] ) ? absint( $input['keep_backups_count'] ) : 5;
		$sanitized['storage_mode'] = isset( $input['storage_mode'] ) ? sanitize_text_field( $input['storage_mode'] ) : 'local';
		$sanitized['export_types'] = isset( $input['export_types'] ) && is_array( $input['export_types'] )
			? array_values( array_intersect(
				array_map( 'sanitize_text_field', $input['export_types'] ),
				array( 'posts', 'pages', 'media', 'settings', 'cpt', 'users' )
			) )
			: array( 'posts' );

		// Validate ranges
		if ( $sanitized['keep_backups_count'] < 1 ) {
			$sanitized['keep_backups_count'] = 1;
		}
		if ( $sanitized['keep_backups_count'] > 100 ) {
			$sanitized['keep_backups_count'] = 100;
		}

		// Update cron schedule
		$this->update_cron_schedule( $sanitized );

		return $sanitized;
	}

	/**
	 * Get settings
	 *
	 * @return array Settings
	 */
	public function get_settings() {
		$settings = get_option( 'peiwm_scheduled_exports', $this->default_settings );
		return wp_parse_args( $settings, $this->default_settings );
	}

	/**
	 * Get specific setting
	 *
	 * @param string $key Setting key
	 * @return mixed Setting value
	 */
	public function get_setting( $key ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public function add_custom_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 604800,
				'display'  => __( 'Once Weekly', 'post-export-import-with-media' ),
			);
		}
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 2635200,
				'display'  => __( 'Once Monthly', 'post-export-import-with-media' ),
			);
		}
		return $schedules;
	}

	/**
	 * Update cron schedule
	 *
	 * @param array $settings Settings
	 */
	private function update_cron_schedule( $settings ) {
		// Clear existing schedule
		$timestamp = wp_next_scheduled( 'peiwm_scheduled_export_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'peiwm_scheduled_export_event' );
		}

		// Schedule new event if enabled
		if ( $settings['enable_scheduled_exports'] ) {
			$frequency = $settings['schedule_frequency'];
			
			// Check if any backups exist
			$backup_dir = $this->get_backup_directory();
			$existing_backups = glob( $backup_dir . '/scheduled_*' );
			
			// If no backups exist, run immediately (first time setup)
			// Otherwise, schedule for next interval
			if ( empty( $existing_backups ) ) {
				$next_run = time() + 5; // Run in 5 seconds for first backup
			} else {
				// Calculate next run time based on frequency
				$intervals = array(
					'daily'   => DAY_IN_SECONDS,
					'weekly'  => WEEK_IN_SECONDS,
					'monthly' => 30 * DAY_IN_SECONDS,
				);
				
				$interval = isset( $intervals[ $frequency ] ) ? $intervals[ $frequency ] : DAY_IN_SECONDS;
				$next_run = time() + $interval;

			}
			
			wp_schedule_event( $next_run, $frequency, 'peiwm_scheduled_export_event' );
		}
	}

	/**
	 * Get backup directory
	 *
	 * @return string Backup directory path
	 */
	public function get_backup_directory() {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/peiwm-scheduled-backups';
		
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			// Add index.php for security
			file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden' );
		}
		
		return $backup_dir;
	}

	/**
	 * Run scheduled export
	 */
	public function run_scheduled_export() {
		$settings = $this->get_settings();
		
		if ( ! $settings['enable_scheduled_exports'] ) {
			return;
		}

		$backup_dir = $this->get_backup_directory();
		$timestamp = current_time( 'Y-m-d_H-i-s' );
		$export_types = $settings['export_types'];
		$exported_files = array();

		// Export each selected type using the existing handlers
		foreach ( $export_types as $type ) {
			$filename = "scheduled_{$type}_{$timestamp}";
			
			switch ( $type ) {
				case 'posts':
					$file = $this->export_posts_scheduled( $backup_dir, $filename );
					if ( $file ) {
						$exported_files[] = $file;
					}
					break;
				case 'pages':
					$file = $this->export_pages_scheduled( $backup_dir, $filename );
					if ( $file ) {
						$exported_files[] = $file;
					}
					break;
				case 'media':
					$file = $this->export_media( $backup_dir, $filename );
					if ( $file ) {
						$exported_files[] = $file;
					}
					break;
				case 'settings':
					$file = $this->export_settings( $backup_dir, $filename );
					if ( $file ) {
						$exported_files[] = $file;
					}
					break;
				case 'cpt':
					$file = $this->export_cpt_scheduled( $backup_dir, $filename );
					if ( $file ) {
						$exported_files[] = $file;
					}
					break;
				case 'users':
					$file = $this->export_users_scheduled( $backup_dir, $filename );
					if ( $file ) {
						$exported_files[] = $file;
					}
					break;
			}
		}

		// Rotate backups if enabled
		if ( $settings['enable_backup_rotation'] ) {
			$this->rotate_backups( $settings['keep_backups_count'] );
		}

		// Send email notification if enabled
		if ( $settings['enable_email_notifications'] && ! empty( $exported_files ) ) {
			$this->send_notification_email( $exported_files );
		}
	}

	/**
	 * Export posts using the same format as manual export
	 *
	 * @param string $dir Directory
	 * @param string $filename Filename
	 * @return string|false File path or false
	 */
	private function export_posts_scheduled( $dir, $filename ) {
		// Get the post handler instance
		$post_handler = PEIWM_Post_Handler::get_instance();
		
		// Get posts using the same query as manual export
		$posts = get_posts( array(
			'post_type'      => 'post',
			'numberposts'    => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		if ( empty( $posts ) ) {
			return false;
		}

		$export_data = array();
		foreach ( $posts as $post ) {
			// Use reflection to call private methods from post handler
			$reflection = new ReflectionClass( $post_handler );
			
			$get_categories = $reflection->getMethod( 'get_post_categories_secure' );
			$get_categories->setAccessible( true );
			
			$get_tags = $reflection->getMethod( 'get_post_tags_secure' );
			$get_tags->setAccessible( true );
			
			$get_meta = $reflection->getMethod( 'get_post_meta_secure' );
			$get_meta->setAccessible( true );
			
			$get_featured = $reflection->getMethod( 'get_featured_image_secure' );
			$get_featured->setAccessible( true );
			
			$get_content_images = $reflection->getMethod( 'get_content_images_secure' );
			$get_content_images->setAccessible( true );
			
			$post_data = array(
				'ID'            => absint( $post->ID ),
				'post_title'    => sanitize_text_field( $post->post_title ),
				'post_content'  => wp_kses_post( $post->post_content ),
				'post_excerpt'  => sanitize_textarea_field( $post->post_excerpt ),
				'post_status'   => sanitize_key( $post->post_status ),
				'post_type'     => sanitize_key( $post->post_type ),
				'post_author'   => absint( $post->post_author ),
				'post_date'     => sanitize_text_field( $post->post_date ),
				'post_modified' => sanitize_text_field( $post->post_modified ),
				'post_name'     => sanitize_title( $post->post_name ),
				'post_format'   => get_post_format( $post->ID ) ?: 'standard',
				'categories'    => $get_categories->invoke( $post_handler, $post->ID ),
				'tags'          => $get_tags->invoke( $post_handler, $post->ID ),
				'meta'          => $get_meta->invoke( $post_handler, $post->ID ),
				'featured_image' => $get_featured->invoke( $post_handler, $post->ID ),
				'content_images' => $get_content_images->invoke( $post_handler, $post->post_content ),
				'source_url'    => home_url(),
			);
			$export_data[] = $post_data;
		}

		wp_reset_postdata();

		$filepath = $dir . '/' . $filename . '.json';
		file_put_contents( $filepath, wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
		
		return $filepath;
	}

	/**
	 * Export pages using the same format as manual export
	 *
	 * @param string $dir Directory
	 * @param string $filename Filename
	 * @return string|false File path or false
	 */
	private function export_pages_scheduled( $dir, $filename ) {
		// Get the page handler instance
		$page_handler = PEIWM_Page_Handler::get_instance();
		
		// Get pages using the same query as manual export
		$pages = get_posts( array(
			'post_type'      => 'page',
			'numberposts'    => -1,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		if ( empty( $pages ) ) {
			return false;
		}

		$export_data = array();
		foreach ( $pages as $page ) {
			// Use reflection to call private methods from page handler
			$reflection = new ReflectionClass( $page_handler );
			
			$get_meta = $reflection->getMethod( 'get_page_meta_secure' );
			$get_meta->setAccessible( true );
			
			$get_featured = $reflection->getMethod( 'get_featured_image_secure' );
			$get_featured->setAccessible( true );
			
			$get_content_images = $reflection->getMethod( 'get_content_images_secure' );
			$get_content_images->setAccessible( true );
			
			$page_data = array(
				'ID'            => absint( $page->ID ),
				'post_title'    => sanitize_text_field( $page->post_title ),
				'post_content'  => wp_kses_post( $page->post_content ),
				'post_excerpt'  => sanitize_textarea_field( $page->post_excerpt ),
				'post_status'   => sanitize_key( $page->post_status ),
				'post_type'     => sanitize_key( $page->post_type ),
				'post_author'   => absint( $page->post_author ),
				'post_date'     => sanitize_text_field( $page->post_date ),
				'post_modified' => sanitize_text_field( $page->post_modified ),
				'post_name'     => sanitize_title( $page->post_name ),
				'menu_order'    => absint( $page->menu_order ),
				'post_parent'   => absint( $page->post_parent ),
				'page_template' => get_page_template_slug( $page->ID ),
				'meta'          => $get_meta->invoke( $page_handler, $page->ID ),
				'featured_image' => $get_featured->invoke( $page_handler, $page->ID ),
				'content_images' => $get_content_images->invoke( $page_handler, $page->post_content ),
				'source_url'    => home_url(),
			);
			$export_data[] = $page_data;
		}

		wp_reset_postdata();

		$filepath = $dir . '/' . $filename . '.json';
		file_put_contents( $filepath, wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
		
		return $filepath;
	}

	/**
	 * Export media using the same format as manual export (ZIP with metadata)
	 *
	 * @param string $dir Directory
	 * @param string $filename Filename
	 * @return string|false File path or false
	 */
	private function export_media( $dir, $filename ) {
		// Check if ZipArchive is available
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$attachments = get_posts( array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		if ( empty( $attachments ) ) {
			return false;
		}

		// Create ZIP file
		$zip_filename = $filename . '.zip';
		$zip_path = $dir . '/' . $zip_filename;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== TRUE ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$media_data = array();
		$added_files = 0;

		foreach ( $attachments as $attachment ) {
			$file_path = get_attached_file( $attachment->ID );
			
			if ( $file_path && file_exists( $file_path ) ) {
				// Create relative path with forward slashes for ZIP compatibility
				$upload_base = rtrim( $upload_dir['basedir'], '/\\' );
				$relative_path = str_replace( $upload_base, '', $file_path );
				$relative_path = ltrim( $relative_path, '/\\' );
				$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', $relative_path );
				
				// Try to add file to ZIP
				$zip_result = $zip->addFile( $file_path, $relative_path );
				
				if ( $zip_result ) {
					$added_files++;
					
					$media_data[] = array(
						'ID' => $attachment->ID,
						'filename' => basename( $file_path ),
						'title' => $attachment->post_title,
						'description' => $attachment->post_content,
						'mime_type' => $attachment->post_mime_type,
						'upload_date' => $attachment->post_date,
						'file_size' => filesize( $file_path ),
						'file_path' => $relative_path,
						'meta' => get_post_meta( $attachment->ID ),
					);
				}
			}
		}

		// Add metadata file
		$metadata_json = wp_json_encode( $media_data, JSON_PRETTY_PRINT );
		$zip->addFromString( 'media_metadata.json', $metadata_json );

		$zip->close();

		// Reset global post data after processing attachments
		wp_reset_postdata();

		if ( $added_files === 0 ) {
			wp_delete_file( $zip_path );
			return false;
		}

		return $zip_path;
	}

	/**
	 * Export settings using the same format as manual export
	 *
	 * @param string $dir Directory
	 * @param string $filename Filename
	 * @return string|false File path or false
	 */
	private function export_settings( $dir, $filename ) {
		// Get all settings groups from the settings handler
		$settings_groups = array(
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

		// Build export data with export_info metadata
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

		// Export all settings groups
		foreach ( $settings_groups as $group => $options ) {
			$export_data['settings'][ $group ] = array();
			
			foreach ( $options as $option_name ) {
				$option_value = get_option( $option_name );
				
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

		$filepath = $dir . '/' . $filename . '.json';
		file_put_contents( $filepath, wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
		
		return $filepath;
	}

	/**
	 * Export all CPTs (Custom Post Types) with ACF fields for scheduled backup.
	 * Password hashes are intentionally excluded from scheduled exports.
	 *
	 * @param string $dir      Backup directory path.
	 * @param string $filename Base filename (no extension).
	 * @return string|false    File path on success, false on failure.
	 */
	private function export_cpt_scheduled( $dir, $filename ) {
		if ( ! class_exists( 'PEIM_CPT_ACF_Exporter' ) ) {
			return false;
		}

		$cpt_exporter = PEIM_CPT_ACF_Exporter::get_instance();
		$is_acf_active = $cpt_exporter->is_acf_active();

		// All non-built-in post types.
		$post_types = get_post_types( array( '_builtin' => false ), 'names' );
		if ( empty( $post_types ) ) {
			return false;
		}

		$export_data = array();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'numberposts'    => -1,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			) );

			foreach ( $posts as $post ) {
				$post_meta = array();
				$all_meta  = get_post_meta( $post->ID );
				foreach ( $all_meta as $key => $values ) {
					if ( strpos( $key, '_' ) === 0 ) {
						continue; // skip private/internal keys
					}
					$post_meta[ sanitize_key( $key ) ] = array_map( function( $v ) {
						if ( is_array( $v ) || is_object( $v ) ) {
							return serialize( $v );
						}
						return wp_kses_post( $v );
					}, $values );
				}

				$entry = array(
					'ID'           => absint( $post->ID ),
					'post_title'   => sanitize_text_field( $post->post_title ),
					'post_content' => wp_kses_post( $post->post_content ),
					'post_excerpt' => sanitize_textarea_field( $post->post_excerpt ),
					'post_status'  => sanitize_key( $post->post_status ),
					'post_type'    => sanitize_key( $post->post_type ),
					'post_name'    => sanitize_title( $post->post_name ),
					'post_date'    => sanitize_text_field( $post->post_date ),
					'menu_order'   => absint( $post->menu_order ),
					'post_meta'    => $post_meta,
					'source_url'   => home_url(),
				);

				// Attach ACF fields if ACF is active.
				if ( $is_acf_active ) {
					$acf_raw = get_fields( $post->ID );
					if ( ! empty( $acf_raw ) && is_array( $acf_raw ) ) {
						$entry['acf_fields'] = $cpt_exporter->flatten_acf_fields_public( $post->ID, $acf_raw );
					}
				}

				$export_data[] = $entry;
			}
		}

		wp_reset_postdata();

		if ( empty( $export_data ) ) {
			return false;
		}

		$filepath = $dir . '/' . $filename . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $filepath, wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		return $filepath;
	}

	/**
	 * Export users for scheduled backup.
	 * Includes: basic info, user meta & capabilities, ACF user fields, WooCommerce data.
	 * Intentionally excludes password hashes — scheduled exports are backups, not migrations.
	 *
	 * @param string $dir      Backup directory path.
	 * @param string $filename Base filename (no extension).
	 * @return string|false    File path on success, false on failure.
	 */
	private function export_users_scheduled( $dir, $filename ) {
		$users = get_users( array( 'number' => -1 ) );

		if ( empty( $users ) ) {
			return false;
		}

		// Meta keys blocked for security (passwords, sessions, tokens).
		$blocked_meta_keys = array(
			'session_tokens', 'user_pass', '_application_passwords',
			'auth_cookie', 'secure_auth_cookie', 'logged_in_cookie',
		);

		// WooCommerce-specific meta keys.
		$woo_meta_keys = array(
			'billing_first_name', 'billing_last_name', 'billing_company',
			'billing_address_1', 'billing_address_2', 'billing_city',
			'billing_state', 'billing_postcode', 'billing_country',
			'billing_phone', 'billing_email',
			'shipping_first_name', 'shipping_last_name', 'shipping_company',
			'shipping_address_1', 'shipping_address_2', 'shipping_city',
			'shipping_state', 'shipping_postcode', 'shipping_country',
			'wc_last_active', 'woocommerce_checkout_profile_id',
		);

		$is_acf_active = function_exists( 'get_fields' );
		$is_woo_active = class_exists( 'WooCommerce' );

		$export = array();

		foreach ( $users as $user ) {
			// ── Basic info (always included) ──────────────────────────
			$user_data = array(
				'ID'              => absint( $user->ID ),
				'user_login'      => sanitize_user( $user->user_login ),
				'user_email'      => sanitize_email( $user->user_email ),
				'user_nicename'   => sanitize_title( $user->user_nicename ),
				'user_url'        => esc_url_raw( $user->user_url ),
				'user_registered' => sanitize_text_field( $user->user_registered ),
				'user_status'     => absint( $user->user_status ),
				'display_name'    => sanitize_text_field( $user->display_name ),
				'roles'           => array_map( 'sanitize_text_field', (array) $user->roles ),
				'locale'          => sanitize_text_field( get_user_locale( $user->ID ) ),
				'first_name'      => sanitize_text_field( get_user_meta( $user->ID, 'first_name', true ) ),
				'last_name'       => sanitize_text_field( get_user_meta( $user->ID, 'last_name', true ) ),
				'description'     => sanitize_textarea_field( get_user_meta( $user->ID, 'description', true ) ),
				// NOTE: user_pass_hash intentionally omitted from scheduled exports.
			);

			// ── User meta & capabilities ─────────────────────────────
			$all_meta  = get_user_meta( $user->ID );
			$safe_meta = array();
			foreach ( $all_meta as $key => $values ) {
				if ( in_array( $key, $blocked_meta_keys, true ) ) {
					continue;
				}
				if ( in_array( $key, $woo_meta_keys, true ) ) {
					continue; // handled separately below
				}
				// Skip raw ACF field storage keys to avoid duplication with the acf block.
				if ( $is_acf_active && strpos( $key, 'field_' ) === 0 ) {
					continue;
				}
				$safe_meta[ sanitize_key( $key ) ] = array_map( 'maybe_unserialize', $values );
			}
			$user_data['meta'] = $safe_meta;

			// ── WooCommerce data ─────────────────────────────────────
			if ( $is_woo_active ) {
				$woo_data = array();
				foreach ( $woo_meta_keys as $woo_key ) {
					$val = get_user_meta( $user->ID, $woo_key, true );
					if ( '' !== $val ) {
						$woo_data[ sanitize_key( $woo_key ) ] = sanitize_text_field( $val );
					}
				}
				$user_data['woocommerce'] = $woo_data;
			}

			// ── ACF user fields ──────────────────────────────────────
			if ( $is_acf_active ) {
				$acf_fields = get_fields( 'user_' . $user->ID );
				if ( ! empty( $acf_fields ) && is_array( $acf_fields ) ) {
					$user_data['acf'] = $acf_fields;
				}
			}

			$export[] = $user_data;
		}

		$filepath = $dir . '/' . $filename . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $filepath, wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		return $filepath;
	}

	/**
	 * Rotate backups - keep only N most recent backups
	 *
	 * @param int $keep_count Number of backups to keep
	 */
	private function rotate_backups( $keep_count ) {
		$backup_dir = $this->get_backup_directory();
		$files = glob( $backup_dir . '/scheduled_*' );
		
		if ( count( $files ) <= $keep_count ) {
			return;
		}

		// Sort by modification time (newest first)
		usort( $files, function( $a, $b ) {
			return filemtime( $b ) - filemtime( $a );
		} );

		// Delete old backups
		$files_to_delete = array_slice( $files, $keep_count );
		foreach ( $files_to_delete as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Send notification email
	 *
	 * @param array $files Exported files
	 */
	private function send_notification_email( $files ) {
		$settings = $this->get_settings();
		$emails = $settings['notification_emails'];
		
		if ( empty( $emails ) ) {
			$emails = get_option( 'admin_email' );
		}

		$subject = sprintf(
			__( '[%s] Scheduled Export Completed', 'post-export-import-with-media' ),
			get_bloginfo( 'name' )
		);

		$heading = __( '✅ Export Completed Successfully', 'post-export-import-with-media' );

		// Build file list HTML
		$file_list = '<ul>';
		foreach ( $files as $file ) {
			$file_size = file_exists( $file ) ? size_format( filesize( $file ) ) : '';
			$file_list .= '<li><strong>' . esc_html( basename( $file ) ) . '</strong>';
			if ( $file_size ) {
				$file_list .= ' <span style="color: #6b7280;">(' . esc_html( $file_size ) . ')</span>';
			}
			$file_list .= '</li>';
		}
		$file_list .= '</ul>';

		$content = '<h2>' . esc_html__( 'Your scheduled export has completed successfully!', 'post-export-import-with-media' ) . '</h2>';
		$content .= '<p>' . esc_html__( 'The following files have been exported and saved to your server:', 'post-export-import-with-media' ) . '</p>';
		$content .= $file_list;
		
		$content .= '<div class="info-box">';
		$content .= '<p><strong>' . esc_html__( 'Storage Location:', 'post-export-import-with-media' ) . '</strong></p>';
		$content .= '<p style="font-family: monospace; font-size: 14px; word-break: break-all;">' . esc_html( $this->get_backup_directory() ) . '</p>';
		$content .= '</div>';

		$content .= '<p>' . esc_html__( 'You can manage your backups from the Scheduled Exports page in your WordPress admin.', 'post-export-import-with-media' ) . '</p>';

		$args = array(
			'button_text' => __( 'View Backups', 'post-export-import-with-media' ),
			'button_url'  => admin_url( 'admin.php?page=peiwm-scheduled-exports' ),
			'footer_text' => sprintf(
				/* translators: %s: export date and time */
				__( 'Export completed on %s', 'post-export-import-with-media' ),
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) )
			),
		);

		PEIWM_Email_Template::send( $emails, $subject, $heading, $content, $args );
	}

	/**
	 * AJAX: Get scheduled backups
	 */
	public function ajax_get_scheduled_backups() {
		check_ajax_referer( 'peiwm_secure_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$backup_dir = $this->get_backup_directory();
		$files = glob( $backup_dir . '/scheduled_*' );
		
		$backups = array();
		foreach ( $files as $file ) {
			$backups[] = array(
				'filename' => basename( $file ),
				'size'     => size_format( filesize( $file ) ),
				'date'     => date( 'Y-m-d H:i:s', filemtime( $file ) ),
				'path'     => $file,
			);
		}

		// Sort by date (newest first)
		usort( $backups, function( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		} );

		wp_send_json_success( array(
			'backups'     => $backups,
			'backup_path' => $backup_dir,
			'total_count' => count( $backups ),
		) );
	}

	/**
	 * AJAX: Delete scheduled backup
	 */
	public function ajax_delete_scheduled_backup() {
		check_ajax_referer( 'peiwm_secure_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		// Unslash before sanitizing — filename is a plain string, no data loss from unslashing.
		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Unslash applied above inline
		
		if ( empty( $filename ) ) {
			wp_send_json_error( array( 'message' => 'Invalid filename' ) );
		}

		$backup_dir = $this->get_backup_directory();
		$filepath = $backup_dir . '/' . $filename;

		// SECURITY FIX: Use realpath() to prevent path traversal
		$backup_dir_real = realpath( $backup_dir );
		$filepath_real = realpath( $filepath );

		// Validate file exists and is within backup directory
		if ( ! $filepath_real || ! $backup_dir_real || strpos( $filepath_real, $backup_dir_real ) !== 0 ) {
			wp_send_json_error( array( 'message' => 'File not found or invalid path' ) );
		}

		if ( ! file_exists( $filepath_real ) ) {
			wp_send_json_error( array( 'message' => 'File not found' ) );
		}

		if ( unlink( $filepath_real ) ) {
			wp_send_json_success( array( 'message' => 'Backup deleted successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete backup' ) );
		}
	}

	/**
	 * AJAX: Download scheduled backup
	 */
	public function ajax_download_scheduled_backup() {
		check_ajax_referer( 'peiwm_secure_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		// Unslash before sanitizing — filename is a plain string, no data loss from unslashing.
		$filename = isset( $_GET['filename'] ) ? sanitize_file_name( wp_unslash( $_GET['filename'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Unslash applied above inline
		
		if ( empty( $filename ) ) {
			wp_die( 'Invalid filename' );
		}

		$backup_dir = $this->get_backup_directory();
		$filepath = $backup_dir . '/' . $filename;

		// SECURITY FIX: Use realpath() to prevent path traversal
		$backup_dir_real = realpath( $backup_dir );
		$filepath_real = realpath( $filepath );

		// Validate file exists and is within backup directory
		if ( ! $filepath_real || ! $backup_dir_real || strpos( $filepath_real, $backup_dir_real ) !== 0 ) {
			wp_die( 'File not found or invalid path' );
		}

		if ( ! file_exists( $filepath_real ) ) {
			wp_die( 'File not found' );
		}

		// SECURITY FIX: Sanitize filename for header
		$safe_filename = preg_replace( '/[^a-zA-Z0-9._-]/', '', basename( $filename ) );

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		header( 'Content-Length: ' . filesize( $filepath_real ) );
		readfile( $filepath_real );
		exit;
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'peiwm-secure',
			__( 'Scheduled Exports', 'post-export-import-with-media' ),
			__( 'Scheduled Exports', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-scheduled-exports',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on scheduled exports page
		if ( 'export-import-posts_page_peiwm-scheduled-exports' === $hook ) {
			// Enqueue admin.css first for modal and notification styles
			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_enqueue_style(
				'peiwm-scheduled-exports-css',
				PEIWM_PLUGIN_URL . 'build/css/scheduled-exports.min.css',
				array( 'peiwm-admin-css' ),
				PEIWM_VERSION
			);

			wp_enqueue_script(
				'peiwm-scheduled-exports-js',
				PEIWM_PLUGIN_URL . 'build/js/scheduled-exports.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_localize_script(
				'peiwm-scheduled-exports-js',
				'peiwm_scheduled_exports',
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'peiwm_secure_nonce' ),
					'backup_path' => $this->get_backup_directory(),
				)
			);
		}
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		// Check if PRO is active
		$main_instance = PEIWM_Main::get_instance();
		$is_pro_active = $main_instance->is_pro_active();

		$settings = $this->get_settings();
		$admin_email = get_option( 'admin_email' );
		$next_run = wp_next_scheduled( 'peiwm_scheduled_export_event' );
		?>
		<div class="wrap peiwm-scheduled-exports-wrap">
			<h1>
				<?php echo esc_html__( 'Scheduled Exports', 'post-export-import-with-media' ); ?>
			</h1>
			<p class="description"><?php echo esc_html__( 'Automate your backups with scheduled exports. Set it and forget it!', 'post-export-import-with-media' ); ?></p>

			<?php settings_errors( 'peiwm_scheduled_exports' ); ?>

			<form method="post" action="options.php" id="peiwm-scheduled-exports-form">
				<?php settings_fields( 'peiwm_scheduled_exports' ); ?>

			<!-- Enable Scheduled Exports -->
			<?php
			$enable_locked_class = ! $is_pro_active ? ' peiwm-locked-section' : '';
			?>
			<div class="peiwm-settings-section<?php echo esc_attr( $enable_locked_class ); ?>" style="position: relative;">
				<?php if ( ! $is_pro_active ) : ?>
					<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
						<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
					</button>
				<?php endif; ?>
				<h2><?php echo esc_html__( 'Enable Scheduled Exports', 'post-export-import-with-media' ); ?></h2>
				<label class="peiwm-toggle-switch">
					<input 
						type="checkbox" 
						id="enable_scheduled_exports" 
						name="peiwm_scheduled_exports[enable_scheduled_exports]" 
						value="1" 
						<?php checked( $settings['enable_scheduled_exports'], true ); ?>
						<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
					/>
					<span class="peiwm-toggle-slider"></span>
				</label>
				<p class="description">
					<?php echo esc_html__( 'Enable automatic scheduled exports of your content.', 'post-export-import-with-media' ); ?>
				</p>
				<?php if ( $next_run ) : ?>
					<p class="peiwm-next-run">
						<strong><?php echo esc_html__( 'Next scheduled run:', 'post-export-import-with-media' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Configuration (always shown, but locked if not PRO) -->
			<?php
			$locked_class = ! $is_pro_active ? ' peiwm-locked-section' : '';
			?>
			<div id="peiwm-scheduled-config" class="<?php echo esc_attr( $locked_class ); ?>" style="position: relative;">
				<?php if ( ! $is_pro_active ) : ?>
					<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
						<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
					</button>
				<?php endif; ?>

					<!-- Schedule Frequency -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Schedule Frequency', 'post-export-import-with-media' ); ?></h2>
						<div class="peiwm-frequency-options">
							<label class="peiwm-radio-label">
								<input type="radio" name="peiwm_scheduled_exports[schedule_frequency]" value="daily" <?php checked( $settings['schedule_frequency'], 'daily' ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-radio-text">
									<strong><?php echo esc_html__( 'Daily', 'post-export-import-with-media' ); ?></strong>
									<small><?php echo esc_html__( 'Export once every day', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
							<label class="peiwm-radio-label">
								<input type="radio" name="peiwm_scheduled_exports[schedule_frequency]" value="weekly" <?php checked( $settings['schedule_frequency'], 'weekly' ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-radio-text">
									<strong><?php echo esc_html__( 'Weekly', 'post-export-import-with-media' ); ?></strong>
									<small><?php echo esc_html__( 'Export once every week', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
							<label class="peiwm-radio-label">
								<input type="radio" name="peiwm_scheduled_exports[schedule_frequency]" value="monthly" <?php checked( $settings['schedule_frequency'], 'monthly' ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-radio-text">
									<strong><?php echo esc_html__( 'Monthly', 'post-export-import-with-media' ); ?></strong>
									<small><?php echo esc_html__( 'Export once every month', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
						</div>
					</div>

					<!-- Export Types -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'What to Export', 'post-export-import-with-media' ); ?></h2>
						<div class="peiwm-export-types">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="posts" <?php checked( in_array( 'posts', $settings['export_types'] ) ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Posts', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="pages" <?php checked( in_array( 'pages', $settings['export_types'] ) ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Pages', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="media" <?php checked( in_array( 'media', $settings['export_types'] ) ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Media', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="settings" <?php checked( in_array( 'settings', $settings['export_types'] ) ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Settings', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="cpt" <?php checked( in_array( 'cpt', $settings['export_types'] ) ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'CPT &amp; ACF', 'post-export-import-with-media' ); ?>
									<!-- <small style="display:block;color:#6b7280;font-size:0.78rem;margin-top:1px;"><?php echo esc_html__( 'All custom post types + ACF field values', 'post-export-import-with-media' ); ?></small> -->
								</span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="users" <?php checked( in_array( 'users', $settings['export_types'] ) ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Users', 'post-export-import-with-media' ); ?>
									<!-- <small style="display:block;color:#6b7280;font-size:0.78rem;margin-top:1px;"><?php echo esc_html__( 'Basic info, meta, capabilities, ACF user fields, WooCommerce data. Passwords excluded.', 'post-export-import-with-media' ); ?></small> -->
								</span>
							</label>
						</div>
					</div>

					<!-- Email Notifications -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Email Notifications', 'post-export-import-with-media' ); ?></h2>
						<label class="peiwm-toggle-switch">
							<input 
								type="checkbox" 
								id="enable_email_notifications" 
								name="peiwm_scheduled_exports[enable_email_notifications]" 
								value="1" 
								<?php checked( $settings['enable_email_notifications'], true ); ?>
								<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
							/>
							<span class="peiwm-toggle-slider"></span>
						</label>
						<p class="description">
							<?php echo esc_html__( 'Send email notifications when exports complete.', 'post-export-import-with-media' ); ?>
						</p>

						<div id="peiwm-email-config" style="<?php echo $settings['enable_email_notifications'] ? '' : 'display: none;'; ?>; margin-top: 1rem;">
							<label for="notification_emails">
								<strong><?php echo esc_html__( 'Email Addresses', 'post-export-import-with-media' ); ?></strong>
							</label>
							<textarea 
								id="notification_emails" 
								name="peiwm_scheduled_exports[notification_emails]" 
								rows="3" 
								class="large-text"
								placeholder="<?php echo esc_attr( $admin_email ); ?>"
								<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
							><?php echo esc_textarea( $settings['notification_emails'] ); ?></textarea>
							<p class="description">
								<?php echo esc_html__( 'Enter email addresses separated by commas. Leave empty to use admin email:', 'post-export-import-with-media' ); ?>
								<strong><?php echo esc_html( $admin_email ); ?></strong>
							</p>
						</div>
					</div>

					<!-- Backup Rotation -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Backup Rotation', 'post-export-import-with-media' ); ?></h2>
						<label class="peiwm-toggle-switch">
							<input 
								type="checkbox" 
								id="enable_backup_rotation" 
								name="peiwm_scheduled_exports[enable_backup_rotation]" 
								value="1" 
								<?php checked( $settings['enable_backup_rotation'], true ); ?>
								<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
							/>
							<span class="peiwm-toggle-slider"></span>
						</label>
						<p class="description">
							<?php echo esc_html__( 'Automatically delete old backups to save space.', 'post-export-import-with-media' ); ?>
						</p>

						<div id="peiwm-rotation-config" style="<?php echo $settings['enable_backup_rotation'] ? '' : 'display: none;'; ?>; margin-top: 1rem;">
							<label for="keep_backups_count">
								<strong><?php echo esc_html__( 'Keep Last N Backups', 'post-export-import-with-media' ); ?></strong>
							</label>
							<input 
								type="number" 
								id="keep_backups_count" 
								name="peiwm_scheduled_exports[keep_backups_count]" 
								value="<?php echo esc_attr( $settings['keep_backups_count'] ); ?>" 
								min="1" 
								max="100" 
								class="small-text"
								<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'Number of recent backups to keep. Older backups will be automatically deleted. (Range: 1-100)', 'post-export-import-with-media' ); ?>
							</p>
						</div>
					</div>

					<!-- Storage Mode -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Storage Mode', 'post-export-import-with-media' ); ?></h2>
						<div class="peiwm-storage-modes">
							<label class="peiwm-storage-mode-card <?php echo $settings['storage_mode'] === 'local' ? 'active' : ''; ?>">
								<input type="radio" name="peiwm_scheduled_exports[storage_mode]" value="local" <?php checked( $settings['storage_mode'], 'local' ); ?> <?php echo ! $is_pro_active ? 'disabled' : ''; ?>>
								<div class="peiwm-storage-mode-content">
									<div class="peiwm-storage-mode-icon">💾</div>
									<h3><?php echo esc_html__( 'Local Storage', 'post-export-import-with-media' ); ?></h3>
									<p><?php echo esc_html__( 'Save backups to your server', 'post-export-import-with-media' ); ?></p>
									<span class="peiwm-storage-mode-badge"><?php echo esc_html__( 'Active', 'post-export-import-with-media' ); ?></span>
								</div>
							</label>
							<label class="peiwm-storage-mode-card disabled">
								<input type="radio" name="peiwm_scheduled_exports[storage_mode]" value="google_drive" disabled>
								<div class="peiwm-storage-mode-content">
									<div class="peiwm-storage-mode-icon">☁️</div>
									<h3><?php echo esc_html__( 'Google Drive', 'post-export-import-with-media' ); ?></h3>
									<p><?php echo esc_html__( 'Save backups to Google Drive', 'post-export-import-with-media' ); ?></p>
									<span class="peiwm-storage-mode-badge coming-soon"><?php echo esc_html__( 'Coming Soon', 'post-export-import-with-media' ); ?></span>
								</div>
							</label>
						</div>

						<!-- Local Storage Info -->
						<div id="peiwm-local-storage-info" style="<?php echo $settings['storage_mode'] === 'local' ? '' : 'display: none;'; ?>">
							<div class="peiwm-storage-info-box">
								<h3><?php echo esc_html__( 'Local Storage Path', 'post-export-import-with-media' ); ?></h3>
								<code class="peiwm-storage-path"><?php echo esc_html( $this->get_backup_directory() ); ?></code>
								<p class="description">
									<?php echo esc_html__( 'Backups are stored in your WordPress uploads directory for security and easy access.', 'post-export-import-with-media' ); ?>
								</p>
							</div>
						</div>
					</div>

				</div>

				<?php if ( $is_pro_active ) : ?>
					<?php submit_button( __( 'Save Settings', 'post-export-import-with-media' ), 'primary', 'submit', true ); ?>
				<?php endif; ?>
			</form>

			<!-- Existing Backups -->
			<?php if ( $is_pro_active ) : ?>
			<div class="peiwm-settings-section peiwm-backups-section">
				<h2><?php echo esc_html__( 'Existing Backups', 'post-export-import-with-media' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Manage your scheduled export backups.', 'post-export-import-with-media' ); ?></p>
				
				<button type="button" id="peiwm-refresh-backups" class="button button-secondary">
					<?php echo esc_html__( 'Refresh List', 'post-export-import-with-media' ); ?>
				</button>

				<div id="peiwm-backups-list" class="peiwm-backups-list">
					<div class="peiwm-loading">
						<div class="peiwm-loading-spinner"></div>
						<p><?php echo esc_html__( 'Loading backups...', 'post-export-import-with-media' ); ?></p>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Delete Confirmation Modal -->
		<div id="peiwm-delete-modal" class="peiwm-modal-overlay" style="display: none;">
			<div class="peiwm-modal peiwm-danger-modal">
				<div class="peiwm-modal-header">
					<h3><?php echo esc_html__( 'Delete Backup', 'post-export-import-with-media' ); ?></h3>
					<button type="button" class="peiwm-modal-close">&times;</button>
				</div>
				<div class="peiwm-modal-body">
					<div class="peiwm-danger-icon">⚠️</div>
					<p><?php echo esc_html__( 'Are you sure you want to delete this backup?', 'post-export-import-with-media' ); ?></p>
					<p class="peiwm-modal-filename" style="font-weight: 600; color: #742a2a; margin-top: 1rem;"></p>
					<p style="color: #6b7280; margin-top: 0.5rem;"><?php echo esc_html__( 'This action cannot be undone.', 'post-export-import-with-media' ); ?></p>
				</div>
				<div class="peiwm-modal-footer">
					<button type="button" id="peiwm-delete-cancel" class="button button-secondary">
						<?php echo esc_html__( 'Cancel', 'post-export-import-with-media' ); ?>
					</button>
					<button type="button" id="peiwm-delete-confirm" class="button button-danger">
						<?php echo esc_html__( 'Delete Backup', 'post-export-import-with-media' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Premium Upgrade Modal -->
		<div id="peiwm-premium-modal" class="peiwm-modal-overlay" style="display: none;">
			<div class="peiwm-modal peiwm-premium-modal">
				<button type="button" class="peiwm-modal-close peiwm-premium-close">&times;</button>
				<div class="peiwm-premium-modal-body">
					<div class="peiwm-premium-badge-wrap">
						<span class="peiwm-premium-fire">🔥</span>
						<span class="peiwm-premium-offer-tag"><?php echo esc_html__( 'LIMITED TIME OFFER', 'post-export-import-with-media' ); ?></span>
					</div>
					<div class="peiwm-premium-icon">🚀</div>
					<h2 class="peiwm-premium-title"><?php echo esc_html__( 'Unlock PRO Features', 'post-export-import-with-media' ); ?></h2>
					<p class="peiwm-premium-subtitle"><?php echo esc_html__( 'You\'re one step away from powerful automation tools!', 'post-export-import-with-media' ); ?></p>
					<div class="peiwm-premium-features">
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Scheduled Automatic Exports', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Selective Export & Import', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Batch Processing (100K+ posts)', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Email Notifications', 'post-export-import-with-media' ); ?></div>
					</div>
					<div class="peiwm-premium-urgency">
						<span class="peiwm-urgency-dot"></span>
						<?php echo esc_html__( 'Special offer active — grab it before it\'s gone!', 'post-export-import-with-media' ); ?>
					</div>
					<a href="https://wpazleen.com/post-export-import-with-media/" target="_blank" class="peiwm-premium-cta-btn">
						<?php echo esc_html__( 'Get PRO Now →', 'post-export-import-with-media' ); ?>
					</a>
					<p class="peiwm-premium-note"><?php echo esc_html__( 'Instant access · 14-day money back guarantee', 'post-export-import-with-media' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
