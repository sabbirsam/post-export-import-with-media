# Update Missing Media Feature - Implementation Plan

## Overview
Add an "Update Media" feature to the "Missing from Disk" modal that allows users to replace missing media files individually or in bulk. This is a **PRO feature** with UI in the base plugin and backend logic in the PRO plugin.

## Current State Analysis

### Existing "Missing from Disk" Feature
**Location:** `assets/js/admin.js` (lines 2380-2590)
- **Trigger:** "View Details" button in media stats when missing files exist
- **Modal Display:** Shows table with columns: ID | Title | Filename | Expected Path
- **Current Actions:** 
  - Fix Paths button
  - Clean Missing Files button
- **Data Storage:** `window.peiwmMissingFiles` array

### PRO Integration Pattern
**How PRO features are locked:**
1. Check `PEIWM_Main::get_instance()->is_pro_active()` in PHP
2. Add `.peiwm-locked-section` class with overlay
3. Click handler opens premium modal via `.peiwm-open-premium-modal`
4. Backend AJAX handlers in PRO plugin validate PRO status

**Example Files:**
- `includes/class-admin-menu.php` - UI with pro locks
- `PRO/includes/class-ajax-handler-pro.php` - Backend handlers
- `assets/js/admin.js` (line 178) - Premium modal trigger

---

## Feature Requirements

### User Flow
1. User clicks "View Details" on missing files warning
2. Modal shows table with missing files + new "Update" button per row
3. If PRO not active: Update button shows 🔒 icon, is disabled, click shows premium modal
4. If PRO active: Update button is clickable
5. Click Update → Opens media selection modal with two tabs:
   - **Media Library Tab:** Browse/select existing media
   - **Upload Tab:** Upload file from desktop
6. After selecting media → Show preview thumbnail with X icon (replaces Update button)
7. Click thumbnail → Reopens selection modal to change
8. Click X → Removes selection, shows Update button again
9. At bottom: "Update All Selected" button (enabled when 1+ items selected)
10. Individual update: "Update Now" button in selection modal

---

## Implementation Plan

### Phase 1: Base Plugin UI Changes (Free Version)

#### File: `assets/js/admin.js`

##### 1.1 Add Update Button Column to Missing Files Table
**Function:** `showMissingFilesModal()` (line 2380)

**Changes:**
```javascript
// Add new column header
tableHtml += '<th style="padding:8px;text-align:center;border-bottom:2px solid #e5e7eb;width:120px;">Action</th>';

// In forEach loop, add action cell
const isProActive = typeof peiwm_ajax.is_pro_active !== 'undefined' && peiwm_ajax.is_pro_active;
const updateBtnClass = isProActive ? '' : 'peiwm-locked-btn peiwm-open-premium-modal';
const updateBtnDisabled = isProActive ? '' : 'disabled';
const updateBtnIcon = isProActive ? '' : '🔒 ';

tableHtml += '<td style="padding:8px;text-align:center;" data-media-id="' + file.id + '">';
tableHtml += '<button type="button" class="button button-small peiwm-update-media-btn ' + updateBtnClass + '" ';
tableHtml += 'data-media-id="' + file.id + '" ';
tableHtml += 'data-title="' + $('<div>').text(file.title || 'Unknown').html() + '" ';
tableHtml += 'data-filename="' + $('<div>').text(file.filename).html() + '" ';
tableHtml += updateBtnDisabled + '>';
tableHtml += updateBtnIcon + 'Update';
tableHtml += '</button>';
tableHtml += '<div class="peiwm-media-preview" style="display:none;"></div>';
tableHtml += '</td>';
```

