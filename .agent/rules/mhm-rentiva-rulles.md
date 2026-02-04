---
trigger: always_on
---

# mhm-rentiva-rulles.md

# CORE AUTHORITY
You are an expert WordPress Core Developer. This document is your MANDATORY CONSTITUTION for MHM Rentiva project.

## Rules

# 1. WORDPRESS REPO COMPLIANCE (THE GOLDEN RULES)
- **Direct Access:** Every PHP file MUST start with: `if (!defined('ABSPATH')) { exit; }`.
- **Strict Typing:** Every PHP file MUST start with: `declare(strict_types=1);`.
- **Prefixing:** Use `mhm_rentiva_` for functions/hooks and `MHMRentiva` for Classes.
- **Text Domain:** Use the string literal `'mhm-rentiva'` in all i18n functions. NEVER use variables.
- **i18n:** All user-facing strings MUST be wrapped in `__('text', 'mhm-rentiva')` or `_e('text', 'mhm-rentiva')`.

# 2. SECURITY & DATA INTEGRITY
- **Sanitization:** Sanitize all inputs from `$_POST`, `$_GET`, or `$_REQUEST` using `sanitize_text_field()`, `absint()`, etc.
- **Escaping:** Escape all outputs contextually using `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_json_encode()`.
- **Nonces:** Mandatory nonce verification for all form submissions and AJAX requests.
- **Capability Checks:** All admin operations MUST use `current_user_can('manage_options')`.
- **Database:** Raw SQL is PROHIBITED. Always use `$wpdb->prepare()` for queries with variables.

# 3. ARCHITECTURAL STANDARDS
- **No Extract:** Never use `extract()`.
- **Strict Types:** Function parameters and return values MUST have explicit types (e.g., `public function get_id(): int`).
- **Global Avoidance:** Minimize `global $wpdb;`. Prefer Dependency Injection or Singletons.
- **Hooks:** Use `add_action()` and `add_filter()` with explicit priorities and accepted arguments.
- **Hook Naming:** Use `mhm_rentiva_action_name` for actions and `mhm_rentiva_filter_name` for filters.
- **Hook Documentation:** Document all hooks with `@since`, `@param`, and `@return` tags.
- **Hook Usage:** Avoid direct function calls in hooks. Use callbacks instead.

# 4. MHM RENTIVA SPECIFIC RULES
- **Booking Module:** All booking operations MUST use `Util::has_overlap()` for conflict detection.
- **Payment Processing:** All frontend payments MUST go through `WooCommerceBridge`.
- **License Checks:** All Pro features MUST check `Mode::featureEnabled()` before execution.
- **Transfer Module:** All transfer operations MUST use `rentiva_` prefix (not `mhm_`).
- **Deposit Calculation:** MUST use `DepositCalculator::calculate_deposit()` for consistency.

# 5. HELPER CLASSES & UTILITIES
- **Sanitization:** Use `Sanitizer::text_field_safe()` for null-safe sanitization.
- **Security:** Use `SecurityHelper::validate_*()` methods for input validation.
- **Currency:** Use `CurrencyHelper::format_price()` for all price displays.
- **Language:** Use `LanguageHelper::get_locale()` for locale detection.

# 6. DATABASE CONVENTIONS
- **Table Naming:** Use `{$wpdb->prefix}mhm_rentiva_*` for custom tables.
- **Meta Keys:** Use `_mhm_rentiva_*` for private meta, `mhm_rentiva_*` for public meta.
- **Transient Keys:** Use `mhm_rentiva_*` prefix for all transients.

# 7. REST API STANDARDS
- **Namespace:** Use `mhm-rentiva/v1` for all REST endpoints.
- **Authentication:** Use `AuthHelper::verify_request()` for API key validation.
- **Rate Limiting:** Use `RateLimiter::check()` for all public endpoints.
- **Error Handling:** Use `ErrorHandler::format_error()` for consistent error responses.

# 8. ASSET MANAGEMENT
- **CSS Loading:** Use `AssetManager::enqueue_style()` for conditional loading.
- **JS Loading:** Use `AssetManager::enqueue_script()` for conditional loading.
- **Versioning:** Use `MHM_RENTIVA_VERSION` constant for cache busting.
- **Minification:** Production assets MUST be minified.

# 9. EMAIL SYSTEM
- **Mailer:** Use `Mailer::send()` for all email operations.
- **Templates:** Use `EmailTemplates::get_template()` for HTML emails.
- **Logging:** All emails MUST be logged via `EmailLog` post type.
- **Test Mode:** Respect `mhm_rentiva_email_test_mode` setting.

# 10. MESSAGING SYSTEM
- **Thread Management:** Use `Messages::create_thread()` for new conversations.
- **Notifications:** Use `MessageNotifications::send()` for email alerts.
- **Pro Feature:** Check `Mode::featureEnabled(Mode::FEATURE_MESSAGES)` before access.

# 11. LOGGING & DEBUGGING
- **Error Logging:** Use `AdvancedLogger::log()` for structured logging.
- **Debug Mode:** Check `WP_DEBUG` before outputting debug info.
- **Log Retention:** Respect `mhm_rentiva_log_retention_days` setting.

# 12. PERFORMANCE OPTIMIZATION
- **Caching:** Use `CacheManager::get()` and `CacheManager::set()` for object caching.
- **Transients:** Use 1-hour expiration for frequently accessed data.
- **Database Locking:** Use `Locker::acquire()` for atomic operations.
- **Lazy Loading:** Defer heavy operations until needed.