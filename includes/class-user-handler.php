<?php
/**
 * User Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.5.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Handler Class - Manages user export/import operations
 */
class PEIWM_User_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_User_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_User_Handler
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
		add_action( 'wp_ajax_peiwm_export_users',   array( $this, 'ajax_export_users' ) );
		add_action( 'wp_ajax_peiwm_import_users',   array( $this, 'ajax_import_users' ) );
		add_action( 'wp_ajax_peiwm_get_users_list', array( $this, 'ajax_get_users_list' ) );
	}

	/**
	 * AJAX: Export all users to a JSON file in the exports directory.
	 * Supports optional extended data: password hash, meta, WooCommerce, ACF, CPT authorship.
	 */
	public function ajax_export_users() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$users  = get_users( array( 'number' => -1 ) );
			$export = array();

			global $wpdb;

			foreach ( $users as $user ) {
				// ── Basic info (always included) ──────────────────────────
				$user_data = array(
					'ID'              => absint( $user->ID ),
					'user_login'      => sanitize_user( $user->user_login ),
					'user_email'      => sanitize_email( $user->user_email ),
					'user_nicename'   => sanitize_title( $user->user_nicename ),
					'user_url'        => esc_url_raw( $user->user_url ),
					'user_registered' => sanitize_text_field( $user->user_registered ),
					'user_status'     => absint( $user->user_status ),
					'display_name'    => sanitize_text_field( $user->display_name ),
					'roles'           => array_map( 'sanitize_text_field', (array) $user->roles ),
					'locale'          => sanitize_text_field( get_user_locale( $user->ID ) ),
					'first_name'      => sanitize_text_field( get_user_meta( $user->ID, 'first_name', true ) ),
					'last_name'       => sanitize_text_field( get_user_meta( $user->ID, 'last_name', true ) ),
					'description'     => sanitize_textarea_field( get_user_meta( $user->ID, 'description', true ) ),
				);

				$export[] = $user_data;
			}

			// Ensure export directory exists
			$upload_dir = wp_upload_dir();
			$export_dir = trailingslashit( $upload_dir['basedir'] ) . 'peiwm-exports/';

			if ( ! file_exists( $export_dir ) ) {
				wp_mkdir_p( $export_dir );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $export_dir . 'index.php', '<?php // Silence is golden.' );
				// Harden against direct web access on Apache hosts (CWE-200 mitigation).
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents(
					$export_dir . '.htaccess',
					"Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>"
				);
			}

			/**
			 * TASK-003 : Add random token to user export filename to prevent guessing (CWE-200).
			 */
			$filename  = 'peiwm-users-' . gmdate( 'Y-m-d-His' ) . '-' . wp_generate_password( 12, false ) . '.json';
			$file_path = $export_dir . $filename;

			$json = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			if ( false === $json ) {
				throw new Exception( 'JSON encoding failed' );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$written = file_put_contents( $file_path, $json );
			if ( false === $written ) {
				throw new Exception( 'Could not write export file' );
			}

			wp_send_json_success( array(
				'count'    => count( $export ),
				'filename' => esc_html( $filename ),
				'message'  => sprintf(
					/* translators: %d: number of users */
					esc_html__( '%d users exported successfully.', 'post-export-import-with-media' ),
					count( $export )
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Export failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Import users from a JSON payload.
	 * Supports optional ID preservation, welcome emails, and detailed summary.
	 */
	public function ajax_import_users() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			// users_json is a JSON payload — raw string sanitization (e.g. sanitize_text_field) would corrupt JSON structure.
			// Validated structurally via json_decode() and is_array() check below.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; sanitized structurally after json_decode() below.
			$users_json_raw = isset( $_POST['users_json'] ) ? wp_unslash( $_POST['users_json'] ) : '';

			if ( empty( $users_json_raw ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'No user data provided.', 'post-export-import-with-media' ),
				) );
			}

			$users = json_decode( $users_json_raw, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $users ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'Invalid JSON format.', 'post-export-import-with-media' ),
				) );
			}

			// Counters for summary
			$imported            = 0;
			$skipped             = 0;

			global $wpdb;

			foreach ( $users as $user_data ) {

				// Sanitize every field from the JSON
				$original_id  = absint( $user_data['ID'] ?? 0 );
				$user_login   = sanitize_user( $user_data['user_login'] ?? '', true );
				$user_email   = sanitize_email( $user_data['user_email'] ?? '' );
				$display_name = sanitize_text_field( $user_data['display_name'] ?? '' );
				$user_nicename = sanitize_title( $user_data['user_nicename'] ?? '' );
				$user_url     = esc_url_raw( $user_data['user_url'] ?? '' );
				$user_status  = absint( $user_data['user_status'] ?? 0 );
				$locale       = sanitize_text_field( $user_data['locale'] ?? '' );
				$first_name   = sanitize_text_field( $user_data['first_name'] ?? '' );
				$last_name    = sanitize_text_field( $user_data['last_name'] ?? '' );
				$description  = sanitize_textarea_field( $user_data['description'] ?? '' );
				$roles        = isset( $user_data['roles'] ) && is_array( $user_data['roles'] )
				                ? array_map( 'sanitize_text_field', $user_data['roles'] )
				                : array( 'subscriber' );

				// Validate required fields — skip silently if malformed
				if ( empty( $user_login ) || empty( $user_email ) || ! is_email( $user_email ) ) {
					$skipped++;
					continue;
				}

				// Whitelist role
				$valid_roles = array_keys( wp_roles()->get_names() );
				$role        = '';
				foreach ( $roles as $r ) {
					if ( in_array( $r, $valid_roles, true ) ) {
						$role = $r;
						break;
					}
				}
				if ( empty( $role ) ) {
					$role = 'subscriber';
				}

				// Skip if already exists by login or email
				if ( get_user_by( 'login', $user_login ) || get_user_by( 'email', $user_email ) ) {
					$skipped++;
					continue;
				}

				$password = wp_generate_password( 16, true, true );
				$created = wp_create_user( $user_login, $password, $user_email );
				if ( is_wp_error( $created ) ) {
					$skipped++;
					continue;
				}
				$new_user_id = absint( $created );

				// Set user meta and role — include all basic fields
				wp_update_user( array(
					'ID'           => $new_user_id,
					'display_name' => $display_name,
					'user_nicename'=> ! empty( $user_nicename ) ? $user_nicename : sanitize_title( $user_login ),
					'user_url'     => $user_url,
					'role'         => $role,
					'locale'       => $locale,
				) );
				update_user_meta( $new_user_id, 'first_name', $first_name );
				update_user_meta( $new_user_id, 'last_name', $last_name );
				update_user_meta( $new_user_id, 'description', $description );

				$imported++;
			} // end foreach

			wp_send_json_success( array(
				'imported'            => $imported,
				'skipped'             => $skipped,
				'message'             => sprintf(
					/* translators: 1: imported count 2: skipped count */
					esc_html__( '%1$d imported, %2$d skipped.', 'post-export-import-with-media' ),
					$imported,
					$skipped
				),
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Import failed. Please try again.', 'post-export-import-with-media' ) ) );
		}
	}

	/**
	 * AJAX: Get a sanitized list of all users (no passwords or sensitive data).
	 */
	public function ajax_get_users_list() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		try {
			$users  = get_users( array( 'number' => -1 ) );
			$output = array();

			foreach ( $users as $user ) {
				$output[] = array(
					'ID'           => absint( $user->ID ),
					'user_login'   => esc_html( $user->user_login ),
					'user_email'   => sanitize_email( $user->user_email ),
					'display_name' => esc_html( $user->display_name ),
					'roles'        => array_map( 'esc_html', (array) $user->roles ),
				);
			}

			wp_send_json_success( array( 'users' => $output, 'total' => count( $output ) ) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to load users list.', 'post-export-import-with-media' ) ) );
		}
	}

}
