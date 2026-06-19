/**
 * Batch Processing for Posts, Pages, and Media
 * 
 * @package Post_Export_Import_With_Media
 * @since 1.3.0
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Check if batch mode is enabled
    const batchEnabled = typeof peiwm_batch_settings !== 'undefined' && peiwm_batch_settings.enabled;

    if (!batchEnabled) {
        return; // Exit if batch mode is not enabled
    }

    // Global image cache to avoid re-checking same images
    const imageCache = {
        existing: {},
        missing: {},
        checked: false
    };

    // Override export posts button
    $('#peiwm-export-posts').off('click').on('click', function () {
        // If selective mode is on, use chunked selective export
        if ($('#peiwm-export-posts-selective').is(':checked')) {
            const button = $(this);
            const originalText = button.text();
            const ids = [];
            $('#peiwm-posts-export-list .peiwm-selective-checkbox:checked').each(function () {
                const id = parseInt($(this).attr('data-id'), 10);
                if (id > 0) ids.push(id);
            });
            if (ids.length === 0) {
                showError('Please select at least one post to export.');
                return;
            }
            button.prop('disabled', true).text('Exporting...');
            $('#peiwm-posts-progress').show();
            $('html, body').animate({ scrollTop: $('#peiwm-posts-progress').offset().top - 40 }, 400);

            const ajaxChunkSize = 50;
            let allData = [];
            let selectiveOffset = 0; // tracks position in ids array

            function exportChunk() {
                // Pre-slice IDs in JS - avoids sending all IDs every request and offset confusion
                const chunkIds = ids.slice(selectiveOffset, selectiveOffset + ajaxChunkSize);
                if (chunkIds.length === 0) {
                    // All done
                    const blob = new Blob([JSON.stringify(allData, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'posts_export_' + new Date().toISOString().slice(0, 10) + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                    showSuccess('Posts exported successfully! (' + allData.length + ' posts)');
                    button.prop('disabled', false).text(originalText);
                    return;
                }

                button.text('Exporting... (' + allData.length + ' of ' + ids.length + ' posts)');
                $.ajax({
                    url: peiwm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'peiwm_export_posts_chunk',
                        nonce: peiwm_ajax.nonce,
                        offset: 0,           // always 0 - IDs are pre-sliced
                        chunk_size: chunkIds.length,
                        post_ids: chunkIds.join(','),
                        export_wpml_data: $('#peiwm-export-wpml-data').is(':checked') ? '1' : '0'
                    },
                    success: function (response) {
                        if (response.success) {
                            allData = allData.concat(response.data.data);
                            selectiveOffset += response.data.data.length;
                            if (selectiveOffset < ids.length) {
                                setTimeout(exportChunk, 100);
                            } else {
                                const blob = new Blob([JSON.stringify(allData, null, 2)], { type: 'application/json' });
                                const url = window.URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.href = url;
                                link.download = 'posts_export_' + new Date().toISOString().slice(0, 10) + '.json';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                window.URL.revokeObjectURL(url);
                                showSuccess('Posts exported successfully! (' + allData.length + ' posts)');
                                button.prop('disabled', false).text(originalText);
                            }
                        } else {
                            showError('Export failed: ' + response.data.message);
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function (xhr, status, error) {
                        showError('Export failed: ' + error);
                        button.prop('disabled', false).text(originalText);
                    }
                });
            }
            exportChunk();
            return;
        }
        batchExportPosts();
    });

    // Override export pages button (if exists)
    $('#peiwm-export-pages').off('click').on('click', function () {
        // If selective mode is on, use regular selective export (not batch)
        if ($('#peiwm-export-pages-selective').is(':checked')) {
            const button = $(this);
            const originalText = button.text();
            const ids = [];
            $('#peiwm-pages-export-list .peiwm-selective-checkbox:checked').each(function () {
                const id = parseInt($(this).attr('data-id'), 10);
                if (id > 0) ids.push(id);
            });
            if (ids.length === 0) {
                showError('Please select at least one page to export.');
                return;
            }
            button.prop('disabled', true).text('Exporting...');
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: { action: 'peiwm_export_pages', nonce: peiwm_ajax.nonce, post_ids: ids.join(',') },
                success: function (response) {
                    if (response.success) {
                        const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'pages-export-' + new Date().toISOString().slice(0, 10) + '.json';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        showSuccess('Pages exported successfully! (' + response.data.count + ' pages)');
                    } else {
                        showError('Export failed: ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) { showError('Export failed: ' + error); },
                complete: function () { button.prop('disabled', false).text(originalText); }
            });
            return;
        }
        batchExportPages();
    });

    // Override export media button
    $('#peiwm-export-media').off('click').on('click', function () {
        batchExportMedia();
    });

    // Override import posts button - supports multiple JSON files
    $('#peiwm-import-posts').off('click').on('click', function () {
        const button = $(this);
        const fileInput = $('#peiwm-posts-file')[0];
        if (!fileInput.files.length) {
            showError(peiwm_ajax.strings.select_file);
            return;
        }

        const files = Array.from(fileInput.files);
        const totalFiles = files.length;
        const isSelective = $('#peiwm-import-posts-selective').is(':checked');

        button.prop('disabled', true).text(totalFiles > 1 ? 'Reading files...' : 'Importing...');
        $('#peiwm-posts-progress').show();
        $('html, body').animate({ scrollTop: $('#peiwm-posts-progress').offset().top - 40 }, 400);

        let allFilesData = [];
        let filesRead = 0;

        files.forEach(function (file, fileIdx) {
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    let data = JSON.parse(e.target.result);
                    if (!Array.isArray(data)) data = [];
                    allFilesData[fileIdx] = data;
                } catch (err) {
                    allFilesData[fileIdx] = [];
                }
                filesRead++;
                if (filesRead === totalFiles) {
                    // Always use the shared startImportFromAllFiles with batchImportPosts
                    const startImport = window.peiwmStartImportFromAllFiles;
                    if (startImport) {
                        startImport(allFilesData, files, isSelective, button, totalFiles, batchImportPosts);
                    } else {
                        let idx = 0;
                        function next() {
                            if (idx >= allFilesData.length) { button.prop('disabled', false).text('Start Import'); return; }
                            batchImportPosts(allFilesData[idx] || [], files[idx] ? files[idx].name : ('file' + (idx + 1)), 1, 1, function () { idx++; next(); });
                        }
                        next();
                    }
                }
            };
            reader.readAsText(file);
        });
    });

    // Override import pages button - supports multiple JSON files
    $('#peiwm-import-pages').off('click').on('click', function () {
        const button = $(this);
        const fileInput = $('#peiwm-pages-file')[0];
        if (!fileInput.files.length) {
            showError(peiwm_ajax.strings.select_file);
            return;
        }

        const files = Array.from(fileInput.files);
        const totalFiles = files.length;
        let currentFileIndex = 0;

        button.prop('disabled', true).text(totalFiles > 1 ? 'Importing file 1 of ' + totalFiles + '...' : 'Importing...');
        $('#peiwm-pages-progress').show();
        $('html, body').animate({ scrollTop: $('#peiwm-pages-progress').offset().top - 40 }, 400);

        function processNextFile() {
            if (currentFileIndex >= totalFiles) {
                button.prop('disabled', false).text('Start Import');
                if (totalFiles > 1) showSuccess('All ' + totalFiles + ' files imported successfully!');
                return;
            }

            const file = files[currentFileIndex];
            if (totalFiles > 1) button.text('Importing file ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...');

            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    let data = JSON.parse(e.target.result);
                    if (!Array.isArray(data)) {
                        showError('File ' + file.name + ': Invalid format.');
                        currentFileIndex++;
                        processNextFile();
                        return;
                    }

                    const pageSettings = window.peiwmPageImportSettings || {};
                    data = data.map(function (page, i) {
                        const s = pageSettings[i];
                        return Object.assign({}, page, { _force_status: s ? s.force_status : 'original' });
                    });

                    if ($('#peiwm-import-pages-selective').is(':checked')) {
                        const selectedIndexes = [];
                        $('#peiwm-pages-list .peiwm-selective-checkbox:checked').each(function () {
                            selectedIndexes.push(parseInt($(this).attr('data-index'), 10));
                        });
                        if (selectedIndexes.length === 0 && totalFiles === 1) {
                            showError('Please select at least one page to import.');
                            button.prop('disabled', false).text('Start Import');
                            return;
                        }
                        if (selectedIndexes.length > 0) {
                            data = data.filter((_, i) => selectedIndexes.includes(i));
                        }
                    }

                    batchImportPages(data, function () {
                        currentFileIndex++;
                        processNextFile();
                    });
                } catch (error) {
                    showError('File ' + file.name + ': ' + error.message);
                    currentFileIndex++;
                    processNextFile();
                }
            };
            reader.readAsText(file);
        }

        processNextFile();
    });

    // Override import media button
    $('#peiwm-import-media').off('click').on('click', function () {
        const button = $(this);
        const fileInput = $('#peiwm-media-file')[0];
        if (!fileInput.files.length) {
            showError(peiwm_ajax.strings.select_file);
            return;
        }

        const files = Array.from(fileInput.files);
        const maxSize = 500 * 1024 * 1024; // 500MB hard limit

        // Get server upload limits
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_get_upload_limits',
                nonce: peiwm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const serverLimit = response.data.limit_bytes;
                    const serverLimitMB = response.data.limit_mb;
                    
                    // Validate all files first
                    for (const file of files) {
                        if (!file.name.toLowerCase().endsWith('.zip')) {
                            showError(file.name + ': ' + peiwm_ajax.strings.select_zip);
                            return;
                        }
                        
                        // Check against server limit first
                        if (file.size > serverLimit) {
                            showError(file.name + ' is too large (' + (file.size / (1024 * 1024)).toFixed(1) + 'MB). ' +
                                'Your server upload limit is ' + serverLimitMB + 'MB. ' +
                                'Contact your hosting provider to increase upload_max_filesize and post_max_size in php.ini.');
                            return;
                        }
                        
                        // Check against plugin limit
                        if (file.size > maxSize) {
                            showError(file.name + ' is too large (' + (file.size / (1024 * 1024)).toFixed(1) + 'MB). Max 500MB per file.');
                            return;
                        }
                    }

                    const totalFiles = files.length;
                    let currentFileIndex = 0;
                    button.prop('disabled', true);

                    function processNextMediaFile() {
                        if (currentFileIndex >= totalFiles) {
                            button.prop('disabled', false).text('Start Import');
                            if (totalFiles > 1) showSuccess('All ' + totalFiles + ' ZIP files imported successfully!');
                            return;
                        }
                        const file = files[currentFileIndex];
                        button.text(totalFiles > 1 ? 'Importing ZIP ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...' : 'Importing...');
                        batchImportMedia(file, function () {
                            currentFileIndex++;
                            processNextMediaFile();
                        });
                    }

                    processNextMediaFile();
                } else {
                    // Fallback to basic validation if we can't get server limits
                    for (const file of files) {
                        if (!file.name.toLowerCase().endsWith('.zip')) {
                            showError(file.name + ': ' + peiwm_ajax.strings.select_zip);
                            return;
                        }
                        if (file.size > maxSize) {
                            showError(file.name + ' is too large (' + (file.size / (1024 * 1024)).toFixed(1) + 'MB). Max 500MB per file.');
                            return;
                        }
                    }

                    const totalFiles = files.length;
                    let currentFileIndex = 0;
                    button.prop('disabled', true);

                    function processNextMediaFile() {
                        if (currentFileIndex >= totalFiles) {
                            button.prop('disabled', false).text('Start Import');
                            if (totalFiles > 1) showSuccess('All ' + totalFiles + ' ZIP files imported successfully!');
                            return;
                        }
                        const file = files[currentFileIndex];
                        button.text(totalFiles > 1 ? 'Importing ZIP ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...' : 'Importing...');
                        batchImportMedia(file, function () {
                            currentFileIndex++;
                            processNextMediaFile();
                        });
                    }

                    processNextMediaFile();
                }
            },
            error: function() {
                // Fallback to basic validation if AJAX fails
                for (const file of files) {
                    if (!file.name.toLowerCase().endsWith('.zip')) {
                        showError(file.name + ': ' + peiwm_ajax.strings.select_zip);
                        return;
                    }
                    if (file.size > maxSize) {
                        showError(file.name + ' is too large (' + (file.size / (1024 * 1024)).toFixed(1) + 'MB). Max 500MB per file.');
                        return;
                    }
                }

                const totalFiles = files.length;
                let currentFileIndex = 0;
                button.prop('disabled', true);

                function processNextMediaFile() {
                    if (currentFileIndex >= totalFiles) {
                        button.prop('disabled', false).text('Start Import');
                        if (totalFiles > 1) showSuccess('All ' + totalFiles + ' ZIP files imported successfully!');
                        return;
                    }
                    const file = files[currentFileIndex];
                    button.text(totalFiles > 1 ? 'Importing ZIP ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...' : 'Importing...');
                    batchImportMedia(file, function () {
                        currentFileIndex++;
                        processNextMediaFile();
                    });
                }

                processNextMediaFile();
            }
        });
    });

    // Batch Export Posts
    function batchExportPosts() {
        const button = $('#peiwm-export-posts');
        const originalText = button.text();
        const progress = $('#peiwm-posts-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        button.prop('disabled', true).text('Initializing...');
        progress.show();
        $('html, body').animate({ scrollTop: progress.offset().top - 40 }, 400);
        progressFill.css('width', '0%');
        log.empty();

        // Start batch export
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_batch_export_posts_start',
                nonce: peiwm_ajax.nonce,
                export_wpml_data: $('#peiwm-export-wpml-data').is(':checked') ? '1' : '0'
            },
            success: function (response) {
                if (response.success) {
                    const batchId = response.data.batch_id;
                    const totalBatches = response.data.total_batches;
                    const totalCount = response.data.total_count;

                    addLog('📦 Batch export started: ' + totalCount + ' posts in ' + totalBatches + ' batches', log);
                    
                    processBatchExport(batchId, 0, totalBatches, 'posts', button, originalText);
                } else {
                    showError('Export failed: ' + response.data.message);
                    button.prop('disabled', false).text(originalText);
                    progress.hide();
                }
            },
            error: function (xhr, status, error) {
                showError('Export failed: ' + error);
                button.prop('disabled', false).text(originalText);
                progress.hide();
            }
        });
    }

    // Batch Export Pages
    function batchExportPages() {
        const button = $('#peiwm-export-pages');
        const originalText = button.text();
        const progress = $('#peiwm-pages-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        button.prop('disabled', true).text('Initializing...');
        progress.show();
        $('html, body').animate({ scrollTop: progress.offset().top - 40 }, 400);
        progressFill.css('width', '0%');
        log.empty();

        // Start batch export
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_batch_export_pages_start',
                nonce: peiwm_ajax.nonce,
                export_wpml_data: $('#peiwm-pages-export-wpml-data').is(':checked') ? '1' : '0'
            },
            success: function (response) {
                if (response.success) {
                    const batchId = response.data.batch_id;
                    const totalBatches = response.data.total_batches;
                    const totalCount = response.data.total_count;

                    addLog('📦 Batch export started: ' + totalCount + ' pages in ' + totalBatches + ' batches', log);
                    
                    processBatchExport(batchId, 0, totalBatches, 'pages', button, originalText);
                } else {
                    showError('Export failed: ' + response.data.message);
                    button.prop('disabled', false).text(originalText);
                    progress.hide();
                }
            },
            error: function (xhr, status, error) {
                showError('Export failed: ' + error);
                button.prop('disabled', false).text(originalText);
                progress.hide();
            }
        });
    }

    // Batch Export Media
    function batchExportMedia() {
        const button = $('#peiwm-export-media');
        const originalText = button.text();
        const progress = $('#peiwm-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        const exportAllSizes = $('#peiwm-export-all-image-sizes').is(':checked');

        // Collect advanced filter params (shared helper from admin.js)
        const advancedParams = (typeof getMediaExportParams === 'function') ? getMediaExportParams() : {};
        if (advancedParams === null) {
            showError('Please select at least one post to export media from.');
            return;
        }

        // Validate date range error
        if ($('#peiwm-media-export-daterange').is(':checked') && $('#peiwm-media-daterange-error').is(':visible')) {
            showError('Please fix the date range error before exporting.');
            return;
        }

        button.prop('disabled', true).text('Initializing...');
        progress.show();
        progressFill.css('width', '0%');
        log.empty();

        // Start batch export
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: Object.assign({
                action: 'peiwm_batch_export_media_start',
                nonce: peiwm_ajax.nonce,
                export_all_sizes: exportAllSizes ? '1' : '0'
            }, advancedParams),
            success: function (response) {
                if (response.success) {
                    const batchId = response.data.batch_id;
                    const totalBatches = response.data.total_batches;
                    const totalCount = response.data.total_count;

                    addLog('📦 Batch export started: ' + totalCount + ' media files in ' + totalBatches + ' batches', log);
                    
                    processBatchExport(batchId, 0, totalBatches, 'media', button, originalText);
                } else {
                    showError('Export failed: ' + response.data.message);
                    button.prop('disabled', false).text(originalText);
                    progress.hide();
                }
            },
            error: function (xhr, status, error) {
                showError('Export failed: ' + error);
                button.prop('disabled', false).text(originalText);
                progress.hide();
            }
        });
    }

    // Process batch export
    function processBatchExport(batchId, currentBatch, totalBatches, type, button, originalText) {
        const progress = $('#peiwm-' + type + '-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        if (currentBatch >= totalBatches) {
            progressFill.css('width', '100%');
            progressText.text('Export complete!');
            addLog('✓ All batches exported successfully!', log);
            showSuccess('Batch export completed! ' + totalBatches + ' file(s) created.');
            button.prop('disabled', false).text(originalText);
            return;
        }

        const action = type === 'posts' ? 'peiwm_batch_export_posts_process' : 
                       type === 'pages' ? 'peiwm_batch_export_pages_process' : 
                       'peiwm_batch_export_media_process';
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: peiwm_ajax.nonce,
                batch_id: batchId,
                batch_number: currentBatch
            },
            success: function (response) {
                if (response.success) {
                    const batchNum = currentBatch + 1;
                    let logMsg = '✓ Batch ' + batchNum + '/' + totalBatches + ': ' + response.data.filename;
                    
                    // Show clear breakdown for media exports
                    if (type === 'media') {
                        if (response.data.export_all_sizes) {
                            logMsg += ' (' + response.data.unique_count + ' media, ' + response.data.count + ' total files with sizes, ' + response.data.file_size + ')';
                        } else {
                            logMsg += ' (' + response.data.count + ' items, ' + response.data.file_size + ')';
                        }
                    } else {
                        logMsg += ' (' + response.data.count + ' items, ' + response.data.file_size + ')';
                    }
                    
                    // Show warning if files were skipped in this batch
                    if (response.data.skipped_count && response.data.skipped_count > 0) {
                        logMsg += ' - ⚠️ ' + response.data.skipped_count + ' file(s) skipped (missing)';
                    }
                    
                    addLog(logMsg, log);
                    
                    // Auto-download the file
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Update progress
                    const progressPercent = Math.round(((currentBatch + 1) / totalBatches) * 100);
                    progressFill.css('width', progressPercent + '%');
                    progressText.text('Exporting batch ' + batchNum + ' of ' + totalBatches + '... (' + progressPercent + '%)');

                    // Process next batch with delay
                    setTimeout(function() {
                        processBatchExport(batchId, currentBatch + 1, totalBatches, type, button, originalText);
                    }, peiwm_batch_settings.delay || 500);
                } else {
                    addLog('✗ Batch ' + (currentBatch + 1) + ' failed: ' + response.data.message, log);
                    showError('Batch export failed at batch ' + (currentBatch + 1));
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function (xhr, status, error) {
                addLog('✗ Batch ' + (currentBatch + 1) + ' error: ' + error, log);
                showError('Batch export error: ' + error);
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Batch Import Posts
    function batchImportPosts(posts, fileLabel, fileIndex, totalFiles, onComplete) {
        // Normalise arguments — legacy callers may pass (posts, onComplete)
        if (typeof fileLabel === 'function') {
            onComplete  = fileLabel;
            fileLabel   = 'file 1';
            fileIndex   = 1;
            totalFiles  = 1;
        }
        if (typeof fileIndex !== 'number') fileIndex  = 1;
        if (typeof totalFiles !== 'number') totalFiles = 1;
        if (!fileLabel) fileLabel = 'file ' + fileIndex;

        const progress = $('#peiwm-posts-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        progress.show();
        progressFill.css('width', '0%');
        log.empty();

        // Generation counter - if a new batchImportPosts call starts, old callbacks are ignored
        const myGeneration = (window._peiwmBatchPostsGen = (window._peiwmBatchPostsGen || 0) + 1);

        const batchSize = peiwm_batch_settings.post_batch_size || 20;
        const totalPosts = posts.length;
        const totalBatches = Math.ceil(totalPosts / batchSize);
        // FIX: Hard cap concurrent at 3 — 10 workers * 60s = server overload / 502
        const rawConcurrent = peiwm_batch_settings.concurrent_requests || 3;
        const concurrentRequests = Math.min(rawConcurrent, 3);

        // Add time tracking info container
        if (!$('#peiwm-batch-time-info').length) {
            progress.find('.peiwm-progress-bar').after('<div id="peiwm-batch-time-info" style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-radius: 4px; font-size: 13px;"></div>');
        }
        const timeInfo = $('#peiwm-batch-time-info');

        addLog('📦 Batch import started: ' + totalPosts + ' posts in ' + totalBatches + ' batches' + (totalFiles > 1 ? ' — File ' + fileIndex + '/' + totalFiles + ': ' + fileLabel : ''), log);
        addLog('⚡ Processing ' + concurrentRequests + ' posts simultaneously', log);
        progressText.text('Starting batch import…');

        const startTime = Date.now();
        let currentBatch = 0;
        let processedCount = 0;
        const failedPosts = [];
        let completed = false; // prevent processNextBatch after onComplete fires // Track all failed/timeout posts for retry

        function updateTimeInfo() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const elapsedMin = Math.floor(elapsed / 60);
            const elapsedSec = elapsed % 60;
            
            let remaining = 0;
            let remainingMin = 0;
            let remainingSec = 0;
            
            if (processedCount > 0) {
                const avgTimePerPost = elapsed / processedCount;
                remaining = Math.floor(avgTimePerPost * (totalPosts - processedCount));
                remainingMin = Math.floor(remaining / 60);
                remainingSec = remaining % 60;
            }

            const timeHtml = '<strong>⏱️ Time:</strong> Elapsed: ' + elapsedMin + 'm ' + elapsedSec + 's' +
                           (processedCount > 0 ? ' | Remaining: ~' + remainingMin + 'm ' + remainingSec + 's' : '') +
                           ' | <strong>📊 Status:</strong> ' + processedCount + ' of ' + totalPosts + ' posts completed' +
                           ' | <strong>🚀 Speed:</strong> ' + (processedCount > 0 ? (processedCount / (elapsed || 1)).toFixed(1) : '0') + ' posts/sec';
            
            timeInfo.html(timeHtml);
        }

        function processNextBatch() {
            if (completed) return; // Guard: don't run after onComplete has fired
            if (currentBatch >= totalBatches) {
                completed = true; // Mark as done before calling onComplete
                progressFill.css('width', '100%');
                progressText.text('Import complete!');
                updateTimeInfo();
                addLog('✓ All batches imported successfully!', log);

                if (failedPosts.length > 0) {
                    const failedCount = failedPosts.length;
                    addLog('⚠ ' + failedCount + ' post(s) failed due to timeout or errors.', log);
                    const retryBtn = $('<button type="button" class="button button-secondary peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">' +
                        '🔄 Some were missed \u2014 retry ' + failedCount + ' failed post(s) now' +
                    '</button>');
                    log.after(retryBtn);
                    retryBtn.on('click', function () {
                        retryBtn.remove();
                        const retryData = failedPosts.splice(0);
                        addLog('🔄 Retrying ' + retryData.length + ' failed post(s)...', log);
                        batchImportPosts(retryData, fileLabel, fileIndex, totalFiles, onComplete);
                    });
                    showSuccess('Batch import done! ' + totalPosts + ' processed. ' + failedCount + ' need retry.');
                    // Always advance to next file even if there are failures
                    // User can retry failed posts separately
                    if (typeof onComplete === 'function') onComplete(failedCount);
                } else {
                    showSuccess('Batch import completed! ' + totalPosts + ' posts processed.');
                    if (typeof onComplete === 'function') onComplete();
                }

                return;
            }

            const startIndex = currentBatch * batchSize;
            const endIndex = Math.min(startIndex + batchSize, totalPosts);
            const batchPosts = posts.slice(startIndex, endIndex);
            const batchNum = currentBatch + 1;

            addLog('📝 Processing batch ' + batchNum + '/' + totalBatches + ' (' + batchPosts.length + ' posts)...', log);

            let batchProcessed = 0;
            let batchImported = 0;
            let batchSkipped = 0;
            let batchFailed = 0;
            let batchDone = false; // guard against double-firing when concurrent requests finish together
            let activeRequests = 0;
            let currentIndex = 0;

            function processNextPost() {
                // Start multiple requests concurrently
                while (activeRequests < concurrentRequests && currentIndex < batchPosts.length) {
                    const post = batchPosts[currentIndex];
                    currentIndex++;
                    activeRequests++;

                    const downloadMissingImages = $('#peiwm-download-missing-images').is(':checked') ? '1' : '0';
                    const checkMediaLibrary = $('#peiwm-check-media-library').is(':checked') ? '1' : '0';

                    // BUG FIX: Track whether this specific request already handled failure
                    // to prevent double-counting in both error() AND complete() callbacks.
                    let requestFailed = false;

                    $.ajax({
                        url: peiwm_ajax.ajax_url,
                        type: 'POST',
                        timeout: 60000, // FIX: Reduced from 90s to 60s — prevents server pile-up. Each concurrent request holds a PHP worker; 10×90s = server collapse.
                        data: {
                            action: 'peiwm_import_post',
                            nonce: peiwm_ajax.nonce,
                            post_data: JSON.stringify(post),
                            download_missing_images: downloadMissingImages,
                            check_media_library: checkMediaLibrary,
                            force_status: post._force_status || 'original',
                            peiwm_smart_author_mapping: $('#peiwm_smart_author_mapping').is(':checked') ? '1' : '0',
                            peiwm_author_fallback: $('input[name="peiwm_author_fallback"]:checked').val() || 'current_user',
                            peiwm_enable_wpml_support: $('#peiwm_enable_wpml_support').is(':checked') ? '1' : '0'
                        },
                        success: function (response) {
                            if (response.success) {
                                let logMessage = '';
                                
                                if (response.data.status === 'skipped') {
                                    batchSkipped++;
                                    logMessage = '  ⚠ Skipped: ' + post.post_title;
                                } else if (response.data.status === 'updated') {
                                    batchImported++;
                                    logMessage = '  🔄 Updated: ' + post.post_title + ' (' + response.data.reason + ')';
                                } else {
                                    batchImported++;
                                    logMessage = '  ✓ Imported: ' + post.post_title;
                                }
                                
                                // Add language info if available
                                if (response.data.language_info && response.data.language_info.success) {
                                    logMessage += ' | ' + response.data.language_info.message;
                                } else if (response.data.language_info && !response.data.language_info.success) {
                                    logMessage += ' | ⚠ ' + response.data.language_info.message;
                                }
                                
                                addLog(logMessage, log);
                            } else {
                                // FIX: Only mark failed here in success callback (server responded but with error)
                                requestFailed = true;
                                batchFailed++;
                                failedPosts.push(post);
                                addLog('  ✗ Failed: ' + post.post_title, log);
                            }
                        },
                        error: function (xhr, status, error) {
                            // FIX: Mark failed here — complete() will NOT push again because requestFailed = true
                            requestFailed = true;
                            batchFailed++;
                            failedPosts.push(post);
                            const errorMsg = status === 'timeout' ? 'timeout (server busy)' : (xhr.status === 502 ? '502 Bad Gateway — server overloaded' : error);
                            addLog('  ✗ Error: ' + post.post_title + ' - ' + errorMsg, log);
                        },
                        complete: function () {
                            // Ignore callbacks from previous batchImportPosts calls
                            if (window._peiwmBatchPostsGen !== myGeneration) return;

                            activeRequests--;
                            batchProcessed++;

                            // FIX: Do NOT push to failedPosts here — already handled in error() above.
                            // Previously both error() and complete() pushed the post, doubling retry count.

                            // Cap batchProcessed to avoid exceeding batchPosts.length
                            const safeBatchProcessed = Math.min(batchProcessed, batchPosts.length);

                            // Update progress bar — use actual global processedCount + current batch progress
                            const totalProcessedSoFar = Math.min(processedCount + safeBatchProcessed, totalPosts);
                            const progressPercent = Math.round((totalProcessedSoFar / totalPosts) * 100);
                            progressFill.css('width', progressPercent + '%');
                            const fileLabel2 = totalFiles > 1 ? ' — File ' + fileIndex + '/' + totalFiles : '';
                            // FIX: Show the real current batch (currentBatch+1) not stale batchNum from outer closure
                            const displayBatch = currentBatch + 1;
                            progressText.text('Processing: ' + totalProcessedSoFar + ' of ' + totalPosts + ' posts (' + progressPercent + '%) - Batch ' + displayBatch + '/' + totalBatches + fileLabel2);
                            
                            // Update time info
                            updateTimeInfo();

                            if (batchProcessed >= batchPosts.length && !batchDone) {
                                batchDone = true; // prevent double-firing from concurrent completions
                                // Batch complete
                                addLog('✓ Batch ' + batchNum + ' complete: ' + batchImported + ' imported, ' + batchSkipped + ' skipped, ' + batchFailed + ' failed', log);
                                
                                currentBatch++;
                                processedCount += batchPosts.length;
                                
                                const finalProgressPercent = Math.round((processedCount / totalPosts) * 100);
                                progressFill.css('width', finalProgressPercent + '%');
                                progressText.text('Batch ' + batchNum + '/' + totalBatches + ' complete. Processed ' + processedCount + ' of ' + totalPosts + ' posts (' + finalProgressPercent + '%)' + (totalFiles > 1 ? ' — File ' + fileIndex + '/' + totalFiles : ''));

                                // FIX: Add a backoff delay after any batch had failures to let the server recover
                                const batchDelay = batchFailed > 0
                                    ? Math.max(peiwm_batch_settings.delay || 500, 1500)
                                    : (peiwm_batch_settings.delay || 500);
                                setTimeout(processNextBatch, batchDelay);
                            } else if (batchProcessed < batchPosts.length) {
                                // Continue processing
                                processNextPost();
                            }
                        }
                    });
                }
            }

            processNextPost();
        }

        processNextBatch();
    }

    // Batch Import Pages
    function batchImportPages(pages, onComplete) {
        const progress = $('#peiwm-pages-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        progress.show();
        progressFill.css('width', '0%');
        log.empty();

        const batchSize = peiwm_batch_settings.page_batch_size || 20;
        const totalPages = pages.length;
        const totalBatches = Math.ceil(totalPages / batchSize);
        // FIX: Hard cap concurrent at 3 — 10 workers * 60s = server overload / 502
        const rawConcurrent = peiwm_batch_settings.concurrent_requests || 3;
        const concurrentRequests = Math.min(rawConcurrent, 3);

        addLog('📦 Batch import started: ' + totalPages + ' pages in ' + totalBatches + ' batches', log);
        progressText.text('Starting batch import...');

        let currentBatch = 0;
        let processedCount = 0;
        const failedPages = []; // Track all failed/timeout pages for retry

        function processNextBatch() {
            if (currentBatch >= totalBatches) {
                progressFill.css('width', '100%');
                progressText.text('Import complete!');
                addLog('✓ All batches imported successfully!', log);

                if (failedPages.length > 0) {
                    const failedCount = failedPages.length;
                    addLog('⚠ ' + failedCount + ' page(s) failed due to timeout or errors.', log);

                    const retryBtn = $('<button type="button" class="button button-secondary peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">' +
                        '🔄 Some were missed \u2014 retry ' + failedCount + ' failed page(s) now' +
                    '</button>');

                    log.after(retryBtn);
                    retryBtn.on('click', function () {
                        retryBtn.remove();
                        const retryData = failedPages.splice(0);
                        addLog('🔄 Retrying ' + retryData.length + ' failed page(s)...', log);
                        batchImportPages(retryData, onComplete);
                    });
                    showSuccess('Batch import done! ' + totalPages + ' processed. ' + failedCount + ' need retry.');
                    // Always advance to next file
                    if (typeof onComplete === 'function') onComplete(failedCount);
                } else {
                    showSuccess('Batch import completed! ' + totalPages + ' pages processed.');
                    if (typeof onComplete === 'function') onComplete(0);
                }

                return;
            }

            const startIndex = currentBatch * batchSize;
            const endIndex = Math.min(startIndex + batchSize, totalPages);
            const batchPages = pages.slice(startIndex, endIndex);
            const batchNum = currentBatch + 1;

            addLog('📝 Processing batch ' + batchNum + '/' + totalBatches + ' (' + batchPages.length + ' pages)...', log);

            let batchProcessed = 0;
            let batchImported = 0;
            let batchSkipped = 0;
            let batchFailed = 0;
            let activeRequests = 0;
            let currentIndex = 0;

            function processNextPage() {
                // Start multiple requests concurrently
                while (activeRequests < concurrentRequests && currentIndex < batchPages.length) {
                    const page = batchPages[currentIndex];
                    currentIndex++;
                    activeRequests++;

                    const downloadMissingImages = $('#peiwm-download-missing-images').is(':checked') ? '1' : '0';
                    const checkMediaLibrary = $('#peiwm-check-media-library').is(':checked') ? '1' : '0';

                    $.ajax({
                        url: peiwm_ajax.ajax_url,
                        type: 'POST',
                        timeout: 90000, // 90 seconds per page
                        data: {
                            action: 'peiwm_import_page',
                            nonce: peiwm_ajax.nonce,
                            page_data: JSON.stringify(page),
                            download_missing_images: downloadMissingImages,
                            check_media_library: checkMediaLibrary,
                            force_status: page._force_status || 'original'
                        },
                        success: function (response) {
                            if (response.success) {
                                if (response.data.status === 'skipped') {
                                    batchSkipped++;
                                    addLog('  ⚠ Skipped: ' + page.post_title, log);
                                } else if (response.data.status === 'updated') {
                                    batchImported++;
                                    addLog('  🔄 Updated: ' + page.post_title + ' (' + response.data.reason + ')', log);
                                } else {
                                    batchImported++;
                                    addLog('  ✓ Imported: ' + page.post_title, log);
                                }
                            } else {
                                batchFailed++;
                                failedPages.push(page);
                                addLog('  ✗ Failed: ' + page.post_title, log);
                            }
                        },
                        error: function (xhr, status, error) {
                            batchFailed++;
                            failedPages.push(page);
                            addLog('  ✗ Error: ' + page.post_title + ' - ' + error, log);
                        },
                        complete: function () {
                            activeRequests--;
                            batchProcessed++;

                            // Update progress bar in real-time after each page
                            const totalProcessedSoFar = processedCount + batchProcessed;
                            const progressPercent = Math.round((totalProcessedSoFar / totalPages) * 100);
                            progressFill.css('width', progressPercent + '%');
                            progressText.text('Processing: ' + totalProcessedSoFar + ' of ' + totalPages + ' pages (' + progressPercent + '%) - Batch ' + batchNum + '/' + totalBatches);

                            if (batchProcessed >= batchPages.length) {
                                // Batch complete
                                addLog('✓ Batch ' + batchNum + ' complete: ' + batchImported + ' imported, ' + batchSkipped + ' skipped, ' + batchFailed + ' failed', log);
                                
                                currentBatch++;
                                processedCount += batchPages.length;
                                
                                const finalProgressPercent = Math.round((processedCount / totalPages) * 100);
                                progressFill.css('width', finalProgressPercent + '%');
                                progressText.text('Batch ' + batchNum + '/' + totalBatches + ' complete. Processed ' + processedCount + ' of ' + totalPages + ' pages (' + finalProgressPercent + '%)');

                                // Process next batch with minimal delay
                                setTimeout(processNextBatch, peiwm_batch_settings.delay || 500);
                            } else {
                                // Continue processing
                                processNextPage();
                            }
                        }
                    });
                }
            }

            processNextPage();
        }

        processNextBatch();
    }

    // Batch Import Media
    function batchImportMedia(file, onComplete) {
        const progress = $('#peiwm-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        progress.show();
        progressFill.css('width', '0%');
        log.empty();

        addLog('📦 Starting batch media import...', log);
        addLog('File: ' + file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)', log);
        progressText.text('Uploading and processing...');

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
            timeout: 300000,
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        progressFill.css('width', Math.min(percentComplete, 20) + '%');
                        progressText.text('Uploading... (' + percentComplete + '%)');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                addLog('✓ Upload complete, starting batch processing...', log);
                if (response.success) {
                    const batchId = response.data.batch_id;
                    const totalFiles = response.data.total_files;
                    const blockedFiles = response.data.blocked_files || [];
                    const blockedCount = response.data.blocked_count || 0;
                    const batchSize = peiwm_batch_settings.media_batch_size || 50;
                    const totalBatches = Math.ceil(totalFiles / batchSize);

                    // Initialize stats with blocked count
                    const stats = { imported: 0, skipped: 0, failed: 0, blocked: blockedCount };

                    // Show blocked files warning if any
                    if (blockedCount > 0) {
                        addLog('⚠️ ' + blockedCount + ' file(s) blocked due to disallowed file type', log, 'peiwm-log-warning');
                        blockedFiles.slice(0, 10).forEach(function(filename) {
                            addLog('  ✗ Blocked: ' + filename, log, 'peiwm-log-warning');
                        });
                        if (blockedCount > 10) {
                            addLog('  ... and ' + (blockedCount - 10) + ' more blocked files', log, 'peiwm-log-warning');
                        }
                        addLog('💡 To allow these file types, go to Settings and update "Allowed Media File Types"', log, 'peiwm-log-info');
                    }

                    addLog('📦 Processing ' + totalFiles + ' files in ' + totalBatches + ' batches', log);
                    
                    processBatchMediaImport(batchId, 0, totalFiles, totalBatches, batchSize, onComplete, stats);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log);
                    showError('Import failed: ' + response.data.message);
                    if (typeof onComplete === 'function') onComplete();
                }
            },
            error: function (xhr, status, error) {
                let errorMsg = error;
                
                // Try to extract more detailed error from response
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed.data && parsed.data.message) {
                            errorMsg = parsed.data.message;
                        }
                    } catch (e) {
                        // If response is not JSON, check for common error patterns
                        if (xhr.responseText.includes('Maximum execution time')) {
                            errorMsg = 'Server timeout - file may be too large or server resources limited';
                        } else if (xhr.responseText.includes('memory')) {
                            errorMsg = 'Server memory limit exceeded - try a smaller file';
                        } else if (xhr.status === 413) {
                            errorMsg = 'File too large - exceeds server upload limit';
                        } else if (xhr.status === 0) {
                            errorMsg = 'Network error - check your connection';
                        }
                    }
                }
                
                addLog('✗ Upload error: ' + errorMsg, log, 'peiwm-log-error');
                if (status === 'timeout') {
                    showError('Upload timed out. Please try with a smaller file.');
                } else {
                    showError('Upload failed: ' + errorMsg);
                }
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    // Process batch media import
    function processBatchMediaImport(batchId, currentIndex, totalFiles, totalBatches, batchSize, onComplete, stats) {
        const progress = $('#peiwm-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        const concurrentRequests = Math.min(peiwm_batch_settings.concurrent_requests || 10, 10); // Cap media at 10 for safety

        // Initialize stats on first call
        if (!stats) {
            stats = { imported: 0, skipped: 0, failed: 0, blocked: 0 };
        }

        if (currentIndex >= totalFiles) {
            progressFill.css('width', '100%');
            progressText.text('Import complete!');
            
            // Show detailed summary
            const summaryParts = [];
            if (stats.imported > 0) summaryParts.push(stats.imported + ' imported');
            if (stats.skipped > 0) summaryParts.push(stats.skipped + ' skipped');
            if (stats.failed > 0) summaryParts.push(stats.failed + ' failed');
            if (stats.blocked > 0) summaryParts.push(stats.blocked + ' blocked');
            
            const summaryMsg = '✓ Import complete! ' + summaryParts.join(', ');
            addLog(summaryMsg, log, 'peiwm-log-success');
            showSuccess('Batch media import completed! ' + totalFiles + ' files processed.');

            // Cleanup
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_cleanup_media_batch',
                    nonce: peiwm_ajax.nonce,
                    batch_id: batchId
                },
                success: function (response) {
                    if (response.success) {
                        addLog('✓ Cleanup completed', log);
                    }
                },
                complete: function () {
                    if (typeof onComplete === 'function') onComplete();
                }
            });
            return;
        }

        const currentBatch = Math.floor(currentIndex / batchSize) + 1;
        const endIndex = Math.min(currentIndex + concurrentRequests, totalFiles);
        let activeRequests = 0;
        let processedInThisCall = 0;

        for (let i = currentIndex; i < endIndex; i++) {
            activeRequests++;
            const fileIndex = i;

            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_import_media_file',
                    nonce: peiwm_ajax.nonce,
                    batch_id: batchId,
                    file_index: fileIndex
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.status === 'skipped') {
                            stats.skipped++;
                            addLog('  ⚠ Skipped: ' + response.data.filename + ' (' + response.data.reason + ')', log, 'peiwm-log-warning');
                        } else if (response.data.status === 'failed') {
                            stats.failed++;
                            addLog('  ✗ Failed: ' + response.data.filename + ' - ' + response.data.reason, log, 'peiwm-log-error');
                        } else {
                            stats.imported++;
                            addLog('  ✓ Imported: ' + response.data.filename + ' (' + response.data.file_size_formatted + ')', log);
                        }
                    } else {
                        stats.failed++;
                        addLog('  ✗ Failed: ' + response.data.message, log, 'peiwm-log-error');
                    }
                    
                    // Update progress bar in real-time after each file
                    const completedFiles = fileIndex + 1;
                    const progressPercent = Math.round((completedFiles / totalFiles) * 100);
                    const adjustedPercent = 20 + Math.round(progressPercent * 0.8);
                    progressFill.css('width', adjustedPercent + '%');
                    progressText.text('Processing: ' + completedFiles + ' of ' + totalFiles + ' files (' + progressPercent + '%) - Batch ' + currentBatch + '/' + totalBatches);
                },
                error: function (xhr, status, error) {
                    stats.failed++;
                    addLog('  ✗ Error: ' + error, log, 'peiwm-log-error');
                },
                complete: function () {
                    activeRequests--;
                    processedInThisCall++;

                    // Show batch completion message
                    if ((fileIndex + 1) % batchSize === 0 || fileIndex + 1 === totalFiles) {
                        const filesInBatch = Math.min(batchSize, totalFiles - (currentBatch - 1) * batchSize);
                        addLog('✓ Batch ' + currentBatch + '/' + totalBatches + ' complete (' + filesInBatch + ' files)', log);
                    }

                    // When all concurrent requests are done, process next batch
                    if (activeRequests === 0) {
                        processBatchMediaImport(batchId, currentIndex + processedInThisCall, totalFiles, totalBatches, batchSize, onComplete, stats);
                    }
                }
            });
        }
    }

    // Helper functions - use existing modal functions from main admin.js
    function addLog(message, logContainer, className) {
        if (!logContainer) return;
        const time = new Date().toLocaleTimeString();
        const classAttr = className ? ' class="peiwm-log-entry ' + className + '"' : ' class="peiwm-log-entry"';
        logContainer.append('<div' + classAttr + '>[' + time + '] ' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    function showSuccess(message) {
        // Use the existing showModal function from admin.js
        if (typeof window.showModal === 'function') {
            window.showModal('success', 'Success!', message);
        } else if (typeof showModal === 'function') {
            showModal('success', 'Success!', message);
        } else {
            // Fallback to custom modal
            showCustomModal('success', message);
        }
    }

    function showError(message) {
        // Use the existing showModal function from admin.js
        if (typeof window.showModal === 'function') {
            window.showModal('error', 'Error', message);
        } else if (typeof showModal === 'function') {
            showModal('error', 'Error', message);
        } else {
            // Fallback to custom modal
            showCustomModal('error', message);
        }
    }

    function showCustomModal(type, message) {
        const modalId = type === 'success' ? '#peiwm-success-modal' : '#peiwm-error-modal';
        const modal = $(modalId);
        
        if (modal.length) {
            modal.find('.peiwm-modal-body p').html(message);
            modal.show().addClass('peiwm-show');
            
            modal.find('.peiwm-modal-close').off('click').on('click', function () {
                modal.removeClass('peiwm-show');
                setTimeout(function () {
                    modal.hide();
                }, 300);
            });
        }
    }
});