##### 1.2 Add Bulk Update Button
**After action buttons section:**
```javascript
// Add bulk update button at bottom
const bulkUpdateClass = isProActive ? '' : 'peiwm-locked-btn peiwm-open-premium-modal';
const bulkUpdateDisabled = isProActive ? '' : 'disabled';
const bulkUpdateIcon = isProActive ? '' : '🔒 ';

tableHtml += '<div style="margin-top:1rem;display:flex;justify-content:center;border-top:1px solid #e5e7eb;padding-top:1rem;">';
tableHtml += '<button type="button" id="peiwm-update-all-selected-btn" class="button button-primary ' + bulkUpdateClass + '" ';
tableHtml += bulkUpdateDisabled + ' style="display:none;background:#7c3aed;border-color:#7c3aed;">';
tableHtml += bulkUpdateIcon + 'Update All Selected (<span class="peiwm-selected-count">0</span>)';
tableHtml += '</button>';
tableHtml += '</div>';
```

##### 1.3 Create Media Selection Modal HTML
**New function after `showMissingFilesModal()`:**
```javascript
// Store selected media for each missing file
window.peiwmMissingMediaSelections = {};

function showMediaSelectionModal(mediaId, title, filename) {
    const modalHtml = `
        <div id="peiwm-media-selector-modal" class="peiwm-modal-overlay" style="display:none;">
            <div class="peiwm-modal peiwm-media-selector-modal" style="max-width:800px;width:90%;">
                <div class="peiwm-modal-header">
                    <h3>Select Replacement Media</h3>
                    <button type="button" class="peiwm-modal-close">&times;</button>
                </div>
                <div class="peiwm-modal-body">
                    <div class="peiwm-media-source-tabs">
                        <button type="button" class="peiwm-tab-btn active" data-tab="library">
                            📁 Media Library
                        </button>
                        <button type="button" class="peiwm-tab-btn" data-tab="upload">
                            ⬆️ Upload File
                        </button>
                    </div>
                    <div class="peiwm-tab-content">
                        <div id="peiwm-tab-library" class="peiwm-tab-panel active">
                            <div class="peiwm-media-search">
                                <input type="text" id="peiwm-media-search-input" placeholder="Search media..." class="regular-text">
                            </div>
                            <div id="peiwm-media-grid" class="peiwm-media-grid">
                                <div class="peiwm-loading-spinner"></div>
                                <p>Loading media library...</p>
                            </div>
                        </div>
                        <div id="peiwm-tab-upload" class="peiwm-tab-panel" style="display:none;">
                            <div class="peiwm-upload-area">
                                <input type="file" id="peiwm-upload-file-input" accept="image/*,video/*,audio/*,application/*" style="display:none;">
                                <button type="button" id="peiwm-upload-trigger-btn" class="button button-large">
                                    Choose File to Upload
                                </button>
                                <p class="description">Select a file from your computer to replace the missing media.</p>
                                <div id="peiwm-upload-preview" style="display:none;margin-top:1rem;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="peiwm-modal-footer" style="display:flex;gap:10px;justify-content:flex-end;padding:1rem;border-top:1px solid #e5e7eb;">
                    <button type="button" id="peiwm-media-select-btn" class="button button-primary" disabled>
                        Select
                    </button>
                    <button type="button" id="peiwm-media-update-now-btn" class="button button-primary" disabled style="background:#7c3aed;border-color:#7c3aed;">
                        Update Now
                    </button>
                    <button type="button" id="peiwm-media-cancel-btn" class="button">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Append to body if not exists
    if ($('#peiwm-media-selector-modal').length === 0) {
        $('body').append(modalHtml);
    }
    
    // Store current context
    $('#peiwm-media-selector-modal').data('currentMediaId', mediaId);
    $('#peiwm-media-selector-modal').data('currentTitle', title);
    $('#peiwm-media-selector-modal').data('currentFilename', filename);
    
    // Show modal
    $('#peiwm-media-selector-modal').show().addClass('peiwm-show');
    
    // Load media library
    loadMediaLibraryForSelection();
    
    // Attach event handlers
    attachMediaSelectorHandlers();
}
```

##### 1.4 Media Library Loading Function
```javascript
function loadMediaLibraryForSelection() {
    const grid = $('#peiwm-media-grid');
    grid.html('<div class="peiwm-loading-spinner"></div><p>Loading media library...</p>');
    
    $.ajax({
        url: peiwm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'peiwm_get_media_library',
            nonce: peiwm_ajax.nonce,
            per_page: 50,
            page: 1
        },
        success: function(response) {
            if (response.success) {
                renderMediaGrid(response.data.media);
            } else {
                grid.html('<p class="peiwm-selective-empty">Failed to load media library.</p>');
            }
        },
        error: function() {
            grid.html('<p class="peiwm-selective-empty">Error loading media library.</p>');
        }
    });
}

