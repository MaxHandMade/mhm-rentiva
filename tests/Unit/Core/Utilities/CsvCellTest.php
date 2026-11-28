<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\CsvCell;
use PHPUnit\Framework\TestCase;

/**
 * @group export
 */
final class CsvCellTest extends TestCase {

	/**
	 * @dataProvider leading_control_chars
	 */
	public function test_leading_tab_cr_lf_are_guarded( string $value ): void {
		$this->assertSame( "'" . $value, CsvCell::guard( $value ) );
	}

	public static function leading_control_chars(): array {
		return array(
			'tab' => array( "\tAli" ),
			'cr'  => array( "\rAli" ),
			'lf'  => array( "\nAli" ),
		);
	}

	public function test_formula_is_prefixed(): void {
		$this->assertSame( "'=HYPERLINK(\"x\")", CsvCell::guard( '=HYPERLINK("x")' ) );
	}

	public function test_leading_space_then_trigger(): void {
		$this->assertSame( " '=1", CsvCell::guard( ' =1' ) );
	}

	public function test_fullwidth_trigger(): void {
		$this->assertSame( "'＝1", CsvCell::guard( '＝1' ) );
		$this->assertSame( "'＋1", CsvCell::guard( '＋1' ) );
		$this->assertSame( "'－1", CsvCell::guard( '－1' ) );
		$this->assertSame( "'＠1", CsvCell::guard( '＠1' ) );
	}

	public function test_numeric_passes_through(): void {
		$this->assertSame( '-5.00', CsvCell::guard( '-5.00', true ) );
		$this->assertSame( '42', CsvCell::guard( '42', true ) );
	}

	public function test_name_starting_with_minus(): void {
		$this->assertSame( "'-Ali", CsvCell::guard( '-Ali' ) );
	}

	public function test_phone_plus(): void {
		$this->assertSame( "'+90 555", CsvCell::guard( '+90 555' ) );
	}

	public function test_separator_hop(): void {
		$this->assertSame( "Ali;'=1+1", CsvCell::guard( 'Ali;=1+1' ) );
		$this->assertSame( "123;\t'@SUM(A1)", CsvCell::guard( "123;\t@SUM(A1)" ) );
		$this->assertSame( "a,'=1", CsvCell::guard( 'a,=1' ) );
		$this->assertSame( "a|'=1", CsvCell::guard( 'a|=1' ) );
		$this->assertSame( "a\t'=1", CsvCell::guard( "a\t=1" ) );
	}

	/**
	 * @dataProvider invisible_prefixes
	 */
	public function test_invisible_prefixes( string $value, string $expected ): void {
		$this->assertSame( $expected, CsvCell::guard( $value ) );
	}

	public static function invisible_prefixes(): array {
		return array(
			'space tab'  => array( " \t=1", " \t'=1" ),
			'bom'        => array( "\u{FEFF}=1", "\u{FEFF}'=1" ),
			'zero width' => array( "\u{200B}=1", "\u{200B}'=1" ),
			'nbsp'       => array( "\u{00A0}=1", "\u{00A0}'=1" ),
			'vt'         => array( "\x0B=1", "\x0B'=1" ),
		);
	}

	public function test_plain_text_unchanged(): void {
		$this->assertSame( 'Ali Veli', CsvCell::guard( 'Ali Veli' ) );
		$this->assertSame( 'ali@x.com', CsvCell::guard( 'ali@x.com' ) );
		$this->assertSame( '', CsvCell::guard( '' ) );
	}

	public function test_invalid_utf8_is_still_guarded(): void {
		$this->assertStringStartsWith( "'", CsvCell::guard( "\xC3=1" ) );
		$this->assertSame( "'\xC3;'=1", CsvCell::guard( "\xC3;=1" ) );
	}

	public function test_leading_control_char_does_not_skip_the_separator_rule(): void {
		$this->assertSame( "'\tAli;'=1+1", CsvCell::guard( "\tAli;=1+1" ) );
		$this->assertSame( "'\nx,'@SUM(A1)", CsvCell::guard( "\nx,@SUM(A1)" ) );
	}

	public function test_null_and_numbers(): void {
		$this->assertSame( '', CsvCell::guard( null ) );
		$this->assertSame( '12', CsvCell::guard( 12 ) );
	}

	public function test_numeric_flag_is_fail_safe(): void {
		$this->assertSame( "'=1", CsvCell::guard( '=1', true ) );
		$this->assertSame( '-5.00', CsvCell::guard( '-5.00', true ) );
		$this->assertSame( "'-5.00x", CsvCell::guard( '-5.00x', true ) );
	}

	public function test_row_applies_numeric_keys_only_to_listed_cells(): void {
		$this->assertSame(
			array( '7', '-5.00', "'=1", "'-5.00" ),
			CsvCell::row( array( 7, '-5.00', '=1', '-5.00' ), array( 0, 1 ) )
		);
	}

	public function test_line_break_is_a_hop_point(): void {
		$this->assertSame( "a\n'=1", CsvCell::guard( "a\n=1" ) );
		$this->assertSame( "a\r\n'@x", CsvCell::guard( "a\r\n@x" ) );
		$this->assertSame( "a\n \t'=1", CsvCell::guard( "a\n \t=1" ) );
		$this->assertSame( "abc\n'=HYPERLINK(\"x\")", CsvCell::guard( "abc\n=HYPERLINK(\"x\")" ) );
	}

	public function test_line_break_hop_in_the_byte_fallback(): void {
		$this->assertSame( "'\xC3\n'=1", CsvCell::guard( "\xC3\n=1" ) );
	}
}
