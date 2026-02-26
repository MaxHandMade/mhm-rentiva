# MHM Rentiva - Skill Registry (v5.2 Normalized)

This document defines the authoritative skill registry for the MHM Rentiva project.

It is normalized for the v5 Chief Engineer orchestration model, meaning:
- Skills are not autonomous agents.
- Skills are capabilities invoked by the Chief Engineer.
- Skills are grouped by responsibility.
- Rules and workflows are not duplicated here.

Registry metadata:
- Registry version: `v5.2`
- Last synchronized: `2026-02-20`
- Source of truth: `SKILLS.md` in orchestration package

---

## 0. Meta Rules (Binding)

- Skills do not make decisions; they execute within a task scope.
- Skills cannot override `PROJECT_MEMORIES.md` or `RULES.md`.
- Skills must be invoked through an Agent Role (Architecture, Implementation, QA, and related roles).
- Skills should run inside an explicit workflow context.
- For micro tasks, a minimal workflow stub is acceptable.
- CLI, PHPUnit, and MCP validation are enforced outside this document by orchestration rules.

---

## 1. Architecture and Design Skills

### mhm-architect

Responsibility: WordPress-native architecture and feature design.

Used by: Architecture and Analysis Agent.

Scope:
- Feature architecture design
- Database schema planning
- Hook-based system design
- PSR-4 structure decisions

Explicitly not responsible for:
- Writing final production code
- Running tests or CLI commands

---

## 2. Data and Performance Skills

### mhm-db-master

Responsibility: Secure and performant database operations.

Used by: Implementation Agent.

Scope:
- Custom table design and migration
- Query optimization
- Data integrity enforcement

---

### mhm-performance-auditor

Responsibility: Runtime and query optimization.

Used by: QA and Performance Agent.

Scope:
- Query Monitor analysis
- Caching strategies
- Asset loading audits

---

## 3. Security and Compliance Skills

### mhm-security-guard

Responsibility: Security enforcement and audit.

Used by: QA and Security Agent.

Scope:
- Nonce validation
- Sanitization and escaping audits
- WordPress.org compliance checks

---

## 4. CLI, Testing, and Validation Skills

### mhm-cli-operator

Responsibility: WordPress CLI and environment operations.

Used by: QA and Testing Agent.

Scope:
- WP-CLI execution
- Scaffolding
- Plugin Check runs

---

### mhm-test-architect

Responsibility: PHPUnit test design and coverage.

Used by: QA and Testing Agent.

Scope:
- Unit and integration test design
- Regression prevention
- Test coverage enforcement

---

### webapp-testing

Responsibility: Browser-level automated testing.

Used by: QA and Testing Agent.

Scope:
- Playwright-based UI tests
- End-to-end scenario validation

---

## 5. Versioning, Release, and Git Skills

### mhm-git-ops

Responsibility: Git operations and commit hygiene.

Used by: Release Agent.

Scope:
- Commit creation
- Branch management
- Repository synchronization

---

### create-pull-request

Responsibility: Pull request creation via gh CLI.

Used by: Release Agent.

Scope:
- PR generation
- Template compliance
- Reviewer preparation

---

### mhm-release-manager

Responsibility: Release readiness and pre-flight checks.

Used by: Release Agent.

Scope:
- Version sync
- Plugin Check validation
- Asset and readme verification

---

### changelog-generator

Responsibility: User-facing changelog generation.

Used by: Release Agent.

Scope:
- Commit analysis
- Release note generation
- Customer-friendly summaries

---

## 6. Documentation and Localization Skills

### mhm-doc-writer

Responsibility: Project documentation maintenance.

Used by: Documentation Agent.

Scope:
- Docusaurus docs
- Feature documentation
- Shortcode documentation

---

### mhm-polyglot

Responsibility: Internationalization (i18n).

Used by: Implementation and QA Agents.

Scope:
- Translation function enforcement
- POT and PO generation
- Text domain validation

---

### mhm-translator

Responsibility: Translation file generation.

Used by: QA and Localization Agent.

Scope:
- PO and MO file generation
- Language pack maintenance

---

## 7. Memory and Continuity Skills

### mhm-memory-keeper

Responsibility: Project memory management.

Used by: Review and Memory Agent.

Scope:
- `PROJECT_MEMORIES.md` updates
- Decision logging
- Redundancy prevention

Invocation rule:
- Mandatory after architectural decisions.
- Mandatory after bug root-cause resolution.

---

## 8. Auxiliary and External Skills

### stitch-layout-translator

Responsibility: Design-to-WordPress translation.

Used by: Architecture Agent.

Scope:
- Google Stitch to WordPress blueprint translation

---

### file-organizer

Responsibility: Local file structure organization.

Used by: Tooling Agent (optional).

Scope:
- File cleanup
- Directory normalization

---

### web-design-guidelines

Responsibility: UI and UX consistency rules.

Used by: Architecture and Review Agents.

Scope:
- Layout consistency
- Design constraint validation

---

## 9. Orchestration Control Skill

### mhm-skills-hub

Responsibility: Skill resolution helper.

Used by: Chief Engineer only.

Scope:
- Maps task requirements to skills
- Does not execute work
- Does not make architectural decisions

---

## 10. Explicit Exclusions

This document intentionally does not define:
- Rules
- Workflows
- MCP usage policies

These are defined in:
- `RULES.md`
- `WORKFLOWS.md`
- `MASTER_ORCHESTRATION_PROMPT.md`

---

## Final Note

This skill registry is stable, bounded, and non-autonomous.

All power flows through the Chief Engineer orchestration layer.
