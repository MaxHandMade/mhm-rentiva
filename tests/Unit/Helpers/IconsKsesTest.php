<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Helpers;

use MHMRentiva\Helpers\Icons;
use WP_UnitTestCase;

/**
 * Icons::get() sizes every icon with an inline `style="width:..;height:.."` and marks it
 * `overflow="visible"`. Icons::echo_svg() escapes that markup through wp_kses() before echoing
 * it, so the allowlist wp_kses is given MUST keep those presentation hooks -- otherwise the
 * icon loses its size and renders huge (or collapses to nothing). This pins that contract:
 * escaping the icon must not change how it looks.
 */
final class IconsKsesTest extends WP_UnitTestCase
{
    /**
     * @param array<string,array<string,bool>> $allowed
     */
    private function escape(string $html, array $allowed): string
    {
        ob_start();
        Icons::echo_svg($html, $allowed);
        return (string) ob_get_clean();
    }

    public function test_keeps_the_inline_size_style_on_a_stroked_icon(): void
    {
        $filtered = $this->escape(Icons::get('search'), Icons::allowed_svg());

        $this->assertStringContainsString('width: 20px', $filtered, 'wp_kses stripped the inline width that sizes the icon.');
        $this->assertStringContainsString('height: 20px', $filtered, 'wp_kses stripped the inline height that sizes the icon.');
        $this->assertStringContainsString('overflow="visible"', $filtered, 'wp_kses stripped the overflow attribute.');
    }

    public function test_keeps_a_custom_size_passed_through_args(): void
    {
        $filtered = $this->escape(
            Icons::get('heart', array( 'width' => '48px', 'height' => '48px' )),
            Icons::allowed_svg_wrapper()
        );

        $this->assertStringContainsString('width: 48px', $filtered);
        $this->assertStringContainsString('height: 48px', $filtered);
    }

    public function test_keeps_the_svg_geometry(): void
    {
        // The path/circle/line children that draw the icon must survive escaping.
        $filtered = $this->escape(Icons::get('search'), Icons::allowed_svg());

        $this->assertStringContainsString('<circle', $filtered);
        $this->assertStringContainsString('<line', $filtered);
    }

    public function test_still_strips_active_content(): void
    {
        // The allowlist must keep presentation hooks WITHOUT letting active content through:
        // the <script> element and the on* handler are removed (the leftover text node is inert).
        $hostile  = '<svg style="width:20px" onload="alert(1)"><script>alert(2)</script><circle cx="1" cy="1" r="1"/></svg>';
        $filtered = $this->escape($hostile, Icons::allowed_svg());

        $this->assertStringNotContainsString('<script', $filtered, 'The <script> element must be removed.');
        $this->assertStringNotContainsString('onload', $filtered, 'Event-handler attributes must be removed.');
        $this->assertStringContainsString('<circle', $filtered);
    }
}
