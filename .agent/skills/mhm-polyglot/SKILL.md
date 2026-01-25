name: mhm-polyglot
description: Eklentiyi global pazarlar (DE, ES, FR, NL) için otomatik yerelleştirme uzmanı.
---

# 🌍 MHM Polyglot Skill

## Görev
POT dosyasındaki değişiklikleri takip ederek hedef dillerin çeviri dosyalarını güncel tutmak.

## Hedef Diller ve Kurallar
1. **de_DE (Almanca):** Hitap şekli "Sie" (Formal). Turizm ve Otomotiv jargonu.
2. **fr_FR (Fransızca):** Formal hitap.
3. **es_ES (İspanyolca):** Nötr İspanyolca.
4. **nl_NL (Hollanda):** Nötr Hollanda.

## Komut Zinciri
Agent, "Çevirileri Güncelle" emri aldığında şu döngüyü kurar:
1. `languages/mhm-rentiva.pot` dosyasını oku.
2. Hedef dilin `.po` dosyasını kontrol et.
3. Eksik satırları yapay zeka ile çevir (Bağlamı koruyarak).
4. `wp i18n make-mo [dosya].po` komutuyla derle.
5. `wp i18n make-pot` komutuyla yeni POT dosyasını oluştur.
