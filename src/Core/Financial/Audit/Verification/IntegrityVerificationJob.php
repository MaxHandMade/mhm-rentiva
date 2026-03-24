<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Verification;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scheduled Job for Daily Ledger Integrity Checks.
 */
class IntegrityVerificationJob
{

    public const CRON_HOOK = 'mhm_rentiva_daily_integrity_check';

    /**
     * Initializes the cron job hooks.
     */
    public static function register(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'execute']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Executes the integrity verification.
     */
    public static function execute(): void
    {
        try {
            $service = new IntegrityVerificationService();
            $service->verify_ledger_integrity();
        } catch (\Exception $e) {
            // Log catastrophic failure of the job itself
            if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::critical(
                    'Integrity Verification Job FAILED to execute: ' . $e->getMessage(),
                    ['trace' => $e->getTraceAsString()],
                    \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
                );
            }
        }
    }

    /**
     * Clear the scheduled event on plugin deactivation.
     */
    public static function clear(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
}
