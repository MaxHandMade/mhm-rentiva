<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use PHPUnit\Framework\TestCase;

/**
 * The admin screens share one look: 4 px cards and panels, WordPress's own
 * button shape, and a 400-weight page title (the screen title wp-admin and
 * the shared UI package draw). Status badges stay fully rounded.
 *
 * This reads the stylesheets of the screens that follow that look and fails
 * on a soft card corner, on a button that draws its own corner or height,
 * and on a bold page title.
 */
final class AdminVisualLanguageTest extends TestCase {

	public const SCOPE = array(
		'assets/css/admin/vehicle-settings-v2.css',
		'assets/css/admin/vehicle-list.css',
		'assets/css/admin/booking-list.css',
		'assets/css/admin/booking-calendar.css',
		'assets/css/admin/occupancy-matrix.css',
		'assets/css/admin/addons-screen.css',
		'assets/css/admin/addon-list.css',
		'assets/css/admin/settings.css',
		'assets/css/admin/setup-wizard.css',
		'src-react/admin/customers/customers.css',
		'src-react/admin/dashboard/dashboard.css',
		'src-react/admin/shortcode-pages/shortcode-pages.css',
		'src-react/admin/about/about.css',
		'src-react/admin/contact-messages/contact-messages.css',
		'src-react/shared/admin.css',
	);

	/** The shared admin header lives here; only its title weight is checked. */
	public const TITLE_ONLY = array( 'assets/css/core/core.css' );

	private const PLAIN_CORNER = '/^((0|0px|2px|3px|4px)(\s+(0|0px|2px|3px|4px)){0,3}|50%|inherit|var\(\s*--mhmui-radius\s*\)|var\(\s*--mhm-radius-(sm|base|none)\s*\))(\s*!important)?$/i';

	private const ROUND_CORNER = '/^(999px|9999px|50%|var\(\s*--mhm-radius-full\s*\))(\s*!important)?$/i';

	private const BUTTON_WORD = '/(btn|button|__save\b|__edit\b|-create(?=$|[\s,:.])|chipbtn|toggle__btn|__tab(?=$|[\s,:.])|nav-item)/i';

	/** Rules aimed at an icon or a message inside a button, not at the button. */
	private const INNER_PART = '/(\.dashicons|feedback|__icon)\s*$/i';

	private const BADGE_WORD = '/(badge|pill|status|count|dot|avatar|slider|swatch|chip|plate|booking-type)/i';

	/**
	 * @return list<array{file: string, selector: string, body: string}>
	 */
	private static function rules( array $files = self::SCOPE ): array {
		$root  = dirname( __DIR__, 2 ) . '/';
		$rules = array();
		foreach ( $files as $file ) {
			$css = (string) file_get_contents( $root . $file );
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
			preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER );
			foreach ( $m as $hit ) {
				$selector = trim( (string) preg_replace( '/\s+/', ' ', $hit[1] ) );
				if ( '' === $selector || '@' === $selector[0] ) {
					continue;
				}
				$rules[] = array(
					'file'     => $file,
					'selector' => $selector,
					'body'     => $hit[2],
				);
			}
		}

		return $rules;
	}

	private static function value( string $body, string $property ): ?string {
		if ( ! preg_match( '/(?:^|;|\s)' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+)/i', $body, $v ) ) {
			return null;
		}

		return trim( $v[1] );
	}

	public function test_no_soft_corners_in_scope(): void {
		$bad = array();
		foreach ( self::rules() as $r ) {
			$corner = self::value( $r['body'], 'border-radius' );
			if ( null === $corner || 1 === preg_match( self::BUTTON_WORD, $r['selector'] ) ) {
				continue;
			}
			if ( 1 === preg_match( self::PLAIN_CORNER, $corner ) ) {
				continue;
			}
			if ( 1 === preg_match( self::BADGE_WORD, $r['selector'] ) && 1 === preg_match( self::ROUND_CORNER, $corner ) ) {
				continue;
			}
			$bad[] = $r['file'] . ': ' . $r['selector'] . ' => ' . $corner;
		}

		$this->assertSame( array(), $bad, "Cards and panels use 4 px; only status badges are fully round:\n" . implode( "\n", $bad ) );
	}

	public function test_buttons_take_the_core_shape(): void {
		$bad = array();
		foreach ( self::rules() as $r ) {
			if ( 1 !== preg_match( self::BUTTON_WORD, $r['selector'] ) || 1 === preg_match( self::INNER_PART, $r['selector'] ) ) {
				continue;
			}
			foreach ( array( 'border-radius', 'height', 'min-height' ) as $property ) {
				$v = self::value( $r['body'], $property );
				if ( null !== $v && 1 !== preg_match( '/^(0|auto|inherit|initial)$/i', $v ) ) {
					$bad[] = $r['file'] . ': ' . $r['selector'] . ' => ' . $property . ': ' . $v;
				}
			}
		}

		$this->assertSame( array(), $bad, "Buttons leave corner and height to WordPress:\n" . implode( "\n", $bad ) );
	}

	public function test_page_title_weight(): void {
		$bad = array();
		foreach ( self::rules( array_merge( self::SCOPE, self::TITLE_ONLY ) ) as $r ) {
			if ( 1 !== preg_match( '/(^|[\s,>])h1\b|page-title(?!-action)|header__title|wp-heading-inline/i', $r['selector'] ) ) {
				continue;
			}
			$weight = self::value( $r['body'], 'font-weight' );
			if ( null !== $weight && 1 === preg_match( '/^(5|6|7|8|9)00|bold/i', $weight ) ) {
				$bad[] = $r['file'] . ': ' . $r['selector'] . ' => ' . $weight;
			}
		}

		$this->assertSame( array(), $bad, "Page titles are drawn at 400:\n" . implode( "\n", $bad ) );
	}
}
