<?php
declare(strict_types=1);
namespace MHMRentiva\Tests\Unit\Carveout;
use PHPUnit\Framework\TestCase;

final class SeamAllowlistGuardTest extends TestCase {
    public function test_allowlisted_absent_class_not_dangling(): void {
        $root = dirname(__DIR__, 3);
        // Synthetic file: a guard on an absent, allowlisted Pro class.
        $tmp = sys_get_temp_dir() . '/seam_probe_' . uniqid();
        mkdir($tmp . '/src/Admin/Messages/Core', 0777, true);
        file_put_contents($tmp . '/src/probe.php',
            "<?php if (class_exists('\\\\MHMRentiva\\\\Admin\\\\Messages\\\\Core\\\\Messages')) { \\MHMRentiva\\Admin\\Messages\\Core\\Messages::register(); }");
        mkdir($tmp . '/bin', 0777, true);
        file_put_contents($tmp . '/bin/seam-classes.txt', "MHMRentiva\\Admin\\Messages\\Core\\Messages\n");
        // The checker's $root defaults to dirname(__DIR__) of its OWN file location,
        // independent of CWD -- `cd`-ing into $tmp alone would not stop it from
        // scanning the real repo's src/ tree. MHM_GUARD_ROOT overrides that so this
        // probe is genuinely isolated from repo state (no dependency on which Pro
        // classes happen to still be physically present). putenv() makes the value
        // visible to the shell_exec()'d child process's environment.
        putenv('MHM_GUARD_ROOT=' . $tmp);
        $out = shell_exec('cd ' . escapeshellarg($tmp) . ' && php ' . escapeshellarg($root . '/bin/check-guarded-refs.php') . ' 2>&1');
        putenv('MHM_GUARD_ROOT');
        $this->assertStringContainsString('[OK]', (string) $out);
        $this->assertStringNotContainsString('Messages', (string) $out);
    }
}
