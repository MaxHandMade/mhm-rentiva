<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout;

use WP_UnitTestCase;

/**
 * Every post and post meta write in the layout import, rollback and audit code
 * hands WordPress a slashed value.
 *
 * wp_insert_post(), wp_update_post() and update_post_meta() unslash their
 * input; the manifest these classes write comes from wp_json_file_decode() and
 * carries raw backslashes. A writer added later without wp_slash() would open
 * the class again (LayoutBackslashTest measures the behaviour, this test keeps
 * the rule). It reads real calls from the token stream -- comments do not
 * count -- and checks the argument that carries the value: the first for the
 * two post functions, the third for update_post_meta().
 *
 * @coversNothing
 */
final class LayoutWritersAreSlashedTest extends WP_UnitTestCase
{
	private const FILES = array(
		'src/Layout/Ingestion/AtomicImporter.php'         => 13,
		'src/Layout/Versioning/LayoutRollbackService.php' => 8,
		'src/Layout/Observability/LayoutAuditService.php' => 1,
	);

	/** Function => index of the argument that carries the written value. */
	private const WRITERS = array(
		'wp_insert_post'   => 0,
		'wp_update_post'   => 0,
		'update_post_meta' => 2,
	);

	private static function source(string $relative): string
	{
		$source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
		self::assertIsString($source);

		return $source;
	}

	/**
	 * Every writer call: its line, whether its value argument starts with
	 * wp_slash(, and the token index of that wp_slash (or -1).
	 *
	 * @return list<array{function: string, line: int, slashed: bool, slash_at: int}>
	 */
	private static function calls(string $source): array
	{
		$tokens = token_get_all($source);
		$count  = count($tokens);
		$calls  = array();

		for ($i = 0; $i < $count; $i++) {
			if (! is_array($tokens[ $i ]) || ! in_array($tokens[ $i ][0], array( T_STRING, T_NAME_FULLY_QUALIFIED ), true)) {
				continue;
			}
			// PHP function names are case-insensitive.
			$name = strtolower(ltrim($tokens[ $i ][1], '\\'));
			if (! isset(self::WRITERS[ $name ])) {
				continue;
			}

			$prev = self::significant($tokens, $i, -1);
			// `function &wp_update_post(` declares by reference.
			// PHP 8.1+ tokenizes that `&` as T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG.
			if (null !== $prev && ( '&' === $tokens[ $prev ] || ( is_array($tokens[ $prev ]) && '&' === $tokens[ $prev ][1] ) )) {
				$prev = self::significant($tokens, $prev, -1);
			}
			if (null !== $prev && is_array($tokens[ $prev ]) && in_array($tokens[ $prev ][0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW ), true)) {
				continue;
			}
			$open = self::significant($tokens, $i, 1);
			if (null === $open || '(' !== $tokens[ $open ]) {
				continue;
			}

			// Walk to the start of the value argument.
			$want  = self::WRITERS[ $name ];
			$arg   = 0;
			$depth = 0;
			$start = null;
			for ($j = $open + 1; $j < $count; $j++) {
				$t = $tokens[ $j ];
				if (0 === $depth && $arg === $want && null === $start && ! ( is_array($t) && in_array($t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true) )) {
					$start = $j;
				}
				if (in_array($t, array( '(', '[', '{' ), true) || ( is_array($t) && in_array($t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true) )) {
					++$depth;
				} elseif (in_array($t, array( ')', ']', '}' ), true)) {
					if (0 === $depth) {
						break;
					}
					--$depth;
				} elseif (',' === $t && 0 === $depth) {
					++$arg;
				}
			}

			$slashed = false;
			$at      = -1;
			if (null !== $start && is_array($tokens[ $start ]) && 'wp_slash' === strtolower(ltrim($tokens[ $start ][1], '\\'))) {
				$after   = self::significant($tokens, $start, 1);
				$slashed = null !== $after && '(' === $tokens[ $after ] && self::wraps_whole_argument($tokens, $after);
				$at      = $slashed ? $start : -1;
			}

			$calls[] = array(
				'function' => $name,
				'line'     => (int) $tokens[ $i ][2],
				'slashed'  => $slashed,
				'slash_at' => $at,
			);
		}

		return $calls;
	}

