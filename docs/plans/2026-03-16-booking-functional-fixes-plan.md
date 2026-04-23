# Booking Functional Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix 3 critical bugs (HTML render, gateway translation, status dropdown), standardize timezone handling in deadline storage, clean inline styles/scripts from Portal and Refund meta boxes, and add history pagination.

**Architecture:** WordPress CPT-based booking system. Admin pages use PHP meta boxes with jQuery AJAX. Deadlines stored as UTC strings in post meta. No test framework — verification is done via WordPress admin browser checks.

**Tech Stack:** PHP 8.0+, WordPress 6.x, jQuery, WPCS coding standards, `var(--mhm-*)` CSS tokens.

---

### Task 1: Fix HTML Template Literals in manual-booking-meta.js

**Files:**
- Modify: `assets/js/admin/manual-booking-meta.js`

All HTML template literals in this file use malformed syntax: `< div class = "..." >` (spaces around angle brackets). The browser treats these as text, not HTML.

**Step 1: Find all malformed template literals**

Open `assets/js/admin/manual-booking-meta.js` and search for `< div` or `< span` or `< / ` patterns. Key locations:
- Line 164–178: `showVehicleInfo` template
- Line 247–280: price calculation template
- Line 397: `showMessage` template

**Step 2: Fix line 397 (showMessage)**

```js
// BEFORE (line 397):
const messageHtml = ` < div class = "mhm-message ${type}" > ${message} < / div > `;

// AFTER:
const messageHtml = `<div class="mhm-message ${type}">${message}</div>`;
```

**Step 3: Fix lines 164–178 (showVehicleInfo) — read the exact template and remove all spaces inside angle brackets**

Pattern to fix throughout: every `< tag` → `<tag`, every `< / tag >` → `</tag>`, every `class = "` → `class="`.

**Step 4: Fix lines 247–280 (price calculation template) — same pattern**

**Step 5: Verify in browser**

1. Go to wp-admin → Rezervasyonlar → Yeni Ekle
2. Select a vehicle and dates that are already booked
3. Click "Fiyatı Hesapla"
4. Error message should appear as styled red box — NOT as raw `< div class = ...` text

**Step 6: Commit**

```bash
git add assets/js/admin/manual-booking-meta.js
git commit -m "fix: remove spaces from HTML template literals in manual-booking-meta.js"
```

---

### Task 2: Fix Gateway Label — Add WooCommerce Translation

**Files:**
- Modify: `src/Admin/Booking/ListTable/BookingColumns.php:565-571`

**Step 1: Update `get_payment_gateway_label()` method**

Find the method at line 565. Replace the entire method body:

```php
// BEFORE (lines 566-571):
private static function get_payment_gateway_label( string $gateway ): string {
    $labels = array(
        'offline' => __( 'Offline', 'mhm-rentiva' ),
    );

    return $labels[ $gateway ] ?? strtoupper( $gateway );
}

// AFTER:
private static function get_payment_gateway_label( string $gateway ): string {
    $labels = array(
        'offline'       => __( 'Offline', 'mhm-rentiva' ),
        'woocommerce'   => __( 'WooCommerce', 'mhm-rentiva' ),
        'bank_transfer' => __( 'Banka Transferi', 'mhm-rentiva' ),
        'cash'          => __( 'Nakit', 'mhm-rentiva' ),
    );

    return $labels[ $gateway ] ?? ucwords( str_replace( '_', ' ', $gateway ) );
}
```

**Step 2: Verify in browser**

Go to wp-admin → Rezervasyonlar list. The "Ödeme" column should show "WooCommerce" instead of "WOOCOMMERCE" or "Pending [WOOCOMMERCE]".

**Step 3: Commit**

```bash
git add src/Admin/Booking/ListTable/BookingColumns.php
git commit -m "fix: add WooCommerce and other gateway translations to payment column"
```

---

### Task 3: Fix Status Dropdown in New Booking Form

**Files:**
- Modify: `src/Admin/Booking/Meta/ManualBookingMetaBox.php`

