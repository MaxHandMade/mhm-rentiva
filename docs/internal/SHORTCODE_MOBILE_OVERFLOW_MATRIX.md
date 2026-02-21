# Shortcode Mobile Overflow Matrix

Date: 2026-02-20  
Method: Chrome DevTools MCP live checks on `http://localhost/otokira`  
Viewport: `430 x 932` (`isMobile=true`, `hasTouch=true`, `deviceScaleFactor=3`)

## Summary

| Result | Count |
| :--- | ---: |
| Pass (no horizontal overflow) | 20 |
| Fail (horizontal overflow detected) | 0 |
| N/A (shortcode wrapper not rendered due gated/empty state) | 0 |

## Matrix

| Shortcode/Page | URL | clientWidth | scrollWidth | Overflow | Wrapper | Wrapper Padding (L/R) | Notes |
| :--- | :--- | ---: | ---: | :---: | :--- | :--- | :--- |
| `rentiva_booking_form` | `/booking-form/` | 430 | 430 | No | `.rv-booking-form-wrapper` | `12px / 12px` | Pass |
| `rentiva_availability_calendar` | `/availability-calendar/` | 430 | 430 | No | `.rv-availability-calendar` | `13px / 13px` | Pass |
| `rentiva_booking_confirmation` | `/booking-confirmation/` | 430 | 430 | No | `.rv-booking-confirmation` | `13px / 13px` | Pass |
| `rentiva_vehicle_details` | `/vehicle-details/` | 430 | 430 | No | `.rv-vehicle-details-wrapper` | `20px / 20px` | Pass (CTA width/box model fix applied) |
| `rentiva_vehicles_list` | `/arac-listesi/` | 430 | 430 | No | `.rv-vehicles-list-container` | `13px / 13px` | Pass |
| `rentiva_featured_vehicles` | `/featured-vehicles/` | 430 | 430 | No | `.mhm-rentiva-featured-wrapper` | `13px / 13px` | Pass |
| `rentiva_featured_vehicles` (legacy page) | `/one-cikan-araclar/` | 430 | 430 | No | `.mhm-rentiva-featured-wrapper` | `13px / 13px` | Pass |
| `rentiva_vehicles_grid` | `/vehicles-grid/` | 430 | 430 | No | `.rv-vehicles-grid-container` | `13px / 13px` | Pass |
| `rentiva_search_results` | `/search-results/` | 430 | 430 | No | `.rv-search-results` | `13px / 13px` | Pass |
| `rentiva_vehicle_comparison` | `/vehicle-comparison/` | 430 | 430 | No | `.rv-vehicle-comparison` | `13px / 13px` | Pass |
| `rentiva_unified_search` | `/arac-sekme-arama/` | 430 | 430 | No | `.rv-unified-search` | `24px / 24px` | Pass |
| `rentiva_transfer_search` | `/transfer-search/` | 430 | 430 | No | `.mhm-premium-transfer-search` | `24px / 24px` | Pass |
| `rentiva_transfer_results` | `/transfer-search-results/` | 430 | 430 | No | `.mhm-transfer-results-page` | `13px / 13px` | Pass |
| `rentiva_contact` | `/contact-form/` | 430 | 430 | No | `.rv-contact-form` | `13px / 13px` | Pass |
| `rentiva_testimonials` | `/customer-reviews/` | 430 | 430 | No | `.rv-testimonials` | `13px / 13px` | Pass |
| `rentiva_vehicle_rating_form` | `/vehicle-rating-form/` | 430 | 430 | No | `.rv-rating-form` | `0px / 0px` | Pass (rendered while logged in) |
| `rentiva_my_bookings` | `/rezervasyonlarim/` | 430 | 430 | No | `.rv-bookings-page` | `12px / 12px` | Pass (rendered while logged in) |
| `rentiva_my_favorites` | `/favorilerim/` | 430 | 430 | No | `.rv-vehicles-grid-container` | `13px / 13px` | Pass (rendered while logged in) |
| `rentiva_payment_history` | `/odeme-gecmisi/` | 430 | 430 | No | `.mhm-rentiva-account-page` | `13px / 13px` | Pass (integrated account wrapper aligned to Golden Ratio gutter) |
| `rentiva_messages` | `/mesajlar/` | 430 | 430 | No | `.mhm-rentiva-account-page` | `13px / 13px` | Pass (integrated account wrapper aligned to Golden Ratio gutter) |

## Additional Notes

- `vehicles-grid` fatal issue from `debug.log` was resolved before this matrix run.
- `vehicle-details` overflow was resolved by normalizing CTA button box model in:
  - `assets/css/frontend/vehicle-details.css`
  - `assets/css/frontend/vehicle-rating-form.css`
- `availability-calendar` wrapper spacing was aligned and overflow-safe with border-box sizing in:
  - `assets/css/frontend/availability-calendar.css`
- This matrix includes logged-in verification using admin session.

## Re-Validation Evidence (2026-02-20, follow-up run)

| Check | URL | clientWidth | scrollWidth | Overflow | Result |
| :--- | :--- | ---: | ---: | :---: | :--- |
| Vehicles Grid | `/vehicles-grid/` | 430 | 430 | No | PASS |
| Vehicle Details | `/vehicle-details/` | 430 | 430 | No | PASS |
| Availability Calendar | `/availability-calendar/` | 430 | 430 | No | PASS |

- Admin session confirmation:
  - URL: `/wp-admin/`
  - `#loginform` not present
  - Dashboard title detected (`Başlangıç ‹ OtoKira — WordPress`)
