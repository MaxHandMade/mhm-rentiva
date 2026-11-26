<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_UnitTestCase;

/**
 * The Dashboard's page title is printed by PHP, before the wp-header-end
 * marker, so admin notices are placed under it as on the other screens. The
 * React root below draws no title of its own.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage::render
 */
final class DashboardTitleBeforeNoticesTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_the_title_comes_before_the_notice_anchor_and_the_root(): void
    {
        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

        $ref  = new \ReflectionClass(DashboardPage::class);
        $page = $ref->newInstanceWithoutConstructor();
        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertSame(1, substr_count($html, '<h1'), 'One page title.');
        $h1     = strpos($html, '<h1');
        $marker = strpos($html, 'wp-header-end');
        $root   = strpos($html, 'id="mhm-rentiva-dashboard"');
        $this->assertNotFalse($marker);
        $this->assertLessThan($marker, $h1);
        $this->assertLessThan($root, $marker);
        $this->assertStringContainsString('class="wrap', $html, 'NoticePlacement looks for the marker inside .wrap.');
    }

    public function test_nothing_is_printed_without_the_capability(): void
    {
        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'subscriber' )));

        $page = ( new \ReflectionClass(DashboardPage::class) )->newInstanceWithoutConstructor();
        ob_start();
        $page->render();

        $this->assertSame('', (string) ob_get_clean());
    }
}
