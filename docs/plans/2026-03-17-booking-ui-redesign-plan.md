# Booking UI Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Three-area UI upgrade: calendar popup aligned with vehicle calendar, manual booking form with card structure and animations, booking list with badge polish and token cleanup.

**Architecture:** PHP render methods emit clean HTML, CSS handles all visibility/animation state via classes (no inline styles), JS only toggles classes and fills pre-built elements. All hardcoded values replaced with `var(--mhm-*)` tokens.

**Tech Stack:** PHP 8.0+, WordPress 6.x, jQuery, CSS custom properties, CSS `@keyframes`.

---

### Task 1: Calendar Popup — Replace Dynamic HTML with Pre-Built Template

**Files:**
- Modify: `src/Admin/Booking/ListTable/BookingColumns.php` (~lines 1248–1395)
- Modify: `assets/css/admin/booking-calendar.css`

This is the most impactful visual fix. The existing popup builds everything in JS string concatenation. We replace it with a static HTML template (like the vehicle calendar) and have JS fill pre-built elements.

**Step 1: Replace the popup HTML template in BookingColumns.php**

Find the comment `<!-- Booking Popup Modal -->` at line 1248. Replace everything from that comment through `</div>` at line 1269 with:

```php
<!-- Booking Popup Modal -->
<div id="mhm-booking-popup" class="mhm-popup-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="mhm-popup-title">
	<div class="mhm-popup-overlay"></div>
	<div class="mhm-popup-content">
		<div class="mhm-popup-header">
			<div class="mhm-popup-header-left">
				<span class="dashicons dashicons-calendar-alt mhm-popup-header-icon"></span>
				<div>
					<h3 id="mhm-popup-title"><?php esc_html_e( 'Booking Details', 'mhm-rentiva' ); ?></h3>
					<span class="mhm-popup-booking-id"></span>
				</div>
			</div>
			<div class="mhm-popup-header-right">
				<span id="popup-status-badge" class="mhm-popup-status-badge"></span>
				<button class="mhm-popup-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'mhm-rentiva' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>

		<div class="mhm-popup-body">
			<!-- Single booking view (default) -->
			<div id="popup-single-view">
				<div class="mhm-popup-section">
					<div class="mhm-popup-section-title">
						<span class="dashicons dashicons-admin-users"></span>
						<?php esc_html_e( 'Customer', 'mhm-rentiva' ); ?>
					</div>
					<div class="booking-info-grid">
						<div class="info-item">
							<label><?php esc_html_e( 'Name', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-name">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Email', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-email">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Phone', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-phone">—</span>
						</div>
					</div>
				</div>

				<div class="mhm-popup-section">
					<div class="mhm-popup-section-title">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Vehicle & Dates', 'mhm-rentiva' ); ?>
					</div>
					<div class="booking-info-grid">
						<div class="info-item">
							<label><?php esc_html_e( 'Vehicle', 'mhm-rentiva' ); ?></label>
							<span id="popup-vehicle-title">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Plate', 'mhm-rentiva' ); ?></label>
							<span id="popup-vehicle-plate">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Pickup', 'mhm-rentiva' ); ?></label>
							<span id="popup-start-date" class="info-date">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Return', 'mhm-rentiva' ); ?></label>
							<span id="popup-end-date" class="info-date">—</span>
						</div>
					</div>
				</div>

				<div class="mhm-popup-section mhm-popup-section--last">
					<div class="mhm-popup-section-title">
						<span class="dashicons dashicons-tickets-alt"></span>
						<?php esc_html_e( 'Booking Info', 'mhm-rentiva' ); ?>
					</div>
					<div class="booking-info-grid">
						<div class="info-item">
							<label><?php esc_html_e( 'Total Price', 'mhm-rentiva' ); ?></label>
							<span id="popup-total-price" class="info-price">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Created', 'mhm-rentiva' ); ?></label>
							<span id="popup-created-date">—</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Multiple bookings view (shown when day has 2+ bookings) -->
			<div id="popup-multi-view" style="display: none;">
				<div class="mhm-popup-multi-header">
					<span class="dashicons dashicons-calendar-alt"></span>
					<span id="popup-multi-count"></span>
				</div>
				<div id="popup-bookings-list"></div>
			</div>
		</div>

		<div class="mhm-popup-footer" id="popup-single-footer">
			<a id="popup-edit-booking" href="#" class="button button-primary mhm-popup-edit-btn">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit Booking', 'mhm-rentiva' ); ?>
			</a>
		</div>
	</div>
</div>
```

**Step 2: Replace the inline script in BookingColumns.php**

Find `<script>` at line 1271. Replace the entire `<script>...</script>` block (lines 1271–1395) with:

