- **Outcome**: Three security fixes applied; plugin passes WP.org scan for report 439aa174
- **Blockers**: None. The import flow destination filename clearly requires validation before `copy()`.
- **AI Workflow**: 
  1. Fix `class-batch-processor.php`: Remove `user_pass` query, add directory hardening, make filenames unguessable.
  2. Fix `class-media-handler.php`: Add extension validation immediately before `copy()`.
  3. Fix `class-user-handler.php`: Make user export filename unguessable, add `.htaccess` directory hardening.
- **Strategy**: Minimal surgical edits; no refactoring; backward-compatible.
- **Key Outputs**:
  - `includes/class-batch-processor.php`
  - `includes/class-media-handler.php`
  - `includes/class-user-handler.php`
