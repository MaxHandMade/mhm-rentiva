---
name: mhm-db-master
description: Manages WordPress database operations with security, performance, and data integrity. Use when writing database queries, creating/updating tables, optimizing database performance, managing schema changes, or when database operations are needed.
---

# MHM DB Master

**Role:** The Custodian of Data Integrity.  
**Motto:** "Prepare first, execute second."

## When to Use This Skill

Apply this skill when:
- Writing database queries or SQL statements
- Creating or updating database tables
- Optimizing database performance
- Managing schema changes or migrations
- Performing data cleanup or maintenance
- Writing repository classes or data access code
- Debugging database-related issues

## Core Responsibilities

### 1. Secure Query Execution

**Mandate:** NEVER use direct variables in SQL strings. ALWAYS use `$wpdb->prepare()` for unsafe data.

**Protocol:**
- Use `$wpdb->prepare()` for all queries containing user input or variables
- Use appropriate placeholders: `%d` (integer), `%f` (float), `%s` (string)
- Never concatenate variables directly into SQL strings

**Examples:**

❌ **Wrong:**
```php
$wpdb->query("SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE id = $id");
$wpdb->query("SELECT * FROM table WHERE status = '$status'");
```

✅ **Correct:**
```php
// Integer placeholder
$wpdb->query($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE id = %d",
    $id
));

// String placeholder
$wpdb->query($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE status = %s",
    $status
));

// Multiple placeholders
$wpdb->query($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE user_id = %d AND status = %s",
    $user_id,
    $status
));
```

**Complex Queries:**
```php
// For LIKE queries
$wpdb->query($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mhm_rentiva_vehicles WHERE name LIKE %s",
    '%' . $wpdb->esc_like($search_term) . '%'
));

// For IN clauses (use array)
$ids = array_map('intval', $booking_ids);
$placeholders = implode(',', array_fill(0, count($ids), '%d'));
$wpdb->query($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE id IN ($placeholders)",
    ...$ids
));
```

### 2. Schema Management

**Mandate:** Use `dbDelta()` for creating and updating tables to prevent data loss during updates.

**Tool:** `dbDelta()` function (requires `require_once(ABSPATH . 'wp-admin/includes/upgrade.php');`)

**Protocol:**
- Always use `dbDelta()` for table creation and updates
- Ensure proper table structure format (one field per line, proper spacing)
- Use `$wpdb->get_charset_collate()` for charset/collation
- Always include `PRIMARY KEY` definition

**Example:**
```php
global $wpdb;
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$table_name = $wpdb->prefix . 'mhm_rentiva_bookings';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    start_date datetime NOT NULL,
    end_date datetime NOT NULL,
    status varchar(20) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY vehicle_id (vehicle_id),
    KEY user_id (user_id),
    KEY status (status)
) $charset_collate;";

dbDelta($sql);
```

**Important Notes:**
- `dbDelta()` is case-sensitive for field names
- Each field must be on its own line
- Two spaces between field name and field definition
- Must include `PRIMARY KEY` definition
- Indexes should be defined with `KEY` keyword

**Optimization:** Ensure columns used in `WHERE` clauses are indexed.

### 3. Performance Optimization

**Mandate:** Avoid `SELECT *`. Select only required columns.

**Best Practices:**
- Select only the columns you need
- Use `WP_Query` over raw SQL whenever possible for caching benefits
- Add appropriate indexes for frequently queried columns
- Use `LIMIT` for large result sets
- Consider pagination for large datasets

**Examples:**

❌ **Wrong:**
```php
$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings");
```

✅ **Correct:**
```php
// Select specific columns
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT id, vehicle_id, start_date, end_date, status 
     FROM {$wpdb->prefix}mhm_rentiva_bookings 
     WHERE user_id = %d 
     LIMIT 100",
    $user_id
));

// Use WP_Query when possible (for posts)
$query = new WP_Query([
    'post_type' => 'vehicle',
    'posts_per_page' => 10,
    'meta_query' => [
        [
            'key' => 'mhm_rentiva_status',
            'value' => 'available',
            'compare' => '='
        ]
    ]
]);
```

**Indexing Strategy:**
- Index foreign keys (`vehicle_id`, `user_id`)
- Index columns used in `WHERE` clauses (`status`, `created_at`)
- Index columns used in `ORDER BY` clauses
- Consider composite indexes for multi-column queries

