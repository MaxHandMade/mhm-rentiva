<?php

/**
 * Validate Changelog JSON files against schema.
 * Usage: php bin/validate-changelog.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$schemaFile = __DIR__ . '/../docs/schemas/changelog.json';
$filesToCheck = [
    __DIR__ . '/../changelog.json',
    __DIR__ . '/../changelog-tr.json',
];

if (!file_exists($schemaFile)) {
    die("Error: Schema file not found at $schemaFile\n");
}

$schemaData = json_decode(file_get_contents($schemaFile));
if (!$schemaData) {
    die("Error: Invalid JSON in schema file.\n");
}

// We need a JSON validator. 
// Since we might not have a library installed, we will do a basic structural check using PHP logic matched to the schema.
// Realistically, composer requiring proper json-schema validator is better, but for this governance layer, a simple script is enough if we don't want to add dev dependencies just yet.
// However, the user said "Governance & Release Discipline", so let's do it robustly but without external deps if possible to keep it valid.
// Actually, let's write a simple validator function related to our specific schema.

function validate_version($v)
{
    return preg_match('/^\d+\.\d+\.\d+$/', $v);
}

function validate_date($d)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

// Re-implementing basic check based on our schema
$hasErrors = false;

foreach ($filesToCheck as $file) {
    echo "Checking " . basename($file) . "...\n";
    if (!file_exists($file)) {
        echo "❌ File not found: $file\n";
        $hasErrors = true;
        continue;
    }

    $content = file_get_contents($file);
    $json = json_decode($content, true);

    if ($json === null) {
        echo "❌ Invalid JSON in " . basename($file) . "\n";
        $hasErrors = true;
        continue;
    }

    if (!is_array($json)) {
        echo "❌ Root must be an array in " . basename($file) . "\n";
        $hasErrors = true;
        continue;
    }

    foreach ($json as $index => $entry) {
        // Check required fields
        if (!isset($entry['version']) || !isset($entry['date']) || !isset($entry['changes'])) {
            echo "❌ Entry #$index missing version, date, or changes in " . basename($file) . "\n";
            $hasErrors = true;
            continue;
        }

        // Validate Version
        if (!validate_version($entry['version'])) {
            echo "❌ Invalid version format '{$entry['version']}' at entry #$index in " . basename($file) . "\n";
            $hasErrors = true;
        }

        // Validate Date
        if (!validate_date($entry['date'])) {
            echo "❌ Invalid date format '{$entry['date']}' at entry #$index in " . basename($file) . "\n";
            $hasErrors = true;
        }

        // Validate Changes
        if (!is_array($entry['changes']) || empty($entry['changes'])) {
            echo "❌ 'changes' must be a non-empty array at entry #$index in " . basename($file) . "\n";
            $hasErrors = true;
        } else {
            foreach ($entry['changes'] as $cIndex => $changeString) {
                if (!is_string($changeString) || trim($changeString) === '') {
                    echo "❌ Invalid change string at entry #$index, change #$cIndex in " . basename($file) . "\n";
                    $hasErrors = true;
                }
            }
        }
    }
}

if ($hasErrors) {
    echo "\n⛔ Validation FAILED.\n";
    exit(1);
} else {
    echo "\n✅ All changelogs are valid.\n";
    exit(0);
}
