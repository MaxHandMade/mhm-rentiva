# Release WPCS Backlog

Scope note: remediation excludes `wp/wp-content/plugins/mhm-rentiva`; this backlog only covers the isolated `chore/release-wpcs` worktree surface.

## Priority Queue

### Bootstrap
1. `mhm-rentiva.php` | `Generic.ControlStructures.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.Formatting.SpaceAfterCast` | lines `61, 133, 206, 295, 308, 318` | Normalize the bootstrap syntax first so the release entry point stays clean.
2. `uninstall.php` | `Generic.PHP.RequireStrictTypes`, `Generic.Files.EndFileNewline` | lines `1, 43` | Keep uninstall bootstrap deterministic and standards-compliant.

### Admin Hotspots
3. `src/Admin/About/Tabs/DeveloperTab.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `25, 51, 56, 61, 66, 208` | Tidy the About tab class and its inline arrays without changing behavior.
4. `src/Admin/About/About.php` | `Generic.WhiteSpace.ScopeIndent`, `WordPress.WhiteSpace.PrecisionAlignment` | line `53` | Fix the single indentation and alignment issue in the About shell.
5. `src/Admin/About/Tabs/GeneralTab.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.Formatting.SpaceAfterCast` | lines `30, 182` | Clean up the class declaration and parenthesis spacing only.
6. `src/Admin/About/Tabs/SupportTab.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `27, 58, 63, 87, 94` | Tidy the support tab class and its inline arrays without changing behavior.
7. `src/Admin/Booking/Meta/BookingMeta.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `310, 313, 337, 340` | Add nonce checks and sanitize booking meta writes before persistence.
8. `src/Admin/Settings/ShortcodePages/ShortcodePageAjax.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `238, 263` | Harden the shortcode-page AJAX handler before exposing it to privileged users.
9. `src/Admin/Frontend/Account/AccountController.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing`, `WordPress.WP.GlobalVariablesOverride.Prohibited` | lines `189, 478, 495` | Validate the account form flow and sanitize request data end to end.
10. `src/Admin/PostTypes/Payouts/PostType.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `75, 238, 263` | Treat payout admin actions as privileged writes and secure their inputs.

### Core Hotspots
11. `src/Core/Attribute/AllowlistRegistry.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.ControlStructures.DisallowShortTernary.Found` | lines `19, 30, 35, 40, 41, 46, 47, 52, 57, 62` | Keep the large registry file on a structural cleanup track after the security-sensitive fixes.
12. `src/Core/Financial/PolicyRepository.php` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | lines `44, 63` | Replace interpolated table lookups with prepared statements before release.
13. `src/Core/Orchestration/ControlPlaneGuard.php` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | lines `75, 100` | Convert tenant and metric queries to `prepare()` so SQL is no longer interpolated.

### Template Hotspots
14. `templates/admin/reports/bookings.php` | `Generic.PHP.RequireStrictTypes`, `WordPress.WP.GlobalVariablesOverride.Prohibited`, `Generic.ControlStructures.DisallowShortTernary.Found` | lines `1, 64, 72, 74, 105` | Remove the status globals and keep the report template presentation-only.
15. `templates/account/bookings.php` | `Generic.PHP.RequireStrictTypes` | line `1` | Add the missing strict-types declaration and keep the account listing bootstrap clean.
16. `templates/admin/dashboard/recent-bookings.php` | `WordPress.WP.GlobalVariablesOverride.Prohibited`, `Generic.WhiteSpace.ScopeIndent` | lines `56, 57, 58, 70` | Stop overriding status/type globals in the dashboard widget.
17. `templates/account/vendor-ledger.php` | `Generic.PHP.RequireStrictTypes`, `WordPress.Security.NonceVerification.Missing` | lines `1, 27, 28` | Protect the ledger submission path and keep the template bootstrap strict.

### Structural Cleanup
18. `templates/admin/dashboard/index.php` | `WordPress.WP.GlobalVariablesOverride.Prohibited`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.ControlStructures.DisallowShortTernary.Found` | lines `22, 29, 43, 52, 55, 58, 61, 89, 92, 95, 153, 194` | Remove the global override and keep this template in presentation-only shape.

