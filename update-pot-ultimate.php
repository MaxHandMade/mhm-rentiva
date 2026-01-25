<?php
// 🛠️ MHM ULTIMATE POT UPDATER (Multi-Folder & Multi-Ext)
// Amacı: Birden fazla klasörü ve dosya türünü tarayıp POT dosyasını güncellemek.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🚀 MHM Ultimate POT Updater</h1><hr>";

// --- AYARLAR ---
$base_dir      = __DIR__;
$pot_file_path = $base_dir . '/languages/mhm-rentiva.pot';
$text_domain   = 'mhm-rentiva';

// 1. TARANACAK KLASÖRLER LİSTESİ (Burayı projenize göre düzenleyin)
$scan_directories = [
    'src',        // Ana Kodlar (Kesin Var)
    'templates',  // Görünüm Dosyaları (Varsa Tara)
    'assets/js'  // JS Dosyaları (Varsa Tara)
];

// 2. TARANACAK UZANTILAR
$allowed_extensions = ['php', 'js']; // Hem PHP hem JS dosyalarına bak

// --- İŞLEM BAŞLIYOR ---

if (!file_exists($pot_file_path)) die("❌ POT dosyası bulunamadı!");

$found_strings = [];
$scanned_files_count = 0;
// JS ve PHP için Regex kalıpları benzerdir (wp.i18n.__ veya __() )
$regex = "/(?:__|trans|x|_e|_n|_nx|_ex|_html|_attr)\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]" . $text_domain . "['\"]/";

echo "<ul>";

foreach ($scan_directories as $dir_name) {
    $full_path = $base_dir . '/' . $dir_name;

    if (is_dir($full_path)) {
        echo "<li>📂 <strong>$dir_name</strong> klasörü taranıyor...</li>";

        try {
            $directory = new RecursiveDirectoryIterator($full_path);
            $iterator  = new RecursiveIteratorIterator($directory);

            foreach ($iterator as $info) {
                if ($info->isFile() && in_array($info->getExtension(), $allowed_extensions)) {
                    $scanned_files_count++;
                    $content = file_get_contents($info->getPathname());

                    if (preg_match_all($regex, $content, $matches)) {
                        foreach ($matches[1] as $string) {
                            $found_strings[$string] = $info->getPathname();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo "<li>⚠️ Hata: $dir_name okunamadı.</li>";
        }
    } else {
        echo "<li>⚠️ Uyarı: <strong>$dir_name</strong> klasörü bulunamadı, geçiliyor.</li>";
    }
}
echo "</ul><hr>";

echo "<p>📊 Toplam <strong>$scanned_files_count</strong> dosya tarandı.</p>";
echo "<p>🔍 Toplam <strong>" . count($found_strings) . "</strong> benzersiz terim bulundu.</p>";

// --- YAZMA İŞLEMİ ---
$current_pot = file_get_contents($pot_file_path);
$added_count = 0;
echo "<h3>📝 Sonuçlar:</h3><ul>";

foreach ($found_strings as $string => $filepath) {
    if (strpos($current_pot, 'msgid "' . $string . '"') === false) {
        $clean_path = str_replace($base_dir, '', $filepath);
        $clean_path = str_replace('\\', '/', $clean_path);

        $entry  = "\n#: " . ltrim($clean_path, '/') . "\n";
        $entry .= "msgid \"" . $string . "\"\n";
        $entry .= "msgstr \"\"\n";

        file_put_contents($pot_file_path, $entry, FILE_APPEND);
        echo "<li style='color:green'>✅ Eklendi: <strong>$string</strong> <small>($clean_path)</small></li>";
        $added_count++;
    }
}
echo "</ul>";

if ($added_count == 0) echo "<div style='background:#eee; padding:10px;'>ℹ️ POT dosyası güncel.</div>";
else echo "<div style='background:#dff0d8; padding:10px;'>🎉 $added_count yeni terim eklendi!</div>";
