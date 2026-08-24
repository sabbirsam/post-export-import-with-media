# Media Health & Audit Feature - Implementation Plan (FREE + PRO Feature)

## Executive Summary

Comprehensive media health and audit system inspired by Janitorix Media Audit, adapted for PEIWM plugin architecture. **This is a FREE feature available to all users** with PRO-exclusive enhancements planned for future phases.

**Key Points:**
- ✅ **FREE Feature**: Core functionality available to all users
- ✅ **Batch Processing**: Handles 100k+ media libraries
- ✅ **Blog Post Scanner**: Scans WordPress blog posts for media usage
- ✅ **Export/Import Integration**: Unique features leveraging our core strength
- ✅ **No AI**: Simple, reliable detection without AI dependencies
- ✅ **Safe by Design**: Multiple safety layers prevent accidents

---

## FREE vs PRO Feature Breakdown

### FREE Features (Phase 1 - Available to All Users)

**Core Scanning:**
- ✅ 6 core scanners (posts, pages, meta, theme, widgets, menus)
- ✅ Batch processing for any library size
- ✅ Confidence & risk scoring
- ✅ Safety rules engine
- ✅ Health score calculation (0-100%)

**UI Components:**
- ✅ Media Health Card (in Media Statistics)
- ✅ Media Audit Dashboard page
- ✅ Review Images page with filters
- ✅ Batch progress tracking
- ✅ Rescan functionality

**Actions:**
- ✅ View unused media list
- ✅ Export unused list (JSON)
- ✅ Manual trash (individual items)
- ✅ Safety checks before deletion

**Settings:**
- ✅ Scanners per batch
- ✅ Scan retention (how many to keep)
- ✅ Cache results toggle
- ✅ Confirm before trash toggle

### PRO Features (Phase 2 - Future)

**Focus: Enhanced Unused Media Cleaning**

**Advanced Scanners:**
- 🔒 Gutenberg Block Scanner (reusable blocks, custom blocks)
- 🔒 Elementor Scanner (page builder content)
- 🔒 WooCommerce Scanner (product images, galleries)
- 🔒 BuddyPress/bbPress Scanner (forums, profiles)

**Bulk Cleaning Operations:**
- 🔒 Bulk trash with one click (with safety checks)
- 🔒 Batch export unused media as ZIP before cleanup
- 🔒 Bulk import decisions

**Automation for Cleanup:**
- 🔒 Scheduled auto-scans (daily/weekly cron)
- 🔒 Auto-scan after post/media import
- 🔒 Email reports (cleanup summary, warnings)
- 🔒 Auto-trash after X days (with safety confirmation)

---

## Architecture Overview

### Three-Phase Processing Flow

```
SCAN → ANALYZE → JUDGE
├─ Scanner: Find references across content
├─ Analysis: Group by attachment, determine status  
└─ Judgement: Risk + Confidence + Recommendation
```

### FREE Version UI Flow

```
┌─────────────────────────────────────────────────┐
│     Media Statistics (Existing - Enhanced)      │
│  ┌──────────────────────────────────────────┐  │
│  │ NEW: Media Health Score Card (FREE)      │  │
│  │  • Health score: 85%                     │  │
│  │  • Used / Possibly Used / Unused stats   │  │
│  │  • "Audit Media" button → Dashboard      │  │
│  └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────────┐
│     Media Audit Dashboard (FREE - New Page)     │
│  ┌──────────────────────────────────────────┐  │
│  │ First Visit: "Start Scan" button         │  │
│  ├──────────────────────────────────────────┤  │
│  │ Scanning: Live progress + logs           │  │
│  ├──────────────────────────────────────────┤  │
│  │ Complete: Health Score Dashboard         │  │
│  │  • Summary cards (9 metrics)             │  │
│  │  • "Review Images" button                │  │
│  │  • "Rescan" button                       │  │
│  └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────────┐
│     Review Images Page (FREE - New Page)        │
│  • Filter: Status, Confidence, Risk level       │
│  • Table: Thumbnail, File, Status, Actions      │
│  • Individual "Move to Trash" (with safety)     │
│  • Export unused list (JSON) - FREE             │
│  • Import decisions (JSON) - FREE               │
│  ┌──────────────────────────────────────────┐  │
│  │ 🔒 PRO: Bulk trash button (locked)       │  │
│  │ 🔒 PRO: Export unused ZIP (locked)       │  │
│  └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
```

---

## Database Schema (FREE - 4 Tables)

### Table: `{prefix}_peiwm_media_scans`

Stores scan sessions.

