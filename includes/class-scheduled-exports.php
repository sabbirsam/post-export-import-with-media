<?php
/**
 * Scheduled Exports Handler (Free)
 *
 * Registers the admin submenu page, renders the UI (locked for free, unlocked
 * for PRO), and owns the AJAX action hooks. Actual AJAX logic delegates to
 * PEIWM_Scheduled_Exports_Pro via WordPress filters when PRO is active.
 *
 * @package Post_Export_Import_With_Media
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Scheduled_Exports {

	/** @var PEIWM_Scheduled_Exports|null */
	private static $instance = null;

	/** @var array */
	private $default_settings = array(
		'enable_scheduled_exports'   => false,
		'schedule_frequency'         => 'daily',
		'enable_email_notifications' => false,
		'notification_emails'        => '',
		'enable_backup_rotation'     => false,
		'keep_backups_count'         => 5,
		'storage_mode'               => 'local',
		'export_types'               => array( 'posts', 'pages', 'media', 'settings' ),
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Always register the menu, because PRO relies on the Free version to render the UI
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 40 );
		add_action( 'wp_ajax_peiwm_get_scheduled_backups',     array( $this, 'ajax_get_scheduled_backups' ) );
		add_action( 'wp_ajax_peiwm_delete_scheduled_backup',   array( $this, 'ajax_delete_scheduled_backup' ) );
		add_action( 'wp_ajax_peiwm_download_scheduled_backup', array( $this, 'ajax_download_scheduled_backup' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_settings_page() {
		add_submenu_page(
			'peiwm-secure',
			__( 'Scheduled Exports', 'post-export-import-with-media' ),
			__( 'Scheduled Exports', 'post-export-import-with-media' ),
			'manage_options',
			'peiwm-scheduled-exports',
			array( $this, 'render_settings_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Settings helper
	// -------------------------------------------------------------------------

	public function get_settings() {
		$saved = get_option( 'peiwm_scheduled_exports', array() );
		return wp_parse_args( $saved, $this->default_settings );
	}

	// -------------------------------------------------------------------------
	// AJAX — registered here, logic delegated to PRO via filters
	// -------------------------------------------------------------------------

	public function ajax_get_scheduled_backups() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}
		if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'PRO version required', 'post-export-import-with-media' ) ) );
		}

		$data = apply_filters( 'peiwm_scheduled_exports_get_backups', array() );
		wp_send_json_success( $data );
	}

	public function ajax_delete_scheduled_backup() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}
		if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'PRO version required', 'post-export-import-with-media' ) ) );
		}

		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
		if ( empty( $filename ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Filename required', 'post-export-import-with-media' ) ) );
		}

		$result = apply_filters( 'peiwm_scheduled_exports_delete_backup', false, $filename );
		if ( $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Backup deleted', 'post-export-import-with-media' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete backup', 'post-export-import-with-media' ) ) );
		}
	}

	public function ajax_download_scheduled_backup() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'post-export-import-with-media' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'post-export-import-with-media' ) );
		}
		if ( ! PEIWM_Main::get_instance()->is_pro_active() ) {
			wp_die( esc_html__( 'PRO version required', 'post-export-import-with-media' ) );
		}

		$filename = isset( $_GET['filename'] ) ? sanitize_file_name( wp_unslash( $_GET['filename'] ) ) : '';
		if ( empty( $filename ) ) {
			wp_die( esc_html__( 'Filename required', 'post-export-import-with-media' ) );
		}

		$handled = apply_filters( 'peiwm_scheduled_exports_download_backup', false, $filename );
		if ( ! $handled ) {
			wp_die( esc_html__( 'File not found', 'post-export-import-with-media' ) );
		}
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'post-export-import-with-media' ) );
		}

		$is_pro      = PEIWM_Main::get_instance()->is_pro_active();
		$settings    = $this->get_settings();
		$admin_email = get_option( 'admin_email' );
		$next_run    = wp_next_scheduled( 'peiwm_scheduled_export_event' );
		$locked      = ! $is_pro ? ' peiwm-locked-section' : '';
		?>
		<div class="wrap peiwm-scheduled-exports-wrap">
			<h1><?php echo esc_html__( 'Scheduled Exports', 'post-export-import-with-media' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Automate your backups with scheduled exports. Set it and forget it!', 'post-export-import-with-media' ); ?></p>

			<?php settings_errors( 'peiwm_scheduled_exports' ); ?>

			<form method="post" action="options.php" id="peiwm-scheduled-exports-form">
				<?php settings_fields( 'peiwm_scheduled_exports' ); ?>

				<!-- Enable -->
				<div class="peiwm-settings-section<?php echo esc_attr( $locked ); ?>" style="position:relative;">
					<?php if ( ! $is_pro ) : ?>
						<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
							<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
						</button>
					<?php endif; ?>
					<h2><?php echo esc_html__( 'Enable Scheduled Exports', 'post-export-import-with-media' ); ?></h2>
					<label class="peiwm-toggle-switch">
						<input type="checkbox" id="enable_scheduled_exports" name="peiwm_scheduled_exports[enable_scheduled_exports]" value="1"
							<?php checked( $settings['enable_scheduled_exports'], true ); ?>
							<?php echo ! $is_pro ? 'disabled' : ''; ?> />
						<span class="peiwm-toggle-slider"></span>
					</label>
					<p class="description"><?php echo esc_html__( 'Enable automatic scheduled exports of your content.', 'post-export-import-with-media' ); ?></p>
					<?php if ( $is_pro && $next_run ) : ?>
						<p class="peiwm-next-run">
							<strong><?php echo esc_html__( 'Next scheduled run:', 'post-export-import-with-media' ); ?></strong>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ); ?>
						</p>
					<?php endif; ?>
				</div>

				<!-- Configuration -->
				<div id="peiwm-scheduled-config" class="<?php echo esc_attr( $locked ); ?>" style="position:relative;">
					<?php if ( ! $is_pro ) : ?>
						<button type="button" class="peiwm-pro-upgrade-overlay peiwm-open-premium-modal">
							<span class="peiwm-pro-upgrade-badge">🔒 <?php echo esc_html__( 'PRO', 'post-export-import-with-media' ); ?></span>
						</button>
					<?php endif; ?>

					<!-- Frequency -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Schedule Frequency', 'post-export-import-with-media' ); ?></h2>
						<div class="peiwm-frequency-options">
							<?php foreach ( array(
								'daily'   => array( __( 'Daily', 'post-export-import-with-media' ),   __( 'Export once every day', 'post-export-import-with-media' ) ),
								'weekly'  => array( __( 'Weekly', 'post-export-import-with-media' ),  __( 'Export once every week', 'post-export-import-with-media' ) ),
								'monthly' => array( __( 'Monthly', 'post-export-import-with-media' ), __( 'Export once every month', 'post-export-import-with-media' ) ),
							) as $val => $labels ) : ?>
							<label class="peiwm-radio-label">
								<input type="radio" name="peiwm_scheduled_exports[schedule_frequency]" value="<?php echo esc_attr( $val ); ?>"
									<?php checked( $settings['schedule_frequency'], $val ); ?>
									<?php echo ! $is_pro ? 'disabled' : ''; ?>>
								<span class="peiwm-radio-text">
									<strong><?php echo esc_html( $labels[0] ); ?></strong>
									<small><?php echo esc_html( $labels[1] ); ?></small>
								</span>
							</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Export Types -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'What to Export', 'post-export-import-with-media' ); ?></h2>
						<div class="peiwm-export-types">
							<?php foreach ( array(
								'posts'    => __( 'Posts', 'post-export-import-with-media' ),
								'pages'    => __( 'Pages', 'post-export-import-with-media' ),
								'media'    => __( 'Media', 'post-export-import-with-media' ),
								'settings' => __( 'Settings', 'post-export-import-with-media' ),
								'cpt'      => __( 'CPT &amp; ACF', 'post-export-import-with-media' ),
								'users'    => __( 'Users', 'post-export-import-with-media' ),
							) as $val => $label ) : ?>
							<label class="peiwm-checkbox-label">
								<input type="checkbox" name="peiwm_scheduled_exports[export_types][]" value="<?php echo esc_attr( $val ); ?>"
									<?php checked( in_array( $val, (array) $settings['export_types'], true ) ); ?>
									<?php echo ! $is_pro ? 'disabled' : ''; ?>>
								<span class="peiwm-checkbox-text"><?php echo esc_html( $label ); ?></span>
							</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Email Notifications -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Email Notifications', 'post-export-import-with-media' ); ?></h2>
						<label class="peiwm-toggle-switch">
							<input type="checkbox" id="enable_email_notifications" name="peiwm_scheduled_exports[enable_email_notifications]" value="1"
								<?php checked( $settings['enable_email_notifications'], true ); ?>
								<?php echo ! $is_pro ? 'disabled' : ''; ?> />
							<span class="peiwm-toggle-slider"></span>
						</label>
						<p class="description"><?php echo esc_html__( 'Send email notifications when exports complete.', 'post-export-import-with-media' ); ?></p>
						<div id="peiwm-email-config" style="<?php echo $settings['enable_email_notifications'] ? '' : 'display:none;'; ?> margin-top:1rem;">
							<label for="notification_emails"><strong><?php echo esc_html__( 'Email Addresses', 'post-export-import-with-media' ); ?></strong></label>
							<textarea id="notification_emails" name="peiwm_scheduled_exports[notification_emails]" rows="3" class="large-text"
								placeholder="<?php echo esc_attr( $admin_email ); ?>"
								<?php echo ! $is_pro ? 'readonly' : ''; ?>><?php echo esc_textarea( $settings['notification_emails'] ); ?></textarea>
							<p class="description">
								<?php echo esc_html__( 'Leave empty to use admin email:', 'post-export-import-with-media' ); ?>
								<strong><?php echo esc_html( $admin_email ); ?></strong>
							</p>
						</div>
					</div>

					<!-- Backup Rotation -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Backup Rotation', 'post-export-import-with-media' ); ?></h2>
						<label class="peiwm-toggle-switch">
							<input type="checkbox" id="enable_backup_rotation" name="peiwm_scheduled_exports[enable_backup_rotation]" value="1"
								<?php checked( $settings['enable_backup_rotation'], true ); ?>
								<?php echo ! $is_pro ? 'disabled' : ''; ?> />
							<span class="peiwm-toggle-slider"></span>
						</label>
						<p class="description"><?php echo esc_html__( 'Automatically delete old backups to save space.', 'post-export-import-with-media' ); ?></p>
						<div id="peiwm-rotation-config" style="<?php echo $settings['enable_backup_rotation'] ? '' : 'display:none;'; ?> margin-top:1rem;">
							<label for="keep_backups_count"><strong><?php echo esc_html__( 'Keep Last N Backups', 'post-export-import-with-media' ); ?></strong></label>
							<input type="number" id="keep_backups_count" name="peiwm_scheduled_exports[keep_backups_count]"
								value="<?php echo esc_attr( $settings['keep_backups_count'] ); ?>"
								min="1" max="100" class="small-text"
								<?php echo ! $is_pro ? 'readonly' : ''; ?> />
							<p class="description"><?php echo esc_html__( 'Number of recent backups to keep. (Range: 1-100)', 'post-export-import-with-media' ); ?></p>
						</div>
					</div>

					<!-- Storage Mode -->
					<div class="peiwm-settings-section">
						<h2><?php echo esc_html__( 'Storage Mode', 'post-export-import-with-media' ); ?></h2>
						<div class="peiwm-storage-modes">
							<label class="peiwm-storage-mode-card <?php echo 'local' === $settings['storage_mode'] ? 'active' : ''; ?>">
								<input type="radio" name="peiwm_scheduled_exports[storage_mode]" value="local"
									<?php checked( $settings['storage_mode'], 'local' ); ?>
									<?php echo ! $is_pro ? 'disabled' : ''; ?>>
								<div class="peiwm-storage-mode-content">
									<div class="peiwm-storage-mode-icon">💾</div>
									<h3><?php echo esc_html__( 'Local Storage', 'post-export-import-with-media' ); ?></h3>
									<p><?php echo esc_html__( 'Save backups to your server', 'post-export-import-with-media' ); ?></p>
									<span class="peiwm-storage-mode-badge"><?php echo esc_html__( 'Active', 'post-export-import-with-media' ); ?></span>
								</div>
							</label>
							<label class="peiwm-storage-mode-card disabled">
								<input type="radio" name="peiwm_scheduled_exports[storage_mode]" value="google_drive" disabled>
								<div class="peiwm-storage-mode-content">
									<div class="peiwm-storage-mode-icon">☁️</div>
									<h3><?php echo esc_html__( 'Google Drive', 'post-export-import-with-media' ); ?></h3>
									<p><?php echo esc_html__( 'Save backups to Google Drive', 'post-export-import-with-media' ); ?></p>
									<span class="peiwm-storage-mode-badge coming-soon"><?php echo esc_html__( 'Coming Soon', 'post-export-import-with-media' ); ?></span>
								</div>
							</label>
						</div>
					</div>

				</div><!-- /#peiwm-scheduled-config -->

				<?php if ( $is_pro ) : ?>
					<?php submit_button( __( 'Save Settings', 'post-export-import-with-media' ), 'primary', 'submit', true ); ?>
				<?php endif; ?>
			</form>

			<?php if ( $is_pro ) : ?>
			<div class="peiwm-settings-section peiwm-backups-section">
				<h2><?php echo esc_html__( 'Existing Backups', 'post-export-import-with-media' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Manage your scheduled export backups.', 'post-export-import-with-media' ); ?></p>
				<button type="button" id="peiwm-refresh-backups" class="button button-secondary">
					<?php echo esc_html__( 'Refresh List', 'post-export-import-with-media' ); ?>
				</button>
				<div id="peiwm-backups-list" class="peiwm-backups-list">
					<div class="peiwm-loading">
						<div class="peiwm-loading-spinner"></div>
						<p><?php echo esc_html__( 'Loading backups...', 'post-export-import-with-media' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Delete Confirmation Modal — must be in DOM for JS to find it -->
			<div id="peiwm-delete-modal" class="peiwm-modal-overlay" style="display:none;">
				<div class="peiwm-modal peiwm-danger-modal">
					<div class="peiwm-modal-header">
						<h3><?php echo esc_html__( 'Delete Backup', 'post-export-import-with-media' ); ?></h3>
						<button type="button" class="peiwm-modal-close">&times;</button>
					</div>
					<div class="peiwm-modal-body">
						<div class="peiwm-danger-icon">⚠️</div>
						<p><?php echo esc_html__( 'Are you sure you want to delete this backup?', 'post-export-import-with-media' ); ?></p>
						<p class="peiwm-modal-filename" style="font-weight:600;color:#742a2a;margin-top:1rem;"></p>
						<p style="color:#6b7280;margin-top:0.5rem;"><?php echo esc_html__( 'This action cannot be undone.', 'post-export-import-with-media' ); ?></p>
					</div>
					<div class="peiwm-modal-footer">
						<button type="button" id="peiwm-delete-cancel" class="button button-secondary">
							<?php echo esc_html__( 'Cancel', 'post-export-import-with-media' ); ?>
						</button>
						<button type="button" id="peiwm-delete-confirm" class="button button-danger">
							<?php echo esc_html__( 'Delete Backup', 'post-export-import-with-media' ); ?>
						</button>
					</div>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- /.wrap -->

		<?php if ( ! $is_pro ) : ?>
		<div id="peiwm-premium-modal" class="peiwm-modal-overlay" style="display:none;">
			<div class="peiwm-modal peiwm-premium-modal">
				<button type="button" class="peiwm-modal-close peiwm-premium-close">&times;</button>
				<div class="peiwm-premium-modal-body">
					<div class="peiwm-premium-badge-wrap">
						<span class="peiwm-premium-fire">🔥</span>
						<span class="peiwm-premium-offer-tag"><?php echo esc_html__( 'LIMITED TIME OFFER', 'post-export-import-with-media' ); ?></span>
					</div>
					<div class="peiwm-premium-icon">🚀</div>
					<h2 class="peiwm-premium-title"><?php echo esc_html__( 'Unlock PRO Features', 'post-export-import-with-media' ); ?></h2>
					<p class="peiwm-premium-subtitle"><?php echo esc_html__( 'You\'re one step away from powerful automation tools!', 'post-export-import-with-media' ); ?></p>
					<div class="peiwm-premium-features">
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Scheduled Automatic Exports', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Email Notifications', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Backup Rotation', 'post-export-import-with-media' ); ?></div>
						<div class="peiwm-premium-feature">✓ <?php echo esc_html__( 'Cloud Storage (Coming Soon)', 'post-export-import-with-media' ); ?></div>
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
		<?php endif; ?>
		<?php
	}
}
