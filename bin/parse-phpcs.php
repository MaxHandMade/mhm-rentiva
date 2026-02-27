<?php
if ($argc < 2) {
    die("Usage: php parse-phpcs.php <json_file>\n");
}

$file = $argv[1];
if (!file_exists($file)) {
    die("File not found: $file\n");
}

$content = file_get_contents($file);

// Handle UTF-16 LE BOM
if (substr($content, 0, 2) === "\xFF\xFE") {
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
} elseif (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

// Handle garbage before JSON (e.g. progress dots)
$firstBrace = strpos($content, '{');
if ($firstBrace !== false) {
    $content = substr($content, $firstBrace);
}

$data = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON Decode Error: " . json_last_error_msg() . "\nSample: " . substr($content, 0, 100) . "\n");
}

$total_errors = $data['totals']['errors'] ?? 0;
$total_warnings = $data['totals']['warnings'] ?? 0;

echo "Found $total_errors errors and $total_warnings warnings.\n\n";

foreach ($data['files'] as $filepath => $report) {
    if ($report['errors'] === 0 && $report['warnings'] === 0) {
        continue;
    }

    // Relative path for readability
    $relPath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $filepath);
    echo "File: $relPath\n";

    foreach ($report['messages'] as $msg) {
        $type = $msg['type'];
        $line = $msg['line'];
        $source = $msg['source'];
        $message = $msg['message'];

        echo "  [$type] Line $line: $message ($source)\n";
    }
    echo "\n";
}
