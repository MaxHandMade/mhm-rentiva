<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Ingestion;

if (! defined('ABSPATH')) {
    exit;
}




use MHMRentiva\Layout\LayoutErrorMessages;
use MHMRentiva\Layout\Observability\LayoutAuditService;
use MHMUiCore\Layout\LayoutEngine;
use Exception;
use Throwable;
use WP_Error;



/**
 * Atomic Importer
 *
 * Orchestrates multi-page ingestion with atomic rollback support.
 *
 * @package MHMRentiva\Layout\Ingestion
 * @since 4.16.0
 */
class AtomicImporter {

    /**
     * The post type this importer writes to.
     *
     * Named TARGET_ deliberately: this importer registers nothing, it addresses
     * a type WordPress already owns. The house gate reads a bare POST_TYPE
     * constant as a registration and would rightly ask why 'page' carries no
     * plugin prefix. Written once so the ID path and the slug path cannot
     * disagree about what a layout page is.
     */
    private const TARGET_POST_TYPE = 'page';

    /**
     * Post meta this importer owns and therefore snapshots before writing.
     *
     * Reading meta answers '' both for "stored as empty" and for "never
     * stored", so the snapshot records which of these keys actually existed.
     * Restoring '' into a key that was absent does not undo the write -- it
     * creates the key.
     */
    private const SNAPSHOT_META_KEYS = [
        '_mhmrentiva_layout_manifest',
        '_mhmrentiva_layout_hash',
        '_mhmrentiva_layout_version_timestamp',
        '_mhmrentiva_layout_manifest_previous',
        '_mhmrentiva_layout_hash_previous',
        '_mhmrentiva_layout_version_timestamp_previous',
        '_wp_page_template',
    ];

    /**
     * @var int[] IDs of posts created during the current batch.
     */
    private array $undo_stack = [];

    /**
     * @var array Audit events held until the batch succeeds.
     */
    private array $pending_audit = [];

    /**
     * @var array Snapshots of modified posts for rollback.
     */
    private array $snapshots = [];

    /**
     * The blueprint engine, owned by mhm/ui-core.
     *
     * Required, not optional: every call site must hand over an engine built
     * from this plugin's own contract. A nullable parameter with an internal
     * fallback would turn a missed wiring into a runtime surprise on a
     * customer's site; a required one turns it into an error at the call.
     *
     * @var LayoutEngine
     */
    private LayoutEngine $engine;

