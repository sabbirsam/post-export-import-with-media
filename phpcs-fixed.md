# Post Export Import with Media — PCP/WPCS Fix Guide

**Mode:** Standalone AI Mode (no local PHPCS/WPCS binary found in this environment — audit performed by reading and verifying every flagged line directly against the uploaded source, per `wp-standards-checker` fallback protocol).
**Scope:** All 14 "Must Fix Now" blockers + all 17 "Should Fix Before Submission" items, verified line-by-line. "Fix Before Next Release" (i18n polish) is summarized at the end since it's repetitive and lower risk.

Every fix below was checked against the *real* surrounding code (not just the line number) so nothing here will break existing logic, hooks, or data structures, in line with the skill's "Safety-First Protocol."

### How to read the risk markers below

Not every PCP/PHPCS suggestion is equally safe to apply blindly. Each fix below is tagged:

- 🟢 **Zero risk** — pure metadata, comment, or string change; no I/O behavior is touched.
- 🟡 **Low risk** — behavior changes, but only for local server-managed paths (temp dirs, upload basedir) that work the same way regardless of host filesystem configuration.
- 🔵 **Kept as-is + documented** — the original code is **left untouched** and only a justified `phpcs:ignore` comment is added, because a "real" fix would introduce host-dependent behavior change that can't be verified without live testing on every hosting environment this plugin runs on. This is the safest possible option for these specific cases.

The first version of this report swapped 4 file-operations (3× `move_uploaded_file()`, 1× `copy()` into `WP_PLUGIN_DIR`) for `WP_Filesystem` equivalents. On reflection that was the wrong call for those specific spots — `WP_Filesystem` can run in FTP/SSH2 mode on some restrictive hosts, and in that mode it can fail to reach PHP's transient upload temp file, which would silently break the import features on a subset of real-world hosting setups where they currently work. Those 4 are now 🔵 in this revision: original code unchanged, only documented with an ignore comment.

---

## 🔴 MUST FIX NOW (Hard Blockers)

### 1–3. `move_uploaded_file()` is forbidden (3 occurrences) — 🔵 Kept as-is + documented

**Why it's blocked:** WP.org's automated checker forbids `move_uploaded_file()` because it bypasses WordPress's preferred filesystem abstraction.

**Why we are NOT swapping it for `WP_Filesystem::move()`:** Your code already has a comment directly above this call — *"Use `move_uploaded_file` for better compatibility on Windows"* — and that comment is correct and important. `move_uploaded_file()` does two things no replacement does as reliably:

1. It internally verifies the source path was genuinely created by PHP's own upload mechanism (the same check `is_uploaded_file()` performs), which is the actual security property reviewers care about here.
2. It works consistently with PHP's upload temp directory across Windows/IIS and locked-down shared hosting permission models.

`WP_Filesystem::move()` is **not a safe drop-in replacement** for this specific case. WordPress can initialize `WP_Filesystem` in `direct`, `ftpext`, or `ssh2` mode depending on host configuration. On a host running in FTP or SSH2 mode — which some restrictive shared hosts force — `WP_Filesystem::move()` tries to reach the file through that FTP/SSH connection, and PHP's transient upload temp file (`/tmp/phpXXXXXX`) is frequently not reachable or permitted that way. Swapping this out would risk **silently breaking the import feature** on a subset of real hosting environments where it currently works fine — exactly the kind of regression the skill's "Safety-First Protocol" exists to prevent.

**The correct, safe fix is to keep `move_uploaded_file()` exactly as-is and add a properly justified `phpcs:ignore`** — the same approach already used elsewhere in this codebase for the chunked `fread()` streaming case, which WP.org reviewers do accept when the justification is genuine and specific.

#### Fix A — `includes/class-media-handler.php:329`

```php
// BEFORE
// Use move_uploaded_file for better compatibility on Windows
if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $zip_file ) ) {
    $this->delete_directory_secure( $temp_dir );
    throw new Exception( esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
}

// AFTER
// Use move_uploaded_file for better compatibility on Windows
// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Source is validated via is_uploaded_file() above. WP_Filesystem::move() is unreliable for PHP's transient upload temp path when the host runs FTP/SSH filesystem mode, which would break uploads on those hosts.
if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $zip_file ) ) {
    $this->delete_directory_secure( $temp_dir );
    throw new Exception( esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
}
```
*Zero behavior change. Nothing about the upload flow is touched — only a documentation comment is added.*

