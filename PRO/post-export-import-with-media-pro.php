<?php
/**
 * Plugin Name: Post Export Import with Media Pro
 *
 * @author            wpazleen
 * @copyright         2024- wpazleen
 * @license           GPL-2.0-or-later
 * @package           Post_Export_Import_With_Media_Pro
 *
 * @wordpress-plugin
 * Plugin Name: Post Export Import with Media Pro
 * Plugin URI: https://wpazleen.com/post-export-import-with-media/
 * Description: Post Export Import with Media Pro – Advanced WordPress content migration with scheduled backups, cloud storage, and premium features for comprehensive site management.
 * Version:           1.0.5
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            wpazleen
 * Author URI:        https://profiles.wordpress.org/wpazleen/
 * Text Domain:       post-export-import-with-media-pro
 * Domain Path: /languages/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include plugin.php for is_plugin_active function
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

// Check if Post Export Import with Media free version is active
if ( ! is_plugin_active( 'post-export-import-with-media/post-export-import-with-media.php' ) ) {
	deactivate_plugins( plugin_basename( __FILE__ ) );
    add_action( 'admin_notices', 'peiwm_pro_alert' );
    return;	
}

/**
 * Show admin notice if free version is not active
 *
 * @since 1.0.0
 */
function peiwm_pro_alert() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php esc_html_e( 'Post Export Import with Media Free version is not active. Please activate the free version to use Post Export Import with Media Pro.', 'post-export-import-with-media-pro' ); ?></p>
    </div>
    <?php
}

// Define plugin constants for better maintainability
if ( ! defined( 'PEIWM_PRO_VERSION' ) ) {
	define( 'PEIWM_PRO_VERSION', '1.0.5' );
}

if ( ! defined( 'PEIWM_PRO_PLUGIN_URL' ) ) {
	define( 'PEIWM_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PEIWM_PRO_PLUGIN_PATH' ) ) {
	define( 'PEIWM_PRO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'PEIWM_PRO_TEXT_DOMAIN' ) ) {
	define( 'PEIWM_PRO_TEXT_DOMAIN', 'post-export-import-with-media-pro' );
}

if ( ! defined( 'PEIWM_PRO_BASENAME' ) ) {
	define( 'PEIWM_PRO_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! function_exists( 'peiwm_pro_fs' ) ) {
    /**
     * Freemius SDK - Pro Takes Ownership pattern.
     * Pro handles ALL Freemius functionality (license, updates, surveys).
     *
     * @since 1.0.0
     * @return object Freemius SDK object.
     */
    function peiwm_pro_fs() {
        global $peiwm_pro_fs;

        if ( ! isset( $peiwm_pro_fs ) ) {

            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';

            $peiwm_pro_fs = fs_dynamic_init( array(
                'id'                  => '23084',
                'slug'                => 'post-export-import-with-media',
                'premium_slug'        => 'post-export-import-with-media-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_acaed015b901db29328b246e9e572',
                'is_premium'          => true,
                'has_premium_version' => true,
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'           => 'peiwm-secure',
                    'first-path'     => 'admin.php?page=peiwm-secure',
                    'account'        => true,
                    'contact'        => true,
                    'support'        => true,
                ),
                'parallel_activation' => array(
                    'enabled'                  => true,
                    'premium_version_basename' => plugin_basename( __FILE__ ),
                ),
            ) );
        }

        return $peiwm_pro_fs;
    }

    // Pro always initializes Freemius (takes ownership)
    peiwm_pro_fs();
    do_action( 'peiwm_pro_fs_loaded' );
}

/**
 * Helper function to ensure Pro takes full ownership of Freemius functionality.
 * Pro handles: license validation, updates, surveys, account management.
 *
 * @since 1.0.0
 * @return object|null Freemius SDK object.
 */
function peiwm_pro_get_freemius_instance() {
	return function_exists( 'peiwm_pro_fs' ) ? peiwm_pro_fs() : null;
}

/**
 * Ensure Free version steps aside when Pro is active.
 * This prevents double initialization and conflicts.
 *
 * @since 1.0.0
 */
function peiwm_pro_ensure_ownership() {
	// Pro is active - ensure Free doesn't initialize Freemius
	if ( function_exists( 'remove_action' ) ) {
		remove_action( 'peiwm_fs_loaded', '__return_true' );
	}
}

// Ensure ownership on Pro activation
add_action( 'plugins_loaded', 'peiwm_pro_ensure_ownership', 1 );

/**
 * Load the main plugin class
 */
require_once PEIWM_PRO_PLUGIN_PATH . 'includes/class-pro-main.php';

/**
 * Initialize the plugin using singleton pattern
 */
add_action( 'plugins_loaded', array( 'PEIWM_Pro_Main', 'get_instance' ), 20 );