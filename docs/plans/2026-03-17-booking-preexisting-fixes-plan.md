# Booking Pre-Existing Bug Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix three pre-existing bugs: missing AJAX handler for customer account creation, non-UTC refund date storage, and missing nonce on email template loader.

**Architecture:** All fixes are isolated to existing PHP classes and one JS file. No new files needed. PHP handlers follow the existing pattern: capability check → nonce verify → logic → `wp_send_json_success/error`. JS nonce is passed via `wp_localize_script`.

**Tech Stack:** PHP 8.0+, WordPress 6.x, jQuery, WPCS standards.

---

### Task 1: Fix Refund Date — Use UTC Instead of Local Time

**Files:**
- Modify: `src/Admin/Booking/Actions/DepositManagementAjax.php:272`

This is the smallest fix. `current_time('mysql')` returns WordPress local time. All other timestamps in the system use `gmdate()` (UTC). One-line change.

**Step 1: Find and fix line 272**

Open `src/Admin/Booking/Actions/DepositManagementAjax.php`. Find the `process_refund()` method. Around line 272:

```php
// BEFORE:
update_post_meta( $booking_id, '_mhm_refund_date', current_time( 'mysql' ) );

// AFTER:
update_post_meta( $booking_id, '_mhm_refund_date', gmdate( 'Y-m-d H:i:s' ) );
```

**Step 2: Self-review**
- Only this one line changed
- No other `current_time('mysql')` calls introduced

**Step 3: Verify**
Go to a completed paid booking → process a refund → check `_mhm_refund_date` post meta value is UTC (matches GMT, not UTC+3).

---

### Task 2: Add Nonce to Email Template Loader

**Files:**
- Modify: `src/Admin/Booking/Meta/BookingMeta.php` (enqueue_scripts method, ~line 243)
- Modify: `assets/js/admin/booking-email-send.js` (~line 95)
- Modify: `src/Admin/Booking/Meta/BookingMeta.php` (ajax_get_email_template method, ~line 1436)

The `loadEmailTemplate()` JS function POSTs to `mhm_rentiva_get_email_template` without a nonce. The server handler only checks `current_user_can('edit_posts')` with no CSRF protection. The nonce field `mhm_rentiva_email_nonce` already exists on the page (created at line 657) — we just need to pass it to the JS and verify it in the handler.

**Step 1: Add email nonce to wp_localize_script in BookingMeta.php**

Find the `enqueue_scripts()` method (~line 224). The `wp_localize_script` call currently passes `ajaxUrl`, `bookingId`, and `strings`. Add `emailNonce` to this array:

```php
// Find the wp_localize_script call (around line 243):
wp_localize_script(
    'mhm-booking-email-send',
    'mhmBookingEmail',
    array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'bookingId' => $booking_id,
        'strings'   => array(
            'sending'         => __( 'Sending...', 'mhm-rentiva' ),
            'success'         => __( 'Email sent successfully!', 'mhm-rentiva' ),
            'error'           => __( 'Error:', 'mhm-rentiva' ),
            'unknownError'    => __( 'Unknown error', 'mhm-rentiva' ),
            'errorOccurred'   => __( 'An error occurred:', 'mhm-rentiva' ),
            'loadingTemplate' => __( 'Loading template...', 'mhm-rentiva' ),
        ),
    )
);

// AFTER — add emailNonce:
wp_localize_script(
    'mhm-booking-email-send',
    'mhmBookingEmail',
    array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'bookingId' => $booking_id,
        'emailNonce' => wp_create_nonce( 'mhm_rentiva_send_email' ),
        'strings'   => array(
            'sending'         => __( 'Sending...', 'mhm-rentiva' ),
            'success'         => __( 'Email sent successfully!', 'mhm-rentiva' ),
            'error'           => __( 'Error:', 'mhm-rentiva' ),
            'unknownError'    => __( 'Unknown error', 'mhm-rentiva' ),
            'errorOccurred'   => __( 'An error occurred:', 'mhm-rentiva' ),
            'loadingTemplate' => __( 'Loading template...', 'mhm-rentiva' ),
        ),
    )
);
```

**Step 2: Add nonce to loadEmailTemplate AJAX call in booking-email-send.js**

