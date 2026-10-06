<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Counts the elements a CSS selector matches in rendered markup.
 *
 * Supports exactly what the widgets' style selectors use: descendant combinator
 * (whitespace), tag names, compound classes (`a.b`, `.a.b`). Pseudo-classes
 * (`:hover`, `:focus` …) are dropped, since a hover rule targets the same element.
 * Anything else (`>`, `+`, `~`, `[attr]`, `#id`, `:not(...)`, `,`) throws, so an
 * unsupported selector can never pass silently.
 */
final class SelectorMatcher
{
	public static function count(\DOMXPath $xp, string $selector): int
	{
		$selector = trim($selector);
		if ('' === $selector || preg_match('/[>+~\[\]#,()*]/', $selector)) {
			throw new \InvalidArgumentException('Unsupported selector syntax: ' . $selector);
		}

		$xpath = '';
		foreach (preg_split('/\s+/', $selector) as $compound) {
			// Drop pseudo-classes and pseudo-elements: `a:hover` → `a`.
			$compound = (string) preg_replace('/::?[a-z-]+$/i', '', $compound);
			if (! preg_match('/^([a-z][a-z0-9-]*)?((?:\.[A-Za-z0-9_-]+)*)$/', $compound, $m) || '' === $compound) {
				throw new \InvalidArgumentException('Unsupported compound selector: ' . $compound . ' in ' . $selector);
			}
			$step = '' !== $m[1] ? $m[1] : '*';
			foreach (array_filter(explode('.', $m[2])) as $class) {
				$step .= "[contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')]";
			}
			$xpath .= '//' . $step;
		}

		$nodes = $xp->query($xpath);
		if (false === $nodes) {
			throw new \InvalidArgumentException('XPath failed for selector: ' . $selector);
		}

		return $nodes->length;
	}
}
