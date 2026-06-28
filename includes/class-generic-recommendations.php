<?php
/**
 * Recommendations System
 * 
 * Enhanced class to fetch and display recommended plugins with caching and attractive design
 *
 * @package Recommendations
 * @version 2.0.0
 */

// If direct access than exit the file.
defined( 'ABSPATH' ) || exit;

/**
 * Generic Recommendations Class
 */
class Recommendations {

	/**
	 * Transient cache key
	 */
	const CACHE_KEY = 'peiwm_recommendations_data_v2';
	
	/**
	 * Cache duration (10 days)
	 */
	const CACHE_DURATION = 10 * DAY_IN_SECONDS;

	/**
	 * Plugin group data with enhanced descriptions and features
	 */
	private function get_group_data() {
		return [
			'woocommerce' => [
				'title' => 'Transform Your WooCommerce Store Experience',
				'description' => 'Overcome slow WooCommerce performance and unlock powerful bulk management tools.',
				'features' => [
					'⚡ Lightning-fast performance',
					'🔧 Powerful bulk management',
					'📊 Advanced analytics',
					'🎨 Beautiful product displays'
				],
				'highlight' => 'Supercharge your online store'
			],
			'security' => [
				'title' => 'Advanced Security & Activity Monitoring',
				'description' => 'Protect your website from threats and monitor all activities with comprehensive security solutions.',
				'features' => [
					'🔒 Real-time security monitoring',
					'📊 Detailed activity logs',
					'🚨 Instant threat alerts',
					'🔍 WooCommerce analytics tracking'
				],
				'highlight' => 'Essential for every WordPress site'
			],
			'forms' => [
				'title' => 'Professional Form Builder & Integrations',
				'description' => 'Create stunning forms with drag-and-drop simplicity and powerful integrations.',
				'features' => [
					'🎨 Drag & drop form builder',
					'📱 Mobile-responsive designs',
					'🔗 Multiple integrations (Telegram, WhatsApp, Mailchimp)',
					'📊 Quizzes and polls support'
				],
				'highlight' => 'Perfect for lead generation'
			],
			'chat' => [
				'title' => 'AI-Powered Customer Support & Engagement',
				'description' => 'Transform your customer support with intelligent AI chat assistance.',
				'features' => [
					'🧠 AI-powered responses',
					'💬 24/7 customer support',
					'🎯 Smart engagement tools',
					'📈 Boost conversion rates'
				],
				'highlight' => 'Increase customer satisfaction'
			],
			
		];
	}

	/**
	 * Enhanced plugin data with compelling descriptions
	 */
	private function get_plugins_data() {
		return [

			'shop-explorer' => [
				'name' => 'Shop Explorer – Speed Booster for WooCommerce with Powerful Bulk Tools and Management System',
				'description' => 'Transform your WooCommerce management! Handle thousands of products effortlessly with powerful bulk tools. Speed up your store and streamline operations like never before.',
				'group' => 'woocommerce',
				'key_benefits' => [
					'Bulk product management',
					'Performance optimization',
					'Advanced filtering',
					'Time-saving automation'
				]
			],
			'product-display' => [
				'name' => 'Product Display for WooCommerce',
				'description' => 'Showcase your products beautifully! Create stunning product displays with customizable layouts that convert visitors into customers. Perfect for any WooCommerce store.',
				'group' => 'woocommerce',
				'key_benefits' => [
					'Beautiful product layouts',
					'Customizable designs',
					'Mobile responsive',
					'Conversion optimized'
				]
			],
			'notifier-to-slack' => [
				'name' => 'Activity Guard – Complete Security, Activity Log & WooCommerce Analytics Tracker',
				'description' => 'Your website\'s security guardian! Monitor every action, track user activities, and get instant alerts about potential threats. Perfect for WooCommerce stores with built-in analytics.',
				'group' => 'security',
				'key_benefits' => [
					'Complete activity logging',
					'Real-time security alerts',
					'WooCommerce analytics',
					'User behavior tracking'
				]
			],
			'simple-form' => [
				'name' => 'Simple Forms — Drag and Drop Form Builder with Quizzes, Polls & Integrations',
				'description' => 'Build stunning forms in minutes! Create contact forms, surveys, quizzes, and polls with our intuitive drag-and-drop builder. Includes powerful integrations with Telegram, WhatsApp, and Mailchimp.',
				'group' => 'forms',
				'key_benefits' => [
					'Visual drag & drop builder',
					'Multiple form types',
					'Instant notifications',
					'Marketing integrations'
				]
			],
			'askany' => [
				'name' => 'AskAny – AI-Powered Chat Assistant',
				'description' => 'Revolutionize customer support with AI! Provide instant, intelligent responses to customer queries 24/7. Boost engagement and conversion rates with smart chat assistance.',
				'group' => 'chat',
				'key_benefits' => [
					'AI-powered responses',
					'24/7 availability',
					'Smart conversations',
					'Easy integration'
				]
			],
			
		];
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_fetch_recommendations', [ $this, 'fetch_all' ] );
		
		// Clear cache on plugin activation/deactivation for fresh data
		add_action( 'activated_plugin', [ $this, 'clear_cache' ] );
		add_action( 'deactivated_plugin', [ $this, 'clear_cache' ] );
	}

