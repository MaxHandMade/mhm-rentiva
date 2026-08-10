<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\About;

if (! defined('ABSPATH')) {
	exit;
}

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
			MHMRENTIVA_PLUGIN_URL . 'build/admin/about.css',
			array(),
			\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'build/admin/about.css' )
		);

		// `initial_tab` is not passed: AboutPage.jsx's getInitialTab() already
		// reads ?tab= from window.location and validates it against the same
		// allow-list (TabNav.jsx's TABS). Whenever the URL carried a valid tab
		// this PHP value was ignored, and whenever it did not, both sides fell
		// back to 'general' -- so reading the superglobal here only duplicated a
		// decision the browser can make about its own URL. The `features` tab
		// stays out of that allow-list on the JS side: it rendered a
		// tier-comparison table from a REST key that no longer exists, so a
		// bookmarked ?tab=features handed React an undefined payload.
		wp_localize_script(
			'mhm-rentiva-react-about',
			'mhmRentivaAbout',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
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

		$support_email = 'support@wpalemi.com';

		$title = sprintf(
			'%s <span class="version-badge">v%s</span>',
			esc_html__( 'About MHM Rentiva', 'mhm-rentiva' ),
			MHMRENTIVA_VERSION
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
