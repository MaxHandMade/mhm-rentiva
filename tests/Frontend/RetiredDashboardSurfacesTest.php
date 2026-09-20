<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

/**
 * All three published surfaces are retired together: new placements are closed,
 * existing placements still render, and none of them loads a stylesheet for a
 * one-line notice.
 */
final class RetiredDashboardSurfacesTest extends \WP_UnitTestCase {

    public function test_the_block_is_closed_to_new_placements(): void
    {
        $json = json_decode(
            (string) file_get_contents(MHMRENTIVA_PLUGIN_PATH . 'assets/blocks/user-dashboard/block.json'),
            true
        );

        $this->assertFalse($json['supports']['inserter'], 'The retired block is still offered in the inserter.');
    }

    public function test_the_block_declares_no_stylesheet(): void
    {
        $json = json_decode(
            (string) file_get_contents(MHMRENTIVA_PLUGIN_PATH . 'assets/blocks/user-dashboard/block.json'),
            true
        );

        $this->assertArrayNotHasKey('style', $json);
        $this->assertArrayNotHasKey('editorStyle', $json);
    }

    public function test_the_block_description_no_longer_advertises_a_vendor_dashboard(): void
    {
        $json = json_decode(
            (string) file_get_contents(MHMRENTIVA_PLUGIN_PATH . 'assets/blocks/user-dashboard/block.json'),
            true
        );

        $this->assertStringNotContainsStringIgnoringCase('vendor', $json['description']);
        $this->assertStringContainsStringIgnoringCase('deprecated', $json['title'] . ' ' . $json['description']);
    }

    public function test_an_existing_block_instance_still_renders_the_stub(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'customer' )));

        $html = do_blocks('<!-- wp:mhm-rentiva/user-dashboard /-->');

        $this->assertStringContainsString('mhm-rentiva-retired-dashboard', $html);
    }

    public function test_the_shortcode_renders_the_same_stub(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'customer' )));

        $this->assertStringContainsString('mhm-rentiva-retired-dashboard', do_shortcode('[rentiva_user_dashboard]'));
    }

    public function test_the_elementor_widget_declares_no_style_dependency(): void
    {
        if (! class_exists('\Elementor\Widget_Base')) {
            $this->markTestSkipped('Elementor is not loaded in this run.');
        }

        $widget = new \MHMRentiva\Admin\Frontend\Widgets\Elementor\UserDashboardWidget();

        $this->assertSame(array(), $widget->get_style_depends());
        $this->assertFalse($widget->show_in_panel());
        $this->assertTrue($widget->hide_on_search());
    }
}
