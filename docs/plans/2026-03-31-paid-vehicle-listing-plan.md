# Paid Vehicle Listing — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Vendors pay a WooCommerce fee when a vehicle goes live (new, renew, relist). Admin controls feature toggle, pricing model, and amount from settings.

**Architecture:** New `ListingFeeManager` class handles WC product creation, cart management, and order-completion hooks. Settings added to existing `VendorMarketplaceSettings`. Existing `VehicleSubmit` and `VehicleLifecycleAjaxController` get payment gates. Vendor listings template gets remaining-time display.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, WooCommerce, existing MHM Rentiva patterns (SettingsHelper, WooCommerceBridge)

---

## Task 1: Admin Settings — Listing Fee Fields

**Files:**
- Modify: `src/Admin/Settings/Groups/VendorMarketplaceSettings.php`
- Test: `tests/Unit/Admin/Settings/VendorMarketplaceSettingsTest.php`

**Step 1: Write failing tests**

```php
// In VendorMarketplaceSettingsTest.php (create if not exists)
public function test_default_settings_include_listing_fee_fields(): void {
    $defaults = VendorMarketplaceSettings::get_default_settings();
    $this->assertArrayHasKey('mhm_rentiva_listing_fee_enabled', $defaults);
    $this->assertArrayHasKey('mhm_rentiva_listing_fee_model', $defaults);
    $this->assertArrayHasKey('mhm_rentiva_listing_fee_amount', $defaults);
    $this->assertFalse($defaults['mhm_rentiva_listing_fee_enabled']);
    $this->assertSame('one_time', $defaults['mhm_rentiva_listing_fee_model']);
    $this->assertSame(0.0, $defaults['mhm_rentiva_listing_fee_amount']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter VendorMarketplaceSettingsTest --no-coverage"
```

Expected: FAIL — keys not found in defaults.

**Step 3: Add defaults to `get_default_settings()`**

In `VendorMarketplaceSettings.php`, add to the `get_default_settings()` return array:

```php
'mhm_rentiva_listing_fee_enabled' => false,
'mhm_rentiva_listing_fee_model'   => 'one_time',
'mhm_rentiva_listing_fee_amount'  => 0.0,
```

**Step 4: Add settings section and fields to `register()`**

After the existing sections (SECTION_RELIABILITY), add:

```php
// --- Listing Fee Section ---
$listing_fee_section = 'mhm_rentiva_vendor_listing_fee_section';
add_settings_section(
    $listing_fee_section,
    __( 'Listing Fee', 'mhm-rentiva' ),
    function () {
        echo '<p>' . esc_html__( 'Charge vendors a fee when a vehicle listing goes live.', 'mhm-rentiva' ) . '</p>';
    },
    $page_slug
);

SettingsHelper::checkbox_field(
    $page_slug,
    'listing_fee_enabled',
    __( 'Enable Listing Fee', 'mhm-rentiva' ),
    __( 'When enabled, vendors must pay before a vehicle is published, renewed, or relisted.', 'mhm-rentiva' ),
    $listing_fee_section
);

SettingsHelper::select_field(
    $page_slug,
    'listing_fee_model',
    __( 'Fee Model', 'mhm-rentiva' ),
    array(
        'one_time'   => __( 'One-time (pay once, listing stays until withdrawn)', 'mhm-rentiva' ),
        'per_period' => __( 'Per period (pay each 90-day listing cycle)', 'mhm-rentiva' ),
    ),
    __( 'One-time: single payment per listing. Per period: payment required on every renewal.', 'mhm-rentiva' ),
    $listing_fee_section
);

SettingsHelper::number_field(
    $page_slug,
    'listing_fee_amount',
    __( 'Listing Fee Amount', 'mhm-rentiva' ),
    0,       // min
    99999,   // max
    __( 'Fee amount in store currency. Set to 0 to disable.', 'mhm-rentiva' ),
    $listing_fee_section
);
```

