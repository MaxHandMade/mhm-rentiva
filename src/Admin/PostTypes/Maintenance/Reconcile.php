<?php declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\Admin\Payment\Gateways\PayTR\Client as PayTRClient;
use MHMRentiva\Admin\Payment\Gateways\Stripe\Client as StripeClient;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

final class Reconcile
{
    public const EVENT = 'mhm_rentiva_reconcile_event';
    public const SCHEDULE = 'mhm_rentiva_10min';

    public static function register(): void
    {
        // Add custom schedule (every 10 minutes) - very early hook (before init)
        add_filter('cron_schedules', [self::class, 'schedules'], 1);
        // Schedule event if not scheduled - run late on init (after schedules are defined)
        add_action('init', [self::class, 'maybe_schedule'], 999);
        add_action(self::EVENT, [self::class, 'run']);
    }

    public static function schedules(array $schedules): array
    {
        if (!isset($schedules[self::SCHEDULE])) {
            $schedules[self::SCHEDULE] = [
                'interval' => 600, // 10 minutes
                'display'  => __('Every 10 Minutes (Rentiva Reconciliation)', 'mhm-rentiva'),
            ];
        }
        return $schedules;
    }

    public static function maybe_schedule(): void
    {
        // If event is already scheduled, skip re-scheduling
        if (wp_next_scheduled(self::EVENT)) {
            return;
        }
        
        // Check if custom schedule exists
        $schedules = wp_get_schedules();
        
        if (!isset($schedules[self::SCHEDULE])) {
            // If custom schedule is not defined, use hourly as fallback
            if (!wp_next_scheduled(self::EVENT)) {
                wp_schedule_event(time() + 120, 'hourly', self::EVENT);
            }
            return;
        }
        
        // Create new schedule
        wp_schedule_event(time() + 120, self::SCHEDULE, self::EVENT);
    }

    public static function run(): void
    {
        $enabled = (string) get_option('mhm_rentiva_reconcile_enabled', '1') === '1';
        if (!$enabled) return;
        $limit = (int) get_option('mhm_rentiva_reconcile_limit', 50);
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;
        $retryLimit = (int) get_option('mhm_rentiva_retry_limit', 2);
        if ($retryLimit < 0) $retryLimit = 0;
        if ($retryLimit > 5) $retryLimit = 5;

        // Reconcile PayTR and Stripe pending/unknown
        self::reconcile_paytr($limit);
        self::reconcile_stripe($limit);

        // Retry queue for recent failures
        if ($retryLimit > 0) {
            self::retry_failed_operations($retryLimit, $limit);
        }

        // Orphan/tidy meta
        self::cleanup_orphans(50);
        self::detect_inconsistent(50);
    }

