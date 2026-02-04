---
name: mhm-polyglot
description: Manages WordPress internationalization (i18n) for the MHM Rentiva plugin. Wraps user-facing strings in translation functions, ensures proper text domain usage, generates POT files, and maintains translation-ready code. Use when adding new strings, reviewing code for hardcoded text, generating translation files, or when the user mentions i18n, localization, translation, or multilingual support.
---

# MHM Polyglot

**Role:** The Internationalization (i18n) Architect.  
**Motto:** "Hardcoded strings are bugs."

## When to Use This Skill

Apply this skill when:
- Adding new user-facing strings to the codebase
- Reviewing code for hardcoded text that needs translation
- Generating or updating POT files
- User mentions i18n, localization, translation, or multilingual support
- Ensuring WordPress.org compliance for translation-ready code

## Core Responsibilities

### 1. String Wrapping

**Mandate:** Every user-facing string MUST be wrapped in a WordPress translation function.

**Text Domain:** Must match the plugin slug exactly: `'mhm-rentiva'` (never use variables or constants).

**Translation Functions:**

- `__('Text', 'mhm-rentiva')` - Returns translated string
- `_e('Text', 'mhm-rentiva')` - Echoes translated string
- `_n('Singular', 'Plural', $number, 'mhm-rentiva')` - Plural forms
- `_x('Text', 'context', 'mhm-rentiva')` - Context-aware translations
- `esc_html__('Text', 'mhm-rentiva')` - Escape and translate
- `esc_html_e('Text', 'mhm-rentiva')` - Escape, translate, and echo

**Examples:**

```php
// ✅ Correct
echo __('Vehicle not found', 'mhm-rentiva');
$message = __('Booking confirmed', 'mhm-rentiva');

// ❌ Wrong - Hardcoded string
echo 'Vehicle not found';

// ❌ Wrong - Variable text domain
__('Text', $this->domain);

// ❌ Wrong - Constant text domain
__('Text', MHM_RENTIVA_DOMAIN);
```

### 2. Variable Management

**Mandate:** Never use PHP variables directly inside translation functions.

**Violation:**
```php
__("Hello $name", 'mhm-rentiva')  // ❌ Wrong
```

**Correction:**
```php
sprintf(__('Hello %s', 'mhm-rentiva'), $name)  // ✅ Correct
```

**Common Patterns:**

```php
// Single variable
sprintf(__('Welcome, %s', 'mhm-rentiva'), $username)

// Multiple variables
sprintf(__('Vehicle %s booked from %s to %s', 'mhm-rentiva'), $vehicle_name, $start_date, $end_date)

// With HTML
sprintf(__('Click <a href="%s">here</a> to view', 'mhm-rentiva'), esc_url($link))

// Plural forms with variables
_n(
    sprintf(__('%d vehicle found', 'mhm-rentiva'), $count),
    sprintf(__('%d vehicles found', 'mhm-rentiva'), $count),
    $count,
    'mhm-rentiva'
)
```

### 3. POT File Generation

**Mandate:** Ensure the `.pot` file is always up-to-date with the latest code.

**Command:**
```bash
wp i18n make-pot . languages/mhm-rentiva.pot
```

**When to Generate:**
- After adding new strings to the codebase
- Before committing translation-related changes
- When preparing for a new release
- When user requests POT file update

**Verification:**
After generation, verify the POT file contains all new strings and check for any warnings or errors.

### 4. WordPress.org Compliance

**Requirements:**
- All user-facing strings must be translatable
- Text domain must be consistent (`'mhm-rentiva'`)
- No hardcoded strings in templates or output
- Proper escaping when outputting translated strings

**Common Violations to Fix:**

```php
// ❌ Hardcoded in templates
<h1>Vehicle Details</h1>

// ✅ Correct
<h1><?php echo esc_html__('Vehicle Details', 'mhm-rentiva'); ?></h1>

// ❌ Hardcoded in JavaScript
alert('Booking saved');

// ✅ Correct (pass from PHP)
wp_localize_script('script-handle', 'mhmRentiva', [
    'bookingSaved' => __('Booking saved', 'mhm-rentiva')
]);
```

## Workflow

### Adding New Strings

1. **Identify user-facing text** - Any text visible to end users
2. **Choose appropriate function** - `__()` for return, `_e()` for echo
3. **Add text domain** - Always `'mhm-rentiva'`
4. **Handle variables** - Use `sprintf()` if needed
5. **Escape output** - Use `esc_html__()` or `esc_attr__()` when appropriate

### Code Review Checklist

When reviewing code for i18n compliance:

- [ ] All user-facing strings are wrapped in translation functions
- [ ] Text domain is `'mhm-rentiva'` (literal string, not variable)
- [ ] Variables are handled with `sprintf()` or similar
- [ ] Output is properly escaped
- [ ] No hardcoded strings in templates
- [ ] JavaScript strings are localized via `wp_localize_script()`

### POT File Update Workflow

1. Run `wp i18n make-pot . languages/mhm-rentiva.pot`
2. Review generated file for completeness
3. Check for any warnings or errors
4. Commit updated POT file with code changes

## Best Practices

### Escaping

Always escape translated strings when outputting:

```php
// HTML output
echo esc_html__('Text', 'mhm-rentiva');

// HTML attributes
echo esc_attr__('Text', 'mhm-rentiva');

// URLs
echo esc_url($url);

// Already escaped in sprintf
sprintf(esc_html__('Hello %s', 'mhm-rentiva'), esc_html($name))
```

### Context-Aware Translations

Use `_x()` when the same string needs different translations based on context:

```php
// Different contexts, same string
_x('Post', 'verb', 'mhm-rentiva');  // "to post"
_x('Post', 'noun', 'mhm-rentiva');  // "a post"
```

### Plural Forms

Use `_n()` for strings that change based on count:

```php
_n(
    __('%d vehicle', 'mhm-rentiva'),
    __('%d vehicles', 'mhm-rentiva'),
    $count,
    'mhm-rentiva'
)
```

## Anti-Patterns

**Never:**
- Use variables for text domain: `__('Text', $domain)`
- Use constants for text domain: `__('Text', CONST_DOMAIN)`
- Put variables inside strings: `__("Hello $name", 'mhm-rentiva')`
- Hardcode strings in templates
- Forget to escape output
- Mix text domains (always use `'mhm-rentiva'`)
