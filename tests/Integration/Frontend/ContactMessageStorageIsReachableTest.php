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
		foreach (array( 'mhmrentiva_contact', 'mhmrentiva_app_log', 'mhmrentiva_email_log' ) as $type) {
			$object = get_post_type_object($type);

			$this->assertNotNull($object, sprintf('%s must be registered.', $type));
			$this->assertFalse($object->can_export, sprintf('%s must not be reachable through Tools -> Export.', $type));
		}
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

	/**
	 * "You read them yourself" has to be true of the whole record, not of the
	 * two fields the post itself carries. The rest live in underscore-prefixed
	 * meta, which the Custom Fields box hides, so the panel is the only path.
	 */
	public function test_the_details_panel_prints_every_stored_field_escaped(): void
	{
		$message_id = self::factory()->post->create(array(
			'post_type'   => ContactMessagePostType::TYPE,
			'post_status' => 'private',
			'post_title'  => 'Contact Message - Alice',
		));

		update_post_meta($message_id, '_mhmrentiva_contact_email', 'alice@example.com');
		update_post_meta($message_id, '_mhmrentiva_contact_phone', '+90 555 000 00 00');
		update_post_meta($message_id, '_mhmrentiva_contact_ip_address', '203.0.113.9');
		update_post_meta($message_id, '_mhmrentiva_contact_user_agent', 'Mozilla/5.0 <script>alert(1)</script>');

		ob_start();
		ContactMessagePostType::render_details_box(get_post($message_id));
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('alice@example.com', $html);
		$this->assertStringContainsString('+90 555 000 00 00', $html);
		$this->assertStringContainsString('203.0.113.9', $html, 'The IP the privacy section sets in bold must be readable.');
		$this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'A stored user-agent must not execute.');
		$this->assertStringContainsString('&lt;script&gt;', $html);
	}

	/**
	 * A field the submission never set must not print an empty row.
	 */
	public function test_the_details_panel_skips_fields_with_no_value(): void
	{
		$message_id = self::factory()->post->create(array(
			'post_type'   => ContactMessagePostType::TYPE,
			'post_status' => 'private',
		));
		update_post_meta($message_id, '_mhmrentiva_contact_email', 'bob@example.com');

		ob_start();
		ContactMessagePostType::render_details_box(get_post($message_id));
		$html = (string) ob_get_clean();

		$this->assertSame(1, substr_count($html, '<tr>'), 'Only the one field that has a value may render a row.');
	}

	/**
	 * The form stores integer 0 for vehicle_id and rating when the enquiry
	 * names neither. A "Vehicle: 0" row is noise, and get_the_title( 0 ) falls
	 * back to the global post, which printed the message's own title.
	 */
	public function test_a_stored_zero_is_treated_as_absent(): void
	{
		$message_id = self::factory()->post->create(array(
			'post_type'   => ContactMessagePostType::TYPE,
			'post_status' => 'private',
			'post_title'  => 'Contact Message - Dave',
		));
		update_post_meta($message_id, '_mhmrentiva_contact_vehicle_id', '0');
		update_post_meta($message_id, '_mhmrentiva_contact_rating', '0');
		update_post_meta($message_id, '_mhmrentiva_contact_email', 'dave@example.com');

		ob_start();
		ContactMessagePostType::render_details_box(get_post($message_id));
		$html = (string) ob_get_clean();

		$this->assertSame(1, substr_count($html, '<tr>'), 'Only the e-mail row may render.');
		$this->assertStringNotContainsString('Contact Message - Dave', $html, 'get_the_title(0) must not leak the post\'s own title into the Vehicle row.');
	}

	/**
	 * The list screen has to show enough to find a record without opening it.
	 */
	public function test_the_list_screen_shows_the_sender_address(): void
	{
		$columns = ContactMessagePostType::columns(array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ));

		$this->assertArrayHasKey('mhmrentiva_email', $columns);
		$this->assertSame('date', array_key_last($columns), 'Date stays last, as on every core list table.');

		$message_id = self::factory()->post->create(array( 'post_type' => ContactMessagePostType::TYPE ));
		update_post_meta($message_id, '_mhmrentiva_contact_email', 'carol@example.com');

		ob_start();
		ContactMessagePostType::column('mhmrentiva_email', $message_id);
		$this->assertSame('carol@example.com', ob_get_clean());
	}
}
