<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Finds every attribute key a block editor script writes, and refuses any write
 * whose key it cannot read off the source.
 *
 * Threat model (spec 2026-10-06-gutenberg-onarim-dilim-1 §3 T4): this catches a
 * developer *accidentally* writing an attribute block.json does not declare --
 * which the editor silently drops on save and the block-renderer preview rejects
 * with HTTP 400. It is not a defence against deliberately obfuscated code; a
 * static scanner cannot be one. Shared by the Lite and Pro suites.
 *
 * Every occurrence of the identifier `setAttributes` must be one of:
 *   a call whose single argument is an object literal with plain or quoted keys;
 *   `var setAttributes = props.setAttributes` or `var { setAttributes } = props`;
 *   the bare 4th argument of a `yesNoToggle(...)` call;
 *   inside the Pro `yesNoToggle` helper, when the helper body matches
 *   HELPER_FINGERPRINT exactly (its parameter and its `setAttributes(update)`).
 * Anything else is a violation.
 */
final class BlockEditorWriteScanner
{
	/**
	 * Whitespace-normalized body of the Pro helper as of 2026-10-06
	 * (vendor-directory/index.js:16-26, vendor-profile/index.js:12-22 -- identical).
	 * Any edit to the helper must update this string, deliberately.
	 */
	public const HELPER_FINGERPRINT = "function (label, key, attributes, setAttributes) { return el(ToggleControl, { label: label, checked: attributes[key] === 'yes', onChange: function (val) { var update = {}; update[key] = val ? 'yes' : 'no'; setAttributes(update); } }); }";

	/**
	 * @return array{literal_keys:string[],helper_keys:string[],exemptions:int,violations:string[]}
	 */
	public static function scan(string $js, string $file_label): array
	{
		$tokens     = self::tokenize($js);
		$classified = array();
		$result     = array(
			'literal_keys' => array(),
			'helper_keys'  => array(),
			'exemptions'   => 0,
			'violations'   => array(),
		);

		self::classify_helper($js, $tokens, $classified, $result, $file_label);
		self::classify_toggle_calls($tokens, $classified, $result, $file_label);

		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			if (! self::is($tokens, $i, 'id', 'setAttributes') || isset($classified[ $i ])) {
				continue;
			}

			// `var setAttributes = props.setAttributes` -- the declaration ...
			if (self::is($tokens, $i - 1, 'id', 'var') || self::is($tokens, $i - 1, 'id', 'let') || self::is($tokens, $i - 1, 'id', 'const')) {
				if (self::is($tokens, $i + 1, 'p', '=') && self::is($tokens, $i + 2, 'id', 'props') && self::is($tokens, $i + 3, 'p', '.')
					&& self::is($tokens, $i + 4, 'id', 'setAttributes') && self::is($tokens, $i + 5, 'p', ';')
				) {
					$classified[ $i + 4 ] = true;
					continue;
				}
			}
			// ... or `var { ..., setAttributes, ... } = props`. Any other read of
			// `props.setAttributes` (aliasing it, passing it on) is unclassified.
			if (self::is_destructured_from_props($tokens, $i)) {
				continue;
			}

			if (self::is($tokens, $i + 1, 'p', '(') && self::is($tokens, $i + 2, 'p', '{')) {
				self::read_object_literal($tokens, $i + 2, $result, $file_label);
				continue;
			}

			$result['violations'][] = sprintf('%s: unclassified use of setAttributes at offset %d', $file_label, $tokens[ $i ][2]);
		}

