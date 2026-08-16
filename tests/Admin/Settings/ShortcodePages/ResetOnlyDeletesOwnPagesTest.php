<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings\ShortcodePages;

use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use WP_UnitTestCase;

/**
 * Independent review finding H-02: "reset all shortcode pages" could permanently
 * delete pages the site owner wrote.
 *
 * reset_pages() resolved each shortcode's page through
 * ShortcodeUrlManager::get_page_id() and called wp_delete_post( $id, true ) --
 * force delete, no trash, no undo. But get_page_id() does not find "the page this
 * plugin created": its query scans every published page for the shortcode or its
 * block equivalent in post_content. Any page the owner wrote themselves, with
 * their own content around a Rentiva shortcode, was an equally valid match.
 *
 * create_page() marks its own pages with _mhmrentiva_auto_created, and reset
 * never consulted it. Ownership is now required before deletion, in both the
 * bulk reset and the single-page delete.
 *
 * @covers \MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions::reset_pages
 * @covers \MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions::delete_page
 */
final class ResetOnlyDeletesOwnPagesTest extends WP_UnitTestCase
{
	private ShortcodePageActions $actions;

	public function setUp(): void
	{
		parent::setUp();
		$this->actions = new ShortcodePageActions();
	}

	/**
	 * A page the owner wrote, which happens to embed a Rentiva shortcode among
	 * their own content. RED before the fix: reset deletes it permanently.
	 */
	public function test_reset_does_not_delete_a_page_the_user_wrote(): void
	{
		$user_page = (int) self::factory()->post->create(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Our Fleet — hand written',
			'post_content' => "<p>Welcome to our fleet page.</p>\n[rentiva_vehicles_grid]\n<p>Call us on 555.</p>",
		));

		$this->actions->reset_pages();

		$this->assertNotNull(get_post($user_page), 'A page the plugin did not create must survive the reset.');
		$this->assertSame(
			'publish',
			(string) get_post_status($user_page),
			'A user-authored page must not even be trashed by the reset, let alone force-deleted.'
		);
	}

	/**
	 * Negative control: the reset must still do its job on pages the plugin
	 * created, or the guard could simply be "delete nothing".
	 */
	public function test_reset_still_deletes_a_page_the_plugin_created(): void
	{
		$auto_page = (int) self::factory()->post->create(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Vehicles',
			'post_content' => '[rentiva_vehicles_grid]',
		));
		update_post_meta($auto_page, '_mhmrentiva_shortcode', 'rentiva_vehicles_grid');
		update_post_meta($auto_page, '_mhmrentiva_auto_created', true);

		$this->actions->reset_pages();

		$this->assertNull(
			get_post($auto_page),
			'A page created by the plugin must still be removed by the reset.'
		);
	}

	/**
	 * The single-page delete carries the same ownership assumption. It trashes
	 * rather than force-deletes, so the damage is recoverable, but it must not
	 * act on content the plugin does not own.
	 */
	public function test_single_delete_refuses_a_page_the_user_wrote(): void
	{
		$user_page = (int) self::factory()->post->create(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => '[rentiva_booking_form]',
		));

		$result = $this->actions->delete_page($user_page);

		$this->assertFalse($result, 'Deleting a page the plugin does not own must be refused.');
		$this->assertSame('publish', (string) get_post_status($user_page));
	}

	public function test_single_delete_still_works_for_a_plugin_page(): void
	{
		$auto_page = (int) self::factory()->post->create(array(
			'post_type'   => 'page',
			'post_status' => 'publish',
		));
		update_post_meta($auto_page, '_mhmrentiva_auto_created', true);

		$this->assertTrue($this->actions->delete_page($auto_page));
		$this->assertSame('trash', (string) get_post_status($auto_page));
	}
}
