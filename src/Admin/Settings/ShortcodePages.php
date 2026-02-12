<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageAjax;
use InvalidArgumentException;
use RuntimeException;

/**
 * Prevent direct access.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * AJAX action constants.
	 */
	public const ACTION_CLEAR_CACHE  = 'mhm_clear_shortcode_cache';
	public const ACTION_CREATE_PAGE  = 'mhm_create_shortcode_page';
	public const ACTION_DELETE_PAGE  = 'mhm_delete_shortcode_page';
	public const ACTION_DEBUG_SEARCH = 'mhm_debug_shortcode_search';
	public const ACTION_RESET_PAGES  = 'mhm_reset_shortcode_pages';

	/**
	 * Asset handles.
	 */
	private const SCRIPT_HANDLE = 'mhm-shortcode-pages';
	private const STYLE_HANDLE  = 'mhm-shortcode-pages';

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
	 * @param ShortcodePageAjax    $ajax        AJAX response handler.
	 * @param ShortcodeUrlManager  $url_manager  Manager for retrieving page data.
	 */
	public function __construct(
		private ShortcodePageActions $actions,
		private ShortcodePageAjax $ajax,
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
			$ajax        = new ShortcodePageAjax( $actions );
			$url_manager = new ShortcodeUrlManager();

			self::$instance = new self( $actions, $ajax, $url_manager );
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
		$this->register_ajax_handlers();
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
		// 1. Primary Check: If page_hook is set (internal reg), verify against it.
		// 2. Fallback Check: If registered externally (Menu.php), verify via regex or GET param.
		$is_valid_page = false;

		if ( '' !== $this->page_hook && $hook_suffix === $this->page_hook ) {
			$is_valid_page = true;
		} elseif ( isset( $_GET['page'] ) && $_GET['page'] === self::MENU_SLUG ) {
			// Additional safety: Check if base hook relates to our plugin if needed,
			// but slug check is usually sufficient for admin pages.
			$is_valid_page = true;
		}

		if ( ! $is_valid_page ) {
			return;
		}

		$version  = defined( 'MHM_RENTIVA_VERSION' ) ? (string) MHM_RENTIVA_VERSION : '1.0.0';
		$base_url = defined( 'MHM_RENTIVA_PLUGIN_URL' ) ? trailingslashit( (string) MHM_RENTIVA_PLUGIN_URL ) : '';

		if ( '' === $base_url ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			$base_url . 'assets/css/mhm-shortcode-pages.css',
			array(),
			$version
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$base_url . 'assets/js/mhm-shortcode-pages.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'MHMRentivaShortcodes',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'actions' => array(
					'clearCache'  => self::ACTION_CLEAR_CACHE,
					'createPage'  => self::ACTION_CREATE_PAGE,
					'deletePage'  => self::ACTION_DELETE_PAGE,
					'debugSearch' => self::ACTION_DEBUG_SEARCH,
					'resetPages'  => self::ACTION_RESET_PAGES,
				),
				'nonces'  => array(
					'clearCache'  => wp_create_nonce( self::ACTION_CLEAR_CACHE ),
					'createPage'  => wp_create_nonce( self::ACTION_CREATE_PAGE ),
					'deletePage'  => wp_create_nonce( self::ACTION_DELETE_PAGE ),
					'debugSearch' => wp_create_nonce( self::ACTION_DEBUG_SEARCH ),
					'resetPages'  => wp_create_nonce( self::ACTION_RESET_PAGES ),
				),
				'i18n'    => array(
					'confirmClearCache' => __( 'Cache will be cleared. Do you want to continue?', 'mhm-rentiva' ),
					'confirmCreatePage' => __( 'A page will be created for this shortcode. Do you want to continue?', 'mhm-rentiva' ),
					'creatingText'      => __( 'Creating...', 'mhm-rentiva' ),
					'confirmGoToEditor' => __( 'Page created! Go to editor?', 'mhm-rentiva' ),
					'confirmDeletePage' => __( 'Move this page to trash?', 'mhm-rentiva' ),
					'confirmReset'      => __( 'This action will permanently delete all created shortcode pages. Are you sure?', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * Registers AJAX handlers for authenticated users.
	 * Checks for method existence on the delegated object.
	 *
	 * @return void
	 */
	public function register_ajax_handlers(): void {
		$handlers = array(
			self::ACTION_CLEAR_CACHE  => 'clear_cache',
			self::ACTION_CREATE_PAGE  => 'create_page',
			self::ACTION_DELETE_PAGE  => 'delete_page',
			self::ACTION_DEBUG_SEARCH => 'debug_search',
			self::ACTION_RESET_PAGES  => 'reset_pages',
		);

		foreach ( $handlers as $action => $method ) {
			if ( method_exists( $this->ajax, $method ) ) {
				add_action( "wp_ajax_{$action}", array( $this->ajax, $method ) );
			}
		}
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

		try {
			ob_start();
			$this->render_admin_header(
				(string) get_admin_page_title(),
				array(
					array(
						'type' => 'documentation',
						'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
					),
					array(
						'type' => 'reset',
						'url'  => '#',
						'id'   => 'mhm-btn-reset-pages',
					),
				)
			);
			$this->render_developer_mode_banner();
			$header_html = ob_get_clean();

			$this->render_view(
				'admin/shortcode-pages',
				array(
					'pages'             => $this->url_manager->get_all_pages(),
					'shortcodes_config' => $this->actions->get_config(),
					'menu_slug'         => self::MENU_SLUG,
					'header_html'       => $header_html,
				)
			);
		} catch ( RuntimeException $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Renders a template file with a secure and isolated scope.
	 *
	 * @param string $template Template name (relative to 'templates/' folder)
	 * @param array  $args     Data to pass to the template
	 * @throws RuntimeException If template cannot be found or accessed.
	 * @return void
	 */
	private function render_view( string $template, array $args = array() ): void {
		$base_path     = defined( 'MHM_RENTIVA_PLUGIN_PATH' ) ? (string) MHM_RENTIVA_PLUGIN_PATH : '';
		$templates_dir = realpath( trailingslashit( $base_path ) . 'templates/' );

		if ( false === $templates_dir ) {
			throw new RuntimeException( esc_html__( 'Templates directory not found.', 'mhm-rentiva' ) );
		}

		$file_path = $templates_dir . DIRECTORY_SEPARATOR . ltrim( $template, '/' ) . '.php';
		$real_file = realpath( $file_path );

		// Strict security check for Directory Traversal.
		if ( false === $real_file || ! str_starts_with( $real_file, $templates_dir ) ) {
			throw new InvalidArgumentException(
				/* translators: %s: illegal template name */
				sprintf( esc_html__( 'Illegal template access: %s', 'mhm-rentiva' ), esc_html( $template ) )
			);
		}

		/**
		 * Isolate scope using an anonymous function.
		 */
		( static function ( string $template_file, array $args ): void {
			// Map only valid variable names into local template scope.
			foreach ( $args as $key => $value ) {
				if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
					continue;
				}
				${$key} = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			}
			require $template_file;
		} )( $real_file, $args );
	}
}
