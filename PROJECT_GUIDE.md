# MHM Rentiva Proje Rehberi

Bu belge, MHM Rentiva projesinde kullanılan yetenekleri (skills), kuralları (rules) ve iş akışlarını (workflows) özetlemektedir.

---

## 🛠 Yetenekler (Skills)

Proje geliştirme sürecinde kullanılan yapay zeka destekli yetenekler:

| Yetenek | Kısa Açıklama |
| :--- | :--- |
| **mhm-architect** | Özellik mimarisi, veritabanı şemaları ve teknik spesifikasyonları tasarlar. |
| **mhm-db-master** | Güvenli ve performanslı WordPress veritabanı işlemlerini yönetir. |
| **mhm-security-guard** | Nonce doğrulama, sanitizasyon ve SQL enjeksiyonu önleme gibi güvenlik standartlarını denetler. |
| **mhm-performance-auditor** | RAM, CPU ve DB kaynak kullanımını optimize eder; Query Monitor verilerini analiz eder. |
| **mhm-git-ops** | Değişiklikleri akıllı commit mesajlarıyla GitHub depolarına senkronize eder. |
| **mhm-polyglot** | WordPress i18n (uluslararasılaştırma) süreçlerini ve çeviri dosyalarını yönetir. |
| **mhm-doc-writer** | Docusaurus tabanlı dokümantasyonu güncel tutar. |
| **mhm-release-manager** | Eklenti yayınlanmadan önce pre-flight kontrollerini ve readme doğrulamalarını yapar. |
| **mhm-test-architect** | PHPUnit tabanlı birim ve entegrasyon testleri oluşturur. |
| **mhm-memory-keeper** | Oturumlar arası proje hafızasını yönetir ve hataların tekrarlanmasını önler. |
| **stitch-layout-translator** | Google Stitch tasarımlarını WordPress uyumlu blueprint'lere dönüştürür. |
| **webapp-testing** | Playwright kullanarak tarayıcı tabanlı otomatik testler gerçekleştirir. |
| **mhm-skills-hub** | İhtiyaca göre doğru yeteneği ve iş akışını otomatik olarak seçen ana orkestratördür. |
| **mhm-cli-operator** | WP-CLI ve terminal komutlarıyla eklenti yönetimi ve scaffold işlemlerini yapar. |
| **mhm-translator** | POT dosyalarından otomatik olarak .po/.mo dil dosyaları üretir. |

---

## 📜 Temel Kurallar (Rules)

MHM Rentiva projesinin "Altın Kuralları":

1.  **WordPress Uyumluluğu:** Her PHP dosyası `ABSPATH` kontrolü ve `declare(strict_types=1);` ile başlamalıdır.
2.  **Güvenlik:** Tüm girdiler sanitize edilmeli (`sanitize_text_field`), tüm çıktılar escape edilmeli (`esc_html`) ve Nonce kontrolü zorunludur.
3.  **Ön ek (Prefixing):** Tüm fonksiyonlarda `mhm_rentiva_`, sınıflarda `MHMRentiva` ön eki kullanılmalıdır.
4.  **Veritabanı:** Doğrudan SQL yasaktır; her zaman `$wpdb->prepare()` kullanılmalıdır.
5.  **Mimari:** Global değişkenlerden kaçınılmalı, Dependency Injection veya Singleton tercih edilmelidir. `extract()` kullanımı yasaktır.
6.  **Eklentiye Özel:** Rezervasyonlarda çakışma kontrolü (`Util::has_overlap`), ödemelerde `WooCommerceBridge` kullanılmalıdır.
7.  **Gutenberg Blokları:** Tüm bloklar `InspectorControls` kullanmalı ve ayarlar standart paneller (Genel, Layout, Görünürlük) altında toplanmalıdır.

---

## 🔄 İş Akışları (Workflows)

Tekrarlanan karmaşık işlemler için tanımlanmış deterministik süreçler:

| Komut / İş Akışı | Açıklama |
| :--- | :--- |
| `/audit-optimize-verify` | Kod tabanını kurallara göre denetler, optimize eder ve doğruluğunu test eder. |

---
*Bu rehber projenin standartlarını korumak ve geliştirme hızını artırmak için oluşturulmuştur.*
