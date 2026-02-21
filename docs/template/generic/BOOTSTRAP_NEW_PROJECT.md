# BOOTSTRAP NEW PROJECT

Applicability: plugin-only, theme-only, hybrid

This runbook explains how to initialize a new project from scratch using the Layout Engine kit.

## 1. Prerequisites
- PHP: 8.1+
- WordPress: 6.x+
- Composer: required
- WP-CLI: required for layout import and preview
- Local environment: XAMPP, LocalWP, Docker, or equivalent

## 2. Step-by-Step Initialization

### Step 1: Create Project Structure
```powershell
mkdir wp-content/plugins/{{PLUGIN_SLUG}}
cd wp-content/plugins/{{PLUGIN_SLUG}}
# Copy Layout Engine core and project preset files
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Configure Test Harness
Update `phpunit.xml.dist` and prepare the test database.

### Step 4: First Verification
```bash
vendor/bin/phpcs
vendor/bin/phpunit
```

### Step 5: Layout Preview
```bash
wp {{CLI_NAMESPACE}} layout import tests/fixtures/minimal.json --preview
```

## 3. Evidence Capture Standard (Mandatory)

```text
### [Bootstrap Verification Evidence]
- Command: `composer install`
  Result: `<pass|fail>`
- Command: `vendor/bin/phpcs`
  Result: `<pass|fail>`
- Command: `vendor/bin/phpunit`
  Result: `<pass|fail>`
- Command: `wp {{CLI_NAMESPACE}} layout import tests/fixtures/minimal.json --preview`
  Result: `<pass|fail>`
- Delta Queries: `<value>` (target: <= 0)
- Notes: `<optional>`
```

Bootstrap is not complete without evidence.

## 4. Common Failure Modes and Fixes
- WP-CLI missing: verify CLI availability and runtime guards.
- Composition error: verify component type in `ContractAllowlist`.
- Delta queries above limit: profile render path and remove extra queries.


