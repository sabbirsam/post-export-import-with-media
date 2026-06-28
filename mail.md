Hello,
This is an automated security review of your latest plugin update.

Our automated security scan identified issues in the updated files that we want to bring to your attention. The items listed below may represent serious security vulnerabilities that could expose your users to potential attacks, data exposure, or unauthorized access, and we strongly recommend resolving them prior to publication in the WordPress Plugin Directory.
Please review each issue carefully and address them in your next submission.
Please do not reply to this email. This message is sent automatically and replies are not monitored. If you need to discuss the review, wait for the human reviewer's follow-up or contact the Plugin Review Team through the usual channels.

## Public Export Password Hashes at includes/class-batch-processor.php:202
Severity: medium (Score: 6.8)

Summary
Post batch exports copy WordPress password hashes into JSON files written under the public uploads tree.

Context / Rationale
Evidence confirms ajax_batch_export_posts_process (lines 130-258) reads each author's user_pass hash via direct query (lines 202-205) and stores it as post_author_data['user_pass_hash'] (line 213), then JSON-encodes the full $export_data and writes it with file_put_contents() to wp_upload_dir()['basedir'].'/peiwm-exports/' under a predictable timestamped filename (lines 224-238). The cleanup hook only targets peiwm-temp (class-main.php cleanup_temp_files), so export files persist. Including password hashes unconditionally in the free export is a genuine sensitive-data exposure (CWE-522/CWE-200). The finding is correctly bounded: creating the export requires manage_options + nonce, the download endpoint uses a nonce, and public retrieval depends on the uploads tree being web-served and the timestamped filename being discovered/guessed. Those real preconditions (admin-triggered export, public uploads dir, filename guessing within a time window, hashes are bcrypt/phpass not plaintext) keep this at medium rather than high; offline cracking of all-user hashes is the impact if conditions hold.

Vulnerable Code
					$author_pass_hash = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						"SELECT user_pass FROM {$wpdb->users} WHERE ID = %d",
						$author_user->ID

Explanation
The export creation entrypoint is an authenticated AJAX action (`wp_ajax_peiwm_batch_export_posts_process`) and the method checks the `peiwm_secure_nonce` plus `manage_options` before generating the file. That gate protects who can create the export, but it does not protect the static file after it is written under `wp_upload_dir()['basedir'] . '/peiwm-exports/'`, which normally maps to a directly web-served `wp-content/uploads` URL.
During export, the code reads each post author's `user_pass` value from the WordPress users table and stores it as `post_author_data['user_pass_hash']`. The full `$export_data` array is then JSON-encoded and written with `file_put_contents()` to a predictable filename containing only the batch number and timestamp.
The first security effect is persistence of password hashes, and potentially draft/pending post data in the same export, into a location that may be retrievable without the AJAX nonce or capability check. Exploitation requires an export to have been run and the attacker to discover or guess the timestamped filename, and this finding does not claim that unauthenticated users can trigger the export or that the hashes are immediately plaintext; the impact is offline password-hash disclosure if the uploads directory is publicly served.

Suggested Fix & Remediation
Stop writing raw user_pass hashes into post exports by default; password-hash export should at minimum be an explicit opt-in tied to a clear security warning. For any export containing sensitive data, write files outside the web root or harden the peiwm-exports directory (drop an index.php/.htaccess deny, disable directory listing), use cryptographically random unguessable filenames instead of timestamp-based names, and delete export files promptly after download or via a scheduled cleanup that also covers peiwm-exports. Verify in the actual deployment whether wp-content/uploads/peiwm-exports is directly web-served and whether any index/.htaccess protection already exists before finalizing the exact mitigation.

## Php Extension Bypass at includes/class-media-handler.php:800
Severity: high (Score: 7.2)

Summary
Media ZIP import can copy an allowed ZIP entry to a metadata-controlled PHP filename in uploads.

Context / Rationale
The flow is verifiable: ZIP entries are extension-validated only at extraction time (lines 362-369), but the destination filename on import comes from attacker-controlled metadata `$file_data['filename']` and is only passed through `sanitize_file_name()` (line 789) with no extension recheck before `copy()` (line 800). An entry with an allowed extension (e.g. payload.txt containing PHP) plus metadata filename `shell.php` yields an arbitrary-extension write into the uploads tree, a plausible webshell on servers that execute PHP in uploads. The finding is real, but it is correctly bounded to a manage_options + valid-nonce caller, where a single-site admin typically already has code-install capability; the elevated impact (RCE) further depends on uploads PHP execution. Score is held to medium accordingly rather than the 8+ band reserved for low-privilege/unauthenticated arbitrary writes.

Vulnerable Code
		if ( ! copy( $source_file, $target_file ) ) {
			return new WP_Error( 'copy_failed', esc_html__( 'Could not copy file to upload directory', 'post-export-import-with-media' ) );
		}

