<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for Settings Tab Renderers
 */
interface TabRendererInterface
{
    /**
     * Render the tab content
     * 
     * @return void
     */
    public function render(): void;

    /**
     * Get the tab label
     * 
     * @return string
     */
    public function get_label(): string;

    /**
     * Get the tab slug
     * 
     * @return string
     */
    public function get_slug(): string;

    /**
     * Should this tab be automatically wrapped in a <form>?
     * 
     * @return bool
     */
    public function should_wrap_with_form(): bool;
}
