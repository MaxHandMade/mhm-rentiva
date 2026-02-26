<?php
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI scanner output.

/**
 * Pre-release Scan Tool
 * 
 * Scans the codebase for forbidden debug code and artifacts before release.
 * Rules:
 * - No console.log
 * - No var_dump/print_r/die/exit (unless allowed)
 * - No TODOs (optional strictness)
 * 
 * Usage: php tools/pre-release-scan.php
 */

$scan_dirs = [
    'src',
    'assets/js',
    'templates',
    'mhm-rentiva.php',
];

$extensions = ['php', 'js'];

$forbidden_patterns = [
    'all' => [
        '/TODO:/i' => 'Pending TODO item',
        '/FIXME:/i' => 'Pending FIXME item',
    ],
    'php' => [
        '/var_dump\s*\(/' => 'var_dump() found',
        '/print_r\s*\(/' => 'print_r() found',
        '/dd\s*\(/' => 'Laravel style dd() found',
        '/die\s*\(/' => 'die() found',
        '/^\\s*exit;$/' => 'Standard exit; found (verify context)',
        '/die\s*\(\s*[\'"]/' => 'die("message") found (verify context)',
        '/error_log\s*\(/' => 'error_log() found (use logger instead)',
    ],
    'js' => [
        '/console\.log\s*\(/' => 'console.log() found',
        '/console\.dir\s*\(/' => 'console.dir() found',
        '/debugger;/' => 'debugger statement found',
        '/alert\s*\(/' => 'alert() found',
    ],
];

// Exemptions (File => [Lines or Patterns])
$exceptions = [
    'src/Core/Logger.php' => ['error_log', 'print_r'],
    'tools/pre-release-scan.php' => ['all'],
    'src/Admin/Transfer/TransferAdmin.php' => ['exit'],
    'src/Admin/Settings/Tabs/PaymentSettings.php' => ['exit'],
    'src/Admin/Utilities/Database/DatabaseCheck.php' => ['exit'],
    'src/Admin/View/ViewRenderer.php' => ['exit'],
];

$root_dir = dirname(__DIR__);
$errors = [];

echo "🔍 Starting Pre-release Scan...\n";

foreach ($scan_dirs as $dir) {
    $path = $root_dir . '/' . $dir;
    if (!file_exists($path)) {
        continue;
    }

    if (is_file($path)) {
        // Handle single file
        $iterator = [new SplFileInfo($path)];
    } else {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
        } catch (Exception $e) {
            echo "⚠️  Warning: Could not scan $path: " . $e->getMessage() . "\n";
            continue;
        }
    }

    foreach ($iterator as $file) {
        $ext = $file->getExtension();
        if (!in_array($ext, $extensions)) {
            continue;
        }

        $relative_path = str_replace($root_dir . '/', '', $file->getPathname());
        $content = file_get_contents($file->getPathname());
        $lines = explode("\n", $content);

        // Check patterns
        $checks = array_merge($forbidden_patterns['all'], $forbidden_patterns[$ext] ?? []);

        foreach ($checks as $pattern => $message) {
            if (isset($exceptions[$relative_path]) && in_array('all', $exceptions[$relative_path])) {
                continue;
            }

            foreach ($lines as $line_num => $line) {
                // Skip comments (naive check)
                if (strpos(trim($line), '//') === 0 || strpos(trim($line), '*') === 0 || strpos(trim($line), '#') === 0) {
                    continue;
                }

                if (preg_match($pattern, $line)) {
                    // Check specific file exceptions
                    if (isset($exceptions[$relative_path])) {
                        $is_exempt = false;
                        foreach ($exceptions[$relative_path] as $ex) {
                            if (strpos($pattern, $ex) !== false) {
                                $is_exempt = true;
                                break;
                            }
                        }
                        if ($is_exempt) continue;
                    }

                    $errors[] = [
                        'file' => $relative_path,
                        'line' => $line_num + 1,
                        'error' => $message,
                        'code' => trim($line)
                    ];
                }
            }
        }
    }
}

if (!empty($errors)) {
    echo "❌ Scan Failed! Found " . count($errors) . " issues:\n\n";
    foreach ($errors as $e) {
        echo "[{$e['file']}:{$e['line']}] {$e['error']}\n   > {$e['code']}\n\n";
    }
    echo "Please fix these issues before releasing.\n";
    exit(1);
}

echo "✅ Scan Passed. Code is clean.\n";
exit(0);
