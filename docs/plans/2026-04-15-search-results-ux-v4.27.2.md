# Arama Sonuçları UX İyileştirmeleri — v4.27.2 Plan

**Tarih:** 2026-04-15 (versiyon numarası iki kez güncellendi: v4.26.8 → v4.27.1 → v4.27.2. v4.27.0 WPCS release prep'e, v4.27.1 i18n locale-leak hotfix'e ayrıldı.)
**Hedef versiyon:** v4.27.2 (patch — Shortcode Review Backlog ile birleşik release)
**Durum:** Beyin fırtınası / öncelik kararları tamamlandı
**Kaynak:** 2026-04-15 YouTube canlı yayın demo hazırlığı sırasında tespit edildi

## Bağlam

`rentiva_search_results` ve `rentiva_unified_search` shortcode'ları üzerinde yapılan inceleme sırasında tespit edilen iyileştirmeler.
Küçük fix'ler aynı oturumda v4.26.6 kapsamında çözüldü.
Bu plan yalnızca vakit alacak, çoklu dosya gerektiren büyük geliştirmeleri kapsar.

## Kapsam Dışı (v4.26.6'da Yapıldı)

- Favori/karşılaştır buton oval→daire fix (CSS specificity, `.mhm-vehicle-card` parent scope)
- `default_sort` attribute'unun sorguya uygulanmaması (`prepare_template_data()` fix)
- "VIP Transfer" tab label → "Transfer" (`unified-search.php`)
- Bagaj alanı label'larına ℹ️ hover tooltip (`unified-search.php` + `unified-search.css`)

---

## Planlanan Geliştirmeler

### 1. URL State (History pushState) — Öncelik: Yüksek

**Sorun:** AJAX filtre/sıralama değişikliklerinde URL güncellenmez.
- Kullanıcı "BMW + İstanbul + Fiyat artan" filtreliyor → linki paylaşıyor → karşı taraf tüm araçları görüyor
- Geri tuşu çalışmıyor (filtreli sonuçlara dönülemiyor)
- Sayfa yenilenince filtreler kaybolur

**Çözüm:**
```js
// search-results.js — reloadResults() içinde
const params = new URLSearchParams(getCurrentFilters($scope));
history.replaceState(null, '', '?' + params.toString());
```
- `replaceState` (pushState değil) — her filtre değişikliği ayrı history entry oluşturmasın
- Sayfa yüklenişinde URL params → filtre form elementlerine yansıtılmalı (PHP zaten yapıyor, AJAX render sonrasında JS de yapmalı)
- Sıralama değişikliği de URL'e yazılmalı

**Etkilenen dosyalar:**
- `assets/js/frontend/search-results.js`

---

### 2. Aktif Filtre Chip'leri — Öncelik: Orta

**Sorun:** Şu an yalnızca "Tüm Filtreleri Temizle (3)" sayaç butonu var. Hangi filtrelerin aktif olduğu görünmüyor.

**Çözüm:**
Filtreler panelinin üstünde chip listesi:
```
[Ankara ×]  [BMW ×]  [0 – 2.000₺ ×]  [Tümünü Temizle]
```
- Her chip tıklandığında ilgili filtre kaldırılır → `reloadResults()` tetiklenir
- Aktif filtre yoksa chip alanı gizlenir
- Fiyat aralığı: her iki değer de dolu ise tek chip (`₺min – ₺max`)
- Konum chip'i: seçili lokasyon sayısı 1 ise isim, >1 ise "3 Konum"

**Etkilenen dosyalar:**
- `assets/js/frontend/search-results.js`
- `templates/shortcodes/search-results.php` (chip container HTML)
- `assets/css/frontend/search-results.css` (chip stilleri)

---

### 3. Karşılaştırma Floating Bar — Öncelik: Orta

**Sorun:** Kullanıcı araçları karşılaştırmaya ekliyor ama kaç araç seçtiğini ve "Karşılaştır" butonuna nereden erişeceğini bilmiyor. Şu an karşılaştırma sayfasına gitmek için URL'i manuel bilmek gerekiyor.

**Çözüm:**
Sayfanın altında sticky floating bar — 1+ araç karşılaştırmaya eklendiğinde çıkar:
```
┌─────────────────────────────────────────────────────────┐
│  [🚗 BMW] [🚗 Fiat]  +1 daha ekle    [Karşılaştır →]  │
└─────────────────────────────────────────────────────────┘
```
- Araç miniaturü + isim chip'i, × ile kaldırma
- Max 3 araç (mevcut karşılaştırma shortcode limiti)
- "Karşılaştır" butonu → `/eklenti-demolari/demo-vehicle-comparison/?ids=3474,3017,3016` gibi URL oluşturur
- Karşılaştırma sayfası URL'si `rentiva_comparison_page` seçeneğinden alınır
- Floating bar `vehicle-interactions.js` veya yeni `comparison-bar.js` dosyasında

**Etkilenen dosyalar:**
- `assets/js/frontend/vehicle-interactions.js` veya yeni `comparison-bar.js`
- `assets/css/core/vehicle-card.css` veya yeni `comparison-bar.css`
- `src/Admin/Frontend/Shortcodes/SearchResults.php` (karşılaştırma sayfası URL'si JS'e localize edilmeli)

