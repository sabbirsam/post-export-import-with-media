<?php
/**
 * Plugin Name: Post Export Import with Media
 *
 * @author            wpazleen
 * @copyright         2024- wpazleen
 * @license           GPL-2.0-or-later
 * @package           Post_Export_Import_With_Media
 *
 * @wordpress-plugin
 * Plugin Name: Post Export Import with Media
 * Plugin URI: https://wordpress.org/plugins/post-export-import-with-media/
 * Description: Post Export Import with Media: A secure plugin to export and import WordPress posts and media files with real-time progress.
 * Version:           1.13.3
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            wpazleen
 * Author URI:        https://profiles.wordpress.org/wpazleen/
 * Text Domain:       post-export-import-with-media
 * Domain Path: /languages/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants for better maintainability
if ( ! defined( 'PEIWM_VERSION' ) ) {
	define( 'PEIWM_VERSION', '1.13.3' );
}

if ( ! defined( 'PEIWM_PLUGIN_URL' ) ) {
	define( 'PEIWM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PEIWM_PLUGIN_PATH' ) ) {
	define( 'PEIWM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'PEIWM_TEXT_DOMAIN' ) ) {
	define( 'PEIWM_TEXT_DOMAIN', 'post-export-import-with-media' );
}


if ( ! function_exists( 'peiwm_fs' ) ) {
    /**
     * Freemius SDK with smart initialization - "Pro Takes Ownership, Free Steps Aside" pattern.
     *
     * @since 1.3.0
     * @return object|null Freemius SDK object.
     */
    function peiwm_fs() {
        global $peiwm_fs;

        if ( ! isset( $peiwm_fs ) ) {
            // This condtion dont block any feature or active, its just load freemius SDK if pro is not active, if pro is active then pro will handle all freemius functionality.
            if ( is_plugin_active( 'post-export-import-with-media-pro/post-export-import-with-media-pro.php' ) ) {
                return null; // Pro will handle all Freemius functionality
            }

            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

            $peiwm_fs = fs_dynamic_init( array(
                'id'                  => '23084',
                'slug'                => 'post-export-import-with-media',
                'premium_slug'        => 'post-export-import-with-media-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_acaed015b901db29328b246e9e572',
                'is_premium'          => false,
                'has_premium_version' => true,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                'menu'                => array(
                    'slug'           => 'peiwm-secure',
                    'first-path'     => 'admin.php?page=peiwm-secure',
                    'network'        => true,
                    'pricing'        => true,
                    'contact'        => true,
                    'support'        => true,
                ),
                'parallel_activation' => array(
                    'enabled'                  => true,
                    'premium_version_basename' => 'post-export-import-with-media-pro/post-export-import-with-media-pro.php',
                ),
            ) );
        }

        return $peiwm_fs;
    }
    
    // Init Freemius.
	// Only load Freemius if Pro is not active, if Pro is active then Pro will handle all Freemius SDK functionality 
    if ( ! is_plugin_active( 'post-export-import-with-media-pro/post-export-import-with-media-pro.php' ) ) {
        peiwm_fs();
        do_action( 'peiwm_fs_loaded' );
    }
}

/**
 * Helper function to check if Freemius is available.
 *
 * @since 1.1.0
 * @return object|null Freemius SDK object or null.
 */
function peiwm_get_freemius_instance() {
	// If Pro is active, get Freemius instance from Pro
	if ( is_plugin_active( 'post-export-import-with-media-pro/post-export-import-with-media-pro.php' ) ) {
		// Pro handles Freemius, so get it from Pro's function
		if ( function_exists( 'peiwm_pro_get_freemius_instance' ) ) {
			return peiwm_pro_get_freemius_instance();
		}
		// Fallback: try to get Pro's Freemius instance directly
		return function_exists( 'peiwm_fs' ) ? peiwm_fs() : null;
	}
	
	// Otherwise, Free handles it
	return function_exists( 'peiwm_fs' ) ? peiwm_fs() : null;
}

/**
 * Load the main plugin class
 */
require_once PEIWM_PLUGIN_PATH . 'includes/class-main.php';

/**
 * Initialize the plugin using singleton pattern
 */
add_action( 'plugins_loaded', array( 'PEIWM_Main', 'get_instance' ) );