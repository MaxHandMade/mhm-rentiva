<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Helpers\Html;
use WP_UnitTestCase;

final class CanonicalHtmlTest extends WP_UnitTestCase
{
	/** @return array<string,array{string,string}> */
	public function cases(): array
	{
		return array(
			'attributes sorted, names lower-cased' => array(
				'<SPAN class="a" Aria-Hidden="true" data-x=\'1\'>t</SPAN>',
				'<span aria-hidden="true" class="a" data-x="1">t</span>',
			),
			'boolean attribute stays bare'        => array(
				'<article data-rvs-card class="c" hidden>x</article>',
				'<article class="c" data-rvs-card hidden>x</article>',
			),
			'boolean attributes written bare'     => array(
				'<input type="radio" checked="checked" disabled=""><option selected="SELECTED" value="value">v</option>',
				'<input checked disabled type="radio"><option selected value="value">v</option>',
			),
			'character references one way'        => array(
				'<a href="/?a=1&#038;b=2" title="&quot;q&quot;">&#038; &amp; &quot;</a>',
				'<a href="/?a=1&amp;b=2" title="&quot;q&quot;">&amp; &amp; "</a>',
			),
			'svg shape keeps its slash'           => array(
				'<svg viewBox="0 0 24 24"><path d="M5 12"/><circle cx="1" cy="1" r="1"></circle></svg>',
				'<svg viewbox="0 0 24 24"><path d="M5 12" /><circle cx="1" cy="1" r="1"></circle></svg>',
			),
			'void elements drop the slash'        => array(
				'<img src="a.jpg" alt="" /><br/><input type="hidden" name="n" value="1" />',
				'<img alt="" src="a.jpg"><br><input name="n" type="hidden" value="1">',
			),
			'script and style content untouched'  => array(
				'<style>a>b{c:"&amp;"}</style><script type="x">if (a < b && c) {}</script>',
				'<style>a>b{c:"&amp;"}</style><script type="x">if (a < b && c) {}</script>',
			),
			'textarea and title text re-encoded'  => array(
				'<textarea name="n">a &amp; <b></textarea><title>x &#038; y</title>',
				'<textarea name="n">a &amp; &lt;b&gt;</textarea><title>x &amp; y</title>',
			),
			'comments and doctype kept'           => array(
				'<!DOCTYPE html><!-- Messages Content --><p>p</p>',
				'<!DOCTYPE html><!-- Messages Content --><p>p</p>',
			),
			'whitespace kept'                     => array(
				"<div>\n\t<p>a  b</p>\n</div>",
				"<div>\n\t<p>a  b</p>\n</div>",
			),
		);
	}

	/**
	 * @dataProvider cases
	 */
	public function test_canonical_form(string $html, string $expected): void
	{
		$this->assertSame($expected, CanonicalHtml::of($html));
	}

	/**
	 * @dataProvider cases
	 */
	public function test_canonical_form_is_a_fixed_point(string $html): void
	{
		$once = CanonicalHtml::of($html);
		$this->assertSame($once, CanonicalHtml::of($once));
	}

	public function test_count_tags_matches_by_attributes_in_any_order(): void
	{
		$html = '<button class="rvs-chip" data-rvs-chip type="button" data-value="diesel">a</button>'
			. '<button type="button" data-value="petrol" class="rvs-chip" data-rvs-chip>b</button>'
			. '<a class="rvs-chip" data-value="diesel">c</a>';

		$this->assertSame(1, CanonicalHtml::count_tags($html, '<button type="button" class="rvs-chip" data-rvs-chip data-value="diesel">'));
		$this->assertSame(2, CanonicalHtml::count_tags($html, '<button class="rvs-chip">'), 'Other attributes are allowed.');
		$this->assertSame(0, CanonicalHtml::count_tags($html, '<button class="rvs-chip" data-value="lpg">'), 'A value must match.');
		$this->assertSame(0, CanonicalHtml::count_tags($html, '<button class="rvs-chip" hidden>'), 'A named attribute must be there.');
		$this->assertSame(1, CanonicalHtml::count_tags($html, '<a data-value="diesel">'), 'The tag name must match.');
	}

	public function test_a_truncated_tail_is_kept_verbatim(): void
	{
		$this->assertSame('<p>a</p><div class="x', CanonicalHtml::of('<p>a</p><div class="x'));
	}

	/**
	 * The point of the helper: both wp_kses() parsers of WordPress 7.2 print
	 * one canonical form. Runs only where both exist.
	 */
	public function test_both_kses_parsers_meet_in_one_canonical_form(): void
	{
		if (! function_exists('wp_sanitize_html_kses')) {
			$this->markTestSkipped('This WordPress has one wp_kses() parser.');
		}

		$html = '<div class="rvs-card" data-id="7" data-rvs-card><a href="/?a=1&b=2" aria-hidden="true" class="m">Fiat &amp; Co</a>'
			. '<button type="button" class="btn">Go</button><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12"/></svg>'
			. '<input type="hidden" name="t" value="a&quot;b" /><img src="/a.jpg" alt="x" /><p>&ldquo;Q&rdquo; &amp; &#036;5</p>'
			. '<input type="radio" name="r" value="1" checked="checked"></div>';

		add_filter('wp_kses_force_legacy_parser', '__return_true');
		$legacy = wp_kses($html, Html::allowed_markup());
		remove_filter('wp_kses_force_legacy_parser', '__return_true');
		$current = wp_kses($html, Html::allowed_markup());

		$this->assertNotSame($legacy, $current, 'PREMISE: the two parsers print different bytes.');
		$this->assertSame(CanonicalHtml::of($legacy), CanonicalHtml::of($current));
	}
}
