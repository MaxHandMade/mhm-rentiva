# MHM Rentiva – v5 Core Operational Specification

This document is a **composite core file** due to project file-count limitations.

It contains, in strict order of authority:
1. Project Rules
2. Standard Workflows
3. Skill Registry

---

## 0. Authority & Precedence (Binding)

The following order of authority is **strict and non-negotiable**:

1. **Project Rules**
2. **Standard Workflows**
3. **Skill Registry**

Interpretation rules:

- Rules override workflows and skills
- Workflows override skills
- Skills may never override rules or workflows
- In case of conflict, the higher authority always wins

This precedence order is enforced by the **Chief Engineer orchestration layer**.

# MHM Rentiva – Project Rules (v5 Normalized)

This document defines the **non-negotiable rules** of the MHM Rentiva project.

These rules are **binding** across all agents, skills, workflows, and tasks.
They are enforced by the **Chief Engineer orchestration layer**.

---

## 0. Rule Authority & Precedence

Order of authority (highest to lowest):

1. PROJECT_MEMORIES.md
2. This RULES.md
3. MASTER_ORCHESTRATION_PROMPT.md
4. WORKFLOWS.md
5. SKILLS.md

Lower-level artifacts **may not override** higher-level ones.

---

## 1. WordPress Core Compliance (Hard Rules)

* Every PHP file **must** include an `ABSPATH` guard.
* `declare(strict_types=1);` is mandatory for all PHP class files.
* All code must comply with **WordPress Coding Standards (WPCS)**.
* Direct execution logic outside hooks is forbidden.

---

## 2. Naming, Prefixing & Namespace Rules

* Global functions: `mhm_rentiva_` prefix is mandatory.
* Hooks (actions & filters): `mhm_rentiva_` prefix is mandatory.
* PHP namespaces must follow: `MHMRentiva\Domain\Feature`.
* Custom DB tables must use: `$wpdb->prefix . 'mhm_rentiva_'`.

Violations are treated as **release-blocking defects**.

---

## 3. Security Rules (Zero Tolerance)

* All inputs must be sanitized before storage.
* All outputs must be escaped before rendering.
* Nonce verification is mandatory for:

  * Forms
  * AJAX
  * State-changing actions
* Raw SQL with variables is forbidden; `$wpdb->prepare()` is mandatory.

Any security violation **fails the task immediately**.

---

## 4. Architecture Rules

* Hook-driven architecture is mandatory.
* Business logic must live in service classes, not templates.
* Templates must contain **presentation logic only**.
* Dependency Injection or controlled Singletons are allowed.
* Global state mutation is forbidden.

---

## 5. Performance Rules

* No N+1 queries.
* No `SELECT *` on large tables.
* Heavy operations must be cached (Transients or Object Cache).
* Assets must be conditionally loaded.

Performance regressions are **QA-fail conditions**.

---

## 6. Internationalization (i18n) Rules

* Every user-facing string must be translatable.
* Text domain is **always** `'mhm-rentiva'` (literal string).
* Escaped translation functions are preferred.
* Concatenation inside translation functions is forbidden.

---

## 7. Testing & Validation Rules

* Any functional change requires:

  * CLI verification
  * PHPUnit tests
* Tests must be updated when behavior changes.
* A task without test evidence is **incomplete**.

---

## 8. Memory & Continuity Rules

* Architectural decisions must be logged in PROJECT_MEMORIES.md.
* Bug fixes must include root-cause documentation.
* Repeating solved problems is a rule violation.

---

## 9. Release Rules

* Plugin Check must report **0 Errors**.
* Version numbers must be synchronized.
* Debug artifacts must be removed before release.

---

## Final Rule

If a task conflicts with any rule in this document:

> **The task must be redesigned, not justified.**

# MHM Rentiva – Standard Workflows (v5 Normalized)

This document defines **approved, repeatable workflows** for the MHM Rentiva project.

Workflows are **deterministic execution paths** invoked by the Chief Engineer.

---

## 0. Workflow Principles

* Workflows reduce ambiguity and improvisation.
* Skills are **called within workflows**, never standalone.
* Validation steps are mandatory, never optional.

---

## 1. audit → optimize → verify (Canonical)

**Purpose:** Improve code quality without regression.

**Agents Involved:**

* Architecture / Analysis Agent
* Implementation Agent
* QA & Testing Agent

**Steps:**

1. Audit code against RULES.md
2. Identify performance, security, or structure issues
3. Apply optimizations
4. Run CLI validation
5. Run PHPUnit tests
6. QA approval or failure

---

## 2. feature → design → implement → test

**Purpose:** Introduce new functionality safely.

**Steps:**

1. Architectural design (mhm-architect)
2. Memory check for conflicts
3. Implementation
4. CLI verification
5. PHPUnit tests
6. Documentation update

---

## 3. bug → analyze → fix → prevent

**Purpose:** Resolve defects permanently.

**Steps:**

1. Root-cause analysis
2. Fix implementation
3. Regression test creation
4. CLI + PHPUnit validation
5. Memory update (prevention note)

---

## 4. refactor → validate → stabilize

**Purpose:** Improve internal quality without behavior change.

**Steps:**

1. Identify refactor scope
2. Ensure no feature change
3. Apply refactor
4. Run full test suite
5. Performance check

---

## 5. release → verify → publish

**Purpose:** Prepare a production-ready release.

**Steps:**

1. Version synchronization
2. Plugin Check (0 errors)
3. Asset verification
4. Readme validation
5. Changelog generation
6. Final approval

---

## Final Note

If a task does not clearly fit an existing workflow:

> **A new workflow must be defined before execution.**

# MHM Rentiva – Skill Registry (Normalized for v5 Orchestration)

This document defines the **authoritative skill registry** for the MHM Rentiva project.

It is **normalized for the v5 Chief Engineer orchestration model**, meaning:

* Skills are **not autonomous agents**
* Skills are **capabilities invoked by the Chief Engineer**
* Skills are grouped by responsibility
* Rules and workflows are **not duplicated here**

---

## 0. Meta Rules (Binding)

* Skills **do not make decisions**; they execute within a task scope.
* Skills **cannot override** PROJECT_MEMORIES.md or RULES.md.
* Skills **must be invoked through an Agent Role** (Architecture, Implementation, QA, etc.).
* CLI, PHPUnit, and MCP validation are enforced **outside** this document by orchestration rules.

---

## 1. Architecture & Design Skills

### mhm-architect

**Responsibility:** WordPress-native architecture and feature design

**Used by:** Architecture / Analysis Agent

**Scope:**

* Feature architecture design
* Database schema planning
* Hook-based system design
* PSR-4 structure decisions

**Explicitly NOT responsible for:**

* Writing final production code
* Running tests or CLI commands

---

## 2. Data & Performance Skills

### mhm-db-master

**Responsibility:** Secure and performant database operations

**Used by:** Implementation Agent

**Scope:**

* Custom table design and migration
* Query optimization
* Data integrity enforcement

---

### mhm-performance-auditor

**Responsibility:** Runtime and query optimization

**Used by:** QA & Performance Agent

**Scope:**

* Query Monitor analysis
* Caching strategies
* Asset loading audits

---

## 3. Security & Compliance Skills

### mhm-security-guard

**Responsibility:** Security enforcement and audit

**Used by:** QA & Security Agent

**Scope:**

* Nonce validation
* Sanitization & escaping audits
* WordPress.org compliance checks

---

## 4. CLI, Testing & Validation Skills

### mhm-cli-operator

**Responsibility:** WordPress CLI and environment operations

**Used by:** QA & Testing Agent

**Scope:**

* WP-CLI execution
* Scaffolding
* Plugin Check runs

---

### mhm-test-architect

**Responsibility:** PHPUnit test design and coverage

**Used by:** QA & Testing Agent

**Scope:**

* Unit and integration test design
* Regression prevention
* Test coverage enforcement

---

### webapp-testing

**Responsibility:** Browser-level automated testing

**Used by:** QA & Testing Agent

**Scope:**

* Playwright-based UI tests
* End-to-end scenario validation

---

## 5. Versioning, Release & Git Skills

### mhm-git-ops

**Responsibility:** Git operations and commit hygiene

**Used by:** Release Agent

**Scope:**

* Commit creation
* Branch management
* Repository synchronization

---

### create-pull-request

**Responsibility:** Pull Request creation via gh CLI

**Used by:** Release Agent

**Scope:**

* PR generation
* Template compliance
* Reviewer preparation

---

### mhm-release-manager

**Responsibility:** Release readiness and pre-flight checks

**Used by:** Release Agent

**Scope:**

* Version sync
* Plugin Check validation
* Asset and readme verification

---

### changelog-generator

**Responsibility:** User-facing changelog generation

**Used by:** Release Agent

**Scope:**

* Commit analysis
* Release note generation
* Customer-friendly summaries

---

## 6. Documentation & Localization Skills

### mhm-doc-writer

**Responsibility:** Project documentation maintenance

**Used by:** Documentation Agent

**Scope:**

* Docusaurus docs
* Feature documentation
* Shortcode documentation

---

### mhm-polyglot

**Responsibility:** Internationalization (i18n)

**Used by:** Implementation & QA Agents

**Scope:**

* Translation function enforcement
* POT/PO generation
* Text domain validation

---

### mhm-translator

**Responsibility:** Translation file generation

**Used by:** QA & Localization Agent

**Scope:**

* .po/.mo file generation
* Language pack maintenance

---

## 7. Memory & Continuity Skills

### mhm-memory-keeper

**Responsibility:** Project memory management

**Used by:** Review / Memory Agent

**Scope:**

* PROJECT_MEMORIES.md updates
* Decision logging
* Redundancy prevention

**Invocation Rule:**

* Mandatory after architectural decisions
* Mandatory after bug root-cause resolution

---

## 8. Auxiliary / External Skills

### stitch-layout-translator

**Responsibility:** Design-to-WordPress translation

**Used by:** Architecture Agent

**Scope:**

* Google Stitch → WordPress blueprint translation

---

### file-organizer

**Responsibility:** Local file structure organization

**Used by:** Tooling Agent (Optional)

**Scope:**

* File cleanup
* Directory normalization

---

### web-design-guidelines

**Responsibility:** UI/UX consistency rules

**Used by:** Architecture & Review Agents

**Scope:**

* Layout consistency
* Design constraint validation

---

## 9. Orchestration Control Skill

### mhm-skills-hub

**Responsibility:** Skill resolution helper

**Used by:** Chief Engineer ONLY

**Scope:**

* Maps task requirements to skills
* Does NOT execute work
* Does NOT make architectural decisions

---

## 10. Explicit Exclusions

This document intentionally does NOT define:

* Rules
* Workflows
* MCP usage policies

These are defined in:

* RULES.md
* WORKFLOWS.md
* MASTER_ORCHESTRATION_PROMPT.md

---

## Final Note

This skill registry is **stable**, **bounded**, and **non-autonomous**.

All power flows through the **Chief Engineer orchestration layer**.
