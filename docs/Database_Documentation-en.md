# Database Documentation

This folder contains the database structure and meta keys documentation for the MHM Rentiva plugin.

## Files

All database-related files are located in `src/Admin/Utilities/Database/`:

### MetaKeysDocumentation.php
Standard documentation for all meta keys used in the plugin. Contains list of meta keys by category, types, values and usage areas. Add new meta keys according to this documentation.

**Location:** `src/Admin/Utilities/Database/MetaKeysDocumentation.php`

### DatabaseInitialization.php
Code that creates the database when the plugin is first installed. Contains meta key registration, default settings, and cron jobs. Runs automatically on plugin activation.

**Location:** `src/Admin/Utilities/Database/DatabaseInitialization.php`

### DatabaseCleanupPage.php
Database cleanup page for admin panel. Provides tools for cleaning unnecessary meta keys and optimizing database performance.

**Location:** `src/Admin/Utilities/Database/DatabaseCleanupPage.php`

## Meta Key Categories

### Vehicle Meta Keys
- `_mhm_vehicle_availability` - Vehicle availability status (STANDARD)
- `_mhm_vehicle_status` - Vehicle status (backup)
- `_mhm_rentiva_*` - Vehicle information (brand, model, year, etc.)

### Booking Meta Keys
- `_mhm_vehicle_id` - ID of the vehicle being booked
- `_mhm_status` - Booking status
- `_mhm_*_date` - Date information
- `_mhm_*_time` - Time information

### Customer Meta Keys
- `_mhm_customer_*` - Customer information (name, surname, email, phone)

### Payment Meta Keys
- `_mhm_payment_*` - Payment information (method, status, amount)

### Receipt Meta Keys
- `_mhm_receipt_*` - Receipt information (status, file, date)

### System Meta Keys
- `_mhm_shortcode` - Shortcode information
- `_mhm_auto_created` - Automatically created
- `_mhm_booking_*` - Booking system information

## Important Rules

### What Should Be Done:
1. Add new meta keys according to this documentation
2. Create meta key names consistently
3. Always add required meta keys
4. Define meta key types correctly (string, number, array)
5. Define value ranges clearly

### What Should Not Be Done:
1. Do not use old meta keys
2. Do not create inconsistent meta key names
3. Do not add unnecessary meta keys
4. Do not mix meta key types
5. Do not leave value ranges undefined

## Standard Meta Key Format

```
_mhm_[category]_[field_name]
```

**Examples:**
- `_mhm_vehicle_availability` - Vehicle availability status
- `_mhm_booking_status` - Booking status
- `_mhm_customer_email` - Customer email
- `_mhm_payment_method` - Payment method

## Meta Key Types

### String Meta Keys
- **Type:** `string`
- **Usage:** Text information (name, surname, email, etc.)
- **Sanitize:** `sanitize_text_field`, `sanitize_email`

### Number Meta Keys
- **Type:** `number`
- **Usage:** Numeric information (ID, price, year, etc.)
- **Sanitize:** `absint`, `floatval`

### Array Meta Keys
- **Type:** `array`
- **Usage:** Multiple values (features, equipment, etc.)
- **Sanitize:** Custom array sanitization callbacks

## Usage

### Adding Meta Key:
```php
register_post_meta('vehicle', '_mhm_vehicle_availability', [
    'type' => 'string',
    'single' => true,
    'sanitize_callback' => 'sanitize_text_field',
    'auth_callback' => '__return_true',
    'show_in_rest' => true,
    'description' => 'Vehicle availability status (active/inactive)'
]);
```

### Using Meta Key:
```php
// Get value
$availability = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);

// Save value
update_post_meta($vehicle_id, '_mhm_vehicle_availability', 'active');
```

## Troubleshooting

### Meta Key Not Found:
1. Check if the meta key is registered in `DatabaseInitialization.php`
2. Re-run plugin activation
3. Check meta key existence in database

### Inconsistent Meta Keys:
1. Check `MetaKeysDocumentation.php` file
2. Use standard format: `_mhm_[category]_[field_name]`
3. Clean up old meta keys

### Performance Issues:
1. Clean up unnecessary meta keys
2. Define meta keys with correct types
3. Use sanitize callbacks
