# License Renewal Phase 3 — Next Session Brief

**Date:** 2026-04-27
**Status:** Phase 1 + Phase 2 tamam (16 commit, 4 repo branch pushed). Yeni seansta Phase 3A + 3B + 4 + 5 yapılacak.

## Yeni seansa başlangıç sırası

1. Hot.md zaten yeni durum kaydedildi — yeni seans onu okuduğunda Phase 1+2 sonuçları görünür.
2. Bu brief dosyasını oku — Phase 3 görev özeti + branch state'leri.
3. Plan dosyasını oku: [`2026-04-26-license-renewal-plan.md`](2026-04-26-license-renewal-plan.md) — Phase 3A ve 3B task'ları satır numarası referansları aşağıda.
4. Spec dosyasını oku: [`2026-04-26-license-renewal-design.md`](2026-04-26-license-renewal-design.md) — sadece "mhm-rentiva v4.32.0" ve "mhm-currency-switcher v0.7.0" bölümleri.
5. Phase 0 audit notları: [`2026-04-26-phase0-notes.md`](2026-04-26-phase0-notes.md) — constant adları + REST namespace + visibility audit sonuçları (Phase 3'te ihtiyaç var).

## Branch state özeti (yeni seans audit etmeli)

| Repo | Branch | Commits | Origin | ZIP |
|---|---|---|---|---|
| `MaxHandMade/mhm-license-server` | `feature/license-renewal-server-v1.11.0` | 5 | ✅ pushed | ✅ local `build/mhm-license-server.1.11.0.zip` (191.7 KB, 48 dosya) |
| `MaxHandMade/wpalemi` (polar-bridge) | `feature/license-renewal-bridge-v1.9.0` | 8 | ✅ pushed | ❌ ZIP henüz yok (Phase 4 sonrası) |
| `MaxHandMade/mhm-rentiva` | `main` (henüz feature branch yok) | — | — | — |
| `MaxHandMade/mhm-currency-switcher` | `main` veya `develop` (henüz feature branch yok) | — | — | — |
| `MaxHandMade/mhm-rentiva-docs` (paylaşılan) | `main` | 0 (Phase 3 sonrası) | — | — |

Bu seansta Phase 3'te yeni feature branch'ler açılacak:
- `mhm-rentiva`: `feature/license-renewal-rentiva-v4.32.0` (main'den)
- `mhm-currency-switcher`: `feature/license-renewal-cs-v0.7.0` (`develop`ten — fingerprint.md'ye göre default branch develop)

## Phase 3A — mhm-rentiva v4.32.0 (~2.5 saat)

Plan dosyasında: Phase 3A task'ları C.1 → C.9.

### Task scope özeti

- **C.1** `LicenseManager::createCustomerPortalSession()` public method (TDD, 5 test) — plan satır ~2125-2168
- **C.2** `LicenseAdmin::handle_manage_subscription` admin-post handler (4 test) — plan satır ~2170-2220
- **C.3** License sayfasında buton render + `compute_days_remaining()` + `compute_emphasis_class()` helpers (5 test) — plan satır ~2222-2300
- **C.4** `assets/css/license-admin.css` state-driven emphasis (≤30 yellow, ≤7 amber, expired red) + enqueue with `filemtime()` cache busting — plan satır ~2302-2350
- **C.5** `display_admin_notices` switch case `manage_unavailable` + `get_manage_unavailable_label()` helper — plan satır ~2352-2390
- **C.6** i18n: 6 yeni string TR çeviri + msgmerge fuzzy temizle + .mo + .l10n.php + cache flush — plan satır ~2392-2430
- **C.7** Version bump 4.31.2 → 4.32.0 (`MHM_RENTIVA_VERSION` constant — Phase 0'da onaylandı) + readme.txt + README.md/README-tr.md badge + changelog.json/changelog-tr.json — plan satır ~2432-2470
- **C.8** ZIP build (`python bin/build-release.py`) + branch push (tag + release Phase 4 sonrası) — plan satır ~2472-2510
- **C.9** wp-conductor release gate TodoList — plan satır ~2512-2540

### Phase 3A önemli detaylar (yeni seans için)

- `mhm-rentiva` baseline: 793 test, 2768 assertion, 7 skipped, 0 PHPCS (Phase 0 audit'te ölçüldü). Hedef: ~810 test (+17 yeni).
- Constant adları: `MHM_RENTIVA_VERSION` ✅ doğrulandı
- Mevcut License sayfası kodu: `c:/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php` — Manage Subscription butonu "License Management" section'ında, "Re-validate Now"dan ÖNCE eklenir
- `LicenseManager` request() RSA imza verify pattern'i v4.30.0+ hazır — yeni metodu sarmaya ekstra kod gerek yok
- Docker container: `rentiva-dev-wpcli-1`
- Plugin path in container: `/var/www/html/wp-content/plugins/mhm-rentiva`

## Phase 3B — mhm-currency-switcher v0.7.0 (~2 saat)

Plan dosyasında: Phase 3B task'ları D.0 → D.8.

### Task scope özeti

- **D.0** `get_site_hash` visibility — Phase 0'da `public` olduğu doğrulandı, **bu task ATLANIR**
- **D.1** `LicenseManager::create_customer_portal_session()` snake_case (5 test) — plan satır ~2570-2610
- **D.2** REST endpoint `/mhm-currency/v1/license/manage-subscription` + handler (5 test) — plan satır ~2612-2670
  - REST namespace: `mhm-currency/v1` (Phase 0'da doğrulandı)
  - REST controller: `c:/projects/mhm-currency-switcher/src/Admin/RestAPI.php` (Phase 0'da bulundu, line 41 `NAMESPACE_V1` constant)
- **D.3** `License.jsx` `handleManageSubscription` callback + state + buton render — plan satır ~2672-2730
  - File path: `c:/projects/mhm-currency-switcher/admin-app/src/components/tabs/License.jsx`
  - Mevcut `daysRemaining` ve `isExpiringSoon` helper'ları zaten var (line 78, 99)
  - Buton Deactivate'in SOLUNA, "License Management" zone'unda
- **D.4** `style.css` emphasis classes — plan satır ~2732-2770
  - File path: `c:/projects/mhm-currency-switcher/admin-app/src/style.css`
  - `!important` zorunlu (WP admin button specificity)
- **D.5** i18n: 4 yeni string TR çeviri — plan satır ~2772-2800
- **D.6** Version bump 0.6.5 → 0.7.0 (`MHM_CS_VERSION` constant — **plan'da `MHM_CURRENCY_SWITCHER_VERSION` öngörülmüştü, gerçek `MHM_CS_VERSION`** — Phase 0'da düzeltildi) + readme + README + changelog — plan satır ~2802-2840
- **D.7** Build (`npm run build` admin-app dist) + ZIP build + branch push (tag/release Phase 4 sonrası) — plan satır ~2842-2880
- **D.8** wp-conductor release gate TodoList — plan satır ~2882-2920

### Phase 3B önemli detaylar (yeni seans için)

- `mhm-currency-switcher` baseline: 148 test, 308 assertion, 1 skipped, **PHPCS baseline 27 errors** (Phase 0'da ölçüldü). Hedef: ~158 test (+10 yeni). PHPCS baseline 27 korunmalı (yeni hata 0).
- Constant adı: `MHM_CS_VERSION` ✅ doğrulandı (plan'da `MHM_CURRENCY_SWITCHER_VERSION` öngörülmüştü, gerçek `MHM_CS_VERSION`)
- CS PHPUnit özel komut: `WP_TESTS_DIR=/nonexistent vendor/bin/phpunit` (fingerprint.md'de belirtildi)
- `get_site_hash`: public ✅ (Phase 0)
- Docker container: `rentiva-dev-wpcli-1` (CS plugin rentiva-dev stack'inde mounted)
- Plugin path in container: `/var/www/html/wp-content/plugins/mhm-currency-switcher`
- Repo PUBLIC, default branch `develop`
- CI matrisi: PHPUnit (PHP 7.4/8.1/8.2/8.3 × WP 6.0/6.4/latest = 6 jobs) + PHPCS + PHPStan level 6 — push sonrası kontrol

## Phase 4 — E2E Sandbox 5 Senaryo (~1 saat manuel)

Plan dosyasında satır ~2922-3020. **Bu phase manuel — ben dispatch yapamam, kullanıcı yapar.** Phase 3A+3B sonrası kullanıcı:

1. Sandbox subscription buy (Polar Sandbox, test card 4242 4242 4242 4242)
2. mhmrentiva.com (veya CS sandbox WP) plugin License sayfasında Manage Subscription tıkla → Polar customer portal yeni sekmede açılır
3. Sandbox subscription period_end manipüle → emphasis state geçişi (yellow/amber/red)
4. Renewal reminder email — `wp cron event run mhm_polar_renewal_reminder_daily` → email Mailpit/Gmail'de
5. Idempotency — cron tekrar çalışsa email tekrar gelmemeli
6. Reset — subscription.updated webhook simulate → renewal_reminder_sent_at clear → bir sonraki cron yeni email göndermeli

## Phase 4 sonrası: Tag + Release + Merge (4 repo atomik)

Phase 4 5/5 yeşilse:

1. **license-server v1.11.0**: `git tag v1.11.0 → push tag → gh release create v1.11.0 build/mhm-license-server.1.11.0.zip` (ZIP zaten hazır local'de)
2. **polar-bridge v1.9.0**: ZIP build önce → `git tag polar-bridge-v1.9.0 → push tag → gh release create` (wpalemi monorepo, prefix'li tag)
3. **mhm-rentiva v4.32.0**: ZIP build (Phase 3A subagent yapacak) → `git tag v4.32.0 → push tag → gh release create v4.32.0 build/...zip` + docs blog (EN+TR)
4. **mhm-currency-switcher v0.7.0**: ZIP build (Phase 3B subagent yapacak) → `git tag v0.7.0 → push tag → gh release create v0.7.0 build/...zip` + docs blog (EN+TR)
5. Branch'leri main'e merge: 4 repo için sıralı PR (server → bridge → plugins paralel)

## Phase 5 — Production Deploy Checklist (~30 dk planlama)

Plan dosyasında satır ~3022-3100. Bu **çalıştırma değil planlama**: ayrı dosyaya production deploy adımları kaydedilir, kullanıcı production'a açma kararı verdiğinde uygulanır.

## Yeni seans için ilk komutlar

```bash
# 1. Branch state'leri audit et (her birini Phase 3 başlangıcında doğrula)
cd c:/projects/rentiva-lisans/plugins/mhm-license-server && git branch --show-current && git log --oneline -3
cd c:/projects/wpalemi && git branch --show-current && git log --oneline -3
cd c:/projects/rentiva-dev/plugins/mhm-rentiva && git branch --show-current && git log --oneline -3
cd c:/projects/mhm-currency-switcher && git branch --show-current && git log --oneline -3

# 2. Phase 3A için Rentiva feature branch aç (main'den)
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git checkout main
git checkout -b feature/license-renewal-rentiva-v4.32.0

# 3. Test baseline doğrula
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage" | tail -5
# Beklenen: OK (793 tests, 2768 assertions, 7 skipped)

# 4. Phase 3A Task C.1 implementer subagent dispatch
# (plan dosyasını referans alarak)
```

## Mevcut docker-compose.yml dirty (uncommitted)

`c:/projects/wpalemi/docker-compose.yml` dosyasında kullanıcının uncommitted değişikliği var (Phase 0+1+2 boyunca dokunulmadı). Yeni seansta da bu dirty state korunmalı (kullanıcının in-progress işi).

## Critical öğrenmeler (memory'ye yazılmış)

- [feedback_verified_claims_only.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_verified_claims_only.md) — Phase 0'da çalıştırıldı, "X yok/var" iddialarında en az iki bağımsız kaynaktan doğrula
- Mevcut Polar API token canlı wpalemi.com'da tanımlı (lokal Docker'da değil — bu doğal). Phase 4 sandbox testleri canlı wpalemi.com'da yapılır.

## Bilinen küçük cosmetic notlar (sonraki PR'da)

Phase 1 ve Phase 2 review'larında flagged minor items (blocker değil):

**License-server v1.11.0:**
- Test 1 column type/nullability assertion (varchar 64 NULL) eksik — sadece column existence
- Test `testReturns401WhenHmacSignatureInvalid` aslında missing-API-key path test ediyor, signature flip değil
- Happy-path test envelope fields (signature/issued_at/nonce) ayrıca assert etmiyor

**Polar-bridge v1.9.0:**
- Bootstrap line 38 `init` wrap dışında (RenewalReminderCron) — diğerleri `init` içinde, kozmetik tutarsızlık
- `get_users` 5k+ subscriber'da batch processing önerisi (v1.9.x)
- Escape-hatch constant `MHM_POLAR_RENEWAL_REMINDER_DISABLE` yok (operational kill-switch)
- `license_index` drift teorik race (webhook+cron aynı tick'te) — `polar_subscription_id` lookup'a geçmek ileride sertleştirme
- Operational visibility: `last_run` timestamp option (v1.9.x)
