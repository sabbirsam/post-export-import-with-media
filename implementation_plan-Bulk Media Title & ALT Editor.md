# Implementation Plan - Bulk Media Title & ALT Editor (Plan 2)

A comprehensive bulk editor for media titles and ALT text featuring inline editing, debounced search, empty ALT filtering, sorting, CSV export/import matching, and configurable batch loading. This feature follows the established FREE/PRO separation pattern with a locked demo UI in the Free tier and full backend processing in PRO.

---

## User Review Required

> [!IMPORTANT]
> - **Menu Position**: Adds a new submenu item "Media Editor" under `peiwm-secure` (Export/Import with Media).
> - **CSV Matching Priority**: When importing CSV files, attachments will be matched sequentially by **ID** $\rightarrow$ **Path** $\rightarrow$ **Filename** $\rightarrow$ **URL**.
> - **FREE Tier Behavior**: The Free tier renders a locked demo UI with blurred media table controls and triggers the existing `peiwm-open-premium-modal` on interaction.

---

## Proposed Changes

---

### Component 1: Admin Menu & Page Handlers (FREE & PRO)

#### [MODIFY] [class-admin-menu.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/includes/class-admin-menu.php)
- Register `peiwm-media-alt-editor` submenu under `peiwm-secure`.
- Implement `media_alt_editor_page()` callback to conditionally load `PEIWM_Media_Alt_Editor_Page_Pro::render()` (PRO) or `PEIWM_Media_Alt_Editor_Page::render()` (Free).
- Enqueue `media-alt-editor.css` and `media-alt-editor.js` on the `export-import-posts_page_peiwm-media-alt-editor` screen hook, localizing `peiwm_media_editor` with nonces, i18n strings, and batch size settings.

#### [NEW] [class-media-alt-editor-page.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/includes/class-media-alt-editor-page.php)
- Implement `PEIWM_Media_Alt_Editor_Page` class for Free users.
- Render locked section wrapped in `.peiwm-locked-section` with `.peiwm-open-premium-modal` button overlay and static preview rows.

#### [NEW] [class-media-alt-editor-page-pro.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/PRO/includes/class-media-alt-editor-page-pro.php)
- Implement `PEIWM_Media_Alt_Editor_Page_Pro` class for PRO users.
- Render functional toolbar: Search input, ALT Filter dropdown (All vs Empty ALT), Sort dropdown (Date, Modified, Title, Name), Edit Mode radio buttons (Both / Title Only / ALT Only), Export CSV, Import CSV button with hidden file input, media table container, and footer with batch load / discard / save controls.

---

### Component 2: AJAX Handlers & Data Processing

#### [MODIFY] [class-ajax-handler.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/includes/class-ajax-handler.php)
- Register stub AJAX endpoints for Free version:
  - `peiwm_load_media_editor`
  - `peiwm_save_media_changes`
  - `peiwm_export_media_csv`
  - `peiwm_import_media_csv`
- Return standard "Upgrade to PRO" error responses for un-gated AJAX attempts.

#### [MODIFY] [class-ajax-handler-pro.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/PRO/includes/class-ajax-handler-pro.php)
- Register priority-5 override hooks for all 4 Alt Editor AJAX actions:
  - `ajax_load_media_editor_pro()`: Performs paginated queries for image attachments (`offset`, `batch_size`). Supports empty ALT filtering via `LEFT JOIN` on `_wp_attachment_image_alt` postmeta, SQL prepared search, and custom ordering (`date`, `modified`, `title`, `name`). Returns media objects with thumbnail URLs, filenames, and relative upload paths.
  - `ajax_save_media_changes_pro()`: Processes JSON-encoded changes array. Sanitizes title and ALT input, updates `post_title` via `wp_update_post()` and `_wp_attachment_image_alt` via `update_post_meta()`.
  - `ajax_export_media_csv_pro()`: Fetches all image attachments, outputs UTF-8 BOM CSV stream with headers (`ID`, `Path`, `Filename`, `URL`, `Title`, `ALT`).
  - `ajax_import_media_csv_pro()`: Parses uploaded CSV file, matches attachments using ID $\rightarrow$ Path $\rightarrow$ Filename $\rightarrow$ URL hierarchy, updates titles/ALT, and returns summary stats (`updated`, `skipped`, `not_found`).

---

### Component 3: Configuration & Assets

#### [MODIFY] [class-batch-settings.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/includes/class-batch-settings.php)
- Add `media_editor_page_size` setting (default: 100, range: 10–1000).
- Add settings page input row under Batch Configuration section.

#### [NEW] [assets/css/media-alt-editor.css](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/assets/css/media-alt-editor.css)
- Layout CSS for controls bar, filters, responsive media table, thumbnail previews, inline edit change highlights (`.peiwm-changed`), action buttons, and loading spinner overlay.

#### [NEW] [assets/js/media-alt-editor.js](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/assets/js/media-alt-editor.js)
- Handle interactive logic:
  - Debounced search & filter reload.
  - Real-time diff tracking on input fields (highlighting modified rows and enabling footer save/discard actions).
  - Batch pagination ("Load Next 100").
  - CSV export trigger and CSV file import AJAX handler.
  - Free tier modal trigger on locked demo table click.

---

## Verification Plan

### Automated Tests / Lint
- Check PHP syntax on all modified and new files:
  `php -l includes/class-admin-menu.php`
  `php -l includes/class-media-alt-editor-page.php`
  `php -l PRO/includes/class-media-alt-editor-page-pro.php`
  `php -l includes/class-ajax-handler.php`
  `php -l PRO/includes/class-ajax-handler-pro.php`
  `php -l includes/class-batch-settings.php`

### Manual Verification
1. **Free Tier Test**:
   - Access "Media Editor" menu.
   - Verify demo table renders with locked overlay and static content.
   - Click anywhere on demo section to verify PRO upgrade modal triggers.
2. **PRO Tier Test**:
   - Access "Media Editor" menu with PRO active.
   - Verify initial 100 media items load with thumbnails, titles, and ALT text.
   - Test search input and "Empty ALT" filter.
   - Perform inline edits on Title and ALT text, verify `.peiwm-changed` row highlighting.
   - Click "Save All Changes" and verify DB updates in WordPress Media Library.
   - Test "Export CSV", modify CSV externally, re-import via "Import CSV", and verify matching statistics.
