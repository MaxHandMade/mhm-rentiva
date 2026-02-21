# MASTER AGENT TASK INSTRUCTION TEMPLATE (v1)

Use this template to generate task instructions for specialist master agents.

---

## 0) Metadata
- Instruction ID: `<ID>`
- Date: `<YYYY-MM-DD>`
- Mode: `<Strict|Fast>`
- Prepared By: `Chief Engineer / Orchestrator`
- Language Rule:
  - User communication: `Turkish`
  - Master-agent instructions: `English`

---

## 1) Task Description
### 1.1 Objective
`<Describe the requested outcome in one clear paragraph.>`

### 1.2 Scope
- In scope:
  - `<item>`
  - `<item>`
- Out of scope:
  - `<item>`
  - `<item>`

### 1.3 Constraints
- `<technical constraint>`
- `<business/domain constraint>`

---

## 2) Relevant Project Memory References
- `PROJECT_MEMORIES.md`:
  - `<memory reference / decision>`
  - `<memory reference / anti-pattern>`
- `PROJECT_GUIDE.md`:
  - `<rule / allowed skill / workflow>`
- `SHORTCODES.md`:
  - `<shortcode compatibility requirement>`

Memory conflict check:
- Detected conflict: `<Yes|No>`
- If yes, explain:
  - `<conflict detail>`
  - `<adaptation or escalation note>`

---

## 3) Agent Team Definition (Minimum 3 Agents)

### 3.1 Architecture and Analysis Agent
- Primary responsibility: `<single responsibility>`
- Assigned skills only:
  - `<skill>`
  - `<skill>`
- MCP usage: `Limited (read-only design validation only)`

### 3.2 Implementation Agent
- Primary responsibility: `<single responsibility>`
- Assigned skills only:
  - `<skill>`
  - `<skill>`
- MCP usage: `Task-specific (file existence/structure checks only)`

### 3.3 QA and Testing Agent
- Primary responsibility: `<single responsibility>`
- Assigned skills only:
  - `<skill>`
  - `<skill>`
- MCP usage: `Mandatory`
- Restriction: `Never writes or modifies code`

Role overlap validation:
- Overlap detected: `<Yes|No>`
- If yes, revise before execution.

---

## 4) Rules to Follow (Non-Negotiable)
- Use only approved skills from project guide.
- Respect project memories over assumptions.
- Keep shortcode backward compatibility.
- Do not bypass validation layers.
- Do not approve without MCP-backed QA evidence.

---

## 5) Workflow Steps
1. Input validation and memory alignment.
2. Architecture plan and impact analysis.
3. Implementation execution.
4. QA verification with MCP evidence.
5. Review against memories and shortcodes.
6. Final decision: `Pass` or `Fail`.

---

## 6) Required Skills Mapping
- Architecture and Analysis Agent:
  - `<skill-name>`: `<why needed>`
- Implementation Agent:
  - `<skill-name>`: `<why needed>`
- QA and Testing Agent:
  - `<skill-name>`: `<why needed>`

---

## 7) Validation and Testing Requirements (Mandatory)

### 7.1 Required CLI Commands
```bash
# Add exact commands used by agents
<wp-cli command>
<composer command>
<cache clear command>
<other required command>
```

### 7.2 PHPUnit Execution
```bash
# Add exact PHPUnit command
<phpunit command>
```

### 7.3 Acceptance Rule
- Missing CLI evidence: `Fail`
- Missing PHPUnit evidence: `Fail`
- Missing MCP evidence: `Fail`

Failure handling:
- Root cause:
  - `<root cause>`
- Corrective action:
  - `<action>`

---

## 8) Evidence Contract (Required Artifacts)
Every agent report must include:
- Scope summary
- Changed files list
- Exact CLI command log
- CLI output summary (`pass|fail|warnings`)
- PHPUnit command and result summary
- MCP verification records
- Shortcode regression notes
- Risks and assumptions

Evidence quality rules:
- Claims without artifacts are invalid.
- Screenshots alone are insufficient without command text.
- Missing evidence means automatic fail.

---

## 9) QA Decision Template (Mandatory)
- Decision: `<Pass|Fail>`
- Findings:
  - Critical: `<list>`
  - Major: `<list>`
  - Minor: `<list>`
- Required fixes (if fail):
  - `<fix item>`
- Re-test plan:
  - `<plan>`
- Memory conflict status:
  - `<No conflict|Conflict exists>`

