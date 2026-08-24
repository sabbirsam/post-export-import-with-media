/**
 * Media Title & ALT Editor - PRO Controller
 */
jQuery(document).ready(function ($) {
    'use strict';

    let mediaData = [];
    let changedItems = {};
    let currentOffset = 0;
    let totalCount = 0;
    let searchTimeout = null;

    const batchSize = parseInt(peiwm_media_editor.batch_size) || 100;

    // Modal Utility Functions
    function showModal(type, title, message) {
        $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
        $(document).off('keydown.peiwm-modal');

        let modalId = '#peiwm-modal-overlay';
        let modalClass = '';

        switch (type) {
            case 'success':
                modalId = '#peiwm-success-modal';
                modalClass = 'peiwm-success-modal';
                break;
            case 'error':
                modalId = '#peiwm-error-modal';
                modalClass = 'peiwm-error-modal';
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
        showModal('success', (peiwm_media_editor.strings && peiwm_media_editor.strings.success) || 'Success!', message);
    }

    function showError(message) {
        showModal('error', (peiwm_media_editor.strings && peiwm_media_editor.strings.error) || 'Error', message);
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

    // Modal Close listener
    $('.peiwm-modal-close, .peiwm-modal-overlay').on('click', function (e) {
        if (e.target === this) {
            $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        }
    });

    // Save & Discard buttons visibility handler
    function updateSaveDiscardButtonsVisibility() {
        const hasChanges = Object.keys(changedItems).length > 0;
        if (hasChanges) {
            $('#peiwm-discard-changes, #peiwm-save-changes').show().css('display', 'inline-flex');
        } else {
            $('#peiwm-discard-changes, #peiwm-save-changes').hide();
        }
    }

    // Initial load
    loadMedia(0, false);

    // Event Listeners: Search & Filters
    $('#peiwm-media-search').on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
            loadMedia(0, false);
        }, 500);
    });

    $('#peiwm-alt-filter, #peiwm-sort-by').on('change', function () {
        loadMedia(0, false);
    });

    $('input[name="peiwm_edit_mode"]').on('change', function () {
        updateColumnVisibility();
    });

    // Event Listeners: Footer Actions
    $('#peiwm-load-more').on('click', function () {
        if (Object.keys(changedItems).length > 0) {
            showConfirm(
                'Unsaved Changes',
                'You have unsaved changes. Loading more items will retain your edits. Continue?',
                function () {
                    loadMedia(currentOffset, true);
                }
            );
            return;
        }
        loadMedia(currentOffset, true);
    });

    $('#peiwm-discard-changes').on('click', function () {
        if (Object.keys(changedItems).length === 0) {
            showError(peiwm_media_editor.strings.no_changes || 'No changes to discard.');
            return;
        }
        showConfirm(
            'Discard Changes',
            peiwm_media_editor.strings.confirm_discard || 'Are you sure you want to discard all pending changes?',
            function () {
                changedItems = {};
                renderTable(mediaData);
                updateSaveDiscardButtonsVisibility();
            }
        );
    });

    $('#peiwm-save-changes').on('click', function () {
        saveChanges();
    });

    // Event Listeners: CSV Actions
    $('#peiwm-export-csv').on('click', function () {
        window.location.href = peiwm_media_editor.ajax_url + '?action=peiwm_export_media_csv&nonce=' + peiwm_media_editor.nonce;
    });

    $('#peiwm-import-csv-btn').on('click', function () {
        $('#peiwm-csv-file').click();
    });

    $('#peiwm-csv-file').on('change', function () {
        if (this.files && this.files[0]) {
            importCSV(this.files[0]);
        }
    });

    // Core function: Load Media
    function loadMedia(offset, append) {
        showLoading(peiwm_media_editor.strings.loading);

        const data = {
            action: 'peiwm_load_media_editor',
            nonce: peiwm_media_editor.nonce,
            offset: offset,
            batch_size: batchSize,
            search: $('#peiwm-media-search').val(),
            alt_filter: $('#peiwm-alt-filter').val(),
            sort_by: $('#peiwm-sort-by').val()
        };

        $.post(peiwm_media_editor.ajax_url, data, function (response) {
            hideLoading();
            if (response.success) {
                currentOffset = response.data.loaded;
                totalCount = response.data.total_count;

                if (append) {
                    mediaData = mediaData.concat(response.data.media);
                } else {
                    mediaData = response.data.media;
                    changedItems = {};
                }

                renderTable(mediaData);
                updateFooter(response.data.has_more);
                updateSaveDiscardButtonsVisibility();
            } else {
                showError((peiwm_media_editor.strings.error || 'Error:') + ' ' + (response.data.message || 'Failed to load media'));
            }
        }).fail(function () {
            hideLoading();
            showError((peiwm_media_editor.strings.error || 'Error:') + ' Network failure');
        });
    }

    // Render Media Table
    function renderTable(items) {
        const tbody = $('#peiwm-media-tbody');
        tbody.empty();

        if (items.length === 0) {
            tbody.append('<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #6b7280;">' + peiwm_media_editor.strings.no_media + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const hasChange = changedItems[item.id];
            const currentTitle = hasChange && hasChange.title !== undefined ? hasChange.title : item.title;
            const currentAlt = hasChange && hasChange.alt !== undefined ? hasChange.alt : item.alt;

            const isTitleChanged = hasChange && hasChange.title !== undefined && hasChange.title !== item.title;
            const isAltChanged = hasChange && hasChange.alt !== undefined && hasChange.alt !== item.alt;
            const isRowChanged = isTitleChanged || isAltChanged;

            const rowClass = isRowChanged ? 'peiwm-changed' : '';
            const titleClass = isTitleChanged ? 'peiwm-input-changed' : '';
            const altClass = isAltChanged ? 'peiwm-input-changed' : '';

            const editUrl = item.edit_url || ('post.php?post=' + item.id + '&action=edit');

            const thumbImgHtml = item.thumb 
                ? '<img src="' + escAttr(item.thumb) + '" class="peiwm-media-thumb" alt="">' 
                : '<div class="peiwm-media-thumb" style="background: #e5e7eb; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:11px;">No image</div>';

            const thumbCellHtml = 
                '<div class="peiwm-thumb-cell" style="display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center;">' +
                    '<a href="' + escAttr(editUrl) + '" target="_blank" title="Edit Media #' + item.id + ' in WordPress">' + thumbImgHtml + '</a>' +
                    '<a href="' + escAttr(editUrl) + '" target="_blank" class="peiwm-media-edit-link" style="font-size: 11.5px; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; font-weight: 500;">' +
                        'Edit Media' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>' +
                    '</a>' +
                '</div>';

            const html = '<tr data-id="' + item.id + '" class="' + rowClass + '">' +
                '<td>' + thumbCellHtml + '</td>' +
                '<td class="peiwm-col-title">' +
                    '<input type="text" class="peiwm-title-input ' + titleClass + '" value="' + escAttr(currentTitle) + '" data-id="' + item.id + '">' +
                    '<small style="color: #9ca3af; display: block; margin-top: 2px;">' + escHtml(item.filename) + '</small>' +
                '</td>' +
                '<td class="peiwm-col-alt">' +
                    '<input type="text" class="peiwm-alt-input ' + altClass + '" value="' + escAttr(currentAlt) + '" placeholder="Enter ALT text..." data-id="' + item.id + '">' +
                '</td>' +
                '<td><small style="color: #6b7280;">' + escHtml(item.date) + '</small></td>' +
            '</tr>';

            tbody.append(html);
        });

        bindInputEvents();
        updateColumnVisibility();
    }

    // Bind real-time input tracking
    function bindInputEvents() {
        $('.peiwm-title-input').off('input').on('input', function () {
            const id = $(this).data('id');
            const val = $(this).val();
            const original = mediaData.find(m => m.id === id);

            if (!changedItems[id]) {
                changedItems[id] = { id: id };
            }

            if (original && val === original.title) {
                delete changedItems[id].title;
            } else {
                changedItems[id].title = val;
            }

            cleanChangedItem(id);
            updateRowStyle(id);
            updateSaveDiscardButtonsVisibility();
        });

        $('.peiwm-alt-input').off('input').on('input', function () {
            const id = $(this).data('id');
            const val = $(this).val();
            const original = mediaData.find(m => m.id === id);

            if (!changedItems[id]) {
                changedItems[id] = { id: id };
            }

            if (original && val === original.alt) {
                delete changedItems[id].alt;
            } else {
                changedItems[id].alt = val;
            }

            cleanChangedItem(id);
            updateRowStyle(id);
            updateSaveDiscardButtonsVisibility();
        });
    }

    function cleanChangedItem(id) {
        if (changedItems[id]) {
            const keys = Object.keys(changedItems[id]);
            if (keys.length === 1 && keys[0] === 'id') {
                delete changedItems[id];
            }
        }
    }

    function updateRowStyle(id) {
        const tr = $('tr[data-id="' + id + '"]');
        const hasChange = changedItems[id];
        const original = mediaData.find(m => m.id === id);

        if (!original) return;

        const titleInput = tr.find('.peiwm-title-input');
        const altInput = tr.find('.peiwm-alt-input');

        const isTitleChanged = hasChange && hasChange.title !== undefined && hasChange.title !== original.title;
        const isAltChanged = hasChange && hasChange.alt !== undefined && hasChange.alt !== original.alt;

        titleInput.toggleClass('peiwm-input-changed', isTitleChanged);
        altInput.toggleClass('peiwm-input-changed', isAltChanged);
        tr.toggleClass('peiwm-changed', isTitleChanged || isAltChanged);
    }

    // Toggle column visibility based on Edit Mode
    function updateColumnVisibility() {
        const mode = $('input[name="peiwm_edit_mode"]:checked').val();

        if (mode === 'both') {
            $('.peiwm-col-title, .peiwm-col-alt').show();
            $('.peiwm-media-table th:nth-child(2), .peiwm-media-table th:nth-child(3)').show();
        } else if (mode === 'title') {
            $('.peiwm-col-title').show();
            $('.peiwm-col-alt').hide();
            $('.peiwm-media-table th:nth-child(2)').show();
            $('.peiwm-media-table th:nth-child(3)').hide();
        } else if (mode === 'alt') {
            $('.peiwm-col-title').hide();
            $('.peiwm-col-alt').show();
            $('.peiwm-media-table th:nth-child(2)').hide();
            $('.peiwm-media-table th:nth-child(3)').show();
        }
    }

    // Update Footer count & buttons
    function updateFooter(hasMore) {
        const loadedCount = mediaData.length;
        const countText = 'Showing ' + loadedCount + ' of ' + totalCount + ' media files';
        $('#peiwm-media-count').text(countText);

        if (hasMore) {
            $('#peiwm-load-more').text('Load Next ' + batchSize).show();
        } else {
            $('#peiwm-load-more').hide();
        }
    }

    // Save All Changes
    function saveChanges() {
        const changesArray = Object.values(changedItems);

        if (changesArray.length === 0) {
            showError(peiwm_media_editor.strings.no_changes || 'No changes to save.');
            return;
        }

        showLoading(peiwm_media_editor.strings.saving);

        const data = {
            action: 'peiwm_save_media_changes',
            nonce: peiwm_media_editor.nonce,
            changes: JSON.stringify(changesArray)
        };

        $.post(peiwm_media_editor.ajax_url, data, function (response) {
            hideLoading();
            if (response.success) {
                showSuccess(peiwm_media_editor.strings.saved || 'Changes saved successfully.');

                // Update local mediaData state
                changesArray.forEach(function (change) {
                    const item = mediaData.find(m => m.id === change.id);
                    if (item) {
                        if (change.title !== undefined) item.title = change.title;
                        if (change.alt !== undefined) item.alt = change.alt;
                    }
                });

                changedItems = {};
                renderTable(mediaData);
                updateSaveDiscardButtonsVisibility();
            } else {
                showError((peiwm_media_editor.strings.error || 'Error:') + ' ' + (response.data.message || 'Failed to save changes'));
            }
        }).fail(function () {
            hideLoading();
            showError((peiwm_media_editor.strings.error || 'Error:') + ' Network failure');
        });
    }

    // Import CSV
    function importCSV(file) {
        showLoading('Importing CSV file...');

        const formData = new FormData();
        formData.append('action', 'peiwm_import_media_csv');
        formData.append('nonce', peiwm_media_editor.nonce);
        formData.append('csv_file', file);

        $.ajax({
            url: peiwm_media_editor.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                hideLoading();
                $('#peiwm-csv-file').val('');

                if (response.success) {
                    showSuccess(response.data.message || 'CSV imported successfully.');
                    loadMedia(0, false);
                } else {
                    showError((peiwm_media_editor.strings.error || 'Error:') + ' ' + (response.data.message || 'Import failed'));
                }
            },
            error: function () {
                hideLoading();
                $('#peiwm-csv-file').val('');
                showError((peiwm_media_editor.strings.error || 'Error:') + ' Import request failed');
            }
        });
    }

    // Helper utilities
    function showLoading(message) {
        const html = '<div class="peiwm-loading-overlay">' +
            '<div class="peiwm-loading-content">' +
                '<div class="peiwm-loading-spinner"></div>' +
                '<p style="margin: 0; font-weight: 500; color: #374151;">' + escHtml(message) + '</p>' +
            '</div>' +
        '</div>';
        $('body').append(html);
    }

    function hideLoading() {
        $('.peiwm-loading-overlay').remove();
    }

    function escHtml(text) {
        return text ? String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : '';
    }

    function escAttr(text) {
        return text ? String(text).replace(/"/g, '&quot;') : '';
    }
});
