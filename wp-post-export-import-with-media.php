<?php
/*
Plugin Name: WP Post Export Import with Media
Description: A plugin to export and import WordPress posts and media files with real-time progress.
Version: 3.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PEIWM_VERSION', '3.0');
define('PEIWM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PEIWM_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Plugin Class
 */
class PEIWM {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_peiwm_export_posts', array($this, 'ajax_export_posts'));
        add_action('wp_ajax_peiwm_import_post', array($this, 'ajax_import_post'));
        add_action('wp_ajax_peiwm_delete_posts', array($this, 'ajax_delete_posts'));
        add_action('wp_ajax_peiwm_export_media', array($this, 'ajax_export_media'));
        add_action('wp_ajax_peiwm_import_media_start', array($this, 'ajax_import_media_start'));
        add_action('wp_ajax_peiwm_import_media_file', array($this, 'ajax_import_media_file'));
        add_action('wp_ajax_peiwm_delete_media', array($this, 'ajax_delete_media'));
        add_action('wp_ajax_peiwm_test_config', array($this, 'ajax_test_config'));
        add_action('wp_ajax_peiwm_get_media_stats', array($this, 'ajax_get_media_stats'));
        add_action('wp_ajax_peiwm_cleanup_media_batch', array($this, 'ajax_cleanup_media_batch'));
        
        // Legacy admin-post actions for direct downloads
        add_action('admin_post_peiwm_export_posts_download', array($this, 'download_export_posts'));
        add_action('admin_post_peiwm_export_media_download', array($this, 'download_export_media'));
        add_action('admin_post_peiwm_download_media', array($this, 'download_media_file'));
        
        // Cleanup hooks
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('wp_scheduled_delete', array($this, 'cleanup_temp_files'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Plugin initialization code here
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'WP Post Export Import',
            'Export/Import Posts',
            'manage_options',
            'peiwm',
            array($this, 'admin_page'),
            'dashicons-upload',
            30
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_peiwm') {
            return;
        }
        
        wp_enqueue_script(
            'peiwm-admin-js',
            PEIWM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PEIWM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'peiwm-admin-css',
            PEIWM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PEIWM_VERSION
        );
        
        // Localize script with AJAX URL and nonces
        wp_localize_script('peiwm-admin-js', 'peiwm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('peiwm_nonce'),
            'strings' => array(
                'select_file' => __('Please select a file to import.', 'peiwm'),
                'file_too_large' => __('File is too large. Please select a file smaller than 500MB.', 'peiwm'),
                'select_zip' => __('Please select a ZIP file.', 'peiwm'),
                'processing' => __('Processing...', 'peiwm'),
                'success' => __('Success!', 'peiwm'),
                'error' => __('Error:', 'peiwm'),
                'complete' => __('Complete!', 'peiwm')
            )
        ));
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        ?>
        <div class="wrap peiwm-admin">
            <h1><?php _e('Export/Import Posts & Media', 'peiwm'); ?></h1>
            
            <div class="peiwm-container">
                <!-- Posts Section -->
                <div class="peiwm-section">
                    <h2><?php _e('Posts Export/Import', 'peiwm'); ?></h2>
                    
                    <div class="peiwm-export-section">
                        <h3><?php _e('Export Posts', 'peiwm'); ?></h3>
                        <p><?php _e('Export all posts with their metadata and featured images.', 'peiwm'); ?></p>
                        <button type="button" id="peiwm-export-posts" class="button button-primary">
                            <?php _e('Export Posts', 'peiwm'); ?>
                        </button>
                    </div>
                    
                    <div class="peiwm-import-section">
                        <h3><?php _e('Import Posts', 'peiwm'); ?></h3>
                        <p><?php _e('Import posts from a previously exported JSON file.', 'peiwm'); ?></p>
                        <div class="button-container">
                            <input type="file" id="peiwm-posts-file" accept=".json" style="display: none;">
                            <button type="button" id="peiwm-select-posts-file" class="button button-secondary">
                                <?php _e('Select JSON File', 'peiwm'); ?>
                            </button>
                            <button type="button" id="peiwm-import-posts" class="button button-primary" style="display: none;">
                                <?php _e('Start Import', 'peiwm'); ?>
                            </button>
                        </div>
                        
                        <div id="peiwm-posts-progress" class="peiwm-progress" style="display: none;">
                            <h4><?php _e('Import Progress', 'peiwm'); ?></h4>
                            <div class="peiwm-progress-bar">
                                <div class="peiwm-progress-fill"></div>
                            </div>
                            <p class="peiwm-progress-text"><?php _e('Starting...', 'peiwm'); ?></p>
                            <div class="peiwm-log"></div>
                        </div>
                    </div>
                    
