---
name: mhm-skills-hub
description: Central Skill Orchestration Guide. Automatically selects the correct skill and workflow based on user intent.
---

# MHM Skills Hub: Orchestration Mapping

**Motto:** "The right tool for the right mission, every single time."

## 🎯 Intent-Based Skill Mapping

| User Intent / Scenario | Primary Skills | Supporting Skills |
| :--- | :--- | :--- |
| **Bug Fix & Debugging** | `audit-code` (Workflow) | `mhm-security-guard`, `mhm-memory-keeper` |
| **Database & Queries** | `mhm-db-master` | `mhm-performance-auditor` |
| **Architecture & New Features** | `mhm-architect` | `mhm-memory-keeper`, `mhm-doc-writer` |
| **Licensing & Security** | `mhm-security-guard` | `mhm-architect`, `mhm-memory-keeper` |
| **Frontend & UI/UX** | `mhm-architect` (JS/CSS) | `mhm-performance-auditor` |
| **Performance & Optimization** | `mhm-performance-auditor` | `full-optimize.md` (Context) |

## ⚙️ Orchestration Rules
1. **Self-Selection:** Upon receiving a task, review the mapping above. Select the required skills and inform the user: "Active skills for this task: X, Y, Z."
2. **Workflow Priority:** For complex tasks, prioritize starting the `audit-code` workflow and integrate skills into its phases.
3. **Memory Synchronization:** Every task MUST end with a `PROJECT_MEMORIES.md` update via `mhm-memory-keeper`.
4. **Autonomous Default:** If no specific skill is requested, assume the `audit-code` workflow as the default standard for quality.
