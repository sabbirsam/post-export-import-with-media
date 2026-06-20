<?php
/**
 * Admin Menu Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Menu Class - Handles admin menu and page rendering
 */
class PEIWM_Admin_Menu {

	/**
	 * Instance
	 *
	 * @var PEIWM_Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Admin_Menu
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_recommendations_menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'current_screen', array( $this, 'protect_plugin_pages' ) );
	}

	/**
	 * Protect our plugin pages from null post warnings
	 */
	public function protect_plugin_pages() {
		$screen = get_current_screen();
		
		// Check if we're on one of our plugin pages
		if ( $screen && ( 
			strpos( $screen->id, 'peiwm' ) !== false || 
			strpos( $screen->id, 'export-import' ) !== false ||
			$screen->id === 'toplevel_page_peiwm-secure' ||
			strpos( $screen->id, 'peiwm-' ) !== false
		) ) {
			// Add multiple protection layers for our pages
			add_action( 'admin_head', array( $this, 'ensure_valid_post_object' ), 1 );
			add_action( 'admin_footer', array( $this, 'ensure_valid_post_object' ), 1 );
			add_action( 'wp_ajax_peiwm_import_widgets_menus', array( $this, 'ensure_valid_post_object' ), 1 );
			
			// Monitor and fix global post throughout page lifecycle
			add_action( 'wp_loaded', array( $this, 'monitor_global_post' ), 999 );
		}
	}

	/**
	 * Ensure valid post object exists
	 */
	public function ensure_valid_post_object() {
		global $post;
		
		if ( null === $post || ! is_object( $post ) ) {
			$post = new WP_Post( (object) array(
				'ID' => 0,
				'post_status' => 'publish',
				'post_type' => 'post',
				'post_title' => '',
				'post_content' => '',
				'post_excerpt' => '',
				'post_author' => get_current_user_id(),
				'post_date' => current_time( 'mysql' ),
				'post_modified' => current_time( 'mysql' ),
				'post_name' => '',
				'post_parent' => 0,
				'menu_order' => 0,
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'comment_count' => 0,
				'post_date_gmt' => current_time( 'mysql', 1 ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
				'post_content_filtered' => '',
				'post_password' => '',
				'to_ping' => '',
				'pinged' => '',
				'guid' => '',
				'post_mime_type' => '',
				'filter' => 'raw'
			) );
		}
	}

	/**
	 * Monitor and maintain global post state
	 */
	public function monitor_global_post() {
		// Set up a periodic check to ensure post object remains valid
		add_action( 'wp_footer', array( $this, 'ensure_valid_post_object' ), 999 );
		add_action( 'admin_footer', array( $this, 'ensure_valid_post_object' ), 999 );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {

		add_menu_page(
			esc_html__( 'WP Post Export Import', 'post-export-import-with-media' ),
			esc_html__( 'Export/Import Posts', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-secure',
			array( $this, 'admin_page' ),
			'dashicons-upload',
			30
		);

		// Add pages submenu
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'Export/Import Pages', 'post-export-import-with-media' ),
			esc_html__( 'Export/Import Pages', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-pages',
			array( $this, 'pages_page' )
		);

		// Add settings submenu
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'WordPress Settings', 'post-export-import-with-media' ),
			esc_html__( 'WordPress Settings', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-settings',
			array( $this, 'settings_page' )
		);

		// Add themes & plugins submenu
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'WP Toolkit', 'post-export-import-with-media' ),
			esc_html__( 'WP Toolkit', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-themes-plugins',
			array( $this, 'themes_plugins_page' )
		);

		// CPT & ACF page (Uses overlay lock pattern for Free users)
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'CPT & ACF Export/Import', 'post-export-import-with-media' ),
			esc_html__( 'CPT Export/Import', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-cpt-acf',
			array( $this, 'cpt_acf_page' )
		);

