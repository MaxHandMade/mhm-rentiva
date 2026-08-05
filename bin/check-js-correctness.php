<?php
/**
 * Gate G-E -- JS correctness over the shipped JavaScript surface.
 *
 * WHY THIS EXISTS. The 2026-08-03 round scoped its ESLint work to `no-undef`
 * and never triaged the rest of the report, so three real defects survived
 * into the submission candidate and were found by hand afterwards: two
 * `no-dupe-keys` in `assets/js/frontend/vehicle-rating-form.js` (a duplicate
 * `timeout` key silently replaced the 10-second AJAX limit with a callback
 * jQuery never calls -- the request had no time limit AND the "Request timed
 * out" message could never reach anyone) and one in
 * `assets/js/core/i18n.js`. Building this gate then immediately surfaced two
 * more of the same family: a duplicate `calculateDays` class member in
 * `assets/js/frontend/booking-form.js` whose second, naive definition won and
 * broke the day count under non-ISO date formats, and a call to a
 * `mhmShowToast` that exists nowhere. A rule class is not triaged until it is
 * enforced.
 *
 * WHAT IT ENFORCES. `eslint:recommended` -- ESLint's own "these are probably
 * bugs" set -- and nothing else, minus a short, explicitly named exclusion
 * list printed on every run. Extending the whole recommended set rather than
 * listing rules one by one is deliberate: a rule ESLint adds to `recommended`
 * in a future version starts being enforced here automatically, which is the
 * fail-safe direction. The named minimum from the brief (`no-dupe-keys`,
 * `no-undef`, `no-redeclare`, `no-func-assign`, `no-cond-assign`,
 * `no-dupe-args`, `no-unreachable`, `no-const-assign`) is a subset of
 * `recommended` and is additionally reported with an explicit count, zero
 * included, so "this rule found nothing" is visible rather than inferred from
 * silence.
 *
 * WHAT IT DELIBERATELY DOES NOT ENFORCE, and why the gate says so in its own
 * output rather than only in this comment:
 *   - Formatting (`prettier/prettier`) and house style (`no-var`,
 *     `object-shorthand`, `camelcase`, `curly`, `jsdoc/*`). None of these are
 *     in `eslint:recommended` at all -- they come from
 *     `@wordpress/eslint-plugin`, which this gate does NOT extend. They are
 *     the standing accepted debt of a large legacy JS tree (19,390 findings
 *     at the T8 baseline) and reformatting that tree is not a correctness
 *     question.
 *   - Four rules that ARE in `recommended` but flag unused or cosmetic code
 *     rather than wrong behaviour: `no-unused-vars` (135 hits, all dead
 *     locals/params), `no-empty` (8, deliberate no-op error handlers),
 *     `no-useless-escape` (6, redundant regex escapes), `no-extra-semi`
 *     (formatting). Turning these on would bury the defect signal in 149
 *     rows of debt; they are listed by name in the output so the exclusion is
 *     never silent.
 *
 * SCOPE. Derived from the ZIP builder -- `bin/build-release.py
 * --list-shipped`, the same `.distignore`-driven list Gate G-D uses -- so it
 * cannot drift from what actually ships, filtered to LINTED_EXTENSIONS
 * (`.js` + `.jsx`; see that constant for why `.jsx` was this gate's own first
 * blind spot). Any shipped file carrying a script extension OUTSIDE that set
 * is counted and printed on every run and FAILS the gate, rather than being
 * silently skipped -- see UNLINTED_SCRIPT_EXTENSIONS. Two families are then
 * excluded, BY NAME, with the file list printed every run:
 *   - `assets/vendor/**` -- third-party minified bundles (flatpickr, swiper).
 *   - `build/**` -- webpack output. Its sources under `src-react/` ARE
 *     linted, so nothing goes unmeasured; the bundle additionally contains
 *     minified chart.js, whose deliberate `case` fall-throughs and
 *     `e.width = e.width` canvas-reset idiom are upstream code this repo
 *     cannot act on.
 * A finding in either family could not be fixed without forking upstream, so
 * failing on one would make the gate un-actionable -- but the narrowing is
 * printed, not assumed.
 *
 * WHERE IT RUNS. The host, not the `rentiva-dev-wpcli-1` container: ESLint is
 * a Node tool and that container has neither node nor python3. That is why
 * this is a `bin/` script and not a PHPUnit test -- a PHPUnit test would have
 * to skip itself in the only environment the suite runs in, which is a green
 * that measures nothing. Same documented host-only shape as Gate G-D
 * (`bin/check-plugin-check-parity.php`) and
 * `bin/check-template-literal-markup.php --verify-scope`.
 *
 * EXIT CODES. 0 clean, 1 findings, 2 cannot measure (no python to derive the
 * shipped list, or no node/npx to run ESLint). 2 is never conflated with 0.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

const EXIT_CLEAN          = 0;
const EXIT_FINDINGS       = 1;
const EXIT_CANNOT_MEASURE = 2;

/**
 * Rules from eslint:recommended that this gate turns OFF, with the reason
 * printed alongside them on every run.
 *
 * @var array<string, string>
 */