    private static function reconcile_paytr(int $limit): void
    {
        // Bookings with PayTR, having OID, and payment status in pending-ish states
        $q = new WP_Query([
            'post_type'      => 'vehicle_booking',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_mhm_payment_gateway',
                    'value'   => 'paytr',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_paytr_merchant_oid',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_mhm_payment_status',
                    'value'   => ['pending', 'unknown', 'processing', 'unpaid'],
                    'compare' => 'IN',
                ],
            ],
        ]);
        if (!$q->have_posts()) return;

        $client = new PayTRClient();
        foreach ($q->posts as $bid) {
            $bid = (int) $bid;
            $oid = (string) get_post_meta($bid, '_mhm_paytr_merchant_oid', true);
            if ($oid === '') continue;
            try {
                $res = $client->inquire(['merchant_oid' => $oid]);
            } catch (\Throwable $e) {
                Logger::add([
                    'gateway'    => 'paytr',
                    'action'     => 'reconcile',
                    'status'     => 'error',
                    'booking_id' => $bid,
                    'oid'        => $oid,
                    'message'    => $e->getMessage(),
                ]);
                continue;
            }
            if (!$res['ok']) {
                Logger::add([
                    'gateway'    => 'paytr',
                    'action'     => 'reconcile',
                    'status'     => 'error',
                    'booking_id' => $bid,
                    'oid'        => $oid,
                    'message'    => (string) ($res['message'] ?? __('Inquiry failed', 'mhm-rentiva')),
                    'context'    => ['code' => (string) ($res['code'] ?? '')],
                ]);
                continue;
            }
            $paid = (bool) ($res['paid'] ?? false);
            if ($paid) {
                $amount = (int) ($res['payment_amount'] ?? 0);
                update_post_meta($bid, '_mhm_payment_status', 'paid');
                if ($amount > 0) update_post_meta($bid, '_mhm_payment_amount', $amount);
                update_post_meta($bid, '_mhm_payment_currency', 'TRY');
                Logger::add([
                    'gateway'      => 'paytr',
                    'action'       => 'reconcile',
                    'status'       => 'success',
                    'booking_id'   => $bid,
                    'oid'          => $oid,
                    'amount_kurus' => $amount,
                    'currency'     => 'TRY',
                    'message'      => __('Reconciled: payment confirmed by PayTR', 'mhm-rentiva'),
                ]);
            }
        }
        wp_reset_postdata();
    }

    private static function reconcile_stripe(int $limit): void
    {
        // Bookings with Stripe payment intent and pending-ish status
        $q = new WP_Query([
            'post_type'      => 'vehicle_booking',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_mhm_payment_gateway',
                    'value'   => 'stripe',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_stripe_payment_intent',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_mhm_payment_status',
                    'value'   => ['pending', 'unknown', 'processing'],
                    'compare' => 'IN',
                ],
            ],
        ]);
        if (!$q->have_posts()) return;

        $client = new StripeClient();
        foreach ($q->posts as $bid) {
            $bid = (int) $bid;
            $pi = (string) get_post_meta($bid, '_mhm_stripe_payment_intent', true);
            if ($pi === '') continue;
            try {
                $res = $client->retrievePaymentIntent($pi);
            } catch (\Throwable $e) {
                Logger::add([
                    'gateway'    => 'stripe',
                    'action'     => 'reconcile',
                    'status'     => 'error',
                    'booking_id' => $bid,
                    'message'    => $e->getMessage(),
                ]);
                continue;
            }
            if (!$res['ok']) {
                Logger::add([
                    'gateway'    => 'stripe',
                    'action'     => 'reconcile',
                    'status'     => 'error',
                    'booking_id' => $bid,
                    'message'    => (string) ($res['message'] ?? __('Stripe inquiry failed', 'mhm-rentiva')),
                    'context'    => ['code' => (string) ($res['code'] ?? '')],
                ]);
                continue;
            }
            $st = (string) ($res['status'] ?? '');
            if ($st === 'succeeded') {
                $amount = (int) ($res['amount'] ?? 0);
                $cur    = (string) ($res['currency'] ?? 'TRY');
                update_post_meta($bid, '_mhm_payment_status', 'paid');
                if ($amount > 0) update_post_meta($bid, '_mhm_payment_amount', $amount);
                if ($cur !== '') update_post_meta($bid, '_mhm_payment_currency', strtoupper($cur));
                Logger::add([
                    'gateway'      => 'stripe',
                    'action'       => 'reconcile',
                    'status'       => 'success',
                    'booking_id'   => $bid,
                    'amount_kurus' => $amount,
                    'currency'     => strtoupper($cur ?: 'TRY'),
                    'message'      => __('Reconciled: payment confirmed by Stripe', 'mhm-rentiva'),
                ]);
            } elseif (in_array($st, ['requires_payment_method','canceled'], true)) {
                update_post_meta($bid, '_mhm_payment_status', 'failed');
                Logger::add([
                    'gateway'    => 'stripe',
                    'action'     => 'reconcile',
                    'status'     => 'success',
                    'booking_id' => $bid,
                    /* translators: %s placeholder. */
                    'message'    => sprintf(__('Reconciled: payment status %s', 'mhm-rentiva'), $st),
                ]);
            }
        }
        wp_reset_postdata();
    }

    private static function retry_failed_operations(int $retryLimit, int $limit): void
    {
        // Find recent error logs for token_create or callback (last 24h)
        $dayAgo = gmdate('Y-m-d H:i:s', time() - 86400);
        $q = new WP_Query([
            'post_type'      => PostType::TYPE,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'date_query'     => [
                [
                    'column'    => 'post_date_gmt',
                    'after'     => $dayAgo,
                    'inclusive' => true,
                ],
            ],
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_mhm_log_status',
                    'value'   => 'error',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_log_action',
                    'value'   => ['callback','token_create'],
                    'compare' => 'IN',
                ],
                [
                    'key'     => '_mhm_log_gateway',
                    'value'   => ['paytr','stripe'],
                    'compare' => 'IN',
                ],
            ],
        ]);
        if (!$q->have_posts()) return;

        foreach ($q->posts as $lid) {
            $lid = (int) $lid;
            $bid = (int) get_post_meta($lid, '_mhm_log_booking_id', true);
            $act = (string) get_post_meta($lid, '_mhm_log_action', true);
            $gw  = (string) get_post_meta($lid, '_mhm_log_gateway', true);
            if ($bid <= 0 || !in_array($act, ['callback','token_create'], true)) continue;

            if ($act === 'callback') {
                $key = '_mhm_retry_callback_count';
                $cnt = (int) get_post_meta($bid, $key, true);
                if ($cnt >= $retryLimit) continue;
                update_post_meta($bid, $key, $cnt + 1);
                // Re-run reconciliation for that booking
                if ($gw === 'paytr') {
                    self::reconcile_paytr(1);
                } elseif ($gw === 'stripe') {
                    self::reconcile_stripe(1);
                }
            } elseif ($act === 'token_create' && $gw === 'paytr') {
                $key = '_mhm_retry_token_create_count';
                $cnt = (int) get_post_meta($bid, $key, true);
                if ($cnt >= $retryLimit) continue;
                update_post_meta($bid, $key, $cnt + 1);
                // Best-effort: try to pre-create a token and store iframe URL
                self::retry_paytr_token_create($bid);
            }
        }
        wp_reset_postdata();
    }

    private static function retry_paytr_token_create(int $booking_id): void
    {
        // Only when still unpaid
        $ps = (string) get_post_meta($booking_id, '_mhm_payment_status', true);
        if (!in_array($ps, ['unpaid','failed','unknown','pending'], true)) return;
        $oid = (string) get_post_meta($booking_id, '_mhm_paytr_merchant_oid', true);
        if ($oid === '') return;

        // Minimal payload reconstruction (some fields best-effort)
        $total = (float) get_post_meta($booking_id, '_mhm_total_price', true);
        if ($total <= 0) return;
        $amount_kurus = (int) round($total * 100);
        $email = (string) get_post_meta($booking_id, '_mhm_contact_email', true);
        $name  = (string) get_post_meta($booking_id, '_mhm_contact_name', true);
        $ip    = '127.0.0.1';

        $cfgOk = \MHMRentiva\Admin\Payment\Gateways\PayTR\Config::enabled();
        if (!$cfgOk) return;

        $basket = [['Booking #'.$booking_id, (string) $amount_kurus, 1]];
        $user_basket = base64_encode(json_encode($basket, JSON_UNESCAPED_UNICODE));
        $payload = [
            'merchant_id'      => \MHMRentiva\Admin\Payment\Gateways\PayTR\Config::merchantId(),
            'merchant_key'     => \MHMRentiva\Admin\Payment\Gateways\PayTR\Config::merchantKey(),
            'merchant_salt'    => \MHMRentiva\Admin\Payment\Gateways\PayTR\Config::merchantSalt(),
            'email'            => (string) $email,
            'user_name'        => (string) $name,
            'user_address'     => '',
            'user_phone'       => '',
            'merchant_oid'     => $oid,
            'payment_amount'   => $amount_kurus,
            'user_basket'      => $user_basket,
            'currency'         => 'TL',
            'test_mode'        => \MHMRentiva\Admin\Payment\Gateways\PayTR\Config::testMode() ? 1 : 0,
            'non_3d'           => 0,
            'merchant_ok_url'  => home_url('/'),
            'merchant_fail_url'=> home_url('/'),
            'ip'               => $ip,
        ];
        $client = new PayTRClient();
        try {
            // Assuming createToken exists like in REST\PayTR flow
            $method = method_exists($client, 'createToken') ? 'createToken' : null;
            if ($method) {
                $res = $client->createToken($payload);
                if (is_array($res) && !empty($res['ok']) && !empty($res['iframe_url'])) {
                    update_post_meta($booking_id, '_mhm_paytr_last_iframe_url', (string) $res['iframe_url']);
                    update_post_meta($booking_id, '_mhm_paytr_last_token_ts', time());
                    Logger::add([
                        'gateway'    => 'paytr',
                        'action'     => 'token_retry',
                        'status'     => 'success',
                        'booking_id' => $booking_id,
                        'message'    => __('Token was previously created and stored.', 'mhm-rentiva'),
                    ]);
                } else {
                    Logger::add([
                        'gateway'    => 'paytr',
                        'action'     => 'token_retry',
                        'status'     => 'error',
                        'booking_id' => $booking_id,
                        'message'    => (string) ($res['message'] ?? __('Token retry failed', 'mhm-rentiva')),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Logger::add([
                'gateway'    => 'paytr',
                'action'     => 'token_retry',
                'status'     => 'error',
                'booking_id' => $booking_id,
                'message'    => $e->getMessage(),
            ]);
        }
    }

    private static function cleanup_orphans(int $limit): void
    {
        // Logs with OID but no booking_id (PayTR)
        $q = new WP_Query([
            'post_type'      => PostType::TYPE,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_mhm_log_gateway',
                    'value'   => 'paytr',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_log_oid',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_mhm_log_booking_id',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);
        if (!$q->have_posts()) return;
        foreach ($q->posts as $lid) {
            $oid = (string) get_post_meta($lid, '_mhm_log_oid', true);
            if ($oid === '') continue;
            Logger::add([
                'gateway' => 'system',
                'action'  => 'orphan_detected',
                'status'  => 'error',
                /* translators: %s placeholder. */
                'message' => sprintf(__('Record with OID but no booking: %s', 'mhm-rentiva'), $oid),
                'context' => ['log_id' => (int) $lid],
            ]);
        }
        wp_reset_postdata();
    }

    private static function detect_inconsistent(int $limit): void
    {
        // Booking marked paid but missing amount metadata
        $q = new WP_Query([
            'post_type'      => 'vehicle_booking',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_mhm_payment_status',
                    'value'   => 'paid',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_payment_amount',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);
        if (!$q->have_posts()) return;
        foreach ($q->posts as $bid) {
            Logger::add([
                'gateway'    => 'system',
                'action'     => 'inconsistent_meta',
                'status'     => 'error',
                'booking_id' => (int) $bid,
                'message'    => __('Booking is marked paid but _mhm_payment_amount is missing', 'mhm-rentiva'),
            ]);
        }
        wp_reset_postdata();
    }
}