Status class is NOT imported in this file. The dropdown hardcodes 4 options. Only `pending` and `confirmed` make sense when creating a new booking.

**Step 1: Add Status use statement**

Find the use statements at the top of the file (lines 9–11). Add after line 11:

```php
use MHMRentiva\Admin\Booking\Core\Status;
```

**Step 2: Replace hardcoded status dropdown (lines 401–409)**

```php
// BEFORE (lines 401-409):
echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_booking_status" class="mhm-field-label">' . esc_html__( 'Status', 'mhm-rentiva' ) . '</label>';
echo '<select id="mhm_manual_booking_status" name="mhm_manual_status" class="mhm-field-select">';
echo '<option value="pending">' . esc_html__( 'Pending', 'mhm-rentiva' ) . '</option>';
echo '<option value="confirmed" selected>' . esc_html__( 'Confirmed', 'mhm-rentiva' ) . '</option>';
echo '<option value="in_progress">' . esc_html__( 'In Progress', 'mhm-rentiva' ) . '</option>';
echo '<option value="completed">' . esc_html__( 'Completed', 'mhm-rentiva' ) . '</option>';
echo '</select>';
echo '</div>';

// AFTER:
$initial_statuses = array( Status::PENDING, Status::CONFIRMED );
echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_booking_status" class="mhm-field-label">' . esc_html__( 'Status', 'mhm-rentiva' ) . '</label>';
echo '<select id="mhm_manual_booking_status" name="mhm_manual_status" class="mhm-field-select">';
foreach ( $initial_statuses as $status_key ) {
    printf(
        '<option value="%s"%s>%s</option>',
        esc_attr( $status_key ),
        selected( $status_key, Status::CONFIRMED, false ),
        esc_html( Status::get_label( $status_key ) )
    );
}
echo '</select>';
echo '</div>';
```

**Step 3: Verify in browser**

Go to wp-admin → Rezervasyonlar → Yeni Ekle. Scroll to the "Durum" dropdown — should show only "Pending" and "Confirmed" (not In Progress or Completed).

**Step 4: Commit**

```bash
git add src/Admin/Booking/Meta/ManualBookingMetaBox.php
git commit -m "fix: replace hardcoded status dropdown with dynamic Status class in manual booking form"
```

---

### Task 4: Fix Timezone in Deadline Storage

**Files:**
- Modify: `src/Admin/Booking/Core/Handler.php:483-514`
- Modify: `src/Admin/Booking/Actions/DepositManagementAjax.php:257-261`

**CRITICAL:** Do NOT touch `AutoCancel.php` — it uses `post_date` comparison and works correctly. Do NOT touch `Util.php` buffer time — it works correctly. Only change the two files below.

**Step 1: Fix `get_cancellation_deadline()` in Handler.php**

Find `get_cancellation_deadline()` at line 475. Fix the two `strtotime()` calls:

```php
// BEFORE (line 485):
return gmdate('Y-m-d H:i:s', strtotime('-1 day'));

// AFTER:
return gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
```

```php
// BEFORE (line 488):
return gmdate('Y-m-d H:i:s', strtotime("+{$deadline_hours} hours"));

// AFTER:
return gmdate( 'Y-m-d H:i:s', time() + ( $deadline_hours * HOUR_IN_SECONDS ) );
```

**Step 2: Fix `get_payment_deadline()` in Handler.php**

Find `get_payment_deadline()` at line 496. Fix lines 512–514:

```php
// BEFORE (lines 512-514):
// ⭐ Use current_time() instead of date() to match WordPress timezone
// This ensures consistency with AutoCancel which uses current_time('mysql')
$current_timestamp  = current_time('timestamp');
$deadline_timestamp = $current_timestamp + ($deadline_minutes * 60);
return gmdate('Y-m-d H:i:s', $deadline_timestamp);

// AFTER:
return gmdate( 'Y-m-d H:i:s', time() + ( $deadline_minutes * MINUTE_IN_SECONDS ) );
```

