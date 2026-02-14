# Re-tag Justification: v4.9.8

## Context
The release tag `v4.9.8` was initially created on 2026-02-13. During the Staging Smoke Test on 2026-02-14, critical bugs related to the Transfer module were discovered in the production-ready code.

## Reasons for Re-tagging
1. **Transfer AJAX Fix**: The "Rezervasyon Yap" button was not triggering the add-to-cart AJAX due to a class mismatch between the template (`.js-mhm-transfer-book`) and the script (`.mhm-transfer-book-btn`).
2. **Missing Metadata**: Transfer search results were missing critical metadata (price, duration, distance) which prevented correct item enrichment in the cart for static results.
3. **Location Synchronization**: Handled location ID mismatches and provided fallbacks for consistent transfer booking across environments.

## Audit Data
- **Previous Tag Hash (OLD v4.9.8)**: `89fdaae68a1283b2e2911c587f1cbd9f`
- **Current Tag Hash (NEW v4.9.8)**: `1a9155973bb79deba7edaafdc9d7a3ceb107d8771`
- **Timestamp**: 2026-02-15 00:43 (Local)
- **Tagger**: MaxHandMade <info@maxhandmade.com>
- **Status**: ✅ RELEASE INTEGRITY VERIFIED (Annotated Tag)
