<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * One spelling for markup that two wp_kses() parsers print differently.
 *
 * WordPress 7.2 reimplements wp_kses() on the HTML API: attributes come out
 * in another order and character references in another form (&#038; and
 * &amp;, a decoded &quot; in text) than the parser before it printed. The
 * markup is the same; a byte-exact comparison is not. This rewrites a
 * fragment through WP_HTML_Tag_Processor -- tag and attribute names in lower
 * case, attributes sorted by name, values and text decoded and re-encoded
 * one way -- so a golden, a regex or a substring reads the same bytes under
 * either parser. Nothing is added or dropped: a lost tag, attribute or word
 * still shows.
 */
final class CanonicalHtml
{
	/** Elements whose content the tokenizer hands over as one raw text. */
	private const RAW_TEXT = array( 'SCRIPT', 'STYLE', 'XMP', 'IFRAME', 'NOEMBED', 'NOFRAMES' );

	/** Elements whose content is text with character references, not markup. */
	private const ESCAPABLE_RAW_TEXT = array( 'TEXTAREA', 'TITLE' );

	/**
	 * Void elements: a trailing slash means nothing on them, and the two
	 * parsers disagree on printing it (<img ... /> and <img ...>). On any other
	 * element -- an SVG shape -- the slash closes it and is kept.
	 */
	private const VOID = array( 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'SOURCE', 'TRACK', 'WBR' );

	/**
	 * HTML boolean attributes: present or absent, the value means nothing.
	 * One parser prints checked="checked", the other checked; both are written
	 * bare. Any other attribute keeps its value, even one equal to its name.
	 */
	private const BOOLEAN = array(
		'allowfullscreen', 'async', 'autofocus', 'autoplay', 'checked', 'controls', 'default', 'defer', 'disabled',
		'formnovalidate', 'hidden', 'inert', 'ismap', 'itemscope', 'loop', 'multiple', 'muted', 'nomodule',
		'novalidate', 'open', 'playsinline', 'readonly', 'required', 'reversed', 'selected',
	);

	public static function of(string $html): string
	{
		$processor = new \WP_HTML_Tag_Processor($html);
		$out       = '';

		while ($processor->next_token()) {
			switch ($processor->get_token_type()) {
				case '#tag':
					$out .= self::tag($processor);
					break;
				case '#text':
					$out .= self::text($processor->get_modifiable_text());
					break;
				case '#comment':
				case '#funky-comment':
					$out .= '<!--' . $processor->get_modifiable_text() . '-->';
					break;
				case '#cdata-section':
					$out .= '<![CDATA[' . $processor->get_modifiable_text() . ']]>';
					break;
				case '#doctype':
					$out .= '<!DOCTYPE ' . trim((string) $processor->get_modifiable_text()) . '>';
					break;
				default:
					// #presumptuous-tag ("</>") prints nothing in a browser.
					break;
			}
		}

		if ($processor->paused_at_incomplete_token()) {
			// A truncated tail (an unterminated tag or comment) stays verbatim
			// rather than silently vanishing from the comparison.
			// The incomplete token starts at the input's last '<'.
			$last = strrpos($html, '<');
			$out .= false === $last ? '' : substr($html, $last);
		}

		return $out;
	}

	/**
	 * How many start tags in $html are a $needle: same tag name, and every
	 * attribute $needle names present with the same (decoded) value -- in any
	 * order, other attributes allowed. A bare attribute in $needle (data-x)
	 * asks only that the attribute is there.
	 *
	 * The order-free form of asserting a tag's source text, e.g.
	 * count_tags($html, '<input type="hidden" name="n" value="1">').
	 */
	public static function count_tags(string $html, string $needle): int
	{
		$want = new \WP_HTML_Tag_Processor($needle);
		if (! $want->next_tag()) {
			throw new \InvalidArgumentException('The needle has no start tag: ' . $needle);
		}
		$name       = (string) $want->get_tag();
		$attributes = array();
		foreach ((array) $want->get_attribute_names_with_prefix('') as $attribute) {
			$attributes[ $attribute ] = $want->get_attribute($attribute);
		}

		$count     = 0;
		$processor = new \WP_HTML_Tag_Processor($html);
		while ($processor->next_tag(array( 'tag_name' => $name ))) {
			foreach ($attributes as $attribute => $value) {
				$actual = $processor->get_attribute($attribute);
				if (null === $actual || ( true !== $value && $actual !== $value )) {
					continue 2;
				}
			}
			++$count;
		}
		return $count;
	}

	private static function tag(\WP_HTML_Tag_Processor $processor): string
	{
		$name = strtolower((string) $processor->get_tag());
		if ($processor->is_tag_closer()) {
			return '</' . $name . '>';
		}

		$names = (array) $processor->get_attribute_names_with_prefix('');
		sort($names, SORT_STRING);

		$out = '<' . $name;
		foreach ($names as $attribute) {
			$value = $processor->get_attribute($attribute);
			$bare  = true === $value || ( in_array($attribute, self::BOOLEAN, true) && in_array(strtolower((string) $value), array( '', $attribute ), true) );
			$out  .= $bare ? ' ' . $attribute : ' ' . $attribute . '="' . self::attribute((string) $value) . '"';
		}
		$upper = strtoupper($name);
		$out  .= $processor->has_self_closing_flag() && ! in_array($upper, self::VOID, true) ? ' />' : '>';

		if (in_array($upper, self::RAW_TEXT, true)) {
			$out .= $processor->get_modifiable_text() . '</' . $name . '>';
		} elseif (in_array($upper, self::ESCAPABLE_RAW_TEXT, true)) {
			$out .= self::text($processor->get_modifiable_text()) . '</' . $name . '>';
		}

		return $out;
	}

	private static function text(string $text): string
	{
		return str_replace(array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $text);
	}

	private static function attribute(string $value): string
	{
		return str_replace(array( '&', '<', '>', '"' ), array( '&amp;', '&lt;', '&gt;', '&quot;' ), $value);
	}
}