Explanation
A logged-in user with a valid plugin nonce and the `manage_options` capability can trigger the media import AJAX flow through `wp_ajax_peiwm_import_media_start` and then `wp_ajax_peiwm_import_media_file`. The handlers do perform nonce and capability checks before importing, so this is not an unauthenticated or subscriber-level issue; the relevant boundary is environments where `manage_options` should not imply arbitrary PHP file upload or code installation, such as multisite or hardened/custom-role sites.
The ZIP extraction step validates the extension of each archive entry, but the later import step trusts `media_metadata.json`. The attacker-controlled metadata supplies `file_path`, which selects the already-extracted source file, and `filename`, which becomes the destination basename. Because line 789 uses `sanitize_file_name( $file_data['filename'] )` without rechecking the extension and line 800 copies the selected source to that target, a ZIP can include an allowed entry such as `payload.txt` containing PHP code and metadata like `file_path: "payload.txt", filename: "shell.php"`.
The first concrete effect is an arbitrary-extension file write into the WordPress uploads tree, typically `wp-content/uploads/YYYY/MM/shell.php`. On servers that execute PHP in uploads, this can become server-side code execution. Exploitability requires a manage-options user/session, a valid nonce, and the two-step import workflow; it must not be claimed as exploitable by anonymous visitors absent a separate nonce/capability bypass, and single-site administrators may already have other code-install paths.

Suggested Fix & Remediation
In `import_media_file_secure()` re-validate the extension of `sanitize_file_name( $file_data['filename'] )` against the same `peiwm_allowed_media_file_types` allowlist (and explicitly reject php/phtml/php5/etc.) before the `copy()` at line 800, returning a WP_Error on disallowed extensions. The destination basename must be checked independently of the extracted source entry, since the import step never re-applies the extraction-time extension gate. Optionally also drop a hardening `.htaccess`/`index.php` and disallow execution in the export/import upload subdirectories. Verify the allowlist contents and the `peiwm_allow_all_file_types` option handling before finalizing.

## Public Export File at includes/class-user-handler.php:104
Severity: medium (Score: 4.6)

Summary
User export writes email and role data to a predictable JSON file under the public uploads directory.

Context / Rationale
The code does write a user export containing logins, emails, roles, locale and names to `wp_upload_dir()['basedir'].'/peiwm-exports/'` with a timestamp-only filename (lines 104-105), and only an `index.php` guard is created elsewhere in the chunk—no `.htaccess` deny or random token—so if the host serves uploads statically the file is directly downloadable outside the AJAX authorization gate. This is a genuine PII-disclosure/hardening concern. However impact is constrained: an attacker must know an export occurred, guess the second-resolution filename within a window, and the file must persist; the finding correctly notes password hashes are no longer exported in this version. These substantial preconditions plus limited (email/profile) data scope justify a low-medium score rather than a high one.

Vulnerable Code
			$filename  = 'peiwm-users-' . gmdate( 'Y-m-d-His' ) . '.json';
			$file_path = $export_dir . $filename;

Explanation
The user export entrypoint is `wp_ajax_peiwm_export_users`, and it is protected by a nonce and `manage_options` check before the export is created. The later access path is different: the code writes the export into the normal uploads filesystem path, which WordPress sites commonly serve as static public files, so a direct request for the generated JSON would not pass through the AJAX authorization gate.
The exported data includes user login, email, roles, locale, first and last name, and description, then writes it to `wp_upload_dir()['basedir']/peiwm-exports/` with a timestamp-only filename such as `peiwm-users-YYYY-MM-DD-HHMMSS.json`. The directory creation adds only `index.php`, which can suppress directory listing but does not protect a known file from direct download.
The proven impact is conditional disclosure of user email/profile data after an administrator creates an export. Exploitation requires the export file to remain present, the web server to serve uploads directly, and the attacker to know or guess the second-resolution timestamp filename or learn it elsewhere. This finding does not claim password-hash exposure in the current version, and it does not claim that anonymous users can create the export.

Suggested Fix & Remediation
Make the export filename unguessable by appending a cryptographically random token (e.g. `wp_generate_password`/`wp_generate_uuid4`) and/or store exports outside the web-served uploads path; additionally drop an `.htaccess`/`web.config` denying direct access to the `peiwm-exports/` directory and serve downloads only through a nonce+capability-checked endpoint, deleting files after retrieval. The smallest confident change is the random filename token plus a deny rule; verify how the generated file is later served/downloaded to ensure the protected endpoint still resolves it.

Please address all findings in your next submission. Remember that this is an automated message — do not reply to this email.
Thank you,
The WordPress Plugin Review Team (automated notification)

Scan date: 2026-06-26 08:47 UTC | Plugin: post-export-import-with-media 1.13.1 | Report ID: 439aa174-f8ff-4114-820b-367d59ac7362 | Scan ID: 976b4166-01ce-430b-bcc0-5969a2364a6c
Review ID: GANDALFRW post-export-import-with-media/wpazleen/26Jun26/TX 26Jun26/1.13.1


--
WordPress Plugins Team | plugins@wordpress.org
https://make.wordpress.org/plugins/
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
https://wordpress.org/plugins/plugin-check/