function renderMediaGrid(mediaItems) {
    const grid = $('#peiwm-media-grid');
    if (!mediaItems || mediaItems.length === 0) {
        grid.html('<p class="peiwm-selective-empty">No media found.</p>');
        return;
    }
    
    let html = '';
    mediaItems.forEach(function(item) {
        const thumbUrl = item.thumbnail || item.url || '';
        html += '<div class="peiwm-media-item" data-id="' + item.id + '" data-url="' + item.url + '">';
        html += '<div class="peiwm-media-thumb" style="background-image:url(' + thumbUrl + ')"></div>';
        html += '<div class="peiwm-media-info">';
        html += '<span class="peiwm-media-title">' + $('<div>').text(item.title).html() + '</span>';
        html += '</div>';
        html += '</div>';
    });
    grid.html(html);
    
    // Click handler for media selection
    grid.find('.peiwm-media-item').on('click', function() {
        grid.find('.peiwm-media-item').removeClass('selected');
        $(this).addClass('selected');
        $('#peiwm-media-select-btn, #peiwm-media-update-now-btn').prop('disabled', false);
    });
}
```

##### 1.5 Event Handlers for Media Selector Modal
```javascript
function attachMediaSelectorHandlers() {
    const modal = $('#peiwm-media-selector-modal');
    
    // Tab switching
    modal.find('.peiwm-tab-btn').off('click').on('click', function() {
        const tab = $(this).data('tab');
        modal.find('.peiwm-tab-btn').removeClass('active');
        $(this).addClass('active');
        modal.find('.peiwm-tab-panel').hide();
        $('#peiwm-tab-' + tab).show();
    });
    
    // Upload trigger
    $('#peiwm-upload-trigger-btn').off('click').on('click', function() {
        $('#peiwm-upload-file-input').click();
    });
    
    // File upload preview
    $('#peiwm-upload-file-input').off('change').on('change', function() {
        const file = this.files[0];
        if (file) {
            const preview = $('#peiwm-upload-preview');
            preview.html('<p><strong>Selected:</strong> ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)</p>');
            preview.show();
            $('#peiwm-media-select-btn, #peiwm-media-update-now-btn').prop('disabled', false);
        }
    });
    
    // Search media
    let searchTimeout;
    $('#peiwm-media-search-input').off('input').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();
        searchTimeout = setTimeout(function() {
            searchMediaLibrary(query);
        }, 300);
    });
    
    // Select button - closes modal and shows preview
    $('#peiwm-media-select-btn').off('click').on('click', function() {
        const mediaId = modal.data('currentMediaId');
        const selectedMedia = getSelectedMediaData();
        
        if (selectedMedia) {
            // Store selection
            window.peiwmMissingMediaSelections[mediaId] = selectedMedia;
            
            // Update UI - replace button with preview
            updateMediaRowPreview(mediaId, selectedMedia);
            
            // Show bulk update button
            updateBulkUpdateButton();
            
            // Close modal
            modal.removeClass('peiwm-show').hide();
        }
    });
    
    // Update Now button - immediately sends to backend
    $('#peiwm-media-update-now-btn').off('click').on('click', function() {
        const mediaId = modal.data('currentMediaId');
        const selectedMedia = getSelectedMediaData();
        
        if (selectedMedia) {
            modal.removeClass('peiwm-show').hide();
            updateSingleMedia(mediaId, selectedMedia);
        }
    });
    
    // Cancel button
    $('#peiwm-media-cancel-btn, #peiwm-media-selector-modal .peiwm-modal-close').off('click').on('click', function() {
        modal.removeClass('peiwm-show').hide();
    });
    
    // Close on overlay click
    modal.off('click').on('click', function(e) {
        if (e.target === this) {
            modal.removeClass('peiwm-show').hide();
        }
    });
}

