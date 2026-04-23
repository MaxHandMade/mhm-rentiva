# Vendor-Location-Transfer Architecture Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Bind vendor vehicles geographically to transfer locations/routes via a city hierarchy, add vendor-specific transfer pricing within admin-defined min/max ranges, and expose transfer capacity fields in the vendor submission form.

**Architecture:** Add `city` column to `rentiva_transfer_locations` table, `max_price` to `rentiva_transfer_routes` table. Vendor's city (from application) filters which locations/routes they see. Each vendor sets their own price per route. TransferSearchEngine filters vehicles by route assignment and uses vendor pricing.

**Tech Stack:** PHP 8.1+, WordPress 6.x, WP_UnitTestCase (PHPUnit), MySQL/MariaDB (dbDelta), jQuery (existing frontend)

**Docker test command:** `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

---

## Task 1: Database Migration — `city` column on locations table

**Files:**
- Modify: `src/Admin/Core/Utilities/DatabaseMigrator.php:25` (version bump)
- Modify: `src/Admin/Core/Utilities/DatabaseMigrator.php:552-566` (CREATE TABLE SQL)

**Step 1: Bump migration version**

In `DatabaseMigrator.php:25`, change:
```php
private const CURRENT_VERSION = '3.3.0';
```
to:
```php
private const CURRENT_VERSION = '3.4.0';
```

**Step 2: Add `city` column to locations CREATE TABLE**

In `DatabaseMigrator.php`, in the `create_transfer_tables()` method, update the `$sql_locations` string. Add `city varchar(100) NOT NULL DEFAULT ''` after the `name` column:

```php
$sql_locations = "CREATE TABLE $table_locations (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    city varchar(100) NOT NULL DEFAULT '',
    type varchar(50) NOT NULL,
    priority int(11) DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    allow_rental tinyint(1) DEFAULT 1,
    allow_transfer tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY type (type),
    KEY is_active (is_active),
    KEY allow_rental (allow_rental),
    KEY allow_transfer (allow_transfer),
    KEY city (city)
) $charset_collate;";
```

Note: `dbDelta()` handles adding new columns to existing tables automatically.

**Step 3: Add `max_price` column to routes CREATE TABLE**

In the same method, update `$sql_routes`. Add `max_price decimal(10,2) DEFAULT 0.00` after `min_price`:

```php
$sql_routes = "CREATE TABLE $table_routes (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    origin_id bigint(20) NOT NULL,
    destination_id bigint(20) NOT NULL,
    distance_km float DEFAULT 0,
    duration_min int(11) DEFAULT 0,
    pricing_method enum('fixed', 'calculated') DEFAULT 'fixed',
    base_price decimal(10,2) DEFAULT 0.00,
    min_price decimal(10,2) DEFAULT 0.00,
    max_price decimal(10,2) DEFAULT 0.00,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY origin_dest (origin_id, destination_id),
    KEY pricing_method (pricing_method)
) $charset_collate;";
```

**Step 4: Run migration test**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors --filter=DatabaseMigrator 2>&1 || echo 'No specific migration tests — run full suite'" `

Then verify columns exist:
```bash
docker exec rentiva-dev-wpcli-1 wp db query "DESCRIBE wp_rentiva_transfer_locations" --allow-root
docker exec rentiva-dev-wpcli-1 wp db query "DESCRIBE wp_rentiva_transfer_routes" --allow-root
```

If `city` column doesn't appear, force re-migration:
```bash
docker exec rentiva-dev-wpcli-1 wp option update mhm_rentiva_db_version '3.3.0' --allow-root
docker exec rentiva-dev-wpcli-1 wp eval 'MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations();' --allow-root
```

**Step 5: Commit**

```bash
git add src/Admin/Core/Utilities/DatabaseMigrator.php
git commit -m "feat(transfer): add city column to locations, max_price to routes (v3.4.0 migration)"
```

---

## Task 2: LocationProvider — `get_by_city()` method

**Files:**
- Modify: `src/Admin/Transfer/Engine/LocationProvider.php`
- Create: `tests/Unit/Transfer/LocationProviderCityFilterTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Transfer/LocationProviderCityFilterTest.php`:

