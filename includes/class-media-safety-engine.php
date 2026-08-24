<?php
/**
 * Media Safety & Risk Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Media_Safety_Engine {

	/**
	 * Assess risk level and recommendation for an attachment
	 *
	 * @param int   $attachment_id
	 * @param array $evidence
	 * @return array Array containing risk_level, recommendation, and safety_flags
	 */
	public static function evaluate( $attachment_id, $evidence = array() ) {
		$safety_flags = array();
		$attachment   = get_post( $attachment_id );

		if ( ! $attachment ) {
			return array(
				'risk_level'     => 'High',
				'recommendation' => 'keep',
				'safety_flags'   => array( 'invalid_attachment' ),
			);
		}

		// 1. Theme logo & Site icon safety rule
		$site_logo_id = (int) get_option( 'site_logo' );
		$custom_logo  = (int) get_theme_mod( 'custom_logo' );
		$site_icon    = (int) get_option( 'site_icon' );

		if ( $attachment_id === $site_logo_id || $attachment_id === $custom_logo || $attachment_id === $site_icon ) {
			$safety_flags[] = 'active_site_brand_asset';
		}

		// 2. Recent upload safety rule (< 7 days old)
		$upload_date = strtotime( $attachment->post_date );
		if ( $upload_date && ( time() - $upload_date ) < ( 7 * DAY_IN_SECONDS ) ) {
			$safety_flags[] = 'recently_uploaded';
		}

		// 3. Featured Image safety rule
		if ( ! empty( $evidence ) ) {
			foreach ( $evidence as $item ) {
				if ( strpos( $item['source'], 'featured_image' ) !== false ) {
					$safety_flags[] = 'featured_image';
					break;
				}
			}
		}

		// Calculate Risk Level & Recommendation
		$has_evidence = ! empty( $evidence );
		$risk_level   = 'Low';

		if ( in_array( 'active_site_brand_asset', $safety_flags, true ) || in_array( 'featured_image', $safety_flags, true ) ) {
			$risk_level     = 'Critical';
			$recommendation = 'keep';
		} elseif ( in_array( 'recently_uploaded', $safety_flags, true ) ) {
			$risk_level     = 'Medium';
			$recommendation = 'keep';
		} elseif ( $has_evidence ) {
			$risk_level     = 'High';
			$recommendation = 'keep';
		} else {
			$risk_level     = 'Very Low';
			$recommendation = 'trash';
		}

		return array(
			'risk_level'     => $risk_level,
			'recommendation' => $recommendation,
			'safety_flags'   => $safety_flags,
		);
	}
}
