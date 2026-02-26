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

        $this->assertStringContainsString('--mhm-primary: #ff0000;', $style);
        $this->assertStringContainsString('--mhm-bg-main: #ffffff;', $style);
        $this->assertStringContainsString('--mhm-spacing-base: 1.5rem;', $style);
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

        $this->assertStringNotContainsString('--mhm-primary', $style);
        $this->assertStringNotContainsString('javascript', $style);
        $this->assertStringContainsString('--mhm-border-radius: 4px;', $style);
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
        $this->assertStringContainsString('--mhm-primary: #123456;', $output);
    }
}
