---
name: mhm-git-ops
description: Synchronizes local changes to GitHub repositories (Plugin and Docs) with intelligent commit message generation. Analyzes git diffs to create descriptive conventional commit messages. Use when syncing changes to GitHub, committing code changes, pushing to repositories, or when the user mentions git-ops, git sync, or repository synchronization.
---

# MHM Git Ops

**Role:** GitHub repository synchronization and intelligent commit message generator.  
**Motto:** "Smart commits, seamless sync."

## When to Use This Skill

Apply this skill when:
- User asks to sync changes to GitHub repositories
- User mentions "git-ops" or "git sync"
- User wants to commit and push changes
- User needs help with commit messages
- Syncing plugin or documentation repositories

## Core Capabilities

### 1. Smart Commit Messages

Analyzes `git diff` to generate descriptive commit messages instead of generic ones like "Bug fix".

**Example:**
- ❌ Generic: "Bug fix yapıldı"
- ✅ Smart: "fix(transfer): Nonce validation error in TransferCartIntegration.php resolved"

### 2. Conventional Commits

Uses standard prefixes for automatic changelog generation:

- `feat:` New feature (increments minor version)
- `fix:` Bug fix (increments patch version)
- `docs:` Documentation changes only
- `style:` Formatting, punctuation (doesn't affect code functionality)
- `refactor:` Code changes that neither fix bugs nor add features
- `chore:` Build processes, library updates

### 3. Dual Repository Management

Manages two repositories:
1. **Plugin:** `MaxHandMade/mhm-rentiva` (root directory)
2. **Docs:** `MaxHandMade/mhm-rentiva-docs` (website subdirectory)

## Workflow

### Plugin Repository Sync

When syncing the plugin repository:

1. Navigate to root: `cd [workspace_root]`
2. Check status: `git status`
3. Stage changes: `git add .`
4. Commit with smart message: `git commit -m "[AI-generated conventional commit message]"`
5. Push to current branch: `git push origin [current_branch]`

**Commit Message Generation:**
- Analyze `git diff` to understand changes
- Identify affected files and functionality
- Generate appropriate conventional commit prefix
- Create descriptive message following format: `type(scope): description`

### Documentation Repository Sync

When syncing the documentation repository:

1. Navigate to docs: `cd website`
2. Check status: `git status`
3. Stage changes: `git add .`
4. Commit with conventional message: `git commit -m "[Conventional commit message]"`
5. Push to current branch: `git push origin [current_branch]`

**Önemli:** Blog yazıları (`website/blog/`) veya yeni dökümanlar eklendiğinde bu repo mutlaka senkronize edilmelidir.

## Branching Strategy

- **Main branches:**
  - `main` - Production
  - `develop` - Staging/Development

- **Temporary branches:**
  - `feature/[name]` - New features
  - `fix/[name]` - Bug fixes
  - `hotfix/[name]` - Urgent production fixes

## Safety and Conflict Prevention

**Before committing:**
- Always run `git status` to check current state
- Review staged changes before committing

**Before pushing:**
- Check for remote changes: `git fetch`
- If conflicts possible, pull with rebase: `git pull --rebase origin [branch]`
- Only push after ensuring local and remote are in sync

## Version Tagging

When releasing a new version:

```bash
git tag -a v4.5.x -m "Release message"
git push origin --tags
```

## Usage Scenarios

**Scenario 1: Plugin Only**
> "Sync plugin changes using git-ops. Generate the commit message automatically."

**Scenario 2: Documentation Only**
> "I've updated the documentation site. Push to docs repo using git-ops."

**Scenario 3: Full Sync**
> "I've finished today's work. Sync both repositories and prepare for shutdown."

## Commit Message Examples

**Feature addition:**
```
feat(booking): add vehicle availability calendar widget

Implement interactive calendar showing available dates for selected vehicle
with real-time availability checking via AJAX.
```

**Bug fix:**
```
fix(payment): resolve nonce verification failure in offline payment flow

Add proper nonce generation and validation in PaymentProcessor class
to prevent CSRF attacks in offline payment submissions.
```

**Documentation:**
```
docs(shortcodes): update vehicle-search shortcode usage examples

Add new attribute examples and clarify parameter descriptions
in shortcode documentation.
```

**Refactoring:**
```
refactor(settings): improve SettingsService class structure

Extract validation logic into separate methods for better
maintainability and testability.
```