function getSelectedMediaData() {
    const activeTab = $('#peiwm-media-selector-modal .peiwm-tab-btn.active').data('tab');
    
    if (activeTab === 'library') {
        const selected = $('#peiwm-media-grid .peiwm-media-item.selected');
        if (selected.length > 0) {
            return {
                type: 'library',
                id: selected.data('id'),
                url: selected.data('url'),
                thumbnail: selected.find('.peiwm-media-thumb').css('background-image').slice(5, -2)
            };
        }
    } else if (activeTab === 'upload') {
        const fileInput = $('#peiwm-upload-file-input')[0];
        if (fileInput.files.length > 0) {
            return {
                type: 'upload',
                file: fileInput.files[0]
            };
        }
    }
    
    return null;
}
```

##### 1.6 Update Row Preview Function
```javascript
function updateMediaRowPreview(mediaId, selectedMedia) {
    const actionCell = $('.peiwm-update-media-btn[data-media-id="' + mediaId + '"]').closest('td');
    const btn = actionCell.find('.peiwm-update-media-btn');
    const preview = actionCell.find('.peiwm-media-preview');
    
    btn.hide();
    
    let previewHtml = '<div class="peiwm-selected-media" style="display:flex;align-items:center;gap:8px;">';
    
    if (selectedMedia.type === 'library') {
        previewHtml += '<div class="peiwm-mini-thumb" style="width:40px;height:40px;background:url(' + selectedMedia.thumbnail + ') center/cover;border:2px solid #7c3aed;border-radius:4px;cursor:pointer;" data-media-id="' + mediaId + '"></div>';
    } else {
        previewHtml += '<div class="peiwm-mini-thumb" style="width:40px;height:40px;background:#e5e7eb;border:2px solid #7c3aed;border-radius:4px;display:flex;align-items:center;justify-content:center;cursor:pointer;" data-media-id="' + mediaId + '">📄</div>';
    }
    
    previewHtml += '<button type="button" class="peiwm-remove-selection" data-media-id="' + mediaId + '" style="background:#dc2626;color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;line-height:1;">×</button>';
    previewHtml += '</div>';
    
    preview.html(previewHtml).show();
    
    // Click thumbnail to reopen modal
    preview.find('.peiwm-mini-thumb').on('click', function() {
        const id = $(this).data('media-id');
        const btn = $('.peiwm-update-media-btn[data-media-id="' + id + '"]');
        showMediaSelectionModal(id, btn.data('title'), btn.data('filename'));
    });
    
    // Click X to remove selection
    preview.find('.peiwm-remove-selection').on('click', function() {
        const id = $(this).data('media-id');
        delete window.peiwmMissingMediaSelections[id];
        preview.hide().html('');
        btn.show();
        updateBulkUpdateButton();
    });
}

function updateBulkUpdateButton() {
    const count = Object.keys(window.peiwmMissingMediaSelections).length;
    const bulkBtn = $('#peiwm-update-all-selected-btn');
    bulkBtn.find('.peiwm-selected-count').text(count);
    
    if (count > 0) {
        bulkBtn.show();
    } else {
        bulkBtn.hide();
    }
}
```

##### 1.7 Update Button Click Handler
**Add in `showMissingFilesModal()` after modal display:**
```javascript
// Update media button click handler
$(document).off('click.update-media').on('click.update-media', '.peiwm-update-media-btn:not(.peiwm-locked-btn)', function() {
    const mediaId = $(this).data('media-id');
    const title = $(this).data('title');
    const filename = $(this).data('filename');
    showMediaSelectionModal(mediaId, title, filename);
});

