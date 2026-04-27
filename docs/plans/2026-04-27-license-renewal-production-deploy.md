# License Renewal — Production Deploy Checklist

> **Durum:** Phase 4 sandbox 5/5 PASS (2026-04-27). Bu deploy henüz çalıştırılmadı.
> Brief'in Phase 5 task'ı — Phase 4 sonrası kullanıcı kararı bekleniyor.

## Mevcut state (atomik release sonrası)

| Repo | Sürüm | Tag | GH Release | Sandbox onay |
|---|---|---|---|---|
| mhm-license-server | **v1.11.1** canlıda wpalemi.com | v1.11.1 | ✓ | ✓ Senaryo 1+5 |
| mhm-polar-bridge | **v1.9.1** canlıda wpalemi.com | polar-bridge-v1.9.1 | ✓ | ✓ Senaryo 3+4+5 |
| mhm-rentiva | **v4.32.0** canlıda mhmrentiva.com | v4.32.0 | ✓ | ✓ Senaryo 1+2 |
| mhm-currency-switcher | **v0.7.0** canlıda mhmrentiva.com | v0.7.0 | ✓ | ✓ smoke + parity |

> Yan ürün hotfix'ler ile 4-repo ekosistem **tutarlı + tüm Phase 4 senaryoları yeşil**.

## Bu deploy değil — bu PROD'a swap

Phase 4 testleri **canlı sandbox** modunda yapıldı (Polar Sandbox org + sandbox webhook). Production'a geçiş = sandbox→prod swap işlemi. Aşağıdaki checklist o swap için.

---

## Pre-deploy

### Polar tarafı

- [ ] **Polar production org açıldı** (sandbox'tan ayrı bir org)
- [ ] **Polar production API token üretildi** (Settings → Developer → Personal Access Token)
- [ ] **Polar production webhook endpoint** ayarlandı:
  - URL: `https://wpalemi.com/wp-json/mhm/v1/polar/webhook` (sandbox ile aynı endpoint, sadece secret farklı)
  - Events: `subscription.active`, `subscription.canceled`, `subscription.uncanceled`, `subscription.updated`, `subscription.revoked`, `refund.created`, `refund.updated`
- [ ] **Polar production'da 4 ürün oluşturuldu** (sandbox'tan kopya):
  - MHM Rentiva Pro Monthly (Early Access) — \$19/ay
  - MHM Rentiva Pro Annual (Early Access) — \$99/yıl
  - MHM Currency Switcher Pro Monthly — \$9/ay
  - MHM Currency Switcher Pro Yearly — \$79/yıl
- [ ] **Production'da test edilecek küçük tutarlı bir abonelik** (~\$1) sandbox'ta oluşturuldu

### Sandbox subscription cleanup

- [ ] Phase 4 sandbox boyunca oluşturulan test subscription'lar arşivlendi/silindi (8 active sandbox sub'ı vardı, çoğu eski test E2E)
- [ ] manager58@gmail.com sandbox subscription'ı (sizin canlı Pro test'iniz) korunuyor — production'da yeni satın alma ile değiştirilecek

## wpalemi.com wp-config swap

- [ ] **`MHM_POLAR_API_TOKEN`** → sandbox token'dan production token'a değiştir
- [ ] **`MHM_POLAR_SANDBOX`** → tanımsız bırak (default false) **VEYA** explicit `define('MHM_POLAR_SANDBOX', false);`
- [ ] **`MHM_POLAR_WEBHOOK_SECRET`** → production webhook secret'a güncelle
- [ ] (server tarafı dokunulmaz) — `MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM` aynı kalır, RSA pair müşteri site'lerinde gömülü (v4.31.0 ile shipped)

> **Sırasıyla yap:** önce token, sonra webhook secret. wp-config.php save sonrası **Polar dashboard'da sandbox webhook endpoint'i deactivate** edilebilir (production yeni endpoint zaten URL aynı, secret farklı, yan etkisiz).

## Plugin deploys (deploy zaten yapıldı, sadece doğrulama)

Brief'in atomik plan'ı sandbox modunda yapıldı, production swap'ı tetiklemiyor. **Plugin'lerin yeniden deploy edilmesine gerek yok**, çünkü:
- mhm-license-server v1.11.1 — sandbox/prod env-agnostic (sadece API token/webhook secret prod tarafı)
- mhm-polar-bridge v1.9.1 — webhook receiver, secret değişikliği yeterli
- mhm-rentiva v4.32.0 — client, server endpoint'i aynı URL
- mhm-currency-switcher v0.7.0 — aynı

