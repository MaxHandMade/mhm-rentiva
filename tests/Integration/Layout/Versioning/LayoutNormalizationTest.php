<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout\Versioning;

use MHMRentiva\Layout\Versioning\LayoutNormalization;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Layout normalization determinism coverage.
 */
final class LayoutNormalizationTest extends TestCase
{
    public function test_deterministic_key_order_produces_identical_output(): void
    {
        $manifestA = [
            'version' => '1.0.0',
            'source' => ['project' => 'abc', 'env' => 'prod'],
            'tokens' => ['z' => 'last', 'a' => 'first'],
            'pages' => [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'components' => [
                        ['type' => 'hero', 'attributes' => ['z' => '2', 'a' => '1']],
                    ],
                ],
            ],
        ];

        $manifestB = [
            'pages' => [
                [
                    'components' => [
                        ['attributes' => ['a' => '1', 'z' => '2'], 'type' => 'hero'],
                    ],
                    'title' => 'Home',
                    'slug' => 'home',
                ],
            ],
            'tokens' => ['a' => 'first', 'z' => 'last'],
            'source' => ['env' => 'prod', 'project' => 'abc'],
            'version' => '1.0.0',
        ];

        $this->assertSame(
            LayoutNormalization::normalize($manifestA),
            LayoutNormalization::normalize($manifestB)
        );
    }

    public function test_null_and_missing_fields_are_equivalent_for_associative_arrays(): void
    {
        $manifestWithNull = [
            'version' => '1.0.0',
            'meta' => [
                'x' => null,
            ],
        ];

        $manifestWithoutKey = [
            'version' => '1.0.0',
            'meta' => [],
        ];

        $this->assertSame(
            LayoutNormalization::normalize($manifestWithNull),
            LayoutNormalization::normalize($manifestWithoutKey)
        );
    }

    public function test_type_canonicalization_is_deterministic(): void
    {
        $manifestStringTypes = [
            'enabled' => 'true',
            'count' => '1',
            'ratio' => '12.5',
        ];

        $manifestNativeTypes = [
            'enabled' => true,
            'count' => 1,
            'ratio' => 12.5,
        ];

        $normalizedString = LayoutNormalization::normalize($manifestStringTypes);
        $normalizedNative = LayoutNormalization::normalize($manifestNativeTypes);

        $this->assertSame($normalizedNative, $normalizedString);
        $this->assertIsFloat($normalizedString['ratio']);
        $this->assertSame(12.5, $normalizedString['ratio']);
    }

    public function test_component_order_is_preserved_and_affects_normalized_output(): void
    {
        $manifestA = [
            'pages' => [
                [
                    'components' => [
                        ['component_id' => 'hero', 'order' => 1],
                        ['component_id' => 'reviews', 'order' => 2],
                    ],
                ],
            ],
        ];

        $manifestB = [
            'pages' => [
                [
                    'components' => [
                        ['component_id' => 'reviews', 'order' => 2],
                        ['component_id' => 'hero', 'order' => 1],
                    ],
                ],
            ],
        ];

        $this->assertNotSame(
            LayoutNormalization::normalize($manifestA),
            LayoutNormalization::normalize($manifestB)
        );
    }

    public function test_deep_nested_structures_are_sorted_deterministically(): void
    {
        $manifestA = [
            'pages' => [
                [
                    'components' => [
                        [
                            'attributes' => [
                                'styles' => [
                                    'desktop' => ['zIndex' => '9', 'align' => 'center'],
                                    'mobile' => ['padding' => '12', 'color' => 'red'],
                                ],
                                'flags' => ['enabled' => 'false', 'priority' => '2'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $manifestB = [
            'pages' => [
                [
                    'components' => [
                        [
                            'attributes' => [
                                'flags' => ['priority' => '2', 'enabled' => 'false'],
                                'styles' => [
                                    'mobile' => ['color' => 'red', 'padding' => '12'],
                                    'desktop' => ['align' => 'center', 'zIndex' => '9'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $normalizedA = LayoutNormalization::normalize($manifestA);
        $normalizedB = LayoutNormalization::normalize($manifestB);

        $this->assertSame($normalizedA, $normalizedB);
        $this->assertSame(
            ['enabled', 'priority'],
            array_keys($normalizedA['pages'][0]['components'][0]['attributes']['flags'])
        );
    }
}