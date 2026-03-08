# Vendor Marketplace Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** P2P araç kiralama/transfer platformu için Pro-only vendor marketplace: admin onaylı kayıt, araç ekleme, escrow ödeme, iade/iptal akışı.

**Architecture:** Mevcut CommissionBridge/Ledger/PayoutService dokunulmadan korunur. Yeni bileşenler eklenir: `mhm_vendor_application` CPT, `VendorApplicationManager`, `VendorOnboardingController`, `VendorMediaIsolation`, `VendorVehicleReviewManager`, 2 shortcode. Pro-only `Mode::canUseVendorMarketplace()` flag'i tüm yeni kodu korur.

**Tech Stack:** PHP 7.4+, WordPress 6.5+, WooCommerce (opsiyonel), PHPUnit 9.6, WPCS 3.3

**Design Doc:** `docs/plans/2026-03-08-vendor-marketplace-design.md`

---

## Task 1: Pro Feature Flag

**Files:**
- Modify: `src/Admin/Licensing/Mode.php`
- Test: `tests/Admin/Licensing/ModeVendorMarketplaceTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Licensing;

use MHMRentiva\Admin\Licensing\Mode;
use PHPUnit\Framework\TestCase;

class ModeVendorMarketplaceTest extends TestCase
{
    public function test_canUseVendorMarketplace_returns_bool(): void
    {
        $result = Mode::canUseVendorMarketplace();
        $this->assertIsBool($result);
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
vendor/bin/phpunit tests/Admin/Licensing/ModeVendorMarketplaceTest.php -v
```

Expected: `Error: Call to undefined method ... canUseVendorMarketplace()`

**Step 3: Implement**

`src/Admin/Licensing/Mode.php` içindeki son `canUse*` metodunun hemen altına ekle:

```php
public static function canUseVendorMarketplace(): bool
{
    return self::isPro();
}
```

**Step 4: Run test — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Licensing/ModeVendorMarketplaceTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Licensing/Mode.php tests/Admin/Licensing/ModeVendorMarketplaceTest.php
git commit -m "feat: add canUseVendorMarketplace() Pro feature flag"
```

---

## Task 2: `rentiva_vendor` Role Registration

**Files:**
- Modify: `src/Plugin.php`
- Test: `tests/VendorRoleRegistrationTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit;

use PHPUnit\Framework\TestCase;

class VendorRoleRegistrationTest extends TestCase
{
    public function test_register_vendor_role_creates_role(): void
    {
        // Arrange: ensure role doesn't exist
        remove_role('rentiva_vendor');

        // Act
        \MHMRentiva\Plugin::register_vendor_role();

        // Assert
        $role = get_role('rentiva_vendor');
        $this->assertNotNull($role, 'rentiva_vendor role should exist after registration');
        $this->assertTrue($role->has_cap('read'));
        $this->assertTrue($role->has_cap('upload_files'));
    }

    public function test_register_vendor_role_is_idempotent(): void
    {
        \MHMRentiva\Plugin::register_vendor_role();
        \MHMRentiva\Plugin::register_vendor_role(); // second call should not error
        $this->assertNotNull(get_role('rentiva_vendor'));
    }

