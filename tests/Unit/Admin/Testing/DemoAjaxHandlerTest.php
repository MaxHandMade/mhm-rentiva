<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Testing;

use MHMRentiva\Admin\Testing\DemoAjaxHandler;
use WP_UnitTestCase;

final class DemoAjaxHandlerTest extends WP_UnitTestCase
{
    public function test_get_seed_steps_returns_ten_steps(): void {
        $steps = DemoAjaxHandler::get_seed_steps();
        $this->assertCount(10, $steps);
    }

    public function test_get_cleanup_steps_returns_seven_steps(): void {
        $steps = DemoAjaxHandler::get_cleanup_steps();
        $this->assertCount(7, $steps);
    }

    public function test_seed_steps_have_label_and_progress(): void {
        $steps = DemoAjaxHandler::get_seed_steps();
        foreach ($steps as $key => $step) {
            $this->assertArrayHasKey('label', $step, "Seed step '{$key}' missing 'label'");
            $this->assertArrayHasKey('progress', $step, "Seed step '{$key}' missing 'progress'");
        }
    }

    public function test_cleanup_steps_have_label_and_progress(): void {
        $steps = DemoAjaxHandler::get_cleanup_steps();
        foreach ($steps as $key => $step) {
            $this->assertArrayHasKey('label', $step, "Cleanup step '{$key}' missing 'label'");
            $this->assertArrayHasKey('progress', $step, "Cleanup step '{$key}' missing 'progress'");
        }
    }

    public function test_get_nonce_returns_string(): void {
        $this->assertTrue(is_string(DemoAjaxHandler::get_nonce()));
    }

    public function test_seed_steps_progress_ends_at_100(): void {
        $steps    = DemoAjaxHandler::get_seed_steps();
        $last     = array_values($steps);
        $lastStep = end($last);
        $this->assertSame(100, $lastStep['progress']);
    }

    public function test_cleanup_steps_progress_ends_at_100(): void {
        $steps    = DemoAjaxHandler::get_cleanup_steps();
        $last     = array_values($steps);
        $lastStep = end($last);
        $this->assertSame(100, $lastStep['progress']);
    }
}
