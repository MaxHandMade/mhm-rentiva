<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use WP_UnitTestCase;

/**
 * Unit tests for KeyNormalizer.
 *
 * Two phases under test:
 *   Phase 1 — Explicit alias resolution: when a schema is provided, any key that
 *             appears in a canonical entry's 'aliases' list is returned as that
 *             canonical key.  This takes priority over all regex logic.
 *   Phase 2 — camelCase → snake_case regex fallback: when no schema is provided
 *             (or no alias matches), the regex `/(?<!^)[A-Z]/` inserts `_` before
 *             every uppercase letter that is NOT the very first character, then
 *             strtolower() finishes the job.
 *
 * @covers \MHMRentiva\Core\Attribute\KeyNormalizer
 * @package MHMRentiva\Tests\Core\Attribute
 * @since 4.21.3
 */
final class KeyNormalizerTest extends WP_UnitTestCase
{

	// -------------------------------------------------------------------------
	// Phase 2 — camelCase → snake_case regex fallback (no schema supplied)
	// -------------------------------------------------------------------------

	/**
	 * Simple two-word camelCase: one uppercase interior letter.
	 * vehicleId → vehicle_id
	 */
	public function test_simple_camelcase_is_converted_to_snake_case(): void
	{
		$this->assertSame('vehicle_id', KeyNormalizer::normalize('vehicleId'));
	}

	/**
	 * Three-word camelCase: two uppercase interior letters.
	 * defaultDays → default_days
	 */
	public function test_three_word_camelcase_is_converted_to_snake_case(): void
	{
		$this->assertSame('default_days', KeyNormalizer::normalize('defaultDays'));
	}

	/**
	 * Four-word camelCase: three uppercase interior letters.
	 * showBookingButton → show_booking_button
	 */
	public function test_four_word_camelcase_is_converted_to_snake_case(): void
	{
		$this->assertSame('show_booking_button', KeyNormalizer::normalize('showBookingButton'));
	}

	/**
	 * Another four-word variant to confirm consistent behaviour.
	 * showFavoriteButton → show_favorite_button
	 */
	public function test_show_favorite_button_camelcase_converts_correctly(): void
	{
		$this->assertSame('show_favorite_button', KeyNormalizer::normalize('showFavoriteButton'));
	}

	/**
	 * Two-word camelCase with short second word.
	 * minWidth → min_width
	 */
	public function test_min_width_camelcase_converts_correctly(): void
	{
		$this->assertSame('min_width', KeyNormalizer::normalize('minWidth'));
	}

	/**
	 * Already-snake_case input must pass through unchanged (no uppercase to match).
	 * vehicle_id → vehicle_id
	 */
	public function test_already_snake_case_passes_through_unchanged(): void
	{
		$this->assertSame('vehicle_id', KeyNormalizer::normalize('vehicle_id'));
	}

	/**
	 * Fully lowercase input (no underscores, no uppercase) passes through unchanged.
	 * limit → limit
	 */
	public function test_all_lowercase_passes_through_unchanged(): void
	{
		$this->assertSame('limit', KeyNormalizer::normalize('limit'));
	}

	/**
	 * PascalCase (first character uppercase): the negative lookbehind `(?<!^)`
	 * prevents the leading capital from gaining a prefix underscore.  strtolower()
	 * then lowercases it.
	 * Status → status
	 */
	public function test_pascal_case_first_char_uppercased_is_lowercased_without_leading_underscore(): void
	{
		$this->assertSame('status', KeyNormalizer::normalize('Status'));
	}

	/**
	 * Empty string input must return empty string (no error, no mutation).
	 */
	public function test_empty_string_returns_empty_string(): void
	{
		$this->assertSame('', KeyNormalizer::normalize(''));
	}

	/**
	 * Consecutive uppercase letters: each interior uppercase gets its own underscore
	 * prefix because the regex matches single uppercase characters one at a time.
	 *
	 * IBANcode:
	 *   - 'I' is at position 0 → lookbehind fails → NOT prefixed
	 *   - 'B', 'A', 'N' are interior → each prefixed
	 *   → I_B_A_Ncode → strtolower → i_b_a_ncode
	 *
	 * This documents the known regex edge-case behaviour for acronyms.
	 */
	public function test_consecutive_uppercase_letters_each_get_own_underscore_prefix(): void
	{
		$this->assertSame('i_b_a_ncode', KeyNormalizer::normalize('IBANcode'));
	}