```sql
CREATE TABLE {prefix}_peiwm_media_scans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    fingerprint VARCHAR(64) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    duration_ms INT UNSIGNED NULL,
    images_total INT UNSIGNED DEFAULT 0,
    images_used INT UNSIGNED DEFAULT 0,
    images_possibly_used INT UNSIGNED DEFAULT 0,
    images_unused INT UNSIGNED DEFAULT 0,
    confidence INT UNSIGNED DEFAULT 0,
    coverage INT UNSIGNED DEFAULT 0,
    health_score INT UNSIGNED DEFAULT 0,
    resume_state LONGTEXT NULL,
    scanner_states LONGTEXT NULL,
    broken_references LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY status (status),
    KEY fingerprint (fingerprint),
    KEY completed_at (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `{prefix}_peiwm_media_reports`

Per-attachment analysis results.

```sql
CREATE TABLE {prefix}_peiwm_media_reports (
    scan_id BIGINT UNSIGNED NOT NULL,
    attachment_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    url VARCHAR(512) NOT NULL,
    status VARCHAR(20) NOT NULL,
    confidence INT UNSIGNED DEFAULT 0,
    risk_level VARCHAR(20) DEFAULT 'Low',
    recommendation VARCHAR(20) DEFAULT 'keep',
    evidence_count INT UNSIGNED DEFAULT 0,
    evidence LONGTEXT NULL,
    filesize BIGINT UNSIGNED DEFAULT 0,
    user_decision VARCHAR(20) NULL,
    PRIMARY KEY (scan_id, attachment_id),
    KEY attachment_id (attachment_id),
    KEY status (status),
    KEY confidence (confidence),
    KEY risk_level (risk_level),
    KEY recommendation (recommendation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `{prefix}_peiwm_media_decisions`

User decisions persist across rescans.

```sql
CREATE TABLE {prefix}_peiwm_media_decisions (
    attachment_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(20) NOT NULL,
    decided_at DATETIME NOT NULL,
    decided_by BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (attachment_id),
    KEY decided_at (decided_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `{prefix}_peiwm_scan_logs`

Audit trail for debugging.

```sql
CREATE TABLE {prefix}_peiwm_scan_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scan_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(10) NOT NULL,
    scanner VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY scan_id (scan_id),
    KEY level (level),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Scanner System (FREE - 6 Core Scanners)

### Scanner Interface

```php
interface PEIWM_Scanner {
    public function scan(PEIWM_Attachment_Resolver $resolver): PEIWM_Scanner_Result;
    public function get_id(): string;
    public function get_weight(): int; // 0-100, higher = more reliable
    public function is_applicable(): bool;
    public function get_version(): string;
}
```

### FREE Scanners (Phase 1)

#### 1. Post Content Scanner (Weight: 90%)
**File:** `includes/scanners/class-scanner-post-content.php`

**Scans:**
- All published WordPress blog posts (`post_type='post'`)
- Post content (`post_content`)
- Featured images (`_thumbnail_id`)

**Detects:**
- `<img src="...">` tags
- `[gallery ids="..."]` shortcodes
- Inline image URLs
- Attachment IDs in content

**Why Important:** Blog posts are primary content, high confidence.

```php
public function scan(PEIWM_Attachment_Resolver $resolver): PEIWM_Scanner_Result {
    $posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    $references = [];
    
    foreach ($posts as $post_id) {
        $post = get_post($post_id);
        
        // Scan post content
        $refs = $resolver->find_in_html($post->post_content);
        foreach ($refs as $ref) {
            $ref->location_type = 'post';
            $ref->location_id = $post_id;
            $ref->location_label = "Post #{$post_id}: {$post->post_title}";
            $ref->field = 'post_content';
            $ref->strength_cap = 90;
            $references[] = $ref;
        }
        
        // Scan featured image
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $ref = new PEIWM_Reference();
            $ref->attachment_id = $thumb_id;
            $ref->scanner_id = $this->get_id();
            $ref->location_type = 'post';
            $ref->location_id = $post_id;
            $ref->location_label = "Post #{$post_id}: {$post->post_title}";
            $ref->field = '_thumbnail_id';
            $ref->detection_method = 'meta_value';
            $ref->strength_cap = 95;
            $references[] = $ref;
        }
    }
    
    return PEIWM_Scanner_Result::success(
        $this->get_id(),
        $references,
        count($posts)
    );
}
```

#### 2. Page Content Scanner (Weight: 90%)
**File:** `includes/scanners/class-scanner-page-content.php`

**Scans:**
- All published pages (`post_type='page'`)
- Page content and featured images

**Implementation:** Nearly identical to Post Content Scanner, just uses `post_type='page'`

#### 3. Post Meta Scanner (Weight: 85%)
**File:** `includes/scanners/class-scanner-post-meta.php`

**Scans:**
- All postmeta for attachment IDs and URLs
- ACF image fields
- Custom fields with image data

**Detects:**
- `meta_value` containing attachment IDs
- `meta_value` containing image URLs
- Serialized data with image references

```php
public function scan(PEIWM_Attachment_Resolver $resolver): PEIWM_Scanner_Result {
    global $wpdb;
    
    // Get all postmeta that might contain images
    $results = $wpdb->get_results("
        SELECT post_id, meta_key, meta_value 
        FROM {$wpdb->postmeta}
        WHERE meta_value REGEXP '[0-9]{3,}' 
           OR meta_value LIKE '%uploads%'
           OR meta_value LIKE '%jpg%'
           OR meta_value LIKE '%png%'
           OR meta_value LIKE '%gif%'
           OR meta_value LIKE '%svg%'
    ");
    
    $references = [];
    
    foreach ($results as $row) {
        $refs = $resolver->extract_from_meta($row->meta_value);
        
        foreach ($refs as $ref) {
            $post = get_post($row->post_id);
            $ref->location_type = get_post_type($row->post_id);
            $ref->location_id = $row->post_id;
            $ref->location_label = "Post #{$row->post_id}: {$post->post_title}";
            $ref->field = $row->meta_key;
            $ref->strength_cap = 85;
            $references[] = $ref;
        }
    }
    
    return PEIWM_Scanner_Result::success(
        $this->get_id(),
        $references,
        count($results)
    );
}
```

#### 4. Theme Options Scanner (Weight: 98% - Critical)
**File:** `includes/scanners/class-scanner-theme-options.php`

**Scans:**
- `get_theme_mod()` values
- Site logo (`custom_logo`)
- Custom header image
- Custom background image
- Theme settings arrays

**Why Critical:** Theme assets are site-wide, deleting breaks entire site.

```php
public function scan(PEIWM_Attachment_Resolver $resolver): PEIWM_Scanner_Result {
    $references = [];
    
    // Site logo
    $logo_id = get_theme_mod('custom_logo');
    if ($logo_id) {
        $ref = new PEIWM_Reference();
        $ref->attachment_id = $logo_id;
        $ref->scanner_id = $this->get_id();
        $ref->location_type = 'theme';
        $ref->location_id = 0;
        $ref->location_label = 'Site Logo';
        $ref->field = 'custom_logo';
        $ref->detection_method = 'theme_mod';
        $ref->strength_cap = 98;
        $references[] = $ref;
    }
    
    // Custom header
    $header = get_custom_header();
    if ($header && $header->attachment_id) {
        $ref = new PEIWM_Reference();
        $ref->attachment_id = $header->attachment_id;
        $ref->scanner_id = $this->get_id();
        $ref->location_type = 'theme';
        $ref->location_id = 0;
        $ref->location_label = 'Custom Header';
        $ref->field = 'header_image';
        $ref->detection_method = 'theme_header';
        $ref->strength_cap = 98;
        $references[] = $ref;
    }
    
    // Custom background
    $bg_id = get_theme_mod('background_image_id');
    if ($bg_id) {
        $ref = new PEIWM_Reference();
        $ref->attachment_id = $bg_id;
        $ref->scanner_id = $this->get_id();
        $ref->location_type = 'theme';
        $ref->location_id = 0;
        $ref->location_label = 'Custom Background';
        $ref->field = 'background_image';
        $ref->detection_method = 'theme_mod';
        $ref->strength_cap = 98;
        $references[] = $ref;
    }
    
    // Scan all theme_mods for additional images
    $mods = get_theme_mods();
    if ($mods && is_array($mods)) {
        foreach ($mods as $key => $value) {
            $refs = $resolver->extract_from_mixed($value);
            foreach ($refs as $ref) {
                $ref->location_type = 'theme';
                $ref->location_id = 0;
                $ref->location_label = "Theme Mod: {$key}";
                $ref->field = $key;
                $ref->strength_cap = 95;
                $references[] = $ref;
            }
        }
    }
    
    return PEIWM_Scanner_Result::success(
        $this->get_id(),
        $references,
        1 + count($mods)
    );
}
```

#### 5. Widget Scanner (Weight: 80%)
**File:** `includes/scanners/class-scanner-widgets.php`

**Scans:**
- All active widgets in all sidebars
- Image widgets
- Text widgets with images
- Custom widgets with image data

```php
public function scan(PEIWM_Attachment_Resolver $resolver): PEIWM_Scanner_Result {
    global $wp_registered_sidebars;
    
    $references = [];
    $examined = 0;
    
    foreach ($wp_registered_sidebars as $sidebar_id => $sidebar) {
        $widgets = wp_get_sidebars_widgets();
        
        if (!isset($widgets[$sidebar_id])) {
            continue;
        }
        
        foreach ($widgets[$sidebar_id] as $widget_id) {
            $widget_data = get_option('widget_' . $widget_id);
            
            if (!$widget_data) {
                continue;
            }
            
            $refs = $resolver->extract_from_mixed($widget_data);
            
            foreach ($refs as $ref) {
                $ref->location_type = 'widget';
                $ref->location_id = 0;
                $ref->location_label = "Widget: {$widget_id} in {$sidebar['name']}";
                $ref->field = 'widget_data';
                $ref->strength_cap = 80;
                $references[] = $ref;
            }
            
            $examined++;
        }
    }
    
    return PEIWM_Scanner_Result::success(
        $this->get_id(),
        $references,
        $examined
    );
}
```

#### 6. Menu Scanner (Weight: 70%)
**File:** `includes/scanners/class-scanner-menus.php`

**Scans:**
- All nav menus
- Menu item custom fields
- Menu item meta

```php
public function scan(PEIWM_Attachment_Resolver $resolver): PEIWM_Scanner_Result {
    $menus = wp_get_nav_menus();
    $references = [];
    $examined = 0;
    
    foreach ($menus as $menu) {
        $items = wp_get_nav_menu_items($menu->term_id);
        
        if (!$items) {
            continue;
        }
        
        foreach ($items as $item) {
            // Scan menu item meta
            $meta = get_post_meta($item->ID);
            
            foreach ($meta as $key => $values) {
                foreach ($values as $value) {
                    $refs = $resolver->extract_from_mixed($value);
                    
                    foreach ($refs as $ref) {
                        $ref->location_type = 'menu';
                        $ref->location_id = $menu->term_id;
                        $ref->location_label = "Menu: {$menu->name} → {$item->title}";
                        $ref->field = $key;
                        $ref->strength_cap = 70;
                        $references[] = $ref;
                    }
                }
            }
            
            $examined++;
        }
    }
    
    return PEIWM_Scanner_Result::success(
        $this->get_id(),
        $references,
        $examined
    );
}
```

### Attachment Resolver Helper

**File:** `includes/class-media-attachment-resolver.php`

Provides methods for extracting attachment IDs and URLs from various formats:

```php
class PEIWM_Attachment_Resolver {
    
    // Find images in HTML content
    public function find_in_html(string $html): array {
        $references = [];
        
        // <img src="..." />
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        foreach ($matches[1] as $url) {
            $attachment_id = attachment_url_to_postid($url);
            if ($attachment_id) {
                $ref = new PEIWM_Reference();
                $ref->attachment_id = $attachment_id;
                $ref->detection_method = 'img_tag';
                $ref->raw_match = $url;
                $references[] = $ref;
            }
        }
        
        // [gallery ids="1,2,3"]
        preg_match_all('/\[gallery[^\]]*ids=["\']([^"\']+)["\'][^\]]*\]/i', $html, $matches);
        foreach ($matches[1] as $ids_string) {
            $ids = array_map('intval', explode(',', $ids_string));
            foreach ($ids as $id) {
                if ($id > 0) {
                    $ref = new PEIWM_Reference();
                    $ref->attachment_id = $id;
                    $ref->detection_method = 'gallery_shortcode';
                    $ref->raw_match = $ids_string;
                    $references[] = $ref;
                }
            }
        }
        
        return $references;
    }
    
    // Extract from postmeta value
    public function extract_from_meta($value): array {
        $references = [];
        
        // Check if numeric (likely attachment ID)
        if (is_numeric($value) && $value > 0) {
            if (get_post_type($value) === 'attachment') {
                $ref = new PEIWM_Reference();
                $ref->attachment_id = intval($value);
                $ref->detection_method = 'meta_numeric';
                $ref->raw_match = $value;
                $references[] = $ref;
            }
        }
        
        // Check if URL
        if (is_string($value) && strpos($value, 'uploads') !== false) {
            $attachment_id = attachment_url_to_postid($value);
            if ($attachment_id) {
                $ref = new PEIWM_Reference();
                $ref->attachment_id = $attachment_id;
                $ref->detection_method = 'meta_url';
                $ref->raw_match = $value;
                $references[] = $ref;
            }
        }
        
        // Check if serialized/JSON
        if (is_string($value)) {
            $unserialized = @unserialize($value);
            if ($unserialized !== false) {
                return $this->extract_from_mixed($unserialized);
            }
            
            $json = @json_decode($value, true);
            if ($json !== null) {
                return $this->extract_from_mixed($json);
            }
        }
        
        return $references;
    }
    
    // Extract from mixed data (arrays, objects)
    public function extract_from_mixed($data): array {
        $references = [];
        
        if (is_array($data)) {
            foreach ($data as $item) {
                $references = array_merge($references, $this->extract_from_mixed($item));
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $prop) {
                $references = array_merge($references, $this->extract_from_mixed($prop));
            }
        } elseif (is_numeric($data) && $data > 0) {
            if (get_post_type($data) === 'attachment') {
                $ref = new PEIWM_Reference();
                $ref->attachment_id = intval($data);
                $ref->detection_method = 'mixed_numeric';
                $ref->raw_match = $data;
                $references[] = $ref;
            }
        } elseif (is_string($data) && strpos($data, 'uploads') !== false) {
            $attachment_id = attachment_url_to_postid($data);
            if ($attachment_id) {
                $ref = new PEIWM_Reference();
                $ref->attachment_id = $attachment_id;
                $ref->detection_method = 'mixed_url';
                $ref->raw_match = $data;
                $references[] = $ref;
            }
        }
        
        return $references;
    }
}
```

---

## Confidence & Risk Engines (FREE)

### Confidence Algorithm

**File:** `includes/class-media-confidence-engine.php`

```php
class PEIWM_Confidence_Engine {
    
    const COVERAGE_FLOOR = 60; // Below 60% coverage = no deletion recommended
    const USED_EVIDENCE = 70;  // Evidence strength >= 70 = "Used"
    
    /**
     * Calculate library-wide confidence score
     */
    public function calculate(array $reports, array $scanner_results, array $checks): int {
        // 1. Coverage: What % of possible checks were completed
        $total_checks = count($checks);
        $completed_checks = count(array_filter($scanner_results, function($r) {
            return $r->state === 'success';
        }));
        $coverage = $total_checks > 0 ? ($completed_checks / $total_checks) * 100 : 0;
        
        // 2. Scanner reliability: Weight completed scanners
        $reliability = $this->calculate_reliability($scanner_results);
        
        // 3. Library-wide confidence
        $confidence = min(99, round(($coverage * 0.7) + ($reliability * 0.3)));
        
        return max(0, min(99, $confidence)); // Never 100%
    }
    
    private function calculate_reliability(array $scanner_results): int {
        $total_weight = 0;
        $success_weight = 0;
        
        foreach ($scanner_results as $result) {
            $weight = $result->scanner_weight ?? 50;
            $total_weight += $weight;
            
            if ($result->state === 'success') {
                $success_weight += $weight;
            }
        }
        
        return $total_weight > 0 ? round(($success_weight / $total_weight) * 100) : 0;
    }
}
```

### Risk Level Algorithm

**File:** `includes/class-media-risk-engine.php`

```php
class PEIWM_Risk_Engine {
    
    public function calculate(array $reports, array $failed_scanners): void {
        foreach ($reports as $report) {
            $risk_score = 0;
            
            // Factor 1: Recently stopped being used
            if ($report->stopped_being_used) {
                $risk_score += 30;
            }
            
            // Factor 2: Upload age (newer = higher risk)
            $age_days = $this->get_upload_age_days($report->attachment_id);
            if ($age_days < 7) {
                $risk_score += 40;
            } elseif ($age_days < 30) {
                $risk_score += 20;
            } elseif ($age_days < 90) {
                $risk_score += 10;
            }
            
            // Factor 3: File size (larger = higher risk)
            if ($report->filesize > 5 * 1024 * 1024) { // > 5MB
                $risk_score += 15;
            }
            
            // Factor 4: Failed scanners that would cover this
            foreach ($failed_scanners as $scanner_id) {
                if ($this->scanner_would_cover($scanner_id, $report)) {
                    $risk_score += 20;
                    break;
                }
            }
            
            // Convert score to level
            if ($risk_score >= 80) {
                $report->risk_level = 'Critical';
            } elseif ($risk_score >= 60) {
                $report->risk_level = 'High';
            } elseif ($risk_score >= 40) {
                $report->risk_level = 'Medium';
            } elseif ($risk_score >= 20) {
                $report->risk_level = 'Low';
            } else {
                $report->risk_level = 'Very Low';
            }
        }
    }
}
```

### Recommendation Engine

**File:** `includes/class-media-recommendation-engine.php`

```php
class PEIWM_Recommendation_Engine {
    
    public function decide(array $reports): void {
        foreach ($reports as $report) {
            // User decision overrides everything
            if ($report->user_decision) {
                $report->recommendation = $report->user_decision;
                continue;
            }
            
            // Status-based baseline
            if ($report->status === 'used') {
                $report->recommendation = 'keep';
            } elseif ($report->status === 'possibly_used') {
                $report->recommendation = 'review';
            } else { // unused
                // Risk and confidence decide
                if ($report->risk_level === 'Critical' || $report->risk_level === 'High') {
                    $report->recommendation = 'review';
                } elseif ($report->confidence < 70) {
                    $report->recommendation = 'review';
                } elseif ($report->coverage < PEIWM_Confidence_Engine::COVERAGE_FLOOR) {
                    $report->recommendation = 'review';
                } else {
                    $report->recommendation = 'move_to_trash';
                }
            }
        }
    }
}
```

### Health Score Calculation

**File:** `includes/class-media-health-score.php`

```php
class PEIWM_Health_Score_Engine {
    
    public function calculate(object $scan): int {
        $penalties = [];
        
        // 1. Unused media penalty
        if ($scan->images_total > 0) {
            $unused_ratio = $scan->images_unused / $scan->images_total;
            if ($unused_ratio > 0.5) {
                $penalties[] = 30; // > 50% unused
            } elseif ($unused_ratio > 0.3) {
                $penalties[] = 20; // > 30% unused
            } elseif ($unused_ratio > 0.1) {
                $penalties[] = 10; // > 10% unused
            }
        }
        
        // 2. Low confidence penalty
        if ($scan->confidence < 80) {
            $penalties[] = 15;
        } elseif ($scan->confidence < 60) {
            $penalties[] = 25;
        }
        
        // 3. Low coverage penalty
        if ($scan->coverage < 70) {
            $penalties[] = 20;
        } elseif ($scan->coverage < 50) {
            $penalties[] = 35;
        }
        
        // 4. Failed scanners penalty
        $failed_count = $this->count_failed_scanners($scan);
        if ($failed_count > 0) {
            $penalties[] = min(25, $failed_count * 10);
        }
        
        $total_penalty = array_sum($penalties);
        $health_score = max(0, 100 - $total_penalty);
        
        return $health_score;
    }
    
    public function get_label(int $score): string {
        if ($score >= 90) return __('Excellent', 'post-export-import-with-media');
        if ($score >= 75) return __('Good', 'post-export-import-with-media');
        if ($score >= 60) return __('Fair', 'post-export-import-with-media');
        if ($score >= 40) return __('Needs Attention', 'post-export-import-with-media');
        return __('Critical', 'post-export-import-with-media');
    }
}
```

---

## Safety Engine (FREE)

**File:** `includes/class-media-safety-engine.php`

### Safety Rules

```php
class PEIWM_Safety_Engine {
    
    public function evaluate(string $action, int $attachment_id): PEIWM_Safety_Verdict {
        $rules = [
            new PEIWM_Safety_Rule_Featured_Image(),
            new PEIWM_Safety_Rule_Site_Icon(),
            new PEIWM_Safety_Rule_Custom_Header(),
            new PEIWM_Safety_Rule_Custom_Background(),
            new PEIWM_Safety_Rule_Recent_Upload(),
            new PEIWM_Safety_Rule_High_Risk(),
            new PEIWM_Safety_Rule_User_Decision(),
        ];
        
        foreach ($rules as $rule) {
            if (!$rule->applies_to($action, $attachment_id)) {
                continue;
            }
            
            $result = $rule->evaluate($attachment_id);
            
            if (!$result->allowed()) {
                return $result;
            }
        }
        
        return PEIWM_Safety_Verdict::allow();
    }
}
```

### Example Safety Rule

```php
class PEIWM_Safety_Rule_Featured_Image implements PEIWM_Safety_Rule {
    
    public function applies_to(string $action, int $attachment_id): bool {
        return $action === 'trash';
    }
    
    public function evaluate(int $attachment_id): PEIWM_Safety_Verdict {
        global $wpdb;
        
        // Check if this image is a featured image for any post
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
            $attachment_id
        ));
        
        if ($count > 0) {
            return PEIWM_Safety_Verdict::deny(
                __('This is a featured image', 'post-export-import-with-media')
            );
        }
        
        return PEIWM_Safety_Verdict::allow();
    }
    
    public function get_id(): string {
        return 'featured_image';
    }
}
```

---

## Batch Settings Integration (FREE)

**Location:** `includes/class-batch-settings.php`

### Add to Default Settings

```php
'audit_batch_size' => 2,              // Scanners per batch
'audit_cache_results' => true,        // Cache scan results
'audit_scan_retention' => 3,          // Keep last N scans
'audit_confirm_trash' => true,        // Confirm before trashing
```

### Add to Sanitize Method

```php
$sanitized['audit_batch_size'] = isset($input['audit_batch_size']) ? absint($input['audit_batch_size']) : 2;
$sanitized['audit_cache_results'] = isset($input['audit_cache_results']) ? (bool)$input['audit_cache_results'] : true;
$sanitized['audit_scan_retention'] = isset($input['audit_scan_retention']) ? absint($input['audit_scan_retention']) : 3;
$sanitized['audit_confirm_trash'] = isset($input['audit_confirm_trash']) ? (bool)$input['audit_confirm_trash'] : true;

// Validate ranges
if ($sanitized['audit_batch_size'] < 1) $sanitized['audit_batch_size'] = 1;
if ($sanitized['audit_batch_size'] > 6) $sanitized['audit_batch_size'] = 6;

if ($sanitized['audit_scan_retention'] < 1) $sanitized['audit_scan_retention'] = 1;
if ($sanitized['audit_scan_retention'] > 10) $sanitized['audit_scan_retention'] = 10;
```

### Add UI Section

Add after Media ZIP Size Limit:

```php
</tbody>
</table>

<!-- Media Audit Configuration (FREE) -->
<h2 class="peiwm-section-title">
    <?php echo esc_html__( 'Media Audit Configuration', 'post-export-import-with-media' ); ?>
    <span class="peiwm-badge peiwm-badge-free" style="background: #10b981; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">FREE</span>
</h2>

<table class="form-table peiwm-settings-table">
<tbody>
    <!-- Scanners per Batch -->
    <tr>
        <th scope="row">
            <label for="audit_batch_size">
                <?php echo esc_html__( 'Scanners per Batch', 'post-export-import-with-media' ); ?>
            </label>
        </th>
        <td>
            <input 
                type="number" 
                id="audit_batch_size" 
                name="peiwm_batch_settings[audit_batch_size]" 
                value="<?php echo esc_attr( $settings['audit_batch_size'] ); ?>" 
                min="1" 
                max="6" 
                step="1"
                class="small-text"
            />
            <p class="description">
                <?php echo esc_html__( 'How many scanners to run per request. Default: 2 (Range: 1-6)', 'post-export-import-with-media' ); ?>
                <br><?php echo esc_html__( 'Higher = faster scans but more server load', 'post-export-import-with-media' ); ?>
            </p>
        </td>
    </tr>

    <!-- Scan Records to Keep -->
    <tr>
        <th scope="row">
            <label for="audit_scan_retention">
                <?php echo esc_html__( 'Scan Records to Keep', 'post-export-import-with-media' ); ?>
            </label>
        </th>
        <td>
            <input 
                type="number" 
                id="audit_scan_retention" 
                name="peiwm_batch_settings[audit_scan_retention]" 
                value="<?php echo esc_attr( $settings['audit_scan_retention'] ); ?>" 
                min="1" 
                max="10" 
                step="1"
                class="small-text"
            />
            <p class="description">
                <?php echo esc_html__( 'How many historical scans to keep. Default: 3 (Range: 1-10)', 'post-export-import-with-media' ); ?>
            </p>
        </td>
    </tr>

    <!-- Cache Scan Results -->
    <tr>
        <th scope="row">
            <label for="audit_cache_results">
                <?php echo esc_html__( 'Cache Scan Results', 'post-export-import-with-media' ); ?>
            </label>
        </th>
        <td>
            <label class="peiwm-toggle-switch">
                <input 
                    type="checkbox" 
                    id="audit_cache_results" 
                    name="peiwm_batch_settings[audit_cache_results]" 
                    value="1" 
                    <?php checked( $settings['audit_cache_results'], true ); ?>
                />
                <span class="peiwm-toggle-slider"></span>
            </label>
            <p class="description">
                <?php echo esc_html__( 'Reuse previous scan if site hasn\'t changed (fingerprint-based)', 'post-export-import-with-media' ); ?>
            </p>
        </td>
    </tr>

    <!-- Confirm Before Trashing -->
    <tr>
        <th scope="row">
            <label for="audit_confirm_trash">
                <?php echo esc_html__( 'Confirm Before Trashing', 'post-export-import-with-media' ); ?>
            </label>
        </th>
        <td>
            <label class="peiwm-toggle-switch">
                <input 
                    type="checkbox" 
                    id="audit_confirm_trash" 
                    name="peiwm_batch_settings[audit_confirm_trash]" 
                    value="1" 
                    <?php checked( $settings['audit_confirm_trash'], true ); ?>
                />
                <span class="peiwm-toggle-slider"></span>
            </label>
            <p class="description">
                <?php echo esc_html__( 'Show confirmation dialog before moving media to trash', 'post-export-import-with-media' ); ?>
            </p>
        </td>
    </tr>
</tbody>
</table>
```

---

## File Structure Summary

### NEW Files to Create (FREE - ~20 files)

```
includes/
├── class-media-audit-controller.php           (Main controller)
├── class-media-audit-page.php                 (Dashboard page)
├── class-media-audit-review-page.php          (Review images page)
├── class-media-batch-processor.php            (Batch processor)
├── class-media-scanner-registry.php           (Scanner registry)
├── class-media-attachment-resolver.php        (Attachment resolver)
├── class-media-safety-engine.php              (Safety rules)
├── class-media-confidence-engine.php          (Confidence calculation)
├── class-media-risk-engine.php                (Risk calculation)
├── class-media-recommendation-engine.php      (Recommendation logic)
├── class-media-health-score.php               (Health score)
├── class-media-user-decisions.php             (User decision storage)
├── class-media-scan-repository.php            (Database access)
├── class-media-log-repository.php             (Log database access)
└── scanners/
    ├── class-scanner-interface.php
    ├── class-scanner-post-content.php
    ├── class-scanner-page-content.php
    ├── class-scanner-post-meta.php
    ├── class-scanner-theme-options.php
    ├── class-scanner-widgets.php
    └── class-scanner-menus.php

assets/
├── js/
│   └── media-audit.js                         (Frontend JS)
└── css/
    └── media-audit.css                        (Styles)
```

### MODIFIED Files (FREE)

```
includes/
├── class-batch-settings.php                   (Add audit settings)
├── class-admin-menu.php                       (Add health card + menu items)
└── class-ajax-handler.php                     (Add AJAX endpoints)

assets/js/admin.js                             (Integrate health card loading)
```

---

## AJAX Endpoints (FREE - 4 Endpoints)

### 1. Start Scan

**Endpoint:** `peiwm_start_audit`

```php
public function ajax_start_audit() {
    check_ajax_referer('peiwm_secure_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $controller = PEIWM_Media_Audit_Controller::get_instance();
    $force = isset($_POST['force']) && $_POST['force'] === '1';
    
    $scan_id = $controller->begin_scan($force);
    
    wp_send_json_success([
        'scan_id' => $scan_id,
        'message' => __('Scan started', 'post-export-import-with-media')
    ]);
}
```

### 2. Poll Progress

**Endpoint:** `peiwm_audit_progress`

```php
public function ajax_audit_progress() {
    check_ajax_referer('peiwm_secure_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $scan_id = isset($_POST['scan_id']) ? absint($_POST['scan_id']) : 0;
    
    if (!$scan_id) {
        wp_send_json_error(['message' => 'Invalid scan ID']);
    }
    
    $controller = PEIWM_Media_Audit_Controller::get_instance();
    
    // Run one batch
    $batch = $controller->tick($scan_id);
    
    wp_send_json_success([
        'done' => $batch['done'],
        'ran' => $batch['ran'],
        'remaining' => $batch['remaining'],
        'progress' => $batch['progress'], // 0-100
        'current_scanner' => $batch['current_scanner'],
        'message' => $batch['message']
    ]);
}
```

### 3. Get Audit Summary

**Endpoint:** `peiwm_get_audit_summary`

```php
public function ajax_get_audit_summary() {
    check_ajax_referer('peiwm_secure_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $controller = PEIWM_Media_Audit_Controller::get_instance();
    $scan = $controller->get_latest_scan();
    
    if (!$scan) {
        wp_send_json_success([
            'has_scan' => false
        ]);
        return;
    }
    
    wp_send_json_success([
        'has_scan' => true,
        'health_score' => $scan->health_score,
        'images_total' => $scan->images_total,
        'images_used' => $scan->images_used,
        'images_possibly_used' => $scan->images_possibly_used,
        'images_unused' => $scan->images_unused,
        'confidence' => $scan->confidence,
        'coverage' => $scan->coverage,
        'completed_at' => $scan->completed_at,
        'is_stale' => $controller->is_scan_stale($scan)
    ]);
}
```

### 4. Individual Trash (FREE)

**Endpoint:** `peiwm_trash_unused_media`

```php
public function ajax_trash_unused_media() {
    check_ajax_referer('peiwm_secure_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
    
    if (!$attachment_id) {
        wp_send_json_error(['message' => 'Invalid attachment ID']);
    }
    
    $safety = new PEIWM_Safety_Engine();
    $verdict = $safety->evaluate('trash', $attachment_id);
    
    if (!$verdict->allowed()) {
        wp_send_json_error([
            'message' => $verdict->reason(),
            'protected' => true
        ]);
        return;
    }
    
    // Move to trash (not permanent delete)
    $result = wp_trash_post($attachment_id);
    
    if ($result) {
        wp_send_json_success([
            'message' => __('Media moved to trash', 'post-export-import-with-media')
        ]);
    } else {
        wp_send_json_error([
            'message' => __('Failed to trash media', 'post-export-import-with-media')
        ]);
    }
}
```

---

## Development Timeline

### Week 1-2: Core Infrastructure (30 hours)
- Database schema + migrations: 4 hours
- Scanner architecture + interface: 8 hours
- Batch processor: 12 hours
- Repository classes: 6 hours

### Week 2-3: Scanners (34 hours)
- Post Content Scanner: 4 hours
- Page Content Scanner: 3 hours
- Post Meta Scanner: 5 hours
- Theme Options Scanner: 6 hours
- Widget Scanner: 4 hours
- Menu Scanner: 4 hours
- Scanner registry: 4 hours
- Attachment resolver: 4 hours

### Week 3-4: Engines (28 hours)
- Confidence engine: 6 hours
- Risk engine: 6 hours
- Recommendation engine: 4 hours
- Safety engine: 8 hours
- Health score: 4 hours

### Week 4-5: UI & Pages (30 hours)
- Health card integration: 4 hours
- Audit dashboard page: 10 hours
- Review images page: 12 hours
- Batch settings UI: 4 hours

### Week 5-6: AJAX & JavaScript (30 hours)
- AJAX endpoints: 12 hours
- Frontend JS: 12 hours
- Progress polling: 6 hours

### Week 6: NEW Features (12 hours)
- Export unused list (JSON): 4 hours
- Import decisions (JSON): 4 hours
- Auto-scan hooks: 4 hours

### Week 7-8: Testing & Polish (48 hours)
- Unit tests: 12 hours
- Integration tests: 10 hours
- Performance testing: 8 hours
- Bug fixes: 10 hours
- Documentation: 8 hours

**Total FREE Version: 212 hours (~5-6 weeks)**

---

## Testing Checklist (FREE)

### Functional Tests
- [ ] Scan 10k media library completes
- [ ] Scan 50k media library completes
- [ ] Scan 100k+ media library completes
- [ ] Batch processing resumes after timeout
- [ ] Fingerprint cache works
- [ ] Health score calculates correctly
- [ ] Confidence/risk levels accurate
- [ ] Safety rules prevent accidental deletion
- [ ] Individual trash works with confirmation
- [ ] Export unused list generates valid JSON
- [ ] Import decisions applies correctly
- [ ] Rescan invalidates cache

### Security Tests
- [ ] Nonce verification blocks invalid requests
- [ ] Non-admin users cannot access
- [ ] SQL injection attempts fail
- [ ] XSS attempts are escaped
- [ ] File upload validates JSON only

### Performance Tests
- [ ] Memory usage under 128MB
- [ ] No PHP timeouts on shared hosting
- [ ] Database queries optimized (no N+1)
- [ ] Frontend loads under 2 seconds

---

## Security Implementation (FREE)

✅ **Nonce Verification**: All AJAX requests verify `peiwm_secure_nonce`  
✅ **Capability Checks**: `manage_options` for audit, `upload_files` for trash  
✅ **Input Sanitization**: `absint()`, `sanitize_text_field()`  
✅ **SQL Injection Prevention**: `$wpdb->prepare()` for all custom queries  
✅ **Output Escaping**: `esc_html()`, `esc_attr()`, `esc_url()`  
✅ **File Upload Validation**: Type, size, JSON structure checks  
✅ **Trash Not Delete**: Uses `wp_trash_post()`, reversible  

---

## PRO Features (Phase 2 - Future)

**Focus: Enhanced Unused Media Cleaning**

This section outlines PRO-exclusive enhancements for future development focused on making unused media cleanup faster and more efficient.

### PRO Advanced Scanners
- Gutenberg Block Scanner (reusable blocks, custom blocks)
- Elementor Scanner (page builder content)
- WooCommerce Scanner (product images, galleries, product variations)
- BuddyPress/bbPress Scanner (forums, profiles, activity streams)

### PRO Bulk Cleaning Operations
- **Bulk trash with one click** (with safety checks applied to each item)
- **Batch export unused media as ZIP** (download before cleanup for backup)
- **Bulk import decisions** (apply decisions across multiple sites)

### PRO Cleanup Automation
- **Scheduled auto-scans** (daily/weekly cron jobs)
- **Auto-scan after import** (trigger scan after post/media imports)
- **Email reports** (cleanup summary, warnings, space saved)
- **Auto-trash after X days** (with safety confirmation and user review)

**PRO Timeline:** +3-4 weeks

---

## Conclusion

This implementation provides a complete FREE media health system focused on **unused media cleaning** that:

✅ **Scales**: Batch processing for 100k+ libraries  
✅ **Safe**: Multiple safety layers prevent accidental deletion  
✅ **FREE**: Core cleanup features available to all  
✅ **Blog Scanner**: Scans WordPress posts and pages  
✅ **No AI**: Simple, reliable detection without dependencies  
✅ **Integrated**: Seamless with existing export/import features  
✅ **Extensible**: Easy to add PRO scanners for advanced content builders  
✅ **Focused**: Clear goal of identifying and cleaning unused media safely  
✅ **Standards**: WordPress best practices  

The FREE/PRO split provides strong value to free users while creating clear upgrade incentives. The modular architecture enables incremental development, and the batch system ensures it works on shared hosting.