// Bulk update button handler
$('#peiwm-update-all-selected-btn:not(.peiwm-locked-btn)').on('click', function() {
    updateAllSelectedMedia();
});
```

##### 1.8 Backend Update Functions (Stub for Base, Real in PRO)
```javascript
function updateSingleMedia(mediaId, selectedMedia) {
    showInfo('Updating media #' + mediaId + '...');
    
    const formData = new FormData();
    formData.append('action', 'peiwm_update_missing_media');
    formData.append('nonce', peiwm_ajax.nonce);
    formData.append('media_id', mediaId);
    formData.append('type', selectedMedia.type);
    
    if (selectedMedia.type === 'library') {
        formData.append('replacement_id', selectedMedia.id);
    } else {
        formData.append('file', selectedMedia.file);
    }
    
    $.ajax({
        url: peiwm_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showSuccess(response.data.message);
                // Remove from selections
                delete window.peiwmMissingMediaSelections[mediaId];
                // Reload stats
                loadMediaStats();
            } else {
                showError('Update failed: ' + response.data.message);
            }
        },
        error: function() {
            showError('Update failed. Please try again.');
        }
    });
}

function updateAllSelectedMedia() {
    const selections = window.peiwmMissingMediaSelections;
    const mediaIds = Object.keys(selections);
    
    if (mediaIds.length === 0) {
        showError('No media selected for update.');
        return;
    }
    
    const bulkBtn = $('#peiwm-update-all-selected-btn');
    bulkBtn.prop('disabled', true).text('Updating ' + mediaIds.length + ' items...');
    
    // Close the missing files modal
    $('#peiwm-modal-overlay').removeClass('peiwm-show').hide();
    
    // Process each selection
    let completed = 0;
    let errors = 0;
    
    function processNext(index) {
        if (index >= mediaIds.length) {
            // All done
            if (errors === 0) {
                showSuccess('Successfully updated ' + completed + ' media files!');
            } else {
                showSuccess('Updated ' + completed + ' files. ' + errors + ' failed.');
            }
            // Clear selections and reload stats
            window.peiwmMissingMediaSelections = {};
            loadMediaStats();
            return;
        }
        
        const mediaId = mediaIds[index];
        const selectedMedia = selections[mediaId];
        
        const formData = new FormData();
        formData.append('action', 'peiwm_update_missing_media');
        formData.append('nonce', peiwm_ajax.nonce);
        formData.append('media_id', mediaId);
        formData.append('type', selectedMedia.type);
        
        if (selectedMedia.type === 'library') {
            formData.append('replacement_id', selectedMedia.id);
        } else {
            formData.append('file', selectedMedia.file);
        }
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    completed++;
                } else {
                    errors++;
                }
                processNext(index + 1);
            },
            error: function() {
                errors++;
                processNext(index + 1);
            }
        });
    }
    
    processNext(0);
}
```

#### File: `assets/css/admin.css`

##### 1.9 Add CSS for Media Selection Modal
**Add after premium modal styles (around line 2950):**
```css
/* ============================================
   Media Selector Modal
   ============================================ */
.peiwm-media-selector-modal {
    max-width: 800px;
    width: 90%;
}

.peiwm-media-source-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 1rem;
}

.peiwm-tab-btn {
    background: none;
    border: none;
    padding: 12px 20px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.peiwm-tab-btn:hover {
    color: #1f2937;
    background: #f9fafb;
}

.peiwm-tab-btn.active {
    color: #7c3aed;
    border-bottom-color: #7c3aed;
}

.peiwm-media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
    padding: 8px;
    background: #f9fafb;
    border-radius: 8px;
}

.peiwm-media-item {
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
    background: #fff;
}

.peiwm-media-item:hover {
    border-color: #d1d5db;
    transform: scale(1.02);
}

.peiwm-media-item.selected {
    border-color: #7c3aed;
    box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.2);
}

.peiwm-media-thumb {
    width: 100%;
    padding-bottom: 100%;
    background: #e5e7eb center/cover no-repeat;
}

.peiwm-media-info {
    padding: 8px;
    font-size: 11px;
    color: #4b5563;
    text-align: center;
}

