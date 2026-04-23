<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Versioning;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\BlueprintValidator;
use Exception;
use Throwable;



/**
 * Safe Rollback Engine
 *
 * Implements atomic one-step flip between current and previous layout states.
 * Follows the binding state machine spec (A-F) and Chief Engineer directives.
 *
 * @package MHMRentiva\Layout\Versioning
 * @since 4.18.0
 */
final class LayoutRollbackService {



    /**
     * Perform rollback for a specific post.
     *
     * @param int  $post_id Post ID to rollback.
     * @param bool $dry_run If true, only validate without writing.
     * @return array Success summary.
     * @throws Exception If rollback fails.
     */
    public static function rollback(int $post_id, bool $dry_run = false): array
    {
        // 5.1 Pre-conditions
        $post = get_post($post_id);
        if (! $post) {
            throw new Exception(esc_html__('Post not found.', 'mhm-rentiva'));
        }

        $prev_manifest = get_post_meta($post_id, '_mhm_layout_manifest_previous', true);
        $prev_hash     = get_post_meta($post_id, '_mhm_layout_hash_previous', true);

        if (empty($prev_manifest) || empty($prev_hash)) {
            throw new Exception(esc_html__('No rollback version available for this post.', 'mhm-rentiva'));
        }

        // STATE A — Snapshot Current (Only if not dry-run)
        $snapshot = array();
        if (! $dry_run) {
            $snapshot = array(
                'post_content'   => $post->post_content,
                'post_title'     => $post->post_title,
                'post_status'    => $post->post_status,
                'manifest'       => get_post_meta($post_id, '_mhm_layout_manifest', true),
                'hash'           => get_post_meta($post_id, '_mhm_layout_hash', true),
                'timestamp'      => get_post_meta($post_id, '_mhm_layout_version_timestamp', true),
                'manifest_prev'  => $prev_manifest,
                'hash_prev'      => $prev_hash,
                'timestamp_prev' => get_post_meta($post_id, '_mhm_layout_version_timestamp_previous', true),
                'template'       => get_post_meta($post_id, '_wp_page_template', true),
            );
        }

        try {
            // STATE B — Load Previous Target & Verify Hash
            $prev_manifest_data = is_string($prev_manifest) ? json_decode($prev_manifest, true) : $prev_manifest;

            if (! is_array($prev_manifest_data)) {
                throw new Exception(__('Previous manifest data is corrupted or invalid.', 'mhm-rentiva'));
            }

            $normalized    = LayoutNormalization::normalize($prev_manifest_data);
            $computed_hash = hash('sha256', (string) wp_json_encode($normalized));

            if ($computed_hash !== $prev_hash) {
                throw new Exception(__('Hash mismatch: Previous manifest data corruption detected.', 'mhm-rentiva'));
            }

            // STATE C — Validate & Gate (No Bypass)
            $validator         = new BlueprintValidator();
            $validation_result = $validator->validate($prev_manifest_data);
            if (is_wp_error($validation_result)) {
                throw new Exception(
                    sprintf(
                        /* translators: %s: governance validation error message. */
                        __('Governance validation failed for previous layout: %s', 'mhm-rentiva'),
                        sanitize_text_field( (string) $validation_result->get_error_message())
                    )
                );
            }

            if ($dry_run) {
                return array(
                    'status'       => 'possible',
                    'post_id'      => $post_id,
                    'target_hash'  => $prev_hash,
                    'current_hash' => get_post_meta($post_id, '_mhm_layout_hash', true),
                    'message'      => __('Rollback is possible and valid.', 'mhm-rentiva'),
                    'gates'        => 'PASS',
                );
            }

            // STATE D — Apply via Atomic Import Path & Flip
            // Rule: Flip only after success.
            $importer = new AtomicImporter();
            // Re-run atomic import on previous manifest with is_rollback => true to avoid shifting.
            // also suppress_audit => true because we log rollback separately here.
            $importer->import($prev_manifest_data, array(
				'is_rollback'    => true,
				'suppress_audit' => true,
			));

            // Meta Flip on Success:
            // new current = old previous
            // new previous = old current
            update_post_meta($post_id, '_mhm_layout_manifest', $snapshot['manifest_prev']);
            update_post_meta($post_id, '_mhm_layout_hash', $snapshot['hash_prev']);
            update_post_meta($post_id, '_mhm_layout_version_timestamp', $snapshot['timestamp_prev']);

            update_post_meta($post_id, '_mhm_layout_manifest_previous', $snapshot['manifest']);
            update_post_meta($post_id, '_mhm_layout_hash_previous', $snapshot['hash']);
            update_post_meta($post_id, '_mhm_layout_version_timestamp_previous', $snapshot['timestamp']);

            clean_post_cache($post_id);

            // STATE F — Audit Log & Success
            \MHMRentiva\Layout\Observability\LayoutAuditService::log_rollback($post_id, $snapshot['hash'], $snapshot['hash_prev']);

            return array(
                'status'    => 'success',
                'post_id'   => $post_id,
                'new_hash'  => $snapshot['hash_prev'],
                'old_hash'  => $snapshot['hash'],
                'timestamp' => current_time('mysql', true),
            );
        } catch (Throwable $e) {
            // STATE E — Rollback Failure Recovery (Restore Snapshot)
            if (! $dry_run && ! empty($snapshot)) {
                self::restore_snapshot($post_id, $snapshot);
            }
            if ($e instanceof Exception) {
                throw $e;
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Throwable wrapped for upstream CLI/UI handling.
            throw new Exception(sanitize_text_field($e->getMessage()), (int) $e->getCode(), $e);
        }
    }


    /**
     * Restore post and meta from snapshot.
     */
    private static function restore_snapshot(int $post_id, array $snapshot): void
    {
        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => $snapshot['post_content'],
                'post_title'   => $snapshot['post_title'],
                'post_status'  => $snapshot['post_status'],
            )
        );

        update_post_meta($post_id, '_mhm_layout_manifest', $snapshot['manifest']);
        update_post_meta($post_id, '_mhm_layout_hash', $snapshot['hash']);
        update_post_meta($post_id, '_mhm_layout_version_timestamp', $snapshot['timestamp']);

        update_post_meta($post_id, '_mhm_layout_manifest_previous', $snapshot['manifest_prev']);
        update_post_meta($post_id, '_mhm_layout_hash_previous', $snapshot['hash_prev']);
        update_post_meta($post_id, '_mhm_layout_version_timestamp_previous', $snapshot['timestamp_prev']);

        update_post_meta($post_id, '_wp_page_template', $snapshot['template']);
        clean_post_cache($post_id);
    }
}
