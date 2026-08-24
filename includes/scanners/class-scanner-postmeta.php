<?php
/**
 * Post Meta Scanner
 * Weight: 85%
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-scanner-interface.php';

class PEIWM_Scanner_Postmeta implements PEIWM_Scanner_Interface {

	public function get_id() {
		return 'postmeta';
	}

	public function get_title() {
		return __( 'Post Meta & Custom Fields', 'post-export-import-with-media' );
	}

	public function get_weight() {
		return 85;
	}

	public function scan( $attachment_ids ) {
		global $wpdb;
		$results = array();

		if ( empty( $attachment_ids ) ) {
			return $results;
		}

		foreach ( $attachment_ids as $att_id ) {
			$file_url = wp_get_attachment_url( $att_id );
			$filename = $file_url ? basename( $file_url ) : '';

			// Direct ID match in postmeta (excluding _thumbnail_id and internal wp meta)
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_key, p.post_title 
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					 WHERE pm.meta_key NOT IN ('_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_image_alt')
					 AND (pm.meta_value = %s OR pm.meta_value LIKE %s)
					 AND p.post_status = 'publish'
					 LIMIT 20",
					(string) $att_id,
					'%' . $wpdb->esc_like( (string) $att_id ) . '%'
				)
			);

			foreach ( $rows as $row ) {
				if ( ! isset( $results[ $att_id ] ) ) {
					$results[ $att_id ] = array();
				}
				$results[ $att_id ][] = array(
					'source' => 'postmeta',
					'detail' => sprintf( __( 'Referenced in meta key "%s" on Post #%d (%s)', 'post-export-import-with-media' ), esc_html( $row->meta_key ), $row->post_id, esc_html( $row->post_title ) ),
					'weight' => $this->get_weight(),
				);
			}
		}

		return $results;
	}
}
