
## File Locations

**Missing Media - plan 1:** `implementation-plan-missing-media.md` (PRO feature)   
**Alt Editor: - plan 2** `implementation-alt-title-editor.md` (48 hours, PRO feature)  
**Main Plan: - plan 3** `implementation-plan-media-health.md` (212 hours of FREE cleanup features)   

All plans follow the same FREE/PRO separation pattern focused on their specific goals.

---

## UI/UX Consistency Requirements

**CRITICAL: All features MUST follow existing plugin design patterns**

### Design System to Follow:
**Reference Page:** `admin.php?page=peiwm-secure` (Posts & Media Migration)

#### 1. **Class Naming Convention**
Use existing class prefixes and patterns:
```css
.peiwm-*           /* All components */
.wrap              /* WordPress standard wrapper */
.page-header       /* Header section */
.heading-admin     /* Page titles */
.crumb             /* Breadcrumb navigation */
.btn               /* All buttons */
.btn-primary       /* Primary action buttons */
.btn-ghost         /* Secondary buttons */
.peiwm-section     /* Main content sections */
.panel-head        /* Panel headers */
.tabs              /* Tab navigation */
.tab-btn           /* Tab buttons */
.tab-content       /* Tab content wrapper */
.tab-panel         /* Individual tab panels */
```

#### 2. **Color Palette** (Match Existing)
```css
Primary Purple: #7c3aed
Success Green: #10b981
Warning Yellow: #f59e0b
Error Red: #ef4444
Neutral Gray: #6b7280
Light Background: #f9fafb
Border: #e5e7eb
Text Dark: #1f2937
Text Light: #6b7280
```

#### 3. **Component Patterns to Reuse**

**Journey Section:**
```html
<section class="journey" id="journey">
    <div class="journey-head">
        <h2>Your Journey Title</h2>
        <p id="journey-desc">Description text</p>
    </div>
    <div class="steps">
        <button type="button" class="step active">
            <div class="step-connector"></div>
            <div class="step-top">
                <div class="step-num">1</div>
                <span class="step-title">Step Title</span>
            </div>
            <p class="step-desc">Step description</p>
        </button>
    </div>
</section>
```

**Section with Tabs:**
```html
<div class="peiwm-section">
    <div class="panel-head">
        <div class="panel-title">
            <div class="panel-icon">
                <svg>...</svg>
            </div>
            <div>
                <h3>Title</h3>
                <span>Subtitle</span>
            </div>
        </div>
    </div>
    <div class="tabs" data-group="groupname">
        <button type="button" class="tab-btn active">Tab 1</button>
        <button type="button" class="tab-btn">Tab 2</button>
    </div>
    <div class="tab-content">
        <div class="tab-panel active" data-panel="groupname-tab1">
            Content here
        </div>
    </div>
</div>
```

**Button Styles:**
```html
<!-- Primary action -->
<button class="btn btn-primary">Primary Action</button>

<!-- Secondary action -->
<button class="btn btn-ghost">Secondary Action</button>

<!-- With icon -->
<button class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="..."></path>
    </svg>
    Button Text
</button>
```

**Page Header Pattern:**
```html
<div class="page-header">
    <div>
        <div class="crumb">
            <svg>...</svg>
            Breadcrumb / Navigation
        </div>
        <h1 class="heading-admin">
            Page Title
        </h1>
        <p class="sub">Subtitle description</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-ghost">Action 1</button>
        <button class="btn btn-primary">Action 2</button>
    </div>
</div>
```

#### 4. **Specific Requirements per Feature**

**Media Health & Audit:**
- Health card in Media Statistics MUST match existing stats cards style
- Dashboard cards use same grid layout as Journey section
- Progress bars follow existing batch processing UI
- Table styling matches current export list tables
- Filter dropdowns use existing form styles

**Media Title & ALT Editor:**
- Search/filter bar layout like selective export panel
- Table follows same structure as posts/media export lists
- Edit mode toggles styled as tab buttons
- Change tracking (row highlighting) matches journey step states
- CSV buttons use btn-ghost style

**Update Missing Media:**
- Modal design matches existing premium upgrade modal
- Two-tab layout follows existing tab pattern
- Thumbnail preview cards consistent with media display
- "Update" button styling matches existing action buttons
- Progress indicators follow batch processing style

