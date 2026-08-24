<?php
/**
 * Page Content Scanner
 * Weight: 90%
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-scanner-interface.php';

class PEIWM_Scanner_Page_Content implements PEIWM_Scanner_Interface {

	public function get_id() {
		return 'page_content';
	}

	public function get_title() {
		return __( 'WordPress Pages', 'post-export-import-with-media' );
	}

	public function get_weight() {
		return 90;
	}

	public function scan( $attachment_ids ) {
		global $wpdb;
		$results = array();

		if ( empty( $attachment_ids ) ) {
			return $results;
		}

		// 1. Page Featured Image
		$ids_in = implode( ',', array_map( 'absint', $attachment_ids ) );
		$query  = "SELECT meta_value as attachment_id, post_id 
		          FROM {$wpdb->postmeta} pm
		          INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
		          WHERE pm.meta_key = '_thumbnail_id' 
		          AND pm.meta_value IN ({$ids_in}) 
		          AND p.post_type = 'page' 
		          AND p.post_status = 'publish'";

		$featured = $wpdb->get_results( $query );
		foreach ( $featured as $row ) {
			$att_id = (int) $row->attachment_id;
			if ( ! isset( $results[ $att_id ] ) ) {
				$results[ $att_id ] = array();
			}
			$results[ $att_id ][] = array(
				'source' => 'page_featured_image',
				'detail' => sprintf( __( 'Featured image in Page #%d', 'post-export-import-with-media' ), $row->post_id ),
				'weight' => $this->get_weight(),
			);
		}

		// 2. Page Content
		$pages = $wpdb->get_results(
			"SELECT ID, post_title, post_content 
			 FROM {$wpdb->posts} 
			 WHERE post_type = 'page' AND post_status = 'publish' AND post_content != ''"
		);

		foreach ( $attachment_ids as $att_id ) {
			$file_url = wp_get_attachment_url( $att_id );
			$filename = $file_url ? basename( $file_url ) : '';

			foreach ( $pages as $page ) {
				$matched = false;

				if ( strpos( $page->post_content, 'wp-image-' . $att_id ) !== false ) {
					$matched = true;
				} elseif ( ! empty( $filename ) && strpos( $page->post_content, $filename ) !== false ) {
					$matched = true;
				}

				if ( $matched ) {
					if ( ! isset( $results[ $att_id ] ) ) {
						$results[ $att_id ] = array();
					}
					$results[ $att_id ][] = array(
						'source' => 'page_content',
						'detail' => sprintf( __( 'Used in Page #%d (%s)', 'post-export-import-with-media' ), $page->ID, esc_html( $page->post_title ) ),
						'weight' => $this->get_weight(),
					);
				}
			}
		}

		return $results;
	}
}
