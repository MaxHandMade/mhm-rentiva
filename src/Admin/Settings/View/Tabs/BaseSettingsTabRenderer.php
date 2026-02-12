<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base Renderer for standard settings tabs that use Group classes
 */
class BaseSettingsTabRenderer extends AbstractTabRenderer {


	/**
	 * @param string      $label Tab Label
	 * @param string      $slug Tab Slug
	 * @param string|null $description Tab description
	 * @param string|null $group_class Fully qualified group class name
	 * @param array       $sections Fallback sections if group class is not used/found
	 */
	public function __construct(
		string $label,
		string $slug,
		protected readonly ?string $description = null,
		protected readonly ?string $group_class = null,
		protected readonly array $sections = array()
	) {
		parent::__construct( $label, $slug );
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void {
		if ( $this->group_class && class_exists( $this->group_class ) ) {
			$class = $this->group_class;
			$class::render_settings_section();
		} else {
			foreach ( $this->sections as $section ) {
				$this->render_section_clean( (string) $section );
			}
		}
	}
}