```php
<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Transfer;

use MHMRentiva\Admin\Transfer\Engine\LocationProvider;

class LocationProviderCityFilterTest extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $table = $wpdb->prefix . 'rentiva_transfer_locations';

        // Ensure table has city column (migration should have run).
        // Insert test data.
        $wpdb->insert($table, [
            'name'           => 'IST Airport',
            'city'           => 'Istanbul',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);
        $wpdb->insert($table, [
            'name'           => 'Kadikoy Center',
            'city'           => 'Istanbul',
            'type'           => 'city_center',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);
        $wpdb->insert($table, [
            'name'           => 'Esenboga Airport',
            'city'           => 'Ankara',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);

        LocationProvider::clear_cache();
    }

    public function tearDown(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rentiva_transfer_locations';
        $wpdb->query("DELETE FROM {$table} WHERE name IN ('IST Airport','Kadikoy Center','Esenboga Airport')");
        LocationProvider::clear_cache();
        parent::tearDown();
    }

    public function test_get_by_city_returns_only_matching_city(): void
    {
        $istanbul = LocationProvider::get_by_city('Istanbul', 'transfer');

        $names = array_column($istanbul, 'name');
        $this->assertContains('IST Airport', $names);
        $this->assertContains('Kadikoy Center', $names);
        $this->assertNotContains('Esenboga Airport', $names);
    }

    public function test_get_by_city_returns_empty_for_unknown_city(): void
    {
        $result = LocationProvider::get_by_city('Izmir', 'transfer');
        $this->assertEmpty($result);
    }

    public function test_get_by_city_is_case_insensitive(): void
    {
        $result = LocationProvider::get_by_city('istanbul', 'transfer');
        $this->assertNotEmpty($result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors --filter=LocationProviderCityFilterTest"`

Expected: FAIL — `get_by_city` method does not exist.

**Step 3: Implement `get_by_city()` in LocationProvider**

Add to `src/Admin/Transfer/Engine/LocationProvider.php` before the `clear_cache()` method:

```php
/**
 * Get locations filtered by city
 *
 * @param string $city         City name to filter by.
 * @param string $service_type rental|transfer|both
 * @return array Array of location objects with id, name, type, city columns.
 */
public static function get_by_city(string $city, string $service_type = 'both'): array
{
    if ($city === '') {
        return array();
    }

    global $wpdb;
    $table_name = self::resolve_table_name();

    $query = $wpdb->prepare(
        "SELECT id, name, type, city FROM {$table_name} WHERE is_active = 1 AND LOWER(city) = LOWER(%s)",
        $city
    );

    if ($service_type === 'rental') {
        $query .= " AND allow_rental = 1";
    } elseif ($service_type === 'transfer') {
        $query .= " AND allow_transfer = 1";
    }

    $query .= " ORDER BY priority ASC, name ASC";

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    $results = $wpdb->get_results($query);

    return is_array($results) ? $results : array();
}
```

Also update `get_locations()` SELECT to include `city`:

Change line 45 from:
```php
$query = "SELECT id, name, type, allow_rental, allow_transfer FROM {$table_name} WHERE is_active = 1";
```
to:
```php
$query = "SELECT id, name, type, city, allow_rental, allow_transfer FROM {$table_name} WHERE is_active = 1";
```

**Step 4: Run test to verify it passes**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors --filter=LocationProviderCityFilterTest"`

Expected: 3 tests PASS.

**Step 5: Run full test suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

Expected: 562+ tests, 0 failures.

**Step 6: Commit**

```bash
git add src/Admin/Transfer/Engine/LocationProvider.php tests/Unit/Transfer/LocationProviderCityFilterTest.php
git commit -m "feat(transfer): add LocationProvider::get_by_city() with city filter"
```

---

## Task 3: TransferAdmin — city field on location form + max_price on route form

**Files:**
- Modify: `src/Admin/Transfer/TransferAdmin.php:436-475` (location form)
- Modify: `src/Admin/Transfer/TransferAdmin.php:682-691` (route form)
- Modify: `src/Admin/Transfer/TransferAdmin.php:785-791` (location save handler)
- Modify: `src/Admin/Transfer/TransferAdmin.php:831-838` (route save handler)
- Modify: `src/Admin/Transfer/TransferAdmin.php:487-530` (location list table)

**Step 1: Add city field to location form**

In `render_locations_page()`, after the `name` form-field div (after line 439), add:

