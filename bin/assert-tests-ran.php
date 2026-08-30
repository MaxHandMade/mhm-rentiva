<?php
/**
 * Assert that a PHPUnit run actually executed something.
 *
 * WHY THIS EXISTS
 *
 * PHPUnit 9.6 exits 0 when a --filter or --group matches nothing. It prints
 * "No tests executed!" and returns success, so a scoped run whose scope has
 * rotted -- a renamed class, a deleted @group annotation -- becomes a step that
 * passes without measuring anything. Measured, both forms:
 *
 *   phpunit --filter ZzzNoSuchTestAnywhere  ->  "No tests executed!"  exit 0
 *   phpunit --group  zzz-no-such-group      ->  "No tests executed!"  exit 0
 *
 * 9.6 has --fail-on-incomplete, --fail-on-risky, --fail-on-skipped and
 * --fail-on-warning, but no --fail-on-empty-test-suite (that arrived in 10).
 * So the emptiness check is this file.
 *
 * It reads the JUnit log rather than scraping stdout, because the human-facing
 * output changes between versions and a grep against it is a second thing to
 * keep in sync.
 *
 * Exit codes follow the house convention used by the other gates:
 *   0  tests ran
 *   1  the run executed zero tests
 *   2  cannot measure -- no log, or a log this cannot parse
 *
 * The distinction between 1 and 2 is deliberate and was corrected after the
 * first draft got it wrong: an empty selection writes a well-formed log with no
 * children at all -- literally `<testsuites/>`, measured -- and reporting that
 * as "cannot measure" sends the next reader hunting a missing file instead of a
 * rotted scope.
 *
 * @package Mhm_Rentiva
 */

declare(strict_types=1);

$log = $argv[1] ?? '';

if ('' === $log) {
    fwrite(STDERR, "assert-tests-ran: CANNOT MEASURE -- no JUnit log path given.\n");
    fwrite(STDERR, "  usage: php bin/assert-tests-ran.php <junit.xml>\n");
    exit(2);
}

if (! is_file($log)) {
    fwrite(STDERR, "assert-tests-ran: CANNOT MEASURE -- '$log' does not exist.\n");
    fwrite(STDERR, "  the phpunit run was expected to write it with --log-junit.\n");
    exit(2);
}

$previous = libxml_use_internal_errors(true);
$xml      = simplexml_load_file($log);
libxml_use_internal_errors($previous);

if (false === $xml) {
    fwrite(STDERR, "assert-tests-ran: CANNOT MEASURE -- '$log' is not parseable XML.\n");
    exit(2);
}

if ('testsuites' !== $xml->getName()) {
    fwrite(STDERR, "assert-tests-ran: CANNOT MEASURE -- '$log' is not a PHPUnit JUnit log.\n");
    fwrite(STDERR, '  expected a <testsuites> root, found <' . $xml->getName() . ">.\n");
    exit(2);
}

// The root carries no counts of its own; each child <testsuite> does. Only
// top-level children are summed, or nested suites would be counted twice.
$total = 0;

foreach ($xml->testsuite as $suite) {
    $total += (int) $suite['tests'];
}

if ($total < 1) {
    fwrite(STDERR, "assert-tests-ran: ZERO TESTS EXECUTED.\n");
    fwrite(STDERR, "  phpunit exits 0 on an empty selection, so this is the check that says so.\n");
    fwrite(STDERR, "  the --filter or --group almost certainly no longer matches anything.\n");
    exit(1);
}

printf("assert-tests-ran: OK -- %d test(s) executed (%s).\n", $total, $log);
exit(0);
