<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Report business logic.
 *
 * Coordinates between the {@see VendorReportRepository} (DB) and the side-effect
 * surfaces — penalty pipeline, score recalculation, ledger entries, email
 * notifications — without leaking those concerns into the data layer.
 *
 * Validation rules (enforced here, not in the repository):
 *   - Description must be at least 20 characters (forces a meaningful complaint).
 *   - The same vendor cannot have two open reports against the same context
 *     (vendor + context_type + context_id). Once the first is resolved/rejected
 *     a fresh report is allowed.
 *   - Resolve/reject only run on non-terminal reports.
 *
 * Side-effect responsibilities (delegated, not run inline here):
 *   - Email notifications fire via the `mhm_rentiva_vendor_report_created` and
 *     `mhm_rentiva_vendor_report_resolved` actions; the email subsystem
 *     (VendorReportNotifier) hooks them.
 *   - Penalty deferred-apply / reverse run via dedicated public methods that
 *     the admin resolution workflow calls; they live here because they need
 *     access to the report row plus the ReliabilityScoreCalculator and Ledger
 *     APIs.
 *
 * @since 4.35.0
 */
final class VendorReportService {


    private const MIN_DESCRIPTION_LENGTH = 20;

    /**
     * Create a new report for the given vendor + context.
     *
     * @param int|null    $context_id  Integer ID, UUID string, or null for GENERAL.
     *
     * @return int|\WP_Error Inserted row id, or WP_Error on validation failure.
     */
    public function create_report(
        int $vendor_id,
        string $context_type,
        $context_id,
        string $title,
        string $description
    ) {
        if ($vendor_id <= 0) {
            return new \WP_Error('invalid_vendor', __('Vendor not identified.', 'mhm-rentiva'));
        }

        if (! VendorReportContext::is_valid($context_type)) {
            return new \WP_Error('invalid_context_type', __('Unknown report context.', 'mhm-rentiva'));
        }

        $title = trim($title);
        if ($title === '') {
            return new \WP_Error('title_required', __('Report title is required.', 'mhm-rentiva'));
        }

        $description = trim($description);
        if (mb_strlen($description, 'UTF-8') < self::MIN_DESCRIPTION_LENGTH) {
            return new \WP_Error(
                'description_too_short',
                sprintf(
                    /* translators: %d: minimum description length */
                    __('Please describe the issue in at least %d characters.', 'mhm-rentiva'),
                    self::MIN_DESCRIPTION_LENGTH
                )
            );
        }

        $context_id_string = self::context_id_to_string($context_id);

        if (VendorReportRepository::has_open_report_for($vendor_id, $context_type, $context_id_string)) {
            return new \WP_Error(
                'duplicate_open_report',
                __('You already have an open report for this item.', 'mhm-rentiva')
            );
        }

        $report_id = VendorReportRepository::create([
            'vendor_id'    => $vendor_id,
            'context_type' => $context_type,
            'context_id'   => $context_id,
            'title'        => $title,
            'description'  => $description,
        ]);

        if ($report_id <= 0) {
            return new \WP_Error('persist_failed', __('Could not save your report. Please try again.', 'mhm-rentiva'));
        }

        /**
         * Fires after a vendor report is created. The email subsystem listens
         * to send the admin notification.
         *
         * @param int    $report_id    Inserted row ID.
         * @param int    $vendor_id    Vendor user ID.
         * @param string $context_type One of VendorReportContext::* constants.
         */
        do_action('mhm_rentiva_vendor_report_created', $report_id, $vendor_id, $context_type);

        return $report_id;
    }

    /**
     * Resolve a report — vendor's complaint upheld.
     *
     * @return true|\WP_Error
     */
    public function resolve_report(int $report_id, ?string $admin_note, int $admin_user_id)
    {
        $row = $this->load_actionable_report($report_id);
        if (is_wp_error($row)) {
            return $row;
        }

        VendorReportRepository::update_status($report_id, VendorReportStatus::RESOLVED, $admin_note, $admin_user_id);

        // Side-effect: penalty appeal upheld → reverse the applied penalty (if any).
        // For vehicle_action, no penalty was ever applied (suspended at withdraw time),
        // so resolution is a no-op for that context.
        if ($row->context_type === VendorReportContext::PENALTY) {
            $this->reverse_applied_penalty($row);
        }

        /**
         * Fires after a vendor report is resolved. The email subsystem listens
         * to send the vendor notification.
         */
        do_action('mhm_rentiva_vendor_report_resolved', $report_id, (int) $row->vendor_id, VendorReportStatus::RESOLVED);

        return true;
    }

