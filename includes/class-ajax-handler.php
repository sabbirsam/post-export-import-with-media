<?php
/**
 * AJAX Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler Class - Manages all AJAX requests
 */
class PEIWM_Ajax_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Ajax_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Ajax_Handler
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
		// System test
		add_action( 'wp_ajax_peiwm_test_config', array( $this, 'ajax_test_config' ) );
		
		// Media stats
		add_action( 'wp_ajax_peiwm_get_media_stats', array( $this, 'ajax_get_media_stats' ) );
		
		// Clean missing media
		add_action( 'wp_ajax_peiwm_clean_missing_media', array( $this, 'ajax_clean_missing_media' ) );
		
		// Fix missing media paths
		add_action( 'wp_ajax_peiwm_fix_missing_media_paths', array( $this, 'ajax_fix_missing_media_paths' ) );
		
		// Content stats for batch settings
		add_action( 'wp_ajax_peiwm_get_content_stats', array( $this, 'ajax_get_content_stats' ) );
		
		// Download handlers
		add_action( 'admin_post_peiwm_export_posts_download', array( $this, 'download_export_posts' ) );
		add_action( 'admin_post_peiwm_export_media_download', array( $this, 'download_export_media' ) );
		add_action( 'admin_post_peiwm_download_users_export', array( $this, 'download_export_users' ) );
	}

	/**
	 * AJAX: Test system configuration
	 */
	public function ajax_test_config() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$upload_dir = wp_upload_dir();
			
			$config = array(
				'php_version' => phpversion(),
				'wordpress_version' => get_bloginfo( 'version' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'post_max_size' => ini_get( 'post_max_size' ),
				'max_input_time' => ini_get( 'max_input_time' ),
				'max_file_uploads' => ini_get( 'max_file_uploads' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'memory_limit' => ini_get( 'memory_limit' ),
				'current_memory_usage' => memory_get_usage(),
				'peak_memory_usage' => memory_get_peak_usage(),
				'ziparchive_available' => class_exists( 'ZipArchive' ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Used for a read-only health diagnostic check.
				'upload_dir_writable' => is_writable( $upload_dir['basedir'] ),
			);

			wp_send_json_success( $config );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'System test failed', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Get content statistics for batch settings
	 */
	public function ajax_get_content_stats() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$total_posts = wp_count_posts( 'post' );
			$total_pages = wp_count_posts( 'page' );
			$total_media = wp_count_posts( 'attachment' );

			wp_send_json_success( array(
				'total_posts' => $total_posts->publish + $total_posts->draft + $total_posts->pending,
				'total_pages' => $total_pages->publish + $total_pages->draft + $total_pages->pending,
				'total_media' => $total_media->inherit,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to get content statistics', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Get media statistics - memory efficient using IDs only
	 */
	public function ajax_get_media_stats() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			@ini_set( 'memory_limit', '512M' );

			// Use wp_count_posts for fast total count - no memory issue
			$counts     = wp_count_posts( 'attachment' );
			$unique_files = (int) $counts->inherit;

			// Fetch only IDs to calculate sizes - much less memory than full objects
			$attachment_ids = get_posts( array(
				'post_type'              => 'attachment',
				'numberposts'            => -1,
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			$total_size   = 0;
			$unique_size  = 0;
			$file_types   = array();
			$largest_file = array( 'size' => 0, 'name' => '' );
			$total_physical_files = 0;
			$available_files = 0;
			$missing_files = 0;
			$missing_files_list = array();

			foreach ( $attachment_ids as $id ) {
				$file_path = get_attached_file( $id );
				$mime_type = get_post_mime_type( $id );
				if ( $file_path && @file_exists( $file_path ) ) {
					// Count the original file
					$total_physical_files++;
					$available_files++;
					
					$file_size = @filesize( $file_path );
					if ( $file_size === false ) $file_size = 0;
					$total_size += $file_size;
					$unique_size += $file_size; // Track unique files size separately

					$mime = sanitize_mime_type( $mime_type );
					$file_types[ $mime ] = ( $file_types[ $mime ] ?? 0 ) + 1;

					if ( $file_size > $largest_file['size'] ) {
						$largest_file = array(
							'size' => $file_size,
							'name' => sanitize_text_field( basename( $file_path ) ),
						);
					}

					// Count image size variations
					if ( wp_attachment_is_image( $id ) ) {
						$metadata = wp_get_attachment_metadata( $id );
						if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
							foreach ( $metadata['sizes'] as $size_name => $size_data ) {
								if ( ! empty( $size_data['file'] ) ) {
									$size_file_path = path_join( dirname( $file_path ), $size_data['file'] );
									if ( @file_exists( $size_file_path ) ) {
										$total_physical_files++;
										$size_file_size = @filesize( $size_file_path );
										if ( $size_file_size !== false ) {
											$total_size += $size_file_size; // Add to total but NOT to unique
										}
									}
								}
							}
						}
					}
				} else {
					// File is missing from disk
					$missing_files++;
					$post = get_post( $id );
					$missing_files_list[] = array(
						'id'       => $id,
						'title'    => $post ? $post->post_title : 'Unknown',
						'filename' => $file_path ? basename( $file_path ) : 'Unknown',
						'path'     => $file_path ? $file_path : 'Unknown',
						'date'     => $post ? $post->post_date : '',
					);
				}
			}

			arsort( $file_types );

			wp_send_json_success( array(
				'unique_files'         => $unique_files,
				'available_files'      => $available_files,
				'missing_files'        => $missing_files,
				'missing_files_list'   => $missing_files_list,
				'total_physical_files' => $total_physical_files,
				'unique_size'          => $unique_size,
				'unique_size_formatted'=> $this->format_file_size( $unique_size ),
				'total_size'           => $total_size,
				'total_size_formatted' => $this->format_file_size( $total_size ),
				'file_types'           => $file_types,
				'largest_file'         => array(
					'name'           => $largest_file['name'],
					'size'           => $largest_file['size'],
					'size_formatted' => $this->format_file_size( $largest_file['size'] ),
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to get media statistics', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Clean missing media attachments
	 */
	public function ajax_clean_missing_media() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$attachment_ids = get_posts( array(
				'post_type'              => 'attachment',
				'numberposts'            => -1,
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			$deleted_count = 0;
			$deleted_ids = array();
			$errors = array();

			foreach ( $attachment_ids as $id ) {
				$file_path = get_attached_file( $id );
				if ( ! $file_path || ! @file_exists( $file_path ) ) {
					// For missing files, delete post directly to avoid file path issues
					// This is safer than wp_delete_attachment() when paths are corrupted
					try {
						// Delete all metadata first
						$meta_keys = get_post_custom_keys( $id );
						if ( is_array( $meta_keys ) ) {
							foreach ( $meta_keys as $meta_key ) {
								delete_post_meta( $id, $meta_key );
							}
						}
						
						// Delete the post itself
						$result = wp_delete_post( $id, true );
						
						if ( $result ) {
							$deleted_count++;
							$deleted_ids[] = $id;
						}
					} catch ( Exception $e ) {
						$errors[] = 'ID ' . $id . ': ' . $e->getMessage();
					}
				}
			}

			if ( $deleted_count > 0 ) {
				$message = sprintf(
					esc_html__( 'Successfully cleaned %d missing media attachment(s) from database.', 'post-export-import-with-media' ),
					$deleted_count
				);
				
				if ( ! empty( $errors ) ) {
					$message .= ' ' . esc_html__( 'Some errors occurred but most were cleaned.', 'post-export-import-with-media' );
				}
				
				wp_send_json_success( array(
					'deleted_count' => $deleted_count,
					'deleted_ids'   => $deleted_ids,
					'message'       => $message,
				) );
			} else {
				wp_send_json_error( array( 
					'message' => esc_html__( 'No attachments were deleted. They may have already been cleaned.', 'post-export-import-with-media' ) 
				) );
			}

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to clean missing media', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Fix missing media paths (e.g., 202311 -> 2023/11)
	 */
	public function ajax_fix_missing_media_paths() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$attachment_ids = get_posts( array(
				'post_type'              => 'attachment',
				'numberposts'            => -1,
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			$fixed_count = 0;
			$fixed_details = array();
			$upload_dir = wp_upload_dir();
			$upload_base = rtrim( $upload_dir['basedir'], '/\\' );

			foreach ( $attachment_ids as $id ) {
				$file_path = get_attached_file( $id );
				
				// Skip if file exists (no fix needed)
				if ( $file_path && @file_exists( $file_path ) ) {
					continue;
				}

				// Try to fix the path
				if ( $file_path ) {
					// Check if path has format like /202311/ instead of /2023/11/
					if ( preg_match( '#/(\d{4})(\d{2})/#', $file_path, $matches ) ) {
						$year = $matches[1];
						$month = $matches[2];
						
						// Try the corrected path
						$corrected_path = preg_replace( '#/(\d{4})(\d{2})/#', '/$1/$2/', $file_path );
						
						if ( @file_exists( $corrected_path ) ) {
							// File exists with corrected path! Update the database
							update_post_meta( $id, '_wp_attached_file', str_replace( $upload_base, '', $corrected_path ) );
							update_attached_file( $id, $corrected_path );
							
							$fixed_count++;
							$fixed_details[] = array(
								'id'       => $id,
								'old_path' => basename( dirname( $file_path ) ) . '/' . basename( $file_path ),
								'new_path' => $year . '/' . $month . '/' . basename( $file_path ),
							);
						}
					}
				}
			}

			if ( $fixed_count > 0 ) {
				wp_send_json_success( array(
					'fixed_count'   => $fixed_count,
					'fixed_details' => $fixed_details,
					'message'       => sprintf(
						esc_html__( 'Successfully fixed %d media file path(s). Files are now accessible.', 'post-export-import-with-media' ),
						$fixed_count
					),
				) );
			} else {
				wp_send_json_success( array(
					'fixed_count' => 0,
					'message'     => esc_html__( 'No fixable paths found. Missing files may be permanently deleted from server.', 'post-export-import-with-media' ),
				) );
			}

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to fix media paths', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * Download exported posts
	 */
	public function download_export_posts() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'peiwm_download_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'post-export-import-with-media' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'post-export-import-with-media' ) );
		}

		$file_path = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
		
		if ( empty( $file_path ) ) {
			wp_die( esc_html__( 'File not specified', 'post-export-import-with-media' ) );
		}

		$upload_dir = wp_upload_dir();
		$full_path = $upload_dir['basedir'] . '/peiwm-exports/' . basename( $file_path );

		if ( ! file_exists( $full_path ) ) {
			wp_die( esc_html__( 'File not found', 'post-export-import-with-media' ) );
		}

		// SECURITY FIX: Sanitize filename for header to prevent header injection
		$safe_filename = preg_replace( '/[^a-zA-Z0-9._-]/', '', basename( $file_path ) );

		// Set headers for download
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		// Output file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct readfile is required to stream large JSON exports directly to the browser without memory exhaustion.
		readfile( $full_path );
		exit;
	}

	/**
	 * Download exported media
	 */
	public function download_export_media() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'peiwm_download_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'post-export-import-with-media' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'post-export-import-with-media' ) );
		}

		$file_path = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
		
		if ( empty( $file_path ) ) {
			wp_die( esc_html__( 'File not specified', 'post-export-import-with-media' ) );
		}

		$upload_dir = wp_upload_dir();
		$full_path = $upload_dir['basedir'] . '/peiwm-exports/' . basename( $file_path );

		if ( ! file_exists( $full_path ) ) {
			wp_die( esc_html__( 'File not found', 'post-export-import-with-media' ) );
		}

		// SECURITY FIX: Sanitize filename for header to prevent header injection
		$safe_filename = preg_replace( '/[^a-zA-Z0-9._-]/', '', basename( $file_path ) );

		// Increase limits for large file downloads
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );

		// Set headers for download
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		// For large files, use chunked reading to avoid memory issues
		$file_size = filesize( $full_path );
		if ( $file_size > 10 * 1024 * 1024 ) { // If file is larger than 10MB
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct fopen is required to chunk-read large ZIP files without loading them into memory.
			$handle = fopen( $full_path, 'rb' );
			if ( $handle ) {
				while ( ! feof( $handle ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Chunk reading large files.
					echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream output, escaping would corrupt the file
					flush();
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing chunk reader.
				fclose( $handle );
			}
		} else {
			// For smaller files, use readfile
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct readfile is required to stream large ZIP exports directly to the browser without memory exhaustion.
			readfile( $full_path );
		}
		exit;
	}



	/**
	 * Download exported users JSON
	 */
	public function download_export_users() {
		// FIX: Add missing nonce check (was absent unlike other download handlers)
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'peiwm_download_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'post-export-import-with-media' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'post-export-import-with-media' ) );
		}

		$file_path = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';

		if ( empty( $file_path ) ) {
			wp_die( esc_html__( 'File not specified', 'post-export-import-with-media' ) );
		}

		$upload_dir = wp_upload_dir();
		$full_path  = $upload_dir['basedir'] . '/peiwm-exports/' . basename( $file_path );

		if ( ! file_exists( $full_path ) ) {
			wp_die( esc_html__( 'File not found', 'post-export-import-with-media' ) );
		}

		// SECURITY FIX: Sanitize filename for header to prevent header injection
		$safe_filename = preg_replace( '/[^a-zA-Z0-9._-]/', '', basename( $file_path ) );

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );
		readfile( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Convert size string to bytes
	 *
	 * @param string $size Size string (e.g., '128M', '1G')
	 * @return int Size in bytes
	 */
	private function convert_to_bytes( $size ) {
		$size = trim( $size );
		$last = strtolower( $size[ strlen( $size ) - 1 ] );
		$size = (int) $size;

		switch ( $last ) {
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}

		return $size;
	}

	/**
	 * Get directory size
	 *
	 * @param string $directory Directory path
	 * @return int Size in bytes
	 */
	private function get_directory_size( $directory ) {
		$size = 0;
		
		if ( ! is_dir( $directory ) ) {
			return $size;
		}

		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
			}
		}

		return $size;
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