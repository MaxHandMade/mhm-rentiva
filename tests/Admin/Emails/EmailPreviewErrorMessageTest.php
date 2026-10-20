<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * A preview that fails to render told the operator "Connection error: …" -- a
 * hard-coded English sentence, and a wrong one: nothing failed to connect, the
 * template threw. The message is translatable and says what happened.
 *
 * @covers \MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler::handle_preview_email
 */
final class EmailPreviewErrorMessageTest extends WP_Ajax_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (! has_action('wp_ajax_mhmrentiva_preview_email_ajax')) {
            EmailAjaxHandler::register();
        }
    }

    public function tearDown(): void
    {
        $_POST = array();
        parent::tearDown();
    }

    public function test_a_render_failure_is_reported_in_a_translatable_sentence(): void
    {
        add_filter(
            'mhmrentiva_email_body',
            static function (): string {
                throw new \RuntimeException('template exploded');
            }
        );
        add_filter(
            'gettext',
            static function (string $translation, string $text, string $domain): string {
                return 'mhm-rentiva' === $domain && 'Could not render the preview: %s' === $text ? 'TRANSLATED: %s' : $translation;
            },
            10,
            3
        );

        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
        $_POST = array(
            'action'       => 'mhmrentiva_preview_email_ajax',
            'nonce'        => wp_create_nonce('mhmrentiva_email_preview_action'),
            'template_key' => 'booking_cancelled',
            'booking_id'   => '',
        );

        try {
            $this->_handleAjax('mhmrentiva_preview_email_ajax');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success']);
        $this->assertSame('TRANSLATED: template exploded', $response['data']);
    }
}
