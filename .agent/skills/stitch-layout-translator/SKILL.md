---
name: stitch-layout-translator
description: Analyzes HTML/CSS output from Google Stitch and translates it into WordPress-aware integration blueprints for MHM Rentiva. Identifies i18n risks, escaping requirements, asset handling needs, and prefix compliance. Use when converting Stitch designs to WordPress templates, analyzing layout compatibility, or when user mentions Stitch, design-to-code, or layout analysis.
metadata:
  category: design
  project: mhm-rentiva
  version: 1.0.0
  author: MHM Development Team
  requires:
    - mhm-architect
    - mhm-security-guard
    - wp-development.md
    - mhm-rentiva-rulles.md
---

# Stitch Layout Translator

**Role:** The Design-to-WordPress Bridge.  
**Motto:** "Reduce ambiguity, not automate integration."

## When to Use This Skill

Apply this skill when:
- User provides HTML/CSS output from Google Stitch (https://stitch.withgoogle.com)
- User asks to analyze a design layout for WordPress compatibility
- User mentions "Stitch", "design-to-code", or "layout analysis"
- Before creating new templates or pages from external designs
- During design-to-development transitions
- As input to the `mhm-architect` skill for implementation planning

## Core Capabilities

### 1. Structural Decomposition

Identify high-level layout regions and components:

**Layout Regions:**
- Header
- Main content
- Sidebar (if applicable)
- Footer

**Component Classification:**
- **Reusable Components:** Can be used across multiple pages
- **Page-Specific Components:** Unique to a single page
- **WordPress Native:** Can use existing WordPress functions/templates

**Semantic Intent Translation:**
- Translate non-semantic `<div>` structures into semantic HTML5 elements
- Identify potential WordPress template parts
- Suggest component hierarchy

### 2. CSS Analysis & Risk Assessment

Analyze CSS for WordPress compatibility:

**Style Categories:**
- **Inline Styles:** Flag for extraction to external CSS
- **Global Selectors:** Flag for collision risks
- **Component-Scoped Styles:** Safe for integration

**Risk Detection:**
- Selector collisions with WordPress core or theme
- Overly generic selectors (e.g., `.button`, `.container`)
- Style leakage risks
- Missing responsive breakpoints

**WordPress Best Practices Check:**
- CSS variables usage (should use `--mhm-*` or `--wp--preset--*`)
- Responsive breakpoints (should use 782px, 600px)
- Accessibility (color contrast, focus states)

### 3. WordPress Compatibility Mapping

Analyze against MHM Rentiva integration standards:

**i18n Requirements:**
- Detect hardcoded user-facing strings
- Flag strings requiring `__()` or `_e()` wrapping
- Identify text domain requirements (`'mhm-rentiva'`)

**Escaping Requirements:**
- Identify unescaped output
- Flag areas requiring `esc_html()`, `esc_attr()`, `esc_url()`
- Detect potential XSS vulnerabilities

**Asset Handling:**
- Identify CSS files requiring enqueue
- Identify JavaScript files requiring enqueue
- Identify images requiring proper path handling
- Flag inline scripts/styles for extraction

**Security Concerns:**
- Inline scripts (should use `wp_add_inline_script()`)
- Inline styles (should use `wp_add_inline_style()`)
- External resources (should be enqueued properly)

### 4. Integration Readiness Assessment

Produce clear assessment of integration readiness:

**Categories:**
- ✅ **Ready for Integration:** Components safe for direct template conversion
- ⚠️ **Requires Adjustment:** Components needing minor modifications
- ❌ **Not Suitable:** Components requiring major redesign

---

## MHM RENTIVA INTEGRATION RULES

When analyzing Stitch output for MHM Rentiva:

### 1. Prefix Requirements

**CSS Classes:**
- MUST be prefixed with `mhm-` or `rv-`
- Example: `.mhm-vehicle-card`, `.rv-booking-form`

**JavaScript Variables:**
- MUST be prefixed with `mhmRentiva` or `rentiva`
- Example: `mhmRentivaVehicles`, `rentivaBooking`

**HTML IDs:**
- MUST be prefixed with `mhm-rentiva-`
- Example: `#mhm-rentiva-search-form`

### 2. Asset Management

**CSS Files:**
- MUST be placed in `assets/css/frontend/` (frontend) or `assets/css/admin/` (admin)
- MUST be enqueued via `AssetManager::enqueue_style()`
- MUST use versioning: `MHM_RENTIVA_VERSION`

**JavaScript Files:**
- MUST be placed in `assets/js/frontend/` (frontend) or `assets/js/admin/` (admin)
- MUST be enqueued via `AssetManager::enqueue_script()`
- MUST declare dependencies (jQuery, etc.)

**Images:**
- MUST be placed in `assets/images/`
- MUST use `MHM_RENTIVA_PLUGIN_URL . 'assets/images/...'` for paths

### 3. Template Structure

**Template Files:**
- MUST be placed in `templates/` directory
- MUST start with ABSPATH check: `if (!defined('ABSPATH')) { exit; }`
- MUST use WordPress template functions when applicable

**Template Hierarchy:**
```
templates/
├── admin/           # Admin templates
├── shortcodes/      # Shortcode templates
├── emails/          # Email templates
└── partials/        # Reusable template parts
```

### 4. Security Requirements

**i18n (Internationalization):**
- All user-facing strings MUST be wrapped in `__()` or `_e()`
- Text domain MUST be `'mhm-rentiva'` (string literal, not variable)
- Example: `<?php echo esc_html__('Vehicle Details', 'mhm-rentiva'); ?>`

**Escaping (Output Security):**
- HTML content: `esc_html()`
- HTML attributes: `esc_attr()`
- URLs: `esc_url()`
- JavaScript: `wp_json_encode()`

**Inline Styles/Scripts:**
- Inline styles MUST use `wp_add_inline_style()` or be moved to external CSS
- Inline scripts MUST use `wp_add_inline_script()` or be moved to external JS
- NO direct `<style>` or `<script>` tags in templates

### 5. CSS Architecture

**CSS Variables:**
- MUST use variables from `assets/css/core/css-variables.css`
- Primary color: `var(--mhm-primary-color)`
- Secondary color: `var(--mhm-secondary-color)`
- Text color: `var(--mhm-text-color)`
- Background: `var(--mhm-bg-color)`

**WordPress Site Editor (FSE) Compatibility:**
- MUST respect `--wp--preset--color--*` variables
- MUST support `.has-*-color` classes
- MUST support `.has-*-background-color` classes

**Responsive Breakpoints:**
- Tablet: `@media (max-width: 782px)`
- Mobile: `@media (max-width: 600px)`
- Use `var(--mhm-breakpoint-md)` and `var(--mhm-breakpoint-sm)`

**Accessibility:**
- Color contrast MUST meet WCAG AA standards
- Focus states MUST be visible
- Touch targets MUST be minimum 44px

### 6. Component Naming Conventions

**BEM Methodology:**
- Block: `.mhm-vehicle-card`
- Element: `.mhm-vehicle-card__title`
- Modifier: `.mhm-vehicle-card--featured`

**WordPress Classes:**
- Respect WordPress core classes (`.wp-block-*`, `.has-*-color`)
- Use WordPress utility classes when available

---

## WORKFLOW INTEGRATION

### When to Use

- User provides Stitch HTML/CSS output
- User asks to analyze a design layout
- User mentions "Stitch", "design-to-code", or "layout analysis"
- Before creating new templates or pages from external designs

### Workflow Steps

1. **Receive Input:** Get HTML/CSS from user (and optional screenshot)
2. **Analyze Structure:** Decompose layout and identify components
3. **Assess Compatibility:** Check against MHM Rentiva standards
4. **Generate Report:** Produce integration blueprint (see Output Format)
5. **Hand Off:** Pass blueprint to `mhm-architect` or Code mode for implementation

### Supporting Skills

- **Primary:** [`mhm-architect`](.kilocode/skills/mhm-architect/SKILL.md) - For implementation planning
- **Secondary:** [`mhm-security-guard`](.kilocode/skills/mhm-security-guard/SKILL.md) - For security review
- **Tertiary:** [`mhm-doc-writer`](.kilocode/skills/mhm-doc-writer/SKILL.md) - For documentation

---

## OUTPUT FORMAT

### Integration Blueprint Structure

```markdown
# Stitch Layout Analysis Report

## 1. Layout Overview
- **Page Type:** [Admin Page / Frontend Page / Widget / Shortcode]
- **Complexity:** [Simple / Medium / Complex]
- **Components:** [List of identified components]

## 2. Structural Breakdown

### Header Region
- **Elements:** [List]
- **WordPress Mapping:** [Suggested template parts]

### Main Content
- **Sections:** [List]
- **WordPress Mapping:** [Suggested template structure]

### Footer Region
- **Elements:** [List]
- **WordPress Mapping:** [Suggested template parts]

## 3. CSS Risk Assessment

### Global Styles
- **Selectors:** [List of global selectors]
- **Risk Level:** [Low / Medium / High]
- **Recommendation:** [Action needed]

### Component Styles
- **Scoped Selectors:** [List]
- **Risk Level:** [Low / Medium / High]
- **Recommendation:** [Action needed]

### Inline Styles
- **Count:** [Number]
- **Risk Level:** [Low / Medium / High]
- **Recommendation:** [Move to external CSS / Use wp_add_inline_style()]

## 4. WordPress Integration Notes

### i18n Requirements
- **Hardcoded Strings:** [List with line numbers]
- **Action:** Wrap in `__()` or `_e()` with text domain `'mhm-rentiva'`

### Escaping Requirements
- **Unescaped Output:** [List with line numbers]
- **Action:** Add `esc_html()`, `esc_attr()`, `esc_url()`, etc.

### Asset Handling
- **CSS Files:** [List]
- **JS Files:** [List]
- **Images:** [List]
- **Action:** Enqueue via AssetManager

### Security Concerns
- **Inline Scripts:** [List]
- **Inline Styles:** [List]
- **Action:** Move to external files or use wp_add_inline_script()

### Prefix Compliance
- **CSS Classes:** [List of classes needing mhm- or rv- prefix]
- **JavaScript Variables:** [List of variables needing mhmRentiva or rentiva prefix]
- **HTML IDs:** [List of IDs needing mhm-rentiva- prefix]

## 5. Integration Readiness Summary

### Ready for Integration ✅
- [List of components/sections]

### Requires Adjustment ⚠️
- [List of components/sections with required changes]

### Not Suitable Without Redesign ❌
- [List of components/sections with major issues]

## 6. Recommended Next Steps

1. [Step 1]
2. [Step 2]
3. [Step 3]

## 7. Estimated Integration Effort

- **Time:** [Hours/Days]
- **Complexity:** [Low / Medium / High]
- **Risk:** [Low / Medium / High]
```

---

## VISUAL REFERENCE HANDLING (NON-AUTHORITATIVE)

If a visual reference (screenshot) is provided:

- Treat it as **secondary context only**
- Use it solely to understand layout intent, hierarchy, and component roles
- DO NOT infer missing HTML or CSS from the image
- DO NOT generate or modify code based on the image
- In case of conflict, **code always overrides visual reference**

The visual reference must never be treated as a source of truth.

---

## EXPLICIT LIMITATIONS (CRITICAL)

This skill MUST NOT:

- Generate HTML, CSS, PHP, or JavaScript code
- Modify any files
- Perform auto-fixing or refactoring
- Enqueue assets
- Create templates
- Make architectural or business logic decisions
- Override rules defined in `wp-development.md` or `mhm-rentiva-rulles.md`

This skill provides guidance only.

---

## FAILURE CONDITIONS

This skill MUST STOP and report failure if:

- Required HTML or CSS input is missing
- Inputs are incomplete or corrupted
- A request implies direct code generation or integration
- User asks to modify files directly

In such cases, the skill must clearly report why it cannot proceed and suggest appropriate next steps.

---

## USAGE EXAMPLES

### Example 1: Basic Analysis

**User Input:**
```
"Stitch'ten aldığım bu HTML/CSS çıktısını analiz et ve WordPress entegrasyonu için blueprint hazırla"
```

**Skill Action:**
1. Analyze provided HTML/CSS
2. Generate integration blueprint
3. Hand off to `mhm-architect` for implementation

### Example 2: Security-Focused Analysis

**User Input:**
```
"Bu Stitch layout'unu güvenlik açısından değerlendir"
```

**Skill Action:**
1. Analyze HTML/CSS for security risks
2. Identify i18n and escaping requirements
3. Hand off to `mhm-security-guard` for detailed security review

### Example 3: Performance-Focused Analysis

**User Input:**
```
"Bu layout'un performans etkisini değerlendir"
```

**Skill Action:**
1. Analyze CSS complexity and asset size
2. Identify optimization opportunities
3. Hand off to `mhm-performance-auditor` for detailed performance review

---

## INTEGRATION CHECKLIST

When analyzing Stitch output, verify:

- [ ] All CSS classes use `mhm-` or `rv-` prefix
- [ ] All JavaScript variables use `mhmRentiva` or `rentiva` prefix
- [ ] All HTML IDs use `mhm-rentiva-` prefix
- [ ] All user-facing strings are flagged for i18n
- [ ] All output is flagged for escaping
- [ ] All assets are identified for proper enqueue
- [ ] All inline styles/scripts are flagged for extraction
- [ ] Responsive breakpoints use 782px and 600px
- [ ] CSS variables from `css-variables.css` are recommended
- [ ] Accessibility requirements are noted

---

## FINAL NOTE

This skill exists to **reduce ambiguity**, not to automate integration.

Its value lies in:
- **Accuracy:** Precise analysis of design output
- **Discipline:** Strict adherence to MHM Rentiva standards
- **Predictability:** Consistent output format

All downstream decisions remain the responsibility of higher-level agents and workflows (`mhm-architect`, Code mode, etc.).