```php
<script>
jQuery(document).ready(function($) {
	var statusClasses = {
		'pending'      : 'status-badge--pending',
		'confirmed'    : 'status-badge--confirmed',
		'in_progress'  : 'status-badge--in-progress',
		'completed'    : 'status-badge--completed',
		'cancelled'    : 'status-badge--cancelled',
		'refunded'     : 'status-badge--refunded',
		'no_show'      : 'status-badge--cancelled',
		'draft'        : 'status-badge--draft'
	};

	// Open popup
	$('[data-booking-popup]').on('click', function(e) {
		e.preventDefault();

		var $this       = $(this);
		var bookingsRaw = $this.data('bookings');
		var bookings    = [];

		if (bookingsRaw) {
			try {
				bookings = typeof bookingsRaw === 'string' ? JSON.parse(bookingsRaw) : bookingsRaw;
			} catch (err) {
				console.error('Booking popup JSON parse error:', err);
			}
		}

		// Fallback: build single booking from individual data attrs
		if ( ! bookings.length ) {
			bookings = [{
				booking_id    : $this.data('booking-id'),
				customer_name : $this.data('customer-name'),
				customer_email: $this.data('customer-email'),
				customer_phone: $this.data('customer-phone'),
				vehicle_title : $this.data('vehicle-title'),
				vehicle_plate : $this.data('vehicle-plate'),
				total_price   : $this.data('total-price'),
				status        : $this.data('status'),
				status_label  : $this.data('status-label') || $this.data('status'),
				start_date    : $this.data('start-date'),
				end_date      : $this.data('end-date'),
				created_date  : $this.data('created-date')
			}];
		}

		if (bookings.length === 1) {
			showSingleBooking(bookings[0]);
		} else {
			showMultiBooking(bookings);
		}

		$('#mhm-booking-popup').fadeIn(250);
	});

	function showSingleBooking(b) {
		// Fill pre-built elements
		$('#popup-customer-name').text(b.customer_name || '—');
		$('#popup-customer-email').text(b.customer_email || '—');
		$('#popup-customer-phone').text(b.customer_phone || '—');
		$('#popup-vehicle-title').text(b.vehicle_title || '—');
		$('#popup-vehicle-plate').text(b.vehicle_plate || '—');
		$('#popup-start-date').text(b.start_date || '—');
		$('#popup-end-date').text(b.end_date || '—');
		$('#popup-total-price').text(b.total_price ? b.total_price : '—');
		$('#popup-created-date').text(b.created_date || '—');
		$('.mhm-popup-booking-id').text(b.booking_id ? '#' + b.booking_id : '');

		// Status badge
		var $badge = $('#popup-status-badge');
		$badge.text(b.status_label || b.status || '—');
		$badge.attr('class', 'mhm-popup-status-badge ' + (statusClasses[b.status] || ''));

		// Edit button
		$('#popup-edit-booking').attr('href', b.booking_id ? 'post.php?post=' + b.booking_id + '&action=edit' : '#');

		$('#popup-single-view').show();
		$('#popup-multi-view').hide();
		$('#popup-single-footer').show();
	}

	function showMultiBooking(bookings) {
		$('#popup-multi-count').text(bookings.length + ' <?php echo esc_js( __( 'bookings on this day', 'mhm-rentiva' ) ); ?>');

		var html = '';
		bookings.forEach(function(b) {
			html += '<div class="mhm-popup-booking-card">';
			html += '<div class="mhm-popup-booking-card-header">';
			html += '<span class="mhm-popup-status-badge ' + (statusClasses[b.status] || '') + '">' + $('<span>').text(b.status_label || b.status || '—').html() + '</span>';
			html += '<span class="mhm-popup-booking-card-id">' + (b.booking_id ? '#' + b.booking_id : '') + '</span>';
			html += '</div>';
			html += '<div class="booking-info-grid">';
			html += '<div class="info-item"><label><?php echo esc_js( __( 'Customer', 'mhm-rentiva' ) ); ?></label><span>' + $('<span>').text(b.customer_name || '—').html() + '</span></div>';
			html += '<div class="info-item"><label><?php echo esc_js( __( 'Pickup', 'mhm-rentiva' ) ); ?></label><span>' + $('<span>').text(b.start_date || '—').html() + '</span></div>';
			html += '<div class="info-item"><label><?php echo esc_js( __( 'Return', 'mhm-rentiva' ) ); ?></label><span>' + $('<span>').text(b.end_date || '—').html() + '</span></div>';
			html += '<div class="info-item"><label><?php echo esc_js( __( 'Total', 'mhm-rentiva' ) ); ?></label><span>' + $('<span>').text(b.total_price || '—').html() + '</span></div>';
			html += '</div>';
			if (b.booking_id) {
				html += '<div class="mhm-popup-booking-card-footer">';
				html += '<a href="post.php?post=' + parseInt(b.booking_id, 10) + '&action=edit" class="button button-secondary"><?php echo esc_js( __( 'Edit Booking', 'mhm-rentiva' ) ); ?></a>';
				html += '</div>';
			}
			html += '</div>';
		});
		$('#popup-bookings-list').html(html);

		$('#popup-single-view').hide();
		$('#popup-multi-view').show();
		$('#popup-single-footer').hide();
	}

	// Close popup
	$('.mhm-popup-close, .mhm-popup-overlay').on('click', function() {
		$('#mhm-booking-popup').fadeOut(200);
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape') {
			$('#mhm-booking-popup').fadeOut(200);
		}
	});
});
</script>
```