#### Fix B — `includes/class-themes-plugins-handler.php:536` (theme import)

```php
// BEFORE
$temp_zip = $temp_dir . 'import.zip';
if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $temp_zip ) ) {
    $this->rrmdir( $temp_dir );
    return array( 'success' => false, 'message' => esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
}

// AFTER
$temp_zip = $temp_dir . 'import.zip';
// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Source is the just-uploaded $_FILES temp file. WP_Filesystem::move() is unreliable for this path when the host runs FTP/SSH filesystem mode, which would break theme import on those hosts.
if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $temp_zip ) ) {
    $this->rrmdir( $temp_dir );
    return array( 'success' => false, 'message' => esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) );
}
```
*Zero behavior change.*

#### Fix C — `includes/class-themes-plugins-handler.php:709` (plugin import)

Identical pattern and identical justification — add the same `phpcs:ignore Generic.PHP.ForbiddenFunctions.Found` comment directly above the plugin-import method's `move_uploaded_file( $uploaded_file['tmp_name'], $temp_zip )` call. No logic changes.

> **If you specifically want to remove the ignore and use a real replacement instead:** that's only safe to do if you're confident your supported hosting environments run `WP_Filesystem` in `direct` mode (true for the vast majority of modern hosts, but not guaranteed for all). Since this can't be verified without live testing on each target host, keeping `move_uploaded_file()` with a justified ignore is the recommendation that carries zero regression risk.

---

### 4. Writing to `WP_PLUGIN_DIR` via `copy()` — `includes/class-themes-plugins-handler.php:771` — 🔵 Kept as-is + documented

**Why it's blocked:** Plugin folders are wiped and replaced on every WP core/plugin upgrade. Anything written there outside the install/upgrade process can vanish silently, and PCP flags any direct write into `WP_PLUGIN_DIR`.

Looking at the surrounding code, this is the **single-file plugin** branch of `import_plugins_from_zip()`. The directory-plugin branch a few lines above it already writes into `WP_PLUGIN_DIR` via the private `rcopy()` helper, using plain PHP `copy()` internally — PCP doesn't flag that one because it can't statically trace through the recursive helper, but functionally it's the exact same operation.

**Same host-compatibility caveat as the `move_uploaded_file()` fixes above applies here.** Swapping `copy()` for `WP_Filesystem::copy()` is *usually* safe since it's writing from one local path to another (not from a PHP-only transient upload path), so the FTP/SSH-mode risk is smaller than the upload case — but it's still a behavior change on hosts using a non-`direct` filesystem method, and there's no way to verify that without live testing on your supported hosting matrix. Per the skill's "no-break guarantee," the safer call here is the same one: keep the working `copy()` call and document why with a justified `phpcs:ignore`, exactly like the existing `rcopy()` helper functionally already does (just without PCP being able to see it).

```php
// BEFORE
} elseif ( is_file( $extracted_plugin ) ) {
    // Single-file plugin
    if ( copy( $extracted_plugin, $target_plugin ) ) {
        $imported_plugins[] = $plugin_slug;
    } else {
        $failed_plugins[] = $plugin_slug;
    }
}

// AFTER
} elseif ( is_file( $extracted_plugin ) ) {
    // Single-file plugin
    // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite -- Required for the plugin-import feature: this writes the user's just-extracted, already-uploaded plugin file into WP_PLUGIN_DIR, the same way WordPress's own native plugin installer does. The write only happens once at import time, not on every page load, so the upgrade-wipe concern this rule targets does not apply here.
    if ( copy( $extracted_plugin, $target_plugin ) ) {
        $imported_plugins[] = $plugin_slug;
    } else {
        $failed_plugins[] = $plugin_slug;
    }
}
```
*Zero behavior change — this is exactly what the feature needs to do (write an imported plugin file into the plugins directory), and it's no different in risk profile from what WordPress's own plugin installer does.*

> **If you'd rather silence this with an actual code change instead of an ignore:** `WP_Filesystem::copy()` is the option, and on `direct`-mode hosts (the large majority) it behaves identically. Just be aware it introduces the same theoretical FTP/SSH-mode edge case described above, so it's a tradeoff between "passes the static scan with zero ignores" and "more universally guaranteed to keep working as-is."

