<?php
/**
 * Email Template Handler
 *
 * @package Post_Export_Import_With_Media
 * @since 1.5.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Template Class - Provides consistent, branded email templates
 */
class PEIWM_Email_Template {

	/**
	 * Get email template settings
	 *
	 * @return array Email template settings
	 */
	public static function get_settings() {
		$defaults = array(
			'brand_name'       => get_bloginfo( 'name' ),
			'primary_color'     => '#f97316',
			'secondary_color'   => '#14b8a6',
			'header_text_color' => '#ffffff',
			'body_text_color'   => '#1e293b',
			'show_branding'    => true,
			'custom_footer'    => '',
		);

		$settings = get_option( 'peiwm_email_template_settings', array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Replace tags in content
	 *
	 * @param string $content Content with tags
	 * @param array  $data Additional data for tag replacement
	 * @return string Content with tags replaced
	 */
	public static function replace_tags( $content, $data = array() ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$admin_email = get_option( 'admin_email' );
		$current_year = gmdate( 'Y' );
		$current_date = date_i18n( get_option( 'date_format' ) );
		$current_time = date_i18n( get_option( 'time_format' ) );

		// Default tags
		$tags = array(
			'{site_name}'    => $site_name,
			'{site_url}'     => $site_url,
			'{admin_email}'  => $admin_email,
			'{current_year}' => $current_year,
			'{current_date}' => $current_date,
			'{current_time}' => $current_time,
			'{user_name}'    => isset( $data['user_name'] ) ? $data['user_name'] : '',
			'{user_email}'   => isset( $data['user_email'] ) ? $data['user_email'] : '',
			'{user_login}'   => isset( $data['user_login'] ) ? $data['user_login'] : '',
			'{password}'     => isset( $data['password'] ) ? $data['password'] : '',
			'{login_url}'    => wp_login_url(),
			'{content}'      => isset( $data['content'] ) ? $data['content'] : '',
		);

		// Merge with additional data
		if ( ! empty( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( ! isset( $tags[ '{' . $key . '}' ] ) ) {
					$tags[ '{' . $key . '}' ] = $value;
				}
			}
		}

		return str_replace( array_keys( $tags ), array_values( $tags ), $content );
	}

	/**
	 * Get HTML email template
	 *
	 * @param string $subject Email subject
	 * @param string $heading Email heading
	 * @param string $content Email content (HTML)
	 * @param array  $args Optional arguments
	 * @return string HTML email
	 */
	public static function get_template( $subject, $heading, $content, $args = array() ) {
		$settings = self::get_settings();
		
		$brand_name = sanitize_text_field( $settings['brand_name'] );
		$primary_color = sanitize_hex_color( $settings['primary_color'] );
		$secondary_color = sanitize_hex_color( $settings['secondary_color'] );
		$header_text_color = sanitize_hex_color( $settings['header_text_color'] );
		$body_text_color = sanitize_hex_color( $settings['body_text_color'] );
		$show_branding = (bool) $settings['show_branding'];
		$custom_footer = wp_kses_post( $settings['custom_footer'] );
		
		$site_url  = esc_url( home_url() );
		$year      = gmdate( 'Y' );
		
		// Parse optional arguments
		$button_text = isset( $args['button_text'] ) ? sanitize_text_field( $args['button_text'] ) : '';
		$button_url  = isset( $args['button_url'] ) ? esc_url( $args['button_url'] ) : '';
		$footer_text = isset( $args['footer_text'] ) ? sanitize_text_field( $args['footer_text'] ) : '';

		// Replace tags in content, heading, and footer
		$tag_data = isset( $args['tag_data'] ) ? $args['tag_data'] : array();
		$heading = self::replace_tags( $heading, $tag_data );
		$content = self::replace_tags( $content, $tag_data );
		$footer_text = self::replace_tags( $footer_text, $tag_data );
		$custom_footer = self::replace_tags( $custom_footer, $tag_data );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $subject ); ?></title>
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			background-color: #f3f4f6;
			color: #1f2937;
			line-height: 1.6;
		}
		.email-wrapper {
			width: 100%;
			background-color: #f3f4f6;
			padding: 40px 20px;
		}
		.email-container {
			max-width: 600px;
			margin: 0 auto;
			background-color: #ffffff;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}
		.email-header {
			background: linear-gradient(135deg, <?php echo esc_attr( $primary_color ); ?> 0%, <?php echo esc_attr( $secondary_color ); ?> 100%);
			padding: 40px 30px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			color: <?php echo esc_attr( $header_text_color ); ?>;
			font-size: 28px;
			font-weight: 700;
			letter-spacing: -0.5px;
		}
		.email-header .site-name {
			display: inline-block;
			margin-top: 8px;
			padding: 6px 16px;
			background-color: rgba(255, 255, 255, 0.2);
			border-radius: 20px;
			color: <?php echo esc_attr( $header_text_color ); ?>;
			font-size: 14px;
			font-weight: 500;
		}
		.email-body {
			padding: 40px 30px;
		}
		.email-body h2 {
			margin: 0 0 20px 0;
			color: #111827;
			font-size: 22px;
			font-weight: 600;
		}
		.email-body p {
			margin: 0 0 16px 0;
			color: <?php echo esc_attr( $body_text_color ); ?>;
			font-size: 16px;
			line-height: 1.7;
		}
		.email-body ul {
			margin: 0 0 16px 0;
			padding-left: 20px;
			color: <?php echo esc_attr( $body_text_color ); ?>;
		}
		.email-body li {
			margin-bottom: 8px;
			font-size: 16px;
		}
		.info-box {
			background-color: #f9fafb;
			border-left: 4px solid <?php echo esc_attr( $primary_color ); ?>;
			padding: 20px;
			margin: 24px 0;
			border-radius: 6px;
		}
		.info-box p {
			margin: 0;
			color: #374151;
			font-size: 15px;
		}
		.info-box strong {
			color: #111827;
			font-weight: 600;
		}
		.button-container {
			text-align: center;
			margin: 32px 0;
		}
		.button {
			display: inline-block;
			padding: 14px 32px;
			background: linear-gradient(135deg, <?php echo esc_attr( $primary_color ); ?> 0%, <?php echo esc_attr( $secondary_color ); ?> 100%);
			color: <?php echo esc_attr( $header_text_color ); ?> !important;
			text-decoration: none;
			border-radius: 8px;
			font-weight: 600;
			font-size: 16px;
			box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
			transition: all 0.3s ease;
		}
		.button:hover {
			background: linear-gradient(135deg, <?php echo esc_attr( $secondary_color ); ?> 0%, <?php echo esc_attr( $primary_color ); ?> 100%);
			box-shadow: 0 6px 8px rgba(37, 99, 235, 0.4);
		}
		.email-footer {
			background-color: #f9fafb;
			padding: 30px;
			text-align: center;
			border-top: 1px solid #e5e7eb;
		}
		.email-footer p {
			margin: 0 0 8px 0;
			color: #6b7280;
			font-size: 14px;
		}
		.email-footer a {
			color: <?php echo esc_attr( $primary_color ); ?>;
			text-decoration: none;
			font-weight: 500;
		}
		.email-footer a:hover {
			text-decoration: underline;
		}
		.powered-by {
			margin-top: 20px;
			padding-top: 20px;
			border-top: 1px solid #e5e7eb;
			color: #9ca3af;
			font-size: 13px;
		}
		.powered-by a {
			color: <?php echo esc_attr( $primary_color ); ?>;
			text-decoration: none;
			font-weight: 600;
		}
		.powered-by a:hover {
			text-decoration: underline;
		}
		@media only screen and (max-width: 600px) {
			.email-wrapper {
				padding: 20px 10px;
			}
			.email-header {
				padding: 30px 20px;
			}
			.email-header h1 {
				font-size: 24px;
			}
			.email-body {
				padding: 30px 20px;
			}
			.email-body h2 {
				font-size: 20px;
			}
			.email-footer {
				padding: 20px;
			}
		}
	</style>
</head>
<body>
	<div class="email-wrapper">
		<div class="email-container">
			<!-- Header -->
			<div class="email-header">
				<h1><?php echo esc_html( $heading ); ?></h1>
				<span class="site-name"><?php echo esc_html( $brand_name ); ?></span>
			</div>

			<!-- Body -->
			<div class="email-body">
				<?php echo wp_kses_post( $content ); ?>

				<?php if ( ! empty( $button_text ) && ! empty( $button_url ) ) : ?>
				<div class="button-container">
					<a href="<?php echo esc_url( $button_url ); ?>" class="button"><?php echo esc_html( $button_text ); ?></a>
				</div>
				<?php endif; ?>
			</div>

			<!-- Footer -->
			<div class="email-footer">
				<?php if ( ! empty( $custom_footer ) ) : ?>
					<div style="margin-bottom: 16px;">
						<?php echo wp_kses_post( $custom_footer ); ?>
					</div>
				<?php endif; ?>
				
				<?php if ( ! empty( $footer_text ) ) : ?>
					<p><?php echo esc_html( $footer_text ); ?></p>
				<?php endif; ?>
				
				<p>
					<?php
					printf(
						/* translators: %s: site URL */
						esc_html__( 'This email was sent from %s', 'post-export-import-with-media' ),
						'<a href="' . esc_url( $site_url ) . '">' . esc_html( $brand_name ) . '</a>'
					);
					?>
				</p>
				
				<?php if ( $show_branding ) : ?>
				<div class="powered-by">
					<?php
					printf(
						/* translators: %s: plugin link */
						esc_html__( 'Powered by %s', 'post-export-import-with-media' ),
						'<a href="https://wpazleen.com/post-export-import-with-media/" target="_blank">Post Export Import with Media</a>'
					);
					?>
					<br>
					&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $brand_name ); ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send HTML email
	 *
	 * @param string|array $to Recipient email address(es)
	 * @param string       $subject Email subject
	 * @param string       $heading Email heading
	 * @param string       $content Email content (HTML)
	 * @param array        $args Optional arguments
	 * @return bool Whether the email was sent successfully
	 */
	public static function send( $to, $subject, $heading, $content, $args = array() ) {
		$html_content = self::get_template( $subject, $heading, $content, $args );
		
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		return wp_mail( $to, $subject, $html_content, $headers );
	}

	/**
	 * Get available tags for email templates
	 *
	 * @return array Available tags with descriptions
	 */
	public static function get_available_tags() {
		return array(
			'{site_name}'    => __( 'Your website name', 'post-export-import-with-media' ),
			'{site_url}'     => __( 'Your website URL', 'post-export-import-with-media' ),
			'{admin_email}'  => __( 'Admin email address', 'post-export-import-with-media' ),
			'{current_year}' => __( 'Current year', 'post-export-import-with-media' ),
			'{current_date}' => __( 'Current date', 'post-export-import-with-media' ),
			'{current_time}' => __( 'Current time', 'post-export-import-with-media' ),
			'{user_name}'    => __( 'User display name', 'post-export-import-with-media' ),
			'{user_email}'   => __( 'User email address', 'post-export-import-with-media' ),
			'{user_login}'   => __( 'User login username', 'post-export-import-with-media' ),
			'{password}'     => __( 'User password (for welcome emails)', 'post-export-import-with-media' ),
			'{login_url}'    => __( 'WordPress login URL', 'post-export-import-with-media' ),
			'{content}'      => __( 'Dynamic email content', 'post-export-import-with-media' ),
		);
	}
}
