---
description: Deterministic end-to-end workflow for auditing, optimizing, and verifying the MHM Rentiva codebase under strict project rules.
---

# mhm-rentiva-execution.md

Project-specific logic and naming conventions for MHM Rentiva.

# PROJECT: MHM Rentiva Execution Workflow

## PHASE 0: PROJECT IDENTITY INITIALIZATION
- **Prefixing:** Apply `mhm_rentiva_` (snake_case) for functions/hooks and `MHMRentiva` (PascalCase) for Namespaces/Classes.
- **Text Domain:** Use `'mhm-rentiva'` as the mandatory text domain literal.
- **Context Loading:** Load additional project business logic from `mhm-rentiva-rulles.md` and `PROJECT_MEMORIES.md`.

## PHASE 1: IMPLEMENTATION & HOOKS

### 1.1 Hook Registration
- Register hooks in `Plugin::__construct()` or dedicated `register()` methods.
- Use static methods for hook callbacks when possible.
- Document hook priority reasoning in comments.
- **Hook Naming:** Custom actions/filters must follow the `mhm_rentiva_action_name` format.

### 1.2 AJAX Handler Implementation
- Verify nonce: `wp_verify_nonce()`
- Check capability: `current_user_can('manage_options')`
- Sanitize inputs: `sanitize_text_field()`, `absint()`, etc.
- Validate data: Use `SecurityHelper::validate_*()` methods
- Process request
- Send JSON response: `wp_send_json_success()` or `wp_send_json_error()`

### 1.3 Form Handler Implementation
- Check form submission: `isset($_POST['submit'])`
- Verify nonce: `wp_verify_nonce()`
- Check capability: `current_user_can('manage_options')`
- Sanitize inputs
- Validate data
- Process form
- Redirect with message: `wp_safe_redirect()`

### 1.4 Module Specifics
- **Booking Module:** Use `Util::has_overlap()` for conflict detection
- **Vehicle Management:** Use `VehicleDataHelper` for data operations
- **Transfer Module:** Use `rentiva_` prefix for all transfer-related functions
- **Payment Module:** Route all frontend payments through `WooCommerceBridge`

## PHASE 2: CODE REVIEW CRITERIA

### 2.1 Security Review
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] All nonces verified
- [ ] All capabilities checked
- [ ] All SQL queries use prepare()

### 2.2 Standards Review
- [ ] ABSPATH check present
- [ ] Strict types declared
- [ ] Proper prefixing used (`mhm_rentiva_` / `MHMRentiva`)
- [ ] Text domain correct (`'mhm-rentiva'`)
- [ ] No extract() usage

### 2.3 Architecture Review
- [ ] PSR-4 autoloading followed
- [ ] Dependency injection used
- [ ] Singleton pattern used correctly
- [ ] No global variables
- [ ] Proper error handling

## PHASE 3: TESTING & VALIDATION

### 3.1 Security Testing
- Test nonce verification bypass attempts
- Test SQL injection with malicious inputs
- Test XSS with script tags in inputs
- Test CSRF with forged requests

### 3.2 Functional Testing
- Test all AJAX endpoints
- Test all form submissions
- Test all shortcodes
- Test all REST API endpoints

### 3.3 Performance Testing
- Test with 1000+ vehicles
- Test with 10000+ bookings
- Test concurrent booking attempts
- Test database query performance

## PHASE 4: PERSISTENCE & LOGGING
- **Memory Keeper:** Document all architectural decisions and changes in `PROJECT_MEMORIES.md`.
- **Version Control:** Ensure version numbers are synchronized across `readme.txt`, `mhm-rentiva.php`, and `changelog.json`.
- **Changelog:** Update both `changelog.json` (EN) and `changelog-tr.json` (TR).

## PHASE 5: DEPLOYMENT & RELEASE

### 5.1 Pre-Release Checklist
- [ ] All tests passing
- [ ] Version numbers synchronized
- [ ] Changelog updated (both EN and TR)
- [ ] README updated
- [ ] PROJECT_MEMORIES.md updated
- [ ] Database migrations tested
- [ ] Backward compatibility verified

### 5.2 Release Process
- Tag version in Git
- Build production assets (minified CSS/JS)
- Create release package
- Upload to WordPress.org (if applicable)
- Deploy to production server
- Monitor error logs

## PHASE 6: MONITORING & ROLLBACK

### 6.1 Post-Deployment Monitoring
- Monitor error logs for 24 hours
- Check database migration success
- Verify critical features working
- Monitor performance metrics

### 6.2 Rollback Procedure
- Deactivate plugin
- Restore database backup
- Restore previous plugin version
- Reactivate plugin
- Investigate issue