#### 5. **Typography**
```css
Page Title (h1): heading-admin class
Section Title (h2): peiwm-section-title
Card Title (h3): Default with margin
Body Text: 14px, color: #4a5568
Description: 13px, color: #6b7280, .description class
Small Text: 12px, color: #9ca3af
```

#### 6. **Spacing & Layout**
```css
Section Margins: 2rem (32px) between major sections
Card Padding: 1.5rem (24px)
Button Padding: 0.5rem 1rem (8px 16px)
Grid Gap: 1rem (16px) or 1.5rem (24px)
Border Radius: 8px (cards), 6px (buttons), 4px (inputs)
```

#### 7. **Icons**
Use existing SVG icon style:
```html
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <path d="..."/>
</svg>
```
Consistent size: 14px-20px inline, 24px in headers

#### 8. **Responsive Behavior**
Follow existing breakpoints and mobile patterns:
- Desktop: Full layout
- Tablet: Stacked sections, maintained spacing
- Mobile: Single column, larger touch targets

### Implementation Checklist:
- [ ] All class names use `peiwm-` prefix
- [ ] Colors match existing palette exactly
- [ ] Button styles (btn, btn-primary, btn-ghost) used correctly
- [ ] Journey/steps pattern reused where applicable
- [ ] Tabs follow existing data-group/data-panel pattern
- [ ] Page headers use crumb + heading-admin + sub structure
- [ ] Icons are SVG with viewBox="0 0 24 24"
- [ ] Spacing uses rem units matching existing scale
- [ ] No new CSS files for basic components (reuse existing)
- [ ] Only add feature-specific CSS (audit-specific, editor-specific)

**Goal:** New features should look like they were built with the original plugin, not bolted on later. Users should feel seamless navigation between all pages.

---

# All Features Summary


## Feature 1: Update Missing Media (PRO Feature)

**File:** `implementation-plan-missing-media.md`  
**Focus:** Replace missing media files (broken image references)  
**Timeline:** Included in implementation plan

### FREE Version ✅ (Demo UI)

**What FREE Users See:**
- "Missing from Disk" view shows list:
  - ID, Title, Filename, Expected Path
  - "Fix Path" button (works)
  - "Clean Missing File" button (works)
  - **"Update" button (🔒 locked + disabled)**

**Interaction:**
- Clicking locked "Update" button shows PRO upgrade modal
- Tooltip: "PRO feature - Replace missing media"
- Other buttons (Fix Path, Clean) work normally

### PRO Version 🔒 (Full Functionality)

**Individual Update:**
- Click "Update" button on any missing media
- Modal opens with 2 tabs:
  1. **Media Library Tab:** Browse existing media to select replacement
  2. **Upload Tab:** Upload new file from desktop
- Preview selected image with filename
- "X" icon to remove selection
- Two action buttons:
  - "Select" - Closes modal, shows preview thumbnail
  - "Update Now" - Immediately replaces media

**After Selection:**
- "Update" button replaced with thumbnail preview
- "X" icon to remove and reselect
- Click thumbnail to reopen modal

**Bulk Update:**
- Select multiple missing media with previews
- "Update All" button at bottom
- Batch processes all replacements
- Progress indicator
- Success/error summary

**Backend (PRO):**
- 2 AJAX endpoints: select media, update media
- File handling: Upload + media library selection
- Database: Updates attachment post, metadata, file paths
- Validation: File types, sizes, permissions
- Batch processing with safety checks

**Value Proposition:**
- FREE: Identify missing media, fix paths, clean references
- PRO: Replace missing files easily, bulk operations, upload or select from library

---


## Feature 2: Bulk Media Title & ALT Editor (PRO Feature)

**File:** `implementation-alt-title-editor.md`  
**Focus:** Bulk edit media titles and ALT text efficiently  
**Timeline:** 48 hours (~1-1.5 weeks)

### FREE Version ✅ (Demo UI)