```php
<div class="form-field">
    <label for="city"><?php echo esc_html__('City', 'mhm-rentiva'); ?></label>
    <input name="city" id="city" type="text" value="<?php echo $edit_location ? esc_attr($edit_location->city) : ''; ?>" placeholder="<?php echo esc_attr__('e.g. Istanbul, Ankara', 'mhm-rentiva'); ?>">
    <p class="description"><?php echo esc_html__('City this location belongs to. Used to filter locations for vendors.', 'mhm-rentiva'); ?></p>
</div>
```

**Step 2: Add city column to location list table**

In the `<thead>` section (~line 490), add a `City` column after Name:
```php
<th><?php echo esc_html__('City', 'mhm-rentiva'); ?></th>
```

In the `<tbody>` loop (~line 501), after the Name `<td>`, add:
```php
<td><?php echo esc_html($location->city); ?></td>
```

Update the `colspan` in the empty row from `4` to `6` (Name, City, Type, Priority, Services, Actions).

**Step 3: Save city in location handler**

In `handle_save_location()` (~line 785-791), add `city` to the `$data` array:

```php
$data = array(
    'name'           => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
    'city'           => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
    'type'           => isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '',
    'priority'       => isset($_POST['priority']) ? intval(wp_unslash($_POST['priority'])) : 0,
    'allow_rental'   => isset($_POST['allow_rental']) ? 1 : 0,
    'allow_transfer' => isset($_POST['allow_transfer']) ? 1 : 0,
);
```

**Step 4: Add max_price field to route form**

In `render_routes_page()`, after the `min_price_field` div (~line 691), add:

```php
<div class="form-field" id="max_price_field">
    <label for="max_price"><?php echo esc_html__('Maximum Price (Vendor Ceiling)', 'mhm-rentiva'); ?></label>
    <input name="max_price" id="max_price" type="number" step="0.01" min="0" value="<?php echo $edit_route ? esc_attr($edit_route->max_price) : ''; ?>">
    <p class="description"><?php echo esc_html__('Maximum price vendors can charge. Leave 0 for no ceiling.', 'mhm-rentiva'); ?></p>
</div>
```

**Step 5: Save max_price in route handler**

In `handle_save_route()` (~line 838), add `max_price` to the `$data` array:

```php
'max_price'      => isset($_POST['max_price']) ? floatval(wp_unslash($_POST['max_price'])) : 0.0,
```

**Step 6: Show max_price in route list table**

In the route list pricing cell (~line 731-739), update to show min/max range when applicable. After the existing pricing display, add vendor price range info:

After the `Min:` line in the calculated pricing block, add:
```php
<?php if ((float) $route->max_price > 0) : ?>
    Max: <?php echo wp_kses_post(call_user_func('wc_price', $route->max_price)); ?>
<?php endif; ?>
```

For fixed pricing, also show range:
```php
<?php if ((float) $route->min_price > 0 || (float) $route->max_price > 0) : ?>
    <br><small>
    <?php if ((float) $route->min_price > 0) : ?>
        Min: <?php echo wp_kses_post(call_user_func('wc_price', $route->min_price)); ?>
    <?php endif; ?>
    <?php if ((float) $route->max_price > 0) : ?>
        Max: <?php echo wp_kses_post(call_user_func('wc_price', $route->max_price)); ?>
    <?php endif; ?>
    </small>
<?php endif; ?>
```

**Step 7: Run full test suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

Expected: All tests pass (no regressions).

**Step 8: Commit**

```bash
git add src/Admin/Transfer/TransferAdmin.php
git commit -m "feat(transfer): add city field to locations, max_price to routes admin UI"
```

---

## Task 4: VehicleSubmit — city-filtered transfer selection + pricing + capacity fields

**Files:**
- Modify: `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php:80-108` (prepare_template_data)
- Modify: `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php:334-372` (transfer form HTML)
- Modify: `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php:580-670` (handle_submit save)
- Modify: `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php:895-901` (handle_update save)
- Modify: `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php:783-787` (handle_get_edit_data)

**Step 1: Filter locations/routes by vendor city in `prepare_template_data()`**

Replace lines 90-108 in `prepare_template_data()` with city-filtered queries:

