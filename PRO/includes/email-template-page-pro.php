<?php
/**
 * Email Template Settings Page
 *
 * @package Post_Export_Import_With_Media
 * @since 1.5.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro_active = PEIWM_Main::get_instance()->is_pro_active();
$settings = PEIWM_Email_Template::get_settings();
$available_tags = PEIWM_Email_Template::get_available_tags();
?>
<div class="wrap peiwm-settings-wrap">
	<h1><?php echo esc_html__( 'Email Template Settings', 'post-export-import-with-media' ); ?></h1>
	
	<div class="peiwm-settings-header">
		<p class="description">
			<?php echo esc_html__( 'Customize the email templates used for user welcome emails and scheduled export notifications. These settings apply globally to all emails sent by the plugin.', 'post-export-import-with-media' ); ?>
		</p>
	</div>

	<?php settings_errors( 'peiwm_email_template_settings' ); ?>

	<form method="post" action="options.php" id="peiwm-email-template-form">
		<?php settings_fields( 'peiwm_email_template_settings' ); ?>
		
		<?php
		$locked_class = ! $is_pro_active ? ' peiwm-locked-section' : '';
		?>
		<div class="<?php echo esc_attr( $locked_class ); ?>" style="position: relative;">
			<?php if ( ! $is_pro_active ) : ?>
				<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
					<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
				</button>
			<?php endif; ?>

			<table class="form-table peiwm-settings-table">
				<tbody>
					<!-- Brand Name -->
					<tr>
						<th scope="row">
							<label for="brand_name">
								<?php echo esc_html__( 'Brand Name', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="text" 
								id="brand_name" 
								name="peiwm_email_template_settings[brand_name]" 
								value="<?php echo esc_attr( $settings['brand_name'] ); ?>" 
								class="regular-text"
								<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'The brand name displayed in email headers. Default: Your site name', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>

					<!-- Primary Color -->
					<tr>
						<th scope="row">
							<label for="primary_color">
								<?php echo esc_html__( 'Primary Color', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="color" 
								id="primary_color" 
								name="peiwm_email_template_settings[primary_color]" 
								value="<?php echo esc_attr( $settings['primary_color'] ); ?>" 
								<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'Primary color for email header gradient and buttons. Default: #2563eb', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>

					<!-- Secondary Color -->
					<tr>
						<th scope="row">
							<label for="secondary_color">
								<?php echo esc_html__( 'Secondary Color', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="color" 
								id="secondary_color" 
								name="peiwm_email_template_settings[secondary_color]" 
								value="<?php echo esc_attr( $settings['secondary_color'] ); ?>" 
								<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'Secondary color for email header gradient. Default: #1d4ed8', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>

					<!-- Header Text Color -->
					<tr>
						<th scope="row">
							<label for="header_text_color">
								<?php echo esc_html__( 'Header Text Color', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="color" 
								id="header_text_color" 
								name="peiwm_email_template_settings[header_text_color]" 
								value="<?php echo esc_attr( $settings['header_text_color'] ); ?>" 
								<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'Text color for email header. Default: #ffffff', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>

					<!-- Body Text Color -->
					<tr>
						<th scope="row">
							<label for="body_text_color">
								<?php echo esc_html__( 'Body Text Color', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="color" 
								id="body_text_color" 
								name="peiwm_email_template_settings[body_text_color]" 
								value="<?php echo esc_attr( $settings['body_text_color'] ); ?>" 
								<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'Body text color for email content. Default: #4b5563', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>

					<!-- Show Branding -->
					<tr>
						<th scope="row">
							<label for="show_branding">
								<?php echo esc_html__( 'Show Plugin Branding', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<label class="peiwm-toggle-switch">
								<input 
									type="checkbox" 
									id="show_branding" 
									name="peiwm_email_template_settings[show_branding]" 
									value="1" 
									<?php checked( $settings['show_branding'], true ); ?>
									<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
								/>
								<span class="peiwm-toggle-slider"></span>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Show "Powered by Post Export Import with Media" in email footer', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>

					<!-- Custom Footer -->
					<tr>
						<th scope="row">
							<label for="custom_footer">
								<?php echo esc_html__( 'Custom Footer Text', 'post-export-import-with-media' ); ?>
							</label>
						</th>
						<td>
							<textarea 
								id="custom_footer" 
								name="peiwm_email_template_settings[custom_footer]" 
								rows="4" 
								class="large-text"
								<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
							><?php echo esc_textarea( $settings['custom_footer'] ); ?></textarea>
							<p class="description">
								<?php echo esc_html__( 'Optional custom text to display in email footer. Supports HTML and template tags.', 'post-export-import-with-media' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Available Tags -->
			<div class="peiwm-settings-section">
				<h2><?php echo esc_html__( 'Available Template Tags', 'post-export-import-with-media' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Use these tags in your custom footer text. They will be automatically replaced with actual values when emails are sent.', 'post-export-import-with-media' ); ?>
				</p>
				<div class="peiwm-tags-grid">
					<?php foreach ( $available_tags as $tag => $description ) : ?>
						<div class="peiwm-tag-item">
							<code class="peiwm-tag-code"><?php echo esc_html( $tag ); ?></code>
							<span class="peiwm-tag-desc"><?php echo esc_html( $description ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Test Email -->
			<div class="peiwm-settings-section" style="margin-top: 32px;">
				<h2><?php echo esc_html__( 'Test Email', 'post-export-import-with-media' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Send a test email to preview your template settings.', 'post-export-import-with-media' ); ?>
				</p>
				<div class="peiwm-test-email-form" style="margin-top: 16px;">
					<input 
						type="email" 
						id="test_email_address" 
						placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" 
						class="regular-text"
						<?php echo ! $is_pro_active ? 'readonly' : ''; ?>
					/>
					<button 
						type="button" 
						id="peiwm-send-test-email" 
						class="button button-secondary"
						<?php echo ! $is_pro_active ? 'disabled' : ''; ?>
					>
						<?php echo esc_html__( 'Send Test Email', 'post-export-import-with-media' ); ?>
					</button>
				</div>
			</div>

			<?php if ( $is_pro_active ) : ?>
				<div class="peiwm-button-group" style="margin-top: 32px;">
					<?php submit_button( __( 'Save Settings', 'post-export-import-with-media' ), 'primary', 'submit', false ); ?>
					<button type="button" id="peiwm-reset-email-template" class="button button-secondary">
						<?php echo esc_html__( 'Reset to Defaults', 'post-export-import-with-media' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</form>
</div>

<!-- Premium Upgrade Modal -->
<div id="peiwm-premium-modal" class="peiwm-modal-overlay" style="display: none;">
	<div class="peiwm-modal peiwm-premium-modal">
		<button type="button" class="peiwm-modal-close peiwm-premium-close">&times;</button>
		<div class="peiwm-premium-modal-body">
			<div class="peiwm-premium-badge-wrap">
				<span class="peiwm-premium-fire">🔥</span>
				<span class="peiwm-premium-offer-tag"><?php echo esc_html__( 'LIMITED TIME OFFER', 'post-export-import-with-media' ); ?></span>
			</div>
			<div class="peiwm-premium-icon">🚀</div>
			<h2 class="peiwm-premium-title"><?php echo esc_html__( 'Unlock PRO Features', 'post-export-import-with-media' ); ?></h2>
			<p class="peiwm-premium-subtitle"><?php echo esc_html__( 'Customize your email templates with PRO!', 'post-export-import-with-media' ); ?></p>
			<div class="peiwm-premium-features">
				<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Custom Brand Colors', 'post-export-import-with-media' ); ?></div>
				<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Custom Footer Text', 'post-export-import-with-media' ); ?></div>
				<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Template Tags Support', 'post-export-import-with-media' ); ?></div>
				<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Test Email Preview', 'post-export-import-with-media' ); ?></div>
			</div>
			<div class="peiwm-premium-urgency">
				<span class="peiwm-urgency-dot"></span>
				<?php echo esc_html__( 'Special offer active — grab it before it\'s gone!', 'post-export-import-with-media' ); ?>
			</div>
			<a href="https://wpazleen.com/post-export-import-with-media/" target="_blank" class="peiwm-premium-cta-btn">
				<?php echo esc_html__( 'Get PRO Now →', 'post-export-import-with-media' ); ?>
			</a>
			<p class="peiwm-premium-note"><?php echo esc_html__( 'Instant access · 14-day money back guarantee', 'post-export-import-with-media' ); ?></p>
		</div>
	</div>
</div>

<!-- Reset Confirmation Modal -->
<div id="peiwm-reset-modal" class="peiwm-modal-overlay" style="display: none;">
	<div class="peiwm-modal peiwm-danger-modal">
		<div class="peiwm-modal-header">
			<h3><?php echo esc_html__( 'Reset Email Template', 'post-export-import-with-media' ); ?></h3>
			<button type="button" class="peiwm-modal-close">&times;</button>
		</div>
		<div class="peiwm-modal-body">
			<div class="peiwm-danger-icon">⚠️</div>
			<p><?php echo esc_html__( 'Are you sure you want to reset the email template to default settings? All your customizations will be lost. This action cannot be undone.', 'post-export-import-with-media' ); ?></p>
		</div>
		<div class="peiwm-modal-footer">
			<button type="button" id="peiwm-reset-cancel" class="button button-secondary">
				<?php echo esc_html__( 'Cancel', 'post-export-import-with-media' ); ?>
			</button>
			<button type="button" id="peiwm-reset-confirm" class="button button-danger">
				<?php echo esc_html__( 'Reset to Defaults', 'post-export-import-with-media' ); ?>
			</button>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	'use strict';

	// Premium modal handler
	$(document).on('click', '.peiwm-open-premium-modal, .peiwm-locked-section', function(e) {
		if ($(e.target).is('input, select, textarea, button:not(.peiwm-open-premium-modal), label, a')) return;
		e.preventDefault();
		e.stopPropagation();
		const modal = $('#peiwm-premium-modal');
		modal.show().addClass('peiwm-show');
		modal.find('.peiwm-premium-close, .peiwm-modal-close').off('click').on('click', function() {
			modal.removeClass('peiwm-show').hide();
		});
		modal.off('click.premium').on('click.premium', function(ev) {
			if (ev.target === this) modal.removeClass('peiwm-show').hide();
		});
		$(document).off('keydown.premium-modal').on('keydown.premium-modal', function(ev) {
			if (ev.key === 'Escape') modal.removeClass('peiwm-show').hide();
		});
	});

	// Reset email template
	$('#peiwm-reset-email-template').on('click', function() {
		showResetModal().then(function() {
			// User confirmed - proceed with reset
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'peiwm_reset_email_template',
					nonce: '<?php echo esc_js( wp_create_nonce( 'peiwm_secure_nonce' ) ); ?>'
				},
				success: function(response) {
					if (response.success) {
						showToast('success', '<?php echo esc_js( __( 'Email template reset successfully', 'post-export-import-with-media' ) ); ?>');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						showToast('error', response.data.message);
					}
				},
				error: function() {
					showToast('error', '<?php echo esc_js( __( 'Failed to reset email template', 'post-export-import-with-media' ) ); ?>');
				}
			});
		}).catch(function() {
			// User cancelled - do nothing
		});
	});

	// Send test email
	$('#peiwm-send-test-email').on('click', function() {
		var email = $('#test_email_address').val() || '<?php echo esc_js( get_option( 'admin_email' ) ); ?>';
		
		$(this).prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'post-export-import-with-media' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'peiwm_test_email',
				nonce: '<?php echo esc_js( wp_create_nonce( 'peiwm_secure_nonce' ) ); ?>',
				test_email: email
			},
			success: function(response) {
				if (response.success) {
					showToast('success', response.data.message);
				} else {
					showToast('error', response.data.message);
				}
			},
			error: function() {
				showToast('error', '<?php echo esc_js( __( 'Failed to send test email', 'post-export-import-with-media' ) ); ?>');
			},
			complete: function() {
				$('#peiwm-send-test-email').prop('disabled', false).text('<?php echo esc_js( __( 'Send Test Email', 'post-export-import-with-media' ) ); ?>');
			}
		});
	});

	/**
	 * Show reset confirmation modal
	 */
	function showResetModal() {
		return new Promise(function(resolve, reject) {
			const modal = $('#peiwm-reset-modal');
			
			// Show modal
			modal.show().addClass('peiwm-show');
			
			// Handle confirm button
			$('#peiwm-reset-confirm').off('click').on('click', function() {
				hideModal('#peiwm-reset-modal');
				resolve();
			});
			
			// Handle cancel button
			$('#peiwm-reset-cancel').off('click').on('click', function() {
				hideModal('#peiwm-reset-modal');
				reject();
			});
			
			// Handle close button
			modal.find('.peiwm-modal-close').off('click').on('click', function() {
				hideModal('#peiwm-reset-modal');
				reject();
			});
			
			// Handle overlay click
			modal.off('click').on('click', function(e) {
				if (e.target === this) {
					hideModal('#peiwm-reset-modal');
					reject();
				}
			});
			
			// Handle escape key
			$(document).off('keydown.peiwm-modal').on('keydown.peiwm-modal', function(e) {
				if (e.key === 'Escape') {
					hideModal('#peiwm-reset-modal');
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
</script>
