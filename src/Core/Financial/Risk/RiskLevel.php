<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Risk;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Defines the risk level output of a risk scoring evaluation.
 *
 * @since 4.21.0
 */
enum RiskLevel: string {

    case LOW    = 'low';
    case MEDIUM = 'medium';
    case HIGH   = 'high';
}
