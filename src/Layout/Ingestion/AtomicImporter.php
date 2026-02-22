<?php

declare(strict_types=1);
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal control flow, not direct HTML output.

namespace MHMRentiva\Layout\Ingestion;

use MHMRentiva\Layout\BlueprintValidator;
use MHMRentiva\Layout\CompositionBuilder;
use MHMRentiva\Layout\Versioning\LayoutNormalization;
use MHMRentiva\Layout\Observability\LayoutAuditService;
use Exception;
use Throwable;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Atomic Importer
 *
 * Orchestrates multi-page ingestion with atomic rollback support.
 *
 * @package MHMRentiva\Layout\Ingestion
 * @since 4.16.0
 */
class AtomicImporter
{
    /**
     * @var int[] IDs of posts created during the current batch.
     */
    private array $undo_stack = [];

    /**
     * @var array Snapshots of modified posts for rollback.
     */
    private array $snapshots = [];

    /**
     * @var CompositionBuilder
     */
    private CompositionBuilder $builder;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->builder = new CompositionBuilder();
    }

    /**
     * Import manifest pages atomically.
     *
     * @param array $manifest Manifest data.
     * @param array $options  Import options (create).
     * @return array Summary of operations.
     * @throws Exception If validation or write fails.
     */
    public function import(array $manifest, array $options = []): array
    {
        $this->undo_stack = [];
        $this->snapshots  = [];

        // 1. Pre-Validation
        $validator = new BlueprintValidator();
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for internal import control flow.
        $validation_result = $validator->validate($manifest);
        if (is_wp_error($validation_result)) {
            throw new Exception(sanitize_text_field((string) $validation_result->get_error_message()));
        }

        // 2. Hash Calculation (Task 1)
        $normalized = LayoutNormalization::normalize($manifest);
        $hash       = hash('sha256', (string) wp_json_encode($normalized));

        $pages = $manifest['pages'] ?? [];
        if (empty($pages)) {
            return [];
        }

        $summary = [];

        try {
            foreach ($pages as $index => $page_data) {
                $resolution = $this->resolve_page($page_data, $options);

                if ($resolution['status'] === 'ignore') {
                    $summary[] = $resolution;
                    continue;
                }

                // Composition Build
                $markup = $this->builder->build($manifest, $page_data);
                if (is_wp_error($markup)) {
                    throw new Exception(sprintf(
                        /* translators: 1: page index, 2: error message. */
                        __('Composition error in page %1$d: %2$s', 'mhm-rentiva'),
                        $index,
                        sanitize_text_field((string) $markup->get_error_message())
                    ));
                }

                if ($resolution['status'] === 'update') {
                    // Check if hash matches (Task 2: Skip identical)
                    if (($resolution['current_hash'] ?? '') === $hash) {
                        $resolution['status'] = 'skip';
                        $resolution['message'] = esc_html__('Layout identical, skipping update.', 'mhm-rentiva');
                        $summary[] = $resolution;
                        continue;
                    }
                    $this->perform_update($resolution['post_id'], $markup, $manifest, $hash, $options);
                } elseif ($resolution['status'] === 'create') {
                    $new_id = $this->perform_create($page_data, $markup, $manifest, $hash);
                    $resolution['post_id'] = $new_id;
                }

                $summary[] = $resolution;
            }
        } catch (Throwable $e) {
            $this->rollback();
            if ($e instanceof Exception) {
                throw $e;
            }
            $exception_message = sanitize_text_field($e->getMessage());
            throw new Exception($exception_message, (int) $e->getCode(), $e);
        }

        return $summary;
    }

    /**
     * Dry-run simulation (100% side-effect free).
     *
     * @param array $manifest
     * @param array $options
     * @return array Summary of what would happen.
     */
    public function dry_run(array $manifest, array $options = []): array
    {
        $pages = $manifest['pages'] ?? [];
        $summary = [];

        foreach ($pages as $page_data) {
            $summary[] = $this->resolve_page($page_data, $options);
        }

        return $summary;
    }

    /**
     * Deterministic Page Resolution with Hash Awareness
     */
    private function resolve_page(array $page_data, array $options): array
    {
        $post_id = isset($page_data['post_id']) ? (int) $page_data['post_id'] : 0;
        $slug    = $page_data['slug']    ?? '';
        $title   = $page_data['title']   ?? 'Layout Page';

        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                return [
                    'status'       => 'update',
                    'post_id'      => $post->ID,
                    'title'        => $post->post_title,
                    'slug'         => $post->post_name,
                    'current_hash' => get_post_meta($post->ID, '_mhm_layout_hash', true)
                ];
            }
        }

        if (!empty($slug)) {
            $existing = get_page_by_path($slug, OBJECT, 'page');
            if ($existing) {
                return [
                    'status'       => 'update',
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal state read from existing page object.
                    'post_id'      => $existing->ID,
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal state read from existing page object.
                    'title'        => $existing->post_title,
                    'slug'         => $existing->post_name,
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal metadata read, not directly rendered.
                    'current_hash' => get_post_meta($existing->ID, '_mhm_layout_hash', true)
                ];
            }
        }

        if (!empty($options['create'])) {
            return ['status' => 'create', 'post_id' => 0, 'title' => $title, 'slug' => $slug, 'current_hash' => ''];
        }

        return [
            'status'       => 'ignore',
            'post_id'      => 0,
            'title'        => $title,
            'slug'         => $slug,
            'current_hash' => '',
            'message'      => esc_html__('Page not found and --create flag not set.', 'mhm-rentiva')
        ];
    }

    /**
     * Perform update with snapshotting and state shifting.
     */
    private function perform_update(int $post_id, string $markup, array $manifest, string $hash, array $options = []): void
    {
        clean_post_cache($post_id);
        $post = get_post($post_id);
        if (!$post) {
            throw new Exception(
                sprintf(
                    /* translators: %d: post ID. */
                    esc_html__('Post ID %d lost during processing.', 'mhm-rentiva'),
                    $post_id
                )
            );
        }

        $is_rollback = ! empty($options['is_rollback']);

        // 1. Snapshot for atomicity (Internal Rollback)
        $this->snapshots[$post_id] = [
            'post_content' => $post->post_content,
            'post_title'   => $post->post_title,
            'post_status'  => $post->post_status,
            'manifest'     => get_post_meta($post_id, '_mhm_layout_manifest', true),
            'hash'         => get_post_meta($post_id, '_mhm_layout_hash', true),
            'timestamp'    => get_post_meta($post_id, '_mhm_layout_version_timestamp', true),
            'template'     => get_post_meta($post_id, '_wp_page_template', true),
            // Previous set for full restore if needed
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal metadata snapshot for rollback.
            'manifest_prev'  => get_post_meta($post_id, '_mhm_layout_manifest_previous', true),
            'hash_prev'      => get_post_meta($post_id, '_mhm_layout_hash_previous', true),
            'timestamp_prev' => get_post_meta($post_id, '_mhm_layout_version_timestamp_previous', true),
        ];

        // 2. State Shifting (Current -> Previous) - ONLY if NOT a rollback
        if (! $is_rollback) {
            $current_manifest = get_post_meta($post_id, '_mhm_layout_manifest', true);
            if (! empty($current_manifest)) {
                update_post_meta($post_id, '_mhm_layout_manifest_previous', $current_manifest);
                update_post_meta($post_id, '_mhm_layout_hash_previous', get_post_meta($post_id, '_mhm_layout_hash', true));
                update_post_meta($post_id, '_mhm_layout_version_timestamp_previous', get_post_meta($post_id, '_mhm_layout_version_timestamp', true));
            }
        }

        // 3. Write Current
        wp_update_post(['ID' => $post_id, 'post_content' => $markup], true);
        update_post_meta($post_id, '_mhm_layout_manifest', $manifest);
        update_post_meta($post_id, '_mhm_layout_hash', $hash);
        update_post_meta($post_id, '_mhm_layout_version_timestamp', current_time('mysql', true));

        // 4. Audit Log (Task: Observability)
        if (empty($options['suppress_audit'])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal telemetry call, no direct output.
            LayoutAuditService::log_import($post_id, $this->snapshots[$post_id]['hash'] ?? '', $hash, false);
        }
    }

    /**
     * Perform creation and track for force-deletion.
     */
    private function perform_create(array $page_data, string $markup, array $manifest, string $hash): int
    {
        $new_id = wp_insert_post([
            'post_title'   => $page_data['title'] ?? 'Layout Page',
            'post_name'    => $page_data['slug']  ?? '',
            'post_content' => $markup,
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ], true);

        if (is_wp_error($new_id)) {
            throw new Exception(sanitize_text_field((string) $new_id->get_error_message()));
        }

        $this->undo_stack[] = $new_id;
        update_post_meta($new_id, '_mhm_layout_manifest', $manifest);
        update_post_meta($new_id, '_mhm_layout_hash', $hash);
        update_post_meta($new_id, '_mhm_layout_version_timestamp', current_time('mysql', true));

        // Audit Log for creation
        LayoutAuditService::log_import($new_id, '', $hash);

        return $new_id;
    }

    private function rollback(): void
    {
        foreach ($this->snapshots as $post_id => $data) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $data['post_content'],
                'post_title'   => $data['post_title'],
                'post_status'  => $data['post_status'],
            ]);
            update_post_meta($post_id, '_mhm_layout_manifest', $data['manifest']);
            update_post_meta($post_id, '_mhm_layout_hash', $data['hash']);
            update_post_meta($post_id, '_mhm_layout_version_timestamp', $data['timestamp']);

            update_post_meta($post_id, '_mhm_layout_manifest_previous', $data['manifest_prev']);
            update_post_meta($post_id, '_mhm_layout_hash_previous', $data['hash_prev']);
            update_post_meta($post_id, '_mhm_layout_version_timestamp_previous', $data['timestamp_prev']);
            update_post_meta($post_id, '_wp_page_template', $data['template']);
            clean_post_cache($post_id);
        }

        foreach ($this->undo_stack as $post_id) {
            wp_delete_post($post_id, true);
        }
    }
}
