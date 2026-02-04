# SKILL DEFINITION
## stitch-layout-translator

---

## SKILL IDENTITY

**Name:** stitch-layout-translator  
**Type:** READ + ANALYZE + TRANSFORM (Non-Mutating)  
**Scope:** Design-to-Integration Translation  
**Authority Level:** NON-AUTHORITATIVE (Advisory Only)

This skill is designed to analyze HTML and CSS output generated via https://stitch.withgoogle.com and translate it into a structured, WordPress-aware integration blueprint.

This skill does NOT perform code integration or modification.

---

## PRIMARY PURPOSE

To bridge the gap between visual design output (Stitch) and WordPress project standards by:

- Understanding layout intent
- Decomposing structure and styles
- Identifying integration risks
- Producing integration-ready guidance for downstream agents

---

## INPUTS

This skill may receive one or more of the following inputs:

### Required
- Raw HTML output from Stitch
- Associated CSS files or inline CSS blocks

### Optional (Secondary Context)
- Visual reference (screenshot) of the rendered page

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

## SKILL CAPABILITIES

### 1. Structural Decomposition

- Identify high-level layout regions:
  - Header
  - Main content
  - Footer
- Detect logical sections and components
- Classify components as:
  - Reusable
  - Page-specific
- Translate non-semantic `<div>` structures into semantic intent (without rewriting code)

---

### 2. CSS Analysis & Normalization Assessment

- Identify:
  - Inline styles
  - Global selectors
  - Component-scoped styles
- Detect potential issues:
  - Selector collisions
  - Overly generic selectors
  - Style leakage risks
- Flag CSS patterns that are incompatible with WordPress best practices

This skill does NOT rewrite or optimize CSS.

---

### 3. WordPress Compatibility Mapping

Analyze the extracted structure against WordPress integration standards:

- Detect hardcoded user-facing strings (i18n risks)
- Identify areas requiring escaping (`esc_html`, `esc_attr`, etc.)
- Identify assets requiring proper enqueue mechanisms
- Flag inline scripts or styles as integration risks

No PHP code is generated.

---

### 4. Integration Readiness Assessment

Produce a clear assessment of integration readiness, including:

- Components safe for template conversion
- Components requiring refactor before integration
- CSS that must be scoped or modularized
- Areas that violate or may violate project rules

---

## OUTPUT CONTRACT

This skill MUST output a structured analysis report containing:

### A) Layout Breakdown
- Page regions
- Section list
- Component classification

### B) CSS Risk Report
- Global vs local styles
- Collision risks
- Inline style warnings

### C) WordPress Integration Notes
- i18n risks
- Escaping requirements
- Asset handling notes

### D) Integration Readiness Summary
- ✅ Ready for integration
- ⚠️ Requires adjustment
- ❌ Not suitable without redesign

The output MUST be descriptive and explanatory only.

---

## EXPLICIT LIMITATIONS (CRITICAL)

This skill MUST NOT:

- Generate HTML, CSS, PHP, or JavaScript code
- Modify any files
- Perform auto-fixing or refactoring
- Enqueue assets
- Create templates
- Make architectural or business logic decisions
- Override rules defined in `rules.md`

This skill provides guidance only.

---

## WORKFLOW USAGE RULES

This skill is intended to be used:

- Before adding new pages or layouts
- During design-to-development transitions
- As an input to the Instruction Architect Agent

This skill MUST NOT be used:

- Inside `audit-code`
- Inside `full-optimize`
- As a replacement for code review or audit workflows

---

## FAILURE CONDITIONS

This skill MUST STOP and report failure if:

- Required HTML or CSS input is missing
- Inputs are incomplete or corrupted
- A request implies direct code generation or integration

In such cases, the skill must clearly report why it cannot proceed.

---

## FINAL NOTE

This skill exists to **reduce ambiguity**, not to automate integration.

Its value lies in:
- Accuracy
- Discipline
- Predictability

All downstream decisions remain the responsibility of higher-level agents and workflows.
