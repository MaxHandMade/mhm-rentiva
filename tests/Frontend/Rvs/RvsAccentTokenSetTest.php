<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Tests\Support\RvsTokenFile;
use WP_UnitTestCase;

/**
 * Colour setting PR 1: one accent drives the front end.
 *
 * Eight tokens stay literal (the ones PR 2's inline block overrides); every other
 * accent copy is bound to them with `var()`. The spec's acceptance greps live
 * here as sets, so a later PR cannot slide them back.
 */
final class RvsAccentTokenSetTest extends WP_UnitTestCase
{
	private const OVERRIDABLE = array(
		'--rvs-bg',
		'--rvs-accent-strong',
		'--rvs-accent-deep',
		'--rvs-accent-100',
		'--rvs-sel-text',
		'--rvs-on-accent',
		'--rvs-accent-text',
		'--rvs-accent-text-hover',
	);

	public function test_new_tokens_are_literal_with_designer_defaults(): void
	{
		$decls = RvsTokenFile::declarations();

		$this->assertSame('#ffffff', $decls['--rvs-on-accent'] ?? null);
		$this->assertSame('#2271b1', $decls['--rvs-accent-text'] ?? null);
		$this->assertSame('#135e96', $decls['--rvs-accent-text-hover'] ?? null);
	}

