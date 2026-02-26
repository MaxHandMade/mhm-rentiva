---
name: mhm-architect
description: Designs WordPress-native feature architecture, database schemas, and technical specifications following MHM Rentiva standards. Use when planning new features, designing database structures, creating technical specs, or when architectural decisions are needed.
---

# MHM Architect

**Role:** The Designer of WordPress-Native Structures.  
**Motto:** "Namespace everything, pollute nothing."

## When to Use This Skill

Apply this skill when:
- Planning new features or functionality
- Designing database schemas or data structures
- Creating technical specifications
- Making architectural decisions
- Reviewing feature proposals for WordPress compliance

## Core Design Principles

### 1. Namespace & Collision Prevention
**Mandate:** Prevent fatal errors from class/function name conflicts.

**Standards:**
- **Classes:** Use PHP namespaces (`MHMRentiva\Domain\FeatureName\...`)
- **Global functions:** Prefix with `mhm_rentiva_` (e.g., `mhm_rentiva_get_booking()`)
- **Hooks:** Prefix with `mhm_rentiva_` (e.g., `mhm_rentiva_booking_created`)
- **Database tables:** Prefix with `mhm_rentiva_` (e.g., `wp_mhm_rentiva_bookings`)
- **Options:** Prefix with `mhm_rentiva_` (e.g., `mhm_rentiva_settings`)

**Example:**
```php
namespace MHMRentiva\Domain\Booking;

class BookingService {
    public function create(): void {
        do_action('mhm_rentiva_booking_created', $booking_id);
    }
}
```

### 2. Hook-Driven Architecture
**Mandate:** Decouple logic using WordPress hooks (Actions & Filters).

**Pattern:** Logic must be attached to hooks, not executed directly.

**Example:**
```php
// ✅ Correct: Hook-driven
add_action('init', function() {
    // Registration logic here
});

// ❌ Wrong: Direct execution
// Registration logic runs immediately
```

### 3. File Organization
**Structure:**
- `/src`: Classes & Logic (Autoloaded via PSR-4)
- `/assets`: Public JS/CSS (enqueued)
- `/templates`: View files (loaded via `load_template()`)
- `/languages`: Translation files (.pot, .mo)

**Access Control:** Every PHP file must start with:
```php
if (!defined('ABSPATH')) {
    exit;
}
```

## Feature Design Workflow

When designing a new feature, follow this process:

### Step 1: Requirements Analysis
- [ ] Identify user-facing functionality
- [ ] List required data points
- [ ] Determine WordPress integration points (admin, frontend, API)
- [ ] Identify dependencies on other features

### Step 2: Database Schema Design
- [ ] Create table names with `mhm_rentiva_` prefix
- [ ] Use `$wpdb->prefix` for table names: `$wpdb->prefix . 'mhm_rentiva_bookings'`
- [ ] Design indexes for query performance
- [ ] Plan for data migration if modifying existing tables
- [ ] Document relationships between tables

**Example Schema:**
```php
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
```

### Step 3: Class Structure Design
- [ ] Map feature to namespace: `MHMRentiva\Domain\FeatureName`
- [ ] Identify service classes (business logic)
- [ ] Identify repository classes (data access)
- [ ] Identify controller classes (hook handlers)
- [ ] Plan dependency injection points

**Example Structure:**
```
src/
└── Domain/
    └── Booking/
        ├── BookingService.php      # Business logic
        ├── BookingRepository.php   # Data access
        └── BookingController.php   # Hook handlers
```

### Step 4: Hook Integration Points
- [ ] Identify WordPress hooks to use (actions/filters)
- [ ] Define custom hooks for extensibility
- [ ] Plan hook priority and dependencies
- [ ] Document hook parameters and return values

**Example:**
```php
// Custom action hook
do_action('mhm_rentiva_booking_created', $booking_id, $booking_data);

// Custom filter hook
$price = apply_filters('mhm_rentiva_booking_price', $base_price, $booking_data);
```

### Step 5: Security & Validation
- [ ] Plan input sanitization points
- [ ] Plan output escaping points
- [ ] Identify nonce requirements
- [ ] Plan capability checks
- [ ] Design SQL injection prevention (use `$wpdb->prepare()`)

### Step 6: Internationalization
- [ ] Identify all user-facing strings
- [ ] Plan translation function usage (`__()`, `_e()`, `_n()`)
- [ ] Use text domain `'mhm-rentiva'` (string literal, not variable)

## Technical Specification Template

When creating a spec, include:

```markdown
# Feature: [Feature Name]

## Overview
[Brief description of the feature]

## Database Schema
- Table: `wp_mhm_rentiva_[table_name]`
- Fields: [list with types]
- Indexes: [list]
- Relationships: [describe]

## Class Structure
- Namespace: `MHMRentiva\Domain\[Feature]`
- Classes: [list with responsibilities]

## Hooks
- Actions: [list custom actions]
- Filters: [list custom filters]

## Security Considerations
- Input sanitization: [where and how]
- Output escaping: [where and how]
- Nonces: [which forms/actions]
- Capabilities: [required permissions]

## Integration Points
- WordPress hooks used: [list]
- Plugin dependencies: [list]
- External APIs: [if any]
```

## Anti-Patterns to Avoid

1. **Generic naming:** Never use `BookingForm`, use `MHMRentiva\Domain\Booking\BookingForm`
2. **Direct execution:** Never run logic outside hooks
3. **Raw SQL:** Always use `$wpdb->prepare()` for queries with variables
4. **Missing prefixes:** Never create global items without `mhm_rentiva_` prefix
5. **Direct access:** Never skip `ABSPATH` check in PHP files

## Verification Checklist

Before finalizing a design:
- [ ] All classes use proper namespaces
- [ ] All global items are prefixed with `mhm_rentiva_`
- [ ] Logic is attached to WordPress hooks
- [ ] Database tables use proper prefixes
- [ ] Security measures are planned (sanitization, escaping, nonces)
- [ ] All strings are planned for translation
- [ ] File structure follows project conventions