<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\AssetManager;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * WP.org T7 named finding: AssetManager's inline `:root { ... }` block
 * interpolated two option values straight into a stylesheet.
 *
 * The shipped guard at the time was `wp_strip_all_tags()` on the finished
 * block. That satisfies WPCS -- the sniff's escaping list contains
 * wp_strip_all_tags() -- while being the wrong instrument entirely: a CSS
 * declaration value is not HTML, and stripping tags does nothing to a `}`
 * that closes the rule and opens an attacker-chosen one. `#fff; } body {
 * background: url(//evil) ` survives wp_strip_all_tags() unchanged.
 *
 * The fix validates each value against the grammar of the declaration it
 * lands in and DROPS anything that does not match, falling back to the
 * default. Escaping is not available here: there is no CSS-context escaper
 * in WordPress, and inventing one would be a second wrong instrument.
 *
 * These tests drive AssetManager::add_inline_styles() -- the shipped path,
 * the one wp_enqueue_scripts/admin_enqueue_scripts actually call -- and read
 * back what wp_add_inline_style() stored, so they measure what reaches the
 * browser rather than what a helper returns.
 */
final class AssetManagerCssTest extends WP_UnitTestCase {

	private const HANDLE = 'mhm-rentiva-css-variables';

	protected function setUp(): void {
		parent::setUp();

		// Precondition of the mechanism, not the guard under test:
		// wp_add_inline_style() silently discards data for an unregistered
		// handle, so without this every assertion would read an empty string
		// and pass vacuously. Registering it makes the CSS observable; the
		// mutation proof in the task report shows every assertion below goes
		// red against the pre-fix get_css_variables() with these lines in place.
		//
		// Deregister first: $wp_styles is a global that survives between test
		// cases, and earlier tests in a full-suite run leave inline chunks on
		// this handle. wp_register_style() is a no-op for an already-registered
		// handle, so without the deregister those stale chunks would be read
		// back here and the "exactly one rule" assertions would count them.
		wp_deregister_style( self::HANDLE );
		wp_register_style( self::HANDLE, false, array(), '1' );
	}

	protected function tearDown(): void {
		delete_option( 'mhmrentiva_primary_color' );
		delete_option( 'mhmrentiva_secondary_color' );
		wp_deregister_style( self::HANDLE );
		parent::tearDown();
	}

	/**
	 * Runs the shipped enqueue path and returns the inline CSS it produced.
	 */
	private function inline_css(): string {
		AssetManager::add_inline_styles();

		$data = wp_styles()->get_data( self::HANDLE, 'after' );

		return is_array( $data ) ? implode( "\n", $data ) : (string) $data;
	}

	public function test_css_variable_values_are_context_validated(): void {
		update_option( 'mhmrentiva_primary_color', '#fff; } body { background: url(//evil) ' );

		$css = $this->inline_css();

		$this->assertStringNotContainsString( 'url(', $css, 'A dropped value must not reintroduce a CSS function.' );
		$this->assertStringNotContainsString( 'evil', $css, 'No fragment of the injected payload may survive.' );
		$this->assertSame(
			1,
			substr_count( $css, '{' ),
			'The emitted CSS must still be exactly one rule -- a value that opens a second block escaped its declaration.'
		);
		$this->assertSame( 1, substr_count( $css, '}' ), 'The emitted CSS must still close exactly one rule.' );
	}

	public function test_a_rejected_value_falls_back_to_the_default(): void {
		update_option( 'mhmrentiva_primary_color', 'red; } body { display: none' );

		$this->assertStringContainsString(
			'--mhm-primary: #2271b1;',
			$this->inline_css(),
			'A rejected value must fall back to the shipped default, not to an empty declaration.'
		);
	}

	/**
	 * Guards the helper's contract against the caller's fallback: '0' is a value
	 * the helper accepts (see accepted_values), so a falsy `?:` test in
	 * get_css_variables() would silently replace it with the default and make the
	 * two disagree.
	 */
	public function test_a_falsy_but_accepted_value_is_not_replaced_by_the_default(): void {
		update_option( 'mhmrentiva_primary_color', '0' );

		$css = $this->inline_css();

		$this->assertStringContainsString( '--mhm-primary: 0;', $css );
		$this->assertStringNotContainsString( '--mhm-primary: #2271b1;', $css );
	}

	public function test_a_legitimate_colour_still_reaches_the_stylesheet(): void {
		update_option( 'mhmrentiva_primary_color', '#ff0000' );
		update_option( 'mhmrentiva_secondary_color', '#0f0' );

		$css = $this->inline_css();

		$this->assertStringContainsString( '--mhm-primary: #ff0000;', $css );
		$this->assertStringContainsString( '--mhm-secondary: #0f0;', $css );
	}

	/**
	 * @dataProvider accepted_values
	 */
	public function test_declaration_values_of_a_known_type_are_accepted( string $value ): void {
		$this->assertSame( $value, $this->sanitize( $value ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function accepted_values(): array {
		return array(
			'six digit hex'   => array( '#2271b1' ),
			'three digit hex' => array( '#abc' ),
			'rgb'             => array( 'rgb(34, 113, 177)' ),
			'rgba'            => array( 'rgba(34, 113, 177, 0.5)' ),
			'unitless number' => array( '0' ),
			'length'          => array( '1.5rem' ),
			'negative length' => array( '-2px' ),
			'percentage'      => array( '50%' ),
			'keyword'         => array( 'sans-serif' ),
		);
	}

	/**
	 * @dataProvider rejected_values
	 */
	public function test_declaration_values_outside_every_known_type_are_dropped( string $value ): void {
		$this->assertSame( '', $this->sanitize( $value ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function rejected_values(): array {
		return array(
			'block escape'      => array( '#fff; } body { background: url(//evil) ' ),
			'declaration break' => array( '#fff; color: red' ),
			'css function'      => array( 'url(//evil)' ),
			'expression'        => array( 'expression(alert(1))' ),
			'empty'             => array( '' ),
			'whitespace only'   => array( '   ' ),
			'comment escape'    => array( '#fff /* } body { */' ),
			'unicode escape'    => array( '\\75 rl(//evil)' ),
			'hex without hash'  => array( '2271b1' ),
			'oversized keyword' => array( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
		);
	}

	private function sanitize( string $value ): string {
		$method = new ReflectionMethod( AssetManager::class, 'sanitize_css_declaration_value' );
		$method->setAccessible( true );

		return (string) $method->invoke( null, $value );
	}
}
