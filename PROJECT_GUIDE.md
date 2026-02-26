# MHM Rentiva Proje Rehberi

Bu belge, MHM Rentiva projesinde kullanılan yetenekleri (skills), kuralları (rules), iş akışlarını (workflows) ve yeni nesil mimari standartları özetlemektedir.

---

## 🛠 Yetenekler (Skills)

Proje geliştirme sürecinde kullanılan yapay zeka destekli yetenekler:

| Yetenek | Kısa Açıklama |
| :--- | :--- |
| **changelog-generator** | Git commit'lerinden kullanıcı dostu ve profesyonel sürüm notları (changelog) üretir. |
| **create-pull-request** | GitHub üzerinde proje standartlarına ve best-practice'lere uygun Pull Request (PR) oluşturur. |
| **file-organizer** | Dosya ve klasörleri akıllıca organize eder, gereksiz dosyaları temizleyerek çalışma alanını düzenli tutar. |
| **mhm-architect** | WordPress tabanlı özellik mimarisi, veritabanı şemaları ve teknik spesifikasyonları tasarlar. |
| **mhm-cli-operator** | WP-CLI ve terminal komutlarıyla eklenti yönetimi, çevre kurulumu ve scaffold işlemlerini yapar. |
| **mhm-db-master** | Güvenli, performanslı ve WordPress standartlarına uygun veritabanı işlemlerini yönetir. |
| **mhm-doc-writer** | Docusaurus tabanlı teknik dokümantasyonu sürdürülebilir ve güncel tutar. |
| **mhm-git-ops** | Değişiklikleri akıllı ve açıklayıcı commit mesajlarıyla GitHub depolarına senkronize eder. |
| **mhm-memory-keeper** | Proje hafızasını yönetir; geçmiş kararları ve teknik detayları hatırlayarak hataların tekrarını önler. |
| **mhm-performance-auditor** | Sistem kaynaklarını (RAM/CPU/DB) en verimli şekilde kullanmak için Query Monitor verilerini analiz ve optimize eder. |
| **mhm-polyglot** | WordPress i18n (uluslararasılaştırma) süreçlerini, POT dosyalarını ve çeviri yönetimini sağlar. |
| **mhm-release-manager** | Yayın öncesi pre-flight kontrollerini, readme doğrulamalarını ve sürüm hazırlıklarını yapar. |
| **mhm-security-guard** | Nonce doğrulama, sanitizasyon ve SQL enjeksiyonu gibi kritik güvenlik standartlarını denetler. |
| **mhm-skills-hub** | İhtiyaca göre doğru yeteneği ve iş akışını otomatik olarak seçen merkezi orkestratördür. |
| **mhm-test-architect** | PHPUnit tabanlı birim (unit) ve entegrasyon test mimarilerini kurgular ve uygular. |
| **mhm-translator** | POT dosyalarından AI desteğiyle otomatik olarak .po/.mo dil dosyaları üretir. |
| **stitch-layout-translator** | Google Stitch tasarımlarını WordPress uyumlu blueprint ve şablonlara dönüştürür. |
| **web-design-guidelines** | UI kodlarını erişilebilirlik, tasarım standartları ve UX best-practice'lerine göre denetler. |
| **webapp-testing** | Playwright kullanarak tarayıcı tabanlı otomatik uçtan uca (E2E) testler gerçekleştirir. |


---

## 📜 Temel Kurallar (Rules)

1.  **WordPress Standartları:** Her PHP dosyası `ABSPATH` kontrolü ve `declare(strict_types=1);` ile başlamalıdır.
2.  **Ön ek (Prefixing):** Fonksiyonlarda `mhm_rentiva_` (snake_case), sınıflarda `MHMRentiva` (PascalCase).
3.  **Güvenlik:** Girdi sanitizasyonu (`sanitize_text_field`), çıktı escape işlemi (`esc_html`) ve Nonce kontrolü.
4.  **Veritabanı:** Ham SQL yasaktır; her zaman `$wpdb->prepare()` kullanılmalıdır.
5.  **Performans:** `admin_init` üzerinde maliyetli hash kontrollerinden kaçınılmalı, sadece versiyon değiştiğinde veya ayar güncellendiğinde rewrite flush yapılmalıdır.

---

## 🧪 Test & Doğrulama

Tüm yeni geliştirmeler şu testlerden geçmelidir:

- **PHPUnit:** `vendor/bin/phpunit tests/...` ile birim test doğrulaması.
- **CLI:** `wp eval` veya `wp rewrite list` ile URL ve uç nokta tutarlılık kontrolü.
- **Query Monitor:** Veritabanı sorgu sayısı ve RAM kullanımı denetimi.

---

## 🔄 İş Akışları (Workflows)

| Komut / İş Akışı | Açıklama |
| :--- | :--- |
| `/audit-optimize-verify` | Kod tabanını kurallara göre denetler, optimize eder ve PHPUnit ile doğrular. |

---
*Bu rehber projenin standartlarını korumak ve geliştirme hızını artırmak için oluşturulmuştur.*