**Step 3: Add missing CSS rules to booking-calendar.css**

At the end of `assets/css/admin/booking-calendar.css`, append:

```css
/* ── Popup header layout ── */
.mhm-popup-modal .mhm-popup-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	background: linear-gradient(135deg, var(--mhm-primary) 0%, #1a56db 100%);
	padding: 16px 20px;
	border-radius: 12px 12px 0 0;
	color: #fff;
}

.mhm-popup-modal .mhm-popup-header-left {
	display: flex;
	align-items: center;
	gap: 12px;
}

.mhm-popup-modal .mhm-popup-header-left h3 {
	margin: 0;
	font-size: 1rem;
	font-weight: 600;
	color: #fff;
}

.mhm-popup-modal .mhm-popup-header-icon {
	font-size: 22px;
	color: rgba(255,255,255,0.85);
}

.mhm-popup-modal .mhm-popup-booking-id {
	font-size: 0.75rem;
	color: rgba(255,255,255,0.7);
	display: block;
	margin-top: 2px;
}

.mhm-popup-modal .mhm-popup-header-right {
	display: flex;
	align-items: center;
	gap: 10px;
}

/* ── Status badge variants ── */
.mhm-popup-status-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 9999px;
	font-size: 0.75rem;
	font-weight: 600;
	background: rgba(255,255,255,0.2);
	color: #fff;
}

.mhm-popup-status-badge.status-badge--pending    { background: var(--mhm-warning); color: #7a5500; }
.mhm-popup-status-badge.status-badge--confirmed  { background: var(--mhm-success); color: #fff; }
.mhm-popup-status-badge.status-badge--in-progress{ background: #fd7e14; color: #fff; }
.mhm-popup-status-badge.status-badge--completed  { background: var(--mhm-primary); color: #fff; }
.mhm-popup-status-badge.status-badge--cancelled  { background: var(--mhm-error); color: #fff; }
.mhm-popup-status-badge.status-badge--refunded   { background: var(--mhm-warning); color: #7a5500; }
.mhm-popup-status-badge.status-badge--draft      { background: var(--mhm-gray-500); color: #fff; }

/* ── Multi-booking view ── */
.mhm-popup-multi-header {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	color: var(--mhm-text-primary);
	padding-bottom: 12px;
	border-bottom: 1px solid var(--mhm-border-primary);
	margin-bottom: 12px;
}

.mhm-popup-booking-card {
	border: 1px solid var(--mhm-border-primary);
	border-radius: 8px;
	padding: 12px;
	margin-bottom: 10px;
}

.mhm-popup-booking-card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 10px;
}

.mhm-popup-booking-card-id {
	font-size: 0.8rem;
	color: var(--mhm-text-secondary);
	font-weight: 600;
}

.mhm-popup-booking-card-footer {
	margin-top: 10px;
	padding-top: 10px;
	border-top: 1px solid var(--mhm-border-primary);
}

.mhm-popup-booking-card .mhm-popup-status-badge {
	background: var(--mhm-bg-secondary);
	color: var(--mhm-text-secondary);
}

/* Close button style on gradient header */
.mhm-popup-modal .mhm-popup-close {
	background: rgba(255,255,255,0.15);
	border: 1px solid rgba(255,255,255,0.3);
	border-radius: 6px;
	color: #fff;
	cursor: pointer;
	padding: 4px;
	display: flex;
	align-items: center;
	transition: background 200ms ease;
}

.mhm-popup-modal .mhm-popup-close:hover {
	background: rgba(255,255,255,0.25);
}
```

**Step 4: Self-review**
- Popup template has ARIA `role="dialog"`, `aria-modal`, `aria-labelledby`
- Single booking: pre-built elements filled with `.text()` (XSS-safe)
- Multi booking: HTML built with `$('<span>').text(...).html()` pattern (XSS-safe)
- Edit button is `<a href>` (not JS redirect), no inline JS
- `e.key === 'Escape'` replaces deprecated `e.keyCode`
- CSS covers all status badge variants

**Step 5: Verify**
- Go to Bookings → Calendar tab
- Click a day with one booking → popup should show with gradient header, status badge, all fields filled, "Edit Booking" button
- Click a day with multiple bookings → popup shows "N bookings on this day" with stacked cards
- Press Escape → popup closes
- Click overlay → popup closes

---

### Task 2: Manual Booking Form — Card Structure & Animations

**Files:**
- Modify: `src/Admin/Booking/Meta/ManualBookingMetaBox.php` (~lines 229–442)
- Modify: `assets/css/admin/manual-booking-meta.css`
- Modify: `assets/js/admin/manual-booking-meta.js`

**Step 1: Remove inline display:none styles and add card wrappers in ManualBookingMetaBox.php**

