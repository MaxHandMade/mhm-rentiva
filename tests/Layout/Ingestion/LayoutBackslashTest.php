<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Ingestion;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\LayoutEngineFactory;
use MHMRentiva\Layout\Observability\LayoutAuditService;
use MHMRentiva\Layout\Versioning\LayoutRollbackService;
use MHMUiCore\Layout\LayoutEngine;
use Exception;
use WP_UnitTestCase;

/**
 * A layout import, its rollback and its audit log keep the backslashes the
 * manifest carries.
 *
 * WP-CLI reads the manifest with wp_json_file_decode(), so its strings hold raw
 * backslashes: a Windows path in `source`, a page title, an instance id, a
 * shortcode attribute. wp_insert_post(), wp_update_post() and
 * update_post_meta() unslash what they are given, and every writer here passed
 * raw values: the stored manifest lost a backslash while the stored hash was
 * the lossless one's, so the next rollback refused with "Hash mismatch".
 *
 * Plan: docs/superpowers/plans/2026-11-09-ters-bolu-layout-cli.md (r2).
 *
 * @covers \MHMRentiva\Layout\Ingestion\AtomicImporter
 * @covers \MHMRentiva\Layout\Versioning\LayoutRollbackService
 * @covers \MHMRentiva\Layout\Observability\LayoutAuditService::append_event
 */
final class LayoutBackslashTest extends WP_UnitTestCase
{
	private const SOURCE   = 'C:\\layouts\\v1.json';
	private const TITLE    = 'Kampanya \\ Yaz';
	private const CLASS_V1 = 'kampanya\\yaz';
	private const CLASS_V2 = 'kampanya\\\\kis "2027"';
	private const SLUG     = 'ters-bolu-yerlesim';

	private LayoutEngine $engine;

