<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Utilities;

use MHMRentiva\Admin\Utilities\Uninstall\Uninstaller;
use WP_UnitTestCase;

/**
 * Uninstall takes the add-on's conversations with it: the `_mhmrentiva%` meta
 * sweep removes whose words they are and would leave the words behind.
 *
 * @coversNothing
 */
final class UninstallRemovesConversationsTest extends WP_UnitTestCase {

	public function test_a_conversation_and_its_replies_are_deleted_and_a_post_is_not(): void
	{
		$root    = self::factory()->post->create(
			array(
				'post_type'    => 'mhmrentiva_message',
				'post_content' => 'Call me on 0532 000 00 00',
			)
		);
		$reply   = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_message',
				'post_status' => 'draft',
			)
		);
		$foreign = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $root, '_mhmrentiva_customer_email', 'ayse@example.com' );

		Uninstaller::uninstall_direct( false );

		$this->assertNull( get_post( $root ) );
		$this->assertNull( get_post( $reply ), 'Whatever its status.' );
		$this->assertNotNull( get_post( $foreign ) );
	}
}
