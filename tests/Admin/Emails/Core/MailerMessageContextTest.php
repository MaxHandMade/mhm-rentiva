<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Core;

use MHMRentiva\Admin\Emails\Core\Mailer;
use WP_UnitTestCase;

/**
 * Mailer::getMessageContext() reads a thread's status, category and thread id
 * from the thread's ROOT (spec v2.4 §5). A reply record's own status is
 * whatever its writer left on it -- Pro <= 6.2.0 births every admin reply as
 * `pending`, so an e-mail about a reply reported "pending" for a thread the
 * admin had just closed.
 *
 * Fixture shapes mirror the live dev threads (2026-09-28): roots carry
 * thread_id = own ID and NO parent meta row (3228, 3358); 3665's parent is the
 * reply 3664 while its thread_id is the root 3358; 3358's root says
 * `answered` while both customer replies say `pending`.
 *
 * `mhmrentiva_message` is registered by Pro only; in this Lite suite it is an
 * unregistered post type, which wp_insert_post/wp_trash_post/wp_delete_post
 * handle like any other.
 *
 * @covers \MHMRentiva\Admin\Emails\Core\Mailer::getMessageContext
 */
final class MailerMessageContextTest extends WP_UnitTestCase {

	/**
	 * @param array<string, mixed> $meta Meta without the `_mhmrentiva_` prefix.
	 * @param array<string, mixed> $post Overrides for the post row.
	 */
	private function message( array $meta, array $post = array() ): int {
		$id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'mhmrentiva_message',
					'post_status'  => 'publish',
					'post_title'   => 'Bekleme Süresi',
					'post_content' => 'root body',
				),
				$post
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_mhmrentiva_' . $key, $value );
		}
		return $id;
	}

	/** A root as Pro writes it: thread_id = own ID, no parent meta row. */
	private function root( string $status, string $category = 'general' ): int {
		$id = $this->message(
			array(
				'message_status'   => $status,
				'message_category' => $category,
			)
		);
		update_post_meta( $id, '_mhmrentiva_thread_id', $id );
		return $id;
	}

	/** @param array<string, mixed> $post */
	private function reply( int $root_id, int $parent_id, string $status, array $post = array() ): int {
		return $this->message(
			array(
				'thread_id'         => $root_id,
				'parent_message_id' => $parent_id,
				'message_status'    => $status,
				'message_category'  => 'general',
				'message_type'      => 'customer_to_admin',
			),
			$post
		);
	}

	public function test_a_reply_carries_the_root_status_and_category(): void {
		$root  = $this->root( 'closed', 'technical' );
		$reply = $this->reply( $root, $root, 'pending' );

		$context = Mailer::getMessageContext( $reply );

		$this->assertSame( 'closed', $context['message']['status'] );
		$this->assertSame( 'technical', $context['message']['category'] );
		$this->assertSame( (string) $root, $context['message']['thread_id'] );
	}

	public function test_a_reply_keeps_its_own_identity_text_sender_and_booking(): void {
		// Guard: green before AND after. The e-mail is about this record; only
		// the three thread-level fields move to the root.
		$root    = $this->root( 'answered' );
		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		$reply   = $this->reply(
			$root,
			$root,
			'pending',
			array(
				'post_title'   => 'Re: Bekleme Süresi',
				'post_content' => 'reply body',
			)
		);
		update_post_meta( $reply, '_mhmrentiva_customer_name', 'Ayşe Yılmaz' );
		update_post_meta( $reply, '_mhmrentiva_customer_email', 'ayse@example.com' );
		update_post_meta( $reply, '_mhmrentiva_booking_id', $booking );

		$context = Mailer::getMessageContext( $reply );

		$this->assertSame( $reply, $context['message']['id'] );
		$this->assertSame( 'Re: Bekleme Süresi', $context['message']['subject'] );
		$this->assertSame( 'reply body', $context['message']['content'] );
		$this->assertSame( get_post( $reply )->post_date, $context['message']['date'] );
		$this->assertSame( 'Ayşe Yılmaz', $context['customer']['name'] );
		$this->assertSame( 'ayse@example.com', $context['customer']['email'] );
		$this->assertSame( $booking, $context['booking']['id'] );
	}

	public function test_a_chained_reply_reads_the_root_not_its_parent(): void {
		// Live 3665: parent is the reply 3664, thread_id is the root 3358.
		$root   = $this->root( 'answered' );
		$first  = $this->reply( $root, $root, 'pending' );
		$second = $this->reply( $root, $first, 'closed' );

		$this->assertSame( 'answered', Mailer::getMessageContext( $second )['message']['status'] );
	}

	public function test_pre_migration_data_reports_the_root_status_not_the_latest_reply(): void {
		// Live 3358 before Pro's 1.0.8 migration (plan R-5): the root says
		// answered, both customer replies say pending. The context follows the
		// root; the data itself is corrected by the migration, not here.
		$root = $this->root( 'answered' );
		$this->reply( $root, $root, 'pending' );
		$last = $this->reply( $root, $root, 'pending' );

		$this->assertSame( 'answered', Mailer::getMessageContext( $last )['message']['status'] );
	}

	public function test_a_root_without_a_parent_meta_row_reads_itself(): void {
		$root = $this->root( 'closed', 'billing' );
		$this->assertSame( '', get_post_meta( $root, '_mhmrentiva_parent_message_id', true ), 'fixture shape: no parent row' );

		$context = Mailer::getMessageContext( $root );

		$this->assertSame( 'closed', $context['message']['status'] );
		$this->assertSame( 'billing', $context['message']['category'] );
		$this->assertSame( (string) $root, $context['message']['thread_id'] );
	}

	public function test_a_uuid_thread_id_falls_back_to_the_given_record(): void {
		// The legacy admin form wrote UUID thread ids (Pro Core/Messages.php);
		// Pro 1.0.8 rewrites them. Until then the record is its own source.
		$uuid  = '9f1c2d3e-4b5a-4978-8a9b-0c1d2e3f4a5b';
		$reply = $this->message(
			array(
				'thread_id'        => $uuid,
				'message_status'   => 'pending',
				'message_category' => 'billing',
			)
		);

		$context = Mailer::getMessageContext( $reply );

		$this->assertSame( 'pending', $context['message']['status'] );
		$this->assertSame( 'billing', $context['message']['category'] );
		$this->assertSame( $uuid, $context['message']['thread_id'] );
	}

	/**
	 * Every case names a thread the reader must NOT follow. The malformed
	 * cases embed the ID of a real `closed` root: an implementation that casts
	 * with (int) before checking the digits reaches that root for the suffix,
	 * space and plus cases, and one without the leading-zero check does so for
	 * the leading-zero case -- each turns this test red. (The minus case casts
	 * to a negative number and falls back either way; it stays as a guard.)
	 *
	 * @dataProvider unresolvable_thread_ids
	 */
	public function test_a_thread_id_that_names_no_live_message_falls_back( string $case ): void {
		$thread = $this->unresolvable_thread_id( $case );
		$reply  = $this->message(
			array(
				'thread_id'        => $thread,
				'message_status'   => 'pending',
				'message_category' => 'billing',
			)
		);

		$context = Mailer::getMessageContext( $reply );

		$this->assertSame( 'pending', $context['message']['status'] );
		$this->assertSame( 'billing', $context['message']['category'] );
		$this->assertSame( $thread, $context['message']['thread_id'] );
	}

	/** @return array<string, array{0: string}> */
	public static function unresolvable_thread_ids(): array {
		return array(
			'deleted root'        => array( 'deleted' ),
			'trashed root'        => array( 'trashed' ),
			'a booking id'        => array( 'booking' ),
			'zero'                => array( 'zero' ),
			'empty'               => array( 'empty' ),
			'digits then letters' => array( 'suffix' ),
			'leading space'       => array( 'space' ),
			'negative'            => array( 'minus' ),
			'explicit plus'       => array( 'plus' ),
			'leading zero'        => array( 'zero_padded' ),
		);
	}

	private function unresolvable_thread_id( string $case ): string {
		switch ( $case ) {
			case 'deleted':
				$gone = $this->root( 'closed' );
				wp_delete_post( $gone, true );
				return (string) $gone;
			case 'trashed':
				$trashed = $this->root( 'closed' );
				wp_trash_post( $trashed );
				// With EMPTY_TRASH_DAYS = 0 core deletes instead of trashing;
				// this case would then silently repeat 'deleted root'.
				$this->assertSame( 'trash', get_post_status( $trashed ), 'fixture: the root is in the trash' );
				return (string) $trashed;
			case 'booking':
				$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
				update_post_meta( $booking, '_mhmrentiva_message_status', 'closed' );
				return (string) $booking;
			case 'zero':
				return '0';
			case 'empty':
				return '';
		}
		$real = (string) $this->root( 'closed' );
		$map  = array(
			'suffix'      => $real . 'abc',
			'space'       => ' ' . $real,
			'minus'       => '-' . $real,
			'plus'        => '+' . $real,
			'zero_padded' => '0' . $real,
		);
		return $map[ $case ];
	}
}
