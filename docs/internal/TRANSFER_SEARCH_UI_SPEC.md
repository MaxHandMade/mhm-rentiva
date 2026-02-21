# TRANSFER_SEARCH_UI_SPEC.md

## 1. Visual Intent
`Transfer Search` bileşeni, `DESIGN.md`'de tanımlanan **mhm-rentiva-ui-beta** standartlarına uygun olarak modernize edilecektir.

**Hedef Görünüm:**
*   **Konteyner:** Beyaz zeminli, 16px radius (`xl`), yumuşak gölgeli bir kart.
*   **Sekmeler (Tabs):** Mockup'taki "Airport Transfer" sekmesi stilinde, aktif sekme `Modern Blue (#137FEC)` vurgulu.
*   **Input Alanları:** 
    *   Arka plan: `Soft Cloud Gray (#F8FAFC)`.
    *   Sınırlar (Borders): `Slate 200` veya `Slate 700` (Dark Mode).
    *   İkonlar: Sol tarafa hizalanmış `Material Symbols`.
*   **Butonlar:** Tam genişlikte, Modern Blue zeminli, lüks hissiyat veren yüksek dolgulu (padding).

## 2. Component Structure (Scoped)

Mevcut HTML yapısı (özellikle root wrapper) korunacak ve yeni premium sınıflarla genişletilecektir.

```html
<!-- Mevcut root korunur, üzerine premium class eklenir -->
<div class="rv-transfer-search mhm-premium-transfer-search mhm-card">
  <!-- Tab Header (Visual only if no logic exists) -->
  <div class="mhm-tabs">
     <div class="mhm-tab is-active">Airport Transfer</div>
  </div>
  
  <form class="rv-unified-search__form js-unified-transfer-form">
     <!-- Mevcut grid yapısı korunarak premium sınıflarla süslenir -->
     <!-- Grid Layout matching Stitch -->
     <div class="mhm-form-grid">
        <!-- Input fields with icon wrappers -->
     </div>
     <button class="mhm-btn-primary">Search Transfers</button>
  </form>
</div>
```

## 3. CSS Invariants
*   **Root Class:** `.mhm-premium-transfer-search`
*   **No Tailwind:** Tüm stiller `transfer-premium.css` içinde manuel yazılacaktır.
*   **Tokens:**
    *   `--mhm-primary: #137fec;`
    *   `--mhm-bg-soft: #f8fafc;`
    *   `--mhm-radius: 16px;`

## 4. Interaction & Logic Invariants
*   **Tab Logic:** Yeni bir JS davranışı eklenmeyecektir. Eğer gerçek sekmeler yoksa sadece görsel header olarak kalacaktır.
*   **Input focus:** `Modern Blue` border vurgusu CSS ile yapılacaktır.
*   **Asset Loading:** `self::$rendered_transfer_search` flag'i üzerinden sadece render anında enqueue yapılacaktır.
