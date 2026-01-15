# Messages Sayfası

`MHM Rentiva > Messages`, müşterilerinizle yaptığınız tüm iletişimi tek yerde toplayan destek merkezidir. Rezervasyonlar, ödemeler veya genel sorularla ilgili mesajlar bu ekranda listelenir; cevap, durum ve öncelik yönetimi buradan yapılır.

## Genel Yapı
- **Inbox/Filters:** Var olan mesajları kategori, durum (Pending, Answered, Closed, Urgent) ve tarih aralığına göre filtreleyebilirsiniz.
- **Message Listesi:** Her satır bir mesaj trafiğini temsil eder. Sol tarafta müşteri adı, sağ tarafta son aktivite zamanı gösterilir.
- **Bulk Actions:** Seçilen mesajları toplu olarak “Mark as read/unread” veya “Delete” edebilirsiniz.

## Mesaj Detayı
- Satıra tıkladığınızda sağda (veya ayrı sayfada) mesaj trafiği açılır.
- **Thread Yapısı:** Müşteri ve admin yanıtları zaman damgası ile kronolojik olarak listelenir.
- **Replies:** Admin yanıt kutusundan HTML veya hazır şablon ekleyerek cevap verebilirsiniz. Ek görsel/dosya alanı kullanılabilir (eklentinin sürümüne göre).
- **Status Değişikliği:** Thread içinde “Mark as Answered / Closed / Urgent” gibi aksiyon butonları bulunur.
- **E-posta Entegrasyonu:** Yanıt gönderildiğinde müşteriye e-posta bildirimi gider. Email Logs üzerinden takip edilebilir.

## Ayarlar & Kısayollar
- `Messages > Settings` alt menüsünden:
  - Varsayılan kategoriler oluşturabilir, yeni durumlar ekleyebilirsiniz.
  - Otomatik yanıt metinlerini düzenleyebilirsiniz.
  - Yönetici bildirim e-postalarını yapılandırabilirsiniz.
- Müşteri panelindeki `[rentiva_messages]` kısa kodu, bu iletiyi front-end’de müşteriye gösterir; burada yapılan her işlem admin paneline senkronize olur.

## İpuçları
- **Öncelik Yönetimi:** “Urgent” ve özel statüler, mesaj listesinde farklı renklerle vurgulanır. SLA takibi için bu kategorileri kullanabilirsiniz.
- **Rezervasyon Bağlantısı:** Bir mesaj belirli bir rezervasyonla ilgiliyse, thread üzerinde ilgili rezervasyona hızlı bağlantı gösterilir.
- **Arama:** Keyword araması ile müşteri adı, rezervasyon kodu veya mesaj içeriğinde arama yapabilirsiniz.

## İlgili Dokümanlar
- [Customers](customers.md): Müşteri profillerinden mesajlara erişim.
- [Email Templates](../configuration/emails.md) *(hazırlanacak)*: Mesaj yanıt e-posta şablonlarının yönetimi.
- [Checklist](../checklists/testing-checklists.md#5-mesajlasma-ve-bildirimler): Mesaj sistemi test adımları.



