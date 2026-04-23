# Skipped Tests Details

**Goal:** Document Skipped Tests

This document outlines the environmental dependencies responsible for the 4 skipped tests in the MHM Rentiva PHPUnit test suite.

## 1. Vendor Module Skipped Test
**Test:** `MHMRentiva\Tests\Integration\Vendor\VehicleSubmitShortcodeTest::test_prepare_template_data_returns_vendor_only_false_for_vendor_user_when_pro_enabled`
- **Reason:** "Vendor marketplace not available in this environment (Lite/test mode)."
- **Dependency:** Requires the Pro version or a specific feature flag for the Vendor Marketplace to be enabled in the test environment.
- **Action Required:** None for baseline validation. If Pro feature validation is required, the testing environment must be configured with defined constants or database options simulating a Pro license.

## 2. WooCommerce Integration Skipped Tests
**Tests:**
- `MHMRentiva\Tests\Integrations\WooCommerce\CommissionBridgeTest::test_payment_complete_firing_twice_yields_single_entry`
- `MHMRentiva\Tests\Integrations\WooCommerce\CommissionBridgeTest::test_refund_twice_yields_single_entry`
- `MHMRentiva\Tests\Integrations\WooCommerce\CommissionBridgeTest::test_unrelated_order_ignored_safely`
- **Reason:** "WooCommerce not loaded."
- **Dependency:** These integration tests require the active presence of the WooCommerce plugin (`woocommerce/woocommerce.php`) in the WordPress instance where the tests are running.
- **Action Required:** To run these, WooCommerce needs to be installed, activated, and bootstrapped within the Docker testing container (`rentiva-dev-wpcli-1`). Currently, they safely skip to prevent fatal errors from missing WooCommerce classes.
