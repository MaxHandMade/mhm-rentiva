<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * Task 6 of the Faz 1a list transform — the neutral toolbar seam.
 * WP.org trialware discipline: with no subscriber the seam must render
 * NOTHING, container included (no empty box teasing an absent feature).
 * With a subscriber (Pro), items render escaped.
 */
final class BookingToolbarSeamTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_booking';
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        parent::tearDown();
    }

    public function test_renders_nothing_without_a_subscriber(): void
    {
        ob_start();
        BookingColumns::toolbar_actions();
        $this->assertSame('', ob_get_clean());
    }

    public function test_renders_escaped_subscriber_actions(): void
    {
        add_filter('mhmrentiva_booking_list_toolbar_actions', static function (array $actions): array {
            $actions[] = array(
                'label' => 'Export <b>now</b>',
                'url'   => admin_url('admin.php?page=mhm-rentiva-export'),
            );
            return $actions;
        });

        ob_start();
        BookingColumns::toolbar_actions();
        $html = ob_get_clean();

        $this->assertStringContainsString('rv-bkl-toolbar__btn', $html);
        $this->assertStringContainsString('page=mhm-rentiva-export', $html);
        $this->assertStringNotContainsString('<b>now</b>', $html);
        $this->assertStringContainsString('Export &lt;b&gt;now&lt;/b&gt;', $html);
    }

    public function test_malformed_items_are_skipped(): void
    {
        add_filter('mhmrentiva_booking_list_toolbar_actions', static function (): array {
            return array(array('label' => 'No URL'), array('url' => 'https://example.com'));
        });

        ob_start();
        BookingColumns::toolbar_actions();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('No URL', $html);
        $this->assertStringNotContainsString('example.com', $html);
    }
}
