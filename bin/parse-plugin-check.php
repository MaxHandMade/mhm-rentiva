<?php
$json = file_get_contents('plugin-check-report.json');
// Remove "FILE: ..." lines to leave JSON arrays
$cleanJson = preg_replace('/^FILE: .*$/m', '', $json);
// Decode concatenated JSON arrays (e.g. [] [])
// We can try to frame them as a single array or split them.
// A simple way is to match all [...] blocks.
preg_match_all('/\[(?:[^\[\]]|(?R))*\]/', $cleanJson, $matches);

if (empty($matches[0])) {
    echo "No JSON found in report.\n";
    exit(1);
}

foreach ($matches[0] as $jsonChunk) {
    if (trim($jsonChunk) === '') continue;
    $data = json_decode($jsonChunk, true);
    if (is_array($data)) {
        foreach ($data as $entry) {
            if (isset($entry['type']) && $entry['type'] === 'ERROR') {
                $file = $entry['file'] ?? 'unknown';
                $line = $entry['line'] ?? 0;
                $code = $entry['code'] ?? 'UNKNOWN';
                $message = $entry['message'] ?? '';
                echo "❌ [$code] in $file:$line\n $message\n\n";
            }
        }
    } else {
        // Maybe corrupted JSON?
        // echo "Failed to decode chunk.\n";
    }
}