```php
// Fetch vendor's city.
$vendor_city = get_user_meta(get_current_user_id(), '_mhm_rentiva_vendor_city', true);
$vendor_city = is_string($vendor_city) ? trim($vendor_city) : '';

// Fetch transfer locations filtered by vendor's city.
$transfer_locations = array();
$transfer_routes    = array();

if ($vendor_city !== '') {
    $transfer_locations = LocationProvider::get_by_city($vendor_city, 'transfer');

    if (! empty($transfer_locations)) {
        $city_location_ids = array_map(function ($loc) {
            return (int) $loc->id;
        }, $transfer_locations);

        global $wpdb;
        $routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
        $loc_table    = $wpdb->prefix . 'rentiva_transfer_locations';

        $placeholders = implode(',', array_fill(0, count($city_location_ids), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $transfer_routes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.origin_id, r.destination_id, r.base_price, r.min_price, r.max_price,
                        o.name AS origin_name, d.name AS destination_name
                 FROM {$routes_table} r
                 LEFT JOIN {$loc_table} o ON r.origin_id = o.id
                 LEFT JOIN {$loc_table} d ON r.destination_id = d.id
                 WHERE r.origin_id IN ({$placeholders}) AND r.destination_id IN ({$placeholders})
                 ORDER BY o.name ASC, d.name ASC",
                array_merge($city_location_ids, $city_location_ids)
            )
        );
        if (! is_array($transfer_routes)) {
            $transfer_routes = array();
        }
    }
}

return array(
    'pro_required'       => false,
    'login_required'     => false,
    'vendor_only'        => false,
    'ajax_url'           => admin_url('admin-ajax.php'),
    'nonce'              => wp_create_nonce('mhm_vehicle_submit'),
    'categories'         => $categories,
    'transfer_locations' => $transfer_locations,
    'transfer_routes'    => $transfer_routes,
    'vendor_city'        => $vendor_city,
);
```

**Step 2: Update transfer form HTML with pricing inputs and capacity fields**

Replace the transfer section (lines 334-372) with:

```php
<?php if (! empty($transfer_locations) || ! empty($transfer_routes)) : ?>
<div class="mhm-vendor-form__section mhm-vendor-form__section--transfer" id="mhm-transfer-section" style="display:none">
    <h3><?php esc_html_e('Transfer Service Settings', 'mhm-rentiva'); ?></h3>

    <!-- Vendor city (read-only) -->
    <div class="mhm-vendor-form__field">
        <label><?php esc_html_e('Service City', 'mhm-rentiva'); ?></label>
        <input type="text" value="<?php echo esc_attr($data['vendor_city'] ?? ''); ?>" disabled>
        <p class="mhm-vendor-form__hint"><?php esc_html_e('Your city is set during vendor application. Contact admin to change.', 'mhm-rentiva'); ?></p>
    </div>

    <!-- Transfer Capacity -->
    <div class="mhm-vendor-form__field-row" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="mhm-vendor-form__field">
            <label for="mhm-transfer-max-pax"><?php esc_html_e('Max Passengers', 'mhm-rentiva'); ?> <span class="required">*</span></label>
            <input type="number" id="mhm-transfer-max-pax" name="transfer_max_pax" min="1" max="50" placeholder="<?php esc_attr_e('Excluding driver', 'mhm-rentiva'); ?>">
        </div>
        <div class="mhm-vendor-form__field">
            <label for="mhm-transfer-luggage-score"><?php esc_html_e('Luggage Score Capacity', 'mhm-rentiva'); ?></label>
            <input type="number" id="mhm-transfer-luggage-score" name="transfer_luggage_score" min="0" step="0.5">
            <p class="mhm-vendor-form__hint"><?php esc_html_e('Small Bag = 1 pt, Big Bag = 2.5 pt', 'mhm-rentiva'); ?></p>
        </div>
    </div>
    <div class="mhm-vendor-form__field-row" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="mhm-vendor-form__field">
            <label for="mhm-transfer-max-big"><?php esc_html_e('Max Big Luggage', 'mhm-rentiva'); ?></label>
            <input type="number" id="mhm-transfer-max-big" name="transfer_max_big_luggage" min="0">
        </div>
        <div class="mhm-vendor-form__field">
            <label for="mhm-transfer-max-small"><?php esc_html_e('Max Small Luggage', 'mhm-rentiva'); ?></label>
            <input type="number" id="mhm-transfer-max-small" name="transfer_max_small_luggage" min="0">
        </div>
    </div>

    <!-- Service Locations -->
    <?php if (! empty($transfer_locations)) : ?>
    <div class="mhm-vendor-form__field">
        <label><?php esc_html_e('Service Locations', 'mhm-rentiva'); ?> <span class="required">*</span></label>
        <p class="mhm-vendor-form__hint"><?php esc_html_e('Select the locations where you can provide transfer service.', 'mhm-rentiva'); ?></p>
        <div class="mhm-vendor-form__checkboxes mhm-vendor-form__checkboxes--grid-4">
            <?php foreach ($transfer_locations as $loc) : ?>
                <label class="mhm-vendor-form__checkbox">
                    <input type="checkbox" name="transfer_locations[]" value="<?php echo esc_attr((string) $loc->id); ?>">
                    <?php echo esc_html($loc->name); ?>
                    <?php if (! empty($loc->type)) : ?>
                        <small class="mhm-vendor-form__checkbox-hint">(<?php echo esc_html($loc->type); ?>)</small>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transfer Routes with Price Input -->
    <?php if (! empty($transfer_routes)) : ?>
    <div class="mhm-vendor-form__field">
        <label><?php esc_html_e('Transfer Routes', 'mhm-rentiva'); ?></label>
        <p class="mhm-vendor-form__hint"><?php esc_html_e('Select routes and set your price for each.', 'mhm-rentiva'); ?></p>
        <div class="mhm-vendor-form__routes-list">
            <?php foreach ($transfer_routes as $route) :
                $min_p = (float) $route->min_price;
                $max_p = (float) $route->max_price;
                $price_hint = '';
                if ($min_p > 0 && $max_p > 0) {
                    $price_hint = sprintf(__('Range: %s – %s', 'mhm-rentiva'), number_format($min_p, 0, ',', '.') . ' ₺', number_format($max_p, 0, ',', '.') . ' ₺');
                } elseif ($min_p > 0) {
                    $price_hint = sprintf(__('Min: %s', 'mhm-rentiva'), number_format($min_p, 0, ',', '.') . ' ₺');
                } elseif ($max_p > 0) {
                    $price_hint = sprintf(__('Max: %s', 'mhm-rentiva'), number_format($max_p, 0, ',', '.') . ' ₺');
                }
            ?>
                <div class="mhm-vendor-form__route-item" style="display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid #eee;">
                    <label class="mhm-vendor-form__checkbox" style="flex:1;">
                        <input type="checkbox" name="transfer_routes[]" value="<?php echo esc_attr((string) $route->id); ?>" class="mhm-route-checkbox" data-route-id="<?php echo esc_attr((string) $route->id); ?>">
                        <?php echo esc_html($route->origin_name . ' → ' . $route->destination_name); ?>
                    </label>
                    <div class="mhm-vendor-form__route-price" style="width:140px;">
                        <input type="number" name="route_prices[<?php echo esc_attr((string) $route->id); ?>]"
                               step="0.01"
                               <?php if ($min_p > 0) : ?>min="<?php echo esc_attr((string) $min_p); ?>"<?php endif; ?>
                               <?php if ($max_p > 0) : ?>max="<?php echo esc_attr((string) $max_p); ?>"<?php endif; ?>
                               placeholder="<?php esc_attr_e('Price (₺)', 'mhm-rentiva'); ?>"
                               disabled
                               class="mhm-route-price-input"
                               data-route-id="<?php echo esc_attr((string) $route->id); ?>">
                        <?php if ($price_hint) : ?>
                            <small class="mhm-vendor-form__checkbox-hint"><?php echo esc_html($price_hint); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    (function(){
        document.querySelectorAll('.mhm-route-checkbox').forEach(function(cb){
            cb.addEventListener('change', function(){
                var routeId = this.dataset.routeId;
                var priceInput = document.querySelector('.mhm-route-price-input[data-route-id="'+routeId+'"]');
                if(priceInput){
                    priceInput.disabled = !this.checked;
                    if(!this.checked) priceInput.value = '';
                }
            });
        });
    })();
    </script>
    <?php endif; ?>
</div>
<?php endif; ?>
```

**Step 3: Save transfer capacity + route prices in `handle_submit()`**

In the `handle_submit()` method, find where transfer locations/routes are saved (~line 666-669). Add capacity meta saves and route prices after that block:

```php
// Transfer capacity fields.
if (in_array($service_type, ['transfer', 'both'], true)) {
    update_post_meta($post_id, '_rentiva_transfer_max_pax', absint($_POST['transfer_max_pax'] ?? 0));
    update_post_meta($post_id, '_rentiva_transfer_max_luggage_score', floatval($_POST['transfer_luggage_score'] ?? 0));
    update_post_meta($post_id, '_rentiva_vehicle_max_big_luggage', absint($_POST['transfer_max_big_luggage'] ?? 0));
    update_post_meta($post_id, '_rentiva_vehicle_max_small_luggage', absint($_POST['transfer_max_small_luggage'] ?? 0));

    // Vendor route prices.
    $raw_prices  = isset($_POST['route_prices']) ? (array) wp_unslash($_POST['route_prices']) : array();
    $route_prices = array();
    foreach ($raw_prices as $route_id => $price) {
        $rid = absint($route_id);
        $p   = floatval($price);
        if ($rid > 0 && $p > 0) {
            $route_prices[$rid] = $p;
        }
    }
    update_post_meta($post_id, '_mhm_rentiva_transfer_route_prices', wp_json_encode($route_prices));
}
```

**Step 4: Same for `handle_update()` AJAX handler**

In `handle_update()` (~line 898-901), after the existing transfer meta saves, add the same capacity + route prices block (adapted for AJAX context — data comes from `$_POST` same way).

**Step 5: Return capacity + route prices in `handle_get_edit_data()`**

In `handle_get_edit_data()` (~line 786-787), after the existing `transfer_locations` and `transfer_routes` entries, add:

```php
'transfer_max_pax'          => (int) get_post_meta($vehicle_id, '_rentiva_transfer_max_pax', true),
'transfer_luggage_score'    => (float) get_post_meta($vehicle_id, '_rentiva_transfer_max_luggage_score', true),
'transfer_max_big_luggage'  => (int) get_post_meta($vehicle_id, '_rentiva_vehicle_max_big_luggage', true),
'transfer_max_small_luggage'=> (int) get_post_meta($vehicle_id, '_rentiva_vehicle_max_small_luggage', true),
'transfer_route_prices'     => json_decode(get_post_meta($vehicle_id, '_mhm_rentiva_transfer_route_prices', true) ?: '{}', true),
```

**Step 6: Update JS edit form population**

In `assets/js/frontend/vehicle-submit.js`, find the edit form population code. Add population for the new fields when loading edit data:

```javascript
// Transfer capacity fields
if (data.transfer_max_pax) {
    document.getElementById('mhm-transfer-max-pax').value = data.transfer_max_pax;
}
if (data.transfer_luggage_score) {
    document.getElementById('mhm-transfer-luggage-score').value = data.transfer_luggage_score;
}
if (data.transfer_max_big_luggage) {
    document.getElementById('mhm-transfer-max-big').value = data.transfer_max_big_luggage;
}
if (data.transfer_max_small_luggage) {
    document.getElementById('mhm-transfer-max-small').value = data.transfer_max_small_luggage;
}

// Transfer route prices — check route checkboxes + fill prices
if (data.transfer_routes && Array.isArray(data.transfer_routes)) {
    data.transfer_routes.forEach(function(routeId) {
        var cb = document.querySelector('.mhm-route-checkbox[data-route-id="'+routeId+'"]');
        if (cb) {
            cb.checked = true;
            cb.dispatchEvent(new Event('change'));
        }
    });
}
if (data.transfer_route_prices && typeof data.transfer_route_prices === 'object') {
    Object.keys(data.transfer_route_prices).forEach(function(routeId) {
        var input = document.querySelector('.mhm-route-price-input[data-route-id="'+routeId+'"]');
        if (input) {
            input.value = data.transfer_route_prices[routeId];
        }
    });
}
```

**Step 7: Run full test suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

Expected: All tests pass.

**Step 8: Commit**

```bash
git add src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php assets/js/frontend/vehicle-submit.js
git commit -m "feat(vendor): city-filtered transfer UI with pricing and capacity fields"
```

---

## Task 5: TransferSearchEngine — route-based filtering + vendor pricing

**Files:**
- Modify: `src/Admin/Transfer/Engine/TransferSearchEngine.php:147-222`
- Create: `tests/Integration/Transfer/TransferSearchEngineRouteFilterTest.php`

**Step 1: Write the failing test**

Create `tests/Integration/Transfer/TransferSearchEngineRouteFilterTest.php`:

