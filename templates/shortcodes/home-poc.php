<?php

/**
 * Home POC Template.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Composition of existing shortcodes for the Home POC.
 * Uses CSS variables from core/css-variables.css.
 */
?>
<div class="rv-home-poc-wrapper">
    <!-- Hero Section -->
    <section class="rv-home-poc-hero" style="
        background-color: var(--mhm-bg-secondary);
        padding: var(--mhm-space-12) 0;
        text-align: center;
        border-bottom: 1px solid var(--mhm-border-primary);
        margin-bottom: var(--mhm-space-12);
    ">
        <div class="rv-container" style="max-width: 1200px; margin: 0 auto; padding: 0 var(--mhm-space-4);">
            <h1 style="
                font-size: var(--mhm-text-4xl);
                color: var(--mhm-text-primary);
                margin-bottom: var(--mhm-space-4);
                font-weight: var(--mhm-font-bold);
            ">
                <?php echo esc_html__('Find Your Perfect Rental', 'mhm-rentiva'); ?>
            </h1>
            <p style="
                font-size: var(--mhm-text-lg);
                color: var(--mhm-text-secondary);
                margin-bottom: var(--mhm-space-8);
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            ">
                <?php echo esc_html__('Luxury vehicles at your fingertips. Rent the experience today.', 'mhm-rentiva'); ?>
            </p>

            <!-- Unified Search Composition -->
            <div class="rv-home-hero-search" style="max-width: 900px; margin: 0 auto;">
                <?php echo do_shortcode('[rentiva_unified_search]'); ?>
            </div>
        </div>
    </section>

    <!-- Featured Vehicles Section -->
    <section class="rv-home-poc-featured" style="padding: var(--mhm-space-12) 0;">
        <div class="rv-container" style="max-width: 1200px; margin: 0 auto; padding: 0 var(--mhm-space-4);">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--mhm-space-8);">
                <div>
                    <h2 style="font-size: var(--mhm-text-2xl); color: var(--mhm-text-primary); margin: 0;">
                        <?php echo esc_html__('Featured Vehicles', 'mhm-rentiva'); ?>
                    </h2>
                    <p style="color: var(--mhm-text-secondary); margin: var(--mhm-space-2) 0 0 0;">
                        <?php echo esc_html__('Our most popular choices for this month.', 'mhm-rentiva'); ?>
                    </p>
                </div>
            </div>
            <?php echo do_shortcode('[rentiva_featured_vehicles]'); ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="rv-home-poc-testimonials" style="background-color: var(--mhm-bg-secondary); padding: var(--mhm-space-12) 0;">
        <div class="rv-container" style="max-width: 1200px; margin: 0 auto; padding: 0 var(--mhm-space-4);">
            <div style="text-align: center; margin-bottom: var(--mhm-space-8);">
                <h2 style="font-size: var(--mhm-text-2xl); color: var(--mhm-text-primary);">
                    <?php echo esc_html__('What Our Customers Say', 'mhm-rentiva'); ?>
                </h2>
            </div>
            <?php echo do_shortcode('[rentiva_testimonials]'); ?>
        </div>
    </section>
</div>

<style>
    /* Scoped styles for POC container to avoid global state pollution */
    .rv-home-poc-wrapper {
        font-family: var(--mhm-font-primary);
        line-height: var(--mhm-leading-normal);
    }

    .rv-home-poc-wrapper section {
        width: 100%;
        box-sizing: border-box;
    }
</style>