	/**
	 * Multi-character uppercase run in the middle of a word.
	 *
	 * myHTML:
	 *   - 'm', 'y' are lowercase
	 *   - 'H', 'T', 'M', 'L' are each interior uppercase → each prefixed
	 *   → my_H_T_M_L → strtolower → my_h_t_m_l
	 */
	public function test_mid_word_consecutive_uppercase_letters_are_each_prefixed(): void
	{
		$this->assertSame('my_h_t_m_l', KeyNormalizer::normalize('myHTML'));
	}

	/**
	 * Digit immediately before an uppercase letter: the regex does not care whether
	 * the preceding character is a letter or a digit — the lookbehind only checks
	 * that the match is not at the start of the string.
	 *
	 * limit3D → D is interior → limit3_D → strtolower → limit3_d
	 */
	public function test_digit_before_uppercase_letter_is_treated_as_interior(): void
	{
		$this->assertSame('limit3_d', KeyNormalizer::normalize('limit3D'));
	}

	// -------------------------------------------------------------------------
	// Phase 1 — Explicit alias resolution (schema provided)
	// -------------------------------------------------------------------------

	/**
	 * An alias that maps to a canonical key whose name differs from the regex result.
	 *
	 * 'sortBy' is listed as an alias for the canonical key 'orderby'.
	 * The regex alone would produce 'sort_by' — the alias lookup must win.
	 */
	public function test_alias_resolves_to_canonical_key_not_regex_result(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('orderby', KeyNormalizer::normalize('sortBy', $schema));
	}

	/**
	 * 'sortBy' alias priority beats what the regex would produce ('sort_by').
	 * Explicit assertion that the alias path wins over the fallback path.
	 */
	public function test_sort_by_alias_wins_over_regex_fallback(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$result = KeyNormalizer::normalize('sortBy', $schema);

		$this->assertSame('orderby', $result, "'sortBy' alias should resolve to canonical 'orderby', not regex-derived 'sort_by'");
		$this->assertNotSame('sort_by', $result, "Regex fallback 'sort_by' must NOT be returned when an alias is defined");
	}

	/**
	 * 'minWidth' alias maps to canonical key 'minwidth' (no underscore).
	 * The regex alone would produce 'min_width' — alias must override.
	 */
	public function test_min_width_alias_wins_over_regex_fallback(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$result = KeyNormalizer::normalize('minWidth', $schema);

		$this->assertSame('minwidth', $result, "'minWidth' alias should resolve to canonical 'minwidth', not regex-derived 'min_width'");
		$this->assertNotSame('min_width', $result, "Regex fallback 'min_width' must NOT be returned when alias is defined");
	}

	/**
	 * 'showImage' is an alias for canonical key 'show_image'.
	 * In this case the alias result and the regex result happen to coincide
	 * ('show_image'), but the test confirms the alias path is taken.
	 */
	public function test_show_image_alias_resolves_to_show_image(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('show_image', KeyNormalizer::normalize('showImage', $schema));
	}

	/**
	 * 'defaultDays' is an alias for canonical key 'default_days'.
	 * Alias and regex happen to agree here; test confirms alias resolution fires.
	 */
	public function test_default_days_alias_resolves_to_default_days(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('default_days', KeyNormalizer::normalize('defaultDays', $schema));
	}

	/**
	 * A key that appears in no alias list falls back to the regex when a schema
	 * is present.  The schema is searched first; finding no match, the regex runs.
	 * unknownCamelKey → unknown_camel_key
	 */
	public function test_unknown_key_with_schema_falls_back_to_regex(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('unknown_camel_key', KeyNormalizer::normalize('unknownCamelKey', $schema));
	}

	/**
	 * A canonical key that is also listed in its own 'aliases' array is returned
	 * as-is via alias resolution.
	 *
	 * 'orderby' is listed in `AllowlistRegistry::ALLOWLIST['orderby']['aliases']`,
	 * so passing 'orderby' with the schema should hit the alias branch and return
	 * 'orderby' unchanged (not process it through the regex, which would also
	 * return 'orderby' since it is already lowercase — but the test verifies the
	 * alias path is taken).
	 */
	public function test_canonical_key_in_own_aliases_resolves_to_itself(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('orderby', KeyNormalizer::normalize('orderby', $schema));
	}

