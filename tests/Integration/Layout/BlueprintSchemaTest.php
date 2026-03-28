<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\BlueprintValidator;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Blueprint Schema Test
 *
 * @package MHMRentiva\Tests\Integration\Layout
 */
final class BlueprintSchemaTest extends TestCase
{

    private BlueprintValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BlueprintValidator();
    }

    public function test_valid_manifest_passes(): void
    {
        $manifest = [
            'version'     => '1.0.0',
            'source'      => ['stitch_project_id' => '123'],
            'pages'       => [
                [
                    'id'          => 'p1',
                    'slug'        => 'home',
                    'title'       => 'Home',
                    'layout'      => [],
                    'composition' => [],
                    'slots'       => [],
                    'assets'      => ['styles' => [], 'scripts' => []]
                ]
            ],
            'tokens'      => [],
            'components'  => [],
            'constraints' => [
                'forbidden'   => [],
                'contract'    => [],
                'performance' => ['max_delta_q' => 0, 'cache_strategy' => 'block_meta']
            ]
        ];

        $result = $this->validator->validate($manifest);
        $this->assertTrue($result);
    }

    public function test_missing_required_key_fails(): void
    {
        $manifest = [
            'version' => '1.0.0'
            // Missing source, pages, etc.
        ];

        $result = $this->validator->validate($manifest);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('mhm_rentiva_invalid_blueprint', $result->get_error_code());
        $this->assertStringContainsString('Manifest root key missing', $result->get_error_message());
    }

    public function test_forbidden_pattern_fails(): void
    {
        $manifest = [
            'version'     => '1.0.0',
            'source'      => [],
            'pages'       => [],
            'tokens'      => ['bg-color' => 'tailwind-blue'], // Forbidden pattern "tailwind"
            'components'  => [],
            'constraints' => ['forbidden' => [], 'contract' => [], 'performance' => []]
        ];

        $result = $this->validator->validate($manifest);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('mhm_rentiva_forbidden_pattern', $result->get_error_code());
    }

    public function test_unsupported_version_fails(): void
    {
        $manifest = [
            'version'     => '2.0.0', // Unsupported version
            'source'      => [],
            'pages'       => [],
            'tokens'      => [],
            'components'  => [],
            'constraints' => []
        ];

        $result = $this->validator->validate($manifest);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('mhm_rentiva_unsupported_version', $result->get_error_code());
    }
}
