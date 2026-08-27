<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\TokenMapper;
use MHMRentiva\Layout\CompositionBuilder;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Token Mapping Test
 *
 * Verifies that the TokenMapper correctly translates manifest tokens
 * into MHM-standard CSS variables and handles fallbacks.
 *
 * @package MHMRentiva\Tests\Integration\Layout
 * @since 4.15.0
 */
final class TokenMappingTest extends TestCase
{
    private TokenMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new TokenMapper();
    }

    /**
     * Test mapping of valid design tokens.
     */
    public function test_valid_token_mapping(): void
    {
        $tokens = [
            'colors' => [
                'primary'    => '#ff0000',
                'background' => '#ffffff',
            ],
            'spacing' => [
                'unit' => '1.5rem',
            ],
        ];

        $style = $this->mapper->map_to_style_string($tokens);

        $this->assertStringContainsString('--mhm-bp-primary: #ff0000;', $style);
        $this->assertStringContainsString('--mhm-bp-bg-main: #ffffff;', $style);
        $this->assertStringContainsString('--mhm-bp-spacing-base: 1.5rem;', $style);

        // The point of the `bp` namespace, and the only assertion that would
        // notice if someone mapped these back. The mapper's output lands as an
        // inline style on .mhm-layout-root, and an inline custom property beats
        // an inherited one on every descendant -- so emitting a shared name here
        // hands a published blueprint the product's palette for its subtree.
        $this->assertStringNotContainsString('--mhm-primary:', $style);
        $this->assertStringNotContainsString('--mhm-bg-main:', $style);
        $this->assertStringNotContainsString('--mhm-spacing-base:', $style);
    }

    /**
     * Test that empty or missing tokens are handled gracefully.
     */
    public function test_empty_token_mapping(): void
    {
        $tokens = [];
        $style  = $this->mapper->map_to_style_string($tokens);
        $this->assertEmpty($style);
    }

    /**
     * Test sanitization of unauthorized or malicious values.
     */
    public function test_token_sanitization(): void
    {
        $tokens = [
            'colors' => [
                'primary' => 'tailwind-blue-500', // Forbidden
                'accent'  => 'javascript:alert(1)', // Harmful
            ],
            'radius' => [
                'main' => '4px', // Valid
            ],
        ];

        $style = $this->mapper->map_to_style_string($tokens);

        $this->assertStringNotContainsString('--mhm-bp-primary', $style);
        $this->assertStringNotContainsString('javascript', $style);
        $this->assertStringContainsString('--mhm-bp-border-radius: 4px;', $style);
    }

    /**
     * Test integration with CompositionBuilder markup.
     */
    public function test_composition_builder_token_integration(): void
    {
        $builder = new CompositionBuilder();
        $manifest = [
            'version'    => '1.0.0',
            'source'     => 'test',
            'pages'      => [],
            'components' => [],
            'constraints' => [],
            'tokens'     => [
                'colors' => [
                    'primary' => '#123456',
                ],
            ],
        ];
        $page = [
            'slug'        => 'test-page',
            'layout'      => 'full-width',
            'composition' => [],
        ];

        $output = $builder->build($manifest, $page);

        $this->assertStringContainsString('class="mhm-layout-root"', $output);
        $this->assertStringContainsString('--mhm-bp-primary: #123456;', $output);
    }
}