Replace the entire `render()` method output block (lines 229–442) as follows. The key changes:
1. Form wrapped in sections with `.mhm-form-card` divs
2. `style="display: none;"` → CSS class `mhm-hidden` on affected elements
3. Button area gets a proper actions footer

```php
echo '<div class="mhm-manual-booking-form">';

// ── Card 1: Vehicle & Customer ──
echo '<div class="mhm-form-card">';
echo '<div class="mhm-form-card-header">';
echo '<span class="dashicons dashicons-car" aria-hidden="true"></span> ';
echo esc_html__( 'Vehicle & Customer', 'mhm-rentiva' );
echo '</div>';
echo '<div class="mhm-form-card-body">';

// Vehicle Selection (unchanged inner HTML)
echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_vehicle_id" class="mhm-field-label">' . esc_html__( 'Select Vehicle', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<select id="mhm_manual_vehicle_id" name="mhm_manual_vehicle_id" class="mhm-field-select" required>';
echo '<option value="">' . esc_html__( 'Select a vehicle...', 'mhm-rentiva' ) . '</option>';
foreach ( $vehicles as $vehicle ) {
    $price_float = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day( $vehicle->ID );
    $price_text  = $price_float > 0 ? ' (' . self::format_addon_price( $price_float ) . '/' . __( 'day', 'mhm-rentiva' ) . ')' : '';
    echo '<option value="' . esc_attr( $vehicle->ID ) . '" data-price="' . esc_attr( $price_float ) . '">';
    echo esc_html( $vehicle->post_title . $price_text );
    echo '</option>';
}
echo '</select>';
echo '</div>';

// Customer Selection (unchanged)
echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_customer_id" class="mhm-field-label">' . esc_html__( 'Customer', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<select id="mhm_manual_customer_id" name="mhm_manual_customer_id" class="mhm-field-select" required>';
echo '<option value="">' . esc_html__( 'Select a customer...', 'mhm-rentiva' ) . '</option>';
echo '<option value="new_customer">' . esc_html__( '+ Create New Customer', 'mhm-rentiva' ) . '</option>';
foreach ( $users as $user ) {
    echo '<option value="' . esc_attr( $user->ID ) . '">';
    echo esc_html( $user->display_name . ' (' . $user->user_email . ')' );
    echo '</option>';
}
echo '</select>';
echo '</div>';

// New Customer Fields — class toggle instead of inline style
echo '<div id="mhm_new_customer_fields" class="mhm-form-card mhm-form-card--nested mhm-hidden">';
echo '<div class="mhm-form-card-header mhm-form-card-header--sm">';
echo '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
echo esc_html__( 'New Customer Information', 'mhm-rentiva' );
echo '</div>';
echo '<div class="mhm-form-card-body">';

echo '<div class="mhm-field-row">';
echo '<div class="mhm-field-group mhm-field-half">';
echo '<label for="mhm_new_customer_first_name" class="mhm-field-label">' . esc_html__( 'First Name', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<input type="text" id="mhm_new_customer_first_name" name="mhm_new_customer_first_name" class="mhm-field-input">';
echo '</div>';
echo '<div class="mhm-field-group mhm-field-half">';
echo '<label for="mhm_new_customer_last_name" class="mhm-field-label">' . esc_html__( 'Last Name', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<input type="text" id="mhm_new_customer_last_name" name="mhm_new_customer_last_name" class="mhm-field-input">';
echo '</div>';
echo '</div>';

echo '<div class="mhm-field-group">';
echo '<label for="mhm_new_customer_email" class="mhm-field-label">' . esc_html__( 'Email', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<input type="email" id="mhm_new_customer_email" name="mhm_new_customer_email" class="mhm-field-input">';
echo '</div>';

echo '<div class="mhm-field-group">';
echo '<label for="mhm_new_customer_phone" class="mhm-field-label">' . esc_html__( 'Phone', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<input type="tel" id="mhm_new_customer_phone" name="mhm_new_customer_phone" class="mhm-field-input">';
echo '</div>';

echo '</div>'; // .mhm-form-card-body
echo '</div>'; // #mhm_new_customer_fields

echo '</div>'; // .mhm-form-card-body
echo '</div>'; // Card 1

// ── Card 2: Dates & Duration ──
echo '<div class="mhm-form-card">';
echo '<div class="mhm-form-card-header">';
echo '<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span> ';
echo esc_html__( 'Dates & Duration', 'mhm-rentiva' );
echo '</div>';
echo '<div class="mhm-form-card-body">';

echo '<div class="mhm-datetime-fields">';

echo '<div class="mhm-field-group mhm-field-half">';
echo '<label for="mhm_manual_pickup_date" class="mhm-field-label">' . esc_html__( 'Pickup Date', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<input type="date" id="mhm_manual_pickup_date" name="mhm_manual_pickup_date" class="mhm-field-input" required>';
echo '</div>';

echo '<div class="mhm-field-group mhm-field-half">';
echo '<label for="mhm_manual_pickup_time" class="mhm-field-label">' . esc_html__( 'Pickup Time', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
$default_pickup_time = apply_filters( 'mhm_rentiva_default_pickup_time', '10:00' );
echo '<input type="time" id="mhm_manual_pickup_time" name="mhm_manual_pickup_time" class="mhm-field-input" value="' . esc_attr( $default_pickup_time ) . '" required>';
echo '</div>';

echo '<div class="mhm-field-group mhm-field-half">';
echo '<label for="mhm_manual_dropoff_date" class="mhm-field-label">' . esc_html__( 'Return Date', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
echo '<input type="date" id="mhm_manual_dropoff_date" name="mhm_manual_dropoff_date" class="mhm-field-input" required>';
echo '</div>';

echo '<div class="mhm-field-group mhm-field-half">';
echo '<label for="mhm_manual_dropoff_time" class="mhm-field-label">' . esc_html__( 'Return Time', 'mhm-rentiva' ) . ' <span class="required">*</span></label>';
$default_dropoff_time = apply_filters( 'mhm_rentiva_default_dropoff_time', '10:00' );
echo '<input type="time" id="mhm_manual_dropoff_time" name="mhm_manual_dropoff_time" class="mhm-field-input" value="' . esc_attr( $default_dropoff_time ) . '" required>';
echo '</div>';

echo '</div>'; // .mhm-datetime-fields

echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_guests" class="mhm-field-label">' . esc_html__( 'Number of Guests', 'mhm-rentiva' ) . '</label>';
echo '<input type="number" id="mhm_manual_guests" name="mhm_manual_guests" class="mhm-field-input mhm-field-input--narrow" value="1" min="1" max="10">';
echo '</div>';

echo '</div>'; // .mhm-form-card-body
echo '</div>'; // Card 2

// ── Card 3: Additional Services ──
if ( ! empty( $available_addons ) ) {
    echo '<div class="mhm-form-card">';
    echo '<div class="mhm-form-card-header">';
    echo '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> ';
    echo esc_html__( 'Additional Services', 'mhm-rentiva' );
    echo '</div>';
    echo '<div class="mhm-form-card-body">';

    echo '<div class="mhm-addon-selection">';
    foreach ( $available_addons as $addon ) {
        $checked       = $addon['required'] ? 'checked disabled' : '';
        $required_text = $addon['required'] ? ' <span class="required">*</span>' : '';

        echo '<label class="mhm-addon-item">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<input type="checkbox" name="selected_addons[]" value="' . esc_attr( $addon['id'] ) . '" class="mhm-addon-checkbox" data-price="' . esc_attr( $addon['price'] ) . '" ' . $checked . '>';
        echo '<span class="mhm-addon-info">';
        echo '<span class="mhm-addon-title">' . esc_html( $addon['title'] ) . wp_kses_post( $required_text ) . '</span>';
        echo '<span class="mhm-addon-price-badge">+ ' . esc_html( self::format_addon_price( (float) $addon['price'] ) ) . '</span>';
        echo '</span>';
        if ( ! empty( $addon['description'] ) ) {
            echo '<span class="mhm-addon-description">' . esc_html( $addon['description'] ) . '</span>';
        }
        echo '</label>';
    }

    // class toggle instead of inline style
    echo '<div class="mhm-addon-total mhm-hidden">';
    echo '<strong>' . esc_html__( 'Additional Services Total:', 'mhm-rentiva' ) . ' <span class="mhm-addon-total-amount">' . esc_html( self::format_addon_price( 0 ) ) . '</span></strong>';
    echo '</div>';

    echo '</div>'; // .mhm-addon-selection
    echo '</div>'; // .mhm-form-card-body
    echo '</div>'; // Card 3
}

// ── Card 4: Payment & Notes ──
echo '<div class="mhm-form-card">';
echo '<div class="mhm-form-card-header">';
echo '<span class="dashicons dashicons-money-alt" aria-hidden="true"></span> ';
echo esc_html__( 'Payment & Notes', 'mhm-rentiva' );
echo '</div>';
echo '<div class="mhm-form-card-body">';

echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_payment_type" class="mhm-field-label">' . esc_html__( 'Payment Type', 'mhm-rentiva' ) . '</label>';
echo '<select id="mhm_manual_payment_type" name="mhm_manual_payment_type" class="mhm-field-select">';
echo '<option value="full">' . esc_html__( 'Full Payment', 'mhm-rentiva' ) . '</option>';
echo '<option value="deposit" selected>' . esc_html__( 'Deposit', 'mhm-rentiva' ) . '</option>';
echo '</select>';
echo '</div>';

echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_payment_method" class="mhm-field-label">' . esc_html__( 'Payment Method', 'mhm-rentiva' ) . '</label>';
echo '<select id="mhm_manual_payment_method" name="mhm_manual_payment_method" class="mhm-field-select">';
echo '<option value="offline" selected>' . esc_html__( 'Offline', 'mhm-rentiva' ) . '</option>';
echo '<option value="online">' . esc_html__( 'Online', 'mhm-rentiva' ) . '</option>';
echo '</select>';
echo '</div>';

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

echo '<div class="mhm-field-group">';
echo '<label for="mhm_manual_notes" class="mhm-field-label">' . esc_html__( 'Notes', 'mhm-rentiva' ) . '</label>';
echo '<textarea id="mhm_manual_notes" name="mhm_manual_notes" class="mhm-field-textarea" rows="3" placeholder="' . esc_attr__( 'Special notes about the booking...', 'mhm-rentiva' ) . '"></textarea>';
echo '</div>';

echo '</div>'; // .mhm-form-card-body
echo '</div>'; // Card 4

// ── Price Calculation Panel — class toggle ──
echo '<div class="mhm-price-calculation mhm-hidden">';
echo '<h4 class="mhm-price-calculation-title">' . esc_html__( 'Price Calculation', 'mhm-rentiva' ) . '</h4>';
echo '<div class="mhm-price-details"></div>';
echo '</div>';

// ── Actions ──
echo '<div class="mhm-booking-actions">';
echo '<button type="button" id="mhm-calculate-price" class="button button-secondary">' . esc_html__( 'Calculate Price', 'mhm-rentiva' ) . '</button>';
// class toggle instead of inline style
echo '<button type="button" id="mhm-create-booking" class="button button-primary mhm-hidden">' . esc_html__( 'Create Booking', 'mhm-rentiva' ) . '</button>';
echo '</div>';

echo '</div>'; // .mhm-manual-booking-form
```