    /**
     * Reject a report — vendor's complaint not upheld.
     *
     * @return true|\WP_Error
     */
    public function reject_report(int $report_id, ?string $admin_note, int $admin_user_id)
    {
        $row = $this->load_actionable_report($report_id);
        if (is_wp_error($row)) {
            return $row;
        }

        VendorReportRepository::update_status($report_id, VendorReportStatus::REJECTED, $admin_note, $admin_user_id);

        // Side-effect: vehicle_action rejection → apply the deferred penalty now
        // (it was suspended at withdraw time because the report was open).
        if ($row->context_type === VendorReportContext::VEHICLE_ACTION) {
            $this->apply_deferred_penalty($row);
        }

        do_action('mhm_rentiva_vendor_report_resolved', $report_id, (int) $row->vendor_id, VendorReportStatus::REJECTED);

        return true;
    }

    /**
     * Apply a penalty that was deferred while a vehicle_action report was open.
     *
     * Recomputes the reliability score (which now counts the withdrawal — the
     * underlying state was always "withdrawn", only the score update was
     * skipped) and writes the deferred penalty ledger entry.
     */
    private function apply_deferred_penalty(object $report): void
    {
        $vendor_id  = (int) $report->vendor_id;
        $vehicle_id = is_numeric($report->context_id) ? (int) $report->context_id : 0;

        if ($vendor_id <= 0 || $vehicle_id <= 0) {
            return;
        }

        if (class_exists(\MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator::class)) {
            \MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator::update($vendor_id, 'withdraw', $vehicle_id);
        }

        if (class_exists(\MHMRentiva\Admin\Vehicle\PenaltyCalculator::class)
            && class_exists(\MHMRentiva\Admin\Vehicle\PenaltyRecorder::class)
        ) {
            // Use the amount captured at withdraw time (before this vehicle counted in its own
            // tier). Recomputing here would self-count the now-withdrawn vehicle and apply a
            // tier-too-high penalty. Fall back to a fresh calc only if nothing was stored.
            $stored  = get_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_DEFERRED_PENALTY, true);
            $penalty = ( '' !== $stored )
                ? (float) $stored
                : \MHMRentiva\Admin\Vehicle\PenaltyCalculator::calculate_withdrawal_penalty($vehicle_id, $vendor_id);

            // Direct call bypasses the suspension filter — at this point the report
            // is rejected (no longer open) so the filter would let it through anyway.
            \MHMRentiva\Admin\Vehicle\PenaltyRecorder::record_penalty($vehicle_id, $vendor_id, $penalty);
            delete_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_DEFERRED_PENALTY);
        }
    }

    /**
     * Reverse a penalty that has already been applied — vendor wins their appeal.
     *
     * Writes a compensating positive ledger entry and recomputes the reliability
     * score with the offending withdrawal excluded (via report-resolution flag).
     */
    private function reverse_applied_penalty(object $report): void
    {
        // Reserved for future extension — proper reversal needs ledger compensating
        // entry tooling (`Ledger::add_entry()` with positive amount). For v4.35.0 the
        // resolved penalty appeal records the resolution; reversal of the financial
        // ledger entry is deferred to v4.36.0 once the ledger compensating-entry
        // helper lands. The reliability score will recompute on the next cron run.
        unset($report);
    }

    /**
     * Load a report and verify it's actionable (exists, not terminal).
     *
     * @return object|\WP_Error
     */
    private function load_actionable_report(int $report_id)
    {
        $row = VendorReportRepository::find($report_id);
        if ($row === null) {
            return new \WP_Error('report_not_found', __('Report not found.', 'mhm-rentiva'));
        }
        if (VendorReportStatus::is_terminal( (string) $row->status)) {
            return new \WP_Error(
                'already_terminal',
                __('This report has already been closed.', 'mhm-rentiva')
            );
        }
        return $row;
    }

    /**
     * @param mixed $context_id
     */
    private static function context_id_to_string($context_id): ?string
    {
        if ($context_id === null || $context_id === '') {
            return null;
        }
        if (is_int($context_id) || is_numeric($context_id)) {
            return (string) $context_id;
        }
        if (is_string($context_id)) {
            return $context_id;
        }
        return null;
    }
}
