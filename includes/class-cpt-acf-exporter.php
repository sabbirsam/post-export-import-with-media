<?php
/**
 * CPT & ACF Export/Import Handler
 *
 * Deliverable A: Public flatten_acf_fields_public() / import_acf_fields_public()
 *                used by class-post-handler.php for standard post/page exports.
 *
 * Deliverable B: Full CPT & ACF admin page AJAX endpoints (Pro only).
 *
 * @package Post_Export_Import_With_Media
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PEIM_CPT_ACF_Exporter
 */
class PEIM_CPT_ACF_Exporter {

	/**
	 * Singleton instance.
	 *
	 * @var PEIM_CPT_ACF_Exporter|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return PEIM_CPT_ACF_Exporter
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	// =========================================================================
	// BOOT — called only when Pro is active
	// =========================================================================

	/**
	 * Register all AJAX hooks for the CPT & ACF admin page.
	 * Called via add_action( 'init', ... ) only for Pro users.
	 */
	public function init() {

		add_action( 'wp_ajax_peim_get_cpt_list',             array( $this, 'ajax_get_cpt_list' ) );
		add_action( 'wp_ajax_peim_get_cpt_acf_fields',       array( $this, 'ajax_get_cpt_acf_fields' ) );
		add_action( 'wp_ajax_peim_get_cpt_posts_list',       array( $this, 'ajax_get_cpt_posts_list' ) );
		add_action( 'wp_ajax_peim_export_cpt',               array( $this, 'ajax_export_cpt' ) );
		add_action( 'wp_ajax_peim_export_all_cpts',          array( $this, 'ajax_export_all_cpts' ) );
		add_action( 'wp_ajax_peim_import_cpt_post',          array( $this, 'ajax_import_cpt_post' ) );
		add_action( 'wp_ajax_peim_batch_export_cpt_start',   array( $this, 'ajax_batch_export_cpt_start' ) );
		add_action( 'wp_ajax_peim_batch_export_cpt_process', array( $this, 'ajax_batch_export_cpt_process' ) );
		add_action( 'wp_ajax_peim_batch_import_cpt_start',   array( $this, 'ajax_batch_import_cpt_start' ) );
		add_action( 'wp_ajax_peim_batch_import_cpt_process', array( $this, 'ajax_batch_import_cpt_process' ) );
		add_action( 'admin_post_peiwm_download_cpt_export',  array( $this, 'download_cpt_export' ) );
	}

	// =========================================================================
	// SECURITY HELPER
	// =========================================================================