	public function setUp(): void
	{
		parent::setUp();
		$this->engine = LayoutEngineFactory::engine();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function manifest(string $instance, string $class, string $source, array $page_extra = array()): array
	{
		return array(
			'version'     => '1.0.0',
			'source'      => array(
				'file'  => $source,
				'notes' => array( 'nested' => 'a\\b', 'quote' => 'say "hi"' ),
			),
			'tokens'      => array(),
			'components'  => array(
				'hero' => array( 'type' => 'search_hero' ),
			),
			'constraints' => array(),
			'pages'       => array(
				array_merge(
					array(
						'title'       => self::TITLE,
						'slug'        => self::SLUG,
						'layout'      => 'layout_container',
						'composition' => array(
							array(
								'component_id' => 'hero',
								'instance_id'  => $instance,
								'attributes'   => array( 'class' => $class ),
							),
						),
					),
					$page_extra
				),
			),
		);
	}

	private function v1(): array
	{
		return $this->manifest('hero\\v1', self::CLASS_V1, self::SOURCE);
	}

	private function v2(int $post_id): array
	{
		return $this->manifest('hero\\v2', self::CLASS_V2, 'D:\\layouts\\v2.json', array( 'post_id' => $post_id ));
	}

	private function hash_of(array $manifest): string
	{
		return hash('sha256', (string) wp_json_encode($this->engine->normalize($manifest)));
	}

	private function import(array $manifest, array $options = array()): int
	{
		$summary = ( new AtomicImporter($this->engine) )->import($manifest, $options);
		$this->assertNotEmpty($summary);

		return (int) $summary[0]['post_id'];
	}

	private function assert_state(int $post_id, array $current, ?array $previous, string $class, string $instance, string $where): void
	{
		clean_post_cache($post_id);
		$post = get_post($post_id);

		$this->assertSame(self::TITLE, $post->post_title, "post_title, $where");
		$this->assertStringContainsString(esc_attr($class), $post->post_content, "post_content class, $where");
		$this->assertStringContainsString($instance, $post->post_content, "post_content instance id, $where");

		$this->assertSame($current, get_post_meta($post_id, '_mhmrentiva_layout_manifest', true), "current manifest, $where");
		$this->assertSame($this->hash_of($current), get_post_meta($post_id, '_mhmrentiva_layout_hash', true), "current hash, $where");

		if (null !== $previous) {
			$this->assertSame($previous, get_post_meta($post_id, '_mhmrentiva_layout_manifest_previous', true), "previous manifest, $where");
			$this->assertSame($this->hash_of($previous), get_post_meta($post_id, '_mhmrentiva_layout_hash_previous', true), "previous hash, $where");
		}
	}

	public function test_a_created_page_keeps_every_backslash(): void
	{
		$id = $this->import($this->v1(), array( 'create' => true ));

		$this->assert_state($id, $this->v1(), null, self::CLASS_V1, 'hero\\v1', 'after create');
	}

	public function test_an_update_keeps_every_backslash_in_current_and_previous(): void
	{
		$id = $this->import($this->v1(), array( 'create' => true ));
		$this->import($this->v2($id));

		$this->assert_state($id, $this->v2($id), $this->v1(), self::CLASS_V2, 'hero\\v2', 'after update');
	}

	public function test_rollback_and_rollback_again_flip_without_losing_a_backslash(): void
	{
		$id = $this->import($this->v1(), array( 'create' => true ));
		$this->import($this->v2($id));

		$result = LayoutRollbackService::rollback($id, false, $this->engine);
		$this->assertSame('success', $result['status']);
		$this->assert_state($id, $this->v1(), $this->v2($id), self::CLASS_V1, 'hero\\v1', 'after first rollback');

		// A missed writer on the previous side shows only on the way back.
		$result = LayoutRollbackService::rollback($id, false, $this->engine);
		$this->assertSame('success', $result['status']);
		$this->assert_state($id, $this->v2($id), $this->v1(), self::CLASS_V2, 'hero\\v2', 'after second rollback');
	}

	public function test_a_failed_batch_restores_an_existing_page_without_losing_a_backslash(): void
	{
		$id = $this->import($this->v1(), array( 'create' => true ));
		$before_content = get_post($id)->post_content;

		$manifest                          = $this->v2($id);
		$manifest['pages'][]               = array(
			'title'       => 'Broken',
			'slug'        => 'ters-bolu-yerlesim-bozuk',
			'layout'      => 'layout_container',
			'composition' => array( array( 'component_id' => 'missing_component', 'instance_id' => 'x' ) ),
		);

		try {
			( new AtomicImporter($this->engine) )->import($manifest, array( 'create' => true ));
			$this->fail('The second page must break the batch.');
		} catch (Exception $e) {
			$this->assertStringContainsString('Composition error', $e->getMessage());
		}

		$this->assertSame($before_content, get_post($id)->post_content, 'content restored exactly');
		$this->assert_state($id, $this->v1(), null, self::CLASS_V1, 'hero\\v1', 'after the batch failed');
		$this->assertFalse(metadata_exists('post', $id, '_mhmrentiva_layout_manifest_previous'), 'a key the page did not have is removed again');
	}

	public function test_a_rollback_that_writes_nothing_restores_without_losing_a_backslash(): void
	{
		$id = $this->import($this->v1(), array( 'create' => true ));
		$this->import($this->v2($id));
		$before_content = get_post($id)->post_content;

		// The previous manifest resolves by slug; a renamed page leaves it nothing.
		wp_update_post(wp_slash(array( 'ID' => $id, 'post_name' => 'yeniden-adlandirildi' )));

		try {
			LayoutRollbackService::rollback($id, false, $this->engine);
			$this->fail('The rollback must refuse.');
		} catch (Exception $e) {
			$this->assertStringContainsString('Rollback wrote nothing', $e->getMessage());
		}

		$this->assertSame($before_content, get_post($id)->post_content, 'content restored exactly');
		$this->assertSame(self::TITLE, get_post($id)->post_title);
		$this->assertSame($this->v2($id), get_post_meta($id, '_mhmrentiva_layout_manifest', true));
		$this->assertSame($this->v1(), get_post_meta($id, '_mhmrentiva_layout_manifest_previous', true));
	}

	public function test_the_audit_log_keeps_an_earlier_event_word_for_word(): void
	{
		$id = self::factory()->post->create(array( 'post_type' => 'page' ));

		LayoutAuditService::append_event($id, array( 'operation' => 'restore_failure', 'error' => 'C:\\wp\\page.php failed' ));
		LayoutAuditService::append_event($id, array( 'operation' => 'import', 'previous_hash' => 'a', 'new_hash' => 'b' ));

		$log = get_post_meta($id, '_mhmrentiva_layout_audit_log', true);
		$this->assertSame('C:\\wp\\page.php failed', $log[0]['error'], 'compared with the original, not with the first write');
	}
}