    protected function tearDown(): void
    {
        remove_role('rentiva_vendor');
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/VendorRoleRegistrationTest.php -v
```

**Step 3: Implement**

`src/Plugin.php` içinde `register_customer_role()` metodunun hemen altına ekle:

```php
/**
 * Register the rentiva_vendor WordPress role.
 * Called once during plugin activation and on init.
 * Idempotent — safe to call multiple times.
 */
public static function register_vendor_role(): void
{
    if ( get_role( 'rentiva_vendor' ) ) {
        return;
    }

    add_role(
        'rentiva_vendor',
        __( 'Rentiva Vendor', 'mhm-rentiva' ),
        array(
            'read'         => true,
            'upload_files' => true,
        )
    );
}
```

Aynı dosyada `register_customer_role()` çağrısı neredeyse (init hook veya activate hook), yanına ekle:

```php
self::register_vendor_role();
```

**Step 4: Run test — verify PASS**

```bash
vendor/bin/phpunit tests/VendorRoleRegistrationTest.php -v
```

**Step 5: Commit**

```bash
git add src/Plugin.php tests/VendorRoleRegistrationTest.php
git commit -m "feat: register rentiva_vendor WordPress role"
```

---

## Task 3: `mhm_vendor_application` Custom Post Type

**Files:**
- Create: `src/Admin/Vendor/PostType/VendorApplication.php`
- Modify: `src/Plugin.php` (register the CPT)
- Test: `tests/Admin/Vendor/VendorApplicationPostTypeTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Vendor;

use MHMRentiva\Admin\Vendor\PostType\VendorApplication;
use PHPUnit\Framework\TestCase;

class VendorApplicationPostTypeTest extends TestCase
{
    public function test_post_type_constant_is_correct(): void
    {
        $this->assertSame('mhm_vendor_application', VendorApplication::POST_TYPE);
    }

    public function test_register_post_type_registers_cpt(): void
    {
        VendorApplication::register_post_type();
        $this->assertTrue(post_type_exists('mhm_vendor_application'));
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorApplicationPostTypeTest.php -v
```

**Step 3: Implement**

Yeni dosya oluştur: `src/Admin/Vendor/PostType/VendorApplication.php`

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\PostType;

/**
 * Registers the mhm_vendor_application custom post type.
 * Stores vendor onboarding applications with document uploads and status tracking.
 */
final class VendorApplication
{
    public const POST_TYPE = 'mhm_vendor_application';

    /**
     * Hook into WordPress init.
     */
    public static function register(): void
    {
        add_action( 'init', array( self::class, 'register_post_type' ) );
    }

    /**
     * Register the CPT.
     */
    public static function register_post_type(): void
    {
        $labels = array(
            'name'               => __( 'Vendor Applications', 'mhm-rentiva' ),
            'singular_name'      => __( 'Vendor Application', 'mhm-rentiva' ),
            'menu_name'          => __( 'Vendor Applications', 'mhm-rentiva' ),
            'add_new'            => __( 'Add New', 'mhm-rentiva' ),
            'add_new_item'       => __( 'Add New Application', 'mhm-rentiva' ),
            'edit_item'          => __( 'Review Application', 'mhm-rentiva' ),
            'view_item'          => __( 'View Application', 'mhm-rentiva' ),
            'search_items'       => __( 'Search Applications', 'mhm-rentiva' ),
            'not_found'          => __( 'No applications found.', 'mhm-rentiva' ),
            'not_found_in_trash' => __( 'No applications found in trash.', 'mhm-rentiva' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'mhm-rentiva',
            'query_var'          => false,
            'rewrite'            => false,
            'capabilities'       => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
            ),
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array( 'title', 'author' ),
            'show_in_rest'       => false,
        );

        register_post_type( self::POST_TYPE, $args );
    }
}
```

`src/Plugin.php` içinde CPT'yi kaydet (diğer CPT kayıtlarının yanına):

```php
\MHMRentiva\Admin\Vendor\PostType\VendorApplication::register();
```

**Step 4: Run test — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorApplicationPostTypeTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Vendor/PostType/VendorApplication.php src/Plugin.php tests/Admin/Vendor/VendorApplicationPostTypeTest.php
git commit -m "feat: register mhm_vendor_application CPT"
```

---

## Task 4: `VendorApplicationManager` — Core Logic

**Files:**
- Create: `src/Admin/Vendor/VendorApplicationManager.php`
- Test: `tests/Admin/Vendor/VendorApplicationManagerTest.php`

**Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Vendor;

use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use PHPUnit\Framework\TestCase;

class VendorApplicationManagerTest extends TestCase
{
    public function test_can_apply_returns_false_if_already_vendor(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User( $user_id );
        $user->add_role( 'rentiva_vendor' );

        $result = VendorApplicationManager::can_apply( $user_id );
        $this->assertFalse( $result );
    }

    public function test_can_apply_returns_true_for_plain_customer(): void
    {
        $user_id = $this->factory()->user->create();
        $result = VendorApplicationManager::can_apply( $user_id );
        $this->assertTrue( $result );
    }

    public function test_can_apply_returns_false_if_pending_application_exists(): void
    {
        $user_id = $this->factory()->user->create();

        // Create pending application
        wp_insert_post( array(
            'post_type'   => 'mhm_vendor_application',
            'post_author' => $user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Application',
        ) );

        $result = VendorApplicationManager::can_apply( $user_id );
        $this->assertFalse( $result );
    }

    public function test_create_application_returns_post_id(): void
    {
        $user_id = $this->factory()->user->create();

        $data = array(
            'phone'    => '+90 555 123 4567',
            'city'     => 'Istanbul',
            'iban'     => 'TR123456789012345678901234',
            'doc_id'   => 0,
            'doc_license'   => 0,
            'doc_address'   => 0,
            'doc_insurance' => 0,
        );

        $result = VendorApplicationManager::create_application( $user_id, $data );
        $this->assertIsInt( $result );
        $this->assertGreaterThan( 0, $result );
    }

    public function test_create_application_returns_error_if_cannot_apply(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User( $user_id );
        $user->add_role( 'rentiva_vendor' );

        $result = VendorApplicationManager::create_application( $user_id, array() );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorApplicationManagerTest.php -v
```

**Step 3: Implement**

`src/Admin/Vendor/VendorApplicationManager.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

/**
 * CRUD operations and state transitions for vendor applications.
 */
final class VendorApplicationManager
{
    /**
     * Application status values.
     */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'publish';
    public const STATUS_REJECTED = 'trash';

    /**
     * Determine if a user is eligible to submit a vendor application.
     *
     * @param int $user_id
     * @return bool
     */
    public static function can_apply( int $user_id ): bool
    {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Already a vendor.
        if ( in_array( 'rentiva_vendor', (array) $user->roles, true ) ) {
            return false;
        }

        // Has a pending application.
        $existing = get_posts( array(
            'post_type'      => VendorApplication::POST_TYPE,
            'author'         => $user_id,
            'post_status'    => self::STATUS_PENDING,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );

        if ( ! empty( $existing ) ) {
            return false;
        }

        return true;
    }

    /**
     * Create a new vendor application.
     *
     * @param int   $user_id
     * @param array $data {
     *     @type string $phone
     *     @type string $city
     *     @type string $iban
     *     @type array  $service_areas
     *     @type string $bio           Optional.
     *     @type string $tax_number    Optional.
     *     @type int    $doc_id
     *     @type int    $doc_license
     *     @type int    $doc_address
     *     @type int    $doc_insurance
     * }
     * @return int|\WP_Error Post ID on success.
     */
    public static function create_application( int $user_id, array $data )
    {
        if ( ! self::can_apply( $user_id ) ) {
            return new \WP_Error(
                'cannot_apply',
                __( 'You are not eligible to submit a vendor application.', 'mhm-rentiva' )
            );
        }

        $user = get_userdata( $user_id );
        $title = sprintf(
            /* translators: 1: user display name */
            __( 'Vendor Application — %s', 'mhm-rentiva' ),
            $user ? $user->display_name : (string) $user_id
        );

        $post_id = wp_insert_post( array(
            'post_type'   => VendorApplication::POST_TYPE,
            'post_author' => $user_id,
            'post_status' => self::STATUS_PENDING,
            'post_title'  => sanitize_text_field( $title ),
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Store application meta.
        update_post_meta( $post_id, '_vendor_phone',     sanitize_text_field( $data['phone'] ?? '' ) );
        update_post_meta( $post_id, '_vendor_city',      sanitize_text_field( $data['city'] ?? '' ) );
        update_post_meta( $post_id, '_vendor_iban',      self::encrypt_iban( $data['iban'] ?? '' ) );
        update_post_meta( $post_id, '_vendor_service_areas', array_map( 'sanitize_text_field', (array) ( $data['service_areas'] ?? array() ) ) );
        update_post_meta( $post_id, '_vendor_profile_bio',   sanitize_textarea_field( $data['bio'] ?? '' ) );
        update_post_meta( $post_id, '_vendor_tax_number',    sanitize_text_field( $data['tax_number'] ?? '' ) );
        update_post_meta( $post_id, '_vendor_doc_id',        (int) ( $data['doc_id'] ?? 0 ) );
        update_post_meta( $post_id, '_vendor_doc_license',   (int) ( $data['doc_license'] ?? 0 ) );
        update_post_meta( $post_id, '_vendor_doc_address',   (int) ( $data['doc_address'] ?? 0 ) );
        update_post_meta( $post_id, '_vendor_doc_insurance', (int) ( $data['doc_insurance'] ?? 0 ) );
        update_post_meta( $post_id, '_vendor_status',        self::STATUS_PENDING );

        return $post_id;
    }

    /**
     * Get an application by ID with basic validation.
     *
     * @param int $application_id
     * @return \WP_Post|\WP_Error
     */
    public static function get_application( int $application_id )
    {
        $post = get_post( $application_id );
        if ( ! $post instanceof \WP_Post || $post->post_type !== VendorApplication::POST_TYPE ) {
            return new \WP_Error( 'invalid_application', __( 'Invalid application ID.', 'mhm-rentiva' ) );
        }
        return $post;
    }

    /**
     * Encrypt IBAN before storage.
     * Uses a deterministic key derived from WP auth keys.
     *
     * @param string $iban
     * @return string Encrypted base64 string, or empty string on failure.
     */
    public static function encrypt_iban( string $iban ): string
    {
        if ( $iban === '' ) {
            return '';
        }

        $key    = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_SALT ), 0, 32 );
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $iban, 'AES-256-CBC', $key, 0, $iv );

        if ( $cipher === false ) {
            return '';
        }

        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt a stored IBAN value.
     *
     * @param string $encrypted
     * @return string Plain IBAN or empty string on failure.
     */
    public static function decrypt_iban( string $encrypted ): string
    {
        if ( $encrypted === '' ) {
            return '';
        }

        $key  = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_SALT ), 0, 32 );
        $raw  = base64_decode( $encrypted );
        $iv   = substr( $raw, 0, 16 );
        $data = substr( $raw, 16 );

        $plain = openssl_decrypt( $data, 'AES-256-CBC', $key, 0, $iv );
        return $plain !== false ? $plain : '';
    }
}
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorApplicationManagerTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Vendor/VendorApplicationManager.php tests/Admin/Vendor/VendorApplicationManagerTest.php
git commit -m "feat: add VendorApplicationManager with CRUD and eligibility checks"
```

---

## Task 5: `VendorOnboardingController` — Approve/Reject + Meta Sync

**Files:**
- Create: `src/Admin/Vendor/VendorOnboardingController.php`
- Test: `tests/Admin/Vendor/VendorOnboardingControllerTest.php`

**Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Vendor;

use MHMRentiva\Admin\Vendor\VendorOnboardingController;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use PHPUnit\Framework\TestCase;

class VendorOnboardingControllerTest extends TestCase
{
    private int $user_id;
    private int $application_id;

    protected function setUp(): void
    {
        $this->user_id = $this->factory()->user->create();

        $data = array(
            'phone'         => '+90 555 000 0001',
            'city'          => 'Ankara',
            'iban'          => 'TR000000000000000000000001',
            'service_areas' => array( 'Ankara', 'Istanbul' ),
            'bio'           => 'Test bio',
            'doc_id'        => 1,
            'doc_license'   => 2,
            'doc_address'   => 3,
            'doc_insurance' => 4,
        );

        $this->application_id = VendorApplicationManager::create_application( $this->user_id, $data );
    }

    public function test_approve_assigns_vendor_role(): void
    {
        $result = VendorOnboardingController::approve( $this->application_id );

        $this->assertTrue( $result );

        $user = get_userdata( $this->user_id );
        $this->assertContains( 'rentiva_vendor', $user->roles );
    }

    public function test_approve_syncs_user_meta(): void
    {
        VendorOnboardingController::approve( $this->application_id );

        $city = get_user_meta( $this->user_id, '_rentiva_vendor_city', true );
        $this->assertSame( 'Ankara', $city );

        $areas = get_user_meta( $this->user_id, '_rentiva_vendor_service_areas', true );
        $this->assertContains( 'Istanbul', $areas );
    }

    public function test_reject_stores_rejection_note(): void
    {
        $reason = 'Belgeler eksik.';
        $result = VendorOnboardingController::reject( $this->application_id, $reason );

        $this->assertTrue( $result );

        $note = get_post_meta( $this->application_id, '_vendor_rejection_note', true );
        $this->assertSame( $reason, $note );
    }

    public function test_reject_does_not_assign_vendor_role(): void
    {
        VendorOnboardingController::reject( $this->application_id, 'Test reason' );

        $user = get_userdata( $this->user_id );
        $this->assertNotContains( 'rentiva_vendor', $user->roles );
    }

    protected function tearDown(): void
    {
        remove_role( 'rentiva_vendor' );
        wp_delete_user( $this->user_id );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorOnboardingControllerTest.php -v
```

**Step 3: Implement**

`src/Admin/Vendor/VendorOnboardingController.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

/**
 * Handles vendor application approval and rejection workflows.
 * Assigns roles, syncs user meta, and dispatches email notifications.
 */
final class VendorOnboardingController
{
    /**
     * Approve a vendor application.
     * - Assigns rentiva_vendor role
     * - Syncs key meta fields to user meta
     * - Updates application status to approved
     * - Fires do_action('mhm_rentiva_vendor_approved', $user_id, $application_id)
     *
     * @param int $application_id
     * @return true|\WP_Error
     */
    public static function approve( int $application_id )
    {
        $application = VendorApplicationManager::get_application( $application_id );
        if ( is_wp_error( $application ) ) {
            return $application;
        }

        $user_id = (int) $application->post_author;
        $user    = get_userdata( $user_id );

        if ( ! $user ) {
            return new \WP_Error( 'invalid_user', __( 'Associated user not found.', 'mhm-rentiva' ) );
        }

        // Assign vendor role.
        $user->add_role( 'rentiva_vendor' );

        // Sync meta to user meta for fast retrieval.
        $meta_map = array(
            '_vendor_phone'         => '_rentiva_vendor_phone',
            '_vendor_city'          => '_rentiva_vendor_city',
            '_vendor_iban'          => '_rentiva_vendor_iban',
            '_vendor_service_areas' => '_rentiva_vendor_service_areas',
            '_vendor_profile_bio'   => '_rentiva_vendor_bio',
        );

        foreach ( $meta_map as $post_meta_key => $user_meta_key ) {
            $value = get_post_meta( $application_id, $post_meta_key, true );
            update_user_meta( $user_id, $user_meta_key, $value );
        }

        // Record approval timestamp and approver.
        $now = current_time( 'mysql' );
        update_post_meta( $application_id, '_vendor_approved_at', $now );
        update_post_meta( $application_id, '_vendor_approved_by', get_current_user_id() );
        update_post_meta( $application_id, '_vendor_status', VendorApplicationManager::STATUS_APPROVED );
        update_user_meta( $user_id, '_rentiva_vendor_approved_at', $now );

        // Update post status to published.
        wp_update_post( array(
            'ID'          => $application_id,
            'post_status' => VendorApplicationManager::STATUS_APPROVED,
        ) );

        do_action( 'mhm_rentiva_vendor_approved', $user_id, $application_id );

        return true;
    }

    /**
     * Reject a vendor application.
     * - Stores rejection note
     * - Updates application status to rejected (trash)
     * - Fires do_action('mhm_rentiva_vendor_rejected', $user_id, $application_id, $reason)
     *
     * @param int    $application_id
     * @param string $reason Admin's rejection note.
     * @return true|\WP_Error
     */
    public static function reject( int $application_id, string $reason )
    {
        $application = VendorApplicationManager::get_application( $application_id );
        if ( is_wp_error( $application ) ) {
            return $application;
        }

        $user_id = (int) $application->post_author;

        update_post_meta( $application_id, '_vendor_rejection_note', sanitize_textarea_field( $reason ) );
        update_post_meta( $application_id, '_vendor_status', VendorApplicationManager::STATUS_REJECTED );

        wp_update_post( array(
            'ID'          => $application_id,
            'post_status' => VendorApplicationManager::STATUS_REJECTED,
        ) );

        do_action( 'mhm_rentiva_vendor_rejected', $user_id, $application_id, $reason );

        return true;
    }
}
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorOnboardingControllerTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Vendor/VendorOnboardingController.php tests/Admin/Vendor/VendorOnboardingControllerTest.php
git commit -m "feat: add VendorOnboardingController with approve/reject + meta sync"
```

---

## Task 6: `DashboardContext` — vendor_application_pending State

**Files:**
- Modify: `src/Core/Dashboard/DashboardContext.php`
- Test: `tests/Core/Dashboard/DashboardContextVendorTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Dashboard;

use MHMRentiva\Core\Dashboard\DashboardContext;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use PHPUnit\Framework\TestCase;

class DashboardContextVendorTest extends TestCase
{
    public function test_resolve_returns_vendor_application_pending_for_pending_applicant(): void
    {
        $user_id = $this->factory()->user->create();
        wp_set_current_user( $user_id );

        // Create pending application
        wp_insert_post( array(
            'post_type'   => 'mhm_vendor_application',
            'post_author' => $user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test',
        ) );

        $result = DashboardContext::resolve();
        $this->assertSame( 'vendor_application_pending', $result );
    }

    protected function tearDown(): void
    {
        wp_set_current_user( 0 );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Core/Dashboard/DashboardContextVendorTest.php -v
```

**Step 3: Implement**

`src/Core/Dashboard/DashboardContext.php` içindeki `resolve()` metoduna `vendor` kontrolünden önce ekle:

```php
// Check for pending vendor application.
$pending = get_posts( array(
    'post_type'      => 'mhm_vendor_application',
    'author'         => $user->ID,
    'post_status'    => 'pending',
    'posts_per_page' => 1,
    'fields'         => 'ids',
) );

if ( ! empty( $pending ) ) {
    return 'vendor_application_pending';
}
```

`UserDashboard::render()` içinde bu yeni durumu handle et:

```php
if ( 'vendor_application_pending' === $type ) {
    return '<div class="mhm-rentiva-notice">' .
        esc_html__( 'Your vendor application is under review. We will notify you by email.', 'mhm-rentiva' ) .
        '</div>';
}
```

**Step 4: Run test — verify PASS**

```bash
vendor/bin/phpunit tests/Core/Dashboard/DashboardContextVendorTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Dashboard/DashboardContext.php src/Admin/Frontend/Shortcodes/Account/UserDashboard.php tests/Core/Dashboard/DashboardContextVendorTest.php
git commit -m "feat: add vendor_application_pending context to DashboardContext"
```

---

## Task 7: `VendorMediaIsolation` — Per-user Media Library

**Files:**
- Create: `src/Admin/Vendor/VendorMediaIsolation.php`
- Modify: `src/Plugin.php` (register hooks)
- Test: `tests/Admin/Vendor/VendorMediaIsolationTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Vendor;

use MHMRentiva\Admin\Vendor\VendorMediaIsolation;
use PHPUnit\Framework\TestCase;

class VendorMediaIsolationTest extends TestCase
{
    public function test_filter_query_restricts_to_current_user_for_vendor(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User( $user_id );
        $user->add_role( 'rentiva_vendor' );
        wp_set_current_user( $user_id );

        $query = array( 'orderby' => 'date' );
        $result = VendorMediaIsolation::filter_ajax_query( $query );

        $this->assertArrayHasKey( 'author', $result );
        $this->assertSame( $user_id, $result['author'] );
    }

    public function test_filter_query_does_not_restrict_for_admin(): void
    {
        $admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        $query = array( 'orderby' => 'date' );
        $result = VendorMediaIsolation::filter_ajax_query( $query );

        $this->assertArrayNotHasKey( 'author', $result );
    }

    protected function tearDown(): void
    {
        wp_set_current_user( 0 );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorMediaIsolationTest.php -v
```

**Step 3: Implement**

`src/Admin/Vendor/VendorMediaIsolation.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

/**
 * Prevents vendors from accessing other vendors' media uploads.
 * Filters the media library query to restrict results to the current user's uploads.
 */
final class VendorMediaIsolation
{
    /**
     * Register WordPress hooks.
     */
    public static function register(): void
    {
        add_filter( 'ajax_query_attachments_args', array( self::class, 'filter_ajax_query' ) );
    }

    /**
     * Restrict media library AJAX query to current user's uploads for vendors.
     *
     * @param array $query WP_Query arguments.
     * @return array Modified query arguments.
     */
    public static function filter_ajax_query( array $query ): array
    {
        if ( current_user_can( 'manage_options' ) ) {
            return $query;
        }

        if ( ! current_user_can( 'rentiva_vendor' ) && ! in_array( 'rentiva_vendor', (array) wp_get_current_user()->roles, true ) ) {
            return $query;
        }

        $query['author'] = get_current_user_id();
        return $query;
    }
}
```

`src/Plugin.php` içine register çağrısı ekle:

```php
\MHMRentiva\Admin\Vendor\VendorMediaIsolation::register();
```

**Step 4: Run test — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorMediaIsolationTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Vendor/VendorMediaIsolation.php src/Plugin.php tests/Admin/Vendor/VendorMediaIsolationTest.php
git commit -m "feat: isolate media library per vendor user"
```

---

## Task 8: `VendorVehicleReviewManager` — Araç Onay/Red + Partial Edit

**Files:**
- Create: `src/Admin/Vendor/VendorVehicleReviewManager.php`
- Test: `tests/Admin/Vendor/VendorVehicleReviewManagerTest.php`

**Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Vendor;

use MHMRentiva\Admin\Vendor\VendorVehicleReviewManager;
use PHPUnit\Framework\TestCase;

class VendorVehicleReviewManagerTest extends TestCase
{
    private int $vehicle_id;

    protected function setUp(): void
    {
        $this->vehicle_id = $this->factory()->post->create( array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
        ) );
        update_post_meta( $this->vehicle_id, '_vehicle_review_status', 'pending_review' );
    }

    public function test_approve_sets_review_status_to_approved(): void
    {
        VendorVehicleReviewManager::approve( $this->vehicle_id );
        $status = get_post_meta( $this->vehicle_id, '_vehicle_review_status', true );
        $this->assertSame( 'approved', $status );
    }

    public function test_approve_publishes_vehicle(): void
    {
        VendorVehicleReviewManager::approve( $this->vehicle_id );
        $post = get_post( $this->vehicle_id );
        $this->assertSame( 'publish', $post->post_status );
    }

    public function test_reject_sets_review_status_and_note(): void
    {
        $reason = 'Fotoğraflar yetersiz.';
        VendorVehicleReviewManager::reject( $this->vehicle_id, $reason );

        $status = get_post_meta( $this->vehicle_id, '_vehicle_review_status', true );
        $note   = get_post_meta( $this->vehicle_id, '_vehicle_rejection_note', true );

        $this->assertSame( 'rejected', $status );
        $this->assertSame( $reason, $note );
    }

    public function test_is_critical_field_detects_price(): void
    {
        $this->assertTrue( VendorVehicleReviewManager::is_critical_field( 'price_per_day' ) );
    }

    public function test_is_critical_field_detects_non_critical(): void
    {
        $this->assertFalse( VendorVehicleReviewManager::is_critical_field( 'description' ) );
    }

    public function test_handle_vendor_edit_sets_partial_edit_for_critical_field(): void
    {
        // First, publish the vehicle
        wp_update_post( array( 'ID' => $this->vehicle_id, 'post_status' => 'publish' ) );
        update_post_meta( $this->vehicle_id, '_vehicle_review_status', 'approved' );

        VendorVehicleReviewManager::handle_vendor_edit( $this->vehicle_id, array( 'price_per_day' => 500 ) );

        $status = get_post_meta( $this->vehicle_id, '_vehicle_review_status', true );
        $this->assertSame( 'pending_review', $status );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorVehicleReviewManagerTest.php -v
```

**Step 3: Implement**

`src/Admin/Vendor/VendorVehicleReviewManager.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

/**
 * Manages vehicle review lifecycle: pending_review → approved | rejected.
 * Differentiates critical vs. minor edits to determine re-review requirement.
 */
final class VendorVehicleReviewManager
{
    /**
     * Fields that require admin re-review when changed on a published vehicle.
     */
    private const CRITICAL_FIELDS = array(
        'price_per_day',
        'service_type',
        'city',
        'service_areas',
        'vehicle_year',
    );

    /**
     * Approve a vehicle: publish it and mark as approved.
     *
     * @param int $vehicle_id
     * @return true|\WP_Error
     */
    public static function approve( int $vehicle_id )
    {
        $post = get_post( $vehicle_id );
        if ( ! $post || $post->post_type !== 'vehicle' ) {
            return new \WP_Error( 'invalid_vehicle', __( 'Invalid vehicle ID.', 'mhm-rentiva' ) );
        }

        wp_update_post( array( 'ID' => $vehicle_id, 'post_status' => 'publish' ) );
        update_post_meta( $vehicle_id, '_vehicle_review_status', 'approved' );
        delete_post_meta( $vehicle_id, '_vehicle_rejection_note' );

        do_action( 'mhm_rentiva_vehicle_approved', $vehicle_id, (int) $post->post_author );

        return true;
    }

    /**
     * Reject a vehicle with a reason.
     *
     * @param int    $vehicle_id
     * @param string $reason
     * @return true|\WP_Error
     */
    public static function reject( int $vehicle_id, string $reason )
    {
        $post = get_post( $vehicle_id );
        if ( ! $post || $post->post_type !== 'vehicle' ) {
            return new \WP_Error( 'invalid_vehicle', __( 'Invalid vehicle ID.', 'mhm-rentiva' ) );
        }

        update_post_meta( $vehicle_id, '_vehicle_review_status', 'rejected' );
        update_post_meta( $vehicle_id, '_vehicle_rejection_note', sanitize_textarea_field( $reason ) );

        do_action( 'mhm_rentiva_vehicle_rejected', $vehicle_id, (int) $post->post_author, $reason );

        return true;
    }

    /**
     * Determine if an edit to a published vehicle requires re-review.
     * Critical fields (price, service type, city) → pending_review.
     * Minor fields (description, photos) → immediate update.
     *
     * @param int   $vehicle_id
     * @param array $changed_fields Keys of fields being changed.
     * @return void
     */
    public static function handle_vendor_edit( int $vehicle_id, array $changed_fields ): void
    {
        $post = get_post( $vehicle_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }

        foreach ( array_keys( $changed_fields ) as $field ) {
            if ( self::is_critical_field( $field ) ) {
                update_post_meta( $vehicle_id, '_vehicle_review_status', 'pending_review' );
                do_action( 'mhm_rentiva_vehicle_needs_rereview', $vehicle_id );
                return;
            }
        }
    }

    /**
     * Check if a field name is considered critical (requires re-review on edit).
     *
     * @param string $field_name
     * @return bool
     */
    public static function is_critical_field( string $field_name ): bool
    {
        return in_array( $field_name, self::CRITICAL_FIELDS, true );
    }
}
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Vendor/VendorVehicleReviewManagerTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Vendor/VendorVehicleReviewManager.php tests/Admin/Vendor/VendorVehicleReviewManagerTest.php
git commit -m "feat: add VendorVehicleReviewManager with approve/reject/partial-edit logic"
```

---

## Task 9: `PayoutService::create_refund_entry()` — Ledger Iade

**Files:**
- Modify: `src/Core/Financial/PayoutService.php`
- Test: `tests/Core/Financial/PayoutServiceRefundTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Financial;

use MHMRentiva\Core\Financial\PayoutService;
use MHMRentiva\Core\Financial\Ledger;
use PHPUnit\Framework\TestCase;

class PayoutServiceRefundTest extends TestCase
{
    public function test_create_refund_entry_returns_error_for_zero_amount(): void
    {
        $result = PayoutService::create_refund_entry( 1, 0, 0.0 );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_amount', $result->get_error_code() );
    }

    public function test_create_refund_entry_returns_error_for_negative_amount(): void
    {
        $result = PayoutService::create_refund_entry( 1, 0, -50.0 );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_create_refund_entry_creates_ledger_debit(): void
    {
        $vendor_id  = 1;
        $booking_id = 0;
        $amount     = 150.0;

        $result = PayoutService::create_refund_entry( $vendor_id, $booking_id, $amount );
        $this->assertTrue( $result );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Core/Financial/PayoutServiceRefundTest.php -v
```

**Step 3: Implement**

`src/Core/Financial/PayoutService.php` içine son metodun altına ekle:

```php
/**
 * Create a refund ledger entry to reverse a commission credit.
 * Called when a completed booking is refunded or cancelled after clearing.
 *
 * @param int    $vendor_id
 * @param int    $booking_id  0 if not associated with a specific booking.
 * @param float  $amount      Positive amount to refund (will be stored as negative).
 * @return true|\WP_Error
 */
public static function create_refund_entry( int $vendor_id, int $booking_id, float $amount )
{
    if ( $amount <= 0 ) {
        return new \WP_Error(
            'invalid_amount',
            __( 'Refund amount must be greater than zero.', 'mhm-rentiva' )
        );
    }

    $uuid  = 'refund_' . $vendor_id . '_' . $booking_id . '_' . time();
    $entry = new LedgerEntry(
        $uuid,
        $vendor_id,
        $booking_id ?: null,
        null,  // order_id
        'refund',
        $amount * -1,  // negative — reduces balance
        null,
        null,
        null,
        'TRY',
        'vendor',
        'cleared'
    );

    try {
        Ledger::add_entry( $entry );
    } catch ( \RuntimeException $e ) {
        return new \WP_Error( 'ledger_error', $e->getMessage() );
    }

    MetricCacheManager::flush_subject_all_metrics( (string) $vendor_id );

    return true;
}
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Core/Financial/PayoutServiceRefundTest.php -v
```

**Step 5: Commit**

```bash
git add src/Core/Financial/PayoutService.php tests/Core/Financial/PayoutServiceRefundTest.php
git commit -m "feat: add PayoutService::create_refund_entry() for booking refund/cancellation"
```

---

## Task 10: `[rentiva_vendor_apply]` Shortcode — Frontend Başvuru Formu

**Files:**
- Create: `src/Admin/Frontend/Shortcodes/Account/VendorApply.php`
- Create: `templates/shortcodes/vendor-apply.php`
- Modify: `src/Admin/Core/ShortcodeServiceProvider.php` (register shortcode)
- Test: `tests/Admin/Frontend/Shortcodes/VendorApplyShortcodeTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\VendorApply;
use PHPUnit\Framework\TestCase;

class VendorApplyShortcodeTest extends TestCase
{
    public function test_shortcode_tag_is_correct(): void
    {
        $this->assertSame( 'rentiva_vendor_apply', VendorApply::get_shortcode_tag_public() );
    }

    public function test_render_returns_pro_notice_in_lite_mode(): void
    {
        // Mode::canUseVendorMarketplace() returns false in test environment (lite)
        $output = VendorApply::render_for_test( array() );
        $this->assertStringContainsString( 'pro', strtolower( $output ) );
    }

    public function test_render_returns_empty_for_guest(): void
    {
        wp_set_current_user( 0 );
        $output = VendorApply::render_for_test( array(), true ); // force pro=true
        $this->assertSame( '', $output );
    }

    protected function tearDown(): void
    {
        wp_set_current_user( 0 );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Frontend/Shortcodes/VendorApplyShortcodeTest.php -v
```

**Step 3: Implement**

`src/Admin/Frontend/Shortcodes/Account/VendorApply.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;

/**
 * [rentiva_vendor_apply] — Frontend vendor application form.
 * Pro-only. Shows eligibility check, form fields, and document upload inputs.
 */
final class VendorApply extends AbstractShortcode
{
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vendor_apply';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/vendor-apply';
    }

    protected static function get_default_attributes(): array
    {
        return array();
    }

    protected static function prepare_template_data( array $atts ): array
    {
        if ( ! Mode::canUseVendorMarketplace() ) {
            return array( 'pro_required' => true );
        }

        if ( ! is_user_logged_in() ) {
            return array( 'login_required' => true );
        }

        $user_id   = get_current_user_id();
        $can_apply = VendorApplicationManager::can_apply( $user_id );

        return array(
            'pro_required'   => false,
            'login_required' => false,
            'can_apply'      => $can_apply,
            'user_id'        => $user_id,
            'nonce'          => wp_create_nonce( 'mhm_vendor_apply' ),
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
        );
    }

    /**
     * Public accessor for test assertions.
     * @internal
     */
    public static function get_shortcode_tag_public(): string
    {
        return self::get_shortcode_tag();
    }

    /**
     * Render helper for tests.
     * @internal
     * @param array $atts
     * @param bool  $force_pro Bypass Mode check in test.
     * @return string
     */
    public static function render_for_test( array $atts, bool $force_pro = false ): string
    {
        if ( ! $force_pro && ! Mode::canUseVendorMarketplace() ) {
            return '<p class="mhm-pro-required">' . esc_html__( 'This feature requires the Pro version.', 'mhm-rentiva' ) . '</p>';
        }

        $data = self::prepare_template_data( $atts );
        if ( ! empty( $data['login_required'] ) ) {
            return '';
        }

        // Simple render for test environment
        return '<form class="mhm-vendor-apply-form"></form>';
    }

    protected static function enqueue_assets( array $atts = array() ): void
    {
        wp_enqueue_style(
            'mhm-rentiva-vendor-apply',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vendor-apply.css',
            array(),
            MHM_RENTIVA_VERSION
        );
    }
}
```

Aynı zamanda `templates/shortcodes/vendor-apply.php` dosyasını oluştur — temel PHP/HTML form şablonu (ayrıntılı template kodu Task 11 ile birlikte CSS ile gelir).

`src/Admin/Core/ShortcodeServiceProvider.php` içine shortcode'u kaydet (mevcut kayıt satırlarının yanına):

```php
\MHMRentiva\Admin\Frontend\Shortcodes\Account\VendorApply::class,
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Frontend/Shortcodes/VendorApplyShortcodeTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Frontend/Shortcodes/Account/VendorApply.php src/Admin/Core/ShortcodeServiceProvider.php tests/Admin/Frontend/Shortcodes/VendorApplyShortcodeTest.php
git commit -m "feat: add [rentiva_vendor_apply] shortcode skeleton (Pro-only)"
```

---

## Task 11: `[rentiva_vehicle_submit]` Shortcode — Araç Ekleme Formu

**Files:**
- Create: `src/Admin/Frontend/Shortcodes/Account/VehicleSubmit.php`
- Create: `templates/shortcodes/vehicle-submit.php`
- Modify: `src/Admin/Core/ShortcodeServiceProvider.php`
- Test: `tests/Admin/Frontend/Shortcodes/VehicleSubmitShortcodeTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\VehicleSubmit;
use PHPUnit\Framework\TestCase;

class VehicleSubmitShortcodeTest extends TestCase
{
    public function test_shortcode_tag(): void
    {
        $this->assertSame( 'rentiva_vehicle_submit', VehicleSubmit::get_shortcode_tag_public() );
    }

    public function test_returns_pro_notice_in_lite(): void
    {
        $output = VehicleSubmit::render_for_test( array() );
        $this->assertStringContainsString( 'pro', strtolower( $output ) );
    }

    public function test_returns_empty_for_non_vendor(): void
    {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );
        $output = VehicleSubmit::render_for_test( array(), true );
        $this->assertSame( '', $output );
    }

    public function test_returns_form_for_vendor(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User( $user_id );
        $user->add_role( 'rentiva_vendor' );
        wp_set_current_user( $user_id );

        $output = VehicleSubmit::render_for_test( array(), true );
        $this->assertStringContainsString( 'vehicle-submit', $output );
    }

    protected function tearDown(): void
    {
        wp_set_current_user( 0 );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Frontend/Shortcodes/VehicleSubmitShortcodeTest.php -v
```

**Step 3: Implement**

`src/Admin/Frontend/Shortcodes/Account/VehicleSubmit.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;

/**
 * [rentiva_vehicle_submit] — Vendor vehicle submission form.
 * Pro-only. Requires rentiva_vendor role.
 */
final class VehicleSubmit extends AbstractShortcode
{
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vehicle_submit';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/vehicle-submit';
    }

    protected static function get_default_attributes(): array
    {
        return array( 'vehicle_id' => 0 ); // 0 = new, int = edit
    }

    protected static function prepare_template_data( array $atts ): array
    {
        if ( ! Mode::canUseVendorMarketplace() ) {
            return array( 'pro_required' => true );
        }

        if ( ! is_user_logged_in() ) {
            return array( 'login_required' => true );
        }

        $user = wp_get_current_user();
        if ( ! in_array( 'rentiva_vendor', (array) $user->roles, true ) ) {
            return array( 'vendor_only' => true );
        }

        $vehicle_id = (int) ( $atts['vehicle_id'] ?? 0 );
        $is_edit    = $vehicle_id > 0;

        return array(
            'pro_required'   => false,
            'login_required' => false,
            'vendor_only'    => false,
            'is_edit'        => $is_edit,
            'vehicle_id'     => $vehicle_id,
            'nonce'          => wp_create_nonce( 'mhm_vehicle_submit' ),
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
        );
    }

    /** @internal */
    public static function get_shortcode_tag_public(): string
    {
        return self::get_shortcode_tag();
    }

    /** @internal */
    public static function render_for_test( array $atts, bool $force_pro = false ): string
    {
        if ( ! $force_pro && ! Mode::canUseVendorMarketplace() ) {
            return '<p class="mhm-pro-required">' . esc_html__( 'Pro required.', 'mhm-rentiva' ) . '</p>';
        }

        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user = wp_get_current_user();
        if ( ! in_array( 'rentiva_vendor', (array) $user->roles, true ) ) {
            return '';
        }

        return '<form class="mhm-vehicle-submit-form"></form>';
    }
}
```

`ShortcodeServiceProvider.php` içine ekle:

```php
\MHMRentiva\Admin\Frontend\Shortcodes\Account\VehicleSubmit::class,
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Frontend/Shortcodes/VehicleSubmitShortcodeTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Frontend/Shortcodes/Account/VehicleSubmit.php src/Admin/Core/ShortcodeServiceProvider.php tests/Admin/Frontend/Shortcodes/VehicleSubmitShortcodeTest.php
git commit -m "feat: add [rentiva_vehicle_submit] shortcode skeleton (Pro-only)"
```

---

## Task 12: Admin UI — Vendor Applications List & Approve/Reject Actions

**Files:**
- Create: `src/Admin/Vendor/AdminVendorApplicationsPage.php`
- Modify: `src/Plugin.php` (register admin menu)
- Test: `tests/Admin/Vendor/AdminVendorApplicationsPageTest.php`

**Step 1: Write failing test**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Vendor;

use MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage;
use PHPUnit\Framework\TestCase;

class AdminVendorApplicationsPageTest extends TestCase
{
    public function test_handle_approval_action_calls_onboarding_controller(): void
    {
        $user_id = $this->factory()->user->create();

        $application_id = wp_insert_post( array(
            'post_type'   => 'mhm_vendor_application',
            'post_author' => $user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Application',
        ) );

        // Simulate the admin action
        $result = AdminVendorApplicationsPage::process_approve( $application_id );
        $this->assertTrue( $result );

        $user = get_userdata( $user_id );
        $this->assertContains( 'rentiva_vendor', $user->roles );
    }

    public function test_handle_rejection_action(): void
    {
        $user_id = $this->factory()->user->create();

        $application_id = wp_insert_post( array(
            'post_type'   => 'mhm_vendor_application',
            'post_author' => $user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Application',
        ) );

        $result = AdminVendorApplicationsPage::process_reject( $application_id, 'Test rejection' );
        $this->assertTrue( $result );

        $note = get_post_meta( $application_id, '_vendor_rejection_note', true );
        $this->assertSame( 'Test rejection', $note );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
vendor/bin/phpunit tests/Admin/Vendor/AdminVendorApplicationsPageTest.php -v
```

**Step 3: Implement**

`src/Admin/Vendor/AdminVendorApplicationsPage.php`:

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

use MHMRentiva\Admin\Licensing\Mode;

/**
 * Registers and handles the WP Admin "Vendor Applications" menu page.
 * Only visible in Pro mode.
 */
final class AdminVendorApplicationsPage
{
    /**
     * Register admin hooks.
     */
    public static function register(): void
    {
        if ( ! Mode::canUseVendorMarketplace() ) {
            return;
        }

        add_action( 'admin_menu', array( self::class, 'add_submenu' ) );
        add_action( 'admin_post_mhm_approve_vendor', array( self::class, 'handle_approval_request' ) );
        add_action( 'admin_post_mhm_reject_vendor',  array( self::class, 'handle_rejection_request' ) );
    }

    /**
     * Add submenu under the main Rentiva admin menu.
     */
    public static function add_submenu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __( 'Vendor Applications', 'mhm-rentiva' ),
            __( 'Vendor Applications', 'mhm-rentiva' ),
            'manage_options',
            'mhm-vendor-applications',
            array( self::class, 'render_page' )
        );
    }

    /**
     * Render the admin list page.
     */
    public static function render_page(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'mhm-rentiva' ) );
        }

        $applications = get_posts( array(
            'post_type'      => 'mhm_vendor_application',
            'post_status'    => array( 'pending', 'publish', 'trash' ),
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Vendor Applications', 'mhm-rentiva' ) . '</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Applicant', 'mhm-rentiva' ) . '</th>';
        echo '<th>' . esc_html__( 'City', 'mhm-rentiva' ) . '</th>';
        echo '<th>' . esc_html__( 'Date', 'mhm-rentiva' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'mhm-rentiva' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'mhm-rentiva' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $applications as $app ) {
            $user   = get_userdata( (int) $app->post_author );
            $city   = get_post_meta( $app->ID, '_vendor_city', true );
            $status = get_post_meta( $app->ID, '_vendor_status', true );

            $approve_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=mhm_approve_vendor&application_id=' . $app->ID ),
                'mhm_approve_vendor_' . $app->ID
            );
            $reject_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=mhm_reject_vendor&application_id=' . $app->ID ),
                'mhm_reject_vendor_' . $app->ID
            );

            echo '<tr>';
            echo '<td>' . esc_html( $user ? $user->display_name : '—' ) . '</td>';
            echo '<td>' . esc_html( $city ) . '</td>';
            echo '<td>' . esc_html( $app->post_date ) . '</td>';
            echo '<td>' . esc_html( $status ) . '</td>';
            echo '<td>';

            if ( $app->post_status === 'pending' ) {
                echo '<a href="' . esc_url( $approve_url ) . '" class="button button-primary">' . esc_html__( 'Approve', 'mhm-rentiva' ) . '</a> ';
                echo '<a href="' . esc_url( $reject_url ) . '" class="button">' . esc_html__( 'Reject', 'mhm-rentiva' ) . '</a>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Handle admin approval POST action.
     */
    public static function handle_approval_request(): void
    {
        $application_id = (int) ( $_GET['application_id'] ?? 0 );

        if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'mhm_approve_vendor_' . $application_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'mhm-rentiva' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'mhm-rentiva' ) );
        }

        self::process_approve( $application_id );
        wp_safe_redirect( admin_url( 'admin.php?page=mhm-vendor-applications&approved=1' ) );
        exit;
    }

    /**
     * Handle admin rejection POST action.
     */
    public static function handle_rejection_request(): void
    {
        $application_id = (int) ( $_GET['application_id'] ?? 0 );

        if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'mhm_reject_vendor_' . $application_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'mhm-rentiva' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'mhm-rentiva' ) );
        }

        $reason = sanitize_textarea_field( $_POST['rejection_reason'] ?? '' );
        self::process_reject( $application_id, $reason );
        wp_safe_redirect( admin_url( 'admin.php?page=mhm-vendor-applications&rejected=1' ) );
        exit;
    }

    /**
     * Process approval (testable, no redirects).
     *
     * @param int $application_id
     * @return true|\WP_Error
     */
    public static function process_approve( int $application_id )
    {
        return VendorOnboardingController::approve( $application_id );
    }

    /**
     * Process rejection (testable, no redirects).
     *
     * @param int    $application_id
     * @param string $reason
     * @return true|\WP_Error
     */
    public static function process_reject( int $application_id, string $reason )
    {
        return VendorOnboardingController::reject( $application_id, $reason );
    }
}
```

`src/Plugin.php` içine register çağrısı ekle:

```php
\MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage::register();
```

**Step 4: Run tests — verify PASS**

```bash
vendor/bin/phpunit tests/Admin/Vendor/AdminVendorApplicationsPageTest.php -v
```

**Step 5: Commit**

```bash
git add src/Admin/Vendor/AdminVendorApplicationsPage.php src/Plugin.php tests/Admin/Vendor/AdminVendorApplicationsPageTest.php
git commit -m "feat: add vendor applications admin page with approve/reject actions"
```

---

## Task 13: Full Test Suite — Regression Check

**Tüm testleri çalıştır. Yeni kodun mevcutları kırmadığını doğrula.**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
vendor/bin/phpunit -c phpunit.xml --testdox
```

**Beklenen:**
- Tüm önceki testler geçiyor (268+ baseline)
- Yeni testler de geçiyor
- 0 failure, 0 error

**Eğer hata varsa:** Hata mesajını oku, ilgili Task'a dön, düzelt, tekrar çalıştır.

**Commit (sadece düzeltme yapıldıysa):**

```bash
git add -p
git commit -m "fix: resolve test regression from vendor marketplace implementation"
```

---

## Task 14: PHPCS Compliance Check

```bash
vendor/bin/phpcs --standard=phpcs.xml src/Admin/Vendor/ -n
```

**Beklenen:** No errors (warnings ignore edilebilir)

**Sorun varsa:**

```bash
vendor/bin/phpcbf --standard=phpcs.xml src/Admin/Vendor/ -n
```

Auto-fix sonrası tekrar check:

```bash
vendor/bin/phpcs --standard=phpcs.xml src/Admin/Vendor/ -n
```

**Commit:**

```bash
git add src/Admin/Vendor/
git commit -m "style: fix PHPCS violations in vendor marketplace code"
```

---

## Tamamlanma Kriterleri

- [ ] `Mode::canUseVendorMarketplace()` → bool döner
- [ ] `rentiva_vendor` rolü plugin activation'da oluşuyor
- [ ] `mhm_vendor_application` CPT kayıtlı
- [ ] `VendorApplicationManager::can_apply()` unique check yapıyor
- [ ] Onay → rol atanıyor + user meta sync
- [ ] Red → gerekçe kaydediliyor
- [ ] Media library vendor'a izole
- [ ] Araç düzenleme: kritik field → pending_review, minor → anlık
- [ ] Refund → Ledger'da negatif entry oluşuyor
- [ ] `[rentiva_vendor_apply]` → Lite'ta pro notice, vendor'a form
- [ ] `[rentiva_vehicle_submit]` → sadece vendor rolüne form
- [ ] Admin sayfası → pending applications listesi + onayla/reddet
- [ ] Tüm PHPUnit testleri geçiyor
- [ ] PHPCS temiz

---

## Notlar

**Mode check pattern:**
```php
if ( ! Mode::canUseVendorMarketplace() ) {
    return; // or return WP_Error / pro notice HTML
}
```

**Mevcut dokunulmayacak dosyalar:**
- `src/Core/Financial/Ledger.php`
- `src/Core/Financial/CommissionBridge.php`
- `src/Core/Financial/CommissionResolver.php`
- `src/Admin/PostTypes/Payouts/PostType.php`

**Email notifications** (Task 5'te `do_action()` hook'ları ateşlendi):
- `mhm_rentiva_vendor_approved` → `VendorOnboardingController::approve()`
- `mhm_rentiva_vendor_rejected` → `VendorOnboardingController::reject()`
- `mhm_rentiva_vehicle_approved` → `VendorVehicleReviewManager::approve()`
- `mhm_rentiva_vehicle_rejected` → `VendorVehicleReviewManager::reject()`

Email handler sınıfı bu hook'lara bağlanabilir — MVP sonrası.