	/**
	 * Verify nonce + capability for every AJAX request.
	 */
	private function verify_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified here for all callers
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}
	}

	// =========================================================================
	// AJAX: CPT LIST
	// =========================================================================

	/**
	 * Return all registered non-built-in public CPTs with post count.
	 */
	public function ajax_get_cpt_list() {

		/* if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
			return;
		} */

		/* $main_instance_exp = PEIWM_Main::get_instance();
		$is_pro_exp        = $main_instance_exp->is_pro_active(); */

		$this->verify_request();

		$built_in = array(
			'post', 'page', 'attachment', 'revision', 'nav_menu_item',
			'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
			'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation',
		);

		$all_cpts = get_post_types( array( 'public' => true ), 'objects' );
		$result   = array();

		foreach ( $all_cpts as $cpt ) {
			if ( in_array( $cpt->name, $built_in, true ) ) {
				continue;
			}
			$count = wp_count_posts( $cpt->name );
			$total = 0;
			foreach ( (array) $count as $status => $n ) {
				if ( in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
					$total += (int) $n;
				}
			}
			$result[] = array(
				'name'  => $cpt->name,
				'label' => $cpt->labels->name,
				'count' => $total,
			);
		}

		wp_send_json_success( $result );
	}

	// =========================================================================
	// AJAX: GET ACF FIELDS FOR A CPT (for selective ACF export picker)
	// =========================================================================

	/**
	 * Return all ACF field groups and fields registered for a given post type.
	 * Used by the ACF field multi-select picker in the export UI.
	 */
	public function ajax_get_cpt_acf_fields() {
		$this->verify_request();

		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			wp_send_json_success( array( 'groups' => array() ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

		if ( empty( $post_type ) ) {
			wp_send_json_error( array( 'message' => 'Post type required' ) );
		}

		$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
		$result = array();

		foreach ( $groups as $group ) {
			$fields     = acf_get_fields( $group['key'] );
			$flat_fields = array();

			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					$flat_fields[] = array(
						'key'   => sanitize_text_field( $field['key'] ),
						'name'  => sanitize_text_field( $field['name'] ),
						'label' => sanitize_text_field( $field['label'] ),
						'type'  => sanitize_text_field( $field['type'] ),
					);
				}
			}

			if ( ! empty( $flat_fields ) ) {
				$result[] = array(
					'key'    => sanitize_text_field( $group['key'] ),
					'title'  => sanitize_text_field( $group['title'] ),
					'fields' => $flat_fields,
				);
			}
		}

		wp_send_json_success( array( 'groups' => $result ) );
	}

	// =========================================================================
	// AJAX: CPT POSTS LIST (for selective export)
	// =========================================================================

	/**
	 * Return a paginated, searchable list of posts for a given CPT.
	 * Mirrors ajax_get_posts_list() in class-post-handler.php:
	 * - Regular mode: 300 per page
	 * - Batch mode:   uses export_list_page_size from batch settings
	 * - Returns total_count, page_size, has_more, show_batch_warn
	 */
	public function ajax_get_cpt_posts_list() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$offset    = isset( $_POST['offset'] )    ? absint( wp_unslash( $_POST['offset'] ) )           : 0;
		$search    = isset( $_POST['search'] )    ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $post_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Post type is required', 'post-export-import-with-media' ) ) );
		}

		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore

		// Mirror post handler: batch mode uses configured page size, regular mode uses 300
		$batch_settings  = PEIWM_Batch_Settings::get_instance();
		$batch_enabled   = $batch_settings->is_batch_enabled();
		$page_size       = $batch_enabled
			? (int) $batch_settings->get_setting( 'export_list_page_size' )
			: 300;
		$page_size       = max( 10, min( $page_size, 2000 ) );

		// Get total count for this CPT
		$count_obj   = wp_count_posts( $post_type );
		$total_count = 0;
		foreach ( array( 'publish', 'draft', 'private', 'pending', 'future' ) as $s ) {
			$total_count += (int) ( $count_obj->$s ?? 0 );
		}

		$large_site      = $total_count >= 800;
		$show_batch_warn = $large_site && ! $batch_enabled;

		$args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => $page_size,
			'offset'                 => $offset,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$posts  = get_posts( $args );
		$result = array();

		foreach ( $posts as $post ) {
			$result[] = array(
				'ID'          => absint( $post->ID ),
				'post_title'  => sanitize_text_field( $post->post_title ),
				'post_status' => sanitize_key( $post->post_status ),
				'post_date'   => sanitize_text_field( $post->post_date ),
			);
		}

		wp_reset_postdata();

		wp_send_json_success( array(
			'posts'           => $result,
			'count'           => count( $result ),
			'total_count'     => $total_count,
			'offset'          => $offset,
			'page_size'       => $page_size,
			'has_more'        => ( $offset + count( $result ) ) < $total_count,
			'show_batch_warn' => $show_batch_warn,
			'batch_enabled'   => $batch_enabled,
		) );
	}

	// =========================================================================
	// AJAX: EXPORT CPT (chunked / selective)
	// =========================================================================

	/**
	 * Export posts of a given CPT, optionally filtered by IDs, paginated by page/per_page.
	 */
	public function ajax_export_cpt() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		$post_type    = isset( $_POST['post_type'] )         ? sanitize_key( wp_unslash( $_POST['post_type'] ) )      : '';
		$selected_ids = isset( $_POST['post_ids'] )          ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['post_ids'] ) ) ) ) ) : array();
		$export_acf   = isset( $_POST['export_acf_fields'] ) && '1' === sanitize_key( wp_unslash( $_POST['export_acf_fields'] ) );
		// Selective ACF fields: comma-separated field keys — empty means export all
		$acf_field_keys_raw = isset( $_POST['acf_field_keys'] ) ? sanitize_text_field( wp_unslash( $_POST['acf_field_keys'] ) ) : '';
		$acf_field_keys     = $acf_field_keys_raw !== '' ? array_filter( array_map( 'sanitize_text_field', explode( ',', $acf_field_keys_raw ) ) ) : array();
		$page         = isset( $_POST['page'] )              ? absint( wp_unslash( $_POST['page'] ) )                 : 1;
		$per_page     = isset( $_POST['per_page'] )          ? absint( wp_unslash( $_POST['per_page'] ) )             : 50;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $post_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Post type is required', 'post-export-import-with-media' ) ) );
		}

		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore

		$args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $selected_ids ) ) {
			$args['post__in']      = $selected_ids;
			$args['orderby']       = 'post__in';
			$args['no_found_rows'] = true;
			$args['posts_per_page'] = -1;
		}

		$query      = new WP_Query( $args );
		$posts_data = array();

		foreach ( $query->posts as $post ) {
			$posts_data[] = $this->build_post_export_data( $post, $export_acf, $acf_field_keys );
		}

		wp_reset_postdata();

		wp_send_json_success( array(
			'posts'     => $posts_data,
			'total'     => $query->found_posts,
			'page'      => $page,
			'has_more'  => empty( $selected_ids ) && ( $page * $per_page ) < $query->found_posts,
			'post_type' => $post_type,
		) );
	}

	// =========================================================================
	// AJAX: EXPORT ALL CPTs (one file per CPT, sequential chunked)
	// =========================================================================

	/**
	 * Export ALL non-built-in public CPTs at once.
	 *
	 * The JS calls this endpoint repeatedly (page by page) per CPT, accumulating
	 * posts until has_more is false, then moves to the next CPT.
	 * Each CPT produces one JSON download file.
	 *
	 * Request params:
	 *   post_type         — current CPT slug being exported
	 *   export_acf_fields — '1'|'0'
	 *   page              — 1-based page number
	 *   per_page          — posts per call (default 50)
	 */
	public function ajax_export_all_cpts() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_type  = isset( $_POST['post_type'] )         ? sanitize_key( wp_unslash( $_POST['post_type'] ) )       : '';
		$export_acf = isset( $_POST['export_acf_fields'] ) && '1' === sanitize_key( wp_unslash( $_POST['export_acf_fields'] ) );
		$page       = isset( $_POST['page'] )              ? absint( wp_unslash( $_POST['page'] ) )                  : 1;
		$per_page   = isset( $_POST['per_page'] )          ? absint( wp_unslash( $_POST['per_page'] ) )              : 50;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid post type', 'post-export-import-with-media' ) ) );
		}

		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore

		$args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
		);

		$query      = new WP_Query( $args );
		$posts_data = array();

		foreach ( $query->posts as $post ) {
			$posts_data[] = $this->build_post_export_data( $post, $export_acf );
		}

		wp_reset_postdata();

		wp_send_json_success( array(
			'posts'     => $posts_data,
			'total'     => $query->found_posts,
			'page'      => $page,
			'has_more'  => ( $page * $per_page ) < $query->found_posts,
			'post_type' => $post_type,
		) );
	}

	// =========================================================================
	// CORE: Build per-post export payload
	// =========================================================================

	/**
	 * Build the full export data array for a single post.
	 *
	 * @param WP_Post $post       The post object.
	 * @param bool    $export_acf Whether to include ACF fields.
	 * @return array
	 */
	/**
	 * Build export data array for a single post.
	 *
	 * @param WP_Post $post           The post object.
	 * @param bool    $export_acf     Whether to export ACF fields.
	 * @param array   $acf_field_keys Optional whitelist of ACF field keys to export.
	 *                                Empty array = export all fields.
	 * @return array
	 */
	private function build_post_export_data( $post, $export_acf = true, $acf_field_keys = array() ) {
		$data = array(
			'ID'             => absint( $post->ID ),
			'post_title'     => sanitize_text_field( $post->post_title ),
			'post_content'   => wp_kses_post( $post->post_content ),
			'post_excerpt'   => sanitize_textarea_field( $post->post_excerpt ),
			'post_status'    => sanitize_key( $post->post_status ),
			'post_type'      => sanitize_key( $post->post_type ),
			'post_name'      => sanitize_title( $post->post_name ),
			'post_date'      => sanitize_text_field( $post->post_date ),
			'menu_order'     => absint( $post->menu_order ),
			'comment_status' => sanitize_key( $post->comment_status ),
			'ping_status'    => sanitize_key( $post->ping_status ),
			'post_meta'      => $this->get_all_post_meta( $post->ID ),
			'taxonomies'     => $this->get_post_taxonomies( $post->ID, $post->post_type ),
			'featured_image' => $this->get_featured_image_data( $post->ID ),
			'attached_media' => $this->get_attached_media( $post->ID ),
			'content_images' => $this->get_content_images( $post->post_content ),
			'acf_fields'     => array(),
		);

		if ( $export_acf && $this->is_acf_active() ) {
			$acf_raw = get_fields( $post->ID );
			if ( ! empty( $acf_raw ) && is_array( $acf_raw ) ) {
				$all_fields = $this->flatten_acf_fields( $post->ID, $acf_raw );
				// If specific field keys were requested, filter to only those.
				if ( ! empty( $acf_field_keys ) ) {
					$filtered = array();
					foreach ( $all_fields as $field_name => $field_data ) {
						if ( isset( $field_data['field_key'] ) && in_array( $field_data['field_key'], $acf_field_keys, true ) ) {
							$filtered[ $field_name ] = $field_data;
						}
					}
					$data['acf_fields'] = $filtered;
				} else {
					$data['acf_fields'] = $all_fields;
				}
			}
		}

		return $data;
	}

	// =========================================================================
	// HELPERS: Export data builders
	// =========================================================================

	/**
	 * Export ALL post meta, including underscore-prefixed ACF reference keys.
	 * NOTE: deliberately keeps _ keys (unlike class-post-handler.php) so ACF
	 * can re-link field definitions on import.
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function get_all_post_meta( $post_id ) {
		$skip_keys = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_encloseme', '_pingme' );
		$raw       = get_post_meta( $post_id );
		$result    = array();

		foreach ( $raw as $key => $values ) {
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}
			// Unserialize single values for easier portability
			$result[ $key ] = array_map( 'maybe_unserialize', $values );
		}

		return $result;
	}

	/**
	 * Get all taxonomy terms for a post.
	 *
	 * @param int    $post_id
	 * @param string $post_type
	 * @return array
	 */
	private function get_post_taxonomies( $post_id, $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$result     = array();

		foreach ( $taxonomies as $tax_name => $tax_obj ) {
			$terms = wp_get_object_terms( $post_id, $tax_name, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$parent_slug = '';
				if ( $term->parent ) {
					$parent_term = get_term( $term->parent, $tax_name );
					$parent_slug = ( $parent_term && ! is_wp_error( $parent_term ) ) ? sanitize_title( $parent_term->slug ) : '';
				}
				$result[] = array(
					'taxonomy'    => sanitize_key( $tax_name ),
					'name'        => sanitize_text_field( $term->name ),
					'slug'        => sanitize_title( $term->slug ),
					'description' => sanitize_textarea_field( $term->description ),
					'parent_slug' => $parent_slug,
				);
			}
		}

		return $result;
	}

	/**
	 * Get featured image data for export.
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	private function get_featured_image_data( $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return null;
		}

		$attachment = get_post( $thumb_id );
		return array(
			'url'      => esc_url( wp_get_attachment_url( $thumb_id ) ),
			'filename' => sanitize_file_name( basename( get_attached_file( $thumb_id ) ) ),
			'title'    => sanitize_text_field( $attachment ? $attachment->post_title : '' ),
			'alt'      => sanitize_text_field( get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ),
			'mime'     => sanitize_mime_type( get_post_mime_type( $thumb_id ) ),
		);
	}

	/**
	 * Get all media attachments for a post.
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function get_attached_media( $post_id ) {
		$attachments = get_posts( array(
			'post_parent'   => $post_id,
			'post_type'     => 'attachment',
			'post_status'   => 'inherit',
			'numberposts'   => -1,
			'no_found_rows' => true,
		) );

		$result = array();
		foreach ( $attachments as $att ) {
			$result[] = array(
				'url'      => esc_url( wp_get_attachment_url( $att->ID ) ),
				'filename' => sanitize_file_name( basename( get_attached_file( $att->ID ) ) ),
				'title'    => sanitize_text_field( $att->post_title ),
				'alt'      => sanitize_text_field( get_post_meta( $att->ID, '_wp_attachment_image_alt', true ) ),
				'mime'     => sanitize_mime_type( $att->post_mime_type ),
			);
		}

		return $result;
	}

	/**
	 * Extract content images from post_content (Gutenberg blocks + img tags).
	 * Mirrors get_content_images_secure() in class-post-handler.php.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function get_content_images( $content ) {
		$images     = array();
		$seen_ids   = array();

		// 1. Extract from wp:image block comments — e.g. <!-- wp:image {"id":123,...} -->
		preg_match_all( '/wp:image\s+{[^}]*"id":(\d+)[^}]*}/', $content, $block_matches );
		if ( ! empty( $block_matches[1] ) ) {
			foreach ( $block_matches[1] as $raw_id ) {
				$att_id = absint( $raw_id );
				if ( $att_id > 0 && ! in_array( $att_id, $seen_ids, true ) ) {
					$image_data = $this->get_attachment_data( $att_id );
					if ( $image_data ) {
						$images[]   = $image_data;
						$seen_ids[] = $att_id;
					}
				}
			}
		}

		// 2. Extract from wp-image-{id} CSS class on <img> tags
		preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/', $content, $class_matches );
		if ( ! empty( $class_matches[1] ) ) {
			foreach ( $class_matches[1] as $raw_id ) {
				$att_id = absint( $raw_id );
				if ( $att_id > 0 && ! in_array( $att_id, $seen_ids, true ) ) {
					$image_data = $this->get_attachment_data( $att_id );
					if ( $image_data ) {
						$images[]   = $image_data;
						$seen_ids[] = $att_id;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Get attachment data array for a single attachment ID.
	 *
	 * @param int $attachment_id
	 * @return array|null
	 */
	private function get_attachment_data( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		return array(
			'id'       => absint( $attachment_id ),
			'url'      => esc_url( wp_get_attachment_url( $attachment_id ) ),
			'filename' => sanitize_file_name( basename( get_attached_file( $attachment_id ) ) ),
			'title'    => sanitize_text_field( $attachment->post_title ),
			'alt'      => sanitize_text_field( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
		);
	}

	// =========================================================================
	// ACF FLATTEN (export)
	// =========================================================================

	/**
	 * Public wrapper — called by class-post-handler.php (Deliverable A).
	 *
	 * @param int   $post_id
	 * @param array $fields
	 * @param string $parent_key
	 * @return array
	 */
	public function flatten_acf_fields_public( $post_id, $fields, $parent_key = '' ) {
		return $this->flatten_acf_fields( $post_id, $fields, $parent_key );
	}

	/**
	 * Recursively flatten ACF fields into an export-friendly structure.
	 *
	 * @param int    $post_id
	 * @param array  $fields     Associative: field_name => value (from get_fields()).
	 * @param string $parent_key Prefix for nested meta key lookups.
	 * @return array
	 */
	private function flatten_acf_fields( $post_id, $fields, $parent_key = '' ) {
		$result = array();

		foreach ( $fields as $field_name => $value ) {
			$meta_key  = $parent_key ? $parent_key . '_' . $field_name : $field_name;
			$field_key = get_post_meta( $post_id, '_' . $meta_key, true );

			// Detect field type if ACF internals are available
			if ( ! empty( $field_key ) && function_exists( 'acf_get_field' ) ) {
				$field_obj = acf_get_field( $field_key );
				if ( $field_obj ) {
					// Convert taxonomy IDs to slugs for portability
					if ( 'taxonomy' === $field_obj['type'] ) {
						$value = $this->convert_taxonomy_ids_to_slugs( $value, $field_obj );
					}
					// Skip UI-only display fields
					if ( in_array( $field_obj['type'], array( 'accordion', 'tab', 'message', 'separator' ), true ) ) {
						continue;
					}
					// Skip media fields — IDs are site-specific; featured_image handles them
					if ( in_array( $field_obj['type'], array( 'image', 'file', 'gallery', 'relationship', 'post_object', 'page_link', 'user' ), true ) ) {
						continue;
					}
				}
			}

			if ( is_array( $value ) && ! empty( $value ) ) {
				if ( isset( $value[0] ) && is_array( $value[0] ) ) {
					// Repeater: array of row arrays
					$rows = array();
					foreach ( $value as $row_index => $row ) {
						$row_prefix = $meta_key . '_' . $row_index;
						$rows[]     = $this->flatten_acf_fields( $post_id, $row, $row_prefix );
					}
					$result[ $field_name ] = array(
						'field_name' => $field_name,
						'field_key'  => $field_key,
						'type'       => 'repeater',
						'value'      => $rows,
					);
				} elseif ( is_string( key( $value ) ) ) {
					// Group / flexible content layout
					$result[ $field_name ] = array(
						'field_name' => $field_name,
						'field_key'  => $field_key,
						'type'       => 'group',
						'value'      => $this->flatten_acf_fields( $post_id, $value, $meta_key ),
					);
				} else {
					// Simple array (checkbox multi-select, etc.)
					$result[ $field_name ] = array(
						'field_name' => $field_name,
						'field_key'  => $field_key,
						'type'       => 'array',
						'value'      => $value,
					);
				}
			} else {
				$result[ $field_name ] = array(
					'field_name' => $field_name,
					'field_key'  => $field_key,
					'type'       => 'scalar',
					'value'      => $value,
				);
			}
		}

		return $result;
	}

	/**
	 * Convert ACF taxonomy field IDs to term slugs for portability.
	 *
	 * @param int|array $value
	 * @param array     $field_obj ACF field object.
	 * @return string|array
	 */
	private function convert_taxonomy_ids_to_slugs( $value, $field_obj ) {
		$taxonomy = $field_obj['taxonomy'] ?? '';
		if ( empty( $taxonomy ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_map( function ( $id ) use ( $taxonomy ) {
				$term = get_term( (int) $id, $taxonomy );
				return ( $term && ! is_wp_error( $term ) ) ? $term->slug : $id;
			}, $value );
		}

		$term = get_term( (int) $value, $taxonomy );
		return ( $term && ! is_wp_error( $term ) ) ? $term->slug : $value;
	}

	// =========================================================================
	// ACF IMPORT (restore)
	// =========================================================================

	/**
	 * Public wrapper — called by class-post-handler.php (Deliverable A).
	 *
	 * @param int   $post_id
	 * @param array $acf_fields
	 */
	public function import_acf_fields_public( $post_id, $acf_fields ) {
		$this->import_acf_fields( $post_id, $acf_fields );
	}

	/**
	 * Recursively import ACF fields using ACF's own update_field() API.
	 *
	 * @param int   $post_id
	 * @param array $acf_fields Exported fields array.
	 */
	private function import_acf_fields( $post_id, $acf_fields ) {
		foreach ( $acf_fields as $field_export ) {
			$field_key  = $field_export['field_key']  ?? '';
			$field_name = $field_export['field_name'] ?? '';
			$type       = $field_export['type']       ?? 'scalar';
			$value      = $field_export['value']      ?? '';

			if ( empty( $field_key ) && empty( $field_name ) ) {
				continue;
			}

			if ( ! function_exists( 'update_field' ) ) {
				// ACF not active — fall back to raw meta
				update_post_meta( $post_id, sanitize_key( $field_name ), $value );
				continue;
			}

			// FIXED: Bug 2 — Fallback to field_name if field_key does not resolve
			$identifier = $field_name;
			if ( ! empty( $field_key ) && function_exists( 'get_field_object' ) ) {
				$field_obj_by_key = get_field_object( $field_key );
				if ( $field_obj_by_key ) {
					$identifier = $field_key;
				} else {
					$identifier = $field_name;
					$field_obj_by_key = function_exists( 'get_field_object' ) ? get_field_object( 'field_' . $field_name ) : null;
					if ( ! $field_obj_by_key && function_exists( 'acf_get_field' ) ) {
						$field_obj_by_key = null;
					}
				}
			}
			$field_obj = isset( $field_obj_by_key ) ? $field_obj_by_key : null;

			// Resolve taxonomy slugs → IDs if needed
			if ( $field_obj ) {
				if ( 'taxonomy' === $field_obj['type'] ) {
					$value = $this->resolve_taxonomy_slugs_to_ids( $value, $field_obj );
				}
				// Skip media fields entirely — IDs are site-specific
				if ( in_array( $field_obj['type'], array( 'image', 'file', 'gallery', 'relationship', 'post_object', 'page_link', 'user' ), true ) ) {
					continue;
				}
			}

			// FIXED: Bug 4 — Use field_key for sub-fields if available
			if ( 'repeater' === $type ) {
				$rows = array();
				foreach ( (array) $value as $row ) {
					$row_data = array();
					foreach ( (array) $row as $sub ) {
						if ( ! isset( $sub['field_name'] ) ) {
							continue;
						}
						$sub_identifier = $sub['field_name'];
						if ( ! empty( $sub['field_key'] ) && function_exists( 'get_field_object' ) ) {
							$sub_obj = get_field_object( $sub['field_key'] );
							if ( $sub_obj ) {
								$sub_identifier = $sub['field_key'];
							}
						}
						$row_data[ $sub_identifier ] = $sub['value'] ?? '';
					}
					$rows[] = $row_data;
				}
				update_field( $identifier, $rows, $post_id );
			} elseif ( 'group' === $type ) {
				$group_data = array();
				foreach ( (array) $value as $sub ) {
					if ( ! isset( $sub['field_name'] ) ) {
						continue;
					}
					$sub_identifier = $sub['field_name'];
					if ( ! empty( $sub['field_key'] ) && function_exists( 'get_field_object' ) ) {
						$sub_obj = get_field_object( $sub['field_key'] );
						if ( $sub_obj ) {
							$sub_identifier = $sub['field_key'];
						}
					}
					$group_data[ $sub_identifier ] = $sub['value'] ?? '';
				}
				update_field( $identifier, $group_data, $post_id );
			} else {
				update_field( $identifier, $value, $post_id );
			}
		}
	}

	/**
	 * Resolve term slugs back to term IDs for ACF taxonomy fields.
	 *
	 * @param string|array $value
	 * @param array        $field_obj ACF field object.
	 * @return int|array
	 */
	private function resolve_taxonomy_slugs_to_ids( $value, $field_obj ) {
		$taxonomy = $field_obj['taxonomy'] ?? '';
		if ( empty( $taxonomy ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$ids = array();
			foreach ( $value as $slug ) {
				$term = get_term_by( 'slug', sanitize_title( (string) $slug ), $taxonomy );
				if ( $term ) {
					$ids[] = $term->term_id;
				}
			}
			return $ids;
		}

		$term = get_term_by( 'slug', sanitize_title( (string) $value ), $taxonomy );
		return $term ? $term->term_id : $value;
	}

	// =========================================================================
	// AJAX: IMPORT CPT POST (single)
	// =========================================================================

	/**
	 * Import a single CPT post from JSON data.
	 */
	public function ajax_import_cpt_post() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		// Use wp_unslash() only — sanitize_text_field() strips HTML which corrupts post_content and content_images JSON
		$raw = isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded; fields sanitized individually inside do_import_single_post()
		if ( empty( $raw ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No post data provided', 'post-export-import-with-media' ) ) );
		}

		$post_data = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $post_data ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid JSON data', 'post-export-import-with-media' ) ) );
		}

		$check_media    = isset( $_POST['check_media_library'] )     && '1' === sanitize_key( wp_unslash( $_POST['check_media_library'] ) );
		$download_media = isset( $_POST['download_missing_images'] ) && '1' === sanitize_key( wp_unslash( $_POST['download_missing_images'] ) );

		// Optional per-post status override (set via the ⚙ gear modal in the UI)
		$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
		$force_status     = isset( $_POST['force_status'] ) ? sanitize_key( wp_unslash( $_POST['force_status'] ) ) : 'original';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( 'original' !== $force_status && in_array( $force_status, $allowed_statuses, true ) ) {
			$post_data['post_status'] = $force_status;
		}

		$result = $this->do_import_single_post( $post_data, $check_media, $download_media );

		if ( 'failed' === $result['status'] ) {
			wp_send_json_error( $result );
		} else {
			wp_send_json_success( $result );
		}
	}

	// =========================================================================
	// CORE: Import a single post (shared by single + batch)
	// =========================================================================

	/**
	 * Do the actual import of one post data array.
	 *
	 * @param array $post_data
	 * @param bool  $check_media
	 * @param bool  $download_media
	 * @return array { status, message, post_id? }
	 */
	private function do_import_single_post( $post_data, $check_media, $download_media ) {
		$post_type = isset( $post_data['post_type'] ) ? sanitize_key( $post_data['post_type'] ) : '';

		if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
			return array(
				'status'  => 'failed',
				'message' => sprintf(
					/* translators: %s: post type slug */
					esc_html__( 'Post type "%s" does not exist on this site. Please register it before importing.', 'post-export-import-with-media' ),
					esc_html( $post_type )
				),
			);
		}

		// FIXED: Bug 3 — Match on slug + title together
		$post_name       = sanitize_title( $post_data['post_name'] ?? '' );
		$post_title_norm = sanitize_text_field( $post_data['post_title'] ?? '' );
		
		if ( ! empty( $post_name ) ) {
			$existing = get_page_by_path( $post_name, OBJECT, $post_type );
			
			$is_true_duplicate = $existing && (
				strtolower( trim( $existing->post_title ) ) === strtolower( trim( $post_title_norm ) )
			);

			if ( $is_true_duplicate ) {
				$desired_status   = sanitize_key( $post_data['post_status'] ?? 'publish' );
				$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );

				// If a force_status was set and it differs from the current status, update it
				if ( in_array( $desired_status, $allowed_statuses, true ) && $existing->post_status !== $desired_status ) {
					wp_update_post( array(
						'ID'          => $existing->ID,
						'post_status' => $desired_status,
					) );
					return array(
						'status'  => 'updated',
						'post_id' => $existing->ID,
						'message' => sprintf(
							/* translators: 1: post title, 2: new status */
							esc_html__( 'Post "%1$s" already exists — status updated to %2$s.', 'post-export-import-with-media' ),
							esc_html( $post_title_norm ),
							esc_html( $desired_status )
						),
					);
				}

				return array(
					'status'  => 'skipped',
					'post_id' => $existing->ID,
					'message' => sprintf(
						/* translators: %s: post title */
						esc_html__( 'Post "%s" already exists (slug + title match). Skipped.', 'post-export-import-with-media' ),
						esc_html( $post_title_norm )
					),
				);
			}

			// Slug exists but title differs — append suffix to avoid slug collision on wp_insert_post
			if ( $existing ) {
				$post_data['post_name'] = $post_name . '-imported-' . time();
			}
		}

		$post_arr = array(
			'post_title'     => sanitize_text_field( $post_data['post_title']     ?? '' ),
			'post_content'   => $this->sanitize_gutenberg_content( $post_data['post_content']  ?? '' ),
			'post_excerpt'   => sanitize_textarea_field( $post_data['post_excerpt'] ?? '' ),
			'post_status'    => sanitize_key(          $post_data['post_status']   ?? 'draft' ),
			'post_type'      => $post_type,
			'post_name'      => sanitize_title(        $post_data['post_name']     ?? '' ),
			'post_date'      => sanitize_text_field(   $post_data['post_date']     ?? current_time( 'mysql' ) ),
			'menu_order'     => absint(                $post_data['menu_order']    ?? 0 ),
			'comment_status' => sanitize_key(          $post_data['comment_status'] ?? 'closed' ),
		);

		$post_id = wp_insert_post( $post_arr );
		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'failed',
				'message' => $post_id->get_error_message(),
			);
		}

		// If content has Gutenberg block comments, wp_insert_post will have stripped them
		// via its internal kses pass. Re-save the sanitized (block-safe) content immediately.
		if ( ! empty( $post_arr['post_content'] ) ) {
			kses_remove_filters();
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $post_arr['post_content'],
			) );
			kses_init_filters();
		}

		// Import taxonomies
		if ( ! empty( $post_data['taxonomies'] ) && is_array( $post_data['taxonomies'] ) ) {
			$this->import_taxonomies( $post_id, $post_data['taxonomies'] );
		}

		// Import raw post meta
		// FIXED: Bug 1 — Pass has_acf flag
		$has_acf = ! empty( $post_data['acf_fields'] ) && is_array( $post_data['acf_fields'] );
		if ( ! empty( $post_data['post_meta'] ) && is_array( $post_data['post_meta'] ) ) {
			$this->import_post_meta( $post_id, $post_data['post_meta'], $has_acf );
		}

		// Import ACF fields
		if ( ! empty( $post_data['acf_fields'] ) && is_array( $post_data['acf_fields'] ) ) {
			$this->import_acf_fields( $post_id, $post_data['acf_fields'] );
		}

		// Import featured image
		if ( $check_media && ! empty( $post_data['featured_image'] ) && is_array( $post_data['featured_image'] ) ) {
			$this->import_featured_image( $post_id, $post_data['featured_image'], $download_media );
		}

		// Import content images and remap URLs in post_content.
		// IMPORTANT: use raw $post_data['post_content'] (not $post_arr['post_content']) as the
		// working copy so that Gutenberg block comments ("<!-- wp:image {...} -->") are intact
		// for ID remapping. The final result is always saved back via wp_update_post so the DB
		// reflects both the correct content and the remapped URLs.
		// kses_remove_filters() prevents wp_update_post from stripping block comments.
		if ( $check_media && ! empty( $post_data['content_images'] ) && is_array( $post_data['content_images'] ) ) {
			$remapped_content = $this->import_content_images(
				$post_id,
				$post_data['content_images'],
				$post_data['post_content'] ?? '',
				$download_media
			);
			// Temporarily unhook KSES so block comments survive the update.
			kses_remove_filters();
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $remapped_content,
			) );
			kses_init_filters();
		}

		wp_cache_flush();

		return array(
			'status'  => 'imported',
			'post_id' => $post_id,
			'message' => sprintf(
				/* translators: %s: post title */
				esc_html__( 'Imported: %s', 'post-export-import-with-media' ),
				esc_html( $post_arr['post_title'] )
			),
		);
	}

	// =========================================================================
	// IMPORT HELPERS
	// =========================================================================

	/**
	 * Sanitize imported post content while preserving Gutenberg block comments.
	 *
	 * wp_kses_post() strips HTML comments, which destroys <!-- wp:image {...} -->
	 * block attributes. This method removes only genuinely dangerous content
	 * (script/iframe/JS events) while keeping block structure intact.
	 *
	 * @param string $content Raw post content from import JSON.
	 * @return string Sanitized content with Gutenberg blocks intact.
	 */
	private function sanitize_gutenberg_content( $content ) {
		$content = wp_unslash( $content );
		// Strip script and iframe tags
		$content = preg_replace( '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content );
		$content = preg_replace( '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $content );
		// Basic XSS protection — remove dangerous URI schemes and inline event attributes
		$content = str_replace( array( 'javascript:', 'vbscript:' ), '', $content );
		$content = preg_replace( '/\s+on\w+\s*=/i', ' data-removed=', $content );
		return $content;
	}

	/**
	 * Import taxonomies for a post.
	 *
	 * @param int   $post_id
	 * @param array $taxonomies Flat array of {taxonomy, name, slug, description, parent_slug}
	 */
	private function import_taxonomies( $post_id, $taxonomies ) {
		$grouped = array();
		foreach ( $taxonomies as $tax_item ) {
			$tax = sanitize_key( $tax_item['taxonomy'] ?? '' );
			if ( empty( $tax ) || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$grouped[ $tax ][] = $tax_item;
		}

		foreach ( $grouped as $taxonomy => $terms ) {
			$term_ids = array();
			foreach ( $terms as $term_data ) {
				$parent_id = 0;
				if ( ! empty( $term_data['parent_slug'] ) ) {
					$parent = get_term_by( 'slug', sanitize_title( $term_data['parent_slug'] ), $taxonomy );
					if ( $parent ) {
						$parent_id = $parent->term_id;
					}
				}

				$existing = get_term_by( 'slug', sanitize_title( $term_data['slug'] ?? '' ), $taxonomy );
				if ( $existing ) {
					$term_ids[] = $existing->term_id;
				} else {
					$new_term = wp_insert_term(
						sanitize_text_field( $term_data['name'] ?? '' ),
						$taxonomy,
						array(
							'slug'        => sanitize_title( $term_data['slug'] ?? '' ),
							'description' => sanitize_textarea_field( $term_data['description'] ?? '' ),
							'parent'      => $parent_id,
						)
					);
					if ( ! is_wp_error( $new_term ) ) {
						$term_ids[] = $new_term['term_id'];
					}
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, $taxonomy );
			}
		}
	}

	/**
	 * Import raw post meta. Skips certain WP internal keys.
	 *
	 * @param int   $post_id
	 * @param array $meta
	 */
	private function import_post_meta( $post_id, $meta, $has_acf_fields = false ) {
		$skip_keys = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_thumbnail_id' );

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}

			// FIXED: Bug 1 — Skip ACF reference keys if ACF fields are being imported
			if ( $has_acf_fields && substr( $key, 0, 1 ) === '_' ) {
				$val = is_array( $values ) ? reset( $values ) : $values;
				if ( is_string( $val ) && substr( trim( $val ), 0, 6 ) === 'field_' ) {
					continue;
				}
			}

			// $key may have underscore prefix for ACF reference keys — keep as-is
			delete_post_meta( $post_id, $key );
			foreach ( (array) $values as $value ) {
				add_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Import featured image for a post.
	 *
	 * @param int   $post_id
	 * @param array $image_data   { url, filename, title, alt, mime }
	 * @param bool  $download_missing
	 */
	private function import_featured_image( $post_id, $image_data, $download_missing = false ) {
		if ( ! is_array( $image_data ) || empty( $image_data['filename'] ) ) {
			return;
		}

		$filename = sanitize_file_name( $image_data['filename'] );
		global $wpdb;

		// Check media library by filename
		$attachment_id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
			'%' . $wpdb->esc_like( $filename )
		) );

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, (int) $attachment_id );
			return;
		}

		if ( $download_missing && ! empty( $image_data['url'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$tmp = download_url( esc_url_raw( $image_data['url'] ) );
			if ( is_wp_error( $tmp ) ) {
				return;
			}

			$file_array = array(
				'name'     => $filename,
				'tmp_name' => $tmp,
			);

			$att_id = media_handle_sideload( $file_array, $post_id, sanitize_text_field( $image_data['title'] ?? '' ) );
			if ( ! is_wp_error( $att_id ) ) {
				set_post_thumbnail( $post_id, $att_id );
				if ( ! empty( $image_data['alt'] ) ) {
					update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( $image_data['alt'] ) );
				}
			} else {
				@unlink( $tmp ); // phpcs:ignore
			}
		}
	}

	/**
	 * Find an existing attachment by filename (partial path match).
	 * Mirrors find_existing_attachment_by_filename() in class-post-handler.php.
	 *
	 * @param string $filename
	 * @return int|null Attachment ID or null.
	 */
	private function find_attachment_by_filename( $filename ) {
		global $wpdb;

		// Primary: match by _wp_attached_file (handles year/month sub-directories)
		$att_id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attached_file'
			 AND meta_value LIKE %s
			 LIMIT 1",
			'%' . $wpdb->esc_like( $filename )
		) );

		if ( $att_id ) {
			return absint( $att_id );
		}

		// Fallback: match by post_title (filename without extension)
		$title  = pathinfo( $filename, PATHINFO_FILENAME );
		$att_id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_title = %s
			 LIMIT 1",
			$title
		) );

		return $att_id ? absint( $att_id ) : null;
	}

	/**
	 * Import content images for a CPT post and return updated post_content
	 * with old source-site URLs replaced by new destination-site URLs.
	 *
	 * Mirrors import_content_images_secure() in class-post-handler.php.
	 *
	 * @param int    $post_id
	 * @param array  $images_data  Array of { id, url, filename, title, alt }
	 * @param string $content      Current post_content (already inserted)
	 * @param bool   $download_missing
	 * @return string Updated post_content
	 */
	private function import_content_images( $post_id, $images_data, $content, $download_missing = false ) {
		$updated_content = $content;
		$url_mapping     = array(); // exact-match: old_url => new_url
		$url_regex_map   = array(); // regex-match: pattern => new_url (for resized -WxH variants)

		foreach ( $images_data as $image_data ) {
			if ( ! is_array( $image_data ) || empty( $image_data['filename'] ) ) {
				continue;
			}

			$filename    = sanitize_file_name( $image_data['filename'] );
			$old_url     = isset( $image_data['url'] )   ? esc_url_raw( $image_data['url'] )          : '';
			$image_title = isset( $image_data['title'] ) ? sanitize_text_field( $image_data['title'] ) : '';
			$image_alt   = isset( $image_data['alt'] )   ? sanitize_text_field( $image_data['alt'] )   : '';
			$old_id      = isset( $image_data['id'] )    ? absint( $image_data['id'] )                 : 0;

			$att_id = $this->find_attachment_by_filename( $filename );

			if ( ! $att_id && $download_missing && ! empty( $old_url ) ) {
				// Download the image and register it as an attachment
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$tmp = download_url( esc_url_raw( $old_url ) );
				if ( ! is_wp_error( $tmp ) ) {
					$file_array = array(
						'name'     => $filename,
						'tmp_name' => $tmp,
					);
					$new_att_id = media_handle_sideload( $file_array, $post_id, $image_title );
					if ( ! is_wp_error( $new_att_id ) ) {
						$att_id = $new_att_id;
						if ( ! empty( $image_alt ) ) {
							update_post_meta( $att_id, '_wp_attachment_image_alt', $image_alt );
						}
					} else {
						@unlink( $tmp ); // phpcs:ignore
					}
				}
			}

			if ( ! $att_id ) {
				continue; // Could not locate or download — leave content as-is for this image
			}

			$new_url = wp_get_attachment_url( $att_id );

			// Build URL replacement map.
			//
			// WHY REGEX instead of plain str_replace:
			// content_images[].url is the FULL-SIZE URL, e.g.:
			//   .../mohamad-khosravi-vS0Kya7E5V4-unsplash-1-scaled.jpg
			// But Gutenberg stores RESIZED variants in <img src="">, e.g.:
			//   .../mohamad-khosravi-vS0Kya7E5V4-unsplash-1-scaled-653x1024.jpg
			//
			// Plain str_replace(old_url, new_url, content) never matches those resized
			// src values, so images stay pointing at the old domain and appear broken.
			//
			// Fix: strip the extension (and any trailing -WxH) from old_url to get a base,
			// then use preg_replace with a pattern that matches the base + optional -WxH + ext.
			if ( $new_url && ! empty( $old_url ) ) {
				// Derive base path: remove extension, then remove any trailing -WxH dimension
				$old_base_no_ext = preg_replace( '/\.[a-zA-Z0-9]+$/', '', $old_url );
				$old_base_no_ext = preg_replace( '/-\d+x\d+$/', '', $old_base_no_ext );

				// Pattern matches: <base>  (optionally -WxH)  .<any image ext>
				// Covers full-size, -scaled, -scaled-653x1024, -1024x683, etc.
				$old_pattern = preg_quote( $old_base_no_ext, '/' ) . '(?:-\d+x\d+)?\.[a-zA-Z0-9]+';

				// Exact-match fallback for JSON block attributes / href links
				$url_mapping[ $old_url ]        = $new_url;
				// Regex replacement for sized <img src> variants
				$url_regex_map[ $old_pattern ]  = $new_url;
			}

			// Remap block-comment "id":OLD → "id":NEW
			if ( $old_id > 0 && $att_id !== $old_id ) {
				$updated_content = preg_replace(
					'/(\"id\":)' . $old_id . '([,}])/',
					'${1}' . $att_id . '${2}',
					$updated_content
				);
				// Remap CSS class wp-image-OLD → wp-image-NEW
				$updated_content = str_replace(
					'wp-image-' . $old_id,
					'wp-image-' . $att_id,
					$updated_content
				);
			}
		}

		// 1. Regex replacements — covers resized -WxH src variants (must run first)
		foreach ( $url_regex_map as $pattern => $new ) {
			$updated_content = preg_replace( '/' . $pattern . '/', $new, $updated_content );
		}

		// 2. Exact str_replace — catches any remaining occurrences in block JSON / links
		foreach ( $url_mapping as $old => $new ) {
			$updated_content = str_replace( $old, $new, $updated_content );
		}

		return $updated_content;
	}

	// =========================================================================
	// AJAX: BATCH EXPORT — START
	// =========================================================================

	/**
	 * Start a batch export session for a CPT.
	 */
	public function ajax_batch_export_cpt_start() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		$post_type  = isset( $_POST['post_type'] )         ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$export_acf = isset( $_POST['export_acf_fields'] ) && '1' === sanitize_key( wp_unslash( $_POST['export_acf_fields'] ) );
		$acf_field_keys_raw = isset( $_POST['acf_field_keys'] ) ? sanitize_text_field( wp_unslash( $_POST['acf_field_keys'] ) ) : '';
		$acf_field_keys     = $acf_field_keys_raw !== '' ? array_filter( array_map( 'sanitize_text_field', explode( ',', $acf_field_keys_raw ) ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $post_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Post type is required', 'post-export-import-with-media' ) ) );
		}

		$count = wp_count_posts( $post_type );
		$total = 0;
		foreach ( (array) $count as $status => $n ) {
			if ( in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
				$total += (int) $n;
			}
		}

		if ( 0 === $total ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No posts found for this post type', 'post-export-import-with-media' ) ) );
		}

		$batch_size    = max( 10, (int) PEIWM_Batch_Settings::get_instance()->get_setting( 'export_json_size' ) );
		$total_batches = (int) ceil( $total / $batch_size );
		$batch_id      = 'peim_cpt_export_' . wp_generate_password( 12, false );

		set_transient( $batch_id, array(
			'post_type'      => $post_type,
			'export_acf'     => $export_acf,
			'acf_field_keys' => $acf_field_keys,
			'batch_size'     => $batch_size,
			'total'          => $total,
			'total_batches'  => $total_batches,
			'collected'      => array(),
		), HOUR_IN_SECONDS );

		wp_send_json_success( array(
			'batch_id'      => $batch_id,
			'total'         => $total,
			'total_batches' => $total_batches,
			'batch_size'    => $batch_size,
		) );
	}

	// =========================================================================
	// AJAX: BATCH EXPORT — PROCESS
	// =========================================================================

	/**
	 * Process one chunk of a CPT batch export.
	 */
	public function ajax_batch_export_cpt_process() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		$batch_id     = isset( $_POST['batch_id'] )     ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$batch_number = isset( $_POST['batch_number'] ) ? absint( wp_unslash( $_POST['batch_number'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$session = get_transient( $batch_id );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Batch session expired or not found', 'post-export-import-with-media' ) ) );
		}

		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore

		$posts = get_posts( array(
			'post_type'      => $session['post_type'],
			'posts_per_page' => $session['batch_size'],
			'paged'          => $batch_number,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'no_found_rows'  => true,
		) );

		$chunk_data = array();
		foreach ( $posts as $post ) {
			$chunk_data[] = $this->build_post_export_data( $post, $session['export_acf'], $session['acf_field_keys'] ?? array() );
		}

		$session['collected'] = array_merge( $session['collected'], $chunk_data );

		$processed = $batch_number * $session['batch_size'];
		$has_more  = $processed < $session['total'];

		if ( $has_more ) {
			set_transient( $batch_id, $session, HOUR_IN_SECONDS );
			wp_send_json_success( array(
				'has_more'     => true,
				'batch_number' => $batch_number,
				'processed'    => min( $processed, $session['total'] ),
				'total'        => $session['total'],
			) );
		} else {
			// All chunks done — write file via WP_Filesystem
			$upload_dir = wp_upload_dir();
			$export_dir = trailingslashit( $upload_dir['basedir'] ) . 'peiwm-exports/';
			wp_mkdir_p( $export_dir );

			$filename = 'cpt-' . $session['post_type'] . '-export-' . gmdate( 'Y-m-d-His' ) . '.json';
			$filepath = $export_dir . $filename;
			$json     = wp_json_encode( $session['collected'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			$wp_filesystem->put_contents( $filepath, $json, FS_CHMOD_FILE );

			delete_transient( $batch_id );
			wp_cache_flush();

			$download_url = add_query_arg( array(
				'action'   => 'peiwm_download_cpt_export',
				'file'     => rawurlencode( $filename ),
				'_wpnonce' => wp_create_nonce( 'peiwm_download_nonce' ),
			), admin_url( 'admin-post.php' ) );

			wp_send_json_success( array(
				'has_more'     => false,
				'total'        => $session['total'],
				'processed'    => $session['total'], // Add processed key for final batch log
				'download_url' => $download_url,
				'filename'     => $filename,
			) );
		}
	}

	// =========================================================================
	// AJAX: BATCH IMPORT — START
	// =========================================================================

	/**
	 * Start a batch import session for CPT posts.
	 */
	public function ajax_batch_import_cpt_start() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		// Use wp_unslash() only — sanitize_text_field() strips HTML and corrupts post_content/content_images JSON
		$raw = isset( $_POST['json_data'] ) ? wp_unslash( $_POST['json_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded; individual fields sanitized inside do_import_single_post()
		if ( empty( $raw ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No import data provided', 'post-export-import-with-media' ) ) );
		}

		$posts = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $posts ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid JSON', 'post-export-import-with-media' ) ) );
		}

		$check_media    = isset( $_POST['check_media_library'] )     && '1' === sanitize_key( wp_unslash( $_POST['check_media_library'] ) );
		$download_media = isset( $_POST['download_missing_images'] ) && '1' === sanitize_key( wp_unslash( $_POST['download_missing_images'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Build a per-post force_status map from _originalIdx embedded in each post object
		// (set by JS when building the selective posts array)
		$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
		$force_status_map = array(); // index => status string
		foreach ( $posts as $i => $post ) {
			if ( isset( $post['_force_status'] ) ) {
				$fs = sanitize_key( $post['_force_status'] );
				if ( in_array( $fs, $allowed_statuses, true ) ) {
					$force_status_map[ $i ] = $fs;
				}
			}
		}

		$batch_id = 'peim_cpt_import_' . wp_generate_password( 12, false );

		set_transient( $batch_id, array(
			'posts'            => $posts,
			'check_media'      => $check_media,
			'download_media'   => $download_media,
			'force_status_map' => $force_status_map,
			'total'            => count( $posts ),
		), HOUR_IN_SECONDS );

		wp_send_json_success( array(
			'batch_id' => $batch_id,
			'total'    => count( $posts ),
		) );
	}

	// =========================================================================
	// AJAX: BATCH IMPORT — PROCESS
	// =========================================================================

	/**
	 * Process one post in a CPT batch import.
	 */
	public function ajax_batch_import_cpt_process() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via $this->verify_request()
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$index    = isset( $_POST['index'] )    ? absint( wp_unslash( $_POST['index'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$session = get_transient( $batch_id );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Batch session expired', 'post-export-import-with-media' ) ) );
		}

		$posts = $session['posts'];

		if ( ! isset( $posts[ $index ] ) ) {
			delete_transient( $batch_id );
			wp_send_json_success( array( 'has_more' => false, 'total' => $session['total'] ) );
			return;
		}

		// Apply per-post force_status if set
		$post_data        = $posts[ $index ];
		$force_status_map = $session['force_status_map'] ?? array();
		$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
		if ( isset( $force_status_map[ $index ] ) && in_array( $force_status_map[ $index ], $allowed_statuses, true ) ) {
			$post_data['post_status'] = $force_status_map[ $index ];
		}

		$result   = $this->do_import_single_post( $post_data, $session['check_media'], $session['download_media'] );
		$has_more = ( $index + 1 ) < $session['total'];

		wp_send_json_success( array(
			'has_more' => $has_more,
			'index'    => $index,
			'total'    => $session['total'],
			'result'   => $result,
		) );
	}

	// =========================================================================
	// ADMIN_POST: Download export file
	// =========================================================================

	/**
	 * Serve a previously generated CPT export JSON file for download.
	 */
	public function download_cpt_export() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'peiwm_download_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'post-export-import-with-media' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'post-export-import-with-media' ) );
		}

		// Unslash then URL-decode then sanitize — this is a JSON export filename, no structured data loss.
		// rawurldecode() is intentional to handle URL-encoded filenames from download links; sanitize_file_name() strips any remaining unsafe characters.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_unslash applied before rawurldecode; sanitize_file_name() is the final sanitizer.
		$filename   = isset( $_GET['file'] ) ? sanitize_file_name( rawurldecode( wp_unslash( $_GET['file'] ) ) ) : '';
		$upload_dir = wp_upload_dir();
		$full_path  = trailingslashit( $upload_dir['basedir'] ) . 'peiwm-exports/' . $filename;

		if ( empty( $filename ) || ! file_exists( $full_path ) ) {
			wp_die( esc_html__( 'File not found', 'post-export-import-with-media' ) );
		}

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		readfile( $full_path ); // phpcs:ignore
		exit;
	}

	// =========================================================================
	// UTILITY
	// =========================================================================

	/**
	 * Check if ACF plugin is active.
	 *
	 * @return bool
	 */
	public function is_acf_active() {
		return function_exists( 'get_fields' ) && function_exists( 'update_field' );
	}
}