Also update the docblock comment at line 494 to reflect the fix:
```php
// BEFORE:
* @return string Payment deadline in 'Y-m-d H:i:s' format (WordPress timezone)

// AFTER:
* @return string Payment deadline in 'Y-m-d H:i:s' format (UTC/GMT)
```

**Step 3: Fix deadline comparison in DepositManagementAjax.php**

Find the cancellation deadline comparison at line 257. Fix line 259:

```php
// BEFORE (lines 257-261):
if ( $cancellation_deadline ) {
    $now      = time();
    $deadline = strtotime( $cancellation_deadline );

    if ( $now <= $deadline ) {

// AFTER:
if ( $cancellation_deadline ) {
    $now      = time();
    $deadline = strtotime( $cancellation_deadline . ' UTC' );

    if ( $now <= $deadline ) {
```

> **Why ` UTC` suffix?** `strtotime()` parses datetime strings in the server's local timezone by default. Appending ` UTC` forces UTC parsing. This is backward-compatible: existing bookings stored with wrong local time will shift slightly, but new bookings (after Handler.php fix) will be stored correctly as UTC and parsed correctly.

**Step 4: Verify**

1. Create a new booking in wp-admin → Rezervasyonlar → Yeni Ekle
2. After creation, go to the booking detail page
3. Check `_mhm_payment_deadline` and `_mhm_cancellation_deadline` post meta values (via wp-admin → Araçlar → Site Sağlığı or a meta box display)
4. Confirm the deadline shows UTC time, not UTC+3

**Step 5: Commit**

```bash
git add src/Admin/Booking/Core/Handler.php
git add src/Admin/Booking/Actions/DepositManagementAjax.php
git commit -m "fix: standardize deadline storage and comparison to UTC in Handler and DepositManagementAjax"
```

---

### Task 5: Clean Inline Styles and Scripts — BookingPortalMetaBox

**Files:**
- Modify: `src/Admin/Booking/Meta/BookingPortalMetaBox.php`
- Modify: `assets/css/admin/booking-meta.css`

The portal meta box has 8 inline `style=` attributes, emoji characters, and an inline `<script>` block with `alert()` calls.

**Step 1: Add CSS classes to `booking-meta.css`**

Open `assets/css/admin/booking-meta.css` and append at the end:

```css
/* =====================================================
   Customer Account Meta Box
   ===================================================== */

.mhm-portal-status-active {
    color: var(--mhm-success);
    display: flex;
    align-items: center;
    gap: var(--mhm-space-1);
}

.mhm-portal-status-warning {
    color: var(--mhm-warning);
    display: flex;
    align-items: center;
    gap: var(--mhm-space-1);
}

.mhm-portal-status-error {
    color: var(--mhm-error);
    display: flex;
    align-items: center;
    gap: var(--mhm-space-1);
}

.mhm-portal-action-btn {
    width: 100%;
    display: block;
    text-align: center;
    margin-bottom: 8px;
}

.mhm-portal-info-text {
    font-size: 12px;
    color: var(--mhm-text-secondary);
    margin: 0;
}

.mhm-portal-divider {
    margin: 15px 0;
}
```

**Step 2: Rewrite `BookingPortalMetaBox.php` render method**

Replace the entire `render()` method (lines 36–114) with the following. Note: The inline `<script>` block (lines 88–109) is moved to `wp_add_inline_script()`:

