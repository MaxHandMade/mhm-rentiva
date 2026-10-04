<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\ContactMessages\ContactMessagesPage;
use MHMRentiva\Admin\ContactMessages\ContactStatus;
use WP_UnitTestCase;

final class ContactMessagesPageTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_transient(ContactStatus::BADGE_TRANSIENT);
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_plain_legacy_list_view_redirects_but_core_actions_do_not(): void
	{
		$t = 'mhmrentiva_contact';
		$this->assertTrue(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t ), $t));
		$this->assertTrue(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'action' => '-1', 'action2' => '-1' ), $t));
		$this->assertFalse(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'action' => 'trash' ), $t));
		$this->assertFalse(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'action2' => 'untrash' ), $t));
		$this->assertFalse(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'delete_all' => 'Empty Trash' ), $t));
		$this->assertFalse(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'delete_all2' => 'Empty Trash' ), $t));
		$this->assertFalse(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'filter_action' => 'Filter' ), $t));
		$this->assertFalse(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => 'post' ), 'post'));
	}

	/**
	 * Codex minor: core's current_action() (WP_List_Table) only treats
	 * filter_action as "present" when it is non-empty -- a plain view of the
	 * filter bar with nothing chosen submits `filter_action=` (empty), which
	 * must still redirect to the new screen, not be mistaken for a filter
	 * request left on the legacy list.
	 */
	public function test_an_empty_filter_action_is_treated_as_absent(): void
	{
		$t = 'mhmrentiva_contact';
		$this->assertTrue(ContactMessagesPage::legacy_list_should_redirect(array( 'post_type' => $t, 'filter_action' => '' ), $t));
	}

	public function test_legacy_edit_screen_redirects_only_for_view(): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		$this->assertSame($id, ContactMessagesPage::legacy_post_redirect_id(array( 'post' => (string) $id, 'action' => 'edit' )));
		$this->assertSame($id, ContactMessagesPage::legacy_post_redirect_id(array( 'post' => (string) $id )));
		$this->assertSame(0, ContactMessagesPage::legacy_post_redirect_id(array( 'post' => (string) $id, 'action' => 'trash' )));
		$other = (int) self::factory()->post->create();
		$this->assertSame(0, ContactMessagesPage::legacy_post_redirect_id(array( 'post' => (string) $other, 'action' => 'edit' )));
	}

	public function test_menu_title_shows_a_bubble_only_for_admins_with_new_messages(): void
	{
		set_transient(ContactStatus::BADGE_TRANSIENT, 0, 300);
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->assertStringNotContainsString('awaiting-mod', ContactMessagesPage::menu_title());

		set_transient(ContactStatus::BADGE_TRANSIENT, 3, 300);
		$title = ContactMessagesPage::menu_title();
		$this->assertStringContainsString('awaiting-mod', $title);
		$this->assertStringContainsString('aria-hidden="true">3<', $title);
		$this->assertStringContainsString('screen-reader-text', $title);

		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		$this->assertStringNotContainsString('awaiting-mod', ContactMessagesPage::menu_title());
	}

	public function test_menu_title_formats_large_count(): void
	{
		set_transient(ContactStatus::BADGE_TRANSIENT, 1250, 300);
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->assertStringContainsString(number_format_i18n(1250), ContactMessagesPage::menu_title());
	}

	public function test_render_prints_nothing_without_manage_options(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		ob_start();
		( new ContactMessagesPage() )->render();
		$this->assertSame('', ob_get_clean());
	}

	public function test_the_core_details_panel_is_gone(): void
	{
		$this->assertFalse(has_action('add_meta_boxes_mhmrentiva_contact'));
		$this->assertFalse(has_filter('manage_mhmrentiva_contact_posts_columns'));
	}

	public function test_register_wires_forget_badge_to_every_status_changing_hook(): void
	{
		ContactMessagesPage::register();

		foreach ( array( 'save_post_mhmrentiva_contact', 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			$this->assertNotFalse(
				has_action($hook, array( ContactStatus::class, 'forget_badge' )),
				"expected {$hook} to clear the badge transient"
			);
		}
	}

	public function test_creating_a_contact_post_clears_the_badge_transient(): void
	{
		ContactMessagesPage::register();
		set_transient(ContactStatus::BADGE_TRANSIENT, 5, 300);

		self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));

		$this->assertFalse(get_transient(ContactStatus::BADGE_TRANSIENT));
	}

	public function test_trashing_a_contact_post_clears_the_badge_transient(): void
	{
		ContactMessagesPage::register();
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		set_transient(ContactStatus::BADGE_TRANSIENT, 5, 300);

		wp_trash_post($id);

		$this->assertFalse(get_transient(ContactStatus::BADGE_TRANSIENT));
	}

	public function test_the_script_localizes_the_month_options(): void
	{
		self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private', 'post_date' => '2026-09-15 10:00:00' ));
		ContactMessagesPage::enqueue_assets('toplevel_page_' . ContactMessagesPage::SLUG);

		$data = wp_scripts()->get_data('mhm-rentiva-react-contact-messages', 'data');
		$this->assertIsString($data);
		$this->assertStringContainsString('"months":[{"value":"2026-09"', $data);
		$this->assertStringContainsString('"shortcodePagesUrl":"' . admin_url('admin.php?page=mhm-rentiva-shortcode-pages') . '"', $data);
	}
}
