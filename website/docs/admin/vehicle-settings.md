# Vehicle Settings Sayfası

`MHM Rentiva > Vehicle Settings`, araç kayıt ekranında hangi alanların gösterileceğini, hangi özellik ve ekipmanların kullanılacağını ve kısa kod sayfalarının atanacağını yöneten kontrol panelidir. Filonuz büyüdükçe standart alanları sadeleştirmek veya yeni alanlar eklemek için bu menüyü kullanırsınız.

## Ana Bölümler

### 1. Vehicle Details
- Araç detay alanlarının (Price/Day, Year, Mileage, vb.) hangilerinin aktif olacağını seçmenize izin verir.
- “Select All / Deselect All / Edit Names” butonlarıyla toplu işlemler gerçekleştirebilirsiniz.
- Custom Details kutusuyla, özel alanlar ekleyip Quick Edit/Vehicle meta ekranında kullanılmasını sağlayabilirsiniz.

### 2. Vehicle Features
- Araç iç donanım özelliklerini (Air Conditioning, ABS, Bluetooth…) yönetir.
- Özel özellik ekleyerek (örn. “Wireless Charger”) müşteri formunda seçim yapılmasını sağlayabilirsiniz.

### 3. Vehicle Equipment
- Araçla birlikte verilen ekipmanların (Spare Tire, GPS, Child Seat…) listesini tutar.
- Custom Equipment bölümüyle yeni ekipman seçenekleri tanımlayabilirsiniz.

### 4. Kısa Kod Sayfaları
- Araç arama, rezervasyon, müşteri portalı gibi sayfaların hangi WordPress sayfasına bağlı olduğunu belirler.
- Kurulum rehberindeki zorunlu sayfa listesi ile eşleşmediğinde ilgili kısa kodlar çalışmaz; bu yüzden bu sekmenin eksiksiz doldurulması gerekir.

### 5. Kart Öğeleri (Visible Card Items)
- `Vehicle Display` bölümündeki **Visible Card Items** paneli, grid/list kartlarında (ve Hesabım > Favoriler’de) gösterilecek bilgileri yönetir.
- Sol taraftaki liste kartta görünen alanları belirtir; sürükle-bırak ile sıralamayı değiştirebilir, sağdaki “Available” kolonuna taşıyarak öğeyi gizleyebilirsiniz.
- Detay, Feature ve Equipment alanlarının tamamı – custom ekledikleriniz dahil – bu panelde seçilebilir. Kartta gösterilmeyecek bir alanı Vehicle Settings’te kapatırsanız, burada da otomatik olarak listeden çıkar.

## Kullanım İpuçları

- **Yeni Alan Eklerken:** Custom detail/feature/equipment ekledikten sonra ön yüzde görünmesi için kısa kod şablonlarını güncellemeniz gerekebilir.
- **Araç Formunu Sadeleştirme:** İşletmeniz için gereksiz alanlar varsa, bu sayfadan devre dışı bırakıp formu basitleştirebilirsiniz.
- **Quick Edit Senkronu:** Burada aktif ettiğiniz alanlar Quick Edit’e de yansır (örneğin “Seats” alanı kapatılırsa Quick Edit’te de gizlenir).

## İlgili Dokümanlar

- [Vehicles Sayfası](vehicles.md): Alanlar araç listesi ve meta kutuları üzerinde nasıl görüntülenir.
- [Zorunlu Sayfalar ve Kısa Kodlar](../getting-started/installation.md): Sayfa/kısa kod eşleşmelerinin tam listesi.
- [Checklist](../checklists/testing-checklists.md#1-arac-yonetimi): Alan ayarları değiştirildiğinde yapılması gereken testler.



