<?php
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI maintenance output.

function clean_dir($dir)
{
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            clean_dir($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            if (strpos($content, '') !== false) {
                echo "Cleaning $path...\n";
                $content = str_replace('', '', $content);
                file_put_contents($path, $content);
            }
        }
    }
}

clean_dir(dirname(__DIR__));
echo "Done cleaning.\n";
