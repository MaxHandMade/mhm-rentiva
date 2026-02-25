# QA — v4.20.3 Allowlist Enum Expansion

Date: 2026-02-25  
Scope: Allowlist enum expansion only (`status`, `orderby`, `sort_by`) in `AllowlistRegistry::ALLOWLIST`.

## 1) Before/After Enum Diff

### status
- Before: `""`, `pending`, `confirmed`, `in_progress`, `cancelled`, `completed`, `refunded`
- After: `""`, `draft`, `pending_payment`, `pending`, `confirmed`, `in_progress`, `completed`, `cancelled`, `refunded`, `no_show`

### orderby
- Before: `price`, `popularity`, `newest`, `capacity`, `title`, `date`, `rand`, `rating`
- After: `price`, `popularity`, `newest`, `capacity`, `title`, `date`, `modified`, `rand`, `post__in`, `rating`, `rating_average`, `rating_count`, `confidence`

### sort_by
- Before: `price`, `popularity`, `newest`, `capacity`, `title`, `date`, `rand`, `rating`
- After: `price`, `popularity`, `newest`, `capacity`, `title`, `date`, `modified`, `rand`, `rating`, `rating_average`, `rating_count`, `confidence`

## 2) PHPUnit Output Snapshot

Command:
```bash
vendor/bin/phpunit --testdox
```

Snapshot:
```text
OK (273 tests, 1390 assertions)
```

Execution context:
- Docker (`rentiva-dev-wpcli-1`) with DB host `db`.
- WordPress test library path: `/opt/wordpress-tests-lib`.

## 3) Risk Note

- Risk Level: LOW
- Rationale: Only enum value sets were expanded. No changes to CAM, TAG_MAPPING, render logic, or Elementor layer.
- Compatibility: Superset allowlist approach preserves existing behavior while unblocking runtime-supported values.
