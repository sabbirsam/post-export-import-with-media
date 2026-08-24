(function ($) {
    'use strict';

    let currentScanId = null;
    let pollTimer = null;

    $(document).ready(function () {
        initAuditEvents();
        initReviewTableEvents();
    });

    function initAuditEvents() {
        // Start Audit Button
        $('#peiwm-btn-start-audit').on('click', function (e) {
            e.preventDefault();
            startAuditScan();
        });

        // Move single item to trash
        $(document).on('click', '.peiwm-trash-single-btn', function (e) {
            e.preventDefault();
            const attId = $(this).data('id');
            confirmSingleDecision(attId, 'trash', 'Move Media to Trash', 'Are you sure you want to move this media item to Trash?');
        });

        // Mark single item as Safe
        $(document).on('click', '.peiwm-action-safe-btn', function (e) {
            e.preventDefault();
            const attId = $(this).data('id');
            confirmSingleDecision(attId, 'safe', 'Mark as Safe', 'Are you sure you want to mark this media item as safe? It will be kept in your library and excluded from unused flag list.');
        });

        // Exclude single item
        $(document).on('click', '.peiwm-action-exclude-btn', function (e) {
            e.preventDefault();
            const attId = $(this).data('id');
            confirmSingleDecision(attId, 'exclude', 'Exclude Media', 'Are you sure you want to exclude this media item from current and future audits?');
        });

        // PRO feature lock click
        $(document).on('click', '.peiwm-pro-only-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if ($(this).is(':checkbox')) {
                $(this).prop('checked', false);
            }
            if (typeof window.peiwmOpenPremiumModal === 'function') {
                window.peiwmOpenPremiumModal();
            } else if ($('#peiwm-premium-modal').length) {
                $('#peiwm-premium-modal').show().addClass('peiwm-show');
            }
        });
    }

    function initReviewTableEvents() {
        // Select All Checkbox
        $('#peiwm-select-all').on('change', function () {
            const isChecked = $(this).is(':checked');
            $('#peiwm-review-tbody .peiwm-select-item:visible').prop('checked', isChecked);
        });

        // Apply Bulk Action Button
        $('#peiwm-btn-apply-bulk').on('click', function (e) {
            e.preventDefault();
            executeBulkAction();
        });

        // PRO Bulk Trash All Unused button
        $('.peiwm-btn-bulk-trash-all').on('click', function (e) {
            e.preventDefault();
            const allIds = [];
            $('#peiwm-review-tbody tr[data-id]').each(function () {
                allIds.push($(this).data('id'));
            });
            if (!allIds.length) {
                showPluginError('No unused media files to trash.');
                return;
            }
            confirmBulkDecision(allIds, 'trash', 'Bulk Move All Unused to Trash', 'Are you sure you want to move all ' + allIds.length + ' unused media items to Trash?');
        });

        // Filtering & Sorting Inputs
        $('#peiwm-review-search, #peiwm-filter-risk, #peiwm-filter-confidence, #peiwm-sort-by').on('input change', function () {
            applyTableFilters();
        });
    }

    function applyTableFilters() {
        const query = ($('#peiwm-review-search').val() || '').toLowerCase().trim();
        const risk = ($('#peiwm-filter-risk').val() || '').toLowerCase().trim();
        const minConf = parseInt($('#peiwm-filter-confidence').val() || '0', 10);
        const sortBy = $('#peiwm-sort-by').val() || 'id_desc';

        const rows = $('#peiwm-review-tbody tr[data-id]').get();

        rows.forEach(function (row) {
            const $row = $(row);
            const title = String($row.data('title') || '').toLowerCase();
            const rowRisk = String($row.data('risk') || '').toLowerCase().trim();
            const rowConf = parseInt($row.data('confidence') || '0', 10);

            let visible = true;

            if (query && title.indexOf(query) === -1) {
                visible = false;
            }
            if (risk && rowRisk !== risk) {
                visible = false;
            }
            if (minConf > 0 && rowConf < minConf) {
                visible = false;
            }

            if (visible) {
                $row.show();
            } else {
                $row.hide();
                $row.find('.peiwm-select-item').prop('checked', false);
            }
        });

        // Sorting
        rows.sort(function (a, b) {
            const idA = parseInt($(a).data('id'), 10);
            const idB = parseInt($(b).data('id'), 10);
            const confA = parseInt($(a).data('confidence'), 10);
            const confB = parseInt($(b).data('confidence'), 10);
            const riskWeight = { 'critical': 5, 'high': 4, 'medium': 3, 'low': 2, 'very low': 1 };
            const riskA = riskWeight[String($(a).data('risk') || '').toLowerCase().trim()] || 0;
            const riskB = riskWeight[String($(b).data('risk') || '').toLowerCase().trim()] || 0;

            if (sortBy === 'id_asc') return idA - idB;
            if (sortBy === 'id_desc') return idB - idA;
            if (sortBy === 'confidence_desc') return confB - confA;
            if (sortBy === 'risk_desc') return riskB - riskA;
            return idB - idA;
        });

        $.each(rows, function (idx, row) {
            $('#peiwm-review-tbody').append(row);
        });
    }

    function executeBulkAction() {
        const action = $('#peiwm-bulk-action-select').val();
        if (!action) {
            showPluginError('Please select a bulk action from the dropdown.');
            return;
        }

        const selectedIds = [];
        $('#peiwm-review-tbody .peiwm-select-item:checked').each(function () {
            selectedIds.push(parseInt($(this).val(), 10));
        });

        if (!selectedIds.length) {
            showPluginError('Please select at least one media item checkbox.');
            return;
        }

        let title = 'Bulk Action';
        let msg = 'Are you sure you want to apply this bulk action to ' + selectedIds.length + ' item(s)?';

        if (action === 'trash') {
            title = 'Move Selected to Trash';
            msg = 'Are you sure you want to move ' + selectedIds.length + ' selected media item(s) to Trash?';
        } else if (action === 'safe') {
            title = 'Mark Selected as Safe';
            msg = 'Are you sure you want to mark ' + selectedIds.length + ' selected item(s) as safe?';
        } else if (action === 'exclude') {
            title = 'Exclude Selected Forever';
            msg = 'Are you sure you want to exclude ' + selectedIds.length + ' selected item(s) from future audits?';
        }

        confirmBulkDecision(selectedIds, action, title, msg);
    }

    function confirmSingleDecision(attId, decision, title, msg) {
        if (typeof window.peiwmShowDangerConfirmation === 'function') {
            window.peiwmShowDangerConfirmation(title, msg).then(function () {
                executeDecisionAJAX([attId], decision);
            }).catch(function () {
                // User cancelled
            });
        } else {
            executeDecisionAJAX([attId], decision);
        }
    }

    function confirmBulkDecision(attIds, decision, title, msg) {
        if (typeof window.peiwmShowDangerConfirmation === 'function') {
            window.peiwmShowDangerConfirmation(title, msg).then(function () {
                executeDecisionAJAX(attIds, decision);
            }).catch(function () {
                // User cancelled
            });
        } else {
            executeDecisionAJAX(attIds, decision);
        }
    }

    function executeDecisionAJAX(attIds, decision) {
        $.ajax({
            url: peiwm_media_audit.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_update_media_decision',
                attachment_ids: attIds,
                decision: decision,
                nonce: peiwm_media_audit.nonce
            },
            success: function (response) {
                if (response.success) {
                    attIds.forEach(function (id) {
                        $('#peiwm-row-' + id).fadeOut(300, function () {
                            $(this).remove();
                            updateUnusedBadgeCount();
                        });
                    });

                    if (typeof window.peiwmShowSuccess === 'function') {
                        window.peiwmShowSuccess(response.data.message || 'Media items updated successfully.');
                    }
                } else {
                    showPluginError(response.data.message || 'Failed to update media decision');
                }
            },
            error: function () {
                showPluginError('Network error executing media action.');
            }
        });
    }

    function updateUnusedBadgeCount() {
        const remaining = $('#peiwm-review-tbody tr[data-id]').length;
        $('#peiwm-unused-count-badge').text(remaining + ' Unused');
    }

    function startAuditScan() {
        const btn = $('#peiwm-btn-start-audit');
        btn.prop('disabled', true).addClass('updating-message');

        $('#peiwm-audit-progress-card').slideDown(200);
        updateProgress(0, 'Starting media library scan...');

        $.ajax({
            url: peiwm_media_audit.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_start_audit',
                nonce: peiwm_media_audit.nonce
            },
            success: function (response) {
                if (response.success) {
                    currentScanId = response.data.scan_id;
                    addLog('[System] Scan #' + currentScanId + ' initialized.');
                    pollProgress();
                } else {
                    btn.prop('disabled', false).removeClass('updating-message');
                    showPluginError(response.data.message || 'Failed to start scan');
                }
            },
            error: function () {
                btn.prop('disabled', false).removeClass('updating-message');
                showPluginError('Network failure initiating audit scan.');
            }
        });
    }

    function pollProgress() {
        if (!currentScanId) return;

        $.ajax({
            url: peiwm_media_audit.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_audit_progress',
                scan_id: currentScanId,
                nonce: peiwm_media_audit.nonce
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    updateProgress(data.progress, 'Scanning attachments...');

                    if (data.logs && data.logs.length) {
                        data.logs.forEach(function (log) {
                            addLog('[' + log.scanner + '] ' + log.message);
                        });
                    }

                    if (data.completed) {
                        updateProgress(100, 'Scan completed!');
                        addLog('[System] Audit finished successfully. Reloading results...');
                        setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                    } else {
                        pollTimer = setTimeout(pollProgress, 800);
                    }
                } else {
                    showPluginError(response.data.message || 'Error processing scan chunk');
                    $('#peiwm-btn-start-audit').prop('disabled', false).removeClass('updating-message');
                }
            },
            error: function () {
                showPluginError('Network communication error during scan.');
                $('#peiwm-btn-start-audit').prop('disabled', false).removeClass('updating-message');
            }
        });
    }

    function updateProgress(percent, statusText) {
        $('#peiwm-audit-bar').css('width', percent + '%');
        $('#peiwm-audit-percent-text').text(percent + '%');
        if (statusText) {
            $('#peiwm-audit-status-text').text(statusText);
        }
    }

    function addLog(msg) {
        const logBox = $('#peiwm-audit-log-list');
        logBox.append('<div>' + escapeHtml(msg) + '</div>');
        logBox.scrollTop(logBox[0].scrollHeight);
    }

    function showPluginError(msg) {
        if (typeof window.peiwmShowError === 'function') {
            window.peiwmShowError(msg);
        } else if (typeof window.showError === 'function') {
            window.showError(msg);
        }
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery);
