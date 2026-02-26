# Codex Agent Integration for MHM Rentiva

This repository contains Antigravity IDE rule, workflow, and skill files.
This file is the integration manifest so Codex can auto-detect and use them while you work.
Only `.agent` is authoritative for Codex in this project.

## Authority and Load Order

When multiple sources overlap, apply this order:
1. `.agent/rules/mhm-rentiva-rulles.md`
2. `.agent/workflows/audit-optimize-verify.md`
3. Skill files listed below

If there is a conflict, higher entry wins.

## Rule Sources (`.agent` only)

- `.agent/rules/mhm-rentiva-rulles.md` (core project rules, always-on)

## Workflow Sources (`.agent` only)

- `.agent/workflows/audit-optimize-verify.md` (canonical optimization workflow)

## Skill Discovery

Primary skill roots:
- `.agent/skills/*/SKILL.md`

Agent profile shortcuts:
- `.agent/agents/*.md`

## Available Skills

- `changelog-generator`: Auto-generates user-friendly changelogs from git history. (`.agent/skills/changelog-generator/SKILL.md`)
- `create-pull-request`: Creates GitHub pull requests using project conventions and `gh` CLI. (`.agent/skills/create-pull-request/SKILL.md`)
- `file-organizer`: Organizes files/folders, duplicate cleanup, and structure suggestions. (`.agent/skills/file-organizer/SKILL.md`)
- `mhm-architect`: WordPress-native architecture and technical design. (`.agent/skills/mhm-architect/SKILL.md`)
- `mhm-cli-operator`: WP-CLI and terminal operations for plugin workflows. (`.agent/skills/mhm-cli-operator/SKILL.md`)
- `mhm-db-master`: Secure and performant WordPress DB design and query work. (`.agent/skills/mhm-db-master/SKILL.md`)
- `mhm-doc-writer`: Documentation updates for `website/docs` and plugin docs. (`.agent/skills/mhm-doc-writer/SKILL.md`)
- `mhm-git-ops`: Commit/push sync workflows and commit message generation. (`.agent/skills/mhm-git-ops/SKILL.md`)
- `mhm-memory-keeper`: Project memory continuity with `PROJECT_MEMORIES.md`. (`.agent/skills/mhm-memory-keeper/SKILL.md`)
- `mhm-performance-auditor`: Performance and resource optimization audits. (`.agent/skills/mhm-performance-auditor/SKILL.md`)
- `mhm-polyglot`: WordPress i18n/l10n and POT readiness. (`.agent/skills/mhm-polyglot/SKILL.md`)
- `mhm-release-manager`: Release pre-flight checks, PCP/readme/release validation. (`.agent/skills/mhm-release-manager/SKILL.md`)
- `mhm-security-guard`: Nonce/sanitize/escape/capability/SQL security enforcement. (`.agent/skills/mhm-security-guard/SKILL.md`)
- `mhm-skills-hub`: Central skill orchestration selector. (`.agent/skills/mhm-skills-hub/SKILL.md`)
- `mhm-test-architect`: PHPUnit/WP test architecture and test implementation. (`.agent/skills/mhm-test-architect/SKILL.md`)
- `mhm-translator`: Translation generation from POT to PO/MO. (`.agent/skills/mhm-translator/SKILL.md`)
- `stitch-layout-translator`: Converts Stitch HTML/CSS outputs to WP integration blueprints. (`.agent/skills/stitch-layout-translator/SKILL.md`)
- `web-design-guidelines`: UI/UX/accessibility guideline auditing. (`.agent/skills/web-design-guidelines/SKILL.md`)
- `webapp-testing`: Playwright-based local web app testing and diagnostics. (`.agent/skills/webapp-testing/SKILL.md`)

## Auto-Trigger Policy

Codex should auto-load skill instructions when either condition is true:
- The user names a skill directly (for example: `$mhm-architect`).
- The task intent clearly matches a skill description.

Selection rules:
1. Use the minimal number of skills needed.
2. Prefer `mhm-skills-hub` when multiple skills may apply.
3. Read only the required sections of `SKILL.md`.
4. Reuse skill scripts/assets if they exist.

## Sync Policy (Critical)

- `.agent` content is the source of truth for Codex execution behavior.
- If external docs (`RULES.md`, `WORKFLOWS.md`, `SKILLS.md`, task specs) change, matching `.agent` rules/workflows/skills must be reviewed and synchronized before enforcing new behavior.
- Until sync is completed, `.agent` definitions remain authoritative.
- In case of mismatch, report a `Sync Drift` note in the task output.

## Practical Notes

- Ignore `.ai` and `.agents` for Codex skill/rule/workflow loading in this repository.
- To add a new skill, create `.agent/skills/<skill-name>/SKILL.md` with frontmatter:
  - `name: <skill-name>`
  - `description: <short purpose and when-to-use>`
- Keep skill descriptions explicit so intent matching is reliable.
- If a skill is missing or unreadable, continue with best-effort fallback and report it briefly.
