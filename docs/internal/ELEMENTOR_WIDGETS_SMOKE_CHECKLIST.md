# Elementor Widgets Smoke Checklist (Critical 4)

Date: 2026-02-20  
Scope: Quick smoke validation for critical widgets after shortcode/block parity fixes.

## 1) Pre-check Results (Code-Level)

| Check | Result |
| :--- | :--- |
| Base widget has `register_controls()` hook | PASS |
| Base widget category slug is `mhm-rentiva` | PASS |
| `SearchResultsWidget` registered in `ElementorIntegration` | PASS |
| `TransferResultsWidget` registered in `ElementorIntegration` | PASS |
| `VehicleDetailsWidget` registered in `ElementorIntegration` | PASS |
| `VehicleComparisonWidget` registered in `ElementorIntegration` | PASS |
| Widget -> shortcode render links exist | PASS |
| Required block JSON files exist | PASS |

## 2) Manual Smoke Matrix (Editor + Frontend)

Use one dedicated test page and add these widgets one by one.

| Widget | Editor Check | Frontend Check | Expected |
| :--- | :--- | :--- | :--- |
| Search Results | Widget is visible under `MHM Rentiva` category. `Show Filters` control appears and can toggle. | Toggle OFF hides filter UI, toggle ON shows filter UI. | Editor and frontend reflect same visibility behavior. |
| Transfer Results | Widget appears under `MHM Rentiva`. Content controls are visible. | `showBookButton/showCompareButton/showFavoriteButton/showRouteInfo` toggles affect output. | Route info and action buttons follow widget settings. |
| Vehicle Details | Widget appears under `MHM Rentiva`. Controls render correctly. | `show_gallery`, `show_booking_form`, `show_calendar`, `show_booking_button` affect output sections. | Hidden sections are not rendered on frontend. |
| Vehicle Comparison | Widget appears under `MHM Rentiva`. Controls render correctly. | Booking button controls (`showBookButton/show_booking_buttons`) hide/show reservation CTA buttons. | No reservation CTA is rendered when disabled. |

## 3) Focused Assertions

- Category assertion: all tested widgets must be listed under `MHM Rentiva`.
- Control assertion: widget controls must be visible in Elementor sidebar.
- Parity assertion: changed controls must produce immediate frontend-visible differences.
- Regression assertion: no PHP warning/fatal in debug log while rendering tested widgets.

## 4) Evidence to Capture

- Screenshot from Elementor editor panel for each widget.
- Screenshot from frontend for ON/OFF states of at least one critical control per widget.
- Short note for each widget: `PASS` or `FAIL` + issue summary.

## 5) Pass/Fail Rule

- PASS: all 4 widgets satisfy category, control visibility, and frontend parity checks.
- FAIL: any widget missing in category, missing controls, or control changes not reflected in frontend.
