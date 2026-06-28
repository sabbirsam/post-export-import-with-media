<?php
/**
 * Heartbeat Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.3.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Heartbeat Handler Class - Keeps site responsive during imports
 */
class PEIWM_Heartbeat_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Heartbeat_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Heartbeat_Handler
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Optimize heartbeat during imports
		add_filter( 'heartbeat_settings', array( $this, 'optimize_heartbeat_settings' ) );
	}

	/**
	 * Optimize heartbeat settings
	 *
	 * @param array $settings Heartbeat settings
	 * @return array Modified settings
	 */
	public function optimize_heartbeat_settings( $settings ) {
		// Increase heartbeat interval during admin operations
		if ( is_admin() ) {
			$settings['interval'] = 60; // 60 seconds instead of default 15
		}
		return $settings;
	}


}
