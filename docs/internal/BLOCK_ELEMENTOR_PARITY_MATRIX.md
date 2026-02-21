# Gutenberg Block vs Elementor Widget Parity Matrix

Date: 2026-02-20

Coverage logic: runtime parity from `ElementorWidgetBase::register_parity_controls_from_block()`.

Parity rule:
- Widget uses shortcode render path (`render_shortcode`)
- Widget has shortcode->block mapping
- Mapped block has valid `block.json`
- Missing canonical block attrs are auto-added as Elementor controls
- `className` is intentionally excluded (Elementor Advanced > CSS Classes)

| Tag | Block Slug | Elementor Widget | Parity Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `rentiva_availability_calendar` | `availability-calendar` | `AvailabilityCalendarWidget.php` | Pass | Runtime parity active |
| `rentiva_booking_confirmation` | `booking-confirmation` | `BookingConfirmationWidget.php` | Pass | Runtime parity active |
| `rentiva_booking_form` | `booking-form` | `BookingFormWidget.php` | Pass | Runtime parity active |
| `rentiva_contact` | `contact` | `ContactFormWidget.php` | Pass | Runtime parity active |
| `rentiva_featured_vehicles` | `featured-vehicles` | `FeaturedVehiclesWidget.php` | Pass | Runtime parity active |
| `rentiva_messages` | `messages` | `MyMessagesWidget.php` | Pass | Runtime parity active |
| `rentiva_my_bookings` | `my-bookings` | `MyBookingsWidget.php` | Pass | Runtime parity active |
| `rentiva_my_favorites` | `my-favorites` | `MyFavoritesWidget.php` | Pass | Runtime parity active |
| `rentiva_payment_history` | `payment-history` | `PaymentHistoryWidget.php` | Pass | Runtime parity active |
| `rentiva_search_results` | `search-results` | `SearchResultsWidget.php` | Pass | Runtime parity active |
| `rentiva_testimonials` | `testimonials` | `TestimonialsWidget.php` | Pass | Runtime parity active |
| `rentiva_transfer_results` | `transfer-results` | `TransferResultsWidget.php` | Pass | Runtime parity active |
| `rentiva_transfer_search` | `transfer-search` | `TransferSearchWidget.php` | Pass | Runtime parity active |
| `rentiva_unified_search` | `unified-search` | `UnifiedSearchWidget.php` | Pass | Runtime parity active |
| `rentiva_vehicle_comparison` | `vehicle-comparison` | `VehicleComparisonWidget.php` | Pass | Runtime parity active |
| `rentiva_vehicle_details` | `vehicle-details` | `VehicleDetailsWidget.php` | Pass | Runtime parity active |
| `rentiva_vehicle_rating_form` | `vehicle-rating-form` | `VehicleRatingWidget.php` | Pass | Runtime parity active |
| `rentiva_vehicles_grid` | `vehicles-grid` | `VehiclesGridWidget.php` | Pass | Runtime parity active |
| `rentiva_vehicles_list` | `vehicles-list` | `VehiclesListWidget.php` | Pass | Runtime parity active |
| `rentiva_vehicles_list` | `vehicles-list` | `VehicleCardWidget.php` | Pass | Shared shortcode contract |

## Expected Exception

- Excluded key: `className`
- Reason: Elementor already provides CSS class management in the Advanced panel.

## QA Validation Summary

- 20/20 Elementor widgets render via `render_shortcode`
- 20/20 widget class mappings resolved to existing block.json files
- PHPCS clean on modified parity files
- PHP syntax checks passed on modified parity files

## WPCS and Consistency Notes

- New widget classes must be added to shortcode and block mapping in `ElementorWidgetBase`.
- Any future block attribute is auto-injected as parity control if it is canonical.
- Keep `className` exclusion unless Elementor API strategy changes.
