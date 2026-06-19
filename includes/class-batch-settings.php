<?php
/**
 * Batch Settings Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.3.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch Settings Class - Manages batch processing settings
 */
class PEIWM_Batch_Settings {

	/**
	 * Instance
	 *
	 * @var PEIWM_Batch_Settings|null
	 */
	private static $instance = null;

	/**
	 * Default settings
	 *
	 * @var array
	 */
	private $default_settings = array(
		'preset_mode'                  => 'default',
		'enable_batch_processing'      => false,
		'post_batch_size'              => 10,
		'page_batch_size'              => 10,
		'media_batch_size'             => 10,
		'concurrent_requests'          => 2,
		'export_list_page_size'        => 50,
		'export_json_size'             => 100,
		'media_zip_size_limit'         => 10,
		'batch_delay'                  => 2000,
		'enable_background_processing' => false,
	);
	

	/**
	 * Get instance
	 *
	 * @return PEIWM_Batch_Settings
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_batch_scripts' ) );
		add_action( 'wp_ajax_peiwm_get_content_stats', array( $this, 'ajax_get_content_stats' ) );
	}

	/**
	 * AJAX: Get content statistics
	 */
	public function ajax_get_content_stats() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$total_posts = wp_count_posts( 'post' );
		$total_pages = wp_count_posts( 'page' );
		$total_media = wp_count_posts( 'attachment' );

