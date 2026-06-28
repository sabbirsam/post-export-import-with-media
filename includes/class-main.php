<?php
/**
 * Main Plugin Class
 *
 * @package Post_Export_Import_With_Media
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class - Coordinates all plugin functionality
 */
class PEIWM_Main {

	/**
	 * Plugin instance
	 *
	 * @var PEIWM_Main|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance (Singleton pattern)
	 *
	 * @return PEIWM_Main
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize plugin
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
		$this->init_global_post_protection();
	}

	/**
	 * Initialize global post protection to prevent null post warnings
	 */
	private function init_global_post_protection() {
		// Add early hook to protect against null post access
		add_action( 'init', array( $this, 'setup_global_post_protection' ), 1 );
		add_action( 'admin_init', array( $this, 'setup_admin_post_protection' ), 1 );
		
		// Add custom error handler for null post warnings
		add_action( 'plugins_loaded', array( $this, 'setup_error_suppression' ), 1 );
	}

	/**
	 * Helper function to check if Freemius is available (either from Free or Pro).
	 *
	 * @since 1.3.0
	 * @return object|null Freemius SDK object or null.
	 */
	public function is_pro_active() {
		$is_pro_installed = class_exists( 'PEIWM_Pro_Main' )
			|| file_exists( WP_PLUGIN_DIR . '/post-export-import-with-media-pro/post-export-import-with-media-pro.php' );

		if ( ! $is_pro_installed ) {
			return false;
		}

		// Ensure is_plugin_active() is available
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'post-export-import-with-media-pro/post-export-import-with-media-pro.php' ) ) {
			return false;
		}

		$freemius_instance = peiwm_get_freemius_instance();
		if ( ! $freemius_instance ) {
			return false;
		}

		return $freemius_instance->can_use_premium_code__premium_only();
	}

	/**
	 * Helper function to check if Pro version is active.
	 *
	 * @since 1.3.0
	 * @return bool Whether Pro version is active.
	 */
	public function check_pro_plugin_exists() {
		// return is_plugin_active( 'post-export-import-with-media-pro/post-export-import-with-media-pro.php' );
		return file_exists( WP_PLUGIN_DIR . '/post-export-import-with-media-pro/post-export-import-with-media-pro.php' );
	}


	/**
	 * Setup error suppression for null post warnings
	 */
	public function setup_error_suppression() {
		// Only in admin context and for our plugin pages
		if ( is_admin() ) {
			// Set custom error handler to suppress null post warnings
			set_error_handler( array( $this, 'custom_error_handler' ), E_WARNING );
		}
	}

	/**
	 * Custom error handler to suppress null post property warnings
	 *
	 * @param int    $errno   Error number
	 * @param string $errstr  Error message
	 * @param string $errfile Error file
	 * @param int    $errline Error line
	 * @return bool True to suppress error, false to continue normal handling
	 */
	public function custom_error_handler( $errno, $errstr, $errfile, $errline ) {
		// Check if this is a null post property warning
		if ( $errno === E_WARNING && 
			 ( strpos( $errstr, 'Attempt to read property "post_status" on null' ) !== false ||
			   strpos( $errstr, 'Attempt to read property "ID" on null' ) !== false ||
			   strpos( $errstr, 'Attempt to read property' ) !== false && strpos( $errstr, 'on null' ) !== false ) ) {
			
			// Suppress these specific warnings
			return true;
		}
		
		// Let other errors be handled normally
		return false;
	}

	/**
	 * Setup global post protection
	 */
	public function setup_global_post_protection() {
		// Ensure global post is never null in admin context
		if ( is_admin() ) {
			add_action( 'wp_loaded', array( $this, 'ensure_valid_global_post' ), 1 );
			add_action( 'admin_head', array( $this, 'ensure_valid_global_post' ), 1 );
		}
	}

	/**
	 * Setup admin-specific post protection
	 */
	public function setup_admin_post_protection() {
		// Protect against null post in admin context
		add_action( 'current_screen', array( $this, 'ensure_valid_global_post' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'ensure_valid_global_post' ), 1 );
	}

	/**
	 * Ensure global post is never null to prevent warnings
	 */
	public function ensure_valid_global_post() {
		global $post;
		
		// If post is null, create a minimal valid post object
		if ( null === $post ) {
			$post = new stdClass();
			$post->ID = 0;
			$post->post_status = 'publish';
			$post->post_type = 'post';
			$post->post_title = '';
			$post->post_content = '';
			$post->post_excerpt = '';
			$post->post_author = 0;
			$post->post_date = current_time( 'mysql' );
			$post->post_modified = current_time( 'mysql' );
			$post->post_name = '';
			$post->post_parent = 0;
			$post->menu_order = 0;
			$post->comment_status = 'closed';
			$post->ping_status = 'closed';
			$post->comment_count = 0;
		}
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		
		$pro_path = WP_PLUGIN_DIR . '/post-export-import-with-media-pro/includes/';
		$has_pro  = is_dir( $pro_path );

		// Core classes always loaded
		require_once PEIWM_PLUGIN_PATH . 'includes/class-email-template.php';
		require_once PEIWM_PLUGIN_PATH . 'includes/class-admin-menu.php';

		require_once PEIWM_PLUGIN_PATH . 'includes/class-ajax-handler.php';
		if ( $has_pro && file_exists( $pro_path . 'class-ajax-handler-pro.php' ) ) {
			require_once $pro_path . 'class-ajax-handler-pro.php';
		}

		// Post handler 
		require_once PEIWM_PLUGIN_PATH . 'includes/class-post-handler.php';
		if ( $has_pro && file_exists( $pro_path . 'class-post-handler-pro.php' ) ) {
			require_once $pro_path . 'class-post-handler-pro.php';
		}

		// Page handler
		require_once PEIWM_PLUGIN_PATH . 'includes/class-page-handler.php';
		if ( $has_pro && file_exists( $pro_path . 'class-page-handler-pro.php' ) ) {
			require_once $pro_path . 'class-page-handler-pro.php';
		}

		// Media handler
		require_once PEIWM_PLUGIN_PATH . 'includes/class-media-handler.php';
		if ( $has_pro && file_exists( $pro_path . 'class-media-handler-pro.php' ) ) {
			require_once $pro_path . 'class-media-handler-pro.php';
		}

		// User handler
		require_once PEIWM_PLUGIN_PATH . 'includes/class-user-handler.php';
		if ( $has_pro && file_exists( $pro_path . 'class-user-handler-pro.php' ) ) {
			require_once $pro_path . 'class-user-handler-pro.php';
		}

		// Email settings handler
		if ( $has_pro && file_exists( $pro_path . 'class-email-settings-handler-pro.php' ) ) {
			require_once $pro_path . 'class-email-settings-handler-pro.php';
		}

		// CPT & ACF exporter
		if ( $has_pro && file_exists( $pro_path . 'class-cpt-acf-exporter-pro.php' ) ) {
			require_once $pro_path . 'class-cpt-acf-exporter-pro.php';
		}

		// Always needed
		require_once PEIWM_PLUGIN_PATH . 'includes/class-settings-handler.php';
		require_once PEIWM_PLUGIN_PATH . 'includes/class-themes-plugins-handler.php';
		require_once PEIWM_PLUGIN_PATH . 'includes/class-widgets-menus-handler.php';
		require_once PEIWM_PLUGIN_PATH . 'includes/class-admin-download-buttons.php';

		require_once PEIWM_PLUGIN_PATH . 'includes/class-batch-settings.php';
		if ( $has_pro && file_exists( $pro_path . 'class-batch-settings-pro.php' ) ) {
			require_once $pro_path . 'class-batch-settings-pro.php';
		}
		require_once PEIWM_PLUGIN_PATH . 'includes/class-batch-processor.php';
		if ( $has_pro && file_exists( $pro_path . 'class-batch-processor-pro.php' ) ) {
			require_once $pro_path . 'class-batch-processor-pro.php';
		}

		require_once PEIWM_PLUGIN_PATH . 'includes/class-heartbeat-handler.php';
		require_once PEIWM_PLUGIN_PATH . 'includes/class-generic-recommendations.php';

		// Scheduled exports
		require_once PEIWM_PLUGIN_PATH . 'includes/class-scheduled-exports.php';
		if ( $has_pro && file_exists( $pro_path . 'class-scheduled-exports.php' ) ) {
			require_once $pro_path . 'class-scheduled-exports.php';
		}
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Initialize components
		add_action( 'init', array( $this, 'init_components' ) );
		
		// Cleanup hooks
		add_action( 'wp_scheduled_delete', array( $this, 'cleanup_temp_files' ) );
		
		// Admin init
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Fix wp-editor script conflict
		add_action( 'admin_enqueue_scripts', array( $this, 'fix_script_conflicts' ), 1 );
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		$is_pro = $this->is_pro_active();

		// Initialize admin menu
		PEIWM_Admin_Menu::get_instance();
		
		// Initialize AJAX handler
		if ( $is_pro && class_exists( 'PEIWM_Ajax_Handler_Pro' ) ) {
			PEIWM_Ajax_Handler_Pro::get_instance();
		}else {
			PEIWM_Ajax_Handler::get_instance();
		}
		
		// Initialize post handler
		if ( $is_pro && class_exists( 'PEIWM_Post_Handler_Pro' ) ) {
			PEIWM_Post_Handler_Pro::get_instance();
		}else {
			PEIWM_Post_Handler::get_instance();
		}
		
		// Initialize page handler
		if ( $is_pro && class_exists( 'PEIWM_Page_Handler_Pro' ) ) {
			PEIWM_Page_Handler_Pro::get_instance();
		}else {
			PEIWM_Page_Handler::get_instance();
		}
		
		// Initialize media handler
		if ( $is_pro && class_exists( 'PEIWM_Media_Handler_Pro' ) ) {
			PEIWM_Media_Handler_Pro::get_instance();
		}else {
			PEIWM_Media_Handler::get_instance();
		}
		
		
		// Initialize settings handler
		PEIWM_Settings_Handler::get_instance();

		// Initialize email settings handler
		if ( $is_pro && class_exists( 'PEIWM_Email_Settings_Handler_Pro' ) ) {
			PEIWM_Email_Settings_Handler_Pro::get_instance();
		}
		
		// Initialize themes & plugins handler
		PEIWM_Themes_Plugins_Handler::get_instance();
		
		// Initialize widgets & menus handler
		PEIWM_Widgets_Menus_Handler::get_instance();
		
		// Initialize admin download buttons
		PEIWM_Admin_Download_Buttons::get_instance();

		// Initialize scheduled exports
		PEIWM_Scheduled_Exports::get_instance();
		if ( $is_pro && class_exists( 'PEIWM_Scheduled_Exports_Pro' ) ) {
			PEIWM_Scheduled_Exports_Pro::get_instance();
		}
		
		// Initialize batch settings
		if ( $is_pro && class_exists( 'PEIWM_Batch_Settings_Pro' ) ) {
			PEIWM_Batch_Settings_Pro::get_instance();
		}else {
			PEIWM_Batch_Settings::get_instance();
		}
		
		// Initialize batch processor
		if ( $is_pro && class_exists( 'PEIWM_Batch_Processor_Pro' ) ) {
			PEIWM_Batch_Processor_Pro::get_instance();
		}else {
			PEIWM_Batch_Processor::get_instance();
		}
		
		// Initialize heartbeat handler
		PEIWM_Heartbeat_Handler::get_instance();

		// Initialize CPT & ACF Exporter
		if ( $is_pro && class_exists( 'PEIM_CPT_ACF_Exporter_Pro' ) ) {
			PEIM_CPT_ACF_Exporter_Pro::get_instance()->init();
		}
		
		// Initialize user handler
		if ( $is_pro && class_exists( 'PEIWM_User_Handler_Pro' ) ) {
			PEIWM_User_Handler_Pro::get_instance();
		}else {
			PEIWM_User_Handler::get_instance();
		}
		
		// Initialize recommendations
		if ( class_exists( 'Recommendations' ) ) {
			new Recommendations();
		}
	}

	/**
	 * Initialize admin settings
	 */
	public function admin_init() {
		$this->add_capabilities();
	}

	/**
	 * Add custom capabilities
	 */
	private function add_capabilities() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( 'peiwm_manage' ) ) {
			$role->add_cap( 'peiwm_manage' );
		}
	}

	/**
	 * Fix script conflicts
	 *
	 * @param string $hook Current admin page hook
	 */
	public function fix_script_conflicts( $hook ) {
		// Prevent wp-editor script conflict on widgets page
		if ( in_array( $hook, array( 'widgets.php', 'customize.php' ), true ) ) {
			wp_dequeue_script( 'wp-editor' );
		}
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'peiwm_admin_download_buttons',
			'peiwm_enable_admin_download_buttons',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'peiwm_settings',
			'peiwm_user_import_default_password',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'peiwm_settings',
			'peiwm_user_import_send_email',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'peiwm_settings',
			'peiwm_allowed_media_file_types',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_file_types' ),
				'default'           => 'jpg,jpeg,png,gif,webp,svg,json,pdf,mp4,mp3,wav,doc,docx,txt',
			)
		);

		register_setting(
			'peiwm_settings',
			'peiwm_allow_all_file_types',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Sanitize file types input
	 */
	public function sanitize_file_types( $value ) {
		if ( empty( $value ) ) {
			return 'jpg,jpeg,png,gif,webp,svg,json,pdf,mp4,mp3,wav,doc,docx,txt';
		}
		
		// Remove spaces, convert to lowercase, split by comma
		$types = array_map( 'trim', explode( ',', strtolower( $value ) ) );
		
		// Remove empty values and sanitize each extension (only alphanumeric and dash)
		$types = array_filter( array_map( function( $ext ) {
			// Only allow alphanumeric characters, dash, and underscore in extensions
			return preg_replace( '/[^a-z0-9_-]/', '', $ext );
		}, $types ) );
		
		return implode( ',', $types );
	}

	/**
	 * Cleanup temporary files
	 */
	public function cleanup_temp_files() {
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/peiwm-temp/';
		
		if ( is_dir( $temp_dir ) ) {
			$files = glob( $temp_dir . '*' );
			$now = time();
			
			foreach ( $files as $file ) {
				if ( is_file( $file ) && ( $now - filemtime( $file ) ) > 3600 ) { // 1 hour
					wp_delete_file( $file );
				}
			}
		}
	}
}