Find `loadEmailTemplate()` function (~line 88). The `$.ajax` data object currently has `action`, `booking_id`, `email_type`. Add `nonce`:

```js
// BEFORE:
data: {
    action:     'mhm_rentiva_get_email_template',
    booking_id: bookingId,
    email_type: emailType
    // ⚠️ NO NONCE HERE!
},

// AFTER:
data: {
    action:     'mhm_rentiva_get_email_template',
    booking_id: bookingId,
    email_type: emailType,
    nonce:      ( window.mhmBookingEmail && window.mhmBookingEmail.emailNonce ) ? window.mhmBookingEmail.emailNonce : '',
},
```

**Step 3: Add nonce verification to ajax_get_email_template in BookingMeta.php**

Find `ajax_get_email_template()` (~line 1436). Add nonce check after the capability check:

```php
// BEFORE:
public static function ajax_get_email_template()
{
    // Permission check
    if (! current_user_can('edit_posts')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
        return;
    }

    $booking_id = self::post_int('booking_id');
    $email_type = self::post_text('email_type');

// AFTER:
public static function ajax_get_email_template()
{
    // Permission check
    if (! current_user_can('edit_posts')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
        return;
    }

    // Nonce check
    $nonce = self::post_text( 'nonce' );
    if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_send_email' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
        return;
    }

    $booking_id = self::post_int('booking_id');
    $email_type = self::post_text('email_type');
```

**Step 4: Self-review**
- `emailNonce` passed in localized data
- `nonce` key added to AJAX POST data in JS
- `wp_verify_nonce` added to PHP handler before any processing
- No other changes

**Step 5: Verify**
1. Go to booking detail page → Email meta box
2. Change the email type dropdown → template should load correctly (nonce is valid)
3. Browser dev tools → Network → see POST request has `nonce` parameter

---

### Task 3: Implement Create Customer Account AJAX Handler

**Files:**
- Modify: `src/Admin/Booking/Meta/BookingPortalMetaBox.php`
- Modify: `src/Admin/Booking/Meta/BookingMeta.php` (enqueue_scripts, ~line 243)

The "Create Customer Account" button POSTs to `mhm_create_customer_account_manual` but no PHP handler exists. The button passes `booking_id`, `email`, `name`, `nonce` (nonce action: `mhm_create_customer_account`).

**Step 1: Add `register_ajax` and handler to BookingPortalMetaBox.php**

Add a static `register_ajax()` method and the AJAX handler. Also update `register()` to call it.

Find the `register()` method (line 21):

```php
// BEFORE:
public static function register(): void {
    add_action( 'add_meta_boxes', array( self::class, 'add' ) );
}

// AFTER:
public static function register(): void {
    add_action( 'add_meta_boxes', array( self::class, 'add' ) );
    add_action( 'wp_ajax_mhm_create_customer_account_manual', array( self::class, 'ajax_create_customer_account' ) );
}
```

**Step 2: Add the `ajax_create_customer_account()` method to BookingPortalMetaBox.php**

Add this method after the `render()` method (before the final closing `}`):

```php
public static function ajax_create_customer_account(): void {
    // Capability check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mhm-rentiva' ) ) );
        return;
    }

    // Nonce check
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'mhm_create_customer_account' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
        return;
    }

    // Get and validate input
    $booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
    $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

    if ( ! $booking_id || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid booking or email.', 'mhm-rentiva' ) ) );
        return;
    }

    // Verify booking exists and is correct post type
    $booking = get_post( $booking_id );
    if ( ! $booking || $booking->post_type !== 'vehicle_booking' ) {
        wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
        return;
    }

    // Check if user already exists
    $existing_user = get_user_by( 'email', $email );
    if ( $existing_user ) {
        // Link existing user to booking
        update_post_meta( $booking_id, '_mhm_customer_user_id', $existing_user->ID );
        wp_send_json_success( array(
            'message' => __( 'Existing account linked to booking.', 'mhm-rentiva' ),
        ) );
        return;
    }

    // Parse display name into first/last
    $name_parts  = explode( ' ', trim( $name ), 2 );
    $first_name  = $name_parts[0];
    $last_name   = isset( $name_parts[1] ) ? $name_parts[1] : '';
    $username    = sanitize_user( $email, true );

    // Create WP user
    $user_id = wp_create_user( $username, wp_generate_password(), $email );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
        return;
    }

    // Set display name and role
    wp_update_user( array(
        'ID'           => $user_id,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => trim( $name ) ?: $email,
        'role'         => 'customer',
    ) );

    // Link user to booking
    update_post_meta( $booking_id, '_mhm_customer_user_id', $user_id );

    wp_send_json_success( array(
        'message' => __( 'Customer account created and linked to booking.', 'mhm-rentiva' ),
    ) );
}
```

