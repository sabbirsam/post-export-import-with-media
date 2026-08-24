<?php
/**
 * Media Audit Page (Dashboard)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Media_Audit_Page {

	public function render() {
		global $title;
		if ( empty( $title ) ) {
			$title = __( 'Media Health & Audit', 'post-export-import-with-media' );
		}

		require_once __DIR__ . '/class-media-audit-controller.php';
		$controller = PEIWM_Media_Audit_Controller::get_instance();

		$active_scan = $controller->get_active_scan();
		$latest_scan = $controller->get_latest_scan();

		global $wpdb;
		$table_reports = $wpdb->prefix . 'peiwm_media_reports';

		$trashed_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'trash'" );

		?>
		<div class="wrap peiwm-admin peiwm-media-audit-wrap">
			<!-- Header & Breadcrumbs -->
			<div class="page-header" style="margin-bottom: 20px;">
				<div>
					<div class="crumb" style="font-size: 12.5px; color: #6c7385; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
						</svg>
						<?php echo esc_html__( 'Export/Import', 'post-export-import-with-media' ); ?> 
						<span style="margin: 0 2px;">/</span> 
						<?php echo esc_html__( 'Media Health & Audit', 'post-export-import-with-media' ); ?>
					</div>
					<h1 class="heading-admin" style="font-size: 22px; font-weight: 700; color: #111827; margin: 0; display: flex; align-items: center; gap: 10px;">
						<?php echo esc_html__( 'Media Health & Audit', 'post-export-import-with-media' ); ?>
					</h1>
					<p class="sub" style="font-size: 13.5px; color: #6c7385; margin-top: 6px; max-width: 600px;">
						<?php echo esc_html__( 'Scan and audit your media library to detect unused images, clean up disk space, and maintain peak performance.', 'post-export-import-with-media' ); ?>
					</p>
				</div>
				<div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
					<?php if ( $trashed_count > 0 ) : ?>
						<a href="<?php echo esc_url( admin_url( 'upload.php?mode=list&attachment-filter=trash' ) ); ?>" class="btn btn-secondary" style="background: #fff; color: #dc2626; border: 1px solid #fca5a5; padding: 8px 14px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
							🗑️ <?php echo sprintf( esc_html__( 'View Trash (%d)', 'post-export-import-with-media' ), $trashed_count ); ?>
						</a>
					<?php endif; ?>
					<button type="button" class="btn btn-primary" id="peiwm-btn-start-audit" style="background: #FF6A3D; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
						<?php echo $latest_scan ? esc_html__( 'Rescan Library', 'post-export-import-with-media' ) : esc_html__( 'Start Media Scan', 'post-export-import-with-media' ); ?>
					</button>
				</div>
			</div>

			<!-- Journey Banner -->
			<section class="journey" id="journey" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px;">
				<div class="journey-head" style="display: flex; justify-content: space-between; align-items: center;">
					<div>
						<h2 style="color: #fff; font-size: 18px; margin: 0 0 4px 0; font-weight: 600;">
							<?php echo esc_html__( 'Automated Library Audit & Cleanup', 'post-export-import-with-media' ); ?>
						</h2>
						<p id="journey-desc" style="color: #c7d2fe; font-size: 13px; margin: 0;">
							<?php echo esc_html__( 'Our safety engine cross-checks posts, pages, widgets, and theme settings before identifying removable media.', 'post-export-import-with-media' ); ?>
						</p>
					</div>
					<span class="journey-badge" style="background: rgba(255, 255, 255, 0.15); color: #e0e7ff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
						✨ <?php echo esc_html__( 'Media Audit Engine', 'post-export-import-with-media' ); ?>
					</span>
				</div>
			</section>

			<!-- Progress Box (Shown during scan) -->
			<div id="peiwm-audit-progress-card" class="peiwm-section" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; margin-bottom: 24px; <?php echo $active_scan ? '' : 'display: none;'; ?>">
				<h3 style="margin-top: 0; font-size: 16px; color: #111827;"><?php echo esc_html__( 'Scanning Media Library...', 'post-export-import-with-media' ); ?></h3>
				<div style="background: #e5e7eb; border-radius: 10px; height: 12px; overflow: hidden; margin-bottom: 12px;">
					<div id="peiwm-audit-bar" style="background: #7c3aed; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
				</div>
				<div style="display: flex; justify-content: space-between; font-size: 13px; color: #6b7280;">
					<span id="peiwm-audit-status-text"><?php echo esc_html__( 'Initializing scanners...', 'post-export-import-with-media' ); ?></span>
					<span id="peiwm-audit-percent-text">0%</span>
				</div>
				<div id="peiwm-audit-log-list" style="margin-top: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; max-height: 120px; overflow-y: auto; font-family: monospace; font-size: 12px; color: #4b5563;">
					<div>[System] Ready to begin media scan...</div>
				</div>
			</div>

			<?php if ( $latest_scan ) : ?>
				<!-- Health Hero Score Card & Metrics -->
				<div class="peiwm-audit-dashboard" style="display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 24px;">
					<!-- Health Score Card -->
					<div class="peiwm-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
						<span style="font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">
							<?php echo esc_html__( 'Library Health Score', 'post-export-import-with-media' ); ?>
						</span>
						<div style="font-size: 54px; font-weight: 800; color: <?php echo $latest_scan->health_score >= 80 ? '#10b981' : ( $latest_scan->health_score >= 50 ? '#f59e0b' : '#ef4444' ); ?>; margin: 10px 0;">
							<?php echo (int) $latest_scan->health_score; ?>%
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=peiwm-media-audit-review' ) ); ?>" class="btn btn-primary" style="background: #FF6A3D; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; display: inline-block;">
							<?php echo esc_html__( 'Review Unused Media', 'post-export-import-with-media' ); ?>
						</a>
					</div>

					<!-- 9 Metric Summary Cards Grid -->
					<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
						<div class="peiwm-metric-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px;">
							<div style="font-size: 12px; color: #6b7280; font-weight: 600;"><?php echo esc_html__( 'Total Scanned', 'post-export-import-with-media' ); ?></div>
							<div style="font-size: 26px; font-weight: 700; color: #111827; margin-top: 4px;"><?php echo number_format_i18n( $latest_scan->images_total ); ?></div>
						</div>
						<div class="peiwm-metric-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px;">
							<div style="font-size: 12px; color: #10b981; font-weight: 600;"><?php echo esc_html__( 'In Use', 'post-export-import-with-media' ); ?></div>
							<div style="font-size: 26px; font-weight: 700; color: #10b981; margin-top: 4px;"><?php echo number_format_i18n( $latest_scan->images_used ); ?></div>
						</div>
						<div class="peiwm-metric-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px;">
							<div style="font-size: 12px; color: #ef4444; font-weight: 600;"><?php echo esc_html__( 'Unused Files', 'post-export-import-with-media' ); ?></div>
							<div style="font-size: 26px; font-weight: 700; color: #ef4444; margin-top: 4px;"><?php echo number_format_i18n( $latest_scan->images_unused ); ?></div>
						</div>
						<div class="peiwm-metric-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px;">
							<div style="font-size: 12px; color: #6b7280; font-weight: 600;"><?php echo esc_html__( 'Blog Post Media', 'post-export-import-with-media' ); ?></div>
							<div style="font-size: 20px; font-weight: 700; color: #111827; margin-top: 4px;">Active</div>
						</div>
						<div class="peiwm-metric-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px;">
							<div style="font-size: 12px; color: #6b7280; font-weight: 600;"><?php echo esc_html__( 'Theme Assets', 'post-export-import-with-media' ); ?></div>
							<div style="font-size: 20px; font-weight: 700; color: #10b981; margin-top: 4px;">Protected</div>
						</div>
						<div class="peiwm-metric-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px;">
							<div style="font-size: 12px; color: #6b7280; font-weight: 600;"><?php echo esc_html__( 'Confidence Floor', 'post-export-import-with-media' ); ?></div>
							<div style="font-size: 20px; font-weight: 700; color: #111827; margin-top: 4px;">90%</div>
						</div>
					</div>
				</div>
			<?php else : ?>
				<!-- Empty State CTA -->
				<div class="peiwm-card" style="background: #fff; border: 1px dotted #cbd5e1; border-radius: 12px; padding: 48px; text-align: center; margin-bottom: 24px;">
					<div style="width: 56px; height: 56px; background: #f3e8ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; color: #7c3aed;">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
					</div>
					<h3 style="font-size: 18px; color: #111827; margin: 0 0 8px 0;"><?php echo esc_html__( 'No Media Audit Results Yet', 'post-export-import-with-media' ); ?></h3>
					<p style="color: #6b7280; font-size: 14px; max-width: 480px; margin: 0 auto 20px auto;">
						<?php echo esc_html__( 'Run your first media health audit to scan for unused files and inspect your site health score.', 'post-export-import-with-media' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
