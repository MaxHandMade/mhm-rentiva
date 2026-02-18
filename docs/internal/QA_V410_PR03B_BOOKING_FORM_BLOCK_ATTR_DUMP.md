# QA_V410_PR03B_BOOKING_FORM_BLOCK_ATTR_DUMP

## A) File Reference
- **Source:** [assets/blocks/booking-form/block.json](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/assets/blocks/booking-form/block.json)
- **Status:** Baseline before PR-03B implementation.

## B) Raw Attributes Dump
```json
"attributes": {
    "vehicle_id": {
        "type": "string",
        "default": ""
    },
    "show_insurance": {
        "type": "boolean",
        "default": true
    },
    "show_addons": {
        "type": "boolean",
        "default": true
    },
    "show_vehicle_selector": {
        "type": "boolean",
        "default": true
    },
    "form_title": {
        "type": "string",
        "default": ""
    },
    "enable_deposit": {
        "type": "boolean",
        "default": true
    },
    "show_vehicle_info": {
        "type": "boolean",
        "default": true
    },
    "show_payment_options": {
        "type": "boolean",
        "default": true
    },
    "show_date_picker": {
        "type": "boolean",
        "default": true
    },
    "show_time_select": {
        "type": "boolean",
        "default": true
    },
    "className": {
        "type": "string",
        "default": ""
    }
}
```

## C) Normalized Table (Top Summary)

| Block Attribute | Type | Default | Notes |
| :--- | :--- | :--- | :--- |
| `vehicle_id` | string | "" | SOT Match |
| `show_insurance` | boolean | true | Legacy (Not in shortcode) |
| `show_addons` | boolean | true | SOT Match |
| `show_vehicle_selector` | boolean | true | SOT Match |
| `form_title` | string | "" | SOT Match |
| `enable_deposit` | boolean | true | SOT Match |
| `show_vehicle_info` | boolean | true | SOT Match |
| `show_payment_options` | boolean | true | SOT Match |
| `show_date_picker` | boolean | true | Legacy (Not in shortcode) |
| `show_time_select` | boolean | true | SOT Match |
| `className` | string | "" | Maps to `class` in Registry |

## D) Candidate Mapping Notes

### SOT Candidate Keys (Shortcode Match)
- `vehicle_id`
- `show_addons`
- `show_vehicle_selector`
- `form_title`
- `enable_deposit`
- `show_vehicle_info`
- `show_payment_options`
- `show_time_select`

### Legacy-Only Keys (No Shortcode Equivalent)
- `show_insurance`: Shortcode template displays insurance/addons only if `show_addons` is enabled. No separate `show_insurance` attribute exists in `BookingForm.php`.
- `show_date_picker`: Shortcode form always includes date fields; no conditional attribute exists.

### Missing Attributes in Block (Present in Shortcode SOT)
- `start_date`
- `end_date`
- `default_days`
- `min_days`
- `max_days`
- `redirect_url`
- `default_payment`

### Naming Mismatch Candidates
- `className` (Block) vs `class` (Shortcode) — *Handled by BlockRegistry mapper.*
- `show_addons` (Block) is functionally the same as `show_addons` (Shortcode), but block has `show_insurance` which might be a naming drift or intended sub-feature not implemented in shortcode.
