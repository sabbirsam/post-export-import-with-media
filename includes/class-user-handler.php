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
			// Read export option flags (all PRO-gated except basic)
			$is_pro              = PEIWM_Main::get_instance()->is_pro_active();
			$export_password     = $is_pro && isset( $_POST['export_password'] )    && '1' === $_POST['export_password'];
			$export_meta         = $is_pro && isset( $_POST['export_meta'] )        && '1' === $_POST['export_meta'];
			$export_woocommerce  = $is_pro && isset( $_POST['export_woocommerce'] ) && '1' === $_POST['export_woocommerce'];
			$export_acf          = $is_pro && isset( $_POST['export_acf'] )         && '1' === $_POST['export_acf'];
			$export_cpt          = $is_pro && isset( $_POST['export_cpt'] )         && '1' === $_POST['export_cpt'];

			// Meta keys that are always excluded regardless of settings
			$blocked_meta_keys = array(
				'session_tokens', 'user_pass', '_application_passwords',
				'auth_cookie', 'secure_auth_cookie', 'logged_in_cookie',
			);

			// WooCommerce meta keys
			$woo_meta_keys = array(
				'billing_first_name', 'billing_last_name', 'billing_company',
				'billing_address_1', 'billing_address_2', 'billing_city',
				'billing_state', 'billing_postcode', 'billing_country',
				'billing_phone', 'billing_email',
				'shipping_first_name', 'shipping_last_name', 'shipping_company',
				'shipping_address_1', 'shipping_address_2', 'shipping_city',
				'shipping_state', 'shipping_postcode', 'shipping_country',
				'wc_last_active', 'woocommerce_checkout_profile_id',
			);

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

				// ── Password hash (PRO, opt-in) ───────────────────────────
				if ( $export_password ) {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery
					$hashed = $wpdb->get_var( $wpdb->prepare(
						"SELECT user_pass FROM {$wpdb->users} WHERE ID = %d",
						$user->ID
					) );
					// phpcs:enable WordPress.DB.DirectDatabaseQuery
					$user_data['user_pass_hash'] = $hashed ? $hashed : null;
				}

				// ── User meta & capabilities (PRO, opt-in) ────────────────
				if ( $export_meta ) {
					$all_meta = get_user_meta( $user->ID );
					$safe_meta = array();
					foreach ( $all_meta as $key => $values ) {
						if ( in_array( $key, $blocked_meta_keys, true ) ) {
							continue;
						}
						if ( in_array( $key, $woo_meta_keys, true ) ) {
							continue; // WooCommerce handled separately
						}
						// Skip ACF field keys if ACF export is also enabled (avoid duplication)
						if ( $export_acf && strpos( $key, 'field_' ) === 0 ) {
							continue;
						}
						$safe_meta[ sanitize_key( $key ) ] = array_map( 'maybe_unserialize', $values );
					}
					$user_data['meta'] = $safe_meta;
				}

				// ── WooCommerce data (PRO, opt-in, WC must be active) ─────
				if ( $export_woocommerce && class_exists( 'WooCommerce' ) ) {
					$woo_data = array();
					foreach ( $woo_meta_keys as $woo_key ) {
						$val = get_user_meta( $user->ID, $woo_key, true );
						if ( '' !== $val ) {
							$woo_data[ sanitize_key( $woo_key ) ] = sanitize_text_field( $val );
						}
					}
					$user_data['woocommerce'] = $woo_data;
				}

				// ── ACF user fields (PRO, opt-in, ACF must be active) ─────
				if ( $export_acf && function_exists( 'get_fields' ) ) {
					$acf_fields = get_fields( 'user_' . $user->ID );
					if ( ! empty( $acf_fields ) && is_array( $acf_fields ) ) {
						$user_data['acf'] = $acf_fields;
					}
				}

				// ── CPT authorship (PRO, opt-in) ──────────────────────────
				if ( $export_cpt ) {
					$post_types = get_post_types( array( '_builtin' => false ), 'names' );
					$authored   = array();
					foreach ( $post_types as $pt ) {
						// phpcs:disable WordPress.DB.DirectDatabaseQuery
						$ids = $wpdb->get_col( $wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status != 'auto-draft'",
							$user->ID,
							$pt
						) );
						// phpcs:enable WordPress.DB.DirectDatabaseQuery
						if ( ! empty( $ids ) ) {
							$authored[ $pt ] = array_map( 'absint', $ids );
						}
					}
					if ( ! empty( $authored ) ) {
						$user_data['cpt_authorship'] = $authored;
					}
				}

				$export[] = $user_data;
			}

			// Ensure export directory exists
			$upload_dir = wp_upload_dir();
			$export_dir = trailingslashit( $upload_dir['basedir'] ) . 'peiwm-exports/';

			if ( ! file_exists( $export_dir ) ) {
				wp_mkdir_p( $export_dir );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $export_dir . 'index.php', '<?php // Silence is golden.' );
			}

			$filename  = 'peiwm-users-' . gmdate( 'Y-m-d-His' ) . '.json';
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
			// Read and sanitize options
			$default_password = isset( $_POST['default_password'] )
			                    ? sanitize_text_field( wp_unslash( $_POST['default_password'] ) )
			                    : '';
			$send_email       = isset( $_POST['send_email'] ) && '1' === $_POST['send_email'];
			$force_same_id    = isset( $_POST['force_same_id'] ) && '1' === $_POST['force_same_id'];

			// Extended import options (PRO only — server-side enforced)
			$is_pro              = PEIWM_Main::get_instance()->is_pro_active();
			$import_password     = $is_pro && isset( $_POST['import_password'] )    && '1' === $_POST['import_password'];
			$import_meta         = $is_pro && isset( $_POST['import_meta'] )        && '1' === $_POST['import_meta'];
			$import_woocommerce  = $is_pro && isset( $_POST['import_woocommerce'] ) && '1' === $_POST['import_woocommerce'];
			$import_acf          = $is_pro && isset( $_POST['import_acf'] )         && '1' === $_POST['import_acf'];

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
			$id_preserved        = 0;
			$id_mismatches       = array();
			$emails_sent         = 0;
			$emails_failed       = 0;
			$mail_not_configured = false;

			// Detect if wp_mail is functional before the loop
			if ( $send_email ) {
				$mail_not_configured = ! $this->is_mail_configured();
			}

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

				// Determine password:
				// Priority: 1) default_password if explicitly set by admin
				//           2) exported hash if import_password is enabled and hash exists
				//           3) auto-generate
				$has_exported_hash = $import_password
				                     && ! empty( $user_data['user_pass_hash'] )
				                     && preg_match( '/^\$(?:P\$|wp\$|2y\$)/', $user_data['user_pass_hash'] );

				if ( ! empty( $default_password ) ) {
					// Admin explicitly set a default — always wins
					$password         = $default_password;
					$use_exported_hash = false;
				} elseif ( $has_exported_hash ) {
					// Use exported hash — no plain-text password needed for creation
					$password         = wp_generate_password( 24, true, true ); // temp, will be overwritten
					$use_exported_hash = true;
				} else {
					$password         = wp_generate_password( 16, true, true );
					$use_exported_hash = false;
				}

				$new_user_id   = 0;
				$id_was_forced = false;

				// Attempt to preserve original ID via direct DB insert
				if ( $force_same_id && $original_id > 0 ) {
					$existing = get_userdata( $original_id );

					if ( ! $existing ) {
						$hashed = $use_exported_hash
						          ? sanitize_text_field( $user_data['user_pass_hash'] )
						          : wp_hash_password( $password );

						// phpcs:disable WordPress.DB.DirectDatabaseQuery
						$inserted = $wpdb->insert(
							$wpdb->users,
							array(
								'ID'                  => $original_id,
								'user_login'          => $user_login,
								'user_pass'           => $hashed,
								'user_email'          => $user_email,
								'display_name'        => $display_name,
								'user_registered'     => sanitize_text_field( $user_data['user_registered'] ?? current_time( 'mysql' ) ),
								'user_status'         => $user_status,
								'user_nicename'       => ! empty( $user_nicename ) ? $user_nicename : sanitize_title( $user_login ),
								'user_url'            => $user_url,
								'user_activation_key' => '',
							),
							array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
						);
						// phpcs:enable WordPress.DB.DirectDatabaseQuery

						if ( $inserted ) {
							$new_user_id   = $original_id;
							$id_was_forced = true;
							clean_user_cache( $new_user_id );
						}
					}
				}

				// If forced ID failed or was not requested, use wp_create_user()
				if ( 0 === $new_user_id ) {
					$created = wp_create_user( $user_login, $password, $user_email );
					if ( is_wp_error( $created ) ) {
						$skipped++;
						continue;
					}
					$new_user_id = absint( $created );
				}

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

				// ── Restore password hash (PRO, opt-in) ───────────────────
				// Only applies when no default_password was set by admin.
				// If admin set a default password, that always wins.
				if ( $use_exported_hash ) {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery
					$wpdb->update(
						$wpdb->users,
						array( 'user_pass' => sanitize_text_field( $user_data['user_pass_hash'] ) ),
						array( 'ID' => $new_user_id ),
						array( '%s' ),
						array( '%d' )
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery
					clean_user_cache( $new_user_id );
				}

				// ── Restore user meta (PRO, opt-in) ───────────────────────
				if ( $import_meta && ! empty( $user_data['meta'] ) && is_array( $user_data['meta'] ) ) {
					$blocked = array( 'session_tokens', 'user_pass', '_application_passwords' );
					foreach ( $user_data['meta'] as $meta_key => $meta_values ) {
						$safe_key = sanitize_key( $meta_key );
						if ( empty( $safe_key ) || in_array( $safe_key, $blocked, true ) ) {
							continue;
						}
						delete_user_meta( $new_user_id, $safe_key );
						foreach ( (array) $meta_values as $val ) {
							add_user_meta( $new_user_id, $safe_key, maybe_unserialize( $val ) );
						}
					}
				}

				// ── Restore WooCommerce data (PRO, opt-in) ────────────────
				if ( $import_woocommerce && ! empty( $user_data['woocommerce'] ) && is_array( $user_data['woocommerce'] ) ) {
					foreach ( $user_data['woocommerce'] as $woo_key => $woo_val ) {
						update_user_meta( $new_user_id, sanitize_key( $woo_key ), sanitize_text_field( $woo_val ) );
					}
				}

				// ── Restore ACF user fields (PRO, opt-in) ─────────────────
				if ( $import_acf && ! empty( $user_data['acf'] ) && is_array( $user_data['acf'] ) && function_exists( 'update_field' ) ) {
					foreach ( $user_data['acf'] as $acf_key => $acf_val ) {
						update_field( sanitize_key( $acf_key ), $acf_val, 'user_' . $new_user_id );
					}
				}

				$imported++;

				// Track ID outcome
				if ( $id_was_forced ) {
					$id_preserved++;
				} elseif ( $original_id > 0 && $new_user_id !== $original_id ) {
					$id_mismatches[] = array(
						'login'       => esc_html( $user_login ),
						'original_id' => $original_id,
						'new_id'      => $new_user_id,
					);
				}

				// Send welcome email
				if ( $send_email && ! $mail_not_configured ) {
					$site_name = sanitize_text_field( get_bloginfo( 'name' ) );
					$subject   = sprintf(
						/* translators: %s: site name */
						esc_html__( 'Welcome to %s - Your Account Details', 'post-export-import-with-media' ),
						$site_name
					);

					$heading = sprintf(
						/* translators: %s: display name */
						esc_html__( 'Welcome, %s!', 'post-export-import-with-media' ),
						$display_name
					);

					$content = '<h2>' . esc_html__( 'Your account has been created successfully!', 'post-export-import-with-media' ) . '</h2>';
					$content .= '<p>' . esc_html__( 'You can now log in to your account using the credentials below:', 'post-export-import-with-media' ) . '</p>';
					
					$content .= '<div class="info-box">';
					$content .= '<p><strong>' . esc_html__( 'Username:', 'post-export-import-with-media' ) . '</strong> ' . esc_html( $user_login ) . '</p>';
					$content .= '<p><strong>' . esc_html__( 'Password:', 'post-export-import-with-media' ) . '</strong> ' . esc_html( $password ) . '</p>';
					$content .= '<p style="margin-top: 12px; color: #d97706; font-size: 14px;">⚠️ ' . esc_html__( 'Please save these credentials in a secure location.', 'post-export-import-with-media' ) . '</p>';
					$content .= '</div>';

					$content .= '<p>' . esc_html__( 'We recommend changing your password after your first login for security purposes.', 'post-export-import-with-media' ) . '</p>';

					$args = array(
						'button_text' => __( 'Log In Now', 'post-export-import-with-media' ),
						'button_url'  => wp_login_url(),
						'footer_text' => __( 'If you did not request this account, please contact the site administrator.', 'post-export-import-with-media' ),
					);

					$sent = PEIWM_Email_Template::send( $user_email, $subject, $heading, $content, $args );
					if ( $sent ) {
						$emails_sent++;
					} else {
						$emails_failed++;
					}
				}
			} // end foreach

			wp_send_json_success( array(
				'imported'            => $imported,
				'skipped'             => $skipped,
				'id_preserved'        => $id_preserved,
				'id_mismatches'       => $id_mismatches,
				'emails_sent'         => $emails_sent,
				'emails_failed'       => $emails_failed,
				'mail_not_configured' => $mail_not_configured,
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

	/**
	 * Check if WordPress email sending is likely configured.
	 * Best-effort check — wp_mail itself is the authoritative test.
	 *
	 * @return bool
	 */
	private function is_mail_configured() {
		$smtp_plugins = array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'post-smtp/postman-smtp.php',
			'fluent-smtp/fluent-smtp.php',
		);

		foreach ( $smtp_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return true;
			}
		}

		// PHP's mail() as a rough fallback indicator
		return function_exists( 'mail' );
	}
}
