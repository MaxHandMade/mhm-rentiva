<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\Versioning\LayoutNormalization;
use WP_UnitTestCase;

/**
 * Tests for LayoutNormalization logic.
 */
class LayoutNormalizationTest extends WP_UnitTestCase
{
    /**
     * @test
     */
    public function it_normalizes_and_sorts_keys_recursively()
    {
        $input = [
            'z_key' => 'value',
            'a_key' => [
                'nested_z' => 2,
                'nested_a' => 1
            ],
            'null_key' => null
        ];

        $expected = [
            'a_key' => [
                'nested_a' => 1,
                'nested_z' => 2
            ],
            'z_key' => 'value'
        ];

        $result = LayoutNormalization::normalize($input);

        $this->assertEquals($expected, $result);
        $this->assertEquals(array_keys($expected), array_keys($result));
    }

    /**
     * @test
     */
    public function it_canonicalizes_scalar_types()
    {
        $input = [
            'is_true' => 'true',
            'is_false' => 'false',
            'numeric_int' => '123',
            'numeric_float' => '123.45',
            'normal_string' => 'hello'
        ];

        $result = LayoutNormalization::normalize($input);

        $this->assertIsBool($result['is_true']);
        $this->assertTrue($result['is_true']);
        $this->assertIsBool($result['is_false']);
        $this->assertFalse($result['is_false']);
        $this->assertIsInt($result['numeric_int']);
        $this->assertEquals(123, $result['numeric_int']);
        $this->assertIsFloat($result['numeric_float']);
        $this->assertEquals(123.45, $result['numeric_float']);
    }

    /**
     * @test
     */
    public function it_preserves_sensitive_identifier_strings()
    {
        $input = [
            'id' => '123',
            'post_id' => '456',
            'slug' => 'home-page',
            'parent_slug' => '789'
        ];

        $result = LayoutNormalization::normalize($input);

        // Should NOT be cast to int even if they look like numbers
        $this->assertIsString($result['id']);
        $this->assertIsString($result['post_id']);
        $this->assertIsString($result['parent_slug']);
    }

    /**
     * @test
     */
    public function it_handles_nested_lists_without_keys()
    {
        $input = [
            'list' => [3, 1, 2],
            'complex' => [
                ['name' => 'B', 'val' => 2],
                ['name' => 'A', 'val' => 1]
            ]
        ];

        $result = LayoutNormalization::normalize($input);

        // Lists should preserve order because keys 0, 1, 2 are already sorted
        $this->assertEquals([3, 1, 2], $result['list']);
        $this->assertEquals('B', $result['complex'][0]['name']);
    }
}
