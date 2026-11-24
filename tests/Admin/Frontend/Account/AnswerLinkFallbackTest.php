<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AnswerLinkFallback;
use WP_UnitTestCase;

/**
 * Without an add-on that answers the sign-in-free link, the link lands on a
 * plain "no longer valid" page (HTTP 410) instead of the site's front page.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AnswerLinkFallback
 */
final class AnswerLinkFallbackTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		remove_all_filters( 'mhmrentiva_answer_page_handled' );
		set_query_var( AnswerLinkFallback::QUERY_VAR, '' );
		parent::tearDown();
	}

	public function test_query_var_is_registered(): void
	{
		AnswerLinkFallback::register();

		$vars = apply_filters( 'query_vars', array() );

		$this->assertContains( 'mhmrentiva_answer', $vars );
	}

	public function test_invalid_link_page_is_a_410_with_one_sentence(): void
	{
		set_query_var( AnswerLinkFallback::QUERY_VAR, 'x' );

		$html = AnswerLinkFallback::render();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'This link is no longer valid.', $html );
		$this->assertSame( 410, AnswerLinkFallback::STATUS );
	}

	public function test_nothing_when_a_listener_handled_the_request(): void
	{
		set_query_var( AnswerLinkFallback::QUERY_VAR, 'x' );
		add_filter( 'mhmrentiva_answer_page_handled', '__return_true' );

		$this->assertNull( AnswerLinkFallback::render() );
	}

	public function test_nothing_without_the_query_var(): void
	{
		$this->assertNull( AnswerLinkFallback::render() );
	}
}