                    <div class="peiwm-delete-section">
                        <h3><?php _e('Delete Posts', 'peiwm'); ?></h3>
                        <p><?php _e('⚠️ <strong>Warning:</strong> This will permanently delete all posts. This action cannot be undone.', 'peiwm'); ?></p>
                        <button type="button" id="peiwm-delete-posts" class="button button-danger">
                            <?php _e('Delete All Posts', 'peiwm'); ?>
                        </button>
                        
                        <div id="peiwm-delete-posts-progress" class="peiwm-progress" style="display: none;">
                            <h4><?php _e('Delete Progress', 'peiwm'); ?></h4>
                            <div class="peiwm-progress-bar">
                                <div class="peiwm-progress-fill"></div>
                            </div>
                            <p class="peiwm-progress-text"><?php _e('Starting...', 'peiwm'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Media Section -->
                <div class="peiwm-section">
                    <h2><?php _e('Media Export/Import', 'peiwm'); ?></h2>
                    
                    <div class="peiwm-stats-section">
                        <h3><?php _e('Media Statistics', 'peiwm'); ?></h3>
                        <div id="peiwm-media-stats" class="peiwm-stats">
                            <p><?php _e('Loading media statistics...', 'peiwm'); ?></p>
                        </div>
                        <button type="button" id="peiwm-refresh-stats" class="button button-secondary">
                            <?php _e('Refresh Stats', 'peiwm'); ?>
                        </button>
                    </div>
                    
                    <div class="peiwm-export-section">
                        <h3><?php _e('Export Media', 'peiwm'); ?></h3>
                        <p><?php _e('Export all media files with their metadata as a ZIP file.', 'peiwm'); ?></p>
                        <button type="button" id="peiwm-export-media" class="button button-primary">
                            <?php _e('Export Media', 'peiwm'); ?>
                        </button>
                    </div>
                    
                    <div class="peiwm-import-section">
                        <h3><?php _e('Import Media', 'peiwm'); ?></h3>
                        <p><?php _e('Import media files from a previously exported ZIP file. Maximum file size: 500MB.', 'peiwm'); ?></p>
                        <div class="button-container">
                            <input type="file" id="peiwm-media-file" accept=".zip" style="display: none;">
                            <button type="button" id="peiwm-select-media-file" class="button button-secondary">
                                <?php _e('Select ZIP File', 'peiwm'); ?>
                            </button>
                            <button type="button" id="peiwm-import-media" class="button button-primary" style="display: none;">
                                <?php _e('Start Import', 'peiwm'); ?>
                            </button>
                        </div>
                        
                        <div id="peiwm-media-progress" class="peiwm-progress" style="display: none;">
                            <h4><?php _e('Import Progress', 'peiwm'); ?></h4>
                            <div class="peiwm-progress-bar">
                                <div class="peiwm-progress-fill"></div>
                            </div>
                            <p class="peiwm-progress-text"><?php _e('Starting...', 'peiwm'); ?></p>
                            <div class="peiwm-log"></div>
                        </div>
                    </div>
                    
                    <div class="peiwm-delete-section">
                        <h3><?php _e('Delete Media', 'peiwm'); ?></h3>
                        <p><?php _e('⚠️ <strong>Warning:</strong> This will permanently delete all media files from the library. This action cannot be undone.', 'peiwm'); ?></p>
                        <button type="button" id="peiwm-delete-media" class="button button-danger">
                            <?php _e('Delete All Media', 'peiwm'); ?>
                        </button>
                        
                        <div id="peiwm-delete-media-progress" class="peiwm-progress" style="display: none;">
                            <h4><?php _e('Delete Progress', 'peiwm'); ?></h4>
                            <div class="peiwm-progress-bar">
                                <div class="peiwm-progress-fill"></div>
                            </div>
                            <p class="peiwm-progress-text"><?php _e('Starting...', 'peiwm'); ?></p>
                            <div class="peiwm-log"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Test Section -->
                <div class="peiwm-section">
                    <h2><?php _e('System Test', 'peiwm'); ?></h2>
                    <p><?php _e('Test your server configuration to ensure compatibility.', 'peiwm'); ?></p>
                    <button type="button" id="peiwm-test-config" class="button button-secondary">
                        <?php _e('Test Configuration', 'peiwm'); ?>
                    </button>
                    <div id="peiwm-test-results" class="peiwm-test-results" style="display: none;"></div>
                </div>
            </div>
        </div>
        
        <!-- Modal Overlay -->
        <div id="peiwm-modal-overlay" class="peiwm-modal-overlay" style="display: none;">
            <div class="peiwm-modal">
                <div class="peiwm-modal-header">
                    <h3 id="peiwm-modal-title">Confirmation</h3>
                    <button type="button" class="peiwm-modal-close">&times;</button>
                </div>
                <div class="peiwm-modal-body">
                    <p id="peiwm-modal-message">Are you sure you want to proceed?</p>
                </div>
                <div class="peiwm-modal-footer">
                    <button type="button" id="peiwm-modal-cancel" class="button button-secondary">Cancel</button>
                    <button type="button" id="peiwm-modal-confirm" class="button button-danger">Confirm</button>
                </div>
            </div>
        </div>
        
        <!-- Success Modal -->
        <div id="peiwm-success-modal" class="peiwm-modal-overlay" style="display: none;">
            <div class="peiwm-modal peiwm-success-modal">
                <div class="peiwm-modal-header">
                    <h3>Success!</h3>
                    <button type="button" class="peiwm-modal-close">&times;</button>
                </div>
                <div class="peiwm-modal-body">
                    <div class="peiwm-success-icon">✓</div>
                    <p id="peiwm-success-message">Operation completed successfully!</p>
                </div>
                <div class="peiwm-modal-footer">
                    <button type="button" class="peiwm-modal-close button button-primary">OK</button>
                </div>
            </div>
        </div>
        
        <!-- Error Modal -->
        <div id="peiwm-error-modal" class="peiwm-modal-overlay" style="display: none;">
            <div class="peiwm-modal peiwm-error-modal">
                <div class="peiwm-modal-header">
                    <h3>Error</h3>
                    <button type="button" class="peiwm-modal-close">&times;</button>
                </div>
                <div class="peiwm-modal-body">
                    <div class="peiwm-error-icon">✗</div>
                    <p id="peiwm-error-message">An error occurred.</p>
                </div>
                <div class="peiwm-modal-footer">
                    <button type="button" class="peiwm-modal-close button button-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    }
    
    /**
     * AJAX: Export posts
     */
    public function ajax_export_posts() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $posts = get_posts(array(
                'post_type' => 'post',
                'numberposts' => -1,
                'post_status' => 'publish'
            ));
            
            $export_data = array();
            foreach ($posts as $post) {
                $post_data = array(
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_excerpt' => $post->post_excerpt,
                    'post_status' => $post->post_status,
                    'post_type' => $post->post_type,
                    'post_author' => $post->post_author,
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified,
                    'meta' => $this->get_post_meta($post->ID),
                    'featured_image' => $this->get_featured_image($post->ID)
                );
                
                $export_data[] = $post_data;
            }
            
            wp_send_json_success(array(
                'data' => $export_data,
                'count' => count($export_data)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Import post
     */
    public function ajax_import_post() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $post_data = json_decode(stripslashes($_POST['post_data']), true);
            if (!$post_data) {
                throw new Exception('Invalid post data');
            }
            
            // Check if post already exists
            $existing_posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'any',
                'title' => $post_data['post_title'],
                'posts_per_page' => 1
            ));
            
            if (!empty($existing_posts)) {
                wp_send_json_success(array('status' => 'skipped', 'reason' => 'Post already exists'));
            }
            
            // Insert post
            $post_id = wp_insert_post(array(
                'post_title' => $post_data['post_title'],
                'post_content' => $post_data['post_content'],
                'post_excerpt' => $post_data['post_excerpt'],
                'post_status' => $post_data['post_status'],
                'post_type' => $post_data['post_type'],
                'post_author' => $post_data['post_author'],
                'post_date' => $post_data['post_date']
            ));
            
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Import meta
            if (!empty($post_data['meta'])) {
                foreach ($post_data['meta'] as $meta_key => $meta_value) {
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }
            
            // Import featured image
            if (!empty($post_data['featured_image'])) {
                $this->import_featured_image($post_id, $post_data['featured_image']);
            }
            
            wp_send_json_success(array('status' => 'imported', 'post_id' => $post_id));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Delete posts
     */
    public function ajax_delete_posts() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $posts = get_posts(array(
                'post_type' => 'post',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            
            if (empty($posts)) {
                wp_send_json_success(array('message' => 'No posts found to delete'));
            }
            
            $deleted_count = 0;
            $failed_count = 0;
            
            foreach ($posts as $post) {
                $result = wp_delete_post($post->ID, true); // true = force delete
                if ($result) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                }
            }
            
            wp_send_json_success(array(
                'message' => "Deleted {$deleted_count} posts successfully" . ($failed_count > 0 ? ". Failed to delete {$failed_count} posts." : ""),
                'deleted_count' => $deleted_count,
                'failed_count' => $failed_count,
                'total_count' => count($posts)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Delete media
     */
    public function ajax_delete_media() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            
            if (empty($attachments)) {
                wp_send_json_success(array('message' => 'No media files found to delete'));
            }
            
            $deleted_count = 0;
            $failed_count = 0;
            
            foreach ($attachments as $attachment) {
                $result = wp_delete_attachment($attachment->ID, true); // true = force delete
                if ($result) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                }
            }
            
            wp_send_json_success(array(
                'message' => "Deleted {$deleted_count} media files successfully" . ($failed_count > 0 ? ". Failed to delete {$failed_count} files." : ""),
                'deleted_count' => $deleted_count,
                'failed_count' => $failed_count,
                'total_count' => count($attachments)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Export media (Refactored)
     */
    public function ajax_export_media() {
        $this->verify_nonce();
        $this->check_permissions();
        try {
            error_log('PEIWM: [Refactor] Starting media export');
            if (!class_exists('ZipArchive')) {
                error_log('PEIWM: [Refactor] ZipArchive class not available');
                throw new Exception('ZipArchive class is not available on this server');
            }
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => 'inherit'
            ));
            if (empty($attachments)) {
                error_log('PEIWM: [Refactor] No media files found to export');
                throw new Exception('No media files found to export');
            }
            $upload_dir = wp_upload_dir();
            $unique_id = uniqid('peiwm_export_', true);
            $temp_dir = $upload_dir['basedir'] . '/tmp_' . $unique_id;
            if (!wp_mkdir_p($temp_dir)) {
                error_log('PEIWM: [Refactor] Failed to create temporary directory: ' . $temp_dir);
                throw new Exception('Failed to create temporary directory');
            }
            $media_data = array();
            $processed_count = 0;
            $total_attachments = count($attachments);
            $total_size = 0;
            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);
                if (file_exists($file_path)) {
                    $file_name = basename($file_path);
                    $new_file_path = $temp_dir . '/' . $file_name;
                    $file_size = filesize($file_path);
                    if (copy($file_path, $new_file_path)) {
                        $media_data[] = array(
                            'id' => $attachment->ID,
                            'filename' => $file_name,
                            'title' => $attachment->post_title,
                            'description' => $attachment->post_content,
                            'caption' => $attachment->post_excerpt,
                            'alt_text' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                            'mime_type' => $attachment->post_mime_type,
                            'date' => $attachment->post_date,
                            'file_size' => $file_size,
                            'file_size_formatted' => $this->format_file_size($file_size),
                            'metadata' => wp_get_attachment_metadata($attachment->ID)
                        );
                        $processed_count++;
                        $total_size += $file_size;
                    } else {
                        error_log('PEIWM: [Refactor] Failed to copy file: ' . $file_path . ' to ' . $new_file_path);
                    }
                } else {
                    error_log('PEIWM: [Refactor] File does not exist: ' . $file_path);
                }
            }
            if (empty($media_data)) {
                $this->delete_directory($temp_dir);
                error_log('PEIWM: [Refactor] No media files could be processed. Checked ' . $total_attachments . ' attachments.');
                throw new Exception('No media files could be processed. Checked ' . $total_attachments . ' attachments.');
            }
            // Save metadata
            $metadata_file = $temp_dir . '/media_metadata.json';
            if (file_put_contents($metadata_file, json_encode($media_data)) === false) {
                $this->delete_directory($temp_dir);
                error_log('PEIWM: [Refactor] Failed to write metadata file: ' . $metadata_file);
                throw new Exception('Failed to write metadata file');
            }
            // Create ZIP in temp dir
            $zip_filename = 'media_export_' . date('Y-m-d_H-i-s') . '.zip';
            $zip_temp_file = $temp_dir . '/' . $zip_filename;
            $zip = new ZipArchive();
            $zip_result = $zip->open($zip_temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zip_result !== TRUE) {
                $this->delete_directory($temp_dir);
                error_log('PEIWM: [Refactor] Failed to create ZIP file: ' . $zip_temp_file . ' Error code: ' . $zip_result);
                throw new Exception('Failed to create ZIP file. Error code: ' . $zip_result);
            }
            $files = glob($temp_dir . '/*');
            $added_files = 0;
            foreach ($files as $file) {
                if ($zip->addFile($file, basename($file))) {
                    $added_files++;
                } else {
                    error_log('PEIWM: [Refactor] Failed to add file to ZIP: ' . $file);
                }
            }
            if (!$zip->close()) {
                $this->delete_directory($temp_dir);
                error_log('PEIWM: [Refactor] Failed to close ZIP file: ' . $zip_temp_file);
                throw new Exception('Failed to close ZIP file');
            }
            // Move ZIP to uploads root
            $final_zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
            if (!rename($zip_temp_file, $final_zip_path)) {
                $this->delete_directory($temp_dir);
                error_log('PEIWM: [Refactor] Failed to move ZIP to uploads root: ' . $final_zip_path);
                throw new Exception('Failed to move ZIP file to uploads directory');
            }
            clearstatcache();
            $file_size = filesize($final_zip_path);
            if ($file_size === 0) {
                unlink($final_zip_path);
                $this->delete_directory($temp_dir);
                error_log('PEIWM: [Refactor] ZIP file is empty: ' . $final_zip_path);
                throw new Exception('ZIP file is empty');
            }
            error_log('PEIWM: [Refactor] ZIP file created and moved successfully: ' . $final_zip_path . ' Size: ' . $file_size);
            $this->delete_directory($temp_dir);
            // Return success with file info for download
            wp_send_json_success(array(
                'filename' => $zip_filename,
                'count' => $processed_count,
                'size' => $file_size,
                'total_attachments' => $total_attachments,
                'added_files' => $added_files,
                'total_size' => $total_size,
                'total_size_formatted' => $this->format_file_size($total_size),
                'download_url' => preg_replace('/^http:/i', 'https:', admin_url('admin-post.php?action=peiwm_download_media&file=' . urlencode($zip_filename) . '&nonce=' . wp_create_nonce('peiwm_download_media')))
            ));
        } catch (Exception $e) {
            error_log('PEIWM: [Refactor] Exception in ajax_export_media: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Start media import
     */
    public function ajax_import_media_start() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            // Log the start of the process
            error_log('WPPEI: Starting media import process');
            
            if (!isset($_FILES['media_file'])) {
                error_log('WPPEI: No file uploaded');
                throw new Exception('No file uploaded');
            }

            if (!class_exists('ZipArchive')) {
                error_log('WPPEI: ZipArchive not available');
                throw new Exception('ZipArchive class is not available on this server');
            }

            $uploaded_file = $_FILES['media_file'];
            error_log('WPPEI: File uploaded - Name: ' . $uploaded_file['name'] . ', Size: ' . $uploaded_file['size'] . ', Error: ' . $uploaded_file['error']);
            
            // Check upload errors
            if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
                $error_msg = $this->get_upload_error_message($uploaded_file['error']);
                error_log('WPPEI: Upload error - ' . $error_msg);
                throw new Exception($error_msg);
            }
            
            // Validate file type
            if ($uploaded_file['type'] !== 'application/zip' && !preg_match('/\.zip$/i', $uploaded_file['name'])) {
                error_log('WPPEI: Invalid file type - ' . $uploaded_file['type']);
                throw new Exception('Please upload a ZIP file');
            }

            $upload_dir = wp_upload_dir();
            $batch_id = uniqid('media_import_');
            $temp_dir = $upload_dir['basedir'] . '/temp_' . $batch_id;
            
            error_log('WPPEI: Creating temp directory: ' . $temp_dir);
            
            if (!wp_is_writable($upload_dir['basedir'])) {
                error_log('WPPEI: Upload directory not writable: ' . $upload_dir['basedir']);
                throw new Exception('Upload directory is not writable');
            }
            
            if (!wp_mkdir_p($temp_dir)) {
                error_log('WPPEI: Failed to create temp directory');
                throw new Exception('Failed to create temporary directory');
            }
            
            // Move uploaded file
            $zip_file = $temp_dir . '/media.zip';
            error_log('WPPEI: Moving uploaded file to: ' . $zip_file);
            
            if (!move_uploaded_file($uploaded_file['tmp_name'], $zip_file)) {
                error_log('WPPEI: Failed to move uploaded file');
                $this->delete_directory($temp_dir);
                throw new Exception('Failed to move uploaded file');
            }
            
            error_log('WPPEI: File moved successfully, size: ' . filesize($zip_file));
            
            // Extract ZIP
            error_log('WPPEI: Opening ZIP file for extraction');
            $zip = new ZipArchive();
            $zip_result = $zip->open($zip_file);
            if ($zip_result !== TRUE) {
                error_log('WPPEI: Failed to open ZIP file, error code: ' . $zip_result);
                $this->delete_directory($temp_dir);
                throw new Exception('Failed to open ZIP file - file may be corrupted (Error: ' . $zip_result . ')');
            }
            
            error_log('WPPEI: Extracting ZIP contents');
            if (!$zip->extractTo($temp_dir)) {
                error_log('WPPEI: Failed to extract ZIP contents');
                $zip->close();
                $this->delete_directory($temp_dir);
                throw new Exception('Failed to extract ZIP contents');
            }
                
            $zip->close();
            unlink($zip_file);
            error_log('WPPEI: ZIP extracted successfully');
            
            // Read metadata
            $metadata_file = $temp_dir . '/media_metadata.json';
            if (!file_exists($metadata_file)) {
                error_log('WPPEI: Metadata file not found: ' . $metadata_file);
                $this->delete_directory($temp_dir);
                throw new Exception('Invalid media export file - metadata not found');
            }
            
            error_log('WPPEI: Reading metadata file');
            $metadata_content = file_get_contents($metadata_file);
            $media_data = json_decode($metadata_content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('WPPEI: JSON decode error: ' . json_last_error_msg());
                $this->delete_directory($temp_dir);
                throw new Exception('Invalid JSON in metadata file: ' . json_last_error_msg());
            }
            
            error_log('WPPEI: Metadata loaded successfully, ' . count($media_data) . ' files found');
            
            // Store batch info
            set_transient('peiwm_media_batch_' . $batch_id, array(
                'temp_dir' => $temp_dir,
                'media_data' => $media_data
            ), HOUR_IN_SECONDS);
            
            error_log('WPPEI: Batch stored successfully, batch_id: ' . $batch_id);
            
            wp_send_json_success(array(
                'batch_id' => $batch_id,
                'total_files' => count($media_data)
            ));
            
        } catch (Exception $e) {
            error_log('WPPEI: Exception in media import start: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Import individual media file
     */
    public function ajax_import_media_file() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $batch_id = sanitize_text_field($_POST['batch_id']);
            $file_index = intval($_POST['file_index']);
            
            $batch_data = get_transient('peiwm_media_batch_' . $batch_id);
            if (!$batch_data) {
                throw new Exception('Batch not found or expired');
            }
            
            $temp_dir = $batch_data['temp_dir'];
            $media_data = $batch_data['media_data'];
            
            if (!isset($media_data[$file_index])) {
                throw new Exception('File index not found');
            }
            
            $file_data = $media_data[$file_index];
            $source_file = $temp_dir . '/' . $file_data['filename'];
            
            if (!file_exists($source_file)) {
                throw new Exception('Source file not found: ' . $file_data['filename']);
            }
            
            // Check if file already exists by multiple methods
            if ($this->file_exists_in_media_library($file_data)) {
                wp_send_json_success(array(
                    'filename' => $file_data['filename'], 
                    'status' => 'skipped',
                    'reason' => 'File already exists in media library'
                ));
            }
            
            // Import file using WordPress media handling
            $upload_result = $this->import_media_file($source_file, $file_data);
            
            if (is_wp_error($upload_result)) {
                wp_send_json_success(array(
                    'filename' => $file_data['filename'],
                    'status' => 'failed',
                    'reason' => $upload_result->get_error_message()
                ));
            }
            
            wp_send_json_success(array(
                'filename' => $file_data['filename'],
                'status' => 'imported',
                'file_size' => $file_data['file_size'],
                'file_size_formatted' => $file_data['file_size_formatted'],
                'attachment_id' => $upload_result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Import a single media file with proper WordPress handling
     */
    private function import_media_file($source_file, $file_data) {
        // Get file info
        $file_info = wp_check_filetype($file_data['filename'], null);
        if (!$file_info['type']) {
            return new WP_Error('invalid_file_type', 'Invalid file type: ' . $file_data['filename']);
        }
        
        // Prepare upload directory
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'];
        $upload_url = $upload_dir['url'];
        
        // Create year/month directories if needed
        $time = strtotime($file_data['date']);
        $year = date('Y', $time);
        $month = date('m', $time);
        
        $upload_path .= '/' . $year . '/' . $month;
        $upload_url .= '/' . $year . '/' . $month;
        
        if (!wp_mkdir_p($upload_path)) {
            return new WP_Error('upload_error', 'Failed to create upload directory');
        }
        
        // Copy file to upload directory
        $destination_file = $upload_path . '/' . $file_data['filename'];
        
        if (!copy($source_file, $destination_file)) {
            return new WP_Error('copy_error', 'Failed to copy file: ' . $file_data['filename']);
        }
        
        // Set proper file permissions
        chmod($destination_file, 0644);
        
        // Create attachment post
        $attachment = array(
            'post_mime_type' => $file_info['type'],
            'post_title' => $file_data['title'] ?: pathinfo($file_data['filename'], PATHINFO_FILENAME),
            'post_content' => $file_data['description'] ?: '',
            'post_excerpt' => $file_data['caption'] ?: '',
            'post_status' => 'inherit',
            'post_date' => $file_data['date'],
            'post_date_gmt' => get_gmt_from_date($file_data['date']),
            'guid' => $upload_url . '/' . $file_data['filename']
        );
        
        // Insert attachment
        $attachment_id = wp_insert_post($attachment);
        
        if (is_wp_error($attachment_id)) {
            unlink($destination_file);
            return $attachment_id;
        }
        
        // Update attachment metadata
        update_post_meta($attachment_id, '_wp_attached_file', $year . '/' . $month . '/' . $file_data['filename']);
        
        // Set alt text if provided
        if (!empty($file_data['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($file_data['alt_text']));
        }
        
        // Generate attachment metadata (thumbnails, etc.)
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Only generate metadata for images
        if (strpos($file_info['type'], 'image/') === 0) {
            $attach_data = wp_generate_attachment_metadata($attachment_id, $destination_file);
            if (!is_wp_error($attach_data)) {
                wp_update_attachment_metadata($attachment_id, $attach_data);
            }
        }
        
        return $attachment_id;
    }
    
    /**
     * AJAX: Test configuration
     */
    public function ajax_test_config() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $config = array(
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'max_input_time' => ini_get('max_input_time'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'ziparchive_available' => class_exists('ZipArchive'),
                'upload_dir_writable' => wp_is_writable(wp_upload_dir()['basedir']),
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'current_memory_usage' => memory_get_usage(true),
                'peak_memory_usage' => memory_get_peak_usage(true)
            );
            
            wp_send_json_success($config);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Get media statistics
     */
    public function ajax_get_media_stats() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => 'inherit'
            ));
            
            $total_files = count($attachments);
            $total_size = 0;
            $file_types = array();
            $largest_file = array('size' => 0, 'name' => '');
            
            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);
                if (file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    $total_size += $file_size;
                    
                    // Track file types
                    $mime_type = $attachment->post_mime_type;
                    if (!isset($file_types[$mime_type])) {
                        $file_types[$mime_type] = 0;
                    }
                    $file_types[$mime_type]++;
                    
                    // Track largest file
                    if ($file_size > $largest_file['size']) {
                        $largest_file = array(
                            'size' => $file_size,
                            'name' => basename($file_path)
                        );
                    }
                }
            }
            
            // Sort file types by count
            arsort($file_types);
            
            wp_send_json_success(array(
                'total_files' => $total_files,
                'total_size' => $total_size,
                'total_size_formatted' => $this->format_file_size($total_size),
                'file_types' => $file_types,
                'largest_file' => array(
                    'name' => $largest_file['name'],
                    'size' => $largest_file['size'],
                    'size_formatted' => $this->format_file_size($largest_file['size'])
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Download media file (Refactored)
     */
    public function download_media_file() {
        // Start output buffering to prevent 'headers already sent' issues
        if (ob_get_level() === 0) {
            ob_start();
        }
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'peiwm_download_media')) {
            wp_die('Security check failed');
        }
        $filename = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';
        if (empty($filename) || !preg_match('/^media_export_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $filename)) {
            wp_die('Invalid file');
        }
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;
        if (!file_exists($file_path)) {
            wp_die('File not found');
        }
        // Set headers for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        // Clean (erase) the output buffer and turn off output buffering
        if (ob_get_length()) {
            ob_end_clean();
        }
        // Output file
        readfile($file_path);
        // Clean up file after download
        unlink($file_path);
        exit;
    }
    
    /**
     * Download exported posts
     */
    public function download_export_posts() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        try {
            $posts = get_posts(array(
                'post_type' => 'post',
                'numberposts' => -1,
                'post_status' => 'publish'
            ));
            
            $export_data = array();
            foreach ($posts as $post) {
                $post_data = array(
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_excerpt' => $post->post_excerpt,
                    'post_status' => $post->post_status,
                    'post_type' => $post->post_type,
                    'post_author' => $post->post_author,
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified,
                    'meta' => $this->get_post_meta($post->ID),
                    'featured_image' => $this->get_featured_image($post->ID)
                );
                
                $export_data[] = $post_data;
            }
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename=posts_export_' . date('Y-m-d_H-i-s') . '.json');
            echo json_encode($export_data, JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Download exported media
     */
    public function download_export_media() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        try {
            if (!class_exists('ZipArchive')) {
                wp_die('ZipArchive class is not available on this server');
            }
            
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => 'inherit'
            ));
            
            if (empty($attachments)) {
                wp_die('No media files found to export');
            }
            
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/temp_media_export_' . time();
            
            if (!wp_mkdir_p($temp_dir)) {
                wp_die('Failed to create temporary directory');
            }
            
            $media_data = array();
            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);
                if (file_exists($file_path)) {
                    $file_name = basename($file_path);
                    $new_file_path = $temp_dir . '/' . $file_name;
                    
                    if (copy($file_path, $new_file_path)) {
                        $media_data[] = array(
                            'id' => $attachment->ID,
                            'filename' => $file_name,
                            'title' => $attachment->post_title,
                            'description' => $attachment->post_content,
                            'caption' => $attachment->post_excerpt,
                            'alt_text' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                            'mime_type' => $attachment->post_mime_type,
                            'date' => $attachment->post_date,
                            'file_size' => filesize($file_path),
                            'file_size_formatted' => $this->format_file_size(filesize($file_path)),
                            'metadata' => wp_get_attachment_metadata($attachment->ID)
                        );
                    }
                }
            }
            
            file_put_contents($temp_dir . '/media_metadata.json', json_encode($media_data));
            
            $zip_file = $upload_dir['basedir'] . '/media_export_' . date('Y-m-d_H-i-s') . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                $this->delete_directory($temp_dir);
                wp_die('Failed to create ZIP file');
            }
            
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            
            $this->delete_directory($temp_dir);
            
            if (file_exists($zip_file)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename=' . basename($zip_file));
                header('Content-Length: ' . filesize($zip_file));
                readfile($zip_file);
                unlink($zip_file);
            } else {
                wp_die('Failed to create export file');
            }
            
            exit;
            
        } catch (Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!wp_verify_nonce($_POST['nonce'], 'peiwm_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
    }
    
    /**
     * Check user permissions
     */
    private function check_permissions() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
    }
    
    /**
     * Get post meta
     */
    private function get_post_meta($post_id) {
        $meta = get_post_meta($post_id);
        $clean_meta = array();
        
        foreach ($meta as $key => $values) {
            if (!in_array($key, array('_edit_lock', '_edit_last'))) {
                $clean_meta[$key] = $values[0];
            }
        }
        
        return $clean_meta;
    }
    
    /**
     * Get featured image
     */
    private function get_featured_image($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $attachment = get_post($thumbnail_id);
            return array(
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_url($thumbnail_id),
                'title' => $attachment->post_title,
                'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)
            );
        }
        return null;
    }
    
    /**
     * Import featured image
     */
    private function import_featured_image($post_id, $image_data) {
        if (!$image_data || empty($image_data['url'])) {
            return;
        }
        
        $image_id = media_sideload_image($image_data['url'], $post_id, $image_data['title'], 'id');
        if (!is_wp_error($image_id)) {
            set_post_thumbnail($post_id, $image_id);
            
            if (!empty($image_data['alt'])) {
                update_post_meta($image_id, '_wp_attachment_image_alt', $image_data['alt']);
            }
        }
    }
    
    /**
     * Check if file exists in media library
     */
    private function file_exists_in_media_library($file_data) {
        // Check by title
        if (!empty($file_data['title'])) {
            $existing_attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_status' => 'any',
                'title' => $file_data['title'],
                'posts_per_page' => 1
            ));
            if (!empty($existing_attachments)) return true;
        }
        
        // Check by filename in upload directory
        $upload_dir = wp_upload_dir();
        $time = strtotime($file_data['date']);
        $year = date('Y', $time);
        $month = date('m', $time);
        $check_file = $upload_dir['path'] . '/' . $year . '/' . $month . '/' . $file_data['filename'];
        
        if (file_exists($check_file)) {
            // Check if this file is already in the media library
            global $wpdb;
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
                $year . '/' . $month . '/' . $file_data['filename']
            ));
            if ($attachment_id) return true;
        }
        
        // Check by meta (more flexible search)
        global $wpdb;
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $file_data['filename']
        ));
        
        return $attachment_id ? true : false;
    }
    
    /**
     * Format file size in human readable format
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        );
        
        return isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Unknown upload error';
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->delete_directory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        
        return rmdir($dir);
    }
    
    /**
     * Cleanup temp files
     */
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dirs = glob($upload_dir['basedir'] . '/temp_*');
        
        foreach ($temp_dirs as $dir) {
            if (filemtime($dir) < (time() - DAY_IN_SECONDS)) {
                $this->delete_directory($dir);
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        $this->cleanup_temp_files();
    }
    
    /**
     * AJAX: Cleanup media import batch
     */
    public function ajax_cleanup_media_batch() {
        $this->verify_nonce();
        $this->check_permissions();
        
        try {
            $batch_id = sanitize_text_field($_POST['batch_id']);
            
            $batch_data = get_transient('peiwm_media_batch_' . $batch_id);
            if ($batch_data && !empty($batch_data['temp_dir'])) {
                $this->delete_directory($batch_data['temp_dir']);
            }
            
            delete_transient('peiwm_media_batch_' . $batch_id);
            
            wp_send_json_success(array('message' => 'Cleanup completed'));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}

// Initialize the plugin
new PEIWM();
?>