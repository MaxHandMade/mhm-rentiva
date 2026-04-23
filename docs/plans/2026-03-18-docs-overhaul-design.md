# Tasarım Belgesi: MHM Rentiva Dokümantasyon Dönüşümü (2026-03-18)

**Tarih:** 18 Mart 2026  
**Durum:** Taslak/Onaylandı  
**Konu:** Dokümantasyonun Hibrit (Basit -> Teknik) Model ile Yeniden Yapılandırılması

---

## 1. Amaç ve Hedefler
Mevcut dokümantasyonu statik listelerden, görsel destekli, hem basit kullanıcıyı (işletme sahibi) hem de teknik kullanıcıyı (geliştirici) tatmin edecek zengin bir hibrit formata dönüştürmek.

- **Hedef Kitle:** WordPress site sahipleri, Ajanslar, Yazılımcılar.
- **Dil:** Türkçe (Tüm revizyon bittikten sonra İngilizce versiyon hazırlanacak).
- **Format:** Docusaurus 4 (Markdown + MDX).

---

## 2. Tasarım Prensipleri
- **Hibrit Metin Yapısı:** Sayfalar kolay anlaşılır bir girişle başlar, ilerledikçe derin teknik detaylara (API, Hook vb.) iner.
- **Ekran Görüntüleri:** Her kritik işlem için `![Açıklama](./img/dosya-adi.png)` formatında placeholderlar kullanılacak.
- **Video Entegrasyonu:** Gelecek YouTube videoları için "Yakında Gelecektir" notuyla şık placeholder kutuları eklenecek.
- **Bütüncül Kapsam:** Vendor (geliştirme aşamasında olsa da) dahil tüm eklenti modülleri dokümante edilecek.
- **Build Kontrolü:** Her sayfa güncellemesinden sonra `npm run build` ile yerel sağlamlık kontrolü yapılacak.

---

## 3. Yapılandırma ve Mimarî
Dokümantasyon klasör yapısı (`website/docs/`) korunacak ancak içerikler modül bazlı yenilenecek:

| Modül | Klasör | Odak Noktası |
|---|---|---|
| **Başlangıç Rehberi** | `01-getting-started` | Kurulum, İlk Ayarlar, Sihirbaz, Checklist. |
| **Temel Yapılandırma** | `02-core-configuration` | Ayarlar, Ödemeler, E-posta Entegrasyonu. |
| **Özellikler ve Kullanım** | `03-features-usage` | Araçlar, Rezervasyonlar, Vendor Modülü, VIP Transfer. |
| **Geliştirici Rehberi** | `04-developer` | REST API, Hooks/Filters, Veritabanı İlişkileri. |
| **Diğer** | `05` - `08` | FAQ, Tema Uyumluluğu, API Referansları. |

---

## 4. İş Akışı (Sayfa Bazlı)
1. **Analiz:** Mevcut `.md` dosyasını oku, eklenti içindeki karşılığıyla (Vendor vb.) kıyasla.
2. **Düzenleme:** İçeriği hibrit modele göre zenginleştir, gereksizleri sil.
3. **Build:** `npm run build` ile derleme hatası olmadığını doğrula.
4. **Kontrol:** Build çıktısını (HTML) veya dev server'ı localde incele.
5. **Next:** Bir sonraki sayfaya geç.

---

## 5. Önemli Notlar
- İngilizce versiyon tüm Türkçe revizyon bittikten sonra hazırlanacaktır.
- Vendor modülü gibi güncel geliştirmeler "Beta" veya "Geliştirme Aşamasında" notuyla mutlaka dokümana dahil edilecektir.
