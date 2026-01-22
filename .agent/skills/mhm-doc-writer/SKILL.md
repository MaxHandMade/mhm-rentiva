---
name: mhm-doc-writer
description: Docusaurus tabanlı dökümantasyonu güncelleme ve yönetme yeteneği.
---

# MHM Doc Writer Skill

Bu skill, kod tabanındaki değişikliklerin `website/docs` altındaki dökümantasyona yansıtılmasını yönetir.

## Dökümantasyon Yapısı
Dökümanlar `website/docs/` altında kategorize edilmiştir:
- `01-getting-started/`: Kurulum ve başlangıç.
- `02-core-configuration/`: Temel ayarlar.
- `03-features-usage/`: Özelliklerin kullanımı.
- `04-developer/`: Geliştirici dökümanları, hook'lar, API.

## Döküman Güncelleme Kuralları

### 1. Dosya Tespiti
Kod değişikliği yapıldığında (örneğin yeni bir Shortcode eklendiğinde veya bir Ayar değiştiğinde), ilgili döküman dosyası `website/docs` altında bulunmalıdır.
- Eğer ilgili dosya yoksa, uygun kategori klasörü altına yeni bir `.md` dosyası oluşturulmalıdır.

### 2. Docusaurus Formatı
Her Markdown dosyası mutlaka **Frontmatter** ile başlamalıdır:

```markdown
---
id: dosya-id
title: Başlık (Kullanıcıya Görünen)
sidebar_label: Yan Menü Adı
---
```

### 3. İçerik Yazımı
- **Dil:** Temel dökümantasyon dili **Türkçe**'dir. Tüm başlıklar, açıklamalar ve menü etiketleri Türkçe olmalıdır. İngilizce çeviriler ayrı bir fazda yapılacaktır.
- **Stil:** Açık, anlaşılır ve teknik terimlerin doğru kullanıldığı bir dil.
- **Kod Örnekleri:** Mutlaka kod blokları (` ```php ... ``` `) ile örnekler verilmelidir.

### 4. İş Akışı
1.  **Analiz:** Değişen kodun ne yaptığını ve kullanıcıyı nasıl etkilediğini anla.
2.  **Hedef Belirleme:** `website/docs` içindeki hangi dosyanın güncellenmesi gerektiğini (veya yeni dosya yolunu) belirle.
3.  **Yazma:** Markdown içeriğini güncelle.
    - Yeni parametreler eklendiyse tabloya ekle.
    - İşleyiş değiştiyse adımları güncelle.
4.  **Kontrol:** Frontmatter yapısının bozulmadığından emin ol.

## Özel Durumlar

- **Yeni Özellik:** `03-features-usage` altına yeni dosya.
- **Dev Değişikliği (Hook, Class):** `04-developer` altına güncelleme.
- **Kritik Uyarılar:** Docusaurus "Admonitions" kullan (`:::danger`, `:::warning`, `:::tip`).

```markdown
:::warning
Bu ayarı değiştirmek veritabanı yapısını etkiler. Yedek almadan işlem yapmayın.
:::
```

### 5. Çoklu Dil ve Çeviri (i18n Strategy)
Sitemiz varsayılan olarak Türkçe'dir (`i18n/tr`). İngilizce çeviriler için Crowdin veya manuel yöntem kullanılır.
- Kod içindeki `__()` fonksiyonları `makepot` ile `.pot` dosyasına çıkarılır.
- Dökümantasyon için `website/i18n/en/docusaurus-plugin-content-docs/current/` dizini kullanılır.

### 6. Versiyonlama (Versioning)
Eski versiyonları korumak için Docusaurus'un versiyonlama özelliği kullanılır.
- Yeni bir major sürüm çıktığında (Örn: v4.0 -> v5.0):
  ```bash
  - Bu komut mevcut `docs` klasörünü `versioned_docs/version-4.5.0` altına yedekler.
- `docs` klasörü artık "Next" (Gelecek) sürüm için serbest kalır.

### 7. Medya ve Ekran Görüntüleri
- Görseller `website/static/img/` altına yüklenir.
- İsimlendirme: `feature-name-screenshot-tr.png`
- Markdown içi kullanım: `![Açıklama](/img/feature-name.png)`
- Mümkünse `.webp` veya sıkıştırılmış `.png` kullanın.
