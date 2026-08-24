<?php
/**
 * Scheduled Exports Class
 *
 * @package Post_Export_Import_With_Media_Pro
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduled Exports Class - Handles automatic scheduled exports
 */
class PEIWM_Scheduled_Exports_Pro {

	/**
	 * Instance
	 *
	 * @var PEIWM_Scheduled_Exports_Pro|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Scheduled_Exports_Pro
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Hook in AJAX handlers — registered by the free class, handled here
		add_filter( 'peiwm_scheduled_exports_get_backups',    array( $this, 'handle_get_backups' ) );
		add_filter( 'peiwm_scheduled_exports_delete_backup',  array( $this, 'handle_delete_backup' ), 10, 2 );
		add_filter( 'peiwm_scheduled_exports_download_backup', array( $this, 'handle_download_backup' ), 10, 2 );

		// Settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Cron
		add_action( 'peiwm_scheduled_export_event', array( $this, 'run_scheduled_export' ) );
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'peiwm_scheduled_exports',
			'peiwm_scheduled_exports',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$settings = array();

		$settings['enable_scheduled_exports']   = ! empty( $input['enable_scheduled_exports'] );
		$settings['schedule_frequency']         = in_array( $input['schedule_frequency'] ?? 'daily', array( 'daily', 'weekly', 'monthly' ), true )
			? $input['schedule_frequency'] : 'daily';
		$settings['enable_email_notifications'] = ! empty( $input['enable_email_notifications'] );
		$settings['notification_emails']        = sanitize_textarea_field( $input['notification_emails'] ?? '' );
		$settings['enable_backup_rotation']     = ! empty( $input['enable_backup_rotation'] );
		$settings['keep_backups_count']         = max( 1, min( 100, absint( $input['keep_backups_count'] ?? 5 ) ) );
		$settings['storage_mode']               = 'local';
		$settings['export_types']               = array_intersect(
			(array) ( $input['export_types'] ?? array() ),
			array( 'posts', 'pages', 'media', 'settings', 'cpt', 'users' )
		);

		// Update cron schedule when settings change
		$this->update_cron_schedule( $settings );

		return $settings;
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['peiwm_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'post-export-import-with-media' ),
		);
		$schedules['peiwm_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once Monthly', 'post-export-import-with-media' ),
		);
		return $schedules;
	}

	/**
	 * Update cron schedule based on settings
	 *
	 * @param array $settings
	 */
	private function update_cron_schedule( $settings ) {
		$hook = 'peiwm_scheduled_export_event';

		if ( empty( $settings['enable_scheduled_exports'] ) ) {
			wp_clear_scheduled_hook( $hook );
			return;
		}

		$recurrence_map = array(
			'daily'   => 'daily',
			'weekly'  => 'peiwm_weekly',
			'monthly' => 'peiwm_monthly',
		);
		$recurrence = $recurrence_map[ $settings['schedule_frequency'] ] ?? 'daily';

		$existing = wp_get_scheduled_event( $hook );
		if ( $existing && $existing->schedule !== $recurrence ) {
			wp_clear_scheduled_hook( $hook );
			$existing = false;
		}

		if ( ! $existing ) {
			wp_schedule_event( time(), $recurrence, $hook );
		}
	}

	/**
	 * Run the scheduled export
	 */
	public function run_scheduled_export() {
		$free = PEIWM_Scheduled_Exports::get_instance();
		$settings = $free->get_settings();

		if ( empty( $settings['enable_scheduled_exports'] ) ) {
			return;
		}

		// Placeholder — full export logic to be implemented
		do_action( 'peiwm_pro_run_scheduled_export', $settings );
	}

	/**
	 * Get backup directory path
	 *
	 * @return string
	 */
	public function get_backup_directory() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'peiwm-scheduled-backups/';
	}

	/**
	 * Handle get backups (called via filter from free class AJAX handler)
	 *
	 * @return array
	 */
	public function handle_get_backups() {
		$dir = $this->get_backup_directory();

		if ( ! is_dir( $dir ) ) {
			return array(
				'backups'     => array(),
				'total_count' => 0,
				'backup_path' => $dir,
			);
		}

		$files  = glob( $dir . '*.zip' ) ?: array();
		$files  = array_merge( $files, glob( $dir . '*.json' ) ?: array() );
		usort( $files, fn( $a, $b ) => filemtime( $b ) - filemtime( $a ) );

		$backups = array();
		foreach ( $files as $file ) {
			$backups[] = array(
				'filename' => basename( $file ),
				'date'     => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $file ) ),
				'size'     => size_format( filesize( $file ) ),
			);
		}

		return array(
			'backups'     => $backups,
			'total_count' => count( $backups ),
			'backup_path' => $dir,
		);
	}

	/**
	 * Handle delete backup (called via filter from free class AJAX handler)
	 *
	 * @param bool   $result
	 * @param string $filename
	 * @return bool
	 */
	public function handle_delete_backup( $result, $filename ) {
		$dir  = $this->get_backup_directory();
		$file = $dir . sanitize_file_name( $filename );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		// Ensure file is inside the backup directory
		if ( strpos( realpath( $file ), realpath( $dir ) ) !== 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		return @unlink( $file );
	}

	/**
	 * Handle download backup (called via filter from free class AJAX handler)
	 *
	 * @param bool   $handled
	 * @param string $filename
	 * @return bool
	 */
	public function handle_download_backup( $handled, $filename ) {
		$dir  = $this->get_backup_directory();
		$file = $dir . sanitize_file_name( $filename );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		// Ensure file is inside the backup directory
		if ( strpos( realpath( $file ), realpath( $dir ) ) !== 0 ) {
			return false;
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		readfile( $file ); // phpcs:ignore
		exit;
	}
}
