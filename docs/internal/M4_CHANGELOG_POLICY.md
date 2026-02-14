# Changelog & Commit Policy (Conventional Commits)

To ensure automated, semantic versioning-aware changelogs, MHM Rentiva adheres strictly to the **Conventional Commits** specification.

## 1. Commit Message Format

All commits must follow this format:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Allowed Types

| Type | Description | SemVer Impace | Changelog Section |
| :--- | :--- | :--- | :--- |
| **feat** | A new feature | **MINOR** | ✨ Features |
| **fix** | A bug fix | **PATCH** | 🐛 Bug Fixes |
| **perf** | A code change that improves performance | **PATCH** | 🚀 Performance |
| **refactor** | A code change that neither fixes a bug nor adds a feature | **PATCH** | ♻️ Refactoring |
| **test** | Adding missing tests or correcting existing tests | **None** | 🧪 Testing |
| **docs** | Documentation only changes | **None** | 📚 Documentation |
| **style** | Changes that do not affect the meaning of the code (white-space, formatting, etc) | **None** | 💎 Styles |
| **chore** | Other changes that don't modify src or test files | **None** | 🔧 Chores |
| **build** | Changes that affect the build system or external dependencies | **PATCH** | 📦 Build |
| **ci** | Changes to our CI configuration files and scripts | **None** | ⚙️ CI/CD |

### Scope (Optional)
The scope provides additional context:
*   `admin`
*   `frontend`
*   `booking`
*   `api`
*   `deps`

## 2. Examples

**Feature:**
```
feat(booking): add date range validation for rentals
```

**Bug Fix:**
```
fix(frontend): resolve layout shift on mobile devices
```

**Performance:**
```
perf(db): optimize vehicle query with meta index
```

**Breaking Change (MAJOR):**
```
feat(api): change response format for search endpoint

BREAKING CHANGE: The `data` property is now an array instead of an object.
```

## 3. Workflow Integration

The `changelog-generator` skill parses these commits to:
1.  Determine the next version number (Major/Minor/Patch).
2.  Group changes by section in `changelog.json`.
3.  Generate clean, readable release notes.

**Strict Rule:** Commits that do not follow this policy will be rejected or formatted incorrectly in the changelog.
