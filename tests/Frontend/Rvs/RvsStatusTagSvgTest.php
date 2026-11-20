<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Helpers\Html;
use WP_UnitTestCase;

/**
 * The status tag keeps its icon through the shortcode wrapper's wp_kses().
 *
 * WordPress 7.2 parses wp_kses() input with the HTML API. An SVG shape
 * filtered on its own, outside its <svg>, is read as an unknown HTML element:
 * its self-closing slash is ignored and the shape comes back unclosed. The
 * wrapper's wp_kses() then drops the whole <svg>, and in the bookings table
 * the cell after it as well. The tag therefore filters the icon as one piece.
 */
final class RvsStatusTagSvgTest extends WP_UnitTestCase
{
	/** @return array<string,array{string,int}> modifier => shapes in its icon */
	public function modifiers(): array
	{
		return array(
			'confirmed' => array( 'confirmed', 2 ),
			'pending'   => array( 'pending', 2 ),
			'payment'   => array( 'payment', 2 ),
			'active'    => array( 'active', 2 ),
			'done'      => array( 'done', 1 ),
			'cancelled' => array( 'cancelled', 2 ),
		);
	}

	/**
	 * @dataProvider modifiers
	 */
	public function test_the_icon_survives_the_wrapper_kses(string $modifier, int $shapes): void
	{
		$html = (string) Templates::render(
			'partials/rvs/status-tag',
			array( 'status' => array( 'modifier' => $modifier, 'label' => 'Label' ) ),
			true
		);
		// The label and a following cell: what an unclosed shape swallowed.
		$wrapped = wp_kses('<table><tr><td>' . $html . '</td><td><a class="next" href="http://example.org/">View</a></td></tr></table>', Html::allowed_markup());

		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $wrapped . '</body></html>');
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		$xpath = new \DOMXPath($doc);

		$this->assertSame(1, $xpath->query('//span[contains(@class,"rvs-status--' . $modifier . '")]/svg')->length, "$modifier: the icon is gone.");
		$this->assertSame($shapes, $xpath->query('//span[contains(@class,"rvs-status--' . $modifier . '")]/svg/*')->length, "$modifier: the icon lost a shape.");
		$this->assertSame('Label', trim($xpath->query('//span[contains(@class,"rvs-status--' . $modifier . '")]')->item(0)->textContent), "$modifier: the label is gone.");
		$this->assertSame(1, $xpath->query('//td/a[@class="next"]')->length, "$modifier: the cell after the tag is gone.");
	}
}
