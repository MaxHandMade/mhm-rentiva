<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\ContactFormWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\TestimonialsWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleRatingWidget;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * PR F, browser F2: a render-time enqueue never reaches the Elementor editor's preview
 * iframe, so a widget names its rvs stylesheet as a dependency, by a registered handle.
 */
final class RvsWidgetStyleDependsTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		if (! class_exists(\Elementor\Widget_Base::class)) {
			$this->markTestSkipped('Elementor is absent from this test environment.');
		}
	}

	/**
	 * @return array<string, array{0: class-string, 1: string}>
	 */
	public function widgets(): array
	{
		return array(
			'contact'      => array( ContactFormWidget::class, 'mhm-rentiva-rvs-contact' ),
			'testimonials' => array( TestimonialsWidget::class, 'mhm-rentiva-rvs-reviews' ),
			'rating'       => array( VehicleRatingWidget::class, 'mhm-rentiva-rvs-reviews' ),
			'AvailabilityCalendarWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\AvailabilityCalendarWidget::class, 'mhm-rentiva-rvs-availability' ),
			'MyBookingsWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\MyBookingsWidget::class, 'mhm-rentiva-rvs-account' ),
			'MyFavoritesWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\MyFavoritesWidget::class, 'mhm-rentiva-rvs-card' ),
			'PaymentHistoryWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\PaymentHistoryWidget::class, 'mhm-rentiva-rvs-account' ),
			'SearchResultsWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\SearchResultsWidget::class, 'mhm-rentiva-rvs-results' ),
			'UnifiedSearchWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\UnifiedSearchWidget::class, 'mhm-rentiva-rvs-search' ),
			'VehicleComparisonWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleComparisonWidget::class, 'mhm-rentiva-rvs-comparison' ),
			'VehicleDetailsWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleDetailsWidget::class, 'mhm-rentiva-rvs-vehicle' ),
			'VehiclesGridWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesGridWidget::class, 'mhm-rentiva-rvs-card' ),
			'VehiclesListWidget' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesListWidget::class, 'mhm-rentiva-rvs-card' ),
		);
	}

	/**
	 * @dataProvider widgets
	 * @param class-string $widget Widget class.
	 */
	public function test_the_widget_names_its_registered_sheet(string $widget, string $handle): void
	{
		AssetManager::register_core_styles();

		$this->assertContains($handle, WidgetFactory::make($widget)->get_style_depends());
		$this->assertArrayHasKey($handle, wp_styles()->registered);
	}
}
