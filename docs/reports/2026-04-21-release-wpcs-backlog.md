# Release WPCS Backlog

Scope note: remediation excludes `wp/wp-content/plugins/mhm-rentiva`; this backlog only covers the isolated `chore/release-wpcs` worktree surface.

Priority order applied in this document:
1. `mhm-rentiva.php`
2. `uninstall.php`
3. `src/Admin/About/**` security and output hotspots
4. `src/Core/**` shared sanitization, SQL, and helper code
5. `templates/**` escaping and output fixes
6. remaining structural conventions

The items below are grouped by severity, but the ordering inside the document follows the release surface above so bootstrap files lead, then admin/core security work, then templates, then cleanup.

## Blocker
1. `src/Core/Financial/PolicyRepository.php` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | lines `44, 63` | Replace interpolated table lookups with prepared statements before release.
2. `src/Core/Orchestration/ControlPlaneGuard.php` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | lines `75, 100` | Convert tenant and metric queries to `prepare()` so SQL is no longer interpolated.
3. `src/Admin/Booking/Meta/BookingMeta.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `310, 313, 337, 340` | Add nonce checks and sanitize all booking meta writes.
4. `src/Admin/Settings/ShortcodePages/ShortcodePageAjax.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing`, `WordPress.Security.SuperGlobalInputUsage` | lines `238, 263, 310, 340` | Harden the shortcode page AJAX handler before exposing it to privileged users.

## Release-Critical
1. `uninstall.php` | `Generic.PHP.RequireStrictTypes`, `Generic.Files.EndFileNewline` | lines `1, 43` | Keep uninstall bootstrap deterministic and standards-compliant.
2. `src/Admin/Frontend/Account/AccountController.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing`, `WordPress.WP.GlobalVariablesOverride.Prohibited` | lines `189, 478, 495` | Validate the account form flow and sanitize request data end to end.
3. `src/Admin/PostTypes/Payouts/PostType.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `75, 238, 263` | Treat payout admin actions as privileged writes and secure their inputs.
4. `src/Admin/Booking/Meta/BookingPortalMetaBox.php` | `WordPress.Security.ValidatedSanitizedInput.Missing` | line `126` | Sanitize `$_POST['booking_id']` before the portal meta box consumes it.
5. `src/Admin/REST/Helpers/AuthHelper.php` | `WordPress.PHP.DiscouragedPHPFunctions` | lines `215, 218, 219, 237, 250` | Review the token encoding path, replace `json_encode()` with `wp_json_encode()`, and confirm the base64 framing is intentional.
6. `templates/account/vendor-ledger.php` | `Generic.PHP.RequireStrictTypes`, `WordPress.Security.NonceVerification.Missing` | lines `1, 27, 28` | Protect the ledger submission path and keep the template bootstrap strict.

## Cleanup
1. `mhm-rentiva.php` | `Generic.ControlStructures.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.Formatting.SpaceAfterCast` | lines `61, 133, 206, 295, 308, 318` | Normalize bootstrap syntax only; no behavior change is expected.
2. `src/Admin/About/About.php` | `Generic.WhiteSpace.ScopeIndent`, `WordPress.WhiteSpace.PrecisionAlignment` | line `53` | Fix the single indentation/alignment issue in the About shell.
3. `src/Admin/About/Tabs/GeneralTab.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.Formatting.SpaceAfterCast` | lines `30, 182` | Clean up the class declaration and parenthesis spacing only.
4. `src/Admin/About/Tabs/SupportTab.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `27, 58, 63, 87, 94` | Tidy the support tab class and its inline arrays without changing behavior.
5. `src/Admin/Core/SecurityHelper.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.ControlStructures.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.Formatting.SpaceAfterCast` | lines `20, 32, 46, 66, 83, 102, 129, 157, 180, 198, 199` | Normalize helper formatting after the security-heavy fixes land.
6. `src/Admin/Settings/Core/SettingsSanitizer.php` | `Generic.ControlStructures.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `301, 418, 419, 420, 421, 422, 423, 424` | Keep the sanitizer changes scoped to syntax cleanup and array layout.
7. `src/Admin/Frontend/Account/AccountRenderer.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.Formatting.SpaceAfterCast`, `Generic.PHP.StrictComparisons.StrictComparison` | lines `28, 40, 64, 163, 164, 196` | Leave the renderer as a presentation-only cleanup pass once controller issues are fixed.
8. `templates/admin/dashboard/index.php` | `WordPress.WP.GlobalVariablesOverride.Prohibited`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.ControlStructures.DisallowShortTernary.Found` | lines `22, 29, 43, 52, 55, 58, 61, 89, 92, 95, 153, 194` | Remove the global override and keep this template in presentation-only shape.