const EXCLUDED_RULES = array(
	'no-unused-vars'     => 'unused locals/params -- dead code, not wrong behaviour (standing debt)',
	'no-empty'           => 'empty block -- several deliberate no-op error handlers',
	'no-useless-escape'  => 'redundant regex escape -- cosmetic',
	'no-extra-semi'      => 'formatting',
);

/**
 * The brief's named minimum. Always reported with a count, zero included, so
 * a rule that found nothing is stated rather than left to silence.
 *
 * @var list<string>
 */
const REPORTED_MINIMUM = array(
	'no-dupe-keys',
	'no-undef',
	'no-redeclare',
	'no-func-assign',
	'no-cond-assign',
	'no-dupe-args',
	'no-unreachable',
	'no-const-assign',
);

/**
 * Path prefixes excluded from the shipped scope, with their reason.
 *
 * @var array<string, string>
 */
const EXCLUDED_PREFIXES = array(
	'assets/vendor/' => 'third-party minified bundles (flatpickr, swiper)',
	'build/'         => 'webpack output; its sources under src-react/ are linted instead',
);

/**
 * File extensions this gate lints.
 *
 * `.jsx` is here because leaving it out was this gate's own first blind spot:
 * the scope filter matched `.js` only, so 35 shipped `src-react` component
 * sources -- the real sources behind the `build/` bundles this gate excludes
 * BY NAME, on the stated grounds that "its sources under src-react/ are
 * linted instead" -- were neither linted nor printed as excluded. They were
 * clean at the time, which is the dangerous kind of gap: the gate printed a
 * confident zero over a surface it had never read.
 *
 * @var list<string>
 */
const LINTED_EXTENSIONS = array( 'js', 'jsx' );

/**
 * Script extensions that do NOT ship today and that this gate therefore does
 * not lint. Counted on every run rather than assumed: if one ever appears in
 * the shipped list, the gate says so and fails, instead of silently reporting
 * zero over a surface it never read. That is the same failure this gate's own
 * `.jsx` gap was, and the only defence against repeating it is to measure the
 * question rather than answer it in a comment.
 *
 * @var list<string>
 */
const UNLINTED_SCRIPT_EXTENSIONS = array( 'ts', 'tsx', 'mjs', 'cjs' );

/**
 * Run a command without a shell. proc_open() with an ARRAY command line
 * bypasses the shell on both POSIX and Windows, so nothing here depends on
 * quoting rules. Mirrors bin/check-plugin-check-parity.php's own run_cmd().
 *
 * @param list<string> $argv_cmd
 * @param string|null  $stdin_data Written to the child's stdin when given.
 * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
 */
