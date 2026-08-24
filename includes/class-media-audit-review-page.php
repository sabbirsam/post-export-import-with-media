<?php
/**
 * Review Unused Media Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PEIWM_Media_Audit_Review_Page {

	public function render() {
		global $title;
		if ( empty( $title ) ) {
			$title = __( 'Review Unused Media', 'post-export-import-with-media' );
		}
		$GLOBALS['title'] = $title;

		require_once __DIR__ . '/class-media-audit-controller.php';
		$controller  = PEIWM_Media_Audit_Controller::get_instance();
		$latest_scan = $controller->get_latest_scan();

		$is_pro = false;
		if ( class_exists( 'PEIWM_Main' ) ) {
			$is_pro = PEIWM_Main::get_instance()->is_pro_active();
		}

		global $wpdb;
		$table_reports = $wpdb->prefix . 'peiwm_media_reports';

		$unused_items = array();
		if ( $latest_scan ) {
			// Query unused media items excluding ones already marked safe, excluded, or trashed
			$sql          = $wpdb->prepare( "SELECT * FROM {$table_reports} WHERE scan_id = %d AND status = 'unused' AND (user_decision IS NULL OR user_decision = 'none') ORDER BY attachment_id DESC LIMIT 300", $latest_scan->id );
			$unused_items = $wpdb->get_results( $sql );
		}

		$trashed_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'trash'" );

		?>
		<div class="wrap peiwm-admin peiwm-media-review-wrap">
			<!-- Back Button & Breadcrumbs -->
			<div style="margin-bottom: 14px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=peiwm-media-audit' ) ); ?>" class="btn btn-secondary" style="background: #fff; border: 1px solid #d1d5db; color: #374151; padding: 6px 14px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					← <?php echo esc_html__( 'Back', 'post-export-import-with-media' ); ?>
				</a>
			</div>

			<!-- Header & Page Intro -->
			<div class="page-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 14px;">
				<div>
					<div class="crumb" style="font-size: 12.5px; color: #6c7385; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
						</svg>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=peiwm-media-audit' ) ); ?>" style="color: #6c7385; text-decoration: none;">
							<?php echo esc_html__( 'Media Health', 'post-export-import-with-media' ); ?>
						</a>
						<span style="margin: 0 2px;">/</span> 
						<?php echo esc_html__( 'Review Unused Media', 'post-export-import-with-media' ); ?>
					</div>
					<h1 class="heading-admin" style="font-size: 24px; font-weight: 700; color: #111827; margin: 0; display: flex; align-items: center; gap: 10px;">
						<?php echo esc_html__( 'Review Unused Media Files', 'post-export-import-with-media' ); ?>
						<span id="peiwm-unused-count-badge" class="peiwm-badge" style="padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
							<?php echo count( $unused_items ); ?> <?php echo esc_html__( 'Unused', 'post-export-import-with-media' ); ?>
						</span>
					</h1>
					<p class="sub" style="font-size: 13.5px; color: #6c7385; margin-top: 6px; max-width: 600px;">
						<?php echo esc_html__( 'Safely inspect files flagged as unused, mark items safe, exclude them, or move them to WordPress Trash.', 'post-export-import-with-media' ); ?>
					</p>
				</div>
				<?php if ( $trashed_count > 0 ) : ?>
					<div class="header-actions">
						<a href="<?php echo esc_url( admin_url( 'upload.php?mode=list&attachment-filter=trash' ) ); ?>" class="btn btn-secondary" style="background: #fff; color: #dc2626; border: 1px solid #fca5a5; padding: 8px 14px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
							🗑️ <?php echo sprintf( esc_html__( 'View Trash (%d)', 'post-export-import-with-media' ), $trashed_count ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>

			<!-- Filter, Search & Bulk Actions Bar -->
			<div class="peiwm-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 14px;">
				<!-- Controls Row: Search, Filter, Sort -->
				<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
					<!-- Search Input -->
					<div style="flex: 1; min-width: 240px;">
						<input type="text" id="peiwm-review-search" class="regular-text" placeholder="<?php echo esc_attr__( '🔍 Search by title or filename...', 'post-export-import-with-media' ); ?>" style="width: 100%;padding: 7px 12px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 13px;">
					</div>

					<!-- Risk Filter -->
					<div>
						<select id="peiwm-filter-risk" style="width: 100%; min-width: 120px; padding: 0px 10px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 13px; color: #374151;">
							<option value=""><?php echo esc_html__( 'All Risk Levels', 'post-export-import-with-media' ); ?></option>
							<option value="Critical"><?php echo esc_html__( 'Critical Risk', 'post-export-import-with-media' ); ?></option>
							<option value="High"><?php echo esc_html__( 'High Risk', 'post-export-import-with-media' ); ?></option>
							<option value="Medium"><?php echo esc_html__( 'Medium Risk', 'post-export-import-with-media' ); ?></option>
							<option value="Low"><?php echo esc_html__( 'Low Risk', 'post-export-import-with-media' ); ?></option>
							<option value="Very Low"><?php echo esc_html__( 'Very Low Risk', 'post-export-import-with-media' ); ?></option>
						</select>
					</div>

					<!-- Confidence Filter -->
					<div>
						<select id="peiwm-filter-confidence" style="padding: 0px 10px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 13px; color: #374151;">
							<option value="0"><?php echo esc_html__( 'All Confidence Levels', 'post-export-import-with-media' ); ?></option>
							<option value="90"><?php echo esc_html__( '90%+ High Confidence', 'post-export-import-with-media' ); ?></option>
							<option value="70"><?php echo esc_html__( '70%+ Medium Confidence', 'post-export-import-with-media' ); ?></option>
						</select>
					</div>

					<!-- Sort By -->
					<div>
						<select id="peiwm-sort-by" style="padding: 0px 10px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 13px; color: #374151;">
							<option value="id_desc"><?php echo esc_html__( 'Sort: Newest First', 'post-export-import-with-media' ); ?></option>
							<option value="id_asc"><?php echo esc_html__( 'Sort: Oldest First', 'post-export-import-with-media' ); ?></option>
							<option value="risk_desc"><?php echo esc_html__( 'Sort: Highest Risk', 'post-export-import-with-media' ); ?></option>
							<option value="confidence_desc"><?php echo esc_html__( 'Sort: Highest Confidence', 'post-export-import-with-media' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Bulk Action Toolbar -->
				<div style="display: flex; justify-content: space-between; align-items: center; pt-3; border-top: 1px solid #f3f4f6; padding-top: 10px;">
					<div style="display: flex; align-items: center; gap: 10px;">
						<select id="peiwm-bulk-action-select" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 13px;">
							<option value=""><?php echo esc_html__( 'Bulk Actions', 'post-export-import-with-media' ); ?></option>
							<option value="trash"><?php echo esc_html__( 'Move Selected to Trash', 'post-export-import-with-media' ); ?></option>
							<option value="safe"><?php echo esc_html__( 'Mark Selected as Safe', 'post-export-import-with-media' ); ?></option>
							<option value="exclude"><?php echo esc_html__( 'Exclude Selected Forever', 'post-export-import-with-media' ); ?></option>
						</select>
						<?php if ( $is_pro ) : ?>
							<button type="button" id="peiwm-btn-apply-bulk" class="btn btn-secondary" style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
								<?php echo esc_html__( 'Apply', 'post-export-import-with-media' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="btn btn-secondary peiwm-pro-only-btn" style="background: #f3f4f6; color: #9ca3af; border: 1px solid #d1d5db; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
								🔒 <?php echo esc_html__( 'Apply (PRO)', 'post-export-import-with-media' ); ?>
							</button>
						<?php endif; ?>
					</div>

					<div style="display: flex; gap: 10px; align-items: center;">
						<?php if ( $is_pro ) : ?>
							<button type="button" class="btn btn-primary peiwm-btn-bulk-trash-all" style="background: #ef4444; color: #fff; border: none; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
								<?php echo esc_html__( 'Trash All Unused', 'post-export-import-with-media' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="btn btn-ghost peiwm-pro-only-btn" style="border: 1px solid #d1d5db; background: #fff; color: #6b7280; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;">
								🔒 <?php echo esc_html__( 'Trash All Unused', 'post-export-import-with-media' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Unused Media Table -->
			<div class="peiwm-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
				<table class="wp-list-table widefat fixed striped" id="peiwm-review-table" style="border: none;">
					<thead>
						<tr>
							<th style="width: 40px; text-align: center;">
								<input type="checkbox" id="peiwm-select-all" <?php echo $is_pro ? '' : 'class="peiwm-pro-only-btn"'; ?> style="cursor: pointer;">
							</th>
							<th style="width: 90px; text-align: center;"><?php echo esc_html__( 'Thumbnail', 'post-export-import-with-media' ); ?></th>
							<th><?php echo esc_html__( 'Title / Media Details', 'post-export-import-with-media' ); ?></th>
							<th style="width: 110px;"><?php echo esc_html__( 'Risk Level', 'post-export-import-with-media' ); ?></th>
							<th style="width: 100px;"><?php echo esc_html__( 'Confidence', 'post-export-import-with-media' ); ?></th>
							<th style="width: 240px; text-align: right;"><?php echo esc_html__( 'Actions', 'post-export-import-with-media' ); ?></th>
						</tr>
					</thead>
					<tbody id="peiwm-review-tbody">
						<?php if ( empty( $unused_items ) ) : ?>
							<tr>
								<td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
									✨ <?php echo esc_html__( 'No unused media files found in your library!', 'post-export-import-with-media' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $unused_items as $item ) : ?>
								<?php
								$thumb_url = wp_get_attachment_image_url( $item->attachment_id, 'medium' );
								if ( ! $thumb_url ) {
									$thumb_url = wp_get_attachment_image_url( $item->attachment_id, 'thumbnail' );
								}
								$edit_url  = admin_url( 'post.php?post=' . $item->attachment_id . '&action=edit' );
								$att_title = get_the_title( $item->attachment_id );
								$att_title = $att_title ? $att_title : 'Attachment #' . $item->attachment_id;
								?>
								<tr id="peiwm-row-<?php echo (int) $item->attachment_id; ?>" 
									data-id="<?php echo (int) $item->attachment_id; ?>"
									data-title="<?php echo esc_attr( strtolower( $att_title . ' ' . $item->filename ) ); ?>"
									data-risk="<?php echo esc_attr( $item->risk_level ); ?>"
									data-confidence="<?php echo (int) $item->confidence; ?>">
									<td style="text-align: center; vertical-align: middle;">
										<input type="checkbox" class="peiwm-select-item <?php echo $is_pro ? '' : 'peiwm-pro-only-btn'; ?>" value="<?php echo (int) $item->attachment_id; ?>" style="cursor: pointer;">
									</td>
									<td style="text-align: center; vertical-align: middle; padding: 12px 8px;">
										<?php if ( $thumb_url ) : ?>
											<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" title="<?php echo esc_attr__( 'Edit media in WordPress editor', 'post-export-import-with-media' ); ?>">
												<img src="<?php echo esc_url( $thumb_url ); ?>" style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; display: block; margin: 0 auto;" alt="">
											</a>
										<?php else : ?>
											<div style="width: 70px; height: 70px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #9ca3af; margin: 0 auto;">No Image</div>
										<?php endif; ?>
									</td>
									<td style="vertical-align: middle; padding: 12px 8px;">
										<strong style="font-size: 14px; color: #111827; display: block; margin-bottom: 3px;">
											<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" style="color: #111827; text-decoration: none;" title="<?php echo esc_attr__( 'Open media edit page', 'post-export-import-with-media' ); ?>">
												<?php echo esc_html( $att_title ); ?>
											</a>
										</strong>
										<small style="color: #6b7280; font-size: 12px; display: block; margin-bottom: 4px;"><?php echo esc_html( $item->filename ); ?></small>
										<div style="font-size: 11.5px; color: #4f46e5;">
											<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" style="color: #4f46e5; text-decoration: underline; word-break: break-all;">
												<?php echo esc_html( $edit_url ); ?>
											</a>
										</div>
									</td>
									<td style="vertical-align: middle;">
										<?php
										$badge_bg   = '#f3f4f6';
										$badge_text = '#374151';
										if ( 'Critical' === $item->risk_level ) {
											$badge_bg   = '#fee2e2';
											$badge_text = '#991b1b';
										} elseif ( 'High' === $item->risk_level ) {
											$badge_bg   = '#ffedd5';
											$badge_text = '#9a3412';
										} elseif ( 'Medium' === $item->risk_level ) {
											$badge_bg   = '#fef3c7';
											$badge_text = '#92400e';
										} elseif ( 'Low' === $item->risk_level ) {
											$badge_bg   = '#e0f2fe';
											$badge_text = '#075985';
										} elseif ( 'Very Low' === $item->risk_level ) {
											$badge_bg   = '#dcfce7';
											$badge_text = '#166534';
										}
										?>
										<span style="background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_text ); ?>; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
											<?php echo esc_html( $item->risk_level ); ?>
										</span>
									</td>
									<td style="vertical-align: middle;">
										<span style="font-size: 13px; font-weight: 600; color: #4b5563;"><?php echo (int) $item->confidence; ?>%</span>
									</td>
									<td style="text-align: right; vertical-align: middle; padding: 12px 8px;">
										<div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
											<button type="button" class="btn btn-ghost peiwm-action-safe-btn" data-id="<?php echo (int) $item->attachment_id; ?>" title="<?php echo esc_attr__( 'Mark as Safe (Keep in library)', 'post-export-import-with-media' ); ?>" style="color: #059669; border: 1px solid #a7f3d0; background: #ecfdf5; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
												🛡️ <?php echo esc_html__( 'Mark Safe', 'post-export-import-with-media' ); ?>
											</button>
											<button type="button" class="btn btn-ghost peiwm-action-exclude-btn" data-id="<?php echo (int) $item->attachment_id; ?>" title="<?php echo esc_attr__( 'Exclude from future audits', 'post-export-import-with-media' ); ?>" style="color: #4b5563; border: 1px solid #d1d5db; background: #fff; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
												👁️‍🗨️ <?php echo esc_html__( 'Exclude', 'post-export-import-with-media' ); ?>
											</button>
											<?php if ( $is_pro ) : ?>
												<button type="button" class="btn btn-ghost peiwm-trash-single-btn" data-id="<?php echo (int) $item->attachment_id; ?>" title="<?php echo esc_attr__( 'Move item to Trash', 'post-export-import-with-media' ); ?>" style="color: #ef4444; border: 1px solid #fca5a5; background: #fff; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
													🗑️ <?php echo esc_html__( 'Trash', 'post-export-import-with-media' ); ?>
												</button>
											<?php else : ?>
												<button type="button" class="btn btn-ghost peiwm-pro-only-btn" title="<?php echo esc_attr__( 'Trashing unused media is a PRO feature', 'post-export-import-with-media' ); ?>" style="color: #6b7280; border: 1px solid #d1d5db; background: #f9fafb; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
													🔒 <?php echo esc_html__( 'Trash (PRO)', 'post-export-import-with-media' ); ?>
												</button>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
