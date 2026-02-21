# TAILWIND_STRIP_PLAN.md

## 1. Giriş
`docs/stitch/code.html` dosyası Stitch tarafından üretilmiş olup yoğun şekilde Tailwind sınıfları içermektedir. Yönetişim (Governance) kuralı gereği, bu sınıflar eklentinin çalışma zamanı (runtime) koduna sızdırılmamalıdır.

## 2. Analiz: Tailwind -> Vanilla CSS Dönüşümü

Aşağıdaki anahtar sınıflar ve karşılık gelen CSS özellikleri, projenin mevcut CSS yapısına entegre edilecektir:

| Tailwind Sınıfı | CSS Karşılığı | Proje Belirteci (Target) |
| :--- | :--- | :--- |
| `bg-primary` | `var(--mhm-primary)` | Global primary rengi |
| `rounded-xl` | `var(--mhm-radius-xl, 16px)` | Global radius sistemi |
| `shadow-xl` | `var(--mhm-shadow-xl)` | Global shadow sistemi |
| `bg-slate-50` | `var(--mhm-bg-soft)` | Global soft background |
| `grid-cols-1 md:grid-cols-2` | `display: grid; ...` | CSS Grid |

## 3. Soyutlama Stratejisi
*   **Doğrudan Kopyalama Yasak:** `code.html` dosyasındaki sınıfları HTML template'lerine (`transfer-search.php`) kopyalamak kesinlikle yasaktır.
*   **BEM Metodolojisi:** Yeni bileşen yapıları için BEM (`mhm-premium-search__input-group`) veya mevcut `rv-` ön ekli sınıflar kullanılacaktır.
*   **Scoped Styling:** Tüm "premium" stiller `.mhm-premium-transfer-search` kapsayıcı sınıfı altına izole edilecektir.

## 4. Uygulama Adımları
1.  `docs/stitch/code.html` dosyasındaki layout yapısını (div hiyerarşisi) analiz et.
2.  Görsel tasarımın ruhunu (boşluklar, renkler) `transfer-premium.css` dosyasına aktar.
3.  `TransferSearch.php` içinde yeni CSS dosyasını `enqueue_assets` metoduna ekle.
4.  Gerekiyorsa template dosyasına minimal "hook" sınıflar ekle (fonskiyonelliği bozmadan).