> **Note on role:** `'customer'` role is created by WooCommerce. If WooCommerce is not active, use `'subscriber'` as fallback. Use this logic:
> ```php
> $role = get_role( 'customer' ) ? 'customer' : 'subscriber';
> ```

**Step 3: Update role assignment in ajax_create_customer_account**

Replace `'role' => 'customer'` with the dynamic check:

```php
$role = get_role( 'customer' ) ? 'customer' : 'subscriber';
wp_update_user( array(
    'ID'           => $user_id,
    'first_name'   => $first_name,
    'last_name'    => $last_name,
    'display_name' => trim( $name ) ?: $email,
    'role'         => $role,
) );
```

**Step 4: Add confirmCreateAccount and confirmRefund to wp_localize_script**

In `BookingMeta.php` `enqueue_scripts()` (same place as Task 2), the localized data also needs the confirm strings that JS references but are never passed. Add to the array:

```php
'confirmCreateAccount' => __( 'Create a WordPress account for this customer?', 'mhm-rentiva' ),
'confirmRefund'        => __( 'Proceed with refund?', 'mhm-rentiva' ),
```

Full updated `wp_localize_script` call (combining Task 2 + Task 3):

```php
wp_localize_script(
    'mhm-booking-email-send',
    'mhmBookingEmail',
    array(
        'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
        'bookingId'            => $booking_id,
        'emailNonce'           => wp_create_nonce( 'mhm_rentiva_send_email' ),
        'confirmCreateAccount' => __( 'Create a WordPress account for this customer?', 'mhm-rentiva' ),
        'confirmRefund'        => __( 'Proceed with refund?', 'mhm-rentiva' ),
        'strings'              => array(
            'sending'         => __( 'Sending...', 'mhm-rentiva' ),
            'success'         => __( 'Email sent successfully!', 'mhm-rentiva' ),
            'error'           => __( 'Error:', 'mhm-rentiva' ),
            'unknownError'    => __( 'Unknown error', 'mhm-rentiva' ),
            'errorOccurred'   => __( 'An error occurred:', 'mhm-rentiva' ),
            'loadingTemplate' => __( 'Loading template...', 'mhm-rentiva' ),
        ),
    )
);
```

**Step 5: Self-review**
- `wp_ajax_mhm_create_customer_account_manual` hooked in `register()`
- Handler verifies: capability → nonce → valid input → valid booking post type
- Handles: existing user (link) vs new user (create + link)
- Role: WooCommerce customer if exists, else subscriber
- `wp_generate_password()` for random password
- All `$_POST` values use `wp_unslash()` + sanitize
- `confirmCreateAccount` and `confirmRefund` strings in localized data
- `emailNonce` in localized data

**Step 6: Verify**
1. Go to a booking with no linked WP user
2. "Customer Account" meta box shows "No customer account linked" and email
3. Click "Create Customer Account" → confirm dialog shows translated string (not hardcoded English)
4. Click OK → loading state → success toast → page reloads
5. After reload: meta box shows user name, email, Active status, profile and account buttons
6. In WP admin → Users: new user exists with correct email, name, and customer/subscriber role

---

## Test Checklist

- [ ] Refund date stored as UTC (not UTC+3 local time) after processing a refund
- [ ] Email template loads correctly when changing email type (nonce validation passes)
- [ ] Create Customer Account button works end-to-end
- [ ] Existing user by email: gets linked without duplicate creation
- [ ] New user: created with correct name, role, random password
- [ ] Confirm dialog uses translated string from mhmBookingEmail.confirmCreateAccount
- [ ] Confirm refund dialog uses translated string from mhmBookingEmail.confirmRefund
