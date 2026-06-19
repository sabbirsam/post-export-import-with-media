jQuery(document).ready(function ($) {
    'use strict';

    console.log('Pages.js loaded successfully');

    // Premium Modal handler
    $(document).on('click', '.peiwm-open-premium-modal, .peiwm-locked-section', function (e) {
        if ($(e.target).is('input, select, textarea, button:not(.peiwm-open-premium-modal), label, a')) return;
        e.preventDefault();
        e.stopPropagation();
        const modal = $('#peiwm-premium-modal');
        modal.show().addClass('peiwm-show');
        modal.find('.peiwm-premium-close, .peiwm-modal-close').off('click').on('click', function () {
            modal.removeClass('peiwm-show').hide();
        });
        modal.off('click.premium').on('click.premium', function (ev) {
            if (ev.target === this) modal.removeClass('peiwm-show').hide();
        });
        $(document).off('keydown.premium-modal').on('keydown.premium-modal', function (ev) {
            if (ev.key === 'Escape') modal.removeClass('peiwm-show').hide();
        });
    });

    // Initialize checkbox default state
    $('#peiwm-download-missing-page-images').prop('checked', true);

    // WPML Support toggle for Pages — save setting via AJAX
    $('#peiwm_enable_wpml_support_pages').on('change', function () {
        const isChecked = $(this).is(':checked') ? 1 : 0;
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_save_wpml_setting_pages',
                nonce: peiwm_ajax.nonce,
                enabled: isChecked
            },
            success: function (response) {
                if (response.success) {
                    // Setting saved successfully
                } else {
                    // Revert checkbox on error
                    $('#peiwm_enable_wpml_support_pages').prop('checked', !isChecked);
                }
            },
            error: function () {
                // Revert checkbox on error
                $('#peiwm_enable_wpml_support_pages').prop('checked', !isChecked);
            }
        });
    });

    // Modal Utility Functions (reuse from main admin.js)
    function showModal(type, title, message) {
        $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
        $(document).off('keydown.peiwm-modal');

        let modalId = '#peiwm-modal-overlay';

        switch (type) {
            case 'success':
                modalId = '#peiwm-success-modal';
                break;
            case 'error':
                modalId = '#peiwm-error-modal';
                break;
            case 'confirm':
                modalId = '#peiwm-modal-overlay';
                break;
        }

        const modal = $(modalId);
        
        if (type === 'success') {
            modal.find('#peiwm-success-message').html(message);
        } else if (type === 'error') {
            modal.find('#peiwm-error-message').html(message);
        } else {
            modal.find('#peiwm-modal-title').text(title);
            modal.find('#peiwm-modal-message').html(message);
        }
        
        modal.addClass('peiwm-show').show();

        $(document).on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });
    }

    function showSuccess(message) {
        showModal('success', 'Success!', message);
    }

    function showError(message) {
        showModal('error', 'Error', message);
    }

    function showConfirm(title, message, callback) {
        const modal = $('#peiwm-modal-overlay');
        modal.find('#peiwm-modal-title').text(title);
        modal.find('#peiwm-modal-message').html(message);
        modal.addClass('peiwm-show').show();

        $(document).on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });
        
        $('#peiwm-modal-confirm').off('click').on('click', function () {
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
            if (callback) callback();
        });
        
        $('#peiwm-modal-cancel').off('click').on('click', function () {
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        });
    }

    function addLog(message, logContainer = null, className = '') {
        if (!logContainer) {
            logContainer = $('#peiwm-pages-progress .peiwm-log');
        }

        const time = new Date().toLocaleTimeString();
        const classAttr = className ? ' class="peiwm-log-entry ' + className + '"' : ' class="peiwm-log-entry"';
        logContainer.append('<div' + classAttr + '>[' + time + '] ' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    // Export Pages
    $('#peiwm-export-pages').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        const isSelective = $('#peiwm-export-pages-selective').is(':checked');

        button.prop('disabled', true).text(peiwm_ajax.strings.processing);

        // Selective export — send all IDs, server handles it as one batch
        if (isSelective) {
            const ids = [];
            $('#peiwm-pages-export-list .peiwm-selective-checkbox:checked').each(function () {
                const id = parseInt($(this).attr('data-id'), 10);
                if (id > 0) ids.push(id);
            });
            if (ids.length === 0) {
                showError('Please select at least one page to export.');
                button.prop('disabled', false).text(originalText);
                return;
            }

            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_export_pages',
                    nonce: peiwm_ajax.nonce,
                    export_wpml_data: $('#peiwm-pages-export-wpml-data').is(':checked') ? '1' : '0',
                    post_ids: ids.join(',')
                },
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
                        showSuccess('Pages exported successfully! ' + response.data.count + ' pages exported.');
                    } else {
                        showError(response.data.message || 'Export failed');
                    }
                },
                error: function (xhr, status, error) {
                    showError('Export failed: ' + error);
                },
                complete: function () {
                    button.prop('disabled', false).text(originalText);
                }
            });
            return;
        }

        // Non-selective — chunked export using Export JSON File Size setting
        const pagesPerFile = peiwm_ajax.export_json_size ? parseInt(peiwm_ajax.export_json_size, 10) : 500;
        const exportWpml   = $('#peiwm-pages-export-wpml-data').is(':checked') ? '1' : '0';

        let currentPage  = 1;
        let fileNum      = 0;
        let totalExported = 0;
        let collectedPages = [];

        function triggerDownload(data, num) {
            const suffix   = num > 1 ? '_part' + num : '';
            const filename = 'pages-export' + suffix + '_' + new Date().toISOString().slice(0, 10) + '.json';
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url  = window.URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }

        function fetchChunk() {
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_export_pages',
                    nonce: peiwm_ajax.nonce,
                    export_wpml_data: exportWpml,
                    page: currentPage,
                    per_page: pagesPerFile
                },
                success: function (response) {
                    if (!response.success) {
                        showError(response.data.message || 'Export failed');
                        button.prop('disabled', false).text(originalText);
                        return;
                    }

                    collectedPages = collectedPages.concat(response.data.data);
                    totalExported += response.data.count;

                    if (collectedPages.length >= pagesPerFile || !response.data.has_more) {
                        fileNum++;
                        triggerDownload(collectedPages, fileNum);
                        collectedPages = [];
                    }

                    if (response.data.has_more) {
                        currentPage++;
                        setTimeout(fetchChunk, 100);
                    } else {
                        const msg = fileNum > 1
                            ? 'Export complete! ' + totalExported + ' pages in ' + fileNum + ' files.'
                            : 'Pages exported successfully! ' + totalExported + ' pages exported.';
                        showSuccess(msg);
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function (xhr, status, error) {
                    showError('Export failed: ' + error);
                    button.prop('disabled', false).text(originalText);
                }
            });
        }

        fetchChunk();
    });

    // Toggle selective export panel for pages
    $('#peiwm-export-pages-selective').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-pages-export-selective-panel').slideDown();
            loadPagesExportList();
        } else {
            $('#peiwm-pages-export-selective-panel').slideUp();
        }
    });

    // Search pages in export list
    $('#peiwm-pages-export-search').on('input', function () {
        const query = $(this).val().toLowerCase();
        $('#peiwm-pages-export-list .peiwm-selective-item').each(function () {
            $(this).toggle($(this).find('.peiwm-selective-title').text().toLowerCase().includes(query));
        });
    });

    // Select all pages export
    $('#peiwm-pages-export-select-all').on('change', function () {
        const checked = $(this).is(':checked');
        $('#peiwm-pages-export-list .peiwm-selective-item:visible .peiwm-selective-checkbox').prop('checked', checked);
        updatePagesExportCount();
    });

    function loadPagesExportList() {
        $('#peiwm-pages-export-list').html('<div class="peiwm-selective-loading"><div class="peiwm-loading-spinner"></div><p>Loading pages...</p></div>');
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: { action: 'peiwm_get_pages_list', nonce: peiwm_ajax.nonce },
            success: function (response) {
                if (response.success) {
                    renderPagesExportList(response.data.pages);
                } else {
                    $('#peiwm-pages-export-list').html('<p class="peiwm-selective-empty">Failed to load pages.</p>');
                }
            },
            error: function () {
                $('#peiwm-pages-export-list').html('<p class="peiwm-selective-empty">Error loading pages.</p>');
            }
        });
    }

    function renderPagesExportList(pages) {
        const list = $('#peiwm-pages-export-list');
        if (!pages || !pages.length) {
            list.html('<p class="peiwm-selective-empty">No pages found.</p>');
            return;
        }
        let html = '';
        pages.forEach(function (page) {
            const status = page.post_status ? '<span class="peiwm-selective-status peiwm-status-' + page.post_status + '">' + page.post_status + '</span>' : '';
            const date = page.post_date ? page.post_date.slice(0, 10) : '';
            html += '<label class="peiwm-selective-item">' +
                '<input type="checkbox" class="peiwm-selective-checkbox" data-id="' + page.ID + '" checked>' +
                '<span class="peiwm-selective-info">' +
                    '<span class="peiwm-selective-title">' + $('<div>').text(page.post_title || 'Untitled').html() + '</span>' +
                    '<span class="peiwm-selective-meta">' + date + ' ' + status + '</span>' +
                '</span>' +
            '</label>';
        });
        list.html(html);
        $('#peiwm-pages-export-select-all').prop('checked', true);
        updatePagesExportCount();
        list.off('change').on('change', '.peiwm-selective-checkbox', function () {
            updatePagesExportCount();
            const allChecked = list.find('.peiwm-selective-item:visible .peiwm-selective-checkbox:not(:checked)').length === 0;
            $('#peiwm-pages-export-select-all').prop('checked', allChecked);
        });
    }

    function updatePagesExportCount() {
        const count = $('#peiwm-pages-export-list .peiwm-selective-checkbox:checked').length;
        $('#peiwm-pages-export-selected-count').text(count + ' selected');
    }

    // Select Pages File
    $('#peiwm-select-pages-file').on('click', function () {
        $('#peiwm-pages-file').click();
    });

    $('#peiwm-pages-file').on('change', function () {
        const file = this.files[0];
        if (file) {
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                showError('Please select a JSON file.');
                return;
            }
            const label = this.files.length > 1 ? this.files.length + ' files selected' : file.name;
            $('#peiwm-select-pages-file').text(label);
            $('#peiwm-import-pages').show();
            // Always populate list when file is loaded
            loadPagesSelectionList(file);
        }
    });

    // Toggle selective panel for pages
    $('#peiwm-import-pages-selective').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-pages-selective-panel').slideDown();
        } else {
            $('#peiwm-pages-selective-panel').slideUp();
        }
    });

    // Search pages in selective list
    $('#peiwm-pages-search').on('input', function () {
        const query = $(this).val().toLowerCase();
        $('#peiwm-pages-list .peiwm-selective-item').each(function () {
            const title = $(this).find('.peiwm-selective-title').text().toLowerCase();
            $(this).toggle(title.includes(query));
        });
    });

    // Select all pages
    $('#peiwm-pages-select-all').on('change', function () {
        const checked = $(this).is(':checked');
        $('#peiwm-pages-list .peiwm-selective-item:visible .peiwm-selective-checkbox').prop('checked', checked);
        updatePagesSelectedCount();
    });

    function loadPagesSelectionList(file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const pages = JSON.parse(e.target.result);
                if (!Array.isArray(pages)) throw new Error('Invalid format');
                renderPagesSelectionList(pages);
            } catch (err) {
                $('#peiwm-pages-list').html('<p class="peiwm-selective-empty">Invalid JSON file.</p>');
            }
        };
        reader.readAsText(file);
    }

    function renderPagesSelectionList(pages) {
        const list = $('#peiwm-pages-list');
        if (!pages.length) {
            list.html('<p class="peiwm-selective-empty">No pages found in file.</p>');
            return;
        }
        let html = '';
        pages.forEach(function (page, index) {
            const status = page.post_status || 'publish';
            const date = page.post_date ? page.post_date.slice(0, 10) : '';
            html += '<div class="peiwm-selective-item" data-index="' + index + '">' +
                '<label style="display:flex;align-items:center;gap:0.5rem;flex:1;cursor:pointer;min-width:0;">' +
                    '<input type="checkbox" class="peiwm-selective-checkbox" data-index="' + index + '" checked>' +
                    '<span class="peiwm-selective-info">' +
                        '<span class="peiwm-selective-title">' + $('<div>').text(page.post_title || 'Untitled').html() + '</span>' +
                        '<span class="peiwm-selective-meta">' + date + '</span>' +
                    '</span>' +
                '</label>' +
                '<span class="peiwm-selective-status-wrap">' +
                    '<span class="peiwm-selective-status peiwm-status-' + status + '" data-index="' + index + '">' + status + '</span>' +
                    '<button type="button" class="peiwm-item-settings-btn" data-index="' + index + '" title="Change import status">⚙</button>' +
                '</span>' +
            '</div>';
        });
        list.html(html);
        $('#peiwm-pages-select-all').prop('checked', true);
        updatePagesSelectedCount();

        list.off('change').on('change', '.peiwm-selective-checkbox', function () {
            updatePagesSelectedCount();
            const allChecked = $('#peiwm-pages-list .peiwm-selective-item:visible .peiwm-selective-checkbox:not(:checked)').length === 0;
            $('#peiwm-pages-select-all').prop('checked', allChecked);
        });

        list.off('click.settings').on('click.settings', '.peiwm-item-settings-btn', function () {
            const index = parseInt($(this).attr('data-index'), 10);
            openImportSettingsModal('pages', index, pages[index]);
        });
    }

    function updatePagesSelectedCount() {
        const count = $('#peiwm-pages-list .peiwm-selective-checkbox:checked').length;
        $('#peiwm-pages-selected-count').text(count + ' selected');
    }

    // Per-item import settings for pages
    // Exposed globally so admin-batch.js can access them
    if (!window.peiwmPageImportSettings) window.peiwmPageImportSettings = {};
    const pageImportSettings = window.peiwmPageImportSettings;

    function openImportSettingsModal(type, index, item) {
        const settings = pageImportSettings;
        const current = settings[index] || { force_status: 'original' };
        const title = item.post_title || 'Untitled';
        const originalStatus = item.post_status || 'publish';

        const body = `
            <div style="text-align:left;">
                <p style="margin-bottom:1rem;color:#4a5568;">
                    <strong>${$('<div>').text(title).html()}</strong><br>
                    <small>Original status: <span class="peiwm-selective-status peiwm-status-${originalStatus}">${originalStatus}</span></small>
                </p>
                <label style="display:block;margin-bottom:0.5rem;font-weight:600;">Import as status:</label>
                <div class="peiwm-status-options">
                    <label class="peiwm-status-option">
                        <input type="radio" name="peiwm_force_status" value="original" ${current.force_status === 'original' ? 'checked' : ''}>
                        <span>Keep original <span class="peiwm-selective-status peiwm-status-${originalStatus}">${originalStatus}</span></span>
                    </label>
                    <label class="peiwm-status-option">
                        <input type="radio" name="peiwm_force_status" value="publish" ${current.force_status === 'publish' ? 'checked' : ''}>
                        <span><span class="peiwm-selective-status peiwm-status-publish">publish</span></span>
                    </label>
                    <label class="peiwm-status-option">
                        <input type="radio" name="peiwm_force_status" value="draft" ${current.force_status === 'draft' ? 'checked' : ''}>
                        <span><span class="peiwm-selective-status peiwm-status-draft">draft</span></span>
                    </label>
                    <label class="peiwm-status-option">
                        <input type="radio" name="peiwm_force_status" value="private" ${current.force_status === 'private' ? 'checked' : ''}>
                        <span><span class="peiwm-selective-status peiwm-status-private">private</span></span>
                    </label>
                </div>
                <p style="margin-top:1rem;font-size:0.8rem;color:#718096;">
                    If this page already exists and the status differs, it will be updated to the selected status.
                </p>
            </div>
        `;

        const modal = $('#peiwm-modal-overlay');
        modal.find('.peiwm-modal-header h3').text('Import Settings');
        modal.find('.peiwm-modal-body p').html(body);
        modal.find('.peiwm-modal').removeClass('peiwm-warning-modal peiwm-danger-modal');
        modal.show().addClass('peiwm-show');

        const confirmBtn = modal.find('#peiwm-modal-confirm');
        const cancelBtn = modal.find('#peiwm-modal-cancel');
        confirmBtn.off('click').text('Apply');
        cancelBtn.off('click').text('Cancel');

        confirmBtn.on('click', function () {
            const selected = modal.find('input[name="peiwm_force_status"]:checked').val() || 'original';
            pageImportSettings[index] = { force_status: selected };
            const badge = $('#peiwm-pages-list .peiwm-selective-item[data-index="' + index + '"] .peiwm-selective-status');
            const displayStatus = selected === 'original' ? originalStatus : selected;
            badge.attr('class', 'peiwm-selective-status peiwm-status-' + displayStatus).text(displayStatus);
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        });

        cancelBtn.on('click', function () {
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        });

        modal.find('.peiwm-modal-close').off('click').on('click', function () {
            modal.removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        });

        modal.off('click').on('click', function (e) {
            if (e.target === this) { modal.removeClass('peiwm-show').hide(); $(document).off('keydown.peiwm-modal'); }
        });

        $(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') { modal.removeClass('peiwm-show').hide(); $(document).off('keydown.peiwm-modal'); }
        });
    }

    // Import Pages
    $('#peiwm-import-pages').on('click', function () {
        const button = $(this);
        const fileInput = $('#peiwm-pages-file')[0];
        if (!fileInput.files.length) {
            showError('Please select a file to import.');
            return;
        }

        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function (e) {
            try {
                // Strip Unicode line/paragraph separators that some editors flag as unusual
                const raw = e.target.result.replace(/[\u2028\u2029]/g, ' ');
                let pages = JSON.parse(raw);
                if (!Array.isArray(pages)) {
                    throw new Error('Invalid file format');
                }

                // Always attach per-item force_status settings
                pages = pages.map(function (page, i) {
                    const s = pageImportSettings[i];
                    return Object.assign({}, page, { _force_status: s ? s.force_status : 'original' });
                });

                // Filter to selected pages if selective mode is on
                if ($('#peiwm-import-pages-selective').is(':checked')) {
                    const selectedIndexes = [];
                    $('#peiwm-pages-list .peiwm-selective-checkbox:checked').each(function () {
                        selectedIndexes.push(parseInt($(this).attr('data-index'), 10));
                    });
                    if (selectedIndexes.length === 0) {
                        showError('Please select at least one page to import.');
                        return;
                    }
                    pages = pages.filter((_, i) => selectedIndexes.includes(i));
                }

                // Disable button, show progress, then scroll to it
                button.prop('disabled', true).text('Importing...');
                $('#peiwm-pages-progress').show();
                $('html, body').animate({ scrollTop: $('#peiwm-pages-progress').offset().top - 40 }, 400);

                startPagesImport(pages, function () {
                    button.prop('disabled', false).text('Start Import');
                });
            } catch (error) {
                showError('Invalid JSON file: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    });

    function startPagesImport(pages, onComplete) {
        const progress = $('#peiwm-pages-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Starting import...');
        
        let currentIndex = 0;
        const totalPages = pages.length;
        const failedPages = [];
        
        addLog('Starting import of ' + totalPages + ' page(s)...', log);
        
        function processNextPage() {
            if (currentIndex >= totalPages) {
                progressText.text('Import complete!');

                if (failedPages.length > 0) {
                    addLog('⚠ ' + failedPages.length + ' page(s) failed due to timeout or errors.', log);
                    
                    /* const retryBtn = $('<button type="button" class="button peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">' +
                        '🔄 Some were missed — retry ' + failedPages.length + ' failed page(s) now' +
                    '</button>'); */

                    const retryBtn = $('<button type="button" class="button peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">' +
                        '🔄 Some missed — Let’s retry' + '</button>');

                    log.after(retryBtn);
                    retryBtn.on('click', function () {
                        retryBtn.remove();
                        addLog('🔄 Retrying ' + failedPages.length + ' failed page(s)...', log);
                        const retryData = failedPages.splice(0);
                        startPagesImport(retryData, onComplete);
                    });
                    /* showSuccess('Import done! ' + totalPages + ' processed. ' + failedPages.length + ' need retry.'); */
                    showSuccess('Import done! ' + totalPages + ' processed. A few items may need retry.');
                } else {
                    addLog('All pages processed successfully!', log);
                    showSuccess('Pages import completed successfully!');
                    if (typeof onComplete === 'function') onComplete();
                }
                return;
            }

            const page = pages[currentIndex];
            const downloadMissingImages = $('#peiwm-download-missing-page-images').is(':checked') ? '1' : '0';
            const checkMediaLibrary = $('#peiwm-check-media-library-pages').is(':checked') ? '1' : '0';
            
            // Show what we're about to do
            addLog('📝 Processing: ' + page.post_title, log, 'peiwm-log-info');
            
            // If download is enabled and page has images, show download intent
            if (downloadMissingImages === '1') {
                let imageCount = 0;
                if (page.content_images) imageCount += page.content_images.length;
                if (page.featured_image) imageCount += 1;
                
                if (imageCount > 0) {
                    addLog('  🔍 Checking ' + imageCount + ' image(s) - will download if missing', log, 'peiwm-log-info');
                }
            }
            
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
                            addLog('⚠ Skipped: ' + page.post_title + ' (' + response.data.reason + ')', log);
                        } else if (response.data.status === 'updated') {
                            addLog('🔄 Updated: ' + page.post_title + ' (' + response.data.reason + ')', log, 'peiwm-log-info');
                        } else {
                            let logMessage = '✓ Imported: ' + page.post_title;
                            
                            // Show missing images information
                            if (response.data.missing_images && response.data.missing_images.length > 0) {
                                const downloadStatus = response.data.download_enabled ? 'download attempted' : 'download disabled';
                                logMessage += ' (⚠ ' + response.data.missing_images.length + ' image(s) not found - ' + downloadStatus + ')';
                            }
                            
                            addLog(logMessage, log);
                        }
                    } else {
                        failedPages.push(page);
                        addLog('✗ Failed: ' + page.post_title + ' - ' + response.data.message, log);
                    }
                },
                error: function (xhr, status, error) {
                    failedPages.push(page);
                    addLog('✗ Error: ' + page.post_title + ' - ' + error, log);
                },
                complete: function () {
                    currentIndex++;
                    const progressPercent = Math.round((currentIndex / totalPages) * 100);
                    progressFill.css('width', progressPercent + '%');
                    progressText.text('Importing pages... (' + currentIndex + ' of ' + totalPages + ')');

                    // Process next page with a small delay
                    setTimeout(processNextPage, 100);
                }
            });
        }
        
        processNextPage();
    }

    // Delete All Pages
    $('#peiwm-delete-pages').on('click', function () {
        console.log('Delete pages button clicked');
        const deleteMessage = `
            <div class="peiwm-danger-text">
                ⚠ <strong>WARNING:</strong> This will permanently delete ALL pages from your website.
            </div>
            <p>This action cannot be undone and will remove all pages, including drafts and published content.</p>
            <p>Are you sure you want to continue?</p>
        `;
        
        showConfirm('Delete All Pages', deleteMessage, function () {
            const progress = $('#peiwm-delete-pages-progress');
            const progressFill = progress.find('.peiwm-progress-fill');
            const progressText = progress.find('.peiwm-progress-text');
            const log = progress.find('.peiwm-log');
            
            progress.show();
            log.empty();
            progressFill.css('width', '0%');
            progressText.text('Deleting pages...');
            
            addLog('Starting page deletion...', log);
            
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_delete_pages',
                    nonce: peiwm_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        progressFill.css('width', '100%');
                        progressText.text('Deletion complete!');
                        addLog('✓ ' + response.data.message, log);
                        showSuccess(response.data.message);
                    } else {
                        progressText.text('Deletion failed: ' + response.data.message);
                        addLog('✗ Error: ' + response.data.message, log);
                        showError('Delete failed: ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    progressText.text('Deletion failed: ' + error);
                    addLog('✗ Error: ' + error, log);
                    showError('Delete failed: ' + error);
                }
            });
        });
    });

    // Close modal handlers
    $('.peiwm-modal-close, .peiwm-modal-overlay').on('click', function (e) {
        if (e.target === this) {
            $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        }
    });

    // ── Advanced Options Toggle ──────────────────────────────────────────────
    $(document).on('click', '.peiwm-advanced-toggle', function () {
        var $btn    = $(this);
        var targetId = $btn.attr('aria-controls');
        var $panel  = $('#' + targetId);
        var isOpen  = $btn.hasClass('is-open');

        $btn.toggleClass('is-open', !isOpen)
            .attr('aria-expanded', String(!isOpen));

        $panel.toggleClass('is-open', !isOpen)
              .attr('aria-hidden', String(isOpen));
    });

    // ── PRO inline row click → show toast (only for locked rows) ─────────────
    $(document).on('click', '.peiwm-pro-inline-row.is-locked', function (e) {
        // Don't fire if user clicked a real link or checkbox
        if ($(e.target).is('a, input, label')) return;

        var $section = $(this).closest('.peiwm-export-section, .peiwm-import-section, .peiwm-section');
        var $toast   = $section.find('.peiwm-pro-toast');
        if ($toast.length) {
            $toast.show().addClass('is-visible');
            setTimeout(function() {
                if ($toast[0]) {
                    $toast[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 50);
        }
    });

    // ── Toast close button ────────────────────────────────────────────────────
    $(document).on('click', '.peiwm-pro-toast-close', function () {
        $(this).closest('.peiwm-pro-toast').removeClass('is-visible').fadeOut(200);
    });

    // ── Keyboard: Enter/Space on toggle ──────────────────────────────────────
    $(document).on('keydown', '.peiwm-advanced-toggle', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
});