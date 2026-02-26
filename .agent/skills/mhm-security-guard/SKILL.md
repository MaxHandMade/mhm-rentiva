---
name: mhm-security-guard
description: Enforces WordPress security standards for the MHM Rentiva plugin including nonce verification, data sanitization, output escaping, capability checks, and SQL injection prevention. Use when writing AJAX handlers, form processing, database queries, template rendering, or when the user mentions security, nonce, sanitization, escaping, or SQL injection.
---

# MHM Security Guard

**Role:** The Security Enforcer.  
**Motto:** "Verify, Don't Trust."

## When to Use This Skill

Apply this skill when:
- Writing AJAX handlers or form processing code
- Creating database queries or SQL statements
- Rendering templates or outputting user data
- Reviewing code for security vulnerabilities
- User mentions security, nonce, sanitization, escaping, or SQL injection
- Implementing rate limiting for public forms
- Performing security audits or code reviews

## Core Responsibilities

### 1. Direct Access Protection

**Mandate:** Every PHP file MUST prevent direct access from outside WordPress.

**Protocol:**
```php
<?php
defined('ABSPATH') || exit;
```

This must be the first line after the opening PHP tag in all class files, view files, and includes.

### 2. AJAX and Form Security

**Mandate:** All AJAX handlers and form processors MUST use `SecurityHelper::verify_ajax_request_or_die()`.

**Protocol:**

```php
public function handle_ajax_request() {
    // Nonce + Capability verification in one call
    \MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die(
        'mhm_rentiva_action_nonce',  // Action-specific nonce name
        'manage_options',             // Required capability
        __('Security check failed.', 'mhm-rentiva')
    );

    // Business logic...
}
```

**Wrong ❌:**
```php
check_ajax_referer('my_nonce'); // Insufficient - no capability check
if (!current_user_can('edit_posts')) return; // Manual check error-prone
```

**Correct ✅:**
```php
\MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die('my_action_nonce', 'edit_posts');
```

### 3. Data Validation and Sanitization

**Mandate:** All user input MUST be validated and sanitized using appropriate methods.

**SecurityHelper Methods:**

| Data Type | Method | Purpose |
| :--- | :--- | :--- |
| **Vehicle ID** | `SecurityHelper::validate_vehicle_id($id)` | Validates ID > 0 |
| **Date** | `SecurityHelper::validate_date($date)` | Validates date format |
| **Email** | `SecurityHelper::validate_email($email)` | Validates and sanitizes email |
| **Numeric Array** | `SecurityHelper::validate_numeric_array($arr)` | Sanitizes numeric arrays |
| **Text** | `mhm_rentiva_sanitize_text_field_safe($str)` | Safely converts null to string |

**Example:**
```php
try {
    $vehicle_id = \MHMRentiva\Admin\Core\SecurityHelper::validate_vehicle_id($_POST['vehicle_id'] ?? 0);
    $start_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['start_date'] ?? '');
    
    // Process...
} catch (\InvalidArgumentException $e) {
    wp_send_json_error(['message' => $e->getMessage()]);
}
```

### 4. SQL Injection Prevention

**Mandate:** Variables MUST NEVER be directly inserted into SQL strings. `$wpdb->prepare()` is mandatory.

**Protocol:**
```php
// WRONG ❌
$wpdb->query("SELECT * FROM table WHERE id = $id");

// CORRECT ✅
$wpdb->query($wpdb->prepare("SELECT * FROM table WHERE id = %d", $id));
```

### 5. Output Escaping

**Mandate:** All data output in templates MUST be escaped using appropriate WordPress functions.

**Escaping Functions:**

- **HTML Content:** `esc_html($var)` or `esc_html__('Text', 'mhm-rentiva')`
- **HTML Attribute:** `esc_attr($var)`
- **URL:** `esc_url($var)`
- **JavaScript:** `esc_js($var)` or `wp_json_encode($var)`

**Template Example:**
```php
<!-- WRONG ❌ -->
<div class="vehicle-title"><?php echo $vehicle->post_title; ?></div>
<a href="<?php echo $url; ?>">Link</a>

<!-- CORRECT ✅ -->
<div class="vehicle-title"><?php echo esc_html($vehicle->post_title); ?></div>
<a href="<?php echo esc_url($url); ?>"><?php esc_html_e('View Details', 'mhm-rentiva'); ?></a>
```

### 6. Rate Limiting

**Mandate:** Public forms (e.g., Booking Form) MUST implement rate limiting to prevent flood attacks.

**Protocol:**
```php
// Maximum 5 requests per 300 seconds (5 minutes)
\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
    'booking_submission', 
    5, 
    300, 
    __('Too many requests.', 'mhm-rentiva')
);
```

## Security Audit Checklist

When reviewing code, verify:

- [ ] All files start with `defined('ABSPATH') || exit;`
- [ ] AJAX handlers use `SecurityHelper::verify_ajax_request_or_die()`
- [ ] All `$wpdb` queries use `prepare()` method
- [ ] `$_POST` / `$_GET` data is sanitized with appropriate functions
- [ ] All `echo` output uses `esc_*` functions
- [ ] Public forms implement rate limiting
- [ ] User capabilities are checked before sensitive operations
- [ ] Nonces are used for all form submissions and AJAX requests