		// Users Export/Import page
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'Users Export/Import', 'post-export-import-with-media' ),
			esc_html__( 'Users Export/Import', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-users',
			array( $this, 'users_page' )
		);

		// Email Template Settings page
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'Email Template Settings', 'post-export-import-with-media' ),
			esc_html__( 'Email Template', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-email-template',
			array( $this, 'email_template_page' )
		);

		// Note: Batch Settings (priority 30) and Scheduled Exports (priority 40)
		// are added by their respective classes
		// Recommendations (priority 50) is added in add_recommendations_menu()
	}

	/**
	 * Add recommendations menu (priority 50 to appear last)
	 */
	public function add_recommendations_menu() {
		add_submenu_page(
			'peiwm-secure',
			esc_html__( 'Plugin Recommendations', 'post-export-import-with-media' ),
			esc_html__( 'Recommendations', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-recommendations',
			array( $this, 'recommendations_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Main plugin page (Posts & Media)
		if ( 'toplevel_page_peiwm-secure' === $hook ) {
			wp_enqueue_script(
				'peiwm-admin-js',
				PEIWM_PLUGIN_URL . 'build/js/admin.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_localize_script( 'peiwm-admin-js', 'peiwm_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'peiwm_secure_nonce' ),
				'strings'  => array(
					'select_file'     => esc_html__( 'Please select a file to import.', 'post-export-import-with-media' ),
					'file_too_large'  => esc_html__( 'File is too large. Please select a file smaller than 500MB.', 'post-export-import-with-media' ),
					'select_zip'      => esc_html__( 'Please select a ZIP file.', 'post-export-import-with-media' ),
					'processing'      => esc_html__( 'Processing...', 'post-export-import-with-media' ),
					'success'         => esc_html__( 'Success!', 'post-export-import-with-media' ),
					'error'           => esc_html__( 'Error:', 'post-export-import-with-media' ),
					'complete'        => esc_html__( 'Complete!', 'post-export-import-with-media' ),
					'confirm_delete'  => esc_html__( 'Are you sure you want to delete all items? This action cannot be undone.', 'post-export-import-with-media' ),
				),
			) );
		}
		else{
			// Global styles 
			wp_enqueue_style(
				'global-peiwm-css',
				PEIWM_PLUGIN_URL . 'build/css/global-peiwm.css.min.css',
				array(),
				PEIWM_VERSION
			);
		}

		// Pages page
		if ( 'export-import-posts_page_peiwm-pages' === $hook ) {
			wp_enqueue_script(
				'peiwm-pages-js',
				PEIWM_PLUGIN_URL . 'build/js/pages.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_localize_script( 'peiwm-pages-js', 'peiwm_ajax', array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'peiwm_secure_nonce' ),
				'export_json_size' => PEIWM_Batch_Settings::get_instance()->get_setting( 'export_json_size' ),
				'strings'          => array(
					'select_file'    => esc_html__( 'Please select a file to import.', 'post-export-import-with-media' ),
					'file_too_large' => esc_html__( 'File is too large. Please select a file smaller than 500MB.', 'post-export-import-with-media' ),
					'select_json'    => esc_html__( 'Please select a JSON file.', 'post-export-import-with-media' ),
					'processing'     => esc_html__( 'Processing...', 'post-export-import-with-media' ),
					'success'        => esc_html__( 'Success!', 'post-export-import-with-media' ),
					'error'          => esc_html__( 'Error:', 'post-export-import-with-media' ),
					'complete'       => esc_html__( 'Complete!', 'post-export-import-with-media' ),
					'confirm_delete' => esc_html__( 'Are you sure you want to delete all items? This action cannot be undone.', 'post-export-import-with-media' ),
				),
			) );
		}

		// Settings page
		if ( 'export-import-posts_page_peiwm-settings' === $hook ) {
			wp_enqueue_script(
				'peiwm-settings-js',
				PEIWM_PLUGIN_URL . 'build/js/settings.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_localize_script( 'peiwm-settings-js', 'peiwm_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'peiwm_secure_nonce' ),
				'strings'  => array(
					'select_file'     => esc_html__( 'Please select a file to import.', 'post-export-import-with-media' ),
					'file_too_large'  => esc_html__( 'File is too large. Please select a file smaller than 500MB.', 'post-export-import-with-media' ),
					'select_json'     => esc_html__( 'Please select a JSON file.', 'post-export-import-with-media' ),
					'processing'      => esc_html__( 'Processing...', 'post-export-import-with-media' ),
					'success'         => esc_html__( 'Success!', 'post-export-import-with-media' ),
					'error'           => esc_html__( 'Error:', 'post-export-import-with-media' ),
					'complete'        => esc_html__( 'Complete!', 'post-export-import-with-media' ),
					'confirm_delete'  => esc_html__( 'Are you sure you want to delete all items? This action cannot be undone.', 'post-export-import-with-media' ),
				),
			) );
		}

		// Themes & Plugins page
		if ( 'export-import-posts_page_peiwm-themes-plugins' === $hook ) {
			wp_enqueue_script(
				'peiwm-themes-plugins-js',
				PEIWM_PLUGIN_URL . 'build/js/themes-plugins.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_localize_script( 'peiwm-themes-plugins-js', 'peiwm_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'peiwm_secure_nonce' ),
				'strings'  => array(
					'select_file'     => esc_html__( 'Please select a file to import.', 'post-export-import-with-media' ),
					'file_too_large'  => esc_html__( 'File is too large. Please select a file smaller than 500MB.', 'post-export-import-with-media' ),
					'select_zip'      => esc_html__( 'Please select a ZIP file.', 'post-export-import-with-media' ),
					'processing'      => esc_html__( 'Processing...', 'post-export-import-with-media' ),
					'success'         => esc_html__( 'Success!', 'post-export-import-with-media' ),
					'error'           => esc_html__( 'Error:', 'post-export-import-with-media' ),
					'complete'        => esc_html__( 'Complete!', 'post-export-import-with-media' ),
					'confirm_delete'  => esc_html__( 'Are you sure you want to delete all items? This action cannot be undone.', 'post-export-import-with-media' ),
				),
			) );
		}

		// Recommendations page
		if ( 'export-import-posts_page_peiwm-recommendations' === $hook ) {
			add_thickbox();

			wp_enqueue_style(
				'peiwm-recommendations-css',
				PEIWM_PLUGIN_URL . 'build/css/recommendations.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_enqueue_script(
				'peiwm-recommendations-js',
				PEIWM_PLUGIN_URL . 'build/js/recommendations.min.js',
				array( 'jquery', 'thickbox', 'updates' ),
				PEIWM_VERSION,
				true
			);

			wp_localize_script( 'peiwm-recommendations-js', 'peiwmRecommendations', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'peiwm_recommendations_nonce' ),
				'pluginUrl' => admin_url( 'plugin-install.php' ),
			) );
		}

		// Scheduled Exports page
		if ( 'export-import-posts_page_peiwm-scheduled-exports' === $hook ) {
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
			wp_localize_script( 'peiwm-scheduled-exports-js', 'peiwm_scheduled_exports', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'peiwm_secure_nonce' ),
				'is_pro'   => PEIWM_Main::get_instance()->is_pro_active() ? '1' : '0',
			) );
		}

		// Batch Settings page
		if ( 'export-import-posts_page_peiwm-batch-settings' === $hook ) {
			
			// batch-settings.min.js
			wp_enqueue_script(
				'peiwm-batch-settings-js',
				PEIWM_PLUGIN_URL . 'build/js/batch-settings.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);
			
			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			
		}

		// CPT & ACF page
		if ( 'export-import-posts_page_peiwm-cpt-acf' === $hook ) {			// Try built JS file first; fall back to unminified source
			wp_enqueue_script(
				'peiwm-cpt-acf-js',
				PEIWM_PLUGIN_URL . 'build/js/cpt-acf.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_enqueue_style(
				'peiwm-cpt-acf-css',
				PEIWM_PLUGIN_URL . 'build/css/cpt-acf.min.css',
				array( 'peiwm-admin-css' ),
				PEIWM_VERSION
			);

			wp_localize_script( 'peiwm-cpt-acf-js', 'peiwm_cpt_acf', array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'peiwm_secure_nonce' ),
				'batch_mode'        => PEIWM_Batch_Settings::get_instance()->get_setting( 'enable_batch_processing' ) ? '1' : '0',
				'batch_size'        => PEIWM_Batch_Settings::get_instance()->get_setting( 'post_batch_size' ),
				'concurrent_requests' => PEIWM_Batch_Settings::get_instance()->get_setting( 'concurrent_requests' ),
				'export_json_size'  => PEIWM_Batch_Settings::get_instance()->get_setting( 'export_json_size' ),
				'batch_delay'       => PEIWM_Batch_Settings::get_instance()->get_setting( 'batch_delay' ),
				'is_pro_active'     => PEIWM_Main::get_instance()->is_pro_active() ? '1' : '0',
				'strings'    => array(
					'select_file'     => esc_html__( 'Please select a JSON file.', 'post-export-import-with-media' ),
					'select_post_type' => esc_html__( 'Please select a post type first.', 'post-export-import-with-media' ),
					'exporting'       => esc_html__( 'Exporting...', 'post-export-import-with-media' ),
					'importing'       => esc_html__( 'Importing...', 'post-export-import-with-media' ),
					'export_complete' => esc_html__( 'Export complete!', 'post-export-import-with-media' ),
					'import_complete' => esc_html__( 'Import complete!', 'post-export-import-with-media' ),
					'processing'      => esc_html__( 'Processing...', 'post-export-import-with-media' ),
					'error'           => esc_html__( 'Error:', 'post-export-import-with-media' ),
					'confirm_delete'  => esc_html__( 'Are you sure? This will permanently delete all posts of this type. This action cannot be undone.', 'post-export-import-with-media' ),
				),
			) );
		}

		// Users page
		if ( 'export-import-posts_page_peiwm-users' === $hook ) {
			wp_enqueue_script(
				'peiwm-users-js',
				PEIWM_PLUGIN_URL . 'build/js/users.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_localize_script( 'peiwm-users-js', 'peiwm_users_ajax', array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'peiwm_secure_nonce' ),
				'download_url'   => admin_url( 'admin-post.php?action=peiwm_download_users_export' ),
				'download_nonce' => wp_create_nonce( 'peiwm_download_nonce' ),
				'strings'        => array(
					'export_btn'          => esc_html__( 'Export Users', 'post-export-import-with-media' ),
					'import_btn'          => esc_html__( 'Import Users', 'post-export-import-with-media' ),
					'exporting'           => esc_html__( 'Exporting...', 'post-export-import-with-media' ),
					'importing'           => esc_html__( 'Importing...', 'post-export-import-with-media' ),
					'select_file'         => esc_html__( 'Please select a JSON file.', 'post-export-import-with-media' ),
					'invalid_json'        => esc_html__( 'Invalid JSON file.', 'post-export-import-with-media' ),
					'download_json'       => esc_html__( 'Download users JSON', 'post-export-import-with-media' ),
					'error'               => esc_html__( 'An error occurred. Please try again.', 'post-export-import-with-media' ),
					'summary_title'       => esc_html__( 'User Import Summary', 'post-export-import-with-media' ),
					'summary_imported'    => esc_html__( 'Imported', 'post-export-import-with-media' ),
					'summary_skipped'     => esc_html__( 'Already existed (skipped)', 'post-export-import-with-media' ),
					'summary_id_preserved'=> esc_html__( 'ID preserved', 'post-export-import-with-media' ),
					'summary_id_mismatch' => esc_html__( 'ID mismatch', 'post-export-import-with-media' ),
					'summary_emails_sent' => esc_html__( 'Emails sent', 'post-export-import-with-media' ),
					'summary_emails_failed'=> esc_html__( 'Emails failed', 'post-export-import-with-media' ),
					'mail_not_configured' => esc_html__( 'Mail not configured — emails skipped', 'post-export-import-with-media' ),
					'show_details'        => esc_html__( 'Show details', 'post-export-import-with-media' ),
					'hide_details'        => esc_html__( 'Hide details', 'post-export-import-with-media' ),
				),
			) );
		}

		// Email Template page
		if ( 'export-import-posts_page_peiwm-email-template' === $hook ) {
			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			wp_enqueue_style(
				'peiwm-email-template-css',
				PEIWM_PLUGIN_URL . 'build/css/email-template.min.css',
				array( 'peiwm-admin-css' ),
				PEIWM_VERSION
			);
		}

		
	}

	/**
	 * Render main admin page
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		?>
		<div class="wrap peiwm-admin">
			<h1 class='heading-admin'>
				<?php echo esc_html__( 'Export/Import Posts & Media', 'post-export-import-with-media' ); ?>
				<a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
					<span class="dashicons dashicons-video-alt3"></span>
				</a>
			</h1>
			
			<div class="peiwm-container">
				<!-- Posts Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Posts Export/Import', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Posts', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Export all posts with their metadata and featured images.', 'post-export-import-with-media' ); ?></p>

						<?php
						$main_instance_exp = PEIWM_Main::get_instance();
						$is_pro_exp        = $main_instance_exp->is_pro_active();
						$exp_locked        = ! $is_pro_exp ? ' peiwm-locked-section' : '';
						?>

						<!-- Advanced Options Toggle Button -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-export-posts">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'Advanced options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel (Collapsible) -->
						<div class="peiwm-advanced-panel" id="peiwm-advanced-export-posts" aria-hidden="true">
							
							<!-- PRO Row: Export individually -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
							<!-- <div class="peiwm-pro-inline-row peiwm-locked-section peiwm-open-premium-modal"> -->
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-posts-selective" <?php echo ! $is_pro_exp ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export individually (select specific posts)', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_exp ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Choose which posts to export instead of exporting all.', 'post-export-import-with-media' ); ?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_exp ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

						<!-- ============================================================ -->
						<!-- NEW: Export by date range row                                -->
						<!-- ============================================================ -->
						<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-export-posts-daterange" <?php echo ! $is_pro_exp ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text">
									<strong>
										<?php echo esc_html__( 'Export by date range', 'post-export-import-with-media' ); ?>
										<?php if ( ! $is_pro_exp ) : ?>
											<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
										<?php endif; ?>
									</strong>
									<span class="peiwm-checkbox-description">
										<?php echo esc_html__( 'Filter posts by published date range before selecting which to export.', 'post-export-import-with-media' ); ?>
									</span>
								</span>
							</label>
							<?php if ( ! $is_pro_exp ) : ?>
								<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
							<?php endif; ?>
						</div>

						<!-- Date range filter UI — shown when checkbox above is checked -->
						<div id="peiwm-daterange-filter-ui" style="display:none; margin: 0.5rem 0 0.25rem 1.75rem;">
							<div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
								<label style="font-size:0.875rem; font-weight:500; white-space:nowrap;">
									<?php echo esc_html__( 'From', 'post-export-import-with-media' ); ?>
									<input type="date" id="peiwm-export-date-from"
									       max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
									       style="margin-left:0.4rem; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; font-size:0.875rem;">
								</label>
								<label style="font-size:0.875rem; font-weight:500; white-space:nowrap;">
									<?php echo esc_html__( 'To', 'post-export-import-with-media' ); ?>
									<input type="date" id="peiwm-export-date-to"
									       max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
									       style="margin-left:0.4rem; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; font-size:0.875rem;">
								</label>
								<button type="button" id="peiwm-apply-date-filter" class="button button-secondary" style="padding:4px 12px; font-size:0.875rem;">
									<?php echo esc_html__( 'Apply Filter', 'post-export-import-with-media' ); ?>
								</button>
							</div>
							<p id="peiwm-daterange-error" style="display:none; color:#dc2626; font-size:0.8rem; margin:0.35rem 0 0;"></p>
							<p id="peiwm-daterange-summary" style="display:none; color:#6b7280; font-size:0.8rem; margin:0.35rem 0 0;"></p>
						</div>
						<!-- ============================================================ -->

							<!-- PRO Row: Export ACF fields -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-acf-fields" <?php echo ( ! $is_pro_exp || ! function_exists( 'get_fields' ) ) ? 'disabled' : ''; ?> <?php echo ! function_exists( 'get_fields' ) ? 'title="ACF plugin not active"' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export custom ACF meta fields', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_exp ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php
											if ( function_exists( 'get_fields' ) ) {
												echo esc_html__( 'Include Advanced Custom Fields data in the export with field keys.', 'post-export-import-with-media' );
											} else {
												echo esc_html__( 'ACF (Advanced Custom Fields) plugin is not active. Install and activate ACF to use this option.', 'post-export-import-with-media' );
											}
											?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_exp ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- Multilingual Support Row for Export (WPML & Polylang) -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<?php
									$multilingual_active = defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' );
									$multilingual_plugin = defined( 'ICL_SITEPRESS_VERSION' ) ? 'WPML' : ( defined( 'POLYLANG_VERSION' ) ? 'Polylang' : '' );
									?>
									<input type="checkbox" id="peiwm-export-wpml-data" <?php echo ( ! $is_pro_exp || ! $multilingual_active ) ? 'disabled' : ''; ?> <?php echo ! $multilingual_active ? 'title="Multilingual plugin not active"' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export WPML multilingual language data', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_exp ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php
											if ( ! $is_pro_exp ) {
												echo esc_html__( 'Export language assignments with posts. Requires PRO version and WPML or Polylang plugin.', 'post-export-import-with-media' );
											} elseif ( $multilingual_active ) {
												echo sprintf(
													esc_html__( 'Include %s language assignments in the export. Required for preserving multilingual structure on import.', 'post-export-import-with-media' ),
													esc_html( $multilingual_plugin )
												);
											} else {
												echo esc_html__( 'Multilingual plugin (WPML or Polylang) is not active. Install and activate WPML or Polylang to use this option.', 'post-export-import-with-media' );
											}
											?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_exp ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

						</div><!-- /.peiwm-advanced-panel -->

						<!-- Selective Export Panel for Posts -->
						<div id="peiwm-posts-export-selective-panel" style="display: none; margin-top: 1rem;">
							<div class="peiwm-selective-panel">
								<div class="peiwm-selective-header">
									<h4><?php echo esc_html__( 'Select Posts to Export', 'post-export-import-with-media' ); ?></h4>
									<div class="peiwm-selective-controls">
										<input type="text" id="peiwm-posts-export-search" class="peiwm-selective-search" placeholder="<?php echo esc_attr__( 'Search posts...', 'post-export-import-with-media' ); ?>">
										<label class="peiwm-select-all-label">
											<input type="checkbox" id="peiwm-posts-export-select-all" checked>
											<?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?>
										</label>
									</div>
								</div>
								<div id="peiwm-posts-export-list" class="peiwm-selective-list">
									<div class="peiwm-selective-loading">
										<div class="peiwm-loading-spinner"></div>
										<p><?php echo esc_html__( 'Loading posts...', 'post-export-import-with-media' ); ?></p>
									</div>
								</div>
								<div class="peiwm-selective-footer">
									<span id="peiwm-posts-export-selected-count" class="peiwm-selected-count"><?php echo esc_html__( '0 selected', 'post-export-import-with-media' ); ?></span>
									<span id="peiwm-posts-export-load-more-wrap"></span>
								</div>
							</div>
						</div>

						<button type="button" id="peiwm-export-posts" class="button button-primary">
							<?php echo esc_html__( 'Export Posts', 'post-export-import-with-media' ); ?>
						</button>

						<!-- PRO Toast -->
						<div class="peiwm-pro-toast" role="alert" aria-live="polite">
							<span class="peiwm-pro-toast__icon">🔒</span>
							<span class="peiwm-pro-toast__text">
								<?php echo esc_html__( 'This is a', 'post-export-import-with-media' ); ?> <strong><?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></strong> <?php echo esc_html__( 'feature. Upgrade to unlock it.', 'post-export-import-with-media' ); ?>
							</span>
							<a class="peiwm-pro-toast__cta button button-secondary peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media" target="_blank"><?php echo esc_html__( 'Learn more', 'post-export-import-with-media' ); ?> ↗</a>
							<button type="button" class="peiwm-pro-toast__close peiwm-pro-toast-close" aria-label="<?php echo esc_attr__( 'Close', 'post-export-import-with-media' ); ?>">×</button>
						</div>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Posts', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import posts from a previously exported JSON file.', 'post-export-import-with-media' ); ?></p>
						<div class="button-container">
							<input type="file" id="peiwm-posts-file" accept=".json" multiple style="display: none;">
							<button type="button" id="peiwm-select-posts-file" class="button button-secondary">
								<?php echo esc_html__( 'Select JSON File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-posts" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<!-- Free Options (Always Visible) -->
						<div class="peiwm-import-options" style="margin-top: 1rem;">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-check-media-library" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Check media library for post images', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description">
										<?php echo esc_html__( 'Check if images already exist in media library before importing. Uncheck for faster import (images will be missing).', 'post-export-import-with-media' ); ?>
									</small>
								</span>
							</label>
							
							<label class="peiwm-checkbox-label" style="margin-top: 0.5rem;">
								<input type="checkbox" id="peiwm-download-missing-images" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Download missing images from original URLs', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description">
										<?php echo esc_html__( 'If images are not found in media library, try to download them from their original locations. Uncheck for faster import.', 'post-export-import-with-media' ); ?>
									</small>
								</span>
							</label>
						</div>

						<?php
						$main_instance = PEIWM_Main::get_instance();
						$is_pro = $main_instance->is_pro_active();
						?>

						<!-- Advanced Options Toggle Button -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-import-posts" style="margin-top: 0.75rem;">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'More options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel (Collapsible) -->
						<div class="peiwm-advanced-panel" id="peiwm-advanced-import-posts" aria-hidden="true">
							
							<!-- Multilingual Support Row (WPML & Polylang) - Now available for free users when multilingual plugin is active -->
							<div class="peiwm-inline-row">
								<label class="peiwm-checkbox-label">
									<?php
									$multilingual_active_import = defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) || ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) );
									$multilingual_plugin_import = defined( 'ICL_SITEPRESS_VERSION' ) ? 'WPML' : ( ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_languages_list' ) ) ? 'Polylang' : '' );
									?>
									<input type="checkbox" id="peiwm_enable_wpml_support" name="peiwm_enable_wpml_support" value="1" <?php echo ( ! $multilingual_active_import ) ? 'disabled' : ''; ?> <?php echo get_option( 'peiwm_enable_wpml_support' ) ? 'checked' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Enable WPML multilingual language support', 'post-export-import-with-media' ); ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php
											if ( $multilingual_active_import ) {
												echo sprintf(
													esc_html__( 'Preserve %s language assignments when importing posts. Requires multilingual plugin to be active on both source and destination sites.', 'post-export-import-with-media' ),
													esc_html( $multilingual_plugin_import )
												);
											} else {
												echo esc_html__( 'Multilingual plugin (WPML or Polylang) is not active. Install and activate WPML or Polylang to use this option.', 'post-export-import-with-media' );
											}
											?>
										</span>
									</span>
								</label>
							</div>

							<!-- PRO Row: Import individually -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-import-posts-selective" <?php echo ! $is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Import individually (select specific posts)', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Choose which posts to import from the file instead of importing all. Even you can change the status before import.', 'post-export-import-with-media' ); ?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- PRO Row: Smart author mapping -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<div style="flex: 1;">
									<label class="peiwm-checkbox-label" style="margin: 0;">
										<input type="checkbox" id="peiwm_smart_author_mapping" name="peiwm_smart_author_mapping" value="1" <?php echo $is_pro ? 'checked' : 'disabled'; ?>>
										<span class="peiwm-checkbox-text">
											<strong>
												<?php echo esc_html__( 'Smart author mapping', 'post-export-import-with-media' ); ?>
												<?php if ( ! $is_pro ) : ?>
													<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
												<?php endif; ?>
											</strong>
											<span class="peiwm-checkbox-description">
												<?php echo esc_html__( 'Recommended. Prevents posts being assigned to the wrong author when user IDs differ between sites.', 'post-export-import-with-media' ); ?>
											</span>
										</span>
									</label>

									<div id="peiwm-author-fallback-options" style="margin: 6px 0 0 24px; <?php echo $is_pro ? '' : 'display:none;'; ?>">
										<p style="margin: 0 0 6px; font-weight: 600; font-size: 0.875rem;">
											<?php echo esc_html__( 'If the author is not found on this site:', 'post-export-import-with-media' ); ?>
										</p>
										<label class="peiwm-checkbox-label" style="margin-bottom: 4px;">
											<input type="radio" name="peiwm_author_fallback" value="current_user" <?php echo $is_pro ? 'checked' : 'disabled'; ?>>
											<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Assign post to the current admin user', 'post-export-import-with-media' ); ?></span>
										</label>
										<label class="peiwm-checkbox-label">
											<input type="radio" name="peiwm_author_fallback" value="create_user" <?php echo ! $is_pro ? 'disabled' : ''; ?>>
											<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Automatically create the missing user', 'post-export-import-with-media' ); ?></span>
										</label>
									</div>
								</div>
								<?php if ( ! $is_pro ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank" style="align-self: flex-start;"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- Add more  -->

						</div><!-- /.peiwm-advanced-panel -->

						<!-- Selective Import Panel -->
						<div id="peiwm-posts-selective-panel" style="display: none; margin-top: 1rem;">
							<div class="peiwm-selective-panel">
								<div class="peiwm-selective-header">
									<h4><?php echo esc_html__( 'Select Posts to Import', 'post-export-import-with-media' ); ?></h4>
									<div class="peiwm-selective-controls">
										<input type="text" id="peiwm-posts-search" class="peiwm-selective-search" placeholder="<?php echo esc_attr__( 'Search posts...', 'post-export-import-with-media' ); ?>">
										<label class="peiwm-select-all-label">
											<input type="checkbox" id="peiwm-posts-select-all">
											<?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?>
										</label>
									</div>
								</div>
								<div id="peiwm-posts-list" class="peiwm-selective-list">
									<p class="peiwm-selective-empty">👆 <?php echo esc_html__( 'Select a JSON file above to load posts for selection.', 'post-export-import-with-media' ); ?></p>
								</div>
								<div class="peiwm-selective-footer">
									<span id="peiwm-posts-selected-count" class="peiwm-selected-count"><?php echo esc_html__( '0 selected', 'post-export-import-with-media' ); ?></span>
								</div>
							</div>
						</div>
						
						<div id="peiwm-posts-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>

						<!-- PRO Toast -->
						<div class="peiwm-pro-toast" role="alert" aria-live="polite">
							<span class="peiwm-pro-toast__icon">🔒</span>
							<span class="peiwm-pro-toast__text">
								<?php echo esc_html__( 'This is a', 'post-export-import-with-media' ); ?> <strong><?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></strong> <?php echo esc_html__( 'feature. Upgrade to unlock it.', 'post-export-import-with-media' ); ?>
							</span>
							<a class="peiwm-pro-toast__cta button button-secondary peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media" target="_blank"><?php echo esc_html__( 'Learn more', 'post-export-import-with-media' ); ?> ↗</a>
							<button type="button" class="peiwm-pro-toast__close peiwm-pro-toast-close" aria-label="<?php echo esc_attr__( 'Close', 'post-export-import-with-media' ); ?>">×</button>
						</div>
					</div>
					
					<div class="peiwm-delete-section">
						<h3><?php echo esc_html__( 'Delete Posts', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( '⚠️ Warning: This will permanently delete all posts. This action cannot be undone.', 'post-export-import-with-media' ); ?></p>
						<button type="button" id="peiwm-delete-posts" class="button button-danger">
							<?php echo esc_html__( 'Delete All Posts', 'post-export-import-with-media' ); ?>
						</button>
						
						<div id="peiwm-delete-posts-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Delete Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
						</div>
					</div>
				</div>
				
				<!-- Media Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Media Export/Import', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-stats-section">
						<h3><?php echo esc_html__( 'Media Statistics', 'post-export-import-with-media' ); ?></h3>
						<div id="peiwm-media-stats" class="peiwm-stats">
							<div class="peiwm-stats-loader">
								<div class="peiwm-stats-loader-spinner"></div>
								<div class="peiwm-stats-loader-text"><?php echo esc_html__( 'Loading media statistics...', 'post-export-import-with-media' ); ?></div>
								<div class="peiwm-stats-loader-subtext"><?php echo esc_html__( 'Analyzing your media library', 'post-export-import-with-media' ); ?></div>
							</div>
						</div>
						<button type="button" id="peiwm-refresh-stats" class="button button-secondary">
							<?php echo esc_html__( 'Refresh Stats', 'post-export-import-with-media' ); ?>
						</button>
					</div>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Media', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Export all media files with their metadata as a ZIP file.', 'post-export-import-with-media' ); ?></p>
						
						<div style="margin: 15px 0;">
							<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
								<input type="checkbox" id="peiwm-export-all-image-sizes" value="1" style="margin-top: 2px;">
								<span style="font-size: 14px; line-height: 1.5;">
									<strong><?php echo esc_html__( 'Export all image sizes (thumbnails, medium, large)', 'post-export-import-with-media' ); ?></strong>
									<br>
									<span style="color: #666; font-size: 13px;">
										<?php echo esc_html__( 'Include all generated image size variations. Unchecked (default) exports only original files. WordPress will regenerate thumbnails on import.', 'post-export-import-with-media' ); ?>
									</span>
								</span>
							</label>
						</div>

						<?php
						$main_instance_media = PEIWM_Main::get_instance();
						$is_pro_media        = $main_instance_media->is_pro_active();
						?>

						<!-- Advanced Options Toggle for Media -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-export-media">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'Advanced options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel for Media Export -->
						<div class="peiwm-advanced-panel" id="peiwm-advanced-export-media" aria-hidden="true">

							<!-- PRO: Export by date range -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_media ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-media-export-daterange" <?php echo ! $is_pro_media ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export by date range', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_media ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Filter media by upload date range before exporting.', 'post-export-import-with-media' ); ?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_media ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- Date range UI for media (hidden until checkbox checked) -->
							<div id="peiwm-media-daterange-filter-ui" style="display:none; margin: 0.5rem 0 0.25rem 1.75rem;">
								<div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
									<label style="font-size:0.875rem; font-weight:500; white-space:nowrap;">
										<?php echo esc_html__( 'From', 'post-export-import-with-media' ); ?>
										<input type="date" id="peiwm-media-export-date-from"
										       max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
										       style="margin-left:0.4rem; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; font-size:0.875rem;">
									</label>
									<label style="font-size:0.875rem; font-weight:500; white-space:nowrap;">
										<?php echo esc_html__( 'To', 'post-export-import-with-media' ); ?>
										<input type="date" id="peiwm-media-export-date-to"
										       max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
										       style="margin-left:0.4rem; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; font-size:0.875rem;">
									</label>
								</div>
								<p id="peiwm-media-daterange-error" style="display:none; color:#dc2626; font-size:0.8rem; margin:0.35rem 0 0;"></p>
								<p id="peiwm-media-daterange-summary" style="display:none; color:#6b7280; font-size:0.8rem; margin:0.35rem 0 0;"></p>
							</div>

							<!-- PRO: Export media by post -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_media ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-media-export-by-post" <?php echo ! $is_pro_media ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export media by post', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_media ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Choose specific posts and export only the media attached to them.', 'post-export-import-with-media' ); ?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_media ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- Post selector panel for media (reuses posts-list style) -->
							<div id="peiwm-media-by-post-panel" style="display:none; margin: 0.5rem 0 0.5rem 1.75rem;">
								<div style="margin-bottom:0.5rem; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
									<input type="text" id="peiwm-media-post-search" placeholder="<?php echo esc_attr__( 'Search posts…', 'post-export-import-with-media' ); ?>" style="flex:1; min-width:160px; padding:5px 10px; border:1px solid #d1d5db; border-radius:4px; font-size:0.875rem;">
									<button type="button" id="peiwm-media-post-select-all" class="button button-secondary" style="font-size:0.8rem; padding:3px 10px;"><?php echo esc_html__( 'Select all', 'post-export-import-with-media' ); ?></button>
									<button type="button" id="peiwm-media-post-deselect-all" class="button button-secondary" style="font-size:0.8rem; padding:3px 10px;"><?php echo esc_html__( 'Deselect all', 'post-export-import-with-media' ); ?></button>
								</div>
								<div id="peiwm-media-post-list" style="max-height:240px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:4px; padding:0.4rem; background:#fff;">
									<p style="color:#9ca3af; font-size:0.85rem; margin:0.5rem;"><?php echo esc_html__( 'Loading posts…', 'post-export-import-with-media' ); ?></p>
								</div>
								<div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.35rem;">
									<span id="peiwm-media-post-selected-count" style="font-size:0.8rem; color:#6b7280;"></span>
									<span id="peiwm-media-post-load-more-wrap"></span>
								</div>
							</div>

						</div>
						<!-- /Advanced Panel for Media Export -->
						
						<button type="button" id="peiwm-export-media" class="button button-primary">
							<?php echo esc_html__( 'Export Media', 'post-export-import-with-media' ); ?>
						</button>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Media', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import media files from a previously exported ZIP file. Maximum file size: 500MB.', 'post-export-import-with-media' ); ?></p>
						<div class="button-container">
							<input type="file" id="peiwm-media-file" accept=".zip" multiple style="display: none;">
							<button type="button" id="peiwm-select-media-file" class="button button-secondary">
								<?php echo esc_html__( 'Select ZIP File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-media" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<div id="peiwm-media-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
					
					<div class="peiwm-delete-section">
						<h3><?php echo esc_html__( 'Delete Media', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( '⚠️ Warning: This will permanently delete all media files from the library. This action cannot be undone.', 'post-export-import-with-media' ); ?></p>
						<button type="button" id="peiwm-delete-media" class="button button-danger">
							<?php echo esc_html__( 'Delete All Media', 'post-export-import-with-media' ); ?>
						</button>
						
						<div id="peiwm-delete-media-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Delete Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>
				
				<!-- System Test Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'System Test', 'post-export-import-with-media' ); ?></h2>
					<p><?php echo esc_html__( 'Test your server configuration to ensure compatibility.', 'post-export-import-with-media' ); ?></p>
					<button type="button" id="peiwm-test-config" class="button button-secondary">
						<?php echo esc_html__( 'Test Configuration', 'post-export-import-with-media' ); ?>
					</button>
					<div id="peiwm-test-results" class="peiwm-test-results" style="display: none;"></div>
				</div>

				<!-- How to Use Section -->
				<div class="peiwm-section peiwm-how-to-use-section">
					<h2><?php echo esc_html__( 'How to Use This Plugin', 'post-export-import-with-media' ); ?></h2>
					<p><?php echo esc_html__( 'Follow these steps for successful export and import with all images intact:', 'post-export-import-with-media' ); ?></p>
					
					<div class="peiwm-workflow-steps">
						<div class="peiwm-workflow-step">
							<div class="peiwm-step-number">1</div>
							<div class="peiwm-step-content">

								<h4 style="margin:0 0 10px; font-size:16px; font-weight:600; color:#111827;">
									<?php echo esc_html__( 'Export Media Files', 'post-export-import-with-media' ); ?>
								</h4>

								<p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#374151;">
									<?php echo esc_html__( 'Start by exporting all your media files using the "Export Media" button above. This will create a ZIP file containing your images, videos, and documents.', 'post-export-import-with-media' ); ?>
								</p>

								<div style="background:#f8fafc; border-left:4px solid #3b82f6; padding:12px 14px; border-radius:6px;">
									<p style="margin:0; font-size:13px; line-height:1.6; color:#1f2937;">
										<strong style="color:#2563eb;">💡 Tip:</strong>
										<?php echo esc_html__( 'If your images are still available on the live site, you can enable "Download missing images from original URLs". This option downloads media directly from their original sources instead of the export file. Note that this may take more time. For faster imports, export the media first and keep this option unchecked.', 'post-export-import-with-media' ); ?>
									</p>
								</div>

							</div>

						</div>
						
						<div class="peiwm-workflow-step">
							<div class="peiwm-step-number">2</div>
							<div class="peiwm-step-content">
								<h4><?php echo esc_html__( 'Export Posts', 'post-export-import-with-media' ); ?></h4>
								<p><?php echo esc_html__( 'Next, export your posts using the "Export Posts" button. This creates a JSON file with all post content and image references.', 'post-export-import-with-media' ); ?></p>
							</div>
						</div>
						
						<div class="peiwm-workflow-step">
							<div class="peiwm-step-number">3</div>
							<div class="peiwm-step-content">
								<h4><?php echo esc_html__( 'Import Media First', 'post-export-import-with-media' ); ?></h4>
								<p><?php echo esc_html__( 'On your destination site, import the media ZIP file first. This ensures all images are available before importing posts.', 'post-export-import-with-media' ); ?></p>
							</div>
						</div>
						
						<div class="peiwm-workflow-step">
							<div class="peiwm-step-number">4</div>
							<div class="peiwm-step-content">
								<h4><?php echo esc_html__( 'Import Posts Last', 'post-export-import-with-media' ); ?></h4>
								<p><?php echo esc_html__( 'Finally, import the posts JSON file. The plugin will automatically link posts to the previously imported images.', 'post-export-import-with-media' ); ?></p>
							</div>
						</div>
					</div>
					
					<div class="peiwm-workflow-tips">
						<h4><?php echo esc_html__( 'Pro Tips', 'post-export-import-with-media' ); ?></h4>
						<ul>
							<li><?php echo esc_html__( '✓ Always import media before posts for complete image restoration', 'post-export-import-with-media' ); ?></li>
							<li><?php echo esc_html__( '✓ The plugin will show which images are missing during post import', 'post-export-import-with-media' ); ?></li>
							<li><?php echo esc_html__( '✓ Run the System Test first to ensure your server can handle large files', 'post-export-import-with-media' ); ?></li>
							<li><?php echo esc_html__( '✓ Existing images with the same filename will be reused automatically', 'post-export-import-with-media' ); ?></li>
							<li><?php echo esc_html__( '✓ If you enable "Download missing images from original URLs" and ensure the source site is live, media will be fetched directly from the original URLs. This means you won’t need to import media files separately, though the process may take additional time to download and complete.', 'post-export-import-with-media' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		
		<?php $this->render_modal_templates(); ?>
		<?php
	}

	/**
	 * Render pages page
	 */
	public function pages_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		?>
		<div class="wrap peiwm-admin">
			<h1 class='heading-admin'>
				<?php echo esc_html__( 'Pages Export/Import', 'post-export-import-with-media' ); ?>
				<a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
					<span class="dashicons dashicons-video-alt3"></span>
				</a>
			</h1>
			
			<div class="peiwm-container">
				<!-- Pages Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Pages Export/Import', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Pages', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Export all pages with their metadata, featured images, and hierarchy.', 'post-export-import-with-media' ); ?></p>

						<?php
						$main_instance_pexp = PEIWM_Main::get_instance();
						$is_pro_exp        = $main_instance_pexp->is_pro_active();
						?>

						<!-- Advanced Options Toggle Button -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-export-pages">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'Advanced options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel (Collapsible) -->
						<div class="peiwm-advanced-panel" id="peiwm-advanced-export-pages" aria-hidden="true">
							
							<!-- PRO Row: Export individually -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-pages-selective" <?php echo ! $is_pro_exp ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export individually (select specific pages)', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_exp ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Choose which pages to export instead of exporting all.', 'post-export-import-with-media' ); ?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_exp ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- PRO Row: Export ACF fields -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-pages-export-acf-fields" <?php echo ( ! $is_pro_exp || ! function_exists( 'get_fields' ) ) ? 'disabled' : ''; ?> <?php echo ! function_exists( 'get_fields' ) ? 'title="ACF plugin not active"' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export custom ACF meta fields', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_exp ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php
											if ( function_exists( 'get_fields' ) ) {
												echo esc_html__( 'Include Advanced Custom Fields data in the export with field keys.', 'post-export-import-with-media' );
											} else {
												echo esc_html__( 'ACF (Advanced Custom Fields) plugin is not active. Install and activate ACF to use this option.', 'post-export-import-with-media' );
											}
											?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_exp ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- Multilingual Support Row for Export (WPML & Polylang) -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_exp ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<?php
									$multilingual_active_pages = defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' );
									$multilingual_plugin_pages = defined( 'ICL_SITEPRESS_VERSION' ) ? 'WPML' : ( defined( 'POLYLANG_VERSION' ) ? 'Polylang' : '' );
									?>
									<input type="checkbox" id="peiwm-pages-export-wpml-data" <?php echo ( ! $is_pro_exp || ! $multilingual_active_pages ) ? 'disabled' : ''; ?> <?php echo ! $multilingual_active_pages ? 'title="Multilingual plugin not active"' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Export WPML multilingual language data', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_exp ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php
											if ( ! $is_pro_exp ) {
												echo esc_html__( 'Export language assignments with pages. Requires PRO version and WPML or Polylang plugin.', 'post-export-import-with-media' );
											} elseif ( $multilingual_active_pages ) {
												echo sprintf(
													esc_html__( 'Include %s language assignments in the export. Required for preserving multilingual structure on import.', 'post-export-import-with-media' ),
													esc_html( $multilingual_plugin_pages )
												);
											} else {
												echo esc_html__( 'Multilingual plugin (WPML or Polylang) is not active. Install and activate WPML or Polylang to use this option.', 'post-export-import-with-media' );
											}
											?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_exp ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

						</div><!-- /.peiwm-advanced-panel -->

						<!-- Selective Export Panel for Pages -->
						<div id="peiwm-pages-export-selective-panel" style="display: none; margin-top: 1rem;">
							<div class="peiwm-selective-panel">
								<div class="peiwm-selective-header">
									<h4><?php echo esc_html__( 'Select Pages to Export', 'post-export-import-with-media' ); ?></h4>
									<div class="peiwm-selective-controls">
										<input type="text" id="peiwm-pages-export-search" class="peiwm-selective-search" placeholder="<?php echo esc_attr__( 'Search pages...', 'post-export-import-with-media' ); ?>">
										<label class="peiwm-select-all-label">
											<input type="checkbox" id="peiwm-pages-export-select-all" checked>
											<?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?>
										</label>
									</div>
								</div>
								<div id="peiwm-pages-export-list" class="peiwm-selective-list">
									<div class="peiwm-selective-loading">
										<div class="peiwm-loading-spinner"></div>
										<p><?php echo esc_html__( 'Loading pages...', 'post-export-import-with-media' ); ?></p>
									</div>
								</div>
								<div class="peiwm-selective-footer">
									<span id="peiwm-pages-export-selected-count" class="peiwm-selected-count"><?php echo esc_html__( '0 selected', 'post-export-import-with-media' ); ?></span>
								</div>
							</div>
						</div>

						<button type="button" id="peiwm-export-pages" class="button button-primary">
							<?php echo esc_html__( 'Export Pages', 'post-export-import-with-media' ); ?>
						</button>

						<!-- PRO Toast -->
						<div class="peiwm-pro-toast" role="alert" aria-live="polite">
							<span class="peiwm-pro-toast__icon">🔒</span>
							<span class="peiwm-pro-toast__text">
								<?php echo esc_html__( 'This is a', 'post-export-import-with-media' ); ?> <strong><?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></strong> <?php echo esc_html__( 'feature. Upgrade to unlock it.', 'post-export-import-with-media' ); ?>
							</span>
							<a class="peiwm-pro-toast__cta button button-secondary peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media" target="_blank"><?php echo esc_html__( 'Learn more', 'post-export-import-with-media' ); ?> ↗</a>
							<button type="button" class="peiwm-pro-toast__close peiwm-pro-toast-close" aria-label="<?php echo esc_attr__( 'Close', 'post-export-import-with-media' ); ?>">×</button>
						</div>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Pages', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import pages from a previously exported JSON file.', 'post-export-import-with-media' ); ?></p>
						<div class="button-container">
							<input type="file" id="peiwm-pages-file" accept=".json" multiple style="display: none;">
							<button type="button" id="peiwm-select-pages-file" class="button button-secondary">
								<?php echo esc_html__( 'Select JSON File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-pages" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<!-- Free Options (Always Visible) -->
						<div class="peiwm-import-options" style="margin-top: 1rem;">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-check-media-library-pages" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Check media library for page images', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description">
										<?php echo esc_html__( 'Search for images in your media library before downloading. Uncheck for faster import if you plan to add images manually later.', 'post-export-import-with-media' ); ?>
									</small>
								</span>
							</label>
							
							<label class="peiwm-checkbox-label" style="margin-top: 0.5rem;">
								<input type="checkbox" id="peiwm-download-missing-page-images">
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Download missing images from original URLs', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description">
										<?php echo esc_html__( 'If images are not found in media library, try to download them from their original locations', 'post-export-import-with-media' ); ?>
									</small>
								</span>
							</label>
						</div>

						<?php
						$main_instance_pages = PEIWM_Main::get_instance();
						$is_pro_pages = $main_instance_pages->is_pro_active();
						?>

						<!-- Advanced Options Toggle Button -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-import-pages" style="margin-top: 0.75rem;">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'More options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel (Collapsible) -->
						<div class="peiwm-advanced-panel" id="peiwm-advanced-import-pages" aria-hidden="true">
							
							<!-- PRO Row: Import individually -->
							<div class="peiwm-inline-row <?php echo ! $is_pro_pages ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-import-pages-selective" <?php echo ! $is_pro_pages ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Import individually (select specific pages)', 'post-export-import-with-media' ); ?>
											<?php if ( ! $is_pro_pages ) : ?>
												<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
											<?php endif; ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Choose which pages to import from the file instead of importing all. Even you can change the status before import.', 'post-export-import-with-media' ); ?>
										</span>
									</span>
								</label>
								<?php if ( ! $is_pro_pages ) : ?>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- Multilingual Support Row (WPML & Polylang) - Now available for free users when multilingual plugin is active -->
							<div class="peiwm-inline-row">
								<label class="peiwm-checkbox-label">
									<?php
									$multilingual_active_pages_import = defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) || ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) );
									$multilingual_plugin_pages_import = defined( 'ICL_SITEPRESS_VERSION' ) ? 'WPML' : ( ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_languages_list' ) ) ? 'Polylang' : '' );
									?>
									<input type="checkbox" id="peiwm_enable_wpml_support_pages" name="peiwm_enable_wpml_support_pages" value="1" <?php echo ( ! $multilingual_active_pages_import ) ? 'disabled' : ''; ?> <?php echo get_option( 'peiwm_enable_wpml_support_pages' ) ? 'checked' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<strong>
											<?php echo esc_html__( 'Enable WPML multilingual language support', 'post-export-import-with-media' ); ?>
										</strong>
										<span class="peiwm-checkbox-description">
											<?php
											if ( $multilingual_active_pages_import ) {
												echo sprintf(
													esc_html__( 'Preserve %s language assignments when importing pages. Requires multilingual plugin to be active on both source and destination sites.', 'post-export-import-with-media' ),
													esc_html( $multilingual_plugin_pages_import )
												);
											} else {
												echo esc_html__( 'Multilingual plugin (WPML or Polylang) is not active. Install and activate WPML or Polylang to use this option.', 'post-export-import-with-media' );
											}
											?>
										</span>
									</span>
								</label>
							</div>

						</div><!-- /.peiwm-advanced-panel -->

						<!-- Selective Import Panel -->
						<div id="peiwm-pages-selective-panel" style="display: none; margin-top: 1rem;">
							<div class="peiwm-selective-panel">
								<div class="peiwm-selective-header">
									<h4><?php echo esc_html__( 'Select Pages to Import', 'post-export-import-with-media' ); ?></h4>
									<div class="peiwm-selective-controls">
										<input type="text" id="peiwm-pages-search" class="peiwm-selective-search" placeholder="<?php echo esc_attr__( 'Search pages...', 'post-export-import-with-media' ); ?>">
										<label class="peiwm-select-all-label">
											<input type="checkbox" id="peiwm-pages-select-all">
											<?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?>
										</label>
									</div>
								</div>
								<div id="peiwm-pages-list" class="peiwm-selective-list">
									<p class="peiwm-selective-empty">👆 <?php echo esc_html__( 'Select a JSON file above to load pages for selection.', 'post-export-import-with-media' ); ?></p>
								</div>
								<div class="peiwm-selective-footer">
									<span id="peiwm-pages-selected-count" class="peiwm-selected-count"><?php echo esc_html__( '0 selected', 'post-export-import-with-media' ); ?></span>
								</div>
							</div>
						</div>
						
						<div id="peiwm-pages-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>

						<!-- PRO Toast -->
						<div class="peiwm-pro-toast" role="alert" aria-live="polite">
							<span class="peiwm-pro-toast__icon">🔒</span>
							<span class="peiwm-pro-toast__text">
								<?php echo esc_html__( 'This is a', 'post-export-import-with-media' ); ?> <strong><?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></strong> <?php echo esc_html__( 'feature. Upgrade to unlock it.', 'post-export-import-with-media' ); ?>
							</span>
							<a class="peiwm-pro-toast__cta button button-secondary peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media" target="_blank"><?php echo esc_html__( 'Learn more', 'post-export-import-with-media' ); ?> ↗</a>
							<button type="button" class="peiwm-pro-toast__close peiwm-pro-toast-close" aria-label="<?php echo esc_attr__( 'Close', 'post-export-import-with-media' ); ?>">×</button>
						</div>
					</div>
					
					<div class="peiwm-delete-section">
						<h3><?php echo esc_html__( 'Delete All Pages', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Permanently delete all pages from your website. This action cannot be undone.', 'post-export-import-with-media' ); ?></p>
						<button type="button" id="peiwm-delete-pages" class="button button-secondary peiwm-danger-button">
							<?php echo esc_html__( 'Delete All Pages', 'post-export-import-with-media' ); ?>
						</button>
						
						<div id="peiwm-delete-pages-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Deletion Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<?php $this->render_modal_templates(); ?>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		?>
		<div class="wrap peiwm-admin">
			<h1 class='heading-admin'>
				<?php echo esc_html__( 'WordPress Settings Export/Import', 'post-export-import-with-media' ); ?>
				<a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
					<span class="dashicons dashicons-video-alt3"></span>
				</a>
			</h1>
			
			<div class="peiwm-container">
				<!-- Settings Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'WordPress Settings Export/Import', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Settings', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Export WordPress configuration settings from General, Writing, Reading, Discussion, Media, Permalinks, and Privacy sections.', 'post-export-import-with-media' ); ?></p>
						
						<div class="peiwm-settings-groups">
							<h4><?php echo esc_html__( 'Select Settings Groups to Export:', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-checkbox-grid">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="general" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'General Settings', 'post-export-import-with-media' ); ?></span>
								</label>
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="writing" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Writing Settings', 'post-export-import-with-media' ); ?></span>
								</label>
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="reading" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Reading Settings', 'post-export-import-with-media' ); ?></span>
								</label>
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="discussion" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Discussion Settings', 'post-export-import-with-media' ); ?></span>
								</label>
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="media" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Media Settings', 'post-export-import-with-media' ); ?></span>
								</label>
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="permalinks" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Permalink Settings', 'post-export-import-with-media' ); ?></span>
								</label>
								<label class="peiwm-checkbox-label">
									<input type="checkbox" name="export_settings_groups[]" value="privacy" checked>
									<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Privacy Settings', 'post-export-import-with-media' ); ?></span>
								</label>
							</div>
						</div>
						
						<button type="button" id="peiwm-export-settings" class="button button-primary">
							<?php echo esc_html__( 'Export Settings', 'post-export-import-with-media' ); ?>
						</button>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Settings', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import WordPress settings from a previously exported JSON file.', 'post-export-import-with-media' ); ?></p>
						<div class="button-container">
							<input type="file" id="peiwm-settings-file" accept=".json" style="display: none;">
							<button type="button" id="peiwm-select-settings-file" class="button button-secondary">
								<?php echo esc_html__( 'Select JSON File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-settings" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<div id="peiwm-settings-preview" class="peiwm-settings-preview" style="display: none;">
							<h4><?php echo esc_html__( 'Settings Preview & Selection:', 'post-export-import-with-media' ); ?></h4>
							<div id="peiwm-settings-groups-selection"></div>
						</div>
						
						<div id="peiwm-settings-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>

				<!-- Widgets & Menus Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Widgets & Navigation Menus', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Widgets & Menus', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Export your widgets and navigation menus configuration.', 'post-export-import-with-media' ); ?></p>
						
						<div class="peiwm-export-options">
							<button type="button" id="peiwm-export-widgets" class="button button-primary">
								<?php echo esc_html__( 'Export Widgets Only', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-export-nav-menus" class="button button-primary">
								<?php echo esc_html__( 'Export Menus Only', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-export-widgets-menus" class="button button-primary">
								<?php echo esc_html__( 'Export Both', 'post-export-import-with-media' ); ?>
							</button>
						</div>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Widgets & Menus', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import widgets and navigation menus from a previously exported JSON file.', 'post-export-import-with-media' ); ?></p>
						
						<div class="button-container">
							<input type="file" id="peiwm-widgets-menus-file" accept=".json" style="display: none;">
							<button type="button" id="peiwm-select-widgets-menus-file" class="button button-secondary">
								<?php echo esc_html__( 'Select JSON File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-widgets-menus" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<div class="peiwm-import-options" id="peiwm-widgets-menus-import-options" style="display: none;">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-replace-existing-widgets-menus" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Replace existing widgets and menus', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Clear existing widgets and menus before importing', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
						</div>
						
						<div id="peiwm-widgets-menus-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<?php $this->render_modal_templates(); ?>
		<?php
	}

	/**
	 * Render themes & plugins page
	 */
	public function themes_plugins_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}
		?>
		<div class="wrap peiwm-admin">
			<h1 class='heading-admin'>
				<?php echo esc_html__( 'WP Themes & Plugins Toolkit', 'post-export-import-with-media' ); ?>
				<a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
					<span class="dashicons dashicons-video-alt3"></span>
				</a>
			</h1>
			
			<div class="peiwm-container">
				<!-- Themes Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Themes Backup', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Themes', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Create a backup of your themes as a ZIP file.', 'post-export-import-with-media' ); ?></p>
						
						<div class="peiwm-theme-options">
							<label class="peiwm-checkbox-label">
								<input type="radio" name="theme_export_type" value="active" checked>
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Active Theme Only', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="radio" name="theme_export_type" value="all">
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'All Installed Themes', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="radio" name="theme_export_type" value="selected">
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Selected Themes', 'post-export-import-with-media' ); ?></span>
							</label>
						</div>
						
						<div id="peiwm-theme-selection" class="peiwm-selection-grid" style="display: none;">
							<!-- Theme selection will be populated by JavaScript -->
						</div>
						
						<button type="button" id="peiwm-export-themes" class="button button-primary">
							<?php echo esc_html__( 'Export Themes', 'post-export-import-with-media' ); ?>
						</button>
						
						<div id="peiwm-themes-export-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Export Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Themes', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import themes from a previously exported ZIP file.', 'post-export-import-with-media' ); ?></p>
						
						<div class="button-container">
							<input type="file" id="peiwm-themes-file" accept=".zip" style="display: none;">
							<button type="button" id="peiwm-select-themes-file" class="button button-secondary">
								<?php echo esc_html__( 'Select ZIP File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-themes" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<div class="peiwm-import-options" id="peiwm-themes-import-options" style="display: none;">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-replace-existing-themes" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Replace existing themes', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Overwrite themes that already exist on this site', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
							<label class="peiwm-checkbox-label" style="margin-top:0.4rem;">
								<input type="checkbox" id="peiwm-skip-existing-themes">
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Skip if already present', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Skip themes that already exist — do not overwrite', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
							<label class="peiwm-checkbox-label" style="margin-top:0.4rem;">
								<input type="checkbox" id="peiwm-activate-imported-theme">
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Activate imported theme', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Switch to the first imported theme after import', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
						</div>
						
						<div id="peiwm-themes-import-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>

				<!-- Plugins Section -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Plugins Backup', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-export-section">
						<h3><?php echo esc_html__( 'Export Plugins', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Create a backup of your plugins as a ZIP file.', 'post-export-import-with-media' ); ?></p>
						
						<div class="peiwm-plugin-options">
							<label class="peiwm-checkbox-label">
								<input type="radio" name="plugin_export_type" value="active" checked>
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Active Plugins Only', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="radio" name="plugin_export_type" value="all">
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'All Installed Plugins', 'post-export-import-with-media' ); ?></span>
							</label>
							<label class="peiwm-checkbox-label">
								<input type="radio" name="plugin_export_type" value="selected">
								<span class="peiwm-checkbox-text"><?php echo esc_html__( 'Selected Plugins', 'post-export-import-with-media' ); ?></span>
							</label>
						</div>
						
						<div id="peiwm-plugin-selection" class="peiwm-selection-grid" style="display: none;">
							<!-- Plugin selection will be populated by JavaScript -->
						</div>
						
						<button type="button" id="peiwm-export-plugins" class="button button-primary">
							<?php echo esc_html__( 'Export Plugins', 'post-export-import-with-media' ); ?>
						</button>
						
						<div id="peiwm-plugins-export-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Export Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
					
					<div class="peiwm-import-section">
						<h3><?php echo esc_html__( 'Import Plugins', 'post-export-import-with-media' ); ?></h3>
						<p><?php echo esc_html__( 'Import plugins from a previously exported ZIP file.', 'post-export-import-with-media' ); ?></p>
						
						<div class="button-container">
							<input type="file" id="peiwm-plugins-file" accept=".zip" style="display: none;">
							<button type="button" id="peiwm-select-plugins-file" class="button button-secondary">
								<?php echo esc_html__( 'Select ZIP File', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-import-plugins" class="button button-primary" style="display: none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>
						
						<div class="peiwm-import-options" id="peiwm-plugins-import-options" style="display: none;">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-replace-existing-plugins" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Replace existing plugins', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Overwrite plugins that already exist on this site', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
							<label class="peiwm-checkbox-label" style="margin-top:0.4rem;">
								<input type="checkbox" id="peiwm-skip-existing-plugins">
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Skip if already present', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Skip plugins that already exist — do not overwrite', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
							<label class="peiwm-checkbox-label" style="margin-top:0.4rem;">
								<input type="checkbox" id="peiwm-activate-imported-plugins">
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Activate imported plugins', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Automatically activate plugins after import', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
						</div>
						
						<div id="peiwm-plugins-import-progress" class="peiwm-progress" style="display: none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar">
								<div class="peiwm-progress-fill"></div>
							</div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting...', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>

				<!-- Settings Section -->
				<div class="peiwm-section" style="grid-column: 1 / -1;">
					<h2><?php echo esc_html__( 'Settings', 'post-export-import-with-media' ); ?></h2>
					
					<div class="peiwm-settings-section">
						<form method="post" action="options.php">
							<?php settings_fields( 'peiwm_admin_download_buttons' ); ?>
							<?php settings_fields( 'peiwm_settings' ); ?>

							<?php
							// Declare once — shared by all locked sections in this form
							$main_instance = PEIWM_Main::get_instance();
							$is_pro        = $main_instance->is_pro_active();
							$locked        = ! $is_pro ? ' peiwm-locked-section' : '';
							?>

							<!-- Admin Download Buttons -->
							<div class="download-btn-active">
								<h4><?php echo esc_html__( 'Admin Download Buttons', 'post-export-import-with-media' ); ?></h4>
								<label>
									<input type="checkbox" name="peiwm_enable_admin_download_buttons" value="1" <?php checked( get_option( 'peiwm_enable_admin_download_buttons', false ) ); ?>>
									<?php echo esc_html__( 'Add download buttons to WordPress Themes and Plugins pages', 'post-export-import-with-media' ); ?>
								</label>
								<p class="description">
									<?php echo esc_html__( 'When enabled, download buttons will appear on the Appearance > Themes and Plugins pages, allowing you to download individual themes and plugins as ZIP files.', 'post-export-import-with-media' ); ?>
								</p>
							</div>

							<!-- Allowed Media File Types -->
							<div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
								<h4><?php echo esc_html__( 'Allowed Media File Types', 'post-export-import-with-media' ); ?></h4>
								<p><?php echo esc_html__( 'Define which file types are allowed during media import. Files with extensions not in this list will be blocked for security.', 'post-export-import-with-media' ); ?></p>

								<table class="form-table" role="presentation">
									<tr>
										<th scope="row">
											<?php echo esc_html__( 'File type validation', 'post-export-import-with-media' ); ?>
										</th>
										<td>
											<div class="<?php echo esc_attr( $locked ); ?>" style="position: relative; display: inline-block;">
												<?php if ( ! $is_pro ) : ?>
													<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
														<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
													</button>
												<?php endif; ?>
												<label>
													<input type="checkbox"
														name="peiwm_allow_all_file_types"
														value="1"
														<?php checked( get_option( 'peiwm_allow_all_file_types', false ) ); ?>
														<?php echo ! $is_pro ? 'disabled' : ''; ?>>
													<?php echo esc_html__( 'Allow all file types (bypass validation)', 'post-export-import-with-media' ); ?>
												</label>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="peiwm_allowed_media_file_types">
												<?php echo esc_html__( 'Allowed file extensions', 'post-export-import-with-media' ); ?>
											</label>
										</th>
										<td>
											<div class="<?php echo esc_attr( $locked ); ?>" style="position: relative; display: inline-block; width: 100%;">
												<?php if ( ! $is_pro ) : ?>
													<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
														<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
													</button>
												<?php endif; ?>
												<input type="text"
													id="peiwm_allowed_media_file_types"
													name="peiwm_allowed_media_file_types"
													value="<?php echo esc_attr( get_option( 'peiwm_allowed_media_file_types', 'jpg,jpeg,png,gif,webp,svg,json,pdf,mp4,mp3,wav,doc,docx,txt' ) ); ?>"
													class="large-text"
													placeholder="jpg,jpeg,png,gif,webp,svg,json,pdf,mp4,mp3,wav,doc,docx,txt"
													<?php echo ! $is_pro ? 'disabled' : ''; ?>>
											</div>
											<p class="description">
												<?php echo esc_html__( 'Enter file extensions separated by commas (e.g., jpg,png,pdf,ods,mov). Common additions: ods (spreadsheets), mov (videos), xlsx, pptx, zip.', 'post-export-import-with-media' ); ?>
											</p>
										</td>
									</tr>
								</table>
							</div>

							<!-- User Import Defaults -->
							<div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
								<h4><?php echo esc_html__( 'User Import Defaults', 'post-export-import-with-media' ); ?></h4>
								<p><?php echo esc_html__( "Set a default password during post import. If 'Automatically create missing users' is enabled, a welcome email will be sent when a new account is created.", 'post-export-import-with-media' ); ?></p>

								<table class="form-table" role="presentation">
									<tr class="peiwm-user-import-defaults">
										<th scope="row">
											<label for="peiwm_user_import_default_password">
												<?php echo esc_html__( 'Default password for imported users', 'post-export-import-with-media' ); ?>
											</label>
										</th>
										<td>
											<div class="<?php echo esc_attr( $locked ); ?>" style="position: relative; display: inline-block;">
												<?php if ( ! $is_pro ) : ?>
													<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
														<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
													</button>
												<?php endif; ?>
												<input type="text"
													id="peiwm_user_import_default_password"
													name="peiwm_user_import_default_password"
													value="<?php echo esc_attr( get_option( 'peiwm_user_import_default_password', '' ) ); ?>"
													class="regular-text"
													<?php echo ! $is_pro ? 'disabled' : ''; ?>>
											</div>
											<p class="description">
												<?php echo esc_html__( 'Leave blank to auto-generate a secure password per user.', 'post-export-import-with-media' ); ?>
											</p>
											<p class="description-hint">
												<?php echo esc_html__( 'If no password hash exists in the import data and no default password is set, a secure password will be generated automatically for each user.', 'post-export-import-with-media' ); ?>
											</p>
										</td>
									</tr>
									<tr class="peiwm-user-import-defaults">
										<th scope="row">
											<?php echo esc_html__( 'Welcome email', 'post-export-import-with-media' ); ?>
										</th>
										<td>
											<div class="<?php echo esc_attr( $locked ); ?>" style="position: relative; display: flex; align-items: flex-start; gap: 0.5rem;">
												<?php if ( ! $is_pro ) : ?>
													<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
														<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
													</button>
												<?php endif; ?>
												<label>
													<input type="checkbox"
														name="peiwm_user_import_send_email"
														value="1"
														<?php checked( get_option( 'peiwm_user_import_send_email', false ) ); ?>
														<?php echo ! $is_pro ? 'disabled' : ''; ?>>
													<?php echo esc_html__( 'Send welcome email to imported users by default', 'post-export-import-with-media' ); ?>
												</label>
											</div>
										</td>
									</tr>
								</table>
							</div>

							<?php submit_button(); ?>
						</form>
					</div>
				</div>

				<div class="peiwm-section peiwm-faq-section" id="peiwm-faq-section" style="grid-column: 1 / -1;">
					<h2>
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
							stroke="#e67428" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="12" cy="12" r="10"/>
							<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
							<circle cx="12" cy="17" r=".5" fill="#e67428"/>
						</svg>
						<?php esc_html_e( 'Frequently Asked Questions', 'post-export-import-with-media' ); ?>
					</h2>

					<!-- Search -->
					<div class="peiwm-faq-search">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
						</svg>
						<input type="text"
							id="peiwm-faq-search"
							placeholder="<?php esc_attr_e( 'Search questions…', 'post-export-import-with-media' ); ?>"
							aria-label="<?php esc_attr_e( 'Search FAQ', 'post-export-import-with-media' ); ?>"
							autocomplete="off">
					</div>

					<!-- Category tabs -->
					<div class="peiwm-faq-tabs" role="tablist">
						<button class="peiwm-faq-tab active" data-cat="all"       role="tab" aria-selected="true">
							<?php esc_html_e( 'All', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="posts"            role="tab" aria-selected="false">
							<?php esc_html_e( 'Posts', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="media"            role="tab" aria-selected="false">
							<?php esc_html_e( 'Media', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="pages"            role="tab" aria-selected="false">
							<?php esc_html_e( 'Pages', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="settings"         role="tab" aria-selected="false">
							<?php esc_html_e( 'Settings & Widgets', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="themes"           role="tab" aria-selected="false">
							<?php esc_html_e( 'Themes & Plugins', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="cpt"              role="tab" aria-selected="false">
							<?php esc_html_e( 'CPT & ACF', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="users"            role="tab" aria-selected="false">
							<?php esc_html_e( 'Users', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="batch"            role="tab" aria-selected="false">
							<?php esc_html_e( 'Batch & Scheduled', 'post-export-import-with-media' ); ?>
						</button>
						<button class="peiwm-faq-tab" data-cat="system"           role="tab" aria-selected="false">
							<?php esc_html_e( 'System & Email', 'post-export-import-with-media' ); ?>
						</button>
					</div>

					<p class="peiwm-faq-count" id="peiwm-faq-count"></p>

					<div class="peiwm-faq-list" id="peiwm-faq-list"></div>
				</div>
				
			</div>
		</div>
		
		<?php $this->render_modal_templates(); ?>
		<?php
	}

	/**
	 * Render recommendations page
	 */
	public function recommendations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		?>
		<div class="wrap peiwm-recommendations-wrap">
			<div class="peiwm-recommendations-header">
				<h1>🚀 Recommended Plugins</h1>
				<h2>Add Some (Mostly Free) Freemius Plugins to Your Toolkit</h2>
				<p class="subtitle">
					<?php echo esc_html__( 'Discover powerful plugins to enhance your WordPress experience and boost your site\'s functionality', 'post-export-import-with-media' ); ?>
				</p>
			</div>

			<div class="peiwm-recommendations-content">
				<div id="peiwm-recommendations-container">
					<!-- Content will be loaded via AJAX -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Users Export/Import page (Part 6)
	 */
	public function users_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}
		?>
		<div class="wrap peiwm-admin">
			<h1>
				<?php echo esc_html__( 'Users Export/Import', 'post-export-import-with-media' ); ?>
				<a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
					<span class="dashicons dashicons-video-alt3"></span>
				</a>
			</h1>

			<div class="peiwm-container">

				<!-- Export Users -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Export Users', 'post-export-import-with-media' ); ?></h2>
					<p><?php echo esc_html__( 'Export all WordPress users to a JSON file.', 'post-export-import-with-media' ); ?></p>

					<?php
					$ue_is_pro   = PEIWM_Main::get_instance()->is_pro_active();
					$ue_woo      = class_exists( 'WooCommerce' );
					$ue_acf      = function_exists( 'get_fields' );
					?>

					<div class="peiwm-export-options" style="margin-bottom: 1rem;">

						<!-- Basic info — always included -->
						<label class="peiwm-checkbox-label peiwm-option-locked">
							<input type="checkbox" checked disabled>
							<span class="peiwm-checkbox-text">
								<?php echo esc_html__( 'Necessary Info (always included)', 'post-export-import-with-media' ); ?>
								<small class="peiwm-checkbox-description">
									<?php echo esc_html__( 'user_login, user_email, display_name, user_registered, user_nicename, user_url, user_status, locale, roles', 'post-export-import-with-media' ); ?>
								</small>
							</span>
						</label>

						<!-- Advanced Options Toggle -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-export-users">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'Advanced options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel -->
						<div class="peiwm-advanced-panel" id="peiwm-advanced-export-users" aria-hidden="true">

							<!-- Password hash — PRO -->
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="export-password">
								
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-password" <?php echo ! $ue_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Password (hashed)', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Include encrypted password hash so users can log in immediately after import.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								
								<?php if ( ! $ue_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- SECURITY WARNING for password export -->
							<?php if ( $ue_is_pro ) : ?>
							<div id="peiwm-password-warning" style="display: none; margin: -4px 0 12px 24px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
								<p style="margin: 0; color: #856404; font-size: 13px;">
									<strong>⚠️ <?php echo esc_html__( 'Security Warning:', 'post-export-import-with-media' ); ?></strong>
									<?php echo esc_html__( 'Exporting password hashes allows users to login on the destination site with their current passwords. Only enable this if you trust the destination site and will secure the export file. If the export file is compromised, all user passwords are at risk of offline brute force attacks.', 'post-export-import-with-media' ); ?>
								</p>
							</div>
							<?php endif; ?>

							<!-- User Meta & Capabilities — PRO -->
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="export-meta">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-meta" <?php echo ! $ue_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'User Meta & Capabilities', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Custom capabilities, meta_capabilities, plugin-stored role data.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								<?php if ( ! $ue_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<!-- WooCommerce — PRO + WC active -->
							<?php if ( $ue_woo ) : ?>
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="export-woocommerce">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-woocommerce" <?php echo ! $ue_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'WooCommerce Data', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'billing_address, shipping_address, wc_last_active.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								<?php if ( ! $ue_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>
							<?php endif; ?>

							<!-- ACF User Fields — PRO + ACF active -->
							<?php if ( $ue_acf ) : ?>
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="export-acf">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-acf" <?php echo ! $ue_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'ACF User Fields', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'All Advanced Custom Fields attached to user profiles.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								<?php if ( ! $ue_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>
							<?php endif; ?>

							<!-- CPT Authorship — PRO -->
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="export-cpt">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-export-cpt" <?php echo ! $ue_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Custom Post Type Authorship', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Export which CPTs this user authored, for remapping on import.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								<?php if ( ! $ue_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal"  href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

						</div>

					</div>

					<button type="button" id="peiwm-export-users" class="button button-primary">
						<?php echo esc_html__( 'Export Users', 'post-export-import-with-media' ); ?>
					</button>

					<div id="peiwm-users-export-result" style="margin-top: 1rem; display: none;"></div>

					<!-- PRO Toast Notification -->
					<div class="peiwm-pro-toast" role="alert" aria-live="polite">
						<span class="peiwm-pro-toast__icon">🔒</span>
						<span class="peiwm-pro-toast__text">
							<?php echo esc_html__( 'This is a', 'post-export-import-with-media' ); ?> <strong><?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></strong> <?php echo esc_html__( 'feature. Upgrade to unlock it.', 'post-export-import-with-media' ); ?>
						</span>
						<a class="peiwm-pro-toast__cta button button-secondary peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media" target="_blank"><?php echo esc_html__( 'Learn more', 'post-export-import-with-media' ); ?> ↗</a>
						<button type="button" class="peiwm-pro-toast__close peiwm-pro-toast-close" aria-label="<?php echo esc_attr__( 'Close', 'post-export-import-with-media' ); ?>">×</button>
					</div>
				</div>

				<!-- Import Users -->
				<div class="peiwm-section">
					<h2><?php echo esc_html__( 'Import Users', 'post-export-import-with-media' ); ?></h2>
					<p><?php echo esc_html__( 'Import users from a previously exported JSON file. Existing users (matched by login or email) are skipped.', 'post-export-import-with-media' ); ?></p>

					<div class="button-container">
						<input type="file" id="peiwm-users-file" accept=".json" style="display: none;">
						<button type="button" id="peiwm-users-select-file" class="button button-secondary">
							<?php echo esc_html__( 'Choose File', 'post-export-import-with-media' ); ?>
						</button>
						<button type="button" id="peiwm-import-users" class="button button-primary" style="display: none;">
							<?php echo esc_html__( 'Import Users', 'post-export-import-with-media' ); ?>
						</button>
					</div>

					<?php
					$users_is_pro = PEIWM_Main::get_instance()->is_pro_active();
					?>

					<div class="peiwm-import-options" style="margin-top: 1rem;">
						<!-- Advanced Options Toggle -->
						<button type="button" class="peiwm-advanced-toggle" aria-expanded="false" aria-controls="peiwm-advanced-import-users">
							<svg class="peiwm-advanced-toggle__gear" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
								<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
							</svg>
							<span><?php echo esc_html__( 'Advanced options', 'post-export-import-with-media' ); ?></span>
							<svg class="peiwm-advanced-toggle__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<polyline points="6 9 12 15 18 9"/>
							</svg>
						</button>

						<!-- Advanced Panel -->
						<div id="peiwm-advanced-import-users" class="peiwm-advanced-panel" aria-hidden="true">

							<!-- Set default password — PRO -->
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="users-set-password">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-users-set-password" <?php echo ! $users_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Set a default password for all imported users', 'post-export-import-with-media' ); ?>
									</span>
								</label>
								<?php if ( ! $users_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>

							<div id="peiwm-users-password-wrap <?php echo ! $ue_is_pro ? ' is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" style="display: none; margin: -4px 0 6px 24px;">
								<input type="text"
									id="peiwm-users-default-password"
									class="regular-text"
									placeholder="<?php echo esc_attr__( 'Enter default password', 'post-export-import-with-media' ); ?>">
								<p class="description">
									<?php echo esc_html__( 'Leave blank to auto-generate a secure password per user.', 'post-export-import-with-media' ); ?>
								</p>
							</div>

							<!-- Preserve original user IDs — PRO -->
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="users-force-id">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-users-force-id" <?php echo ! $users_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Try to preserve original user IDs', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'Works only if the ID is not already taken on this site. Conflicts are logged in the import summary.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								<?php if ( ! $users_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>
							</div>


							<!-- Send welcome email — PRO -->
							
							<div class="peiwm-inline-row <?php echo ! $ue_is_pro ? 'peiwm-pro-inline-row is-locked peiwm-locked-section peiwm-open-premium-modal' : ''; ?>" data-pro-feature="users-send-email">
								<label class="peiwm-checkbox-label">
									<input type="checkbox" id="peiwm-users-send-email" <?php echo ! $users_is_pro ? 'disabled' : ''; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Send welcome email with login credentials to imported users', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description">
											<?php echo esc_html__( 'If your server email is not configured, this will be silently skipped and noted in the import summary.', 'post-export-import-with-media' ); ?>
										</small>
									</span>
								</label>
								
								<?php if ( ! $users_is_pro ) : ?>
									<span class="peiwm-pro-inline-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									<a class="peiwm-pro-upgrade-link peiwm-open-premium-modal" href="https://wpazleen.com/post-export-import-with-media-pricing/" target="_blank"><?php echo esc_html__( 'Upgrade', 'post-export-import-with-media' ); ?> ↗</a>
								<?php endif; ?>

							</div>

						</div>

					</div>

					<div id="peiwm-users-import-result" style="margin-top: 1rem; display: none;"></div>

					<!-- PRO Toast Notification -->
					<!-- <div class="peiwm-pro-toast" id="peiwm-pro-toast-import-users" style="display: none;">
						<div class="peiwm-pro-toast-content">
							<svg class="peiwm-pro-toast-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM11 15H9V13H11V15ZM11 11H9V5H11V11Z" fill="currentColor"/>
							</svg>
							<div class="peiwm-pro-toast-text">
								<strong><?php echo esc_html__( 'PRO Feature', 'post-export-import-with-media' ); ?></strong>
								<p><?php echo esc_html__( 'Upgrade to unlock advanced import options', 'post-export-import-with-media' ); ?></p>
							</div>
							<a href="#" class="peiwm-pro-toast-cta peiwm-open-premium-modal"><?php echo esc_html__( 'Upgrade Now', 'post-export-import-with-media' ); ?></a>
						</div>
						<button type="button" class="peiwm-pro-toast-close" aria-label="<?php echo esc_attr__( 'Close', 'post-export-import-with-media' ); ?>">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M1 1L13 13M1 13L13 1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</button>
					</div> -->
				</div>

			</div>
		</div>

		<style>
		.peiwm-users-summary-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 1.25rem 1.5rem;
			max-width: 480px;
		}
		.peiwm-users-summary-card h4 {
			margin: 0 0 0.75rem;
			font-size: 1rem;
			font-weight: 600;
		}
		.peiwm-users-summary-table {
			width: 100%;
			border-collapse: collapse;
		}
		.peiwm-users-summary-table td {
			padding: 4px 8px 4px 0;
			vertical-align: top;
		}
		.peiwm-users-summary-table td:first-child {
			width: 24px;
		}
		.peiwm-users-summary-table td:last-child {
			text-align: right;
		}
		</style>

		<?php $this->render_modal_templates(); ?>
		<?php
	}

	/**
	 * Render email template settings page
	 */
	public function email_template_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		$is_pro = PEIWM_Main::get_instance()->is_pro_active();

		if ( $is_pro && defined( 'PEIWM_PRO_PLUGIN_PATH' ) && file_exists( PEIWM_PRO_PLUGIN_PATH . 'includes/email-template-page-pro.php' ) ) {
			require_once PEIWM_PRO_PLUGIN_PATH . 'includes/email-template-page-pro.php';
		} else {
			require_once PEIWM_PLUGIN_PATH . 'includes/email-template-page.php';
		}
	}

	/**
	 * Render modal templates
	 */
	private function render_modal_templates() {		?>
		<!-- Confirmation Modal -->
		<div id="peiwm-modal-overlay" class="peiwm-modal-overlay" style="display: none;">
			<div class="peiwm-modal">
				<div class="peiwm-modal-header">
					<h3 id="peiwm-modal-title"><?php echo esc_html__( 'Confirmation', 'post-export-import-with-media' ); ?></h3>
					<button type="button" class="peiwm-modal-close">&times;</button>
				</div>
				<div class="peiwm-modal-body">
					<p id="peiwm-modal-message"><?php echo esc_html__( 'Are you sure you want to proceed?', 'post-export-import-with-media' ); ?></p>
				</div>
				<div class="peiwm-modal-footer">
					<button type="button" id="peiwm-modal-cancel" class="button button-secondary">
						<?php echo esc_html__( 'Cancel', 'post-export-import-with-media' ); ?>
					</button>
					<button type="button" id="peiwm-modal-confirm" class="button button-danger">
						<?php echo esc_html__( 'Confirm', 'post-export-import-with-media' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<!-- Success Modal -->
		<div id="peiwm-success-modal" class="peiwm-modal-overlay" style="display: none;">
			<div class="peiwm-modal peiwm-success-modal">
				<div class="peiwm-modal-header">
					<h3><?php echo esc_html__( 'Success!', 'post-export-import-with-media' ); ?></h3>
					<button type="button" class="peiwm-modal-close">&times;</button>
				</div>
				<div class="peiwm-modal-body">
					<div class="peiwm-success-icon">✓</div>
					<p id="peiwm-success-message"><?php echo esc_html__( 'Operation completed successfully!', 'post-export-import-with-media' ); ?></p>
				</div>
				<div class="peiwm-modal-footer">
					<button type="button" class="peiwm-modal-close button button-primary">
						<?php echo esc_html__( 'OK', 'post-export-import-with-media' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<!-- Error Modal -->
		<div id="peiwm-error-modal" class="peiwm-modal-overlay" style="display: none;">
			<div class="peiwm-modal peiwm-error-modal">
				<div class="peiwm-modal-header">
					<h3><?php echo esc_html__( 'Error', 'post-export-import-with-media' ); ?></h3>
					<button type="button" class="peiwm-modal-close">&times;</button>
				</div>
				<div class="peiwm-modal-body">
					<div class="peiwm-error-icon">✗</div>
					<p id="peiwm-error-message"><?php echo esc_html__( 'An error occurred.', 'post-export-import-with-media' ); ?></p>
				</div>
				<div class="peiwm-modal-footer">
					<button type="button" class="peiwm-modal-close button button-secondary">
						<?php echo esc_html__( 'Close', 'post-export-import-with-media' ); ?>
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
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Selective Export & Import', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Scheduled Automatic Exports', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Batch Processing (100K+ posts)', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Import Status Override', 'post-export-import-with-media' ); ?></div>
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
	
	/**
	 * CPT & ACF page — Pro users see the full export/import UI, Free users see it with Pro overlay
	 */
	public function cpt_acf_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$is_pro = PEIWM_Main::get_instance()->is_pro_active();
		$locked_class = ! $is_pro ? ' peiwm-locked-section' : '';
		?>
		<div class="wrap peiwm-admin">
			<h1 class='heading-admin'>
				<?php echo esc_html__( 'CPT & ACF Export/Import', 'post-export-import-with-media' ); ?>
				<a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
					<span class="dashicons dashicons-video-alt3"></span>
				</a>
			</h1>

			<div class="peiwm-container">

				<!-- EXPORT SECTION -->
				<div class="peiwm-section">
					<div class="peiwm-export-section <?php echo esc_attr( $locked_class ); ?>" style="position: relative;">
						<?php if ( ! $is_pro ) : ?>
							<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
								<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
							</button>
						<?php endif; ?>
						<h2><?php echo esc_html__( 'Export CPT Posts', 'post-export-import-with-media' ); ?></h2>
						<p><?php echo esc_html__( 'Select a Custom Post Type and export all its posts with ACF fields, taxonomies, and media.', 'post-export-import-with-media' ); ?></p>

						<div style="margin-bottom:1rem;">
							<label for="peiwm-cpt-select"><strong><?php echo esc_html__( 'Select Post Type:', 'post-export-import-with-media' ); ?></strong></label><br>
							<select id="peiwm-cpt-select" style="min-width:250px;margin-top:0.5rem;">
								<option value=""><?php echo esc_html__( '— Loading post types…', 'post-export-import-with-media' ); ?></option>
							</select>
						</div>

						<div class="peiwm-export-options" style="margin-bottom:1rem;">
							<label class="peiwm-checkbox-label">
								<div style="display:flex;align-items:flex-start;gap:0.5rem;">
									<input type="checkbox" id="peiwm-cpt-export-acf-fields" checked <?php echo function_exists( 'get_fields' ) ? '' : 'disabled title="ACF not active"'; ?>>
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Export all ACF meta fields', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Include all Advanced Custom Fields data in each exported post.', 'post-export-import-with-media' ); ?></small>
									</span>
								</div>
							</label>

							<?php if ( function_exists( 'get_fields' ) ) : ?>
							<!-- ACF selective field picker — shown when a CPT is selected -->
							<div id="peiwm-cpt-acf-field-picker" style="display:none;margin-top:0.75rem;padding:0.75rem;background:#f9fafb;border:1px solid #e2e8f0;border-radius:6px;">
								<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
									<strong style="font-size:0.875rem;color:#374151;"><?php echo esc_html__( 'Select specific ACF fields to export:', 'post-export-import-with-media' ); ?></strong>
									<span style="font-size:0.8rem;color:#6b7280;" id="peiwm-cpt-acf-fields-count"></span>
								</div>
								<input type="text" id="peiwm-cpt-acf-field-search"
									placeholder="<?php echo esc_attr__( 'Search fields…', 'post-export-import-with-media' ); ?>"
									style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:0.85rem;margin-bottom:0.5rem;box-sizing:border-box;">
								<div id="peiwm-cpt-acf-fields-list"
									style="max-height:180px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:4px;background:#fff;padding:4px 0;">
									<div style="padding:8px 12px;color:#9ca3af;font-size:0.85rem;"><?php echo esc_html__( 'Loading fields…', 'post-export-import-with-media' ); ?></div>
								</div>
								<div style="display:flex;gap:8px;margin-top:0.5rem;">
									<button type="button" id="peiwm-cpt-acf-select-all-fields" class="button" style="font-size:0.8rem;padding:2px 8px;"><?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?></button>
									<button type="button" id="peiwm-cpt-acf-deselect-all-fields" class="button" style="font-size:0.8rem;padding:2px 8px;"><?php echo esc_html__( 'Deselect All', 'post-export-import-with-media' ); ?></button>
								</div>
							</div>
							<?php endif; ?>

							<label class="peiwm-checkbox-label" style="margin-top:0.5rem;">
								<div style="display:flex;align-items:flex-start;gap:0.5rem;">
									<input type="checkbox" id="peiwm-cpt-export-selective">
									<span class="peiwm-checkbox-text">
										<?php echo esc_html__( 'Export individually (select specific posts)', 'post-export-import-with-media' ); ?>
										<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Choose which posts to export instead of exporting all.', 'post-export-import-with-media' ); ?></small>
									</span>
								</div>
							</label>
						</div>

						<!-- Selective Export Panel -->
						<div id="peiwm-cpt-export-selective-panel" style="display:none;margin-bottom:1rem;">
							<div class="peiwm-selective-panel">
								<div class="peiwm-selective-header">
									<h4><?php echo esc_html__( 'Select Posts to Export', 'post-export-import-with-media' ); ?></h4>
									<div class="peiwm-selective-controls">
										<input type="text" id="peiwm-cpt-export-search" class="peiwm-selective-search" placeholder="<?php echo esc_attr__( 'Search posts…', 'post-export-import-with-media' ); ?>">
										<label class="peiwm-select-all-label">
											<input type="checkbox" id="peiwm-cpt-export-select-all" checked>
											<?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?>
										</label>
									</div>
								</div>
								<div id="peiwm-cpt-export-posts-list" class="peiwm-selective-list"></div>
								<div class="peiwm-selective-footer">
									<span id="peiwm-cpt-export-selected-count" class="peiwm-selected-count"><?php echo esc_html__( '0 selected', 'post-export-import-with-media' ); ?></span>
									<span id="peiwm-cpt-export-load-more-wrap"></span>
								</div>
							</div>
						</div>

						<button type="button" id="peiwm-export-cpt" class="button button-primary">
							<?php echo esc_html__( 'Export CPT Posts', 'post-export-import-with-media' ); ?>
						</button>

						<!-- Export Progress -->
						<div id="peiwm-cpt-export-progress" class="peiwm-progress" style="display:none;">
							<h4><?php echo esc_html__( 'Export Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar"><div class="peiwm-progress-fill"></div></div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting…', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>

				<!-- IMPORT SECTION -->
				<div class="peiwm-section">
					<div class="peiwm-import-section <?php echo esc_attr( $locked_class ); ?>" style="position: relative;">
						<?php if ( ! $is_pro ) : ?>
							<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
								<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
							</button>
						<?php endif; ?>
						<h2><?php echo esc_html__( 'Import CPT Posts', 'post-export-import-with-media' ); ?></h2>
						<p><?php echo esc_html__( 'Import posts from a previously exported CPT JSON file.', 'post-export-import-with-media' ); ?></p>

						<div class="button-container">
							<input type="file" id="peiwm-cpt-import-file" accept=".json" multiple style="display:none;">
							<button type="button" id="peiwm-cpt-select-import-file" class="button button-secondary">
								<?php echo esc_html__( 'Select JSON File(s)', 'post-export-import-with-media' ); ?>
							</button>
							<button type="button" id="peiwm-cpt-start-import" class="button button-primary" style="display:none;">
								<?php echo esc_html__( 'Start Import', 'post-export-import-with-media' ); ?>
							</button>
						</div>

						<div class="peiwm-import-options" style="margin-top:1rem;">
							<label class="peiwm-checkbox-label">
								<input type="checkbox" id="peiwm-cpt-check-media-library" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Check media library for post images', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Check if images already exist in media library before importing.', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>

							<label class="peiwm-checkbox-label" style="margin-top:0.5rem;">
								<input type="checkbox" id="peiwm-cpt-download-missing-images" checked>
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Download missing images from original URLs', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'If images are not found in media library, try to download them from their original locations.', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>

							<label class="peiwm-checkbox-label" style="margin-top:0.5rem;">
								<input type="checkbox" id="peiwm-cpt-import-selective">
								<span class="peiwm-checkbox-text">
									<?php echo esc_html__( 'Import individually (select specific posts)', 'post-export-import-with-media' ); ?>
									<small class="peiwm-checkbox-description"><?php echo esc_html__( 'Choose which posts to import from the file.', 'post-export-import-with-media' ); ?></small>
								</span>
							</label>
						</div>

						<!-- Selective Import Panel -->
						<div id="peiwm-cpt-import-selective-panel" style="display:none;margin-top:1rem;">
							<div class="peiwm-selective-panel">
								<div class="peiwm-selective-header">
									<h4><?php echo esc_html__( 'Select Posts to Import', 'post-export-import-with-media' ); ?></h4>
									<div class="peiwm-selective-controls">
										<input type="text" id="peiwm-cpt-import-search" class="peiwm-selective-search" placeholder="<?php echo esc_attr__( 'Search posts…', 'post-export-import-with-media' ); ?>">
										<label class="peiwm-select-all-label">
											<input type="checkbox" id="peiwm-cpt-import-select-all" checked>
											<?php echo esc_html__( 'Select All', 'post-export-import-with-media' ); ?>
										</label>
									</div>
								</div>
								<div id="peiwm-cpt-import-posts-list" class="peiwm-selective-list">
									<p class="peiwm-selective-empty"><?php echo esc_html__( '👆 Select a JSON file above to load posts for selection.', 'post-export-import-with-media' ); ?></p>
								</div>
								<div class="peiwm-selective-footer">
									<span id="peiwm-cpt-import-selected-count" class="peiwm-selected-count"><?php echo esc_html__( '0 selected', 'post-export-import-with-media' ); ?></span>
								</div>
							</div>
						</div>

						<!-- Import Progress -->
						<div id="peiwm-cpt-import-progress" class="peiwm-progress" style="display:none;">
							<h4><?php echo esc_html__( 'Import Progress', 'post-export-import-with-media' ); ?></h4>
							<div class="peiwm-progress-bar"><div class="peiwm-progress-fill"></div></div>
							<p class="peiwm-progress-text"><?php echo esc_html__( 'Starting…', 'post-export-import-with-media' ); ?></p>
							<div class="peiwm-log"></div>
						</div>
					</div>
				</div>

			</div><!-- /.peiwm-container -->
		</div><!-- /.wrap -->

		<?php $this->render_modal_templates(); ?>
		<?php
	}


	
}