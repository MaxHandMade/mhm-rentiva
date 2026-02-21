# Shortcode Page Spacing Validation

Date: 2026-02-20  
Scope: active shortcode pages and their main frontend wrappers

## Rule Used
- Surface wrapper must have non-zero horizontal spacing.
- Preferred source: `assets/css/core/golden-ratio-contract.css` via shared gutter tokens.
- `padding: 0` is allowed only for internal utility/layout elements, not for page surface wrappers.

## Global Contract Coverage
- Source: `assets/css/core/golden-ratio-contract.css`
- Shared surface wrappers now include:
`rv-search-results`, `rv-unified-search`, `rv-vehicles-grid-container`, `rv-vehicles-list-container`, `mhm-transfer-results-page`, `mhm-premium-search`, `mhm-premium-transfer-search`, `mhm-transfer-search-wrapper`, `mhm-rentiva-featured-wrapper`, `mhm-rentiva-account-page`, `rv-unified-ratings-section`, `rv-booking-form-wrapper`, `rv-contact-form`, `rv-testimonials`, `rv-availability-calendar`, `rv-booking-confirmation`, `rv-vehicle-details`, `rv-vehicle-comparison`.

## Page-by-Page Validation

| Shortcode | Main Wrapper | File | Horizontal Spacing Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `rentiva_search_results` | `.rv-search-results` | `assets/css/frontend/search-results.css` | PASS | Uses tokenized `padding-inline`; no wrapper-level `padding-inline: 0`. |
| `rentiva_unified_search` | `.rv-unified-search` | `assets/css/frontend/unified-search.css` | PASS | Local inner padding exists and global contract also covers surface. |
| `rentiva_transfer_search` | `.mhm-transfer-search-wrapper` | `assets/css/frontend/transfer.css` | PASS | Wrapper now under global surface contract. |
| `rentiva_transfer_results` | `.mhm-transfer-results-page` | `assets/css/frontend/transfer-results.css` | PASS | Grid/list gaps tokenized; route row no longer treated as card column. |
| `rentiva_booking_form` | `.rv-booking-form-wrapper` | `assets/css/frontend/booking-form.css` | PASS | Surface wrapper covered by global contract. |
| `rentiva_booking_confirmation` | `.rv-booking-confirmation` | `assets/css/frontend/booking-confirmation.css` | PASS | Surface has non-zero padding; internal `padding: 0` lines are list/utility level. |
| `rentiva_availability_calendar` | `.rv-availability-calendar` | `assets/css/frontend/availability-calendar.css` | PASS | Surface has non-zero padding; internal zero paddings are component-level resets. |
| `rentiva_vehicle_details` | `.rv-vehicle-details-wrapper` / `.rv-vehicle-details` | `assets/css/frontend/vehicle-details.css` | PASS | Wrapper has side padding; detail panel uses internal layout spacing. |
| `rentiva_vehicle_comparison` | `.rv-vehicle-comparison` | `assets/css/frontend/vehicle-comparison.css` | PASS | Surface wrapper preserved; zero paddings are table/mobile accordion internals. |
| `rentiva_vehicles_grid` | `.rv-vehicles-grid-container` | `assets/css/frontend/vehicles-grid.css` | PASS | `padding-inline: 0` removed; token-based gutter active. |
| `rentiva_vehicles_list` | `.rv-vehicles-list-container` | `assets/css/frontend/vehicles-list.css` | PASS | Tokenized inline spacing active on wrapper. |
| `rentiva_featured_vehicles` | `.mhm-rentiva-featured-wrapper` | `assets/css/frontend/featured-vehicles.css` | FIXED | `padding: 2rem 0` replaced with tokenized inline + block padding. |
| `rentiva_contact` | `.rv-contact-form` | `assets/css/frontend/contact-form.css` | PASS | Wrapper has non-zero local padding and global contract coverage. |
| `rentiva_testimonials` | `.rv-testimonials` | `assets/css/frontend/testimonials.css` | PASS | Global contract supplies uniform surface gutter. |
| `rentiva_vehicle_rating_form` | `.rv-unified-ratings-section` | `assets/css/frontend/vehicle-rating-form.css` | PASS | Wrapper now covered by global contract. |
| `rentiva_my_bookings` / `rentiva_my_favorites` / `rentiva_payment_history` / `rentiva_messages` | `.mhm-rentiva-account-page` | `assets/css/frontend/my-account.css` | PASS | Shared account wrapper now in global contract; internal menu resets are expected. |

## Why You Still See `padding: 0` In Some Files
- Remaining `padding: 0` declarations are mostly internal elements:
list resets, checkbox controls, icon/button wrappers, table/accordion bodies.
- These do not define page surface spacing and are not considered Golden Ratio surface violations.

## Featured Vehicles Mobile Overflow Fix (2026-02-20)

Issue:
- Mobile page showed horizontal scrollbar in `Featured Vehicles`.
- Root cause was mixed block alignment behavior:
some `mhm-rentiva` blocks (including featured) were not covered by mobile `alignfull/alignwide` normalization in `gutenberg-blocks.css`.
- Secondary risk was grid/item width behavior in featured wrapper.

Fixes:
- `assets/css/frontend/gutenberg-blocks.css`
  - Added generic block selectors:
  - `[class*="wp-block-mhm-rentiva-"].alignwide`
  - `[class*="wp-block-mhm-rentiva-"].alignfull`
  - Mobile normalization at `782px`: width/max-width/margins reset for all plugin blocks.
  - Mobile safety: `overflow-x: clip` for all plugin blocks.
- `assets/css/frontend/featured-vehicles.css`
  - Added surface overflow guard: `overflow-x: clip`.
  - Added width guards: `max-width: 100%`, grid/item `min-width: 0`.
  - Updated mobile breakpoint to project standard (`782px`) for 1-column behavior.
  - Zeroed swiper side paddings on mobile to avoid extra horizontal pressure.

Assets sweep note:
- JS check (`assets/js/frontend/featured-vehicles.js`) showed no direct width forcing.
- Overflow trigger was CSS alignment/width interaction, not JS logic.

## Quick Re-Check Commands
```powershell
rg -n "padding-inline\\s*:\\s*0" assets/css/frontend assets/css/core
rg -n "^\\.(rv-search-results|rv-unified-search|mhm-transfer-search-wrapper|mhm-transfer-results-page|rv-booking-form-wrapper|rv-booking-confirmation|rv-vehicles-grid-container|rv-vehicles-list-container|mhm-rentiva-featured-wrapper|rv-contact-form|rv-testimonials|rv-availability-calendar|rv-vehicle-details|rv-vehicle-comparison|rv-unified-ratings-section|mhm-rentiva-account-page)" assets/css/frontend assets/css/core/golden-ratio-contract.css
```
