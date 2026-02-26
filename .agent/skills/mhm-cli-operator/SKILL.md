---
name: mhm-cli-operator
description: Uses WP-CLI and terminal commands for WordPress plugin management, compliance verification, code scaffolding, and test environment setup. Use when running plugin checks, generating code scaffolds, managing test data, verifying configurations, or performing WordPress CLI operations.
---

# MHM CLI Operator

**Role:** The Command Line Commander of WordPress.  
**Motto:** "Automate via terminal, verify via code."

## When to Use This Skill

Apply this skill when:
- Running WordPress plugin compliance checks
- Generating code scaffolds using WP-CLI
- Managing test environments and data
- Verifying WordPress configuration
- Performing database operations via CLI
- Generating translation files
- Creating test data for development

## Core Responsibilities

### 1. Plugin Verification via CLI

**Mandate:** Run compliance checks without leaving the terminal.

**Command:** `wp plugin check .` (Requires Plugin Check CLI)

**Action:** 
- Parse output for Errors/Warnings
- Report issues immediately
- Focus on WordPress.org compliance violations

**Example:**
```bash
wp plugin check .
# Parse output for:
# - Errors (must fix)
# - Warnings (should fix)
# - Info (recommendations)
```

### 2. Scaffolding & Generation

**Mandate:** Use standard WP-CLI commands to generate code skeletons ensuring WordPress compliance.

**Available Commands:**
- `wp scaffold plugin` - Generate standardized plugin structure
- `wp scaffold post-type` - Register custom post types correctly
- `wp scaffold taxonomy` - Register custom taxonomies
- `wp i18n make-pot` - Generate translation template files
- `wp scaffold block` - Create Gutenberg block structure

**Usage Examples:**
```bash
# Generate translation file
wp i18n make-pot . languages/mhm-rentiva.pot --domain=mhm-rentiva

# Scaffold custom post type
wp scaffold post-type vehicle --label="Vehicle" --textdomain=mhm-rentiva

# Scaffold plugin structure
wp scaffold plugin new-plugin-name --plugin_name="New Plugin" --plugin_author="MHM"
```

### 3. Environment Management

**Mandate:** Manage test environments rapidly and efficiently.

**Database Operations:**
- Reset database: `wp db reset --yes`
- Export database: `wp db export backup.sql`
- Import database: `wp db import backup.sql`
- Query database: `wp db query "SELECT * FROM wp_posts LIMIT 5"`

**Data Generation:**
- Generate users: `wp user generate --count=10`
- Generate posts: `wp post generate --count=20 --post_type=vehicle`
- Generate terms: `wp term generate category --count=5`

**Configuration Verification:**
- List constants: `wp config list`
- Get option value: `wp option get siteurl`
- Set option value: `wp option set my_option value`

**Example Workflow:**
```bash
# Reset test environment
wp db reset --yes

# Generate test data
wp user generate --count=5 --role=subscriber
wp post generate --count=10 --post_type=vehicle

# Verify configuration
wp config list | grep WP_DEBUG
```

### 4. Translation File Management

**Mandate:** Generate and update translation files using WP-CLI i18n commands.

**Commands:**
```bash
# Generate .pot file
wp i18n make-pot . languages/mhm-rentiva.pot --domain=mhm-rentiva

# Update existing .po files
wp i18n update-po languages/mhm-rentiva.pot languages/mhm-rentiva-tr_TR.po

# Generate .mo files from .po files
wp i18n make-mo languages/
```

**Best Practices:**
- Always specify `--domain=mhm-rentiva` explicitly
- Run `make-pot` after code changes with new strings
- Verify all user-facing strings are extracted

### 5. Plugin Testing & Validation

**Mandate:** Use CLI tools to validate plugin functionality.

**Commands:**
```bash
# Check plugin status
wp plugin list

# Activate plugin
wp plugin activate mhm-rentiva

# Deactivate plugin
wp plugin deactivate mhm-rentiva

# Verify plugin is active
wp plugin is-active mhm-rentiva
```

## Common Workflows

### Complete Plugin Check Workflow

```bash
# 1. Run compliance check
wp plugin check . > plugin-check-report.txt

# 2. Parse for critical issues
grep -i "error" plugin-check-report.txt

# 3. Generate translation file
wp i18n make-pot . languages/mhm-rentiva.pot --domain=mhm-rentiva

# 4. Verify plugin is active
wp plugin is-active mhm-rentiva
```

### Test Environment Setup

```bash
# 1. Reset database
wp db reset --yes

# 2. Generate test users
wp user generate --count=10 --role=subscriber

# 3. Generate test vehicles
wp post generate --count=20 --post_type=vehicle

# 4. Verify data
wp post list --post_type=vehicle --format=count
```

## Output Parsing Guidelines

When parsing WP-CLI output:
- **Errors:** Lines containing "Error:" or "ERROR"
- **Warnings:** Lines containing "Warning:" or "WARNING"
- **Success:** Exit code 0 typically indicates success
- **Format:** Use `--format=json` for structured output when available

## Anti-Patterns to Avoid

1. **Manual file editing:** Use WP-CLI scaffolds instead of creating files manually
2. **GUI-only operations:** Prefer CLI commands for repeatable tasks
3. **Missing domain:** Always specify `--domain=mhm-rentiva` for i18n commands
4. **Unverified operations:** Always verify results after CLI operations
5. **Hardcoded paths:** Use relative paths (`.`) when possible

## Verification Checklist

Before completing CLI operations:
- [ ] Command executed successfully (exit code 0)
- [ ] Output parsed for errors/warnings
- [ ] Results verified (e.g., files created, data generated)
- [ ] Translation domain specified correctly
- [ ] Database operations confirmed (if applicable)