.peiwm-media-title {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.peiwm-upload-area {
    text-align: center;
    padding: 3rem 1rem;
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
}

.peiwm-upload-area button {
    font-size: 16px;
    padding: 12px 24px;
}

.peiwm-media-search {
    margin-bottom: 1rem;
}

.peiwm-media-search input {
    width: 100%;
}

.peiwm-locked-btn {
    opacity: 0.6;
    cursor: not-allowed !important;
    position: relative;
}

.peiwm-mini-thumb {
    flex-shrink: 0;
}

.peiwm-selected-media {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
```

#### File: `includes/class-ajax-handler.php`

##### 1.10 Add AJAX Hook Registration (Stub)
**In `init_ajax_hooks()` method, add:**
```php
// Get media library for selection (base version - limited)
add_action( 'wp_ajax_peiwm_get_media_library', array( $this, 'ajax_get_media_library' ) );

// Update missing media (will return error in free version)
add_action( 'wp_ajax_peiwm_update_missing_media', array( $this, 'ajax_update_missing_media' ) );
```

##### 1.11 Add Stub Methods
**Add new methods in class:**
```php
/**
 * AJAX: Get media library items (Free version - limited to 20 items)
 */
public function ajax_get_media_library() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
    }

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
    }

    // Free version: return limited results
    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 20,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $attachments = get_posts( $args );
    $media = array();

    foreach ( $attachments as $attachment ) {
        $media[] = array(
            'id'        => $attachment->ID,
            'title'     => get_the_title( $attachment->ID ),
            'url'       => wp_get_attachment_url( $attachment->ID ),
            'thumbnail' => wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ),
        );
    }

    wp_send_json_success( array(
        'media'     => $media,
        'has_more'  => false,
        'is_pro'    => false,
        'message'   => 'Showing 20 most recent items. Upgrade to PRO for full library access.',
    ) );
}

/**
 * AJAX: Update missing media (Free version - returns upgrade message)
 */
public function ajax_update_missing_media() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
    }

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
    }

    // This feature requires PRO
    wp_send_json_error( array(
        'message' => esc_html__( 'This is a PRO feature. Please upgrade to use the Update Missing Media feature.', 'post-export-import-with-media' ),
        'is_pro_feature' => true,
    ) );
}
```

#### File: `includes/class-main.php`

##### 1.12 Pass PRO Status to JavaScript
**In `enqueue_scripts()` method, update localized script:**
```php
wp_localize_script(
    'peiwm-admin-script',
    'peiwm_ajax',
    array(
        'ajax_url'       => admin_url( 'admin-ajax.php' ),
        'nonce'          => wp_create_nonce( 'peiwm_secure_nonce' ),
        'is_pro_active'  => $this->is_pro_active(), // ADD THIS LINE
        'strings'        => array(
            'select_file'    => esc_html__( 'Please select a file', 'post-export-import-with-media' ),
            'confirm_import' => esc_html__( 'Are you sure you want to import?', 'post-export-import-with-media' ),
        ),
    )
);
```

---

### Phase 2: PRO Plugin Backend Implementation

#### File: `PRO/includes/class-ajax-handler-pro.php`

##### 2.1 Override AJAX Hooks
**In `init_ajax_hooks()` method, add:**
```php
// Override base plugin handlers with PRO versions
add_action( 'wp_ajax_peiwm_get_media_library', array( $this, 'ajax_get_media_library_pro' ), 5 ); // Priority 5 to override
add_action( 'wp_ajax_peiwm_update_missing_media', array( $this, 'ajax_update_missing_media_pro' ), 5 );
```

##### 2.2 Implement Full Media Library Handler
**Add new method:**
```php
/**
 * AJAX: Get media library items (PRO version - full access with pagination)
 */
