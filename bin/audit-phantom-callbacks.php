<?php
/**
 * Audit helper: find add_action()/add_filter() registrations naming a method
 * that does not exist on the class.
 *
 * Round 4 of the pre-ZIP audit found BookingDepositMetaBox registering three
 * wp_ajax_ handlers whose methods were not on the class at all. PHPStan had
 * detected two of them and both entries were sitting silenced in the baseline,
 * so a check that does not depend on the baseline is worth having. Sweeping the
 * class rather than the instance then turned up a fourth: AddonManager's bulk
 * action was registered to `handle_bulk_action` while the method is
 * `handle_bulk_actions`, which is why bulk enable/disable never worked.
 *
 * ---------------------------------------------------------------------------
 * A REPORT, NOT A GATE. It prints what it cannot see before what it found.
 * ---------------------------------------------------------------------------
 *
 * Must run with WordPress loaded so the classes are autoloadable:
 *   docker exec <wpcli> wp --allow-root eval-file \
 *     /var/www/html/wp-content/plugins/mhm-rentiva/bin/audit-phantom-callbacks.php
 *
 * Exit code is always 0.
 */

$roots = array(
	'mhm-rentiva'     => WP_PLUGIN_DIR . '/mhm-rentiva/src',
	'mhm-rentiva-pro' => WP_PLUGIN_DIR . '/mhm-rentiva-pro/src',
);

$checked = 0;
$bad     = array();
$unres   = array();
$interp  = array();

foreach ( $roots as $label => $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
	foreach ( $it as $f ) {
		if ( $f->getExtension() !== 'php' ) {
			continue;
		}
		$src = (string) file_get_contents( $f->getPathname() );

		if ( ! preg_match( '/^namespace\s+([^;]+);/m', $src, $ns ) ) {
			continue;
		}
		if ( ! preg_match( '/^(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $src, $cl ) ) {
			continue;
		}
		$fqcn = trim( $ns[1] ) . '\\' . $cl[1];

		// Interpolated hook names, collected BEFORE the literal scan can `continue`
		// past this file. A literal-string scan cannot see
		// add_action( "wp_ajax_{$action}", ... ), so the actions such a loop
		// registers never appear in any grep-built inventory of this plugin's
		// endpoints. That is not hypothetical: two such sites hid four dead
		// unauthenticated actions -- one pair carrying the bare `mhmrentiva_` prefix --
		// through five audit rounds. Surfaced for reading rather than omitted.
		// Three shapes build a hook name at runtime: "wp_ajax_{$a}" interpolation,
		// 'wp_ajax_' . $a concatenation, and a bare variable. All are invisible to a
		// literal scan; the middle one is how the framework hooks here are written
		// ('save_post_' . POST_TYPE), so most hits are ordinary, but the shape is the
		// same one that hid four dead endpoints, and a reader has to see them to
		// judge.
		$patterns = array(
			'/add_(?:action|filter)\(\s*"[^"]*\$[^"]*"/',                 // "…{$var}…"
			'/add_(?:action|filter)\(\s*\'[^\']*\'\s*\.[^,]+/',            // '…' . $var
			'/add_(?:action|filter)\(\s*sprintf\([^,]+/',                  // sprintf(…)
			'/add_(?:action|filter)\(\s*\$[A-Za-z_][A-Za-z0-9_]*\s*,/',    // $hook
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $src, $im ) ) {
				foreach ( $im[0] as $hook ) {
					$interp[] = sprintf( '[%s] %s  <- %s', $label, $cl[1], trim( preg_replace( '/\s+/', ' ', $hook ) ) );
				}
			}
		}

		// static::class is late static binding: at runtime it is the SUBCLASS, so an
		// abstract base may legitimately register a method only its children define
		// (AbstractMetaBox::enqueue_scripts, AbstractPostType::register_taxonomies),
		// and those sites guard with method_exists( static::class, ... ) themselves.
		// Only self::class / __CLASS__ resolve to this file's own class and can be
		// verified here.
		$n = preg_match_all(
			'/add_(?:action|filter)\(\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*array\(\s*(self::class|__CLASS__)\s*,\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]/',
			$src,
			$m,
			PREG_SET_ORDER
		);
		if ( ! $n ) {
			continue;
		}

		if ( ! class_exists( $fqcn ) ) {
			$unres[] = sprintf( '%s (%s) -- class did not load; %d registration(s) unchecked', $fqcn, $label, $n );
			continue;
		}

		foreach ( $m as $hit ) {
			++$checked;
			if ( ! method_exists( $fqcn, $hit[3] ) ) {
				$bad[] = sprintf( '[%s] %s::%s()  <- hook %s', $label, $cl[1], $hit[3], $hit[1] );
			}
		}

	}
}

echo "What this cannot see:\n";
echo "  closures and string callables; classes resolved through a variable\n";
echo "  (\$class::method); registrations added at runtime. static::class is\n";
echo "  excluded on purpose -- late static binding resolves it to the subclass,\n";
echo "  and those sites guard with method_exists() themselves.\n\n";

echo "Registrations checked: {$checked}\n";
echo 'Pointing at a method that does not exist: ' . count( $bad ) . "\n";
foreach ( $bad as $b ) {
	echo "  {$b}\n";
}
if ( ! $bad ) {
	echo "  (none)\n";
}

echo "\nFiles skipped because the class did not load: " . count( $unres ) . "\n";
foreach ( $unres as $u ) {
	echo "  {$u}\n";
}
if ( ! $unres ) {
	echo "  (none)\n";
}

echo "\nInterpolated hook names -- read these by hand: " . count( $interp ) . "\n";
echo "  A grep-built inventory of this plugin's endpoints is short by whatever\n";
echo "  these loops register. For each: does a handler exist, does anything call\n";
echo "  it, and is the action name prefixed?\n";
foreach ( $interp as $i ) {
	echo "  {$i}\n";
}
if ( ! $interp ) {
	echo "  (none)\n";
}
