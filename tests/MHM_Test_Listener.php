<?php

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;

/**
 * MHM Rentiva Test Listener.
 * Implements the "Hybrid Reset" cleanup strategy.
 */
class MHM_Test_Listener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function endTestSuite(TestSuite $suite): void
    {
        // Only run cleanup if we are in an ISOLATED run (Hybrid Strategy Mode B)
        if (! defined('MHM_TEST_ISOLATION_PREFIX') || ! defined('MHM_TEST_IS_ISOLATED') || ! MHM_TEST_IS_ISOLATED) {
            return;
        }

        $prefix = MHM_TEST_ISOLATION_PREFIX;

        // Safety Guard Loop
        // Ensure prefix is not empty and looks like our generated pattern 'wptests_'.
        if (empty($prefix) || ! str_starts_with($prefix, 'wptests_')) {
            fwrite(STDERR, "\n[MHM_Test_Listener] SAFETY ABORT: Prefix '$prefix' does not match safe pattern.\n");
            return;
        }

        $this->dropTabsWithPrefix($prefix);
    }

    private function dropTabsWithPrefix(string $prefix): void
    {
        global $wpdb;

        // Note: At endTestSuite, Core WPDB might be closed or reset. 
        // We attempt to use the global $wpdb if available, OR establish a raw mysqli connection if needed.
        // For simplicity in this environment, try $wpdb first.

        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            fwrite(STDERR, "\n[MHM_Test_Listener] WARNING: \$wpdb not available for cleanup.\n");
            return;
        }

        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$prefix}%'", ARRAY_N);

        if (empty($tables)) {
            return;
        }

        fwrite(STDERR, "\n[MHM_Test_Listener] Implementation: Cleaning up isolated tables ($prefix)...\n");

        foreach ($tables as $table_row) {
            $table = $table_row[0];
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        fwrite(STDERR, "[MHM_Test_Listener] Cleanup complete.\n");
    }
}