**What FREE Users See:**
- Full UI visible (blurred/disabled)
- Menu item: "Media Editor" under main plugin menu
- Demo page shows:
  - Search box
  - Filters (All images / Images with empty ALT)
  - Sort options (date, title, URL)
  - Edit mode toggles (Title & ALT / Title Only / ALT Only)
  - Sample table with 3 rows (disabled)
  - Export/Import CSV buttons (disabled)
  - Load More / Discard / Save buttons (disabled)

**Interaction:**
- Clicking anywhere shows PRO upgrade modal
- "🔒 PRO" badge visible everywhere
- All inputs/buttons disabled
- Clear call-to-action to upgrade

### PRO Version 🔒 (Full Functionality)

**Search & Filter:**
- Search by filename or title (debounced 500ms)
- Filter: All images / Images with empty ALT
- Sort by: Upload date, Modified date, Title (A-Z/Z-A), URL

**Edit Modes:**
- Title & ALT (both columns visible)
- Title Only (hide ALT column)
- ALT Only (hide Title column)

**Inline Editing:**
- Direct editing in table cells
- Change tracking (rows highlight on change)
- "Discard Changes" button appears when changes exist
- "Save All Changes" button appears when changes exist
- Real-time change counter

**Batch Loading:**
- Configurable items per page (batch settings: 10-1000, default 100)
- "Load Next 100" pagination
- Shows "X of Y media files (Z unsaved changes)"

**CSV Export/Import:**
- **Export:** Downloads CSV with ID, Path, Filename, URL, Title, ALT
- **Import:** Upload CSV to bulk update
- **Matching priority:**
  1. ID match (direct)
  2. Path match (relative from uploads)
  3. Filename match (first found)
  4. URL match (full URL)
- Shows import summary (updated, skipped, not found)

**Backend (PRO):**
- 4 AJAX endpoints: load media, save changes, export CSV, import CSV
- Security: Nonces, capability checks, sanitization
- Database: Updates `post_title` and `_wp_attachment_image_alt`
- Batch processing support

**Value Proposition:**
- FREE: See what's possible, understand the feature
- PRO: Save hours editing media metadata, bulk operations, CSV import/export

---

## Feature 3: Media Health & Audit (FREE + PRO Enhancements)

**File:** `implementation-plan-media-health.md`  
**Focus:** Identify and safely clean unused media files  
**Timeline:** 212 hours FREE (~5-6 weeks) + 3-4 weeks PRO enhancements

### FREE Features ✅

**Core Scanning:**
- 6 core scanners: Posts (90%), Pages (90%), Post Meta (85%), Theme Options (98%), Widgets (80%), Menus (70%)
- Batch processing for 100k+ media libraries
- Confidence scoring (0-99%) - how sure we are media is unused
- Risk level calculation (Very Low → Critical)
- Health score (0-100%) for entire library
- Safety rules engine (prevents accidental deletion)

**UI Components:**
- Media Health Card (shows in Media Statistics section)
- Media Audit Dashboard page
  - Empty state: "Start Scan" button
  - Scanning state: Live progress + logs
  - Complete state: Health score hero, 9 summary cards
- Review Images page
  - Filters: Status, confidence, risk level
  - Table: Thumbnail, filename, status, actions
  - Individual "Move to Trash" with safety checks

**Cleanup Actions (FREE):**
- View all unused media in filterable table
- Individual trash (with confirmation + safety checks)
- Export unused list as JSON
- Import decisions from JSON (apply across sites)
- Rescan functionality

**Settings (FREE):**
- Scanners per batch (1-6, default 2)
- Scan retention (1-10, default 3)
- Cache results (fingerprint-based)
- Confirm before trash toggle

**Database (FREE):**
- 4 tables: scans, reports, decisions, logs
- Stores scan history, per-image analysis, user decisions, audit trail

**Safety Features (FREE):**
- Never deletes automatically (user must confirm)
- Safety rules: Featured images, site icons, theme assets, recent uploads, high-risk items
- Trash (reversible) not permanent delete
- Confidence floor: <60% coverage = no deletion recommended

### PRO Enhancements 🔒

**Advanced Scanners:**
- Gutenberg Block Scanner (reusable blocks, custom blocks)
- Elementor Scanner (page builder content)
- WooCommerce Scanner (product images, galleries, variations)
- BuddyPress/bbPress Scanner (forums, profiles, activity)

