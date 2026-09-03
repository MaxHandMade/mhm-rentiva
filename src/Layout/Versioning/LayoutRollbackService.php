<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Versioning;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\LayoutErrorMessages;
use MHMUiCore\Layout\LayoutEngine;
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
     * @param int          $post_id Post ID to rollback.
     * @param bool          $dry_run If true, only validate without writing.
     * @param LayoutEngine  $engine  Engine built from this plugin's contract.
     * @return array Success summary.
     * @throws Exception If rollback fails.
     */
    public static function rollback(int $post_id, bool $dry_run, LayoutEngine $engine): array
    {
        // 5.1 Pre-conditions
        $post = get_post($post_id);
        if (! $post) {
            throw new Exception(esc_html__('Post not found.', 'mhm-rentiva'));
        }

        $prev_manifest = get_post_meta($post_id, '_mhmrentiva_layout_manifest_previous', true);
        $prev_hash     = get_post_meta($post_id, '_mhmrentiva_layout_hash_previous', true);

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
                'manifest'       => get_post_meta($post_id, '_mhmrentiva_layout_manifest', true),
                'hash'           => get_post_meta($post_id, '_mhmrentiva_layout_hash', true),
                'timestamp'      => get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp', true),
                'manifest_prev'  => $prev_manifest,
                'hash_prev'      => $prev_hash,
                'timestamp_prev' => get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp_previous', true),
                'template'       => get_post_meta($post_id, '_wp_page_template', true),
                // get_post_meta() answers '' both for "absent" and for "empty",
                // so record which keys were really there. Restoring '' into an
                // absent key creates it instead of undoing the write.
                'existing_meta'  => self::existing_meta_keys($post_id),
            );
        }

        try {
            // STATE B — Load Previous Target & Verify Hash
            $prev_manifest_data = is_string($prev_manifest) ? json_decode($prev_manifest, true) : $prev_manifest;

            if (! is_array($prev_manifest_data)) {
                throw new Exception(__('Previous manifest data is corrupted or invalid.', 'mhm-rentiva'));
            }

            $normalized    = $engine->normalize($prev_manifest_data);
            $computed_hash = hash('sha256', (string) wp_json_encode($normalized));

            if ($computed_hash !== $prev_hash) {
                throw new Exception(__('Hash mismatch: Previous manifest data corruption detected.', 'mhm-rentiva'));
            }

            // STATE C — Validate & Gate (No Bypass)
            $validation_result = $engine->validate($prev_manifest_data);
            if (is_wp_error($validation_result)) {
                // The engine's WP_Error carries no message -- only a code and
                // a payload. LayoutErrorMessages rebuilds the sentence in this
                // plugin's text domain.
                throw new Exception(
                    sprintf(
                        /* translators: %s: governance validation error message. */
                        __('Governance validation failed for previous layout: %s', 'mhm-rentiva'),
                        sanitize_text_field(LayoutErrorMessages::render($validation_result))
                    )
                );
            }

            if ($dry_run) {
                return array(
                    'status'       => 'possible',
                    'post_id'      => $post_id,
                    'target_hash'  => $prev_hash,
                    'current_hash' => get_post_meta($post_id, '_mhmrentiva_layout_hash', true),
                    'message'      => __('Rollback is possible and valid.', 'mhm-rentiva'),
                    'gates'        => 'PASS',
                );
            }

            // STATE D — Apply via Atomic Import Path & Flip
            // Rule: Flip only after success.
            $importer = new AtomicImporter($engine);
            // Re-run atomic import on previous manifest with is_rollback => true to avoid shifting.
            // also suppress_audit => true because we log rollback separately here.
            // restrict_to_post_id keeps a multi-page manifest from dragging the
            // sibling pages back with it: only this post was asked to move, and
            // only this post's meta is flipped below.
            $summary = $importer->import($prev_manifest_data, array(
				'is_rollback'         => true,
				'suppress_audit'      => true,
				'restrict_to_post_id' => $post_id,
			));

            // The replay can resolve nothing -- an editor renaming a page breaks
            // a manifest that resolves by slug -- and it says so by returning an
            // 'ignore' row instead of raising. Flipping the meta after that would
            // advertise a version the page does not carry.
            if (! self::target_was_written($summary, $post_id)) {
                throw new Exception(
                    esc_html__(
                        'Rollback wrote nothing for this page: the previous manifest no longer resolves to it.',
                        'mhm-rentiva'
                    )
                );
            }

            // Meta Flip on Success:
            // new current = old previous
            // new previous = old current
            update_post_meta($post_id, '_mhmrentiva_layout_manifest', $snapshot['manifest_prev']);
            update_post_meta($post_id, '_mhmrentiva_layout_hash', $snapshot['hash_prev']);
            update_post_meta($post_id, '_mhmrentiva_layout_version_timestamp', $snapshot['timestamp_prev']);

            update_post_meta($post_id, '_mhmrentiva_layout_manifest_previous', $snapshot['manifest']);
            update_post_meta($post_id, '_mhmrentiva_layout_hash_previous', $snapshot['hash']);
            update_post_meta($post_id, '_mhmrentiva_layout_version_timestamp_previous', $snapshot['timestamp']);

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
            // Built first, thrown second: the message is escaped on the line that
            // assembles it. Inlining the constructor into the `throw` made the
            // sniff examine the previous-Throwable argument too, which is an
            // object with nothing to escape and no other way to pass it.
            $wrapped = new Exception(esc_html($e->getMessage()), (int) $e->getCode(), $e);
            throw $wrapped;
        }
    }


    /**
     * Did the replay actually write the page we were asked to roll back?
     *
     * @param array $summary Import summary rows.
     * @param int   $post_id The rollback target.
     * @return bool
     */
    private static function target_was_written(array $summary, int $post_id): bool
    {
        foreach ($summary as $row) {
            if ( (int) ( $row['post_id'] ?? 0 ) !== $post_id) {
                continue;
            }

            // Sibling pages are skipped on purpose and carry that reason; an
            // identical-hash skip means the page already holds the target state.
            if (( $row['reason'] ?? '' ) === 'out_of_scope') {
                continue;
            }

            if (in_array($row['status'] ?? '', array( 'update', 'create', 'skip' ), true)) {
                return true;
            }
        }

        return false;
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

        $values = array(
            '_mhmrentiva_layout_manifest'          => $snapshot['manifest'],
            '_mhmrentiva_layout_hash'              => $snapshot['hash'],
            '_mhmrentiva_layout_version_timestamp' => $snapshot['timestamp'],
            '_mhmrentiva_layout_manifest_previous' => $snapshot['manifest_prev'],
            '_mhmrentiva_layout_hash_previous'     => $snapshot['hash_prev'],
            '_mhmrentiva_layout_version_timestamp_previous' => $snapshot['timestamp_prev'],
            '_wp_page_template'                    => $snapshot['template'],
        );

        $existing = (array) ( $snapshot['existing_meta'] ?? array() );

        foreach ($values as $key => $value) {
            if (in_array($key, $existing, true)) {
                update_post_meta($post_id, $key, $value);
                continue;
            }

            delete_post_meta($post_id, $key);
        }

        clean_post_cache($post_id);
    }

    /**
     * Which of the snapshotted meta keys currently exist on a post.
     *
     * @param int $post_id Post to inspect.
     * @return string[]
     */
    private static function existing_meta_keys(int $post_id): array
    {
        $keys = array(
            '_mhmrentiva_layout_manifest',
            '_mhmrentiva_layout_hash',
            '_mhmrentiva_layout_version_timestamp',
            '_mhmrentiva_layout_manifest_previous',
            '_mhmrentiva_layout_hash_previous',
            '_mhmrentiva_layout_version_timestamp_previous',
            '_wp_page_template',
        );

        $existing = array();

        foreach ($keys as $key) {
            if (metadata_exists('post', $post_id, $key)) {
                $existing[] = $key;
            }
        }

        return $existing;
    }
}
