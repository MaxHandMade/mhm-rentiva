---
name: mhm-skills-hub
description: Central Skill Orchestration Guide. Automatically selects the correct skill and workflow based on user intent.
---

# MHM Skills Hub: Orchestration Mapping

**Motto:** "The right tool for the right mission, every single time."

## 🎯 Intent-Based Skill Mapping

| User Intent / Scenario | Primary Skills & Workflows | Supporting Skills |
| :--- | :--- | :--- |
| **Project Audit & Optimization** | `/audit-optimize-verify` (Workflow) | `mhm-performance-auditor`, `mhm-security-guard` |
| **New Feature Architecture** | `mhm-architect` | `mhm-db-master`, `mhm-doc-writer` |
| **Database Management** | `mhm-db-master` | `mhm-performance-auditor` |
| **Security & Compliance** | `mhm-security-guard` | `mhm-architect`, `web-design-guidelines` |
| **Tests & QA** | `mhm-test-architect` | `webapp-testing`, `mhm-cli-operator` |
| **Translations & i18n** | `mhm-polyglot` | `mhm-translator`, `stitch-layout-translator` |
| **Documentation** | `mhm-doc-writer` | `changelog-generator` |
| **Release & Git Ops** | `mhm-release-manager` | `mhm-git-ops`, `create-pull-request`, `changelog-generator` |
| **Frontend Layouts (Stitch)** | `stitch-layout-translator` | `web-design-guidelines` |
| **CLI & DevOps** | `mhm-cli-operator` | `file-organizer` |
| **Memory & Context** | `mhm-memory-keeper` | *All Skills* |

## ⚙️ Orchestration Rules

1.  **Workflow First:** If the request implies a full system check, optimization, or strict compliance audit, ALWAYS trigger the `/audit-optimize-verify` workflow.
2.  **Standards Enforcement (Golden Rules):** All code generation must strictly adhere to `mhm-rentiva-rulles.md`.
    *   `mhm-security-guard` is implicitly active for ALL PHP coding tasks.
    *   `web-design-guidelines` is implicitly active for ALL Frontend/UI tasks.
3.  **Memory Synchronization:** Every significant session or decision MUST be logged via `mhm-memory-keeper` to `PROJECT_MEMORIES.md`.
4.  **Translation Awareness:** Any new user-facing string added MUST trigger a mental check for `mhm-polyglot` (wrapping strings) and `mhm-translator` (generating safe translations).
5.  **Documentation Sync:** Code changes affecting features or usage require `mhm-doc-writer` to keep Docusaurus docs in sync. 
6.  **Release Readiness:** Any major change must trigger `mhm-release-manager` to ensure proper versioning and changelog updates.
7.  **Testing Integration:** All new features must be tested via `mhm-test-architect` and `webapp-testing`.
8.  **Database Integrity:** Any database schema change must be validated by `mhm-db-master` and `mhm-performance-auditor`. 

**Golden Rule:** Never generate code without first consulting `mhm-rentiva-rulles.md`. If you detect a violation, immediately flag it to `mhm-security-guard`. Always verify compliance with `mhm-architect` before finalizing any output. 

**Note:** The `mhm-memory-keeper` is always active and should be used to store and recall critical information about the project.  
**Note:** The `mhm-architect` is always active and should be used to verify the compliance of the code with the `mhm-rentiva-rulles.md`.

## 🔧 Tools

- **mhm-memory-keeper**: A tool to store and recall critical information about the project.
- **mhm-architect**: A tool to verify the compliance of the code with the `mhm-rentiva-rulles.md`.
- **mhm-security-guard**: A tool to verify the security of the code.
- **mhm-polyglot**: A tool to wrap strings in a way that allows for easy translation.
- **mhm-translator**: A tool to generate safe translations of strings.
- **mhm-doc-writer**: A tool to keep Docusaurus docs in sync with the code.
- **mhm-release-manager**: A tool to ensure proper versioning and changelog updates.
- **mhm-test-architect**: A tool to test the code.
- **webapp-testing**: A tool to test the web application.
- **mhm-db-master**: A tool to validate database schema changes.
- **mhm-performance-auditor**: A tool to audit the performance of the code.
- **mhm-cli-operator**: A tool to manage the CLI.
- **file-organizer**: A tool to organize files.
- **stitch-layout-translator**: A tool to translate frontend layouts.
- **web-design-guidelines**: A tool to ensure compliance with web design guidelines.
- **changelog-generator**: A tool to generate a changelog.
- **create-pull-request**: A tool to create a pull request.
- **mhm-rentiva-rulles.md**: A document containing the rules for the project.
- **mhm-git-ops**: A tool to manage git operations. 

## 📝 Documentation

- **mhm-doc-writer**: A tool to keep Docusaurus docs in sync with the code.
- **changelog-generator**: A tool to generate a changelog.
- **mhm-rentiva-rulles.md**: A document containing the rules for the project.
- **mhm-git-ops**: A tool to manage git operations. 

## 🧪 Testing

- **mhm-test-architect**: A tool to test the code.
- **webapp-testing**: A tool to test the web application.
- **mhm-performance-auditor**: A tool to audit the performance of the code.

## 🔒 Security

- **mhm-security-guard**: A tool to verify the security of the code.
- **mhm-db-master**: A tool to validate database schema changes.
- **mhm-performance-auditor**: A tool to audit the performance of the code.

## 🌐 Web Design

- **web-design-guidelines**: A tool to ensure compliance with web design guidelines.

## 🔧 CLI

- **mhm-cli-operator**: A tool to manage the CLI.
- **file-organizer**: A tool to organize files.
- **stitch-layout-translator**: A tool to translate frontend layouts.

## 📦 Packaging

- **mhm-packager**: A tool to package the code.
- **mhm-release-manager**: A tool to ensure proper versioning and changelog updates.
- **mhm-git-ops**: A tool to manage git operations. 

## 📦 Deployment

- **mhm-deployer**: A tool to deploy the code.
- **mhm-git-ops**: A tool to manage git operations. 

## 📦 Monitoring

- **mhm-monitor**: A tool to monitor the code.
- **mhm-git-ops**: A tool to manage git operations.

## 📦 CI/CD

- **mhm-ci-cd**: A tool to manage CI/CD pipelines.
- **mhm-git-ops**: A tool to manage git operations. 

## 📦 Other

- **mhm-git-ops**: A tool to manage git operations.
- **mhm-rentiva-rulles.md**: A document containing the rules for the project.       
- **mhm-git-ops**: A tool to manage git operations.