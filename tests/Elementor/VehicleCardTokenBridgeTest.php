<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 2: the vehicle card widget's controls keep their ids (saved pages
 * store values under them) and now drive the front-end standard card: plain
 * controls write --rvs-card-* tokens on .rvs-shell, which rvs-card.css reads.
 */
final class VehicleCardTokenBridgeTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		if (! class_exists(\Elementor\Widget_Base::class)) {
			$this->markTestSkipped('Elementor is absent from this test environment.');
		}
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function controls(): array
	{
		$widget = WidgetFactory::make(VehicleCardWidget::class);
		\Elementor\Plugin::$instance->controls_manager->delete_stack($widget);
		$stack = $widget->get_stack(false);

		return (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function token_controls(): array
	{
		return array(
			'card_background'            => array( 'card_background', '--rvs-card-bg' ),
			'border_radius'              => array( 'border_radius', '--rvs-card-radius' ),
			'card_padding'               => array( 'card_padding', '--rvs-card-pad' ),
			'title_color'                => array( 'title_color', '--rvs-card-title' ),
			'title_hover_color'          => array( 'title_hover_color', '--rvs-card-title-hover' ),
			'title_margin'               => array( 'title_margin', '--rvs-card-title-margin' ),
			'price_color'                => array( 'price_color', '--rvs-card-price' ),
			'price_period_color'         => array( 'price_period_color', '--rvs-card-price-period' ),
			'primary_button_color'       => array( 'primary_button_color', '--rvs-card-btn-bg' ),
			'primary_button_hover_color' => array( 'primary_button_hover_color', '--rvs-card-btn-bg-hover' ),
			'button_border_radius'       => array( 'button_border_radius', '--rvs-card-btn-radius' ),
		);
	}

	/**
	 * @dataProvider token_controls
	 */
	public function test_saved_control_reaches_its_token(string $id, string $token): void
	{
		$control = $this->controls()[ $id ] ?? null;

		$this->assertIsArray($control, "$id must keep its id.");
		$this->assertSame(array( '{{WRAPPER}} .rvs-shell' ), array_keys($control['selectors']));
		$this->assertStringStartsWith($token . ': ', (string) reset($control['selectors']));

		$css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/rvs/rvs-card.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringContainsString('var(' . $token . ')', $css, "rvs-card.css must read $token.");

		$tokens = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/rvs/rvs-tokens.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringContainsString($token . ':', $tokens, "$token needs its default in rvs-tokens.css.");
	}

	public function test_group_controls_target_rvs_classes(): void
	{
		$controls = $this->controls();

		$this->assertSame('{{WRAPPER}} .rvs-card', $controls['card_border_border']['selector'] ?? ( $controls['card_border_border']['selectors'] ? key($controls['card_border_border']['selectors']) : null ));
		$this->assertStringContainsString('.rvs-card__title', (string) wp_json_encode($controls['title_typography_font_family'] ?? array()));
		$this->assertStringContainsString('.rvs-card__amount', (string) wp_json_encode($controls['price_amount_typography_font_family'] ?? array()));
		$this->assertStringContainsString('.rvs-card__select', (string) wp_json_encode($controls['booking_button_typography_font_family'] ?? array()));
	}
}
