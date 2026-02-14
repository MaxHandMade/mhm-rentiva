# M1 Shortcode Inventory & Audit Baseline

**Generated:** 2026-02-14
**Source Version:** 4.9.8
**Context:** M1 Shortcode Engine Audit & Optimization

This document serves as the **single source of truth** for the M1 audit. It reconciles `UI_CONTRACTS.md` (contract definition), `SHORTCODES.md` (legacy documentation), and the actual codebase.

## 1. Core Shortcodes (Stable/Semi-stable)

These shortcodes are critically important and constitute the primary public surface.

### `[rentiva_booking_form]`
*   **Class:** `MHMRentiva\Admin\Frontend\Shortcodes\BookingForm`
*   **Status:** Stable
*   **Code Attributes:**
    *   `vehicle_id` (string)
    *   `start_date` (string)
    *   `end_date` (string)
    *   `show_vehicle_selector` (bool "1")
    *   `default_days` (int)
    *   `min_days` (int)
    *   `max_days` (int)
    *   `show_payment_options` (bool "1")
    *   `show_addons` (bool "1")
    *   `class` (string)
    *   `redirect_url` (string)
    *   `enable_deposit` (bool "1")
    *   `default_payment` (string "deposit")
    *   `form_title` (string)
    *   `show_vehicle_info` (bool "1")
    *   `show_time_select` (bool "1")
*   **Discrepancies:**
    *   `show_insurance`: Listed in legacy `SHORTCODES.md` but **missing** in codebase.
    *   **Action:** Mark `show_insurance` as deprecated/phantom in documentation.

### `[rentiva_availability_calendar]`
*   **Class:** `MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar`
*   **Status:** Stable
*   **Code Attributes:**
    *   `vehicle_id` (string)
    *   `show_pricing` (bool "1")
    *   `theme` (string "default")
    *   `start_date` (string)
    *   `months_ahead` (int "3")
    *   `show_weekends` (bool "1")
    *   `show_past_dates` (bool "0")
    *   `class` (string)
*   **Discrepancies:**
    *   Legacy `SHORTCODES.md` lists **no attributes**. Code has 8 functional attributes.
    *   **Action:** Legacy docs need full update.

### `[rentiva_vehicle_details]`
*   **Class:** `MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails`
*   **Status:** Stable
*   **Code Attributes:**
    *   `vehicle_id` (string)
    *   `show_gallery` (bool "1")
    *   `show_features` (bool "1")
    *   `show_pricing` (bool "1")
    *   `show_booking` (bool "1")
    *   `show_calendar` (bool "1")
    *   `show_price` (bool "1")
    *   `show_booking_button` (bool "1")
    *   `class` (string)
*   **Discrepancies:**
    *   None major. Code matches expected contract.

### `[rentiva_vehicles_list]`
*   **Class:** `MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList`
*   **Status:** Stable
*   **Code Attributes:**
    *   `limit` ("12")
    *   `columns` ("1")
    *   `orderby` ("title")
    *   `order` ("ASC")
    *   `category` ("")
    *   `featured` ("0")
    *   `show_image` ("1")
    *   `show_title` ("1")
    *   `show_price` ("1")
    *   `show_features` ("1")
    *   `show_rating` ("1")
    *   `show_booking_btn` ("1")
    *   `show_favorite_btn` ("1")
    *   `show_favorite_button` ("1")
    *   `show_category` ("1")
    *   `show_brand` ("0")
    *   `show_badges` ("1")
    *   `show_description` ("1")
    *   `show_availability` ("0")
    *   `show_compare_btn` ("1")
    *   `show_compare_button` ("1")
    *   `enable_lazy_load` ("1")
    *   `enable_ajax_filtering` ("0")
    *   `enable_infinite_scroll` ("0")
    *   `image_size` ("medium")
    *   `ids` ("")
    *   `max_features` ("5")
    *   `price_format` ("daily")
    *   `class` ("")
    *   `custom_css_class` ("")
    *   `min_rating` ("")
    *   `min_reviews` ("")
*   **Discrepancies:**
     *   Extensive visual toggles not fully documented in legacy docs.
     *   Duplicate aliases (`show_favorite_btn` vs `show_favorite_button`) exist for block parity.

### `[rentiva_vehicles_grid]`
*   **Class:** `MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid`
*   **Status:** Stable
*   **Code Attributes:**
    *   Same as `vehicles_list` plus:
    *   `layout` ("grid")
    *   `columns` defaults to "2"
*   **Discrepancies:**
    *   Matches `vehicles_list` discrepancies.

## 2. Other Stable Shortcodes (Audited via UI_CONTRACTS)

*   `[rentiva_booking_confirmation]` (BookingConfirmation.php)
*   `[rentiva_search_results]` (SearchResults.php) - **Issue:** Legacy doc lists URL filter params (`min_price`, etc.) as attributes. Code does not support these as attributes; they are query parameters.
*   `[rentiva_vehicle_comparison]` (VehicleComparison.php)
*   `[rentiva_unified_search]` (UnifiedSearch.php)
*   `[rentiva_my_bookings]` (MyBookings.php)
*   `[rentiva_my_favorites]` (MyFavorites.php) - Uses reused grid logic.
*   `[rentiva_payment_history]` (PaymentHistory.php)
*   `[rentiva_transfer_search]` (TransferShortcodes.php)
*   `[rentiva_transfer_results]` (TransferResults.php)
*   `[rentiva_contact]` (ContactForm.php)
*   `[rentiva_testimonials]` (Testimonials.php)
*   `[rentiva_vehicle_rating_form]` (VehicleRatingForm.php)
*   `[rentiva_messages]` (AccountMessages.php)

## 3. Legacy / Phantom Shortcodes

The following appear in `SHORTCODES.md` ("Elite Edition" or planned) but **do not exist** in the codebase:

1.  `rentiva_hero_section`
2.  `rentiva_trust_stats`
3.  `rentiva_brand_scroller`
4.  `rentiva_comparison_table`
5.  `rentiva_extra_services`

**Action:** Remove from active documentation or mark as "Planned".

## 4. Lifecycle Audit (Preliminary)

*   **Inheritance:** All audited core shortcodes extend `MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode`.
*   **Method Signature:** Standard `render($atts, $content)` implementation.
*   **Parity:** Block parity is achieved via mappings in `prepare_template_data` (e.g., camelCase to snake_case mapping in `VehiclesList`).

## 5. Audit Checklist Data

*   **Total Active Shortcodes:** 18
*   **Total Legacy/Phantom Shortcodes:** 5
*   **Critical Attribute Mismatches:** 2 (`show_insurance`, Search Results filters)
