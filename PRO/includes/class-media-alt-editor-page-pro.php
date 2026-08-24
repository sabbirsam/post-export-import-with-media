<?php
/**
 * Media Title & ALT Editor Page (PRO Version)
 *
 * @package Post_Export_Import_With_Media_Pro
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Media Title & ALT Editor Page Class (PRO)
 */
class PEIWM_Media_Alt_Editor_Page_Pro {

    /**
     * Render PRO page
     */
    public static function render() {
        ?>
        <div class="wrap peiwm-admin peiwm-media-alt-editor">
            <div class="page-header" style="display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 26px; flex-wrap: wrap; gap: 14px;">
                <div>
                    <div class="crumb" style="font-size: 12.5px; color: #6c7385; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
                        </svg>Export/Import <span style="margin:0 2px;">/</span> Media Editor
                    </div>
                    <h1 class="heading-admin">
                        <?php echo esc_html__( 'Media Title & ALT Editor', 'post-export-import-with-media' ); ?>
                        <a href="https://www.youtube.com/watch?v=ecoNG8aA_JY&list=PLWeDkVnCRHAbCh6CvoUi-NTNI1GgFiPqV" target="_blank" rel="noopener noreferrer" class="peiwm-help-icon" title="<?php echo esc_attr__( 'Watch video tutorials', 'post-export-import-with-media' ); ?>">
                            <span class="dashicons dashicons-video-alt3"></span>
                        </a>
                    </h1>
                    <p class="sub" style="font-size: 13.5px; color: #6c7385; margin-top: 6px; max-width: 560px;">
                        <?php echo esc_html__( 'Bulk edit media titles and ALT text with search, filters, real-time diff tracking, and CSV import/export.', 'post-export-import-with-media' ); ?>
                    </p>
                </div>
                <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" id="peiwm-export-csv" class="btn btn-ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <?php echo esc_html__( 'Export CSV', 'post-export-import-with-media' ); ?>
                    </button>
                    <button type="button" id="peiwm-import-csv-btn" class="btn btn-ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <?php echo esc_html__( 'Import CSV', 'post-export-import-with-media' ); ?>
                    </button>
                    <input type="file" id="peiwm-csv-file" accept=".csv" style="display: none;">

                    <button type="button" id="peiwm-discard-changes" class="btn btn-ghost" style="display: none;">
                        <?php echo esc_html__( 'Discard Changes', 'post-export-import-with-media' ); ?>
                    </button>
                    <button type="button" id="peiwm-save-changes" class="btn btn-primary" style="display: none;">
                        <?php echo esc_html__( 'Save All Changes', 'post-export-import-with-media' ); ?>
                    </button>
                </div>
            </div>

            <!-- JOURNEY SECTION -->
            <section class="journey" id="journey" style="margin-bottom: 24px;">
                <div class="journey-head" style="margin-bottom: 0;">
                    <div>
                        <h2><?php echo esc_html__( 'Bulk Media Title & ALT Optimization Journey', 'post-export-import-with-media' ); ?></h2>
                        <p><?php echo esc_html__( 'Filter images with missing ALT attributes, edit titles & ALT texts inline with live diff tracking, or perform bulk batch sync via CSV export and import.', 'post-export-import-with-media' ); ?></p>
                    </div>
                    <span class="journey-badge">✨ <?php echo esc_html__( 'Bulk Optimization', 'post-export-import-with-media' ); ?></span>
                </div>
            </section>

            <!-- Controls Section -->
            <div class="peiwm-editor-controls">
                <div class="peiwm-editor-filters">
                    <input 
                        type="text" 
                        id="peiwm-media-search" 
                        class="peiwm-search-input" 
                        placeholder="<?php echo esc_attr__( 'Search by filename or title...', 'post-export-import-with-media' ); ?>"
                    >
                    
                    <select id="peiwm-alt-filter" class="peiwm-filter-select">
                        <option value="all"><?php echo esc_html__( 'All Images', 'post-export-import-with-media' ); ?></option>
                        <option value="empty_alt"><?php echo esc_html__( 'Images with Empty ALT', 'post-export-import-with-media' ); ?></option>
                    </select>

                    <select id="peiwm-sort-by" class="peiwm-filter-select">
                        <option value="date_desc"><?php echo esc_html__( 'Upload Date (Newest)', 'post-export-import-with-media' ); ?></option>
                        <option value="date_asc"><?php echo esc_html__( 'Upload Date (Oldest)', 'post-export-import-with-media' ); ?></option>
                        <option value="modified_desc"><?php echo esc_html__( 'Modified Date (Newest)', 'post-export-import-with-media' ); ?></option>
                        <option value="modified_asc"><?php echo esc_html__( 'Modified Date (Oldest)', 'post-export-import-with-media' ); ?></option>
                        <option value="title_asc"><?php echo esc_html__( 'Title (A-Z)', 'post-export-import-with-media' ); ?></option>
                        <option value="title_desc"><?php echo esc_html__( 'Title (Z-A)', 'post-export-import-with-media' ); ?></option>
                        <option value="url_asc"><?php echo esc_html__( 'URL (A-Z)', 'post-export-import-with-media' ); ?></option>
                    </select>

                    <div class="peiwm-edit-mode-group">
                        <label>
                            <input type="radio" name="peiwm_edit_mode" value="both" checked>
                            <?php echo esc_html__( 'Title & ALT', 'post-export-import-with-media' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="peiwm_edit_mode" value="title">
                            <?php echo esc_html__( 'Title Only', 'post-export-import-with-media' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="peiwm_edit_mode" value="alt">
                            <?php echo esc_html__( 'ALT Only', 'post-export-import-with-media' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Media List -->
            <div class="peiwm-media-list">
                <table class="peiwm-media-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><?php echo esc_html__( 'Thumbnail', 'post-export-import-with-media' ); ?></th>
                            <th><?php echo esc_html__( 'Media Title', 'post-export-import-with-media' ); ?></th>
                            <th><?php echo esc_html__( 'ALT Text', 'post-export-import-with-media' ); ?></th>
                            <th style="width: 120px;"><?php echo esc_html__( 'Date', 'post-export-import-with-media' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="peiwm-media-tbody">
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: #6b7280;">
                                <?php echo esc_html__( 'Loading media files...', 'post-export-import-with-media' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="peiwm-editor-footer">
                <div class="peiwm-media-count" id="peiwm-media-count">
                    <?php echo esc_html__( 'Loading...', 'post-export-import-with-media' ); ?>
                </div>
                <div class="peiwm-editor-footer-actions" id="peiwm-footer-actions">
                    <button type="button" id="peiwm-load-more" class="btn btn-ghost" style="display: none;">
                        <?php echo esc_html__( 'Load Next 100', 'post-export-import-with-media' ); ?>
                    </button>
                </div>
            </div>

        </div>
        <?php
    }
}