---

### 4. Birleşik Arama — Custom Inline Validasyon — Öncelik: Orta

**Sorun:** VIP Transfer formu browser native HTML5 `required` popup kullanıyor ("Lütfen bu alanı doldurun"). Araç Kiralama ise hiç validasyon göstermiyor (bilerek: boş arama = tüm araçları göster).

**Çözüm:** VIP Transfer formunda browser native yerine custom inline validasyon:
- Her zorunlu alan boş submit'te kırmızı border + alanın altında `"Bu alanı doldurun"` mesajı
- Validasyon JS tarafında `submit` event'inde kontrol edilir, form submit engellenir
- Takvim otomatik açılma kaldırılır, kullanıcı kendi tıklar

**Etkilenen dosyalar:**
- `assets/js/frontend/unified-search.js`
- `assets/css/frontend/unified-search.css` (hata durumu stilleri)

---

### 5. Birleşik Arama — `default_tab` Shortcode Attribute — Öncelik: Düşük

**Sorun:** Sekme her zaman Araç Kiralama'dan açılıyor. Transfer odaklı sayfalarda (havalimanı transfer landing page gibi) Transfer sekmesi varsayılan olsun istenebilir.

**Çözüm:** `default_tab="transfer"` shortcode attribute — zaten template'de `$default_tab` değişkeni var, sadece CAM/AllowlistRegistry'e eklemek yeterli.

**Etkilenen dosyalar:**
- `src/Admin/Settings/ShortcodePages/ShortcodePageActions.php` (default attributes)
- `src/Admin/Core/AllowlistRegistry.php` (allowlist entry)
- `assets/blocks/unified-search/block.json` (block attribute)
- `src/Admin/Frontend/Widgets/Elementor/UnifiedSearchWidget.php` (widget control)

---

### 6. Birleşik Arama — "Farklı Lokasyona Bırak" Settings Toggle — Öncelik: Orta

**Sorun:** Araç Kiralama formunda "Teslim Etme" alanı her zaman görünüyor. Tek ofisi olan bayiler için gereksiz. Çok ofisli bayiler için ise çok faydalı.

**Çözüm:** Plugin settings'e toggle ekle: `Farklı Teslim Lokasyonu ☐ Etkin`
- Etkin değilse `show_dropoff_location` false geçilir → alan template'de gizlenir
- Template zaten `$show_dropoff_location` flag'ini destekliyor
- Shortcode attribute olarak da override edilebilir

**Etkilenen dosyalar:**
- `src/Admin/Settings/Core/SettingsCore.php` (yeni setting key)
- `src/Admin/Frontend/Shortcodes/UnifiedSearch.php` (setting okuma + attribute'a aktarma)
- Settings admin template (toggle UI)

---

### 7. Mobil Filtre Drawer — Öncelik: Düşük

**Sorun:** Filtre paneli her zaman açık, mobilde ekran alanını kaplıyor.

**Çözüm:**
- Mobil (<768px) `rv-filters-sidebar` default gizli
- Sayfanın üstünde "🔍 Filtrele (3 aktif)" butonu → slide-in drawer açar
- Drawer kapatma: backdrop click veya × butonu
- Masaüstünde mevcut davranış korunur

**Etkilenen dosyalar:**
- `assets/js/frontend/search-results.js`
- `templates/shortcodes/search-results.php` (mobil filtre butonu)
- `assets/css/frontend/search-results.css`

---

## Uygulama Sırası (Öneri)

1. **URL State** — bağımsız, JS-only, düşük risk. Diğer özellikler bunun üzerine inşa edilir.
2. **Karşılaştırma Floating Bar** — kullanıcıya direkt görünür fayda, demo değeri yüksek.
3. **Chip'ler** — URL state tamamlandıktan sonra, chip kaldırma URL'i güncellemeli.
4. **Mobil Drawer** — en düşük öncelik, CSS/JS only.

## Tahmini Efor

| Geliştirme | Efor |
|-----------|------|
| URL State | ~2 saat / 1 dosya |
| Floating Bar | ~4 saat / 3-4 dosya |
| Chip'ler | ~3 saat / 3 dosya |
| Mobil Drawer | ~2 saat / 2 dosya |
| **Toplam** | **~11 saat / ~8 dosya** |

## Test Kriterleri

- [ ] Filtre değişince URL güncellenir, sayfa yenilenince aynı filtreler aktif gelir
- [ ] Filtreli URL başka tarayıcıda açılınca aynı sonuçlar görünür
- [ ] Geri tuşu çalışır
- [ ] Chip'ler aktif filtreleri doğru gösterir, × ile kaldırma çalışır
- [ ] Karşılaştırmaya 2+ araç eklenince floating bar görünür
- [ ] Floating bar → Karşılaştır butonu doğru URL'e gider
- [ ] Mobil (<768px) filtre drawer açılıp kapanır
- [ ] Masaüstünde mevcut davranış bozulmaz
- [ ] PHPUnit baseline (720 test) geçiyor
