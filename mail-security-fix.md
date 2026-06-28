# PEIWM Security Fix — AI Agent Prompt
# WP.org Automated Scan Report ID: 439aa174 | Plugin: post-export-import-with-media 1.13.1
# Use this prompt directly in Claude Code, Cursor, Windsurf, or any IDE AI agent.

---

```
You are a senior WordPress plugin security engineer working on the plugin:
**Post Export Import with Media** (slug: `post-export-import-with-media`, version 1.13.1)
by WPAzleen (https://wpazleen.com).

Your task is to fix 3 confirmed security vulnerabilities flagged by the WordPress.org
automated security scanner. Do NOT add new features. Do NOT refactor unrelated code.
Fix only what is described. Preserve all existing comments, including commented-out lines.

---

## STEP 1 — Codebase Analysis (Do this before writing any code)

1. Read the full plugin structure, focusing on:
   - `includes/class-batch-processor.php`
   - `includes/class-media-handler.php`
   - `includes/class-user-handler.php`
   - `includes/class-main.php`

2. Check for a `/pro` or `post-export-import-with-media-pro/` directory in WP_PLUGIN_DIR.
   If found, note how it extends the free classes — do NOT modify PRO files.

3. Identify:
   - The `peiwm_exports` directory creation pattern (used in batch processor and user handler)
   - The `import_media_file_secure()` method in class-media-handler.php
   - The `ajax_batch_export_posts_process()` method in class-batch-processor.php
   - The `ajax_export_users()` method in class-user-handler.php

---

## STEP 2 — Create doc.md

Before writing any code, create `doc.md` in the plugin root containing:

- **Outcome**: Three security fixes applied; plugin passes WP.org scan for report 439aa174
- **Blockers**: Note any ambiguity in the import flow that could affect Fix 2 (extension check placement)
- **AI Workflow**: Your planned sequence across the 3 files
- **Strategy**: Minimal surgical edits; no refactoring; backward-compatible
- **Key Outputs**: List the 3 files that will be modified

---

## STEP 3 — Security Fixes to Implement

### FIX 1 — Remove password hash from batch post export
**File**: `includes/class-batch-processor.php`
**Severity**: Medium (CWE-522 / CWE-200)

**Problem**: In `ajax_batch_export_posts_process()`, the code reads each post author's
`user_pass` (bcrypt hash) via direct DB query and stores it as `user_pass_hash` inside
the exported JSON file. The JSON is written to `wp-content/uploads/peiwm-exports/` with
a predictable timestamp-based filename, making it potentially retrievable if the uploads
directory is web-served.

**Fix — Part A: Remove the password hash query**

Locate the block that starts with:
```php
$author_user = get_userdata( absint( $post->post_author ) );
if ( $author_user ) {
    $author_pass_hash = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_pass FROM {$wpdb->users} WHERE ID = %d",
        $author_user->ID
    ) );
    $post_data['post_author_data'] = array(
        ...
        'user_pass_hash' => $author_pass_hash ? $author_pass_hash : null,
    );
}
```

Replace it with (keep all other fields, ONLY remove the `user_pass` query and `user_pass_hash` key):
```php
// Enrich with author identity data for smart mapping on import.
// NOTE: password hashes intentionally excluded — security hardening (CWE-522).
$author_user = get_userdata( absint( $post->post_author ) );
if ( $author_user ) {
    $post_data['post_author_data'] = array(
        'user_login'   => sanitize_user( $author_user->user_login ),
        'user_email'   => sanitize_email( $author_user->user_email ),
        'display_name' => sanitize_text_field( $author_user->display_name ),
        'role'         => ! empty( $author_user->roles )
                          ? sanitize_text_field( $author_user->roles[0] )
                          : 'subscriber',
    );
} else {
    $post_data['post_author_data'] = null;
}
```

**Fix — Part B: Harden the exports directory**

Find the export directory creation block (around where `wp_mkdir_p( $export_dir )` is called
in `ajax_batch_export_posts_process()`). After the `wp_mkdir_p()` call, add:

```php
// Harden exports directory against direct web access.
$htaccess = $export_dir . '.htaccess';
if ( ! file_exists( $htaccess ) ) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    file_put_contents(
        $htaccess,
        "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>"
    );
}
if ( ! file_exists( $export_dir . 'index.php' ) ) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    file_put_contents( $export_dir . 'index.php', '<?php // Silence is golden.' );
}
```

**Fix — Part C: Use an unguessable filename**

Locate the filename construction line:
```php
$filename = 'posts-export-batch-' . ( $batch_number + 1 ) . '_' . date( 'Y-m-d-H-i-s' ) . '.json';
```

Replace with:
```php
$filename = 'posts-export-batch-' . ( $batch_number + 1 ) . '_' . gmdate( 'Y-m-d-H-i-s' ) . '_' . wp_generate_password( 12, false ) . '.json';
```

Also apply the same directory hardening + random token pattern to
`ajax_batch_export_pages_process()` and `ajax_batch_export_media_process()` if those
methods also write JSON files to `peiwm-exports/`.

---

### FIX 2 — Re-validate destination file extension before copy in media import
**File**: `includes/class-media-handler.php`
**Severity**: High (CWE-434)

**Problem**: In `import_media_file_secure()`, the destination filename comes from
`$file_data['filename']` (attacker-controlled metadata). It is only passed through
`sanitize_file_name()` with no extension check before `copy()` at line 800. This allows
a ZIP with `payload.txt` (PHP code) + metadata `"filename": "shell.php"` to write an
arbitrary extension file to the uploads directory.

**Fix**: Add extension validation immediately after the `$safe_filename` assignment,
BEFORE the `$target_file` construction and the `copy()` call.

Find this block:
```php
$target_file = $target_dir . DIRECTORY_SEPARATOR . sanitize_file_name( $file_data['filename'] );

