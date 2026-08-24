<?php
/**
 * Pro Main Plugin Class
 *
 * @package Post_Export_Import_With_Media_Pro
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro Main Plugin Class - Coordinates all PRO plugin functionality
 */
class PEIWM_Pro_Main {

	/**
	 * Plugin instance
	 *
	 * @var PEIWM_Pro_Main|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance (Singleton pattern)
	 *
	 * @return PEIWM_Pro_Main
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
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		// Load PRO-specific classes
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-ajax-handler-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-batch-processor-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-batch-settings-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-cpt-acf-exporter-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-email-settings-handler-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-email-template-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-pro-handler.php';

		
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-post-handler-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-page-handler-pro.php';
		
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-media-handler-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-media-alt-editor-page-pro.php';
		
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-settings-handler-pro.php';
		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-user-handler-pro.php';
		// Note: email-template-page-pro.php is a template file loaded on-demand
		// by PEIWM_Admin_Menu::email_template_page() — not included here.

		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-scheduled-exports.php';

		require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-cloud-storage.php'; // Upcoming feature
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Initialize components
		add_action( 'init', array( $this, 'init_components' ), 25 ); // After free version
		
		// Add PRO badge to menu
		add_filter( 'admin_menu', array( $this, 'add_pro_badge' ), 999 );
		
		// Enqueue PRO assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pro_assets' ) );
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		
		// Initialize scheduled exports
		PEIWM_Scheduled_Exports_Pro::get_instance();
		
		// Initialize cloud storage
		PEIWM_Cloud_Storage::get_instance();
	}

	/**
	 * Add PRO badge to admin menu
	 */
	public function add_pro_badge() {
		global $menu, $submenu;
		
		// Add PRO badge to main menu item
		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === 'peiwm-secure' ) {
				$menu[ $key ][0] .= ' <span class="peiwm-pro-badge">PRO</span>';
				break;
			}
		}
	}

	/**
	 * Enqueue PRO assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_pro_assets( $hook ) {
		// Only load on plugin pages
		if ( strpos( $hook, 'peiwm' ) === false ) {
			return;
		}

		// Enqueue PRO CSS
		wp_enqueue_style(
			'peiwm-pro-admin',
			PEIWM_PRO_PLUGIN_URL . 'assets/css/pro-admin.css',
			array(),
			PEIWM_PRO_VERSION
		);

		// Enqueue PRO JS
		wp_enqueue_script(
			'peiwm-pro-admin',
			PEIWM_PRO_PLUGIN_URL . 'assets/js/pro-admin.js',
			array( 'jquery' ),
			PEIWM_PRO_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'peiwm-pro-admin',
			'peiwm_pro_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'peiwm_pro_secure_nonce' ),
				'is_pro'   => true,
				'version'  => PEIWM_PRO_VERSION,
			)
		);
	}
}
