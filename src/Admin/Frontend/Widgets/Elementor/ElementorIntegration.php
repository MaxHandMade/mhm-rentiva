<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Integration Class
 *
 * Registers MHM Rentiva shortcodes as Elementor widgets
 *
 * @since 3.0.1
 */
class ElementorIntegration {


	/**
	 * Register widgets
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager
	 */
	public static function register_widgets( $widgets_manager = null ): void {
		// Elementor aktif mi kontrol et
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Get Widgets Manager (from parameter or instance)
		$elementor = '\Elementor\Plugin';
		$manager   = $widgets_manager ?? $elementor::instance()->widgets_manager;

		// Register widgets (Elementor 3.5+ API)
		foreach ( self::get_widget_classes() as $widget_class ) {
			$manager->register( new $widget_class() );
		}
	}

	/**
	 * Lite's own widget classes, plus whatever the add-on's `ElementorExtensions`
	 * subscriber (or any other subscriber) hands back via the
	 * `mhmrentiva_elementor_widgets` filter -- the extension point. Lite no
	 * longer knows the 6 add-on widget classes or their registration state
	 * exist; gating them is entirely the add-on's own responsibility now
	 * (mirrors `BlockRegistry::get_block_config()` and
	 * `ShortcodeServiceProvider::get_registry()`).
	 *
	 * @return array<int, class-string>
	 */
	private static function get_widget_classes(): array {
		$widgets = array(
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\UnifiedSearchWidget::class, // Formerly VehicleSearch
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\SearchResultsWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesListWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesGridWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\FeaturedVehiclesWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleDetailsWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\BookingFormWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\AvailabilityCalendarWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\MyBookingsWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\MyFavoritesWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\PaymentHistoryWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleComparisonWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\ContactFormWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\TestimonialsWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleRatingWidget::class,
			\MHMRentiva\Admin\Frontend\Widgets\Elementor\UserDashboardWidget::class,
		);

		/** @var array<int, class-string> $widgets */
		$widgets = apply_filters( 'mhmrentiva_elementor_widgets', $widgets );

		return $widgets;
	}

	/**
	 * Check if Elementor is active
	 *
	 * @return bool Elementor aktif mi
	 */
	public static function is_elementor_active(): bool {
		return (bool) did_action( 'elementor/loaded' );
	}

	/**
	 * Elementor kategorisini kaydeder
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager
	 */
	public static function register_category( $elements_manager = null ): void {
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Get Elements Manager (from parameter or instance)
		$elementor = '\Elementor\Plugin';
		$manager   = $elements_manager ?? $elementor::instance()->elements_manager;

		// Kategoriyi kaydet
		$manager->add_category(
			'mhm-rentiva',
			array(
				'title' => __( 'MHM Rentiva', 'mhm-rentiva' ),
				'icon'  => 'fa fa-car',
			),
			1 // Position (1 = at the beginning)
		);
	}

	/**
	 * Load Elementor CSS files
	 */
	public static function enqueue_styles(): void {
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Load CSS file if exists
		$css_file = MHMRENTIVA_PLUGIN_DIR . 'assets/css/frontend/elementor-widgets.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'mhm-rentiva-elementor',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/elementor-widgets.css',
				array( 'elementor-frontend', 'mhm-rentiva-core-css' ),
				MHMRENTIVA_VERSION
			);
		}
	}

	/**
	 * Load Elementor JavaScript files
	 */
	public static function enqueue_scripts(): void {
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Load JS file if exists
		$js_file = MHMRENTIVA_PLUGIN_DIR . 'assets/js/frontend/elementor-widgets.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'mhm-rentiva-elementor',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/elementor-widgets.js',
				array( 'elementor-frontend', 'mhm-rentiva-core-js' ),
				MHMRENTIVA_VERSION,
				true
			);

			// The mhmRentivaElementor localize call (and the nonce it minted)
			// formerly here were removed: elementor-widgets.js contains only
			// empty handler stubs and never
			// referenced this object.
		}
	}

	/**
	 * Load Elementor editor CSS files
	 */
	public static function enqueue_editor_styles(): void {
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Load CSS file if exists
		$css_file = MHMRENTIVA_PLUGIN_DIR . 'assets/css/admin/elementor-editor.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'mhm-rentiva-elementor-editor',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/elementor-editor.css',
				array( 'elementor-editor' ),
				MHMRENTIVA_VERSION
			);
		}
	}

	/**
	 * Load Elementor editor JavaScript files
	 */
	public static function enqueue_editor_scripts(): void {
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Load JS file if exists
		$js_file = MHMRENTIVA_PLUGIN_DIR . 'assets/js/admin/elementor-editor.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'mhm-rentiva-elementor-editor',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/elementor-editor.js',
				array( 'elementor-editor' ),
				MHMRENTIVA_VERSION,
				true
			);

			// The mhmRentivaElementorEditor localize call (and the nonce it
			// minted) formerly here were removed: elementor-editor.js never
			// referenced this object.
		}
	}

	/**
	 * Enqueue front-end widget assets into the Elementor preview iframe.
	 *
	 * Some widgets enqueue their JS/CSS only at shortcode-render time. Those render-time
	 * enqueues are not replayed into the editor preview iframe, so e.g. the Featured
	 * Vehicles slider renders its markup but its Swiper initialiser never loads — the
	 * widget then looks empty in the editor while working fine on the front-end.
	 * Loading the initialiser here lets the preview initialise like the front-end.
	 */
	public static function enqueue_preview_scripts(): void {
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Featured Vehicles slider (default layout) — Swiper is registered by AssetManager.
		wp_enqueue_style( 'mhm-rentiva-swiper-css' );
		wp_enqueue_script( 'mhm-rentiva-swiper' );
		wp_enqueue_style(
			'mhm-rentiva-featured-vehicles-preview',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/featured-vehicles.css',
			array( 'mhm-rentiva-css-variables' ),
			MHMRENTIVA_VERSION
		);
		wp_enqueue_script(
			'mhm-rentiva-featured-vehicles-preview',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/featured-vehicles.js',
			array( 'jquery', 'mhm-rentiva-swiper' ),
			MHMRENTIVA_VERSION,
			true
		);
	}

	/**
	 * Register Elementor hooks
	 */
	public static function register_hooks(): void {
		// Exit if Elementor is not active
		if ( ! self::is_elementor_active() ) {
			return;
		}

		// Register widgets
		add_action( 'elementor/widgets/register', array( self::class, 'register_widgets' ), 10 );

		// Register category
		add_action( 'elementor/elements/categories_registered', array( self::class, 'register_category' ), 10 );

		// Load CSS/JS
		add_action( 'elementor/frontend/after_enqueue_styles', array( self::class, 'enqueue_styles' ), 10 );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( self::class, 'enqueue_scripts' ), 10 );
		add_action( 'elementor/editor/before_enqueue_styles', array( self::class, 'enqueue_editor_styles' ), 10 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( self::class, 'enqueue_editor_scripts' ), 10 );

		// Preview iframe: some widget assets are enqueued only at shortcode-render time
		// and are NOT replayed into the Elementor editor preview, so sliders render but
		// never initialise (appear empty in the editor). Load them here for the preview.
		add_action( 'elementor/preview/enqueue_scripts', array( self::class, 'enqueue_preview_scripts' ), 10 );
	}

	/**
	 * Initializes Elementor integration
	 */
	public static function init(): void {
		// Register hooks after Elementor is loaded
		add_action( 'elementor/loaded', array( self::class, 'register_hooks' ) );

		// Also register manually (for security)
		add_action( 'init', array( self::class, 'register_hooks' ), 20 );
	}
}
