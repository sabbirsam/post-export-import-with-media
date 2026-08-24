<?php
/**
 * Media Scanner Registry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Media_Scanner_Registry {

	private static $instance = null;
	private $scanners = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->register_default_scanners();
	}

	private function register_default_scanners() {
		require_once __DIR__ . '/scanners/class-scanner-post-content.php';
		require_once __DIR__ . '/scanners/class-scanner-page-content.php';
		require_once __DIR__ . '/scanners/class-scanner-postmeta.php';
		require_once __DIR__ . '/scanners/class-scanner-theme-options.php';
		require_once __DIR__ . '/scanners/class-scanner-widgets.php';
		require_once __DIR__ . '/scanners/class-scanner-menus.php';

		$this->register( new PEIWM_Scanner_Post_Content() );
		$this->register( new PEIWM_Scanner_Page_Content() );
		$this->register( new PEIWM_Scanner_Postmeta() );
		$this->register( new PEIWM_Scanner_Theme_Options() );
		$this->register( new PEIWM_Scanner_Widgets() );
		$this->register( new PEIWM_Scanner_Menus() );
	}

	public function register( PEIWM_Scanner_Interface $scanner ) {
		$this->scanners[ $scanner->get_id() ] = $scanner;
	}

	public function get_scanners() {
		return $this->scanners;
	}

	public function scan_batch( $attachment_ids ) {
		$results = array();

		foreach ( $this->scanners as $scanner_id => $scanner ) {
			$scanner_results = $scanner->scan( $attachment_ids );
			foreach ( $scanner_results as $att_id => $evidence_items ) {
				if ( ! isset( $results[ $att_id ] ) ) {
					$results[ $att_id ] = array();
				}
				$results[ $att_id ] = array_merge( $results[ $att_id ], $evidence_items );
			}
		}

		return $results;
	}
}
