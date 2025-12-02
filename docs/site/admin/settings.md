# Settings Sayfası

`MHM Rentiva > Settings`, eklentinin tüm yapılandırma seçeneklerini barındıran çok sekmeli yönetim alanıdır. Kurulumun hemen ardından bu menüyü gözden geçirmek, çalışma kurallarını belirlemek açısından kritiktir.

## Sekmeler ve İçerikleri

> *Sekme adları sürüme göre değişiklik gösterebilir; aşağıdaki başlıklar genel yapıyı temsil eder.*

### 1. General
- Para birimi, tarih/saat formatı, varsayılan ülke gibi temel ayarlar.
- Araç listesi ve müşteri portalı için kullanılacak varsayılan sayfa URL’leri.
- Dashboard kartları veya istatistik davranışlarına yönelik toggle’lar.

### 2. Booking
- Minimum/maksimum kiralama süresi, buffer saatleri, önceden rezervasyon limiti.
- Otomatik iptal süresi (pending rezervasyonlar için).
- Rezervasyon formu doğrulama ayarları, müşteri onayı vb.

### 3. Payments
- **WooCommerce Entegrasyonu:** WooCommerce ile tam entegrasyon. WooCommerce destekli tüm ödeme ağ geçitlerini kullanabilirsiniz.
- **Offline Ödeme:** Banka havalesi, nakit veya diğer offline yöntemler için makbuz yükleme sistemi.
- Depozito ve kısmi ödeme seçenekleri.
- **Versiyon 4.4.4:** Para birimi artık WooCommerce veya eklenti ayarlarından dinamik olarak alınır (hardcode değer yok).
- **Versiyon 4.4.4:** WooCommerce iade entegrasyonu - WooCommerce'de iade yapıldığında rezervasyon durumu otomatik güncellenir.

### 4. Emails
- Gönderici adı/adresi, SMTP testi, global email enable toggle.
- Rezervasyon, ödeme, mesaj ve hesap olayları için şablon ayarları.
- Reminder scheduler ve cron aralığı.

### 5. Messages
- Destek kategorileri, otomatik yanıt metinleri ve admin bildirim e-postaları.
- Müşteri paneli mesaj limitleri, dosya yükleme izinleri.

### 6. Shortcodes / Pages
- Kurulum sırasında oluşturduğunuz sayfaları ilgili kısa kodlara bağlamak için dropdown alanları.
- Önbellek temizleme butonları (Shortcode URL cache).

### 7. Performance & Security
- Rate limiter (istek başına sınır), cache TTL değerleri, veritabanı optimizasyon tercihleri.
- IP whitelist/blacklist, brute force koruması gibi gelişmiş güvenlik seçenekleri.

### 8. Advanced / System
- Cron job yapılandırmaları, debug/log ayarları.
- API anahtarı yönetimi veya REST sınırlandırmaları (sürüme göre).
- Geliştirici moduna dair toggle’lar.

## Kullanım İpuçları

- **Sekme Bazlı Kaydetme:** Her sekmede yapılan değişiklikler ayrı ayrı kaydedilir; sekme değiştirmeden önce `Save` butonuna basmayı unutmayın.
- **Test Etmeden Canlıya Alma:** Ödeme ve e-posta sekmelerinde sandbox/test modlarıyla doğrulama yapmadan canlı anahtarları kullanmayın.
- **Yedekleme:** Performans ve güvenlik seçenekleri sistem davranışını etkiler. Değişiklik öncesi yedek almanız önerilir.

## İlgili Dokümanlar

- [Vehicles & Vehicle Settings](vehicles.md) – Bu menüdeki ayarların araç ekranına etkisi.
- [Payments](../configuration/payments.md) *(hazırlanacak)* – Ödeme gateway’lerinin detaylı kurulumu.
- [Core Utilities](core-utilities.md) – Sistem bakımı ve temizleme araçları.
- [Test Checklist](../../checklists/testing-checklists.md) – Ayar değişikliklerinden sonra yapılması gereken kontroller.

