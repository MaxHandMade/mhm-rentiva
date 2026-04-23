<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (! defined('ABSPATH')) {
	exit; }

final class DemoAjaxHandler {

    private const NONCE_ACTION = 'mhm_rentiva_demo_nonce';

    public static function register(): void {
        add_action('wp_ajax_mhm_rentiva_demo_seed', [ self::class, 'handle_seed' ]);
        add_action('wp_ajax_mhm_rentiva_demo_cleanup', [ self::class, 'handle_cleanup' ]);
    }

    public static function get_seed_steps(): array {
        return [
            'cleanup'    => [
				'label'    => __('Cleaning old demo data...', 'mhm-rentiva'),
				'progress' => 10,
			],
            'categories' => [
				'label'    => __('Creating categories...', 'mhm-rentiva'),
				'progress' => 20,
			],
            'images'     => [
				'label'    => __('Uploading vehicle images...', 'mhm-rentiva'),
				'progress' => 35,
			],
            'vehicles'   => [
				'label'    => __('Creating vehicles...', 'mhm-rentiva'),
				'progress' => 50,
			],
            'users'      => [
				'label'    => __('Creating customers...', 'mhm-rentiva'),
				'progress' => 60,
			],
            'addons'     => [
				'label'    => __('Creating add-on services...', 'mhm-rentiva'),
				'progress' => 70,
			],
            'transfers'  => [
				'label'    => __('Creating transfer points...', 'mhm-rentiva'),
				'progress' => 80,
			],
            'bookings'   => [
				'label'    => __('Creating bookings...', 'mhm-rentiva'),
				'progress' => 90,
			],
            'messages'   => [
				'label'    => __('Creating messages...', 'mhm-rentiva'),
				'progress' => 95,
			],
            'finalize'   => [
				'label'    => __('Finalizing...', 'mhm-rentiva'),
				'progress' => 100,
			],
        ];
    }

    public static function get_cleanup_steps(): array {
        return [
            'posts'    => [
				'label'    => __('Removing demo records...', 'mhm-rentiva'),
				'progress' => 30,
			],
            'users'    => [
				'label'    => __('Removing demo customers...', 'mhm-rentiva'),
				'progress' => 50,
			],
            'terms'    => [
				'label'    => __('Removing demo categories...', 'mhm-rentiva'),
				'progress' => 60,
			],
            'tables'   => [
				'label'    => __('Cleaning custom tables...', 'mhm-rentiva'),
				'progress' => 75,
			],
            'images'   => [
				'label'    => __('Removing demo images...', 'mhm-rentiva'),
				'progress' => 85,
			],
            'cache'    => [
				'label'    => __('Clearing cache...', 'mhm-rentiva'),
				'progress' => 95,
			],
            'finalize' => [
				'label'    => __('Finalizing...', 'mhm-rentiva'),
				'progress' => 100,
			],
        ];
    }

    public static function get_nonce(): string {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    public static function handle_seed(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error([ 'message' => __('Permission denied.', 'mhm-rentiva') ], 403);
        }
        $step  = sanitize_key(wp_unslash($_POST['step'] ?? ''));
        $steps = self::get_seed_steps();
        if (! isset($steps[ $step ])) {
            wp_send_json_error([ 'message' => __('Invalid step.', 'mhm-rentiva') ], 400);
        }
        $seeder = new DemoSeeder();
        $method = 'step_' . $step;
        if (! method_exists($seeder, $method)) {
            wp_send_json_error([ 'message' => __('Step not implemented.', 'mhm-rentiva') ], 500);
        }
        $result = $seeder->$method();
        wp_send_json_success([
            'step'     => $step,
            'message'  => $result['message'] ?? '',
            'count'    => $result['count']   ?? 0,
            'progress' => $steps[ $step ]['progress'],
        ]);
    }

    public static function handle_cleanup(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error([ 'message' => __('Permission denied.', 'mhm-rentiva') ], 403);
        }
        $step  = sanitize_key(wp_unslash($_POST['step'] ?? ''));
        $steps = self::get_cleanup_steps();
        if (! isset($steps[ $step ])) {
            wp_send_json_error([ 'message' => __('Invalid step.', 'mhm-rentiva') ], 400);
        }
        $seeder = new DemoSeeder();
        $method = 'cleanup_' . $step;
        if (! method_exists($seeder, $method)) {
            wp_send_json_error([ 'message' => __('Step not implemented.', 'mhm-rentiva') ], 500);
        }
        $result = $seeder->$method();
        wp_send_json_success([
            'step'     => $step,
            'message'  => $result['message'] ?? '',
            'count'    => $result['count']   ?? 0,
            'progress' => $steps[ $step ]['progress'],
        ]);
    }
}
