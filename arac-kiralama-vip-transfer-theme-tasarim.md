# Araç Kiralama ve VIP Transfer WordPress + WooCommerce Tema Tasarım Dokümanı

## 1. Özet
Bu doküman, Araç Kiralama ve VIP Transfer odaklı modern, kurumsal ve güven veren bir WordPress + WooCommerce teması için UI/UX, sayfa akışları, bileşenler ve teknik gereksinimleri tanımlar. Tema; araç seçiminden ödeme tamamlamaya kadar kesintisiz, sezgisel ve profesyonel bir deneyim sunmayı hedefler.

## 2. Hedef Kullanıcı
- Bireysel araç kiralama müşterileri.
- VIP Transfer veya şoförlü araç hizmeti arayan kurumsal/premium kullanıcılar.
- Mobil ağırlıklı kullanım senaryosu.

## 3. Marka ve Tasarım Dili
### 3.1 Stil
- Modern, sade, premium ve güven veren.
- Açık tonlu arayüz, ferah white space.
- Yüksek okunabilirlik, net tipografi.

### 3.2 Renk Paleti
- Ana renk: Mavi ve mavi tonları.
- Destek renkler: Beyaz, açık gri, çok hafif pastel tonlar.
- Durum renkleri:
  - Müsait: Mavi tonları.
  - Dolu: Açık gri.

### 3.3 Tipografi
- Yeni font eklenmeden, tek ve tutarlı bir font ailesi kullanılır.
- Başlıklar net ve güçlü, gövde metni sade ve okunaklı.

## 4. Tasarım Prensipleri
- Mobile-first ve tamamen responsive.
- Gutenberg ve Full Site Editing (FSE) uyumlu.
- WooCommerce template ve hooks yapısına uygun.
- SEO ve erişilebilirlik (a11y) kurallarına uygun.
- Hızlı ve kesintisiz rezervasyon akışı.

## 5. Bilgi Mimarisi ve Sayfa Akışları

### 5.1 Ana Sayfa
**Amaç:** Hızlı arama, güven oluşturma ve dönüşüm.

**Hero Alanı**
- Geniş hero alanı.
- Araç Kiralama ve VIP Transfer için ayrı sekmeli arama formu.
- Alanlar: Lokasyon, tarih/saat, araç tipi.
- Ana CTA: “Araç Bul” / “Transfer Ara”.

**Öne Çıkan Bölümler**
- Öne çıkan araçlar (kart yapısı).
- VIP araçlar (premium kart yapısı).
- Avantajlar (ikonlu, kısa, net).
- Güven unsurları (müşteri yorumları, sertifikalar, partner logolar).

### 5.2 Araç Kiralama Akışı
**Araç Listeleme Sayfası**
- Filtreler: fiyat, segment, vites, yakıt.
- Net fiyat bilgisi (günlük / toplam).
- Kartlarda hızlı karşılaştırma bilgileri.

**Araç Detay Sayfası**
- Büyük görseller (galeri).
- Teknik özellikler.
- Kiralama koşulları.
- “Rezervasyona Devam Et” CTA.

### 5.3 VIP Transfer Akışı
- Rota bazlı veya mesafe bazlı fiyatlandırma.
- Transfer araçları için özel kart tasarımı.
- Şoför bilgisi ve premium hizmet vurgusu.
- Sabit veya dinamik fiyat gösterimi.

### 5.4 Müsaitlik Takvimi
- Araç bazlı müsaitlik takvimi.
- Renk kodlu günler: müsait (mavi), dolu (açık gri).
- Tarih seçimi sonrası otomatik fiyat özeti.
- Sticky “Devam Et” CTA.

### 5.5 Sepet (WooCommerce Cart)
- Seçilen araç/transfer özeti.
- Tarih, lokasyon ve ek hizmetler.
- Fiyat dökümü (ara toplam, vergiler, toplam).
- “Ödemeye Geç” ana CTA.

### 5.6 Ödeme (WooCommerce Checkout)
- Adım adım sade akış.
- Müşteri ve fatura bilgileri.
- Ödeme yöntemleri (kredi kartı, havale).
- Güven ve gizlilik vurguları.
- “Ödemeyi Tamamla” CTA.

### 5.7 Sipariş Onay / Teşekkür
- Başarılı ödeme bildirimi.
- Rezervasyon ve transfer özeti.
- E-posta bilgilendirme mesajı.

## 6. Ek Sayfalar ve Akışlar

### 6.1 Gelişmiş Araç Karşılaştırma Sayfası
**Amaç:** Kullanıcının birden fazla aracı objektif olarak karşılaştırıp karar vermesini sağlamak.
- Karşılaştırma kriterleri: fiyat, segment, vites, yakıt, bagaj, yolcu sayısı, sigorta, depozito.
- Mobilde yatay kaydırmalı tablo düzeni.
- Her araç için hızlı “Rezervasyona Devam Et” CTA.
- Block tema uyumu: Karşılaştırma tablosu, filtre çubuğu ve CTA blokları ayrı şablon parçaları.

### 6.2 Akıllı Arama Sonuçları Sayfası
**Amaç:** Arama kriterlerine göre en uygun araçları öncelikli göstermek.
- Kriterlere göre otomatik sıralama (fiyat, uygunluk, popülerlik).
- “En Uygun”, “Hızlı Teslim”, “Premium” etiketleri.
- “Sonraki müsait tarih” önerisi (boş sonuç durumları için).
- Block tema uyumu: Sonuç listesi, filtreler ve boş durum mesajları ayrı blok düzenleri.

