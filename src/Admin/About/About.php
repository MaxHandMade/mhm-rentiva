<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\About\SystemInfo;
use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\About\Tabs\GeneralTab;
use MHMRentiva\Admin\About\Tabs\FeaturesTab;
use MHMRentiva\Admin\About\Tabs\SystemTab;
use MHMRentiva\Admin\About\Tabs\SupportTab;
use MHMRentiva\Admin\About\Tabs\DeveloperTab;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * About page main class
 */
final class About {



	/**
	 * Registers the About class hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_mhm_rentiva_about_load_tab', array( self::class, 'ajax_load_tab' ) );
	}

	/**
	 * Renders the About page content.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Retrieve system information from cache and set it globally.
		$system_info            = SystemInfo::get_cached_system_info();
		$GLOBALS['system_info'] = $system_info;
		$features               = FeaturesTab::get_features_list();
		$changelog              = SupportTab::get_changelog();

		// Determine the active tab from the URL or default to 'general'.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		?>
		<div class="wrap mhm-rentiva-about-wrap">
			<div class="about-header">
				<div class="header-content">
					<h1><?php esc_html_e( 'About MHM Rentiva', 'mhm-rentiva' ); ?></h1>
					<div class="version-info">
						<span class="version-badge">v<?php echo esc_html( MHM_RENTIVA_VERSION ); ?></span>
						<span class="license-badge <?php echo esc_attr( Mode::isPro() ? 'pro' : 'lite' ); ?>">
							<?php echo esc_html( Mode::isPro() ? esc_html__( 'Pro Version', 'mhm-rentiva' ) : esc_html__( 'Lite Version', 'mhm-rentiva' ) ); ?>
						</span>
					</div>
				</div>
				<div class="header-actions">
					<?php
					$company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
					$support_email   = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();
					?>
					<?php \MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button(); ?>
					<?php
					echo wp_kses_post(
						Helpers::render_external_link(
							'mailto:' . esc_attr( $support_email ),
							esc_html__( 'Support', 'mhm-rentiva' ),
							array( 'class' => 'button button-primary' )
						)
					);
					?>
				</div>
			</div>

			<div class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'general' ) ); ?>"
					class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General Information', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'features' ) ); ?>"
					class="nav-tab <?php echo 'features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Features', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'system' ) ); ?>"
					class="nav-tab <?php echo 'system' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'System Information', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'support' ) ); ?>"
					class="nav-tab <?php echo 'support' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Support', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'developer' ) ); ?>"
					class="nav-tab <?php echo 'developer' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Developer', 'mhm-rentiva' ); ?>
				</a>
			</div>

			<div class="tab-content">
				<?php
				self::render_tab_content( $active_tab, $system_info, $features, $changelog );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the content for a specific tab.
	 *
	 * @param string $tab         The identifier for the tab to render.
	 * @param array  $system_info An array containing system information.
	 * @param array  $features    An array of plugin features.
	 * @param array  $changelog   An array containing changelog data.
	 * @return void
	 */
	private static function render_tab_content( string $tab, array $system_info, array $features, array $changelog ): void {
		switch ( $tab ) {
			case 'general':
				GeneralTab::render( $system_info );
				break;
			case 'features':
				FeaturesTab::render( $features );
				break;
			case 'system':
				SystemTab::render( $system_info );
				break;
			case 'support':
				SupportTab::render( $changelog );
				break;
			case 'developer':
				DeveloperTab::render();
				break;
		}
	}

	/**
	 * Enqueues admin scripts and styles for the About page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( 'mhm-rentiva_page_mhm-rentiva-about' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-about-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/about.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-about-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/about.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-about-admin',
			'mhmRentivaAboutAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mhm_rentiva_about_admin_nonce' ),
				'strings'  => array(
					'loading' => esc_html__( 'Loading...', 'mhm-rentiva' ),
					'error'   => esc_html__( 'An error occurred.', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * Handles AJAX requests for loading tab content.
	 *
	 * @return void
	 */
	public static function ajax_load_tab(): void {
		// Verify nonce for security.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_rentiva_about_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'mhm-rentiva' ) ) );
			return;
		}

		$tab = sanitize_key( wp_unslash( $_POST['tab'] ?? '' ) );

		if ( empty( $tab ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid tab specified.', 'mhm-rentiva' ) ) );
			return;
		}

		// Start output buffering to capture tab content.
		ob_start();

		try {
			// Retrieve system information from cache and set it globally.
			$system_info            = SystemInfo::get_cached_system_info();
			$GLOBALS['system_info'] = $system_info;
			$features               = FeaturesTab::get_features_list();
			$changelog              = SupportTab::get_changelog();

			// Validate and render the requested tab.
			$allowed_tabs = array( 'general', 'features', 'system', 'support', 'developer' );
			if ( ! in_array( $tab, $allowed_tabs, true ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Unknown tab requested.', 'mhm-rentiva' ) ) );
				return;
			}

			self::render_tab_content( $tab, $system_info, $features, $changelog );

			$content = ob_get_clean();

			wp_send_json_success( array( 'content' => $content ) );
		} catch ( \Exception $e ) {
			ob_end_clean();
			wp_send_json_error( array( 'message' => esc_html__( 'An unexpected error occurred: ', 'mhm-rentiva' ) . $e->getMessage() ) );
		}
	}
}