	/**
	 * Fetch products ajax endpoint with caching.
	 */
	public function fetch_all() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'peiwm_recommendations_nonce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed', 'post-export-import-with-media' ),
			) );
		}

		// Check if we should force refresh (for development/testing)
		$force_refresh = isset( $_POST['force_refresh'] ) && $_POST['force_refresh'] === '1';
		
		// Try to get cached data first (unless force refresh)
		$cached_data = false;
		if ( ! $force_refresh ) {
			$cached_data = get_transient( self::CACHE_KEY );
		}
		
		if ( false !== $cached_data ) {
			// Return cached data
			wp_send_json_success([
				'plugin_cards_html' => $cached_data['plugin_cards_html'],
				'header_data' => $cached_data['header_data'],
				'from_cache' => true
			]);
			wp_die();
		}

		// Clear cache if force refresh or generate fresh data
		if ( $force_refresh ) {
			delete_transient( self::CACHE_KEY );
		}

		// Generate fresh data
		ob_start();
		$this->get_all_products();
		$plugin_cards_html = ob_get_clean();

		// Prepare header data
		$group_data = $this->get_group_data();
		$header_data = [];
		
		foreach ( $group_data as $key => $data ) {
			$header_data[$key] = [
				'title' => $data['title'],
				'content' => $data['description'],
				'features' => $data['features'],
				'highlight' => $data['highlight']
			];
		}

		// Cache the data
		$cache_data = [
			'plugin_cards_html' => $plugin_cards_html,
			'header_data' => $header_data
		];
		set_transient( self::CACHE_KEY, $cache_data, self::CACHE_DURATION );

		// Return fresh data
		wp_send_json_success([
			'plugin_cards_html' => $plugin_cards_html,
			'header_data' => $header_data,
			'from_cache' => false
		]);
		wp_die();
	}

	/**
	 * Get all products without WooCommerce dependency checks.
	 */
	public function get_all_products() {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		remove_all_filters( 'plugins_api' );

		$plugins_allowedtags = [
			'a' => [ 'href' => [], 'title' => [], 'target' => [] ],
			'abbr' => [ 'title' => [] ],
			'acronym' => [ 'title' => [] ],
			'code' => [], 'pre' => [], 'em' => [], 'strong' => [],
			'ul' => [], 'ol' => [], 'li' => [], 'p' => [], 'br' => [],
		];

		$recommended_plugins = [];
		$plugins_data = $this->get_plugins_data();

		// Fetch all plugins
		foreach ( $plugins_data as $slug => $info ) {
			$data = plugins_api( 'plugin_information', [
				'slug' => $slug,
				'fields' => [ 'short_description' => true, 'icons' => true, 'reviews' => false ]
			] );

			if ( $data && ! is_wp_error( $data ) ) {
				$recommended_plugins[$slug] = $data;
				$recommended_plugins[$slug]->name = sanitize_text_field( $info['name'] );
				$recommended_plugins[$slug]->short_description = esc_html( $info['description'] );
				$recommended_plugins[$slug]->group = $info['group'];
				$recommended_plugins[$slug]->key_benefits = $info['key_benefits'];
			}
		}

		// Get group data
		$group_data = $this->get_group_data();
		$current_group = '';

		foreach ( (array) $recommended_plugins as $plugin ) {
			if ( is_object( $plugin ) ) {
				$plugin = (array) $plugin;
			}

			// Display the group heading
			if ( isset( $plugin['group'] ) && $plugin['group'] !== $current_group ) {
				$group_name = $plugin['group'];

				// Close previous group
				if ( ! empty( $current_group ) ) {
					echo '</div></div></div>';
				}

				// Get group info
				$group_info = isset( $group_data[$group_name] ) ? $group_data[$group_name] : [
					'title' => ucfirst( $group_name ),
					'description' => 'Recommended plugins for ' . $group_name,
					'features' => [],
					'highlight' => ''
				];
				
				// Start new group with enhanced design
				echo '<div class="recommendation-section plugin-group-section">';
				echo '<div class="section-header">';
				echo '<h2 class="group-title">' . esc_html( $group_info['title'] ) . '</h2>';
				echo '<p class="group-description">' . esc_html( $group_info['description'] ) . '</p>';
				
				if ( ! empty( $group_info['features'] ) ) {
					echo '<div class="group-features">';
					echo '<ul class="features-list">';
					foreach ( $group_info['features'] as $feature ) {
						echo '<li>' . esc_html( $feature ) . '</li>';
					}
					echo '</ul>';
					if ( ! empty( $group_info['highlight'] ) ) {
						echo '<span class="group-highlight">' . esc_html( $group_info['highlight'] ) . '</span>';
					}
					echo '</div>';
				}
				
				echo '</div>';
				echo '<div class="plugin-group">';
				echo '<div class="plugin-items">';

				$current_group = $plugin['group'];
			}

			// Plugin card HTML
			$this->render_enhanced_plugin_card( $plugin, $plugins_allowedtags );
		}

		// Close last group
		if ( ! empty( $current_group ) ) {
			echo '</div></div></div>';
		}
	}

	/**
	 * Render enhanced plugin card without ratings and active installations
	 */
	private function render_enhanced_plugin_card( $plugin, $plugins_allowedtags ) {
		$title = wp_kses( $plugin['name'], $plugins_allowedtags );
		$description = wp_strip_all_tags( $plugin['short_description'] );
		$version = wp_kses( $plugin['version'], $plugins_allowedtags );
		$name = wp_strip_all_tags( $title . ' ' . $version );

		$author = wp_kses( $plugin['author'], $plugins_allowedtags );
		if ( ! empty( $author ) ) {
			$author = ' <cite>' . sprintf( __( 'By %s', 'post-export-import-with-media' ), $author ) . '</cite>';
		}

		// Compatibility checks
		$requires_php = isset( $plugin['requires_php'] ) ? $plugin['requires_php'] : null;
		$requires_wp = isset( $plugin['requires'] ) ? $plugin['requires'] : null;
		$compatible_php = is_php_version_compatible( $requires_php );
		$compatible_wp = is_wp_version_compatible( $requires_wp );

		// Action links
		$action_links = [];
		if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {
			$status = install_plugin_install_status( $plugin );
			$action_links = $this->get_action_links( $status, $plugin, $name, $compatible_php, $compatible_wp );
		}

		// Details link
		$details_link = esc_url( self_admin_url(
			'plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin['slug']
			. '&amp;TB_iframe=true&amp;width=600&amp;height=550'
		) );

		$action_links[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
			esc_url( $details_link ),
			esc_attr( sprintf( __( 'More information about %s', 'post-export-import-with-media' ), $name ) ),
			esc_attr( $name ),
			__( 'More Details', 'post-export-import-with-media' )
		);

		// Icon URL
		$plugin_icon_url = $this->get_plugin_icon_url( $plugin );

		$action_links = apply_filters( 'plugin_install_action_links', $action_links, $plugin );
		$last_updated_timestamp = strtotime( $plugin['last_updated'] );

		// Render the enhanced card with new layout
		?>
		<div class="plugin-card plugin-card-<?php echo sanitize_html_class( $plugin['slug'] ); ?> enhanced-plugin-card">
			<?php $this->render_compatibility_notice( $compatible_php, $compatible_wp ); ?>
			
			<div class="plugin-card-content">
				<!-- Large Plugin Icon -->
				<div class="plugin-icon-section">
					<a href="<?php echo esc_url( $details_link ); ?>" class="thickbox open-plugin-details-modal">
						<img src="<?php echo esc_attr( $plugin_icon_url ); ?>" class="plugin-icon-large" alt="<?php echo esc_attr( $title ); ?>" />
					</a>
				</div>
				
				<!-- Plugin Info -->
				<div class="plugin-info-section">
					<h3 class="plugin-title">
						<a href="<?php echo esc_url( $details_link ); ?>" class="thickbox open-plugin-details-modal">
							<?php echo esc_html( $title ); ?>
						</a>
					</h3>
					
					<?php if ( ! empty( $plugin['key_benefits'] ) ) : ?>
						<div class="plugin-benefits">
							<?php foreach ( array_slice( $plugin['key_benefits'], 0, 3 ) as $benefit ) : ?>
								<span class="benefit-tag"><?php echo esc_html( $benefit ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					
					<div class="plugin-description">
						<p><?php echo esc_html( $description ); ?></p>
						
						<?php if ( ! empty( $plugin['key_benefits'] ) ) : ?>
							<div class="key-features">
								<h4>✨ Key Features:</h4>
								<ul>
									<?php foreach ( $plugin['key_benefits'] as $benefit ) : ?>
										<li><?php echo esc_html( $benefit ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				</div>
				
				<!-- Plugin Actions -->
				<div class="plugin-actions-section">
					<?php if ( $action_links ) : ?>
						<div class="action-buttons">
							<?php foreach ( $action_links as $link ) : ?>
								<?php echo wp_kses_post( $link ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Plugin Meta Footer -->
			<div class="plugin-card-footer">
				<div class="plugin-meta">
					<div class="meta-item">
						<strong><?php esc_attr_e( 'Last Updated:', 'post-export-import-with-media' ); ?></strong>
						<?php printf( esc_html( __( '%s ago', 'post-export-import-with-media' ) ), esc_html( human_time_diff( $last_updated_timestamp ) ) ); ?>
					</div>
					<div class="meta-item">
						<strong><?php esc_attr_e( 'Version:', 'post-export-import-with-media' ); ?></strong>
						<?php echo esc_html( $version ); ?>
					</div>
					<?php if ( ! empty( $author ) ) : ?>
						<div class="meta-item author-info">
							<?php echo $author; //phpcs:ignore ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get plugin icon URL with fallback
	 */
	private function get_plugin_icon_url( $plugin ) {
		if ( ! empty( $plugin['icons']['svg'] ) ) {
			return $plugin['icons']['svg'];
		} elseif ( ! empty( $plugin['icons']['2x'] ) ) {
			return $plugin['icons']['2x'];
		} elseif ( ! empty( $plugin['icons']['1x'] ) ) {
			return $plugin['icons']['1x'];
		} elseif ( ! empty( $plugin['icons']['default'] ) ) {
			return $plugin['icons']['default'];
		} else {
			return 'https://s.w.org/plugins/geopattern-icon/' . $plugin['slug'] . '.svg';
		}
	}

	/**
	 * Get action links for plugin
	 */
	private function get_action_links( $status, $plugin, $name, $compatible_php, $compatible_wp ) {
		$action_links = [];

		switch ( $status['status'] ) {
			case 'install':
				if ( $status['url'] ) {
					if ( $compatible_php && $compatible_wp ) {
						$action_links[] = sprintf(
							'<a class="install-now button button-primary" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
							esc_attr( $plugin['slug'] ),
							esc_url( $status['url'] ),
							esc_attr( sprintf( _x( 'Install %s now', 'plugin', 'post-export-import-with-media' ), $name ) ),
							esc_attr( $name ),
							__( 'Install Now', 'post-export-import-with-media' )
						);
					} else {
						$action_links[] = sprintf(
							'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
							_x( 'Cannot Install', 'plugin', 'post-export-import-with-media' )
						);
					}
				}
				break;

			case 'update_available':
				if ( $status['url'] && $compatible_php && $compatible_wp ) {
					$action_links[] = sprintf(
						'<a class="update-now button button-primary aria-button-if-js" data-plugin="%s" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
						esc_attr( $status['file'] ),
						esc_attr( $plugin['slug'] ),
						esc_url( $status['url'] ),
						esc_attr( sprintf( _x( 'Update %s now', 'plugin', 'post-export-import-with-media' ), $name ) ),
						esc_attr( $name ),
						__( 'Update Now', 'post-export-import-with-media' )
					);
				} else {
					$action_links[] = sprintf(
						'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
						_x( 'Cannot Update', 'plugin', 'post-export-import-with-media' )
					);
				}
				break;

			case 'latest_installed':
			case 'newer_installed':
				if ( is_plugin_active( $status['file'] ) ) {
					$action_links[] = sprintf(
						'<button type="button" class="button button-success" disabled="disabled">✅ %s</button>',
						_x( 'Active', 'plugin', 'post-export-import-with-media' )
					);
				} elseif ( current_user_can( 'activate_plugin', $status['file'] ) ) {
					$button_text = esc_html__( 'Activate', 'post-export-import-with-media' );
					$button_label = _x( 'Activate %s', 'plugin', 'post-export-import-with-media' );
					$activate_url = add_query_arg([
						'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $status['file'] ),
						'action' => 'activate',
						'plugin' => $status['file'],
					], network_admin_url( 'plugins.php' ) );

					if ( is_network_admin() ) {
						$button_text = __( 'Network Activate', 'post-export-import-with-media' );
						$button_label = _x( 'Network Activate %s', 'plugin', 'post-export-import-with-media' );
						$activate_url = add_query_arg( [ 'networkwide' => 1 ], $activate_url );
					}

					$action_links[] = sprintf(
						'<a href="%1$s" class="button button-primary activate-now" aria-label="%2$s">%3$s</a>',
						esc_url( $activate_url ),
						esc_attr( sprintf( $button_label, $plugin['name'] ) ),
						$button_text
					);
				} else {
					$action_links[] = sprintf(
						'<button type="button" class="button button-secondary" disabled="disabled">%s</button>',
						_x( 'Installed', 'plugin', 'post-export-import-with-media' )
					);
				}
				break;
		}

		return $action_links;
	}

	/**
	 * Render compatibility notice
	 */
	private function render_compatibility_notice( $compatible_php, $compatible_wp ) {
		if ( $compatible_php && $compatible_wp ) {
			return;
		}

		echo '<div class="notice inline notice-error notice-alt"><p>';
		if ( ! $compatible_php && ! $compatible_wp ) {
			esc_html_e( 'This plugin doesn&#8217;t work with your versions of WordPress and PHP.', 'post-export-import-with-media' );
		} elseif ( ! $compatible_wp ) {
			esc_html_e( 'This plugin doesn&#8217;t work with your version of WordPress.', 'post-export-import-with-media' );
		} elseif ( ! $compatible_php ) {
			esc_html_e( 'This plugin doesn&#8217;t work with your version of PHP.', 'post-export-import-with-media' );
		}
		echo '</p></div>';
	}

	/**
	 * Clear cache (useful for development or manual refresh)
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
		// Also clear the old cache key
		delete_transient( 'peiwm_recommendations_data' );
	}

}

// Initialize the class
new Recommendations();