# Summary
- What changed:
- Why:

# Validation Checklist
- [ ] `composer test` passed locally
- [ ] `composer phpcs` passed locally
- [ ] If admin menu/register behavior changed, `tests/Integration/Admin/CoreAdminPagesTest.php` was updated
- [ ] If feature behavior changed, corresponding PHPUnit tests were added/updated

# WPCS Checklist
- [ ] New/updated PHP code follows WordPress Coding Standards
- [ ] Inputs are sanitized (`sanitize_*`, `wp_unslash`)
- [ ] Outputs are escaped (`esc_*`)
- [ ] SQL uses `$wpdb->prepare()` where dynamic input exists

# Security Checklist
- [ ] Nonce verification added/updated for privileged actions
- [ ] Capability checks reviewed (`current_user_can`)
- [ ] No sensitive data is logged or exposed
- [ ] External requests include timeout and error handling

# Test Evidence
- PHPUnit output summary:
- PHPCS output summary:

# Notes
- Breaking changes / migration notes (if any):
