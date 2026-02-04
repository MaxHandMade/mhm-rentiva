---
name: mhm-test-architect
description: Creates PHPUnit tests for MHM Rentiva plugin components using WordPress test library. Defines test structure, naming conventions, mocking strategies, and integration test patterns. Use when writing unit tests, creating test files, testing critical components, or when the user mentions PHPUnit, testing, test coverage, or WP_UnitTestCase.
---

# MHM Test Architect

**Role:** The Testing Architect for MHM Rentiva.  
**Motto:** "Every critical component deserves reliable tests."

## When to Use This Skill

Apply this skill when:
- Writing PHPUnit tests for plugin components
- Creating new test files for classes or features
- Testing critical components like SecurityHelper, BookingForm, VehicleRepository
- User mentions PHPUnit, testing, test coverage, or WP_UnitTestCase
- Setting up integration tests with WooCommerce
- Refactoring code that requires test updates

## Test Environment and Structure

The plugin uses WordPress test library. Tests should be located in `tests/` directory and follow the `MHMRentiva` namespace structure.

## Test Writing Rules

### 1. Class Naming

Test classes must start with the tested class name and end with `Test` suffix.
- Class: `SecurityHelper` → Test: `SecurityHelperTest`
- Location: `tests/Admin/Core/SecurityHelperTest.php`

### 2. Namespace Usage

Test files should follow the plugin's namespace structure, with `Tests` sub-namespace added.

```php
namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\SecurityHelper;
use WP_UnitTestCase;
```

### 3. Example Test Scenario: SecurityHelper

Example structure for testing static helper classes like `SecurityHelper`:

```php
class SecurityHelperTest extends WP_UnitTestCase {
    
    // Runs before each test
    public function setUp(): void {
        parent::setUp();
        // Create necessary mock user or data
    }

    /** @test */ // Annotation usage
    public function it_validates_vehicle_id_correctly() {
        // Valid ID
        $valid_id = SecurityHelper::validate_vehicle_id(123);
        $this->assertEquals(123, $valid_id);

        // Invalid ID (Exception Expected)
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_vehicle_id(0);
    }

    /** @test */
    public function it_verifies_nonce_correctly() {
        // Create nonce
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_POST['nonce'] = $nonce;

        // Validation
        $result = SecurityHelper::verify_ajax_request($action, 'read');
        $this->assertTrue($result);
    }
}
```

### 4. Important Considerations

- **Mocking:** Use `Mockery` or PHPUnit mock objects for classes with database or external service dependencies.
- **Database Cleanup:** `WP_UnitTestCase` automatically rolls back the database to pre-test state after each test. No manual cleanup needed.
- **Coverage Priority:** Focus on data-processing classes like `SecurityHelper`, `BookingForm` (partial), and `VehicleRepository`.

## Integration Tests

For testing plugin interactions with WooCommerce:

```php
/** @test */
public function it_creates_order_when_booking_confirmed() {
    // 1. Create product and vehicle (using Factory)
    $vehicle_id = $this->factory->post->create(['post_type' => 'mhm_vehicle']);
    
    // 2. Simulate order
    $order = wc_create_order();
    $order->add_product(wc_get_product($vehicle_id), 1);
    $order->calculate_totals();
    
    // 3. Assertion
    $this->assertEquals('pending', $order->get_status());
}
```

## Test Data Generation (Factories)

Use WordPress test library factory methods:
- `$this->factory->post->create(...)`
- `$this->factory->user->create(...)`
- `$this->factory->term->create(...)`

These methods generate data that is automatically cleaned up after tests.

## Goal

Every new feature or critical refactoring should have its corresponding test file created or updated.