Yine de doğrulama:
- [ ] Schema migration: `docker exec ... wp eval 'global \$wpdb; var_dump(\$wpdb->get_results("SHOW COLUMNS FROM {\$wpdb->prefix}mhm_licenses LIKE \"polar_customer_id\""));'` → 1 row dönmeli
- [ ] Cron register: `wp cron event list | grep mhm_polar_renewal_reminder` → schedule = `daily`, next_run UTC 06:00
- [ ] Manuel email test (production env): bir test customer için cron tetikle, mailbox kontrol

## Post-deploy smoke test

Production'a geçiş sonrası sandbox'tan farklı bir test:

- [ ] Production'da küçük tutarlı subscription buy (~\$1 test product, gerçek kart)
- [ ] Webhook geldi mi? `tail wp-content/debug.log | grep PolarBridge`
- [ ] License row oluştu (polar_customer_id non-NULL, plan='monthly' veya 'yearly')
- [ ] Customer site'da "Manage Subscription" tıkla → portal açılır → real customer's billing info görünür
- [ ] -7 gün cron simulate (manuel `expires_at` ayarla, cron run, email kontrol)

## Mevcut customer'lara bildirim

Phase 4 sandbox'taki manager58 + dilaminduygu customer'ları aslında **gerçek** müşteriler (kullanıcının kendisi + dilaminduygu). Sandbox'tan production'a geçişte:

- [ ] manager58@gmail.com'a email: "MHM Rentiva subscription is now in production billing. Your sandbox period_end was test data; new billing will follow real Polar subscription cycle."
- [ ] dilaminduygu@gmail.com'a benzer email
- [ ] (opsiyonel) Manuel grace period uygulamak: `expires_at` 30 gün ileri al, müşteriyi bilgilendirme süresi tanı

## Rollback prosedürü

Hata durumunda (production'da kritik bug):

### 1. wp-config swap'ı geri al
- `MHM_POLAR_API_TOKEN` → sandbox token'a geri
- `MHM_POLAR_WEBHOOK_SECRET` → sandbox secret'a geri
- Polar production webhook endpoint deactivate
- Polar sandbox webhook endpoint reactivate

### 2. Plugin downgrade (gerekirse)
- mhm-license-server: `mhm-license-server.bak.1.10.x` rollback klasörü File Manager'da hazır
- mhm-polar-bridge: `mhm-polar-bridge.bak.1.9.0` rollback klasörü
- mhm-rentiva v4.31.2 GH release ZIP indir + müşteri site'lerine deploy
- mhm-currency-switcher v0.6.5 GH release ZIP

### 3. DB rollback (sadece kritik bug)
- `wp_mhm_licenses` tablosundan production'da oluşan yeni row'lar archive et (timestamp filter)
- `polar_customer_id` kolonu DROP edilebilir (v1.10.x öncesi schema)
- Sandbox row'lar (manager58, dilaminduygu, test-e2e) tutulur

## Phase 5 sonrası — yeni sürüm planlama

Production'da bir hafta gözlem sonrası **Phase 6** patch ele alınır:
- `project_pro_gate_unification_v4.33.md` — A2/B/C bug'ları + dev mode bypass
- License-server license-row backfill admin trigger (mevcut row'ların plan/customer_id sync'i için, polar-bridge v1.9.x backfill desenine paralel)
- Email body redundant text cleanup ("Pro Monthly Early Access monthly subscription" → "Pro Monthly subscription")
- changelog.json EN backfill (v4.27.6 → v4.31.2 atlanmış entry'ler)

## Karar gate

Bu plan **çalıştırma değil planlama**. Production swap için kullanıcı kararı bekleniyor:

- [ ] Phase 4 sandbox 5/5 PASS — onaylandı (2026-04-27)
- [ ] Mevcut atomik release ekosistemi tutarlı (4 repo, 4 tag, 4 GH release) — onaylandı
- [ ] **Production swap onayı:** _bekleniyor_
- [ ] Production swap tarihi: _belirsiz_

Kullanıcı "production'a geçelim" dediğinde bu checklist sırayla uygulanır. Tahmini süre: ~1 saat (wp-config swap + Polar production setup + smoke test).
