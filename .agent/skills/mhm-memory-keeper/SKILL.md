---
name: mhm-memory-keeper
description: Projenin sürekliliğini sağlar, oturumlar arası hafızayı yönetir ve aynı hataların tekrar edilmesini (redundancy) engeller. PROJECT_MEMORIES.md dosyasını yönetir.
---

# MHM Memory Keeper

**Role:** The Guardian of Context & Continuity.
**Motto:** "Never lose a thought, never repeat a fix."

## When to Use This Skill
- **Session Start:** To read the previous context and pending tasks.
- **Task Completion:** To update the status and log technical notes.
- **Critical Decisions:** To record WHY a specific architectural choice was made.

## Operational Workflow

### 1. The Entry Protocol (Bootstrap)
- **Mandate:** Before any code change, read `PROJECT_MEMORIES.md`.
- **Action:** Identify items marked `[İŞLEMDE]` or `[YAPILACAK]` to avoid redundant work.

### 2. Status Management
- **[YAPILACAK]:** New tasks or features yet to be started.
- **[İŞLEMDE]:** Tasks currently being coded or debugged.
- **[TAMAMLANDI]:** Tasks finished but not yet verified by the user.
- **[DOĞRULANDI]:** Tasks confirmed as fixed/working by the user.

### 3. Redundancy Prevention (Anti-Repeat)
- **Rule:** If a file path is already marked as `[DOĞRULANDI]` for a specific fix, do not modify that logic unless explicitly requested.
- **Logging:** Briefly record the "How & Why" for every major fix to prevent future regressions.

### 4. Technical Debt & Risk Preservation (Mandatory)
- **Mandate:** When a task is marked as [DONE] or [VERIFIED] and moved to the ARCHIVE section, any "Future Risks," "Technical Debts," or "Performance Warnings" identified during that task MUST NOT be deleted.
- **Action:** Before archiving a task, the Agent must extract all critical findings and permanently move them to the TECHNICAL NOTES section.
- **Specific Requirement:** Performance bottlenecks (e.g., inefficient SQL queries, lack of AJAX/Lazy Loading) are considered permanent technical debts until a specific refactoring task is completed and verified.
- **Anti-Data Loss Rule:** ARCHIVE is for completed actions; TECHNICAL NOTES is for the ongoing health of the plugin. Never confuse the two.

## File Structure (`PROJECT_MEMORIES.md`)
The Agent must maintain this structure in the root directory:
1. **ACTIVE TASKS:** Sorted by priority with status labels.
2. **TECHNICAL NOTES:** Critical paths, folder locations, and fixed bugs.
3. **ARCHIVE:** Completed and verified tasks.

## Memory Checklist
- [ ] Did I check the memory file before starting?
- [ ] Did I update the status of the current task after the fix?
- [ ] Did I move verified tasks to the ARCHIVE section?
- [ ] Did I note down the specific file paths and line numbers for the fix?