<?php
/**
 * Post Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post Handler Class - Manages post export/import operations
 */
class PEIWM_Post_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Post_Handler|null
	 */
	private static $instance = null;

	/**
	 * Import results for detailed feedback
	 *
	 * @var array
	 */
	private $import_results = array();

	/**
	 * Get instance
	 *
	 * @return PEIWM_Post_Handler
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
		$this->import_results = array();
	}

	/**
	 * Initialize AJAX hooks
	 */
	private function init_ajax_hooks() {
		add_action( 'wp_ajax_peiwm_export_posts', array( $this, 'ajax_export_posts' ) );
		add_action( 'wp_ajax_peiwm_export_posts_chunk', array( $this, 'ajax_export_posts_chunk' ) );
		add_action( 'wp_ajax_peiwm_import_post', array( $this, 'ajax_import_post' ) );
		add_action( 'wp_ajax_peiwm_delete_posts', array( $this, 'ajax_delete_posts' ) );
		add_action( 'wp_ajax_peiwm_check_and_download_image', array( $this, 'ajax_check_and_download_image' ) );
		add_action( 'wp_ajax_peiwm_get_posts_list', array( $this, 'ajax_get_posts_list' ) );
	}

	/**
	 * AJAX: Export posts
	 */
	public function ajax_export_posts() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			@ini_set( 'memory_limit', '512M' );

			$query_args = array(
				'post_type'              => 'post',
				'numberposts'            => -1,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			);

			$posts = get_posts( $query_args );

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
					'page_template' => 'page' === $post->post_type ? ( get_page_template_slug( $post->ID ) ?: 'default' ) : '',
					'categories'    => $this->get_post_categories_secure( $post->ID ),
					'tags'          => $this->get_post_tags_secure( $post->ID ),
					'meta'          => $this->get_post_meta_secure( $post->ID ),
					'featured_image' => $this->get_featured_image_secure( $post->ID ),
					'content_images' => $this->get_content_images_secure( $post->post_content ),
					'source_url'    => home_url(),
					'acf_fields'    => array(),
				);

				$export_data[] = $post_data;			}

			wp_reset_postdata();

			wp_send_json_success( array(
				'data'  => $export_data,
				'count' => count( $export_data ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Export posts in a single chunk (offset + limit) for large sites
	 */
	public function ajax_export_posts_chunk() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			@ini_set( 'memory_limit', '512M' );

			$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
			$chunk_size = isset( $_POST['chunk_size'] ) ? absint( $_POST['chunk_size'] ) : 50;
			$chunk_size = max( 1, min( $chunk_size, 100 ) );

			$query_args = array(
				'post_type'              => 'post',
				'numberposts'            => $chunk_size,
				'offset'                 => $offset,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			);

			$posts       = get_posts( $query_args );
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
					'page_template' => 'page' === $post->post_type ? ( get_page_template_slug( $post->ID ) ?: 'default' ) : '',
					'categories'     => $this->get_post_categories_secure( $post->ID ),
					'tags'           => $this->get_post_tags_secure( $post->ID ),
					'meta'           => $this->get_post_meta_secure( $post->ID ),
					'featured_image' => $this->get_featured_image_secure( $post->ID ),
					'content_images' => $this->get_content_images_secure( $post->post_content ),
					'source_url'    => home_url(),
					'acf_fields'    => array(),
				);

				$export_data[] = $post_data;
			}

			wp_reset_postdata();

			$has_more = count( $posts ) === $chunk_size;

			wp_send_json_success( array(
				'data'        => $export_data,
				'count'       => count( $export_data ),
				'has_more'    => $has_more,
				'offset'      => $offset,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Export chunk failed.', 'post-export-import-with-media' ), 'debug' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Get posts list (paginated - 500 per page to avoid memory exhaustion)
	 */
	public function ajax_get_posts_list() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			@ini_set( 'memory_limit', '512M' );

			// Use configured page size: batch mode uses export_list_page_size, regular mode uses 300
			$batch_settings  = PEIWM_Batch_Settings::get_instance();
			$batch_enabled   = $batch_settings->is_batch_enabled();
			$page_size       = $batch_enabled
				? (int) $batch_settings->get_setting( 'export_list_page_size' )
				: 300;
			$page_size       = max( 10, min( $page_size, 2000 ) );
			$offset          = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

			// Get total count using same statuses as the query
			$counts      = wp_count_posts( 'post' );
			$total_count = (int) ( $counts->publish ?? 0 )
				+ (int) ( $counts->draft ?? 0 )
				+ (int) ( $counts->private ?? 0 )
				+ (int) ( $counts->pending ?? 0 )
				+ (int) ( $counts->future ?? 0 );

			$large_site      = $total_count >= 800;
			$show_batch_warn = $large_site && ! $batch_enabled;
			$large_site      = $total_count >= 800;
			$show_batch_warn = $large_site && ! $batch_enabled;

			$query_args = array(
				'post_type'              => 'post',
				'numberposts'            => $page_size,
				'offset'                 => $offset,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			$posts = get_posts( $query_args );

			$list = array();
			foreach ( $posts as $post ) {
				$list[] = array(
					'ID'          => absint( $post->ID ),
					'post_title'  => sanitize_text_field( $post->post_title ),
					'post_date'   => sanitize_text_field( $post->post_date ),
					'post_status' => sanitize_key( $post->post_status ),
				);
			}

			wp_reset_postdata();

			wp_send_json_success( array(
				'posts'           => $list,
				'count'           => count( $list ),
				'total_count'     => $total_count,
				'offset'          => $offset,
				'page_size'       => $page_size,
				'has_more'        => ( $offset + count( $list ) ) < $total_count,
				'show_batch_warn' => $show_batch_warn,
				'batch_enabled'   => $batch_enabled,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to load posts list.', 'post-export-import-with-media' ), 'debug' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Import post
	 */
	public function ajax_import_post() {
		if ( session_id() ) {
			session_write_close();
		}
		
		// Set reasonable execution time limit
		@set_time_limit( 90 );
		@ini_set( 'max_execution_time', '90' );
		
		// Optimize memory - each concurrent request needs its own budget
		@ini_set( 'memory_limit', '256M' );
		
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_data is structurally validated and sanitized deeply via sanitize_post_data() later.
			$post_data_raw = isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '';
			$download_missing_images = isset( $_POST['download_missing_images'] ) && $_POST['download_missing_images'] === '1';
			$check_media_library = isset( $_POST['check_media_library'] ) && $_POST['check_media_library'] === '1';


			
			if ( empty( $post_data_raw ) ) {
				throw new Exception( esc_html__( 'No post data provided', 'post-export-import-with-media' ) );
			}
			
			$post_data = json_decode( $post_data_raw, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
			}

			if ( ! $post_data || ! is_array( $post_data ) ) {
				throw new Exception( esc_html__( 'Invalid post data', 'post-export-import-with-media' ) );
			}

			// SECURITY FIX: Validate required fields exist
			$required_fields = array( 'post_title', 'post_type' );
			foreach ( $required_fields as $field ) {
				if ( ! isset( $post_data[ $field ] ) ) {
					error_log( 'PEIWM Security: Missing required field in post import: ' . $field );
					throw new Exception( sprintf( esc_html__( 'Invalid post data: missing required field', 'post-export-import-with-media' ) ) );
				}
			}

			$sanitized_post_data = $this->sanitize_post_data( $post_data ); // Sanitize input data properly

			// Apply force_status override if provided
			$force_status = isset( $_POST['force_status'] ) ? sanitize_key( wp_unslash( $_POST['force_status'] ) ) : 'original';
			$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
			if ( $force_status !== 'original' && in_array( $force_status, $allowed_statuses, true ) ) {
				$sanitized_post_data['post_status'] = $force_status;
			}

			$original_author_id = isset( $post_data['post_author'] ) ? absint( $post_data['post_author'] ) : 0;

			$resolved_author_id = ( $original_author_id > 0 && false !== get_userdata( $original_author_id ) )
			                      ? $original_author_id
			                      : get_current_user_id();

			// Check if post already exists.
			// Primary: match by slug (post_name) — unique per post type in WordPress.
			// Fallback: match by title + content hash, only when content is non-empty,
			// to avoid false positives where multiple different posts share empty content.
			$existing_post  = null;
			$import_slug    = $sanitized_post_data['post_name'];
			$import_content = $sanitized_post_data['post_content'];

			if ( ! empty( $import_slug ) ) {
				$slug_matches = get_posts( array(
					'post_type'      => 'post',
					'post_status'    => 'any',
					'name'           => $import_slug,
					'posts_per_page' => 1,
				) );
				if ( ! empty( $slug_matches ) ) {
					$existing_post = $slug_matches[0];
				}
			}

			// Fallback: title + content hash — only when content is non-empty.
			// Skipping this check for empty-content posts prevents false duplicates
			// where multiple distinct posts share the same title and no content yet.
			if ( ! $existing_post && ! empty( trim( $import_content ) ) ) {
				$title_matches = get_posts( array(
					'post_type'      => 'post',
					'post_status'    => 'any',
					'title'          => $sanitized_post_data['post_title'],
					'posts_per_page' => 50,
				) );
				$import_hash = md5( $import_content );
				foreach ( $title_matches as $candidate ) {
					if ( md5( $candidate->post_content ) === $import_hash ) {
						$existing_post = $candidate;
						break;
					}
				}
			}

			$existing_posts = $existing_post ? array( $existing_post ) : array();

			if ( ! empty( $existing_posts ) ) {
				$existing_post = $existing_posts[0];
				// If force_status is set and differs from current, update the status
				if ( $force_status !== 'original' && in_array( $force_status, $allowed_statuses, true ) && $existing_post->post_status !== $force_status ) {
					wp_update_post( array(
						'ID'          => $existing_post->ID,
						'post_status' => $force_status,
					) );
					wp_reset_postdata();
					wp_send_json_success( array(
						'status' => 'updated',
						'reason' => sprintf( 'Status updated to %s', $force_status ),
					) );
				}
				wp_send_json_success( array(
					'status' => 'skipped',
					'reason' => esc_html__( 'Post already exists', 'post-export-import-with-media' ),
				) );
			}

			// Insert post
			$post_id = wp_insert_post( array(
				'post_title'    => $sanitized_post_data['post_title'],
				'post_content'  => $sanitized_post_data['post_content'],
				'post_excerpt'  => $sanitized_post_data['post_excerpt'],
				'post_status'   => $sanitized_post_data['post_status'],
				'post_type'     => $sanitized_post_data['post_type'],
				'post_author'   => $resolved_author_id,
				'post_date'     => $sanitized_post_data['post_date'],
				'post_name'     => $sanitized_post_data['post_name'], // slug
			) );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( sprintf(
					esc_html__( 'Failed to create post: %s', 'post-export-import-with-media' ),
					$post_id->get_error_message()
				) );
			}

			$language_result = null;

			// Set post format
			if ( ! empty( $sanitized_post_data['post_format'] ) && $sanitized_post_data['post_format'] !== 'standard' ) {
				set_post_format( $post_id, $sanitized_post_data['post_format'] );
			}

			// Set page template for pages — validate template exists on this site before applying
			if ( 'page' === $sanitized_post_data['post_type'] && ! empty( $sanitized_post_data['page_template'] ) && 'default' !== $sanitized_post_data['page_template'] ) {
				$template  = $sanitized_post_data['page_template'];
				$theme     = wp_get_theme();
				$templates = $theme->get_page_templates();
				if ( isset( $templates[ $template ] ) || file_exists( get_stylesheet_directory() . '/' . $template ) ) {
					update_post_meta( $post_id, '_wp_page_template', $template );
				} else {
					update_post_meta( $post_id, '_wp_page_template', 'default' );
				}
			}

			// Import categories
			if ( ! empty( $sanitized_post_data['categories'] ) ) {
				$this->import_post_categories_secure( $post_id, $sanitized_post_data['categories'] );
			}

			// Import tags
			if ( ! empty( $sanitized_post_data['tags'] ) ) {
				$this->import_post_tags_secure( $post_id, $sanitized_post_data['tags'] );
			}

			// Import meta data
			if ( ! empty( $sanitized_post_data['meta'] ) && is_array( $sanitized_post_data['meta'] ) ) {
				$this->import_post_meta_secure( $post_id, $sanitized_post_data['meta'] );
			}



			// Import content images first and update post content
			$updated_content = $sanitized_post_data['post_content'];
			if ( $check_media_library && ! empty( $sanitized_post_data['content_images'] ) ) {
				$updated_content = $this->import_content_images_secure( $post_id, $sanitized_post_data['content_images'], $sanitized_post_data['post_content'], $download_missing_images );
			}
			// If not checking media, we skip image processing entirely - images remain as placeholders in content

			// Replace internal links from the source site with the destination site URL.
			// source_url is stamped at export time; if it differs from the current site we
			// do a simple string replacement so all internal hrefs and srcsets update correctly.
			$source_url = isset( $sanitized_post_data['source_url'] ) ? esc_url_raw( $sanitized_post_data['source_url'] ) : '';
			$dest_url   = untrailingslashit( home_url() );
			if ( ! empty( $source_url ) ) {
				$source_url = untrailingslashit( $source_url );
				if ( $source_url !== $dest_url ) {
					$updated_content = str_replace( $source_url, $dest_url, $updated_content );
				}
			}

			// Import featured image
			if ( $check_media_library && ! empty( $sanitized_post_data['featured_image'] ) ) {
				$this->import_featured_image_secure( $post_id, $sanitized_post_data['featured_image'], $download_missing_images );
			}

			// Update post content if it was modified
			if ( $updated_content !== $sanitized_post_data['post_content'] ) {
				wp_update_post( array(
					'ID' => $post_id,
					'post_content' => $updated_content,
				) );
			}

			// Reset global post data once at the end
			wp_reset_postdata();
			
			// Clear object cache to prevent memory buildup
			wp_cache_flush();

			// Get detailed import results
			$import_results = $this->get_import_results();
			
			// Check if any images were missing
			$missing_images = $this->check_missing_images( $sanitized_post_data );
			$message = 'Post imported successfully';
			if ( ! empty( $missing_images ) ) {
				if ( $download_missing_images ) {
					$message .= '. Note: ' . count( $missing_images ) . ' image(s) not found and could not be downloaded.';
				} else {
					$message .= '. Note: ' . count( $missing_images ) . ' image(s) not found in media library. Import media files first or enable "Download missing images".';
				}
			}

			wp_send_json_success( array(
				'status'  => 'imported',
				'post_id' => $post_id,
				'message' => $message,
				'missing_images' => $missing_images,
				'download_enabled' => $download_missing_images,
				'import_details' => $import_results,
			) );

		} catch ( Exception $e ) {
			// Clear cache on error too
			wp_cache_flush();
			wp_send_json_error( array( 'message' => esc_html__( 'Import failed. Please check the file format.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Check and download individual image
	 */
	public function ajax_check_and_download_image() {
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- image_data is structurally validated and its fields are sanitized below.
			$image_data_raw = isset( $_POST['image_data'] ) ? wp_unslash( $_POST['image_data'] ) : '';
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			
			if ( empty( $image_data_raw ) ) {
				throw new Exception( esc_html__( 'No image data provided', 'post-export-import-with-media' ) );
			}
			
			$image_data = json_decode( $image_data_raw, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
			}

			if ( ! $image_data || ! is_array( $image_data ) ) {
				throw new Exception( esc_html__( 'Invalid image data', 'post-export-import-with-media' ) );
			}

			$filename = isset( $image_data['filename'] ) ? sanitize_file_name( $image_data['filename'] ) : ''; // Sanitize input data properly
			if ( empty( $filename ) ) {
				throw new Exception( esc_html__( 'No filename provided', 'post-export-import-with-media' ) );
			}

			// Try to find existing attachment by filename
			$attachment_id = $this->find_existing_attachment_by_filename( $filename );

			if ( $attachment_id ) {
				wp_send_json_success( array(
					'status' => 'found_local',
					'attachment_id' => $attachment_id,
					'message' => 'Found in media library',
				) );
			}

			// Try to download from URL
			$image_url = isset( $image_data['url'] ) ? esc_url_raw( $image_data['url'] ) : '';
			if ( empty( $image_url ) ) {
				wp_send_json_success( array(
					'status' => 'failed',
					'message' => 'No URL provided for download',
				) );
			}

			$image_title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
			$image_alt = isset( $image_data['alt'] ) ? sanitize_text_field( $image_data['alt'] ) : '';

			$attachment_id = $this->download_and_create_attachment( $image_url, $post_id, $image_title, $image_alt );

			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				wp_send_json_success( array(
					'status' => 'downloaded',
					'attachment_id' => $attachment_id,
					'message' => 'Successfully downloaded',
				) );
			} else {
				wp_send_json_success( array(
					'status' => 'failed',
					'message' => 'Download failed',
				) );
			}

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Delete posts
	 */
	public function ajax_delete_posts() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$posts = get_posts( array(
				'post_type'   => 'post',
				'numberposts' => -1,
				'post_status' => 'any',
			) );

			if ( empty( $posts ) ) {
				wp_reset_postdata(); // Reset before returning
				wp_send_json_success( array( 'message' => esc_html__( 'No posts found to delete', 'post-export-import-with-media' ) ) );
			}

			$deleted_count = 0;
			$failed_count = 0;

			foreach ( $posts as $post ) {
				$result = wp_delete_post( $post->ID, true );
				if ( $result ) {
					$deleted_count++;
				} else {
					$failed_count++;
				}
			}

			// Reset global post data after operations
			wp_reset_postdata();

			$message = sprintf(
				esc_html__( 'Deleted %d posts successfully', 'post-export-import-with-media' ),
				$deleted_count
			);

			if ( $failed_count > 0 ) {
				$message .= sprintf(
					esc_html__( '. Failed to delete %d posts.', 'post-export-import-with-media' ),
					$failed_count
				);
			}

			wp_send_json_success( array(
				'message'       => $message,
				'deleted_count' => $deleted_count,
				'failed_count'  => $failed_count,
				'total_count'   => count( $posts ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Delete operation failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}


	/**
	 * Get post meta securely
	 *
	 * @param int $post_id Post ID
	 * @return array Post meta
	 */
	private function get_post_meta_secure( $post_id ) {
		$meta        = get_post_meta( $post_id );
		$secure_meta = array();

		foreach ( $meta as $key => $values ) {
			// Always skip truly internal WordPress core meta.
			if ( $this->is_internal_wp_meta_key( $key ) ) {
				continue;
			}

			// Allow public meta (no underscore prefix) AND known SEO plugin meta.
			if ( ! str_starts_with( $key, '_' ) || $this->is_seo_meta_key( $key ) ) {
				// Use the raw key for SEO keys (they contain underscores that sanitize_key would strip).
				$safe_key = sanitize_text_field( $key );

				// Sanitize values — preserve HTML in SEO meta, re-serialize structured ACF data.
				$secure_meta[ $safe_key ] = array_map( function( $value ) use ( $key ) {
					// SEO meta may contain HTML (focus keyword lists, descriptions).
					if ( $this->is_seo_meta_key( $key ) ) {
						return wp_kses( $value, array( 'a' => array( 'href' => array(), 'title' => array() ), 'em' => array(), 'strong' => array() ) );
					}
					// Preserve serialized data (ACF repeaters, flexible content, link fields, etc.)
					// get_post_meta() already unserializes — re-serialize so the JSON carries the
					// raw DB string which can be written back intact on import.
					if ( is_array( $value ) || is_object( $value ) ) {
						return serialize( $value );
					}
					// Use wp_kses_post() instead of sanitize_text_field() so WYSIWYG/HTML meta
					// values (ACF textarea, wysiwyg fields) are preserved with their markup intact.
					return wp_kses_post( $value );
				}, $values );
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
	 * Sanitize post data
	 *
	 * @param array $post_data Raw post data
	 * @return array Sanitized post data
	 */
	private function sanitize_post_data( $post_data ) {
		return array(
			'post_title'    => isset( $post_data['post_title'] ) ? sanitize_text_field( $post_data['post_title'] ) : '',
			'post_content'  => isset( $post_data['post_content'] ) ? $this->sanitize_gutenberg_content( $post_data['post_content'] ) : '',
			'post_excerpt'  => isset( $post_data['post_excerpt'] ) ? sanitize_textarea_field( $post_data['post_excerpt'] ) : '',
			'post_status'   => isset( $post_data['post_status'] ) ? sanitize_key( $post_data['post_status'] ) : 'draft',
			'post_type'     => isset( $post_data['post_type'] ) ? sanitize_key( $post_data['post_type'] ) : 'post',
			'post_author'   => isset( $post_data['post_author'] ) ? absint( $post_data['post_author'] ) : get_current_user_id(),
			'post_date'     => isset( $post_data['post_date'] ) ? sanitize_text_field( $post_data['post_date'] ) : current_time( 'mysql' ),
			'post_name'     => isset( $post_data['post_name'] ) ? sanitize_title( $post_data['post_name'] ) : '',
			'post_format'   => isset( $post_data['post_format'] ) ? sanitize_key( $post_data['post_format'] ) : 'standard',
			'page_template' => isset( $post_data['page_template'] ) ? sanitize_file_name( $post_data['page_template'] ) : '',
			'categories'    => isset( $post_data['categories'] ) && is_array( $post_data['categories'] ) ? $post_data['categories'] : array(),
			'tags'          => isset( $post_data['tags'] ) && is_array( $post_data['tags'] ) ? $post_data['tags'] : array(),
			'meta'          => isset( $post_data['meta'] ) && is_array( $post_data['meta'] ) ? $post_data['meta'] : array(),
			'featured_image' => isset( $post_data['featured_image'] ) ? $post_data['featured_image'] : null,
			'content_images' => isset( $post_data['content_images'] ) && is_array( $post_data['content_images'] ) ? $post_data['content_images'] : array(),
			'acf_fields'    => isset( $post_data['acf_fields'] ) && is_array( $post_data['acf_fields'] ) ? $post_data['acf_fields'] : array(),
			'wpml_data'     => isset( $post_data['wpml_data'] ) && is_array( $post_data['wpml_data'] ) ? $post_data['wpml_data'] : null,
			'source_url'    => isset( $post_data['source_url'] ) ? esc_url_raw( $post_data['source_url'] ) : '',
		);
	}
	 
	 /**
	 * @param int   $post_id Post ID
	 * @param array $meta_data Meta data
	 */
	private function import_post_meta_secure( $post_id, $meta_data ) {
		foreach ( $meta_data as $key => $values ) {
			// Preserve original key for SEO meta (sanitize_key strips underscores/hyphens).
			$safe_key = sanitize_text_field( $key );

			if ( empty( $safe_key ) ) {
				continue;
			}

			// Block internal WordPress core meta from being written.
			if ( $this->is_internal_wp_meta_key( $safe_key ) ) {
				continue;
			}

			// SPECIAL CASE: _wp_page_template must be allowed (validates & applies page template)
			$is_page_template = ( $safe_key === '_wp_page_template' );

			// Allow public keys (no leading underscore) AND known SEO plugin keys AND _wp_page_template.
			if ( str_starts_with( $safe_key, '_' ) && ! $this->is_seo_meta_key( $safe_key ) && ! $is_page_template ) {
				continue;
			}

			// Special handling for _wp_page_template: validate template exists before applying
			if ( $is_page_template ) {
				$template_value = is_array( $values ) ? reset( $values ) : $values;
				$template_value = sanitize_text_field( $template_value );
				
				// Validate template file exists in active theme
				if ( $template_value && $template_value !== 'default' ) {
					$theme           = wp_get_theme();
					$page_templates  = $theme->get_page_templates();
					$template_exists = isset( $page_templates[ $template_value ] ) || file_exists( get_stylesheet_directory() . '/' . $template_value );
					
					if ( ! $template_exists ) {
						// Template doesn't exist — fallback to default
						$template_value = 'default';
					}
				}
				
				update_post_meta( $post_id, '_wp_page_template', $template_value );
				continue; // Skip normal processing
			}

			delete_post_meta( $post_id, $safe_key );

			foreach ( (array) $values as $value ) {
				// Preserve HTML in SEO meta; handle serialized ACF data; sanitize plain text.
				if ( $this->is_seo_meta_key( $safe_key ) ) {
					$clean_value = wp_kses( $value, array( 'a' => array( 'href' => array(), 'title' => array() ), 'em' => array(), 'strong' => array() ) );
					add_post_meta( $post_id, $safe_key, $clean_value );
				} else {
					// Detect PHP-serialized strings (ACF repeaters, flex content, link fields).
					// maybe_unserialize() returns the real PHP value when serialized, or the
					// original string unchanged. WordPress re-serializes arrays/objects automatically.
					$unserialized = maybe_unserialize( $value );
					if ( is_array( $unserialized ) || is_object( $unserialized ) ) {
						add_post_meta( $post_id, $safe_key, $unserialized );
					} else {
						// Use wp_kses_post() to preserve HTML from WYSIWYG/wysiwyg ACF fields.
						add_post_meta( $post_id, $safe_key, wp_kses_post( $value ) );
					}
				}
			}
		}
	}

	/**
	 * Import featured image securely
	 *
	 * @param int   $post_id Post ID
	 * @param array $image_data Image data
	 * @param bool  $download_missing Whether to download missing images
	 */
	private function import_featured_image_secure( $post_id, $image_data, $download_missing = false ) {
		if ( ! is_array( $image_data ) || empty( $image_data['filename'] ) ) {
			return;
		}

		$filename = sanitize_file_name( $image_data['filename'] );
		$image_alt = isset( $image_data['alt'] ) ? sanitize_text_field( $image_data['alt'] ) : '';

		// Try to find existing attachment by filename
		$attachment_id = $this->find_existing_attachment_by_filename( $filename );

		if ( $attachment_id ) {
			// Record that featured image was found locally
			$this->import_results[] = array(
				'type' => 'found_local',
				'filename' => $filename,
				'attachment_id' => $attachment_id,
				'message' => 'Found featured image in media library: ' . $filename,
			);
			set_post_thumbnail( $post_id, $attachment_id );
			
			if ( ! empty( $image_alt ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_alt );
			}

			// Fix _wp_attached_file meta to ensure proper srcset generation
			$this->fix_attachment_meta( $attachment_id );
		} else if ( $download_missing && ! empty( $image_data['url'] ) ) {
			// Try to download the featured image from original URL
			$image_url = esc_url_raw( $image_data['url'] );
			$image_title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
			
			$attachment_id = $this->download_and_create_attachment( $image_url, $post_id, $image_title, $image_alt );
			
			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
			// If download failed, featured image will be reported as missing
		}
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

		// Also extract from img tags with wp-image class
		preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $image_id ) {
				$attachment_id = absint( $image_id );
				if ( $attachment_id > 0 ) {
					$image_data = $this->get_attachment_data_secure( $attachment_id );
					if ( $image_data ) {
						// Check if we already have this image
						$exists = false;
						foreach ( $images as $existing_image ) {
							if ( $existing_image['id'] === $attachment_id ) {
								$exists = true;
								break;
							}
						}
						if ( ! $exists ) {
							$images[] = $image_data;
						}
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

		// Calculate relative file path for precise year/month matching on import
		$full_path   = get_attached_file( $attachment_id );
		$upload_dir  = wp_upload_dir();
		$upload_base = rtrim( $upload_dir['basedir'], '/\\' );
		$rel_path    = $full_path
						 ? ltrim( str_replace( $upload_base, '', $full_path ), '/\\' )
						 : sanitize_file_name( basename( $full_path ) );
		$rel_path    = str_replace( DIRECTORY_SEPARATOR, '/', $rel_path ); // normalize to forward slashes

		return array(
			'id'        => absint( $attachment_id ),
			'url'       => esc_url( wp_get_attachment_url( $attachment_id ) ),
			'title'     => sanitize_text_field( $attachment->post_title ),
			'alt'       => sanitize_text_field( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'filename'  => sanitize_file_name( basename( get_attached_file( $attachment_id ) ) ),
			'file_path' => $rel_path, // NEW: precise relative path e.g. "2026/06/photo.jpg"
		);
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
	 * Import post categories securely
	 *
	 * @param int   $post_id Post ID
	 * @param array $categories_data Categories data
	 */
	private function import_post_categories_secure( $post_id, $categories_data ) {
		$category_ids = array();

		foreach ( $categories_data as $category_data ) {
			if ( ! is_array( $category_data ) || empty( $category_data['name'] ) ) {
				continue;
			}

			$category_name = sanitize_text_field( $category_data['name'] );
			$category_slug = isset( $category_data['slug'] ) ? sanitize_title( $category_data['slug'] ) : '';
			$category_description = isset( $category_data['description'] ) ? sanitize_textarea_field( $category_data['description'] ) : '';

			// Check if category exists
			$existing_category = get_category_by_slug( $category_slug );
			if ( ! $existing_category ) {
				$existing_category = get_term_by( 'name', $category_name, 'category' );
			}

			if ( $existing_category ) {
				$category_ids[] = $existing_category->term_id;
			} else {
				// Create new category
				$new_category = wp_insert_term( $category_name, 'category', array(
					'slug' => $category_slug,
					'description' => $category_description,
				) );

				if ( ! is_wp_error( $new_category ) ) {
					$category_ids[] = $new_category['term_id'];
				}
			}
		}

		if ( ! empty( $category_ids ) ) {
			wp_set_post_categories( $post_id, $category_ids );
		}
	}

	/**
	 * Import post tags securely
	 *
	 * @param int   $post_id Post ID
	 * @param array $tags_data Tags data
	 */
	private function import_post_tags_secure( $post_id, $tags_data ) {
		$tag_names = array();

		foreach ( $tags_data as $tag_data ) {
			if ( ! is_array( $tag_data ) || empty( $tag_data['name'] ) ) {
				continue;
			}

			$tag_name = sanitize_text_field( $tag_data['name'] );
			$tag_slug = isset( $tag_data['slug'] ) ? sanitize_title( $tag_data['slug'] ) : '';
			$tag_description = isset( $tag_data['description'] ) ? sanitize_textarea_field( $tag_data['description'] ) : '';

			// Check if tag exists
			$existing_tag = get_term_by( 'slug', $tag_slug, 'post_tag' );
			if ( ! $existing_tag ) {
				$existing_tag = get_term_by( 'name', $tag_name, 'post_tag' );
			}

			if ( $existing_tag ) {
				$tag_names[] = $tag_name;
			} else {
				// Create new tag
				$new_tag = wp_insert_term( $tag_name, 'post_tag', array(
					'slug' => $tag_slug,
					'description' => $tag_description,
				) );

				if ( ! is_wp_error( $new_tag ) ) {
					$tag_names[] = $tag_name;
				}
			}
		}

		if ( ! empty( $tag_names ) ) {
			wp_set_post_tags( $post_id, $tag_names );
		}
	}

	/**
	 * Find existing attachment by filename
	 *
	 * @param string $filename Filename to search for
	 * @return int|null Attachment ID if found
	 */
	private function find_existing_attachment_by_filename( $filename ) {
		global $wpdb;
		
		// Use direct database query for better performance
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_wp_attached_file' 
			AND meta_value LIKE %s 
			LIMIT 1",
			'%' . $wpdb->esc_like( $filename )
		) );

		if ( $attachment_id ) {
			return absint( $attachment_id );
		}

		// If not found, try searching by post title (filename without extension)
		$title = pathinfo( $filename, PATHINFO_FILENAME );
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_title = %s 
			LIMIT 1",
			$title
		) );

		return $attachment_id ? absint( $attachment_id ) : null;
	}

	/**
	 * Extract upload base URL from a full attachment URL.
	 * Returns everything up to and including the uploads directory URL.
	 * e.g. "https://site.com/wp-content/uploads/2026/06/photo-768x768.jpg"
	 * returns "https://site.com/wp-content/uploads/"
	 *
	 * @param string $url Full attachment URL
	 * @return string Upload base URL, or empty string if not a uploads URL
	 */
	private function get_upload_base_url( $url ) {
		$upload_dir  = wp_upload_dir();
		$uploads_url = trailingslashit( $upload_dir['baseurl'] );
		// Check if URL starts with the upload base URL
		if ( strpos( $url, $uploads_url ) === 0 ) {
			return $uploads_url;
		}
		// Fallback: try to extract via string pattern for cross-domain imports
		if ( preg_match( '#^(https?://[^/]+/(?:.*?/)?(?:wp-content/uploads|files)/)#i', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Import content images securely and update content URLs
	 *
	 * @param int    $post_id Post ID
	 * @param array  $images_data Images data
	 * @param string $content Original content
	 * @param bool   $download_missing Whether to download missing images
	 * @return string Updated content
	 */
	private function import_content_images_secure( $post_id, $images_data, $content, $download_missing = false ) {
		$updated_content = $content;

		foreach ( $images_data as $image_data ) {
			if ( ! is_array( $image_data ) || empty( $image_data['filename'] ) ) {
				continue;
			}

			$filename    = sanitize_file_name( $image_data['filename'] );
			$image_title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
			$image_alt   = isset( $image_data['alt'] )   ? sanitize_text_field( $image_data['alt'] )   : '';
			$old_url     = isset( $image_data['url'] )   ? esc_url_raw( $image_data['url'] )          : '';

			// Try to find existing attachment by filename
			$attachment_id = $this->find_existing_attachment_by_filename( $filename );

			if ( $attachment_id ) {
				// Record that image was found locally
				$this->import_results[] = array(
					'type'          => 'found_local',
					'filename'      => $filename,
					'attachment_id' => $attachment_id,
					'message'       => 'Found in media library: ' . $filename,
				);
				// Set alt text if provided
				if ( ! empty( $image_alt ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_alt );
				}

				// Fix _wp_attached_file meta to ensure proper srcset generation
				$this->fix_attachment_meta( $attachment_id );

			} elseif ( $download_missing && ! empty( $old_url ) ) {
				// Try to download the image from original URL
				$attachment_id = $this->download_and_create_attachment( $old_url, $post_id, $image_title, $image_alt );
			}

			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				$new_url = wp_get_attachment_url( $attachment_id );
				
				if ( $new_url && ! empty( $old_url ) ) {
					$old_base = $this->get_upload_base_url( $old_url );
					$new_base = $this->get_upload_base_url( $new_url );

					if ( empty( $old_base ) || empty( $new_base ) ) {
						// Fallback if base extraction fails
						if ( $old_url !== $new_url ) {
							$updated_content = str_replace( $old_url, $new_url, $updated_content );
						}
					} else {
						// Identify base filename to construct matching regex
						$filename_no_ext = preg_replace( '/\.[a-zA-Z0-9]+$/', '', basename( $old_url ) );
						$base_filename = preg_replace( '/-\d+x\d+$/', '', $filename_no_ext );
						$base_filename = preg_replace( '/(?:-scaled|-rotated)$/', '', $base_filename );

						$relative_path = str_replace( $old_base, '', dirname( $old_url ) );
						if ( $relative_path && $relative_path !== '.' ) {
							$relative_path = trailingslashit( $relative_path );
						} else {
							$relative_path = '';
						}

						// old_base might be http:// in content but https:// in json, so allow either
						$old_base_regex = preg_replace( '#^https?://#i', 'https?://', preg_quote( $old_base, '/' ) );
						$old_pattern = $old_base_regex . preg_quote( $relative_path . $base_filename, '/' ) . '(?:-scaled|-rotated)?(?:-\d+x\d+)?\.[a-zA-Z0-9]+';

						if ( preg_match_all( '/(' . $old_pattern . ')/i', $updated_content, $matches ) ) {
							$unique_matches = array_unique( $matches[1] );
							$upload_dir = wp_upload_dir();
							$dest_base_url = trailingslashit( $upload_dir['baseurl'] );
							$dest_base_dir = trailingslashit( $upload_dir['basedir'] );

							foreach ( $unique_matches as $matched_old_url ) {
								$matched_new_url = preg_replace( '#^' . $old_base_regex . '#i', $new_base, $matched_old_url );

								// Check if matched_new_url physically exists on the server
								$file_exists = false;
								if ( strpos( $matched_new_url, $dest_base_url ) === 0 ) {
									$local_path = str_replace( $dest_base_url, $dest_base_dir, $matched_new_url );
									$local_path = str_replace( '/', DIRECTORY_SEPARATOR, $local_path );
									if ( file_exists( $local_path ) ) {
										$file_exists = true;
									}
								}

								// Fallback to full-size URL if the generated size does not exist
								$replacement_url = $file_exists ? $matched_new_url : $new_url;

								if ( $matched_old_url !== $replacement_url ) {
									$updated_content = str_replace( $matched_old_url, $replacement_url, $updated_content );
								}
							}
						} elseif ( $old_url !== $new_url ) {
							// Regex didn't match any for some reason, do a blunt exact replacement
							$updated_content = str_replace( $old_url, $new_url, $updated_content );
						}
					}
				}

				// Update image ID references in content (for block editor)
				if ( isset( $image_data['id'] ) ) {
					$old_id = absint( $image_data['id'] );
					$updated_content = preg_replace(
						'/("id":)' . $old_id . '([,}])/',
						'${1}' . $attachment_id . '${2}',
						$updated_content
					);
					$updated_content = str_replace(
						'wp-image-' . $old_id,
						'wp-image-' . $attachment_id,
						$updated_content
					);
				}
			}
		}

		return $updated_content;
	}

	/**
	 * Sanitize Gutenberg content while preserving block comments
	 *
	 * @param string $content Raw content
	 * @return string Sanitized content
	 */
	private function sanitize_gutenberg_content( $content ) {
		// For imported content from our own export, we need to preserve Gutenberg blocks
		// Use wp_unslash to handle any slashing, then basic sanitization without stripping comments
		$content = wp_unslash( $content );
		
		// Remove any potentially dangerous scripts but keep Gutenberg block structure
		$content = preg_replace( '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content );
		$content = preg_replace( '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $content );
		
		// Basic XSS protection while preserving block comments
		$content = str_replace( array( 'javascript:', 'vbscript:', 'onload=', 'onerror=' ), '', $content );
		
		return $content;
	}

	/**
	 * Check for missing images in post data
	 *
	 * @param array $post_data Post data
	 * @return array Missing image filenames
	 */
	private function check_missing_images( $post_data ) {
		$missing = array();

		// Check featured image
		if ( ! empty( $post_data['featured_image']['filename'] ) ) {
			$filename = $post_data['featured_image']['filename'];
			if ( ! $this->find_existing_attachment_by_filename( $filename ) ) {
				$missing[] = $filename;
			}
		}

		// Check content images
		if ( ! empty( $post_data['content_images'] ) ) {
			foreach ( $post_data['content_images'] as $image ) {
				if ( ! empty( $image['filename'] ) ) {
					$filename = $image['filename'];
					if ( ! $this->find_existing_attachment_by_filename( $filename ) ) {
						$missing[] = $filename;
					}
				}
			}
		}

		return array_unique( $missing );
	}

	/**
	 * Fix attachment metadata to ensure proper srcset generation
	 *
	 * @param int $attachment_id Attachment ID
	 */
	private function fix_attachment_meta( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$upload_base = rtrim( $upload_dir['basedir'], '/\\' );
		
		// Calculate correct relative path
		$relative_path = str_replace( $upload_base, '', $file_path );
		$relative_path = ltrim( $relative_path, '/\\' );
		$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', $relative_path );
		
		// Update the _wp_attached_file meta with correct path
		update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );
		
		// Regenerate attachment metadata to fix srcset
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Download and create attachment from URL
	 *
	 * @param string $image_url Image URL
	 * @param int    $post_id Post ID
	 * @param string $image_title Image title
	 * @param string $image_alt Image alt text
	 * @return int|WP_Error|null Attachment ID or error
	 */
	private function download_and_create_attachment( $image_url, $post_id, $image_title = '', $image_alt = '' ) {
		$filename = basename( wp_parse_url( $image_url, PHP_URL_PATH ) );
		
		// Record download attempt
		$this->import_results[] = array(
			'type' => 'download_start',
			'filename' => $filename,
			'url' => $image_url,
			'message' => 'Downloading image: ' . $filename,
		);

		// Add timeout and error handling for media_sideload_image
		add_filter( 'http_request_timeout', array( $this, 'set_import_timeout' ) );
		add_filter( 'http_request_args', array( $this, 'set_import_request_args' ) );
		
		$attachment_id = media_sideload_image( $image_url, $post_id, $image_title, 'id' );
		
		// Remove filters
		remove_filter( 'http_request_timeout', array( $this, 'set_import_timeout' ) );
		remove_filter( 'http_request_args', array( $this, 'set_import_request_args' ) );
		
		if ( is_wp_error( $attachment_id ) ) {
			// Record download failure
			$this->import_results[] = array(
				'type' => 'download_failed',
				'filename' => $filename,
				'url' => $image_url,
				'message' => 'Failed to download: ' . $filename . ' - ' . $attachment_id->get_error_message(),
			);
			return null; // Return null on failure
		}

		// Record download success
		$this->import_results[] = array(
			'type' => 'download_success',
			'filename' => $filename,
			'url' => $image_url,
			'attachment_id' => $attachment_id,
			'message' => 'Successfully downloaded: ' . $filename,
		);

		// Set alt text if provided
		if ( ! empty( $image_alt ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_alt );
		}

		// Fix _wp_attached_file meta to ensure proper srcset generation
		$this->fix_attachment_meta( $attachment_id );

		return $attachment_id;
	}

	/**
	 * Get import results for detailed feedback
	 *
	 * @return array Import results
	 */
	private function get_import_results() {
		return $this->import_results;
	}

	/**
	 * Set import timeout for HTTP requests
	 *
	 * @param int $timeout Current timeout
	 * @return int New timeout
	 */
	public function set_import_timeout( $timeout ) {
		return 20; // 20 seconds timeout - reduced from 30
	}

	/**
	 * Set import request args for HTTP requests
	 *
	 * @param array $args Current args
	 * @return array Modified args
	 */
	public function set_import_request_args( $args ) {
		$args['timeout'] = 20; // Reduced from 30
		$args['redirection'] = 2; // Reduced from 3
		$args['httpversion'] = '1.1'; // Use HTTP/1.1 for better compatibility
		$args['blocking'] = true;
		$args['compress'] = true; // Enable compression
		return $args;
	}

	/**
	 * Resolve post author ID for import — fully backward compatible.
	 *
	 * Behavior matrix:
	 *
	 * | post_author_data present | User found by login/email | Result                      |
	 * |--------------------------|---------------------------|-----------------------------|
	 * | Yes                      | Yes                       | Matched user's ID           |
	 * | Yes                      | No + fallback=create_user | Create user, return new ID  |
	 * | Yes                      | No + fallback=current_user| Current logged-in admin ID  |
	 * | No (old export)          | ID exists on this site    | Use original ID as-is       |
	 * | No (old export)          | ID missing on this site   | Current logged-in admin ID  |
	 *
	 * @param int        $original_id  Raw post_author value from exported JSON.
	 * @param array|null $author_data  post_author_data block, or null if old export.
	 * @param string     $fallback     'current_user' or 'create_user'.
	 * @return int Valid WordPress user ID to use for the imported post.
	 */
	private function resolve_post_author( $original_id, $author_data, $fallback = 'current_user' ) {

		// ── NEW EXPORT: has identity data ──────────────────────────────────────
		if ( ! empty( $author_data ) && is_array( $author_data ) ) {

			// Try user_login first — unique per site, most reliable
			if ( ! empty( $author_data['user_login'] ) ) {
				$user = get_user_by( 'login', sanitize_user( $author_data['user_login'] ) );
				if ( $user instanceof WP_User ) {
					return absint( $user->ID );
				}
			}

			// Try user_email second — also unique per site
			if ( ! empty( $author_data['user_email'] ) ) {
				$user = get_user_by( 'email', sanitize_email( $author_data['user_email'] ) );
				if ( $user instanceof WP_User ) {
					return absint( $user->ID );
				}
			}

			// Author not found on this site — apply chosen fallback
			if ( 'create_user' === $fallback ) {
				$new_id = $this->create_imported_user( $author_data );
				if ( $new_id > 0 ) {
					return $new_id;
				}
			}

			// Default: assign to currently logged-in admin
			return absint( get_current_user_id() );
		}

		// ── OLD EXPORT: no identity data, only a raw ID ────────────────────────
		// Use the original ID only if that user actually exists on this site.
		if ( $original_id > 0 && false !== get_userdata( $original_id ) ) {
			return $original_id;
		}

		// Original ID does not exist on this site — silently assign to current admin.
		return absint( get_current_user_id() );
	}

	/**
	 * Create a WordPress user from imported author data.
	 *
	 * Uses the plugin's configured default password if set, otherwise generates
	 * a secure random password. Optionally sends a welcome email.
	 * Logs email send results to a transient for the import summary.
	 *
	 * @param array $author_data Author identity array from post_author_data.
	 * @return int New user ID on success, 0 on failure.
	 */
	private function create_imported_user( $author_data ) {

		$user_login   = sanitize_user( $author_data['user_login'] ?? '', true );
		$user_email   = sanitize_email( $author_data['user_email'] ?? '' );
		$display_name = sanitize_text_field( $author_data['display_name'] ?? '' );
		$role         = sanitize_text_field( $author_data['role'] ?? 'subscriber' );

		// Validate required fields
		if ( empty( $user_login ) || empty( $user_email ) || ! is_email( $user_email ) ) {
			return 0;
		}

		// Whitelist the role to only valid WordPress roles
		$valid_roles = array_keys( wp_roles()->get_names() );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			$role = 'subscriber';
		}

		
		$default_password = get_option( 'peiwm_user_import_default_password', '' );
		$use_hash         = false;

		if ( ! empty( $default_password ) ) {
			$password = $default_password;
		} elseif ( ! empty( $author_data['user_pass_hash'] )
		           && preg_match( '/^\$(?:P\$|wp\$|2y\$)/', $author_data['user_pass_hash'] ) ) {
			// Will use hash directly after creation — use temp password for wp_create_user
			$password = wp_generate_password( 24, true, true );
			$use_hash = true;
		} else {
			$password = wp_generate_password( 16, true, true );
		}

		// Attempt to create user with original login
		$user_id = wp_create_user( $user_login, $password, $user_email );

		// If login is taken, try a derived fallback login
		if ( is_wp_error( $user_id ) ) {
			$fallback_login = sanitize_user(
				substr( strstr( $user_email, '@', true ), 0, 20 ) . '_imported',
				true
			);
			$user_id = wp_create_user( $fallback_login, $password, $user_email );
		}

		// If still failing (e.g. email already exists), bail out
		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'role'         => $role,
		) );

		// Restore original password hash if applicable
		if ( $use_hash ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->users,
				array( 'user_pass' => sanitize_text_field( $author_data['user_pass_hash'] ) ),
				array( 'ID' => $user_id ),
				array( '%s' ),
				array( '%d' )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
			clean_user_cache( $user_id );
		}

		// Send welcome email if option is enabled
		$send_email = (bool) get_option( 'peiwm_user_import_send_email', false );
		if ( $send_email ) {
			$site_name = sanitize_text_field( get_bloginfo( 'name' ) );
			$subject   = sprintf(
				/* translators: %s: site name */
				esc_html__( 'Your account on %s', 'post-export-import-with-media' ),
				$site_name
			);
			$message = sprintf(
				/* translators: 1: display name 2: username 3: password 4: login URL */
				esc_html__(
					"Hello %1\$s,\n\nYour account has been created.\n\nUsername: %2\$s\nPassword: %3\$s\n\nLogin at: %4\$s",
					'post-export-import-with-media'
				),
				$display_name,
				$user_login,
				$password,
				esc_url( wp_login_url() )
			);

			$sent      = wp_mail( $user_email, $subject, $message );
			$email_log = get_transient( 'peiwm_email_log' );
			$email_log = is_array( $email_log ) ? $email_log : array();
			$email_log[] = array(
				'email' => $user_email,
				'sent'  => $sent,
			);
			set_transient( 'peiwm_email_log', $email_log, HOUR_IN_SECONDS );
		}

		return absint( $user_id );
	}

	/**
	 * Check if a meta key belongs to a known SEO plugin and should be exported/imported.
	 *
	 * Covers: Yoast SEO, Rank Math, All In One SEO, SEOPress, The SEO Framework.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	private function is_seo_meta_key( $key ) {
		$seo_prefixes = array(
			'_yoast_wpseo_',       // Yoast SEO
			'_rank_math_',         // Rank Math (underscore variants)
			'_aioseo_',            // All In One SEO
			'_seopress_',          // SEOPress
			'_genesis_',           // Genesis SEO
			'_aioseop_',           // AIOSEO legacy
		);

		foreach ( $seo_prefixes as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a meta key is an internal WordPress core key that should never be exported.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	private function is_internal_wp_meta_key( $key ) {
		$blocked = array(
			'_edit_lock',
			'_edit_last',
			'_wp_trash_meta_time',
			'_wp_trash_meta_status',
			'_wp_old_slug',
			'_wp_old_date',
			'_encloseme',
			'_pingme',
			'_thumbnail_id',        // handled separately via featured_image
			'_wp_attached_file',
			'_wp_attachment_metadata',
			'_wp_page_template',
		);

		if ( in_array( $key, $blocked, true ) ) {
			return true;
		}

		// Block internal transient-style and cache keys
		if ( str_starts_with( $key, '_transient_' ) || str_starts_with( $key, '_site_transient_' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get WPML language data for a post
	 *
	 * @param int $post_id Post ID
	 * @return array|null WPML data or null if not available
	 */
	private function get_wpml_post_data( $post_id ) {
		// Check if WPML is active
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( null, $post_id );
			
			if ( is_array( $lang_info ) && ! empty( $lang_info['language_code'] ) ) {
				global $sitepress;
				$trid = $sitepress->get_element_trid( $post_id, 'post_post' );
				$translations = $sitepress->get_element_translations( $trid, 'post_post' );
				
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
			$lang_slug = pll_get_post_language( $post_id, 'slug' );

			if ( $lang_slug ) {
				// Get language object to access all properties
				$lang_obj = pll_get_post_language( $post_id, OBJECT );

				// Initialize variables
				$lang_locale = '';
				$lang_name = '';
				$lang_flag = '';
				$lang_rtl = false;

				// Get full language details from object
				if ( $lang_obj && is_object( $lang_obj ) ) {
					$lang_locale = isset( $lang_obj->locale ) ? $lang_obj->locale : '';
					$lang_name = isset( $lang_obj->name ) ? $lang_obj->name : '';
					
					// Flag code - try multiple properties
					if ( isset( $lang_obj->flag_code ) ) {
						$lang_flag = $lang_obj->flag_code;
					} elseif ( isset( $lang_obj->flag ) ) {
						$lang_flag = $lang_obj->flag;
					}
					
					// RTL - try multiple properties
					if ( isset( $lang_obj->is_rtl ) ) {
						$lang_rtl = (bool) $lang_obj->is_rtl;
					} elseif ( isset( $lang_obj->rtl ) ) {
						$lang_rtl = (bool) $lang_obj->rtl;
					}
				}

				// Fallback to function-based calls if object method fails
				if ( empty( $lang_locale ) && function_exists( 'pll_get_post_language' ) ) {
					$lang_locale = pll_get_post_language( $post_id, 'locale' );
				}
				if ( empty( $lang_name ) && function_exists( 'pll_get_post_language' ) ) {
					$lang_name = pll_get_post_language( $post_id, 'name' );
				}
				if ( empty( $lang_flag ) && function_exists( 'pll_get_post_language' ) ) {
					$lang_flag = pll_get_post_language( $post_id, 'flag' );
				}

				$translations = array();

				// Get all available languages
				if ( function_exists( 'pll_languages_list' ) ) {
					$languages = pll_languages_list();
					foreach ( $languages as $lang ) {
						if ( $lang !== $lang_slug && function_exists( 'pll_get_post' ) ) {
							$translation_id = pll_get_post( $post_id, $lang );
							if ( $translation_id && $translation_id != $post_id ) {
								$translations[ $lang ] = absint( $translation_id );
							}
						}
					}
				}

				return array(
					'language_code'   => sanitize_text_field( $lang_slug ),
					'language_locale' => sanitize_text_field( $lang_locale ),
					'language_name'   => sanitize_text_field( $lang_name ),
					'language_flag'   => sanitize_text_field( $lang_flag ),
					'language_rtl'   => (bool) $lang_rtl,
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
	 * Apply WPML/Polylang language assignment to an imported post
	 *
	 * @param int   $post_id Post ID
	 * @param array $wpml_data Language data from export
	 * @param bool  $wpml_support_enabled Whether WPML support is enabled (read from $_POST in AJAX caller)
	 */
	private function apply_wpml_language( $post_id, $wpml_data, $wpml_support_enabled = null ) {
		
		// Check if language code is present
		if ( empty( $wpml_data['language_code'] ) && empty( $wpml_data['language_locale'] ) ) {
			// error_log( 'PEIWM: No language code in wpml_data' );
			return array( 'success' => false, 'message' => 'No language code' );
		}

		// Check if multilingual support is enabled in settings.
		// Value is passed in from the AJAX caller (where the nonce is verified) to avoid
		// reading $_POST inside a private helper — which triggers PHPCS NonceVerification.Missing.
		if ( null === $wpml_support_enabled ) {
			$wpml_support_enabled = (bool) get_option( 'peiwm_enable_wpml_support', false );
		}
		
		if ( ! $wpml_support_enabled ) {
			// error_log( 'PEIWM: Multilingual support not enabled in settings' );
			return array( 'success' => false, 'message' => 'Multilingual support not enabled' );
		}

		// Check if Polylang is active (check both constant and functions)
		$polylang_active = defined( 'POLYLANG_VERSION' ) || ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) );
		
		$language_code = sanitize_text_field( $wpml_data['language_code'] );

		// Apply language based on which plugin is active
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			// WPML: Register the post with WPML in its original language
			do_action( 'wpml_set_element_language_details', array(
				'element_id'           => $post_id,
				'element_type'         => 'post_post',
				'trid'                 => null, // null = create new translation group
				'language_code'        => $language_code,
				'source_language_code' => null, // treat as original, not a translation
			) );

			return array( 'success' => true, 'message' => sprintf( 'WPML language: %s', $language_code ), 'plugin' => 'WPML' );

		} elseif ( ( defined( 'POLYLANG_VERSION' ) || ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) ) ) ) {
			
			$language_locale = !empty( $wpml_data['language_locale'] ) ? sanitize_text_field( $wpml_data['language_locale'] ) : '';
			$language_name = !empty( $wpml_data['language_name'] ) ? sanitize_text_field( $wpml_data['language_name'] ) : '';
			$language_flag = !empty( $wpml_data['language_flag'] ) ? sanitize_text_field( $wpml_data['language_flag'] ) : '';
			$language_rtl = !empty( $wpml_data['language_rtl'] ) ? (bool) $wpml_data['language_rtl'] : false;

			if ( function_exists( 'pll_languages_list' ) ) {
				$available_languages = pll_languages_list();
				
				// Try to use language slug first, fallback to locale
				$language_to_use = $language_code;

				// If language doesn't exist, try to create it
				if ( ! in_array( $language_to_use, $available_languages, true ) ) {
					
					// Try to create the language
					if ( !empty( $language_locale ) && !empty( $language_code ) ) {
						$created = $this->ensure_polylang_language_exists(
							$language_code,
							$language_locale,
							$language_name,
							$language_flag,
							$language_rtl
						);

						if ( ! $created ) {
							return array( 'success' => false, 'message' => sprintf( 'Language "%s" not found and could not be created. Add manually in Polylang settings.', $language_to_use ), 'plugin' => 'Polylang' );
						}
						// Refresh available languages after creation
						$available_languages = pll_languages_list();
					} else {
						return array( 'success' => false, 'message' => sprintf( 'Language "%s" not found. Available: %s', $language_to_use, implode( ', ', $available_languages ) ), 'plugin' => 'Polylang' );
					}
				}

				// Verify language now exists
				if ( ! in_array( $language_to_use, $available_languages, true ) ) {
					return array( 'success' => false, 'message' => sprintf( 'Language "%s" still not available after creation attempt', $language_to_use ), 'plugin' => 'Polylang' );
				}

				// Set the post language
				$result = pll_set_post_language( $post_id, $language_to_use );
				
				if ( $result ) {
					// Store translation info for post-import linking
					if ( ! empty( $wpml_data['translations'] ) && is_array( $wpml_data['translations'] ) ) {
						update_post_meta( $post_id, '_peiwm_pending_translations', $wpml_data['translations'] );
					}

					return array( 'success' => true, 'message' => sprintf( 'Polylang language: %s', $language_to_use ), 'plugin' => 'Polylang' );
				} else {
					return array( 'success' => false, 'message' => sprintf( 'Failed to set Polylang language: %s', $language_to_use ), 'plugin' => 'Polylang' );
				}
			}
		}

		return array( 'success' => false, 'message' => 'No multilingual plugin active' );
	}

	/**
	 * Create a language in Polylang if it doesn't exist
	 *
	 * @param string $slug Language slug (e.g., 'ru', 'bn', 'zh')
	 * @param string $locale Locale code (e.g., 'ru_RU', 'bn_BD', 'zh_CN')
	 * @param string $name Display name (e.g., 'Русский', 'বাংলা', '中文')
	 * @param string $flag Flag code (e.g., 'ru', 'bd', 'cn')
	 * @param bool $rtl RTL direction
	 * @return bool True if language exists or was created
	 */
	private function ensure_polylang_language_exists( $slug, $locale, $name = '', $flag = '', $rtl = false ) {
	
		// Check for Polylang using both constant and functions
		$polylang_active = defined( 'POLYLANG_VERSION' ) || ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) );

		if ( ! $polylang_active ) {
			return false;
		}

		if ( ! function_exists( 'pll_languages_list' ) ) {
			return false;
		}

		// Check if language already exists (by slug)
		$existing_languages = pll_languages_list();
	
		if ( in_array( $slug, $existing_languages, true ) ) {
			// error_log( 'PEIWM: Language already exists: ' . $slug );
			return true;
		}

		// Try to create language using Polylang model
		if ( ! function_exists( 'PLL' ) || ! isset( PLL()->model ) ) {
			// error_log( 'PEIWM: PLL() or PLL()->model not available' );
			return false;
		}

		$model = PLL()->model;
		
		// Parse locale to get country code for flag
		$country_code = $flag;
		if ( empty( $country_code ) && strpos( $locale, '_' ) !== false ) {
			$parts = explode( '_', $locale );
			$country_code = strtolower( $parts[1] );
		}
	
		// Default name from locale if not provided
		if ( empty( $name ) ) {
			$name = $locale;
		}

		$args = array(
			'name'       => $name,
			'slug'       => $slug,
			'locale'     => $locale,
			'rtl'        => $rtl,
			'flag'       => $country_code,
			'term_group' => 0,
		);

	
		$result = $model->add_language( $args );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Clear language cache
		delete_transient( 'pll_languages_list' );

		return true;
	}

	/**
	 * Finalize translation links after all posts are imported
	 * This should be called after batch import completes
	 */
	public function finalize_translation_links() {
		if ( ! defined( 'POLYLANG_VERSION' ) || ! function_exists( 'pll_save_post_translations' ) ) {
			return;
		}

		// Find all posts with pending translation meta
		$posts_with_pending = get_posts( array(
			'post_type'      => 'any',
			'post_status'   => 'any',
			'meta_key'       => '_peiwm_pending_translations',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		foreach ( $posts_with_pending as $post_id ) {
			$pending_translations = get_post_meta( $post_id, '_peiwm_pending_translations', true );

			if ( empty( $pending_translations ) || ! is_array( $pending_translations ) ) {
				continue;
			}

			// Get current post's language
			$current_lang = pll_get_post_language( $post_id, 'slug' );
			if ( empty( $current_lang ) ) {
				continue;
			}

			// Build translation array with existing posts on destination site
			$translations = array();

			foreach ( $pending_translations as $lang_code => $source_post_id ) {
				// Skip if it's the current post's language
				if ( $lang_code === $current_lang ) {
					continue;
				}

				// Try to find translation by matching source post ID stored in meta
				// Used a custom meta to track source post IDs
				$translations[ $lang_code ] = 0; // Placeholder
			}

			// For now, just add current post to its language group
			// Full translation linking requires source post ID mapping which is complex
			// Remove the pending meta after processing
			delete_post_meta( $post_id, '_peiwm_pending_translations' );
		}
	}


}