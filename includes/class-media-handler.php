<?php
/**
 * Media Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media Handler Class - Manages media export/import operations
 */
class PEIWM_Media_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Media_Handler|null
	 */
	private static $instance = null;

	/**
	 * Allowed file types
	 *
	 * @var array
	 */
	private $allowed_file_types = array(
		'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
		'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
		'mp3', 'wav', 'ogg', 'mp4', 'avi', 'mov', 'wmv',
		'zip', 'rar', '7z', 'tar', 'gz',
	);

	/**
	 * Maximum file size (500MB)
	 *
	 * @var int
	 */
	private $max_file_size = 524288000;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Media_Handler
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
		add_action( 'wp_ajax_peiwm_export_media', array( $this, 'ajax_export_media' ) );
		add_action( 'wp_ajax_peiwm_import_media_start', array( $this, 'ajax_import_media_start' ) );
		add_action( 'wp_ajax_peiwm_import_media_file', array( $this, 'ajax_import_media_file' ) );
		add_action( 'wp_ajax_peiwm_delete_media', array( $this, 'ajax_delete_media' ) );
		add_action( 'wp_ajax_peiwm_cleanup_media_batch', array( $this, 'ajax_cleanup_media_batch' ) );
		add_action( 'wp_ajax_peiwm_get_upload_limits', array( $this, 'ajax_get_upload_limits' ) );
	}

	/**
	 * AJAX: Get upload limits
	 */
	public function ajax_get_upload_limits() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$upload_max = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
		$post_max = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		$php_limit = min( $upload_max, $post_max );

		wp_send_json_success( array(
			'limit_bytes' => $php_limit,
			'limit_mb' => round( $php_limit / 1024 / 1024, 2 ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size' => ini_get( 'post_max_size' ),
		) );
	}

	/**
	 * AJAX: Export media
	 */
	public function ajax_export_media() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'ZipArchive extension is required', 'post-export-import-with-media' ) ) );
			}

			// Check if user wants to export all image sizes
			$export_all_sizes = isset( $_POST['export_all_sizes'] ) && $_POST['export_all_sizes'] === '1';

			// FIX: Raise limits before a potentially heavy operation
			@set_time_limit( 300 );
			@ini_set( 'memory_limit', '512M' );

			// Build attachment query args
			$attachment_query = array(
				'post_type'              => 'attachment',
				'numberposts'            => -1,
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			// FIX: Fetch IDs only — NOT full WP_Post objects — to prevent memory exhaustion
			// FIX: Use suppress_filters=true so no third-party plugin can cap the result count
			// FIX: Use post_status='inherit' to be consistent with stats query
			$attachment_ids = get_posts( $attachment_query );

			if ( empty( $attachment_ids ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No media files found to export', 'post-export-import-with-media' ) ) );
			}

			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';

			if ( ! wp_mkdir_p( $export_dir ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Could not create export directory', 'post-export-import-with-media' ) ) );
			}

			$zip_filename = 'media-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.zip';
			$zip_path     = $export_dir . $zip_filename;

			$zip = new ZipArchive();
			if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Could not create ZIP file', 'post-export-import-with-media' ) ) );
			}

			$media_data  = array();
			$added_files = 0;
			$skipped_files = 0;
			$upload_base = rtrim( $upload_dir['basedir'], '/\\' );

			// FIX: Loop over IDs only. Fetch only what we need per file — no WP_Post object needed.
			foreach ( $attachment_ids as $id ) {
				$file_path = get_attached_file( $id );

				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$skipped_files++;
					// error_log( 'PEIWM: Skipping attachment ID ' . $id . ' - file not found: ' . $file_path );
					continue;
				}

				// Build relative path for ZIP structure
				$relative_path = str_replace( $upload_base, '', $file_path );
				$relative_path = ltrim( $relative_path, '/\\' );
				$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', $relative_path );

				if ( ! $zip->addFile( $file_path, $relative_path ) ) {
					$skipped_files++;
					continue;
				}

				$added_files++;

				// Export all image sizes if requested
				if ( $export_all_sizes && wp_attachment_is_image( $id ) ) {
					$metadata = wp_get_attachment_metadata( $id );
					if ( ! empty( $metadata['sizes'] ) ) {
						$upload_dir_path = dirname( $file_path );
						foreach ( $metadata['sizes'] as $size_name => $size_data ) {
							$size_file = $upload_dir_path . DIRECTORY_SEPARATOR . $size_data['file'];
							if ( file_exists( $size_file ) ) {
								$size_relative_path = str_replace( $upload_base, '', $size_file );
								$size_relative_path = ltrim( $size_relative_path, '/\\' );
								$size_relative_path = str_replace( DIRECTORY_SEPARATOR, '/', $size_relative_path );
								
								if ( $zip->addFile( $size_file, $size_relative_path ) ) {
									$added_files++;
								}
							}
						}
					}
				}

				// FIX: Fetch post fields individually instead of loading WP_Post object
				$post = get_post( $id );
				if ( ! $post ) {
					continue;
				}

				$media_data[] = array(
					'ID'          => $id,
					'filename'    => basename( $file_path ),
					'title'       => $post->post_title,
					'description' => $post->post_content,
					'mime_type'   => $post->post_mime_type,
					'upload_date' => $post->post_date,
					'file_size'   => (int) @filesize( $file_path ),
					'file_path'   => $relative_path,
					'meta'        => get_post_meta( $id ),
				);

				// Free memory every 100 files
				if ( $added_files % 100 === 0 ) {
					wp_cache_flush();
				}
			}

			// Add metadata JSON to ZIP
			$zip->addFromString( 'media_metadata.json', wp_json_encode( $media_data, JSON_PRETTY_PRINT ) );
			$zip->close();

			if ( $added_files === 0 ) {
				wp_delete_file( $zip_path );
				wp_send_json_error( array( 'message' => esc_html__( 'No valid media files found to export', 'post-export-import-with-media' ) ) );
			}

			$download_url = add_query_arg(
				array(
					'action'   => 'peiwm_export_media_download',
					'file'     => $zip_filename,
					'_wpnonce' => wp_create_nonce( 'peiwm_download_nonce' ),
				),
				admin_url( 'admin-post.php' )
			);

			wp_send_json_success( array(
				'download_url'         => $download_url,
				'filename'             => $zip_filename,
				'count'                => $added_files,
				'unique_count'         => count( $media_data ),
				'total_attachments'    => count( $attachment_ids ),
				'skipped_count'        => $skipped_files,
				'export_all_sizes'     => $export_all_sizes,
				'total_size_formatted' => size_format( filesize( $zip_path ) ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Media export failed', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Start media import
	 */
	public function ajax_import_media_start() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// Validate file upload
			if ( ! isset( $_FILES['media_file'] ) ) {
				throw new Exception( esc_html__( 'No file uploaded', 'post-export-import-with-media' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				throw new Exception( esc_html__( 'ZipArchive class is not available on this server', 'post-export-import-with-media' ) );
			}
			
			// Sanitize uploaded file data - don't sanitize tmp_name as it's a system path
			$uploaded_file = array(
				'name'     => isset( $_FILES['media_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['media_file']['name'] ) ) : '',
				'type'     => isset( $_FILES['media_file']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['media_file']['type'] ) ) : '',
				'tmp_name' => isset( $_FILES['media_file']['tmp_name'] ) ? $_FILES['media_file']['tmp_name'] : '', // phpcs:ignore tmp_name as its system path 
				'error'    => isset( $_FILES['media_file']['error'] ) ? absint( $_FILES['media_file']['error'] ) : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $_FILES['media_file']['size'] ) ? absint( $_FILES['media_file']['size'] ) : 0,
			);

			// Check upload errors
			if ( $uploaded_file['error'] !== UPLOAD_ERR_OK ) {
				$error_msg = $this->get_upload_error_message( $uploaded_file['error'] );
				throw new Exception( $error_msg );
			}

			// Validate file type and size
			$this->validate_uploaded_file( $uploaded_file );

			// Create secure temporary directory
			$upload_dir = wp_upload_dir();
			$batch_id = wp_generate_uuid4();
			$temp_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'temp_' . sanitize_file_name( $batch_id );

			if ( ! is_writable( $upload_dir['basedir'] ) ) {
				throw new Exception( esc_html__( 'Upload directory is not writable', 'post-export-import-with-media' ) );
			}

			// Use wp_mkdir_p for better cross-platform compatibility
			if ( ! wp_mkdir_p( $temp_dir ) ) {
				throw new Exception( esc_html__( 'Failed to create temporary directory', 'post-export-import-with-media' ) );
			}

			// Move uploaded file securely using WordPress functions
			$zip_file = $temp_dir . DIRECTORY_SEPARATOR . 'media.zip';
			
			// Check if temporary file exists
			if ( ! file_exists( $uploaded_file['tmp_name'] ) || ! is_uploaded_file( $uploaded_file['tmp_name'] ) ) {
				$this->delete_directory_secure( $temp_dir );
				throw new Exception( esc_html__( 'Temporary file not found or invalid', 'post-export-import-with-media' ) );
			}
			
			// Use move_uploaded_file for better compatibility on Windows
			if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $zip_file ) ) {
				$this->delete_directory_secure( $temp_dir );
				throw new Exception( esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
			}

			// Extract ZIP securely with file validation
			$zip = new ZipArchive();
			$zip_result = $zip->open( $zip_file );
			if ( $zip_result !== true ) {
				$this->delete_directory_secure( $temp_dir );
				throw new Exception( sprintf(
					esc_html__( 'Failed to open ZIP file - file may be corrupted (Error: %d)', 'post-export-import-with-media' ),
					$zip_result
				) );
			}

			// SECURITY FIX: Manually extract files with validation instead of extractTo()
			$allow_all_types = get_option( 'peiwm_allow_all_file_types', false );
			$allowed_extensions_option = get_option( 'peiwm_allowed_media_file_types', 'jpg,jpeg,png,gif,webp,svg,json,pdf,mp4,mp3,wav,doc,docx,txt' );
			$allowed_extensions = array_map( 'trim', explode( ',', strtolower( $allowed_extensions_option ) ) );
			
			$blocked_files = array();
			
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$file_info = $zip->statIndex( $i );
				$filename = $file_info['name'];
				
				// SECURITY FIX: Prevent path traversal
				if ( strpos( $filename, '..' ) !== false || strpos( $filename, '/' ) === 0 ) {
					// error_log( 'PEIWM Security: Blocked path traversal attempt in media ZIP: ' . $filename );
					continue; // Skip files with path traversal attempts
				}
				
				// SECURITY FIX: Validate file extension (unless "allow all" is enabled)
				if ( ! $allow_all_types ) {
					$file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
					if ( ! empty( $file_ext ) && ! in_array( $file_ext, $allowed_extensions, true ) ) {
						// error_log( 'PEIWM Security: Blocked disallowed file type in media ZIP: ' . $filename );
						$blocked_files[] = $filename;
						continue; // Skip disallowed file types
					}
				}
				
				// Extract file
				$target_path = $temp_dir . DIRECTORY_SEPARATOR . $filename;
				
				// Create directory if needed
				$target_dir = dirname( $target_path );
				if ( ! is_dir( $target_dir ) ) {
					wp_mkdir_p( $target_dir );
				}
				
				// Extract file or create directory
				if ( ! $file_info['size'] ) {
					// Directory
					wp_mkdir_p( $target_path );
				} else {
					// File
					$file_content = $zip->getFromIndex( $i );
					if ( $file_content !== false ) {
						file_put_contents( $target_path, $file_content );
					}
				}
			}

			$zip->close();
			wp_delete_file( $zip_file );

			// Read and validate metadata
			$metadata_file = $temp_dir . DIRECTORY_SEPARATOR . 'media_metadata.json';
			if ( ! file_exists( $metadata_file ) ) {
				$this->delete_directory_secure( $temp_dir );
				throw new Exception( esc_html__( 'Invalid media export file - metadata not found', 'post-export-import-with-media' ) );
			}

			$metadata_content = file_get_contents( $metadata_file );
			$media_data = json_decode( $metadata_content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$this->delete_directory_secure( $temp_dir );
				throw new Exception( sprintf(
					esc_html__( 'Invalid JSON in metadata file: %s', 'post-export-import-with-media' ),
					json_last_error_msg()
				) );
			}

			// Validate media data structure
			if ( ! is_array( $media_data ) ) {
				$this->delete_directory_secure( $temp_dir );
				throw new Exception( esc_html__( 'Invalid media data format', 'post-export-import-with-media' ) );
			}

			// Store batch info securely
			set_transient( 'peiwm_media_batch_' . $batch_id, array(
				'temp_dir'      => $temp_dir,
				'media_data'    => $media_data,
				'blocked_files' => $blocked_files,
				'created'       => time(),
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'batch_id'      => $batch_id,
				'total_files'   => count( $media_data ),
				'blocked_files' => $blocked_files,
				'blocked_count' => count( $blocked_files ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Import media file
	 */
	public function ajax_import_media_file() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// Validate and sanitize input
			$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
			$file_index = isset( $_POST['file_index'] ) ? absint( wp_unslash( $_POST['file_index'] ) ) : 0;

			if ( empty( $batch_id ) ) {
				throw new Exception( esc_html__( 'Invalid batch ID', 'post-export-import-with-media' ) );
			}

			// Get batch data
			$batch_data = get_transient( 'peiwm_media_batch_' . $batch_id );
			if ( ! $batch_data ) {
				throw new Exception( esc_html__( 'Batch not found or expired', 'post-export-import-with-media' ) );
			}

			$temp_dir = $batch_data['temp_dir'];
			$media_data = $batch_data['media_data'];

			if ( ! isset( $media_data[ $file_index ] ) ) {
				throw new Exception( esc_html__( 'File index not found', 'post-export-import-with-media' ) );
			}

			$file_data = $media_data[ $file_index ];
			// Use the file_path from metadata which includes the directory structure
			// Handle old metadata that might not have file_path
			$file_path = isset( $file_data['file_path'] ) ? $file_data['file_path'] : $file_data['filename'];
			$relative_file_path = str_replace( '/', DIRECTORY_SEPARATOR, $file_path );
			$source_file = $temp_dir . DIRECTORY_SEPARATOR . $relative_file_path;
			
			if ( ! file_exists( $source_file ) ) {
				throw new Exception( sprintf(
					esc_html__( 'Source file not found: %s (looking in: %s)', 'post-export-import-with-media' ),
					$file_data['filename'],
					$source_file
				) );
			}

			// Check if file already exists in media library
			if ( $this->file_exists_in_media_library_secure( $file_data ) ) {
				wp_send_json_success( array(
					'filename' => sanitize_text_field( $file_data['filename'] ),
					'status'   => 'skipped',
					'reason'   => esc_html__( 'File already exists in media library', 'post-export-import-with-media' ),
				) );
			}

			// Import file securely
			$upload_result = $this->import_media_file_secure( $source_file, $file_data );
			if ( is_wp_error( $upload_result ) ) {
				wp_send_json_success( array(
					'filename' => sanitize_text_field( $file_data['filename'] ),
					'status'   => 'failed',
					'reason'   => $upload_result->get_error_message(),
				) );
			}

			wp_send_json_success( array(
				'filename'             => sanitize_text_field( $file_data['filename'] ),
				'status'               => 'imported',
				'file_size'            => absint( $file_data['file_size'] ),
				'file_size_formatted'  => $this->format_file_size( $file_data['file_size'] ),
				'attachment_id'        => $upload_result,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Delete media
	 */
	public function ajax_delete_media() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$attachments = get_posts( array(
				'post_type'   => 'attachment',
				'numberposts' => -1,
				'post_status' => 'any',
			) );

			if ( empty( $attachments ) ) {
				wp_reset_postdata(); // Reset before returning
				wp_send_json_success( array( 'message' => esc_html__( 'No media files found to delete', 'post-export-import-with-media' ) ) );
			}

			$deleted_count = 0;
			$failed_count = 0;

			foreach ( $attachments as $attachment ) {
				$result = wp_delete_attachment( $attachment->ID, true );
				if ( $result ) {
					$deleted_count++;
				} else {
					$failed_count++;
				}
			}

			// Reset global post data after processing
			wp_reset_postdata();

			$message = sprintf(
				esc_html__( 'Deleted %d media files successfully', 'post-export-import-with-media' ),
				$deleted_count
			);

			if ( $failed_count > 0 ) {
				$message .= sprintf(
					esc_html__( '. Failed to delete %d files.', 'post-export-import-with-media' ),
					$failed_count
				);
			}

			wp_send_json_success( array(
				'message'       => $message,
				'deleted_count' => $deleted_count,
				'failed_count'  => $failed_count,
				'total_count'   => count( $attachments ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Delete operation failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Cleanup media batch
	 */
	public function ajax_cleanup_media_batch() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		
		if ( ! empty( $batch_id ) ) {
			// Get batch data
			$batch_data = get_transient( 'peiwm_media_batch_' . $batch_id );
			if ( $batch_data && isset( $batch_data['temp_dir'] ) ) {
				$this->delete_directory_secure( $batch_data['temp_dir'] );
			}
			
			// Delete transient
			delete_transient( 'peiwm_media_batch_' . $batch_id );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Cleanup completed', 'post-export-import-with-media' ) ) );
	}

	/**
	 * Get upload error message
	 *
	 * @param int $error_code Upload error code
	 * @return string Error message
	 */
	private function get_upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
				return esc_html__( 'File is too large (exceeds upload_max_filesize)', 'post-export-import-with-media' );
			case UPLOAD_ERR_FORM_SIZE:
				return esc_html__( 'File is too large (exceeds MAX_FILE_SIZE)', 'post-export-import-with-media' );
			case UPLOAD_ERR_PARTIAL:
				return esc_html__( 'File was only partially uploaded', 'post-export-import-with-media' );
			case UPLOAD_ERR_NO_FILE:
				return esc_html__( 'No file was uploaded', 'post-export-import-with-media' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return esc_html__( 'Missing temporary folder', 'post-export-import-with-media' );
			case UPLOAD_ERR_CANT_WRITE:
				return esc_html__( 'Failed to write file to disk', 'post-export-import-with-media' );
			case UPLOAD_ERR_EXTENSION:
				return esc_html__( 'File upload stopped by extension', 'post-export-import-with-media' );
			default:
				return esc_html__( 'Unknown upload error', 'post-export-import-with-media' );
		}
	}

	/**
	 * Validate uploaded file
	 *
	 * @param array $file File data
	 * @throws Exception If validation fails
	 */
	private function validate_uploaded_file( $file ) {
		// Get PHP upload limits
		$upload_max = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
		$post_max = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		$php_limit = min( $upload_max, $post_max );
		
		// Check file size against PHP limits first
		if ( $file['size'] > $php_limit ) {
			$file_size_mb  = (string) round( $file['size'] / 1024 / 1024, 2 );
			$php_limit_mb  = (string) round( $php_limit / 1024 / 1024, 2 );
			throw new Exception(
				sprintf(
					/* translators: 1: uploaded file size in MB, 2: server upload limit in MB */
					esc_html__( 'File is too large (%1$sMB). Your server upload limit is %2$sMB. Contact your hosting provider to increase upload_max_filesize and post_max_size in php.ini.', 'post-export-import-with-media' ),
					esc_html( $file_size_mb ),
					esc_html( $php_limit_mb )
				)
			);
		}
		
		// Check file size against plugin limit
		if ( $file['size'] > $this->max_file_size ) {
			throw new Exception( esc_html__( 'File is too large. Maximum size is 500MB.', 'post-export-import-with-media' ) );
		}

		// Check file extension
		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $file_extension !== 'zip' ) {
			throw new Exception( esc_html__( 'Only ZIP files are allowed', 'post-export-import-with-media' ) );
		}

		// Check MIME type
		if ( $file['type'] !== 'application/zip' && $file['type'] !== 'application/x-zip-compressed' ) {
			throw new Exception( esc_html__( 'Invalid file type. Only ZIP files are allowed.', 'post-export-import-with-media' ) );
		}
	}

	/**
	 * Delete directory securely
	 *
	 * @param string $dir Directory path
	 */
	private function delete_directory_secure( $dir ) {
		if ( is_dir( $dir ) ) {
			// Use WordPress function for recursive directory removal
			$this->rmdir_recursive( $dir );
		}
	}

	/**
	 * Recursively remove directory
	 *
	 * @param string $dir Directory path
	 */
	private function rmdir_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $path ) ) {
				$this->rmdir_recursive( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Check if file exists in media library
	 *
	 * @param array $file_data File data
	 * @return bool True if exists
	 */
	private function file_exists_in_media_library_secure( $file_data ) {
		// Use full file_path (e.g. 2025/01/image.jpg) for accurate duplicate detection
		// This prevents false positives when same filename exists in different year/month folders
		$check_value = ! empty( $file_data['file_path'] ) ? $file_data['file_path'] : $file_data['filename'];

		$existing = get_posts( array(
			'post_type'      => 'attachment',
			'meta_query'     => array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $check_value,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
		) );

		$result = ! empty( $existing );
		wp_reset_postdata();
		return $result;
	}

	/**
	 * Import media file securely
	 *
	 * @param string $source_file Source file path
	 * @param array  $file_data File data
	 * @return int|WP_Error Attachment ID or error
	 */
	private function import_media_file_secure( $source_file, $file_data ) {
		global $wp_filesystem;

		// Get upload directory and use the original file path structure
		$upload_dir = wp_upload_dir();
		
		// Use the original file path from metadata to maintain directory structure
		// Handle old metadata that might not have file_path
		$original_path = isset( $file_data['file_path'] ) ? $file_data['file_path'] : $file_data['filename'];
		$target_subdir = dirname( $original_path ); // e.g., "2025/11"
		
		// If dirname returns '.' (current directory), use current date structure
		if ( $target_subdir === '.' || empty( $target_subdir ) ) {
			// Use original upload date to preserve year/month directory structure.
			// upload_date is exported in media_data[] — use it before falling back to current date.
			$upload_date   = isset( $file_data['upload_date'] ) && ! empty( $file_data['upload_date'] )
							 ? $file_data['upload_date']
							 : '';
			$target_subdir = ! empty( $upload_date )
							 ? gmdate( 'Y/m', strtotime( $upload_date ) )
							 : gmdate( 'Y/m' ); // Last resort: no date info available
			$original_path = $target_subdir . '/' . $file_data['filename'];
		}
		
		// Convert forward slashes to proper directory separators
		$target_subdir = str_replace( '/', DIRECTORY_SEPARATOR, $target_subdir );
		
		$target_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $target_subdir;
		$target_url = $upload_dir['baseurl'] . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $target_subdir );

		// Create directory if it doesn't exist
		if ( ! is_dir( $target_dir ) ) {
			if ( ! wp_mkdir_p( $target_dir ) ) {
				return new WP_Error( 'mkdir_failed', esc_html__( 'Could not create upload directory', 'post-export-import-with-media' ) );
			}
		}

		$target_file = $target_dir . DIRECTORY_SEPARATOR . sanitize_file_name( $file_data['filename'] );

		// Copy file to target location
		if ( ! file_exists( $source_file ) ) {
			return new WP_Error( 'source_not_found', esc_html__( 'Source file not found', 'post-export-import-with-media' ) );
		}
		
		if ( is_dir( $source_file ) ) {
			return new WP_Error( 'source_is_directory', esc_html__( 'Source is a directory, not a file', 'post-export-import-with-media' ) );
		}
		
		if ( ! copy( $source_file, $target_file ) ) {
			return new WP_Error( 'copy_failed', esc_html__( 'Could not copy file to upload directory', 'post-export-import-with-media' ) );
		}

		// Create attachment
		$attachment_data = array(
			'post_title' => sanitize_text_field( $file_data['title'] ),
			'post_content' => wp_kses_post( $file_data['description'] ),
			'post_status' => 'inherit',
			'post_mime_type' => sanitize_mime_type( $file_data['mime_type'] ),
			'post_date' => isset( $file_data['upload_date'] ) ? sanitize_text_field( $file_data['upload_date'] ) : current_time( 'mysql' ),
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $target_file );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $target_file );
			wp_reset_postdata(); // Reset before returning error
			return $attachment_id;
		}

		// Reset global post data after attachment creation
		wp_reset_postdata();

		// Set the _wp_attached_file meta field correctly
		// This is crucial for WordPress to generate proper URLs
		$relative_path_for_meta = str_replace( DIRECTORY_SEPARATOR, '/', $original_path );
		update_post_meta( $attachment_id, '_wp_attached_file', $relative_path_for_meta );

		// Generate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $target_file );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		
		// Reset global post data after metadata operations
		wp_reset_postdata();

		// Import custom meta if available
		if ( isset( $file_data['meta'] ) && is_array( $file_data['meta'] ) ) {
			foreach ( $file_data['meta'] as $key => $values ) {
				$safe_key = sanitize_key( $key );
				if ( ! empty( $safe_key ) && ! str_starts_with( $safe_key, '_wp_' ) ) {
					foreach ( (array) $values as $value ) {
						add_post_meta( $attachment_id, $safe_key, sanitize_text_field( $value ) );
					}
				}
			}
		}

		return $attachment_id;
	}

	/**
	 * Format file size
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