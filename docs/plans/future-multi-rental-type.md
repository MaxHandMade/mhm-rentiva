# Gelecek Sprint: Çoklu Kiralama Tipi Desteği

> Bu sprint şu an için ertelenmiştir. README Seçenek A ile güncellendi (2026-03-16).

## Hedef
Eklentiyi araç kiralama dışında bisiklet, ekipman vb. kiralamalar için de kullanılabilir hale getirmek.

## Yapılması Gerekenler

### Yüksek Öncelik
- [ ] `license_plate` alanını core'dan çıkar, opsiyonel yap (bisiklette plaka yok)
- [ ] VehicleMeta.php admin başlıklarını (`ARAÇ DETAYLARI`) çevirilebilir/yapılandırılabilir yap
- [ ] Post type etiketlerini (`vehicle`, `Araç`) dinamikleştir — eklenti ayarlarından değiştirilebilir

### Orta Öncelik
- [ ] Default features/equipment listelerini kiralama tipine göre farklılaştır
- [ ] "Araç Özellikleri" bölüm başlığını admin'de özelleştirilebilir yap
- [ ] Transfer modülünü araç-spesifik bağımlılıklardan soyutla

### Düşük Öncelik
- [ ] Onboarding/Setup Wizard'a "kiralama tipi" adımı ekle
- [ ] README'yi tekrar güncelle — bisiklet/ekipman iddialarını geri ekle

## Notlar
- Şu anki yapı araç kiralaması için optimize
- Bisiklet için teorik olarak çalışır ama UX kötü (plaka, motor hacmi zorunlu gibi görünür)
- Equipment için terminoloji (Araç, Vehicle) tamamen yanlış hissettiriyor