		wp_send_json_success( array(
			'total_posts' => $total_posts->publish + $total_posts->draft + $total_posts->private,
			'total_pages' => $total_pages->publish + $total_pages->draft + $total_pages->private,
			'total_media' => $total_media->inherit,
		) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'peiwm-secure',
			__( 'Batch Settings', 'post-export-import-with-media' ),
			__( 'Batch Settings', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-batch-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'peiwm_batch_settings',
			'peiwm_batch_settings',
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

		// In sanitize_settings(), add:
		$allowed_modes = array('default','micro','low','light','standard','balanced','performance','turbo','max');
		$sanitized['preset_mode'] = isset($input['preset_mode']) && in_array($input['preset_mode'], $allowed_modes, true) ? $input['preset_mode'] : 'default';

		$sanitized['enable_batch_processing'] = isset( $input['enable_batch_processing'] ) ? (bool) $input['enable_batch_processing'] : false;
		$sanitized['post_batch_size'] = isset( $input['post_batch_size'] ) ? absint( $input['post_batch_size'] ) : 10;
		$sanitized['page_batch_size'] = isset( $input['page_batch_size'] ) ? absint( $input['page_batch_size'] ) : 10;
		$sanitized['media_batch_size'] = isset( $input['media_batch_size'] ) ? absint( $input['media_batch_size'] ) : 10;
		$sanitized['concurrent_requests'] = isset( $input['concurrent_requests'] ) ? absint( $input['concurrent_requests'] ) : 2;
		$sanitized['export_list_page_size'] = isset( $input['export_list_page_size'] ) ? absint( $input['export_list_page_size'] ) : 50;
		$sanitized['export_json_size'] = isset( $input['export_json_size'] ) ? absint( $input['export_json_size'] ) : 100;
		$sanitized['media_zip_size_limit'] = isset( $input['media_zip_size_limit'] ) ? absint( $input['media_zip_size_limit'] ) : 10;
		$sanitized['batch_delay'] = isset( $input['batch_delay'] ) ? absint( $input['batch_delay'] ) : 2000;
		$sanitized['enable_background_processing'] = isset( $input['enable_background_processing'] ) ? (bool) $input['enable_background_processing'] : false;

		// Validate ranges
		if ( $sanitized['post_batch_size'] < 10 ) {
			$sanitized['post_batch_size'] = 10;
		}
		if ( $sanitized['post_batch_size'] > 10000 ) {
			$sanitized['post_batch_size'] = 10000;
		}

		if ( $sanitized['page_batch_size'] < 10 ) {
			$sanitized['page_batch_size'] = 10;
		}
		if ( $sanitized['page_batch_size'] > 10000 ) {
			$sanitized['page_batch_size'] = 10000;
		}

		if ( $sanitized['media_batch_size'] < 10 ) {
			$sanitized['media_batch_size'] = 10;
		}
		if ( $sanitized['media_batch_size'] > 1000 ) {
			$sanitized['media_batch_size'] = 1000;
		}

		if ( $sanitized['concurrent_requests'] < 2 ) {
			$sanitized['concurrent_requests'] = 2;
		}
		if ( $sanitized['concurrent_requests'] > 200 ) {
			$sanitized['concurrent_requests'] = 200;
		}

		if ( $sanitized['export_list_page_size'] < 10 ) {
			$sanitized['export_list_page_size'] = 10;
		}
		if ( $sanitized['export_list_page_size'] > 2000 ) {
			$sanitized['export_list_page_size'] = 2000;
		}

		if ( $sanitized['export_json_size'] < 100 ) {
			$sanitized['export_json_size'] = 100;
		}
		if ( $sanitized['export_json_size'] > 10000 ) {
			$sanitized['export_json_size'] = 10000;
		}

		if ( $sanitized['media_zip_size_limit'] < 10 ) {
			$sanitized['media_zip_size_limit'] = 10;
		}
		if ( $sanitized['media_zip_size_limit'] > 500 ) {
			$sanitized['media_zip_size_limit'] = 500;
		}

		if ( $sanitized['batch_delay'] < 0 ) {
			$sanitized['batch_delay'] = 0;
		}
		if ( $sanitized['batch_delay'] > 5000 ) {
			$sanitized['batch_delay'] = 5000;
		}

		return $sanitized;
	}

	/**
	 * Get settings
	 *
	 * @return array Settings
	 */
	public function get_settings() {
		$settings = get_option( 'peiwm_batch_settings', $this->default_settings );
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
	 * Check if batch processing is enabled
	 *
	 * @return bool
	 */
	public function is_batch_enabled() {
		return (bool) $this->get_setting( 'enable_batch_processing' );
	}

	/**
	 * Enqueue batch processing scripts
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_batch_scripts( $hook ) {
		// Only load on plugin pages
		if ( strpos( $hook, 'peiwm' ) === false ) {
			return;
		}

		// Always enqueue admin CSS on batch settings page
		if ( 'export-import-posts_page_peiwm-batch-settings' === $hook ) {
			wp_enqueue_style(
				'peiwm-admin-css',
				PEIWM_PLUGIN_URL . 'build/css/admin.min.css',
				array(),
				PEIWM_VERSION
			);

			// Enqueue batch settings JS
			wp_enqueue_script(
				'peiwm-batch-settings-js',
				PEIWM_PLUGIN_URL . 'build/js/batch-settings.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			// Localize script for AJAX
			wp_localize_script(
				'peiwm-batch-settings-js',
				'peiwm_batch_ajax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'peiwm_secure_nonce' ),
				)
			);
		}

		// Enqueue batch script if enabled
		if ( $this->is_batch_enabled() ) {
			wp_enqueue_script(
				'peiwm-batch-admin',
				PEIWM_PLUGIN_URL . 'build/js/admin-batch.min.js',
				array( 'jquery' ),
				PEIWM_VERSION,
				true
			);

			// Pass batch settings to JavaScript
			wp_localize_script(
				'peiwm-batch-admin',
				'peiwm_batch_settings',
				array(
					'enabled'              => true,
					'post_batch_size'      => $this->get_setting( 'post_batch_size' ),
					'page_batch_size'      => $this->get_setting( 'page_batch_size' ),
					'media_batch_size'     => $this->get_setting( 'media_batch_size' ),
					'concurrent_requests'  => $this->get_setting( 'concurrent_requests' ),
					'export_list_page_size' => $this->get_setting( 'export_list_page_size' ),
					'export_json_size'     => $this->get_setting( 'export_json_size' ),
					'delay'                => $this->get_setting( 'batch_delay' ),
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

		$settings = $this->get_settings();
		?>
		<div class="wrap peiwm-settings-wrap">
			<h1><?php echo esc_html__( 'Batch Processing Settings', 'post-export-import-with-media' ); ?></h1>
			
			<div class="peiwm-settings-header">
				<p class="description">
					<?php echo esc_html__( 'Configure batch processing for large-scale import/export operations. Please note that importing posts is a resource-intensive process, as it needs to detect and process all associated images, post formatting, WordPress tags, categories, attributes, and other metadata. For better performance and server stability, bulk import with batch processing is highly recommended.', 'post-export-import-with-media' ); ?>
				</p>
			</div>


			<?php settings_errors( 'peiwm_batch_settings' ); ?>

			<form method="post" action="options.php" id="peiwm-batch-settings-form">
				<?php settings_fields( 'peiwm_batch_settings' ); ?>
				
				<table class="form-table peiwm-settings-table">
					<tbody>
						<!-- Enable Batch Processing -->
						<tr>
							<th scope="row">
								<label for="enable_batch_processing">
									<?php echo esc_html__( 'Enable Batch Processing', 'post-export-import-with-media' ); ?>
								</label>
							</th>
							<td>
								<label class="peiwm-toggle-switch">
									<input 
										type="checkbox" 
										id="enable_batch_processing" 
										name="peiwm_batch_settings[enable_batch_processing]" 
										value="1" 
										<?php checked( $settings['enable_batch_processing'], true ); ?>
									/>
									<span class="peiwm-toggle-slider"></span>
								</label>
								<p class="description">
									<?php echo esc_html__( 'Enable this to process large-scale operations in batches. ', 'post-export-import-with-media' ); ?>
								</p>
								<div class="peiwm-feature-badge">
									<span class="peiwm-badge peiwm-badge-free"><?php echo esc_html__( 'FREE', 'post-export-import-with-media' ); ?></span>
									<span class="peiwm-badge-text"><?php echo esc_html__( 'Available in free version', 'post-export-import-with-media' ); ?></span>
								</div>
							</td>
						</tr>

						<?php
							$main_instance = PEIWM_Main::get_instance();
							$is_pro_active = $main_instance->is_pro_active();
							$locked_class = ! $is_pro_active ? ' peiwm-locked-section-2' : '';
						?>

						<tr id="peiwm-preset-row" class="<?php echo esc_attr( $locked_class ); ?>" <?php echo $settings['enable_batch_processing'] ? '' : 'style="display:none"'; ?>>
							<th scope="row">
								<label><?php esc_html_e('Server preset mode', 'post-export-import-with-media'); ?></label>
							</th>
							<td style="position: relative;">

								<?php if ( ! $is_pro_active ) : ?>
									<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
										<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
									</button>
								<?php endif; ?>

								<div id="peiwm-preset-modes-ui">
									<!-- JS renders the mode cards here -->
								</div>

								<p class="description">
									<?php esc_html_e('Pick a preset matching your server. Values auto-fill below - save to apply.', 'post-export-import-with-media'); ?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

				<!-- Batch Configuration (shown only when enabled) -->
				<?php
				$main_instance = PEIWM_Main::get_instance();
				$is_pro_active = $main_instance->is_pro_active();
				$locked_class = ! $is_pro_active ? ' peiwm-locked-section' : '';
				?>
				<div id="peiwm-batch-config" class="<?php echo esc_attr( $locked_class ); ?>" style="<?php echo $settings['enable_batch_processing'] ? '' : 'display: none;'; ?>; position: relative;">
					<?php if ( ! $is_pro_active ) : ?>
						<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
							<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
						</button>
					<?php endif; ?>
					<h2 class="peiwm-section-title">
						<?php echo esc_html__( 'Batch Configuration', 'post-export-import-with-media' ); ?>
						<?php if ( ! $is_pro_active ) : ?>
							<span class="peiwm-pro-lock">🔒 PRO</span>
						<?php endif; ?>
					</h2>
					
					<table class="form-table peiwm-settings-table">
						<tbody>
							<!-- Post Batch Size -->
							<tr>
								<th scope="row">
									<label for="post_batch_size">
										<?php echo esc_html__( 'Posts Batch Size', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="post_batch_size" 
										name="peiwm_batch_settings[post_batch_size]" 
										value="<?php echo esc_attr( $settings['post_batch_size'] ); ?>" 
										min="1" 
										max="10000" 
										step="1"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<p class="description">
										<?php echo esc_html__( 'Number of posts per batch file. Default: 20 (Range: 5-10,000)', 'post-export-import-with-media' ); ?>
										<br><strong><?php echo esc_html__( 'For 100K+ posts: Use 100-500', 'post-export-import-with-media' ); ?></strong>
									</p>
								</td>
							</tr>

							<!-- Page Batch Size -->
							<tr>
								<th scope="row">
									<label for="page_batch_size">
										<?php echo esc_html__( 'Pages Batch Size', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="page_batch_size" 
										name="peiwm_batch_settings[page_batch_size]" 
										value="<?php echo esc_attr( $settings['page_batch_size'] ); ?>" 
										min="1" 
										max="10000" 
										step="1"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<p class="description">
										<?php echo esc_html__( 'Number of pages per batch file. Default: 20 (Range: 1-10,000)', 'post-export-import-with-media' ); ?>
										<br><strong><?php echo esc_html__( 'For 10K+ pages: Use 100-500', 'post-export-import-with-media' ); ?></strong>
									</p>
								</td>
							</tr>

							<!-- Concurrent Requests -->
							<tr>
								<th scope="row">
									<label for="concurrent_requests">
										<?php echo esc_html__( 'Concurrent Requests', 'post-export-import-with-media' ); ?>
										<span class="peiwm-badge peiwm-badge-important" style="background: #d63638; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">IMPORTANT</span>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="concurrent_requests" 
										name="peiwm_batch_settings[concurrent_requests]" 
										value="<?php echo esc_attr( isset( $settings['concurrent_requests'] ) ? $settings['concurrent_requests'] : 5 ); ?>" 
										min="1" 
										max="200" 
										step="1"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<p class="description">
										<?php echo esc_html__( 'How many posts/pages to process simultaneously. Default: 10 (Range: 1-200)', 'post-export-import-with-media' ); ?>
										<br><strong style="color: #d63638;"><?php echo esc_html__( '⚡ This is the KEY setting for speed!', 'post-export-import-with-media' ); ?></strong>
										<br><?php echo esc_html__( '• Small server (shared hosting): 5-10', 'post-export-import-with-media' ); ?>
										<br><?php echo esc_html__( '• Medium server (VPS): 20-50', 'post-export-import-with-media' ); ?>
										<br><?php echo esc_html__( '• Powerful server (dedicated): 100-200', 'post-export-import-with-media' ); ?>
										<br><?php echo esc_html__( '• With 100 concurrent: 100K posts in ~20 minutes (vs 50+ hours sequential)', 'post-export-import-with-media' ); ?>
									</p>
								</td>
							</tr>

							<!-- Media Batch Size -->
							<tr>
								<th scope="row">
									<label for="media_batch_size">
										<?php echo esc_html__( 'Media Files Batch Size', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="media_batch_size" 
										name="peiwm_batch_settings[media_batch_size]" 
										value="<?php echo esc_attr( $settings['media_batch_size'] ); ?>" 
										min="5" 
										max="1000" 
										step="5"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<p class="description">
										<?php echo esc_html__( 'Number of media files per batch. Default: 50 (Range: 5-1000)', 'post-export-import-with-media' ); ?>
									</p>
								</td>
							</tr>

							<!-- Media ZIP Size Limit -->
							<tr>
								<th scope="row">
									<label for="media_zip_size_limit">
										<?php echo esc_html__( 'Media ZIP Size Limit (MB)', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="media_zip_size_limit" 
										name="peiwm_batch_settings[media_zip_size_limit]" 
										value="<?php echo esc_attr( $settings['media_zip_size_limit'] ); ?>" 
										min="1" 
										max="500" 
										step="1"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<span class="peiwm-unit">MB</span>
									<p class="description">
										<?php echo esc_html__( 'Maximum size for each media ZIP file. Default: 50MB (Range: 1-500MB)', 'post-export-import-with-media' ); ?>
									</p>
								</td>
							</tr>

							<!-- Batch Delay -->
							<tr>
								<th scope="row">
									<label for="batch_delay">
										<?php echo esc_html__( 'Batch Delay (ms)', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="batch_delay" 
										name="peiwm_batch_settings[batch_delay]" 
										value="<?php echo esc_attr( $settings['batch_delay'] ); ?>" 
										min="0" 
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
										max="5000" 
										step="50"
										class="small-text"
									/>
									<span class="peiwm-unit">ms</span>
									<p class="description">
										<?php echo esc_html__( 'Delay between batches to prevent server overload. Default: 500ms (Range: 0-5000ms)', 'post-export-import-with-media' ); ?>
										<br><?php echo esc_html__( '• Powerful server: 0-100ms (no delay needed)', 'post-export-import-with-media' ); ?>
										<br><?php echo esc_html__( '• Shared hosting: 500-1000ms (prevent throttling)', 'post-export-import-with-media' ); ?>
									</p>
								</td>
							</tr>

							<!-- Export JSON Size -->
							<tr>
								<th scope="row">
									<label for="export_json_size">
										<?php echo esc_html__( 'Export JSON File Size (posts/page)', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="export_json_size" 
										name="peiwm_batch_settings[export_json_size]" 
										value="<?php echo esc_attr( isset( $settings['export_json_size'] ) ? $settings['export_json_size'] : 500 ); ?>" 
										min="100" 
										max="10000" 
										step="100"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<p class="description">
										<?php echo esc_html__( 'Number of posts per JSON file when clicking "Export Posts" (without selective mode). Default: 500. For 502 posts with 500 = 2 files.', 'post-export-import-with-media' ); ?>
									</p>
								</td>
							</tr>

							<!-- Export List Post/Page Size -->
							<tr>
								<th scope="row">
									<label for="export_list_page_size">
										<?php echo esc_html__( 'Export List Post/Page Size', 'post-export-import-with-media' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="number" 
										id="export_list_page_size" 
										name="peiwm_batch_settings[export_list_page_size]" 
										value="<?php echo esc_attr( isset( $settings['export_list_page_size'] ) ? $settings['export_list_page_size'] : 50 ); ?>" 
										min="10" 
										max="2000" 
										step="10"
										class="small-text"
										<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
									/>
									<p class="description">
										<?php echo esc_html__( 'Number of posts/pages loaded per page in "Export individually" list. Default: 300. Regular mode (batch disabled): always 300.', 'post-export-import-with-media' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<!-- Server Recommendations -->
					<div class="peiwm-recommendations-box">
						<h3><?php echo esc_html__( '📊 Recommended Settings Based on Your Content', 'post-export-import-with-media' ); ?></h3>
						<div id="peiwm-content-stats">
							<p><?php echo esc_html__( 'Loading content statistics...', 'post-export-import-with-media' ); ?></p>
						</div>
					</div>
				</div>

				<input type="hidden" id="peiwm_preset_mode" name="peiwm_batch_settings[preset_mode]" value="<?php echo esc_attr($settings['preset_mode'] ?? 'standard'); ?>">
				<div class="save-btn" id="peiwm-save-btn">
					<?php submit_button( __( 'Save Settings', 'post-export-import-with-media' ), 'primary', 'submit', true ); ?>
				</div>
			</form>
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
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Batch Processing (100K+ posts)', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Selective Export & Import', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Scheduled Automatic Exports', 'post-export-import-with-media' ); ?></div>
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
}