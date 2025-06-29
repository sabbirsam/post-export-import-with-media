jQuery(document).ready(function($) {
    'use strict';
    
    // Modal Utility Functions
    function showModal(type, title, message) {
        // Always close any open modal overlays first
        $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
        $(document).off('keydown.peiwm-modal');

        let modalId = '#peiwm-modal-overlay';
        let modalClass = '';
        
        switch(type) {
            case 'success':
                modalId = '#peiwm-success-modal';
                modalClass = 'peiwm-success-modal';
                break;
            case 'error':
                modalId = '#peiwm-error-modal';
                modalClass = 'peiwm-error-modal';
                break;
            case 'warning':
                modalId = '#peiwm-modal-overlay';
                modalClass = 'peiwm-warning-modal';
                break;
            case 'danger':
                modalId = '#peiwm-modal-overlay';
                modalClass = 'peiwm-danger-modal';
                break;
        }
        
        const modal = $(modalId);
        const modalContent = modal.find('.peiwm-modal');
        
        // Set content
        modal.find('.peiwm-modal-header h3').text(title);
        modal.find('.peiwm-modal-body p').html(message);
        
        // Add warning/danger styling to body if needed
        if (type === 'warning' || type === 'danger') {
            modalContent.addClass(modalClass);
        } else {
            modalContent.removeClass('peiwm-warning-modal peiwm-danger-modal');
        }
        
        // Detach previous handlers before attaching new ones
        const confirmBtn = modal.find('#peiwm-modal-confirm');
        const cancelBtn = modal.find('#peiwm-modal-cancel');
        confirmBtn.off('click');
        cancelBtn.off('click');

        // Return a promise for confirmation modals
        if (type === 'warning' || type === 'danger') {
            return new Promise((resolve, reject) => {
                confirmBtn.on('click', function() {
                    hideModal(modalId);
                    resolve();
                });
                cancelBtn.on('click', function() {
                    hideModal(modalId);
                    reject();
                });
                // Show modal
                modal.show().addClass('peiwm-show');
                // Handle close button
                modal.find('.peiwm-modal-close').off('click').on('click', function() {
                    hideModal(modalId);
                    reject();
                });
                // Handle overlay click to close
                modal.off('click').on('click', function(e) {
                    if (e.target === this) {
                        hideModal(modalId);
                        reject();
                    }
                });
                // Handle escape key
                $(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function(e) {
                    if (e.key === 'Escape') {
                        hideModal(modalId);
                        reject();
                    }
                });
            });
        } else {
            // Show modal
            modal.show().addClass('peiwm-show');
            // Handle close button
            modal.find('.peiwm-modal-close').off('click').on('click', function() {
                hideModal(modalId);
            });
            // Handle overlay click to close
            modal.off('click').on('click', function(e) {
                if (e.target === this) {
                    hideModal(modalId);
                }
            });
            // Handle escape key
            $(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function(e) {
                if (e.key === 'Escape') {
                    hideModal(modalId);
                }
            });
        }
    }
    
    function hideModal(modalId) {
        const modal = $(modalId);
        modal.removeClass('peiwm-show');
        setTimeout(function() {
            modal.hide();
        }, 300);
    }
    
    function showConfirmation(title, message) {
        return showModal('warning', title, message);
    }
    
    function showSuccess(message) {
        showModal('success', 'Success!', message);
    }
    
    function showError(message) {
        showModal('error', 'Error', message);
    }
    
    function showDangerConfirmation(title, message) {
        return showModal('danger', title, message);
    }
    
    // File input handlers
    $('#peiwm-select-posts-file').on('click', function() {
        $('#peiwm-posts-file').click();
    });
    
    $('#peiwm-select-media-file').on('click', function() {
        $('#peiwm-media-file').click();
    });
    
    $('#peiwm-posts-file').on('change', function() {
        if (this.files.length > 0) {
            $('#peiwm-import-posts').show();
        } else {
            $('#peiwm-import-posts').hide();
        }
    });
    
    $('#peiwm-media-file').on('change', function() {
        if (this.files.length > 0) {
            $('#peiwm-import-media').show();
        } else {
            $('#peiwm-import-media').hide();
        }
    });
    
    // Export Posts
    $('#peiwm-export-posts').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_posts',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and download file
                    const dataStr = JSON.stringify(response.data.data, null, 2);
                    const dataBlob = new Blob([dataStr], {type: 'application/json'});
                    const url = window.URL.createObjectURL(dataBlob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'posts_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                    
                    showSuccess('Posts exported successfully! (' + response.data.count + ' posts)');
                } else {
                    showError('Export failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Export failed: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Import Posts
    $('#peiwm-import-posts').on('click', function() {
        const fileInput = $('#peiwm-posts-file')[0];
        if (!fileInput.files.length) {
            showError(peiwm_ajax.strings.select_file);
            return;
        }
        
        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const data = JSON.parse(e.target.result);
                if (!Array.isArray(data)) {
                    showError('Invalid file format. Please select a valid export file.');
                    return;
                }
                
                importPosts(data);
            } catch (error) {
                showError('Error parsing file: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    });
    
    // Export Media
    $('#peiwm-export-media').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_media',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showSuccess('Media exported successfully! (' + response.data.count + ' files, ' + response.data.total_size_formatted + ')');
                } else {
                    showError('Export failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Export failed: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Import Media
    $('#peiwm-import-media').on('click', function() {
        const fileInput = $('#peiwm-media-file')[0];
        if (!fileInput.files.length) {
            showError(peiwm_ajax.strings.select_file);
            return;
        }
        
        const file = fileInput.files[0];
        
        // Debug: Log file size information
        console.log('File size in bytes:', file.size);
        console.log('File size in MB:', (file.size / (1024 * 1024)).toFixed(2));
        console.log('File name:', file.name);
        
        // Validate file size (500MB limit for media ZIP files)
        const maxSize = 500 * 1024 * 1024; // 500MB in bytes
        console.log('Max allowed size in bytes:', maxSize);
        console.log('Max allowed size in MB:', (maxSize / (1024 * 1024)).toFixed(2));
        console.log('Is file too large?', file.size > maxSize);
        
        if (file.size > maxSize) {
            showError('File is too large. Please select a file smaller than 500MB. (File size: ' + (file.size / (1024 * 1024)).toFixed(2) + 'MB)');
            return;
        }
        
        // Validate file type
        if (!file.name.toLowerCase().endsWith('.zip')) {
            showError(peiwm_ajax.strings.select_zip);
            return;
        }
        
        importMedia(file);
    });
    
    // Test Configuration
    $('#peiwm-test-config').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_test_config',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayTestResults(response.data);
                } else {
                    showError('Test failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Test failed: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Load Media Statistics
    loadMediaStats();
    
    // Refresh Media Statistics
    $('#peiwm-refresh-stats').on('click', function() {
        loadMediaStats();
    });
    
    // Delete Posts
    $('#peiwm-delete-posts').on('click', function() {
        const deleteMessage = `
            <div class="peiwm-danger-text">
                ⚠️ <strong>WARNING:</strong> This will permanently delete ALL posts from your website.
            </div>
            <p>This action cannot be undone and will remove all posts, including drafts and published content.</p>
            <p><strong>Are you absolutely sure you want to continue?</strong></p>
        `;
        showDangerConfirmation('Delete All Posts', deleteMessage)
            .then(() => {
                deleteAllPosts();
            })
            .catch(() => {});
    });
    
    // Delete Media
    $('#peiwm-delete-media').on('click', function() {
        const deleteMessage = `
            <div class="peiwm-danger-text">
                ⚠️ <strong>WARNING:</strong> This will permanently delete ALL media files from your library.
            </div>
            <p>This action cannot be undone and will remove all images, videos, and other media files.</p>
            <p><strong>Are you absolutely sure you want to continue?</strong></p>
        `;
        showDangerConfirmation('Delete All Media', deleteMessage)
            .then(() => {
                deleteAllMedia();
            })
            .catch(() => {});
    });
    
    // Helper Functions
    
    function deleteAllPosts() {
        const button = $('#peiwm-delete-posts');
        const progress = $('#peiwm-delete-posts-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        
        button.prop('disabled', true).text('Deleting...');
        progress.show();
        progressFill.css('width', '0%');
        progressText.text('Starting deletion...');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_delete_posts',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Deletion complete!');
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Deletion failed: ' + response.data.message);
                    showError('Delete failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                progressText.text('Deletion failed: ' + error);
                showError('Delete failed: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text('Delete All Posts');
            }
        });
    }
    
    function deleteAllMedia() {
        const button = $('#peiwm-delete-media');
        const progress = $('#peiwm-delete-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        button.prop('disabled', true).text('Deleting...');
        progress.show();
        progressFill.css('width', '0%');
        progressText.text('Starting deletion...');
        log.empty();
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_delete_media',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Deletion complete!');
                    addLog('✓ ' + response.data.message);
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Deletion failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message);
                    showError('Delete failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                progressText.text('Deletion failed: ' + error);
                addLog('✗ Error: ' + error);
                showError('Delete failed: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text('Delete All Media');
            }
        });
    }
    
    function importPosts(posts) {
        const progress = $('#peiwm-posts-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        progress.show();
        progressFill.css('width', '0%');
        progressText.text('Starting import...');
        log.empty();
        
        let currentIndex = 0;
        const totalPosts = posts.length;
        
        function processNextPost() {
            if (currentIndex >= totalPosts) {
                progressText.text('Import complete!');
                addLog('All posts processed successfully!', log);
                showSuccess('Posts import completed successfully!');
                return;
            }
            
            const post = posts[currentIndex];
            
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_import_post',
                    nonce: peiwm_ajax.nonce,
                    post_data: JSON.stringify(post)
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'skipped') {
                            addLog('⚠ Skipped: ' + post.post_title + ' (' + response.data.reason + ')', log);
                        } else {
                            addLog('✓ Imported: ' + post.post_title, log);
                        }
                    } else {
                        addLog('✗ Failed: ' + post.post_title + ' - ' + response.data.message, log);
                    }
                },
                error: function(xhr, status, error) {
                    addLog('✗ Error: ' + post.post_title + ' - ' + error, log);
                },
                complete: function() {
                    currentIndex++;
                    const progressPercent = Math.round((currentIndex / totalPosts) * 100);
                    progressFill.css('width', progressPercent + '%');
                    progressText.text('Importing posts... (' + currentIndex + ' of ' + totalPosts + ')');
                    
                    // Process next post with a small delay
                    setTimeout(processNextPost, 100);
                }
            });
        }
        
        processNextPost();
    }
    
    function importMedia(file) {
        const progress = $('#peiwm-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        progress.show();
        progressFill.css('width', '0%');
        progressText.text('Uploading and processing...');
        log.empty();
        
        addLog('Starting media import...', log);
        addLog('File: ' + file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)', log);
        
        const formData = new FormData();
        formData.append('action', 'peiwm_import_media_start');
        formData.append('nonce', peiwm_ajax.nonce);
        formData.append('media_file', file);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 300000, // 5 minutes timeout
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        progressFill.css('width', percentComplete + '%');
                        progressText.text('Uploading... (' + percentComplete + '%)');
                        addLog('Upload progress: ' + percentComplete + '%', log);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                addLog('Server response received', log);
                if (response.success) {
                    addLog('Starting file processing...', log);
                    processMediaFiles(response.data.batch_id, response.data.total_files);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('Error: ' + response.data.message, log);
                    showError('Import failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                addLog('AJAX Error - Status: ' + status + ', Error: ' + error, log);
                if (status === 'timeout') {
                    progressText.text('Upload timed out. The file may be too large or server is slow.');
                    addLog('Upload timed out after 5 minutes', log);
                    showError('Upload timed out. Please try with a smaller file or contact your server administrator.');
                } else if (status === 'error') {
                    progressText.text('Upload failed: ' + error);
                    addLog('Upload failed: ' + error, log);
                    showError('Upload failed: ' + error);
                } else {
                    progressText.text('Upload failed: ' + status);
                    addLog('Upload failed: ' + status, log);
                    showError('Upload failed: ' + status);
                }
            }
        });
    }
    
    function processMediaFiles(batchId, totalFiles) {
        const progress = $('#peiwm-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        let currentIndex = 0;
        
        function processNextFile() {
            if (currentIndex >= totalFiles) {
                progressText.text('Import complete!');
                addLog('All files processed successfully!', log);
                showSuccess('Media import completed successfully!');
                
                // Cleanup temporary files
                $.ajax({
                    url: peiwm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'peiwm_cleanup_media_batch',
                        nonce: peiwm_ajax.nonce,
                        batch_id: batchId
                    },
                    success: function(response) {
                        if (response.success) {
                            addLog('✓ Cleanup completed', log);
                        }
                    }
                });
                return;
            }
            
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_import_media_file',
                    nonce: peiwm_ajax.nonce,
                    batch_id: batchId,
                    file_index: currentIndex
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'skipped') {
                            addLog('⚠ Skipped: ' + response.data.filename + ' (' + response.data.reason + ')', log);
                        } else if (response.data.status === 'failed') {
                            addLog('✗ Failed: ' + response.data.filename + ' - ' + response.data.reason, log);
                        } else {
                            addLog('✓ Imported: ' + response.data.filename + ' (' + response.data.file_size_formatted + ')', log);
                        }
                    } else {
                        addLog('✗ Failed: ' + response.data.message, log);
                    }
                },
                error: function(xhr, status, error) {
                    addLog('✗ Error: ' + error, log);
                },
                complete: function() {
                    currentIndex++;
                    const progressPercent = Math.round((currentIndex / totalFiles) * 100);
                    progressFill.css('width', progressPercent + '%');
                    progressText.text('Importing media... (' + currentIndex + ' of ' + totalFiles + ')');
                    
                    // Process next file with a small delay
                    setTimeout(processNextFile, 200);
                }
            });
        }
        
        processNextFile();
    }
    
    function displayTestResults(config) {
        const results = $('#peiwm-test-results');
        let html = '<h3>Server Configuration</h3><table class="peiwm-test-table">';
        
        html += '<tr><td>PHP Version:</td><td>' + config.php_version + '</td></tr>';
        html += '<tr><td>WordPress Version:</td><td>' + config.wordpress_version + '</td></tr>';
        html += '<tr><td>Upload Max Filesize:</td><td>' + config.upload_max_filesize + '</td></tr>';
        html += '<tr><td>Post Max Size:</td><td>' + config.post_max_size + '</td></tr>';
        html += '<tr><td>Max Input Time:</td><td>' + config.max_input_time + ' seconds</td></tr>';
        html += '<tr><td>Max File Uploads:</td><td>' + config.max_file_uploads + '</td></tr>';
        html += '<tr><td>Max Execution Time:</td><td>' + config.max_execution_time + ' seconds</td></tr>';
        html += '<tr><td>Memory Limit:</td><td>' + config.memory_limit + '</td></tr>';
        html += '<tr><td>Current Memory Usage:</td><td>' + (config.current_memory_usage / 1024 / 1024).toFixed(2) + ' MB</td></tr>';
        html += '<tr><td>Peak Memory Usage:</td><td>' + (config.peak_memory_usage / 1024 / 1024).toFixed(2) + ' MB</td></tr>';
        html += '<tr><td>ZipArchive Available:</td><td>' + (config.ziparchive_available ? '✓ Yes' : '✗ No') + '</td></tr>';
        html += '<tr><td>Upload Directory Writable:</td><td>' + (config.upload_dir_writable ? '✓ Yes' : '✗ No') + '</td></tr>';
        
        html += '</table>';
        
        // Add recommendations
        html += '<h3>Recommendations</h3><ul>';
        if (parseInt(config.max_execution_time) < 300) {
            html += '<li class="peiwm-warning">⚠️ Max Execution Time is low (' + config.max_execution_time + 's). Consider increasing to 300+ seconds for large file uploads.</li>';
        }
        if (parseInt(config.max_input_time) < 300) {
            html += '<li class="peiwm-warning">⚠️ Max Input Time is low (' + config.max_input_time + 's). Consider increasing to 300+ seconds for large file uploads.</li>';
        }
        if (!config.ziparchive_available) {
            html += '<li class="peiwm-error">✗ ZipArchive is not available. This is required for media import/export.</li>';
        }
        if (!config.upload_dir_writable) {
            html += '<li class="peiwm-error">✗ Upload directory is not writable. Check permissions.</li>';
        }
        html += '</ul>';
        
        results.html(html).show();
    }
    
    function addLog(message, logContainer = null) {
        // If no specific log container is provided, try to find the active one
        if (!logContainer) {
            // Check if media progress is visible
            if ($('#peiwm-media-progress').is(':visible')) {
                logContainer = $('#peiwm-media-progress .peiwm-log');
            }
            // Check if posts progress is visible
            else if ($('#peiwm-posts-progress').is(':visible')) {
                logContainer = $('#peiwm-posts-progress .peiwm-log');
            }
            // Check if delete media progress is visible
            else if ($('#peiwm-delete-media-progress').is(':visible')) {
                logContainer = $('#peiwm-delete-media-progress .peiwm-log');
            }
            // Default to media log if none are visible
            else {
                logContainer = $('#peiwm-media-progress .peiwm-log');
            }
        }
        
        const time = new Date().toLocaleTimeString();
        logContainer.append('<div class="peiwm-log-entry">[' + time + '] ' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }
    
    function loadMediaStats() {
        const statsContainer = $('#peiwm-media-stats');
        const refreshButton = $('#peiwm-refresh-stats');
        
        statsContainer.html('<p>Loading media statistics...</p>');
        refreshButton.prop('disabled', true).text('Loading...');
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_get_media_stats',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    let html = '<div class="peiwm-stats-grid">';
                    
                    // Total files and size
                    html += '<div class="peiwm-stat-item">';
                    html += '<div class="peiwm-stat-number">' + stats.total_files + '</div>';
                    html += '<div class="peiwm-stat-label">Total Files</div>';
                    html += '</div>';
                    
                    html += '<div class="peiwm-stat-item">';
                    html += '<div class="peiwm-stat-number">' + stats.total_size_formatted + '</div>';
                    html += '<div class="peiwm-stat-label">Total Size</div>';
                    html += '</div>';
                    
                    // Largest file
                    if (stats.largest_file.name) {
                        html += '<div class="peiwm-stat-item">';
                        html += '<div class="peiwm-stat-number">' + stats.largest_file.size_formatted + '</div>';
                        html += '<div class="peiwm-stat-label">Largest File</div>';
                        html += '<div class="peiwm-stat-detail">' + stats.largest_file.name + '</div>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    
                    // File types breakdown
                    if (Object.keys(stats.file_types).length > 0) {
                        html += '<div class="peiwm-file-types">';
                        html += '<h4>File Types</h4>';
                        html += '<div class="peiwm-file-types-list">';
                        
                        let count = 0;
                        for (const [mimeType, fileCount] of Object.entries(stats.file_types)) {
                            if (count >= 5) break; // Show top 5
                            const fileType = mimeType.split('/')[1] || mimeType;
                            html += '<div class="peiwm-file-type-item">';
                            html += '<span class="peiwm-file-type-name">' + fileType.toUpperCase() + '</span>';
                            html += '<span class="peiwm-file-type-count">' + fileCount + '</span>';
                            html += '</div>';
                            count++;
                        }
                        
                        if (Object.keys(stats.file_types).length > 5) {
                            html += '<div class="peiwm-file-type-item peiwm-more">';
                            html += '<span>+' + (Object.keys(stats.file_types).length - 5) + ' more types</span>';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    statsContainer.html(html);
                } else {
                    statsContainer.html('<p class="peiwm-error">Failed to load statistics: ' + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                statsContainer.html('<p class="peiwm-error">Failed to load statistics: ' + error + '</p>');
            },
            complete: function() {
                refreshButton.prop('disabled', false).text('Refresh Stats');
            }
        });
    }
});
