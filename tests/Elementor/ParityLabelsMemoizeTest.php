<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ParityLabels;
use ReflectionProperty;
use WP_UnitTestCase;

/**
 * The filtered ParityLabels dictionary is memoised per locale and per set of
 * callbacks hooked to `mhmrentiva_parity_labels`.
 */
final class ParityLabelsMemoizeTest extends WP_UnitTestCase
{
	private const FILTER = 'mhmrentiva_parity_labels';

	public function set_up(): void
	{
		parent::set_up();
		$this->reset_statics();
	}

	public function tear_down(): void
	{
		remove_all_filters( self::FILTER );
		$this->reset_statics();
		parent::tear_down();
	}

	private function reset_statics(): void
	{
		foreach ( array( 'cache' => array(), 'filtered' => null ) as $name => $value ) {
			$property = new ReflectionProperty( ParityLabels::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, $value );
		}
	}

	/**
	 * @return callable(array<string,mixed>):array<string,mixed>
	 */
	private function writer( string $key, string $label ): callable
	{
		return static function ( array $dictionary ) use ( $key, $label ): array {
			$dictionary['labels'][ $key ] = $label;
			return $dictionary;
		};
	}

	public function test_same_key_does_not_rerun_filter(): void
	{
		$calls = 0;
		add_filter(
			self::FILTER,
			static function ( array $dictionary ) use ( &$calls ): array {
				++$calls;
				return $dictionary;
			}
		);

		ParityLabels::label( 'theme' );
		ParityLabels::label( 'theme' );

		$this->assertSame( 1, $calls );
	}

	public function test_filter_added_after_first_lookup_applies(): void
	{
		$this->assertNull( ParityLabels::label( 'x' ) );

		add_filter( self::FILTER, $this->writer( 'x', 'Added' ) );

		$this->assertSame( 'Added', ParityLabels::label( 'x' ) );
	}

	public function test_filter_removed_after_first_lookup_applies(): void
	{
		$callback = $this->writer( 'x', 'Present' );
		add_filter( self::FILTER, $callback );
		$this->assertSame( 'Present', ParityLabels::label( 'x' ) );

		remove_filter( self::FILTER, $callback );

		$this->assertNull( ParityLabels::label( 'x' ) );
	}

	public function test_same_priority_replacement_applies(): void
	{
		$a = $this->writer( 'x', 'A' );
		add_filter( self::FILTER, $a );
		$this->assertSame( 'A', ParityLabels::label( 'x' ) );

		remove_filter( self::FILTER, $a );
		$b = $this->writer( 'x', 'B' );
		add_filter( self::FILTER, $b );

		$this->assertSame( 'B', ParityLabels::label( 'x' ) );
		// Keep $a alive: a freed closure id could be reused by $b.
		$this->assertNotSame( $a, $b );
	}

	public function test_same_priority_reorder_applies(): void
	{
		$a = $this->writer( 'x', 'A' );
		$b = $this->writer( 'x', 'B' );
		add_filter( self::FILTER, $a );
		add_filter( self::FILTER, $b );
		$this->assertSame( 'B', ParityLabels::label( 'x' ) );

		remove_filter( self::FILTER, $a );
		add_filter( self::FILTER, $a );

		$this->assertSame( 'A', ParityLabels::label( 'x' ) );
		$this->assertNotSame( $a, $b );
	}

	public function test_locale_switch_uses_other_dictionary(): void
	{
		add_filter(
			self::FILTER,
			static function ( array $dictionary ): array {
				$dictionary['labels']['x'] = 'label-' . determine_locale();
				return $dictionary;
			}
		);

		$this->assertSame( 'label-en_US', ParityLabels::label( 'x' ) );

		$this->assertTrue( switch_to_locale( 'de_DE' ) );
		$this->assertSame( 'label-de_DE', ParityLabels::label( 'x' ) );

		restore_previous_locale();
		$this->assertSame( 'label-en_US', ParityLabels::label( 'x' ) );
	}
}