---

## 10) Conflict Resolution Block
Resolution order:
1. `PROJECT_MEMORIES.md`
2. Rules file
3. Workflow file
4. `PROJECT_GUIDE.md` skill constraints
5. Task-specific preferences

If unresolved, use this block:
```md
### Conflict Note
- Conflict source: <source>
- Why unresolved: <reason>
- Proposed options: <option A / option B>
- Escalation required: Yes
```

---

## 11) Input Completeness Check
- Strict mode:
  - All mandatory files uploaded: `<Yes|No>`
  - If no: `Do not generate final instructions.`
- Fast mode:
  - Minimum set present (`PROJECT_MEMORIES.md`, `PROJECT_GUIDE.md`, `SHORTCODES.md`): `<Yes|No>`
  - Missing inputs:
    - `<file>`
    - `<file>`
  - Add label to output: `Needs Full Input Validation`

---

## 12) Definition of Done (DoD)
- [ ] Required agents completed assigned responsibilities.
- [ ] No role overlap occurred.
- [ ] CLI evidence is complete and valid.
- [ ] PHPUnit results are present and acceptable.
- [ ] MCP validation confirms implementation claims.
- [ ] No shortcode regression detected.
- [ ] No unresolved memory conflict remains.
- [ ] QA final decision is `Pass`.

---

## 13) Final Instruction Output (For Master Agents)
```md
# Task Instruction
## Task Description
<fill>

## Relevant Project Memory References
<fill>

## Agent Team Definition
<fill>

## Rules to Follow
<fill>

## Workflow Steps
<fill>

## Required Skills (Mapped Per Agent)
<fill>

## Validation and Testing Requirements
<fill>

## Evidence Contract
<fill>

## QA Decision Criteria
<fill>
```

---

## 14) Sequence-Based Sprint Instruction Pack (No Date Dependency)
Use this pack when execution speed is variable and planning must stay order-based.

### 14.1 Sequence Rule
- Do not use day/date labels in task instructions.
- Use only ordered labels:
  - `Sequence-1`, `Sequence-2`, `Sequence-3`, ...
- A sequence may start only after previous sequence evidence is accepted by QA.

### 14.2 Sequence Instruction Skeleton
```md
# Sequence Instruction: <Sequence-N Title>

## Task Description
- Objective: <single clear objective>
- Scope:
  - In: <items>
  - Out: <items>

## Relevant Project Memory References
- PROJECT_MEMORIES.md:
  - <refs>
- PROJECT_GUIDE.md:
  - <refs>
- SHORTCODES.md:
  - <compatibility notes>

## Agent Team Definition
- Architecture Agent:
  - Responsibility: <one responsibility>
  - Skills: <list>
- Implementation Agent:
  - Responsibility: <one responsibility>
  - Skills: <list>
- QA & Testing Agent:
  - Responsibility: <one responsibility>
  - Skills: <list>
  - MCP usage: Mandatory

## Rules to Follow
- <rules>

## Workflow Steps
1. Analyze and align with memories.
2. Implement within scope.
3. Validate via CLI + PHPUnit.
4. QA verification with MCP artifacts.
5. Decision: Pass/Fail.

## Required Skills (Mapped Per Agent)
- <mapping>

## Validation and Testing Requirements
- CLI commands:
```bash
<commands>
```
- PHPUnit:
```bash
<phpunit command>
```
- Acceptance:
  - Missing CLI/PHPUnit/MCP evidence => Fail

## Evidence Contract
- Changed files
- Command logs
- Test summary
- MCP verification summary
- Regression notes

## QA Decision Criteria
- Critical:
  - <items>
- Major:
  - <items>
- Minor:
  - <items>
```

### 14.3 Canonical Sequence Order (Current Sprint)
1. `Sequence-1` Baseline Stabilization
2. `Sequence-2` Vehicle Card Unification
3. `Sequence-3` Search/Transfer Result Consistency
4. `Sequence-4` Block/Elementor Parity Hardening
5. `Sequence-5` Mobile Overflow and Spacing Contract
6. `Sequence-6` Documentation Source of Truth
7. `Sequence-7` Final Hardening and Release Gate

### 14.4 Gate Policy Between Sequences
- Next sequence cannot start if previous sequence has:
  - Open critical QA findings
  - Missing CLI evidence
  - Missing PHPUnit evidence
  - Missing MCP verification artifacts
