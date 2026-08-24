<?php
/**
 * Widget Scanner
 * Weight: 80%
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-scanner-interface.php';

class PEIWM_Scanner_Widgets implements PEIWM_Scanner_Interface {

	public function get_id() {
		return 'widgets';
	}

	public function get_title() {
		return __( 'Active Sidebar Widgets', 'post-export-import-with-media' );
	}

	public function get_weight() {
		return 80;
	}

	public function scan( $attachment_ids ) {
		$results        = array();
		$sidebars_widgets = get_option( 'sidebars_widgets', array() );

		if ( empty( $attachment_ids ) || empty( $sidebars_widgets ) ) {
			return $results;
		}

		$all_widgets_json = json_encode( $sidebars_widgets );

		foreach ( $attachment_ids as $att_id ) {
			$file_url = wp_get_attachment_url( $att_id );
			$filename = $file_url ? basename( $file_url ) : '';

			if ( strpos( $all_widgets_json, (string) $att_id ) !== false || ( ! empty( $filename ) && strpos( $all_widgets_json, $filename ) !== false ) ) {
				if ( ! isset( $results[ $att_id ] ) ) {
					$results[ $att_id ] = array();
				}
				$results[ $att_id ][] = array(
					'source' => 'widgets',
					'detail' => __( 'Referenced in active sidebar widget', 'post-export-import-with-media' ),
					'weight' => $this->get_weight(),
				);
			}
		}

		return $results;
	}
}
