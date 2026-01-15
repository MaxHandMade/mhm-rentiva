# Kurulum Sihirbazı (Setup Wizard)

Rentiva’yı aktif eder etmez karşınıza çıkan Setup Wizard, eklentiyi birkaç dakikada çalışır hâle getirmeniz için tasarlandı. Sistem gereksinimlerinden zorunlu sayfalara, e-posta ve ödeme ayarlarından ön yüzde gözükecek varsayılanlara kadar tüm kritik noktaları tek akışta tamamlamanıza yardım eder.

## Nasıl Açılır?

- **İlk aktivasyonda otomatik:** Eklentiyi etkinleştirdiğinizde yönetici paneli `MHM Rentiva > Setup Wizard` ekranına yönlendirilir.  
- **Daha sonra tekrar:** Menüde `MHM Rentiva > Setup Wizard` bağlantısı her zaman erişilebilir. Sayfanın üst kısmındaki “Skip wizard and configure later” butonuyla sihirbazı kapatsanız bile menüden yeniden açabilirsiniz.

> Her adım bilgileri kaydeder. Tarayıcıyı kapatsanız bile kaldığınız yerden devam edebilirsiniz.

## Adım Adım Akış

1. **System Check**  
   - PHP/WP sürümü, veritabanı, WP Memory Limit, max execution time, HTTPS ve WP-Cron durumu taranır.  
   - SMTP eklentisi algılanırsa “Email Delivery” satırı **Ready** olur. Warnings/Required satırları için öneriler tabloya otomatik eklenir (ör. hafızayı artırmak için `wp-config.php` notu).

2. **License**  
   - Aktif lisans varsa kart üzerinde anahtar, plan ve bitiş tarihi gösterilir.  
   - Lisans yoksa alanı doldurup kaydedebilir, isterseniz “Skip for now” ile sonraki adıma geçebilirsiniz.

3. **Required Pages**  
   - Kısa kodların bağlı olduğu sayfalar (Booking Form, Confirmation, My Account, Login, Register, Contact vb.) listelenir.  
   - Eksik olanlar için “Create Missing Pages” butonu tek tıkla oluşturur.  
   - “Open Shortcode Pages” kısayolu detaylı eşleşmeleri yönetmek için ana ayar sayfasını açar.

4. **Email Settings**  
   - Gönderen isim/adres, reply-to, test modu ve otomatik mail seçenekleri bu adımda konfigüre edilir.  
   - Varsayılan olarak tüm otomasyon kutuları işaretlidir (otomatik bildirimler + loglama).  

5. **Payment Gateways**  
   - Stripe, PayPal, PayTR ve Offline ödemeler için hızlı anahtar alanları bulunur.  
   - Hangi ağ geçitlerinin aktif olacağını seçer, varsayılan ödeme yöntemini belirleyebilirsiniz.

6. **Frontend & Display**  
   - Genel ayarlar sekmesindeki **Currency** ve **Currency Position** listeleri aynen kullanılır.  
   - Varsayılan/Minimum/Maximum kiralama günleri ile kart rozetlerini gösterip göstermeyeceğiniz burada kaydedilir.

7. **Summary & Tests**  
   - Tüm adımların durumu Ready/Warning olarak özetlenir.  
   - “Open Settings”, “Review Shortcode Pages” ve “Send Test Email” kısayolları kritik ekranları yeni sekmede açar.  
   - “Complete Setup” tıklanana kadar Summary satırı **Warning** kalır (bilerek bırakılmış güvenlik adımı).

## Uyarılar ve Sorun Giderme

- **System Check Warning’leri** bilgi amaçlıdır; “Required” olanlar (örn. WP Memory Limit 40 MB) canlıya çıkmadan önce mutlaka düzeltilmeli.  
- **HTTPS / SSL** satırı yalnızca `is_ssl()` sonuçlarına bakar; reverse proxy kullanıyorsanız `FORCE_SSL_ADMIN` veya gerçek sertifika ile testi doğrulayın.  
- **Email Delivery Warning** yalnızca SMTP eklentisi tespit edilmediğinde gösterilir. FluentSMTP, WP Mail SMTP, Post SMTP gibi eklentiler otomatik algılanır.

## Sihirbazı Tekrar Çalıştırmak

Kurulumu tamamladıktan sonra bile ayarlarınızı sıfırlamak istemiyorsanız “Complete Setup” sonrası sihirbaz sadece rehber olarak açılır. Yeni bir sitede sıfırdan test etmek için eklentiyi devre dışı bırakıp tekrar etkinleştirebilir veya ilgili seçenekleri manuel olarak güncelleyebilirsiniz.

Daha ayrıntılı test senaryoları için [Testing Checklist](../checklists/testing-checklists.md) dosyasına göz atmayı unutmayın. Ayrıca, eklentinin her bir yönetici sayfasının görev ve yeteneklerini görmek için [Yönetici Sayfaları rehberine](../admin/README.md) bakabilirsiniz.
