<?php
/**
 * Report every remaining mention of the retired payment-amount meta keys.
 *
 * REPORTS, NEVER GATES. Exit code is always 0. An absence defect needs a tool
 * you read, not one you silence.
 *
 * Starting set: every file `git ls-files` tracks, minus languages/ and
 * bin/prefix-inventory-baseline.txt. That is deliberately the whole tree and
 * not src/ -- the 2026-08-18 sweep that started at src/ could not see the root
 * plugin file, templates/ or a key named inside a SQL string, and reported
 * "(none)" while telling the truth about what it had looked at.
 *
 * Not visible to this probe, by construction: a key assembled at runtime
 * ($prefix . '_payment_amount'), a key inside a compiled JS bundle, and a key
 * living in the Pro tree (run it there separately).
 */

declare(strict_types=1);

$keys = array(
	'_mhmrentiva_payment_amount',
	'_mhmrentiva_booking_payment_amount',
);

$tracked = array();
exec('git ls-files', $tracked, $status);

if ($status !== 0) {
	fwrite(STDERR, "git ls-files failed; run this from inside the repository.\n");
	exit(0);
}

$skip = static function (string $path): bool {
	return str_starts_with($path, 'languages/')
		|| $path === 'bin/prefix-inventory-baseline.txt'
		|| $path === 'bin/audit-retired-meta.php';
};

$hits = array();

foreach ($tracked as $path) {
	if ($skip($path) || ! is_file($path)) {
		continue;
	}

	$lines = file($path, FILE_IGNORE_NEW_LINES);

	if ($lines === false) {
		continue;
	}

	foreach ($lines as $n => $line) {
		foreach ($keys as $key) {
			if (str_contains($line, $key)) {
				$hits[] = sprintf('%s:%d  %s', $path, $n + 1, trim($line));
			}
		}
	}
}

echo "Retired-meta report\n";
echo "Keys: " . implode(', ', $keys) . "\n";
echo "Scanned: " . count($tracked) . " tracked files (languages/ and the prefix baseline excluded)\n";
echo "Cannot see: runtime-assembled key names, compiled JS bundles, the Pro tree\n";
echo str_repeat('-', 72) . "\n";

if ($hits === array()) {
	echo "No mentions found in the scanned set.\n";
} else {
	echo implode("\n", $hits) . "\n";
	echo str_repeat('-', 72) . "\n";
	echo count($hits) . " mention(s). Each one is either a comment, a cleanup list, or a defect.\n";
}

exit(0);