function mhmrentiva_run_cmd( array $argv_cmd, ?string $stdin_data = null ): array {
	$spec = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);

	$proc = @proc_open( $argv_cmd, $spec, $pipes );
	if ( ! is_resource( $proc ) ) {
		return array( -1, '', 'proc_open failed for: ' . implode( ' ', $argv_cmd ) );
	}

	if ( null !== $stdin_data ) {
		fwrite( $pipes[0], $stdin_data );
	}
	fclose( $pipes[0] );

	$stdout = (string) stream_get_contents( $pipes[1] );
	$stderr = (string) stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $proc );

	return array( $code, $stdout, $stderr );
}

$root    = rtrim( str_replace( '\\', '/', dirname( __DIR__ ) ), '/' );
$verbose = in_array( '--list-files', $argv, true );

// ---- scope: the real shipped list, filtered to LINTED_EXTENSIONS -------

$shipped = null;
foreach ( array( 'python3', 'python', 'py' ) as $py ) {
	list( $rc, $out ) = mhmrentiva_run_cmd( array( $py, $root . '/bin/build-release.py', '--list-shipped' ) );
	if ( 0 === $rc && '' !== trim( $out ) ) {
		$shipped = preg_split( '/\R/', trim( $out ) );
		break;
	}
}

if ( null === $shipped ) {
	fwrite( STDERR, "G-E: cannot measure -- no working python3/python/py to run bin/build-release.py --list-shipped\n" );
	exit( EXIT_CANNOT_MEASURE );
}

/**
 * Extension of one shipped path, lowercased, '' when it has none.
 */
$extension_of = static function ( string $rel ): string {
	$base = basename( $rel );
	$dot  = strrpos( $base, '.' );

	return false === $dot ? '' : strtolower( substr( $base, $dot + 1 ) );
};

$shipped_scripts = array();
$per_extension   = array();
$unlinted        = array();
foreach ( $shipped as $rel ) {
	$ext = $extension_of( $rel );

	if ( in_array( $ext, LINTED_EXTENSIONS, true ) ) {
		$shipped_scripts[]     = $rel;
		$per_extension[ $ext ] = ( $per_extension[ $ext ] ?? 0 ) + 1;
		continue;
	}

	// Measured, not assumed -- see UNLINTED_SCRIPT_EXTENSIONS.
	if ( in_array( $ext, UNLINTED_SCRIPT_EXTENSIONS, true ) ) {
		$unlinted[] = $rel;
	}
}
sort( $shipped_scripts );
sort( $unlinted );

$in_scope = array();
$excluded = array();
foreach ( $shipped_scripts as $rel ) {
	$skipped = false;
	foreach ( array_keys( EXCLUDED_PREFIXES ) as $prefix ) {
		if ( 0 === strpos( $rel, $prefix ) ) {
			$excluded[] = $rel;
			$skipped    = true;
			break;
		}
	}
	if ( ! $skipped ) {
		$in_scope[] = $rel;
	}
}

if ( array() === $in_scope ) {
	fwrite( STDERR, "G-E: cannot measure -- the shipped list contains no in-scope script files\n" );
	exit( EXIT_CANNOT_MEASURE );
}

// ---- config: eslint:recommended + the project's own env/globals --------
//
// env and globals are READ from the committed .eslintrc.json rather than
// restated here. Restating them would create a second, silently-drifting
// definition of "what is a legitimate global in this codebase", and a stale
// copy shows up as phantom no-undef findings.

$project_config_path = $root . '/.eslintrc.json';
$project_config      = is_readable( $project_config_path )
	? json_decode( (string) file_get_contents( $project_config_path ), true )
	: null;

if ( ! is_array( $project_config ) ) {
	fwrite( STDERR, "G-E: cannot measure -- .eslintrc.json is missing or not valid JSON\n" );
	exit( EXIT_CANNOT_MEASURE );
}

$env        = is_array( $project_config['env'] ?? null ) ? $project_config['env'] : array();
$env['es2022'] = true;
// Third-party UMD wrappers in the shipped tree declare themselves against
// module/exports/define; without these envs every one is a phantom no-undef.
$env['commonjs'] = true;
$env['amd']      = true;

