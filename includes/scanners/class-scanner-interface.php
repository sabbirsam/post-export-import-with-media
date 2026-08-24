<?php
/**
 * Interface PEIWM_Scanner_Interface
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface PEIWM_Scanner_Interface {
	/**
	 * Unique identifier for the scanner
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable title
	 * @return string
	 */
	public function get_title();

	/**
	 * Confidence weight (0-100)
	 * @return int
	 */
	public function get_weight();

	/**
	 * Execute scan on given attachment IDs
	 * @param array $attachment_ids Array of attachment IDs
	 * @return array Array of found usage indexed by attachment ID
	 */
	public function scan( $attachment_ids );
}