### 4. Data Integrity & Transactions

**Mandate:** Ensure data consistency and handle errors gracefully.

**Protocol:**
- Use transactions for multi-step operations when possible
- Always check for errors after database operations
- Validate data before insertion/updates
- Use appropriate data types

**Example:**
```php
// Check for errors
$result = $wpdb->insert(
    $wpdb->prefix . 'mhm_rentiva_bookings',
    [
        'vehicle_id' => $vehicle_id,
        'user_id' => $user_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'status' => 'pending'
    ],
    ['%d', '%d', '%s', '%s', '%s']
);

if (false === $result) {
    // Handle error
    error_log('Failed to insert booking: ' . $wpdb->last_error);
    return false;
}

$booking_id = $wpdb->insert_id;
```

**Data Validation:**
```php
// Validate before database operations
$vehicle_id = absint($_POST['vehicle_id']);
$user_id = get_current_user_id();

if (!$vehicle_id || !$user_id) {
    return new WP_Error('invalid_data', __('Invalid booking data.', 'mhm-rentiva'));
}

// Sanitize dates
$start_date = sanitize_text_field($_POST['start_date']);
$end_date = sanitize_text_field($_POST['end_date']);
```

### 5. Table Prefix & Naming

**Mandate:** Always use `$wpdb->prefix` for table names and follow naming conventions.

**Protocol:**
- Use `$wpdb->prefix` for all table names
- Prefix custom tables with `mhm_rentiva_`
- Use descriptive table names

**Example:**
```php
global $wpdb;

$table_name = $wpdb->prefix . 'mhm_rentiva_bookings';
$meta_table = $wpdb->prefix . 'mhm_rentiva_booking_meta';
```

## Common Database Operations

### Insert
```php
$wpdb->insert(
    $wpdb->prefix . 'mhm_rentiva_bookings',
    [
        'vehicle_id' => $vehicle_id,
        'user_id' => $user_id,
        'status' => 'pending'
    ],
    ['%d', '%d', '%s'] // Format specifiers
);
```

### Update
```php
$wpdb->update(
    $wpdb->prefix . 'mhm_rentiva_bookings',
    ['status' => 'confirmed'],
    ['id' => $booking_id],
    ['%s'],
    ['%d']
);
```

### Delete
```php
$wpdb->delete(
    $wpdb->prefix . 'mhm_rentiva_bookings',
    ['id' => $booking_id],
    ['%d']
);
```

### Select (Single Row)
```php
$booking = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE id = %d",
    $booking_id
), ARRAY_A);
```

### Select (Multiple Rows)
```php
$bookings = $wpdb->get_results($wpdb->prepare(
    "SELECT id, vehicle_id, status 
     FROM {$wpdb->prefix}mhm_rentiva_bookings 
     WHERE user_id = %d",
    $user_id
), ARRAY_A);
```

### Select (Single Value)
```php
$count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}mhm_rentiva_bookings WHERE status = %s",
    'pending'
));
```

## Anti-Patterns to Avoid

1. **SQL Injection:** Never concatenate variables directly into SQL strings
2. **SELECT *:** Always select specific columns
3. **Missing Indexes:** Don't forget to index frequently queried columns
4. **Raw SQL for Posts:** Use `WP_Query` for post-related queries when possible
5. **Missing Error Handling:** Always check for errors after database operations
6. **Hardcoded Table Names:** Always use `$wpdb->prefix`
7. **Missing Format Specifiers:** Always provide format specifiers for `insert()` and `update()`
8. **Direct dbDelta:** Don't use raw `CREATE TABLE` without `dbDelta()`

## Verification Checklist

Before finalizing database code:
- [ ] All queries use `$wpdb->prepare()` for variables
- [ ] Table names use `$wpdb->prefix`
- [ ] `dbDelta()` is used for table creation/updates
- [ ] Only required columns are selected (no `SELECT *`)
- [ ] Appropriate indexes are defined
- [ ] Error handling is implemented
- [ ] Data validation occurs before database operations
- [ ] Format specifiers are provided for `insert()`/`update()`
- [ ] `WP_Query` is used when possible instead of raw SQL