    /**
     * Constructor
     *
     * @param LayoutEngine $engine Engine built from this plugin's contract.
     */
    public function __construct(LayoutEngine $engine)
    {
        $this->engine = $engine;
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
        $this->undo_stack    = [];
        $this->snapshots     = [];
        $this->pending_audit = [];

        // 1. Pre-Validation
        $validation_result = $this->engine->validate($manifest);
        if (is_wp_error($validation_result)) {
            // The engine's WP_Error carries no message -- only a code and a
            // payload. LayoutErrorMessages turns that back into a sentence in
            // this plugin's text domain.
            throw new Exception(esc_html(LayoutErrorMessages::render($validation_result)));
        }

        // 2. Hash Calculation
        $normalized = $this->engine->normalize($manifest);
        $hash       = hash('sha256', (string) wp_json_encode($normalized));

        $pages = $manifest['pages'] ?? [];
        if (empty($pages)) {
            return [];
        }

        $summary = [];

        // A rollback asks about one page, but the manifest it replays describes
        // all of them. Without this restriction the sibling pages are rewritten
        // to a version nobody asked for, while only the target's meta is flipped.
        $restrict_to = isset($options['restrict_to_post_id']) ? (int) $options['restrict_to_post_id'] : 0;

        try {
            foreach ($pages as $index => $page_data) {
                $resolution = $this->resolve_page($page_data, $options);

                if ($resolution['status'] === 'ignore') {
                    $summary[] = $resolution;
                    continue;
                }

                if ($restrict_to > 0 && (int) $resolution['post_id'] !== $restrict_to) {
                    $resolution['status'] = 'skip';
                    $resolution['reason'] = 'out_of_scope';
                    $summary[]            = $resolution;
                    continue;
                }

                // Composition Build
                $markup = $this->engine->build($manifest, $page_data);
                if (is_wp_error($markup)) {
                    throw new Exception(esc_html(sprintf(
                        /* translators: 1: page index, 2: error message. */
                        __('Composition error in page %1$d: %2$s', 'mhm-rentiva'),
                        $index,
                        LayoutErrorMessages::render($markup)
                    )));
                }

                if ($resolution['status'] === 'update') {
                    // Check if hash matches (skip identical)
                    if (( $resolution['current_hash'] ?? '' ) === $hash) {
                        $resolution['status']  = 'skip';
                        $resolution['message'] = esc_html__('Layout identical, skipping update.', 'mhm-rentiva');
                        $summary[]             = $resolution;
                        continue;
                    }
                    $this->perform_update($resolution['post_id'], $markup, $manifest, $hash, $options);
                } elseif ($resolution['status'] === 'create') {
                    $new_id                = $this->perform_create($page_data, $markup, $manifest, $hash, $options);
                    $resolution['post_id'] = $new_id;
                }

                $summary[] = $resolution;
            }
        } catch (Throwable $e) {
            $this->rollback();
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

        // The batch is complete: only now is "an import happened" true.
        if (empty($options['suppress_audit'])) {
            $this->flush_audit();
        }
        $this->pending_audit = [];

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
        $pages   = $manifest['pages'] ?? [];
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
        // The fallback title is the caller's word, not ours: a page created here
        // carries it into the site's content, where a shared package's consumer
        // could not translate a string this class invented.
        $title = $page_data['title'] ?? (string) ( $options['default_title'] ?? '' );

        if ($post_id > 0) {
            $post = get_post($post_id);

            // Resolution by slug asks for a page and so cannot stray. An ID
            // asks get_post() for anything at all, so a stale or mistyped one
            // would overwrite whatever carries that number -- a blog post, a
            // vehicle -- with layout markup. Fall through to the slug instead.
            if ($post && self::TARGET_POST_TYPE === $post->post_type) {
                return [
                    'status'       => 'update',
                    'post_id'      => $post->ID,
                    'title'        => $post->post_title,
                    'slug'         => $post->post_name,
                    'current_hash' => get_post_meta($post->ID, '_mhmrentiva_layout_hash', true),
                ];
            }
        }

        if (!empty($slug)) {
            $existing = get_page_by_path($slug, OBJECT, self::TARGET_POST_TYPE);
            if ($existing) {
                return [
                    'status'       => 'update',
                    'post_id'      => $existing->ID,
                    'title'        => $existing->post_title,
                    'slug'         => $existing->post_name,
                    'current_hash' => get_post_meta($existing->ID, '_mhmrentiva_layout_hash', true),
                ];
            }
        }

        if (!empty($options['create'])) {
            return [
				'status'       => 'create',
				'post_id'      => 0,
				'title'        => $title,
				'slug'         => $slug,
				'current_hash' => '',
			];
        }

        return [
            'status'       => 'ignore',
            'post_id'      => 0,
            'title'        => $title,
            'slug'         => $slug,
            'current_hash' => '',
            'message'      => esc_html__('Page not found and --create flag not set.', 'mhm-rentiva'),
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
                    absint($post_id)
                )
            );
        }

        $is_rollback = ! empty($options['is_rollback']);

        // 1. Snapshot for atomicity (Internal Rollback)
        $this->snapshots[ $post_id ] = [
            'post_content'   => $post->post_content,
            'post_title'     => $post->post_title,
            'post_status'    => $post->post_status,
            'manifest'       => get_post_meta($post_id, '_mhmrentiva_layout_manifest', true),
            'hash'           => get_post_meta($post_id, '_mhmrentiva_layout_hash', true),
            'timestamp'      => get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp', true),
            'template'       => get_post_meta($post_id, '_wp_page_template', true),
            // Previous set for full restore if needed
            'manifest_prev'  => get_post_meta($post_id, '_mhmrentiva_layout_manifest_previous', true),
            'hash_prev'      => get_post_meta($post_id, '_mhmrentiva_layout_hash_previous', true),
            'timestamp_prev' => get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp_previous', true),
            'existing_meta'  => self::existing_meta_keys($post_id),
        ];

        // 2. State Shifting (Current -> Previous) - ONLY if NOT a rollback
        if (! $is_rollback) {
            $current_manifest = get_post_meta($post_id, '_mhmrentiva_layout_manifest', true);
            if (! empty($current_manifest)) {
                update_post_meta($post_id, '_mhmrentiva_layout_manifest_previous', $current_manifest);
                update_post_meta($post_id, '_mhmrentiva_layout_hash_previous', get_post_meta($post_id, '_mhmrentiva_layout_hash', true));
                update_post_meta($post_id, '_mhmrentiva_layout_version_timestamp_previous', get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp', true));
            }
        }

        // 3. Write Current
        // wp_update_post() reports failure by return value, not by raising.
        // Writing the meta regardless would stamp a hash onto content the post
        // never received -- and the next import would then skip it as identical.
        $updated = wp_update_post([
			'ID'           => $post_id,
			'post_content' => $markup,
		], true);

        $failure = '';
        if (is_wp_error($updated)) {
            $failure = $updated->get_error_message();
        } elseif (0 === $updated) {
            $failure = 'unknown error';
        }

        if ('' !== $failure) {
            throw new Exception(esc_html(sprintf(
                /* translators: 1: post ID, 2: failure reason reported by WordPress. */
                __('Failed to write layout content to post %1$d: %2$s', 'mhm-rentiva'),
                absint($post_id),
                $failure
            )));
        }

        update_post_meta($post_id, '_mhmrentiva_layout_manifest', $manifest);
        update_post_meta($post_id, '_mhmrentiva_layout_hash', $hash);
        update_post_meta($post_id, '_mhmrentiva_layout_version_timestamp', current_time('mysql', true));

        // 4. Audit Log -- held until the whole batch succeeds. Written here, a
        // failed batch would leave an "import happened" entry behind, and the
        // internal rollback does not touch the log.
        $this->pending_audit[] = [
            'post_id'       => $post_id,
            'previous_hash' => (string) ( $this->snapshots[ $post_id ]['hash'] ?? '' ),
            'new_hash'      => $hash,
        ];
    }

