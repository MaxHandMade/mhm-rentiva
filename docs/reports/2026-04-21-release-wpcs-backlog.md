# Release WPCS Backlog

Scope note: remediation excludes `wp/wp-content/plugins/mhm-rentiva`; this backlog only covers the isolated `chore/release-wpcs` worktree surface.

Ordering used here follows the requested priority: `mhm-rentiva.php`, `uninstall.php`, `src/Admin/**` security and output hotspots, `src/Core/**` shared sanitization/SQL/helper code, `templates/**` escaping and output fixes, then remaining structural conventions.

## Blocker
1. `src/Core/Financial/PolicyRepository.php` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | lines `44, 63` | Replace interpolated table SQL with prepared statements before any release cut.
2. `src/Core/Orchestration/ControlPlaneGuard.php` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | lines `75, 100` | Prepare tenant and metric lookups; no direct table interpolation.
3. `src/Admin/Booking/Meta/BookingMeta.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `310, 313, 337, 340` | Gate the booking save flow with a nonce and sanitize every submitted field before persistence.
4. `src/Admin/PostTypes/Payouts/PostType.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `75, 238, 263` | Protect payout admin actions with nonce checks and sanitize request data.

## Release-Critical
1. `uninstall.php` | `Generic.PHP.RequireStrictTypes`, `Generic.Files.EndFileNewline` | lines `1, 43` | Keep the uninstall bootstrap strict and deterministic; confirm the uninstall guard remains intact.
2. `src/Admin/Frontend/Account/AccountController.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing`, `WordPress.WP.GlobalVariablesOverride.Prohibited` | lines `189, 473, 478, 495` | Harden account form handlers, validate request method/nonce, and sanitize payloads.
3. `src/Admin/Booking/Meta/BookingPortalMetaBox.php` | `WordPress.Security.ValidatedSanitizedInput.Missing` | line `126` | Sanitize `$_POST['booking_id']` before use; keep the rest of the meta box cleanup in follow-up.
4. `src/Admin/REST/Helpers/AuthHelper.php` | `WordPress.PHP.DiscouragedPHPFunctions` | lines `215, 218, 219, 237, 250` | Review the token encoding/decoding path, replace `json_encode()` with `wp_json_encode()`, and confirm the base64 framing is intentional.
5. `templates/account/vendor-ledger.php` | `WordPress.Security.NonceVerification.Missing`, `Generic.PHP.RequireStrictTypes` | lines `1, 27, 28` | Protect the ledger form submission and keep the template bootstrap standards-compliant.

## Cleanup
1. `mhm-rentiva.php` | `Generic.PHP.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.Formatting.SpaceAfterCast` | lines `61, 133, 206, 295, 308, 318` | Normalize the bootstrap syntax only; no behavior change expected.
2. `src/Admin/Core/SecurityHelper.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.PHP.DisallowShortTernary.Found`, `Generic.Formatting.SpaceAfterCast` | lines `20, 32, 46, 66, 83, 102, 129, 157, 180, 198, 199` | Clean up helper formatting after the security-focused fixes land.
3. `src/Admin/About/Tabs/DeveloperTab.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `25, 51, 56, 61, 66, 208` | Tidy the tab class formatting and array layout.
4. `templates/admin/dashboard/index.php` | `WordPress.WP.GlobalVariablesOverride.Prohibited`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.PHP.DisallowShortTernary.Found` | lines `22, 29, 43, 52, 55, 58, 61, 89, 92, 95, 153, 194` | Remove the global override and leave the dashboard template as a pure presentation layer.
