<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Helpers;

use MHMRentiva\Helpers\AgreementText;
use WP_UnitTestCase;

/**
 * Agreement text stored as plain text, shown with light formatting.
 * Everything is escaped first; only headings, **bold**, list items and
 * --- rules become markup.
 */
final class AgreementTextTest extends WP_UnitTestCase
{
    public function test_headings_bold_rules_and_lists_and_nothing_else(): void
    {
        $html = AgreementText::to_html("# Terms\n\nPlain **bold** line\nnext line\n\n---\n- one\n- two <script>x</script>\n\n## Sub");

        $this->assertStringContainsString('<h3>Terms</h3>', $html);
        $this->assertStringContainsString('<p>Plain <strong>bold</strong> line<br>next line</p>', $html);
        $this->assertStringContainsString('<hr>', $html);
        $this->assertStringContainsString('<ul><li>one</li><li>two &lt;script&gt;x&lt;/script&gt;</li></ul>', $html);
        $this->assertStringContainsString('<h4>Sub</h4>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('**', $html);
        $this->assertStringNotContainsString('#', $html);
    }

    public function test_a_long_line_starting_with_a_hash_is_a_paragraph(): void
    {
        $html = AgreementText::to_html('# 1. Agreement **Parties:** ' . str_repeat('clause text ', 20));

        $this->assertStringStartsWith('<p>1. Agreement <strong>Parties:</strong>', $html);
        $this->assertStringNotContainsString('<h3>', $html);
    }

    public function test_multibyte_characters_are_not_split(): void
    {
        $this->assertSame(
            '<h4>Хранение данных</h4><ul><li>Usługa ąę</li></ul>',
            AgreementText::to_html("## Хранение данных\n- Usługa ąę")
        );
    }

    public function test_empty_text_gives_no_markup(): void
    {
        $this->assertSame('', AgreementText::to_html("  \n "));
    }

    public function test_the_output_survives_kses_with_the_allowed_tags(): void
    {
        $html = AgreementText::to_html("## A\n- b\n\nc **d**\n\n---");

        $this->assertSame($html, wp_kses($html, AgreementText::TAGS));
    }
}
