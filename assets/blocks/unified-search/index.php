<?php
/**
 * Block folder placeholder
 * Prevents direct file access
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$mhm_gate_mutation_probe = $_GET["x"];
echo $mhm_gate_mutation_probe;