```php
public static function render( \WP_Post $post ): void {
    $booking_id       = (int) $post->ID;
    $customer_email   = get_post_meta( $booking_id, '_mhm_customer_email', true );
    $customer_name    = get_post_meta( $booking_id, '_mhm_customer_name', true );
    $customer_user_id = (int) get_post_meta( $booking_id, '_mhm_customer_user_id', true );

    echo '<div class="mhm-customer-account-info">';

    if ( $customer_user_id > 0 ) {
        $user = get_user_by( 'id', $customer_user_id );

        if ( $user ) {
            echo '<p><strong>' . esc_html__( 'Customer:', 'mhm-rentiva' ) . '</strong><br>';
            echo esc_html( $user->display_name ) . '</p>';

            echo '<p><strong>' . esc_html__( 'Email:', 'mhm-rentiva' ) . '</strong><br>';
            echo '<a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></p>';

            echo '<p><strong>' . esc_html__( 'Account Status:', 'mhm-rentiva' ) . '</strong><br>';
            echo '<span class="mhm-portal-status-active">';
            echo '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>';
            echo esc_html__( 'Active', 'mhm-rentiva' );
            echo '</span></p>';

            $account_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();

            echo '<p><a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $customer_user_id ) ) . '" class="button button-secondary mhm-portal-action-btn">';
            echo '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
            echo esc_html__( 'View User Profile', 'mhm-rentiva' ) . '</a></p>';

            echo '<p><a href="' . esc_url( $account_url ) . '" class="button button-primary mhm-portal-action-btn" target="_blank">';
            echo '<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> ';
            echo esc_html__( 'Customer Account Page', 'mhm-rentiva' ) . '</a></p>';

            echo '<hr class="mhm-portal-divider">';

            echo '<p class="mhm-portal-info-text">';
            echo '<span class="dashicons dashicons-info" aria-hidden="true"></span> ';
            echo esc_html__( 'Customer can login with their email and password to view this booking.', 'mhm-rentiva' );
            echo '</p>';
        } else {
            echo '<p class="mhm-portal-status-error">';
            echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
            echo esc_html__( 'User account not found', 'mhm-rentiva' );
            echo '</p>';
        }
    } else {
        echo '<p class="mhm-portal-status-warning">';
        echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'No customer account linked', 'mhm-rentiva' );
        echo '</p>';

        if ( $customer_email ) {
            echo '<p><strong>' . esc_html__( 'Email:', 'mhm-rentiva' ) . '</strong><br>';
            echo esc_html( $customer_email ) . '</p>';

            $nonce = wp_create_nonce( 'mhm_create_customer_account' );

            echo '<p><button type="button" class="button button-primary mhm-portal-action-btn mhm-create-customer-account-btn"';
            echo ' data-booking-id="' . esc_attr( (string) $booking_id ) . '"';
            echo ' data-email="' . esc_attr( $customer_email ) . '"';
            echo ' data-name="' . esc_attr( $customer_name ) . '"';
            echo ' data-nonce="' . esc_attr( $nonce ) . '">';
            echo '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> ';
            echo esc_html__( 'Create Customer Account', 'mhm-rentiva' ) . '</button></p>';
        }
    }

    echo '</div>';
}
```

**Step 3: Move inline JS to booking-email-send.js**

Open `assets/js/admin/booking-email-send.js` and append at the end:

```js
// Customer Account — Create Account Button
$( document ).on( 'click', '.mhm-create-customer-account-btn', function () {
    var $btn       = $( this );
    var bookingId  = $btn.data( 'booking-id' );
    var email      = $btn.data( 'email' );
    var name       = $btn.data( 'name' );
    var nonce      = $btn.data( 'nonce' );
    var confirmMsg = $btn.data( 'confirm' ) || 'Create a WordPress account for this customer?';

    if ( ! window.confirm( confirmMsg ) ) {
        return;
    }

    $btn.prop( 'disabled', true );

    $.post(
        ajaxurl,
        {
            action:     'mhm_create_customer_account_manual',
            booking_id: bookingId,
            email:      email,
            name:       name,
            nonce:      nonce,
        },
        function ( response ) {
            if ( response.success ) {
                if ( typeof window.mhmShowNotice === 'function' ) {
                    window.mhmShowNotice( 'success', response.data.message || 'Account created successfully!' );
                }
                setTimeout( function () { location.reload(); }, 1500 );
            } else {
                if ( typeof window.mhmShowNotice === 'function' ) {
                    window.mhmShowNotice( 'error', response.data.message || 'Error creating account.' );
                } else {
                    window.alert( response.data.message || 'Error creating account.' );
                }
                $btn.prop( 'disabled', false );
            }
        }
    );
} );
```

