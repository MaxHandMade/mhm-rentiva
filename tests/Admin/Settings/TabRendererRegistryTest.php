<?php

namespace MHMRentiva\Tests\Admin\Settings\View;

use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use MHMRentiva\Admin\Settings\View\TabRendererInterface;
use WP_UnitTestCase;

/**
 * Test class for TabRendererRegistry
 */
class TabRendererRegistryTest extends WP_UnitTestCase
{

    private TabRendererRegistry $registry;

    public function setUp(): void
    {
        parent::setUp();
        $this->registry = new TabRendererRegistry();
    }

    /**
     * @test
     */
    public function it_registers_default_renderers()
    {
        $tabs = $this->registry->get_all();

        $this->assertArrayHasKey('general', $tabs);
        $this->assertArrayHasKey('vehicle', $tabs);
        $this->assertInstanceOf(TabRendererInterface::class, $tabs['general']);
    }

    /**
     * @test
     */
    public function it_can_get_specific_renderer()
    {
        $renderer = $this->registry->get('general');
        $this->assertNotNull($renderer);
        $this->assertEquals('general', $renderer->get_slug());
    }

    /**
     * @test
     */
    public function it_returns_null_for_non_existent_renderer()
    {
        $renderer = $this->registry->get('non_existent');
        $this->assertNull($renderer);
    }

    /**
     * @test
     */
    public function it_does_not_register_the_settings_testing_tab()
    {
        // The Settings Testing diagnostics tab shipped a developer-only
        // screen (raw superglobal handling flagged by WP.org review) that
        // has no place in a production build. It must never come back.
        $tabs = $this->registry->get_all();

        $this->assertArrayNotHasKey('testing', $tabs);
    }

    /**
     * @test
     */
    public function it_is_extensible_via_actions()
    {
        // Mock a new renderer and register it via action
        add_action('mhmrentiva_settings_register_renderers', function ($registry) {
            $mock_renderer = $this->createMock(TabRendererInterface::class);
            $mock_renderer->method('get_slug')->willReturn('custom_tab');
            $mock_renderer->method('get_label')->willReturn('Custom Tab');
            $registry->register($mock_renderer);
        });

        // Re-instantiate to trigger defaults + action
        $registry = new TabRendererRegistry();
        $tabs = $registry->get_all();

        $this->assertArrayHasKey('custom_tab', $tabs);
    }
}