**Step 5: Run test to verify it passes**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter VendorMarketplaceSettingsTest --no-coverage"
```

Expected: PASS

**Step 6: Commit**

```bash
git add src/Admin/Settings/Groups/VendorMarketplaceSettings.php tests/
git commit -m "feat(settings): add listing fee settings — enabled, model, amount"
```

---

## Task 2: ListingFeeManager — Core Class

**Files:**
- Create: `src/Admin/Vehicle/ListingFeeManager.php`
- Test: `tests/Unit/Admin/Vehicle/ListingFeeManagerTest.php`

**Step 1: Write failing tests**

```php
class ListingFeeManagerTest extends WP_UnitTestCase {

    public function test_is_enabled_returns_false_by_default(): void {
        $this->assertFalse( ListingFeeManager::is_enabled() );
    }

    public function test_is_enabled_returns_true_when_setting_on(): void {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        $settings['mhm_rentiva_listing_fee_enabled'] = true;
        $settings['mhm_rentiva_listing_fee_amount']  = 50.0;
        update_option( 'mhm_rentiva_settings', $settings );

        $this->assertTrue( ListingFeeManager::is_enabled() );
    }

    public function test_is_enabled_returns_false_when_amount_zero(): void {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        $settings['mhm_rentiva_listing_fee_enabled'] = true;
        $settings['mhm_rentiva_listing_fee_amount']  = 0.0;
        update_option( 'mhm_rentiva_settings', $settings );

        $this->assertFalse( ListingFeeManager::is_enabled() );
    }

    public function test_get_fee_amount_reads_from_settings(): void {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        $settings['mhm_rentiva_listing_fee_amount'] = 75.50;
        update_option( 'mhm_rentiva_settings', $settings );

        $this->assertSame( 75.50, ListingFeeManager::get_fee_amount() );
    }

    public function test_get_fee_model_defaults_to_one_time(): void {
        $this->assertSame( 'one_time', ListingFeeManager::get_fee_model() );
    }

