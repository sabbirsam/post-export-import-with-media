jQuery(document).ready(function ($) {
    'use strict';

    // ── Cached download URL from last export ──────────────────────────────
    var lastExportFilename = '';

    // ── Premium modal listener (admin.js not loaded on this page) ─────────
    $(document).on('click', '.peiwm-open-premium-modal, .peiwm-locked-section', function (e) {
        if ($(e.target).is('input, select, textarea, button:not(.peiwm-open-premium-modal), label, a')) return;
        e.preventDefault();
        e.stopPropagation();
        var $modal = $('#peiwm-premium-modal');
        $modal.show().addClass('peiwm-show');
        $modal.find('.peiwm-premium-close, .peiwm-modal-close').off('click').on('click', function () {
            $modal.removeClass('peiwm-show').hide();
        });
        $modal.off('click.premium').on('click.premium', function (ev) {
            if (ev.target === this) $modal.removeClass('peiwm-show').hide();
        });
        $(document).off('keydown.premium-modal').on('keydown.premium-modal', function (ev) {
            if (ev.key === 'Escape') $modal.removeClass('peiwm-show').hide();
        });
    });

    // ── Toggle: default password input (PRO only; no-op when disabled) ───
    $('#peiwm-users-set-password').on('change', function () {
        if ($(this).prop('disabled')) return;
        if ($(this).is(':checked')) {
            $('#peiwm-users-password-wrap').slideDown(200);
        } else {
            $('#peiwm-users-password-wrap').slideUp(200);
        }
    });

    // ── Toggle: password export security warning ──────────────────────────
    $('#peiwm-export-password').on('change', function () {
        if ($(this).prop('disabled')) return;
        if ($(this).is(':checked')) {
            $('#peiwm-password-warning').slideDown(200);
        } else {
            $('#peiwm-password-warning').slideUp(200);
        }
    });

    // ── Export Users ───────────────────────────────────────────────────────
    $('#peiwm-export-users').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text(peiwm_users_ajax.strings.exporting);
        $('#peiwm-users-export-result').hide().empty();

        $.ajax({
            url:  peiwm_users_ajax.ajax_url,
            type: 'POST',
            data: {
                action:             'peiwm_export_users',
                nonce:              peiwm_users_ajax.nonce,
                export_password:    $('#peiwm-export-password').is(':checked') && !$('#peiwm-export-password').prop('disabled') ? '1' : '0',
                export_meta:        $('#peiwm-export-meta').is(':checked')     && !$('#peiwm-export-meta').prop('disabled')     ? '1' : '0',
                export_woocommerce: $('#peiwm-export-woocommerce').is(':checked') && !$('#peiwm-export-woocommerce').prop('disabled') ? '1' : '0',
                export_acf:         $('#peiwm-export-acf').is(':checked')      && !$('#peiwm-export-acf').prop('disabled')      ? '1' : '0',
                export_cpt:         $('#peiwm-export-cpt').is(':checked')      && !$('#peiwm-export-cpt').prop('disabled')      ? '1' : '0',
            },
            success: function (response) {
                $btn.prop('disabled', false).text(peiwm_users_ajax.strings.export_btn);
                if (response.success) {
                    lastExportFilename = response.data.filename;
                    // Build download URL via admin-post handler with nonce
                    var dlUrl = peiwm_users_ajax.download_url 
                        + '&file=' + encodeURIComponent(lastExportFilename)
                        + '&_wpnonce=' + encodeURIComponent(peiwm_users_ajax.download_nonce);
                    $('#peiwm-users-export-result').html(
                        '<p style="color:#10b981;">✅ ' + $('<div>').text(response.data.message).html() + '</p>' +
                        '<a href="' + dlUrl + '" class="button button-secondary" download>' +
                            '⬇ ' + peiwm_users_ajax.strings.download_json +
                        '</a>'
                    ).show();
                } else {
                    $('#peiwm-users-export-result').html(
                        '<p style="color:#ef4444;">❌ ' + $('<div>').text(response.data.message || peiwm_users_ajax.strings.error).html() + '</p>'
                    ).show();
                }
            },
            error: function () {
                $btn.prop('disabled', false).text(peiwm_users_ajax.strings.export_btn);
                $('#peiwm-users-export-result').html(
                    '<p style="color:#ef4444;">❌ ' + peiwm_users_ajax.strings.error + '</p>'
                ).show();
            }
        });
    });

    // ── File picker ────────────────────────────────────────────────────────
    $('#peiwm-users-select-file').on('click', function () {
        $('#peiwm-users-file').click();
    });

    $('#peiwm-users-file').on('change', function () {
        if (this.files.length > 0) {
            $('#peiwm-users-select-file').text(this.files[0].name);
            $('#peiwm-import-users').show();
        } else {
            $('#peiwm-import-users').hide();
        }
    });

    // ── Import Users ───────────────────────────────────────────────────────
    $('#peiwm-import-users').on('click', function () {
        var file = $('#peiwm-users-file')[0].files[0];
        if (!file) {
            alert(peiwm_users_ajax.strings.select_file);
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(peiwm_users_ajax.strings.importing);
        $('#peiwm-users-import-result').hide().empty();

        var reader = new FileReader();
        reader.onload = function (e) {
            var usersJson = e.target.result;

            // Validate JSON before sending
            try {
                JSON.parse(usersJson);
            } catch (err) {
                $btn.prop('disabled', false).text(peiwm_users_ajax.strings.import_btn);
                $('#peiwm-users-import-result').html(
                    '<p style="color:#ef4444;">❌ ' + peiwm_users_ajax.strings.invalid_json + '</p>'
                ).show();
                return;
            }

            var defaultPassword = $('#peiwm-users-set-password').is(':checked')
                ? $('#peiwm-users-default-password').val()
                : '';

            $.ajax({
                url:  peiwm_users_ajax.ajax_url,
                type: 'POST',
                data: {
                    action:           'peiwm_import_users',
                    nonce:            peiwm_users_ajax.nonce,
                    users_json:       usersJson,
                    default_password: defaultPassword,
                    send_email:       $('#peiwm-users-send-email').is(':checked') ? '1' : '0',
                    force_same_id:    $('#peiwm-users-force-id').is(':checked') ? '1' : '0',
                    import_password:  $('#peiwm-export-password').is(':checked') && !$('#peiwm-export-password').prop('disabled') ? '1' : '0',
                    import_meta:      $('#peiwm-export-meta').is(':checked')     && !$('#peiwm-export-meta').prop('disabled')     ? '1' : '0',
                    import_woocommerce: $('#peiwm-export-woocommerce').is(':checked') && !$('#peiwm-export-woocommerce').prop('disabled') ? '1' : '0',
                    import_acf:       $('#peiwm-export-acf').is(':checked')      && !$('#peiwm-export-acf').prop('disabled')      ? '1' : '0',
                },
                success: function (response) {
                    $btn.prop('disabled', false).text(peiwm_users_ajax.strings.import_btn);
                    if (response.success) {
                        renderImportSummary(response.data);
                    } else {
                        $('#peiwm-users-import-result').html(
                            '<p style="color:#ef4444;">❌ ' + $('<div>').text(response.data.message || peiwm_users_ajax.strings.error).html() + '</p>'
                        ).show();
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).text(peiwm_users_ajax.strings.import_btn);
                    $('#peiwm-users-import-result').html(
                        '<p style="color:#ef4444;">❌ ' + peiwm_users_ajax.strings.error + '</p>'
                    ).show();
                }
            });
        };
        reader.readAsText(file);
    });

    // ── Build import summary card from JSON response ───────────────────────
    function renderImportSummary(data) {
        var sendEmail   = $('#peiwm-users-send-email').is(':checked');
        var forceId     = $('#peiwm-users-force-id').is(':checked');
        var hasMismatch = data.id_mismatches && data.id_mismatches.length > 0;

        var rows = '';

        rows += summaryRow('✅', peiwm_users_ajax.strings.summary_imported, data.imported);
        rows += summaryRow('⏭', peiwm_users_ajax.strings.summary_skipped, data.skipped);

        if (forceId) {
            rows += summaryRow('🔒', peiwm_users_ajax.strings.summary_id_preserved, data.id_preserved);

            if (hasMismatch) {
                var detailRows = '';
                $.each(data.id_mismatches, function (i, m) {
                    detailRows += '<tr><td>' + $('<div>').text(m.login).html() + '</td>' +
                        '<td>' + m.original_id + ' → ' + m.new_id + '</td></tr>';
                });
                rows += '<tr>' +
                    '<td>⚠</td>' +
                    '<td>' + peiwm_users_ajax.strings.summary_id_mismatch + '</td>' +
                    '<td>' + data.id_mismatches.length +
                        ' <button type="button" class="button-link peiwm-toggle-mismatch" style="font-size:0.8rem;">▼ ' +
                        peiwm_users_ajax.strings.show_details + '</button>' +
                    '</td>' +
                '</tr>' +
                '<tr class="peiwm-mismatch-details" style="display:none;">' +
                    '<td colspan="3">' +
                        '<table style="width:100%;font-size:0.8rem;margin-top:0.5rem;">' +
                            '<thead><tr><th style="text-align:left;">Login</th><th style="text-align:left;">ID change</th></tr></thead>' +
                            '<tbody>' + detailRows + '</tbody>' +
                        '</table>' +
                    '</td>' +
                '</tr>';
            }
        }

        if (sendEmail) {
            if (data.mail_not_configured) {
                rows += '<tr><td>ℹ</td><td colspan="2">' + peiwm_users_ajax.strings.mail_not_configured + '</td></tr>';
            } else {
                rows += summaryRow('✉', peiwm_users_ajax.strings.summary_emails_sent, data.emails_sent);
                if (data.emails_failed > 0) {
                    rows += summaryRow('✉', peiwm_users_ajax.strings.summary_emails_failed, data.emails_failed);
                }
            }
        }

        var html = '<div class="peiwm-users-summary-card">' +
            '<h4>' + peiwm_users_ajax.strings.summary_title + '</h4>' +
            '<table class="peiwm-users-summary-table">' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>';

        $('#peiwm-users-import-result').html(html).show();

        // Toggle mismatch details
        $(document).off('click.mismatch').on('click.mismatch', '.peiwm-toggle-mismatch', function () {
            var $row = $(this).closest('tr').next('.peiwm-mismatch-details');
            $row.toggle();
            $(this).text($row.is(':visible') ? '▲ ' + peiwm_users_ajax.strings.hide_details : '▼ ' + peiwm_users_ajax.strings.show_details);
        });
    }

    function summaryRow(icon, label, value) {
        return '<tr><td>' + icon + '</td><td>' + label + '</td><td><strong>' + value + '</strong></td></tr>';
    }

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
