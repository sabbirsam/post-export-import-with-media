jQuery(document).ready(function ($) {
    'use strict';

    // Initialize checkbox default states
    $('input[name="export_settings_groups[]"]').prop('checked', true);

    // Modal Utility Functions (reuse from main admin.js)
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
                modalId = '#peiwm-confirm-modal';
                modalClass = 'peiwm-confirm-modal';
                break;
        }

        const modal = $(modalId);
        modal.find('.peiwm-modal-title').text(title);
        modal.find('.peiwm-modal-message').html(message);
        modal.removeClass('peiwm-success-modal peiwm-error-modal peiwm-confirm-modal').addClass(modalClass);
        modal.addClass('peiwm-show').show();

        $(document).on('keydown.peiwm-modal', function (e) {
            if (e.key === 'Escape') {
                modal.removeClass('peiwm-show').hide();
                $(document).off('keydown.peiwm-modal');
            }
        });
    }

    function showSuccess(message) {
        showModal('success', peiwm_ajax.strings.success, message);
    }

    function showError(message) {
        showModal('error', peiwm_ajax.strings.error, message);
    }

    function showConfirm(title, message, callback) {
        showModal('confirm', title, message);
        
        $('#peiwm-confirm-yes').off('click').on('click', function () {
            $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
            if (callback) callback();
        });
        
        $('#peiwm-confirm-no').off('click').on('click', function () {
            $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
        });
    }

    function addLog(message, logContainer = null, className = '') {
        if (!logContainer) {
            logContainer = $('#peiwm-settings-progress .peiwm-log');
        }

        const time = new Date().toLocaleTimeString();
        const classAttr = className ? ' class="peiwm-log-entry ' + className + '"' : ' class="peiwm-log-entry"';
        logContainer.append('<div' + classAttr + '>[' + time + '] ' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    // Export Settings
    $('#peiwm-export-settings').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        const selectedGroups = [];
        
        $('input[name="export_settings_groups[]"]:checked').each(function() {
            selectedGroups.push($(this).val());
        });
        
        if (selectedGroups.length === 0) {
            showError('Please select at least one settings group to export.');
            return;
        }
        
        button.prop('disabled', true).text(peiwm_ajax.strings.processing);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_settings',
                nonce: peiwm_ajax.nonce,
                settings_groups: selectedGroups
            },
            success: function (response) {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'wordpress-settings-export-' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showSuccess('Settings exported successfully! ' + response.data.settings_count + ' settings from ' + response.data.groups_count + ' groups exported.');
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
    });

    // Select Settings File Drop Zone Setup
    function setupDropZoneSettings(zoneId, inputId, isWidgets = false) {
        const zone = $(zoneId);
        const input = $(inputId);

        zone.on('click', function() {
            input.click();
        });

        zone.on('dragover', function(e) {
            e.preventDefault();
            zone.addClass('dragover');
        });

        zone.on('dragleave', function(e) {
            e.preventDefault();
            zone.removeClass('dragover');
        });

        zone.on('drop', function(e) {
            e.preventDefault();
            zone.removeClass('dragover');
            if (e.originalEvent.dataTransfer.files.length) {
                input[0].files = e.originalEvent.dataTransfer.files;
                input.trigger('change');
            }
        });

        input.on('change', function() {
            const file = this.files[0];
            if (file) {
                if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                    showError('Please select a JSON file.');
                    return;
                }
                const label = this.files.length > 1 ? this.files.length + ' files selected' : file.name;
                zone.html('<svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg><b style="font-size: 14.5px; margin-bottom: 2px; color: #1e1e1e;">' + label + '</b><span style="font-size: 13.5px; color: #6c7385;">Ready to import</span>');
                
                if (isWidgets) {
                    $('#peiwm-import-widgets-menus').show();
                    $('#peiwm-widgets-menus-import-options').show();
                } else {
                    // Read and preview the settings file
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        try {
                            const settingsData = JSON.parse(e.target.result);
                            if (!settingsData.settings) {
                                throw new Error('Invalid settings file format');
                            }
                            
                            showSettingsPreview(settingsData);
                            $('#peiwm-import-settings').show();
                        } catch (error) {
                            showError('Invalid JSON file: ' + error.message);
                        }
                    };
                    reader.readAsText(file);
                }
            }
        });
    }

    setupDropZoneSettings('#peiwm-select-settings-file', '#peiwm-settings-file', false);

    function showSettingsPreview(settingsData) {
        const preview = $('#peiwm-settings-preview');
        const selection = $('#peiwm-settings-groups-selection');
        
        let html = '<div class="peiwm-checkbox-grid">';
        
        for (const group in settingsData.settings) {
            const settingsCount = Object.keys(settingsData.settings[group]).length;
            html += `
                <label class="peiwm-checkbox-label">
                    <input type="checkbox" name="import_settings_groups[]" value="${group}" checked>
                    <span class="peiwm-checkbox-text">
                        ${group.charAt(0).toUpperCase() + group.slice(1)} Settings
                        <small class="peiwm-checkbox-description">${settingsCount} settings</small>
                    </span>
                </label>
            `;
        }
        
        html += '</div>';
        selection.html(html);
        preview.show();
    }

    // Import Settings
    $('#peiwm-import-settings').on('click', function () {
        const fileInput = $('#peiwm-settings-file')[0];
        if (!fileInput.files.length) {
            showError('Please select a file to import.');
            return;
        }

        const selectedGroups = [];
        $('input[name="import_settings_groups[]"]:checked').each(function() {
            selectedGroups.push($(this).val());
        });
        
        if (selectedGroups.length === 0) {
            showError('Please select at least one settings group to import.');
            return;
        }

        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function (e) {
            try {
                const settingsData = JSON.parse(e.target.result);
                startSettingsImport(settingsData, selectedGroups);
            } catch (error) {
                showError('Invalid JSON file: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    });

    function startSettingsImport(settingsData, selectedGroups) {
        const progress = $('#peiwm-settings-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Starting settings import...');
        
        addLog('Starting import of ' + selectedGroups.length + ' settings group(s)...', log);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_import_settings',
                nonce: peiwm_ajax.nonce,
                settings_data: JSON.stringify(settingsData),
                settings_groups: selectedGroups
            },
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Import complete!');
                    addLog('✓ ' + response.data.message, log);
                    
                    // Show detailed results if available
                    if (response.data.details) {
                        const details = response.data.details;
                        
                        // Show imported settings
                        if (details.imported && details.imported.length > 0) {
                            addLog('📥 Imported Settings:', log, 'peiwm-log-success');
                            details.imported.forEach(function(item) {
                                addLog('  ✓ ' + item.option + ' (' + item.status + ')', log, 'peiwm-log-success');
                            });
                        }
                        
                        // Show skipped settings
                        if (details.skipped && details.skipped.length > 0) {
                            addLog('⚠ Skipped Settings:', log, 'peiwm-log-warning');
                            details.skipped.forEach(function(item) {
                                addLog('  ⚠ ' + item.option + ' - ' + item.reason, log, 'peiwm-log-warning');
                            });
                        }
                        
                        // Show failed settings
                        if (details.failed && details.failed.length > 0) {
                            addLog('❌ Failed Settings:', log, 'peiwm-log-error');
                            details.failed.forEach(function(item) {
                                let failMsg = '  ❌ ' + item.option + ' - ' + item.reason;
                                if (item.expected !== undefined && item.actual !== undefined) {
                                    failMsg += ' (Expected: ' + JSON.stringify(item.expected) + ', Got: ' + JSON.stringify(item.actual) + ')';
                                }
                                addLog(failMsg, log, 'peiwm-log-error');
                            });
                        }
                    }
                    
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log);
                    showError('Import failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Import failed: ' + error);
                addLog('✗ Error: ' + error, log);
                showError('Import failed: ' + error);
            }
        });
    }

    // Widgets & Menus Export Functions
    $('#peiwm-export-widgets').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text(peiwm_ajax.strings.processing);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_widgets',
                nonce: peiwm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'widgets-export-' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showSuccess('Widgets exported successfully! ' + response.data.count + ' widgets exported.');
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
    });

    $('#peiwm-export-nav-menus').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text(peiwm_ajax.strings.processing);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_nav_menus',
                nonce: peiwm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'nav-menus-export-' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showSuccess('Navigation menus exported successfully! ' + response.data.count + ' menus exported.');
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
    });

    $('#peiwm-export-widgets-menus').on('click', function () {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text(peiwm_ajax.strings.processing);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_export_widgets_menus',
                nonce: peiwm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'widgets-menus-export-' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showSuccess('Widgets and menus exported successfully! ' + response.data.widgets_count + ' widgets and ' + response.data.menus_count + ' menus exported.');
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
    });

    setupDropZoneSettings('#peiwm-select-widgets-menus-file', '#peiwm-widgets-menus-file', true);

    $('#peiwm-import-widgets-menus').on('click', function () {
        const fileInput = $('#peiwm-widgets-menus-file')[0];
        if (!fileInput.files.length) {
            showError('Please select a file to import.');
            return;
        }

        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function (e) {
            try {
                const data = JSON.parse(e.target.result);
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid file format');
                }
                
                startWidgetsMenusImport(data);
            } catch (error) {
                showError('Invalid JSON file: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    });

    function startWidgetsMenusImport(data) {
        const progress = $('#peiwm-widgets-menus-progress');
        const progressFill = progress.find('.peiwm-progress-fill');
        const progressText = progress.find('.peiwm-progress-text');
        const log = progress.find('.peiwm-log');
        const replaceExisting = $('#peiwm-replace-existing-widgets-menus').is(':checked');
        
        progress.show();
        log.empty();
        progressFill.css('width', '0%');
        progressText.text('Starting import...');
        
        addLog('Starting widgets and menus import...', log);
        
        $.ajax({
            url: peiwm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'peiwm_import_widgets_menus',
                nonce: peiwm_ajax.nonce,
                widgets_menus_data: JSON.stringify(data),
                replace_existing: replaceExisting ? '1' : '0'
            },
            success: function (response) {
                if (response.success) {
                    progressFill.css('width', '100%');
                    progressText.text('Import complete!');
                    addLog('✓ ' + response.data.message, log, 'peiwm-log-success');
                    
                    // Show detailed results
                    if (response.data.widgets_result) {
                        const wr = response.data.widgets_result;
                        if (wr.imported_count > 0) {
                            addLog('📦 Widgets: ' + wr.imported_count + ' imported, ' + wr.skipped_count + ' skipped, ' + wr.failed_count + ' failed', log, 'peiwm-log-info');
                        }
                    }
                    
                    if (response.data.menus_result) {
                        const mr = response.data.menus_result;
                        if (mr.imported_count > 0) {
                            addLog('🧭 Menus: ' + mr.imported_count + ' imported, ' + mr.skipped_count + ' skipped, ' + mr.failed_count + ' failed', log, 'peiwm-log-info');
                        }
                    }
                    
                    showSuccess(response.data.message);
                } else {
                    progressText.text('Import failed: ' + response.data.message);
                    addLog('✗ Error: ' + response.data.message, log, 'peiwm-log-error');
                    showError('Import failed: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                progressText.text('Import failed: ' + error);
                addLog('✗ Error: ' + error, log, 'peiwm-log-error');
                showError('Import failed: ' + error);
            }
        });
    }

    // Close modal handlers
    $('.peiwm-modal-close, .peiwm-modal-overlay').on('click', function (e) {
        if (e.target === this) {
            $('.peiwm-modal-overlay').removeClass('peiwm-show').hide();
            $(document).off('keydown.peiwm-modal');
        }
    });
    // Global tab switching functions for New-UI design
    window.switchTab = window.switchTab || function(group, name) {
        var tabs = document.querySelectorAll('.tabs[data-group="' + group + '"] .tab-btn');
        tabs.forEach(function(b) {
            b.classList.remove('active');
            if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + name + "'")) {
                b.classList.add('active');
            }
        });
        document.querySelectorAll('.tab-panel[data-panel^="' + group + '-"]').forEach(function(p) {
            p.classList.remove('active');
        });
        document.querySelectorAll('.tab-panel[data-group="' + group + '"]').forEach(function(p) {
            p.classList.remove('active');
        });
        var targetPanel = document.querySelector('.tab-panel[data-group="' + group + '"][data-panel="' + name + '"]');
        if (!targetPanel) {
            targetPanel = document.querySelector('.tab-panel[data-panel="' + group + '-' + name + '"]');
        }
        if (targetPanel) {
            targetPanel.classList.add('active');
        }
        var steps = document.querySelectorAll('.journey .step');
        if (steps.length > 0) {
            var foundActive = false;
            steps.forEach(function(s) {
                s.classList.remove('active', 'done');
                if (s.getAttribute('onclick') && s.getAttribute('onclick').includes("'" + name + "'")) {
                    s.classList.add('active');
                    foundActive = true;
                } else if (!foundActive) {
                    s.classList.add('done');
                }
            });
        }
    };

    window.switchTabByGroup = window.switchTabByGroup || function(group, name) {
        window.switchTab(group, name);
        var mainPage = document.getElementById('peiwm-main-content');
        if (mainPage) {
            mainPage.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };
});
