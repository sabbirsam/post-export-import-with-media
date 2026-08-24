<?php
/**
 * Media Audit Controller
 * Manages database tables, scan sessions, log events, and reports.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Media_Audit_Controller {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Auto-initialize tables on construction
		$this->init_tables();
	}

	/**
	 * Create or update plugin database tables
	 */
	public function init_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_scans     = $wpdb->prefix . 'peiwm_media_scans';
		$table_reports   = $wpdb->prefix . 'peiwm_media_reports';
		$table_decisions = $wpdb->prefix . 'peiwm_media_decisions';
		$table_logs      = $wpdb->prefix . 'peiwm_scan_logs';

		$sql = "CREATE TABLE {$table_scans} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'running',
			fingerprint VARCHAR(64) NOT NULL,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			duration_ms INT UNSIGNED NULL,
			images_total INT UNSIGNED DEFAULT 0,
			images_used INT UNSIGNED DEFAULT 0,
			images_possibly_used INT UNSIGNED DEFAULT 0,
			images_unused INT UNSIGNED DEFAULT 0,
			confidence INT UNSIGNED DEFAULT 0,
			coverage INT UNSIGNED DEFAULT 0,
			health_score INT UNSIGNED DEFAULT 0,
			resume_state LONGTEXT NULL,
			scanner_states LONGTEXT NULL,
			broken_references LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY fingerprint (fingerprint),
			KEY completed_at (completed_at)
		) {$charset_collate};

		CREATE TABLE {$table_reports} (
			scan_id BIGINT UNSIGNED NOT NULL,
			attachment_id BIGINT UNSIGNED NOT NULL,
			filename VARCHAR(255) NOT NULL,
			url VARCHAR(512) NOT NULL,
			status VARCHAR(20) NOT NULL,
			confidence INT UNSIGNED DEFAULT 0,
			risk_level VARCHAR(20) DEFAULT 'Low',
			recommendation VARCHAR(20) DEFAULT 'keep',
			evidence_count INT UNSIGNED DEFAULT 0,
			evidence LONGTEXT NULL,
			filesize BIGINT UNSIGNED DEFAULT 0,
			user_decision VARCHAR(20) NULL,
			PRIMARY KEY (scan_id, attachment_id),
			KEY attachment_id (attachment_id),
			KEY status (status),
			KEY confidence (confidence),
			KEY risk_level (risk_level),
			KEY recommendation (recommendation)
		) {$charset_collate};

		CREATE TABLE {$table_decisions} (
			attachment_id BIGINT UNSIGNED NOT NULL,
			decision VARCHAR(20) NOT NULL,
			decided_at DATETIME NOT NULL,
			decided_by BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (attachment_id),
			KEY decided_at (decided_at)
		) {$charset_collate};

		CREATE TABLE {$table_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NOT NULL,
			level VARCHAR(10) NOT NULL,
			scanner VARCHAR(50) NOT NULL,
			message TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY scan_id (scan_id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get current active scan (running status)
	 */
	public function get_active_scan() {
		global $wpdb;
		$table = $wpdb->prefix . 'peiwm_media_scans';
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'running' ORDER BY id DESC LIMIT 1" );
		return $row ? $row : null;
	}

	/**
	 * Get latest completed scan
	 */
	public function get_latest_scan() {
		global $wpdb;
		$table = $wpdb->prefix . 'peiwm_media_scans';
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'completed' ORDER BY id DESC LIMIT 1" );
		return $row ? $row : null;
	}

	/**
	 * Start a new scan session
	 */
	public function create_scan() {
		global $wpdb;
		$table = $wpdb->prefix . 'peiwm_media_scans';

		// Mark any hanging running scan as failed
		$wpdb->update(
			$table,
			array( 'status' => 'failed' ),
			array( 'status' => 'running' )
		);

		$fingerprint = md5( time() . wp_rand() );
		$now         = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'status'       => 'running',
				'fingerprint'  => $fingerprint,
				'started_at'   => $now,
				'resume_state' => json_encode( array( 'offset' => 0, 'stage' => 'scanners' ) ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Log scan event
	 */
	public function log_event( $scan_id, $level, $scanner, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'peiwm_scan_logs';
		$wpdb->insert(
			$table,
			array(
				'scan_id'    => absint( $scan_id ),
				'level'      => sanitize_text_field( $level ),
				'scanner'    => sanitize_text_field( $scanner ),
				'message'    => sanitize_text_field( $message ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get logs for a scan session
	 */
	public function get_scan_logs( $scan_id, $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'peiwm_scan_logs';
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE scan_id = %d ORDER BY id DESC LIMIT %d", $scan_id, $limit );
		return $wpdb->get_results( $sql );
	}
}