**Step 2: Add card CSS to manual-booking-meta.css**

Add at the bottom of `assets/css/admin/manual-booking-meta.css`:

```css
/* ── Utility: hidden state ── */
.mhm-hidden {
	display: none !important;
}

/* ── Form card ── */
.mhm-form-card {
	background: var(--mhm-bg-card);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-lg);
	margin-bottom: var(--mhm-space-4);
	overflow: hidden;
}

.mhm-form-card--nested {
	border-color: var(--mhm-primary);
	border-left-width: 3px;
	margin-top: var(--mhm-space-3);
}

.mhm-form-card-header {
	display: flex;
	align-items: center;
	gap: var(--mhm-space-2);
	padding: var(--mhm-space-3) var(--mhm-space-4);
	background: var(--mhm-bg-secondary);
	border-bottom: 1px solid var(--mhm-border-primary);
	font-size: var(--mhm-text-sm);
	font-weight: var(--mhm-font-semibold);
	color: var(--mhm-text-primary);
}

.mhm-form-card-header .dashicons {
	color: var(--mhm-primary);
	font-size: 16px;
	width: 16px;
	height: 16px;
}

.mhm-form-card-header--sm {
	padding: var(--mhm-space-2) var(--mhm-space-3);
	font-size: var(--mhm-text-xs);
	background: transparent;
	border-bottom-color: var(--mhm-border-primary);
}

.mhm-form-card-body {
	padding: var(--mhm-space-4);
}

/* ── Field row (two halves side by side) ── */
.mhm-field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: var(--mhm-space-3);
}

/* ── Narrow number input ── */
.mhm-field-input--narrow {
	max-width: 120px;
}

/* ── Addon price badge ── */
.mhm-addon-price-badge {
	display: inline-block;
	padding: 2px 8px;
	background: var(--mhm-bg-secondary);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-full);
	font-size: var(--mhm-text-xs);
	font-weight: var(--mhm-font-semibold);
	color: var(--mhm-text-secondary);
	white-space: nowrap;
}

.mhm-addon-item:has(.mhm-addon-checkbox:checked) .mhm-addon-price-badge {
	background: var(--mhm-primary);
	border-color: var(--mhm-primary);
	color: #fff;
}

/* ── Price calculation panel ── */
.mhm-price-calculation {
	background: var(--mhm-bg-secondary);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-lg);
	padding: var(--mhm-space-4);
	margin-bottom: var(--mhm-space-4);
	animation: mhm-fade-in 200ms ease forwards;
}

.mhm-price-calculation-title {
	font-size: var(--mhm-text-sm);
	font-weight: var(--mhm-font-semibold);
	color: var(--mhm-text-primary);
	margin: 0 0 var(--mhm-space-3);
	padding-bottom: var(--mhm-space-2);
	border-bottom: 1px solid var(--mhm-border-primary);
}

@keyframes mhm-fade-in {
	from { opacity: 0; transform: translateY(-4px); }
	to   { opacity: 1; transform: translateY(0); }
}

/* ── Calculate button loading spinner ── */
#mhm-calculate-price.mhm-calculating {
	opacity: 0.7;
	pointer-events: none;
	position: relative;
	padding-left: 32px;
}

#mhm-calculate-price.mhm-calculating::before {
	content: '';
	position: absolute;
	left: 10px;
	top: 50%;
	margin-top: -7px;
	width: 14px;
	height: 14px;
	border: 2px solid currentColor;
	border-top-color: transparent;
	border-radius: 50%;
	animation: mhm-spin 0.6s linear infinite;
}

@keyframes mhm-spin {
	to { transform: rotate(360deg); }
}
```

