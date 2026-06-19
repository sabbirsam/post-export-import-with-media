<?php
/**
 * Themes and Plugins Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.3.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Themes and Plugins Handler Class - Manages themes and plugins backup/restore operations
 */
class PEIWM_Themes_Plugins_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Themes_Plugins_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Themes_Plugins_Handler
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
	}

	/**
	 * Initialize AJAX hooks
	 */
	private function init_ajax_hooks() {
		// Themes
		add_action( 'wp_ajax_peiwm_get_themes_list', array( $this, 'ajax_get_themes_list' ) );
		add_action( 'wp_ajax_peiwm_export_themes', array( $this, 'ajax_export_themes' ) );
		add_action( 'wp_ajax_peiwm_import_themes', array( $this, 'ajax_import_themes' ) );
		
		// Plugins
		add_action( 'wp_ajax_peiwm_get_plugins_list', array( $this, 'ajax_get_plugins_list' ) );
		add_action( 'wp_ajax_peiwm_export_plugins', array( $this, 'ajax_export_plugins' ) );
		add_action( 'wp_ajax_peiwm_import_plugins', array( $this, 'ajax_import_plugins' ) );
	}

	/**
	 * AJAX: Get themes list
	 */
	public function ajax_get_themes_list() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$themes = wp_get_themes();
			$active_theme = get_stylesheet();
			$themes_list = array();

			foreach ( $themes as $theme_slug => $theme ) {
				$themes_list[] = array(
					'slug' => $theme_slug,
					'name' => $theme->get( 'Name' ),
					'version' => $theme->get( 'Version' ),
					'description' => $theme->get( 'Description' ),
					'author' => $theme->get( 'Author' ),
					'is_active' => ( $theme_slug === $active_theme ),
					'screenshot' => $theme->get_screenshot() ? $theme->get_screenshot() : '',
				);
			}

			wp_send_json_success( array(
				'themes' => $themes_list,
				'active_theme' => $active_theme,
				'total_count' => count( $themes_list ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to get themes list', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Get plugins list
	 */
	public function ajax_get_plugins_list() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$all_plugins = get_plugins();
			$active_plugins = get_option( 'active_plugins', array() );
			$plugins_list = array();

			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				$plugin_slug = dirname( $plugin_file );
				if ( $plugin_slug === '.' ) {
					$plugin_slug = basename( $plugin_file, '.php' );
				}

				$plugins_list[] = array(
					'file' => $plugin_file,
					'slug' => $plugin_slug,
					'name' => $plugin_data['Name'],
					'version' => $plugin_data['Version'],
					'description' => $plugin_data['Description'],
					'author' => $plugin_data['Author'],
					'is_active' => in_array( $plugin_file, $active_plugins, true ),
				);
			}

			wp_send_json_success( array(
				'plugins' => $plugins_list,
				'active_plugins' => $active_plugins,
				'total_count' => count( $plugins_list ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to get plugins list', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Export themes
	 */
	public function ajax_export_themes() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$export_type = isset( $_POST['export_type'] ) ? sanitize_text_field( wp_unslash( $_POST['export_type'] ) ) : 'active';
			$selected_themes = isset( $_POST['selected_themes'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_themes'] ) ) : array();

			$themes_to_export = $this->get_themes_to_export( $export_type, $selected_themes );
			
			if ( empty( $themes_to_export ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No themes selected for export', 'post-export-import-with-media' ) ) );
			}

			$zip_file = $this->create_themes_zip( $themes_to_export );
			
			if ( ! $zip_file ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to create themes backup', 'post-export-import-with-media' ) ) );
			}

			wp_send_json_success( array(
				'download_url' => $zip_file['url'],
				'file_size' => $zip_file['size'],
				'themes_count' => count( $themes_to_export ),
				'message' => sprintf(
					esc_html__( 'Successfully exported %d theme(s)', 'post-export-import-with-media' ),
					count( $themes_to_export )
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Themes export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Export plugins
	 */
	public function ajax_export_plugins() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$export_type = isset( $_POST['export_type'] ) ? sanitize_text_field( wp_unslash( $_POST['export_type'] ) ) : 'active';
			$selected_plugins = isset( $_POST['selected_plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_plugins'] ) ) : array();

			$plugins_to_export = $this->get_plugins_to_export( $export_type, $selected_plugins );
			
			if ( empty( $plugins_to_export ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No plugins selected for export', 'post-export-import-with-media' ) ) );
			}

			$zip_file = $this->create_plugins_zip( $plugins_to_export );
			
			if ( ! $zip_file ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to create plugins backup', 'post-export-import-with-media' ) ) );
			}

			wp_send_json_success( array(
				'download_url' => $zip_file['url'],
				'file_size' => $zip_file['size'],
				'plugins_count' => count( $plugins_to_export ),
				'message' => sprintf(
					esc_html__( 'Successfully exported %d plugin(s)', 'post-export-import-with-media' ),
					count( $plugins_to_export )
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plugins export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Import themes
	 */
	public function ajax_import_themes() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			if ( ! isset( $_FILES['themes_file'] ) || ! isset( $_FILES['themes_file']['error'] ) || $_FILES['themes_file']['error'] !== UPLOAD_ERR_OK ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded or upload error', 'post-export-import-with-media' ) ) );
			}

			$replace_existing = isset( $_POST['replace_existing'] ) && '1' === $_POST['replace_existing'];
			$skip_existing    = isset( $_POST['skip_existing'] )    && '1' === $_POST['skip_existing'];
			$activate_theme   = isset( $_POST['activate_theme'] )   && '1' === $_POST['activate_theme'];

			$uploaded_file = $_FILES['themes_file']; // phpcs:ignore
			$result = $this->import_themes_from_zip( $uploaded_file, $replace_existing, $activate_theme, $skip_existing );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( array( 'message' => $result['message'] ) );
			}

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Themes import failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Import plugins
	 */
	public function ajax_import_plugins() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			if ( ! isset( $_FILES['plugins_file'] ) || ! isset( $_FILES['plugins_file']['error'] ) || $_FILES['plugins_file']['error'] !== UPLOAD_ERR_OK ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded or upload error', 'post-export-import-with-media' ) ) );
			}

			$replace_existing = isset( $_POST['replace_existing'] ) && '1' === $_POST['replace_existing'];
			$skip_existing    = isset( $_POST['skip_existing'] )    && '1' === $_POST['skip_existing'];
			$activate_plugins = isset( $_POST['activate_plugins'] ) && '1' === $_POST['activate_plugins'];

			$uploaded_file = $_FILES['plugins_file']; // phpcs:ignore
			$result = $this->import_plugins_from_zip( $uploaded_file, $replace_existing, $activate_plugins, $skip_existing );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( array( 'message' => $result['message'] ) );
			}

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plugins import failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * Get themes to export based on type and selection
	 *
	 * @param string $export_type Export type (active, all, selected)
	 * @param array  $selected_themes Selected theme slugs
	 * @return array Themes to export
	 */
	private function get_themes_to_export( $export_type, $selected_themes = array() ) {
		$themes = wp_get_themes();
		$active_theme = get_stylesheet();
		$themes_to_export = array();

		switch ( $export_type ) {
			case 'active':
				if ( isset( $themes[ $active_theme ] ) ) {
					$themes_to_export[ $active_theme ] = $themes[ $active_theme ];
				}
				break;

			case 'all':
				$themes_to_export = $themes;
				break;

			case 'selected':
				foreach ( $selected_themes as $theme_slug ) {
					if ( isset( $themes[ $theme_slug ] ) ) {
						$themes_to_export[ $theme_slug ] = $themes[ $theme_slug ];
					}
				}
				break;
		}

		return $themes_to_export;
	}

	/**
	 * Get plugins to export based on type and selection
	 *
	 * @param string $export_type Export type (active, all, selected)
	 * @param array  $selected_plugins Selected plugin files
	 * @return array Plugins to export
	 */
	private function get_plugins_to_export( $export_type, $selected_plugins = array() ) {
		$all_plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$plugins_to_export = array();

		switch ( $export_type ) {
			case 'active':
				foreach ( $active_plugins as $plugin_file ) {
					if ( isset( $all_plugins[ $plugin_file ] ) ) {
						$plugins_to_export[ $plugin_file ] = $all_plugins[ $plugin_file ];
					}
				}
				break;

			case 'all':
				$plugins_to_export = $all_plugins;
				break;

			case 'selected':
				foreach ( $selected_plugins as $plugin_file ) {
					if ( isset( $all_plugins[ $plugin_file ] ) ) {
						$plugins_to_export[ $plugin_file ] = $all_plugins[ $plugin_file ];
					}
				}
				break;
		}

		return $plugins_to_export;
	}

	/**
	 * Create themes ZIP file
	 *
	 * @param array $themes Themes to include in ZIP
	 * @return array|false ZIP file info or false on failure
	 */
	private function create_themes_zip( $themes ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
		
		if ( ! wp_mkdir_p( $export_dir ) ) {
			return false;
		}

		$zip_filename = 'themes-backup-' . date( 'Y-m-d-H-i-s' ) . '.zip';
		$zip_path = $export_dir . $zip_filename;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
			return false;
		}

		$themes_dir = get_theme_root();
		
		foreach ( $themes as $theme_slug => $theme ) {
			$theme_path = $themes_dir . '/' . $theme_slug;
			
			if ( is_dir( $theme_path ) ) {
				$this->add_directory_to_zip( $zip, $theme_path, 'themes/' . $theme_slug );
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
	 * Create plugins ZIP file
	 *
	 * @param array $plugins Plugins to include in ZIP
	 * @return array|false ZIP file info or false on failure
	 */
	private function create_plugins_zip( $plugins ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
		
		if ( ! wp_mkdir_p( $export_dir ) ) {
			return false;
		}

		$zip_filename = 'plugins-backup-' . date( 'Y-m-d-H-i-s' ) . '.zip';
		$zip_path = $export_dir . $zip_filename;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
			return false;
		}

		$plugins_dir = WP_PLUGIN_DIR;
		
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$plugin_slug = dirname( $plugin_file );
			
			if ( $plugin_slug === '.' ) {
				// Single file plugin
				$plugin_path = $plugins_dir . '/' . $plugin_file;
				if ( file_exists( $plugin_path ) ) {
					$zip->addFile( $plugin_path, 'plugins/' . $plugin_file );
				}
			} else {
				// Directory plugin
				$plugin_path = $plugins_dir . '/' . $plugin_slug;
				if ( is_dir( $plugin_path ) ) {
					$this->add_directory_to_zip( $zip, $plugin_path, 'plugins/' . $plugin_slug );
				}
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
	 * Import themes from ZIP file.
	 *
	 * Uses extractTo() for efficiency — avoids per-file memory loading.
	 *
	 * @param array  $uploaded_file    $_FILES entry.
	 * @param bool   $replace_existing Overwrite if theme dir already exists.
	 * @param bool   $activate_theme   Activate first imported theme.
	 * @param bool   $skip_existing    Skip without overwriting if theme exists.
	 * @return array
	 */
	private function import_themes_from_zip( $uploaded_file, $replace_existing = true, $activate_theme = false, $skip_existing = false ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'ZipArchive class not available', 'post-export-import-with-media' ) );
		}

		@set_time_limit( 300 );
		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore

		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'peiwm-temp/theme-import-' . time() . '/';

		if ( ! wp_mkdir_p( $temp_dir ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Failed to create temporary directory', 'post-export-import-with-media' ) );
		}

		$temp_zip = $temp_dir . 'import.zip';
		if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $temp_zip ) ) {
			$this->rrmdir( $temp_dir );
			return array( 'success' => false, 'message' => esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $temp_zip ) ) {
			$this->rrmdir( $temp_dir );
			return array( 'success' => false, 'message' => esc_html__( 'Failed to open ZIP file', 'post-export-import-with-media' ) );
		}

		$extract_dir = $temp_dir . 'extracted/';
		wp_mkdir_p( $extract_dir );
		$zip->extractTo( $extract_dir );
		$zip->close();

		$themes_dir      = get_theme_root();
		$imported_themes = array();
		$skipped_themes  = array();
		$failed_themes   = array();

		$extracted_themes_dir = $extract_dir . 'themes/';
		if ( ! is_dir( $extracted_themes_dir ) ) {
			$this->rrmdir( $temp_dir );
			return array( 'success' => false, 'message' => esc_html__( 'ZIP does not contain a themes/ directory. Please export themes from this plugin first.', 'post-export-import-with-media' ) );
		}

		$theme_slugs = array_diff( scandir( $extracted_themes_dir ), array( '.', '..' ) );

		foreach ( $theme_slugs as $theme_slug ) {
			$theme_slug      = sanitize_file_name( $theme_slug );
			$extracted_theme = $extracted_themes_dir . $theme_slug;
			$target_theme    = $themes_dir . '/' . $theme_slug;

			if ( ! is_dir( $extracted_theme ) ) {
				continue; // skip loose files at root level
			}

			if ( is_dir( $target_theme ) && $skip_existing ) {
				$skipped_themes[] = $theme_slug;
				continue;
			}

			if ( is_dir( $target_theme ) && ! $replace_existing && ! $skip_existing ) {
				$skipped_themes[] = $theme_slug;
				continue;
			}

			if ( is_dir( $target_theme ) && $replace_existing ) {
				$this->rrmdir( $target_theme );
			}

			if ( $this->rcopy( $extracted_theme, $target_theme ) ) {
				$imported_themes[] = $theme_slug;
			} else {
				$failed_themes[] = $theme_slug;
			}
		}

		$this->rrmdir( $temp_dir );

		// Activate first imported theme if requested
		$activated_theme = '';
		if ( $activate_theme && ! empty( $imported_themes ) ) {
			$theme = wp_get_theme( $imported_themes[0] );
			if ( $theme->exists() ) {
				switch_theme( $imported_themes[0] );
				$activated_theme = $theme->get( 'Name' );
			}
		}

		$message = sprintf(
			esc_html__( 'Themes import completed: %d imported, %d skipped, %d failed', 'post-export-import-with-media' ),
			count( $imported_themes ),
			count( $skipped_themes ),
			count( $failed_themes )
		);
		if ( $activated_theme ) {
			$message .= sprintf( esc_html__( '. Activated theme: %s', 'post-export-import-with-media' ), $activated_theme );
		}

		return array(
			'success'         => true,
			'message'         => $message,
			'imported_count'  => count( $imported_themes ),
			'skipped_count'   => count( $skipped_themes ),
			'failed_count'    => count( $failed_themes ),
			'imported_themes' => $imported_themes,
			'skipped_themes'  => $skipped_themes,
			'failed_themes'   => $failed_themes,
			'activated_theme' => $activated_theme,
		);
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string $src Source directory.
	 * @param string $dst Destination directory.
	 * @return bool
	 */
	private function rcopy( $src, $dst ) {
		if ( ! wp_mkdir_p( $dst ) ) {
			return false;
		}
		$items = array_diff( scandir( $src ), array( '.', '..' ) );
		foreach ( $items as $item ) {
			$s = $src . '/' . $item;
			$d = $dst . '/' . $item;
			if ( is_dir( $s ) ) {
				if ( ! $this->rcopy( $s, $d ) ) {
					return false;
				}
			} else {
				if ( ! copy( $s, $d ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $items as $item ) {
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Import plugins from ZIP file.
	 *
	 * Uses extractTo() for efficiency — avoids loading every file into PHP memory
	 * which causes failures with large ZIPs (100+ plugins).
	 *
	 * @param array  $uploaded_file    $_FILES entry.
	 * @param bool   $replace_existing Overwrite if plugin dir already exists.
	 * @param bool   $activate_plugins Activate after import.
	 * @param bool   $skip_existing    Skip (don't overwrite) if plugin already exists.
	 * @return array
	 */
	private function import_plugins_from_zip( $uploaded_file, $replace_existing = true, $activate_plugins = false, $skip_existing = false ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'ZipArchive class not available', 'post-export-import-with-media' ) );
		}

		// Raise limits for large ZIPs
		@set_time_limit( 300 );
		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore

		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'peiwm-temp/plugin-import-' . time() . '/';

		if ( ! wp_mkdir_p( $temp_dir ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Failed to create temporary directory', 'post-export-import-with-media' ) );
		}

		// Move uploaded ZIP to temp area
		$temp_zip = $temp_dir . 'import.zip';
		if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $temp_zip ) ) {
			$this->rrmdir( $temp_dir );
			return array( 'success' => false, 'message' => esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $temp_zip ) ) {
			$this->rrmdir( $temp_dir );
			return array( 'success' => false, 'message' => esc_html__( 'Failed to open ZIP file', 'post-export-import-with-media' ) );
		}

		// Extract everything into temp dir at once — much faster than per-file getFromIndex()
		$extract_dir = $temp_dir . 'extracted/';
		wp_mkdir_p( $extract_dir );
		$zip->extractTo( $extract_dir );
		$zip->close();

		$plugins_dir      = WP_PLUGIN_DIR;
		$imported_plugins = array();
		$skipped_plugins  = array();
		$failed_plugins   = array();

		// Scan extracted plugins/ directory
		$extracted_plugins_dir = $extract_dir . 'plugins/';
		if ( ! is_dir( $extracted_plugins_dir ) ) {
			$this->rrmdir( $temp_dir );
			return array( 'success' => false, 'message' => esc_html__( 'ZIP does not contain a plugins/ directory. Please export plugins from this plugin first.', 'post-export-import-with-media' ) );
		}

		$plugin_slugs = array_diff( scandir( $extracted_plugins_dir ), array( '.', '..' ) );

		foreach ( $plugin_slugs as $plugin_slug ) {
			$plugin_slug      = sanitize_file_name( $plugin_slug );
			$extracted_plugin = $extracted_plugins_dir . $plugin_slug;
			$target_plugin    = $plugins_dir . '/' . $plugin_slug;

			// Skip if exists and skip_existing is set
			if ( is_dir( $target_plugin ) && $skip_existing ) {
				$skipped_plugins[] = $plugin_slug;
				continue;
			}

			// Skip if exists and neither replace nor skip
			if ( is_dir( $target_plugin ) && ! $replace_existing && ! $skip_existing ) {
				$skipped_plugins[] = $plugin_slug;
				continue;
			}

			// Replace: remove old directory first
			if ( is_dir( $target_plugin ) && $replace_existing ) {
				$this->rrmdir( $target_plugin );
			}

			// Copy extracted plugin to plugins dir
			if ( is_dir( $extracted_plugin ) ) {
				if ( $this->rcopy( $extracted_plugin, $target_plugin ) ) {
					$imported_plugins[] = $plugin_slug;
				} else {
					$failed_plugins[] = $plugin_slug;
				}
			} elseif ( is_file( $extracted_plugin ) ) {
				// Single-file plugin
				if ( copy( $extracted_plugin, $target_plugin ) ) {
					$imported_plugins[] = $plugin_slug;
				} else {
					$failed_plugins[] = $plugin_slug;
				}
			}
		}

		// Clean up temp dir
		$this->rrmdir( $temp_dir );

		// Activate imported plugins if requested
		$activated_plugins = array();
		if ( $activate_plugins && ! empty( $imported_plugins ) ) {
			wp_clean_plugins_cache();
			$all_plugins = get_plugins();
			foreach ( $imported_plugins as $plugin_slug ) {
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					if ( dirname( $plugin_file ) === $plugin_slug || basename( $plugin_file, '.php' ) === $plugin_slug ) {
						$result = activate_plugin( $plugin_file );
						if ( ! is_wp_error( $result ) ) {
							$activated_plugins[] = $plugin_data['Name'];
						}
						break;
					}
				}
			}
		}

		$message = sprintf(
			esc_html__( 'Plugins import completed: %d imported, %d skipped, %d failed', 'post-export-import-with-media' ),
			count( $imported_plugins ),
			count( $skipped_plugins ),
			count( $failed_plugins )
		);
		if ( ! empty( $activated_plugins ) ) {
			$message .= sprintf( esc_html__( '. Activated %d plugin(s)', 'post-export-import-with-media' ), count( $activated_plugins ) );
		}

		return array(
			'success'           => true,
			'message'           => $message,
			'imported_count'    => count( $imported_plugins ),
			'skipped_count'     => count( $skipped_plugins ),
			'failed_count'      => count( $failed_plugins ),
			'imported_plugins'  => $imported_plugins,
			'skipped_plugins'   => $skipped_plugins,
			'failed_plugins'    => $failed_plugins,
			'activated_plugins' => $activated_plugins,
		);
	}

	/**
	 * Format file size to human readable
	 *
	 * @param int $bytes File size in bytes
	 * @return string Formatted size
	 */
	private function format_file_size( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		
		for ( $i = 0; $bytes > 1024; $i++ ) {
			$bytes /= 1024;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}