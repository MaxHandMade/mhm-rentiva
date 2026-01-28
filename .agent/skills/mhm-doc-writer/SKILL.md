---
name: mhm-doc-writer
description: Updates and manages Docusaurus documentation in website/docs directory. Creates new documentation files, updates existing ones with code changes, ensures proper frontmatter format, and maintains Turkish language standards. Use when code changes require documentation updates, when new features need documentation, when shortcodes or settings are modified, or when user asks to update documentation.
---

# MHM Doc Writer

Manages Docusaurus documentation updates in `website/docs/` directory, ensuring code changes are reflected in user-facing documentation.

## Documentation Structure

Documents are categorized under `website/docs/`:
- `01-getting-started/`: Installation and getting started
- `02-core-configuration/`: Core settings
- `03-features-usage/`: Feature usage guides
- `04-developer/`: Developer docs, hooks, API

## Update Workflow

### 1. File Detection
When code changes occur (e.g., new Shortcode added or setting changed), locate or create the corresponding documentation file in `website/docs/`.
- If file doesn't exist, create new `.md` file in appropriate category folder

### 2. Docusaurus Format
Every Markdown file MUST start with **Frontmatter**:

```markdown
---
id: file-id
title: Title (Visible to Users)
sidebar_label: Sidebar Menu Name
---
```

### 3. Content Writing
- **Language:** Primary documentation language is **Turkish**. All titles, descriptions, and menu labels must be Turkish. English translations will be handled in a separate phase.
- **Style:** Clear, understandable language with correct technical terminology
- **Code Examples:** Always include code blocks (` ```php ... ``` `) with examples

### 4. Update Process
1. **Analysis:** Understand what the changed code does and how it affects users
2. **Target Identification:** Determine which file in `website/docs/` needs updating (or new file path)
3. **Writing:** Update Markdown content
   - Add new parameters to tables if added
   - Update steps if workflow changed
4. **Verification:** Ensure frontmatter structure is intact

## Special Cases

- **New Feature:** Create new file under `03-features-usage`
- **Dev Change (Hook, Class):** Update under `04-developer`
- **Critical Warnings:** Use Docusaurus Admonitions (`:::danger`, `:::warning`, `:::tip`)

```markdown
:::warning
Bu ayarı değiştirmek veritabanı yapısını etkiler. Yedek almadan işlem yapmayın.
:::
```

## i18n Strategy

Site defaults to Turkish (`i18n/tr`). English translations use Crowdin or manual method.
- Code `__()` functions extracted to `.pot` via `makepot`
- Documentation uses `website/i18n/en/docusaurus-plugin-content-docs/current/` directory

## Versioning

Docusaurus versioning preserves old versions.
- When new major version released (e.g., v4.0 -> v5.0):
  - Current `docs` folder backed up to `versioned_docs/version-4.5.0`
  - `docs` folder becomes available for "Next" version

## Media and Screenshots

- Images uploaded to `website/static/img/`
- Naming: `feature-name-screenshot-tr.png`
- Markdown usage: `![Description](/img/feature-name.png)`
- Prefer `.webp` or compressed `.png` when possible
