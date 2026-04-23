# Popüler Transfer Rotaları Shortcode — v4.28.0 Plan

**Tarih:** 2026-04-15 (revize: rota konseptine pivot + Lite desteği) — versiyon numarası 2026-04-23'te v4.26.9 → v4.28.0'a güncellendi (v4.27.0 WPCS release prep'e, v4.27.1 i18n locale-leak hotfix'e, v4.27.2 Arama UX + Shortcode Review Backlog'a ayrıldı; bu yeni-shortcode/block/widget özelliği minor bump hak ediyor)
**Hedef versiyon:** v4.28.0 (minor — yeni shortcode+block+widget, tek başına release)
**Durum:** Beyin fırtınası / tartışma dokümanı
**Referans tasarım:** Kullanıcı tarafından paylaşılan Stitch mock (Neden VIP Transfer? + Popüler Rotalar bölümü)

## Amaç

Ana sayfada "Popüler Rotalar" bölümü — `İstanbul Havalimanı → Taksim` gibi A→B rota kartları, her biri süre (~45dk), araç tipi (Sedan/Vito) ve başlangıç fiyatı (₺850) ile. Ziyaretçiye "biz **transfer** hizmeti de veriyoruz, şu rotalarda şu fiyatlarla" mesajını landing page'de güçlü şekilde verir.

**Önemli pivot:** İlk plan "lokasyonlar showcase"iydi. Kullanıcının paylaştığı Stitch tasarımı aslında **rotaları** (origin→destination çifti) gösteriyor — çok daha iyi bir satış argümanı. Plan yeniden yazıldı.

## Referans Tasarım Özeti

Ekran görüntüsünde (Stitch):
- **Başlık:** "Popüler Rotalar" + alt başlık "En çok tercih edilen transfer güzergahları"
- **Sağ üst:** "Tümünü Gör →" linki
- **Grid:** 3 sütun × 2 satır (6 kart, tablet 2, mobil 1)
- **Kart anatomisi:**
  - Üst satır: şehir (küçük uppercase "İSTANBUL") + sağda tip ikonu (✈️🚆🏨⛵)
  - Başlık: "İstanbul Havalimanı → Taksim" (bold)
  - Meta: `⏱ ~45dk` + `🚗 Sedan/Vito`
  - Alt: büyük fiyat `₺850` + sağda "Başlayan fiyatlarla"
- **Tema:** Light (beyaz kart, ince gri border, mavi accent #1e6bf5)
- **Tipografi:** Modern sans-serif (Inter/Poppins ailesi)

## Mevcut Altyapı (Keşif)

### Rota Tablosu: `{prefix}rentiva_transfer_routes`
```sql
id              bigint(20) AUTO_INCREMENT
origin_id       bigint(20)              → transfer_locations.id
destination_id  bigint(20)              → transfer_locations.id
distance_km     float
duration_min    int(11)                 ✅ kart "~45dk"
pricing_method  enum('fixed','calculated')
base_price      decimal(10,2)
min_price       decimal(10,2)           ✅ kart "Başlayan ₺850"
max_price       decimal(10,2)
created_at      datetime
```

### Lokasyon Tablosu: `{prefix}rentiva_transfer_locations`
```
id, name, type (airport/train/hotel/marina/center), city, allow_transfer, is_active, priority
```

### Karta Bağlantı
| Kart Alanı | Veri Kaynağı |
|------------|-------------|
| "İSTANBUL" (şehir) | `origin.city` |
| ✈️ ikon (sağ üst) | `origin.type` → type ikon map'i |
| "İstanbul Havalimanı → Taksim" | `origin.name` + ` → ` + `destination.name` |
| "Ortalama ~45 dk" | `route.duration_min` (null değilse) — "Ortalama" etiketi ile, trafiğe göre değişir disclaimer'ı |
| "~35 km" | `route.distance_km` (null/0 değilse) |
| "Trafiğe göre değişebilir" | Statik disclaimer, kart altında küçük gri metin |
| "₺850" | `route.min_price` (calculated ise), `base_price` (fixed ise) |

### Araç Tipi Gösterilmeyecek (Karar: 2026-04-15)
Rentiva'da rota → sabit araç eşleşmesi yok; kullanıcı rezervasyonda araç seçer. Kart **sadece lokasyon/rota odaklı** kalacak — "Sedan/Vito" metni kaldırıldı. Bu tasarımı daha temiz yapar, Stitch mockup'taki 3. meta satırı sadece süre ile doldurulur (veya süre + mesafe).

## Shortcode Tasarımı

### Attribute

```
[rentiva_popular_routes
    limit="6"                                // Max kart (default 6)
    columns="3"                              // Desktop sütun (2 | 3 | 4)
    order="featured"                         // featured | price_asc | price_desc | alphabetical | newest
    heading="Popüler Rotalar"                // Block başlığı
    subheading="En çok tercih edilen transfer güzergahları"
    show_view_all="true"                     // "Tümünü Gör" linki
    view_all_url=""                          // Boşsa transfer search sayfasına yönlenir (otomatik)
    show_duration="true"                     // Ortalama ~45 dk
    show_distance="true"                     // ~35 km
    show_traffic_note="true"                 // "Trafiğe göre değişebilir" disclaimer
    show_price="true"                        // ₺850
    currency_symbol="₺"                      // (WC Currency entegrasyonu ilerleyen versiyonda)
    filter_origin_city=""                    // Sadece İstanbul çıkışlı, vb.
    filter_origin_type=""                    // Sadece havalimanı, vb.
    featured_only="false"                    // true → sadece is_featured=1 rotalar
    theme="light"                            // light | dark (premium alt sayfalar için)
]
```

### `order="featured"` — Admin "Vitrine Koy" Checkbox'ı (Karar: 2026-04-15)

Schema'ya yeni kolon eklenecek:
```sql
ALTER TABLE {prefix}rentiva_transfer_routes
  ADD COLUMN is_featured tinyint(1) NOT NULL DEFAULT 0,
  ADD KEY is_featured (is_featured);
```

Admin Transfer Routes sayfasında her rota satırında "🌟 Vitrine Koy" checkbox'ı. İşaretli rotalar ana sayfa shortcode'unda gösterilir.

**Sıralama davranışı:**
- `order="featured"` (default) → `is_featured=1` önce (pinned), sonra `created_at DESC`
- `featured_only="true"` → sadece `is_featured=1` (vitrin dışı hiçbir rota gösterme)
- Admin hiç vitrin seçmediyse → tüm aktif rotalar `created_at DESC` (fallback)

**Migration:** `DatabaseMigrator::create_transfer_routes_table()` güncellenecek + versiyon bump ile otomatik migration çalışacak.

### Layout Örneği (Grid — Light Theme)

```
┌─────────────────────────────────────────────────────────────────┐
│             Popüler Rotalar                      Tümünü Gör →   │
│  En çok tercih edilen transfer güzergahları                      │
├──────────────┬──────────────┬──────────────────────────────────┤
│ İSTANBUL  ✈️ │ İSTANBUL  👍 │ İSTANBUL         ↗              │
│              │              │                                   │
│ İst. Hav. →  │ Sabiha Gökçen│ İst. Hav. →                     │
│ Taksim       │ → Beşiktaş   │ Kadıköy                         │
│ ⏱~45dk 🚗Sedan│ ⏱~60dk 🚗Sedan│ ⏱~55dk 🚗Sedan/Vito             │
│ ₺850 Başlayan│ ₺850         │ ₺850                            │
└──────────────┴──────────────┴──────────────────────────────────┘
```

## Lite / Pro Stratejisi

**Karar: Lite + Pro (kullanıcı isteği 2026-04-15).**

### Lite Limiti: 3 Rota (doğrulandı 2026-04-15)
`ProFeatureNotice::displayLimitNotice('routes')` zaten Lite → max 3 rota enforcement uyguluyor. Yani Lite kullanıcı transfer admin sayfasında en fazla **3 rota tanımlayabilir**. Shortcode Lite'ta çalıştığında doğal olarak max 3 kart gösterir.

### Layout Adaptasyonu

| Versiyon | Maksimum Kart | Önerilen Grid |
|----------|---------------|---------------|
| Lite     | 3             | 3 sütun × 1 satır (desktop) / 1 sütun × 3 satır (mobile) |
| Pro      | Sınırsız      | 3 sütun × 2+ satır (limit attr'a göre) |

`limit` attribute Lite'ta effective limit `min(limit, 3)` olarak hesaplanır. Tasarım Lite'ta da **dolu** hissi verir — "az ama premium" — boşluk oluşmaz.

### "Tümünü Gör" Linki — Lite'ta Davranış
Lite kullanıcıda sadece 3 rota var ve hepsi zaten görünüyor. Link Lite'ta:
- **(a) Gizle** (`if !is_pro && route_count <= 3 → hide link`) — **önerim**, dürüst
- (b) Yine göster, arşiv sayfasına yönlendir
- (c) "Daha fazla rota için Pro'ya geç" Pro upsell linki — agresif, önerilmez

### Empty State (0 Rota)
Eğer hiç rota tanımlanmamışsa:
- Default: `[rentiva_popular_routes]` **hiç render etmez** (silent no-op)
- Opsiyonel: `empty_state="demo"` → DemoSeeder rotaları render (sadece local dev/demo için)

Gerekçe silent no-op: Lite kullanıcı yeni kurulumda ana sayfaya shortcode koyabilir, henüz rota eklememiş olabilir — boş "Yakında..." placeholder yerine section'ın görünmemesi daha temiz.

## Mimari

### Render Parity
Plugin kuralı: block + widget shortcode'a delege eder.
```
TransferRoutesShowcase → shortcode (kanonik render)
  ↑
  ├─ Gutenberg block (render callback → do_shortcode)
  └─ Elementor widget (render → shortcode)
```

### Dosya Planı
```
src/Admin/Transfer/Frontend/PopularRoutesShortcode.php        [YENİ]
assets/blocks/popular-routes/block.json                        [YENİ]
assets/blocks/popular-routes/block.js                          [YENİ]
assets/blocks/popular-routes/render.php                        [YENİ — delegates to shortcode]
assets/css/frontend/popular-routes.css                         [YENİ]
src/Admin/Frontend/Widgets/Elementor/PopularRoutesWidget.php   [YENİ]
src/Admin/Core/AllowlistRegistry.php                           [UPDATE — canonical attrs]
src/Admin/Core/ShortcodeServiceProvider.php                    [UPDATE — register]
src/Blocks/BlockRegistry.php                                   [UPDATE — register]
src/Admin/Frontend/Widgets/Elementor/ElementorIntegration.php  [UPDATE — register]
tests/Transfer/PopularRoutesShortcodeTest.php                  [YENİ]
tests/Fixtures/TransferRouteFixture.php                        [YENİ — test data factory]
```

### Veri Katmanı
Yeni bir repository class önerisi (`LocationProvider` pattern'i ile):
```php
final class TransferRouteProvider {
    public static function get_popular_routes(int $limit = 6, string $order = 'created_at', array $filters = []): array;
    public static function clear_cache(): void;
    // Transient: mhm_rentiva_popular_routes_{hash}
    // TTL: 1 saat, admin CRUD sonrası invalidation (mevcut LocationProvider pattern'i)
}
```
JOIN: `transfer_routes r JOIN transfer_locations o ON r.origin_id = o.id JOIN transfer_locations d ON r.destination_id = d.id WHERE o.is_active = 1 AND d.is_active = 1 AND o.allow_transfer = 1 AND d.allow_transfer = 1`.

## Kararlar (Kullanıcı Onayı 2026-04-15)

| Konu | Karar |
|------|-------|
| Vehicle label (Sedan/Vito) | **Kaldırıldı** — Rentiva'da sabit araç yok, lokasyon odaklı kalacak |
| Sıralama | `is_featured` kolonu ekle + admin "Vitrine Koy" checkbox'ı |
| "Tümünü Gör" hedefi | Transfer arama sayfası (site genelinde arama URL'i neyse) |
| WC Currency entegrasyonu | **Ertelendi** — Currency Switcher eklentimizle henüz test edilmedi, sonraki versiyon |
| Empty state | **Silent no-op** — 0 rota varsa section hiç render edilmez |
| Demo veri | **Gerekmez** — admin rota ekledikçe dinamik olarak görünür |

## Test Matrisi

- **PHPUnit:**
  - Shortcode render: 0/1/6/20 rota durumlarında doğru kart sayısı
  - Filtrele: `filter_origin_city="İstanbul"` sadece İstanbul çıkışlı getirir
  - Order: `price_asc` min_price ASC sıralı döner
  - Empty state: 0 rota → boş string render
  - Lite+Pro: ikisinde de render eder
  - Inactive location: `is_active=0` origin veya destination → rota gizlenir
  - Cache: aynı arg'larla ikinci çağrı DB hit etmez
- **Visual regression:** 4 viewport × 2 tema (light/dark) × 2 sütun (3/4)
- **Integration:** Admin rota pasif et → ana sayfa 1 istek içinde güncel
- **Browser smoke:** Chrome-devtools ile kartlar render + hover + Tümünü Gör link + CTA tıklama

## Release Pipeline (v4.28.0)

1. Plan onayı (bu doküman)
2. Opsiyonel: Stitch revize mockup (mevcut tasarım zaten yeterli)
3. Implementasyon (shortcode → CSS → block → widget)
4. PHPUnit yeşil (720+ → 730+ hedef)
5. DemoSeeder rotaları (varsayılan: yukarıdaki 6 Türkiye rotası, soru 6'ya bağlı)
6. Chrome-devtools canlı doğrulama
7. wp-code-reviewer
8. Version bump → changelog → README → readme.txt
9. ZIP + commit + tag + push + GitHub Release
10. Docs blog post (`2026-XX-XX-v4.28.0-release.md`)
11. wp-reflect + memory

## Tahmini Effort

| Adım | Süre |
|------|------|
| Repository + Shortcode PHP | 2-2.5 saat |
| CSS (light theme, responsive grid) | 1.5-2 saat |
| Block.json + block.js + render.php | 1 saat |
| Elementor widget | 1-1.5 saat |
| PHPUnit testler | 1-1.5 saat |
| DemoSeeder fixtures | 30 dk |
| Release pipeline | 45 dk |
| Docs blog | 30 dk |
| **Toplam** | **~9 saat (1 gün)** |

Vehicle label kolonu eklenirse +1 saat (migration + admin form field).

## Karar Özeti (Kullanıcı Onayı Bekleyen)

- ✅ Konsept: **Popüler Rotalar** (A→B çifti, Stitch tasarımına göre)
- ✅ Tema: **Light** (tasarımla uyumlu, `theme="dark"` override desteği)
- ✅ Default layout: 3 sütun × 2 satır grid (desktop)
- ✅ Versiyon: **v4.28.0** (minor — yeni shortcode/block/widget için minor bump; tek başına release)
- ✅ **Lite + Pro** (kullanıcı isteği 2026-04-15) — landing page vitrin özelliği; Lite'ta max 3 rota limitine natural uyum (3×1 grid)
- ✅ Veri kaynağı: mevcut `rentiva_transfer_routes` + `rentiva_transfer_locations` JOIN
- ✅ Vehicle label: **Kaldırıldı** (sabit araç yok, lokasyon odaklı)
- ✅ Sıralama: `is_featured` kolon + admin checkbox + `created_at DESC` fallback
- ✅ "Tümünü Gör": Transfer arama sayfasına yönlenir
- ✅ WC Currency: Ertelendi (sonraki versiyon)
- ✅ Empty state: Silent no-op
- ✅ Demo veri: Gerekmez — admin veri girdikçe dinamik görünür

## Notlar

- "Neden VIP Transfer?" üst bölümü (Profesyonel Şoför, Karşılama Hizmeti, Lüks Araçlar, Sabit Fiyat) **bu shortcode'un kapsamı değil** — o ayrı bir "USP kartları" shortcode'u (zaten planlanabilir ama bu release dışında).
- `show_view_all="true"` + `view_all_url=""` (boş) durumunda: link yerine scroll-to-transfer-search-form anchor davranışı düşünülebilir.
- Shortcode'un bir "hero-adjacent" varyantı (tek satır 4 kart, daha kompakt) eklenebilir ama şimdilik gerekmez.
