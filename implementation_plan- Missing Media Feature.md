# Update Missing Media Feature - Implementation Plan

Add an "Update Media" feature to the "Missing from Disk" modal, enabling users to replace missing/broken media files individually or in bulk. This is a **PRO feature** with UI elements in the base plugin (Free) and backend logic in the PRO plugin.

## User Review Required

> [!IMPORTANT]
> - **FREE/PRO Separation Pattern**: The Free plugin provides the UI controls (disabled/locked with a 🔒 icon for Free users, triggering the upgrade modal) and stubbed AJAX handlers. The PRO plugin provides full AJAX endpoints for listing media library items with pagination/search and performing single or bulk replacement operations.
> - **Asset Compilation**: Modern asset files (`assets/js/admin.js` and `assets/css/admin.css`) are compiled into minified build artifacts in `build/` using `webpack`. We will execute `npm run build` after editing source assets.

## Open Questions

- None. Requirements and design patterns are fully documented in `implementation-plan-missing-media.md` and `MEDIA-HEALTH-SUMMARY.md`.

---

## Proposed Changes

### Core Base Plugin (Free Version)

#### [MODIFY] [class-main.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/includes/class-main.php)
- Pass `'is_pro_active' => $this->is_pro_active()` in `wp_localize_script( 'peiwm-admin-script', 'peiwm_ajax', ... )` so frontend JS knows whether PRO is active.

#### [MODIFY] [class-ajax-handler.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/includes/class-ajax-handler.php)
- Register `wp_ajax_peiwm_get_media_library` and `wp_ajax_peiwm_update_missing_media` in `init_ajax_hooks()`.
- Add `ajax_get_media_library()` stub returning top 20 attachments with upgrade message for Free users.
- Add `ajax_update_missing_media()` stub returning error indicating PRO required.

#### [MODIFY] [admin.js](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/assets/js/admin.js)
- Update `showMissingFilesModal()`:
  - Add Action column header (`Action`) to missing files table.
  - Insert Update button cell with `peiwm-update-media-btn`, locked state/modal trigger class (`peiwm-locked-btn peiwm-open-premium-modal`) when Free, and data attributes (`data-media-id`, `data-title`, `data-filename`).
  - Add thumbnail preview container `div.peiwm-media-preview`.
  - Add bulk update button `#peiwm-update-all-selected-btn` at the bottom of the table.
- Add modal helper `showMediaSelectionModal(mediaId, title, filename)` with two tabs ("📁 Media Library" & "⬆️ Upload File"), search input, grid container, upload area, and action buttons.
- Add `loadMediaLibraryForSelection()` & `renderMediaGrid(mediaItems)` to fetch attachments via AJAX and render grid.
- Add `attachMediaSelectorHandlers()` for tab navigation, upload selection preview, search input debounce, Select button (updates `window.peiwmMissingMediaSelections` & row preview), Update Now button (calls `updateSingleMedia`), and Cancel/Close actions.
- Add `updateMediaRowPreview()` to show selected thumbnail preview with a clear '×' button.
- Add `updateBulkUpdateButton()` to toggle bulk update button visibility and count.
- Add click handlers for `.peiwm-update-media-btn:not(.peiwm-locked-btn)` and `#peiwm-update-all-selected-btn`.
- Add `updateSingleMedia(mediaId, selectedMedia)` and `updateAllSelectedMedia()` to submit replacement data to backend AJAX.

#### [MODIFY] [admin.css](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/assets/css/admin.css)
- Add styling for media selection modal (`.peiwm-media-selector-modal`), tabs (`.peiwm-media-source-tabs`, `.peiwm-tab-btn`), grid (`.peiwm-media-grid`, `.peiwm-media-item`), upload zone (`.peiwm-upload-area`), locked buttons (`.peiwm-locked-btn`), mini previews (`.peiwm-mini-thumb`), and selection animations (`@keyframes slideIn`).

---

### PRO Plugin Layer

#### [MODIFY] [class-ajax-handler-pro.php](file:///c:/Users/HP/Local%20Sites/export-import/app/public/wp-content/plugins/post-export-import-with-media/PRO/includes/class-ajax-handler-pro.php)
- Register high-priority hooks (priority 5) to override AJAX endpoints:
  - `wp_ajax_peiwm_get_media_library` -> `ajax_get_media_library_pro`
  - `wp_ajax_peiwm_update_missing_media` -> `ajax_update_missing_media_pro`
- Implement `ajax_get_media_library_pro()` with full pagination (`page`, `per_page`) and title/filename search filtering (`s`).
- Implement `ajax_update_missing_media_pro()`:
  - Validate nonce, user capabilities, and `is_pro_active()`.
  - For `library` type: Get attached replacement file path, verify existence, copy replacement file to missing file target path (`wp_upload_dir()['basedir'] . '/' . $original_file_path`), generate attachment metadata (`wp_generate_attachment_metadata`), and update metadata (`wp_update_attachment_metadata`).
  - For `upload` type: Validate file type & extension (`wp_check_filetype_and_ext`), move uploaded file (`move_uploaded_file`) to target path, and regenerate attachment metadata.

---

### Build & Compilation

#### Webpack Asset Build
- Run `npm run build` via command runner to update `build/js/admin.min.js` and `build/css/admin.min.css`.

---

## Verification Plan

### Automated Tests / Build Verification
- Execute `npm run build` to verify JavaScript and CSS compile cleanly without syntax errors or warnings.
- Run `php -l` on modified PHP files (`includes/class-main.php`, `includes/class-ajax-handler.php`, `PRO/includes/class-ajax-handler-pro.php`) to confirm zero syntax errors.

### Manual Verification
- Check "View Details" on Missing from Disk in Media Statistics.
- Verify Action column displays "Update" button with 🔒 icon for Free users and opens premium modal.
- Verify active PRO environment enables "Update" button, opens selection modal, loads Media Library with search/tabs, allows file upload, and replaces missing media files on disk & database.
- Confirm bulk update selects multiple items and updates them sequentially with proper UI feedback.
