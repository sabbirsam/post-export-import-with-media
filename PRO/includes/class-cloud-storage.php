<?php
/**
 * Cloud Storage Class
 *
 * @package Post_Export_Import_With_Media_Pro
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud Storage Class - Handles cloud storage integrations
 */
class PEIWM_Cloud_Storage {

	/**
	 * Instance
	 *
	 * @var PEIWM_Cloud_Storage|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Cloud_Storage
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
		// Feature coming soon
	}
}