$rules = array();
foreach ( EXCLUDED_RULES as $rule => $_reason ) {
	$rules[ $rule ] = 'off';
}

$gate_config = array(
	'root'          => true,
	'extends'       => array( 'eslint:recommended' ),
	'parserOptions' => array(
		'ecmaVersion'  => 2022,
		'sourceType'   => 'module',
		'ecmaFeatures' => array( 'jsx' => true ),
	),
	'env'           => $env,
	'globals'       => is_array( $project_config['globals'] ?? null ) ? $project_config['globals'] : array(),
	'rules'         => $rules,
);

$config_file = tempnam( sys_get_temp_dir(), 'mhm-ge-' ) . '.json';
file_put_contents( $config_file, (string) wp_json_encode_fallback( $gate_config ) );

// ---- run ESLint --------------------------------------------------------
//
// --no-eslintrc so the project's own @wordpress extends chain (prettier,
// no-var, jsdoc...) cannot leak in; --no-inline-config so an inline
// /* eslint-disable */ in a source file cannot switch the gate off from
// inside the thing being measured.

$eslint_argv = array(
	'--no-eslintrc',
	'--config',
	$config_file,
	'--no-inline-config',
	'--format',
	'json',
);
foreach ( $in_scope as $rel ) {
	$eslint_argv[] = $root . '/' . $rel;
}

// `node node_modules/eslint/bin/eslint.js` rather than `npx eslint`: npx is a
// .cmd shim on Windows, which proc_open()'s shell-free array mode cannot
// launch, and npx would also happily reach for the network. This runs the
// exact ESLint this repo has installed, or nothing.
$report      = null;
$attempt     = array();
$eslint_main = $root . '/node_modules/eslint/bin/eslint.js';

if ( ! is_readable( $eslint_main ) ) {
	fwrite( STDERR, "G-E: cannot measure -- {$eslint_main} not found; run `npm install` in this repo\n" );
	exit( EXIT_CANNOT_MEASURE );
}

foreach ( array( 'node', 'node.exe' ) as $node ) {
	list( $rc, $out, $err ) = mhmrentiva_run_cmd( array_merge( array( $node, $eslint_main ), $eslint_argv ) );
	$attempt[]              = $node . ' -> rc=' . $rc . ( '' === trim( $err ) ? '' : ' stderr=' . substr( trim( $err ), 0, 300 ) );
	// ESLint exits 1 when it finds something, so the exit code is not the
	// health check -- parseable JSON on stdout is.
	$decoded = json_decode( trim( $out ), true );
	if ( is_array( $decoded ) ) {
		$report = $decoded;
		break;
	}
}

@unlink( $config_file );

if ( null === $report ) {
	fwrite( STDERR, "G-E: cannot measure -- could not run ESLint (" . implode( ', ', $attempt ) . ")\n" );
	fwrite( STDERR, "     is node installed and `npm install` run in this repo?\n" );
	exit( EXIT_CANNOT_MEASURE );
}

// ---- report ------------------------------------------------------------

$per_rule = array();
$findings = array();
foreach ( $report as $file_report ) {
	$path = str_replace( '\\', '/', (string) ( $file_report['filePath'] ?? '' ) );
	$rel  = 0 === strpos( $path, $root . '/' ) ? substr( $path, strlen( $root ) + 1 ) : $path;

	foreach ( (array) ( $file_report['messages'] ?? array() ) as $message ) {
		$rule = (string) ( $message['ruleId'] ?? '' );
		if ( '' === $rule ) {
			// A parse error has no ruleId. It is a finding, not a pass.
			$rule = '(parse error)';
		}
		$per_rule[ $rule ] = ( $per_rule[ $rule ] ?? 0 ) + 1;
		$findings[]        = sprintf(
			'%s:%d:%d  %-28s %s',
			$rel,
			(int) ( $message['line'] ?? 0 ),
			(int) ( $message['column'] ?? 0 ),
			$rule,
			(string) ( $message['message'] ?? '' )
		);
	}
}

