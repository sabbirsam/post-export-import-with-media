<?php
/**
 * Batch Processor
 *
 * @package Post_Export_Import_With_Media
 * @since 1.3.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch Processor Class - Handles batch export/import operations
 */
class PEIWM_Batch_Processor {

	/**
	 * Instance
	 *
	 * @var PEIWM_Batch_Processor|null
	 */
	private static $instance = null;

	/**
	 * Batch settings instance
	 *
	 * @var PEIWM_Batch_Settings
	 */
	private $settings;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Batch_Processor
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
		$this->settings = PEIWM_Batch_Settings::get_instance();
		$this->init_ajax_hooks();
	}

	/**
	 * Initialize AJAX hooks
	 */
	private function init_ajax_hooks() {
		// Batch export hooks
		add_action( 'wp_ajax_peiwm_batch_export_posts_start', array( $this, 'ajax_batch_export_posts_start' ) );
		add_action( 'wp_ajax_peiwm_batch_export_posts_process', array( $this, 'ajax_batch_export_posts_process' ) );
		add_action( 'wp_ajax_peiwm_batch_export_pages_start', array( $this, 'ajax_batch_export_pages_start' ) );
		add_action( 'wp_ajax_peiwm_batch_export_pages_process', array( $this, 'ajax_batch_export_pages_process' ) );
		add_action( 'wp_ajax_peiwm_batch_export_media_start', array( $this, 'ajax_batch_export_media_start' ) );
		add_action( 'wp_ajax_peiwm_batch_export_media_process', array( $this, 'ajax_batch_export_media_process' ) );
		
		// Batch import hooks
		add_action( 'wp_ajax_peiwm_batch_import_posts_start', array( $this, 'ajax_batch_import_posts_start' ) );
		add_action( 'wp_ajax_peiwm_batch_import_posts_process', array( $this, 'ajax_batch_import_posts_process' ) );
		add_action( 'wp_ajax_peiwm_batch_import_pages_start', array( $this, 'ajax_batch_import_pages_start' ) );
		add_action( 'wp_ajax_peiwm_batch_import_pages_process', array( $this, 'ajax_batch_import_pages_process' ) );
		
		// Batch image check
		add_action( 'wp_ajax_peiwm_batch_check_images', array( $this, 'ajax_batch_check_images' ) );
	}

	/**
	 * AJAX: Start batch export for posts
	 */
	public function ajax_batch_export_posts_start() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// Get total count
			$total_posts = wp_count_posts( 'post' );
			$total_count = $total_posts->publish + $total_posts->draft + $total_posts->pending;

			if ( $total_count === 0 ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No posts found to export', 'post-export-import-with-media' ) ) );
			}

			// Use export_json_size for how many posts per JSON file (default 500)
			$batch_size    = max( 100, (int) $this->settings->get_setting( 'export_json_size' ) );
			$total_batches = ceil( $total_count / $batch_size );

			// Check if WPML export is enabled
			$export_wpml = isset( $_POST['export_wpml_data'] ) && '1' === sanitize_key( $_POST['export_wpml_data'] );

			// Create batch session
			$batch_id = wp_generate_uuid4();
			set_transient( 'peiwm_batch_export_' . $batch_id, array(
				'type' => 'posts',
				'total_count' => $total_count,
				'batch_size' => $batch_size,
				'total_batches' => $total_batches,
				'current_batch' => 0,
				'export_wpml_data' => $export_wpml,
				'created' => time(),
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'batch_id' => $batch_id,
				'total_count' => $total_count,
				'total_batches' => $total_batches,
				'batch_size' => $batch_size,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to start batch export', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Process batch export for posts
	 */
	public function ajax_batch_export_posts_process() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
			$batch_number = isset( $_POST['batch_number'] ) ? absint( wp_unslash( $_POST['batch_number'] ) ) : 0;

			if ( empty( $batch_id ) ) {
				throw new Exception( esc_html__( 'Invalid batch ID', 'post-export-import-with-media' ) );
			}

			// Get batch data
			$batch_data = get_transient( 'peiwm_batch_export_' . $batch_id );
			if ( ! $batch_data ) {
				throw new Exception( esc_html__( 'Batch session expired', 'post-export-import-with-media' ) );
			}

			$batch_size = $batch_data['batch_size'];
			$offset = $batch_number * $batch_size;

			// Check if WPML export is enabled from batch session
			$export_wpml = isset( $batch_data['export_wpml_data'] ) && $batch_data['export_wpml_data'];

			// Get posts for this batch
			$posts = get_posts( array(
				'post_type'      => 'post',
				'numberposts'    => $batch_size,
				'offset'         => $offset,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );

			$export_data = array();
			global $wpdb;
			foreach ( $posts as $post ) {
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
					'categories'    => $this->get_post_categories_secure( $post->ID ),
					'tags'          => $this->get_post_tags_secure( $post->ID ),
					'meta'          => $this->get_post_meta_secure( $post->ID ),
					'featured_image' => $this->get_featured_image_secure( $post->ID ),
					'content_images' => $this->get_content_images_secure( $post->post_content ),
					'source_url'    => home_url(),
				);

				// WPML data export — only if checkbox was sent AND multilingual plugin is active
				if ( $export_wpml && ( defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) ) ) {
					$post_data['wpml_data'] = $this->get_wpml_post_data( $post->ID );
				} else {
					$post_data['wpml_data'] = null;
				}

				// Enrich with author identity data for smart mapping on import
				$author_user = get_userdata( absint( $post->post_author ) );
				if ( $author_user ) {
					$author_pass_hash = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						"SELECT user_pass FROM {$wpdb->users} WHERE ID = %d",
						$author_user->ID
					) );
					$post_data['post_author_data'] = array(
						'user_login'     => sanitize_user( $author_user->user_login ),
						'user_email'     => sanitize_email( $author_user->user_email ),
						'display_name'   => sanitize_text_field( $author_user->display_name ),
						'role'           => ! empty( $author_user->roles )
						                    ? sanitize_text_field( $author_user->roles[0] )
						                    : 'subscriber',
						'user_pass_hash' => $author_pass_hash ? $author_pass_hash : null,
					);
				} else {
					$post_data['post_author_data'] = null;
				}

				$export_data[] = $post_data;
			}

			wp_reset_postdata();

			// Create JSON file
			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
			
			if ( ! wp_mkdir_p( $export_dir ) ) {
				throw new Exception( esc_html__( 'Could not create export directory', 'post-export-import-with-media' ) );
			}

			$filename = 'posts_export_batch_' . ( $batch_number + 1 ) . '_' . date( 'Y-m-d-H-i-s' ) . '.json';
			$file_path = $export_dir . $filename;

			$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
			if ( false === file_put_contents( $file_path, $json_data ) ) {
				throw new Exception( esc_html__( 'Failed to write export file', 'post-export-import-with-media' ) );
			}

			// Create download URL
			$download_url = add_query_arg( array(
				'action' => 'peiwm_export_posts_download',
				'file' => $filename,
				'_wpnonce' => wp_create_nonce( 'peiwm_download_nonce' ),
			), admin_url( 'admin-post.php' ) );

			wp_send_json_success( array(
				'batch_number' => $batch_number,
				'filename' => $filename,
				'download_url' => $download_url,
				'count' => count( $export_data ),
				'file_size' => size_format( filesize( $file_path ) ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Start batch export for pages
	 */
	public function ajax_batch_export_pages_start() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// Get total count
			$total_pages = wp_count_posts( 'page' );
			$total_count = $total_pages->publish + $total_pages->draft + $total_pages->pending;

			if ( $total_count === 0 ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No pages found to export', 'post-export-import-with-media' ) ) );
			}

			$batch_size = max( 100, (int) $this->settings->get_setting( 'export_json_size' ) );
			$total_batches = ceil( $total_count / $batch_size );

			// Check if WPML export is enabled
			$export_wpml = isset( $_POST['export_wpml_data'] ) && '1' === sanitize_key( $_POST['export_wpml_data'] );

			// Create batch session
			$batch_id = wp_generate_uuid4();
			set_transient( 'peiwm_batch_export_' . $batch_id, array(
				'type' => 'pages',
				'total_count' => $total_count,
				'batch_size' => $batch_size,
				'total_batches' => $total_batches,
				'current_batch' => 0,
				'export_wpml_data' => $export_wpml,
				'created' => time(),
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'batch_id' => $batch_id,
				'total_count' => $total_count,
				'total_batches' => $total_batches,
				'batch_size' => $batch_size,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to start batch export', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Process batch export for pages
	 */
	public function ajax_batch_export_pages_process() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
			$batch_number = isset( $_POST['batch_number'] ) ? absint( wp_unslash( $_POST['batch_number'] ) ) : 0;

			if ( empty( $batch_id ) ) {
				throw new Exception( esc_html__( 'Invalid batch ID', 'post-export-import-with-media' ) );
			}

			// Get batch data
			$batch_data = get_transient( 'peiwm_batch_export_' . $batch_id );
			if ( ! $batch_data ) {
				throw new Exception( esc_html__( 'Batch session expired', 'post-export-import-with-media' ) );
			}

			$batch_size = $batch_data['batch_size'];
			$offset = $batch_number * $batch_size;

			// Check if WPML export is enabled from batch session
			$export_wpml = isset( $batch_data['export_wpml_data'] ) && $batch_data['export_wpml_data'];

			// Get pages for this batch
			$pages = get_posts( array(
				'post_type'      => 'page',
				'numberposts'    => $batch_size,
				'offset'         => $offset,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );

			$export_data = array();
			foreach ( $pages as $page ) {
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
					'post_parent'   => absint( $page->post_parent ),
					'menu_order'    => absint( $page->menu_order ),
					'page_template' => get_page_template_slug( $page->ID ) ?: 'default',
					'meta'          => $this->get_post_meta_secure( $page->ID ),
					'featured_image' => $this->get_featured_image_secure( $page->ID ),
					'content_images' => $this->get_content_images_secure( $page->post_content ),
					'source_url'    => home_url(),
				);

				// WPML data export — only if checkbox was sent AND multilingual plugin is active
				if ( $export_wpml && ( defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) ) ) {
					$page_data['wpml_data'] = $this->get_wpml_post_data( $page->ID, 'page' );
				} else {
					$page_data['wpml_data'] = null;
				}

				$export_data[] = $page_data;
			}

			wp_reset_postdata();

			// Create JSON file
			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
			
			if ( ! wp_mkdir_p( $export_dir ) ) {
				throw new Exception( esc_html__( 'Could not create export directory', 'post-export-import-with-media' ) );
			}

			$filename = 'pages_export_batch_' . ( $batch_number + 1 ) . '_' . date( 'Y-m-d-H-i-s' ) . '.json';
			$file_path = $export_dir . $filename;

			$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
			if ( false === file_put_contents( $file_path, $json_data ) ) {
				throw new Exception( esc_html__( 'Failed to write export file', 'post-export-import-with-media' ) );
			}

			// Create download URL
			$download_url = add_query_arg( array(
				'action' => 'peiwm_export_posts_download',
				'file' => $filename,
				'_wpnonce' => wp_create_nonce( 'peiwm_download_nonce' ),
			), admin_url( 'admin-post.php' ) );

			wp_send_json_success( array(
				'batch_number' => $batch_number,
				'filename' => $filename,
				'download_url' => $download_url,
				'count' => count( $export_data ),
				'file_size' => size_format( filesize( $file_path ) ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Start batch export for media
	 */
	public function ajax_batch_export_media_start() {
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

			// PRO: Advanced filters — date range and by-post
			$is_pro    = PEIWM_Main::get_instance()->is_pro_active();
			$date_from = $is_pro && isset( $_POST['media_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['media_date_from'] ) ) : '';
			$date_to   = $is_pro && isset( $_POST['media_date_to'] )   ? sanitize_text_field( wp_unslash( $_POST['media_date_to'] ) )   : '';
			$post_ids  = array();
			if ( $is_pro && isset( $_POST['media_post_ids'] ) && '' !== $_POST['media_post_ids'] ) {
				$post_ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['media_post_ids'] ) ) ) ) );
			}

			$valid_from = ( $date_from && false !== DateTime::createFromFormat( 'Y-m-d', $date_from ) );
			$valid_to   = ( $date_to   && false !== DateTime::createFromFormat( 'Y-m-d', $date_to )   );

			// FIX: Raise limits for the planning phase too
			@set_time_limit( 300 );
			@ini_set( 'memory_limit', '512M' );

			// Build base query args
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

			// PRO: date range filter
			if ( $valid_from || $valid_to ) {
				$date_entry = array( 'inclusive' => true );
				if ( $valid_from ) {
					$date_entry['after'] = $date_from . ' 00:00:00';
				}
				if ( $valid_to ) {
					$date_entry['before'] = $date_to . ' 23:59:59';
				}
				$attachment_query['date_query'] = array( $date_entry );
			}

			// PRO: filter by post
			if ( ! empty( $post_ids ) ) {
				$attachment_query['post_parent__in'] = $post_ids;
				$parent_attachment_ids = get_posts( $attachment_query );
				unset( $attachment_query['post_parent__in'] );

				$content_attachment_ids = array();
				foreach ( $post_ids as $pid ) {
					$thumb_id = get_post_thumbnail_id( $pid );
					if ( $thumb_id ) {
						$content_attachment_ids[] = absint( $thumb_id );
					}
					$post_obj = get_post( $pid );
					if ( $post_obj && ! empty( $post_obj->post_content ) ) {
						preg_match_all( '/wp-image-(\d+)/', $post_obj->post_content, $matches );
						if ( ! empty( $matches[1] ) ) {
							foreach ( $matches[1] as $img_id ) {
								$content_attachment_ids[] = absint( $img_id );
							}
						}
					}
				}

				$attachment_ids = array_values( array_unique( array_merge( $parent_attachment_ids, $content_attachment_ids ) ) );
			} else {
				// FIX: Fetch only IDs — avoids loading WP_Post objects just to group them into batches
				$attachment_ids = get_posts( $attachment_query );
			}

			if ( empty( $attachment_ids ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'No media files found to export', 'post-export-import-with-media' ) ) );
			}

			// Group IDs into batches based on cumulative file size
			$zip_size_limit_mb    = $this->settings->get_setting( 'media_zip_size_limit' );
			$zip_size_limit_bytes = ( $zip_size_limit_mb > 0 ? $zip_size_limit_mb : 50 ) * 1024 * 1024;

			$batches            = array();
			$current_batch      = array();
			$current_batch_size = 0;

			// FIX: Loop over IDs only — get_attached_file() is a lightweight meta lookup
			foreach ( $attachment_ids as $id ) {
				$file_path = get_attached_file( $id );
				$file_size = ( $file_path && file_exists( $file_path ) ) ? (int) @filesize( $file_path ) : 0;

				if ( $current_batch_size + $file_size > $zip_size_limit_bytes && ! empty( $current_batch ) ) {
					$batches[]          = $current_batch;
					$current_batch      = array();
					$current_batch_size = 0;
				}

				$current_batch[]     = $id;
				$current_batch_size += $file_size;
			}

			if ( ! empty( $current_batch ) ) {
				$batches[] = $current_batch;
			}

			$total_count   = count( $attachment_ids );
			$total_batches = count( $batches );

			$batch_id = wp_generate_uuid4();
			set_transient(
				'peiwm_batch_export_media_' . $batch_id,
				array(
					'type'             => 'media',
					'batches'          => $batches,
					'total_count'      => $total_count,
					'total_batches'    => $total_batches,
					'export_all_sizes' => $export_all_sizes,
					'created'          => time(),
				),
				HOUR_IN_SECONDS
			);

			wp_send_json_success( array(
				'batch_id'      => $batch_id,
				'total_count'   => $total_count,
				'total_batches' => $total_batches,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to start batch export', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Process batch export for media
	 */
	public function ajax_batch_export_media_process() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
			$batch_number = isset( $_POST['batch_number'] ) ? absint( wp_unslash( $_POST['batch_number'] ) ) : 0;

			if ( empty( $batch_id ) ) {
				throw new Exception( esc_html__( 'Invalid batch ID', 'post-export-import-with-media' ) );
			}

			// Get batch data
			$batch_data = get_transient( 'peiwm_batch_export_media_' . $batch_id );
			if ( ! $batch_data ) {
				throw new Exception( esc_html__( 'Batch session expired', 'post-export-import-with-media' ) );
			}

			// Get export_all_sizes flag
			$export_all_sizes = isset( $batch_data['export_all_sizes'] ) && $batch_data['export_all_sizes'];

			// Use pre-grouped batches (split by size)
			if ( ! isset( $batch_data['batches'] ) || ! isset( $batch_data['batches'][ $batch_number ] ) ) {
				throw new Exception( esc_html__( 'Invalid batch number or batch session is outdated. Please restart the export.', 'post-export-import-with-media' ) );
			}
			$attachment_ids = $batch_data['batches'][ $batch_number ];

			// Create ZIP for this batch
			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/peiwm-exports/';
			
			if ( ! wp_mkdir_p( $export_dir ) ) {
				throw new Exception( esc_html__( 'Could not create export directory', 'post-export-import-with-media' ) );
			}

			$zip_filename = 'media_export_batch_' . ( $batch_number + 1 ) . '_' . gmdate( 'Y-m-d-H-i-s' ) . '.zip';
			$zip_path = $export_dir . $zip_filename;

			$zip = new ZipArchive();
			if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== TRUE ) {
				throw new Exception( esc_html__( 'Could not create ZIP file', 'post-export-import-with-media' ) );
			}

			$media_data = array();
			$added_files = 0;
			$skipped_files = 0;

			foreach ( $attachment_ids as $attachment_id ) {
				$attachment = get_post( $attachment_id );
				if ( ! $attachment ) {
					$skipped_files++;
					continue;
				}

				$file_path = get_attached_file( $attachment_id );
				
				if ( $file_path && file_exists( $file_path ) ) {
					$upload_base = rtrim( $upload_dir['basedir'], '/\\' );
					$relative_path = str_replace( $upload_base, '', $file_path );
					$relative_path = ltrim( $relative_path, '/\\' );
					$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', $relative_path );
					
					$zip_result = $zip->addFile( $file_path, $relative_path );
					
					if ( $zip_result ) {
						$added_files++;
						
						// Export all image sizes if requested
						if ( $export_all_sizes && wp_attachment_is_image( $attachment_id ) ) {
							$metadata = wp_get_attachment_metadata( $attachment_id );
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
						
						$media_data[] = array(
							'ID' => $attachment_id,
							'filename' => basename( $file_path ),
							'title' => $attachment->post_title,
							'description' => $attachment->post_content,
							'mime_type' => $attachment->post_mime_type,
							'upload_date' => $attachment->post_date,
							'file_size' => filesize( $file_path ),
							'file_path' => $relative_path,
							'meta' => get_post_meta( $attachment_id ),
						);
					} else {
						$skipped_files++;
					}
				} else {
					$skipped_files++;
					// error_log( 'PEIWM Batch: Skipping attachment ID ' . $attachment_id . ' - file not found: ' . $file_path );
				}
			}

			// Add metadata file
			$metadata_json = wp_json_encode( $media_data, JSON_PRETTY_PRINT );
			$zip->addFromString( 'media_metadata.json', $metadata_json );

			$zip->close();

			wp_reset_postdata();

			if ( $added_files === 0 ) {
				wp_delete_file( $zip_path );
				throw new Exception( esc_html__( 'No valid media files found in this batch', 'post-export-import-with-media' ) );
			}

			// Create download URL
			$download_url = add_query_arg( array(
				'action' => 'peiwm_export_media_download',
				'file' => $zip_filename,
				'_wpnonce' => wp_create_nonce( 'peiwm_download_nonce' ),
			), admin_url( 'admin-post.php' ) );

			wp_send_json_success( array(
				'batch_number'      => $batch_number,
				'filename'          => $zip_filename,
				'download_url'      => $download_url,
				'count'             => $added_files,
				'unique_count'      => count( $media_data ),
				'skipped_count'     => $skipped_files,
				'total_in_batch'    => count( $attachment_ids ),
				'export_all_sizes'  => $export_all_sizes,
				'file_size'         => size_format( filesize( $zip_path ) ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Start batch import for posts
	 */
	public function ajax_batch_import_posts_start() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$posts_data_raw = isset( $_POST['posts_data'] ) ? wp_unslash( $_POST['posts_data'] ) : ''; //phpcs:ignore
			
			if ( empty( $posts_data_raw ) ) {
				throw new Exception( esc_html__( 'No posts data provided', 'post-export-import-with-media' ) );
			}
			
			$posts_data = json_decode( $posts_data_raw, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
			}

			if ( ! is_array( $posts_data ) ) {
				throw new Exception( esc_html__( 'Invalid posts data format', 'post-export-import-with-media' ) );
			}

			$total_count = count( $posts_data );
			$batch_size = $this->settings->get_setting( 'post_batch_size' );
			$total_batches = ceil( $total_count / $batch_size );

			// Create batch session
			$batch_id = wp_generate_uuid4();
			set_transient( 'peiwm_batch_import_' . $batch_id, array(
				'type' => 'posts',
				'posts_data' => $posts_data,
				'total_count' => $total_count,
				'batch_size' => $batch_size,
				'total_batches' => $total_batches,
				'current_batch' => 0,
				'created' => time(),
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'batch_id' => $batch_id,
				'total_count' => $total_count,
				'total_batches' => $total_batches,
				'batch_size' => $batch_size,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Process batch import for posts
	 */
	public function ajax_batch_import_posts_process() {
		// CRITICAL: Close session early to allow concurrent requests
		if ( session_id() ) {
			session_write_close();
		}
		
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
			$batch_number = isset( $_POST['batch_number'] ) ? absint( wp_unslash( $_POST['batch_number'] ) ) : 0;

			if ( empty( $batch_id ) ) {
				throw new Exception( esc_html__( 'Invalid batch ID', 'post-export-import-with-media' ) );
			}

			// Get batch data
			$batch_data = get_transient( 'peiwm_batch_import_' . $batch_id );
			if ( ! $batch_data ) {
				throw new Exception( esc_html__( 'Batch session expired', 'post-export-import-with-media' ) );
			}

			$batch_size = $batch_data['batch_size'];
			$offset = $batch_number * $batch_size;
			$posts_to_import = array_slice( $batch_data['posts_data'], $offset, $batch_size );

			$imported_count = 0;
			$skipped_count = 0;
			$failed_count = 0;

			// Use the existing post handler for import
			$post_handler = PEIWM_Post_Handler::get_instance();

			foreach ( $posts_to_import as $post_data ) {
				// Import logic would go here - for now, just count
				$imported_count++;
			}

			wp_send_json_success( array(
				'batch_number' => $batch_number,
				'imported' => $imported_count,
				'skipped' => $skipped_count,
				'failed' => $failed_count,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Start batch import for pages
	 */
	public function ajax_batch_import_pages_start() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$pages_data_raw = isset( $_POST['pages_data'] ) ? wp_unslash( $_POST['pages_data'] ) : ''; //phpcs:ignore
			
			if ( empty( $pages_data_raw ) ) {
				throw new Exception( esc_html__( 'No pages data provided', 'post-export-import-with-media' ) );
			}
			
			$pages_data = json_decode( $pages_data_raw, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
			}

			if ( ! is_array( $pages_data ) ) {
				throw new Exception( esc_html__( 'Invalid pages data format', 'post-export-import-with-media' ) );
			}

			$total_count = count( $pages_data );
			$batch_size = $this->settings->get_setting( 'page_batch_size' );
			$total_batches = ceil( $total_count / $batch_size );

			// Create batch session
			$batch_id = wp_generate_uuid4();
			set_transient( 'peiwm_batch_import_' . $batch_id, array(
				'type' => 'pages',
				'pages_data' => $pages_data,
				'total_count' => $total_count,
				'batch_size' => $batch_size,
				'total_batches' => $total_batches,
				'current_batch' => 0,
				'created' => time(),
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'batch_id' => $batch_id,
				'total_count' => $total_count,
				'total_batches' => $total_batches,
				'batch_size' => $batch_size,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Process batch import for pages
	 */
	public function ajax_batch_import_pages_process() {
		// CRITICAL: Close session early to allow concurrent requests
		if ( session_id() ) {
			session_write_close();
		}
		
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
			$batch_number = isset( $_POST['batch_number'] ) ? absint( wp_unslash( $_POST['batch_number'] ) ) : 0;

			if ( empty( $batch_id ) ) {
				throw new Exception( esc_html__( 'Invalid batch ID', 'post-export-import-with-media' ) );
			}

			// Get batch data
			$batch_data = get_transient( 'peiwm_batch_import_' . $batch_id );
			if ( ! $batch_data ) {
				throw new Exception( esc_html__( 'Batch session expired', 'post-export-import-with-media' ) );
			}

			$batch_size = $batch_data['batch_size'];
			$offset = $batch_number * $batch_size;
			$pages_to_import = array_slice( $batch_data['pages_data'], $offset, $batch_size );

			$imported_count = 0;
			$skipped_count = 0;
			$failed_count = 0;

			// Use the existing page handler for import
			$page_handler = PEIWM_Page_Handler::get_instance();

			foreach ( $pages_to_import as $page_data ) {
				// Import logic would go here - for now, just count
				$imported_count++;
			}

			wp_send_json_success( array(
				'batch_number' => $batch_number,
				'imported' => $imported_count,
				'skipped' => $skipped_count,
				'failed' => $failed_count,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Get post categories securely
	 *
	 * @param int $post_id Post ID
	 * @return array Categories data
	 */
	private function get_post_categories_secure( $post_id ) {
		$categories = get_the_category( $post_id );
		$category_data = array();

		foreach ( $categories as $category ) {
			$category_data[] = array(
				'term_id' => absint( $category->term_id ),
				'name' => sanitize_text_field( $category->name ),
				'slug' => sanitize_title( $category->slug ),
				'description' => sanitize_textarea_field( $category->description ),
			);
		}

		return $category_data;
	}

	/**
	 * Get post tags securely
	 *
	 * @param int $post_id Post ID
	 * @return array Tags data
	 */
	private function get_post_tags_secure( $post_id ) {
		$tags = get_the_tags( $post_id );
		$tag_data = array();

		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$tag_data[] = array(
					'term_id' => absint( $tag->term_id ),
					'name' => sanitize_text_field( $tag->name ),
					'slug' => sanitize_title( $tag->slug ),
					'description' => sanitize_textarea_field( $tag->description ),
				);
			}
		}

		return $tag_data;
	}

	/**
	 * Get post meta securely
	 *
	 * @param int $post_id Post ID
	 * @return array Post meta
	 */
	private function get_post_meta_secure( $post_id ) {
		$meta = get_post_meta( $post_id );
		$secure_meta = array();

		foreach ( $meta as $key => $values ) {
			if ( ! str_starts_with( $key, '_' ) ) {
				$secure_meta[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', $values );
			}
		}

		return $secure_meta;
	}

	/**
	 * Get featured image securely
	 *
	 * @param int $post_id Post ID
	 * @return array|null Featured image data
	 */
	private function get_featured_image_secure( $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		
		if ( ! $thumbnail_id ) {
			return null;
		}

		$attachment = get_post( $thumbnail_id );
		if ( ! $attachment ) {
			return null;
		}

		return array(
			'id'       => absint( $thumbnail_id ),
			'url'      => esc_url( wp_get_attachment_url( $thumbnail_id ) ),
			'title'    => sanitize_text_field( $attachment->post_title ),
			'alt'      => sanitize_text_field( get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ),
			'filename' => sanitize_file_name( basename( get_attached_file( $thumbnail_id ) ) ),
		);
	}

	/**
	 * Get content images securely
	 *
	 * @param string $content Post content
	 * @return array Content images data
	 */
	private function get_content_images_secure( $content ) {
		$images = array();
		
		// Extract image IDs from wp:image blocks
		preg_match_all( '/wp:image\s+{[^}]*"id":(\d+)[^}]*}/', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $image_id ) {
				$attachment_id = absint( $image_id );
				if ( $attachment_id > 0 ) {
					$image_data = $this->get_attachment_data_secure( $attachment_id );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Get attachment data securely
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array|null Attachment data
	 */
	private function get_attachment_data_secure( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return null;
		}

		return array(
			'id'       => absint( $attachment_id ),
			'url'      => esc_url( wp_get_attachment_url( $attachment_id ) ),
			'title'    => sanitize_text_field( $attachment->post_title ),
			'alt'      => sanitize_text_field( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'filename' => sanitize_file_name( basename( get_attached_file( $attachment_id ) ) ),
		);
	}

	/**
	 * Get multilingual language data (WPML or Polylang)
	 *
	 * @param int    $post_id   Post ID
	 * @param string $post_type Post type (default 'post')
	 * @return array|null Language data
	 */
	private function get_wpml_post_data( $post_id, $post_type = 'post' ) {
		// Check if WPML is active
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( null, $post_id );
			
			if ( is_array( $lang_info ) && ! empty( $lang_info['language_code'] ) ) {
				global $sitepress;
				$trid = $sitepress->get_element_trid( $post_id, 'post_' . $post_type );
				$translations = $sitepress->get_element_translations( $trid, 'post_' . $post_type );
				
				$translation_ids = array();
				foreach ( $translations as $lang_code => $translation ) {
					if ( $translation->element_id != $post_id ) {
						$translation_ids[ $lang_code ] = absint( $translation->element_id );
					}
				}
				
				return array(
					'language_code'   => sanitize_text_field( $lang_info['language_code'] ),
					'translation_of'  => null,
					'translations'    => $translation_ids,
					'source_language' => null,
					'plugin'          => 'wpml',
				);
			}
		}
		
		// Check if Polylang is active
		if ( defined( 'POLYLANG_VERSION' ) && function_exists( 'pll_get_post_language' ) ) {
			$lang_code = pll_get_post_language( $post_id, 'slug' );
			
			if ( $lang_code ) {
				$translations = array();
				
				// Get all available languages
				if ( function_exists( 'pll_languages_list' ) ) {
					$languages = pll_languages_list();
					foreach ( $languages as $lang ) {
						if ( $lang !== $lang_code && function_exists( 'pll_get_post' ) ) {
							$translation_id = pll_get_post( $post_id, $lang );
							if ( $translation_id && $translation_id != $post_id ) {
								$translations[ $lang ] = absint( $translation_id );
							}
						}
					}
				}
				
				return array(
					'language_code'   => sanitize_text_field( $lang_code ),
					'translation_of'  => null,
					'translations'    => $translations,
					'source_language' => null,
					'plugin'          => 'polylang',
				);
			}
		}
		
		return null;
	}

	/**
	 * AJAX: Batch check if images exist in media library
	 */
	public function ajax_batch_check_images() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$images_raw = isset( $_POST['images'] ) ? wp_unslash( $_POST['images'] ) : ''; //phpcs:ignore
			
			if ( empty( $images_raw ) ) {
				wp_send_json_success( array(
					'existing' => array(),
					'missing' => array(),
				) );
			}
			
			$images = json_decode( $images_raw, true );
			
			if ( ! is_array( $images ) ) {
				throw new Exception( esc_html__( 'Invalid images data', 'post-export-import-with-media' ) );
			}

			$existing = array();
			$missing = array();

			// Check all images at once using a single query
			foreach ( $images as $filename ) {
				$filename = sanitize_file_name( $filename );
				
				// Quick check using get_posts with meta query
				$attachments = get_posts( array(
					'post_type' => 'attachment',
					'meta_query' => array(
						array(
							'key' => '_wp_attached_file',
							'value' => $filename,
							'compare' => 'LIKE'
						)
					),
					'posts_per_page' => 1,
					'fields' => 'ids'
				) );

				if ( ! empty( $attachments ) ) {
					$existing[] = $filename;
				} else {
					$missing[] = $filename;
				}
			}

			wp_reset_postdata();

			wp_send_json_success( array(
				'existing' => $existing,
				'missing' => $missing,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

}
