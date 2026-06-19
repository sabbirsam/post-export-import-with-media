<?php
/**
 * Admin Download Buttons Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.3.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Download Buttons Class - Adds download buttons to themes.php and plugins.php
 */
class PEIWM_Admin_Download_Buttons {

	/**
	 * Instance
	 *
	 * @var PEIWM_Admin_Download_Buttons|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Admin_Download_Buttons
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Check if feature is enabled
		if ( ! $this->is_feature_enabled() ) {
			return;
		}

		// Add hooks for themes page
		add_action( 'admin_footer-themes.php', array( $this, 'add_theme_download_buttons' ) );
		
		// Add hooks for plugins page
		add_action( 'admin_footer-plugins.php', array( $this, 'add_plugin_download_buttons' ) );
		
		// Add AJAX handlers
		add_action( 'wp_ajax_peiwm_download_single_theme', array( $this, 'ajax_download_single_theme' ) );
		add_action( 'wp_ajax_peiwm_download_single_plugin', array( $this, 'ajax_download_single_plugin' ) );
		
		// Add CSS and JS
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Check if feature is enabled
	 *
	 * @return bool
	 */
	private function is_feature_enabled() {
		return get_option( 'peiwm_enable_admin_download_buttons', false );
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'themes.php', 'plugins.php' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'peiwm-admin-download-buttons',
			PEIWM_PLUGIN_URL . 'build/js/admin-download-buttons.min.js',
			array( 'jquery' ),
			PEIWM_VERSION,
			true
		);

