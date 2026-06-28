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