```php
<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Transfer;

use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;

class TransferSearchEngineRouteFilterTest extends \WP_UnitTestCase
{
    private int $route_id = 0;
    private int $vehicle_with_route = 0;
    private int $vehicle_without_route = 0;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;

        // Create a route.
        $routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
        $wpdb->insert($routes_table, [
            'origin_id'      => 1,
            'destination_id' => 2,
            'distance_km'    => 50,
            'duration_min'   => 60,
            'pricing_method' => 'fixed',
            'base_price'     => 500.00,
            'min_price'      => 300.00,
            'max_price'      => 800.00,
        ]);
        $this->route_id = (int) $wpdb->insert_id;

        // Vehicle WITH route assigned.
        $this->vehicle_with_route = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Transfer Van',
        ]);
        update_post_meta($this->vehicle_with_route, '_rentiva_vehicle_service_type', 'transfer');
        update_post_meta($this->vehicle_with_route, '_rentiva_transfer_max_pax', 8);
        update_post_meta($this->vehicle_with_route, '_rentiva_transfer_max_luggage_score', 20);
        update_post_meta($this->vehicle_with_route, '_mhm_rentiva_transfer_routes', [$this->route_id]);
        update_post_meta($this->vehicle_with_route, '_mhm_rentiva_transfer_route_prices', wp_json_encode([$this->route_id => 650.00]));

        // Vehicle WITHOUT route assigned.
        $this->vehicle_without_route = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Unassigned Van',
        ]);
        update_post_meta($this->vehicle_without_route, '_rentiva_vehicle_service_type', 'transfer');
        update_post_meta($this->vehicle_without_route, '_rentiva_transfer_max_pax', 8);
        update_post_meta($this->vehicle_without_route, '_rentiva_transfer_max_luggage_score', 20);
        // No _mhm_rentiva_transfer_routes set.
    }

    public function test_vehicle_without_route_assignment_is_excluded(): void
    {
        $results = TransferSearchEngine::search([
            'origin_id'      => 1,
            'destination_id' => 2,
            'date'           => wp_date('Y-m-d', strtotime('+7 days')),
            'time'           => '10:00',
            'adults'         => 2,
            'children'       => 0,
            'luggage_big'    => 0,
            'luggage_small'  => 0,
        ]);

        $ids = array_column($results, 'id');
        $this->assertContains($this->vehicle_with_route, $ids, 'Vehicle with route should be included');
        $this->assertNotContains($this->vehicle_without_route, $ids, 'Vehicle without route should be excluded');
    }

    public function test_vendor_price_is_used_over_base_price(): void
    {
        $results = TransferSearchEngine::search([
            'origin_id'      => 1,
            'destination_id' => 2,
            'date'           => wp_date('Y-m-d', strtotime('+7 days')),
            'time'           => '10:00',
            'adults'         => 2,
            'children'       => 0,
            'luggage_big'    => 0,
            'luggage_small'  => 0,
        ]);

        $vehicle = null;
        foreach ($results as $r) {
            if ($r['id'] === $this->vehicle_with_route) {
                $vehicle = $r;
                break;
            }
        }

        $this->assertNotNull($vehicle, 'Vehicle should be in results');
        $this->assertEquals(650.00, $vehicle['price'], 'Should use vendor price, not route base_price');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors --filter=TransferSearchEngineRouteFilterTest"`

Expected: FAIL — vehicle_without_route is included (no route filtering), price is 500 not 650.

**Step 3: Implement route filtering in TransferSearchEngine**

In `TransferSearchEngine::search()`, after the foreach loop starts (~line 150), add route assignment check right after the availability check:

```php
foreach ($vehicles as $vehicle) {
    // 5. Availability Check
    if (Util::has_overlap($vehicle->ID, $start_ts, $end_ts)) {
        continue;
    }

    // 5a. Route Assignment Check — vehicle must have this route in their selected routes.
    $assigned_routes = get_post_meta($vehicle->ID, '_mhm_rentiva_transfer_routes', true);
    if (is_array($assigned_routes) && ! empty($assigned_routes)) {
        if (! in_array((int) $route->id, array_map('intval', $assigned_routes), true)) {
            continue;
        }
    }
    // If no routes assigned at all, admin vehicles pass through (backward compat).
    // Vendor vehicles always have routes set via form, so they'll be filtered.
```

Then in the pricing section (~line 173-190), add vendor price lookup before the multiplier:

