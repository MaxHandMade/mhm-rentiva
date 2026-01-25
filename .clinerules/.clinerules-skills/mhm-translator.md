name: mhm-translator
description: POT dosyasından otomatik dil dosyaları (.po/.mo) üreten AI çevirmen.
---

# 🌍 MHM AI Translator Skill

## Görev
`languages/mhm-rentiva.pot` dosyasındaki yeni terimleri tespit edip `mhm-rentiva-tr_TR.po` dosyasına eklemek ve çevirmek.

## Çeviri Sözlüğü (Glossary)
Agent aşağıdaki terimlere sadık kalmalıdır:
- **Book / Booking:** Rezervasyon (Asla "Kitap" olarak çevirme)
- **Item / Vehicle:** Araç
- **Checkout:** Ödeme Sayfası
- **Rate:** Günlük Fiyat
- **Add-on:** Ek Hizmet

## İş Akışı
1. **Analiz:** `.pot` dosyası ile mevcut `.po` dosyasını karşılaştır.
2. **Çeviri:** Eksik olan `msgid`leri Türkçe'ye çevir (`msgstr`).
3. **Format:** Asla kod yapısını bozma (`#: src/file.php:123` referanslarını koru).
4. **Derleme:** İşlem bitince mutlaka `wp i18n make-mo` komutunu çalıştır.