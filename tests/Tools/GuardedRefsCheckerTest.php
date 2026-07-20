<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;

/**
 * The checker is a standalone script; these tests exercise its pure core by
 * requiring the file, which returns the collector closure.
 */
final class GuardedRefsCheckerTest extends TestCase {

	/**
	 * @return callable(string): array<int, array{line:int, class:string}>
	 */
	private function collector(): callable {
		return require dirname( __DIR__, 2 ) . '/bin/check-guarded-refs.php';
	}

	public function test_finds_class_referenced_inside_class_exists_guard(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;
if ( class_exists( Admin\Ghost\Missing::class ) ) {
	Admin\Ghost\Missing::register();
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\Ghost\Missing', $found[0]['class'] );
		$this->assertSame( 3, $found[0]['line'] );
	}

	public function test_finds_class_referenced_via_is_class_available_wrapper(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;
if ( $this->is_class_available( 'MHMRentiva\Layout\CLI\LayoutImportCommand' ) ) {
	echo 'ok';
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Layout\CLI\LayoutImportCommand', $found[0]['class'] );
	}

	public function test_ignores_guards_for_third_party_classes(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;
if ( class_exists( '\Elementor\Plugin' ) ) {
	echo 'elementor is here';
}
if ( class_exists( 'WooCommerce' ) ) {
	echo 'woo is here';
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found, 'Only MHMRentiva\\ classes are ours to verify.' );
	}

	public function test_returns_nothing_when_there_are_no_guards(): void {
		$code = "<?php\nnamespace MHMRentiva;\necho 'nothing to see';\n";

		$this->assertSame( array(), ( $this->collector() )( $code ) );
	}

	/**
	 * The bug this whole checker exists to catch: a short class name that
	 * resolves through a `use` import, not through naive "MHMRentiva\" prefixing.
	 * A crude prefix-the-raw-text approach would produce
	 * "MHMRentiva\AdvancedLogger" here, which is wrong -- the real class lives
	 * at MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger, exactly as it does in
	 * this codebase's own src/Admin/Actions/Actions.php.
	 */
	public function test_resolves_short_name_imported_via_use_statement(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Actions;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;

final class Actions {
	public function log(): void {
		if ( class_exists( AdvancedLogger::class ) ) {
			AdvancedLogger::log( 'x' );
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger', $found[0]['class'] );
	}

	/**
	 * A `use ... as Alias;` import must resolve through the alias, not the
	 * original short name.
	 */
	public function test_resolves_aliased_use_import(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Payment;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger as Logger;

final class Service {
	public function run(): void {
		if ( class_exists( Logger::class ) ) {
			Logger::log( 'x' );
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger', $found[0]['class'] );
	}

	/**
	 * A leading-backslash `::class` reference is already fully qualified and
	 * must not be run through `use`/namespace resolution at all.
	 */
	public function test_resolves_fully_qualified_class_reference_without_double_prefixing(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Frontend\Account;

final class WooCommerceIntegration {
	public function check(): bool {
		return class_exists( \MHMRentiva\Admin\Licensing\Ghost::class );
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\Licensing\Ghost', $found[0]['class'] );
	}

	/**
	 * A bare string argument to is_class_available()/class_exists() is never
	 * namespace-resolved by PHP at runtime -- it must already be an absolute
	 * "MHMRentiva\..." name. `BookingReport::is_class_available()` is NOT a
	 * class_exists() proxy: its body doesn't forward the argument to
	 * class_exists() at all, it uses the string as an internal cache key
	 * ('Core\ObjectCache'). That string must never be treated as a class
	 * reference, regardless of whether it happens to look like one.
	 */
	public function test_ignores_bare_string_via_non_forwarding_wrapper(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Reports\BusinessLogic;

final class BookingReport {
	private static function is_class_available( string $class ): bool {
		return true;
	}

	public function run(): void {
		if ( self::is_class_available( 'Core\ObjectCache' ) ) {
			echo 'cached';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * The sharpest false-positive trap this checker must avoid: one of
	 * BookingReport's cache keys, 'Admin\Reports\BackgroundProcessor', is
	 * not just similar in shape to a class name -- prefixing it with
	 * 'MHMRentiva\' lands on a REAL file in this repository
	 * (src/Admin/Reports/BackgroundProcessor.php). A file-existence check
	 * alone would misidentify this as a dead guard. Only gating on the
	 * wrapper's own body shape (not a direct `class_exists()` forward)
	 * correctly rules it out.
	 */
	public function test_ignores_cache_key_that_collides_with_a_real_class_path(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Reports\BusinessLogic;

final class BookingReport {
	private static function is_class_available( string $class ): bool {
		static $cache = array( 'Admin\Reports\BackgroundProcessor' => true );
		return $cache[ $class ] ?? false;
	}

	public function run(): void {
		if ( self::is_class_available( 'Admin\Reports\BackgroundProcessor' ) ) {
			echo 'queued';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found, 'Non-forwarding wrapper must never be treated as a class_exists() proxy, even when its cache key collides with a real file path.' );
	}

	/**
	 * The bug FIX 1 exists to catch: `Plugin::is_class_available()` forwards
	 * its argument *directly* to `class_exists()` (`return class_exists(
	 * $class_name );` -- nothing else), so a bare string missing the
	 * 'MHMRentiva\' prefix can never resolve at runtime. The guard is
	 * permanently false and the code behind it never runs. This must be
	 * reported, not silently skipped. 'Admin\REST\ErrorHandler' is used
	 * because it names a REAL class in this repository
	 * (src/Admin/REST/ErrorHandler.php) -- that's what proves this is a
	 * missing-prefix bug, not a legitimate third-party reference.
	 */
	public function test_reports_dead_guard_missing_mhmrentiva_prefix_via_forwarding_wrapper(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return class_exists( $class_name );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * Same dead-guard shape, but via a direct `class_exists()` call rather
	 * than a wrapper -- both call shapes must be caught.
	 */
	public function test_reports_dead_guard_missing_mhmrentiva_prefix_via_direct_class_exists(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin;

final class Bootstrap {
	public function boot(): void {
		if ( class_exists( 'Admin\Core\Utilities\CacheManager' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\Core\Utilities\CacheManager', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B regression: the wrapper-recognition rule used to require the
	 * body to be *exactly* six tokens (`return class_exists( $x );`), so a
	 * harmless `(bool)` cast silently disabled the dead-guard check for
	 * every call site of the wrapper. The relaxed rule must still recognise
	 * this as forwarding: a cast on the *call result* doesn't change which
	 * class the guard is actually checking.
	 */
	public function test_recognises_forwarding_wrapper_with_bool_cast_on_result(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return (bool) class_exists( $class_name );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B regression: a *paired* `!!` negation cancels out and preserves
	 * the guard's truthiness -- it must still be recognised as forwarding.
	 */
	public function test_recognises_forwarding_wrapper_with_double_negation(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return ! ! class_exists( $class_name );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B regression: the `if ( class_exists( $x ) ) { return true; }
	 * return false;` shape is semantically identical to a direct forward
	 * and must be recognised as such.
	 */
	public function test_recognises_forwarding_wrapper_with_if_return_true_false_shape(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		if ( class_exists( $class_name ) ) {
			return true;
		}
		return false;
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B regression: a trailing comma after the sole argument (PHP 7.3+
	 * call-site trailing commas) must not defeat recognition.
	 */
	public function test_recognises_forwarding_wrapper_with_trailing_comma(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return class_exists( $class_name, );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B regression: a PHP 8 named argument (`class: $x`) still passes
	 * the parameter straight through and must be recognised.
	 */
	public function test_recognises_forwarding_wrapper_with_named_argument(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return class_exists( class: $class_name );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B regression: a cast on the *argument itself* (rather than on the
	 * call's result) doesn't change which variable is being checked and
	 * must not defeat recognition.
	 */
	public function test_recognises_forwarding_wrapper_with_cast_on_argument(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return class_exists( (string) $class_name );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\REST\ErrorHandler', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX B "ters yon" (inverse direction) regression: a *single* `!`
	 * negation inverts the guard's meaning (the wrapper returns true when
	 * the class does NOT exist), so it must NOT be treated as forwarding --
	 * unlike the paired `!!` case above. The dead-guard check must stay
	 * silent for its call sites (treating it as forwarding here would
	 * produce a false positive on 'Admin\REST\ErrorHandler', which is a
	 * real class).
	 */
	public function test_rejects_wrapper_with_single_negation_as_non_forwarding(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return ! class_exists( $class_name );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found, 'A single negation inverts the guard and must not be treated as a forward.' );
	}

	/**
	 * FIX B "ters yon" regression: `return true;` is not a forward at all.
	 * Isolated (no other reliance evidence in the file), it must be
	 * silently rejected -- exactly like BookingReport's cache lookup.
	 */
	public function test_rejects_constant_return_as_non_forwarding(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Reports\BusinessLogic;

final class SomeWrapper {
	private function is_class_available( string $class_name ): bool {
		return true;
	}

	public function run(): void {
		if ( self::is_class_available( 'Core\ObjectCache' ) ) {
			echo 'cached';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * FIX B "ters yon" regression: forwarding to a *different* function
	 * (`method_exists()` instead of `class_exists()`) must not be treated
	 * as a class-existence forward.
	 */
	public function test_rejects_wrapper_forwarding_to_a_different_function(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return method_exists( $class_name, 'x' );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * FIX B "ters yon" regression: `class_exists()` called on a variable
	 * OTHER than the wrapper's own parameter is not a forward of that
	 * parameter.
	 */
	public function test_rejects_wrapper_that_checks_a_different_variable(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		$other = 'WooCommerce';
		return class_exists( $other );
	}

	public function boot(): void {
		if ( $this->is_class_available( 'Admin\REST\ErrorHandler' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * FIX B "silent degrade made noisy" regression -- the core scenario the
	 * fix exists for: a file relies on `is_class_available()` as a real
	 * class checker (proven by a call site passing an already
	 * 'MHMRentiva\'-prefixed string, mirroring Plugin.php's real call
	 * sites), but the method's body no longer matches a recognised
	 * forwarding shape. Silently dropping the dead-guard check here would
	 * hide future bugs; the checker must instead surface a distinct
	 * 'unverified_wrapper' finding so the gate fails loudly instead of
	 * staying green by accident.
	 */
	public function test_reports_unverified_wrapper_when_relied_upon_but_not_forwarding(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return true;
	}

	public function boot(): void {
		if ( $this->is_class_available( 'MHMRentiva\Admin\Core\AssetManager' ) ) {
			echo 'assumed real check';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$unverified = array_values(
			array_filter(
				$found,
				static function ( $hit ) {
					return 'unverified_wrapper' === $hit['kind'];
				}
			)
		);

		$this->assertCount( 1, $unverified, 'A non-forwarding is_class_available() relied on elsewhere in the file must be flagged.' );
		$this->assertSame( 'is_class_available', $unverified[0]['class'] );
	}

	/**
	 * FIX B counterpart: when the file does NOT rely on
	 * `is_class_available()` as a class checker anywhere (every call site
	 * uses a cache-key-shaped string, never an 'MHMRentiva\'-prefixed one --
	 * exactly BookingReport's real-world shape), a non-forwarding body must
	 * NOT trigger the unverified-wrapper warning. This is what keeps
	 * BookingReport::is_class_available() -- a deliberate, real,
	 * already-shipped cache lookup -- from breaking the gate.
	 */
	public function test_does_not_report_unverified_wrapper_without_reliance_evidence(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Reports\BusinessLogic;

final class BookingReport {
	private static function is_class_available( string $class ): bool {
		static $cache = array( 'Core\ObjectCache' => true );
		return $cache[ $class ] ?? false;
	}

	public function run(): void {
		if ( self::is_class_available( 'Core\ObjectCache' ) ) {
			echo 'cached';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * `self::class` / `static::class` / `parent::class` name the enclosing
	 * class, not an external reference, and must never be reported.
	 */
	public function test_ignores_self_static_parent_class_references(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin;

final class Widget {
	public function check(): void {
		if ( class_exists( self::class ) ) {
			echo 'a';
		}
		if ( class_exists( static::class ) ) {
			echo 'b';
		}
		if ( class_exists( parent::class ) ) {
			echo 'c';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * The wrapper's own declaration site (`function is_class_available(...)`)
	 * must never be mistaken for a call.
	 */
	public function test_ignores_the_wrapper_method_declaration_itself(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Plugin {
	private function is_class_available( string $class_name ): bool {
		return class_exists( $class_name );
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found );
	}

	/**
	 * Group-use imports (`use Prefix\{A, B as C};`) must resolve each member
	 * against its prefix, honoring per-member aliases.
	 */
	public function test_resolves_group_use_import(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Reports;

use MHMRentiva\Admin\Core\Utilities\{ObjectCache, QueueManager as QM};

final class Reports {
	public function run(): void {
		if ( class_exists( ObjectCache::class ) && class_exists( QM::class ) ) {
			echo 'ok';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 2, $found );
		$this->assertSame( 'MHMRentiva\Admin\Core\Utilities\ObjectCache', $found[0]['class'] );
		$this->assertSame( 'MHMRentiva\Admin\Core\Utilities\QueueManager', $found[1]['class'] );
	}

	/**
	 * FIX 3 regression: a file-scope closure's `use ($var)` clause is a
	 * `T_USE` token at depth 0 -- the same depth as a real `use` import
	 * statement. Before this fix, the import-parser would mistake it for an
	 * import list and scan forward for a terminating `;`, running straight
	 * through the closure's body (which has no `;` immediately after its
	 * `use (...)`) and silently swallowing any guard inside that body while
	 * corrupting $depth. This must not happen: the guard inside the closure
	 * has to be found.
	 */
	public function test_finds_guard_inside_file_scope_closure_use_clause(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

$x = 1;

$callback = function () use ( $x ) {
	if ( class_exists( Admin\Ghost\Missing::class ) ) {
		Admin\Ghost\Missing::register();
	}
};

$callback();
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\Ghost\Missing', $found[0]['class'] );
	}

	/**
	 * FIX A regression: the "missing MHMRentiva\ prefix" dead-guard check
	 * only recognised one malformation -- the prefix being entirely absent.
	 * The real bug found by the second review pass
	 * (MonitoringManager.php:435) is a *malformed* prefix: the namespace
	 * root was typo'd apart into two segments ('MHM\Rentiva\...') instead of
	 * the real single segment 'MHMRentiva'. Naively re-prefixing the whole
	 * literal with 'MHMRentiva\' produces 'MHMRentiva\MHM\Rentiva\...',
	 * which resolves to no file, so the old check silently let it through.
	 * The generalised check must try every trailing (suffix) run of the
	 * literal's segments, not just the whole string, and catch this via the
	 * suffix resolving to a real file under src/.
	 *
	 * The dead-guard check resolves candidates against the real src/ tree, so
	 * this fixture must name a class the build actually ships. It originally
	 * used Admin\Messages\Core\MessageCache; that class was carved out of Lite,
	 * so the fixture now names Admin\Core\QueryHelper instead. The shape under
	 * test -- a namespace root typo'd apart into 'MHM\Rentiva\...' -- is
	 * unchanged.
	 */
	public function test_reports_dead_guard_with_malformed_namespace_root_prefix(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin\Core\Monitoring;

final class MonitoringManager {
	public function check(): void {
		if ( class_exists( 'MHM\Rentiva\Admin\Core\QueryHelper' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\Core\QueryHelper', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX A regression: same malformed-prefix shape as above, but with an
	 * arbitrary bogus leading segment rather than a plausible typo, and via
	 * a direct `class_exists()` call rather than a namespace-declared file.
	 * Confirms the suffix search isn't special-cased to the 'MHM\Rentiva'
	 * literal -- any bogus leading segment(s) must be tried away.
	 */
	public function test_reports_dead_guard_with_arbitrary_bogus_leading_segment(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;

final class Bootstrap {
	public function boot(): void {
		if ( class_exists( 'XYZ\Admin\PostTypes\Logs\AdvancedLogger' ) ) {
			echo 'never runs';
		}
	}
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 1, $found );
		$this->assertSame( 'MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger', $found[0]['class'] );
		$this->assertSame( 'dead', $found[0]['kind'] );
	}

	/**
	 * FIX A false-positive guard: the suffix search requires at least two
	 * segments in the matched suffix specifically so a single common leaf
	 * name can never trigger it alone. '\Elementor\Plugin' is the sharpest
	 * trap -- its last segment, 'Plugin', collides with this plugin's own
	 * top-level src/Plugin.php, so a naive "any suffix, including a lone
	 * trailing segment" rule would misreport a legitimate third-party
	 * reference as one of our dead guards. 'AIOSEO\Plugin\AIOSEO' exercises
	 * the same trap with a two-segment suffix ('Plugin\AIOSEO') that must
	 * also not resolve to any real file.
	 */
	public function test_ignores_third_party_strings_that_share_a_leaf_name_with_our_own_class(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva;
if ( class_exists( '\Elementor\Plugin' ) ) {
	echo 'elementor is here';
}
if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
	echo 'aioseo is here';
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertSame( array(), $found, 'A shared leaf name alone must never be mistaken for one of our own classes.' );
	}

	/**
	 * interface_exists()/trait_exists() are recognised the same way as
	 * class_exists() -- guards commonly probe any of the three.
	 */
	public function test_finds_dangling_interface_and_trait_guards(): void {
		$code = <<<'PHP'
<?php
namespace MHMRentiva\Admin;

if ( interface_exists( Ghost\MissingInterface::class ) ) {
	echo 'a';
}
if ( trait_exists( Ghost\MissingTrait::class ) ) {
	echo 'b';
}
PHP;

		$found = ( $this->collector() )( $code );

		$this->assertCount( 2, $found );
		$this->assertSame( 'MHMRentiva\Admin\Ghost\MissingInterface', $found[0]['class'] );
		$this->assertSame( 'MHMRentiva\Admin\Ghost\MissingTrait', $found[1]['class'] );
	}
}
