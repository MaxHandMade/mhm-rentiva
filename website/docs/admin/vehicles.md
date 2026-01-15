# Vehicles Sayfası

`MHM Rentiva > Vehicles`, filonuzdaki tüm araçları yönetebileceğiniz ana merkezdir. Varsayılan WordPress “post listesi” görünümü üzerine eklenen metrik kartları, aylık takvim ve hızlı düzenleme bileşenleri sayesinde hem durum takibini hem de düzenlemeleri tek noktadan yapabilirsiniz.

## Öne Çıkan Bölümler

### 1. İstatistik Kartları
- **Monthly Reserved Vehicles:** Aktif ay içinde rezervasyonu bulunan araç sayısı.
- **Inactive Vehicles:** Şu an “Inactive” durumunda olan araç sayısı.
- **Vehicles Under Maintenance:** “Maintenance” işareti verilmiş araç sayısı.
- **Avg. Monthly Revenue:** Onaylanmış/ tamamlanmış rezervasyonlara göre araç başına ortalama gelir.

> Kartlardaki veriler, araç statüsü veya rezervasyonlar güncellendiğinde otomatik yenilenir.

### 2. Monthly Booking Calendar
- Araç bazlı bir takvimdir; her satır belli bir aracı temsil eder.
- Takvim hücreleri, rezervasyon durumlarına göre renklenir (Pending, Confirmed, Completed, Cancelled).
- Bir hücreye tıklandığında rezervasyon detaylarını gösteren mini pop-up açılır.

### 3. Araç Listesi
- Standart WordPress listesine ek olarak şu kolonlar bulunur: `License Plate`, `Price/Day`, `Seats`, `Transmission`, `Fuel`, `Available`.
- `Available` kolonundaki etiketler (Active, Inactive, Maintenance) Quick Edit ile güncellenebilir.
- Liste üstündeki filtreler, durumlara göre arama yapmanıza izin verir.
- Grid, list ve favori kartlarında gösterilecek rozetler `Vehicle Settings > Visible Card Items` panelinden yönetilir. Burada sürükle-bırak ile sıraladığınız alanlar (detay/feature/equipment) ön yüzde aynı sırayla görüntülenir; listeden kaldırdıklarınız tüm kartlarda gizlenir.

### 4. Quick Edit
- Başlık, slug, kategori gibi standart alanların yanında Rentiva'ya özel alanlar da (License Plate, Price/Day, Seats, Transmission, Fuel, Available) hızlıca düzenlenebilir.
- `Available` dropdown'ı otomatik olarak mevcut statüyü seçili getirir. Kaydettiğinizde hem `_mhm_vehicle_status` hem `_mhm_vehicle_availability` meta alanları eşzamanlanır.
- **Versiyon 4.4.4 Güncellemesi:** Seats, Doors ve Engine Size alanları için minimum/maksimum değerler artık ayarlardan yapılandırılabilir (varsayılan: Seats max 100, Doors max 20, Engine Size 0.0-20.0L).
- **Yeni Yakıt Tipleri:** LPG, CNG ve Hidrojen yakıt tipleri eklendi. Alternatif yakıtlı araçlar için bu seçenekleri kullanabilirsiniz.
- **Yeni Vites Tipleri:** Yarı Otomatik ve CVT (Continuously Variable Transmission) vites seçenekleri eklendi. Modern araçlar için bu seçenekleri kullanabilirsiniz.

## Sık Yapılan İşlemler

1. **Yeni Araç Ekleme:** Sağ üstteki `Add New Vehicle` butonuna tıklayın (`vehicle` post type editörü açılır).
2. **Durum Güncelleme:** 
   - Liste üzerinden Quick Edit ile `Available` durumunu değiştirin.
   - Veya araç detay sayfasında “Vehicle Details” meta kutusu içinden ilgili alanları düzenleyin.
3. **Araçları Filtreleme:** Üst liste filtresi ile `Active`, `Inactive` veya `Maintenance` statüsüne göre sonuçları daraltın.
4. **Rezervasyon Takibi:** Takvimde rezervasyon renklerini kontrol ederek çakışmaları fark edin; detay panelinde müşteri bilgilerine ulaşıp hızlı aksiyon alın.

## İlgili Dokümanlar

- [Vehicle Settings](vehicle-settings.md): Araç detay/özellik alanlarını açma/kapama, kısa kod sayfalarını belirleme.
- [Vehicle Meta Yapısı](../getting-started/setup-wizard.md Araç kayıt ekranındaki meta alanlarının anlamı.
- [Test Checklist](../checklists/testing-checklists.md): Araç yönetimi test senaryoları.