### 6.3 Ekstra Hizmet Seçim Sayfası
**Amaç:** Rezervasyon öncesi ek hizmetlerin net ve kolay seçimi.
- Örnek hizmetler: çocuk koltuğu, ek sürücü, sigorta paketi, GPS, karşılama.
- Fiyat etkisi anlık güncellenir.
- Hizmet kartları ve “Toplam Fiyat” özeti.
- Block tema uyumu: Hizmet kart grid, fiyat özeti ve CTA ayrı bloklar.

### 6.4 Kurumsal / Hakkımızda Sayfası
**Amaç:** Güven ve kurumsallık vurgusu.
- Şirket hikayesi, misyon, vizyon.
- Sertifikalar, partner logoları, ödüller.
- Kurumsal transfer hizmetleri için özel anlatım.
- Block tema uyumu: İstatistik sayaçları, zaman çizelgesi ve referans blokları.

### 6.5 Yasal Sayfalar
**Amaç:** Şeffaflık, yasal uyumluluk ve güven.
- KVKK / Gizlilik.
- Kullanım Şartları.
- İptal & İade Politikası.
- Block tema uyumu: Uzun metinler için okunabilir tipografi blok şablonu.

## 7. Bileşenler ve UI Modülleri
- Sekmeli arama formu (Kiralama / VIP Transfer).
- Araç kartı (fiyat, tip, vites, yakıt, kısa özellikler).
- VIP transfer kartı (premium vurgulu).
- Filtre paneli (AJAX destekli).
- Güven unsurları bloğu (yorumlar, sertifikalar).
- Müsaitlik takvimi (mobil-first, sticky CTA).
- WooCommerce mini cart.

## 8. Header & Footer
**Header**
- Sticky, modern header.
- WooCommerce uyumlu:
  - Account (Giriş / Hesabım).
  - Mini Cart (araç sayısı ve toplam).

**Footer**
- Kurumsal bilgiler.
- Hızlı linkler.
- Yasal sayfalar.
- İletişim ve sosyal medya.

## 9. Teknik ve UX Beklentileri
- Mobile-first yaklaşım.
- Gutenberg ve FSE uyumluluğu.
- WooCommerce hooks ve template yapısına uygun.
- AJAX destekli filtreleme ve mini cart.
- SEO, erişilebilirlik ve performans odaklı.

## 10. Genel Hedef
Kullanıcıya güven veren, hızlı, profesyonel ve kesintisiz bir rezervasyon deneyimi sunan, Araç Kiralama ve VIP Transfer odaklı modern bir WordPress + WooCommerce teması oluşturmak.

## 11. FSE Tema Entegrasyon Planı (Blueprint)
Bu bölüm, Google AI Studio veya üretici yapay zeka tarafından oluşturulacak temanın taşıması gereken teknik iskeleti tanımlar.

### 11.1 Gerekli Şablon Dosyaları (Templates)
Tema kök dizinindeki `templates/` klasöründe bulunması gerekenler:

| Dosya Adı | Kullanım Amacı | İçermesi Gereken Bloklar/Desenler |
| :--- | :--- | :--- |
| `front-page.html` | Ana Sayfa | `hero-tabbed-search`, `featured-vehicles`, `why-choose-us`, `vip-transfer-promo` |
| `archive-vehicle.html` | Araç Listesi | `sidebar-filters`, `vehicle-grid` |
| `single-vehicle.html` | Araç Detay | `vehicle-gallery`, `specs-list`, `booking-form-sticky` |
| `page-vip-transfer.html` | VIP Sayfası | `transfer-hero`, `transfer-vehicle-list` |
| `page-compare.html` | Karşılaştırma | `comparison-table-scrollable` |
| `index.html` | Genel Yedek | `post-list`, `sidebar` |
| `404.html` | Hata Sayfası | `search-form`, `home-button` |

### 11.2 Gerekli Blok Desenleri (Patterns)
Tema kök dizinindeki `patterns/` klasöründe bulunması gerekenler:

#### Global
- `header-default`: Logo, Menü, Hesabım, Sepet İkonu.
- `footer-corporate`: Kurumsal linkler, bülten üyeliği, sosyal medya, telif.

#### Ana Sayfa
- `hero-tabbed-search`: Arka plan görsel, üstte "Kiralama vs Transfer" sekmeli arama kutusu (Container).
- `features-grid`: 3 veya 4 kolonlu ikonlu avantajlar listesi.
- `trust-badges`: Partner veya marka logoları kayan bant veya grid.

#### Araç Odaklı
- `vehicle-card`: Görsel, Başlık, Özellikler (vites, yakıt), Fiyat Alanı, "Kirala" Butonu.
- `vehicle-specs-list`: Detay sayfasında teknik özellikleri gösteren ikonlu liste.
- `comparison-table`: Mobilde kaydırılabilir karşılaştırma tablosu.

#### Kenar Çubuğu (Sidebar)
- `sidebar-filters`: Filtreleme başlıkları ve seçenekleri için yer tutucu grup.

### 11.3 Entegrasyon Notları (Placeholders)
Tema oluşturulurken aşağıdaki alanlar "boş" veya "yer tutucu" HTML olarak bırakılmalı, ancak yapısal olarak **Container** görevi görmelidir:
1.  **Arama Formu:** `hero-tabbed-search` deseni içinde arama formu HTML'i yerine bir `div` veya `shortcode` bloğu bırakılabilir. Biz buraya `[rentiva_search_form]` yerleştireceğiz.
2.  **Filtreler:** Sidebar alanında statik checkboxlar olabilir (tasarım için), biz bunları dinamik `[rentiva_filters]` ile değiştireceğiz.
3.  **Fiyat:** Kartlardaki fiyat alanı statik `$XX` olabilir, biz bunu dinamik veri ile besleyeceğiz.
