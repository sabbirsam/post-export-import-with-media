jQuery(document).ready(function($) {
	'use strict';

	// Premium Modal handler for scheduled exports page
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

	// Toggle email config visibility (only if PRO is active)
	$('#enable_email_notifications').on('change', function() {
		if ($(this).is(':checked')) {
			$('#peiwm-email-config').slideDown();
		} else {
			$('#peiwm-email-config').slideUp();
		}
	});

	// Toggle rotation config visibility (only if PRO is active)
	$('#enable_backup_rotation').on('change', function() {
		if ($(this).is(':checked')) {
			$('#peiwm-rotation-config').slideDown();
		} else {
			$('#peiwm-rotation-config').slideUp();
		}
	});

	// Storage mode selection
	$('input[name="peiwm_scheduled_exports[storage_mode]"]').on('change', function() {
		$('.peiwm-storage-mode-card').removeClass('active');
		$(this).closest('.peiwm-storage-mode-card').addClass('active');
		
		if ($(this).val() === 'local') {
			$('#peiwm-local-storage-info').slideDown();
		} else {
			$('#peiwm-local-storage-info').slideUp();
		}
	});

	// Load backups on page load — PRO only
	if ( peiwm_scheduled_exports.is_pro === '1' ) {
		loadBackups();
	}

	// Refresh backups button — PRO only
	$('#peiwm-refresh-backups').on('click', function() {
		if ( peiwm_scheduled_exports.is_pro === '1' ) {
			loadBackups();
		}
	});

	/**
	 * Load backups list
	 */
	function loadBackups() {
		const $list = $('#peiwm-backups-list');
		
		$list.html('<div class="peiwm-loading"><div class="peiwm-loading-spinner"></div><p>Loading backups...</p></div>');

		$.ajax({
			url: peiwm_scheduled_exports.ajax_url,
			type: 'POST',
			data: {
				action: 'peiwm_get_scheduled_backups',
				nonce: peiwm_scheduled_exports.nonce
			},
			success: function(response) {
				if (response.success) {
					displayBackups(response.data);
				} else {
					showError('Failed to load backups: ' + response.data.message);
				}
			},
			error: function(xhr, status, error) {
				showError('Error loading backups: ' + error);
			}
		});
	}

	/**
	 * Display backups list
	 */
	function displayBackups(data) {
		const $list = $('#peiwm-backups-list');
		
		if (!data.backups || data.backups.length === 0) {
			$list.html(`
				<div class="peiwm-no-backups">
					<div class="peiwm-no-backups-icon">📦</div>
					<p>No scheduled backups found yet.</p>
					<p class="description">Backups will appear here once the scheduled export runs.</p>
				</div>
			`);
			return;
		}

		let html = '<div class="peiwm-backups-grid">';
		
		data.backups.forEach(function(backup) {
			html += `
				<div class="peiwm-backup-item" data-filename="${backup.filename}">
					<div class="peiwm-backup-info">
						<div class="peiwm-backup-filename">${backup.filename}</div>
						<div class="peiwm-backup-meta">
							<span>📅 ${backup.date}</span>
							<span>💾 ${backup.size}</span>
						</div>
					</div>
					<div class="peiwm-backup-actions">
						<button type="button" class="button button-secondary peiwm-download-backup" data-filename="${backup.filename}">
							Download
						</button>
						<button type="button" class="button button-danger peiwm-delete-backup" data-filename="${backup.filename}">
							Delete
						</button>
					</div>
				</div>
			`;
		});
		
		html += '</div>';
		html += `<p class="description" style="margin-top: 1rem;">Total backups: ${data.total_count} | Storage path: <code>${data.backup_path}</code></p>`;
		
		$list.html(html);
	}

	/**
	 * Download backup — PRO only
	 */
	$(document).on('click', '.peiwm-download-backup', function() {
		if ( peiwm_scheduled_exports.is_pro !== '1' ) { return; }
		const filename = $(this).data('filename');
		const downloadUrl = peiwm_scheduled_exports.ajax_url + 
			'?action=peiwm_download_scheduled_backup' +
			'&nonce=' + peiwm_scheduled_exports.nonce +
			'&filename=' + encodeURIComponent(filename);
		
		window.location.href = downloadUrl;
	});

	/**
	 * Delete backup — PRO only
	 */
	$(document).on('click', '.peiwm-delete-backup', function() {
		if ( peiwm_scheduled_exports.is_pro !== '1' ) { return; }
		const $button = $(this);
		const filename = $button.data('filename');
		
		// Show delete confirmation modal
		showDeleteModal(filename).then(function() {
			// User confirmed - proceed with deletion
			$button.prop('disabled', true).text('Deleting...');

			$.ajax({
				url: peiwm_scheduled_exports.ajax_url,
				type: 'POST',
				data: {
					action: 'peiwm_delete_scheduled_backup',
					nonce: peiwm_scheduled_exports.nonce,
					filename: filename
				},
				success: function(response) {
					if (response.success) {
						$button.closest('.peiwm-backup-item').fadeOut(300, function() {
							$(this).remove();
							
							// Check if no backups left
							if ($('.peiwm-backup-item').length === 0) {
								loadBackups();
							}
						});
						showSuccess('Backup deleted successfully');
					} else {
						showError('Failed to delete backup: ' + response.data.message);
						$button.prop('disabled', false).text('Delete');
					}
				},
				error: function(xhr, status, error) {
					showError('Error deleting backup: ' + error);
					$button.prop('disabled', false).text('Delete');
				}
			});
		}).catch(function() {
			// User cancelled - do nothing
		});
	});

	/**
	 * Show delete confirmation modal
	 */
	function showDeleteModal(filename) {
		return new Promise(function(resolve, reject) {
			const modal = $('#peiwm-delete-modal');
			
			// Set filename in modal
			modal.find('.peiwm-modal-filename').text(filename);
			
			// Show modal
			modal.show().addClass('peiwm-show');
			
			// Handle confirm button
			$('#peiwm-delete-confirm').off('click').on('click', function() {
				hideModal('#peiwm-delete-modal');
				resolve();
			});
			
			// Handle cancel button
			$('#peiwm-delete-cancel').off('click').on('click', function() {
				hideModal('#peiwm-delete-modal');
				reject();
			});
			
			// Handle close button
			modal.find('.peiwm-modal-close').off('click').on('click', function() {
				hideModal('#peiwm-delete-modal');
				reject();
			});
			
			// Handle overlay click
			modal.off('click').on('click', function(e) {
				if (e.target === this) {
					hideModal('#peiwm-delete-modal');
					reject();
				}
			});
			
			// Handle escape key
			$(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function(e) {
				if (e.key === 'Escape') {
					hideModal('#peiwm-delete-modal');
					reject();
				}
			});
		});
	}

	/**
	 * Hide modal
	 */
	function hideModal(modalId) {
		$(modalId).removeClass('peiwm-show').fadeOut(300);
		$(document).off('keydown.peiwm-modal');
	}

	/**
	 * Show success message
	 */
	function showSuccess(message) {
		showToast('success', message);
	}

	/**
	 * Show error message
	 */
	function showError(message) {
		showToast('error', message);
	}

	/**
	 * Show toast notification
	 */
	function showToast(type, message) {
		// Remove any existing toasts
		$('.peiwm-notification').remove();
		
		const toast = $('<div class="peiwm-notification peiwm-' + type + '">' + message + '</div>');
		$('body').append(toast);
		
		// Show toast
		setTimeout(function() {
			toast.addClass('peiwm-show');
		}, 100);
		
		// Auto-hide after 3 seconds
		setTimeout(function() {
			toast.removeClass('peiwm-show');
			setTimeout(function() {
				toast.remove();
			}, 300);
		}, 3000);
	}
});