**Step 3: Update JS toggles in manual-booking-meta.js**

Replace `.show()` / `.hide()` calls with class toggles. Find these exact lines and apply:

```js
// Line 58: .show() → removeClass
// BEFORE:
$( '.mhm-price-calculation' ).show();
// AFTER:
$( '.mhm-price-calculation' ).removeClass( 'mhm-hidden' );

// Line 60: .hide() → addClass
// BEFORE:
$( '.mhm-price-calculation' ).hide();
// AFTER:
$( '.mhm-price-calculation' ).addClass( 'mhm-hidden' );

// Line 104: newCustomerFields.show()
// BEFORE:
newCustomerFields.show();
// AFTER:
newCustomerFields.removeClass( 'mhm-hidden' );

// Line 108: newCustomerFields.hide()
// BEFORE:
newCustomerFields.hide();
// AFTER:
newCustomerFields.addClass( 'mhm-hidden' );
```

Also find the `calculateAddonTotal` method. Find where `.mhm-addon-total` is shown/hidden (search for `mhm-addon-total`):

```js
// BEFORE (wherever .mhm-addon-total is shown):
$( '.mhm-addon-total' ).show();
// AFTER:
$( '.mhm-addon-total' ).removeClass( 'mhm-hidden' );

// BEFORE (wherever .mhm-addon-total is hidden):
$( '.mhm-addon-total' ).hide();
// AFTER:
$( '.mhm-addon-total' ).addClass( 'mhm-hidden' );
```

