<?php

declare(strict_types=1);

namespace MHMRentiva\Layout\CLI;

use MHMRentiva\Layout\BlueprintValidator;
use MHMRentiva\Layout\CompositionBuilder;
use MHMRentiva\Layout\AdapterRegistry;
use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\Versioning\LayoutRollbackService;
use MHMRentiva\Layout\Observability\LayoutHistoryService;
use MHMRentiva\Layout\Observability\LayoutDiffService;
use MHMRentiva\Layout\Observability\LayoutAuditService;
use WP_CLI;
use WP_Error;
use Exception;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Layout Import Command
 *
 * WP-CLI command to import blueprint manifests into WordPress pages.
 * Usage: wp mhm-rentiva layout import <file.json> [--dry-run] [--post_id=<id>] [--create]
 * Usage: wp mhm-rentiva layout rollback <post_id> [--dry-run]
 *
 * @package MHMRentiva\Layout\CLI
 * @since 4.14.0
 */
class LayoutImportCommand
{
    /**
     * Imports a blueprint manifest.
     *
     * <file>
     * : Path to the blueprint manifest JSON file.
     *
     * [--dry-run]
     * : Whether to perform a dry run (simulation only).
     *
     * [--create]
     * : Whether to create new pages if no match is found.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function import(array $args, array $assoc_args): void
    {
        if (empty($args)) {
            $this->log_error(__('Missing manifest file path.', 'mhm-rentiva'));
            return;
        }

        list($file_path) = $args;
        $dry_run = isset($assoc_args['dry-run']);
        $create  = isset($assoc_args['create']);

        if (! file_exists($file_path)) {
            $this->log_error(sprintf('File not found: %s', $file_path));
            return;
        }

        $raw_content = file_get_contents($file_path);
        $manifest = json_decode((string) $raw_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error(sprintf('Invalid JSON: %s', json_last_error_msg()));
            return;
        }

        // Boot Adapters
        AdapterRegistry::boot_defaults();

        $importer = new AtomicImporter();

        if ($dry_run) {
            $this->log(__('Executing side-effect free dry-run...', 'mhm-rentiva'));
            $summary = $importer->dry_run($manifest, ['create' => $create]);
            $this->render_summary_table($summary);
            $this->log_success(__('Dry-run simulation completed.', 'mhm-rentiva'));
            return;
        }

        try {
            $this->log(__('Starting atomic multi-page ingestion...', 'mhm-rentiva'));
            $summary = $importer->import($manifest, ['create' => $create]);
            $this->render_summary_table($summary);
            $this->log_success(__('All pages imported successfully.', 'mhm-rentiva'));
        } catch (Exception $e) {
            $this->log_error(sprintf(__('Import failed: %s. Rollback executed.', 'mhm-rentiva'), $e->getMessage()));
        }
    }

    /**
     * Rollback a layout to its previous version.
     *
     * <post_id>
     * : The ID of the post to rollback.
     *
     * [--dry-run]
     * : Whether to perform a dry run (validation only).
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function rollback(array $args, array $assoc_args): void
    {
        if (empty($args)) {
            $this->log_error(__('Missing post ID.', 'mhm-rentiva'));
            return;
        }

        $post_id = (int) $args[0];
        $dry_run = isset($assoc_args['dry-run']);

        // Boot Adapters for validation in Service (STATE C)
        AdapterRegistry::boot_defaults();

        if ($dry_run) {
            $this->log(__('Executing side-effect free dry-run for rollback...', 'mhm-rentiva'));
        } else {
            $this->log(sprintf(__('Initiating atomic rollback for Post ID: %d...', 'mhm-rentiva'), $post_id));
        }

        try {
            $result = LayoutRollbackService::rollback($post_id, $dry_run);

            if ($dry_run) {
                $this->log(sprintf(__('Target Hash: %s', 'mhm-rentiva'), $result['target_hash']));
                $this->log(sprintf(__('Current Hash: %s', 'mhm-rentiva'), $result['current_hash'] ?: '-'));
                $this->log_success(sprintf(__('Rollback is possible. (%s)', 'mhm-rentiva'), $result['message']));
            } else {
                $this->log(sprintf(__('Old Hash: %s', 'mhm-rentiva'), $result['old_hash']));
                $this->log(sprintf(__('New Hash: %s', 'mhm-rentiva'), $result['new_hash']));
                $this->log_success(sprintf(__('Rollback successful for Post ID: %d.', 'mhm-rentiva'), $post_id));
            }
        } catch (Exception $e) {
            $this->log_error(sprintf(__('Rollback failed: %s', 'mhm-rentiva'), $e->getMessage()));
        }
    }

    /**
     * Show layout history for a specific post.
     *
     * <post_id>
     * : The ID of the post to show history for.
     *
     * [--format=<format>]
     * : Output format (table, json, csv, yaml). Default: table.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function history(array $args, array $assoc_args): void
    {
        $post_id = (int) ($args[0] ?? 0);
        if ($post_id <= 0) {
            $this->log_error(__('Invalid post ID.', 'mhm-rentiva'));
            return;
        }

        $summary = LayoutHistoryService::get_summary($post_id);
        $events  = LayoutAuditService::get_events($post_id);

        $format = $assoc_args['format'] ?? 'table';

        if ($format === 'table') {
            $this->log(sprintf(__('Layout History for Post ID: %d', 'mhm-rentiva'), $post_id));
            $this->log('--------------------------------------------------');
            foreach ($summary as $key => $value) {
                $this->log(sprintf('%-15s: %s', ucwords(str_replace('_', ' ', $key)), $value));
            }
            $this->log('--------------------------------------------------');
            $this->log(__('Audit Trail (Last 10 events):', 'mhm-rentiva'));

            $table_events = array_map(function ($e) {
                return [
                    'Date'      => $e['timestamp'] ?? '-',
                    'Operation' => strtoupper($e['operation'] ?? '-'),
                    'Actor'     => $e['actor'] ?? '-',
                    'Result'    => sprintf('%s -> %s', substr($e['previous_hash'] ?? '', 0, 8), substr($e['new_hash'] ?? '', 0, 8)),
                ];
            }, array_slice($events, -10));

            if (function_exists('\WP_CLI\Utils\format_items')) {
                \WP_CLI\Utils\format_items('table', $table_events, ['Date', 'Operation', 'Actor', 'Result']);
            }
        } else {
            if (function_exists('\WP_CLI\Utils\format_items')) {
                \WP_CLI\Utils\format_items($format, $events, array_keys($events[0] ?? []));
            }
        }
    }

    /**
     * Show diff between current and previous layout versions.
     *
     * <post_id>
     * : The ID of the post to show diff for.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function diff(array $args, array $assoc_args): void
    {
        $post_id = (int) ($args[0] ?? 0);
        if ($post_id <= 0) {
            $this->log_error(__('Invalid post ID.', 'mhm-rentiva'));
            return;
        }

        $current = LayoutHistoryService::get_current($post_id);
        $prev    = LayoutHistoryService::get_previous($post_id);

        if (empty($prev['manifest'])) {
            $this->log_error(__('No previous layout version found to compare.', 'mhm-rentiva'));
            return;
        }

        $diff = LayoutDiffService::diff($current['manifest'], $prev['manifest']);

        $this->log(sprintf(__('Layout Diff for Post ID: %d', 'mhm-rentiva'), $post_id));
        $this->log(sprintf(__('Comparing %s -> %s', 'mhm-rentiva'), substr($prev['hash'], 0, 8), substr($current['hash'], 0, 8)));
        $this->log('--------------------------------------------------');

        // Tokens Diff
        if (! empty($diff['tokens']['added']) || ! empty($diff['tokens']['removed']) || ! empty($diff['tokens']['changed'])) {
            $this->log_success(__('Tokens Changes:', 'mhm-rentiva'));
            foreach ($diff['tokens']['added'] as $t) $this->log("  [+] $t");
            foreach ($diff['tokens']['removed'] as $t) $this->log("  [-] $t");
            foreach ($diff['tokens']['changed'] as $t => $v) $this->log("  [*] $t ({$v['from']} -> {$v['to']})");
        } else {
            $this->log(__('No Token changes.', 'mhm-rentiva'));
        }

        // Components Diff
        if (! empty($diff['components']['added']) || ! empty($diff['components']['removed']) || ! empty($diff['components']['changed'])) {
            $this->log_success(__('Components Changes:', 'mhm-rentiva'));
            foreach ($diff['components']['added'] as $c) $this->log("  [+] $c");
            foreach ($diff['components']['removed'] as $c) $this->log("  [-] $c");
            foreach ($diff['components']['changed'] as $c) $this->log("  [*] $c (modified)");
        } else {
            $this->log(__('No Component changes.', 'mhm-rentiva'));
        }
    }

    /**
     * Test-safe logging helpers.
     */
    private function log(string $msg): void
    {
        if (class_exists('WP_CLI')) {
            WP_CLI::log($msg);
        }
    }

    private function log_success(string $msg): void
    {
        if (class_exists('WP_CLI')) {
            WP_CLI::success($msg);
        }
    }

    private function log_error(string $msg): void
    {
        if (class_exists('WP_CLI')) {
            WP_CLI::error($msg);
        } else {
            throw new Exception($msg);
        }
    }

    /**
     * Render the result summary as a CLI table.
     */
    private function render_summary_table(array $summary): void
    {
        $items = array_map(function ($item) {
            return [
                'Status'  => strtoupper($item['status']),
                'ID'      => $item['post_id'] > 0 ? (string) $item['post_id'] : '-',
                'Title'   => $item['title'] ?? '-',
                'Slug'    => $item['slug'] ?? '-',
                'Message' => $item['message'] ?? 'OK'
            ];
        }, $summary);

        if (function_exists('\WP_CLI\Utils\format_items')) {
            \WP_CLI\Utils\format_items('table', $items, ['Status', 'ID', 'Title', 'Slug', 'Message']);
        }
    }
}
