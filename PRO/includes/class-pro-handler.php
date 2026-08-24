<?php
/**
 * Pro Handler
 *
 * Central class for all Pro logic. Every public method here mirrors a Free
 * function but carries a _pro suffix. The Free version keeps its original
 * function name and delegates here via the bridge pattern.
 *
 * HOW TO USE:
 *  1. Find the Free function listed in the comment above each method.
 *  2. Cut the logic block described in code-move.md from that Free function.
 *  3. Paste it as the body of the matching _pro method below.
 *  4. The Free function already has the bridge call in place — nothing else
 *     needs changing in the Free plugin.
 *
 * @package Post_Export_Import_With_Media_Pro
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PEIWM_Export_Import_Pro_Handler
 */
class PEIWM_Export_Import_Pro_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var PEIWM_Export_Import_Pro_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return PEIWM_Export_Import_Pro_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	// =========================================================================
	// FEATURE 2 — Smart Author Mapping
	// Free file  : includes/class-post-handler.php
	// Free method: private function resolve_post_author( $original_id, $author_data, $fallback )
	//
	// WHAT TO MOVE HERE (cut from Free resolve_post_author, paste below):
	//   — The entire body of resolve_post_author() from class-post-handler.php line ~1671.
	//   — The entire body of create_imported_user() from class-post-handler.php line ~1724.
	//     (call it as $this->create_imported_user_pro() from inside resolve_post_author_pro)
	//
	// WHAT STAYS IN FREE resolve_post_author():
	//   — Only the bridge call (see code-move.md Feature 2 bridge section).
	//
	// RETURNS: int — resolved WordPress user ID on the destination site.
	// =========================================================================

	/**
	 * Resolve the author ID for an imported post using smart mapping.
	 *
	 * Cut from: PEIWM_Post_Handler::resolve_post_author()
	 * Paste the full body of that method here.
	 *
	 * @param int        $original_id  Author ID from the source export.
	 * @param array|null $author_data  { user_login, user_email, display_name, role, user_pass_hash }
	 * @param string     $fallback     'current_user' | 'create_user'
	 * @return int  Resolved WP user ID.
	 */
	public function resolve_post_author_pro( $original_id, $author_data, $fallback = 'current_user' ) {
		// PASTE HERE: full body of resolve_post_author() from class-post-handler.php
		// Replace any $this->create_imported_user() calls with $this->create_imported_user_pro()
	}

	/**
	 * Create a WordPress user from imported author data.
	 *
	 * Cut from: PEIWM_Post_Handler::create_imported_user()
	 * Paste the full body of that method here.
	 *
	 * @param array $author_data  { user_login, user_email, display_name, role, user_pass_hash }
	 * @return int  New user ID on success, 0 on failure.
	 */
	public function create_imported_user_pro( $author_data ) {
		// PASTE HERE: full body of create_imported_user() from class-post-handler.php
	}

	// =========================================================================
	// FEATURE 1 — Export Posts: Advanced Options (author enrichment + ACF + WPML)
	// Free file  : includes/class-post-handler.php
	// Free method: ajax_export_posts() and ajax_export_posts_chunk() — inside foreach loop
	//
	// WHAT TO MOVE HERE (cut from inside the foreach loop, paste below):
	//   — The entire if ( $author_user ) { ... $post_data['post_author_data'] = ... } block.
	//   — The $export_acf_fields = ... if ( $export_acf_fields && class_exists(...) ) block.
	//   — In chunk exporter: if ( $chunk_export_wpml ... ) { $post_data['wpml_data'] = ... }
	//
	// WHAT STAYS IN FREE:
	//   — Base $post_data array construction.
	//   — The bridge call: array_merge( $post_data, $this->build_post_advanced_export_data_pro(...) )
	//
	// RETURNS: array — keys to merge into $post_data:
	//   { post_author_data: array|null, acf_fields: array, wpml_data: array|null }
	// =========================================================================

	/**
	 * Build advanced export data for a single post.
	 *
	 * Cut from: foreach loop in ajax_export_posts() and ajax_export_posts_chunk().
	 *
	 * @param WP_Post $post     The post object.
	 * @param array   $options  {
	 *   bool export_acf_fields,
	 *   bool export_wpml,
	 *   bool include_pass_hash,
	 * }
	 * @return array { post_author_data, acf_fields, wpml_data }
	 */
	public function build_post_advanced_export_data_pro( $post, array $options ) {
		// PASTE HERE: the author enrichment block, ACF fields block, and WPML block
		// from inside the foreach loop in ajax_export_posts() / ajax_export_posts_chunk()
	}

	// =========================================================================
	// FEATURE 3 — Export Media: Date range + Export by post filters
	// Free file  : includes/class-media-handler.php
	// Free method: ajax_export_media()
	// Also in   : includes/class-batch-processor.php → ajax_batch_export_media_start()
	//
	// WHAT TO MOVE HERE (cut from ajax_export_media, paste below):
	//   — Lines reading $date_from, $date_to, $post_ids behind the $is_pro && guard.
	//   — if ( $valid_from || $valid_to ) { $attachment_query['date_query'] = ... }
	//   — if ( ! empty( $post_ids ) ) { $parent_attachment_ids ... $content_attachment_ids ... }
	//
	// WHAT STAYS IN FREE:
	//   — Base $attachment_query array construction.
	//   — The bridge call: $this->build_media_export_ids_pro( $attachment_query, $filters )
	//   — The ZIP creation loop that uses the returned IDs.
	//
	// RETURNS: int[] — flat array of attachment IDs to include in the export.
	// =========================================================================

	/**
	 * Build the filtered attachment IDs list for media export.
	 *
	 * Cut from: ajax_export_media() in class-media-handler.php,
	 *           and ajax_batch_export_media_start() in class-batch-processor.php.
	 *
	 * @param array $base_query  Base get_posts() args already built by Free.
	 * @param array $filters     { date_from: string, date_to: string, post_ids: int[] }
	 * @return int[]  Attachment IDs.
	 */
	public function build_media_export_ids_pro( array $base_query, array $filters ) {
		// PASTE HERE: date_query building block + post_ids filter block
		// from ajax_export_media() in class-media-handler.php
	}

	// =========================================================================
	// FEATURE 4 — Pages Export: WPML data building
	// Free file  : includes/class-page-handler.php
	// Free method: build_page_export_data( $page, $export_wpml )
	//
	// WHAT TO MOVE HERE:
	//   — if ( $export_wpml && ( defined('ICL_SITEPRESS_VERSION') ... ) ) {
	//         $page_data['wpml_data'] = $this->get_wpml_post_data( $page->ID );
	//     }
	//
	// WHAT STAYS IN FREE:
	//   — Base $page_data array construction.
	//   — The bridge call at the end of build_page_export_data().
	//
	// RETURNS: array — { wpml_data: array|null }
	// =========================================================================

	/**
	 * Build advanced export data for a single page (WPML + selective).
	 *
	 * Cut from: build_page_export_data() in class-page-handler.php.
	 *
	 * @param WP_Post $page     The page object.
	 * @param array   $options  { bool export_wpml, int[] selected_ids }
	 * @return array { wpml_data: array|null }
	 */
	public function build_page_advanced_export_data_pro( $page, array $options ) {
		// PASTE HERE: the WPML block from build_page_export_data()
	}

	// =========================================================================
	// FEATURE 6 — CPT & ACF: Core flatten + import logic
	// Free file  : includes/class-cpt-acf-exporter.php
	// Free methods: flatten_acf_fields_public(), import_acf_fields_public()
	//              (which call private flatten_acf_fields() / import_acf_fields())
	//
	// WHAT TO MOVE HERE:
	//   — Full body of private flatten_acf_fields() (recursive ACF flatten).
	//   — Full body of private import_acf_fields() (recursive ACF restore).
	//   — Full body of private build_post_export_data() (CPT single post payload).
	//   — Full body of private do_import_single_post() (CPT single post import).
	//
	// WHAT STAYS IN FREE (as bridge wrappers):
	//   — flatten_acf_fields_public() → delegates to flatten_acf_fields_pro()
	//   — import_acf_fields_public()  → delegates to import_acf_fields_pro()
	//   — ajax_import_cpt_post()      → delegates to import_cpt_post_pro()
	//
	// RETURNS (flatten): array  — flattened ACF field map.
	// RETURNS (import) : void   — writes directly to DB via update_field().
	// RETURNS (build)  : array  — full CPT post export payload.
	// RETURNS (import) : array  — { post_id, status, message }
	// =========================================================================

	/**
	 * Recursively flatten ACF fields into an export-friendly structure.
	 *
	 * Cut from: PEIM_CPT_ACF_Exporter::flatten_acf_fields() (private).
	 *
	 * @param int    $post_id
	 * @param array  $fields      From get_fields().
	 * @param string $parent_key  Prefix for nested key lookups.
	 * @return array
	 */
	public function flatten_acf_fields_pro( $post_id, array $fields, $parent_key = '' ) {
		// PASTE HERE: full body of flatten_acf_fields() from class-cpt-acf-exporter.php
	}

	/**
	 * Recursively import ACF fields using update_field() API.
	 *
	 * Cut from: PEIM_CPT_ACF_Exporter::import_acf_fields() (private).
	 *
	 * @param int   $post_id
	 * @param array $acf_fields  Exported fields array.
	 */
	public function import_acf_fields_pro( $post_id, array $acf_fields ) {
		// PASTE HERE: full body of import_acf_fields() from class-cpt-acf-exporter.php
	}

	/**
	 * Build full export payload for a single CPT post.
	 *
	 * Cut from: PEIM_CPT_ACF_Exporter::build_post_export_data() (private).
	 *
	 * @param WP_Post $post           The CPT post object.
	 * @param bool    $export_acf     Whether to include ACF fields.
	 * @param array   $acf_field_keys Optional ACF field key whitelist (empty = all).
	 * @return array  Full export payload.
	 */
	public function build_cpt_post_export_data_pro( $post, $export_acf = true, array $acf_field_keys = array() ) {
		// PASTE HERE: full body of build_post_export_data() from class-cpt-acf-exporter.php
	}

	/**
	 * Import a single CPT post including meta, taxonomies, ACF, media.
	 *
	 * Cut from: PEIM_CPT_ACF_Exporter::do_import_single_post() (private).
	 *
	 * @param array $post_data      Decoded post data array from JSON.
	 * @param bool  $check_media    Whether to check media library.
	 * @param bool  $download_media Whether to download missing media.
	 * @return array { post_id: int, status: string, message: string }
	 */
	public function import_cpt_post_pro( array $post_data, $check_media = true, $download_media = false ) {
		// PASTE HERE: full body of do_import_single_post() from class-cpt-acf-exporter.php
	}

	// =========================================================================
	// FEATURE 7 — Export Users: Advanced options
	// Free file  : includes/class-user-handler.php
	// Free method: ajax_export_users() — inside foreach loop
	//
	// WHAT TO MOVE HERE (cut from foreach loop, paste below):
	//   — if ( $export_password ) { ... $user_data['user_pass_hash'] = ... }
	//   — if ( $export_meta ) { ... $user_data['meta'] = ... }
	//   — if ( $export_woocommerce && class_exists('WooCommerce') ) { ... }
	//   — if ( $export_acf && function_exists('get_fields') ) { ... }
	//   — if ( $export_cpt ) { ... $user_data['cpt_authorship'] = ... }
	//
	// WHAT STAYS IN FREE:
	//   — Base $user_data array construction (basic info always included).
	//   — The bridge call: array_merge( $user_data, $this->build_user_advanced_export_data_pro(...) )
	//
	// RETURNS: array — keys to merge into $user_data:
	//   { user_pass_hash?, meta?, woocommerce?, acf?, cpt_authorship? }
	// =========================================================================

	/**
	 * Append all Pro-gated user export data to a base user record.
	 *
	 * Cut from: foreach loop in ajax_export_users() in class-user-handler.php.
	 *
	 * @param WP_User $user     The user object.
	 * @param array   $options  {
	 *   bool   export_password,
	 *   bool   export_meta,
	 *   bool   export_woocommerce,
	 *   bool   export_acf,
	 *   bool   export_cpt,
	 *   array  blocked_meta_keys,
	 *   array  woo_meta_keys,
	 * }
	 * @return array  Extra keys to merge into the base $user_data array.
	 */
	public function build_user_advanced_export_data_pro( $user, array $options ) {
		// PASTE HERE: the 5 conditional blocks from ajax_export_users() foreach loop
		// (password, meta, woocommerce, acf, cpt_authorship)
	}

	// =========================================================================
	// FEATURE 8 — Import Users: Advanced options
	// Free file  : includes/class-user-handler.php
	// Free method: ajax_import_users() — after wp_update_user sets basic fields
	//
	// WHAT TO MOVE HERE (cut from after wp_update_user call, paste below):
	//   — if ( $use_exported_hash ) { $wpdb->update(...user_pass...) } block
	//   — if ( $import_meta && ... ) { delete/add user_meta loop }
	//   — if ( $import_woocommerce && ... ) { update_user_meta loop }
	//   — if ( $import_acf && ... ) { update_field loop }
	//
	// WHAT STAYS IN FREE:
	//   — User creation logic (wp_create_user / direct DB insert for forced ID).
	//   — Basic wp_update_user call (display_name, role, locale, first/last name).
	//   — The bridge call after wp_update_user.
	//
	// RETURNS: void — writes directly to DB.
	// =========================================================================

	/**
	 * Restore Pro-gated user data after a user has been created.
	 *
	 * Cut from: ajax_import_users() in class-user-handler.php,
	 *           the block that runs after wp_update_user().
	 *
	 * @param int   $new_user_id      Newly created WP user ID.
	 * @param array $user_data        Decoded user record from JSON.
	 * @param array $options          {
	 *   bool   import_password,
	 *   bool   import_meta,
	 *   bool   import_woocommerce,
	 *   bool   import_acf,
	 *   bool   use_exported_hash,
	 *   string default_password,
	 * }
	 */
	public function restore_user_advanced_import_data_pro( $new_user_id, array $user_data, array $options ) {
		// PASTE HERE: password hash update, meta restore, WooCommerce meta, ACF user fields blocks
	}

	// =========================================================================
	// FEATURE 9 — Email Template Settings
	// Free file  : includes/class-email-settings-handler.php
	// Free methods: sanitize_settings(), ajax_reset_email_template(), ajax_test_email()
	//
	// WHAT TO MOVE HERE:
	//   — Full body of sanitize_settings() from class-email-settings-handler.php
	//   — Body of ajax_reset_email_template() AFTER nonce+cap check (the delete_option line).
	//   — Body of ajax_test_email() AFTER nonce+cap+email validation (the send logic).
	//
	// WHAT STAYS IN FREE:
	//   — register_settings() and the setting group registration.
	//   — Nonce + capability checks at the top of each AJAX method.
	//   — Bridge calls: delegates to _pro, returns result to wp_send_json_*.
	//
	// RETURNS (sanitize): array  — sanitized settings array.
	// RETURNS (reset)   : void   — deletes option; caller sends JSON response.
	// RETURNS (test)    : bool   — true on successful email send.
	// =========================================================================

	/**
	 * Sanitize and save email template settings.
	 *
	 * Cut from: PEIWM_Email_Settings_Handler::sanitize_settings().
	 *
	 * @param array $input  Raw $_POST input array.
	 * @return array  Sanitized settings.
	 */
	public function sanitize_email_template_settings_pro( array $input ) {
		// PASTE HERE: full body of sanitize_settings() from class-email-settings-handler.php
	}

	/**
	 * Reset email template to factory defaults.
	 *
	 * Cut from: ajax_reset_email_template() in class-email-settings-handler.php
	 *           (the part after nonce + cap check).
	 */
	public function reset_email_template_pro() {
		// PASTE HERE: delete_option( 'peiwm_email_template_settings' );
	}

	/**
	 * Send a test email using the currently saved template settings.
	 *
	 * Cut from: ajax_test_email() in class-email-settings-handler.php
	 *           (the part after nonce + cap + email validation).
	 *
	 * @param string $test_email  Already-validated email address.
	 * @return bool  True on success, false on failure.
	 */
	public function send_test_email_pro( $test_email ) {
		// PASTE HERE: the subject/heading/content/args building + PEIWM_Email_Template::send() call
		// Return $sent (the bool from PEIWM_Email_Template::send())
	}

	// =========================================================================
	// FEATURE 10 — Batch Processing: Preset + Config + Recommendations
	// Free file  : includes/class-batch-settings.php
	// Free methods: sanitize_settings(), render_settings_page() (preset UI section)
	//
	// WHAT TO MOVE HERE:
	//   — Preset mode value computation (the preset-cards data for all modes).
	//   — Full body of sanitize_settings() for all fields EXCEPT enable_batch_processing.
	//   — The "Recommended Settings" box logic.
	//
	// WHAT STAYS IN FREE:
	//   — sanitize_settings() bridge (merges enable_batch_processing + delegates the rest).
	//   — render_settings_page() preset card HTML (already locked behind $is_pro gate).
	//
	// RETURNS (preset)      : array — { post_batch_size, page_batch_size, ... }
	// RETURNS (sanitize)    : array — sanitized batch config values (without enable toggle).
	// RETURNS (recommend)   : array — recommended values with labels.
	// =========================================================================

	/**
	 * Apply a named server preset and return its recommended settings.
	 *
	 * Cut from: the preset-mode computation in class-batch-settings.php.
	 *
	 * @param string $preset_mode  One of: micro|low|light|standard|balanced|performance|turbo|max
	 * @return array  Recommended settings values.
	 */
	public function apply_batch_preset_pro( $preset_mode ) {
		// PASTE HERE: the preset value arrays/switch for each mode name
	}

	/**
	 * Sanitize batch configuration fields (all PRO-locked inputs).
	 *
	 * Cut from: PEIWM_Batch_Settings::sanitize_settings() — everything except enable_batch_processing.
	 *
	 * @param array $input  Raw $_POST input array.
	 * @return array  Sanitized batch config (without enable_batch_processing key).
	 */
	public function sanitize_batch_config_pro( array $input ) {
		// PASTE HERE: all sanitize_settings() body from class-batch-settings.php
		// EXCEPT the $sanitized['enable_batch_processing'] line (stays in Free)
	}

	/**
	 * Compute content-based recommendations for batch settings.
	 *
	 * Cut from: the "Recommended Settings" box logic in class-batch-settings.php.
	 *
	 * @param int $total_posts
	 * @param int $total_pages
	 * @param int $total_media
	 * @return array  Recommended values with explanatory labels.
	 */
	public function get_batch_recommendations_pro( $total_posts, $total_pages, $total_media ) {
		// PASTE HERE: the recommendations computation logic
	}

	// =========================================================================
	// FEATURE 11 — Scheduled Exports: Full operational logic
	// Free file  : includes/class-scheduled-exports.php
	// Free methods: run_scheduled_export(), export_posts_scheduled(),
	//               export_pages_scheduled(), export_cpt_scheduled(),
	//               export_users_scheduled(), export_media(), export_settings(),
	//               rotate_backups(), send_notification_email()
	//
	// WHAT TO MOVE HERE: full bodies of ALL those private methods.
	//
	// WHAT STAYS IN FREE:
	//   — Constructor, AJAX hooks, settings registration, cron schedule management.
	//   — get_backup_directory(), get_settings(), sanitize_settings().
	//   — render_settings_page(), add_settings_page(), enqueue_scripts().
	//   — run_scheduled_export() becomes a 3-line bridge (check Pro active, delegate, return).
	//   — Each private export_*_scheduled() becomes a 3-line stub bridge.
	//
	// RETURNS (run)      : void   — runs the full export cycle.
	// RETURNS (export_*) : string|false — file path on success, false on failure.
	// RETURNS (rotate)   : void.
	// RETURNS (notify)   : void.
	// =========================================================================

	/**
	 * Execute the full scheduled export run.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::run_scheduled_export().
	 *
	 * @param array $settings  Full settings array from get_settings().
	 */
	public function run_scheduled_export_pro( array $settings ) {
		// PASTE HERE: full body of run_scheduled_export() from class-scheduled-exports.php
		// Replace $this->export_*() calls with $this->export_*_pro() calls
	}

	/**
	 * Export posts to JSON for a scheduled backup.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::export_posts_scheduled().
	 *
	 * @param string $dir       Backup directory path.
	 * @param string $filename  Base filename without extension.
	 * @return string|false
	 */
	public function export_posts_scheduled_pro( $dir, $filename ) {
		// PASTE HERE: full body of export_posts_scheduled() from class-scheduled-exports.php
	}

	/**
	 * Export pages to JSON for a scheduled backup.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::export_pages_scheduled().
	 *
	 * @param string $dir
	 * @param string $filename
	 * @return string|false
	 */
	public function export_pages_scheduled_pro( $dir, $filename ) {
		// PASTE HERE: full body of export_pages_scheduled() from class-scheduled-exports.php
	}

	/**
	 * Export all CPTs to JSON for a scheduled backup.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::export_cpt_scheduled().
	 *
	 * @param string $dir
	 * @param string $filename
	 * @return string|false
	 */
	public function export_cpt_scheduled_pro( $dir, $filename ) {
		// PASTE HERE: full body of export_cpt_scheduled() from class-scheduled-exports.php
	}

	/**
	 * Export users to JSON for a scheduled backup (no password hashes).
	 *
	 * Cut from: PEIWM_Scheduled_Exports::export_users_scheduled().
	 *
	 * @param string $dir
	 * @param string $filename
	 * @return string|false
	 */
	public function export_users_scheduled_pro( $dir, $filename ) {
		// PASTE HERE: full body of export_users_scheduled() from class-scheduled-exports.php
	}

	/**
	 * Export media to ZIP for a scheduled backup.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::export_media().
	 *
	 * @param string $dir
	 * @param string $filename
	 * @return string|false
	 */
	public function export_media_scheduled_pro( $dir, $filename ) {
		// PASTE HERE: full body of export_media() from class-scheduled-exports.php
	}

	/**
	 * Export WordPress settings to JSON for a scheduled backup.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::export_settings().
	 *
	 * @param string $dir
	 * @param string $filename
	 * @return string|false
	 */
	public function export_settings_scheduled_pro( $dir, $filename ) {
		// PASTE HERE: full body of export_settings() from class-scheduled-exports.php
	}

	/**
	 * Rotate backups — delete oldest files beyond the keep-count limit.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::rotate_backups().
	 *
	 * @param int $keep_count  Number of most-recent backups to retain.
	 */
	public function rotate_backups_pro( $keep_count ) {
		// PASTE HERE: full body of rotate_backups() from class-scheduled-exports.php
	}

	/**
	 * Send scheduled-export completion notification email.
	 *
	 * Cut from: PEIWM_Scheduled_Exports::send_notification_email().
	 *
	 * @param array $files     Paths to successfully exported files.
	 * @param array $settings  Full settings array (for notification_emails etc.).
	 */
	public function send_notification_email_pro( array $files, array $settings ) {
		// PASTE HERE: full body of send_notification_email() from class-scheduled-exports.php
	}
}
