<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Tests\MHM_Test_Listener;

class CommissionResolverTest extends \WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Ensure any previous cache/options are cleared
        delete_option('mhm_rentiva_commission_tiers');
    }

    public function test_shortcode_requires_vendor_id() {
        $output = do_shortcode('[rentiva_commission_resolver]');
        // Without vendor_id, it should return an empty string or basic error message
        $this->assertEquals('', $output, 'Shortcode without vendor_id should output empty string.');
    }

    public function test_shortcode_renders_global_commission_when_no_tier() {
        // Assume global default implies a fallback (e.g. 15)
        // or we just inject a policy in the DB.
        
        global $wpdb;
        $hash = hash('sha256', 'test');
        $wpdb->insert(
            $wpdb->prefix . 'mhm_rentiva_commission_policy',
            [
                'label' => 'Global rate 15.00%',
                'global_rate' => 15.0,
                'vendor_id' => null,
                'effective_from' => gmdate('Y-m-d H:i:s', strtotime('-1 day')),
                'effective_to' => null,
                'version_hash' => $hash,
            ]
        );

        $output = do_shortcode('[rentiva_commission_resolver vendor_id="10"]');
        
        $this->assertEquals('15', $output, 'Shortcode should output 15 for a global default policy.');
    }

    public function test_shortcode_handles_invalid_vendor_id() {
        $output = do_shortcode('[rentiva_commission_resolver vendor_id="invalid_user_abc"]');
        $this->assertEquals('', $output, 'Shortcode with invalid string vendor_id should resolve to 0 and return empty string.');

        $output_negative = do_shortcode('[rentiva_commission_resolver vendor_id="-5"]');
        $this->assertEquals('', $output_negative, 'Shortcode with negative vendor_id should resolve via absint to 5, but if no vendor exists it acts gracefully or resolves to global.');
    }
}