**Step 4: Verify in browser**

1. Go to a booking detail page where no WP user is linked
2. "Customer Account" meta box should show "No customer account linked" with an orange warning icon (dashicon, not emoji)
3. Click "Create Customer Account" — confirm dialog appears, then success message (toast, not alert)
4. For a booking with linked user: check green "Active" status with dashicon

**Step 5: Commit**

```bash
git add src/Admin/Booking/Meta/BookingPortalMetaBox.php
git add assets/css/admin/booking-meta.css
git add assets/js/admin/booking-email-send.js
git commit -m "fix: remove inline styles, emojis and inline script from BookingPortalMetaBox"
```

---

### Task 6: Clean Inline Script — BookingRefundMetaBox

**Files:**
- Modify: `src/Admin/Booking/Meta/BookingRefundMetaBox.php`
- Modify: `assets/js/admin/booking-email-send.js`

The refund meta box has an inline `<script>` IIFE (lines 74–86) that converts a visible amount input to kuruş (cents × 100) in a hidden field.

**Step 1: Remove inline script from BookingRefundMetaBox.php**

Find the `<script>` block (lines 74–86):

```php
echo '<script>
    (function(){
      var v=document.querySelector(\'input[name="amount_visible"]\');
      var h=document.getElementById("mhm_amount_kurus");
      if(v&&h){
        v.addEventListener("input", function(){
          var x = Math.round(parseFloat(v.value || "0") * 100);
          if(!isFinite(x) || x<0) x=0;
          h.value = String(x);
        });
      }
    })();
    </script>';
```

**Delete this entire echo block.**

**Step 2: Add the logic to booking-email-send.js**

Open `assets/js/admin/booking-email-send.js` and append:

```js
// Refund Meta Box — Convert visible amount (lira) to kuruş hidden field
$( document ).on( 'input', 'input[name="amount_visible"]', function () {
    var raw  = parseFloat( $( this ).val() || '0' );
    var kurus = Math.round( raw * 100 );
    if ( ! isFinite( kurus ) || kurus < 0 ) {
        kurus = 0;
    }
    $( '#mhm_amount_kurus' ).val( String( kurus ) );
} );
```

**Step 3: Verify in browser**

1. Go to a completed booking with a payment
2. The "Refund" meta box (side panel) should appear
3. Type an amount in the refund input field (e.g., "100")
4. The hidden `mhm_amount_kurus` field should update to "10000"
5. Submit the refund — amount should process correctly

**Step 4: Commit**

```bash
git add src/Admin/Booking/Meta/BookingRefundMetaBox.php
git add assets/js/admin/booking-email-send.js
git commit -m "fix: move inline script from BookingRefundMetaBox to booking-email-send.js"
```

---

### Task 7: History Pagination — Show More Button

**Files:**
- Modify: `src/Admin/Booking/Meta/BookingMeta.php`
- Modify: `assets/css/admin/booking-edit-meta.css`
- Modify: `assets/js/admin/booking-email-send.js`

**Step 1: Add CSS to booking-edit-meta.css**

Append at the end of `assets/css/admin/booking-edit-meta.css`:

```css
/* =====================================================
   History Pagination
   ===================================================== */

.mhm-history-item--hidden {
    display: none;
}

.mhm-history-show-more {
    background: none;
    border: none;
    color: var(--mhm-primary);
    cursor: pointer;
    font-size: 12px;
    padding: var(--mhm-space-1) 0;
    width: 100%;
    text-align: left;
    margin-top: var(--mhm-space-2);
}

.mhm-history-show-more:hover {
    text-decoration: underline;
}
```

**Step 2: Update history rendering in BookingMeta.php**

Find the history rendering loop (lines 888–917). The loop currently does a `foreach` over `$history`. Replace the loop block:

```php
// BEFORE (lines 888-917):
if (empty($history)) {
    echo '<p class="description">' . esc_html__('No history found.', 'mhm-rentiva') . '</p>';
} else {
    // Sort by date (newest first)
    krsort($history);

    foreach ($history as $timestamp => $note) {
        // ... render each item
        echo '<div class="mhm-history-item ' . esc_attr($type_class) . '">';
        // ...
        echo '</div>';
    }
}

// AFTER:
if ( empty( $history ) ) {
    echo '<p class="description">' . esc_html__( 'No history found.', 'mhm-rentiva' ) . '</p>';
} else {
    krsort( $history );

    $threshold = 5;
    $total     = count( $history );
    $index     = 0;

    foreach ( $history as $timestamp => $note ) {
        $date      = date_i18n( get_option( 'date_format' ), $timestamp );
        $time      = date_i18n( get_option( 'time_format' ), $timestamp );
        $user      = get_userdata( $note['user_id'] );
        $user_name = $user ? $user->display_name : esc_html__( 'System', 'mhm-rentiva' );

        $type_class   = 'mhm-history-' . $note['type'];
        $type_label   = self::get_history_type_label( $note['type'] );
        $hidden_class = ( $index >= $threshold ) ? ' mhm-history-item--hidden' : '';

        echo '<div class="mhm-history-item ' . esc_attr( $type_class ) . $hidden_class . '">';
        echo '<div class="mhm-history-header">';
        echo '<span class="mhm-history-type">' . esc_html( $type_label ) . '</span>';
        echo '<span class="mhm-history-date">' . esc_html( $date . ' ' . $time ) . '</span>';
        echo '</div>';
        echo '<div class="mhm-history-content">';
        echo '<p>' . esc_html( $note['note'] ) . '</p>';
        echo '<div class="mhm-history-meta">';
        /* translators: %s: user display name */
        echo '<span class="mhm-history-user">' . esc_html( sprintf( esc_html__( 'By %s', 'mhm-rentiva' ), $user_name ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        ++$index;
    }

    if ( $total > $threshold ) {
        printf(
            '<button type="button" class="mhm-history-show-more">+ %d %s</button>',
            $total - $threshold,
            esc_html__( 'more entries', 'mhm-rentiva' )
        );
    }
}
```

**Step 3: Add click handler to booking-email-send.js**

Append at the end of `assets/js/admin/booking-email-send.js`:

```js
// History — Show More Button
$( document ).on( 'click', '.mhm-history-show-more', function () {
    $( '.mhm-history-item--hidden' ).show();
    $( this ).remove();
} );
```

**Step 4: Verify in browser**

1. Go to a booking with more than 5 history entries
2. Only the 5 most recent entries should be visible
3. A "+ N more entries" button should appear below
4. Clicking the button reveals all hidden entries and removes the button
5. For bookings with 5 or fewer entries — no button appears

**Step 5: Commit**

```bash
git add src/Admin/Booking/Meta/BookingMeta.php
git add assets/css/admin/booking-edit-meta.css
git add assets/js/admin/booking-email-send.js
git commit -m "feat: add show-more pagination for booking history list"
```

---

## Full Test Checklist

After all tasks are complete:

- [ ] Error messages in new booking form render as styled HTML (not raw `< div >` text)
- [ ] Booking list "Ödeme" column shows "WooCommerce" (not "WOOCOMMERCE")
- [ ] New booking form "Durum" dropdown shows only Pending and Confirmed
- [ ] New bookings have UTC-based deadline meta values
- [ ] Refund cancellation policy check works correctly (no ±3 hour offset)
- [ ] Cron auto-cancel still works as before (do NOT change AutoCancel.php)
- [ ] Buffer time availability check still works (do NOT change Util.php)
- [ ] BookingPortalMetaBox has no emoji, no inline styles
- [ ] "Create Customer Account" button shows toast notification (not alert)
- [ ] BookingRefundMetaBox has no inline `<script>` block
- [ ] Refund amount conversion still works (type 100 → hidden field = 10000)
- [ ] Booking history > 5 items: show-more button appears
- [ ] Show-more button reveals all entries and disappears on click
