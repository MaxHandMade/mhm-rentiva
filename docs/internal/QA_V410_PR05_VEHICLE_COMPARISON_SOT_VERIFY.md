# SOT Verification: Vehicle Comparison (PR-05)

## A) Shortcode Defaults (SOT)
- **File**: [VehicleComparison.php](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Frontend/Shortcodes/VehicleComparison.php)
- **Defaults (L105-L117)**:
  - `vehicle_ids` => ''
  - `show_features` => 'all'
  - `max_vehicles` => '4'
  - `show_add_vehicle` => '1'
  - `show_remove_buttons` => '1'
  - `show_prices` => '1'
  - `show_images` => '1'
  - `show_booking_buttons` => '1'
  - `layout` => 'table'
  - `title` => ''
  - `class` => ''

## B) Accepted Attribute Surface
- **shortcode_atts() ref**: L119
- **Accepted Keys**: All keys defined in `$defaults` (L105-L117).
- **Unknown keys ignored**: YES (Standard `shortcode_atts` behavior).
- **Ghost Keys**: `manual_add` is used in `prepare_template_data` (L145, L155) but is NOT defined in the `$defaults` array, meaning it will be stripped by `shortcode_atts()` unless passed directly via `do_shortcode` without going through the `render` method's filter.

## C) Template Consumption Map
- **File**: [vehicle-comparison.php](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/templates/shortcodes/vehicle-comparison.php)
- **Consumed Keys**:
  - `layout` (L30)
  - `show_prices` (L31)
  - `class` (L32)
  - `title` (L67)
- **Prepared Context Variables**:
  - `show_add_vehicle` (L39) -> Computed from `manual_add` (Ghost Key) and vehicle count.
  - `features` (L36, L156) -> Array of feature labels, ignores `show_features` attribute logic in current implementation.
- **Ghost/Dead Keys**:
  - `show_remove_buttons`: Defined in defaults but NOT checked in template; remove buttons are currently always rendered if the loop runs.
  - `show_images`: Defined in defaults but NOT checked in template; images render if `image_url` is non-empty.
  - `show_booking_buttons`: Defined in defaults but NOT checked in template.

## D) Block Attributes Dump (Summary)
- **File**: [block.json](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/assets/blocks/vehicle-comparison/block.json)

| Block Attribute | Type | Default | Notes |
| :--- | :--- | :--- | :--- |
| `vehicleIds` | string | "" | |
| `showTechnicalSpecs` | boolean | true | Used for visibility logic? |
| `showComparisonImages`| boolean | true | Mapped to `show_images`? |
| `showPrice` | boolean | true | Mapped to `show_prices`? |
| `showRating` | boolean | true | |
| `showFeatures` | boolean | true | Logic mismatch (Shortcode expects 'all'/'basic') |
| `showBookButton` | boolean | true | Mapped to `show_booking_buttons` |
| `maxVehicles` | string | "4" | |

## E) Naming Mismatch Table
| Block Key | Auto snake_case output | Shortcode Expected Key | Needs Alias? | Reason |
| :--- | :--- | :--- | :--- | :--- |
| `showPrice` | `show_price` | `show_prices` | **YES** | Plural mismatch. |
| `showComparisonImages`| `show_comparison_images` | `show_images` | **YES** | Shortcode use shorter key. |
| `showBookButton` | `show_book_button` | `show_booking_buttons`| **YES** | Plural + naming mismatch. |
| `showFeatures` | `show_features` | `show_features` | **PARTIAL** | Key matches, but value type (bool vs string) mismatch. |

## F) BlockRegistry Current Special-Casing
- **Ref**: [BlockRegistry.php L559-L565](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Blocks/BlockRegistry.php#L559-L565)
- **Function**: Corrects the plural/naming mismatches identified in Table E.
- **Anomaly**: Aliases for `showRemoveButton` and `showAddVehicle` exist (L563-564) but these attributes are NOT defined in `block.json`.

## G) Decision
- **Decision**: Special aliasing is **REQUIRED**.
- **Reason**: The shortcode uses pluralized visibility keys (`show_prices`, `show_images`, `show_booking_buttons`) which diverge from the standard singular camelCase naming used in the block.
- **Minimal Parity Action**:
  1. **Sync block.json**: Add `showRemoveButton` and `showAddVehicle` to attributes.
  2. **Refactor Shortcode**: Fix "Ghost Key" `manual_add` by adding it to defaults or renaming to `show_add_vehicle`.
  3. **Refactor Template**: Implement checks for `show_images`, `show_booking_buttons`, and `show_remove_buttons`.
  4. **Maintain Alias**: Keep and expand `BlockRegistry` aliases to ensure clean attribute passing.
