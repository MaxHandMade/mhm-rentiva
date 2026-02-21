# LAYOUT ENGINE PRESET GUIDE

Applicability: theme-only, hybrid (plugin-only optional)

This guide explains how to adapt the core engine to a new product.

## 1. Adaptation Steps

### A. Update ContractAllowlist
Define product-specific component types and allowed wrappers.
`src/Layout/Config/ContractAllowlist.php`

### B. Update Token Mapping Tables
Map design token names to project CSS variables.
`src/Layout/Tokens/TokenMapper.php`

### C. Create Component Adapters
Transform manifest JSON/XML into shortcode or block output.
`src/Layout/Adapters/`

## 2. Do Not Touch List
The following layers are core and must not be changed per project:
- AtomicImporter transaction and rollback logic
- Normalization and deterministic hashing rules
- Diff engine algorithm
- Governance enforcement layer
- Performance test harness

## 3. Quick Migration Checklist
1. [ ] Copy `src/Layout/` core.
2. [ ] Update ContractAllowlist for the target project.
3. [ ] Update TokenMapper tables for target design system.
4. [ ] Implement project-specific adapters.
5. [ ] Run `composer dump-autoload`.
6. [ ] Execute `BOOTSTRAP_NEW_PROJECT.md` steps.


