# Implementation Plan - Media Health & Audit Feature (Plan 3)

Build a comprehensive, lightweight **Media Health & Audit** feature for Post Export Import with Media (PEIWM). This feature enables users to scan their media library, analyze usage across content (posts, pages, postmeta, theme options, widgets, menus), compute confidence and health scores (0-100%), and safely clean up unused media files.

---

## User Review Required

> [!IMPORTANT]
> - **FREE Core Feature**: This implementation delivers a fully functional FREE cleanup system. PRO features (advanced page builder scanners, one-click bulk trash, ZIP exports, cron automation) will be visually indicated with PRO badges and upgrade modals.
> - **Design System Alignment**: All admin screens (`Media Audit Dashboard` and `Review Images`) will reuse the exact design language, breadcrumb (`crumb`), page header (`heading-admin`), dark gradient banner (`journey`), tab panels, and modal feedback system used across the plugin.

---

## Proposed Changes

### Component 1: Database Architecture

Create 4 custom database tables on plugin activation/update:

#### [NEW] Custom DB Tables
- `{prefix}_peiwm_media_scans`: Stores scan sessions, progress state, health scores, and metrics.
- `{prefix}_peiwm_media_reports`: Per-attachment analysis results (status, confidence score, risk level, usage locations).
- `{prefix}_peiwm_media_decisions`: Stores persistent user actions (e.g., ignore/keep item) that persist across rescans.
- `{prefix}_peiwm_scan_logs`: Audit trail for scan events, error logging, and cleanup actions.

---

### Component 2: Core Scanning & Analysis Engines (`includes/`)

#### [NEW] `includes/class-media-audit-controller.php`
- Master controller handling scan initialization, batch step execution, state management, and summary generation.

#### [NEW] `includes/class-media-batch-processor.php`
- Non-blocking batch execution engine processing attachments and content in chunks to support 100k+ media items without memory timeout.

#### [NEW] `includes/class-media-scanner-registry.php` & 6 Core Scanners:
1. `class-scanner-post-content.php`: Scans WordPress blog posts (`post_type='post'`) for `<img>` tags, image IDs, and post galleries.
2. `class-scanner-page-content.php`: Scans WordPress pages (`post_type='page'`) for media references.
3. `class-scanner-postmeta.php`: Scans all `postmeta` keys/values for attachment IDs, GUIDs, and file paths (ACF & custom fields).
4. `class-scanner-theme-options.php`: Scans theme mods, customizer options, site logo, favicon, header, and background images.
5. `class-scanner-widgets.php`: Scans active widget content and image blocks.
6. `class-scanner-menus.php`: Scans navigation menu items for custom thumbnails and icons.

#### [NEW] Safety, Risk & Health Engines:
- `class-media-safety-engine.php`: Rules engine preventing deletion of critical assets (featured images, site logo, recent uploads < 7 days).
- `class-media-confidence-engine.php`: Calculates confidence percentage (0-99%) based on scanner coverage and detection certainty.
- `class-media-risk-engine.php`: Evaluates risk tier (`Very Low`, `Low`, `Medium`, `High`, `Critical`).
- `class-media-health-score.php`: Computes total library health score (0-100%).
- `class-media-user-decisions.php`: Manages user overrides (ignore item, mark for trash).

---

### Component 3: Admin UI Pages & Menu Integration

#### [NEW] `includes/class-media-audit-page.php`
Render **Media Audit Dashboard** page (`admin.php?page=peiwm-media-audit`):
- Standard breadcrumbs (`crumb`: `Export/Import / Media Health`)
- Page header (`heading-admin`) with subtitle (`sub`)
- Dark gradient Journey section (`journey`) with `✨ Media Audit` badge
- State 1 (First visit): "Start Media Scan" CTA card
- State 2 (Scanning): Live progress bar, current scanner indicator, and real-time activity log
- State 3 (Completed): Health Score Hero card (0-100%), 9 summary metric cards, "Review Unused Images" button, and "Rescan" button

#### [NEW] `includes/class-media-audit-review-page.php`
Render **Review Unused Media** page (`admin.php?page=peiwm-media-audit-review`):
- Filter bar (Status: All / Unused / Possibly Used; Confidence; Risk Level)
- Media items table: Thumbnail, Filename/ID, Usage status, Safety badges, Risk score, and Action buttons ("Move to Trash", "Keep File")
- Locked PRO action placeholders ("Bulk Trash Unused", "Export Unused ZIP") triggering the upgrade modal on click

#### [MODIFY] `includes/class-admin-menu.php`
- Add "Media Health" sub-menu entry under the main plugin menu.
- Add "Media Health Score" summary card to the existing **Media Statistics** section on the main admin page.

---

### Component 4: AJAX Handlers & Frontend Assets

#### [MODIFY] `includes/class-ajax-handler.php`
Register 4 AJAX endpoints:
1. `peiwm_start_audit`: Initiates a new scan session and returns session ID.
2. `peiwm_audit_progress`: Processes the next batch chunk and returns live progress percentage and logs.
3. `peiwm_get_audit_summary`: Retrieves completed scan metrics and health score.
4. `peiwm_trash_unused_media`: Moves an individual unused attachment to WordPress Trash with pre-flight safety check validation.

#### [NEW] `assets/js/media-audit.js`
- Handles dashboard user interactions, batch AJAX polling with error recovery, live log updates, table filtering, and confirmation modals using `showConfirm()`, `showSuccess()`, and `showError()`.

#### [NEW] `assets/css/media-audit.css`
- Feature-specific layout styles adhering strictly to `peiwm-` class prefixing, CSS custom variables, and responsive breakpoints.

---

## Verification Plan

### Automated & Syntax Checks
- Run PHP lint (`php -l`) on all new/modified files in `includes/` and `includes/scanners/`.
- Run JavaScript syntax check (`node -c assets/js/media-audit.js`).

### Manual Functional Verification
1. **Scan Execution**: Click "Start Media Scan", verify batch progress polling, log output, and DB table records creation.
2. **Dashboard Results**: Confirm Health Score (0-100%) and 9 metrics reflect actual media post/page usage.
3. **Review Page**: Verify filtering by status/risk, preview thumbnails, and verify individual "Move to Trash" moves items to WP Trash cleanly with confirmation modal.
4. **Safety Protection**: Verify featured images and site logo items display safety badges and block accidental deletion.
