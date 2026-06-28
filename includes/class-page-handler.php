<?php
/**
 * Page Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page Handler Class - Manages page export/import operations
 */
class PEIWM_Page_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Page_Handler|null
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
	 * @return PEIWM_Page_Handler
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
		add_action( 'wp_ajax_peiwm_export_pages', array( $this, 'ajax_export_pages' ) );
		add_action( 'wp_ajax_peiwm_import_page', array( $this, 'ajax_import_page' ) );
		add_action( 'wp_ajax_peiwm_delete_pages', array( $this, 'ajax_delete_pages' ) );
		add_action( 'wp_ajax_peiwm_check_and_download_page_image', array( $this, 'ajax_check_and_download_image' ) );
		add_action( 'wp_ajax_peiwm_get_pages_list', array( $this, 'ajax_get_pages_list' ) );
	}

	/**
	 * AJAX: Export pages
	 */
	public function ajax_export_pages() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// Pagination — uses Export JSON File Size batch setting (same as posts)
			$page     = isset( $_POST['page'] )     ? max( 1, absint( wp_unslash( $_POST['page'] ) ) )     : 0;
			$per_page = isset( $_POST['per_page'] ) ? max( 1, absint( wp_unslash( $_POST['per_page'] ) ) ) : 0;

			// Non-selective — paginated using Export JSON File Size setting
			if ( $per_page > 0 ) {
				// Paginated mode (JS sends page + per_page)
				$query = new WP_Query( array(
					'post_type'      => 'page',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'no_found_rows'  => false,
				) );

				$export_data = array();
				foreach ( $query->posts as $p ) {
					$export_data[] = $this->build_page_export_data( $p );
				}
				wp_reset_postdata();

				$has_more = ( $page * $per_page ) < $query->found_posts;

				wp_send_json_success( array(
					'data'     => $export_data,
					'count'    => count( $export_data ),
					'has_more' => $has_more,
					'total'    => $query->found_posts,
					'page'     => $page,
				) );
				return;
			}

			// Legacy / non-paginated fallback (all at once)
			$pages = get_posts( array(
				'post_type'      => 'page',
				'numberposts'    => -1,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			) );

			$export_data = array();
			foreach ( $pages as $p ) {
				$export_data[] = $this->build_page_export_data( $p );
			}
			wp_reset_postdata();

			wp_send_json_success( array(
				'data'     => $export_data,
				'count'    => count( $export_data ),
				'has_more' => false,
				'total'    => count( $export_data ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * Build export data array for a single page.
	 *
	 * @param WP_Post $page        The page object.
	 * @return array
	 */
	private function build_page_export_data( $page ) {
		$page_data = array(
			'ID'             => absint( $page->ID ),
			'post_title'     => sanitize_text_field( $page->post_title ),
			'post_content'   => wp_kses_post( $page->post_content ),
			'post_excerpt'   => sanitize_textarea_field( $page->post_excerpt ),
			'post_status'    => sanitize_key( $page->post_status ),
			'post_type'      => sanitize_key( $page->post_type ),
			'post_author'    => absint( $page->post_author ),
			'post_date'      => sanitize_text_field( $page->post_date ),
			'post_modified'  => sanitize_text_field( $page->post_modified ),
			'post_name'      => sanitize_title( $page->post_name ),
			'menu_order'     => absint( $page->menu_order ),
			'post_parent'    => absint( $page->post_parent ),
			'page_template'  => get_page_template_slug( $page->ID ) ?: 'default',
			'meta'           => $this->get_page_meta_secure( $page->ID ),
			'featured_image' => $this->get_featured_image_secure( $page->ID ),
			'content_images' => $this->get_content_images_secure( $page->post_content ),
			'source_url'     => home_url(),
		);



		return $page_data;
	}

	/**
	 * AJAX: Get pages list (titles + IDs for selective export UI)
	 */
	public function ajax_get_pages_list() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$pages = get_posts( array(
			'post_type'      => 'page',
			'numberposts'    => -1,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		$list = array();
		foreach ( $pages as $page ) {
			$list[] = array(
				'ID'          => absint( $page->ID ),
				'post_title'  => sanitize_text_field( $page->post_title ),
				'post_date'   => sanitize_text_field( $page->post_date ),
				'post_status' => sanitize_key( $page->post_status ),
			);
		}

		wp_reset_postdata();
		wp_send_json_success( array( 'pages' => $list, 'count' => count( $list ) ) );
	}

	/**
	 * AJAX: Import page
	 */
	public function ajax_import_page() {
		if ( session_id() ) {
			session_write_close();
		}
		
		// Set reasonable execution time limit
		@set_time_limit( 60 ); // 60 seconds per page
		@ini_set( 'max_execution_time', '60' );
		
		// Optimize memory usage
		@ini_set( 'memory_limit', '256M' );
		
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- page_data is structurally validated and sanitized deeply via sanitize_page_data() later.
			$page_data_raw = isset( $_POST['page_data'] ) ? wp_unslash( $_POST['page_data'] ) : '';
			$download_missing_images = isset( $_POST['download_missing_images'] ) && $_POST['download_missing_images'] === '1';
			$check_media_library     = isset( $_POST['check_media_library'] ) && $_POST['check_media_library'] === '1';
			
			if ( empty( $page_data_raw ) ) {
				throw new Exception( esc_html__( 'No page data provided', 'post-export-import-with-media' ) );
			}
			
			$page_data = json_decode( $page_data_raw, true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( esc_html__( 'Invalid JSON data provided', 'post-export-import-with-media' ) );
			}

			if ( ! $page_data || ! is_array( $page_data ) ) {
				throw new Exception( esc_html__( 'Invalid page data', 'post-export-import-with-media' ) );
			}

			// SECURITY FIX: Validate required fields exist
			$required_fields = array( 'post_title', 'post_type' );
			foreach ( $required_fields as $field ) {
				if ( ! isset( $page_data[ $field ] ) ) {
					error_log( 'PEIWM Security: Missing required field in page import: ' . $field );
					throw new Exception( sprintf( esc_html__( 'Invalid page data: missing required field', 'post-export-import-with-media' ) ) );
				}
			}

			$sanitized_page_data = $this->sanitize_page_data( $page_data ); // Sanitize input data properly

			// Apply force_status override if provided
			$force_status = isset( $_POST['force_status'] ) ? sanitize_key( wp_unslash( $_POST['force_status'] ) ) : 'original';
			$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
			if ( $force_status !== 'original' && in_array( $force_status, $allowed_statuses, true ) ) {
				$sanitized_page_data['post_status'] = $force_status;
			}

			// Check if page already exists.
			// Primary: match by slug (post_name) — unique per post type in WordPress.
			// Fallback: match by title + content hash, only when content is non-empty,
			// to avoid false positives where multiple different pages share empty content.
			$existing_page  = null;
			$import_slug    = $sanitized_page_data['post_name'];
			$import_content = $sanitized_page_data['post_content'];

			if ( ! empty( $import_slug ) ) {
				$slug_matches = get_posts( array(
					'post_type'      => 'page',
					'post_status'    => 'any',
					'name'           => $import_slug,
					'posts_per_page' => 1,
				) );
				if ( ! empty( $slug_matches ) ) {
					$existing_page = $slug_matches[0];
				}
			}

			// Fallback: title + content hash — only when content is non-empty.
			if ( ! $existing_page && ! empty( trim( $import_content ) ) ) {
				$title_matches = get_posts( array(
					'post_type'      => 'page',
					'post_status'    => 'any',
					'title'          => $sanitized_page_data['post_title'],
					'posts_per_page' => 50,
				) );
				$import_hash = md5( $import_content );
				foreach ( $title_matches as $candidate ) {
					if ( md5( $candidate->post_content ) === $import_hash ) {
						$existing_page = $candidate;
						break;
					}
				}
			}

			$existing_pages = $existing_page ? array( $existing_page ) : array();

			if ( ! empty( $existing_pages ) ) {
				$existing_page = $existing_pages[0];
				// If force_status is set and differs from current, update the status
				if ( $force_status !== 'original' && in_array( $force_status, $allowed_statuses, true ) && $existing_page->post_status !== $force_status ) {
					wp_update_post( array(
						'ID'          => $existing_page->ID,
						'post_status' => $force_status,
					) );
					wp_reset_postdata();
					wp_send_json_success( array(
						'status' => 'updated',
						'reason' => sprintf( 'Status updated to %s', $force_status ),
					) );
				}
				wp_reset_postdata();
				wp_send_json_success( array(
					'status' => 'skipped',
					'reason' => esc_html__( 'Page already exists', 'post-export-import-with-media' ),
				) );
			}

			// Reset post data after check
			wp_reset_postdata();

			// Insert page
			$page_id = wp_insert_post( array(
				'post_title'    => $sanitized_page_data['post_title'],
				'post_content'  => $sanitized_page_data['post_content'],
				'post_excerpt'  => $sanitized_page_data['post_excerpt'],
				'post_status'   => $sanitized_page_data['post_status'],
				'post_type'     => 'page',
				'post_author'   => $sanitized_page_data['post_author'],
				'post_date'     => $sanitized_page_data['post_date'],
				'post_name'     => $sanitized_page_data['post_name'], // slug
				'menu_order'    => $sanitized_page_data['menu_order'],
				'post_parent'   => $sanitized_page_data['post_parent'],
			) );

			if ( is_wp_error( $page_id ) ) {
				wp_reset_postdata(); // Reset before throwing exception
				throw new Exception( sprintf(
					esc_html__( 'Failed to create page: %s', 'post-export-import-with-media' ),
					$page_id->get_error_message()
				) );
			}

			// Reset after page creation
			wp_reset_postdata();


			// Set page template — validate it exists on this site before applying
			if ( ! empty( $sanitized_page_data['page_template'] ) && 'default' !== $sanitized_page_data['page_template'] ) {
				$template = $sanitized_page_data['page_template'];
				$theme    = wp_get_theme();
				$templates = $theme->get_page_templates();
				// Accept if registered in theme or file exists in theme directory
				if ( isset( $templates[ $template ] ) || file_exists( get_stylesheet_directory() . '/' . $template ) ) {
					update_post_meta( $page_id, '_wp_page_template', $template );
				} else {
					update_post_meta( $page_id, '_wp_page_template', 'default' );
				}
			}

			// Import meta data
			if ( ! empty( $sanitized_page_data['meta'] ) && is_array( $sanitized_page_data['meta'] ) ) {
				$this->import_page_meta_secure( $page_id, $sanitized_page_data['meta'] );
			}

			// Import content images first and update page content
			$updated_content = $sanitized_page_data['post_content'];
			if ( $check_media_library && ! empty( $sanitized_page_data['content_images'] ) ) {
				$updated_content = $this->import_content_images_secure( $page_id, $sanitized_page_data['content_images'], $sanitized_page_data['post_content'], $download_missing_images );
			}
			// If not checking media, we skip image processing entirely - images remain as placeholders in content

			// Replace internal links from the source site with the destination site URL.
			$source_url = isset( $sanitized_page_data['source_url'] ) ? esc_url_raw( $sanitized_page_data['source_url'] ) : '';
			$dest_url   = untrailingslashit( home_url() );
			if ( ! empty( $source_url ) ) {
				$source_url = untrailingslashit( $source_url );
				if ( $source_url !== $dest_url ) {
					$updated_content = str_replace( $source_url, $dest_url, $updated_content );
				}
			}

			// Import featured image
			if ( $check_media_library && ! empty( $sanitized_page_data['featured_image'] ) ) {
				$this->import_featured_image_secure( $page_id, $sanitized_page_data['featured_image'], $download_missing_images );
			}

			// Update page content if it was modified
			if ( $updated_content !== $sanitized_page_data['post_content'] ) {
				wp_update_post( array(
					'ID' => $page_id,
					'post_content' => $updated_content,
				) );
				// Reset after page update
				wp_reset_postdata();
			}

			// Get detailed import results
			$import_results = $this->get_import_results();
			
			// Check if any images were missing
			$missing_images = $this->check_missing_images( $sanitized_page_data );
			$message = 'Page imported successfully';
			if ( ! empty( $missing_images ) ) {
				if ( $download_missing_images ) {
					$message .= '. Note: ' . count( $missing_images ) . ' image(s) not found and could not be downloaded.';
				} else {
					$message .= '. Note: ' . count( $missing_images ) . ' image(s) not found in media library. Import media files first or enable "Download missing images".';
				}
			}

			wp_send_json_success( array(
				'status'  => 'imported',
				'page_id' => $page_id,
				'message' => $message,
				'missing_images' => $missing_images,
				'download_enabled' => $download_missing_images,
				'import_details' => $import_results,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Import failed. Please check the file format.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Delete pages
	 */
	public function ajax_delete_pages() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$pages = get_posts( array(
				'post_type'   => 'page',
				'numberposts' => -1,
				'post_status' => 'any',
			) );

			if ( empty( $pages ) ) {
				wp_reset_postdata(); // Reset before returning
				wp_send_json_success( array( 'message' => esc_html__( 'No pages found to delete', 'post-export-import-with-media' ) ) );
			}

			$deleted_count = 0;
			$failed_count = 0;

			foreach ( $pages as $page ) {
				$result = wp_delete_post( $page->ID, true );
				if ( $result ) {
					$deleted_count++;
				} else {
					$failed_count++;
				}
			}

			// Reset global post data after processing
			wp_reset_postdata();

			$message = sprintf(
				esc_html__( 'Deleted %d pages successfully', 'post-export-import-with-media' ),
				$deleted_count
			);

			if ( $failed_count > 0 ) {
				$message .= sprintf(
					esc_html__( '. Failed to delete %d pages.', 'post-export-import-with-media' ),
					$failed_count
				);
			}

			wp_send_json_success( array(
				'message'       => $message,
				'deleted_count' => $deleted_count,
				'failed_count'  => $failed_count,
				'total_count'   => count( $pages ),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Delete operation failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}


	// Include all the helper methods from post handler (get_page_meta_secure, get_featured_image_secure, etc.)
	// These methods are identical to the post handler methods, just adapted for pages
	
	/**
	 * Get page meta securely
	 *
	 * @param int $page_id Page ID
	 * @return array Page meta
	 */
	private function get_page_meta_secure( $page_id ) {
		$meta        = get_post_meta( $page_id );
		$secure_meta = array();

		foreach ( $meta as $key => $values ) {
			if ( ! str_starts_with( $key, '_' ) ) { // Skip private meta
				$safe_key = sanitize_key( $key );
				$secure_meta[ $safe_key ] = array_map( function( $value ) {
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
	 * @param int $page_id Page ID
	 * @return array|null Featured image data
	 */
	private function get_featured_image_secure( $page_id ) {
		$thumbnail_id = get_post_thumbnail_id( $page_id );
		
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
	 * @param string $content Page content
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

		return array(
			'id'       => absint( $attachment_id ),
			'url'      => esc_url( wp_get_attachment_url( $attachment_id ) ),
			'title'    => sanitize_text_field( $attachment->post_title ),
			'alt'      => sanitize_text_field( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'filename' => sanitize_file_name( basename( get_attached_file( $attachment_id ) ) ),
		);
	}

	/**
	 * Sanitize page data
	 *
	 * @param array $page_data Raw page data
	 * @return array Sanitized page data
	 */
	private function sanitize_page_data( $page_data ) {
		return array(
			'post_title'    => isset( $page_data['post_title'] ) ? sanitize_text_field( $page_data['post_title'] ) : '',
			'post_content'  => isset( $page_data['post_content'] ) ? $this->sanitize_gutenberg_content( $page_data['post_content'] ) : '',
			'post_excerpt'  => isset( $page_data['post_excerpt'] ) ? sanitize_textarea_field( $page_data['post_excerpt'] ) : '',
			'post_status'   => isset( $page_data['post_status'] ) ? sanitize_key( $page_data['post_status'] ) : 'draft',
			'post_author'   => isset( $page_data['post_author'] ) ? absint( $page_data['post_author'] ) : get_current_user_id(),
			'post_date'     => isset( $page_data['post_date'] ) ? sanitize_text_field( $page_data['post_date'] ) : current_time( 'mysql' ),
			'post_name'     => isset( $page_data['post_name'] ) ? sanitize_title( $page_data['post_name'] ) : '',
			'menu_order'    => isset( $page_data['menu_order'] ) ? absint( $page_data['menu_order'] ) : 0,
			'post_parent'   => isset( $page_data['post_parent'] ) ? absint( $page_data['post_parent'] ) : 0,
			'page_template' => isset( $page_data['page_template'] ) ? sanitize_file_name( $page_data['page_template'] ) : '',
			'meta'          => isset( $page_data['meta'] ) && is_array( $page_data['meta'] ) ? $page_data['meta'] : array(),
			'featured_image' => isset( $page_data['featured_image'] ) ? $page_data['featured_image'] : null,
			'content_images' => isset( $page_data['content_images'] ) && is_array( $page_data['content_images'] ) ? $page_data['content_images'] : array(),
			'wpml_data'     => isset( $page_data['wpml_data'] ) && is_array( $page_data['wpml_data'] ) ? $page_data['wpml_data'] : null,
			'source_url'    => isset( $page_data['source_url'] ) ? esc_url_raw( $page_data['source_url'] ) : '',
		);
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

	// Add other helper methods similar to post handler...
	// (import_page_meta_secure, import_content_images_secure, import_featured_image_secure, etc.)
	// These would be nearly identical to the post handler methods

	/**
	 * Get import results for detailed feedback
	 *
	 * @return array Import results
	 */
	private function get_import_results() {
		return $this->import_results;
	}

	/**
	 * Check for missing images in page data
	 *
	 * @param array $page_data Page data
	 * @return array Missing image filenames
	 */
	private function check_missing_images( $page_data ) {
		$missing = array();

		// Check featured image
		if ( ! empty( $page_data['featured_image']['filename'] ) ) {
			$filename = $page_data['featured_image']['filename'];
			if ( ! $this->find_existing_attachment_by_filename( $filename ) ) {
				$missing[] = $filename;
			}
		}

		// Check content images
		if ( ! empty( $page_data['content_images'] ) ) {
			foreach ( $page_data['content_images'] as $image ) {
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
	 * Find existing attachment by filename
	 *
	 * @param string $filename Filename to search for
	 * @return int|null Attachment ID if found
	 */
	private function find_existing_attachment_by_filename( $filename ) {
		// First try exact filename match
		$attachments = get_posts( array(
			'post_type' => 'attachment',
			'meta_query' => array(
				array(
					'key' => '_wp_attached_file',
					'value' => $filename,
					'compare' => 'LIKE'
				)
			),
			'posts_per_page' => 1
		) );

		if ( ! empty( $attachments ) ) {
			wp_reset_postdata(); // Reset before returning
			return $attachments[0]->ID;
		}

		// Reset after first query
		wp_reset_postdata();

		// If not found, try searching by post title (filename without extension)
		$title = pathinfo( $filename, PATHINFO_FILENAME );
		$attachments = get_posts( array(
			'post_type' => 'attachment',
			'title' => $title,
			'posts_per_page' => 1
		) );

		if ( ! empty( $attachments ) ) {
			wp_reset_postdata(); // Reset before returning
			return $attachments[0]->ID;
		}

		// Reset after second query
		wp_reset_postdata();

		// Last resort: search by filename in post_title
		$attachments = get_posts( array(
			'post_type' => 'attachment',
			's' => $title,
			'posts_per_page' => 1
		) );

		$result = ! empty( $attachments ) ? $attachments[0]->ID : null;
		wp_reset_postdata(); // Always reset at the end
		return $result;
	}

	
    /**
	 * AJAX: Check and download individual image (reuse from post handler)
	 */
	public function ajax_check_and_download_image() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- image_data is structurally validated and its fields are sanitized below.
			$image_data_raw = isset( $_POST['image_data'] ) ? wp_unslash( $_POST['image_data'] ) : '';
			$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
			
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

			$attachment_id = $this->download_and_create_attachment( $image_url, $page_id, $image_title, $image_alt );

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
	 * Import page meta securely
	 *
	 * @param int   $page_id Page ID
	 * @param array $meta_data Meta data
	 */
	private function import_page_meta_secure( $page_id, $meta_data ) {
		foreach ( $meta_data as $key => $values ) {
			$safe_key = sanitize_key( $key );

			if ( empty( $safe_key ) || str_starts_with( $safe_key, '_' ) ) {
				continue; // Skip invalid or private keys
			}

			delete_post_meta( $page_id, $safe_key );

			foreach ( (array) $values as $value ) {
				// Detect PHP-serialized strings (ACF repeaters, flex content, link fields).
				// maybe_unserialize() returns the original string when it isn't serialized,
				// and returns the real PHP value (array/object) when it is. WordPress's
				// add_post_meta() will re-serialize arrays/objects automatically.
				$unserialized = maybe_unserialize( $value );
				if ( is_array( $unserialized ) || is_object( $unserialized ) ) {
					add_post_meta( $page_id, $safe_key, $unserialized );
				} else {
					// Use wp_kses_post() to preserve HTML from WYSIWYG/wysiwyg ACF fields.
					add_post_meta( $page_id, $safe_key, wp_kses_post( $value ) );
				}
			}
		}
	}

	/**
	 * Import content images securely and update content URLs
	 *
	 * @param int    $page_id Page ID
	 * @param array  $images_data Images data
	 * @param string $content Original content
	 * @param bool   $download_missing Whether to download missing images
	 * @return string Updated content
	 */
	private function import_content_images_secure( $page_id, $images_data, $content, $download_missing = false ) {
		$updated_content = $content;
		$url_mapping = array();

		foreach ( $images_data as $image_data ) {
			if ( ! is_array( $image_data ) || empty( $image_data['filename'] ) ) {
				continue;
			}

			$filename = sanitize_file_name( $image_data['filename'] );
			$image_title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
			$image_alt = isset( $image_data['alt'] ) ? sanitize_text_field( $image_data['alt'] ) : '';
			$old_url = isset( $image_data['url'] ) ? esc_url_raw( $image_data['url'] ) : '';

			// Try to find existing attachment by filename
			$attachment_id = $this->find_existing_attachment_by_filename( $filename );

			if ( $attachment_id ) {
				// Record that image was found locally
				$this->import_results[] = array(
					'type' => 'found_local',
					'filename' => $filename,
					'attachment_id' => $attachment_id,
					'message' => 'Found in media library: ' . $filename,
				);

				// Set alt text if provided
				if ( ! empty( $image_alt ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_alt );
				}

				// Fix _wp_attached_file meta to ensure proper srcset generation
				$this->fix_attachment_meta( $attachment_id );

				// Get new URL
				$new_url = wp_get_attachment_url( $attachment_id );
				if ( $new_url && ! empty( $old_url ) ) {
					$url_mapping[ $old_url ] = $new_url;
				}
				
				// Update image ID references in content
				if ( isset( $image_data['id'] ) ) {
					$old_id = absint( $image_data['id'] );
					// Update wp:image blocks
					$updated_content = preg_replace(
						'/("id":)' . $old_id . '([,}])/',
						'${1}' . $attachment_id . '${2}',
						$updated_content
					);
					// Update wp-image class
					$updated_content = str_replace(
						'wp-image-' . $old_id,
						'wp-image-' . $attachment_id,
						$updated_content
					);
				}
			} else if ( $download_missing && ! empty( $old_url ) ) {
				// Try to download the image from original URL
				$attachment_id = $this->download_and_create_attachment( $old_url, $page_id, $image_title, $image_alt );
				
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					// Successfully downloaded and created attachment
					$new_url = wp_get_attachment_url( $attachment_id );
					if ( $new_url ) {
						$url_mapping[ $old_url ] = $new_url;
					}
					
					// Update image ID references in content
					if ( isset( $image_data['id'] ) ) {
						$old_id = absint( $image_data['id'] );
						// Update wp:image blocks
						$updated_content = preg_replace(
							'/("id":)' . $old_id . '([,}])/',
							'${1}' . $attachment_id . '${2}',
							$updated_content
						);
						// Update wp-image class
						$updated_content = str_replace(
							'wp-image-' . $old_id,
							'wp-image-' . $attachment_id,
							$updated_content
						);
					}
				}
				// If download failed, image will be reported as missing
			} else {
				// Image not found in media library - will be reported in response
				// No error_log needed as this will be shown in the UI
			}
		}

		// Replace old URLs with new ones
		foreach ( $url_mapping as $old_url => $new_url ) {
			$updated_content = str_replace( $old_url, $new_url, $updated_content );
		}

		return $updated_content;
	}

	/**
	 * Import featured image securely
	 *
	 * @param int   $page_id Page ID
	 * @param array $image_data Image data
	 * @param bool  $download_missing Whether to download missing images
	 */
	private function import_featured_image_secure( $page_id, $image_data, $download_missing = false ) {
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

			set_post_thumbnail( $page_id, $attachment_id );
			
			if ( ! empty( $image_alt ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_alt );
			}

			// Fix _wp_attached_file meta to ensure proper srcset generation
			$this->fix_attachment_meta( $attachment_id );
		} else if ( $download_missing && ! empty( $image_data['url'] ) ) {
			// Try to download the featured image from original URL
			$image_url = esc_url_raw( $image_data['url'] );
			$image_title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
			
			$attachment_id = $this->download_and_create_attachment( $image_url, $page_id, $image_title, $image_alt );
			
			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $page_id, $attachment_id );
			}
			// If download failed, featured image will be reported as missing
		} else {
			// Featured image not found in media library - will be reported in response
			// No error_log needed as this will be shown in the UI
		}
	}

	/**
	 * Download and create attachment from URL
	 *
	 * @param string $image_url Image URL
	 * @param int    $page_id Page ID
	 * @param string $image_title Image title
	 * @param string $image_alt Image alt text
	 * @return int|WP_Error|null Attachment ID or error
	 */
	private function download_and_create_attachment( $image_url, $page_id, $image_title = '', $image_alt = '' ) {
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
		
		$attachment_id = media_sideload_image( $image_url, $page_id, $image_title, 'id' );
		
		// Remove filters
		remove_filter( 'http_request_timeout', array( $this, 'set_import_timeout' ) );
		remove_filter( 'http_request_args', array( $this, 'set_import_request_args' ) );
		
		// Reset global post data after media_sideload_image (creates attachment posts)
		wp_reset_postdata();
		
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
		
		// Reset global post data after metadata operations
		wp_reset_postdata();
	}

	/**
	 * Set import timeout for HTTP requests
	 *
	 * @param int $timeout Current timeout
	 * @return int New timeout
	 */
	public function set_import_timeout( $timeout ) {
		return 30; // 30 seconds timeout
	}

	/**
	 * Set import request args for HTTP requests
	 *
	 * @param array $args Current args
	 * @return array Modified args
	 */
	public function set_import_request_args( $args ) {
		$args['timeout'] = 30;
		$args['redirection'] = 3; // Limit redirects
		return $args;
	}

	/**
	 * Get WPML language data for a page
	 *
	 * @param int $page_id Page ID
	 * @return array|null WPML data or null if not available
	 */
	private function get_wpml_post_data( $page_id ) {
		// Check if WPML is active
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( null, $page_id );
			
			if ( is_array( $lang_info ) && ! empty( $lang_info['language_code'] ) ) {
				global $sitepress;
				$trid = $sitepress->get_element_trid( $page_id, 'post_page' );
				$translations = $sitepress->get_element_translations( $trid, 'post_page' );
				
				$translation_ids = array();
				foreach ( $translations as $lang_code => $translation ) {
					if ( $translation->element_id != $page_id ) {
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
			$lang_slug = pll_get_post_language( $page_id, 'slug' );

			if ( $lang_slug ) {
				// Get language object to access all properties
				$lang_obj = pll_get_post_language( $page_id, OBJECT );

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
					$lang_locale = pll_get_post_language( $page_id, 'locale' );
				}
				if ( empty( $lang_name ) && function_exists( 'pll_get_post_language' ) ) {
					$lang_name = pll_get_post_language( $page_id, 'name' );
				}
				if ( empty( $lang_flag ) && function_exists( 'pll_get_post_language' ) ) {
					$lang_flag = pll_get_post_language( $page_id, 'flag' );
				}

				$translations = array();

				// Get all available languages
				if ( function_exists( 'pll_languages_list' ) ) {
					$languages = pll_languages_list();
					foreach ( $languages as $lang ) {
						if ( $lang !== $lang_slug && function_exists( 'pll_get_post' ) ) {
							$translation_id = pll_get_post( $page_id, $lang );
							if ( $translation_id && $translation_id != $page_id ) {
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
	 * Apply WPML language assignment to an imported page
	 *
	 * @param int   $page_id Page ID
	 * @param array $wpml_data WPML data from export
	 */
	private function apply_wpml_language( $page_id, $wpml_data ) {
		// Check if language code is present
		if ( empty( $wpml_data['language_code'] ) && empty( $wpml_data['language_locale'] ) ) {
			return;
		}

		// Check if multilingual support is enabled in settings
		$wpml_support_enabled = get_option( 'peiwm_enable_wpml_support', false );
		if ( ! $wpml_support_enabled ) {
			return;
		}

		$language_code = sanitize_text_field( $wpml_data['language_code'] );

		// Apply language based on which plugin is active
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			// WPML: Register the page with WPML in its original language
			// This inserts a row into wp_icl_translations
			do_action( 'wpml_set_element_language_details', array(
				'element_id'           => $page_id,
				'element_type'         => 'post_page',
				'trid'                 => null, // null = create new translation group
				'language_code'        => $language_code,
				'source_language_code' => null, // treat as original, not a translation
			) );

			
		} elseif ( ( defined( 'POLYLANG_VERSION' ) || ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) ) ) ) {
			// Polylang: Get extended language info from export data
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
							// error_log( sprintf( 'PEIWM: Language "%s" not found and could not be created', $language_to_use ) );
							return;
						}
						// Refresh available languages after creation
						$available_languages = pll_languages_list();
					} else {
						// error_log( sprintf( 'PEIWM: Language "%s" not found. Available: %s', $language_to_use, implode( ', ', $available_languages ) ) );
						return;
					}
				}

				// Verify language now exists
				if ( ! in_array( $language_to_use, $available_languages, true ) ) {
					// error_log( sprintf( 'PEIWM: Language "%s" still not available after creation attempt', $language_to_use ) );
					return;
				}

				// Set the page language
				$result = pll_set_post_language( $page_id, $language_to_use );

				if ( $result ) {
					// Store translation info for post-import linking
					if ( ! empty( $wpml_data['translations'] ) && is_array( $wpml_data['translations'] ) ) {
						update_post_meta( $page_id, '_peiwm_pending_translations', $wpml_data['translations'] );
					}
					// error_log( sprintf( 'PEIWM: Applied Polylang language "%s" to page ID %d', $language_to_use, $page_id ) );
				} else {
					// error_log( sprintf( 'PEIWM: Failed to apply Polylang language "%s" to page ID %d', $language_to_use, $page_id ) );
				}
			}
		}
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
		if ( ! defined( 'POLYLANG_VERSION' ) || ! function_exists( 'pll_languages_list' ) ) {
			return false;
		}

		// Check if language already exists (by slug)
		$existing_languages = pll_languages_list();
		if ( in_array( $slug, $existing_languages, true ) ) {
			return true;
		}

		// Try to create language using Polylang model
		if ( ! function_exists( 'PLL' ) || ! isset( PLL()->model ) ) {
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
			// error_log( 'PEIWM: Failed to create language: ' . $result->get_error_message() );
			return false;
		}

		// Clear language cache
		delete_transient( 'pll_languages_list' );

		return true;
	}
}