**Bulk Cleanup Operations:**
- Bulk trash with one click (safety checks applied to each)
- Batch export unused media as ZIP (download before cleanup for backup)
- Bulk import decisions (apply across multiple sites)

**Automation:**
- Scheduled auto-scans (daily/weekly cron)
- Auto-scan after post/media import
- Email reports (cleanup summary, space saved, warnings)
- Auto-trash after X days (with safety confirmation + user review)

**Value Proposition:**
- FREE: Full cleanup system for WordPress core content
- PRO: Advanced content builders + bulk operations + automation

---



# Media Health & Audit Feature - Quick Reference

## What Changed?

Consolidated two draft files into one comprehensive implementation plan with proper FREE/PRO separation and **focused on unused media cleaning**.

**New File:** `implementation-plan-media-health.md` 

---

## Feature Focus: Unused Media Cleaning

**Primary Goal:** Help users identify and safely remove unused media files to:
- ✅ Free up disk space
- ✅ Reduce backup sizes  
- ✅ Improve site performance
- ✅ Clean up cluttered media libraries

**How It Works:**
1. **Scan** - Find all media references across WordPress content (posts, pages, meta, theme, widgets, menus)
2. **Analyze** - Determine which media files are actually used vs unused
3. **Report** - Show unused files with confidence scores (0-99%) and risk levels
4. **Clean** - Allow safe deletion with multiple safety checks

**Safety First:**
- ❌ Never deletes automatically
- ✅ Multiple safety rules (featured images, theme assets, recent uploads, high-risk items)
- ✅ Trash (reversible) instead of permanent delete
- ✅ Individual review before bulk operations (PRO)
- ✅ Confidence scoring so users know reliability of detection

---

## FREE vs PRO Breakdown

### ✅ FREE Features (Available to All Users)

**Core Cleanup Functionality:**
- 6 core scanners (posts, pages, meta, theme, widgets, menus)
- ✅ **WordPress Blog Post Scanner** - Scans all published posts for media usage (90% weight)
- Batch processing (handles 100k+ media)
- Confidence & risk scoring
- Safety rules engine
- Health score (0-100%)

**UI:**
- Media Health Card (in Media Statistics section)
- Media Audit Dashboard page
- Review Images page with filters
- Live progress tracking
- Rescan functionality

**Cleanup Actions:**
- View unused media list
- Export unused list (JSON) - FREE
- Import decisions (JSON) - FREE
- Individual trash (with safety checks)
- Manual confirmation before deletion

**Settings:**
- Scanners per batch (1-6)
- Scan retention (1-10)
- Cache results toggle
- Confirm before trash toggle

### 🔒 PRO Features (Phase 2 - Future)

**Focus: Enhanced Unused Media Cleaning**

**Advanced Scanners:**
- Gutenberg Block Scanner
- Elementor Scanner
- WooCommerce Scanner  
- BuddyPress/bbPress Scanner

**Bulk Cleaning Operations:**
- Bulk trash with one click (with safety checks)
- Batch export unused media as ZIP before cleanup
- Bulk import decisions

**Cleanup Automation:**
- Scheduled auto-scans (daily/weekly cron)
- Auto-scan after post/media import
- Email reports (cleanup summary, space saved)
- Auto-trash after X days (with safety confirmation)

---

## What Was Removed?

**❌ Removed Features (Not Needed for Cleanup Focus):**
1. **Multisite network dashboard** - Too complex, beyond cleanup scope
2. **Cross-site decision sharing** - Not core to unused media cleaning
3. **S3/CDN scanner integration** - Beyond scope of local cleanup
4. **Custom scanner API** - Overengineering for cleanup feature
5. **AI-Powered Detection** - Removed all AI features completely:
   - AI similarity detection
   - AI duplicate finder
   - Smart tagging suggestions
   - Automatic duplicate detection

**✅ Kept Focus on Cleanup:**
- Unused media identification (core goal)
- Safe cleanup with confidence scoring
- Bulk operations for efficiency (PRO)
- Automation for recurring cleanup tasks (PRO)
- Export/import for backup before cleanup

---

## Implementation Changes

