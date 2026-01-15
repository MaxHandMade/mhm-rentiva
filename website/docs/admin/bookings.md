# Bookings Sayfası

`MHM Rentiva > Bookings`, tüm rezervasyonların merkezi yönetim ekranıdır. Hem çevrim içi hem de manuel oluşturduğunuz rezervasyonlar burada listelenir; durum değişiklikleri, ödeme kontrolleri ve müşteri iletişimi bu sayfadan yürütülür.

## Görünüm ve Kolonlar
- **Status:** Rezervasyon durumu (`Pending`, `Confirmed`, `Active/In Progress`, `Completed`, `Cancelled`, `Refunded` vb.)
- **Customer / Vehicle / Dates:** Müşteri bilgisi, araç seçimi ve kiralama tarihleri.
- **Total / Payment Status:** Toplam tutar, alınan depozito, bekleyen bakiye, ödeme yöntemi.
- **Actions:** Detay ekranına gitme, hızlı statü değişikliği, makbuz onaylama gibi butonlar.

## Üst Araçlar
- **Status Filtresi:** Belirli bir durumdaki rezervasyonları getirmek için kullanılır.
- **Takvim Görünümü:** “Calendar” alt menüsü aylık görünüm sunar; hücre renkleri rezervasyon durumlarına göre değişir.
- **Bulk Actions:** Birden fazla rezervasyonu aynı anda “Confirm”, “Cancel” veya “Recycle Bin”e taşıma.

## Rezervasyon Detay Ekranı
- **Genel Bilgiler:** Müşteri kontak, araç, tarih ve fiyat kırılımı.
- **Ödeme Bilgisi:** Online/offline ödemeler, depozito ve kalan tutar.
- **Notlar ve Loglar:** Otomatik e-posta logları, manuel notlar.
- **Durum Değiştirme Paneli:** Confirm, Complete, Cancel, Refund gibi aksiyonlar.
- **Mesajlaşma:** Rezervasyonla ilişkilendirilmiş müşteri mesajlarına hızlı erişim.

### Versiyon 4.4.4 Yeni Özellikler

**Rezervasyon Referans Numarası:**
- Her rezervasyon için benzersiz bir referans numarası otomatik oluşturulur (format: `BK-00001`).
- Bu numara rezervasyon detay ekranında görüntülenir ve müşteri ile iletişimde kullanılabilir.

**Rezervasyon Tipi:**
- Rezervasyonlar artık "Online" veya "Manuel" olarak işaretlenir.
- Online rezervasyonlar: Müşteri tarafından web sitesi üzerinden oluşturulan rezervasyonlar.
- Manuel rezervasyonlar: Admin tarafından manuel olarak oluşturulan rezervasyonlar.

**Özel Notlar / Talepler:**
- Rezervasyon detay ekranında "Özel Notlar / Talepler" alanı eklendi.
- Bu alan müşteri talepleri, özel istekler veya notlar için kullanılabilir.
- Hem admin hem de müşteri tarafından görüntülenebilir ve düzenlenebilir.

**Araç Seçimi (Düzenlenebilir):**
- Rezervasyon düzenleme ekranında araç alanı artık düzenlenebilir.
- Araç seçiminde plaka numarası da gösterilir, böylece benzer isimli araçlar arasında karışıklık önlenir.
- Araç değişikliği yapıldığında fiyatlar otomatik olarak yeniden hesaplanır.

**WooCommerce İade Entegrasyonu:**
- WooCommerce'de iade yapıldığında rezervasyon durumu otomatik olarak güncellenir.
- İade tutarı rezervasyon meta verilerine kaydedilir.
- İade işlemi için e-posta bildirimleri otomatik gönderilir.

## Manuel Rezervasyon Oluşturma
- Ekranın üst kısmındaki `Add New Booking` butonuyla açılan form sayesinde:
  - Müşteri seçebilir veya yeni müşteri oluşturabilirsiniz.
  - Araç, tarih aralığı, addon hizmetleri belirleyip fiyatı otomatik hesaplayabilirsiniz.
  - Online/offline ödeme kaydı yapmadan rezervasyon oluşturabilir, durumu daha sonra güncelleyebilirsiniz.

## Önerilen Kontroller
- Rezervasyon “Pending” durumunda uzun süre kalıyorsa otomatik iptal süresini (`Settings > Booking > Auto cancel`) kontrol edin.
- Offline ödeme makbuzları geldiğinde “Payments” sekmesi üzerinden onaylayın; aksi halde rezervasyon “Pending” veya “Awaiting Payment”ta kalır.
- Her statü değişikliğinde ilgili e-posta şablonunun tetiklendiğini (Email Logs) kontrol etmek iyi bir pratiktir.

## İlgili Dokümanlar
- [Vehicles](vehicles.md): Rezervasyonların takvimde nasıl gösterildiği.
- [Payment Konfigürasyonu](../configuration/payments.md) *(hazırlanacak)*: Online/offline ödeme akışları.
- [Test Checklist](../checklists/testing-checklists.md#2-rezervasyon-akisi): Rezervasyon akışı test adımları.



