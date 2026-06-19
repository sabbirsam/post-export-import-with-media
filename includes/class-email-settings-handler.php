<?php
/**
 * Email Settings Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.5.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Settings Handler Class - Manages email template customization
 */
class PEIWM_Email_Settings_Handler {

	/**
	 * Instance
	 *
	 * @var PEIWM_Email_Settings_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return PEIWM_Email_Settings_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_peiwm_reset_email_template', array( $this, 'ajax_reset_email_template' ) );
		add_action( 'wp_ajax_peiwm_test_email', array( $this, 'ajax_test_email' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'peiwm_email_template_settings',
			'peiwm_email_template_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input settings
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['brand_name']        = isset( $input['brand_name'] ) ? sanitize_text_field( $input['brand_name'] ) : get_bloginfo( 'name' );
		$sanitized['primary_color']     = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '#f97316';
		$sanitized['secondary_color']   = isset( $input['secondary_color'] ) ? sanitize_hex_color( $input['secondary_color'] ) : '#14b8a6';
		$sanitized['header_text_color'] = isset( $input['header_text_color'] ) ? sanitize_hex_color( $input['header_text_color'] ) : '#ffffff';
		$sanitized['body_text_color']   = isset( $input['body_text_color'] ) ? sanitize_hex_color( $input['body_text_color'] ) : '#1e293b';
		$sanitized['show_branding']     = isset( $input['show_branding'] ) && '1' === $input['show_branding'];
		$sanitized['custom_footer']     = isset( $input['custom_footer'] ) ? wp_kses_post( $input['custom_footer'] ) : '';

		return $sanitized;
	}

	/**
	 * AJAX: Reset email template to defaults
	 */
	public function ajax_reset_email_template() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		delete_option( 'peiwm_email_template_settings' );

		wp_send_json_success( array(
			'message' => esc_html__( 'Email template reset to defaults successfully', 'post-export-import-with-media' ),
		) );
	}

	/**
	 * AJAX: Send test email
	 */
	public function ajax_test_email() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_secure_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'post-export-import-with-media' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'post-export-import-with-media' ) ) );
		}

		$test_email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';

		if ( empty( $test_email ) || ! is_email( $test_email ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please enter a valid email address', 'post-export-import-with-media' ) ) );
		}

		$subject = sprintf(
			__( '[%s] Test Email - Email Template Preview', 'post-export-import-with-media' ),
			get_bloginfo( 'name' )
		);

		$heading = __( '📧 Test Email', 'post-export-import-with-media' );

		$content = '<h2>' . esc_html__( 'This is a test email!', 'post-export-import-with-media' ) . '</h2>';
		$content .= '<p>' . esc_html__( 'If you\'re seeing this email, your email template is working correctly.', 'post-export-import-with-media' ) . '</p>';
		
		$content .= '<div class="info-box">';
		$content .= '<p><strong>' . esc_html__( 'Template Tags Example:', 'post-export-import-with-media' ) . '</strong></p>';
		$content .= '<p>' . esc_html__( 'Site Name:', 'post-export-import-with-media' ) . ' {site_name}</p>';
		$content .= '<p>' . esc_html__( 'Current Date:', 'post-export-import-with-media' ) . ' {current_date}</p>';
		$content .= '<p>' . esc_html__( 'Current Year:', 'post-export-import-with-media' ) . ' {current_year}</p>';
		$content .= '</div>';

		$content .= '<p>' . esc_html__( 'You can customize the colors, branding, and footer text from the Email Template Settings.', 'post-export-import-with-media' ) . '</p>';

		$args = array(
			'button_text' => __( 'Visit Website', 'post-export-import-with-media' ),
			'button_url'  => home_url(),
			'footer_text' => __( 'This is a test email sent from your WordPress site.', 'post-export-import-with-media' ),
		);

		$sent = PEIWM_Email_Template::send( $test_email, $subject, $heading, $content, $args );

		if ( $sent ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %s: email address */
					esc_html__( 'Test email sent successfully to %s', 'post-export-import-with-media' ),
					$test_email
				),
			) );
		} else {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to send test email. Please check your email configuration.', 'post-export-import-with-media' ),
			) );
		}
	}
}
