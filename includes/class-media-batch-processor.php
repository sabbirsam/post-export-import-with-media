<?php
/**
 * Media Batch Processor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Media_Batch_Processor {

	public static function process_chunk( $scan_id, $batch_size = 50 ) {
		global $wpdb;
		$controller = PEIWM_Media_Audit_Controller::get_instance();
		$table_scans = $wpdb->prefix . 'peiwm_media_scans';
		$table_reports = $wpdb->prefix . 'peiwm_media_reports';

		$scan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_scans} WHERE id = %d", $scan_id ) );
		if ( ! $scan || 'running' !== $scan->status ) {
			return array(
				'completed' => true,
				'message'   => __( 'Scan not running', 'post-export-import-with-media' ),
			);
		}

		$state = json_decode( $scan->resume_state, true );
		$offset = isset( $state['offset'] ) ? (int) $state['offset'] : 0;

		// Get total attachment count
		$total_query = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image%'";
		$total_count = (int) $wpdb->get_var( $total_query );

		if ( 0 === $total_count ) {
			$wpdb->update(
				$table_scans,
				array(
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql' ),
					'health_score' => 100,
				),
				array( 'id' => $scan_id )
			);
			return array(
				'completed'    => true,
				'progress'     => 100,
				'health_score' => 100,
				'message'      => __( 'No media items found', 'post-export-import-with-media' ),
			);
		}

		// Get chunk of attachment IDs
		$att_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image%' ORDER BY ID ASC LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		) );

		if ( empty( $att_ids ) ) {
			// Scan Complete! Calculate final statistics & health score
			self::finalize_scan( $scan_id, $total_count );
			return array(
				'completed' => true,
				'progress'  => 100,
				'message'   => __( 'Audit complete!', 'post-export-import-with-media' ),
			);
		}

		// Run Scanners on this chunk
		require_once __DIR__ . '/class-media-scanner-registry.php';
		require_once __DIR__ . '/class-media-safety-engine.php';

		$registry = PEIWM_Media_Scanner_Registry::get_instance();
		$evidence = $registry->scan_batch( $att_ids );

		// Process each attachment in chunk
		$upload_dir = wp_get_upload_dir();

		foreach ( $att_ids as $att_id ) {
			$att = get_post( $att_id );
			if ( ! $att ) continue;

			$item_evidence = isset( $evidence[ $att_id ] ) ? $evidence[ $att_id ] : array();
			$eval          = PEIWM_Media_Safety_Engine::evaluate( $att_id, $item_evidence );

			$file_url = wp_get_attachment_url( $att_id );
			$file_url = $file_url ? $file_url : '';
			$filename = $file_url ? basename( $file_url ) : '';
			$file_path = get_attached_file( $att_id );
			$filesize  = ( $file_path && file_exists( $file_path ) ) ? filesize( $file_path ) : 0;

			$status = ! empty( $item_evidence ) ? 'used' : 'unused';
			$confidence = ! empty( $item_evidence ) ? 95 : 85;

			$wpdb->replace(
				$table_reports,
				array(
					'scan_id'        => $scan_id,
					'attachment_id'  => $att_id,
					'filename'       => $filename,
					'url'            => $file_url,
					'status'         => $status,
					'confidence'     => $confidence,
					'risk_level'     => $eval['risk_level'],
					'recommendation' => $eval['recommendation'],
					'evidence_count' => count( $item_evidence ),
					'evidence'       => json_encode( $item_evidence ),
					'filesize'       => $filesize,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d' )
			);
		}

		$new_offset = $offset + count( $att_ids );
		$progress   = min( 99, round( ( $new_offset / $total_count ) * 100 ) );

		$wpdb->update(
			$table_scans,
			array(
				'images_total' => $total_count,
				'resume_state' => json_encode( array( 'offset' => $new_offset ) ),
			),
			array( 'id' => $scan_id )
		);

		$controller->log_event( $scan_id, 'info', 'batch', sprintf( __( 'Processed %d of %d media items (%d%%)', 'post-export-import-with-media' ), $new_offset, $total_count, $progress ) );

		return array(
			'completed' => false,
			'progress'  => $progress,
			'processed' => $new_offset,
			'total'     => $total_count,
		);
	}

	private static function finalize_scan( $scan_id, $total_count ) {
		global $wpdb;
		$table_scans   = $wpdb->prefix . 'peiwm_media_scans';
		$table_reports = $wpdb->prefix . 'peiwm_media_reports';

		$used_count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_reports} WHERE scan_id = %d AND status = 'used'", $scan_id ) );
		$unused_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_reports} WHERE scan_id = %d AND status = 'unused'", $scan_id ) );

		$health_score = $total_count > 0 ? round( ( $used_count / $total_count ) * 100 ) : 100;

		$wpdb->update(
			$table_scans,
			array(
				'status'               => 'completed',
				'completed_at'         => current_time( 'mysql' ),
				'images_total'         => $total_count,
				'images_used'          => $used_count,
				'images_unused'        => $unused_count,
				'health_score'         => $health_score,
				'confidence'           => 90,
				'coverage'             => 100,
			),
			array( 'id' => $scan_id )
		);

		$controller = PEIWM_Media_Audit_Controller::get_instance();
		$controller->log_event( $scan_id, 'info', 'core', sprintf( __( 'Audit completed successfully. Health Score: %d%%', 'post-export-import-with-media' ), $health_score ) );
	}
}
