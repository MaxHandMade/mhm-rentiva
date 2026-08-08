<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Blocks;

use MHMRentiva\Blocks\BlockRegistry;
use WP_UnitTestCase;

/**
 * Locks CSS-context validation for dynamic block dimension attributes.
 *
 * @covers \MHMRentiva\Blocks\BlockRegistry::render_callback
 */
final class BlockDimensionStyleValidationTest extends WP_UnitTestCase
{
	private const BLOCK_NAME = 'mhm-rentiva/test-dimension-style';
	private const TAG        = 'mhmrentiva_test_dimension_style';

	/**
	 * Shortcode attributes observed after BlockRegistry's CAM boundary.
	 *
	 * @var array<string, mixed>
	 */
	private array $rendered_shortcode_attributes = array();

	protected function setUp(): void
	{
		parent::setUp();

		add_shortcode(self::TAG, array($this, 'render_safe_shortcode'));
		add_filter('mhmrentiva_blocks', array($this, 'contribute_test_block'));
		add_filter('mhmrentiva_attribute_registry', array($this, 'contribute_test_attribute_schema'));
		register_block_type(
			self::BLOCK_NAME,
			array(
				'attributes'      => array(
					'minWidth' => array('type' => 'string'),
					'maxWidth' => array('type' => 'string'),
					'height'   => array('type' => 'string'),
				),
				'render_callback' => array(BlockRegistry::class, 'render_callback'),
			)
		);
	}

	protected function tearDown(): void
	{
		remove_filter('mhmrentiva_blocks', array($this, 'contribute_test_block'));
		remove_filter('mhmrentiva_attribute_registry', array($this, 'contribute_test_attribute_schema'));
		remove_shortcode(self::TAG);

		if (\WP_Block_Type_Registry::get_instance()->is_registered(self::BLOCK_NAME)) {
			unregister_block_type(self::BLOCK_NAME);
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider valid_dimension_provider
	 */
	public function test_valid_numeric_and_common_css_lengths_reach_the_wrapper_style(
		string $attribute,
		string $value,
		string $expected_declaration
	): void {
		$wrapper_attributes = $this->render_wrapper_attributes(array($attribute => $value));

		$this->assertStringContainsString($expected_declaration, $wrapper_attributes);
		$this->assertStringContainsString(
			$expected_declaration,
			(string) ($this->rendered_shortcode_attributes['style'] ?? '')
		);
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public function valid_dimension_provider(): array
	{
		return array(
			'unitless numeric values become pixels' => array('minWidth', '320', 'min-width:320px'),
			'percentages remain percentages'         => array('maxWidth', '75%', 'max-width:75%'),
			'rem lengths remain rem lengths'          => array('height', '12.5rem', 'height:12.5rem'),
			'auto remains an intentional keyword'     => array('height', 'auto', 'height:auto'),
		);
	}

	public function test_css_declaration_injection_values_do_not_reach_shortcode_style(): void
	{
		$this->render_wrapper_attributes($this->unsafe_dimensions());

		$this->assertArrayNotHasKey(
			'style',
			$this->rendered_shortcode_attributes,
			'Unsafe dimensions must not reach the shortcode inline-style channel.'
		);
	}

	public function test_css_declaration_injection_values_do_not_reach_wrapper_style(): void
	{
		$wrapper_attributes = $this->render_wrapper_attributes($this->unsafe_dimensions());

		$this->assertStringNotContainsString('background-image', $wrapper_attributes);
		$this->assertStringNotContainsString('javascript:', $wrapper_attributes);
		$this->assertStringNotContainsString('position:fixed', $wrapper_attributes);
		$this->assertStringNotContainsString('expression(', $wrapper_attributes);
		$this->assertStringNotContainsString('min-width:', $wrapper_attributes);
		$this->assertStringNotContainsString('max-width:', $wrapper_attributes);
		$this->assertStringNotContainsString('height:', $wrapper_attributes);
	}

	/**
	 * @param array<string, array<string, mixed>> $blocks Filtered block registry.
	 * @return array<string, array<string, mixed>>
	 */
	public function contribute_test_block(array $blocks): array
	{
		$blocks['test-dimension-style'] = array(
			'tag' => self::TAG,
			'css' => array(),
		);

		return $blocks;
	}

	/**
	 * @param array<string, mixed>|string $attributes Shortcode attributes.
	 */
	public function render_safe_shortcode($attributes): string
	{
		$this->rendered_shortcode_attributes = (array) $attributes;

		return '<span>Safe content</span>';
	}

	/**
	 * @param array<string, array<string, array<string, mixed>>> $registry Attribute registry.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function contribute_test_attribute_schema(array $registry): array
	{
		$registry[self::TAG] = array(
			'style' => array('type' => 'string'),
		);

		return $registry;
	}

	/**
	 * @param array<string, string> $attributes Block attributes.
	 */
	private function render_wrapper_attributes(array $attributes): string
	{
		$output = (string) render_block(
			array(
				'blockName'    => self::BLOCK_NAME,
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$this->assertMatchesRegularExpression('/^<div\s+[^>]*>/', trim($output));
		preg_match('/^<div\s+([^>]*)>/', trim($output), $matches);

		return (string) ($matches[1] ?? '');
	}

	/**
	 * @return array<string, string>
	 */
	private function unsafe_dimensions(): array
	{
		return array(
			'minWidth' => '1px;background-image:url(javascript:alert(1))',
			'maxWidth' => '100%;position:fixed',
			'height'   => 'expression(alert(1))',
		);
	}
}