echo "Gate G-E -- JS correctness over the SHIPPED JavaScript surface\n";
echo "  enforced : eslint:recommended in full (ESLint's own probable-bug set)\n";
echo "  NOT enforced -- formatting and house style, the standing accepted debt:\n";
echo "             prettier/prettier, no-var, object-shorthand, camelcase, curly, jsdoc/*\n";
echo "             (not extended here at all -- they come from @wordpress/eslint-plugin)\n";
echo "  NOT enforced -- recommended rules turned off, by name:\n";
foreach ( EXCLUDED_RULES as $rule => $reason ) {
	printf( "             %-20s %s\n", $rule, $reason );
}
$extension_breakdown = array();
foreach ( LINTED_EXTENSIONS as $ext ) {
	$extension_breakdown[] = $ext . '=' . ( $per_extension[ $ext ] ?? 0 );
}
printf(
	"  scope    : %d shipped script files (%s) from `bin/build-release.py --list-shipped`; %d linted, %d excluded\n",
	count( $shipped_scripts ),
	implode( ' ', $extension_breakdown ),
	count( $in_scope ),
	count( $excluded )
);
foreach ( EXCLUDED_PREFIXES as $prefix => $reason ) {
	printf( "             %-16s %s\n", $prefix, $reason );
}
foreach ( $excluded as $rel ) {
	printf( "             excluded: %s\n", $rel );
}
printf(
	"  unlinted : %d shipped file(s) with a script extension this gate does not lint (%s) -- counted, not assumed\n",
	count( $unlinted ),
	implode( '/', UNLINTED_SCRIPT_EXTENSIONS )
);
foreach ( $unlinted as $rel ) {
	printf( "             UNLINTED SHIPPED SCRIPT: %s\n", $rel );
}
if ( $verbose ) {
	foreach ( $in_scope as $rel ) {
		printf( "             linted:   %s\n", $rel );
	}
}

echo "\n  named minimum (reported with an explicit count, zero included):\n";
foreach ( REPORTED_MINIMUM as $rule ) {
	printf( "    %6d  %s\n", $per_rule[ $rule ] ?? 0, $rule );
}

$other = $per_rule;
foreach ( REPORTED_MINIMUM as $rule ) {
	unset( $other[ $rule ] );
}
arsort( $other );
echo "\n  other enforced rules with findings:\n";
if ( array() === $other ) {
	echo "    (none)\n";
} else {
	foreach ( $other as $rule => $count ) {
		printf( "    %6d  %s\n", $count, $rule );
	}
}

if ( array() !== $findings ) {
	echo "\n  findings:\n";
	foreach ( $findings as $line ) {
		echo '    ' . $line . "\n";
	}
}

$total = array_sum( $per_rule );
printf(
	"\nG-E SUMMARY: findings=%d rules_with_findings=%d files_linted=%d files_excluded=%d shipped_scripts=%d (%s) unlinted_script_files=%d\n",
	$total,
	count( $per_rule ),
	count( $in_scope ),
	count( $excluded ),
	count( $shipped_scripts ),
	implode( ' ', $extension_breakdown ),
	count( $unlinted )
);

// A shipped script this gate cannot read is a failure, not a footnote: the
// alternative is printing findings=0 over a surface never opened, which is
// precisely the gap the `.jsx` filter left.
if ( array() !== $unlinted ) {
	fwrite( STDERR, "G-E: FAIL -- " . count( $unlinted ) . " shipped script file(s) fall outside LINTED_EXTENSIONS; extend the gate before shipping them\n" );
	exit( EXIT_FINDINGS );
}

exit( 0 === $total ? EXIT_CLEAN : EXIT_FINDINGS );

/**
 * json_encode() without depending on WordPress being loaded (this script runs
 * standalone from the host, outside any WP bootstrap).
 *
 * @param mixed $value
 */
function wp_json_encode_fallback( $value ): string {
	return (string) json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}
