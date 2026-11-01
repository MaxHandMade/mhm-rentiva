<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use WP_UnitTestCase;

/**
 * PR D browser round: what a rendered page proved, pinned in the sources. The
 * layout answers to the width the card really has (B2), the section control
 * cannot clip (B3), the payment card has one separator (B5). No layout engine
 * here: the browser checks are in the audit's evidence file.
 *
 * @coversNothing
 */
final class AccountLayoutCssTest extends WP_UnitTestCase
{
	private function css(): string
	{
		return (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/css/frontend/rvs-account.css');
	}

	/**
	 * B2: side-by-side grids live only inside @container; outside it the layout
	 * is the narrow one, whatever the screen width.
	 */
	public function test_two_column_grids_only_inside_container_queries(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('container: rvs-detail / inline-size;', $css);
		$this->assertStringContainsString('container: rvs-next / inline-size;', $css);
		$this->assertStringNotContainsString('@media (max-width: 1100px)', $css, 'The screen says nothing about the column.');

		$outside = (string) preg_replace('/@container[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);
		$this->assertDoesNotMatchRegularExpression('/grid-template-columns:\s*\d+px/', $outside, 'A fixed-width column outside @container.');
		$this->assertDoesNotMatchRegularExpression('/grid-template-columns:\s*minmax\(0, 1fr\)\s+\d+px/', $outside);
	}

	/**
	 * B2: the detail's size container must not hold the position: fixed dialog
	 * (older engines make it the containing block), and the dialog must stay in
	 * the page's shell.
	 */
	public function test_cancel_dialog_is_outside_the_size_container(): void
	{
		$template = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'templates/partials/rvs/account-booking-detail.php');

		$frame_opens = strpos($template, 'class="rvs-account-detail-frame"');
		$dialog      = strpos($template, "'partials/rvs/account-cancel-dialog'");
		$this->assertNotFalse($frame_opens);
		$this->assertNotFalse($dialog);

		// The cut starts inside the frame's own <div>: one more close than open.
		$between = substr($template, $frame_opens, $dialog - $frame_opens);
		$this->assertSame(
			substr_count($between, '<div') + 1,
			substr_count($between, '</div>'),
			'Every div opened since the frame, and the frame, is closed before the dialog.'
		);
	}

	/**
	 * B2 (same class, found measuring the fix): at 1280 the lists overflowed the
	 * 720px WooCommerce column by 46-75px. The table turns into cards by the
	 * section's width, not the screen's.
	 */
	public function test_list_cards_follow_the_section_width(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('container: rvs-list / inline-size;', $css);
		$this->assertMatchesRegularExpression('/@container rvs-list \(max-width: \d+px\) \{\s*\.rvs-shell \.rvs-account-bookings__table thead/', $css);
		$this->assertDoesNotMatchRegularExpression('/__(dates|date) \{[^}]*white-space: nowrap/', $css, 'A date that cannot wrap sets the table a minimum wider than the column.');
	}

	/**
	 * B3: on phones the section control wraps and is not a scroller.
	 */
	public function test_section_control_wraps_on_phones(): void
	{
		$rule = $this->rule('.rvs-shell .rvs-account-bookings__head > .rvs-seg');
		$this->assertStringContainsString('flex-wrap: wrap', $rule);
		$this->assertStringContainsString('overflow: visible', $rule);
	}

	/**
	 * B5 (round 2): a card's values are flex items separated by the gap, never by
	 * a dot pseudo-element: a dot cannot follow its value onto a new line, so a
	 * wrap left a dot at a line start (bookings) and at a line end (payments).
	 */
	public function test_card_values_are_separated_by_gap_not_dots(): void
	{
		$css  = $this->css();
		$card = (string) substr($css, (int) strpos($css, '@container rvs-list (max-width: 700px) {'));
		$this->assertDoesNotMatchRegularExpression('/content:\s*"[^"]*\x{00B7}[^"]*"/u', $card, 'A dot between card values.');
		$this->assertMatchesRegularExpression('/\.rvs-account-bookings__table tr \{[^}]*gap: 4px 12px;/s', $card, 'The gap is the separator.');
	}

	private function rule(string $selector): string
	{
		$css = $this->css();
		$at  = strpos($css, $selector . ' {');
		$this->assertNotFalse($at, "No rule for $selector.");
		return substr($css, (int) $at, (int) strpos($css, '}', (int) $at) - (int) $at);
	}
}
