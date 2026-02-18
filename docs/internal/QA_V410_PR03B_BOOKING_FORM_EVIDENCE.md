# QA Evidence: PR-03B Booking Form Parity Implementation

## Summary
Achieved full parity between the `mhm-rentiva/booking-form` block and the `rentiva_booking_form` shortcode by synchronizing attribute surfaces and implementing a deterministic mapping strategy.

## Key Changes
- **Attribute Synchronization**: Added `startDate`, `endDate`, `defaultDays`, `minDays`, `maxDays`, `redirectUrl`, and `defaultPayment` to `block.json`.
- **Dynamic Defaults**: Omitted defaults for `defaultDays`, `minDays`, and `maxDays` in `block.json` to respect SOT (Shortcode) dynamic settings.
- **Inspector UI**: Added "Redirect & Payment" and "Advanced Date Overrides" panels to the Block Editor settings.
- **Mapping Logic**: Refactored `BlockRegistry::map_attributes_to_shortcode` to:
  - Implement an explicit ignore list for legacy keys (`show_insurance`, `show_date_picker`).
  - Add explicit aliases for SOT-miss attributes.
  - **Class Mapping Fix**: Standardized `className` to `class` mapping globally to avoid `class_name` mismatches.
  - Enforce a deterministic mapping order (Alias > camelCase-to-snake_case > Type Normalization > Ignore).

## Verification Results

### 1. Automated Gates
- [x] **PHPCS**: Passed.
- [x] **PHPUnit**: Passed.
- [x] **Plugin Check**: Passed.

### 2. Manual Smoke Tests
- [x] **Inspector UI**: Verified presence of Redirect URL and Date Overrides.
- [x] **SSR Parity**: Verified that setting block attributes correctly updates the shortcode render via `data-debug-atts`.

#### Evidence: Inspector UI
![Inspector UI Verification](file:///C:/Users/manag/.gemini/antigravity/brain/0f0b0549-7810-48ed-b44a-69381aa99557/booking_form_inspector_panels_verified.png)

#### Evidence: Attribute Mapping (SSR DOM)
````json
{
  "iframe": "editor-canvas",
  "atts": "vehicle_id=\"\" show_addons=\"1\" show_vehicle_selector=\"1\" form_title=\"\" enable_deposit=\"1\" show_vehicle_info=\"1\" show_payment_options=\"1\" show_time_select=\"1\" start_date=\"2026-10-10\" end_date=\"2026-10-15\" redirect_url=\"http://localhost/success\" default_payment=\"full\" class=\"\""
}
````

## Parity Status: YES/YES
The `rentiva_booking_form` is now in full parity.
- Attribute Parity: **YES**
- Default Parity: **YES**
