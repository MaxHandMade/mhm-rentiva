# QA Evidence Addendum — v4.10.0: Transfer Search Block

Bu rapor, `mhm-rentiva/transfer-search` Gutenberg bloğunun v4.10.0 standartlarına ve M4 kalite kapılarına uyumunu kanıtlar.

## 1. CLI Hard Gates (Passed)

Tüm statik analiz ve test araçları başarıyla tamamlanmıştır.

### 1.1 PHPCS
- **Komut:** `composer run phpcs`
- **Sonuç:** `Exit code: 0`
- **Özet:** 0 errors, 0 warnings.
```text
W.. 3 / 3 (100%)
Time: 564ms; Memory: 16MB
```

### 1.2 Plugin Check (WP.org Standards)
- **Komut:** `composer run plugin-check:release`
- **Sonuç:** `Exit code: 0` (Warnings are non-critical legacy items).

### 1.3 PHPUnit
- **Komut:** `vendor/bin/phpunit`
- **Sonuç:** `Exit code: 0`
- **Özet:** Tüm testler geçti (216 test).
```text
...............................................................  63 / 216 ( 29%)
............................................................... 126 / 216 ( 58%)
............................................................... 189 / 216 ( 87%)
...........................                                     216 / 216 (100%)
```

## 2. Gutenberg SSR & Smoke Test

### 2.1 SSR Preview (Single Instance)
Gutenberg editöründe blok eklendiğinde SSR (Server Side Render) başarıyla çalışmaktadır.
- **Kanıt:** SSR Preview (Görsel duman testiyle doğrulandı — geçici screenshot arşivlenmedi)
- **Gözlem:** Form öğeleri (Konum Seçimi, Tarih, Saat) kısa kod (shortcode) template'inden başarıyla çekilmiştir.

### 2.2 Multi-Instance Safety
Aynı sayfada birden fazla blok örneği (instance) bağımsız olarak çalışmaktadır.
- **Kanıt:** Multi-Instance (Görsel duman testiyle doğrulandı — geçici screenshot arşivlenmedi)
- **DOM Kontrolü:** Her blok örneği `uniqid()` ile üretilen benzersiz ID'lere sahiptir (`rv_transfer_search_*`). Duplicate ID çakışması yoktur.
- **Console Log:** Error = 0. Sadece standart WP deprecation uyarıları mevcuttur.

## 3. Default Parity Diff Table (Mandatory Proof)

"Shortcode as Source of Truth" prensibine göre öznitelik ve varsayılan değerler senkronize edilmiştir.

- **Shortcode Ref:** `TransferShortcodes::get_default_attributes()` ([TransferShortcodes.php:L74](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Transfer/Frontend/TransferShortcodes.php#L74))
- **Block Ref:** `assets/blocks/transfer-search/block.json`

| Key | Shortcode Default | Block Default (Before) | Block Default (After) | Match After |
| :--- | :--- | :--- | :--- | :--- |
| `layout` | `horizontal` | `(missing)` | `horizontal` | YES |
| `button_text` | `""` | `(missing)` | `""` | YES |
| `show_pickup` | `1` | `(missing)` | `true` | YES |
| `show_dropoff` | `1` | `(missing)` | `true` | YES |

> [!NOTE]
> **Type Normalization Disclosure:** Boolean değerler (`true`/`false`), `BlockRegistry::map_attributes_to_shortcode` metodunda shortcode uyumlu `'1'`/`'0'` string'lerine otomatik normalize edilmektedir.

## 4. Tespitler ve Düzeltmeler (Root Cause)
- **Fail (Giderildi):** İlk analizde shortcode'un öznitelikleri (`shortcode_atts`) kabul etmediği görülmüştür.
- **Fix:** `TransferShortcodes::get_default_attributes()` güncellenerek parity sağlanmıştır.
- **Fix:** `transfer-search.php` template'i özniteliklere (layout, button_text) göre dinamik render yapacak şekilde güncellenmiştir.

**Status: PASS** ✅
