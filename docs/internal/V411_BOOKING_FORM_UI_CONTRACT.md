# V411_BOOKING_FORM_UI_CONTRACT

## Objective
Unify the "Selected Vehicle Summary" in the Booking Form across all entry points (Direct, Preselect via `vehicle_id`, and Selection List).

## Canonical Component
- **Path:** `templates/partials/vehicle-card.php`
- **Context:** MUST be compatible with the `BookingForm.php` data structure.

## Layout Rules
- **Desktop (>=1024px):**
  - Booking Summary: 2nd column/sidebar feel? NO, the requirement says "date fields 2nd column".
  - Selected Vehicle: Full width above or inside the form container as a premium card.
- **Mobile (<1024px):**
  - Fully stacked layout.

## Data Schema (Mandatory for Parity)
Every vehicle card must display:
1. `image`: High-quality medium size.
2. `title`: Vehicle model name.
3. `meta chips`: Seats, Transmission, Fuel, etc. (consistent SVG icons).
4. `rating`: 5-star display with count.
5. `price`: Formatted price per day with currency symbol.
6. `favorite icon`: Top-right aligned, functional CSS class `rv-vehicle-card__favorite`.

## Wrapper Invariants
- Must preserve the `rv-booking-form-wrapper` ID.
- Must preserve `data-vehicle-id` attributes for JS logic.
- Must preserve form element IDs for validation scripts.
