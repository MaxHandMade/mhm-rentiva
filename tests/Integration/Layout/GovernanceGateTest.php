<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\CompositionBuilder;
use MHMRentiva\Layout\AdapterRegistry;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Governance Gate Test
 *
 * Verifies that the CompositionBuilder correctly blocks prohibited patterns
 * and enforces project-specific class naming conventions.
 *
 * @package MHMRentiva\Tests\Integration\Layout
 */
final class GovernanceGateTest extends TestCase
{
    private CompositionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CompositionBuilder();
        AdapterRegistry::boot_defaults();
    }

    /**
     * Test detection of various Tailwind and prohibited patterns.
     * 
     * @dataProvider prohibited_patterns_provider
     */
    public function test_prohibited_patterns_detection(string $class_content, string $expected_code): void
    {
        $manifest = $this->get_mock_manifest(['class' => $class_content]);
        $page = $manifest['pages'][0];

        $result = $this->builder->build($manifest, $page);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals($expected_code, $result->get_error_code());
    }

    public function prohibited_patterns_provider(): array
    {
        return [
            'Direct Tailwind Prefix'       => ['tw-bg-blue-500', 'mhm_rentiva_tailwind_leakage'],
            'Tailwind Keyword'             => ['tailwind-test', 'mhm_rentiva_tailwind_leakage'],
            'Unprefixed Utility (Flex)'    => ['flex-row', 'mhm_rentiva_utility_leakage'],
            'Unprefixed Utility (Padding)' => ['p-4', 'mhm_rentiva_utility_leakage'],
            'Unprefixed Utility (Margin)'  => ['m-2', 'mhm_rentiva_utility_leakage'],
            'Unprefixed Utility (Grid)'    => ['grid-cols-3', 'mhm_rentiva_utility_leakage'],
        ];
    }

    /**
     * Test that compliant "mhm-" prefixed classes pass.
     */
    public function test_compliant_patterns_pass(): void
    {
        $manifest = $this->get_mock_manifest(['class' => 'mhm-custom-section mhm-flex-container']);
        $page = $manifest['pages'][0];

        $result = $this->builder->build($manifest, $page);

        $this->assertIsString($result);
        $this->assertStringContainsString('mhm-custom-section', $result);
    }

    /**
     * Helper to get a minimal valid manifest for testing.
     */
    private function get_mock_manifest(array $attributes): array
    {
        return [
            'version' => '1.0.0',
            'source'  => [],
            'pages'   => [
                [
                    'id'          => 'p1',
                    'slug'        => 'test',
                    'title'       => 'Test',
                    'layout'      => [],
                    'composition' => [
                        [
                            'component_id' => 'comp1',
                            'instance_id'  => 'inst1',
                            'attributes'   => $attributes
                        ]
                    ],
                    'slots'  => [],
                    'assets' => []
                ]
            ],
            'tokens'      => [],
            'components'  => [
                'comp1' => ['type' => 'search_hero']
            ],
            'constraints' => [
                'forbidden'   => ['tw-', 'tailwind'],
                'contract'    => [],
                'performance' => []
            ]
        ];
    }
}