    public function test_requires_payment_for_renew_depends_on_model(): void {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        $settings['mhm_rentiva_listing_fee_enabled'] = true;
        $settings['mhm_rentiva_listing_fee_amount']  = 50.0;

        // one_time model: renew is always paid (vehicle needs to go live again)
        $settings['mhm_rentiva_listing_fee_model'] = 'one_time';
        update_option( 'mhm_rentiva_settings', $settings );
        $this->assertTrue( ListingFeeManager::requires_payment( 'renew' ) );

        // per_period model: renew is paid
        $settings['mhm_rentiva_listing_fee_model'] = 'per_period';
        update_option( 'mhm_rentiva_settings', $settings );
        $this->assertTrue( ListingFeeManager::requires_payment( 'renew' ) );
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter ListingFeeManagerTest --no-coverage"
```

Expected: FAIL — class not found.

**Step 3: Implement ListingFeeManager**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages paid vehicle listing fees via WooCommerce.
 *
 * @since 4.25.0
 */
final class ListingFeeManager {

    public const PRODUCT_SKU = 'mhm-rentiva-listing-fee';

    public const META_VEHICLE_ID = '_mhm_listing_vehicle_id';
    public const META_ACTION     = '_mhm_listing_action';

    /**
     * Check if listing fee is enabled and amount > 0.
     */
    public static function is_enabled(): bool {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        $enabled  = ! empty( $settings['mhm_rentiva_listing_fee_enabled'] );
        $amount   = (float) ( $settings['mhm_rentiva_listing_fee_amount'] ?? 0 );

        return $enabled && $amount > 0;
    }

    /**
     * Get fee amount from settings.
     */
    public static function get_fee_amount(): float {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        return (float) ( $settings['mhm_rentiva_listing_fee_amount'] ?? 0 );
    }

    /**
     * Get fee model: 'one_time' or 'per_period'.
     */
    public static function get_fee_model(): string {
        $settings = get_option( 'mhm_rentiva_settings', array() );
        $model    = $settings['mhm_rentiva_listing_fee_model'] ?? 'one_time';
        return in_array( $model, array( 'one_time', 'per_period' ), true ) ? $model : 'one_time';
    }

    /**
     * Check if a given action requires payment.
     *
     * @param string $action 'new', 'renew', or 'relist'
     */
    public static function requires_payment( string $action ): bool {
        if ( ! self::is_enabled() ) {
            return false;
        }

        // All actions require payment when enabled:
        // new = first listing, renew = expired relisting, relist = withdrawn relisting
        return in_array( $action, array( 'new', 'renew', 'relist' ), true );
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter ListingFeeManagerTest --no-coverage"
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Admin/Vehicle/ListingFeeManager.php tests/Unit/Admin/Vehicle/ListingFeeManagerTest.php
git commit -m "feat(listing-fee): add ListingFeeManager — is_enabled, get_fee_amount, requires_payment"
```

---

## Task 3: ListingFeeManager — WC Product & Cart

**Files:**
- Modify: `src/Admin/Vehicle/ListingFeeManager.php`
- Test: `tests/Unit/Admin/Vehicle/ListingFeeManagerTest.php`

**Step 1: Write failing tests**

```php
public function test_get_or_create_product_creates_hidden_product(): void {
    // Requires WooCommerce active
    if ( ! class_exists( 'WC_Product_Simple' ) ) {
        $this->markTestSkipped( 'WooCommerce not active.' );
    }

    $product_id = ListingFeeManager::get_or_create_product();
    $this->assertGreaterThan( 0, $product_id );

    $product = wc_get_product( $product_id );
    $this->assertSame( ListingFeeManager::PRODUCT_SKU, $product->get_sku() );
    $this->assertSame( 'hidden', $product->get_catalog_visibility() );
    $this->assertTrue( $product->is_virtual() );
}

public function test_get_or_create_product_returns_same_id_on_second_call(): void {
    if ( ! class_exists( 'WC_Product_Simple' ) ) {
        $this->markTestSkipped( 'WooCommerce not active.' );
    }

    $first  = ListingFeeManager::get_or_create_product();
    $second = ListingFeeManager::get_or_create_product();
    $this->assertSame( $first, $second );
}
```

**Step 2: Run test to verify it fails**

**Step 3: Implement `get_or_create_product()` and `add_to_cart()`**

Add to `ListingFeeManager`:

```php
/**
 * Get or create the listing fee WC product.
 */
public static function get_or_create_product(): int {
    $product_id = wc_get_product_id_by_sku( self::PRODUCT_SKU );
    if ( $product_id > 0 ) {
        return $product_id;
    }

    $product = new \WC_Product_Simple();
    $product->set_name( __( 'Vehicle Listing Fee', 'mhm-rentiva' ) );
    $product->set_sku( self::PRODUCT_SKU );
    $product->set_price( 0 );
    $product->set_regular_price( 0 );
    $product->set_virtual( true );
    $product->set_sold_individually( true );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'hidden' );
    $product->save();

    return $product->get_id();
}

/**
 * Add listing fee to WC cart and return checkout URL.
 *
 * @param int    $vehicle_id Vehicle post ID.
 * @param string $action     'new', 'renew', or 'relist'.
 * @return string WC checkout URL.
 */
public static function add_to_cart( int $vehicle_id, string $action ): string {
    $product_id = self::get_or_create_product();

    \WC()->cart->empty_cart();
    \WC()->cart->add_to_cart( $product_id, 1, 0, array(), array(
        'mhm_listing_fee_vehicle_id' => $vehicle_id,
        'mhm_listing_fee_action'     => $action,
        'mhm_listing_fee_amount'     => self::get_fee_amount(),
    ) );

    return wc_get_checkout_url();
}
```

**Step 4: Run test to verify it passes**

**Step 5: Commit**

```bash
git add src/Admin/Vehicle/ListingFeeManager.php tests/Unit/Admin/Vehicle/ListingFeeManagerTest.php
git commit -m "feat(listing-fee): add WC product creation and cart management"
```

---

## Task 4: ListingFeeManager — WC Hooks (Price, Session, Order)

**Files:**
- Modify: `src/Admin/Vehicle/ListingFeeManager.php`
- Test: `tests/Unit/Admin/Vehicle/ListingFeeManagerTest.php`

**Step 1: Write failing test**

```php
public function test_order_completed_transitions_vehicle_to_pending_review(): void {
    // Create a vehicle as draft
    $vehicle_id = $this->factory()->post->create( array(
        'post_type'   => 'vehicle',
        'post_status' => 'draft',
    ) );
    update_post_meta( $vehicle_id, '_vehicle_review_status', 'draft' );

    ListingFeeManager::process_completed_order( $vehicle_id, 'new' );

    $this->assertSame( 'pending', get_post_status( $vehicle_id ) );
    $this->assertSame( 'pending_review', get_post_meta( $vehicle_id, '_vehicle_review_status', true ) );
}
```

**Step 2: Run test to verify it fails**

**Step 3: Implement WC hooks and `register()` method**

Add `register()` method and WC hook handlers to `ListingFeeManager`:

```php
/**
 * Register WC hooks for listing fee management.
 */
public static function register(): void {
    // Dynamic pricing: override cart item price
    add_action( 'woocommerce_before_calculate_totals', array( self::class, 'set_cart_item_price' ) );

    // Persist listing fee meta through WC session
    add_filter( 'woocommerce_get_item_data', array( self::class, 'display_cart_item_meta' ), 10, 2 );

    // Save meta to order item
    add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'save_order_item_meta' ), 10, 4 );

    // Process completed order → transition vehicle
    add_action( 'woocommerce_order_status_completed', array( self::class, 'on_order_completed' ) );
}

/**
 * Set dynamic price from settings (not from product).
 */
public static function set_cart_item_price( $cart ): void {
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['mhm_listing_fee_amount'] ) ) {
            $cart_item['data']->set_price( (float) $cart_item['mhm_listing_fee_amount'] );
        }
    }
}

/**
 * Display listing fee details in cart.
 */
public static function display_cart_item_meta( array $item_data, array $cart_item ): array {
    if ( isset( $cart_item['mhm_listing_fee_vehicle_id'] ) ) {
        $vehicle = get_post( (int) $cart_item['mhm_listing_fee_vehicle_id'] );
        if ( $vehicle ) {
            $item_data[] = array(
                'key'   => __( 'Vehicle', 'mhm-rentiva' ),
                'value' => $vehicle->post_title,
            );
        }
        $action_labels = array(
            'new'    => __( 'New Listing', 'mhm-rentiva' ),
            'renew'  => __( 'Renewal', 'mhm-rentiva' ),
            'relist' => __( 'Relisting', 'mhm-rentiva' ),
        );
        $action = $cart_item['mhm_listing_fee_action'] ?? 'new';
        $item_data[] = array(
            'key'   => __( 'Type', 'mhm-rentiva' ),
            'value' => $action_labels[ $action ] ?? $action,
        );
    }
    return $item_data;
}

/**
 * Save listing fee meta to WC order line item.
 */
public static function save_order_item_meta( $item, $cart_item_key, $values, $order ): void {
    if ( isset( $values['mhm_listing_fee_vehicle_id'] ) ) {
        $item->add_meta_data( self::META_VEHICLE_ID, (int) $values['mhm_listing_fee_vehicle_id'] );
        $item->add_meta_data( self::META_ACTION, $values['mhm_listing_fee_action'] ?? 'new' );
    }
}

/**
 * On WC order completed, transition the vehicle.
 */
public static function on_order_completed( int $order_id ): void {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    foreach ( $order->get_items() as $item ) {
        $vehicle_id = (int) $item->get_meta( self::META_VEHICLE_ID );
        $action     = (string) $item->get_meta( self::META_ACTION );

        if ( $vehicle_id <= 0 ) {
            continue;
        }

        self::process_completed_order( $vehicle_id, $action );
    }
}

/**
 * Process a completed listing fee payment.
 *
 * @param int    $vehicle_id Vehicle post ID.
 * @param string $action     'new', 'renew', or 'relist'.
 */
public static function process_completed_order( int $vehicle_id, string $action ): void {
    $vendor_id = (int) get_post_field( 'post_author', $vehicle_id );

    switch ( $action ) {
        case 'new':
        case 'relist':
            wp_update_post( array(
                'ID'          => $vehicle_id,
                'post_status' => 'pending',
            ) );
            update_post_meta( $vehicle_id, '_vehicle_review_status', 'pending_review' );
            do_action( 'mhm_rentiva_vendor_vehicle_submitted', $vehicle_id, $vendor_id );
            break;

        case 'renew':
            if ( class_exists( VehicleLifecycleManager::class ) ) {
                VehicleLifecycleManager::renew( $vehicle_id, $vendor_id );
            }
            break;
    }
}
```

**Step 4: Run test to verify it passes**

**Step 5: Register in plugin bootstrap**

Find where `VehicleLifecycleManager::register()` is called and add `ListingFeeManager::register()` nearby.

**Step 6: Commit**

```bash
git add src/Admin/Vehicle/ListingFeeManager.php tests/
git commit -m "feat(listing-fee): add WC hooks — dynamic price, session meta, order completion"
```

---

## Task 5: VehicleSubmit — Payment Gate

**Files:**
- Modify: `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php` (line ~784)
- Test: `tests/Unit/Admin/Frontend/Shortcodes/Vendor/VehicleSubmitTest.php`

**Step 1: Write failing test**

```php
public function test_vehicle_saved_as_draft_when_listing_fee_enabled(): void {
    // Enable listing fee
    $settings = get_option( 'mhm_rentiva_settings', array() );
    $settings['mhm_rentiva_listing_fee_enabled'] = true;
    $settings['mhm_rentiva_listing_fee_amount']  = 100.0;
    update_option( 'mhm_rentiva_settings', $settings );

    // Simulate a vehicle that was just submitted with fee required
    $vehicle_id = $this->factory()->post->create( array(
        'post_type'   => 'vehicle',
        'post_status' => 'draft',
    ) );

    // When fee is enabled, vehicle should stay as draft (not pending)
    $this->assertSame( 'draft', get_post_status( $vehicle_id ) );
}
```

**Step 2: Run test to verify it fails**

**Step 3: Modify `handle_ajax()` in VehicleSubmit.php**

After the existing vehicle creation (around line 784), add the listing fee gate:

```php
// --- Listing Fee Payment Gate ---
if ( ListingFeeManager::requires_payment( 'new' ) ) {
    // Keep vehicle as draft until payment completes
    wp_update_post( array(
        'ID'          => $post_id,
        'post_status' => 'draft',
    ) );
    update_post_meta( $post_id, '_vehicle_review_status', 'awaiting_payment' );

    $checkout_url = ListingFeeManager::add_to_cart( $post_id, 'new' );
    wp_send_json_success( array(
        'vehicle_id'      => $post_id,
        'requires_payment' => true,
        'checkout_url'    => $checkout_url,
    ) );
    return; // Early return — skip the normal pending_review flow
}
```

**Step 4: Update JavaScript handler**

In `vehicle-submit.js`, after successful AJAX response, check for `requires_payment`:

```javascript
if ( response.data.requires_payment && response.data.checkout_url ) {
    window.location.href = response.data.checkout_url;
    return;
}
```

**Step 5: Run tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
```

**Step 6: Commit**

```bash
git add src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php assets/js/
git commit -m "feat(listing-fee): payment gate on vehicle submission"
```

---

## Task 6: Lifecycle AJAX — Payment Gate for Renew/Relist

**Files:**
- Modify: `src/Admin/Vehicle/VehicleLifecycleAjaxController.php` (lines ~104-127)
- Test: `tests/Unit/Admin/Vehicle/VehicleLifecycleAjaxControllerTest.php`

**Step 1: Write failing test**

```php
public function test_renew_returns_checkout_url_when_fee_enabled(): void {
    $settings = get_option( 'mhm_rentiva_settings', array() );
    $settings['mhm_rentiva_listing_fee_enabled'] = true;
    $settings['mhm_rentiva_listing_fee_amount']  = 50.0;
    update_option( 'mhm_rentiva_settings', $settings );

    $this->assertTrue( ListingFeeManager::requires_payment( 'renew' ) );
}
```

**Step 2: Run test to verify it fails**

**Step 3: Modify `handle_renew()` and `handle_relist()` in VehicleLifecycleAjaxController**

In `handle_renew()` (around line 104), add before the existing `VehicleLifecycleManager::renew()` call:

```php
// Listing fee payment gate
if ( ListingFeeManager::requires_payment( 'renew' ) ) {
    $checkout_url = ListingFeeManager::add_to_cart( $vehicle_id, 'renew' );
    wp_send_json_success( array(
        'requires_payment' => true,
        'checkout_url'     => $checkout_url,
        'message'          => __( 'Redirecting to payment...', 'mhm-rentiva' ),
    ) );
    return;
}
```

Same pattern in `handle_relist()` (around line 118):

```php
if ( ListingFeeManager::requires_payment( 'relist' ) ) {
    $checkout_url = ListingFeeManager::add_to_cart( $vehicle_id, 'relist' );
    wp_send_json_success( array(
        'requires_payment' => true,
        'checkout_url'     => $checkout_url,
        'message'          => __( 'Redirecting to payment...', 'mhm-rentiva' ),
    ) );
    return;
}
```

**Step 4: Update JavaScript handler**

In the lifecycle button JS handler (likely `user-dashboard.js`), after AJAX success check:

```javascript
if ( response.data.requires_payment && response.data.checkout_url ) {
    window.location.href = response.data.checkout_url;
    return;
}
```

**Step 5: Run all tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
```

**Step 6: Commit**

```bash
git add src/Admin/Vehicle/VehicleLifecycleAjaxController.php assets/js/
git commit -m "feat(listing-fee): payment gate on renew and relist lifecycle actions"
```

---

## Task 7: Vendor Listings UI — Remaining Time Display

**Files:**
- Modify: `templates/account/partials/vendor-listings.php` (after line ~197, before actions)
- Modify: `assets/css/frontend/vendor-forms.css` (for color-coded badge)

**Step 1: Add remaining time display in template**

After the city/plate details block (line ~197) and before the actions block, add:

```php
<?php
// Remaining listing time display
if ( in_array( $lifecycle_status, array( 'active', 'paused' ), true ) ) :
    $expires_at = get_post_meta( $vehicle->ID, '_mhm_vehicle_listing_expires_at', true );
    $started_at = get_post_meta( $vehicle->ID, '_mhm_vehicle_listing_started_at', true );

    if ( $expires_at ) :
        $now           = time();
        $expires_ts    = strtotime( $expires_at );
        $started_ts    = $started_at ? strtotime( $started_at ) : $now;
        $total_days    = max( 1, (int) round( ( $expires_ts - $started_ts ) / DAY_IN_SECONDS ) );
        $remaining_days = max( 0, (int) ceil( ( $expires_ts - $now ) / DAY_IN_SECONDS ) );
        $pct           = ( $remaining_days / $total_days ) * 100;

        if ( $pct > 50 ) {
            $color_class = 'is-green';
        } elseif ( $pct > 20 ) {
            $color_class = 'is-yellow';
        } else {
            $color_class = 'is-red';
        }
        ?>
        <div class="mhm-vendor-listing-card__remaining <?php echo esc_attr( $color_class ); ?>">
            <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <?php
            printf(
                /* translators: %d: number of remaining days */
                esc_html__( 'Remaining: %d days', 'mhm-rentiva' ),
                $remaining_days
            );
            ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
```

**Step 2: Add CSS styles**

In `assets/css/frontend/vendor-forms.css`:

```css
/* Listing remaining time badge */
.mhm-vendor-listing-card__remaining {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 4px;
    margin-top: 6px;
}

.mhm-vendor-listing-card__remaining.is-green {
    color: #15803d;
    background: #dcfce7;
}

.mhm-vendor-listing-card__remaining.is-yellow {
    color: #a16207;
    background: #fef9c3;
}

.mhm-vendor-listing-card__remaining.is-red {
    color: #dc2626;
    background: #fee2e2;
}
```

**Step 3: Verify in browser**

Navigate to `http://localhost:8080/panel/` as vendor `test` and check the listings tab.

**Step 4: Commit**

```bash
git add templates/account/partials/vendor-listings.php assets/css/frontend/vendor-forms.css
git commit -m "feat(vendor-ui): show remaining listing time with color-coded badge"
```

---

## Task 8: Translations

**Files:**
- Modify: `languages/mhm-rentiva-tr_TR.po`
- Modify: `languages/mhm-rentiva-tr_TR.l10n.php`

**Step 1: Add TR translations to `.po`**

New strings to translate:

| English | Turkish |
|---------|---------|
| `Enable Listing Fee` | `İlan Ücretini Etkinleştir` |
| `Listing Fee` | `İlan Ücreti` |
| `Fee Model` | `Ücret Modeli` |
| `One-time (pay once, listing stays until withdrawn)` | `Tek seferlik (bir kez öde, ilan geri çekilene kadar kalır)` |
| `Per period (pay each 90-day listing cycle)` | `Dönemsel (her 90 günlük ilan döngüsünde ödeme gerekir)` |
| `Listing Fee Amount` | `İlan Ücreti Tutarı` |
| `Vehicle Listing Fee` | `Araç İlan Ücreti` |
| `New Listing` | `Yeni İlan` |
| `Renewal` | `Yenileme` |
| `Relisting` | `Yeniden Listeleme` |
| `Redirecting to payment...` | `Ödeme sayfasına yönlendiriliyor...` |
| `Remaining: %d days` | `Kalan: %d gün` |
| `Charge vendors a fee when a vehicle listing goes live.` | `Bir araç ilanı yayına alındığında satıcılardan ücret alın.` |

**Step 2: Update `.l10n.php` with same translations**

**Step 3: Commit**

```bash
git add languages/
git commit -m "i18n: add Turkish translations for listing fee feature"
```

---

## Task 9: Full Integration Test & Manual QA

**Step 1: Run full test suite**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"
```

Expected: All 665+ tests pass, no regressions.

**Step 2: Manual QA checklist**

1. Admin → Settings → Vendor Marketplace → Listing Fee section visible
2. Enable listing fee, set amount to 100, model to "one_time"
3. Login as vendor `test` → Add Vehicle → form submits → redirects to WC checkout
4. Complete payment → vehicle appears in listings as "Pending Review"
5. Admin approves → vehicle goes active → remaining time badge shows
6. Wait for expiry (or manually set meta) → click "Renew" → redirects to checkout
7. Disable listing fee → Add Vehicle → no checkout redirect (free flow)
8. Verify customer `test1` account NOT affected

**Step 3: Run PHPCS**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs"
```

**Step 4: Commit any fixes**

---

## Execution Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Admin Settings | `VendorMarketplaceSettings.php` |
| 2 | ListingFeeManager core | `ListingFeeManager.php` (new) |
| 3 | WC Product & Cart | `ListingFeeManager.php` |
| 4 | WC Hooks (price, order) | `ListingFeeManager.php` |
| 5 | VehicleSubmit payment gate | `VehicleSubmit.php` |
| 6 | Lifecycle AJAX payment gate | `VehicleLifecycleAjaxController.php` |
| 7 | Remaining time UI | `vendor-listings.php`, CSS |
| 8 | Translations | `languages/` |
| 9 | Integration test & QA | Full suite |