### ✅ Added
1. **Blog Post Scanner** - Explicitly included as primary FREE scanner (90% weight)
2. **Clear FREE/PRO separation** - Every feature labeled with ✅/🔒
3. **Removed all AI** - Simple, reliable detection without AI dependencies
4. **UI Pattern clarified** - FREE users get full functional UI, PRO shows locked enhancements
5. **Cleanup focus** - Removed features that don't directly support cleaning unused media

### ❌ Removed from Original Plans
1. All AI-powered features
2. Multisite network features
3. S3/CDN integration
4. Cross-site decision sharing
5. Custom scanner API

### 🔄 Clarified
1. FREE version is fully functional for cleanup (not a demo)
2. PRO adds bulk operations and automation for faster cleanup
3. Backend separated: FREE in `includes/`, PRO in `PRO/includes/`
4. WordPress blog posts are primary content (Post Content Scanner 90% weight)

---

## Scanner System (FREE - 6 Scanners)

### 1. Post Content Scanner (Weight: 90%)
- **Scans:** WordPress blog posts (`post_type='post'`)
- **Detects:** `<img>` tags, galleries, featured images
- **Why Critical:** Blog posts are primary content

### 2. Page Content Scanner (Weight: 90%)
- **Scans:** Pages (`post_type='page'`)
- **Detects:** Same as posts

### 3. Post Meta Scanner (Weight: 85%)
- **Scans:** All postmeta for image IDs/URLs
- **Detects:** ACF fields, custom fields

### 4. Theme Options Scanner (Weight: 98% - Critical)
- **Scans:** Theme mods, site logo, header, background
- **Why Critical:** Site-wide assets, breaking = broken site

### 5. Widget Scanner (Weight: 80%)
- **Scans:** All active widgets
- **Detects:** Image widgets, text widgets with images

### 6. Menu Scanner (Weight: 70%)
- **Scans:** Nav menus
- **Detects:** Menu item images

---

## Database (FREE - 4 Tables)

1. `peiwm_media_scans` - Scan sessions
2. `peiwm_media_reports` - Per-attachment results
3. `peiwm_media_decisions` - User decisions (persist across rescans)
4. `peiwm_scan_logs` - Audit trail

---

## File Structure (FREE)

### New Files (~20 files)
```
includes/
├── class-media-audit-controller.php
├── class-media-audit-page.php
├── class-media-audit-review-page.php
├── class-media-batch-processor.php
├── class-media-scanner-registry.php
├── class-media-attachment-resolver.php
├── class-media-safety-engine.php
├── class-media-confidence-engine.php
├── class-media-risk-engine.php
├── class-media-recommendation-engine.php
├── class-media-health-score.php
├── class-media-user-decisions.php
├── class-media-scan-repository.php
├── class-media-log-repository.php
└── scanners/ (6 scanner files)

assets/
├── js/media-audit.js
└── css/media-audit.css
```

### Modified Files (FREE)
```
includes/
├── class-batch-settings.php (add audit settings)
├── class-admin-menu.php (add health card + menus)
└── class-ajax-handler.php (add 4 endpoints)
```

---

## AJAX Endpoints (FREE - 4)

1. `peiwm_start_audit` - Start scan
2. `peiwm_audit_progress` - Poll progress
3. `peiwm_get_audit_summary` - Get health card data
4. `peiwm_trash_unused_media` - Individual trash (with safety)

**PRO Endpoints (Future):**
- `peiwm_bulk_trash_unused` - Bulk trash
- `peiwm_export_unused_zip` - Export ZIP

---

## Development Timeline

**FREE Version:**
- Week 1-2: Core Infrastructure (30h)
- Week 2-3: Scanners (34h)
- Week 3-4: Engines (28h)
- Week 4-5: UI & Pages (30h)
- Week 5-6: AJAX & JS (30h)
- Week 6: NEW Features (12h)
- Week 7-8: Testing (48h)

**Total: 212 hours (~5-6 weeks)**

**PRO Version:** +3-4 weeks (Phase 2 - Cleanup enhancements only)

---

## Key Selling Points

### FREE Users Get:
✅ Full media cleanup system  
✅ Batch processing for any library size  
✅ WordPress blog post scanning (90% weight)  
✅ Health score dashboard  
✅ Individual trash with safety  
✅ Export/import unused lists  
✅ No AI dependencies  
✅ Safe cleanup with confidence scores  