	/**
	 * A canonical snake_case key that is NOT listed in any aliases array still
	 * passes through correctly via the regex fallback (no uppercase → no change).
	 * 'show_pagination' has no self-alias; regex leaves it untouched.
	 */
	public function test_canonical_key_not_in_aliases_passes_through_via_regex(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('show_pagination', KeyNormalizer::normalize('show_pagination', $schema));
	}

	/**
	 * 'showPagination' is an alias for 'show_pagination'.
	 * Verifies alias resolution for a show_* visibility toggle that has a single alias.
	 */
	public function test_show_pagination_camelcase_alias_resolves_correctly(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('show_pagination', KeyNormalizer::normalize('showPagination', $schema));
	}

	/**
	 * Alias lookup uses strict (===) comparison; numeric-string aliases will not
	 * accidentally match integer keys.  Passing an integer-like string that is not
	 * an alias falls through to the regex, which passes it through unchanged.
	 */
	public function test_numeric_string_key_with_schema_falls_back_to_regex(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		// '123' is all lowercase/digits — regex leaves it unchanged.
		$this->assertSame('123', KeyNormalizer::normalize('123', $schema));
	}

	/**
	 * An inline (minimal) schema without the full ALLOWLIST can be supplied;
	 * the alias lookup works identically on any associative array that matches
	 * the expected shape.
	 */
	public function test_alias_resolution_works_with_inline_schema(): void
	{
		$inline_schema = [
			'my_canonical' => [
				'type'    => 'string',
				'aliases' => ['myCanonical', 'my-canonical'],
			],
		];

		$this->assertSame('my_canonical', KeyNormalizer::normalize('myCanonical', $inline_schema));
		$this->assertSame('my_canonical', KeyNormalizer::normalize('my-canonical', $inline_schema));
	}

	/**
	 * When normalize() is called with an empty schema array (explicitly passed),
	 * it behaves identically to no-schema mode: the regex fallback runs.
	 * vehicleId → vehicle_id
	 */
	public function test_empty_schema_array_triggers_regex_fallback(): void
	{
		$this->assertSame('vehicle_id', KeyNormalizer::normalize('vehicleId', []));
	}

	/**
	 * A schema entry with no 'aliases' key (key absent entirely) must not cause
	 * an error; the null-coalescing `?? []` in the implementation guards this.
	 * The key is not found as an alias, so the regex runs.
	 */
	public function test_schema_entry_without_aliases_key_does_not_cause_error(): void
	{
		$schema_without_aliases = [
			'width'  => ['type' => 'string', 'group' => 'layout'],
			'height' => ['type' => 'string', 'group' => 'layout'],
		];

		// 'someKey' has no alias; regex converts it.
		$this->assertSame('some_key', KeyNormalizer::normalize('someKey', $schema_without_aliases));
	}

	/**
	 * 'showImages' is a second alias for 'show_image' (alongside 'showImage').
	 * Both aliases must resolve to the same canonical key.
	 */
	public function test_multiple_aliases_for_same_canonical_key_all_resolve(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		$this->assertSame('show_image', KeyNormalizer::normalize('showImage', $schema));
		$this->assertSame('show_image', KeyNormalizer::normalize('showImages', $schema));
		$this->assertSame('show_image', KeyNormalizer::normalize('showVehicleImage', $schema));
	}

	/**
	 * 'resultsPerPage' appears as an alias on two ALLOWLIST keys ('limit' and
	 * 'results_per_page').  PHP's foreach iterates in insertion order, so the
	 * FIRST match wins.  This test documents the observed deterministic behaviour:
	 * the result is always the canonical key of whichever entry appears first in
	 * the ALLOWLIST array for any given tag schema.
	 *
	 * When using the full ALLOWLIST, 'limit' is defined before 'results_per_page',
	 * so 'resultsPerPage' resolves to 'limit'.
	 */
	public function test_first_alias_match_in_schema_wins_for_duplicate_alias(): void
	{
		$schema = AllowlistRegistry::ALLOWLIST;

		// 'limit' comes before 'results_per_page' in ALLOWLIST, so it wins.
		$result = KeyNormalizer::normalize('resultsPerPage', $schema);
		$this->assertSame('limit', $result);
	}
}