---

### 5. `mysqli_close()` direct DB access — `includes/class-heartbeat-handler.php:78` — 🟢 Zero risk

**Why it's blocked:** WordPress manages the single shared `$wpdb` connection for the entire request lifecycle (including stuff that runs *after* your code, like other `shutdown` hooks, logging, object cache flush, etc.). Manually closing it on `shutdown` can break anything hooked after your priority-999 callback, and `$wpdb` has no public "is this safe to close" concept — this is exactly the kind of fragile direct-DB-handle manipulation PCP blocks.

The good news: **this entire method's purpose is unnecessary and should simply be removed.** WordPress already closes the MySQL connection cleanly via `wpdb::__destruct()` / PHP's own request teardown — there is no scenario where manually calling `mysqli_close()` on `shutdown` priority 999 provides real benefit, and it risks breaking later shutdown hooks that still need `$wpdb` (e.g., cache flush, logging plugins).

```php
// BEFORE
private function init_hooks() {
    // Optimize heartbeat during imports
    add_filter( 'heartbeat_settings', array( $this, 'optimize_heartbeat_settings' ) );

    // Close database connections properly
    add_action( 'shutdown', array( $this, 'close_db_connections' ), 999 );
}

public function close_db_connections() {
    global $wpdb;

    // Close database connection if it exists
    if ( isset( $wpdb->dbh ) && is_resource( $wpdb->dbh ) ) {
        mysqli_close( $wpdb->dbh );
    }
}

// AFTER
private function init_hooks() {
    // Optimize heartbeat during imports
    add_filter( 'heartbeat_settings', array( $this, 'optimize_heartbeat_settings' ) );
}
```

Delete the `close_db_connections()` method entirely along with the `shutdown` hook registration. `optimize_heartbeat_settings()` (the actual useful feature of this class) is untouched and keeps working exactly as before.

---

### 6. `register_setting()` missing `sanitize_callback` — `includes/class-main.php:409` — 🟢 Zero risk

**Why it's blocked:** Every registered setting must declare how its value is sanitized before being saved to the options table, or PCP's `SettingSanitization.register_settingMissing` rule fires. The other four `register_setting()` calls right below this one (lines 411, 421, 431, 441) already do this correctly — this is the one outlier.

```php
// BEFORE
register_setting( 'peiwm_admin_download_buttons', 'peiwm_enable_admin_download_buttons' );

// AFTER
register_setting(
    'peiwm_admin_download_buttons',
    'peiwm_enable_admin_download_buttons',
    array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    )
);
```
This matches the `type`/`sanitize_callback`/`default` shape already used for `peiwm_user_import_send_email` a few lines down — same boolean checkbox pattern, since `class-admin-download-buttons.php` reads this option with `get_option( 'peiwm_enable_admin_download_buttons', false )`, confirming it's a simple on/off flag.

---

### 7–8. Variable passed to `__()` — `includes/class-generic-recommendations.php:253–254` — 🟢 Zero risk

**Why it's blocked:** `__()`/`esc_html__()` (and all gettext wrappers) require a **static string literal** as the first argument — never a variable — because WordPress's `makepot` tool performs static source-code parsing to build the `.pot` translation file. It cannot evaluate `$info['name']` at scan time, so the string would never be extracted for translation, which defeats the entire purpose of wrapping it.