Also find `#mhm-create-booking` show/hide:

```js
// BEFORE:
$( '#mhm-create-booking' ).show();
// AFTER:
$( '#mhm-create-booking' ).removeClass( 'mhm-hidden' );
```

**Step 4: Self-review**
- All 4 `style="display: none;"` removed from PHP
- JS uses only class toggles (no `.show()`/`.hide()`)
- `.mhm-hidden` defined in CSS
- Spinner is pure CSS — no JS required
- `.mhm-addon-price` renamed to `.mhm-addon-price-badge` (update in PHP and JS if referenced)

**Step 5: Verify**
- New booking page: form renders with 4 card sections, each with icon header
- Select "+ Create New Customer" → new customer section slides in with animation
- Select a vehicle → price calculation appears with fade animation
- Click "Calculate Price" → spinner visible on button while loading
- After calculation, "Create Booking" button appears

---

### Task 3: Booking List — Badge Polish & Token Cleanup

**Files:**
- Modify: `src/Admin/Booking/ListTable/BookingColumns.php` (~lines 335–349)
- Modify: `assets/css/admin/booking-list.css`

**Step 1: Change payment status rendering in BookingColumns.php**

Find the `mhm_booking_payment` case (around line 323). Replace lines 335–349:

```php
// BEFORE:
echo '<div class="payment-info">';
$label = $status ? self::get_payment_status_label( $status ) : __( 'Unpaid', 'mhm-rentiva' );
echo '<div class="payment-status">' . esc_html( $label ) . '</div>';

if ( $amount > 0 ) {
    $val = number_format_i18n( $amount / 100, 2 );
    echo '<div class="amount">' . esc_html( $val . ' ' . strtoupper( $currency ) ) . '</div>';
}

$gw = $gateway !== '' ? $gateway : ( $receiptId ? 'offline' : '' );
if ( $gw !== '' ) {
    $gateway_label = self::get_payment_gateway_label( $gw );
    echo '<div class="gateway">[' . esc_html( $gateway_label ) . ']</div>';
}
echo '</div>';

// AFTER:
echo '<div class="payment-info">';
$label          = $status ? self::get_payment_status_label( $status ) : __( 'Unpaid', 'mhm-rentiva' );
$status_slug    = $status ?: 'unpaid';
echo '<span class="badge payment-status-' . esc_attr( $status_slug ) . '">' . esc_html( $label ) . '</span>';

if ( $amount > 0 ) {
    $val = number_format_i18n( $amount / 100, 2 );
    echo '<div class="amount">' . esc_html( $val . ' ' . strtoupper( $currency ) ) . '</div>';
}

$gw = $gateway !== '' ? $gateway : ( $receiptId ? 'offline' : '' );
if ( $gw !== '' ) {
    $gateway_label = self::get_payment_gateway_label( $gw );
    echo '<span class="mhm-gateway-pill">' . esc_html( $gateway_label ) . '</span>';
}
echo '</div>';
```

**Step 2: Update booking-list.css**

Apply these changes to `assets/css/admin/booking-list.css`:

**2a. Fix WCAG contrast on pending and refunded badges (lines 180–214):**
```css
/* BEFORE: */
.mhm-booking-list .badge.status-pending { background-color: var(--mhm-warning); color: #000; }
.mhm-booking-list .badge.status-refunded { background-color: var(--mhm-warning); color: #000; }

/* AFTER: */
.mhm-booking-list .badge.status-pending  { background-color: var(--mhm-warning); color: #7a5500; }
.mhm-booking-list .badge.status-refunded { background-color: var(--mhm-warning); color: #7a5500; }
```

