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
	 * "MHMRentiva\..." name. Some real call sites in this codebase pass
	 * namespace-relative-looking fragments (e.g. 'Admin\REST\ErrorHandler',
	 * or BookingReport's internal cache keys like 'Core\ObjectCache') that are
	 * NOT real class references. Naively prefixing these with "MHMRentiva\"
	 * would falsely report them as dangling. They must be skipped entirely.
	 */
	public function test_ignores_bare_string_without_mhmrentiva_prefix(): void {
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