    /**
     * Perform creation and track for force-deletion.
     */
    private function perform_create(array $page_data, string $markup, array $manifest, string $hash, array $options = []): int
    {
        $new_id = wp_insert_post([
            'post_title'   => $page_data['title'] ?? (string) ( $options['default_title'] ?? '' ),
            'post_name'    => $page_data['slug']  ?? '',
            'post_content' => $markup,
            'post_status'  => 'publish',
            'post_type'    => self::TARGET_POST_TYPE,
        ], true);

        if (is_wp_error($new_id)) {
            throw new Exception(esc_html( (string) $new_id->get_error_message()));
        }

        $this->undo_stack[] = $new_id;
        update_post_meta($new_id, '_mhmrentiva_layout_manifest', $manifest);
        update_post_meta($new_id, '_mhmrentiva_layout_hash', $hash);
        update_post_meta($new_id, '_mhmrentiva_layout_version_timestamp', current_time('mysql', true));

        // Audit Log for creation -- held until the batch succeeds, see perform_update().
        $this->pending_audit[] = [
            'post_id'       => $new_id,
            'previous_hash' => '',
            'new_hash'      => $hash,
        ];

        return $new_id;
    }

    private function rollback(): void
    {
        // Nothing this batch queued has been written yet, and nothing it queued
        // should be: the batch did not happen.
        $this->pending_audit = [];

        foreach ($this->snapshots as $post_id => $data) {
            $restored = wp_update_post([
                'ID'           => $post_id,
                'post_content' => $data['post_content'],
                'post_title'   => $data['post_title'],
                'post_status'  => $data['post_status'],
            ], true);

            $restore_failure = '';
            if (is_wp_error($restored)) {
                $restore_failure = $restored->get_error_message();
            } elseif (0 === $restored) {
                $restore_failure = 'unknown error';
            }

            if ('' !== $restore_failure) {
                // The page keeps the half-written content. Say so where it can
                // be found later: the caller only hears about the first failure.
                LayoutAuditService::log_restore_failure($post_id, $restore_failure);
            }

            self::restore_meta(
                $post_id,
                [
                    '_mhmrentiva_layout_manifest'          => $data['manifest'],
                    '_mhmrentiva_layout_hash'              => $data['hash'],
                    '_mhmrentiva_layout_version_timestamp' => $data['timestamp'],
                    '_mhmrentiva_layout_manifest_previous' => $data['manifest_prev'],
                    '_mhmrentiva_layout_hash_previous'     => $data['hash_prev'],
                    '_mhmrentiva_layout_version_timestamp_previous' => $data['timestamp_prev'],
                    '_wp_page_template'                    => $data['template'],
                ],
                (array) ( $data['existing_meta'] ?? [] )
            );

            clean_post_cache($post_id);
        }

        foreach ($this->undo_stack as $post_id) {
            wp_delete_post($post_id, true);
        }
    }

    /**
     * Write the queued audit events. Called only after the batch has succeeded.
     */
    private function flush_audit(): void
    {
        foreach ($this->pending_audit as $event) {
            LayoutAuditService::log_import(
                (int) $event['post_id'],
                (string) $event['previous_hash'],
                (string) $event['new_hash'],
                false
            );
        }

        $this->pending_audit = [];
    }

    /**
     * Which of the snapshotted meta keys currently exist on a post.
     *
     * @param int $post_id Post to inspect.
     * @return string[]
     */
    private static function existing_meta_keys(int $post_id): array
    {
        $existing = [];

        foreach (self::SNAPSHOT_META_KEYS as $key) {
            if (metadata_exists('post', $post_id, $key)) {
                $existing[] = $key;
            }
        }

        return $existing;
    }

    /**
     * Restore meta, deleting the keys that did not exist when the snapshot was taken.
     *
     * @param int      $post_id  Post to restore.
     * @param array    $values   Key => snapshotted value.
     * @param string[] $existing Keys that existed before the write.
     */
    private static function restore_meta(int $post_id, array $values, array $existing): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, $existing, true)) {
                update_post_meta($post_id, $key, $value);
                continue;
            }

            delete_post_meta($post_id, $key);
        }
    }
}
