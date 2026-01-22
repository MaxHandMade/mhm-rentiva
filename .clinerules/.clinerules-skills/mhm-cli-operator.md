---
name: mhm-cli-operator
description: WP CLI ve Terminal komutlarını kullanarak sistem yönetimi, veri doğrulama ve test verisi oluşturma uzmanı.
---

# MHM CLI Operator Skill

Bu skill, MHM Rentiva projesinde arayüz (UI) kullanmadan, doğrudan terminal üzerinden sistem durumunu sorgulamak, veri üretmek ve "Cerrahi Müdahale" yapmak için kullanılır.

**Temel Kural:** "Arayüze Girme, Komutla Çöz." (Don't Click, Just Type).

## 1. İş Akışı ve Tetikleyici (Workflow)
Geliştirme, Test ve Debug aşamalarında devreye girer. Özellikle "Arka planda bu işlem gerçekleşti mi?" sorusuna en hızlı cevabı vermekle yükümlüdür.

### Kullanım Alanları
1.  **Varlık Kontrolü (Existence Check):** Bir Post, User veya Option veritabanında var mı?
2.  **Veri Fabrikası (Data Factory):** Test için hızlıca 50 tane rezervasyon veya araç oluşturmak.
3.  **Acil Müdahale:** Hatalı bir eklentiyi kapatmak, permalinkleri yenilemek.
4.  **Cron Tetikleme:** Zamanlanmış görevleri anlık çalıştırmak.

## 2. Standart Komut Şablonları

Agent, aşağıdaki senaryolarda standart WP CLI komutlarını üretmelidir:

### A. Varlık ve Meta Sorgulama
Bir ürünün veya rezervasyonun meta verileriyle birlikte durumunu kontrol eder.

# Şablon: Post ID ve Meta Key kontrolü
wp post list --post_type=[type] --meta_key=[key] --meta_value=[val] --fields=ID,post_title,post_status --allow-root

### B. WooCommerce entegrasyonu için kritik olan gizli ürünlerin kontrolü.
WooCommerce entegrasyonu için kritik olan gizli ürünlerin kontrolü.

# Şablon: SKU üzerinden ürün bulma
wp post list --post_type=product --meta_key=_sku --meta_value='mhm-rentiva-booking' --format=table --allow-root

### C. Temizlik ve Sıfırlama
Test sonrası veritabanını kirletmemek için oluşturulan verileri silme.

# Şablon: Belirli bir ID'yi zorla silme
wp post delete [ID] --force --allow-root

### D. PHP Kod Yürütme (Eval)
Doğrudan bir sınıfı veya metodu test etmek için.

# Şablon: Tek satırlık PHP kodu çalıştırma
wp eval 'echo \MHMRentiva\Plugin::VERSION;' --allow-root

### E. Önbellek ve Transient Yönetimi (Cache Ops)
Sistem davranışını etkileyen geçici verileri temizlemek için.

# Şablon: Tüm transientleri temizle (Acil durum)
wp transient delete --all --allow-root

# Şablon: Object Cache temizle
wp cache flush --allow-root

### F. Ayar Yönetimi (Option Ops)
Arayüze girmeden eklenti ayarlarını okumak veya değiştirmek için.

# Şablon: Tüm MHM ayarlarını JSON olarak gör
wp option get mhm_rentiva_settings --format=json --allow-root

# Şablon: Belirli bir ayarı güncelle (Örn: Debug Modu aç)
wp option patch insert mhm_rentiva_settings debug_mode "1" --allow-root

### G. Kullanıcı ve Yetki (User Ops)
Yönetici erişimini kurtarmak veya test kullanıcısı oluşturmak için.

# Şablon: Admin şifresini acil değiştir
wp user update [ID_veya_Email] --user_pass="YeniGucluSifre123!" --allow-root

# Şablon: Yeni Admin oluştur
wp user create yeniadmin admin@example.com --role=administrator --user_pass="Sifre123" --allow-root

## 3. Güvenlik ve Risk Kuralları (Safety First)
1. Önce Oku (Read-First): Silme (delete) veya Güncelleme (update) komutu vermeden önce, her zaman list komutu ile hedefi teyit et.
2. Dry Run: Toplu işlemlerde (Search-Replace vb.) önce --dry-run bayrağını kullan.
3. Allow Root: Komutların sonuna her zaman --allow-root bayrağını ekle (Sunucu yetki hatalarını önlemek için).
4. Sessizlik: Çıktıyı JSON formatında isteyerek (--format=json) daha sonra işlenebilir hale getir.

## 4. Örnek Komutlar

# Şablon: Post ID ve Meta Key kontrolü
wp post list --post_type=[type] --meta_key=[key] --meta_value=[val] --fields=ID,post_title,post_status --allow-root

# Şablon: SKU üzerinden ürün bulma
wp post list --post_type=product --meta_key=_sku --meta_value='mhm-rentiva-booking' --format=table --allow-root

# Şablon: Belirli bir ID'yi zorla silme
wp post delete [ID] --force --allow-root

# Şablon: Tek satırlık PHP kodu çalıştırma
wp eval 'echo \MHMRentiva\Plugin::VERSION;' --allow-root

## 5. Örnek Kullanım Senaryoları

### A. Yeni bir araç ekleme

# Şablon: Yeni bir araç ekleme
wp post create --post_type=product --post_title='Yeni Araç' --post_status=publish --meta_input='{"_price":"100","_regular_price":"100"}' --allow-root

### B. Bir aracın fiyatını güncelleme

# Şablon: Bir aracın fiyatını güncelleme
wp post update [ID] --meta_input='{"_price":"100","_regular_price":"100"}' --allow-root

### C. Bir aracı silme

# Şablon: Bir aracı silme
wp post delete [ID] --force --allow-root

### D. Bir aracın meta verilerini kontrol etme

# Şablon: Bir aracın meta verilerini kontrol etme
wp post get [ID] --meta_key=_price --meta_value=100 --format=table --allow-root

## 6. Çıktı Formatı
- Bu skill kullanıldığında, Agent sadece komutu değil, komutun ne yapacağını ve beklenen çıktıyı da açıklamalıdır.