		wp_localize_script( 'peiwm-admin-download-buttons', 'peiwm_download', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'peiwm_download_nonce' ),
			'strings'  => array(
				'downloading' => esc_html__( 'Downloading...', 'post-export-import-with-media' ),
				'download'    => esc_html__( 'Download', 'post-export-import-with-media' ),
				'error'       => esc_html__( 'Download failed', 'post-export-import-with-media' ),
			),
		) );

		wp_add_inline_style( 'wp-admin', $this->get_inline_css() );
	}

	/**
	 * Add download buttons to themes page
	 */
	public function add_theme_download_buttons() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Add download buttons to theme cards
			$('.theme').each(function() {
				const themeCard = $(this);
				const themeSlug = themeCard.attr('data-slug');
				
				if (themeSlug) {
					const actionsDiv = themeCard.find('.theme-actions');
					if (actionsDiv.length) {
						const downloadBtn = $('<a href="#" class="button peiwm-download-theme-btn" data-theme="' + themeSlug + '">📥 ' + peiwm_download.strings.download + '</a>');
						actionsDiv.append(downloadBtn);
					}
				}
			});
			
			// Handle download button clicks
			$(document).on('click', '.peiwm-download-theme-btn', function(e) {
				e.preventDefault();
				const btn = $(this);
				const themeSlug = btn.data('theme');
				
				if (!themeSlug) return;
				
				btn.prop('disabled', true).text('📥 ' + peiwm_download.strings.downloading);
				
				$.ajax({
					url: peiwm_download.ajax_url,
					type: 'POST',
					data: {
						action: 'peiwm_download_single_theme',
						nonce: peiwm_download.nonce,
						theme_slug: themeSlug
					},
					success: function(response) {
						if (response.success) {
							// Trigger download
							const link = document.createElement('a');
							link.href = response.data.download_url;
							link.download = '';
							document.body.appendChild(link);
							link.click();
							document.body.removeChild(link);
						} else {
							alert(peiwm_download.strings.error + ': ' + response.data.message);
						}
					},
					error: function() {
						alert(peiwm_download.strings.error);
					},
					complete: function() {
						btn.prop('disabled', false).text('📥 ' + peiwm_download.strings.download);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Add download buttons to plugins page
	 */
	public function add_plugin_download_buttons() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Add download buttons to plugin rows
			$('tr[data-slug]').each(function() {
				const pluginRow = $(this);
				const pluginSlug = pluginRow.attr('data-slug');
				const pluginFile = pluginRow.attr('data-plugin');
				
				if (pluginSlug && pluginFile) {
					const actionsDiv = pluginRow.find('.row-actions');
					if (actionsDiv.length) {
						const downloadLink = $('<span class="peiwm-download-plugin"> | <a href="#" class="peiwm-download-plugin-btn" data-plugin="' + pluginFile + '">📥 ' + peiwm_download.strings.download + '</a></span>');
						actionsDiv.append(downloadLink);
					}
				}
			});
			
			// Handle download button clicks
			$(document).on('click', '.peiwm-download-plugin-btn', function(e) {
				e.preventDefault();
				const btn = $(this);
				const pluginFile = btn.data('plugin');
				
				if (!pluginFile) return;
				
				btn.text('📥 ' + peiwm_download.strings.downloading);
				
				$.ajax({
					url: peiwm_download.ajax_url,
					type: 'POST',
					data: {
						action: 'peiwm_download_single_plugin',
						nonce: peiwm_download.nonce,
						plugin_file: pluginFile
					},
					success: function(response) {
						if (response.success) {
							// Trigger download
							const link = document.createElement('a');
							link.href = response.data.download_url;
							link.download = '';
							document.body.appendChild(link);
							link.click();
							document.body.removeChild(link);
						} else {
							alert(peiwm_download.strings.error + ': ' + response.data.message);
						}
					},
					error: function() {
						alert(peiwm_download.strings.error);
					},
					complete: function() {
						btn.text('📥 ' + peiwm_download.strings.download);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Download single theme
	 */
	public function ajax_download_single_theme() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_download_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$theme_slug = isset( $_POST['theme_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) ) : '';
		
		if ( empty( $theme_slug ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Theme slug not provided', 'post-export-import-with-media' ) ) );
		}

		try {
			$theme = wp_get_theme( $theme_slug );
			
			if ( ! $theme->exists() ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Theme not found', 'post-export-import-with-media' ) ) );
			}

			$zip_file = $this->create_single_theme_zip( $theme_slug, $theme );
			
			if ( ! $zip_file ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to create theme backup', 'post-export-import-with-media' ) ) );
			}

			wp_send_json_success( array(
				'download_url' => $zip_file['url'],
				'file_size' => $zip_file['size'],
				'message' => sprintf(
					esc_html__( 'Theme "%s" ready for download', 'post-export-import-with-media' ),
					$theme->get( 'Name' )
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Theme download failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Download single plugin
	 */
	public function ajax_download_single_plugin() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_download_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';
		
		if ( empty( $plugin_file ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plugin file not provided', 'post-export-import-with-media' ) ) );
		}

		try {
			$all_plugins = get_plugins();
			
			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Plugin not found', 'post-export-import-with-media' ) ) );
			}

			$plugin_data = $all_plugins[ $plugin_file ];
			$zip_file = $this->create_single_plugin_zip( $plugin_file, $plugin_data );
			
			if ( ! $zip_file ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to create plugin backup', 'post-export-import-with-media' ) ) );
			}

			wp_send_json_success( array(
				'download_url' => $zip_file['url'],
				'file_size' => $zip_file['size'],
				'message' => sprintf(
					esc_html__( 'Plugin "%s" ready for download', 'post-export-import-with-media' ),
					$plugin_data['Name']
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plugin download failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * Create single theme ZIP file
	 *
	 * @param string   $theme_slug Theme slug
	 * @param WP_Theme $theme Theme object
	 * @return array|false ZIP file info or false on failure
	 */
	private function create_single_theme_zip( $theme_slug, $theme ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
		
		if ( ! wp_mkdir_p( $export_dir ) ) {
			return false;
		}

		$zip_filename = 'theme-' . $theme_slug . '-' . date( 'Y-m-d-H-i-s' ) . '.zip';
		$zip_path = $export_dir . $zip_filename;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
			return false;
		}

		$theme_path = $theme->get_stylesheet_directory();
		
		if ( is_dir( $theme_path ) ) {
			$this->add_directory_to_zip( $zip, $theme_path, $theme_slug );
		}

		$zip->close();

		if ( ! file_exists( $zip_path ) ) {
			return false;
		}

		return array(
			'path' => $zip_path,
			'url' => $upload_dir['baseurl'] . '/peiwm-exports/' . $zip_filename,
			'size' => filesize( $zip_path ),
		);
	}

	/**
	 * Create single plugin ZIP file
	 *
	 * @param string $plugin_file Plugin file
	 * @param array  $plugin_data Plugin data
	 * @return array|false ZIP file info or false on failure
	 */
	private function create_single_plugin_zip( $plugin_file, $plugin_data ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
		
		if ( ! wp_mkdir_p( $export_dir ) ) {
			return false;
		}

		$plugin_slug = dirname( $plugin_file );
		if ( $plugin_slug === '.' ) {
			$plugin_slug = basename( $plugin_file, '.php' );
		}

		$zip_filename = 'plugin-' . $plugin_slug . '-' . date( 'Y-m-d-H-i-s' ) . '.zip';
		$zip_path = $export_dir . $zip_filename;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
			return false;
		}

		$plugins_dir = WP_PLUGIN_DIR;
		
		if ( $plugin_slug === basename( $plugin_file, '.php' ) ) {
			// Single file plugin
			$plugin_path = $plugins_dir . '/' . $plugin_file;
			if ( file_exists( $plugin_path ) ) {
				$zip->addFile( $plugin_path, $plugin_file );
			}
		} else {
			// Directory plugin
			$plugin_path = $plugins_dir . '/' . $plugin_slug;
			if ( is_dir( $plugin_path ) ) {
				$this->add_directory_to_zip( $zip, $plugin_path, $plugin_slug );
			}
		}

		$zip->close();

		if ( ! file_exists( $zip_path ) ) {
			return false;
		}

		return array(
			'path' => $zip_path,
			'url' => $upload_dir['baseurl'] . '/peiwm-exports/' . $zip_filename,
			'size' => filesize( $zip_path ),
		);
	}

	/**
	 * Add directory to ZIP recursively
	 *
	 * @param ZipArchive $zip ZIP archive object
	 * @param string     $source_dir Source directory path
	 * @param string     $zip_dir Directory path in ZIP
	 */
	private function add_directory_to_zip( $zip, $source_dir, $zip_dir ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$file_path = $file->getRealPath();
			$relative_path = $zip_dir . '/' . substr( $file_path, strlen( $source_dir ) + 1 );
			
			// Convert Windows paths to Unix paths for ZIP
			$relative_path = str_replace( '\\', '/', $relative_path );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative_path );
			} elseif ( $file->isFile() ) {
				$zip->addFile( $file_path, $relative_path );
			}
		}
	}

	/**
	 * Get inline CSS for download buttons
	 *
	 * @return string CSS styles
	 */
	private function get_inline_css() {
		return '
		.peiwm-download-theme-btn {
			margin-left: 5px !important;
			font-size: 12px !important;
			padding: 4px 8px !important;
			height: auto !important;
			line-height: 1.2 !important;
		}
		
		.peiwm-download-plugin {
			color: #666;
		}
		
		.peiwm-download-plugin-btn {
			text-decoration: none;
			color: #0073aa;
		}
		
		.peiwm-download-plugin-btn:hover {
			color: #005177;
		}
		
		.peiwm-download-theme-btn:disabled,
		.peiwm-download-plugin-btn:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}
		';
	}
}