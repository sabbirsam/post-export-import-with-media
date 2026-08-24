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
class PEIWM_Ajax_Handler_Pro {

	/**
	 * Instance
	 *
	 * @var PEIWM_Ajax_Handler_Pro|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Ajax_Handler_Pro
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

		// Override base plugin handlers with PRO versions (Priority 5)
		add_action( 'wp_ajax_peiwm_get_media_library', array( $this, 'ajax_get_media_library_pro' ), 5 );
		add_action( 'wp_ajax_peiwm_update_missing_media', array( $this, 'ajax_update_missing_media_pro' ), 5 );

		// Alt Editor PRO handlers
		add_action( 'wp_ajax_peiwm_load_media_editor', array( $this, 'ajax_load_media_editor_pro' ), 5 );
		add_action( 'wp_ajax_peiwm_save_media_changes', array( $this, 'ajax_save_media_changes_pro' ), 5 );
		add_action( 'wp_ajax_peiwm_export_media_csv', array( $this, 'ajax_export_media_csv_pro' ), 5 );
		add_action( 'wp_ajax_peiwm_import_media_csv', array( $this, 'ajax_import_media_csv_pro' ), 5 );

		// Media Health & Audit handlers
		add_action( 'wp_ajax_peiwm_start_audit', array( $this, 'ajax_start_audit' ) );
		add_action( 'wp_ajax_peiwm_audit_progress', array( $this, 'ajax_audit_progress' ) );
		add_action( 'wp_ajax_peiwm_get_audit_summary', array( $this, 'ajax_get_audit_summary' ) );
		add_action( 'wp_ajax_peiwm_trash_unused_media', array( $this, 'ajax_trash_unused_media_pro' ), 5 );
		add_action( 'wp_ajax_peiwm_update_media_decision', array( $this, 'ajax_update_media_decision_pro' ), 5 );
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
			// error_log('PEIWM DEBUG [PRO]: ajax_get_media_stats STARTING.');
			// error_log('PEIWM DEBUG [PRO]: Initial Memory: ' . memory_get_usage() / 1024 / 1024 . 'MB');

			@ini_set( 'memory_limit', '512M' );
			wp_suspend_cache_addition( true ); // Disable object cache
			global $wpdb;

			// Clear queries to save memory if SAVEQUERIES is on
			$wpdb->queries = array();

			// Use raw SQL to count attachments. wp_count_posts() causes fatal memory errors on huge DBs.
			$unique_files = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'" );

			// Fetch only IDs to calculate sizes - much less memory than full objects
			$batch_size = 50;
			$offset = 0;

			$total_size   = 0;
			$unique_size  = 0;
			$file_types   = array();
			$largest_file = array( 'size' => 0, 'name' => '' );
			$total_physical_files = 0;
			$available_files = 0;
			$missing_files = 0;
			$missing_files_list = array();

			while ( true ) {
				// Bypass WP_Query AND all WordPress helper functions entirely
				// Fetch raw attachment data directly from database to prevent 3rd party plugin hooks
				$attachments_data = $wpdb->get_results( $wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_date, p.post_mime_type, 
					        pm1.meta_value as file_path,
					        pm2.meta_value as metadata
					 FROM {$wpdb->posts} p
					 LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_wp_attached_file'
					 LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_wp_attachment_metadata'
					 WHERE p.post_type = 'attachment' AND p.post_status != 'trash'
					 ORDER BY p.ID ASC
					 LIMIT %d OFFSET %d",
					$batch_size,
					$offset
				), OBJECT );
				
				if ( empty( $attachments_data ) ) {
					break;
				}

				foreach ( $attachments_data as $attachment ) {
					$id = absint( $attachment->ID );
					$mime_type = $attachment->post_mime_type;
					
					// Build full file path manually (same logic as get_attached_file but without hooks)
					$file_path = '';
					if ( ! empty( $attachment->file_path ) ) {
						if ( strpos( $attachment->file_path, '/' ) === 0 || preg_match( '/^[a-zA-Z]:/', $attachment->file_path ) ) {
							// Already absolute path
							$file_path = $attachment->file_path;
						} else {
							// Relative path, prepend upload dir
							$upload_dir = wp_upload_dir();
							$file_path = $upload_dir['basedir'] . '/' . $attachment->file_path;
						}
					}
					
					if ( $file_path && @file_exists( $file_path ) ) {
						// Count the original file
						$total_physical_files++;
						$available_files++;
						
						$file_size = @filesize( $file_path );
						if ( $file_size === false ) $file_size = 0;
						$total_size += $file_size;
						$unique_size += $file_size;

						$mime = sanitize_mime_type( $mime_type );
						$file_types[ $mime ] = ( $file_types[ $mime ] ?? 0 ) + 1;

						if ( $file_size > $largest_file['size'] ) {
							$largest_file = array(
								'size' => $file_size,
								'name' => sanitize_text_field( basename( $file_path ) ),
							);
						}

						// Count image size variations - check if image by mime type
						if ( strpos( $mime_type, 'image/' ) === 0 && ! empty( $attachment->metadata ) ) {
							$metadata = maybe_unserialize( $attachment->metadata );
							if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
								foreach ( $metadata['sizes'] as $size_name => $size_data ) {
									if ( ! empty( $size_data['file'] ) ) {
										$size_file_path = dirname( $file_path ) . '/' . $size_data['file'];
										if ( @file_exists( $size_file_path ) ) {
											$total_physical_files++;
											$size_file_size = @filesize( $size_file_path );
											if ( $size_file_size !== false ) {
												$total_size += $size_file_size;
											}
										}
									}
								}
							}
						}
					} else {
						// File is missing from disk
						$missing_files++;
						if ( count( $missing_files_list ) < 100 ) {
							$missing_files_list[] = array(
								'id'       => $id,
								'title'    => ! empty( $attachment->post_title ) ? $attachment->post_title : 'Unknown',
								'filename' => $file_path ? basename( $file_path ) : 'Unknown',
								'path'     => $file_path ? $file_path : 'Unknown',
								'date'     => ! empty( $attachment->post_date ) ? $attachment->post_date : '',
							);
						}
					}
				}
				
				// Memory cleanup: Clear cache after each batch
				wp_cache_flush();
				wp_reset_postdata();
				$wpdb->queries = array();

				$offset += $batch_size;

				// Safety limit to prevent infinite loops (max 50,000 attachments)
				if ( $offset > 50000 ) {
					break;
				}
			}

			arsort( $file_types );

			wp_suspend_cache_addition( false );

			// error_log('PEIWM DEBUG [PRO]: ajax_get_media_stats FINISHED. Memory: ' . memory_get_usage() / 1024 / 1024 . 'MB. Time elapsed: ' . (microtime(true) - (isset($_SERVER["REQUEST_TIME_FLOAT"]) ? $_SERVER["REQUEST_TIME_FLOAT"] : time())) . 's');

			wp_send_json_success( array(
				'unique_files'         => $unique_files,
				'available_files'      => $available_files,
				'missing_files'        => $missing_files,
				'missing_files_list'   => $missing_files_list,
				'missing_files_note'   => count( $missing_files_list ) < $missing_files 
					? 'Showing first 100 of ' . $missing_files . ' missing files' 
					: '',
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
			$handle = fopen( $full_path, 'rb' );
			if ( $handle ) {
				while ( ! feof( $handle ) ) {
					echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream output, escaping would corrupt the file
					flush();
				}
				fclose( $handle );
			}
		} else {
			// For smaller files, use readfile
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

	/**
	 * AJAX: Get media library items (PRO version - full access with pagination and search)
	 */
	public function ajax_get_media_library_pro() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'PRO version required', 'post-export-import-with-media' ) ) );
		}

		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 50;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$media = array();

		foreach ( $query->posts as $attachment ) {
			$media[] = array(
				'id'        => $attachment->ID,
				'title'     => get_the_title( $attachment->ID ),
				'url'       => wp_get_attachment_url( $attachment->ID ),
				'thumbnail' => wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ),
				'mime_type' => get_post_mime_type( $attachment->ID ),
			);
		}

		wp_send_json_success( array(
			'media'       => $media,
			'has_more'    => $query->max_num_pages > $page,
			'total_pages' => $query->max_num_pages,
			'total'       => $query->found_posts,
			'is_pro'      => true,
		) );
	}

	/**
	 * AJAX: Update missing media (PRO version - full implementation)
	 */
	public function ajax_update_missing_media_pro() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'PRO version required', 'post-export-import-with-media' ) ) );
		}

		$media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
		$type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( ! $media_id || ! $type ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request', 'post-export-import-with-media' ) ) );
		}

		try {
			$upload_dir         = wp_upload_dir();
			$original_file_path = get_post_meta( $media_id, '_wp_attached_file', true );

			if ( ! $original_file_path ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Original file path not found in metadata', 'post-export-import-with-media' ) ) );
			}

			$target_path = $upload_dir['basedir'] . '/' . $original_file_path;
			$target_dir  = dirname( $target_path );

			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			if ( 'library' === $type ) {
				$replacement_id = isset( $_POST['replacement_id'] ) ? absint( $_POST['replacement_id'] ) : 0;
				if ( ! $replacement_id ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Invalid replacement media selection', 'post-export-import-with-media' ) ) );
				}

				$replacement_path = get_attached_file( $replacement_id );
				if ( ! $replacement_path || ! file_exists( $replacement_path ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Replacement file not found on disk', 'post-export-import-with-media' ) ) );
				}

				if ( copy( $replacement_path, $target_path ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attach_data = wp_generate_attachment_metadata( $media_id, $target_path );
					wp_update_attachment_metadata( $media_id, $attach_data );

					wp_send_json_success( array(
						'message'  => sprintf( esc_html__( 'Successfully updated media #%d', 'post-export-import-with-media' ), $media_id ),
						'media_id' => $media_id,
					) );
				} else {
					wp_send_json_error( array( 'message' => esc_html__( 'Failed to copy replacement file', 'post-export-import-with-media' ) ) );
				}

			} elseif ( 'upload' === $type ) {
				if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
					wp_send_json_error( array( 'message' => esc_html__( 'File upload failed', 'post-export-import-with-media' ) ) );
				}

				$uploaded_file = $_FILES['file'];
				$wp_filetype   = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'] );

				if ( ! $wp_filetype['type'] ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type', 'post-export-import-with-media' ) ) );
				}

				if ( move_uploaded_file( $uploaded_file['tmp_name'], $target_path ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attach_data = wp_generate_attachment_metadata( $media_id, $target_path );
					wp_update_attachment_metadata( $media_id, $attach_data );

					wp_send_json_success( array(
						'message'  => sprintf( esc_html__( 'Successfully updated media #%d with uploaded file', 'post-export-import-with-media' ), $media_id ),
						'media_id' => $media_id,
					) );
				} else {
					wp_send_json_error( array( 'message' => esc_html__( 'Failed to move uploaded file to target location', 'post-export-import-with-media' ) ) );
				}

			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid update type', 'post-export-import-with-media' ) ) );
			}

		} catch ( Exception $e ) {
			error_log( 'PEIWM PRO: Update missing media error - ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => esc_html__( 'Update failed: ', 'post-export-import-with-media' ) . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Load media for editor (PRO)
	 */
	public function ajax_load_media_editor_pro() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 100;
		$search     = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$alt_filter = isset( $_POST['alt_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_filter'] ) ) : 'all';
		$sort_by    = isset( $_POST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : 'date_desc';

		global $wpdb;

		if ( 'empty_alt' === $alt_filter ) {
			$where = "p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image%'";

			if ( ! empty( $search ) ) {
				$search_escaped = $wpdb->esc_like( $search );
				$where .= $wpdb->prepare( " AND (p.post_title LIKE %s OR p.post_name LIKE %s)", '%' . $search_escaped . '%', '%' . $search_escaped . '%' );
			}

			$sql = "
				SELECT p.ID 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
				WHERE {$where}
				AND (pm.meta_value IS NULL OR pm.meta_value = '')
			";

			$orderby = 'p.post_date DESC';
			switch ( $sort_by ) {
				case 'date_asc':
					$orderby = 'p.post_date ASC';
					break;
				case 'modified_asc':
					$orderby = 'p.post_modified ASC';
					break;
				case 'modified_desc':
					$orderby = 'p.post_modified DESC';
					break;
				case 'title_asc':
					$orderby = 'p.post_title ASC';
					break;
				case 'title_desc':
					$orderby = 'p.post_title DESC';
					break;
				case 'url_asc':
					$orderby = 'p.post_name ASC';
					break;
			}

			$sql .= " ORDER BY {$orderby} LIMIT {$batch_size} OFFSET {$offset}";
			$attachment_ids = $wpdb->get_col( $sql );

			$count_sql = "
				SELECT COUNT(p.ID) 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
				WHERE {$where}
				AND (pm.meta_value IS NULL OR pm.meta_value = '')
			";
			$total_count = (int) $wpdb->get_var( $count_sql );

		} else {
			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'fields'         => 'ids',
			);

			if ( ! empty( $search ) ) {
				$args['s'] = $search;
			}

			switch ( $sort_by ) {
				case 'date_asc':
					$args['orderby'] = 'date';
					$args['order']   = 'ASC';
					break;
				case 'date_desc':
					$args['orderby'] = 'date';
					$args['order']   = 'DESC';
					break;
				case 'modified_asc':
					$args['orderby'] = 'modified';
					$args['order']   = 'ASC';
					break;
				case 'modified_desc':
					$args['orderby'] = 'modified';
					$args['order']   = 'DESC';
					break;
				case 'title_asc':
					$args['orderby'] = 'title';
					$args['order']   = 'ASC';
					break;
				case 'title_desc':
					$args['orderby'] = 'title';
					$args['order']   = 'DESC';
					break;
				case 'url_asc':
					$args['orderby'] = 'name';
					$args['order']   = 'ASC';
					break;
				default:
					$args['orderby'] = 'date';
					$args['order']   = 'DESC';
			}

			$query          = new WP_Query( $args );
			$attachment_ids = $query->posts;

			$count_args = $args;
			unset( $count_args['posts_per_page'], $count_args['offset'], $count_args['fields'] );
			$count_args['posts_per_page'] = -1;
			$count_query                  = new WP_Query( $count_args );
			$total_count                  = $count_query->post_count;
		}

		$upload_dir  = wp_get_upload_dir();
		$media_items = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment ) continue;

			$alt_text  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
			$file_url  = wp_get_attachment_url( $attachment_id );
			$file_url  = $file_url ? $file_url : '';
			$edit_url  = get_edit_post_link( $attachment_id, 'raw' );
			if ( ! $edit_url ) {
				$edit_url = admin_url( 'post.php?post=' . $attachment_id . '&action=edit' );
			}

			$media_items[] = array(
				'id'       => $attachment_id,
				'title'    => $attachment->post_title,
				'alt'      => $alt_text ? $alt_text : '',
				'thumb'    => $thumb_url ? $thumb_url : '',
				'url'      => $file_url,
				'edit_url' => $edit_url,
				'filename' => ! empty( $file_url ) ? basename( $file_url ) : '',
				'date'     => get_the_date( 'Y-m-d', $attachment_id ),
				'path'     => ! empty( $file_url ) ? str_replace( $upload_dir['baseurl'], '', $file_url ) : '',
			);
		}

		wp_send_json_success( array(
			'media'       => $media_items,
			'total_count' => $total_count,
			'loaded'      => $offset + count( $media_items ),
			'has_more'    => ( $offset + count( $media_items ) ) < $total_count,
		) );
	}

	/**
	 * AJAX: Save media changes (PRO)
	 */
	public function ajax_save_media_changes_pro() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$changes = isset( $_POST['changes'] ) ? json_decode( stripslashes( $_POST['changes'] ), true ) : array();

		if ( empty( $changes ) || ! is_array( $changes ) ) {
			wp_send_json_error( array( 'message' => 'No changes provided' ) );
		}

		$updated = 0;
		$errors  = array();

		foreach ( $changes as $change ) {
			if ( ! isset( $change['id'] ) ) {
				continue;
			}

			$attachment_id = absint( $change['id'] );

			if ( ! get_post( $attachment_id ) ) {
				$errors[] = sprintf( 'Attachment ID %d not found', $attachment_id );
				continue;
			}

			if ( isset( $change['title'] ) ) {
				$result = wp_update_post( array(
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( $change['title'] ),
				), true );

				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 'Failed to update title for ID %d: %s', $attachment_id, $result->get_error_message() );
				}
			}

			if ( isset( $change['alt'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $change['alt'] ) );
			}

			$updated++;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_success( array(
				'message' => sprintf( 'Updated %d items with %d errors', $updated, count( $errors ) ),
				'updated' => $updated,
				'errors'  => $errors,
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( 'Successfully updated %d media items', $updated ),
			'updated' => $updated,
		) );
	}

	/**
	 * AJAX: Export media CSV (PRO)
	 */
	public function ajax_export_media_csv_pro() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$attachment_ids = get_posts( $args );
		$upload_dir     = wp_get_upload_dir();

		$csv_data   = array();
		$csv_data[] = array( 'ID', 'Path', 'Filename', 'URL', 'Title', 'ALT' );

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			$alt_text   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$file_url   = wp_get_attachment_url( $attachment_id );
			$file_path  = str_replace( $upload_dir['baseurl'], '', $file_url );

			$csv_data[] = array(
				$attachment_id,
				$file_path,
				basename( $file_url ),
				$file_url,
				$attachment->post_title,
				$alt_text ? $alt_text : '',
			);
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=media-export-' . date( 'Y-m-d-His' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

		foreach ( $csv_data as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Import media CSV (PRO)
	 */
	public function ajax_import_media_csv_pro() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => 'File upload failed' ) );
		}

		$file     = $_FILES['csv_file']['tmp_name'];
		$csv_data = array();

		if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				$csv_data[] = $row;
			}
			fclose( $handle );
		}

		if ( empty( $csv_data ) ) {
			wp_send_json_error( array( 'message' => 'CSV file is empty' ) );
		}

		$header       = array_shift( $csv_data );
		$id_idx       = array_search( 'ID', $header, true );
		$path_idx     = array_search( 'Path', $header, true );
		$filename_idx = array_search( 'Filename', $header, true );
		$url_idx      = array_search( 'URL', $header, true );
		$title_idx    = array_search( 'Title', $header, true );
		$alt_idx      = array_search( 'ALT', $header, true );

		if ( false === $title_idx && false === $alt_idx ) {
			wp_send_json_error( array( 'message' => 'CSV must contain Title or ALT column' ) );
		}

		$updated   = 0;
		$skipped   = 0;
		$not_found = 0;

		foreach ( $csv_data as $row ) {
			$attachment_id = null;

			if ( false !== $id_idx && ! empty( $row[ $id_idx ] ) ) {
				$test_id = absint( $row[ $id_idx ] );
				if ( get_post( $test_id ) && 'attachment' === get_post_type( $test_id ) ) {
					$attachment_id = $test_id;
				}
			}

			if ( ! $attachment_id ) {
				if ( false !== $path_idx && ! empty( $row[ $path_idx ] ) ) {
					$attachment_id = $this->find_attachment_by_path( $row[ $path_idx ] );
				}
				if ( ! $attachment_id && false !== $filename_idx && ! empty( $row[ $filename_idx ] ) ) {
					$attachment_id = $this->find_attachment_by_filename( $row[ $filename_idx ] );
				}
				if ( ! $attachment_id && false !== $url_idx && ! empty( $row[ $url_idx ] ) ) {
					$attachment_id = attachment_url_to_postid( $row[ $url_idx ] );
				}
			}

			if ( ! $attachment_id ) {
				$not_found++;
				continue;
			}

			$has_changes = false;

			if ( false !== $title_idx && isset( $row[ $title_idx ] ) ) {
				$new_title     = sanitize_text_field( $row[ $title_idx ] );
				$current_title = get_the_title( $attachment_id );

				if ( $new_title !== $current_title ) {
					wp_update_post( array(
						'ID'         => $attachment_id,
						'post_title' => $new_title,
					) );
					$has_changes = true;
				}
			}

			if ( false !== $alt_idx && isset( $row[ $alt_idx ] ) ) {
				$new_alt     = sanitize_text_field( $row[ $alt_idx ] );
				$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

				if ( $new_alt !== $current_alt ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $new_alt );
					$has_changes = true;
				}
			}

			if ( $has_changes ) {
				$updated++;
			} else {
				$skipped++;
			}
		}

		wp_send_json_success( array(
			'message'   => sprintf( 'Import complete: %d updated, %d skipped, %d not found', $updated, $skipped, $not_found ),
			'updated'   => $updated,
			'skipped'   => $skipped,
			'not_found' => $not_found,
		) );
	}

	/**
	 * Find attachment by path
	 */
	private function find_attachment_by_path( $path ) {
		$upload_dir = wp_get_upload_dir();
		$full_url   = $upload_dir['baseurl'] . $path;

		$attachment_id = attachment_url_to_postid( $full_url );

		if ( $attachment_id ) {
			return $attachment_id;
		}

		$filename = basename( $path );
		return $this->find_attachment_by_filename( $filename );
	}

	/**
	 * Find attachment by filename
	 */
	private function find_attachment_by_filename( $filename ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
			'%' . $wpdb->esc_like( $filename )
		) );

		return $attachment_id ? (int) $attachment_id : null;
	}

	/**
	 * AJAX: Start Media Audit Scan
	 */
	public function ajax_start_audit() {
		if ( class_exists( 'PEIWM_Ajax_Handler' ) ) {
			$base = PEIWM_Ajax_Handler::get_instance();
			$base->ajax_start_audit();
		}
	}

	/**
	 * AJAX: Step / Poll Progress of Media Audit Scan
	 */
	public function ajax_audit_progress() {
		if ( class_exists( 'PEIWM_Ajax_Handler' ) ) {
			$base = PEIWM_Ajax_Handler::get_instance();
			$base->ajax_audit_progress();
		}
	}

	/**
	 * AJAX: Get Media Audit Summary
	 */
	public function ajax_get_audit_summary() {
		if ( class_exists( 'PEIWM_Ajax_Handler' ) ) {
			$base = PEIWM_Ajax_Handler::get_instance();
			$base->ajax_get_audit_summary();
		}
	}

	/**
	 * AJAX: Move Unused Media Item to Trash (PRO)
	 */
	public function ajax_trash_unused_media_pro() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid attachment ID', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'delete_post', $attachment_id ) && ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		global $wpdb;
		$table_reports   = $wpdb->prefix . 'peiwm_media_reports';
		$table_decisions = $wpdb->prefix . 'peiwm_media_decisions';

		$post = get_post( $attachment_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			$wpdb->update(
				$table_reports,
				array(
					'status'        => 'trashed',
					'user_decision' => 'trashed',
				),
				array( 'attachment_id' => $attachment_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			wp_send_json_success( array(
				'attachment_id' => $attachment_id,
				'message'       => sprintf( esc_html__( 'Media #%d record updated.', 'post-export-import-with-media' ), $attachment_id ),
			) );
		}

		$result = wp_trash_post( $attachment_id );
		if ( ! $result ) {
			$result = wp_delete_attachment( $attachment_id, false );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to move media to Trash', 'post-export-import-with-media' ) ) );
		}

		$wpdb->update(
			$table_reports,
			array(
				'status'        => 'trashed',
				'user_decision' => 'trashed',
			),
			array( 'attachment_id' => $attachment_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$wpdb->replace(
			$table_decisions,
			array(
				'attachment_id' => $attachment_id,
				'decision'      => 'trashed',
				'decided_at'    => current_time( 'mysql' ),
				'decided_by'    => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%d' )
		);

		wp_send_json_success( array(
			'attachment_id' => $attachment_id,
			'message'       => sprintf( esc_html__( 'Successfully moved media #%d to Trash', 'post-export-import-with-media' ), $attachment_id ),
		) );
	}

	/**
	 * AJAX: Update Media Decision PRO (Trash Decision Handler)
	 */
	public function ajax_update_media_decision_pro() {
		$decision = isset( $_POST['decision'] ) ? sanitize_text_field( wp_unslash( $_POST['decision'] ) ) : '';
		if ( 'trash' !== $decision ) {
			return; // Handled by free handler for safe/exclude
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$raw_ids        = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
		$attachment_ids = array_map( 'absint', $raw_ids );
		$attachment_ids = array_filter( $attachment_ids );

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No media items selected', 'post-export-import-with-media' ) ) );
		}

		global $wpdb;
		$table_reports   = $wpdb->prefix . 'peiwm_media_reports';
		$table_decisions = $wpdb->prefix . 'peiwm_media_decisions';
		$user_id         = get_current_user_id();
		$now             = current_time( 'mysql' );

		$processed = 0;
		foreach ( $attachment_ids as $att_id ) {
			$post = get_post( $att_id );
			if ( $post && 'trash' !== $post->post_status ) {
				$res = wp_trash_post( $att_id );
				if ( ! $res ) {
					wp_delete_attachment( $att_id, false );
				}
			}
			$wpdb->update(
				$table_reports,
				array(
					'status'        => 'trashed',
					'user_decision' => 'trashed',
				),
				array( 'attachment_id' => $att_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			$wpdb->replace(
				$table_decisions,
				array(
					'attachment_id' => $att_id,
					'decision'      => 'trashed',
					'decided_at'    => $now,
					'decided_by'    => $user_id,
				),
				array( '%d', '%s', '%s', '%d' )
			);
			$processed++;
		}

		wp_send_json_success( array(
			'count'   => $processed,
			'message' => sprintf( esc_html__( 'Successfully moved %d media item(s) to Trash.', 'post-export-import-with-media' ), $processed ),
		) );
	}
}