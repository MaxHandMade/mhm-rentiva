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
 * the same idea across the PHP/JS boundary, for BOTH ids the new handler
 * depends on: the button it is bound to (`#close-manual-refund`) and the
 * input it reads via `.val()` for the request's `reference` field
 * (`#manual-refund-reference`) must each be the SAME id
 * BookingRefundMetaBox::render() actually prints for a manual_pending
 * booking. The second is the more dangerous mismatch of the two (fix round
 * 1, I1): a wrong button id does nothing, loudly; a wrong `.val()` selector
 * returns `undefined`, `|| ''` in the handler swallows it, and the endpoint
 * silently records a money attestation with an empty reference.
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
     * For every click-bound handler in bindEvents(), extract the AJAX
     * `action` string its own body posts, the CSS selector its binding is
     * attached to, and -- where the handler reads one -- the selector it
     * pulls a `.val()` from for its own request payload (I1, fix round 1:
     * a mismatched button id merely does nothing and is loud; a mismatched
     * `.val()` selector returns `undefined` from jQuery, `|| ''` in the
     * handler swallows it, and the endpoint silently records an empty
     * reference -- so this needs the same anchor the button gets).
     *
     * Two passes, both over the real file, neither hard-coding a name:
     * 1. `$(document).on('click', '#selector', (e) => this.method(e))` gives
     *    method => selector.
     * 2. Every `method(e) {` declaration's offset delimits that method's own
     *    body up to the NEXT such declaration (file order, not a name list).
     *    The `action: '...'` found inside that span is the action the
     *    selector posts; a `$('#selector').val()` found in the SAME span,
     *    if any, is what that handler reads for the request.
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     *         array{action => click selector, action => value-read selector}
     */
    private function extract_action_maps(string $js): array
    {
        preg_match_all(
            "/\\\$\\(document\\)\\.on\\('click',\\s*'([^']+)',\\s*\\(e\\)\\s*=>\\s*this\\.(\\w+)\\(e\\)\\)/",
            $js,
            $bindings,
            PREG_SET_ORDER
        );

        $click_selector_by_method = array();
        foreach ($bindings as $binding) {
            $click_selector_by_method[$binding[2]] = $binding[1];
        }

        preg_match_all('/(\w+)\(e\)\s*\{/', $js, $declarations, PREG_OFFSET_CAPTURE);

        $action_to_click_selector = array();
        $action_to_value_selector = array();

        foreach ($declarations[1] as $index => $declaration) {
            [$method, $offset] = $declaration;

            if (! isset($click_selector_by_method[$method])) {
                // A method that also happens to take a single `(e)` argument
                // but is not itself bound to a click in bindEvents().
                continue;
            }

            $next_offset = $declarations[1][$index + 1][1] ?? strlen($js);
            $body        = substr($js, $offset, $next_offset - $offset);

            if (! preg_match("/action:\\s*'([^']+)'/", $body, $action_match)) {
                continue;
            }

            $action                             = $action_match[1];
            $action_to_click_selector[$action]  = $click_selector_by_method[$method];

            if (preg_match("/\\\$\\('([^']+)'\\)\\.val\\(\\)/", $body, $value_match)) {
                $action_to_value_selector[$action] = $value_match[1];
            }
        }

        return array( $action_to_click_selector, $action_to_value_selector );
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
        [$click_map] = $this->extract_action_maps($this->js_source());

        $this->assertSame(
            '#process-refund',
            $click_map['mhmrentiva_deposit_process_refund'] ?? null,
            'Sanity check on the extraction itself: it must recover the well-known process-refund pairing, '
                . 'or nothing in this file proves the scanner reads deposit-management.js at all.'
        );
    }

    public function test_the_close_manual_refund_handler_is_bound_to_a_selector_the_metabox_actually_prints(): void
    {
        [$click_map] = $this->extract_action_maps($this->js_source());

        $this->assertArrayHasKey(
            'mhmrentiva_close_manual_refund',
            $click_map,
            'No click handler in deposit-management.js posts action: "mhmrentiva_close_manual_refund". '
                . 'Either the handler is missing, or it no longer matches the binding pattern this scan looks for.'
        );

        $selector = $click_map['mhmrentiva_close_manual_refund'];
        $id       = ltrim($selector, '#');

        $html = $this->render_manual_pending_box_as_authorised_administrator();

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
     * I1 (fix round 1): the button id is only half the pairing. The handler
     * also reads `$('#manual-refund-reference').val()` for the request's
     * `reference` field, off a DIFFERENT selector than the button's own id.
     * A mismatch here is worse than a mismatched button: the button at
     * least does nothing and is loud, while jQuery returns `undefined` for
     * a missing selector, `|| ''` swallows it, and close_manual_refund()
     * happily records an empty reference with no error surfaced anywhere.
     * Uses the same already-proven body-span extractor as the button test
     * above (see test_the_extraction_correctly_recovers_a_known_sibling_pairing
     * for that mechanism's own read-proving control) -- this only adds a
     * second regex over the identical, already-isolated span.
     */
    public function test_the_close_manual_refund_handler_reads_a_reference_selector_the_metabox_actually_prints(): void
    {
        [, $value_map] = $this->extract_action_maps($this->js_source());

        $this->assertArrayHasKey(
            'mhmrentiva_close_manual_refund',
            $value_map,
            'handleCloseManualRefund() no longer reads any $(\'...\').val() at all -- either the reference '
                . 'field was dropped, or it no longer matches the pattern this scan looks for.'
        );

        $selector = $value_map['mhmrentiva_close_manual_refund'];
        $id       = ltrim($selector, '#');

        $html = $this->render_manual_pending_box_as_authorised_administrator();

        $this->assertStringContainsString(
            'id="' . $id . '"',
            $html,
            sprintf(
                'deposit-management.js reads %1$s for the reference field, but the refund metabox\'s rendered '
                    . 'markup for a manual_pending booking contains no element with id="%2$s". A mismatch here is '
                    . 'silent: jQuery returns undefined, "|| \'\'" swallows it, and the endpoint records an empty '
                    . 'reference with no error anywhere.',
                $selector,
                $id
            )
        );
    }

    private function render_manual_pending_box_as_authorised_administrator(): string
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

        return (string) ob_get_clean();
    }

    /**
     * Task 12 (slice 5), correction #2: the same class of gap Task 11 hit --
     * the plan's Step 5 rendered two more buttons and again said nothing
     * about binding them. Both new handlers extend the SAME extraction this
     * file already proved reads the shipped JS correctly
     * (test_the_extraction_correctly_recovers_a_known_sibling_pairing above)
     * rather than adding a second scanner.
     */
    public function test_the_review_cancel_and_refund_handler_is_bound_to_a_selector_the_metabox_actually_prints(): void
    {
        [$click_map] = $this->extract_action_maps($this->js_source());

        $this->assertArrayHasKey(
            'mhmrentiva_review_cancel_and_refund',
            $click_map,
            'No click handler in deposit-management.js posts action: "mhmrentiva_review_cancel_and_refund". '
                . 'Either the handler is missing, or it no longer matches the binding pattern this scan looks for.'
        );

        $selector = $click_map['mhmrentiva_review_cancel_and_refund'];
        $id       = ltrim($selector, '#');

        $html = $this->render_needs_review_box_as_authorised_administrator();

        $this->assertStringContainsString(
            'id="' . $id . '"',
            $html,
            sprintf(
                'deposit-management.js binds a click handler to %1$s and posts action '
                    . '"mhmrentiva_review_cancel_and_refund", but the refund metabox\'s rendered markup for a '
                    . 'needs_review booking contains no element with id="%2$s".',
                $selector,
                $id
            )
        );
    }

    public function test_the_review_dismiss_handler_is_bound_to_a_selector_the_metabox_actually_prints(): void
    {
        [$click_map] = $this->extract_action_maps($this->js_source());

        $this->assertArrayHasKey(
            'mhmrentiva_review_dismiss',
            $click_map,
            'No click handler in deposit-management.js posts action: "mhmrentiva_review_dismiss". '
                . 'Either the handler is missing, or it no longer matches the binding pattern this scan looks for.'
        );

        $selector = $click_map['mhmrentiva_review_dismiss'];
        $id       = ltrim($selector, '#');

        $html = $this->render_needs_review_box_as_authorised_administrator();

        $this->assertStringContainsString(
            'id="' . $id . '"',
            $html,
            sprintf(
                'deposit-management.js binds a click handler to %1$s and posts action '
                    . '"mhmrentiva_review_dismiss", but the refund metabox\'s rendered markup for a '
                    . 'needs_review booking contains no element with id="%2$s".',
                $selector,
                $id
            )
        );
    }

    /**
     * (b)'s gerekçe (reason) field is a `.val()` read exactly like Task 11's
     * manual-refund-reference field, and correction #2 names it the more
     * dangerous mismatch of the two for the same reason I1 did there: a
     * wrong button id does nothing, loudly; a wrong `.val()` selector returns
     * `undefined`, `|| ''` swallows it, and review_dismiss() would reject
     * every request with "say why no refund is due" while an operator
     * insists they typed one.
     */
    public function test_the_review_dismiss_handler_reads_a_reason_selector_the_metabox_actually_prints(): void
    {
        [, $value_map] = $this->extract_action_maps($this->js_source());

        $this->assertArrayHasKey(
            'mhmrentiva_review_dismiss',
            $value_map,
            'handleReviewDismiss() no longer reads any $(\'...\').val() at all -- either the reason field '
                . 'was dropped, or it no longer matches the pattern this scan looks for.'
        );

        $selector = $value_map['mhmrentiva_review_dismiss'];
        $id       = ltrim($selector, '#');

        $html = $this->render_needs_review_box_as_authorised_administrator();

        $this->assertStringContainsString(
            'id="' . $id . '"',
            $html,
            sprintf(
                'deposit-management.js reads %1$s for the dismiss reason, but the refund metabox\'s rendered '
                    . 'markup for a needs_review booking contains no element with id="%2$s". A mismatch here is '
                    . 'silent: jQuery returns undefined, "|| \'\'" swallows it, and every dismissal is rejected '
                    . 'as if the operator typed nothing.',
                $selector,
                $id
            )
        );
    }

    /**
     * The positive control both needs_review assertions above need: without
     * it, a fixture that rendered none of the three elements for any
     * unrelated reason (the actor failing MoneyAuthorization, a status other
     * than needs_review) would make either previous test pass or fail for
     * the wrong reason regardless of the id match.
     */
    public function test_the_needs_review_controls_render_for_an_authorised_actor_on_a_needs_review_booking(): void
    {
        $html = $this->render_needs_review_box_as_authorised_administrator();

        $this->assertStringContainsString(
            'id="review-cancel-and-refund"',
            $html,
            'Positive control: an authorised administrator on a needs_review booking must see the cancel-and-refund button.'
        );
        $this->assertStringContainsString(
            'id="review-dismiss"',
            $html,
            'Positive control: an authorised administrator on a needs_review booking must see the dismiss button.'
        );
        $this->assertStringContainsString(
            'id="refund-review-dismiss-reason"',
            $html,
            'Positive control: the dismiss reason field must render alongside the buttons.'
        );
    }

    private function render_needs_review_box_as_authorised_administrator(): string
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($admin_id);

        $booking_id = self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, RefundStatus::META_KEY, RefundStatus::NEEDS_REVIEW);

        ob_start();
        BookingRefundMetaBox::render(get_post($booking_id));

        return (string) ob_get_clean();
    }

    /**
     * The positive control both assertions above need: without it, a
     * fixture that rendered neither element for any unrelated reason (the
     * actor failing MoneyAuthorization, a status other than manual_pending)
     * would make either previous test pass or fail for the wrong reason
     * regardless of the id match.
     */
    public function test_the_control_button_and_reference_field_render_for_an_authorised_actor_on_a_manual_pending_booking(): void
    {
        $html = $this->render_manual_pending_box_as_authorised_administrator();

        $this->assertStringContainsString(
            'id="close-manual-refund"',
            $html,
            'Positive control: an authorised administrator on a manual_pending booking must see the button at all.'
        );
        $this->assertStringContainsString(
            'id="manual-refund-reference"',
            $html,
            'Positive control: the reference field must render alongside the button.'
        );
    }
}