	public function test_eight_overridable_tokens_stay_literal_hex(): void
	{
		$decls = RvsTokenFile::declarations();

		foreach (self::OVERRIDABLE as $name) {
			$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $decls[ $name ] ?? '', "{$name} is not a literal hex");
		}
	}

	public function test_copies_are_bound_to_the_accent(): void
	{
		$decls = RvsTokenFile::declarations();

		foreach (
			array(
				'--rvs-sel-bg'            => 'var(--rvs-accent-100)',
				'--rvs-sel-line'          => 'var(--rvs-accent-strong)',
				'--rvs-hover-shadow'      => '0 2px 6px color-mix(in srgb, var(--rvs-accent-deep) 35%, transparent)',
				'--rvs-card-title-hover'  => 'var(--rvs-accent-text)',
				'--rvs-card-btn-bg'       => 'var(--rvs-accent-strong)',
				'--rvs-card-btn-bg-hover' => 'var(--rvs-accent-deep)',
				'--rvs-booking-cta'       => 'var(--rvs-accent-strong)',
				'--rvs-booking-cta-hover' => 'var(--rvs-accent-deep)',
				'--rvs-st-confirmed-bg'   => 'var(--rvs-accent-100)',
				'--rvs-st-confirmed-fg'   => 'var(--rvs-sel-text)',
			) as $name => $value
		) {
			$this->assertSame($value, $decls[ $name ] ?? null, $name);
		}
	}

	public function test_bound_copies_resolve_to_todays_hex(): void
	{
		foreach (
			array(
				'--rvs-sel-bg'            => '#dcecf9',
				'--rvs-sel-line'          => '#2271b1',
				'--rvs-card-title-hover'  => '#2271b1',
				'--rvs-card-btn-bg'       => '#2271b1',
				'--rvs-card-btn-bg-hover' => '#135e96',
				'--rvs-booking-cta'       => '#2271b1',
				'--rvs-booking-cta-hover' => '#135e96',
				'--rvs-st-confirmed-bg'   => '#dcecf9',
				// Deliberate difference 1: the confirmed tag text was #135e96.
				'--rvs-st-confirmed-fg'   => '#0a3c63',
			) as $name => $hex
		) {
			$this->assertSame($hex, RvsTokenFile::resolve($name), $name);
		}
	}

	public function test_unused_tokens_are_gone(): void
	{
		$decls = RvsTokenFile::declarations();
		$names = array( '--rvs-' . 'accent-200', '--rvs-' . 'n-700' );

		foreach ($names as $name) {
			$this->assertArrayNotHasKey($name, $decls, "{$name} is still declared");
		}

		$pattern = '/(' . implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), $names)) . ')(?![-\w])/';
		$this->assertSame(array(), self::files_matching($pattern, array( 'assets', 'templates', 'src' )));
	}

	public function test_new_tokens_classify_as_canonical(): void
	{
		require_once dirname(__DIR__, 3) . '/bin/check-token-definitions.php';

		// The CLI default of bin/check-token-definitions.php: the plugin root and
		// its three canonical stylesheets.
		$root   = dirname(__DIR__, 3);
		$report = mhmrentiva_classify_tokens(
			array( $root ),
			array(
				$root . '/assets/css/core/css-variables.css',
				$root . '/assets/css/core/golden-ratio-contract.css',
				$root . '/assets/css/rvs/rvs-tokens.css',
			)
		);

		foreach (array( '--rvs-on-accent', '--rvs-accent-text', '--rvs-accent-text-hover' ) as $name) {
			$this->assertSame('canonical', $report['tokens'][ $name ]['class'] ?? null, $name);
		}
	}

	/**
	 * Accent-filled rules that legitimately carry no --rvs-on-accent, by
	 * selector. A rule listed here that no longer fills with the accent fails too.
	 *
	 * @return array<string,array{file:string,why:string,from?:string}>
	 */
	private static function fill_exceptions(): array
	{
		$card    = 'the element also carries rvs-btn rvs-btn--primary (templates/partials/rvs-vehicle-card.php)';
		$booking = 'the element also carries rvs-btn--primary (templates/shortcodes/booking-form.php)';

		return array(
			'.rvs-shell .rvs-steps__fill'                                       => array( 'file' => 'rvs/rvs-forms.css', 'why' => 'a fill without text' ),
			'.rvs-shell .rvs-review-summary__fill'                              => array( 'file' => 'rvs/rvs-reviews.css', 'why' => 'a fill without text' ),
			'.rvs-shell .rvs-testimonials__dot[aria-current="true"] span'       => array( 'file' => 'rvs/rvs-reviews.css', 'why' => 'a dot without text' ),
			'.rvs-shell .rvs-vd__swatch--selected'                              => array( 'file' => 'rvs/rvs-vehicle.css', 'why' => 'a swatch without text' ),
			'.rvs-shell .rvs-cal.is-interactive .rvs-cal__day.is-selected:hover' => array(
				'file' => 'rvs/rvs-calendar.css',
				'why'  => 'takes its text from the same element\'s selected rule',
				'from' => '.rvs-shell .rvs-cal__day.is-selected',
			),
			'.rvs-shell .rvs-card__select'                                      => array( 'file' => 'rvs/rvs-card.css', 'why' => $card ),
			'.rvs-shell .rvs-card__select:hover'                                => array( 'file' => 'rvs/rvs-card.css', 'why' => $card ),
			'.rvs-shell .rvs-card__select:disabled'                             => array( 'file' => 'rvs/rvs-card.css', 'why' => $card ),
			'.rvs-shell .rvs-booking .rvs-booking__submit'                      => array( 'file' => 'frontend/rvs-booking.css', 'why' => $booking ),
			'.rvs-shell .rvs-booking .rvs-booking__submit:hover:not(:disabled)' => array( 'file' => 'frontend/rvs-booking.css', 'why' => $booking ),
		);
	}

	public function test_accent_fills_carry_on_accent_text(): void
	{
		$fill       = '/background(-color)?:\s*var\(--rvs-(accent-strong|accent-deep|card-btn-bg|card-btn-bg-hover|booking-cta|booking-cta-hover)\)/';
		$on_accent  = '/(^|[^-])color:\s*var\(--rvs-on-accent\)/';
		$exceptions = self::fill_exceptions();
		$missing    = array();
		$used       = array();

		foreach (self::target_rules() as $rule) {
			if (! preg_match($fill, $rule['body'])) {
				continue;
			}
			if (isset($exceptions[ $rule['selector'] ]) && $exceptions[ $rule['selector'] ]['file'] === $rule['file']) {
				$used[ $rule['selector'] ] = true;
				continue;
			}
			if (! preg_match($on_accent, $rule['body'])) {
				$missing[] = $rule['file'] . ': ' . $rule['selector'];
			}
		}

		$this->assertSame(array(), $missing, 'Accent-filled rules without --rvs-on-accent text and not excepted by name.');
		$this->assertSame(array(), array_values(array_diff(array_keys($exceptions), array_keys($used))), 'Stale exceptions: they no longer match an accent-filled rule.');

		// "Takes its text from X": X declares the on-accent text.
		foreach ($exceptions as $selector => $row) {
			if (! isset($row['from'])) {
				continue;
			}
			$from = self::rules_by_selector($row['from'], $row['file']);
			$this->assertNotSame(array(), $from, "{$selector}: rule {$row['from']} not found");
			$this->assertMatchesRegularExpression($on_accent, implode("\n", array_column($from, 'body')), "{$row['from']} does not declare --rvs-on-accent");
		}

		// The two template-backed exceptions: the element really is a primary button.
		foreach (
			array(
				'templates/partials/rvs-vehicle-card.php'   => 'rvs-card__select',
				'templates/shortcodes/booking-form.php'     => 'rvs-booking__submit',
			) as $template => $class
		) {
			$source = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . $template); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped template.
			preg_match_all('/class="([^"]*\b' . preg_quote($class, '/') . '\b[^"]*)"/', $source, $m);
			$this->assertNotSame(array(), $m[1], "{$template}: no element with {$class}");
			foreach ($m[1] as $classes) {
				$this->assertMatchesRegularExpression('/(^|\s)rvs-btn--primary(\s|$)/', $classes, "{$template}: {$class} without rvs-btn--primary");
			}
		}
	}

	public function test_no_literal_white_on_the_accent(): void
	{
		$white = array();
		foreach (self::target_rules() as $rule) {
			if (preg_match('/(^|[^-])color:\s*#fff(fff)?\b/i', $rule['body'])) {
				$white[] = $rule['file'] . ': ' . $rule['selector'];
			}
		}
		sort($white);

		// Spec §measured: these four sit on --rvs-text or --rvs-alert, not on the accent.
		$expected = array(
			'frontend/rvs-account.css: .rvs-shell .rvs-account-cancel__confirm',
			'frontend/rvs-account.css: .rvs-shell .rvs-account-cancel__confirm:hover',
			'rvs/rvs-results.css: .rvs-shell .rvs-results__band',
			'rvs/rvs-vehicle.css: .rvs-shell .rvs-vd__counter',
		);
		$this->assertSame($expected, $white);
	}

	public function test_no_accent_fill_token_used_as_text(): void
	{
		// Spec §3 greps 1 and 2, verbatim: the fill tokens never colour text.
		$this->assertSame(array(), self::target_lines_matching('/[^-]color:\s*var\(--rvs-accent-strong\)/'), 'Text in --rvs-accent-strong: use --rvs-accent-text.');
		$this->assertSame(array(), self::target_lines_matching('/[^-]color:\s*var\(--rvs-accent-deep\)/'), 'Text in --rvs-accent-deep: use --rvs-accent-text-hover.');
	}

	public function test_active_vehicle_action_moves_line_and_text_together(): void
	{
		$rules = self::rules_by_selector('.rvs-shell .rvs-vd__actions .is-active', 'rvs/rvs-vehicle.css');
		$this->assertCount(1, $rules);
		$this->assertMatchesRegularExpression('/(^|[^-])border-color:\s*var\(--rvs-accent-text\)/', $rules[0]['body']);
		$this->assertMatchesRegularExpression('/(^|[^-])color:\s*var\(--rvs-accent-text\)/', (string) preg_replace('/border-color:[^;]*;/', '', $rules[0]['body']));
	}

	public function test_focus_rings_read_accent_text(): void
	{
		// Spec §3 grep 3, verbatim (the name split so the full-name sweep below
		// does not find this file).
		$this->assertSame(array(), self::target_lines_matching('/outline:[^;]*var\(--rvs-' . 'accent(-strong)?\)/'), 'Focus ring on an accent fill token: use --rvs-accent-text.');

		// And every outline of the target set is off or the accent-text ring, so a
		// new raw-colour ring is caught too.
		$other = array();
		foreach (self::target_rules() as $rule) {
			preg_match_all('/(?:^|[;\s{])outline:\s*([^;]+);/', $rule['body'], $m);
			foreach ($m[1] as $value) {
				$value = trim($value);
				if ('none' !== $value && '0' !== $value && ! str_contains($value, 'var(--rvs-accent-text)')) {
					$other[] = $rule['file'] . ': ' . $rule['selector'] . ' => ' . $value;
				}
			}
		}
		$this->assertSame(array(), $other);
	}

	public function test_selected_lines_read_accent_text(): void
	{
		$nav_active = '.woocommerce-account .woocommerce > .woocommerce-MyAccount-navigation ul li.is-active > a, .woocommerce-account .woocommerce > .woocommerce-MyAccount-navigation ul li > a[aria-current="page"]';
		$wc_focus   = 'body.rvs-rental .woocommerce .input-text:focus, body.rvs-rental .woocommerce select:focus, body.rvs-rental .woocommerce textarea:focus, body.rvs-rental .woocommerce .select2-container--focus .select2-selection--single, body.rvs-rental .woocommerce .select2-container--open .select2-selection--single';

		// file, selector, property.
		$cases = array(
			array( 'frontend/rvs-account-nav.css', $nav_active, 'border-left-color' ),
			array( 'frontend/rvs-account-nav.css', $nav_active, 'border-bottom-color' ),
			array( 'frontend/rvs-availability.css', '.rvs-shell .rvs-availability__option[aria-pressed="true"]', 'border-color' ),
			array( 'frontend/rvs-availability.css', '.rvs-shell .rvs-availability__option[aria-pressed="true"]', 'box-shadow' ),
			// R5: the loading spinner, not a selected line; it must stay visible on a light accent.
			array( 'frontend/rvs-availability.css', '.rvs-shell .rvs-availability[data-rvs-cal-loading] .rvs-availability__months::after', 'border-top-color' ),
			// R4: the step indicator's done and current edges (the fill stays strong).
			array( 'rvs/rvs-forms.css', '.rvs-shell .rvs-steps__item.is-done', 'border-top-color' ),
			array( 'rvs/rvs-forms.css', '.rvs-shell .rvs-steps__item.is-current', 'border-top-color' ),
			array( 'frontend/rvs-checkout.css', $wc_focus, 'border-color' ),
		);

		foreach ($cases as list( $file, $selector, $prop )) {
			$bodies = implode("\n", array_column(self::rules_by_selector($selector, $file), 'body'));
			$this->assertNotSame('', $bodies, "{$file}: {$selector} not found");
			$this->assertMatchesRegularExpression('/(?:^|[;\s{])' . preg_quote($prop, '/') . ':[^;]*var\(--rvs-accent-text\)/', $bodies, "{$file}: {$selector} {$prop}");
		}

		$fill = self::rules_by_selector('.rvs-shell .rvs-steps__fill', 'rvs/rvs-forms.css');
		$this->assertMatchesRegularExpression('/background:\s*var\(--rvs-accent-strong\)/', $fill[0]['body'] ?? '', 'R4: the progress fill keeps the site colour.');
	}

	public function test_today_ring_reads_accent_strong(): void
	{
		$rules = self::rules_by_selector('.rvs-shell .rvs-cal__day.is-today', 'rvs/rvs-calendar.css');
		$this->assertCount(1, $rules);
		$this->assertMatchesRegularExpression('/box-shadow:\s*inset 0 0 0 2px var\(--rvs-accent-strong\);/', $rules[0]['body']);
	}

	public function test_removed_tokens_are_gone_everywhere(): void
	{
		// Spec §3 greps 4 and 5, verbatim.
		$this->assertSame(array(), self::target_lines_matching('/var\(--rvs-' . 'accent\)/'));
		$this->assertSame(array(), self::target_lines_matching('/var\(--rvs-(accent-200|n-700)\)/'));

		$name = '--rvs-' . 'accent';
		$this->assertArrayNotHasKey($name, RvsTokenFile::declarations());

		// The full name, nowhere in the plugin, tests included. Built in parts so
		// this file does not find itself.
		$this->assertSame(array(), self::files_matching('/' . preg_quote($name, '/') . '(?![-\w])/', array( 'assets', 'src', 'templates', 'tests' )));
	}

	/**
	 * Lines of the target set matching the pattern, as "file:line" (grep -n).
	 *
	 * @return list<string>
	 */
	private static function target_lines_matching(string $pattern): array
	{
		$base = MHMRENTIVA_PLUGIN_DIR . 'assets/css/';
		$hits = array();
		foreach (array_merge(glob($base . 'rvs/*.css') ?: array(), glob($base . 'frontend/rvs-*.css') ?: array()) as $path) {
			foreach (file($path) ?: array() as $i => $line) {
				if (preg_match($pattern, $line)) {
					$hits[] = substr($path, strlen($base)) . ':' . ( $i + 1 );
				}
			}
		}
		return $hits;
	}

	/**
	 * Every rule of the target set (assets/css/rvs/*.css and
	 * assets/css/frontend/rvs-*.css), comments stripped, @media blocks entered.
	 *
	 * @return list<array{file:string,selector:string,body:string}>
	 */
	private static function target_rules(): array
	{
		$base  = MHMRENTIVA_PLUGIN_DIR . 'assets/css/';
		$files = array_merge(glob($base . 'rvs/*.css') ?: array(), glob($base . 'frontend/rvs-*.css') ?: array());
		sort($files);

		$rules = array();
		foreach ($files as $path) {
			$rel = substr($path, strlen($base));
			foreach (self::parse_rules((string) file_get_contents($path)) as $rule) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped asset.
				$rules[] = array( 'file' => $rel ) + $rule;
			}
		}
		return $rules;
	}

	/**
	 * @return list<array{file:string,selector:string,body:string}>
	 */
	private static function rules_by_selector(string $selector, string $file): array
	{
		return array_values(array_filter(self::target_rules(), static fn (array $r): bool => $r['selector'] === $selector && $r['file'] === $file));
	}

	/**
	 * Innermost `prelude { body }` blocks; the prelude is whitespace-normalised.
	 *
	 * @return list<array{selector:string,body:string}>
	 */
	private static function parse_rules(string $css): array
	{
		$css   = (string) preg_replace('!/\*.*?\*/!s', '', $css);
		$rules = array();
		$stack = array();
		$mark  = 0;
		$len   = strlen($css);

		for ($i = 0; $i < $len; $i++) {
			$c = $css[ $i ];
			if ('{' === $c) {
				$stack[] = array(
					'prelude' => trim((string) preg_replace('/\s+/', ' ', substr($css, $mark, $i - $mark))),
					'start'   => $i + 1,
					'nested'  => false,
				);
				if (count($stack) > 1) {
					$stack[ count($stack) - 2 ]['nested'] = true;
				}
				$mark = $i + 1;
			} elseif ('}' === $c) {
				$open = array_pop($stack);
				if (null !== $open && ! $open['nested']) {
					$rules[] = array(
						'selector' => (string) preg_replace('/\s*,\s*/', ', ', $open['prelude']),
						'body'     => substr($css, $open['start'], $i - $open['start']),
					);
				}
				$mark = $i + 1;
			} elseif (';' === $c && array() === $stack) {
				$mark = $i + 1; // @import / @charset at top level.
			} elseif (';' === $c) {
				// A declaration inside a block: the next prelude starts after it.
				$mark = $i + 1;
			}
		}
		return $rules;
	}

	/**
	 * Files (relative to the plugin root) under the given directories whose
	 * .css/.php/.js source matches the pattern, as "path:line".
	 *
	 * @param list<string> $dirs
	 * @return list<string>
	 */
	private static function files_matching(string $pattern, array $dirs): array
	{
		$root = rtrim(MHMRENTIVA_PLUGIN_DIR, '/');
		$hits = array();
		foreach ($dirs as $dir) {
			$base = $root . '/' . $dir;
			if (! is_dir($base)) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), array( 'css', 'php', 'js' ), true)) {
					continue;
				}
				$lines = file($file->getPathname()) ?: array();
				foreach ($lines as $i => $line) {
					if (preg_match($pattern, $line)) {
						$hits[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ( $i + 1 );
					}
				}
			}
		}
		return $hits;
	}
}
