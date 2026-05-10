<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Messages\Admin\MessageDeleteHandler;
use WP_UnitTestCase;

final class MessagesDeleteHandlerTest extends WP_UnitTestCase
{
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
    }

    public function tearDown(): void
    {
        wp_set_current_user( 0 );
        $_POST = array();
        parent::tearDown();
    }

    // Test 1
    public function test_process_trashes_message_posts(): void
    {
        wp_set_current_user( $this->admin_id );

        $id1 = (int) $this->factory->post->create( array(
            'post_type'   => 'mhm_message',
            'post_status' => 'publish',
        ) );
        $id2 = (int) $this->factory->post->create( array(
            'post_type'   => 'mhm_message',
            'post_status' => 'publish',
        ) );

        $count = MessageDeleteHandler::process( array( $id1, $id2 ) );

        $this->assertSame( 2, $count );
        $this->assertSame( 'trash', get_post_status( $id1 ) );
        $this->assertSame( 'trash', get_post_status( $id2 ) );
    }

    // Test 2
    public function test_handle_with_invalid_nonce_calls_wp_die(): void
    {
        wp_set_current_user( $this->admin_id );
        $_POST = array(
            'action' => 'mhm_rentiva_delete_messages',
            'nonce'  => 'bad_nonce',
            'ids'    => array( 1 ),
        );
        $this->expectException( \WPDieException::class );
        MessageDeleteHandler::handle();
    }

    // Test 3
    public function test_process_with_empty_ids_returns_zero(): void
    {
        wp_set_current_user( $this->admin_id );
        $count = MessageDeleteHandler::process( array() );
        $this->assertSame( 0, $count );
    }

    // Test 4
    public function test_process_ignores_non_message_post_types(): void
    {
        wp_set_current_user( $this->admin_id );

        $post_id = (int) $this->factory->post->create( array(
            'post_type'   => 'post',
            'post_status' => 'publish',
        ) );

        $count = MessageDeleteHandler::process( array( $post_id ) );

        $this->assertSame( 0, $count );
        $this->assertSame( 'publish', get_post_status( $post_id ) );
    }
}
