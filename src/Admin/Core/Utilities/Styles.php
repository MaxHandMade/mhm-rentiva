<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSS Style Management Class
 *
 * Loads core CSS files in both admin and frontend.
 */
class Styles {

	/**
	 * Plugin directory path
	 */
	private $plugin_dir;

	/**
	 * Plugin URL
	 */
	private $plugin_url;

	/**
	 * CSS handle name - Compatible with AssetManager
	 */
	const CSS_HANDLE = 'mhm-core-css';

	/**
	 * Constructor
	 */
	public function __construct( $plugin_dir, $plugin_url ) {
		$this->plugin_dir = $plugin_dir;
		$this->plugin_url = $plugin_url;
	}

	/**
	 * Register CSS enqueue operations
	 */
	public function register() {
		// Load CSS on frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueCoreCss' ) );

		// Load CSS in admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueCoreCss' ) );
	}

	/**
	 * Load core CSS file - Compatible with AssetManager
	 */
	public function enqueueCoreCss() {
		// If AssetManager is already loaded, don't load again
		if ( wp_style_is( 'mhm-core-css', 'enqueued' ) || wp_style_is( 'mhm-core-css', 'done' ) ) {
			return;
		}

		$css_path = $this->plugin_dir . 'assets/css/core/core.css';
		$css_url  = $this->plugin_url . 'assets/css/core/core.css';

		// Same versioning system as AssetManager
		$version = defined( 'MHM_RENTIVA_VERSION' ) ? MHM_RENTIVA_VERSION : '1.0.0';

		// Enqueue CSS - Compatible with AssetManager dependency order
		wp_enqueue_style(
			self::CSS_HANDLE,
			$css_url,
			array(), // Dependency management handled in AssetManager
			$version,
			'all'
		);

		// Also load CSS Variables
		$css_vars_url = $this->plugin_url . 'assets/css/core/css-variables.css';
		wp_enqueue_style(
			'mhm-css-variables',
			$css_vars_url,
			array(),
			$version,
			'all'
		);

		// Also load Animations
		$animations_url = $this->plugin_url . 'assets/css/core/animations.css';
		wp_enqueue_style(
			'mhm-animations',
			$animations_url,
			array( 'mhm-css-variables' ),
			$version,
			'all'
		);
	}

	/**
	 * Get CSS handle name
	 */
	public static function getCssHandle() {
		return self::CSS_HANDLE;
	}
}