```php
// 6. Pricing Calculation
$price = 0.0;

// 6a. Check for vendor-specific route price first.
$vendor_route_prices_json = get_post_meta($vehicle->ID, '_mhm_rentiva_transfer_route_prices', true);
$vendor_route_prices      = is_string($vendor_route_prices_json) ? json_decode($vendor_route_prices_json, true) : array();
$vendor_price             = $vendor_route_prices[(string) $route->id] ?? $vendor_route_prices[(int) $route->id] ?? null;

if ($vendor_price !== null && (float) $vendor_price > 0) {
    $price = (float) $vendor_price;
} elseif ($route->pricing_method === 'fixed') {
    $price = (float) $route->base_price;
} else {
    $price = (float) $route->distance_km * (float) $route->base_price;
    if ((float) $route->min_price > 0 && $price < (float) $route->min_price) {
        $price = (float) $route->min_price;
    }
}

// 6b. Apply multiplier ONLY when using admin base_price (not vendor price).
if ($vendor_price === null || (float) $vendor_price <= 0) {
    $multiplier = get_post_meta($vehicle->ID, '_rentiva_transfer_price_multiplier', true);
    if (! $multiplier) {
        $multiplier = get_post_meta($vehicle->ID, '_mhm_transfer_price_multiplier', true);
    }
    if ($multiplier && is_numeric($multiplier)) {
        $price *= (float) $multiplier;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors --filter=TransferSearchEngineRouteFilterTest"`

Expected: 2 tests PASS.

**Step 5: Run full test suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

Expected: All tests pass.

**Step 6: Commit**

```bash
git add src/Admin/Transfer/Engine/TransferSearchEngine.php tests/Integration/Transfer/TransferSearchEngineRouteFilterTest.php
git commit -m "feat(transfer): route-based vehicle filtering + vendor-specific pricing in search"
```

---

## Task 6: VehicleTransferMetaBox — respect vendor city

**Files:**
- Modify: `src/Admin/Transfer/VehicleTransferMetaBox.php`

**Step 1: Read the existing render method to understand location/route display**

The existing meta box shows ALL locations/routes to admin. When admin edits a vendor vehicle, locations/routes should be filtered by the vendor's city (informational, not enforced — admin can still override).

**Step 2: Add vendor city detection and filtered view**

In `VehicleTransferMetaBox.php`, in the render callback, after retrieving the existing transfer meta values, add vendor detection:

```php
// Detect if this is a vendor vehicle.
$post_author   = (int) get_post_field('post_author', $post->ID);
$is_vendor_vehicle = in_array('rentiva_vendor', get_userdata($post_author)->roles ?? [], true);
$vendor_city   = '';
if ($is_vendor_vehicle) {
    $vendor_city = (string) get_user_meta($post_author, '_mhm_rentiva_vendor_city', true);
}
```

If `$vendor_city` is set, show an info notice above the transfer fields:

```php
if ($vendor_city !== '') {
    printf(
        '<p class="description" style="background:#fff8e5;padding:8px;border-left:3px solid #ffb900;margin-bottom:12px;">%s <strong>%s</strong></p>',
        esc_html__('Vendor city:', 'mhm-rentiva'),
        esc_html($vendor_city)
    );
}
```

This is informational only — admin retains full control. No location filtering enforced on admin side.

**Step 3: Run full test suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

Expected: All tests pass.

**Step 4: Commit**

```bash
git add src/Admin/Transfer/VehicleTransferMetaBox.php
git commit -m "feat(transfer): show vendor city info in admin vehicle transfer meta box"
```

---

## Task 7: Final Verification + Integration Test

**Step 1: Run full PHPUnit suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"`

Expected: 565+ tests (baseline 562 + 5 new), 0 failures.

**Step 2: Manual browser verification checklist**

1. Admin: Go to Transfer > Locations, verify city field appears in form and list table
2. Admin: Edit existing location, add city "Istanbul", save
3. Admin: Go to Transfer > Routes, verify max_price field appears
4. Admin: Edit existing route, set min_price=300, max_price=1000
5. Vendor: Go to vehicle submit form, select "Transfer" or "Both" service type
6. Vendor: Verify only their city's locations and routes appear
7. Vendor: Select a route, verify price input enables with min/max hint
8. Vendor: Submit vehicle, verify all meta saved correctly
9. Frontend: Search transfer route, verify vendor vehicle appears with vendor price
10. Frontend: Verify vehicle without route assignment does NOT appear

**Step 3: Commit all test files**

```bash
git add -A
git commit -m "test: verify vendor-transfer-location architecture integration"
```
