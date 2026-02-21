# BOOTSTRAP NEW PROJECT

Bu runbook, MHM Rentiva Layout motorunu kullanan yeni bir projenin sıfırdan nasıl başlatılacağını adım adım açıklar.

## 1. Prerequisites
- **PHP:** 7.4 veya 8.x (Strict types zorunluluğu).
- **WordPress:** 6.x+.
- **Composer:** Bağımlılık yönetimi için.
- **WP-CLI:** Layout import/preview için zorunlu.
- **Local Dev:** XAMPP veya LocalWP (Test harness için).

## 2. Step-by-Step Initialization

### Adım 1: Repo Yapısının Kurulması
Proje klasörünü oluşturun ve Core Layout klasörlerini kopyalayın:
```powershell
mkdir wp-content/plugins/your-new-plugin
cd wp-content/plugins/your-new-plugin
# Layout Core kopyalama işlemi...
```

### Adım 2: Bağımlılıkların Yüklenmesi
```bash
composer install
```

### Adım 3: WP Test Harness Yapılandırması
`phpunit.xml.dist` dosyasını projenize göre güncelleyin ve test veritabanını oluşturun.

### Adım 4: İlk Doğrulama (First Success)
Aşağıdaki komutların hatasız çalıştığını teyit edin:
```bash
vendor/bin/phpcs
vendor/bin/phpunit
```

### Adım 5: Layout Preview Testi
Minimal bir manifest ile preview modunu çalıştırın:
```bash
wp mhm-rentiva layout import tests/fixtures/minimal.json --preview
```

## 3. Evidence Capture Standard (Mandatory)

Bootstrap tamamlandı sayılmadan önce aşağıdaki kanıt formatı doldurulmalıdır:

```text
### [Bootstrap Verification Evidence]
- Command: `composer install`
  Result: `<pass|fail>`
- Command: `vendor/bin/phpcs`
  Result: `<pass|fail>`
- Command: `vendor/bin/phpunit`
  Result: `<pass|fail>`
- Command: `wp mhm-rentiva layout import tests/fixtures/minimal.json --preview`
  Result: `<pass|fail>`
- Delta Queries: `<value>` (target: <= 0)
- Notes: `<optional>`
```

Evidence yoksa bootstrap adımı tamamlanmış kabul edilmez.

## 4. Common Failure Modes & Fixes

- **Fatal Error (WP_CLI not found):** Entegrasyon testlerinde `class_exists('WP_CLI')` kontrolü yapıldığından emin olun.
- **Composition Error:** `ContractAllowlist` içinde bileşen tipinin tanımlı olduğunu kontrol edin.
- **ΔQ > 0:** Render sırasında veritabanı sorgusu yapılıp yapılmadığını (Query Monitor ile) inceleyin.