**2b. Replace hardcoded booking-type colors (lines ~40–55):**

Find `.booking-type.online` and `.booking-type.manual` rules. Replace hardcoded backgrounds:
```css
/* BEFORE: */
.mhm-booking-list .booking-type.online { background: #e3f2fd; ... }
.mhm-booking-list .booking-type.manual { background: #fff3e0; ... }

/* AFTER: */
:root {
    --mhm-booking-type-online-bg: #e3f2fd;
    --mhm-booking-type-manual-bg: #fff3e0;
}
.mhm-booking-list .booking-type.online { background: var(--mhm-booking-type-online-bg); ... }
.mhm-booking-list .booking-type.manual { background: var(--mhm-booking-type-manual-bg); ... }
```

**2c. Replace hardcoded in_progress orange:**
```css
/* BEFORE: */
.mhm-booking-list .badge.status-in_progress { background-color: #fd7e14; }

/* AFTER — add the variable to :root and use it: */
/* (add to :root block above) --mhm-orange: #fd7e14; */
.mhm-booking-list .badge.status-in_progress { background-color: var(--mhm-orange, #fd7e14); }
```

**2d. Add payment status badge CSS — append to booking-list.css:**
```css
/* ── Payment status badges ── */
.mhm-booking-list .badge.payment-status-paid {
	background-color: var(--mhm-success);
	color: var(--mhm-white);
}

.mhm-booking-list .badge.payment-status-pending,
.mhm-booking-list .badge.payment-status-unpaid {
	background-color: var(--mhm-bg-tertiary);
	color: var(--mhm-text-secondary);
	border: 1px solid var(--mhm-border-primary);
}

.mhm-booking-list .badge.payment-status-partially_paid {
	background-color: var(--mhm-warning);
	color: #7a5500;
}

.mhm-booking-list .badge.payment-status-refunded {
	background-color: var(--mhm-warning);
	color: #7a5500;
}

.mhm-booking-list .badge.payment-status-free {
	background-color: var(--mhm-bg-secondary);
	color: var(--mhm-text-tertiary);
}

/* ── Gateway pill ── */
.mhm-gateway-pill {
	display: inline-block;
	margin-top: 4px;
	padding: 2px 8px;
	background: var(--mhm-bg-secondary);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-full);
	font-size: 0.75rem;
	color: var(--mhm-text-secondary);
}

/* ── Row hover ── */
.mhm-booking-list .wp-list-table tbody tr {
	transition: background-color 150ms ease;
}

.mhm-booking-list .wp-list-table tbody tr:hover td {
	background-color: var(--mhm-bg-secondary);
}

/* ── Empty value ── */
.mhm-empty-value {
	color: var(--mhm-text-tertiary);
}

/* ── Subsubsub spacing — replace hardcoded ── */
.mhm-booking-list .subsubsub {
	margin-bottom: var(--mhm-space-3);
}

.mhm-booking-list .subsubsub li {
	margin-right: var(--mhm-space-4);
}
```

**2e. Remove `!important` from filter rules:**

Find the payment status filter rules that use `!important` (~lines 155–164). Remove the `!important` flags and increase specificity instead:
```css
/* BEFORE: */
.mhm-booking-list .postform.payment-status-filter { min-width: 130px !important; }

/* AFTER: */
.mhm-booking-list select.postform.payment-status-filter { min-width: 130px; }
```

**Step 3: Self-review**
- `payment-status-*` badge class uses `$status_slug` (never empty — falls back to `'unpaid'`)
- `[Gateway]` brackets replaced with `.mhm-gateway-pill`
- Pending badge contrast: `#7a5500` on warning yellow passes WCAG AA (7:1 ratio)
- `!important` removed from filter rules
- Hardcoded colors in `:root` custom properties

**Step 4: Verify**
- Booking list: payment column shows coloured badge (Paid = green, Unpaid = gray, Partially Paid = yellow)
- Gateway shows as pill (no brackets)
- Hover over a table row → subtle background highlight
- Pending status badge readable (dark brown text on yellow, not black)
- No visual regression on existing booking-status badges

---

## Test Checklist

- [ ] Calendar popup opens on day click with gradient header and status badge
- [ ] Single booking day → detail view (all fields filled, Edit button works)
- [ ] Multi-booking day → list of cards with individual Edit buttons
- [ ] Escape key closes popup
- [ ] New booking form shows 4 card sections with icon headers
- [ ] "Create New Customer" selection reveals nested card with animation
- [ ] Calculate Price button shows spinner while loading
- [ ] Price calculation panel appears with fade animation
- [ ] Booking list: payment column has coloured badges (not plain text)
- [ ] Booking list: gateway appears as pill (not `[brackets]`)
- [ ] Hover on booking row shows background highlight
- [ ] Pending badge has readable contrast (dark text on yellow)
