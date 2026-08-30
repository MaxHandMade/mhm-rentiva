<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout;

use MHMRentiva\Layout\LayoutErrorMessages;
use MHMUiCore\Layout\ErrorCodes;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * mhm-ui-core's Layout engine returns WP_Error( code, '', $data ) --
 * deliberately message-free, see LayoutErrorMessages' own class docblock.
 * These tests pin the EXACT sentence LayoutErrorMessages::render() rebuilds
 * for every real payload shape the package raises (verified against
 * mhm-ui-core's src/Layout at 86e48c3): "not empty" is not an assertion
 * here, because the unrecognised-code fallback is non-empty by construction
 * too, and would satisfy a weaker check even from a broken switch.
 *
 * Eleven codes, thirteen shapes: invalid_page and invalid_instance each
 * have two distinct $data shapes, and each gets its own row below.
 */
final class LayoutErrorMessagesTest extends TestCase
{
    /**
     * @dataProvider every_payload_shape
     *
     * @param array<string, mixed> $data
     */
    public function test_each_shape_renders_its_exact_sentence(string $code, array $data, string $expected): void
    {
        $error = new WP_Error('mhmrentiva_' . $code, '', $data);

        $this->assertSame($expected, LayoutErrorMessages::render($error));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function every_payload_shape(): array
    {
        return [
            'invalid_blueprint: missing root key' => [
                'invalid_blueprint',
                ['key' => 'tokens'],
                'Manifest root key missing: tokens',
            ],
            'unsupported_version' => [
                'unsupported_version',
                ['version' => '3.0.0'],
                'Unsupported blueprint version: 3.0.0',
            ],
            'forbidden_pattern' => [
                'forbidden_pattern',
                ['pattern' => 'tailwind'],
                'Forbidden pattern detected in manifest: tailwind',
            ],
            'no_pages' => [
                'no_pages',
                [],
                'Manifest contains no pages.',
            ],
            'invalid_components' => [
                'invalid_components',
                [],
                'Manifest components section must be an object/array.',
            ],
            'invalid_page: page_index + key (validate_page)' => [
                'invalid_page',
                [
                    'page_index' => 2,
                    'key'        => 'slug',
                ],
                'Page #2 is missing key: slug',
            ],
            'invalid_page: page_index only, no key (root structure check)' => [
                'invalid_page',
                ['page_index' => 0],
                'Page #0 is not a valid page object',
            ],
            'invalid_instance: instance_index + page_index (validate_page)' => [
                'invalid_instance',
                [
                    'instance_index' => 1,
                    'page_index'     => 3,
                ],
                'Component instance #1 in page #3 missing instance_id',
            ],
            'invalid_instance: instance_id, non-string (ui-core CompositionBuilder::build)' => [
                'invalid_instance',
                ['instance_id' => 42],
                'Component instance has a non-string instance_id: 42',
            ],
            'unknown_component' => [
                'unknown_component',
                ['component_id' => 'ghost_widget'],
                'Unknown component reference: ghost_widget',
            ],
            'missing_adapter' => [
                'missing_adapter',
                ['type' => 'carousel'],
                'No adapter found for component type: carousel',
            ],
            'tailwind_leakage' => [
                'tailwind_leakage',
                ['pattern' => 'tw-'],
                'Tailwind leakage detected in rendered markup: tw-',
            ],
            'utility_leakage' => [
                'utility_leakage',
                ['fragment' => 'p-4'],
                'Unprefixed utility class detected: p-4',
            ],
        ];
    }

    /**
     * A code this class does not recognise (e.g. a package-side rename, or an
     * ErrorCodes addition Task B7's parity test would also catch) must never
     * fall back to a made-up sentence -- it must say which code it could not
     * render, so the gap is visible instead of a plausible-looking lie.
     */
    public function test_unrecognised_code_names_the_code_instead_of_pretending(): void
    {
        $error = new WP_Error('mhmrentiva_some_future_code', '', []);

        $this->assertSame(
            'Unhandled layout error: mhmrentiva_some_future_code',
            LayoutErrorMessages::render($error)
        );
    }

    /**
     * HANDLED is a literal list, and a literal list about someone else's
     * constants drifts the moment they change. This ties the two together: the
     * package renaming, adding or dropping a code turns this red instead of
     * letting render() fall through to its generic fallback, which is
     * non-empty and therefore invisible to any "did it produce a sentence"
     * check.
     *
     * Both sides are sorted before comparison. PHP compares list arrays
     * position by position, and these two are each internally coherent but in
     * different orders -- asserting them unsorted would fail while nothing is
     * actually missing, which is a gate reporting on something it is not
     * measuring.
     */
    public function test_every_package_error_code_has_a_sentence(): void
    {
        $expected = array_map(
            static fn (string $suffix): string => 'mhmrentiva_' . $suffix,
            ErrorCodes::ALL
        );

        $handled = LayoutErrorMessages::HANDLED;

        sort($expected);
        sort($handled);

        $this->assertSame($expected, $handled);
    }
}
