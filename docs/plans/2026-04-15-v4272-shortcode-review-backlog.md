# v4.27.2 — Shortcode Review Backlog

**Kaynak:** 2026-04-15 shortcode inceleme seansı (YouTube canlı yayın hazırlığı)
**Durum:** Beklemede
**Versiyon notu:** 2026-04-23'te iki kez renumber edildi: v4.26.8 → v4.27.1 → v4.27.2. v4.27.0 WPCS release prep, v4.27.1 Vehicle Settings i18n locale-leak hotfix olarak alındı. Plan ile birlikte `2026-04-15-search-results-ux-v4.27.2.md` aynı release içinde birleştirilir.

---

## Öne Çıkan Araçlar (rentiva_featured_vehicles)

### 1. Swiper Loop Modu — Az Araçla Bozulma
- **Sorun:** `loop: true` her zaman açık, ama limit < columns × 2 olduğunda Swiper
  düzgün çalışmıyor (duplicate slide sayısı yetersiz).
- **Çözüm:** JS'de `loop: limit > columns * 2` koşulu ekle.
- **Dosya:** `assets/js/frontend/featured-vehicles.js` → Swiper init `loop` parametresi.

### 2. "Tümünü Gör" Linki Yok
- **Sorun:** Araç Izgarası'na eklenen `view_all_url` / `view_all_text` özelliği
  Öne Çıkan Araçlar'da yok.
- **Çözüm:** Aynı pattern'ı uygula — `get_default_attributes()`, AllowlistRegistry,
  Elementor widget, Gutenberg block.json + index.js, template footer.
- **Referans:** `VehiclesGrid.php` + `vehicles-grid.php` implementasyonu.

---

## Vehicles Grid (rentiva_vehicles_grid)

### 3. Gutenberg Block "View All URL" — Görsel Kontrol Doğrulaması
- Block editör JS'de kontrol mevcut (index.js satır 184–195) ama canlı Gutenberg
  ortamında henüz tarayıcıda test edilmedi.
- Elementor tarafı doğrulandı (kullanıcı onayladı).

---

## Genel / Diğer

### 4. URL pushState + Aktif Filtre Chips
- Arama ve filtreleme durumu URL'e yansıtılsın (pushState).
- Aktif filtreler kaldırılabilir chip olarak gösterilsin.

### 5. Comparison Floating Bar
- Araç karşılaştırma seçimi için sabit floating action bar.

### 6. Mobile Filter Drawer
- Mobilde filtreleme için drawer/panel.

### 7. Transfer Formu — Inline Validasyon
- Transfer formu için custom inline validasyon mesajları.

### 8. `default_tab` Attribute
- Unified Search için varsayılan aktif sekmeyi belirleme attribute'u.

### 9. "Farklı Lokasyona Bırak" Toggle
- Transfer/kiralama formlarında farklı teslim lokasyonu seçeneği.
