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
 *
 * The details panel and list columns moved to the React screen (4.4.0); their
 * field coverage lives in ContactMessagesReadRestTest.
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
		$this->assertFalse(
			$object->can_export,
			'Tools -> Export gates on the `export` capability alone and export_wp() checks nothing per type, '
			. 'so leaving this true would hand a shop manager a WXR file of every sender\'s e-mail, phone and IP.'
		);
	}

	/**
	 * Same class, swept: none of this plugin's internal record types may be
	 * exportable, because that path bypasses their capability gates entirely.
	 */
	public function test_no_internal_record_type_is_exportable(): void
	{
		// Derived from the registry, not from a list. The hard-coded version of
		// this test passed while `mhmrentiva_booking` -- the largest personal
		// data store in the plugin -- stayed exportable, because it simply was
		// not one of the three names written down.
		// Scoped to this plugin's NON-PUBLIC types. A public type -- the vehicle
		// catalogue -- is ordinary content a site owner may legitimately move
		// between installs; a non-public one is internal data whose whole point
		// is that it is administrator-only, which Tools -> Export contradicts.
		$exportable = array();

		foreach (get_post_types(array( 'can_export' => true ), 'objects') as $type => $object) {
			if (strpos($type, 'mhmrentiva') !== 0 || $object->public) {
				continue;
			}

			$exportable[] = $type;
		}

		$this->assertSame(
			array(),
			$exportable,
			'Every post type this plugin registers holds operational or personal data that Tools -> Export '
			. 'would hand to anyone with the `export` capability, bypassing the capabilities the type declares.'
		);
	}

	/**
	 * `edit.php` gates a list screen on the post type's own `edit_posts`, so a
	 * type with an admin UI that inherits `post` is reachable by URL by anyone
	 * who can write a post. Both record types with a UI hold personal data --
	 * the contact rows and the activity log's per-request IP and user-agent --
	 * so both must gate on manage_options.
	 */
	public function test_record_screens_with_a_ui_are_administrator_only(): void
	{
		foreach (array( 'mhmrentiva_contact', 'mhmrentiva_app_log' ) as $type) {
			$object = get_post_type_object($type);

			$this->assertNotNull($object);
			$this->assertSame(
				'manage_options',
				$object->cap->edit_posts,
				sprintf('%s: edit.php gates on this capability, so an author would otherwise reach the list.', $type)
			);
			$this->assertSame('manage_options', $object->cap->read_private_posts, $type);
			$this->assertSame('manage_options', $object->cap->delete_posts, $type);
		}

		$author = self::factory()->user->create(array( 'role' => 'author' ));
		wp_set_current_user($author);
		$this->assertFalse(current_user_can(get_post_type_object('mhmrentiva_app_log')->cap->edit_posts));
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

		// Every role below administrator, not just the obviously powerless one.
		// The type used to inherit `post`, which handed editors -- and, because
		// WooCommerce is a hard dependency, shop managers -- read and delete
		// rights over other people's contact details by inheritance.
		foreach (array( 'editor', 'shop_manager', 'author', 'contributor', 'subscriber' ) as $role) {
			if (null === get_role($role)) {
				continue;
			}

			wp_set_current_user(self::factory()->user->create(array( 'role' => $role )));

			$this->assertFalse(
				current_user_can('edit_post', $message_id),
				sprintf('Role "%s" must not reach records holding another person\'s contact details.', $role)
			);
			$this->assertFalse(
				current_user_can('delete_post', $message_id),
				sprintf('Role "%s" must not be able to delete a contact message.', $role)
			);
			$this->assertFalse(
				current_user_can(get_post_type_object(ContactMessagePostType::TYPE)->cap->edit_posts),
				sprintf('Role "%s" must not reach the list screen, which edit.php gates on this capability.', $role)
			);
		}

		wp_set_current_user($admin);
		$this->assertNotFalse(wp_delete_post($message_id, true));
		$this->assertNull(get_post($message_id));
	}

}
