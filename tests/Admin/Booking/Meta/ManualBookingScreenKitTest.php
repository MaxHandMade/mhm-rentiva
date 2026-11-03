<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_UnitTestCase;

/**
 * The new-booking screen (post-new.php for mhmrentiva_booking): ui-core kit
 * under body.mhmui-admin, a single-column layout, and the subline under the
 * core H1 (edit_form_top).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox
 */
final class ManualBookingScreenKitTest extends WP_UnitTestCase
{
	/** @var mixed */
	private $saved_styles;

	public function set_up(): void
	{
		parent::set_up();
		$this->saved_styles   = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = null;
		remove_filter('admin_body_class', array( ManualBookingMetaBox::class, 'add_body_class' ));
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	public function tear_down(): void
	{
		remove_filter('admin_body_class', array( ManualBookingMetaBox::class, 'add_body_class' ));
		$GLOBALS['wp_styles'] = $this->saved_styles;
		unset($GLOBALS['post_type'], $GLOBALS['pagenow']);
		parent::tear_down();
	}

	private function on_new_booking_screen(): void
	{
		$GLOBALS['post_type'] = 'mhmrentiva_booking';
		$GLOBALS['pagenow']   = 'post-new.php';
	}

	public function test_screen_style_depends_on_the_kit(): void
	{
		$this->on_new_booking_screen();

		ManualBookingMetaBox::enqueue_scripts('post-new.php');

		$this->assertContains('mhmuicore-admin', wp_styles()->registered['mhm-rentiva-manual-booking-meta']->deps);
		$this->assertTrue(wp_style_is('mhmuicore-admin', 'enqueued'));
	}

	public function test_screen_adds_mhmui_admin_body_class(): void
	{
		$this->on_new_booking_screen();

		ManualBookingMetaBox::enqueue_scripts('post-new.php');

		$this->assertNotFalse(has_filter('admin_body_class', array( ManualBookingMetaBox::class, 'add_body_class' )));
		$classes = preg_split('/\s+/', trim(ManualBookingMetaBox::add_body_class('a')));
		$this->assertContains('mhmui-admin', $classes);
		$this->assertContains('a', $classes);
	}

	public function test_other_screens_get_nothing(): void
	{
		$GLOBALS['post_type'] = 'mhmrentiva_booking';
		ManualBookingMetaBox::enqueue_scripts('post.php');
		$GLOBALS['post_type'] = 'mhmrentiva_vehicle';
		ManualBookingMetaBox::enqueue_scripts('post-new.php');

		$this->assertFalse(wp_style_is('mhm-rentiva-manual-booking-meta', 'enqueued'));
		$this->assertFalse(has_filter('admin_body_class', array( ManualBookingMetaBox::class, 'add_body_class' )));
	}

	public function test_screen_layout_is_single_column_on_new_booking(): void
	{
		$this->on_new_booking_screen();
		$this->assertSame(1, ManualBookingMetaBox::single_column_layout(2));

		$GLOBALS['pagenow'] = 'post.php';
		$this->assertSame(2, ManualBookingMetaBox::single_column_layout(2));
	}

	public function test_layout_filter_is_registered(): void
	{
		if (! has_filter('get_user_option_screen_layout_mhmrentiva_booking', array( ManualBookingMetaBox::class, 'single_column_layout' ))) {
			ManualBookingMetaBox::register();
		}

		$this->assertNotFalse(has_filter('get_user_option_screen_layout_mhmrentiva_booking', array( ManualBookingMetaBox::class, 'single_column_layout' )));
		$this->assertNotFalse(has_action('edit_form_top', array( ManualBookingMetaBox::class, 'render_header_strip' )));
	}

	public function test_header_strip_links_back_to_bookings(): void
	{
		$post = self::factory()->post->create_and_get(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'auto-draft' ));

		ob_start();
		ManualBookingMetaBox::render_header_strip($post);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('edit.php?post_type=mhmrentiva_booking', $html);
		$this->assertStringContainsString(__('New record · added by an administrator', 'mhm-rentiva'), $html);
		$this->assertStringNotContainsString('name="', $html, 'No form controls inside form#post.');
	}

	public function test_header_strip_silent_elsewhere(): void
	{
		$booking = self::factory()->post->create_and_get(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$vehicle = self::factory()->post->create_and_get(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'auto-draft' ));

		foreach (array( $booking, $vehicle ) as $post) {
			ob_start();
			ManualBookingMetaBox::render_header_strip($post);
			$this->assertSame('', trim((string) ob_get_clean()), $post->post_type . '/' . $post->post_status);
		}
	}
}
