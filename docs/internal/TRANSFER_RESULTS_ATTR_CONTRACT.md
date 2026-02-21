# Transfer Results Attribute Contract

## Root Cause Analysis
The disruption in Gutenberg-to-Frontend parity for Transfer Results is caused by two distinct failures in the pipeline:

1. **Schema Validation Failure (Sorting & Enums):**
   The `CanonicalAttributeMapper` uses `Transformers::to_enum` for attributes like `orderby` and `order`. However, the `AllowlistRegistry` fails to define the allowed `'values'` array for these enums. As a result, the transformer violently rejects any user-provided value from the Gutenberg block, defaulting to `'price'` and `'asc'` regardless of the block configuration.
2. **Template CSS Wrapper Failure (Layout Switcher):**
   The `layout` attribute (e.g., `grid` or `list`) correctly survives the mapping pipeline (because it is typed as `'string'`, bypassing the enum bug). However, `templates/shortcodes/transfer-results.php` applies the layout CSS class (e.g., `rv-transfer-results--grid`) *only inside the populated results block*, skipping the outer component wrapper entirely. This renders the `data-columns` and `layout` styling inaccessible for empty states or wrapper-level CSS dependencies.

## Attribute Contract Map (Block ↔ Shortcode)

| Block Attribute | Shortcode Attribute | CAM Type / Alias | Valid Values | Default | Issue Identified |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `layout` | `layout` | `string` | `list`, `grid`, `compact` | `list` | Fails at Template layer. Class is not applied universally. |
| `columns` | `columns` | `int` | `1`-`4` | `2` | Fails at Template layer for empty states. |
| `sortBy` | `orderby` | `enum` (`sortBy` alias) | `price`, `popularity`, `newest`, `capacity` | `price` | Fails at CAM layer. Missing `'values'` in schema. Drops user input. |
| `sortOrder` | `order` | `enum` (`sortOrder` alias)| `asc`, `desc` | `asc` | Fails at CAM layer. Missing `'values'` in schema. Drops user input. |
| `limit` | `limit` | `int` | integers | `10` | Functioning reliably. |
| `showPrice` | `show_price` | `bool` | `0`,`1` | `true` | Functioning reliably (`"0"` evaluates falsy). |
| `showVehicleDetails` | `show_vehicle_details` | `bool` | `0`,`1` | `true` | Functioning reliably. |
| `showLuggageInfo` | `show_luggage_info`| `bool` | `0`,`1` | `true` | Functioning reliably. |
| `showPassengerCount` | `show_passenger_count`| `bool` | `0`,`1` | `true` | Functioning reliably. |
| `showBookButton` | `show_booking_button`| `bool` (`showBookButton` alias) | `0`,`1` | `true` | Functioning reliably. |
| `showRouteInfo` | `show_route_info`| `bool` | `0`,`1` | `true` | Functioning reliably. |
| `showFavoriteButton` | `show_favorite_button`| `bool` | `0`,`1` | `true` | Functioning reliably. |
| `showCompareButton` | `show_compare_button`| `bool` | `0`,`1` | `true` | Functioning reliably. |
| `className` | `class` | `string` (`className` alias) | custom string | `""` | Functioning reliably. |

## Precedence Rules
1. **Block JSON / Core Defaults:** Lowest precedence. If not present in the block attributes, schema defaults map the fallback.
2. **Shortcode Attributes:** Standard parsed values.
3. **Canonical Block Attributes:** Evaluated via `data-debug-atts` mapping. These represent user interaction in the Gutenberg editor and are the Source of Truth.
4. **URL Query Overrides:** Evaluated only for non-canonical renders (when `_canonical=1` is absent).

## Corrective Actions
1. **Patch `AllowlistRegistry`:** Add `'values'` arrays to `orderby`, `order`, `status`, `type`, and `service_type` schema definitions to prevent `Transformers::to_enum` from stripping user values.
2. **Patch `transfer-results.php`:** Elevate the `$wrapper_class` assembly above the `if (empty($results))` conditional, applying it to the outermost `.mhm-transfer-results-page` wrapper unconditionally.
3. **Verify Routing:** Ensure no `/qa-transfer-results/` pages exist (run `wp post list`). Update documentation.
