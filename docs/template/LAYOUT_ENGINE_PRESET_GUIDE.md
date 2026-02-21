# LAYOUT ENGINE PRESET GUIDE

Bu rehber, kök motoru (Core Engine) yeni bir ürün veya projeye nasıl adapte edeceğinizi açıklar.

## 1. Adapting to a New Product

Yeni bir projenin "Preset" katmanını oluşturmak için 3 ana alanı güncellemeniz gerekir:

### A. Update ContractAllowlist
Projeye özgü bileşen tiplerini ve kullanılabilir CSS sarmalayıcılarını (`wrappers`) burada tanımlayın.
`src/Layout/Config/ContractAllowlist.php`

### B. Update Token Mapping Table
Figma'dan gelen token isimlerini temanızın (veya plugininizin) CSS değişkenlerine eşleyin.
`src/Layout/Tokens/TokenMapper.php`

### C. Create Component Adapters
Manifestteki XML/JSON yapısını WordPress shortcode'larına veya block markup'larına dönüştüren adaptörleri ekleyin.
`src/Layout/Adapters/`

## 2. "DO NOT TOUCH" List (Zorunlu)

Aşağıdaki katmanlar proje bazında **asla değiştirilmemelidir**:

> [!WARNING]
> Proje bazındaki değişiklikler bu dosyalara sızarsa, motorun cross-project uyumluluğu bozulur.

- **AtomicImporter:** Atomik işlem mantığı ve rollback senkronizasyonu.
- **LayoutNormalization:** Hashing ve determinizm kuralları.
- **DiffEngine:** Karşılaştırma algoritmaları.
- **Governance Enforcement:** Tailwind yasağı ve performans kısıtları.
- **Performance Tests:** ΔQ ≤ 0 test altyapısı.

## 3. Quick Migration Checklist

1. [ ] Core dizinini (`src/Layout/`) kopyala.
2. [ ] `ContractAllowlist` içindeki proje adını güncelle.
3. [ ] `TokenMapper` tablosunu temanın renk/spacing paletine göre ayarla.
4. [ ] Projeye özgü adaptörleri `src/Layout/Adapters/` altına yaz.
5. [ ] `composer dump-autoload` çalıştır.
6. [ ] `BOOTSTRAP_NEW_PROJECT.md` adımlarını takip et.