### PRO Upgrades Get:
🔒 Advanced scanners (Elementor, WooCommerce, Gutenberg blocks)  
🔒 Bulk cleaning operations (one-click trash, ZIP export for backup)  
🔒 Cleanup automation (scheduled scans, auto-trash with safety)  
🔒 Email reports (cleanup summary, space saved)

---

## Security (FREE)

✅ Nonce verification on all AJAX  
✅ Capability checks (`manage_options`, `upload_files`)  
✅ Input sanitization (`absint`, `sanitize_text_field`)  
✅ SQL injection prevention (`$wpdb->prepare()`)  
✅ Output escaping (`esc_html`, `esc_attr`, `esc_url`)  
✅ Trash not delete (reversible)  

---

## Next Steps

1. **Review** `implementation-plan-media-health.md` for full details
2. **Approve** FREE/PRO split and cleanup focus
3. **Start development** with Week 1-2 (Core Infrastructure)
4. **Phase 2 planning** for PRO cleanup enhancements after FREE is complete

---

## Questions Answered

**Q: Is blog post scanning included?**  
✅ Yes! Post Content Scanner is the primary scanner (90% weight)

**Q: Is this FREE or PRO?**  
✅ FREE feature with PRO cleanup enhancements planned for Phase 2

**Q: Do we need AI?**  
❌ No, removed all AI features. Simple, reliable detection only.

**Q: What about Multisite/S3/CDN?**  
❌ Removed - not needed for unused media cleanup focus

**Q: How does FREE/PRO UI work?**  
✅ FREE: Full functional cleanup UI + backend  
✅ PRO: Bulk operations + automation with 🔒 locked badges in FREE

**Q: What's the main goal?**  
✅ **Unused media cleaning** - identify and safely remove unused files to free space

---



## Feature Comparison Matrix

| Feature | FREE Version | PRO Version | Development Time |
|---------|--------------|-------------|------------------|
| **Media Health & Audit** | ✅ Full functional system<br>- 6 scanners<br>- Health dashboard<br>- Individual cleanup<br>- Export/import lists | 🔒 Enhanced cleanup<br>- 4 advanced scanners<br>- Bulk operations<br>- Automation<br>- Email reports | 212h FREE<br>+80h PRO |
| **Media Title & ALT Editor** | ✅ Demo UI (locked)<br>- Visible but disabled<br>- Shows capabilities<br>- Upgrade prompt | 🔒 Full editor<br>- Inline editing<br>- CSV import/export<br>- Batch loading<br>- Change tracking | 48h PRO |
| **Update Missing Media** | ✅ Demo button (locked)<br>- Fix path (works)<br>- Clean missing (works)<br>- Update (locked) | 🔒 Full replacement<br>- Media library select<br>- Direct upload<br>- Bulk update<br>- Preview + reselect | Included in plan |

---

## Combined Value Proposition

### For FREE Users:
1. **Media Health** - Full unused media cleanup system with confidence scoring
2. **Media Editor** - See what's possible, understand the value
3. **Missing Media** - Identify and clean broken references

### For PRO Users:
1. **Media Health** - Advanced scanners + bulk cleanup + automation (save hours on large sites)
2. **Media Editor** - Bulk edit thousands of media titles/ALT in minutes with CSV
3. **Missing Media** - Replace missing files easily with drag-drop or library selection

### Clear Upgrade Path:
- FREE users get substantial value (Media Health is fully functional)
- PRO users get time-saving bulk operations and automation
- Each feature has clear FREE→PRO benefits
- No artificial limitations, genuine added value

---

## Development Priority

**Phase 1: Media Health (FREE)** - 5-6 weeks  
Focus on unused media cleanup as the core value proposition

**Phase 2: PRO Enhancements** - 3-4 weeks  
Add bulk operations and automation to Media Health

**Phase 3: Media Editor (PRO)** - 1-1.5 weeks  
Bulk title/ALT editing feature

**Phase 4: Missing Media Updates (PRO)** - Included  
Complete the media management suite

**Total Timeline:** ~10-12 weeks for all features
