<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Tests\Support\SelectorMatcher;
use WP_UnitTestCase;

/**
 * The matcher behind StyleSelectorsMatchMarkupTest: anything it does not model must
 * throw, or a selector it silently simplifies would count as live.
 */
final class SelectorMatcherTest extends WP_UnitTestCase
{
	private function xpath(): \DOMXPath
	{
		$doc = new \DOMDocument();
		$doc->loadHTML('<html><body><div class="root"><h3 class="target a">x</h3><a class="target">y</a></div></body></html>');

		return new \DOMXPath($doc);
	}

	public function test_supported_syntax_matches(): void
	{
		$xp = $this->xpath();
		$this->assertSame(2, SelectorMatcher::count($xp, '.root .target'));
		$this->assertSame(1, SelectorMatcher::count($xp, '.root h3.target.a'));
		$this->assertSame(1, SelectorMatcher::count($xp, '.root a:hover'));
		$this->assertSame(1, SelectorMatcher::count($xp, '.root a:focus-visible'));
		$this->assertSame(0, SelectorMatcher::count($xp, '.root .missing'));
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function unsupported(): array
	{
		return array(
			'unknown pseudo-class'    => array( '.root .target:made-up' ),
			'structural pseudo-class' => array( '.root .target:first-child' ),
			'pseudo-element'          => array( '.root .target::before' ),
			'child combinator'        => array( '.root > .target' ),
			'attribute'               => array( '.root [data-x]' ),
			'negation'                => array( '.root .target:not(.a)' ),
		);
	}

	/**
	 * @dataProvider unsupported
	 */
	public function test_unsupported_syntax_throws(string $selector): void
	{
		$this->expectException(\InvalidArgumentException::class);
		SelectorMatcher::count($this->xpath(), $selector);
	}
}