Looking at where `$info` comes from (`get_plugins_data()`), these are **hardcoded names and descriptions of other WPAzleen plugins** used purely for a cross-promotion/recommendations widget — they are not meant to be translated at all (translating "Shop Explorer" or a product tagline into the site's locale would be incorrect and confusing). The `__()`/`esc_html__()` wrapper was a mistake here, not a missing-domain issue — the correct fix is to remove the translation wrapper and just escape the output normally:

```php
// BEFORE
if ( $data && ! is_wp_error( $data ) ) {
    $recommended_plugins[$slug] = $data;
    $recommended_plugins[$slug]->name = __( $info['name'], 'post-export-import-with-media' );
    $recommended_plugins[$slug]->short_description = esc_html__( $info['description'], 'post-export-import-with-media' );
    $recommended_plugins[$slug]->group = $info['group'];
    $recommended_plugins[$slug]->key_benefits = $info['key_benefits'];
}

// AFTER
if ( $data && ! is_wp_error( $data ) ) {
    $recommended_plugins[$slug] = $data;
    $recommended_plugins[$slug]->name = sanitize_text_field( $info['name'] );
    $recommended_plugins[$slug]->short_description = esc_html( $info['description'] );
    $recommended_plugins[$slug]->group = $info['group'];
    $recommended_plugins[$slug]->key_benefits = $info['key_benefits'];
}
```
This preserves identical output (the values are already trusted plugin-author-authored strings stored in your own `get_plugins_data()` array, not user input) while satisfying the sniff — `sanitize_text_field()` keeps the name safe for storage on the object, `esc_html()` keeps the description safe wherever it's later echoed.

> If you ever *do* want these promo strings translated, the correct (but more invasive) fix is to wrap each literal individually inside `get_plugins_data()`, e.g. `'name' => __( 'Shop Explorer – Speed Booster for WooCommerce...', 'post-export-import-with-media' )`. Given these are third-party-style marketing names, removing translation (as above) is the recommended, lower-risk fix.

---

### 9. `.gitignore` hidden file not permitted

**Not present in the uploaded `includes/` files** — this lives at the plugin root, outside what was shared. Action needed at submission time: delete `.gitignore` from the final SVN/ZIP package before upload. (Keep it in your local git repo if you use one — just exclude it from the `build`/release step, e.g. via your build script's exclude list or `.distignore`.)

### 10–13. Unexpected markdown files (`changes.md`, `doc.md`, `mail-security-fix.md`, `mail.md`, `phpcs-issues.md`)

**Also at plugin root, not in scope of the uploaded files.** WP.org only permits `readme.txt` (and optionally `readme.html`) in the plugin's root/SVN trunk. Action: delete all five files from the release package, or better, add a `.distignore` entry so your build tool (e.g. `wp-scripts`, `grunt-wp-deploy`, or a manual zip script) excludes `*.md` automatically on every future build:

```
# .distignore
*.md
.gitignore
.git
node_modules
```

### 14. `readme.txt` short description over 150 characters

**Also not in the uploaded files.** Action: open `readme.txt`, find the line directly under the plugin header block (before the `== Description ==` heading) — that's the short description shown in WP.org search results — and trim it to **150 characters or fewer**. WP.org truncates anything past that limit, which looks unpolished in search results. If you'd like, share `readme.txt` in a follow-up and the `readme-optimizer` skill can rewrite it properly (it specifically optimizes short descriptions for the 150-char limit).

---

## 🟡 SHOULD FIX BEFORE SUBMISSION (Security + WP Standards)

These are all legitimate reviewer-flagged patterns, but several already have *partial* mitigations in your code (existing `phpcs:ignore` comments, structural JSON validation). The fixes below tighten them to pass cleanly rather than rewriting working logic.

### `is_writable()` → `WP_Filesystem` (2 occurrences) — 🟡 Low risk

- **`class-ajax-handler.php:97`** — used only for a read-only **diagnostics report** (`ajax_test_config()`), not to gate a write operation.
- **`class-media-handler.php:310`** — used to gate an actual upload-directory write.

**Risk check:** unlike the `move_uploaded_file()` case, this one is low-risk. `$upload_dir['basedir']` is always a real, persistent local path on the server (not PHP's transient upload temp file), so `WP_Filesystem::is_writable()` resolves correctly regardless of filesystem mode. Safe to apply as written below.

```php
// BEFORE (both files)
if ( ! is_writable( $upload_dir['basedir'] ) ) { ... }
// or
'upload_dir_writable' => is_writable( $upload_dir['basedir'] ),

// AFTER
global $wp_filesystem;
if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();
$is_writable = $wp_filesystem && $wp_filesystem->is_writable( $upload_dir['basedir'] );

// then use $is_writable in place of the old is_writable() call
```
Same boolean semantics in both call sites — only the writability check mechanism changes.

### `readfile()` / `fopen()` / `fread()` / `fclose()` → `WP_Filesystem` (`class-ajax-handler.php:453, 499, 502, 505, 509`) — 🟡 Low risk (small file) / 🔵 Kept as-is (chunked branch)

**Important nuance:** these two methods (`download_export_posts()` and `download_export_media()`) **stream a file directly to the HTTP response body** after setting `Content-Disposition` headers. `WP_Filesystem`'s `get_contents()` reads a file fully into a PHP string — for the chunked-read branch (8KB-at-a-time `fread()` loop for files >10MB) that defeats the entire point of chunking, which exists specifically to avoid loading large export ZIPs entirely into memory.

The pragmatic, safety-first fix (per the skill's "never break functional integrity to satisfy a linter" rule) is:
- Small-file branch (plain `readfile()`): swap for `$wp_filesystem->get_contents()` + `echo`, since memory isn't a concern at small sizes, and this path reads a local export file (not a transient upload path) so the swap is low-risk.
- Large-file chunked branch: **keep the native `fopen()`/`fread()`/`fclose()` streaming loop exactly as-is**, but silence the sniff with a properly justified `phpcs:ignore` comment, since this is a deliberate, necessary deviation — not an oversight.

```php
// download_export_posts() — small JSON file, BEFORE
readfile( $full_path );
exit;

// AFTER
global $wp_filesystem;
if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JSON export file stream, escaping would corrupt the file
echo $wp_filesystem->get_contents( $full_path );
exit;
```

```php
// download_export_media() — large ZIP, chunked streaming branch, BEFORE
if ( $file_size > 10 * 1024 * 1024 ) {
    $handle = fopen( $full_path, 'rb' );
    if ( $handle ) {
        while ( ! feof( $handle ) ) {
            echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream output, escaping would corrupt the file
            flush();
        }
        fclose( $handle );
    }
} else {
    readfile( $full_path );
}

// AFTER
if ( $file_size > 10 * 1024 * 1024 ) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Chunked binary streaming required for large exports; WP_Filesystem::get_contents() would load the entire file into memory and defeat the purpose of this branch.
    $handle = fopen( $full_path, 'rb' );
    if ( $handle ) {
        while ( ! feof( $handle ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream output, escaping would corrupt the file
            echo fread( $handle, 8192 );
            flush();
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );
    }
} else {
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw ZIP file stream, escaping would corrupt the file
    echo $wp_filesystem->get_contents( $full_path );
}
```
This is the one place in this audit where a `phpcs:ignore` is the correct answer for part of the fix rather than a full rewrite — WP.org reviewers do accept justified ignores for genuine technical constraints like streaming large binaries, as long as the comment explains why.

### `rmdir()` → `WP_Filesystem` (`class-media-handler.php:714`, `class-themes-plugins-handler.php:676`) — 🟡 Low risk

Both are the base case of a recursive directory-delete helper (`rmdir_recursive()` / `rrmdir()`) that already correctly uses `wp_delete_file()` for files. The directories being removed here are always local temp/export folders created earlier in the same request by `wp_mkdir_p()` — not a PHP-only transient path — so this swap carries the same low risk as the `is_writable()` fix above. Apply it to only the final empty-directory removal:

```php
// BEFORE (both files, same line shape)
rmdir( $dir );

// AFTER
global $wp_filesystem;
if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();
if ( $wp_filesystem ) {
    $wp_filesystem->rmdir( $dir );
} else {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP_Filesystem unavailable fallback
    rmdir( $dir );
}
```

### `suppress_filters => true` is prohibited (`class-media-handler.php:130`, `class-batch-processor.php:502`) — 🟢 Zero risk

**Why it's blocked:** `suppress_filters` disables `pre_get_posts` and other query filters — that's a problem on multisite/VIP-style setups where security or scoping plugins rely on those filters firing for *every* query. Despite the code comment claiming this prevents "third-party plugin... capping the result count," the opposite is usually true in practice: it removes a legitimate extensibility point other plugins (including site-security plugins) may depend on.

Since both call sites already pin `post_status => 'inherit'` and `fields => 'ids'` explicitly, removing `suppress_filters` doesn't change correctness in the normal case — it only restores filterability:

```php
// BEFORE
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

// AFTER
$attachment_query = array(
    'post_type'              => 'attachment',
    'numberposts'            => -1,
    'post_status'            => 'inherit',
    'fields'                 => 'ids',
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
);
```
Apply identically to `class-batch-processor.php:502`'s `$attachment_query` array. Also remove or update the now-inaccurate `// FIX: Use suppress_filters=true...` comment above it in `class-media-handler.php`.

### `$_POST[...]` unsanitized warnings (5 occurrences) + `$_FILES[...]['tmp_name']` (1 occurrence) — 🟢 Zero risk

All six of these are **the same underlying pattern**, and all six are already safe in practice — PHPCS just can't statically verify it because the sanitization happens several lines later via custom functions (`sanitize_post_data()`, `sanitize_file_name()`-based handling, `sanitize_import_data()`) rather than inline. `class-user-handler.php`'s `users_json` handling (already in your codebase) shows the *correct, complete* version of this fix — it has a properly scoped `phpcs:ignore` with the exact sniff name and a one-line justification. Apply that same pattern everywhere it's missing or incomplete:

| File | Line | Current state | Fix |
|---|---|---|---|
| `class-post-handler.php` | 310 | Has comment but no sniff code | Add sniff code to existing ignore comment |
| `class-post-handler.php` | 555 | Has comment but no sniff code | Add sniff code to existing ignore comment |
| `class-page-handler.php` | 229 | Has comment but no sniff code | Add sniff code to existing ignore comment |
| `class-page-handler.php` | 789 | Has comment but no sniff code | Add sniff code to existing ignore comment |
| `class-widgets-menus-handler.php` | 249 | **No ignore comment at all** | Add full ignore comment |
| `class-media-handler.php` | 291 | Has comment but no sniff code | Add sniff code to existing ignore comment |

```php
// EXAMPLE FIX — class-post-handler.php:310
// BEFORE
$post_data_raw = isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : ''; //phpcs:ignore we have sanitize below with sanitize_post_data

// AFTER
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON payload; structurally validated via json_decode() and sanitized field-by-field in sanitize_post_data() below.
$post_data_raw = isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '';
```

```php
// EXAMPLE FIX — class-widgets-menus-handler.php:249 (currently has NO ignore comment)
// BEFORE
$widgets_data_raw = isset( $_POST['widgets_data'] ) ? wp_unslash( $_POST['widgets_data'] ) : '';

// AFTER
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON payload; structurally validated via json_decode() and sanitized via sanitize_import_data() below.
$widgets_data_raw = isset( $_POST['widgets_data'] ) ? wp_unslash( $_POST['widgets_data'] ) : '';
```

```php
// EXAMPLE FIX — class-media-handler.php:291
// BEFORE
'tmp_name' => isset( $_FILES['media_file']['tmp_name'] ) ? $_FILES['media_file']['tmp_name'] : '', // phpcs:ignore tmp_name as its system path

// AFTER
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a PHP-generated system path (not user-controllable text); validated below via is_uploaded_file() before use.
'tmp_name' => isset( $_FILES['media_file']['tmp_name'] ) ? $_FILES['media_file']['tmp_name'] : '',
```
Apply the same exact-sniff-code treatment to the remaining three (`class-post-handler.php:555`, `class-page-handler.php:229`, `class-page-handler.php:789`) using their respective existing comments as the basis — just prepend the proper sniff code `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` and tighten the justification text.

---

## 🟠 FIX BEFORE NEXT RELEASE (i18n / Standards — quick pass)

These are mechanical and low-risk. Batch them in one pass per file:

1. **`date()` → `gmdate()`** (`class-admin-download-buttons.php:349,400`; `class-admin-menu.php:644,650`; `class-themes-plugins-handler.php:391,440`) — Both flagged calls in `class-admin-download-buttons.php` build ZIP filenames (`'theme-' . $theme_slug . '-' . date( 'Y-m-d-H-i-s' ) . '.zip'`). Straight swap to `gmdate( 'Y-m-d-H-i-s' )` — filenames don't need local timezone, and this avoids the runtime-timezone-dependency the sniff warns about. Same swap for the other two files' flagged lines.

2. **`parse_url()` → `wp_parse_url()`** (`class-post-handler.php:1393`; `class-page-handler.php:1056`; `class-widgets-menus-handler.php:872`) — Direct drop-in replacement; `wp_parse_url()` has the identical signature/return shape but normalizes behavior across PHP versions. E.g. `basename( parse_url( $image_url, PHP_URL_PATH ) )` → `basename( wp_parse_url( $image_url, PHP_URL_PATH ) )`.

3. **Missing text domain on `__()`/`_x()`/`esc_attr_e()`/`esc_html_e()`** (`class-generic-recommendations.php`, ~20 call sites) — Add `, 'post-export-import-with-media'` as the final argument to every flagged call. Quick regex-assisted pass: search for these functions in this file missing the domain string and append it.

4. **Missing `/* translators: */` comments** (most files, wherever a placeholder like `%s`/`%d` appears in a translatable string) — Add a one-line comment directly above each flagged call describing each placeholder, e.g.:
   ```php
   /* translators: %s: theme name */
   sprintf( esc_html__( 'Theme "%s" ready for download', 'post-export-import-with-media' ), $theme->get( 'Name' ) );
   ```
   `class-email-template.php` and `class-user-handler.php` already do this correctly in several spots (e.g. `/* translators: %s: site URL */`, `/* translators: %d: number of users */`) — use those as the template for the rest.

5. **Unordered placeholders `%d, %d` → `%1$d, %2$d`** (`class-media-handler.php:484`; `class-settings-handler.php:356`; `class-themes-plugins-handler.php:608,801`; `class-widgets-menus-handler.php:368,555,652`) — When a string has 2+ placeholders, number them explicitly so translators can reorder words without breaking argument order:
   ```php
   // BEFORE
   esc_html__( 'Settings import completed: %d imported, %d skipped, %d failed', 'post-export-import-with-media' )
   // AFTER
   esc_html__( 'Settings import completed: %1$d imported, %2$d skipped, %3$d failed', 'post-export-import-with-media' )
   ```
   The `sprintf()`/`printf()` argument order after the string stays exactly the same — only the placeholder syntax inside the string string changes.

---

## ✅ Summary

| Category | Count | Status |
|---|---|---|
| Must Fix Now — 🔵 kept as-is, documented with `phpcs:ignore` | 4 | `move_uploaded_file()` ×3, `WP_PLUGIN_DIR` `copy()` ×1 — no code behavior touched |
| Must Fix Now — 🟢 zero-risk code change | 4 | `mysqli_close()` removal, `register_setting()`, `__()` variable ×2 |
| Must Fix Now — root-level file/readme cleanup | 6 | Action items (no code in scope) |
| Should Fix — 🟢 zero-risk | 8 | `suppress_filters` ×2, `$_POST`/`$_FILES` ignore comments ×6 |
| Should Fix — 🟡 low-risk (local-path-only operations) | 8 | `is_writable()` ×2, `rmdir()` ×2, small-file `readfile()` ×1 |
| Should Fix — 🔵 kept as-is, documented | 1 | Chunked `fopen()`/`fread()`/`fclose()` streaming branch |
| Fix Before Next Release — i18n polish | ~30+ call sites | Documented as repeatable patterns above |

**On the 🔵 items:** these are the ones where the "obvious" PCP-suggested fix (swap to `WP_Filesystem`) would only be reliably equivalent on hosts running `WP_Filesystem` in `direct` mode. Since that can't be confirmed for every hosting environment this plugin will run on, the original working code is left untouched and just documented with a justified `phpcs:ignore` instead — the lowest-risk option for features that depend on file uploads and streaming.

**Recommended order of operations:**
1. Apply the 4 🟢 zero-risk "Must Fix Now" code changes first (these are genuine functional/security blockers with no tradeoff).
2. Add the 4 🔵 `phpcs:ignore` comments for the upload/copy blockers — zero behavior change, satisfies the reviewer requirement to document the deviation.
3. Delete `.gitignore` + the 5 markdown files from your release build, trim the readme short description.
4. Apply the 🟢 and 🟡 "Should Fix" changes — the 🟡 ones are safe specifically because they only touch local server-managed paths, not PHP's transient upload temp files.
5. Add the 1 🔵 `phpcs:ignore` for the chunked download-streaming branch.
6. Do the i18n pass last in one batch per file since it's purely mechanical.
7. Re-run Plugin Check (PCP) locally or via the WP.org PCP plugin to confirm a clean pass before resubmitting — note the 5 `phpcs:ignore` comments will still show in a raw PHPCS run with `-s` (sniff codes shown) but are accepted by WP.org reviewers when justified, and PCP's automated bot generally respects inline ignores for non-critical-severity sniffs.