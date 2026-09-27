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
}
