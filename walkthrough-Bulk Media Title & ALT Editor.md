# Walkthrough - Bulk Media Title & ALT Editor (Plan 2)

We have successfully implemented the **Bulk Media Title & ALT Editor** feature for both FREE and PRO tiers.

---

## 1. Submenu & Page Architecture

### Submenu Registration
- Added `"Media Editor"` (`peiwm-media-alt-editor`) under `peiwm-secure`.
- Conditioned on `PEIWM_Main::is_pro_active()`:
  - **Free Tier**: Renders locked demo page via `PEIWM_Media_Alt_Editor_Page` (`includes/class-media-alt-editor-page.php`).
  - **PRO Tier**: Renders interactive editor via `PEIWM_Media_Alt_Editor_Page_Pro` (`PRO/includes/class-media-alt-editor-page-pro.php`).

### Script Enqueues & Localization
- Enqueues `assets/css/media-alt-editor.css`.
- Localizes `peiwm_media_editor` with secure nonce, batch size setting, and translation strings.

---

## 2. PRO Backend AJAX System (`PRO/includes/class-ajax-handler-pro.php`)

Registered 4 Priority-5 override AJAX endpoints:

1. **`peiwm_load_media_editor`** (`ajax_load_media_editor_pro`):
   - Paginated attachment fetching supporting `offset`, `batch_size`, `search`, and `sort_by`.
   - Supports `empty_alt` filter using SQL `LEFT JOIN` on `_wp_attachment_image_alt` postmeta.
   - Returns attachment list with IDs, titles, ALT text, thumbnail URLs, relative paths, filenames, and dates.

2. **`peiwm_save_media_changes`** (`ajax_save_media_changes_pro`):
   - Batch saves modified titles via `wp_update_post()` and ALT text via `update_post_meta()`.

3. **`peiwm_export_media_csv`** (`ajax_export_media_csv_pro`):
   - Streams UTF-8 BOM formatted CSV file with headers: `ID`, `Path`, `Filename`, `URL`, `Title`, `ALT`.

4. **`peiwm_import_media_csv`** (`ajax_import_media_csv_pro`):
   - Parses uploaded CSV file.
   - Matches attachments by hierarchy: **ID** $\rightarrow$ **Path** $\rightarrow$ **Filename** $\rightarrow$ **URL**.
   - Updates modified fields and returns detailed summary counters (`updated`, `skipped`, `not_found`).

---

## 3. Frontend Controllers

- **Free Controller** (`assets/js/media-alt-editor.js`):
  - Triggers the `.peiwm-open-premium-modal` overlay upon clicking any section of the locked demo UI.

- **PRO Controller** (`PRO/assets/js/media-alt-editor.js`):
  - Real-time debounced search input (500ms).
  - Filter dropdowns (All Images vs Empty ALT, sorting options).
  - Mode toggles: Both / Title Only / ALT Only column visibility.
  - Diff tracking on title and ALT inputs with `.peiwm-changed` row and `.peiwm-input-changed` input highlighting.
  - "Load Next 100" batch pagination.
  - "Save All Changes" and "Discard Changes".
  - CSV Export & Import integration.

---

## 4. Batch Settings Integration (`includes/class-batch-settings.php`)

- Added `media_editor_page_size` setting (Default: 100, Range: 10–1000).
- Configurable directly from the **Batch Configuration** settings page.

---

## Verification Results

### Automated PHP Syntax Verification
```bash
php -l includes/class-admin-menu.php includes/class-media-alt-editor-page.php PRO/includes/class-media-alt-editor-page-pro.php includes/class-ajax-handler.php PRO/includes/class-ajax-handler-pro.php includes/class-batch-settings.php
```
**Result**: `No syntax errors detected` across all files.
