<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use WP_UnitTestCase;

/**
 * Task 11 correction #2/#3 (slice 5): the plan's Step 4 only rendered a
 * button; a button with no bound handler does nothing. This ecosystem has
 * already measured exactly this failure shape once: manual-booking-meta.js
 * reads `#mhmrentiva_manual_status`, a selector that does not exist in the
 * markup its own sibling PHP renders (the real id is
 * `mhmrentiva_manual_booking_status`), jQuery silently returns `''`, and the
 * selected status is dropped -- invisible to `composer check-js-correctness`
 * (`no-undef` cannot see a string literal used as a CSS selector).
 *
 * RefundGateAgreementTest already proves two PHP surfaces (the deposit box's
 * button, the refund box's link) cannot drift from each other. This file is
 * the same idea across the PHP/JS boundary: the id the new
 * `#close-manual-refund` handler in deposit-management.js is
 * bound to must be the SAME id BookingRefundMetaBox::render() actually
 * prints for a manual_pending booking.
 *
 * The extraction reads the shipped JS source rather than hard-coding the
 * selector as a literal on both sides of this test -- a literal on both
 * sides would still pass if a future edit changed one copy and not the
 * other, which is exactly the failure this file exists to catch.
 */
final class ManualRefundButtonHandlerPairingTest extends WP_UnitTestCase
{
    private function js_source(): string
    {
        $path     = dirname(__DIR__, 4) . '/assets/js/admin/deposit-management.js';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'deposit-management.js must be readable at ' . $path);

        return (string) $contents;
    }

    /**
     * Map every AJAX `action` string a click handler posts to the CSS
     * selector its binding in bindEvents() is attached to.
     *
     * Two passes, both over the real file, neither hard-coding a name:
     * 1. `$(document).on('click', '#selector', (e) => this.method(e))` gives
     *    method => selector.
     * 2. Every `method(e) {` declaration's offset delimits that method's own
     *    body up to the NEXT such declaration (file order, not a name list),
     *    and the `action: '...'` found inside that span is the action that
     *    selector posts.
     *
     * @return array<string, string> action => selector
     */
    private function extract_action_to_selector_map(string $js): array
    {
        preg_match_all(
            "/\\\$\\(document\\)\\.on\\('click',\\s*'([^']+)',\\s*\\(e\\)\\s*=>\\s*this\\.(\\w+)\\(e\\)\\)/",
            $js,
            $bindings,
            PREG_SET_ORDER
        );

        $selector_by_method = array();
        foreach ($bindings as $binding) {
            $selector_by_method[$binding[2]] = $binding[1];
        }

        preg_match_all('/(\w+)\(e\)\s*\{/', $js, $declarations, PREG_OFFSET_CAPTURE);

        $map = array();
        foreach ($declarations[1] as $index => $declaration) {
            [$method, $offset] = $declaration;

            if (! isset($selector_by_method[$method])) {
                // A method that also happens to take a single `(e)` argument
                // but is not itself bound to a click in bindEvents().
                continue;
            }

            $next_offset = $declarations[1][$index + 1][1] ?? strlen($js);
            $body        = substr($js, $offset, $next_offset - $offset);

            if (preg_match("/action:\\s*'([^']+)'/", $body, $action_match)) {
                $map[$action_match[1]] = $selector_by_method[$method];
            }
        }

        return $map;
    }

    /**
     * The read-proving control this ecosystem requires of any scan
     * (correction #3): a broken extraction that silently returns an empty
     * or wrong map would not necessarily fail loudly on its own -- this
     * proves the SAME mechanism, over the SAME file, correctly recovers a
     * long-standing, known-good pairing (#process-refund, the button
     * RefundGateAgreementTest already exercises) before the primary
     * assertion below is trusted to mean anything about the new handler.
     */
    public function test_the_extraction_correctly_recovers_a_known_sibling_pairing(): void
    {
        $map = $this->extract_action_to_selector_map($this->js_source());

        $this->assertSame(
            '#process-refund',
            $map['mhmrentiva_deposit_process_refund'] ?? null,
            'Sanity check on the extraction itself: it must recover the well-known process-refund pairing, '
                . 'or nothing in this file proves the scanner reads deposit-management.js at all.'
        );
    }

    public function test_the_close_manual_refund_handler_is_bound_to_a_selector_the_metabox_actually_prints(): void
    {
        $map = $this->extract_action_to_selector_map($this->js_source());

        $this->assertArrayHasKey(
            'mhmrentiva_close_manual_refund',
            $map,
            'No click handler in deposit-management.js posts action: "mhmrentiva_close_manual_refund". '
                . 'Either the handler is missing, or it no longer matches the binding pattern this scan looks for.'
        );

        $selector = $map['mhmrentiva_close_manual_refund'];
        $id       = ltrim($selector, '#');

        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);

        $booking_id = self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, RefundStatus::META_KEY, RefundStatus::MANUAL_PENDING);

        ob_start();
        BookingRefundMetaBox::render(get_post($booking_id));
        $html = (string) ob_get_clean();

        $this->assertStringContainsString(
            'id="' . $id . '"',
            $html,
            sprintf(
                'deposit-management.js binds a click handler to %1$s and posts action '
                    . '"mhmrentiva_close_manual_refund", but the refund metabox\'s rendered markup for a '
                    . 'manual_pending booking contains no element with id="%2$s". This is exactly the class of '
                    . 'bug manual-booking-meta.js had -- a selector/id mismatch invisible to composer '
                    . 'check-js-correctness.',
                $selector,
                $id
            )
        );
    }

    /**
     * The positive control the assertion above needs: without it, a fixture
     * that rendered no button for any unrelated reason (the actor failing
     * MoneyAuthorization, a status other than manual_pending) would make the
     * previous test pass or fail for the wrong reason regardless of the
     * id match.
     */
    public function test_the_control_button_id_renders_for_an_authorised_actor_on_a_manual_pending_booking(): void
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);

        $booking_id = self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, RefundStatus::META_KEY, RefundStatus::MANUAL_PENDING);

        ob_start();
        BookingRefundMetaBox::render(get_post($booking_id));
        $html = (string) ob_get_clean();

        $this->assertStringContainsString(
            'id="close-manual-refund"',
            $html,
            'Positive control: an authorised administrator on a manual_pending booking must see the control at all.'
        );
    }
}
