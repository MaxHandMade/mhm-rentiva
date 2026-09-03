<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Ingestion;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\LayoutEngineFactory;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The fallback title for an untitled manifest page belongs to the caller.
 *
 * 'Layout Page' is an English string the importer invents. A page created from
 * it carries that title into the site's content, where no consumer of a shared
 * package could translate it. The caller knows its own language and passes it.
 *
 * @group layout
 * @group ingestion
 */
class DefaultTitleComesFromCallerTest extends WP_UnitTestCase
{
    private AtomicImporter $importer;

    public function setUp(): void
    {
        parent::setUp();

        $this->importer = new AtomicImporter(LayoutEngineFactory::engine());
    }

    /**
     * A caller-supplied fallback is what an untitled page gets named.
     */
    public function test_the_caller_supplies_the_fallback_title(): void
    {
        $summary = $this->importer->import(
            $this->untitled_manifest(),
            ['create' => true, 'default_title' => 'Düzen Sayfası']
        );

        $this->assertSame(
            'Düzen Sayfası',
            get_post((int) $summary[0]['post_id'])->post_title,
            'The importer must use the fallback its caller handed over.'
        );
    }

    /**
     * With no fallback offered, the importer invents no English of its own.
     */
    public function test_without_a_caller_fallback_the_importer_invents_none(): void
    {
        $summary = $this->importer->import($this->untitled_manifest(), ['create' => true]);

        $this->assertSame(
            '',
            get_post((int) $summary[0]['post_id'])->post_title,
            'An absent title is empty; the importer does not name pages in English.'
        );
    }

    /**
     * Single-page manifest that deliberately carries no title.
     *
     * @return array
     */
    private function untitled_manifest(): array
    {
        return [
            'version'     => '1.0.0',
            'source'      => 'unit-test',
            'tokens'      => [],
            'components'  => [
                'hero' => ['type' => 'search_hero'],
            ],
            'constraints' => [],
            'pages'       => [
                [
                    'slug'        => 'untitled-layout-page',
                    'layout'      => 'layout_container',
                    'composition' => [
                        [
                            'component_id' => 'hero',
                            'instance_id'  => 'untitled-hero',
                            'attributes'   => [],
                        ],
                    ],
                ],
            ],
        ];
    }
}
