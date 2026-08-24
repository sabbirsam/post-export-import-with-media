<?php
/**
 * Theme Options Scanner
 * Weight: 98% (Critical site assets)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-scanner-interface.php';

class PEIWM_Scanner_Theme_Options implements PEIWM_Scanner_Interface {

	public function get_id() {
		return 'theme_options';
	}

	public function get_title() {
		return __( 'Theme Options & Site Assets', 'post-export-import-with-media' );
	}

	public function get_weight() {
		return 98;
	}

	public function scan( $attachment_ids ) {
		$results = array();

		if ( empty( $attachment_ids ) ) {
			return $results;
		}

		$site_logo_id = (int) get_option( 'site_logo' );
		$custom_logo  = (int) get_theme_mod( 'custom_logo' );
		$site_icon    = (int) get_option( 'site_icon' );

		foreach ( $attachment_ids as $att_id ) {
			$att_id = (int) $att_id;

			if ( $att_id === $site_logo_id || $att_id === $custom_logo ) {
				if ( ! isset( $results[ $att_id ] ) ) {
					$results[ $att_id ] = array();
				}
				$results[ $att_id ][] = array(
					'source' => 'theme_options',
					'detail' => __( 'Active Site Logo', 'post-export-import-with-media' ),
					'weight' => $this->get_weight(),
				);
			}

			if ( $att_id === $site_icon ) {
				if ( ! isset( $results[ $att_id ] ) ) {
					$results[ $att_id ] = array();
				}
				$results[ $att_id ][] = array(
					'source' => 'theme_options',
					'detail' => __( 'Active Site Favicon / Icon', 'post-export-import-with-media' ),
					'weight' => $this->get_weight(),
				);
			}
		}

		return $results;
	}
}