	/**
	 * Whether the wp_slash( opening at $open closes at the end of the argument:
	 * `wp_slash($a) + $b` slashes only part of the value.
	 *
	 * @param array<int, mixed> $tokens
	 */
	private static function wraps_whole_argument(array $tokens, int $open): bool
	{
		$depth = 0;
		$count = count($tokens);
		for ($j = $open; $j < $count; $j++) {
			$t = $tokens[ $j ];
			if (in_array($t, array( '(', '[', '{' ), true) || ( is_array($t) && in_array($t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true) )) {
				++$depth;
			} elseif (in_array($t, array( ')', ']', '}' ), true)) {
				--$depth;
				if (0 === $depth) {
					$next = self::significant($tokens, $j, 1);
					return null !== $next && in_array($tokens[ $next ], array( ',', ')' ), true);
				}
			}
		}

		return false;
	}

	/**
	 * @param array<int, mixed> $tokens
	 */
	private static function significant(array $tokens, int $from, int $step): ?int
	{
		for ($k = $from + $step; $k >= 0 && $k < count($tokens); $k += $step) {
			if (is_array($tokens[ $k ]) && in_array($tokens[ $k ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true)) {
				continue;
			}
			return $k;
		}

		return null;
	}

	/** The source with the token at $index replaced: wp_slash( becomes a bare (. */
	private static function without_slash_at(string $source, int $index): string
	{
		$out = '';
		foreach (token_get_all($source) as $k => $t) {
			if ($k === $index) {
				continue;
			}
			$out .= is_array($t) ? $t[1] : $t;
		}

		return $out;
	}

	public function test_every_writer_slashes_its_value(): void
	{
		foreach (self::FILES as $file => $expected) {
			$calls = self::calls(self::source($file));

			// The inventory is fixed, so an empty match cannot pass.
			$this->assertCount($expected, $calls, "$file: writer calls found");

			$unslashed = array_filter($calls, static fn(array $c): bool => ! $c['slashed']);
			$this->assertSame(
				array(),
				array_values(array_map(static fn(array $c): string => $c['function'] . ':' . $c['line'], $unslashed)),
				"$file: writers that pass an unslashed value"
			);
		}
	}

	public function test_removing_any_one_wp_slash_is_caught(): void
	{
		$cases = 0;
		foreach (array_keys(self::FILES) as $file) {
			$source = self::source($file);
			foreach (self::calls($source) as $call) {
				// A call with nothing to remove would pass this loop silently.
				$this->assertNotSame(-1, $call['slash_at'], "$file: {$call['function']}:{$call['line']} has a wp_slash to remove");
				$mutated   = self::without_slash_at($source, $call['slash_at']);
				$unslashed = array_filter(self::calls($mutated), static fn(array $c): bool => ! $c['slashed']);

				$this->assertCount(1, $unslashed, "$file: {$call['function']}:{$call['line']} without wp_slash is reported");
				$this->assertSame($call['line'], array_values($unslashed)[0]['line']);
				++$cases;
			}
		}

		$this->assertSame(22, $cases, 'one negative case per writer');
	}

	public function test_a_comment_or_a_method_of_the_same_name_is_not_a_call(): void
	{
		$calls = self::calls('<?php // update_post_meta($id, "k", $v);' . "\n" . '$x->update_post_meta($id, "k", $v); function wp_update_post($a) {} update_post_meta($id, "k", wp_slash($v));');

		$this->assertCount(1, $calls);
		$this->assertTrue($calls[0]['slashed']);
	}

	public function test_a_value_only_partly_slashed_is_not_slashed(): void
	{
		$calls = self::calls('<?php update_post_meta($id, "k", wp_slash($m) + array( "raw" => $v )); wp_update_post(wp_slash($a) + $b, true); update_post_meta($id, "k", wp_slash($m));');

		$this->assertFalse($calls[0]['slashed'], 'wp_slash($m) + [...]');
		$this->assertFalse($calls[1]['slashed'], 'wp_slash($a) + $b');
		$this->assertTrue($calls[2]['slashed']);
	}

	public function test_case_and_a_by_reference_declaration_are_handled(): void
	{
		$calls = self::calls('<?php function &wp_update_post($a) {} Update_Post_Meta($id, "k", $v); \\WP_UPDATE_POST(WP_SLASH($a));');

		$this->assertCount(2, $calls);
		$this->assertFalse($calls[0]['slashed']);
		$this->assertTrue($calls[1]['slashed']);
	}

	public function test_the_value_argument_is_the_one_checked(): void
	{
		// wp_slash() on the key does not count; only the third argument carries the value.
		$calls = self::calls('<?php update_post_meta($id, wp_slash("k"), $v); wp_update_post(wp_slash(array( "ID" => 1 )), true);');

		$this->assertFalse($calls[0]['slashed']);
		$this->assertTrue($calls[1]['slashed']);
	}
}
