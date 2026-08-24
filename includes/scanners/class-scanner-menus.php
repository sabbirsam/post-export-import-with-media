<?php
/**
 * Navigation Menu Scanner
 * Weight: 70%
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-scanner-interface.php';

class PEIWM_Scanner_Menus implements PEIWM_Scanner_Interface {

	public function get_id() {
		return 'menus';
	}

	public function get_title() {
		return __( 'Navigation Menus', 'post-export-import-with-media' );
	}

	public function get_weight() {
		return 70;
	}

	public function scan( $attachment_ids ) {
		global $wpdb;
		$results = array();

		if ( empty( $attachment_ids ) ) {
			return $results;
		}

		$ids_in = implode( ',', array_map( 'absint', $attachment_ids ) );
		$query  = "SELECT meta_value as attachment_id, post_id 
		          FROM {$wpdb->postmeta} pm
		          INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
		          WHERE pm.meta_key IN ('_menu_item_object_id', '_thumbnail_id') 
		          AND pm.meta_value IN ({$ids_in}) 
		          AND p.post_type = 'nav_menu_item'";

		$menu_items = $wpdb->get_results( $query );
		foreach ( $menu_items as $row ) {
			$att_id = (int) $row->attachment_id;
			if ( ! isset( $results[ $att_id ] ) ) {
				$results[ $att_id ] = array();
			}
			$results[ $att_id ][] = array(
				'source' => 'menus',
				'detail' => sprintf( __( 'Navigation Menu Item #%d', 'post-export-import-with-media' ), $row->post_id ),
				'weight' => $this->get_weight(),
			);
		}

		return $results;
	}
}
