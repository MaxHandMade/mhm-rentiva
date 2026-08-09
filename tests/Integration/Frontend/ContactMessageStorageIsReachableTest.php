<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

/**
 * The contact form's storage must stay reachable from wp-admin.
 *
 * Every submission holds the sender's name, e-mail address, phone number,
 * company, message, IP address and user-agent. Until 6.0.1 the type those
 * rows are written to was never registered, so WordPress could not list,
 * open or delete them: the site owner held personal data they had no way to
 * read or erase, while readme.txt's Privacy section described the records as
 * manageable. These tests lock the registration and the properties that make
 * the screen usable and safe, so the storage cannot go dark again.
 */
final class ContactMessageStorageIsReachableTest extends \WP_UnitTestCase
{
	public function test_the_storage_type_is_registered(): void
	{
		$this->assertTrue(
			post_type_exists(ContactMessagePostType::TYPE),
			'The contact form writes rows of this type; unregistered, WordPress cannot list or delete them.'
		);
	}

	public function test_the_screen_exists_but_the_type_is_not_public(): void
	{
		$object = get_post_type_object(ContactMessagePostType::TYPE);

		$this->assertNotNull($object);
		$this->assertTrue($object->show_ui, 'Without an admin UI the records are unreadable and undeletable.');
		$this->assertFalse($object->public, 'A contact message must never be addressable from the front end.');
		$this->assertFalse($object->publicly_queryable);
		$this->assertFalse($object->query_var, 'The type claims no public query var.');
		$this->assertFalse($object->show_in_rest, 'These rows are not REST-exposed.');
		$this->assertFalse($object->has_archive);
	}

	/**
	 * Submissions come from the form only. An "Add New" screen would be a
	 * blank record with none of the meta the list screen reads.
	 */
	public function test_nobody_can_create_one_by_hand(): void
	{
		$object = get_post_type_object(ContactMessagePostType::TYPE);

		$this->assertSame('do_not_allow', $object->cap->create_posts);

		$editor = self::factory()->user->create_and_get(array( 'role' => 'administrator' ));
		wp_set_current_user($editor->ID);
		$this->assertFalse(current_user_can($object->cap->create_posts));
	}

	/**
	 * The record a submission produces is readable and deletable by a user who
	 * may edit others' content, and invisible to a subscriber.
	 */
	public function test_a_stored_message_can_be_read_and_deleted_by_an_administrator(): void
	{
		$message_id = self::factory()->post->create(array(
			'post_type'   => ContactMessagePostType::TYPE,
			'post_status' => 'private',
			'post_title'  => 'Contact Message - Test',
		));

		$admin = self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($admin);
		$this->assertTrue(current_user_can('edit_post', $message_id));
		$this->assertTrue(current_user_can('delete_post', $message_id));

		$subscriber = self::factory()->user->create(array( 'role' => 'subscriber' ));
		wp_set_current_user($subscriber);
		$this->assertFalse(
			current_user_can('edit_post', $message_id),
			'A subscriber must not reach records holding another person\'s contact details.'
		);

		wp_set_current_user($admin);
		$this->assertNotFalse(wp_delete_post($message_id, true));
		$this->assertNull(get_post($message_id));
	}
}
