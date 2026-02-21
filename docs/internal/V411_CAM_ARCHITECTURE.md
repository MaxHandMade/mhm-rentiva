# Architecture Design: Canonical Attribute Mapper (CAM)

**Version:** 1.1 (Revised)  
**Phase:** v4.11.0 - Phase 2  
**Status:** DRAFT / FOR REVIEW  

## 1. Goal
Replace the ad-hoc attribute mapping logic in `BlockRegistry` with a centralized, strict, and allowlist-driven system. CAM ensures that only explicitly allowed and correctly typed attributes reach the shortcode rendering engine.

## 2. Core Components

### 2.1 KeyNormalizer
- `camelCase` to `snake_case` conversion via regex.
- Explicit alias resolution (e.g., `showPrice` -> `show_prices`).

### 2.2 Advanced Type System (Transformers)
Stateless logic for strict value normalization:
- **bool:** Coerce to string `"1"` or `"0"`.
- **int:** Strict integer conversion.
- **float:** Float conversion (for ratings/deposits).
- **date:** Enforce `Y-m-d` ISO format. If invalid, reset to default.
- **idlist:** Parse comma-separated IDs into a cleaned, unique ID string.
- **url:** Protocol-safe URL cleanup using `esc_url_raw`.
- **array:** (Future-proofing) JSON decoding for complex nested attributes.
- **enum:** Strict validation against `values` allowlist.

### 2.3 AllowlistRegistry (The Golden Source)
Centralized source of truth.
**Constraint:** Must contain the union of `block.json` attributes and shortcode `get_default_attributes()`.

### 3. Schema Authority Strategy
To prevent logic drift and ensure zero-regression:

1.  **Registry as Master:** The `AllowlistRegistry` is the authoritative source for all mappings.
2.  **SchemaParityTest Scope:** A comprehensive test suite will verify that the Registry is a **superset** of all existing definitions:
    - **`block.json` attributes ⊆ Registry:** Ensures all block-supported attributes are recognized.
    - **`get_default_attributes()` ⊆ Registry:** Ensures all shortcode-level defaults are preserved.
    - **Legacy Alias List ⊆ Registry:** Ensures all v4.10.0 era aliases (e.g., in `BlockRegistry`) are migrated.
3.  **Conflict Detection:** Any key found in source files but missing/misconfigured in the Registry will trigger a test failure, blocking the CI/CD pipeline.

## 4. Operational Policies

### 4.1 Strictness & Logging
- **Production:** Unknown attributes are silently dropped to maintain clean output.
- **Development (WP_DEBUG):** When an attribute is dropped or a type is coerced due to invalidity, CAM will trigger a `doing_it_wrong()` style warning or log to `AdvancedLogger` for visibility.

### 4.2 Wrapper Attribute Scope
- **CAM Responsibility:** CAM only handles *logical* attributes defined in the shortcode contract.
- **BlockRegistry Responsibility:** CSS-specific wrapper attributes (`minWidth`, `maxWidth`, `height`, `className`) are handled by `BlockRegistry` *after* CAM processing. These are structural concerns, not logic concerns.

### 4.3 Rollback Strategy
- No toggle constants.
- **Git Policy:** Rollback is performed via `git revert`.
- **Verification:** Any code change must pass the full 216+ PHPUnit suite before merge.

## 5. Implementation Workflow

1.  **Phase A:** Implement service classes (`KeyNormalizer`, `Transformers`, etc.).
2.  **Phase B:** Populate `AllowlistRegistry` for all 19 shortcodes (195+ attributes).
3.  **Phase C:** Implement `SchemaParityTest` to ensure zero drift.
4.  **Phase D:** Switch `BlockRegistry` to use CAM.
5.  **Phase E:** Final verification and legacy code removal.

---
*Revised by Architecture Agent - v4.11.0 Design Suite.*
