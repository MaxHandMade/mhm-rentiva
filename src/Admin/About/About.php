<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\About;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Licensing\Mode;



/**
 * About page main class
 */
final class About {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Registers the About class hooks.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		\MHMRentiva\Admin\About\REST\AboutController::register();
	}

	/**
	 * Enqueues admin scripts and styles for the About page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( string $hook ): void
	{
		if ( 'mhm-rentiva_page_mhm-rentiva-about' !== $hook ) {
			return;
		}

		\MHMRentiva\Admin\Core\AssetManager::enqueue_react_page( 'about' );

		wp_enqueue_style(
			'mhm-rentiva-about',
			MHM_RENTIVA_PLUGIN_URL . 'build/admin/about.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch, no state-changing action.
		$raw_tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$allowed     = array( 'general', 'features', 'system', 'support', 'developer' );
		$initial_tab = in_array( $raw_tab, $allowed, true ) ? $raw_tab : 'general';

		wp_localize_script(
			'mhm-rentiva-react-about',
			'mhmRentivaAbout',
			array(
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'initial_tab' => $initial_tab,
			)
		);
	}

	/**
	 * Renders the About page content.
	 *
	 * @return void
	 */
	public function render_page(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$support_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();

		$title = sprintf(
			'%s <span class="version-badge">v%s</span> <span class="license-badge %s">%s</span>',
			esc_html__( 'About MHM Rentiva', 'mhm-rentiva' ),
			MHM_RENTIVA_VERSION,
			Mode::isPro() ? 'pro' : 'lite',
			Mode::isPro() ? esc_html__( 'Pro', 'mhm-rentiva' ) : esc_html__( 'Lite', 'mhm-rentiva' )
		);
		?>
		<div class="wrap mhm-rentiva-about-wrap">
			<?php
			$this->render_admin_header(
				$title,
				array(
					array(
						'type' => 'documentation',
						'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
					),
					array(
						'text'   => __( 'Support', 'mhm-rentiva' ),
						'url'    => 'https://wpalemi.com/support/',
						'class'  => 'button button-secondary',
						'icon'   => 'dashicons-external',
						'target' => '_blank',
					),
					array(
						'text'  => __( 'Settings', 'mhm-rentiva' ),
						'url'   => admin_url( 'admin.php?page=mhm-rentiva-settings' ),
						'class' => 'button button-primary',
						'icon'  => 'dashicons-admin-settings',
					),
				)
			);
			?>
			<div id="mhm-about-root"></div>
		</div>
		<?php
	}
}