		return $result;
	}

	/** @return string[] */
	public static function block_attributes(string $block_json_path): array
	{
		$json = json_decode((string) file_get_contents($block_json_path), true);
		return array_keys((array) ( $json['attributes'] ?? array() ));
	}

	/**
	 * @param array{literal_keys:string[],helper_keys:string[]} $scan
	 * @return string[] Written keys the block.json does not declare.
	 */
	public static function unregistered(array $scan, string $block_json_path): array
	{
		$declared = self::block_attributes($block_json_path);
		$written  = array_unique(array_merge($scan['literal_keys'], $scan['helper_keys']));
		return array_values(array_diff($written, $declared));
	}

	/**
	 * Keys of the object literal opening at $open; flags computed, spread and
	 * shorthand properties, and a call with more than that one argument.
	 */
	private static function read_object_literal(array $tokens, int $open, array &$result, string $label): void
	{
		$close = self::matching($tokens, $open);
		if (null === $close || ! self::is($tokens, $close + 1, 'p', ')')) {
			$result['violations'][] = sprintf('%s: setAttributes argument is not a single object literal at offset %d', $label, $tokens[ $open ][2]);
			return;
		}

		$i = $open + 1;
		while ($i < $close) {
			$t = $tokens[ $i ];
			if ('p' === $t[0] && '[' === $t[1]) {
				$result['violations'][] = sprintf('%s: computed key in setAttributes at offset %d', $label, $t[2]);
			} elseif ('p' === $t[0] && '.' === $t[1]) {
				$result['violations'][] = sprintf('%s: spread in setAttributes at offset %d', $label, $t[2]);
			} elseif (( 'id' === $t[0] || 'str' === $t[0] ) && self::is($tokens, $i + 1, 'p', ':')) {
				$result['literal_keys'][] = $t[1];
			} else {
				$result['violations'][] = sprintf('%s: unreadable property in setAttributes at offset %d', $label, $t[2]);
			}

			// Skip to the next top-level comma of this literal.
			$depth = 0;
			for (; $i < $close; $i++) {
				$v = $tokens[ $i ];
				if ('p' === $v[0] && in_array($v[1], array( '(', '[', '{' ), true)) {
					++$depth;
				} elseif ('p' === $v[0] && in_array($v[1], array( ')', ']', '}' ), true)) {
					--$depth;
				} elseif (0 === $depth && 'p' === $v[0] && ',' === $v[1]) {
					++$i;
					break;
				}
			}
		}
	}

	/**
	 * Every `yesNoToggle(` call: 2nd argument a single string literal (the key),
	 * 4th argument the bare identifier `setAttributes`.
	 */
	private static function classify_toggle_calls(array $tokens, array &$classified, array &$result, string $label): void
	{
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			if (! self::is($tokens, $i, 'id', 'yesNoToggle') || ! self::is($tokens, $i + 1, 'p', '(')) {
				continue;
			}
			$close = self::matching($tokens, $i + 1);
			$args  = null === $close ? array() : self::split_args($tokens, $i + 2, $close);

			$key_ok    = isset($args[1]) && 1 === count($args[1]) && 'str' === $tokens[ $args[1][0] ][0];
			$setter_ok = isset($args[3]) && 1 === count($args[3]) && self::is($tokens, $args[3][0], 'id', 'setAttributes');

			if ($key_ok) {
				$result['helper_keys'][] = $tokens[ $args[1][0] ][1];
			}
			if ($setter_ok) {
				$classified[ $args[3][0] ] = true;
			}
			if (! $key_ok || ! $setter_ok || 4 !== count($args)) {
				$result['violations'][] = sprintf('%s: yesNoToggle call is not (label, \'key\', attributes, setAttributes) at offset %d', $label, $tokens[ $i ][2]);
			}
		}
	}

	/**
	 * The helper definition `var yesNoToggle = function (...) { ... }`: exempt its
	 * own setAttributes uses only when its body is byte-for-byte (whitespace-
	 * normalized) the fingerprinted one.
	 */
	private static function classify_helper(string $js, array $tokens, array &$classified, array &$result, string $label): void
	{
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			if (! ( self::is($tokens, $i, 'id', 'yesNoToggle') && self::is($tokens, $i + 1, 'p', '=') && self::is($tokens, $i + 2, 'id', 'function') )) {
				continue;
			}
			$params_open = $i + 3;
			if (! self::is($tokens, $params_open, 'p', '(')) {
				continue;
			}
			$params_close = self::matching($tokens, $params_open);
			$body_open    = null === $params_close ? null : $params_close + 1;
			$body_close   = ( null !== $body_open && self::is($tokens, $body_open, 'p', '{') ) ? self::matching($tokens, $body_open) : null;
			if (null === $body_close) {
				$result['violations'][] = sprintf('%s: unparsable yesNoToggle helper', $label);
				continue;
			}

			$start = $tokens[ $i + 2 ][2];
			$end   = $tokens[ $body_close ][2] + 1;
			$text  = (string) preg_replace('/\s+/', ' ', trim(substr($js, $start, $end - $start)));
			if (self::HELPER_FINGERPRINT !== $text) {
				$result['violations'][] = sprintf('%s: yesNoToggle helper body differs from the fingerprinted one', $label);
				continue;
			}

			for ($j = $params_open; $j <= $body_close; $j++) {
				if (self::is($tokens, $j, 'id', 'setAttributes')) {
					$classified[ $j ] = true;
				}
			}
			++$result['exemptions'];
		}
	}

	/**
	 * Token index lists of the top-level, comma-separated arguments in ($from, $to).
	 *
	 * @return array<int,int[]>
	 */
	private static function split_args(array $tokens, int $from, int $to): array
	{
		$args    = array();
		$current = array();
		$depth   = 0;
		for ($i = $from; $i < $to; $i++) {
			$t = $tokens[ $i ];
			if ('p' === $t[0] && in_array($t[1], array( '(', '[', '{' ), true)) {
				++$depth;
			} elseif ('p' === $t[0] && in_array($t[1], array( ')', ']', '}' ), true)) {
				--$depth;
			} elseif (0 === $depth && 'p' === $t[0] && ',' === $t[1]) {
				$args[]  = $current;
				$current = array();
				continue;
			}
			$current[] = $i;
		}
		if (array() !== $current) {
			$args[] = $current;
		}
		return $args;
	}

	/** `var|let|const { a, setAttributes, b } = props` with $i on `setAttributes`. */
	private static function is_destructured_from_props(array $tokens, int $i): bool
	{
		$open = $i;
		while ($open > 0 && ( self::is($tokens, $open - 1, 'id', $tokens[ $open - 1 ][1]) || self::is($tokens, $open - 1, 'p', ',') )) {
			--$open;
		}
		if (! self::is($tokens, $open - 1, 'p', '{')) {
			return false;
		}
		$declarer = $tokens[ $open - 2 ] ?? null;
		if (null === $declarer || 'id' !== $declarer[0] || ! in_array($declarer[1], array( 'var', 'let', 'const' ), true)) {
			return false;
		}

		$close = $i;
		$count = count($tokens);
		while ($close + 1 < $count && ( 'id' === $tokens[ $close + 1 ][0] || self::is($tokens, $close + 1, 'p', ',') )) {
			++$close;
		}
		return self::is($tokens, $close + 1, 'p', '}') && self::is($tokens, $close + 2, 'p', '=') && self::is($tokens, $close + 3, 'id', 'props');
	}

	private static function matching(array $tokens, int $open): ?int
	{
		$pairs = array( '(' => ')', '[' => ']', '{' => '}' );
		$want  = $pairs[ $tokens[ $open ][1] ] ?? null;
		$depth = 0;
		$count = count($tokens);
		for ($i = $open; $i < $count; $i++) {
			$t = $tokens[ $i ];
			if ('p' !== $t[0]) {
				continue;
			}
			if (isset($pairs[ $t[1] ])) {
				++$depth;
			} elseif (in_array($t[1], $pairs, true)) {
				--$depth;
				if (0 === $depth) {
					return $t[1] === $want ? $i : null;
				}
			}
		}
		return null;
	}

	private static function is(array $tokens, int $i, string $type, string $value): bool
	{
		return isset($tokens[ $i ]) && $tokens[ $i ][0] === $type && $tokens[ $i ][1] === $value;
	}

	/**
	 * Minimal JavaScript tokenizer: identifiers, numbers, string literals (any
	 * quote; value unescaped minimally), single-char punctuation; comments and
	 * whitespace dropped. Enough for ES5 editor scripts without a build step.
	 *
	 * @return array<int,array{0:string,1:string,2:int}> [type, value, byte offset]
	 */
	private static function tokenize(string $js): array
	{
		$tokens = array();
		$len    = strlen($js);
		$i      = 0;
		while ($i < $len) {
			$c = $js[ $i ];
			if (ctype_space($c)) {
				++$i;
				continue;
			}
			if ('/' === $c && '/' === ( $js[ $i + 1 ] ?? '' )) {
				$nl = strpos($js, "\n", $i);
				$i  = false === $nl ? $len : $nl;
				continue;
			}
			if ('/' === $c && '*' === ( $js[ $i + 1 ] ?? '' )) {
				$end = strpos($js, '*/', $i + 2);
				$i   = false === $end ? $len : $end + 2;
				continue;
			}
			if ('"' === $c || "'" === $c || '`' === $c) {
				$start = $i;
				$value = '';
				++$i;
				while ($i < $len && $js[ $i ] !== $c) {
					if ('\\' === $js[ $i ] && $i + 1 < $len) {
						$value .= $js[ $i + 1 ];
						$i     += 2;
						continue;
					}
					$value .= $js[ $i ];
					++$i;
				}
				++$i;
				$tokens[] = array( 'str', $value, $start );
				continue;
			}
			if (ctype_alpha($c) || '_' === $c || '$' === $c) {
				$start = $i;
				while ($i < $len && ( ctype_alnum($js[ $i ]) || '_' === $js[ $i ] || '$' === $js[ $i ] )) {
					++$i;
				}
				$tokens[] = array( 'id', substr($js, $start, $i - $start), $start );
				continue;
			}
			if (ctype_digit($c)) {
				$start = $i;
				while ($i < $len && ( ctype_alnum($js[ $i ]) || '.' === $js[ $i ] )) {
					++$i;
				}
				$tokens[] = array( 'num', substr($js, $start, $i - $start), $start );
				continue;
			}
			$tokens[] = array( 'p', $c, $i );
			++$i;
		}
		return $tokens;
	}
}
