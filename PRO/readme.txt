=== Post Export Import with Media Pro ===
Contributors: wpazleen
Tags: export, import, migration, backup, cloud-storage, scheduled-exports
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable Tag: 1.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Advanced WordPress content migration with scheduled backups, cloud storage, and premium features for comprehensive site management.

== Description ==

**Post Export Import with Media Pro** is the premium version of the popular Post Export Import with Media plugin, adding powerful automation, cloud storage, and advanced filtering capabilities.

### 🎯 PRO Features

#### **Scheduled Exports** (Coming Soon)
* Automatic daily, weekly, or monthly exports
* Email notifications when exports complete
* Keep last N backups, auto-delete old ones
* Incremental exports (only new/modified content)
* Set specific times for exports (e.g., 2 AM when traffic is low)

#### **Cloud Storage Integration** (Coming Soon)
* Direct backup to Google Drive
* Dropbox integration
* Amazon S3 support
* FTP/SFTP servers
* Automatic scheduled backups to cloud
* Version history (keep last 10 versions)

#### **Advanced Filtering** (Coming Soon)
* Visual content picker with checkboxes
* Filter by date range
* Filter by author, category, tags
* Filter by custom fields
* Save filter presets for reuse
* Dependency detection (auto-include related content)

#### **Custom Post Types Support** (Coming Soon)
* Export/import any custom post type
* WooCommerce products with variations
* Advanced Custom Fields (ACF) support
* Page builder support (Elementor, Beaver Builder, Divi)
* Custom taxonomies and relationships

#### **Find & Replace During Import** (Coming Soon)
* Replace URLs (old domain → new domain)
* Replace text strings
* Replace shortcodes
* Regex support for advanced users

### ✨ All Free Features Included

* Export and import posts with all attached media files
* Automatic media file detection and download
* Real-time progress tracking
* Smart image handling
* Pages export/import with hierarchy
* WordPress settings backup
* Widgets & navigation menus
* Themes & plugins backup
* Batch processing for large sites
* And much more!

### 🚀 Perfect For

* **Agencies** - Manage multiple client sites efficiently
* **Developers** - Automate backups and deployments
* **Site Migrations** - Professional-grade migration tools
* **Enterprise** - Advanced features for large-scale operations

### 📋 Requirements

* Post Export Import with Media (Free version) must be installed and activated
* WordPress 6.7 or higher
* PHP 7.4 or higher

== Installation ==

1. Install and activate "Post Export Import with Media" (free version) first
2. Upload the Pro plugin folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Enter your license key to receive updates and support

== Frequently Asked Questions ==

= Do I need the free version installed? =
Yes, the Pro version requires the free version to be installed and activated.

= Will my free version settings be preserved? =
Yes, the Pro version extends the free version without affecting your existing settings.

= Can I use both free and Pro versions together? =
Yes, they are designed to work together. The Pro version takes over license management.

= What happens if I deactivate the Pro version? =
The free version will continue to work normally with all your existing data.

== Changelog ==

= 1.0.5 - 2026-07-29 =
* **Fix:** Fixed minor bug in Export and Media Statistics Memory Exhaustion on large sites
* **improvements:** Export and Media Statistics Memory Exhaustion bug fixed 
* **Improvement:** Optimized memory usage for all export processes

= 1.0.4 - 2026-07-24 =
* **New:** Added Image Matching Strategy with three modes:
  * Verify only fallback matches – Verify only filename-based matches for faster imports.
  * Verify all matches – Verify file size for every match to prevent duplicate filename mismatches.
  * Always download fresh – Always download images from the source without reusing existing media.
* **New:** Added option to link reused media to imported posts by updating the Media Library **Uploaded to** relationship.
* **Improved:** * Update SDK 

= 1.0.3 - 2026-07-08 =
* **Fix:** Resolved password hash query issues.
* **Fix:** Improved security by hardening the exports directory.
* **Fix:** Implemented unguessable export filenames using random tokens and directory hardening.
* **Fix:** Added destination file extension re-validation before file copy operations.
* **Fix:** Addressed additional Plugin Check (PCP) issues and code quality improvements.

= 1.0.2 - 2026-06-21 =
* **Fix:** Fixed an issue where imported images could reference the wrong image size.
* **Fix:** Added logic to skip importing duplicate post titles when the content and slug do not match.

= 1.0.1 - 2026-06-06 =
- ⚙️ **Improvement** : Added compatibility support for WordPress 7.0.

= 1.0.0 - 2026-02-14 =
* Initial Pro version release
* Added Pro infrastructure and licensing
* Prepared for scheduled exports feature
* Prepared for cloud storage integration
* Prepared for advanced filtering
* Pro badge and UI enhancements
