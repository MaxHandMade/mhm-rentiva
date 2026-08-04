<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use MHMRentiva\Admin\Settings\ShortcodePages\REST\ShortcodePagesController;
use MHMRentiva\Admin\Core\AssetManager;

/**
 * Class ShortcodePages
 *
 * Orchestrates menu registration, asset loading, and functional sub-modules.
 * Adheres to SRP (Single Responsibility Principle) and PHP 8.2+ standards.
 *
 * @package MHMRentiva\Admin\Settings
 * @since 4.0.0
 * @author Lead WordPress Plugin Architect
 */
final class ShortcodePages {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;
	/**
	 * Slug for the admin page.
	 */
	private const MENU_SLUG = 'mhm-rentiva-shortcode-pages';

	/**
	 * Hook suffix for the admin page.
	 */
	private string $page_hook = '';

	/**
	 * Constructor using Dependency Injection.
	 *
	 * @param ShortcodePageActions $actions     Business logic handler.
	 * @param ShortcodeUrlManager  $url_manager  Manager for retrieving page data.
	 */
	public function __construct(
		private ShortcodePageActions $actions,
		private ShortcodeUrlManager $url_manager
	) {}

	/**
	 * Factory method to initialize the class with proper dependencies.
	 * Decouples instantiation from registration.
	 *
	 * @return self
	 */
	public static function register(): self {
		if ( self::$instance === null ) {
			$actions     = new ShortcodePageActions();
			$url_manager = new ShortcodeUrlManager();

			self::$instance = new self( $actions, $url_manager );
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/**
	 * Get instance
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Registers all WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// REST routes are registered context-agnostically from Plugin::initialize_remaining_services()
		// to ensure they fire on REST API requests where is_admin() returns false.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the page in the WordPress admin menu.
	 *
	 * Note: This method might not be called if Menu.php handles registration centrally.
	 * Use enqueue_assets carefully.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		$this->page_hook = (string) add_submenu_page(
			'mhm-rentiva',
			esc_html__( 'Shortcode Pages', 'mhm-rentiva' ),
			esc_html__( 'Shortcode Pages', 'mhm-rentiva' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues CSS and JS assets specifically for this admin page.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Menu.php registers this screen centrally and never calls
		// add_admin_menu(), so $this->page_hook is empty in production -- the
		// hook-suffix match below is the branch that actually fires. Gating on
		// the $hook_suffix WordPress hands to admin_enqueue_scripts is the same
		// gate DashboardPage and About use; it replaces a $_GET['page'] read
		// that duplicated information already in the callback's own argument.
		//
		// This used to fall back to str_contains($hook_suffix, self::MENU_SLUG)
		// whenever $this->page_hook was empty -- i.e. always, in production --
		// so str_contains() alone decided every request, and any foreign hook
		// that merely EMBEDS "mhm-rentiva-shortcode-pages" as a substring (not
		// just this exact screen) would also pull the bundle in (T8 Görev 11,
		// independent nonce-behavior audit, Fable#2; see
		// ShortcodePagesEnqueueGateTest for the runtime proof of the exact
		// suffix and the foreign-hook cases this closes).
		//
		// Menu.php registers this screen as a submenu of the fixed top-level
		// 'mhm-rentiva' parent (Menu.php's add_menu(), 'mhm-rentiva-shortcode-
		// pages' slug), so WordPress's own hookname formula
		// (get_plugin_page_hookname()) is fully determined at compile time --
		// the same reasoning About::enqueue_scripts() and Görev 3's
		// Addons\AddonSettings::enqueue_scripts() already use for their own
		// screens. $this->page_hook is kept as an exact-match alternative (not
		// a substring one) for the add_admin_menu() path, which no production
		// code calls today but which a test may still exercise directly.
		$is_valid_page = ( '' !== $this->page_hook && $hook_suffix === $this->page_hook )
			|| 'mhm-rentiva_page_' . self::MENU_SLUG === $hook_suffix;

		if ( ! $is_valid_page ) {
			return;
		}

		AssetManager::enqueue_react_page( 'shortcode-pages' );

		wp_enqueue_style(
			'mhm-rentiva-shortcode-pages',
			MHMRENTIVA_PLUGIN_URL . 'build/admin/shortcode-pages.css',
			array(),
			filemtime( MHMRENTIVA_PLUGIN_DIR . 'build/admin/shortcode-pages.css' ) ?: MHMRENTIVA_VERSION
		);

		// The mhmRentivaShortcodePages localize call formerly here was
		// removed (WP.org T8 Görev 10b, row E4): neither src-react/admin/
		// shortcode-pages/ nor its build ever read this object -- the React
		// app authenticates its REST calls via apiFetch instead.
	}

	/**
	 * Renders the admin page content with capability check.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mhm-rentiva' ) );
		}
		?>
		<div class="wrap mhm-rentiva-shortcode-pages-wrap">
			<?php
			$this->render_admin_header(
				(string) get_admin_page_title(),
				array(
					array(
						'type' => 'documentation',
						'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
					),
				)
			);
			$this->render_developer_mode_banner();
			?>
			<div id="mhm-shortcode-pages-root"></div>
		</div>
		<?php
	}
}