public function ajax_get_media_library_pro() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
    }

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
    }

    // Verify PRO is active
    if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
        wp_send_json_error( array( 'message' => esc_html__( 'PRO version required', 'post-export-import-with-media' ) ) );
    }

    $page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
    $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 50;
    $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( ! empty( $search ) ) {
        $args['s'] = $search;
    }

    $query = new WP_Query( $args );
    $media = array();

    foreach ( $query->posts as $attachment ) {
        $media[] = array(
            'id'        => $attachment->ID,
            'title'     => get_the_title( $attachment->ID ),
            'url'       => wp_get_attachment_url( $attachment->ID ),
            'thumbnail' => wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ),
            'mime_type' => get_post_mime_type( $attachment->ID ),
        );
    }

    wp_send_json_success( array(
        'media'       => $media,
        'has_more'    => $query->max_num_pages > $page,
        'total_pages' => $query->max_num_pages,
        'total'       => $query->found_posts,
        'is_pro'      => true,
    ) );
}
```

##### 2.3 Implement Update Missing Media Handler
**Add new method:**
```php
/**
 * AJAX: Update missing media (PRO version - full implementation)
 */
public function ajax_update_missing_media_pro() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
    }

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
    }

    // Verify PRO is active
    if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
        wp_send_json_error( array( 'message' => esc_html__( 'PRO version required', 'post-export-import-with-media' ) ) );
    }

    $media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
    $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

    if ( ! $media_id || ! $type ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Invalid request', 'post-export-import-with-media' ) ) );
    }

    try {
        if ( $type === 'library' ) {
            // Replace with existing media from library
            $replacement_id = isset( $_POST['replacement_id'] ) ? absint( $_POST['replacement_id'] ) : 0;
            
            if ( ! $replacement_id ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Invalid replacement media', 'post-export-import-with-media' ) ) );
            }

            // Get the replacement media file path
            $replacement_path = get_attached_file( $replacement_id );
            $replacement_url = wp_get_attachment_url( $replacement_id );

            if ( ! $replacement_path || ! file_exists( $replacement_path ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Replacement file not found', 'post-export-import-with-media' ) ) );
            }

            // Update the original attachment metadata to point to new file
            $upload_dir = wp_upload_dir();
            $file_info = pathinfo( $replacement_path );
            
            // Copy file to expected location of missing file
            $original_file_path = get_post_meta( $media_id, '_wp_attached_file', true );
            if ( $original_file_path ) {
                $target_path = $upload_dir['basedir'] . '/' . $original_file_path;
                $target_dir = dirname( $target_path );

                // Create directory if needed
                if ( ! file_exists( $target_dir ) ) {
                    wp_mkdir_p( $target_dir );
                }

                // Copy the file
                if ( copy( $replacement_path, $target_path ) ) {
                    // Update attachment metadata
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata( $media_id, $target_path );
                    wp_update_attachment_metadata( $media_id, $attach_data );

                    wp_send_json_success( array(
                        'message' => sprintf(
                            /* translators: %d: Media ID */
                            esc_html__( 'Successfully updated media #%d', 'post-export-import-with-media' ),
                            $media_id
                        ),
                        'media_id' => $media_id,
                    ) );
                } else {
                    wp_send_json_error( array( 'message' => esc_html__( 'Failed to copy file', 'post-export-import-with-media' ) ) );
                }
            } else {
                wp_send_json_error( array( 'message' => esc_html__( 'Original file path not found', 'post-export-import-with-media' ) ) );
            }

        } elseif ( $type === 'upload' ) {
            // Handle uploaded file
            if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
                wp_send_json_error( array( 'message' => esc_html__( 'File upload failed', 'post-export-import-with-media' ) ) );
            }

            $uploaded_file = $_FILES['file'];
            
            // Validate file type
            $wp_filetype = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'] );
            if ( ! $wp_filetype['type'] ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type', 'post-export-import-with-media' ) ) );
            }

            // Get the target path for the missing file
            $upload_dir = wp_upload_dir();
            $original_file_path = get_post_meta( $media_id, '_wp_attached_file', true );

            if ( ! $original_file_path ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Original file path not found', 'post-export-import-with-media' ) ) );
            }

            $target_path = $upload_dir['basedir'] . '/' . $original_file_path;
            $target_dir = dirname( $target_path );

            // Create directory if needed
            if ( ! file_exists( $target_dir ) ) {
                wp_mkdir_p( $target_dir );
            }

            // Move uploaded file to target location
            if ( move_uploaded_file( $uploaded_file['tmp_name'], $target_path ) ) {
                // Update attachment metadata
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata( $media_id, $target_path );
                wp_update_attachment_metadata( $media_id, $attach_data );

                wp_send_json_success( array(
                    'message' => sprintf(
                        /* translators: %d: Media ID */
                        esc_html__( 'Successfully updated media #%d with uploaded file', 'post-export-import-with-media' ),
                        $media_id
                    ),
                    'media_id' => $media_id,
                ) );
            } else {
                wp_send_json_error( array( 'message' => esc_html__( 'Failed to move uploaded file', 'post-export-import-with-media' ) ) );
            }

        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid update type', 'post-export-import-with-media' ) ) );
        }

    } catch ( Exception $e ) {
        error_log( 'PEIWM PRO: Update missing media error - ' . $e->getMessage() );
        wp_send_json_error( array( 'message' => esc_html__( 'Update failed', 'post-export-import-with-media' ) ) );
    }
}
```

---

## Testing Checklist

### Base Plugin (Free Version)
- [ ] "View Details" button shows update column with lock icon
- [ ] Update button shows 🔒 and is disabled when PRO not active
- [ ] Clicking locked button triggers premium upgrade modal
- [ ] Bulk update button shows 🔒 and is disabled
- [ ] Media library loads but shows only 20 items with upgrade message
- [ ] Individual update shows "PRO required" error message

### PRO Plugin
- [ ] Update buttons are enabled and clickable
- [ ] Media selection modal opens on update click
- [ ] Media library tab loads all media with pagination
- [ ] Search filters media library results
- [ ] Upload tab allows file selection
- [ ] "Select" button stores selection and shows thumbnail preview
- [ ] Click thumbnail reopens modal for changing selection
- [ ] Click X removes selection and shows update button again
- [ ] "Update Now" immediately processes single media
- [ ] Bulk update button shows correct count
- [ ] Bulk update processes all selected media
- [ ] Success/error messages display correctly
- [ ] Media stats refresh after update
- [ ] Files are physically copied/moved to correct location
- [ ] Attachment metadata is regenerated
- [ ] Missing files list updates after successful replacement

---

## File Structure Summary

### Base Plugin Files (Modified/Created)
```
assets/js/admin.js (MODIFIED - ~500 lines added)
assets/css/admin.css (MODIFIED - ~150 lines added)
includes/class-ajax-handler.php (MODIFIED - 2 stub methods added)
includes/class-main.php (MODIFIED - 1 line added to localize script)
```

### PRO Plugin Files (Modified/Created)
```
PRO/includes/class-ajax-handler-pro.php (MODIFIED - 2 methods added ~200 lines)
```

---

## Implementation Notes

1. **Security**: All AJAX handlers verify nonce and user capabilities
2. **PRO Validation**: Backend always checks `is_pro_active()` before processing
3. **File Handling**: Uses WordPress core functions for file operations
4. **Error Handling**: Try-catch blocks with detailed error messages
5. **UI/UX**: Smooth animations, loading states, and clear feedback
6. **Performance**: Pagination for large media libraries
7. **Compatibility**: Works with existing modal system and CSS
8. **Extensibility**: Modular functions for easy maintenance

---

## Estimated Development Time

- **Phase 1 (Base UI)**: 6-8 hours
- **Phase 2 (PRO Backend)**: 4-5 hours
- **Testing & Debugging**: 3-4 hours
- **Total**: 13-17 hours

---

## Future Enhancements (Optional)

1. Drag-and-drop file upload
2. Bulk upload multiple files at once
3. Preview comparison (old vs new)
4. Undo/rollback functionality
5. Image optimization on upload
6. Background processing for large batches
7. Email notification on completion
8. Activity log for audit trail
