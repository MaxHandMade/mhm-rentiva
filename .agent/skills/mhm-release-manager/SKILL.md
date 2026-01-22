---
name: mhm-release-manager
description: Çoklu dil ve dosya yapısında senkronize versiyon geçişi sağlayan yetenek.
---

# MHM Release Manager Skill

Bu skill, MHM Rentiva eklentisinin yeni bir versiyonu yayınlanacağı zaman kullanılır. Tek bir komutla tüm versiyon referanslarının ve changelog dosyalarının senkronize edilmesini sağlar.

## Hedef Dosyalar ve İşlemler

Bir versiyon güncellemesi (Örn: `1.0.0` -> `1.0.1`) istendiğinde aşağıdaki dosyalar sırasıyla güncellenmelidir:

### 1. Ana Eklenti Dosyası (`mhm-rentiva.php`)
- **Konum:** Eklenti ana dizini.
- **İşlem:** `Version:` başlığını ve `MHM_RENTIVA_VERSION` sabitini güncelle.

```php
/*
Plugin Name: MHM Rentiva
...
Version: 1.0.1  <-- GÜNCELLE
...
*/

define('MHM_RENTIVA_VERSION', '1.0.1'); <-- GÜNCELLE
```

### 2. WordPress Readme (`readme.txt`)
- **Konum:** Eklenti ana dizini.
- **İşlem:** `Stable tag:` değerini güncelle.

```txt
=== MHM Rentiva ===
...
Stable tag: 1.0.1 <-- GÜNCELLE
...
```

### 3. GitHub Readme Dosyaları (`README.md` & `README-tr.md`)
- **Konum:** Eklenti ana dizini.
- **İşlem:** Dosya içinde geçen versiyon numaralarını bul ve güncelle. Genellikle başlık altında veya rozetlerde bulunur.

### 4. JSON Changelog Dosyaları (`changelog.json` & `changelog-tr.json`)
- **Konum:** Eklenti ana dizini.
- **İşlem:** Yeni versiyon objesini `changelog` array'inin **en başına** ekle.
- **Format:**
  ```json
  {
    "version": "1.0.1",
    "date": "YYYY-MM-DD", // Bugünün tarihi
    "changes": [
      "Yeni özellik eklendi...",
      "Hata düzeltmeleri..."
    ]
  }
  ```

### 5. Docusaurus Dokümantasyon (`website/`)
- **Konum:** `website/` klasörü.
- **Koşul:** Sadece **Minor** veya **Major** güncellemelerde (Örn: 4.6.0, 5.0.0) versiyonlama yapılmalıdır. Patch (4.6.1) güncellemelerinde sadece blog yazısı yeterlidir.
- **İşlem A: Blog Yazısı (Sürüm Notları):**
  - `website/blog/YYYY-MM-DD-vX-X-X-released.md` dosyasını oluştur.
  - İçeriği `changelog.json`'dan al ve hikayeleştir (Öne Çıkanlar, Düzeltmeler vb.).
- **İşlem B: Versiyonlama Komutu:**
  - Eğer bu bir Major/Minor sürümse: `npm run docusaurus docs:version X.X.X` komutunu çalıştır.
  - `versions.json` ve `docusaurus.config.js` (dropdown) kontrolü yap.

## Uygulama Adımları

1.  **Versiyon Kontrolü:** Kullanıcıdan hedef versiyon numarasını al.
2.  **Dosya Yazma:** (Plugin) `mhm-rentiva.php`, `readme.txt`, JSON dosyalarını güncelle.
3.  **Dokümantasyon (Docs):**
    - Blog yazısını oluştur.
    - Gerekirse `docs:version` komutunu çalıştır.
4.  **Doğrulama:** Tüm dosyalarda versiyonları doğrula ve sitenin build olduğundan emin ol.

## 6. Uçuş Öncesi Kontrol (Pre-Flight Checks)
Sürüm çıkmadan önce yapılması zorunlu kontroller:
- [ ] `composer install --no-dev` ile production bağımlılıkları kontrol edildi mi?
- [ ] `npm run build` ile CSS/JS assetleri derlendi mi?
- [ ] PHPUnit testleri (`vendor/bin/phpunit`) hatasız geçti mi?
- [ ] `mhm-rentiva.php` içinde `MHM_RENTIVA_VERSION` güncellendi mi?

## 7. Git Entegrasyonu
Dosyalar güncellendikten sonra `mhm-git-ops` yeteneği kullanılarak:
1. Değişiklikler commit edilir: `chore: release v1.0.1`
2. Git etiketi oluşturulur: `git tag -a v1.0.1 -m "Release v1.0.1"`
3. Push işlemi yapılır: `git push origin main --tags`