// Copy file to target location
if ( ! file_exists( $source_file ) ) {
```

Replace with:
```php
/**
 * TASK-002 : Re-validate destination filename extension before copy.
 * The extraction-time check gates the ZIP entry name, but $file_data['filename']
 * (from attacker-controlled metadata) determines the destination name and must be
 * independently validated to prevent extension-bypass writes (CWE-434).
 */
$safe_filename = sanitize_file_name( $file_data['filename'] );
$dest_ext      = strtolower( pathinfo( $safe_filename, PATHINFO_EXTENSION ) );

// Always block dangerous server-side executable extensions — regardless of allowlist.
$blocked_extensions = array(
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
    'pl', 'py', 'rb', 'cgi', 'asp', 'aspx', 'jsp',
    'sh', 'bash', 'exe', 'bat', 'cmd', 'htaccess', 'htpasswd',
);
if ( in_array( $dest_ext, $blocked_extensions, true ) ) {
    return new WP_Error(
        'blocked_extension',
        esc_html__( 'File type not permitted for import.', 'post-export-import-with-media' )
    );
}

// Also enforce the configured allowlist unless "allow all" is explicitly enabled.
$allow_all = (bool) get_option( 'peiwm_allow_all_file_types', false );
if ( ! $allow_all ) {
    $allowed_raw  = get_option(
        'peiwm_allowed_media_file_types',
        'jpg,jpeg,png,gif,webp,svg,json,pdf,mp4,mp3,wav,doc,docx,txt'
    );
    $allowed_exts = array_map( 'trim', explode( ',', strtolower( $allowed_raw ) ) );
    if ( ! empty( $dest_ext ) && ! in_array( $dest_ext, $allowed_exts, true ) ) {
        return new WP_Error(
            'disallowed_extension',
            esc_html__( 'File type not permitted for import.', 'post-export-import-with-media' )
        );
    }
}

$target_file = $target_dir . DIRECTORY_SEPARATOR . $safe_filename;

// Copy file to target location
if ( ! file_exists( $source_file ) ) {
```

IMPORTANT: The rest of the method (the `copy()` call, `wp_insert_attachment()`, etc.)
must remain exactly as-is after this insertion. Only insert the validation block above.

---

### FIX 3 — Unguessable filename + .htaccess for user export
**File**: `includes/class-user-handler.php`
**Severity**: Medium (CWE-200)

**Problem**: User export JSON is written to `peiwm-exports/` with a timestamp-only filename
(`peiwm-users-YYYY-MM-DD-HHMMSS.json`). Only `index.php` guard exists — no `.htaccess`
deny. An attacker who knows the export occurred can guess the second-resolution filename.

**Fix — Part A: Add random token to filename**

Find:
```php
$filename  = 'peiwm-users-' . gmdate( 'Y-m-d-His' ) . '.json';
$file_path = $export_dir . $filename;
```

Replace with:
```php
/**
 * TASK-003 : Add random token to user export filename to prevent guessing (CWE-200).
 */
$filename  = 'peiwm-users-' . gmdate( 'Y-m-d-His' ) . '-' . wp_generate_password( 12, false ) . '.json';
$file_path = $export_dir . $filename;
```

**Fix — Part B: Add .htaccess protection to exports directory**

Find the directory creation block:
```php
if ( ! file_exists( $export_dir ) ) {
    wp_mkdir_p( $export_dir );
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    file_put_contents( $export_dir . 'index.php', '<?php // Silence is golden.' );
}
```

Replace with:
```php
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
```

---

## STEP 4 — Task Breakdown

- [ ] `TASK-001A` — Remove `user_pass` DB query from `ajax_batch_export_posts_process()`
- [ ] `TASK-001B` — Remove `user_pass_hash` key from `post_author_data` array
- [ ] `TASK-001C` — Add `.htaccess` + `index.php` hardening to exports dir in batch processor
- [ ] `TASK-001D` — Add `wp_generate_password(12, false)` token to batch export filename
- [ ] `TASK-001E` — Apply same hardening to `ajax_batch_export_pages_process()` and `ajax_batch_export_media_process()` if applicable
- [ ] `TASK-002`  — Insert extension validation block in `import_media_file_secure()` before `copy()`
- [ ] `TASK-003A` — Add random token to user export filename in `ajax_export_users()`
- [ ] `TASK-003B` — Add `.htaccess` protection to exports dir in user handler

Mark each ✅ when done.

> [DEV HANDLES] After all fixes: bump version to 1.13.2 in `post-export-import-with-media.php`
> and `readme.txt`. Run PHPCS manually. Do not run builds or tests yourself.

---

## STEP 5 — Code Standards

Every function or code block you add must:

1. Have a doc comment starting with the task tracker ID:
```php
/**
 * TASK-001A : Brief description
 * ...
 */
```
2. Follow the `peiwm_` naming prefix convention
3. Use WordPress coding standards (tabs, Yoda conditions, `wp_` functions over native PHP where available)
4. Include `// phpcs:ignore` comments where file_put_contents is used (already present in the codebase — match the pattern)
5. NOT introduce any new classes, files, or dependencies

---

## STEP 6 — Verification Checklist for Each Fix

After each fix, verify:

**FIX 1**: Search for `user_pass` in `class-batch-processor.php` — it must NOT appear anywhere
in the export data flow. Confirm `user_pass_hash` key is gone from `post_author_data`.

**FIX 2**: In `import_media_file_secure()`, confirm the extension check block exists BEFORE
the `copy()` call. Confirm the `$blocked_extensions` array includes at minimum: `php`, `phtml`,
`phar`, `asp`, `sh`. Confirm `$safe_filename` is used as the variable feeding `$target_file`.

**FIX 3**: In `ajax_export_users()`, confirm `wp_generate_password( 12, false )` is in the
filename. Confirm `.htaccess` file write exists in the directory init block.

---

## STEP 7 — Create changes.md

After all tasks are complete, produce `changes.md`:

```
## Changes — PEIWM Security Fix (WP.org Scan Report 439aa174)

### Modified Files

- `includes/class-batch-processor.php`
  - `TASK-001A/B : ajax_batch_export_posts_process()` — Removed user_pass DB query and user_pass_hash from export data
  - `TASK-001C   : ajax_batch_export_posts_process()` — Added .htaccess + index.php hardening to peiwm-exports/
  - `TASK-001D   : ajax_batch_export_posts_process()` — Added random token to export filename
  - `TASK-001E   : ajax_batch_export_pages_process() / ajax_batch_export_media_process()` — Same hardening if applicable

- `includes/class-media-handler.php`
  - `TASK-002 : import_media_file_secure()` — Added destination extension re-validation before copy()

- `includes/class-user-handler.php`
  - `TASK-003A : ajax_export_users()` — Added random token to user export filename
  - `TASK-003B : ajax_export_users()` — Added .htaccess to exports directory init block
```

---

## Completion Checklist

Before finishing, verify:
- [ ] `doc.md` created with all required sections
- [ ] All 8 tasks listed and marked ✅
- [ ] Every code addition has a `TASK-XXX :` doc comment
- [ ] `changes.md` produced with full file/function listing
- [ ] `user_pass` / `user_pass_hash` fully removed from batch export
- [ ] Extension validation block is BEFORE `copy()` in media handler
- [ ] Random token present in both batch export and user export filenames
- [ ] `.htaccess` hardening present in both batch processor and user handler
- [ ] No build commands, test runners, or terminal commands executed
- [ ] Version NOT bumped (that is [DEV HANDLES])
```
