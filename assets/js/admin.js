jQuery(document).ready(function ($) {
    'use strict';

    // Initialize checkbox default states
    $('#peiwm-check-media-library').prop('checked', true);
    $('#peiwm-download-missing-images').prop('checked', true);

    // Author mapping toggle - show/hide fallback options (PRO only; disabled on free)
    $('#peiwm_smart_author_mapping').on('change', function () {
        if ($(this).prop('disabled')) return; // locked on free plan
        if ($(this).is(':checked')) {
            $('#peiwm-author-fallback-options').slideDown(200);
        } else {
            $('#peiwm-author-fallback-options').slideUp(200);
        }
    });

    // WPML Support toggle - save setting via AJAX
    $('#peiwm_enable_wpml_support').on('change', function () {
        const isChecked = $(this).is(':checked') ? 1 : 0;
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'peiwm_save_wpml_setting',
                nonce: peiwmData.nonce,
                enabled: isChecked
            },
            success: function (response) {
                if (response.success) {
                    // Setting saved successfully
                } else {
                    // Revert checkbox on error
                    $('#peiwm_enable_wpml_support').prop('checked', !isChecked);
                }
            },
            error: function () {
                // Revert checkbox on error
                $('#peiwm_enable_wpml_support').prop('checked', !isChecked);
            }
        });
    });

    // Modal Utility Functions
    function showModal(type, title, message) {
        // Always close any open modal overlays first
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
                confirmBtn.on('click', function () {
                    hideModal(modalId);
                    resolve();
                });
                cancelBtn.on('click', function () {
                    hideModal(modalId);
                    reject();
                });
                // Show modal
                modal.show().addClass('peiwm-show');
                // Handle close button
                modal.find('.peiwm-modal-close').off('click').on('click', function () {
                    hideModal(modalId);
                    reject();
                });
                // Handle overlay click to close
                modal.off('click').on('click', function (e) {
                    if (e.target === this) {
                        hideModal(modalId);
                        reject();
                    }
                });
                // Handle escape key
                $(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function (e) {
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
            modal.find('.peiwm-modal-close').off('click').on('click', function () {
                hideModal(modalId);
            });
            // Handle overlay click to close
            modal.off('click').on('click', function (e) {
                if (e.target === this) {
                    hideModal(modalId);
                }
            });
            // Handle escape key
            $(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function (e) {
                if (e.key === 'Escape') {
                    hideModal(modalId);
                }
            });
        }
    }

    function hideModal(modalId) {
        const modal = $(modalId);
        modal.removeClass('peiwm-show');
        setTimeout(function () {
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


    // Premium Modal - triggered by any locked section or PRO badge click
    $(document).on('click', '.peiwm-open-premium-modal, .peiwm-locked-section', function (e) {
        // Don't trigger if clicking a real interactive element inside
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

    // File input handlers
    $('#peiwm-select-posts-file').on('click', function () {
        $('#peiwm-posts-file').click();
    });

    $('#peiwm-select-media-file').on('click', function () {
        $('#peiwm-media-file').click();
    });

    $('#peiwm-posts-file').on('change', function () {
        if (this.files.length > 0) {
            const label = this.files.length > 1 ? this.files.length + ' files selected' : this.files[0].name;
            $('#peiwm-select-posts-file').text(label);
            $('#peiwm-import-posts').show();
            // Load ALL selected files and merge posts into one list
            loadPostsSelectionListFromFiles(Array.from(this.files));
        } else {
            $('#peiwm-import-posts').hide();
        }
    });

    // Toggle selective panel for posts
    $('#peiwm-import-posts-selective').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-posts-selective-panel').slideDown();
            // If list is already populated (file was loaded), nothing to do
            // If not, the empty state message guides the user
        } else {
            $('#peiwm-posts-selective-panel').slideUp();
        }
    });

    // Search posts in selective list
    $('#peiwm-posts-search').on('input', function () {
        const query = $(this).val().toLowerCase();
        $('#peiwm-posts-list .peiwm-selective-item').each(function () {
            const title = $(this).find('.peiwm-selective-title').text().toLowerCase();
            $(this).toggle(title.includes(query));
        });
    });

    // Select all posts
    $('#peiwm-posts-select-all').on('change', function () {
        const checked = $(this).is(':checked');
        $('#peiwm-posts-list .peiwm-selective-item:visible .peiwm-selective-checkbox').prop('checked', checked);
        updatePostsSelectedCount();
    });

    // Load posts from a single file (kept for backward compat)
    function loadPostsSelectionList(file) {
        loadPostsSelectionListFromFiles([file]);
    }

    // Load and merge posts from ALL selected files
    function loadPostsSelectionListFromFiles(files) {
        $('#peiwm-posts-list').html('<div class="peiwm-selective-loading"><div class="peiwm-loading-spinner"></div><p>Loading posts from ' + files.length + ' file(s)...</p></div>');

        let allPosts = [];
        let loaded = 0;
        let errors = 0;
        // Store file boundaries so import knows which file each post came from
        const fileBoundaries = []; // [{fileIdx, startIndex, endIndex}]

        files.forEach(function (file, fileIdx) {
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const posts = JSON.parse(e.target.result);
                    if (!Array.isArray(posts)) throw new Error('Invalid format');
                    const startIndex = allPosts.length;
                    posts.forEach(function (post) {
                        post._sourceFile = fileIdx;
                        post._fileLocalIndex = posts.indexOf(post); // local index within this file
                    });
                    allPosts = allPosts.concat(posts);
                    fileBoundaries.push({ fileIdx: fileIdx, startIndex: startIndex, endIndex: allPosts.length - 1 });
                } catch (err) {
                    errors++;
                }
                loaded++;
                if (loaded === files.length) {
                    if (errors > 0 && allPosts.length === 0) {
                        $('#peiwm-posts-list').html('<p class="peiwm-selective-empty">Invalid JSON file(s).</p>');
                    } else {
                        if (errors > 0) {
                            $('#peiwm-posts-list').before('<p style="color:#d97706;font-size:0.85rem;margin-bottom:0.5rem;">⚠️ ' + errors + ' file(s) could not be read.</p>');
                        }
                        // Store boundaries globally for import handler
                        window.peiwmPostFileBoundaries = fileBoundaries;
                        renderPostsSelectionList(allPosts);
                    }
                }
            };
            reader.readAsText(file);
        });
    }

    function renderPostsSelectionList(posts) {
        const list = $('#peiwm-posts-list');
        if (!posts.length) {
            list.html('<p class="peiwm-selective-empty">No posts found in file.</p>');
            return;
        }
        let html = '';
        posts.forEach(function (post, index) {
            const status = post.post_status || 'publish';
            const date = post.post_date ? post.post_date.slice(0, 10) : '';
            const sourceFile = post._sourceFile !== undefined ? post._sourceFile : 0;
            html += '<div class="peiwm-selective-item" data-index="' + index + '" data-source-file="' + sourceFile + '">' +
                '<label style="display:flex;align-items:center;gap:0.5rem;flex:1;cursor:pointer;min-width:0;">' +
                    '<input type="checkbox" class="peiwm-selective-checkbox" data-index="' + index + '" checked>' +
                    '<span class="peiwm-selective-info">' +
                        '<span class="peiwm-selective-title">' + $('<div>').text(post.post_title || 'Untitled').html() + '</span>' +
                        '<span class="peiwm-selective-meta">' + date + '</span>' +
                    '</span>' +
                '</label>' +
                '<span class="peiwm-selective-status-wrap">' +
                    '<span class="peiwm-selective-status peiwm-status-' + status + '" data-index="' + index + '">' + status + '</span>' +
                    '<button type="button" class="peiwm-item-settings-btn" data-index="' + index + '" title="Change import status">⚙️</button>' +
                '</span>' +
            '</div>';
        });
        list.html(html);
        $('#peiwm-posts-select-all').prop('checked', true);
        updatePostsSelectedCount();

        list.off('change').on('change', '.peiwm-selective-checkbox', function () {
            updatePostsSelectedCount();
            const allChecked = $('#peiwm-posts-list .peiwm-selective-item:visible .peiwm-selective-checkbox:not(:checked)').length === 0;
            $('#peiwm-posts-select-all').prop('checked', allChecked);
        });

        list.off('click.settings').on('click.settings', '.peiwm-item-settings-btn', function () {
            const index = parseInt($(this).attr('data-index'), 10);
            const post = posts[index];
            openImportSettingsModal('posts', index, post);
        });
    }

    function updatePostsSelectedCount() {
        const count = $('#peiwm-posts-list .peiwm-selective-checkbox:checked').length;
        $('#peiwm-posts-selected-count').text(count + ' selected');
    }

    // Per-item import settings storage: { index: { force_status: 'publish'|'draft'|'original' } }
    // Exposed globally so admin-batch.js can access them
    window.peiwmPostImportSettings = {};
    window.peiwmPageImportSettings = {};
    const postImportSettings = window.peiwmPostImportSettings;
    const pageImportSettings = window.peiwmPageImportSettings;

    function openImportSettingsModal(type, index, item) {
        const settings = type === 'posts' ? postImportSettings : pageImportSettings;
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
                    If this item already exists and the status differs, it will be updated to the selected status.
                </p>
            </div>
        `;

        // Use existing modal
        const modal = $('#peiwm-modal-overlay');
        modal.find('.peiwm-modal-header h3').text('Import Settings');
        modal.find('.peiwm-modal-body p').html(body);
        modal.find('.peiwm-modal').removeClass('peiwm-warning-modal peiwm-danger-modal');
        modal.show().addClass('peiwm-show');

        // Override confirm button
        const confirmBtn = modal.find('#peiwm-modal-confirm');
        const cancelBtn = modal.find('#peiwm-modal-cancel');
        confirmBtn.off('click').text('Apply');
        cancelBtn.off('click').text('Cancel');

        confirmBtn.on('click', function () {
            const selected = modal.find('input[name="peiwm_force_status"]:checked').val() || 'original';
            if (type === 'posts') {
                postImportSettings[index] = { force_status: selected };
                // Update the status badge in the list
                const badge = $('#peiwm-posts-list .peiwm-selective-item[data-index="' + index + '"] .peiwm-selective-status');
                const displayStatus = selected === 'original' ? originalStatus : selected;
                badge.attr('class', 'peiwm-selective-status peiwm-status-' + displayStatus).text(displayStatus);
            } else {
                pageImportSettings[index] = { force_status: selected };
                const badge = $('#peiwm-pages-list .peiwm-selective-item[data-index="' + index + '"] .peiwm-selective-status');
                const displayStatus = selected === 'original' ? originalStatus : selected;
                badge.attr('class', 'peiwm-selective-status peiwm-status-' + displayStatus).text(displayStatus);
            }
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
            if (e.target === this) {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });

        $(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });
    }

    $('#peiwm-media-file').on('change', function () {
        if (this.files.length > 0) {
            const label = this.files.length > 1
                ? this.files.length + ' ZIP files selected'
                : this.files[0].name;
            $('#peiwm-select-media-file').text(label);
            $('#peiwm-import-media').show();
        } else {
            $('#peiwm-import-media').hide();
        }
    });

    // Toggle selective export panel for posts
    $('#peiwm-export-posts-selective').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-posts-export-selective-panel').slideDown();
            loadPostsExportList();
        } else {
            $('#peiwm-posts-export-selective-panel').slideUp();
        }
    });

    // -- NEW: Date range filter ------------------------------------------------
    window.peiwmDateFilter = { date_from: '', date_to: '' };

    $('#peiwm-export-posts-daterange').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-daterange-filter-ui').slideDown(200);
            if ($('#peiwm-posts-export-selective-panel').is(':hidden')) {
                $('#peiwm-posts-export-selective-panel').slideDown();
                loadPostsExportList();
            }
        } else {
            $('#peiwm-daterange-filter-ui').slideUp(200);
            window.peiwmDateFilter = { date_from: '', date_to: '' };
            $('#peiwm-daterange-summary').hide();
            $('#peiwm-daterange-error').hide();
            if ($('#peiwm-posts-export-selective-panel').is(':visible')) {
                loadPostsExportList();
            }
        }
    });

    $('#peiwm-apply-date-filter').on('click', function () {
        const btn       = $(this);
        const dateFrom  = $('#peiwm-export-date-from').val().trim();
        const dateTo    = $('#peiwm-export-date-to').val().trim();
        const errorEl   = $('#peiwm-daterange-error');
        const summaryEl = $('#peiwm-daterange-summary');

        if (!dateFrom && !dateTo) {
            errorEl.text('Please enter at least a From date or a To date.').show();
            summaryEl.hide();
            return;
        }
        if (dateFrom && dateTo && dateFrom > dateTo) {
            errorEl.text('"From" date cannot be after "To" date.').show();
            summaryEl.hide();
            return;
        }
        errorEl.hide();

        window.peiwmDateFilter = { date_from: dateFrom, date_to: dateTo };

        if ($('#peiwm-posts-export-selective-panel').is(':hidden')) {
            $('#peiwm-posts-export-selective-panel').slideDown();
        }

        const origText = btn.text();
        btn.prop('disabled', true).text('Applying...');

        loadPostsExportList(function (totalShown) {
            btn.prop('disabled', false).text(origText);
            const parts = [];
            if (dateFrom) parts.push('from ' + dateFrom);
            if (dateTo)   parts.push('to ' + dateTo);
            summaryEl.text('Showing ' + totalShown + ' posts ' + parts.join(' ')).show();
        });
    });
    // -- END date range --------------------------------------------------------

    // Search posts in export list
    $('#peiwm-posts-export-search').on('input', function () {
        const query = $(this).val().toLowerCase();
        $('#peiwm-posts-export-list .peiwm-selective-item').each(function () {
            $(this).toggle($(this).find('.peiwm-selective-title').text().toLowerCase().includes(query));
        });
    });

    // Select all posts export
    $('#peiwm-posts-export-select-all').on('change', function () {
        const checked = $(this).is(':checked');
        $('#peiwm-posts-export-list .peiwm-selective-item:visible .peiwm-selective-checkbox').prop('checked', checked);
        updatePostsExportCount();
    });

    function loadPostsExportList(onComplete) {
        $('.peiwm-batch-warn').remove();
        const pageSize = (typeof peiwm_batch_settings !== 'undefined' && peiwm_batch_settings.export_list_page_size)
            ? peiwm_batch_settings.export_list_page_size
            : 300;
        window._peiwmExportListPageSize = pageSize;
        $('#peiwm-posts-export-list').html('<div class="peiwm-selective-loading"><div class="peiwm-loading-spinner"></div><p>Loading posts...</p></div>');
        $('#peiwm-posts-export-load-more-wrap').empty();
        loadPostsExportPage(0, [], onComplete);
    }

    function loadPostsExportPage(offset, existingPosts, onComplete) {
        const reqData = { action: 'peiwm_get_posts_list', nonce: peiwm_ajax.nonce, offset: offset };
        const df = window.peiwmDateFilter || {};
        if (df.date_from) reqData.date_from = df.date_from;
        if (df.date_to)   reqData.date_to   = df.date_to;

        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: reqData,
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    const allPosts = existingPosts.concat(data.posts);
                    const pageSize = window._peiwmExportListPageSize || 300;

                    // Show batch warning once at top
                    if (data.show_batch_warn && offset === 0) {
                        const warn = $('<div class="peiwm-batch-warn" style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.75rem;font-size:0.875rem;">' +
                            '⚠️ Your site has <strong>' + data.total_count + ' posts</strong>. Enable <strong><a href="?page=peiwm-batch-settings">Batch Processing</a></strong> for better performance on large sites.' +
                        '</div>');
                        $('#peiwm-posts-export-selective-panel').prepend(warn);
                    }

                    // Render only the new posts (renderPostsExportList appends if items already exist)
                    renderPostsExportList(data.posts);

                    // Update footer: show "Load next N" button beside selected count
                    const loadMoreWrap = $('#peiwm-posts-export-load-more-wrap');
                    if (data.has_more) {
                        const nextOffset = offset + data.count;
                        const remaining  = data.total_count - nextOffset;
                        loadMoreWrap.html(
                            '<button type="button" class="button button-secondary peiwm-load-more-posts" style="margin-left:0.5rem;font-size:0.8rem;padding:2px 10px;">' +
                                '⬇️ Load next ' + pageSize + ' (' + remaining + ' more)' +
                            '</button>'
                        );
                        loadMoreWrap.find('.peiwm-load-more-posts').on('click', function () {
                            loadMoreWrap.html('<span style="font-size:0.8rem;color:#6b7280;margin-left:0.5rem;">Loading...</span>');
                            loadPostsExportPage(nextOffset, [], null);
                        });
                        // First page done — re-enable the Apply button even if more pages exist
                        if (typeof onComplete === 'function') {
                            const totalShown = $('#peiwm-posts-export-list').find('.peiwm-selective-item').length;
                            onComplete(totalShown);
                        }
                    } else {
                        const totalShown = $('#peiwm-posts-export-list').find('.peiwm-selective-item').length;
                        loadMoreWrap.html('<span style="font-size:0.8rem;color:#10b981;margin-left:0.5rem;">✓ ' + totalShown + ' loaded</span>');
                        if (typeof onComplete === 'function') onComplete(totalShown);
                    }
                } else {
                    $('#peiwm-posts-export-list').html('<p class="peiwm-selective-empty">Failed to load posts.</p>');
                    if (typeof onComplete === 'function') onComplete(0);
                }
            },
            error: function () {
                $('#peiwm-posts-export-list').html('<p class="peiwm-selective-empty">Error loading posts.</p>');
                if (typeof onComplete === 'function') onComplete(0);
            }
        });
    }

    function renderPostsExportList(posts) {
        const list = $('#peiwm-posts-export-list');
        if (!posts || !posts.length) {
            list.html('<p class="peiwm-selective-empty">No posts found.</p>');
            return;
        }
        let html = '';
        posts.forEach(function (post) {
            const status = post.post_status ? '<span class="peiwm-selective-status peiwm-status-' + post.post_status + '">' + post.post_status + '</span>' : '';
            const date = post.post_date ? post.post_date.slice(0, 10) : '';
            html += '<label class="peiwm-selective-item" data-id="' + post.ID + '">' +
                '<input type="checkbox" class="peiwm-selective-checkbox" data-id="' + post.ID + '" checked>' +
                '<span class="peiwm-selective-info">' +
                    '<span class="peiwm-selective-title">' + $('<div>').text(post.post_title || 'Untitled').html() + '</span>' +
                    '<span class="peiwm-selective-meta">' + date + ' ' + status + '</span>' +
                '</span>' +
            '</label>';
        });

        // On first load (offset 0) replace; on subsequent loads append new items only
        const existingCount = list.find('.peiwm-selective-item').length;
        if (existingCount === 0) {
            list.html(html);
        } else {
            // Only append posts not already in the list
            const existingIds = new Set();
            list.find('.peiwm-selective-checkbox').each(function () {
                existingIds.add(String($(this).attr('data-id')));
            });
            let newHtml = '';
            posts.forEach(function (post) {
                if (!existingIds.has(String(post.ID))) {
                    const status = post.post_status ? '<span class="peiwm-selective-status peiwm-status-' + post.post_status + '">' + post.post_status + '</span>' : '';
                    const date = post.post_date ? post.post_date.slice(0, 10) : '';
                    newHtml += '<label class="peiwm-selective-item" data-id="' + post.ID + '">' +
                        '<input type="checkbox" class="peiwm-selective-checkbox" data-id="' + post.ID + '" checked>' +
                        '<span class="peiwm-selective-info">' +
                            '<span class="peiwm-selective-title">' + $('<div>').text(post.post_title || 'Untitled').html() + '</span>' +
                            '<span class="peiwm-selective-meta">' + date + ' ' + status + '</span>' +
                        '</span>' +
                    '</label>';
                }
            });
            if (newHtml) list.append(newHtml);
        }

        $('#peiwm-posts-export-select-all').prop('checked', true);
        updatePostsExportCount();
        list.off('change').on('change', '.peiwm-selective-checkbox', function () {
            updatePostsExportCount();
            const allChecked = list.find('.peiwm-selective-item:visible .peiwm-selective-checkbox:not(:checked)').length === 0;
            $('#peiwm-posts-export-select-all').prop('checked', allChecked);
        });
    }

    function updatePostsExportCount() {
        const count = $('#peiwm-posts-export-list .peiwm-selective-checkbox:checked').length;
        $('#peiwm-posts-export-selected-count').text(count + ' selected');
    }

    // Export Posts - splits into multiple JSON files to avoid browser memory exhaustion
    // For 1M posts: downloads posts_export.json, posts_export_part2.json, etc.
    $('#peiwm-export-posts').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        const isSelective = $('#peiwm-export-posts-selective').is(':checked');
        const isDateRange = $('#peiwm-export-posts-daterange').is(':checked');
        // Either mode uses the checked IDs from the visible list
        const useIdList = isSelective || isDateRange;

        let selectedIds = [];
        if (useIdList) {
            $('#peiwm-posts-export-list .peiwm-selective-checkbox:checked').each(function () {
                const id = parseInt($(this).attr('data-id'), 10);
                if (id > 0) selectedIds.push(id);
            });
            if (selectedIds.length === 0) {
                showError('Please select at least one post to export.');
                return;
            }
        }

        button.prop('disabled', true).text('Exporting...');
        $('#peiwm-posts-progress').show();
        $('html, body').animate({ scrollTop: $('#peiwm-posts-progress').offset().top - 40 }, 400);

        // For selective/date-range mode: export all selected IDs into ONE file
        // For non-selective: split into files of postsPerFile each
        const postsPerFile = useIdList
            ? selectedIds.length  // all selected go into one file
            : Math.max(
                (typeof peiwm_batch_settings !== 'undefined' && peiwm_batch_settings.export_json_size)
                    ? peiwm_batch_settings.export_json_size
                    : 500,
                500
              );

        // AJAX chunk size - small to avoid PHP memory exhaustion per request
        const ajaxChunkSize = 50;

        let globalOffset = 0;
        let fileNum = 0;
        let totalExported = 0;
        let exportSessionKey = null;
        // For selective: track position in selectedIds array
        let selectiveOffset = 0;

        function downloadFile(posts) {
            fileNum++;
            totalExported += posts.length;
            const suffix = fileNum > 1 ? '_part' + fileNum : '';
            const blob = new Blob([JSON.stringify(posts, null, 2)], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'posts_export' + suffix + '_' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            setTimeout(() => window.URL.revokeObjectURL(url), 1000);
        }

        function exportNextFile() {
            let currentFilePosts = [];

            function fetchChunk() {
                const needed = postsPerFile - currentFilePosts.length;
                const reqData = {
                    action: 'peiwm_export_posts_chunk',
                    nonce: peiwm_ajax.nonce,
                    chunk_size: Math.min(needed, ajaxChunkSize),
                    export_acf_fields: $('#peiwm-export-acf-fields').is(':checked') ? '1' : '0',
                    export_wpml_data: $('#peiwm-export-wpml-data').is(':checked') ? '1' : '0'
                };

                if (useIdList && selectedIds.length > 0) {
                    // For selective/date-range: send a slice of IDs directly
                    const chunkIds = selectedIds.slice(selectiveOffset, selectiveOffset + Math.min(needed, ajaxChunkSize));
                    if (chunkIds.length === 0) {
                        if (currentFilePosts.length > 0) {
                            downloadFile(currentFilePosts);
                        }
                        return;
                    }
                    reqData.post_ids = chunkIds.join(',');
                    reqData.offset = 0;
                } else {
                    reqData.offset = globalOffset;
                    if (exportSessionKey) {
                        reqData.export_session = exportSessionKey;
                    }
                }

                button.text('Exporting file ' + (fileNum + 1) + '... (' + (totalExported + currentFilePosts.length) + ' posts done)');

                $.ajax({
                    url: peiwm_ajax.ajax_url,
                    type: 'POST',
                    data: reqData,
                    success: function (response) {
                        if (!response.success) {
                            showError('Export failed: ' + (response.data.debug || response.data.message));
                            button.prop('disabled', false).text(originalText);
                            return;
                        }

                        // Capture session key from first response (reuse same key for all chunks)
                        if (response.data.session_key && !exportSessionKey) {
                            exportSessionKey = response.data.session_key;
                        }

                        currentFilePosts = currentFilePosts.concat(response.data.data);
                        const fetched = response.data.data.length;

                        if (useIdList) {
                            selectiveOffset += fetched;
                        } else {
                            globalOffset = globalOffset + fetched;
                        }

                        const moreExist = useIdList
                            ? selectiveOffset < selectedIds.length
                            : response.data.has_more;

                        if (currentFilePosts.length >= postsPerFile || !moreExist) {
                            downloadFile(currentFilePosts);

                            if (moreExist) {
                                setTimeout(exportNextFile, 500);
                            } else {
                                const msg = fileNum > 1
                                    ? 'Export complete! ' + totalExported + ' posts in ' + fileNum + ' files.'
                                    : 'Posts exported! (' + totalExported + ' posts)';
                                showSuccess(msg);
                                button.prop('disabled', false).text(originalText);
                            }
                        } else {
                            setTimeout(fetchChunk, 100);
                        }
                    },
                    error: function (xhr, status, error) {
                        const hint = xhr.status === 500 ? ' (HTTP 500 - check server error log)' : '';
                        showError('Export failed: ' + error + hint);
                        button.prop('disabled', false).text(originalText);
                    }
                });
            }

            fetchChunk();
        }

        exportNextFile();
    });

    // Import Posts - supports multiple JSON files, processes one by one
    $('#peiwm-import-posts').on('click', function () {
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

        // Read ALL files first, then process
        let allFilesData = []; // array of arrays, one per file
        let filesRead = 0;

        files.forEach(function (file, fileIdx) {
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    // Strip Unicode line/paragraph separators that some editors flag as unusual
                    const raw = e.target.result.replace(/[\u2028\u2029]/g, ' ');
                    let data = JSON.parse(raw);
                    if (!Array.isArray(data)) data = [];
                    allFilesData[fileIdx] = data;
                } catch (err) {
                    allFilesData[fileIdx] = [];
                }
                filesRead++;
                if (filesRead === totalFiles) {
                    startImportFromAllFiles(allFilesData, files, isSelective, button, totalFiles);
                }
            };
            reader.readAsText(file);
        });
    });

    // Expose for batch mode - accepts optional importFn (defaults to importPosts)
    window.peiwmStartImportFromAllFiles = startImportFromAllFiles;

    function startImportFromAllFiles(allFilesData, files, isSelective, button, totalFiles, importFn) {
        const doImport = typeof importFn === 'function' ? importFn : importPosts;

        // Attach force_status settings to each post
        let perFileData = allFilesData.map(function (data) {
            return data.map(function (post, i) {
                const s = postImportSettings[i];
                return Object.assign({}, post, { _force_status: s ? s.force_status : 'original' });
            });
        });

        if (isSelective) {
            const selectedGlobalIndexes = [];
            $('#peiwm-posts-list .peiwm-selective-checkbox:checked').each(function () {
                selectedGlobalIndexes.push(parseInt($(this).attr('data-index'), 10));
            });

            if (selectedGlobalIndexes.length === 0) {
                showError('Please select at least one post to import.');
                button.prop('disabled', false).text('Start Import');
                return;
            }

            // Map global indexes back to per-file arrays
            let globalIdx = 0;
            perFileData = perFileData.map(function (data) {
                return data.filter(function () {
                    const keep = selectedGlobalIndexes.includes(globalIdx);
                    globalIdx++;
                    return keep;
                });
            });
        }

        // Filter out empty files
        const filesToProcess = perFileData.map(function (data, i) {
            return { data: data, name: files[i] ? files[i].name : ('file' + (i + 1)) };
        }).filter(function (f) { return f.data.length > 0; });

        if (filesToProcess.length === 0) {
            showError('No posts to import.');
            button.prop('disabled', false).text('Start Import');
            return;
        }

        const totalFilesToProcess = filesToProcess.length;

        // Build file tracker UI inside the progress panel
        const progress = $('#peiwm-posts-progress');
        let trackerHtml = '<div id="peiwm-file-tracker" style="margin-bottom:0.75rem;padding:0.6rem 0.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:0.82rem;">';
        trackerHtml += '<div style="font-weight:600;margin-bottom:0.4rem;color:#374151;">📁 Files (' + totalFilesToProcess + ' total)</div>';
        trackerHtml += '<div id="peiwm-file-tracker-list">';
        filesToProcess.forEach(function (f, i) {
            trackerHtml += '<div id="peiwm-file-row-' + i + '" style="display:flex;align-items:center;gap:0.4rem;padding:2px 0;">' +
                '<span id="peiwm-file-icon-' + i + '" style="width:1.1rem;text-align:center;">⏳</span>' +
                '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + f.name + '">' + f.name + '</span>' +
                '<span id="peiwm-file-status-' + i + '" style="color:#6b7280;font-size:0.78rem;">' + f.data.length + ' posts — pending</span>' +
            '</div>';
        });
        trackerHtml += '</div></div>';

        // Remove any previous tracker and insert fresh
        progress.find('#peiwm-file-tracker').remove();
        progress.find('.peiwm-progress-bar').before(trackerHtml);

        let currentFileIndex = 0;

        function markFileRunning(i) {
            $('#peiwm-file-icon-' + i).text('🔄');
            $('#peiwm-file-status-' + i).text(filesToProcess[i].data.length + ' posts — running…').css('color', '#2563eb');
        }

        function markFileDone(i) {
            $('#peiwm-file-icon-' + i).text('✅');
            $('#peiwm-file-status-' + i).text(filesToProcess[i].data.length + ' posts — done').css('color', '#16a34a');
        }

        function markFilePartial(i, failedCount) {
            $('#peiwm-file-icon-' + i).text('⚠️');
            $('#peiwm-file-status-' + i).text(filesToProcess[i].data.length + ' posts — done (' + failedCount + ' failed - retrying)').css('color', '#d97706');
        }

        function processNextFile() {
            if (currentFileIndex >= totalFilesToProcess) {
                button.prop('disabled', false).text('Start Import');
                if (totalFilesToProcess > 1) {
                    showSuccess('All ' + totalFilesToProcess + ' files imported successfully!');
                }
                return;
            }

            const fileInfo = filesToProcess[currentFileIndex];
            markFileRunning(currentFileIndex);

            if (totalFilesToProcess > 1) {
                button.text('Importing file ' + (currentFileIndex + 1) + ' of ' + totalFilesToProcess + '...');
            }

            // Pass file name, 1-based index, and total so the import fn can label progress correctly
            // onComplete receives optional failedCount so the tracker can show partial state
            const capturedIdx = currentFileIndex;
            doImport(fileInfo.data, fileInfo.name, capturedIdx + 1, totalFilesToProcess, function (failedCount) {
                if (failedCount && failedCount > 0) {
                    markFilePartial(capturedIdx, failedCount);
                } else {
                    markFileDone(capturedIdx);
                }
                currentFileIndex++;
                processNextFile();
            });
        }

        processNextFile();
    }

    // -- Media Advanced Options: date range toggle ----------------------------
    $('#peiwm-media-export-daterange').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-media-daterange-filter-ui').slideDown(200);
        } else {
            $('#peiwm-media-daterange-filter-ui').slideUp(200);
            $('#peiwm-media-export-date-from').val('');
            $('#peiwm-media-export-date-to').val('');
            $('#peiwm-media-daterange-error').hide();
            $('#peiwm-media-daterange-summary').hide();
        }
    });

    // Validate date inputs on change
    $('#peiwm-media-export-date-from, #peiwm-media-export-date-to').on('change', function () {
        const from = $('#peiwm-media-export-date-from').val();
        const to   = $('#peiwm-media-export-date-to').val();
        const err  = $('#peiwm-media-daterange-error');
        const sum  = $('#peiwm-media-daterange-summary');
        err.hide();
        sum.hide();
        if (from && to && from > to) {
            err.text('"From" date cannot be later than "To" date.').show();
            return;
        }
        if (from || to) {
            let msg = 'Filtering media uploaded';
            if (from && to)       msg += ' between ' + from + ' and ' + to;
            else if (from)        msg += ' on or after ' + from;
            else                  msg += ' on or before ' + to;
            sum.text(msg).show();
        }
    });

    // -- Media Advanced Options: export by post toggle -------------------------
    $('#peiwm-media-export-by-post').on('change', function () {
        if ($(this).is(':checked')) {
            $('#peiwm-media-by-post-panel').slideDown(200);
            loadMediaPostList();
        } else {
            $('#peiwm-media-by-post-panel').slideUp(200);
        }
    });

    // Search filtering for the media post list
    $('#peiwm-media-post-search').on('input', function () {
        const term = $(this).val().toLowerCase();
        $('#peiwm-media-post-list .peiwm-media-post-item').each(function () {
            const label = $(this).find('label').text().toLowerCase();
            $(this).toggle(label.indexOf(term) !== -1);
        });
    });

    // Select all / deselect all
    $('#peiwm-media-post-select-all').on('click', function () {
        $('#peiwm-media-post-list .peiwm-media-post-cb:visible').prop('checked', true);
        updateMediaPostCount();
    });
    $('#peiwm-media-post-deselect-all').on('click', function () {
        $('#peiwm-media-post-list .peiwm-media-post-cb').prop('checked', false);
        updateMediaPostCount();
    });

    function updateMediaPostCount() {
        const count = $('#peiwm-media-post-list .peiwm-media-post-cb:checked').length;
        const total = $('#peiwm-media-post-list .peiwm-media-post-cb').length;
        $('#peiwm-media-post-selected-count').text(count + ' of ' + total + ' posts selected');
    }

    function loadMediaPostList() {
        const pageSize = (typeof peiwm_batch_settings !== 'undefined' && peiwm_batch_settings.export_list_page_size)
            ? peiwm_batch_settings.export_list_page_size
            : 300;
        window._peiwmMediaPostPageSize = pageSize;
        const list = $('#peiwm-media-post-list');
        list.html('<div class="peiwm-selective-loading"><div class="peiwm-loading-spinner"></div><p>Loading posts\u2026</p></div>');
        $('#peiwm-media-post-load-more-wrap').empty();
        loadMediaPostPage(0);
    }

    function loadMediaPostPage(offset) {
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: { action: 'peiwm_get_posts_list', nonce: peiwm_ajax.nonce, offset: offset },
            success: function (response) {
                if (!response.success) {
                    $('#peiwm-media-post-list').html('<p style="color:#dc2626;font-size:0.85rem;margin:0.5rem;">Failed to load posts.</p>');
                    return;
                }
                const data     = response.data;
                const pageSize = window._peiwmMediaPostPageSize || 300;

                // Show batch warning on first load for large sites
                if (data.show_batch_warn && offset === 0) {
                    const warn = $('<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.5rem;font-size:0.8rem;">' +
                        '\u26a0\ufe0f ' + data.total_count + ' posts on this site. Enable <a href="?page=peiwm-batch-settings">Batch Processing</a> for better performance.' +
                    '</div>');
                    if ($('#peiwm-media-post-list').find('.peiwm-media-batch-warn').length === 0) {
                        $('#peiwm-media-by-post-panel').prepend(warn.addClass('peiwm-media-batch-warn'));
                    }
                }

                renderMediaPostList(data.posts);

                const loadMoreWrap = $('#peiwm-media-post-load-more-wrap');
                if (data.has_more) {
                    const nextOffset = offset + data.count;
                    const remaining  = data.total_count - nextOffset;
                    loadMoreWrap.html(
                        '<button type="button" class="button button-secondary peiwm-media-load-more" style="font-size:0.8rem;padding:3px 10px;">' +
                            '\u2b07 Load next ' + pageSize + ' (' + remaining + ' more)' +
                        '</button>'
                    );
                    loadMoreWrap.find('.peiwm-media-load-more').on('click', function () {
                        loadMoreWrap.html('<span style="font-size:0.8rem;color:#6b7280;">Loading\u2026</span>');
                        loadMediaPostPage(nextOffset);
                    });
                } else {
                    const totalShown = $('#peiwm-media-post-list').find('.peiwm-media-post-item').length;
                    loadMoreWrap.html('<span style="font-size:0.8rem;color:#10b981;">\u2713 ' + totalShown + ' posts loaded</span>');
                }
                updateMediaPostCount();
            },
            error: function () {
                $('#peiwm-media-post-list').html('<p style="color:#dc2626;font-size:0.85rem;margin:0.5rem;">Error loading posts.</p>');
            }
        });
    }

    function renderMediaPostList(posts) {
        const list = $('#peiwm-media-post-list');
        if (!posts || !posts.length) {
            if (list.find('.peiwm-media-post-item').length === 0) {
                list.html('<p style="color:#9ca3af;font-size:0.85rem;margin:0.5rem;">No posts found.</p>');
            }
            return;
        }

        // Deduplicate — skip IDs already rendered
        const existingIds = new Set();
        list.find('.peiwm-media-post-cb').each(function () {
            existingIds.add(String($(this).attr('data-id')));
        });

        let html = '';
        posts.forEach(function (post) {
            if (existingIds.has(String(post.ID))) return;
            const status = post.post_status ? ' <span style="font-size:0.72rem;background:#e5e7eb;padding:1px 5px;border-radius:3px;">' + post.post_status + '</span>' : '';
            const date = post.post_date ? post.post_date.slice(0, 10) : '';
            html += '<div class="peiwm-media-post-item" style="display:flex;align-items:center;gap:6px;padding:4px 2px;border-bottom:1px solid #f3f4f6;">' +
                '<input type="checkbox" class="peiwm-media-post-cb" data-id="' + post.ID + '" id="mpost-' + post.ID + '" checked>' +
                '<label for="mpost-' + post.ID + '" style="cursor:pointer;font-size:0.85rem;flex:1;margin:0;">' +
                $('<span>').text(post.post_title || '(no title)').html() + status +
                ' <span style="color:#9ca3af;font-size:0.75rem;">(' + date + ')</span></label></div>';
        });

        // First page: replace loading placeholder; subsequent pages: append
        if (list.find('.peiwm-media-post-item').length === 0) {
            list.html(html);
        } else {
            list.append(html);
        }

        list.find('.peiwm-media-post-cb').off('change.mpc').on('change.mpc', updateMediaPostCount);
    }

    // Helper: collect media advanced filter params (exposed globally for admin-batch.js)
    window.getMediaExportParams = function getMediaExportParams() {
        const params = {};
        // Date range
        if ($('#peiwm-media-export-daterange').is(':checked')) {
            const from = $('#peiwm-media-export-date-from').val().trim();
            const to   = $('#peiwm-media-export-date-to').val().trim();
            if (from) params.media_date_from = from;
            if (to)   params.media_date_to   = to;
        }
        // By post
        if ($('#peiwm-media-export-by-post').is(':checked')) {
            const ids = [];
            $('#peiwm-media-post-list .peiwm-media-post-cb:checked').each(function () {
                const id = parseInt($(this).attr('data-id'), 10);
                if (id > 0) ids.push(id);
            });
            if (ids.length === 0) {
                return null; // caller should show error
            }
            params.media_post_ids = ids.join(',');
        }
        return params;
    }

    // Export Media
    $('#peiwm-export-media').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        const exportAllSizes = $('#peiwm-export-all-image-sizes').is(':checked');

        // Validate advanced options
        const advancedParams = getMediaExportParams();
        if (advancedParams === null) {
            showError('Please select at least one post to export media from.');
            return;
        }

        // Validate date range
        if ($('#peiwm-media-export-daterange').is(':checked') && $('#peiwm-media-daterange-error').is(':visible')) {
            showError('Please fix the date range error before exporting.');
            return;
        }

        button.prop('disabled', true).text('Exporting...');

        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: Object.assign({
                action: 'peiwm_export_media',
                nonce: peiwm_ajax.nonce,
                export_all_sizes: exportAllSizes ? '1' : '0'
            }, advancedParams),
            success: function (response) {
                if (response.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    let message = 'Media exported successfully!\n\n';
                    
                    // Show clear breakdown
                    if (response.data.export_all_sizes) {
                        message += '📊 Total files: ' + response.data.count + ' (including size variations)\n';
                        message += '🖼️ Unique media: ' + response.data.unique_count + ' original files\n';
                    } else {
                        message += '📊 Total files: ' + response.data.count + ' (originals only)\n';
                    }
                    message += '📦 ZIP size: ' + response.data.total_size_formatted;
                    
                    // Show warning if files were skipped
                    if (response.data.skipped_count && response.data.skipped_count > 0) {
                        message += '\n\n⚠️ Warning: ' + response.data.skipped_count + ' attachment(s) were skipped because their files are missing from the server.';
                        message += '\n\nDatabase records: ' + response.data.total_attachments;
                        message += '\nSuccessfully exported: ' + response.data.unique_count + ' media items';
                    }
                    
                    // Delay showing success message to allow download to start
                    setTimeout(function() {
                        showSuccess(message);
                    }, 500);
                } else {
                    showError('Export failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                showError('Export failed: ' + error);
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Import Media - supports multiple ZIP files sequentially
    $('#peiwm-import-media').on('click', function () {
        const button = $(this);
        const fileInput = $('#peiwm-media-file')[0];
        if (!fileInput.files.length) {
            showError(peiwm_ajax.strings.select_file);
            return;
        }

        const files = Array.from(fileInput.files);
        const totalFiles = files.length;
        const maxSize = 500 * 1024 * 1024; // 500MB per file
        let currentFileIndex = 0;

        // Get server upload limits first
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

                    button.prop('disabled', true);

                    function processNextMediaFile() {
                        if (currentFileIndex >= totalFiles) {
                            button.prop('disabled', false).text('Start Import');
                            if (totalFiles > 1) showSuccess('All ' + totalFiles + ' ZIP files imported successfully!');
                            return;
                        }

                        const file = files[currentFileIndex];
                        button.text(totalFiles > 1 ? 'Importing ZIP ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...' : 'Importing...');

                        importMedia(file, function () {
                            currentFileIndex++;
                            processNextMediaFile();
                        });
                    }

                    processNextMediaFile();
                } else {
                    // Fallback to basic validation
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

                    button.prop('disabled', true);

                    function processNextMediaFile() {
                        if (currentFileIndex >= totalFiles) {
                            button.prop('disabled', false).text('Start Import');
                            if (totalFiles > 1) showSuccess('All ' + totalFiles + ' ZIP files imported successfully!');
                            return;
                        }

                        const file = files[currentFileIndex];
                        button.text(totalFiles > 1 ? 'Importing ZIP ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...' : 'Importing...');

                        importMedia(file, function () {
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

                button.prop('disabled', true);

                function processNextMediaFile() {
                    if (currentFileIndex >= totalFiles) {
                        button.prop('disabled', false).text('Start Import');
                        if (totalFiles > 1) showSuccess('All ' + totalFiles + ' ZIP files imported successfully!');
                        return;
                    }

                    const file = files[currentFileIndex];
                    button.text(totalFiles > 1 ? 'Importing ZIP ' + (currentFileIndex + 1) + ' of ' + totalFiles + '...' : 'Importing...');

                    importMedia(file, function () {
                        currentFileIndex++;
                        processNextMediaFile();
                    });
                }

                processNextMediaFile();
            }
        });
    });

    // Test Configuration
    $('#peiwm-test-config').on('click', function () {
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
            success: function (response) {
                if (response.success) {
                    displayTestResults(response.data);
                } else {
                    showError('Test failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                showError('Test failed: ' + error);
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Load Media Statistics
    loadMediaStats();

    // Check post count on page load and show batch warning on export button if needed
    $.ajax({
        url: peiwm_ajax.ajax_url,
        type: 'POST',
        data: { action: 'peiwm_get_posts_list', nonce: peiwm_ajax.nonce, offset: 0 },
        success: function (response) {
            if (response.success && response.data.show_batch_warn) {
                const total = response.data.total_count;
                const notice = $(
                    '<div id="peiwm-batch-notice" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:14px;padding:11px 14px;background:#f0f6fc;border-left:3px solid #2271b1;border-radius:0 3px 3px 0;">' +
                        '<div style="display:flex;align-items:center;gap:10px;">' +
                            '<div style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;background:#2271b1;border-radius:50%;flex-shrink:0;">' +
                                '<svg width="14" height="14" fill="#fff" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L13 10.414V15a1 1 0 01-.553.894l-4 2A1 1 0 017 17v-6.586L3.293 6.707A1 1 0 013 6V4z"/></svg>' +
                            '</div>' +
                            '<div style="font-size:13px;color:#1d2327;line-height:1.4;">' +
                                '<strong style="font-weight:600;color:#135e96;">' + total.toLocaleString() + ' posts</strong> ready to export' +
                                '<span style="color:#50575e;font-size:12px;display:block;margin-top:1px;">Enable batch mode to export faster and avoid timeouts on large sets</span>' +
                            '</div>' +
                        '</div>' +
                        '<a href="?page=peiwm-batch-settings" style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#2271b1;color:#fff;font-size:12px;font-weight:500;border-radius:3px;text-decoration:none;white-space:nowrap;flex-shrink:0;">' +
                            'Enable batch mode' +
                            '<svg width="11" height="11" fill="#fff" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>' +
                        '</a>' +
                    '</div>'
                );
                $('#peiwm-export-posts').after(notice);
            }
        }
    });

    // Refresh Media Statistics
    $('#peiwm-refresh-stats').on('click', function () {
        loadMediaStats();
    });

    // Delete Posts
    $('#peiwm-delete-posts').on('click', function () {
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
            .catch(() => { });
    });

    // Delete Media
    $('#peiwm-delete-media').on('click', function () {
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
            .catch(() => { });
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
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Deletion complete!');
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Deletion failed: ' + response.data.message);
                    showError('Delete failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Deletion failed: ' + error);
                showError('Delete failed: ' + error);
            },
            complete: function () {
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
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Deletion complete!');
                    addLog('✅ ' + response.data.message);
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Deletion failed: ' + response.data.message);
                    addLog('❌ Error: ' + response.data.message);
                    showError('Delete failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Deletion failed: ' + error);
                addLog('❌ Error: ' + error);
                showError('Delete failed: ' + error);
            },
            complete: function () {
                button.prop('disabled', false).text('Delete All Media');
            }
        });
    }

    function importPosts(posts, fileLabel, fileIndex, totalFiles, onComplete) {
        // Normalise arguments - legacy callers may pass (posts, onComplete)
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
        progressText.text('Starting import…');
        log.empty();

        let currentIndex = 0;
        const totalPosts = posts.length;
        let isProcessing = false;
        const failedPosts = [];

        function processNextPost() {
            if (currentIndex >= totalPosts) {
                progressText.text('Import complete!');

                if (failedPosts.length > 0) {
                    const failedCount = failedPosts.length;
                    addLog('⚠️ ' + failedCount + ' post(s) failed due to timeout or errors.', log);

                    const retryBtn = $('<button type="button" class="button peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">' +
                        '🔄 Some were missed \u2014 retry ' + failedCount + ' failed post(s) now</button>');

                    log.after(retryBtn);
                    retryBtn.on('click', function () {
                        retryBtn.remove();
                        const retryData = failedPosts.splice(0);
                        addLog('🔄 Retrying ' + retryData.length + ' failed post(s)...', log);
                        importPosts(retryData, fileLabel, fileIndex, totalFiles, onComplete);
                    });

                    showSuccess('Import done! ' + totalPosts + ' processed. A few items may need retry.');
                    // Always advance to next file even with failures; pass failed count so tracker can show partial state
                    if (typeof onComplete === 'function') onComplete(failedCount);
                } else {
                    addLog('All posts processed successfully!', log);
                    showSuccess('Posts import completed successfully!');
                    if (typeof onComplete === 'function') onComplete(0);
                }
                return;
            }

            if (isProcessing) {
                return; // Prevent concurrent processing
            }

            isProcessing = true;
            const post = posts[currentIndex];
            const downloadMissingImages = $('#peiwm-download-missing-images').is(':checked') ? '1' : '0';
            
            // Show what we're about to do
            addLog('🔄 Processing: ' + post.post_title, log, 'peiwm-log-info');
            
            // Process images first if download is enabled
            if (downloadMissingImages === '1') {
                processPostImages(post, function() {
                    // After images are processed, import the post
                    importPostContent(post);
                });
            } else {
                // No downloads needed, import directly
                importPostContent(post);
            }
        }

        function processPostImages(post, callback) {
            const imagesToProcess = [];
            
            // Collect all images that need processing
            if (post.content_images) {
                post.content_images.forEach(function(img) {
                    imagesToProcess.push({type: 'content', data: img});
                });
            }
            if (post.featured_image) {
                imagesToProcess.push({type: 'featured', data: post.featured_image});
            }
            
            if (imagesToProcess.length === 0) {
                callback();
                return;
            }
            
            addLog('  🖼️ Checking ' + imagesToProcess.length + ' image(s)...', log, 'peiwm-log-info');
            
            let processedCount = 0;
            
            function processNextImage() {
                if (processedCount >= imagesToProcess.length) {
                    callback();
                    return;
                }
                
                const imageItem = imagesToProcess[processedCount];
                const filename = imageItem.data.filename;
                
                addLog('  🔍 Checking: ' + filename, log, 'peiwm-log-info');
                
                $.ajax({
                    url: peiwm_ajax.ajax_url,
                    type: 'POST',
                    timeout: 30000, // 30 seconds per image
                    data: {
                        action: 'peiwm_check_and_download_image',
                        nonce: peiwm_ajax.nonce,
                        image_data: JSON.stringify(imageItem.data),
                        post_id: 0 // Temporary post ID
                    },
                    success: function (response) {
                        if (response.success) {
                            if (response.data.status === 'found_local') {
                                addLog('    ✅ Found locally: ' + filename, log, 'peiwm-log-success');
                            } else if (response.data.status === 'downloaded') {
                                addLog('    ⬇️ Downloaded: ' + filename, log, 'peiwm-log-success');
                            } else if (response.data.status === 'failed') {
                                addLog('    ❌ Failed: ' + filename + ' - ' + response.data.message, log, 'peiwm-log-error');
                            }
                        } else {
                            addLog('    ❌ Error: ' + filename + ' - ' + response.data.message, log, 'peiwm-log-error');
                        }
                    },
                    error: function (xhr, status, error) {
                        addLog('    ❌ Error: ' + filename + ' - ' + error, log, 'peiwm-log-error');
                    },
                    complete: function () {
                        processedCount++;
                        setTimeout(processNextImage, 100); // Small delay between images
                    }
                });
            }
            
            processNextImage();
        }

        function importPostContent(post) {
            const downloadMissingImages = $('#peiwm-download-missing-images').is(':checked') ? '1' : '0';
            const checkMediaLibrary = $('#peiwm-check-media-library').is(':checked') ? '1' : '0';
            const forceStatus = post._force_status || 'original';
            const mediaMatchMode = $('input[name="peiwm_media_match_mode"]:checked').val() || 'match_and_reuse';
            
            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                timeout: 90000, // 90 seconds per post
                data: {
                    action: 'peiwm_import_post',
                    nonce: peiwm_ajax.nonce,
                    post_data: JSON.stringify(post),
                    download_missing_images: downloadMissingImages,
                    check_media_library: checkMediaLibrary,
                    media_match_mode: mediaMatchMode,
                    attach_media_to_post: document.getElementById('peiwm-attach-media-to-post') && document.getElementById('peiwm-attach-media-to-post').checked ? '1' : '0',
                    force_status: forceStatus,
                    peiwm_smart_author_mapping: $('#peiwm_smart_author_mapping').is(':checked') ? '1' : '0',
                    peiwm_author_fallback: $('input[name="peiwm_author_fallback"]:checked').val() || 'current_user',
                    peiwm_enable_wpml_support: $('#peiwm_enable_wpml_support').is(':checked') ? '1' : '0'
                },
                success: function (response) {
                    if (response.success) {
                        let logMessage = '';
                        let logClass = '';
                        
                        if (response.data.status === 'skipped') {
                            logMessage = '⏭️ Skipped: ' + post.post_title + ' (' + response.data.reason + ')';
                            logClass = '';
                        } else if (response.data.status === 'updated') {
                            logMessage = '🔄 Updated: ' + post.post_title + ' (' + response.data.reason + ')';
                            logClass = 'peiwm-log-info';
                        } else {
                            logMessage = '✅ Imported: ' + post.post_title;
                            logClass = 'peiwm-log-success';
                        }
                        
                        // Add language info if available
                        if (response.data.language_info && response.data.language_info.success) {
                            logMessage += ' | ' + response.data.language_info.message;
                        } else if (response.data.language_info && !response.data.language_info.success) {
                            logMessage += ' | ⚠️ ' + response.data.language_info.message;
                        }
                        
                        addLog(logMessage, log, logClass);
                    } else {
                        failedPosts.push(post);
                        addLog('❌ Failed: ' + post.post_title + ' - ' + response.data.message, log);
                    }
                },
                error: function (xhr, status, error) {
                    failedPosts.push(post);
                    if (status === 'timeout') {
                        addLog('⌛ Timeout: ' + post.post_title + ' - will be available for retry', log, 'peiwm-log-warning');
                    } else {
                        addLog('❌ Error: ' + post.post_title + ' - ' + error, log);
                    }
                },
                complete: function () {
                    isProcessing = false;
                    currentIndex++;
                    const progressPercent = Math.round((currentIndex / totalPosts) * 100);
                    progressFill.css('width', progressPercent + '%');
                    const fileLabel2 = totalFiles > 1 ? ' - File ' + fileIndex + '/' + totalFiles + ' (' + fileLabel + ')' : '';
                    progressText.text('Processing: ' + currentIndex + ' of ' + totalPosts + ' posts (' + progressPercent + '%)' + fileLabel2);

                    // Process next post with a delay to prevent server overload
                    setTimeout(processNextPost, 500);
                }
            });
        }

        processNextPost();
    }

    function importMedia(file, onComplete) {
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
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        progressFill.css('width', percentComplete + '%');
                        progressText.text('Uploading... (' + percentComplete + '%)');
                        addLog('Upload progress: ' + percentComplete + '%', log);
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                addLog('Server response received', log);
                if (response.success) {
                    const blockedFiles = response.data.blocked_files || [];
                    const blockedCount = response.data.blocked_count || 0;

                    // Show blocked files warning if any
                    if (blockedCount > 0) {
                        addLog('⚠️ ' + blockedCount + ' file(s) blocked due to disallowed file type', log, 'peiwm-log-warning');
                        blockedFiles.slice(0, 10).forEach(function(filename) {
                            addLog('  ⛔ Blocked: ' + filename, log, 'peiwm-log-warning');
                        });
                        if (blockedCount > 10) {
                            addLog('  ... and ' + (blockedCount - 10) + ' more blocked files', log, 'peiwm-log-warning');
                        }
                        addLog('💡 To allow these file types, go to Settings and update "Allowed Media File Types"', log, 'peiwm-log-info');
                    }

                    addLog('Starting file processing...', log);
                    processMediaFiles(response.data.batch_id, response.data.total_files, onComplete, null, null, blockedCount);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('Error: ' + response.data.message, log, 'peiwm-log-error');
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
                
                addLog('AJAX Error - Status: ' + status + ', Error: ' + errorMsg, log, 'peiwm-log-error');
                if (status === 'timeout') {
                    progressText.text('Upload timed out. The file may be too large or server is slow.');
                    addLog('Upload timed out after 5 minutes', log, 'peiwm-log-error');
                    showError('Upload timed out. Please try with a smaller file or contact your server administrator.');
                } else if (status === 'error') {
                    progressText.text('Upload failed: ' + errorMsg);
                    addLog('Upload failed: ' + errorMsg, log, 'peiwm-log-error');
                    showError('Upload failed: ' + errorMsg);
                } else {
                    progressText.text('Upload failed: ' + status);
                    addLog('Upload failed: ' + status, log, 'peiwm-log-error');
                    showError('Upload failed: ' + status);
                }
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    function processMediaFiles(batchId, totalFiles, onComplete, retryIndices, stats, blockedCount) {
        const progress = $('#peiwm-media-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');

        // Initialize stats on first call
        if (!stats) {
            stats = { imported: 0, skipped: 0, failed: 0, blocked: blockedCount || 0 };
        }

        // Support retry mode: process only specific indices, or all 0..totalFiles-1
        const indicesToProcess = Array.isArray(retryIndices)
            ? retryIndices.slice()
            : Array.from({ length: totalFiles }, function (_, i) { return i; });

        let pos = 0;
        const failedIndices = [];
        const totalToProcess = indicesToProcess.length;

        function processNextFile() {
            if (pos >= totalToProcess) {
                if (failedIndices.length > 0) {
                    progressText.text('Import done with ' + failedIndices.length + ' failed file(s).');
                    addLog('⚠️ ' + failedIndices.length + ' file(s) failed and can be retried.', log);

                    const retryBtn = $('<button type="button" class="button peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">' +
                        '🔄 Some were missed \u2014 retry ' + failedIndices.length + ' failed file(s) now</button>');
                    log.after(retryBtn);
                    retryBtn.on('click', function () {
                        retryBtn.remove();
                        const retryList = failedIndices.splice(0);
                        addLog('🔄 Retrying ' + retryList.length + ' failed file(s)...', log);
                        processMediaFiles(batchId, totalFiles, onComplete, retryList, stats, blockedCount);
                    });

                    showSuccess('Media import done! ' + totalToProcess + ' processed. ' + failedIndices.length + ' need retry.');
                    // Don't call cleanup yet - retry may still need the batch
                } else {
                    progressText.text('Import complete!');
                    
                    // Show detailed summary
                    const summaryParts = [];
                    if (stats.imported > 0) summaryParts.push(stats.imported + ' imported');
                    if (stats.skipped > 0) summaryParts.push(stats.skipped + ' skipped');
                    if (stats.failed > 0) summaryParts.push(stats.failed + ' failed');
                    if (stats.blocked > 0) summaryParts.push(stats.blocked + ' blocked');
                    
                    const summaryMsg = '✅ Import complete! ' + summaryParts.join(', ');
                    addLog(summaryMsg, log);
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
                        success: function (response) {
                            if (response.success) {
                                addLog('✅ Cleanup completed', log);
                            }
                        },
                        complete: function () {
                            if (typeof onComplete === 'function') onComplete();
                        }
                    });
                }
                return;
            }

            const fileIndex = indicesToProcess[pos];

            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                timeout: 120000,
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
                            addLog('⏭️ Skipped: ' + response.data.filename + ' (' + response.data.reason + ')', log);
                        } else if (response.data.status === 'failed') {
                            stats.failed++;
                            failedIndices.push(fileIndex);
                            addLog('❌ Failed: ' + response.data.filename + ' - ' + response.data.reason, log);
                        } else {
                            stats.imported++;
                            addLog('✅ Imported: ' + response.data.filename + ' (' + response.data.file_size_formatted + ')', log);
                        }
                    } else {
                        stats.failed++;
                        failedIndices.push(fileIndex);
                        addLog('❌ Failed: ' + (response.data ? response.data.message : 'unknown error'), log);
                    }
                },
                error: function (xhr, status, error) {
                    stats.failed++;
                    failedIndices.push(fileIndex);
                    addLog('❌ Error (file ' + fileIndex + '): ' + error, log);
                },
                complete: function () {
                    pos++;
                    const progressPercent = Math.round((pos / totalToProcess) * 100);
                    progressFill.css('width', progressPercent + '%');
                    progressText.text('Importing media... (' + pos + ' of ' + totalToProcess + ')');

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
        html += '<tr><td>ZipArchive Available:</td><td>' + (config.ziparchive_available ? '✅ Yes' : '❌ No') + '</td></tr>';
        html += '<tr><td>Upload Directory Writable:</td><td>' + (config.upload_dir_writable ? '✅ Yes' : '❌ No') + '</td></tr>';

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
            html += '<li class="peiwm-error">❌ ZipArchive is not available. This is required for media import/export.</li>';
        }
        if (!config.upload_dir_writable) {
            html += '<li class="peiwm-error">❌ Upload directory is not writable. Check permissions.</li>';
        }
        html += '</ul>';

        results.html(html).show();
    }

    function addLog(message, logContainer = null, className = '') {
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
        const classAttr = className ? ' class="peiwm-log-entry ' + className + '"' : ' class="peiwm-log-entry"';
        logContainer.append('<div' + classAttr + '>[' + time + '] ' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    function loadMediaStats() {
        const statsContainer = $('#peiwm-media-stats');
        const refreshButton = $('#peiwm-refresh-stats');

        // Show enhanced loader
        statsContainer.html(`
            <div class="peiwm-stats-loader">
                <div class="peiwm-stats-loader-spinner"></div>
                <div class="peiwm-stats-loader-text">Loading media statistics...</div>
                <div class="peiwm-stats-loader-subtext">Analyzing your media library</div>
            </div>
        `);

        refreshButton.prop('disabled', true).text('Loading...');

        // After 1 second, show skeleton loading
        setTimeout(() => {
            if (statsContainer.find('.peiwm-stats-loader').length > 0) {
                statsContainer.html(`
                    <div class="peiwm-stats-skeleton">
                        <div class="peiwm-stats-skeleton-item">
                            <div class="peiwm-stats-skeleton-number"></div>
                            <div class="peiwm-stats-skeleton-label"></div>
                        </div>
                        <div class="peiwm-stats-skeleton-item">
                            <div class="peiwm-stats-skeleton-number"></div>
                            <div class="peiwm-stats-skeleton-label"></div>
                        </div>
                        <div class="peiwm-stats-skeleton-item">
                            <div class="peiwm-stats-skeleton-number"></div>
                            <div class="peiwm-stats-skeleton-label"></div>
                        </div>
                        <div class="peiwm-stats-skeleton-item">
                            <div class="peiwm-stats-skeleton-number"></div>
                            <div class="peiwm-stats-skeleton-label"></div>
                        </div>
                    </div>
                `);
            }
        }, 1000);

        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_get_media_stats',
                nonce: peiwm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    const stats = response.data;
                    let html = '<div class="peiwm-stats-grid">';

                    // Unique files (attachments)
                    html += '<div class="peiwm-stat-item">';
                    html += '<div class="peiwm-stat-number">' + stats.unique_files + '</div>';
                    html += '<div class="peiwm-stat-label">Unique Files</div>';
                    html += '<div class="peiwm-stat-detail" style="font-size: 11px; color: #666; margin-top: 2px;">' + stats.unique_size_formatted + ' (originals)</div>';
                    html += '</div>';

                    // Total physical files (including size variations)
                    html += '<div class="peiwm-stat-item">';
                    html += '<div class="peiwm-stat-number">' + stats.total_physical_files + '</div>';
                    html += '<div class="peiwm-stat-label">Total Files</div>';
                    html += '<div class="peiwm-stat-detail" style="font-size: 11px; color: #666; margin-top: 2px;">' + stats.total_size_formatted + ' (with sizes)</div>';
                    html += '</div>';

                    // File Status - Available vs Missing
                    html += '<div class="peiwm-stat-item' + (stats.missing_files > 0 ? ' peiwm-stat-warning' : '') + '">';
                    html += '<div class="peiwm-stat-number">' + stats.available_files + ' / ' + stats.unique_files + '</div>';
                    html += '<div class="peiwm-stat-label">Available Files</div>';
                    if (stats.missing_files > 0) {
                        html += '<div class="peiwm-stat-detail" style="font-size: 11px; color: #d97706; margin-top: 2px;">';
                        html += '⚠️ ' + stats.missing_files + ' missing from disk ';
                        html += '<button type="button" class="peiwm-view-missing-btn" style="background:none;border:none;color:#2563eb;cursor:pointer;text-decoration:underline;padding:0;font-size:11px;" title="View missing files">View Details</button>';
                        html += '</div>';
                        // Store missing files data for the modal
                        window.peiwmMissingFiles = stats.missing_files_list;
                    } else {
                        html += '<div class="peiwm-stat-detail" style="font-size: 11px; color: #10b981; margin-top: 2px;">✅ All files present</div>';
                    }
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
                        const allFileTypes = Object.entries(stats.file_types);
                        
                        // Show first 5 file types
                        for (const [mimeType, fileCount] of allFileTypes) {
                            if (count >= 5) break;
                            const fileType = mimeType.split('/')[1] || mimeType;
                            const displayName = fileType.toUpperCase();
                            const truncatedName = displayName.length > 7 ? displayName.substring(0, 7) + '...' : displayName;
                            
                            html += '<div class="peiwm-file-type-item" title="' + displayName + ' (' + fileCount + ' files)">';
                            html += '<span class="peiwm-file-type-name">' + truncatedName + '</span>';
                            html += '<span class="peiwm-file-type-count">' + fileCount + '</span>';
                            html += '</div>';
                            count++;
                        }

                        // Show "+X more types" button if there are more than 5
                        if (allFileTypes.length > 5) {
                            const remainingCount = allFileTypes.length - 5;
                            html += '<div class="peiwm-file-type-item peiwm-more-toggle" style="cursor: pointer;">';
                            html += '<span class="peiwm-more-text">+' + remainingCount + ' more type' + (remainingCount > 1 ? 's' : '') + '</span>';
                            html += '</div>';
                            
                            // Hidden remaining types
                            html += '<div class="peiwm-file-types-hidden" style="display: none;">';
                            for (let i = 5; i < allFileTypes.length; i++) {
                                const [mimeType, fileCount] = allFileTypes[i];
                                const fileType = mimeType.split('/')[1] || mimeType;
                                const displayName = fileType.toUpperCase();
                                const truncatedName = displayName.length > 7 ? displayName.substring(0, 7) + '...' : displayName;
                                
                                html += '<div class="peiwm-file-type-item" title="' + displayName + ' (' + fileCount + ' files)">';
                                html += '<span class="peiwm-file-type-name">' + truncatedName + '</span>';
                                html += '<span class="peiwm-file-type-count">' + fileCount + '</span>';
                                html += '</div>';
                            }
                            html += '</div>';
                        }

                        html += '</div>';
                        html += '</div>';
                    }

                    statsContainer.html(html);
                    
                    // Add click handler for "more types" toggle
                    statsContainer.find('.peiwm-more-toggle').on('click', function() {
                        const $this = $(this);
                        const $hidden = $this.siblings('.peiwm-file-types-hidden');
                        const isVisible = $hidden.is(':visible');
                        
                        if (isVisible) {
                            $hidden.slideUp(200);
                            const remainingCount = $hidden.find('.peiwm-file-type-item').length;
                            $this.find('.peiwm-more-text').text('+' + remainingCount + ' more type' + (remainingCount > 1 ? 's' : ''));
                        } else {
                            $hidden.slideDown(200);
                            $this.find('.peiwm-more-text').text('Show less');
                        }
                    });

                    // Add click handler for "View Details" button
                    statsContainer.find('.peiwm-view-missing-btn').on('click', function() {
                        showMissingFilesModal();
                    });
                } else {
                    statsContainer.html('<p class="peiwm-error">Failed to load statistics: ' + response.data.message + '</p>');
                }
            },
            error: function (xhr, status, error) {
                statsContainer.html('<p class="peiwm-error">Failed to load statistics: ' + error + '</p>');
            },
            complete: function () {
                refreshButton.prop('disabled', false).text('Refresh Stats');
            }
        });
    }

    // Show missing files modal
    function showMissingFilesModal() {
        if (!window.peiwmMissingFiles || window.peiwmMissingFiles.length === 0) {
            showError('No missing files data available.');
            return;
        }

        const missingFiles = window.peiwmMissingFiles;
        let tableHtml = '<div class="peiwm-table-scroll-wrapper" style="max-height:400px;overflow-y:auto;margin:1rem 0;">';
        tableHtml += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        tableHtml += '<thead><tr style="background:#f3f4f6;position:sticky;top:0;">';
        tableHtml += '<th style="padding:8px;text-align:left;border-bottom:2px solid #e5e7eb;">ID</th>';
        tableHtml += '<th style="padding:8px;text-align:left;border-bottom:2px solid #e5e7eb;">Title</th>';
        tableHtml += '<th style="padding:8px;text-align:left;border-bottom:2px solid #e5e7eb;">Filename</th>';
        tableHtml += '<th style="padding:8px;text-align:left;border-bottom:2px solid #e5e7eb;">Expected Path</th>';
        tableHtml += '</tr></thead><tbody>';

        missingFiles.forEach(function(file) {
            tableHtml += '<tr style="border-bottom:1px solid #e5e7eb;">';
            tableHtml += '<td style="padding:8px;">' + file.id + '</td>';
            tableHtml += '<td style="padding:8px;">' + $('<div>').text(file.title || 'Unknown').html() + '</td>';
            tableHtml += '<td style="padding:8px;font-family:monospace;font-size:12px;">' + $('<div>').text(file.filename).html() + '</td>';
            tableHtml += '<td style="padding:8px;font-family:monospace;font-size:11px;color:#666;word-break:break-all;">' + $('<div>').text(file.path).html() + '</td>';
            tableHtml += '</tr>';
        });

       tableHtml += '</tbody></table></div>';

        tableHtml += '<div style="margin-top:1rem;display:flex;flex-direction:column;gap:8px;">';

        // Main info row
        tableHtml += '<div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:6px;padding:10px 12px;font-size:12.5px;color:#78350f;line-height:1.5; text-align:left;">';
        tableHtml += '<strong style="color:#92400e;">⚠️ Database records exist but files are missing from the server.</strong><br>';
        tableHtml += '<span style="color:#78350f;">Run <strong>Fix Paths</strong> first - it corrects misconfigured paths (e.g. <code style="background:#fff8;padding:1px 5px;border-radius:3px;font-size:11px;">202311</code> → <code style="background:#fff8;padding:1px 5px;border-radius:3px;font-size:11px;">2023/11</code>). If that doesn\'t help, use <strong>Clean Up</strong> to remove orphaned entries permanently.</span>';
        tableHtml += '</div>';

        // Unknown entries note
        tableHtml += '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px 12px;font-size:12.5px;color:#7c2d12;line-height:1.5; text-align:left;">';
        tableHtml += '<strong style="color:#9a3412;">❓ Entries showing "Unknown" filename or path</strong><br>';
        tableHtml += 'These records are severely corrupted - no valid file path exists in the database. This is usually caused by a broken migration, an invalid external URL stored as a local path, or a file that was never fully uploaded. <strong>Fix Paths cannot repair these.</strong> Use <strong>Clean Up</strong> to remove them, then re-upload the files via the WordPress Media Library if still needed.';
        tableHtml += '</div>';

        tableHtml += '</div>';

        // Action buttons
        tableHtml += '<div style="margin-top:1.25rem;display:flex;gap:10px;justify-content:flex-end;">';
        tableHtml += '<button type="button" id="peiwm-fix-paths-btn" class="button button-primary" style="background:#2563eb;border-color:#2563eb;">Fix Paths</button>';
        tableHtml += '<button type="button" id="peiwm-clean-missing-btn" class="button button-primary" style="background:#dc2626;border-color:#dc2626;">Clean Missing Files</button>';
        tableHtml += '</div>';

        const modal = $('#peiwm-modal-overlay');
        modal.find('.peiwm-modal-header h3').text('Missing Media Files (' + missingFiles.length + ')');
        modal.find('.peiwm-modal-body p').html(tableHtml);
        modal.find('.peiwm-modal').removeClass('peiwm-warning-modal peiwm-danger-modal').addClass('peiwm-media-missing-modal');
        modal.find('#peiwm-modal-confirm, #peiwm-modal-cancel').hide();
        modal.show().addClass('peiwm-show');

        // X button close handler
        modal.find('.peiwm-modal-close').off('click').on('click', function() {
            modal.removeClass('peiwm-show').hide();
            modal.find('.peiwm-modal').removeClass('peiwm-media-missing-modal');
            modal.find('#peiwm-modal-confirm, #peiwm-modal-cancel').show();
        });

        // Fix Paths button handler
        $('#peiwm-fix-paths-btn').on('click', function() {
            const fixBtn = $('#peiwm-fix-paths-btn');
            fixBtn.prop('disabled', true).text('Fixing...');

            $.ajax({
                url: peiwm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'peiwm_fix_missing_media_paths',
                    nonce: peiwm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        modal.removeClass('peiwm-show').hide();
                        modal.find('.peiwm-modal').removeClass('peiwm-media-missing-modal');
                        modal.find('#peiwm-modal-confirm, #peiwm-modal-cancel').show();
                        
                        let message = response.data.message;
                        if (response.data.fixed_count > 0) {
                            message += '\n\nFixed paths:\n';
                            response.data.fixed_details.slice(0, 5).forEach(function(detail) {
                                message += '\n• ID ' + detail.id + ': ' + detail.old_path + ' → ' + detail.new_path;
                            });
                            if (response.data.fixed_details.length > 5) {
                                message += '\n... and ' + (response.data.fixed_details.length - 5) + ' more';
                            }
                        }
                        
                        showSuccess(message);
                        // Show processing state on the stat card while stats reload
                        const $statDetail = $('#peiwm-media-stats .peiwm-stat-warning .peiwm-stat-detail');
                        if ($statDetail.length) {
                            $statDetail.html('<span style="color:#6b7280;">🔄 Updating stats...</span>');
                        }
                        loadMediaStats();
                    } else {
                        showError('Fix failed: ' + response.data.message);
                        fixBtn.prop('disabled', false).text('Fix Paths');
                    }
                },
                error: function() {
                    showError('Fix failed. Please try again.');
                    fixBtn.prop('disabled', false).text('Fix Paths');
                }
            });
        });

        // Clean up button handler
        $('#peiwm-clean-missing-btn').on('click', function() {
            // First close the missing files modal and restore buttons
            modal.removeClass('peiwm-show').hide();
            modal.find('.peiwm-modal').removeClass('peiwm-media-missing-modal');
            modal.find('#peiwm-modal-confirm, #peiwm-modal-cancel').show();
            
            // Then show the danger confirmation
            showDangerConfirmation(
                'Clean Up Missing Files?',
                'This will permanently delete ' + missingFiles.length + ' attachment record(s) from your database. This action cannot be undone.<br><br>Are you sure you want to proceed?'
            ).then(function() {
                // -- Show "processing" in the stat card immediately after confirm --
                const $statWarningItem = $('#peiwm-media-stats .peiwm-stat-warning');
                const $statDetail = $statWarningItem.find('.peiwm-stat-detail');
                if ($statDetail.length) {
                    $statDetail.html('<span style="color:#6b7280;">🔄 Cleaning... please wait</span>');
                }

                $.ajax({
                    url: peiwm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'peiwm_clean_missing_media',
                        nonce: peiwm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccess(response.data.message);
                            // -- Update the stat card directly — fast, no full reload --
                            if ($statWarningItem.length) {
                                $statWarningItem.removeClass('peiwm-stat-warning');
                                $statDetail.html('<span style="color:#10b981;">✅ All files present</span>');
                                // Update the number to reflect 0 missing
                                const $num = $statWarningItem.find('.peiwm-stat-number');
                                if ($num.length) {
                                    // Format: "X / Y" — set missing to 0, keep total
                                    const numText = $num.text();
                                    const parts = numText.split('/');
                                    if (parts.length === 2) {
                                        $num.text(parts[1].trim() + ' / ' + parts[1].trim());
                                    }
                                }
                            } else {
                                // Fallback: full reload if DOM not as expected
                                loadMediaStats();
                            }
                            // Clear stored missing files
                            window.peiwmMissingFiles = [];
                        } else {
                            // Restore the warning state on failure
                            if ($statDetail.length) {
                                $statDetail.html('<span style="color:#d97706;">⚠️ Cleanup failed — <button type="button" class="peiwm-view-missing-btn" style="background:none;border:none;color:#2563eb;cursor:pointer;text-decoration:underline;padding:0;font-size:11px;">View Details</button></span>');
                                $statWarningItem.addClass('peiwm-stat-warning');
                            }
                            showError('Cleanup failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        // Restore warning state on error
                        if ($statDetail.length) {
                            $statDetail.html('<span style="color:#d97706;">⚠️ Cleanup failed — <button type="button" class="peiwm-view-missing-btn" style="background:none;border:none;color:#2563eb;cursor:pointer;text-decoration:underline;padding:0;font-size:11px;">View Details</button></span>');
                            $statWarningItem.addClass('peiwm-stat-warning');
                        }
                        showError('Cleanup failed. Please try again.');
                    }
                });
            }).catch(function() {
                // User cancelled — restore the stat card if we touched it
                // (We haven't touched it yet at cancel time, so nothing to restore)
            });
        });

        // Close on overlay click
        modal.off('click').on('click', function(e) {
            if (e.target === this) {
                modal.removeClass('peiwm-show').hide();
                modal.find('.peiwm-modal').removeClass('peiwm-media-missing-modal');
                modal.find('#peiwm-modal-confirm, #peiwm-modal-cancel').show();
            }
        });

        // Close on escape key
        $(document).off('keydown.missing-modal').on('keydown.missing-modal', function(e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                modal.find('.peiwm-modal').removeClass('peiwm-media-missing-modal');
                modal.find('#peiwm-modal-confirm, #peiwm-modal-cancel').show();
                $(document).off('keydown.missing-modal');
            }
        });
    }



    // -- Advanced Options Toggle ----------------------------------------------
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

    // -- PRO inline row click → show toast (only for locked rows) -------------
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

    // -- Toast close button ----------------------------------------------------
    $(document).on('click', '.peiwm-pro-toast-close', function () {
        $(this).closest('.peiwm-pro-toast').removeClass('is-visible').fadeOut(200);
    });

    // -- Keyboard: Enter/Space on toggle --------------------------------------
    $(document).on('keydown', '.peiwm-advanced-toggle', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });
    
});
