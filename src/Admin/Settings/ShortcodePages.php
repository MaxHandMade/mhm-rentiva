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
		$is_valid_page = false;

		if ( '' !== $this->page_hook && $hook_suffix === $this->page_hook ) {
			$is_valid_page = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug check for admin hook matching.
		} elseif ( isset( $_GET['page'] ) && $_GET['page'] === self::MENU_SLUG ) {
			$is_valid_page = true;
		}

		if ( ! $is_valid_page ) {
			return;
		}

		AssetManager::enqueue_react_page( 'shortcode-pages' );

		wp_enqueue_style(
			'mhm-shortcode-pages',
			MHM_RENTIVA_PLUGIN_URL . 'build/admin/shortcode-pages.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_localize_script(
			'mhm-rentiva-react-shortcode-pages',
			'mhmRentivaShortcodePages',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
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
		echo '<div id="mhm-shortcode-pages-root"></div>';
	}
}