## Blocker
1. `src/Admin/Booking/Meta/ManualBookingMetaBox.php` | `WordPress.Security.NonceVerification.Missing`, `WordPress.Security.ValidatedSanitizedInput.Missing` | lines `310, 313, 337, 340` | Add nonce verification and sanitize the manual booking payload before any save path runs.
2. `src/Admin/Booking/Meta/BookingPortalMetaBox.php` | `WordPress.Security.ValidatedSanitizedInput.Missing` | line `126` | Sanitize `$_POST['booking_id']` before the portal meta box consumes it.
3. `src/Api/REST/WebhookRateLimiter.php` | `WordPress.Security.ValidatedSanitizedInput.Missing` | line `82` | Unslash the remote IP before sanitization and keep the rate-limit gate safe.
4. `src/Admin/REST/Helpers/AuthHelper.php` | `WordPress.PHP.DiscouragedPHPFunctions` | lines `215, 218, 237, 250` | Review the token encoding path, replace `json_encode()` with `wp_json_encode()`, and confirm the base64 framing is intentional.

## Release-Critical
1. `src/Admin/REST/ErrorHandler.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `16, 21, 22, 31, 34, 35, 78, 100, 108` | Clean up the REST error envelope while preserving the response contract.
2. `src/Admin/Settings/Core/SettingsSanitizer.php` | `Generic.ControlStructures.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing` | lines `301, 418, 419, 420, 421, 422, 423, 424` | Keep the sanitizer changes scoped to syntax cleanup and array layout.
3. `templates/admin/reports/overview.php` | `Generic.PHP.RequireStrictTypes`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.WP.GlobalVariablesOverride.Prohibited` | lines `1, 39, 117, 125, 127, 185, 252` | Fix the report view so it stops overriding globals and using discouraged JSON helpers.
4. `templates/admin/reports/customers.php` | `Generic.PHP.RequireStrictTypes`, `Generic.ControlStructures.DisallowShortTernary.Found` | lines `1, 58` | Add the missing bootstrap strictness and remove the short ternary.
5. `templates/admin/dashboard/transfer-widget.php` | `WordPress.WP.GlobalVariablesOverride.Prohibited`, `Generic.ControlStructures.DisallowShortTernary.Found` | lines `56, 60, 61, 62` | Remove the widget's global overrides and replace short ternaries.

## Cleanup
1. `src/Admin/Frontend/Account/AccountRenderer.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.Formatting.SpaceAfterCast`, `Generic.PHP.StrictComparisons.StrictComparison` | lines `28, 40, 64, 163, 164, 196` | Leave the renderer as a presentation-only cleanup pass once controller issues are fixed.
2. `src/Admin/REST/EndpointListHelper.php` | `Generic.PHP.StrictComparisons.StrictComparison` | line `37` | Tighten the `in_array()` call with strict comparison.
3. `src/Admin/REST/Availability.php` | `Generic.ControlStructures.DisallowShortTernary.Found` | line `170` | Replace the short ternary in the endpoint response flow.
4. `src/Admin/Testing/PerformanceTest.php` | `Generic.ControlStructures.DisallowShortTernary.Found` | lines `196, 197, 198, 208, 209, 210, 221, 222` | Convert the performance test helpers to full conditionals.
5. `src/Admin/Testing/SecurityTest.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `WordPress.PHP.EmptyForeach` | lines `14, 130, 190` | Keep the security test class tidy and remove the empty loop body.
6. `src/Admin/Core/SecurityHelper.php` | `Squiz.Classes.ClassDeclaration.OpenBraceSameLine`, `Generic.ControlStructures.DisallowShortTernary.Found`, `WordPress.Arrays.ArrayDeclarationSpacing`, `Generic.Formatting.SpaceAfterCast` | lines `20, 32, 46, 66, 83, 102, 129, 157, 180, 198, 199` | Normalize helper formatting after the security-heavy fixes land.
