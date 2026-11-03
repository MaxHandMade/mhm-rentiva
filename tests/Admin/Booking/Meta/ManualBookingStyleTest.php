<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use PHPUnit\Framework\TestCase;

/**
 * The new-booking stylesheet against the approved design and its rulings:
 * ui-core tokens only, a sidebar that really sticks (sticky needs
 * align-self: flex-start inside a flex row), the meta box title hidden for
 * sight but kept for assistive tech, and !important only where core's own
 * rules have to be beaten.
 */
final class ManualBookingStyleTest extends TestCase
{
	private function css(): string
	{
		return (string) file_get_contents(dirname(__DIR__, 4) . '/assets/css/admin/manual-booking-meta.css');
	}

	/** @return array<string, string> selector => body */
	private function rules(): array
	{
		$css = (string) preg_replace('#/\*.*?\*/#s', '', $this->css());
		// Wide-screen rules only: the narrow-screen block deliberately unsticks the aside.
		$css = (string) preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);
		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);
		$out = array();
		foreach ($m as $rule) {
			$out[ trim($rule[1]) ] = $rule[2];
		}
		return $out;
	}

	public function test_only_ui_core_tokens(): void
	{
		$this->assertDoesNotMatchRegularExpression('/var\(--mhm-/', $this->css());
	}

	public function test_aside_sticks(): void
	{
		$body = $this->rules()['.mhm-mb-aside'] ?? '';

		$this->assertMatchesRegularExpression('/position:\s*sticky/', $body);
		$this->assertMatchesRegularExpression('/align-self:\s*flex-start/', $body);
	}

	public function test_grand_total_is_emphasised(): void
	{
		$body = $this->rules()['.mhm-price-item--total'] ?? '';

		$this->assertMatchesRegularExpression('/font-weight:\s*700/', $body);
		$this->assertMatchesRegularExpression('/font-size:\s*15px/', $body);
	}

	public function test_meta_box_title_is_visually_hidden(): void
	{
		$found = false;
		foreach ($this->rules() as $selector => $body) {
			if (str_contains($selector, '#mhmrentiva_manual_booking .postbox-header .hndle')) {
				$found = true;
				$this->assertMatchesRegularExpression('/clip|clip-path/', $body);
				$this->assertStringNotContainsString('display: none', $body);
			}
		}
		$this->assertTrue($found, 'The box title keeps its accessible name, hidden for sight only.');
	}

	/**
	 * The one meta box is the whole screen: collapsing it left three tiny
	 * arrows and no form, and core keeps the closed state for the next visit
	 * (Fable PR 2 delta). Its move/collapse controls are not offered, and a
	 * stored closed state still shows the form.
	 */
	public function test_the_form_box_cannot_be_collapsed_away(): void
	{
		$rules = $this->rules();

		$this->assertMatchesRegularExpression('/display:\s*none/', $rules['#mhmrentiva_manual_booking .handle-actions'] ?? '');
		$this->assertMatchesRegularExpression('/display:\s*block/', $rules['#mhmrentiva_manual_booking.closed .inside'] ?? '');
	}

	public function test_important_only_in_core_hiding_and_hidden_helper(): void
	{
		foreach ($this->rules() as $selector => $body) {
			if (! str_contains($body, '!important')) {
				continue;
			}
			$core_hiding = str_contains($selector, '.post-type-mhmrentiva_booking #');
			$this->assertTrue($core_hiding || '.mhm-hidden' === $selector, $selector);
		}
	}
}
