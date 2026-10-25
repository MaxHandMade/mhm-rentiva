<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 1: the Elementor bridge of the front-end standard. A plain control
 * writes one `--rvs-*` token on `.rvs-shell`; a Dimensions control writes a
 * four-value token; the control id is kept verbatim (it is the storage key).
 */
final class RvsTokenControlsTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		if (! class_exists(\Elementor\Widget_Base::class)) {
			$this->markTestSkipped('Elementor is absent from this test environment.');
		}
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function controls(string $class = RvsTokenProbeWidget::class): array
	{
		// Same read as StyleControlInventory: drop the cached stack, then build it.
		$widget = WidgetFactory::make($class);
		\Elementor\Plugin::$instance->controls_manager->delete_stack($widget);
		$stack = $widget->get_stack(false);

		return (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
	}

	public function test_token_control_writes_variable_on_shell(): void
	{
		$control = $this->controls()['card_accent_color'] ?? null;

		$this->assertIsArray($control);
		$this->assertSame('color', $control['type']);
		$this->assertSame(array( '{{WRAPPER}} .rvs-shell' => '--rvs-accent: {{VALUE}};' ), $control['selectors']);
	}

	public function test_dimensions_control_writes_four_value_variable(): void
	{
		$control = $this->controls()['card_padding'] ?? null;

		$this->assertIsArray($control);
		$this->assertSame('dimensions', $control['type']);
		$this->assertSame(
			array( '{{WRAPPER}} .rvs-shell' => '--rvs-card-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			$control['selectors']
		);
	}

	public function test_dimensions_control_is_not_responsive_by_default(): void
	{
		$controls = $this->controls();

		// Elementor 4 marks a responsive control with is_responsive (the per-device
		// values are stored under the same id); a plain one carries no flag.
		$this->assertArrayHasKey('card_padding', $controls);
		$this->assertEmpty($controls['card_padding']['is_responsive'] ?? null, 'Existing Dimensions controls are plain; their saved values must keep their shape.');
		$this->assertTrue((bool) ( $controls['new_gap']['is_responsive'] ?? false ), 'Opt-in responsive control.');
	}

	public function test_control_id_and_args_are_kept(): void
	{
		$control = $this->controls()['card_text_color'] ?? null;

		// The stack keeps the id (array key and name) and the extra args; labels
		// are not part of the stored stack.
		$this->assertIsArray($control);
		$this->assertSame('card_text_color', $control['name']);
		$this->assertSame('#201e1d', $control['default']);
	}

	public function test_token_name_with_prefix_is_a_caller_error(): void
	{
		$this->setExpectedIncorrectUsage('MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase::rvs_token_name');

		$control = $this->controls(RvsTokenPrefixedProbeWidget::class)['bad_prefix'] ?? null;

		$this->assertSame(array( '{{WRAPPER}} .rvs-shell' => '--rvs-accent: {{VALUE}};' ), $control['selectors'] ?? null, 'The prefix is stripped, not doubled.');
	}
}

class RvsTokenProbeWidget extends ElementorWidgetBase
{
	public function get_name(): string
	{
		return 'rvs-token-probe';
	}

	public function get_title(): string
	{
		return 'RVS token probe';
	}

	protected function register_style_controls(): void
	{
		$this->start_controls_section('probe_style', array( 'label' => 'Probe', 'tab' => 'style' ));
		$this->add_rvs_token_control('card_accent_color', 'Accent', 'accent');
		$this->add_rvs_token_control('card_text_color', 'Text', 'text', array( 'default' => '#201e1d' ));
		$this->add_rvs_token_dimensions_control('card_padding', 'Padding', 'card-pad');
		$this->add_rvs_token_dimensions_control('new_gap', 'Gap', 'gap', array(), true);
		$this->end_controls_section();
	}
}

final class RvsTokenPrefixedProbeWidget extends RvsTokenProbeWidget
{
	public function get_name(): string
	{
		return 'rvs-token-prefixed-probe';
	}

	protected function register_style_controls(): void
	{
		$this->start_controls_section('probe_style', array( 'label' => 'Probe', 'tab' => 'style' ));
		$this->add_rvs_token_control('bad_prefix', 'Bad', '--rvs-accent');
		$this->end_controls_section();
	}
}
