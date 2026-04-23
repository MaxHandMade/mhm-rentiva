<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables.

use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;
use MHMRentiva\Admin\Vehicle\PenaltyCalculator;

if (! defined('ABSPATH')) {
	exit;
}

$vendor_id    = (int) get_current_user_id();
$score        = ReliabilityScoreCalculator::get($vendor_id);
$score_label  = ReliabilityScoreCalculator::get_label($score);
$score_color  = ReliabilityScoreCalculator::get_color($score);
$cancel_pts   = ReliabilityScoreCalculator::CANCEL_PENALTY;
$withdraw_pts = ReliabilityScoreCalculator::WITHDRAWAL_PENALTY;
$pause_pts    = ReliabilityScoreCalculator::PAUSE_PENALTY;
$complete_pts = ReliabilityScoreCalculator::COMPLETION_BONUS;
$max_bonus    = ReliabilityScoreCalculator::MAX_COMPLETION_BONUS;
$tier2_pct    = (int) round(PenaltyCalculator::TIER_2_RATE * 100);
$tier3_pct    = (int) round(PenaltyCalculator::TIER_3_RATE * 100);

// Score as percentage for the progress ring (circumference ≈ 251.2 for r=40).
$circumference = 251.2;
$stroke_offset = $circumference - ( $score / 100 ) * $circumference;
?>

<div class="mhm-vendor-reliability">

	<!-- ── Başlık ───────────────────────────────────────── -->
	<div class="mhm-rentiva-dashboard__header">
		<h2><?php esc_html_e('Reliability & Penalties', 'mhm-rentiva'); ?></h2>
		<p class="mhm-vendor-reliability__subtitle">
			<?php esc_html_e('Your performance score and the penalty system that keeps the marketplace fair for everyone.', 'mhm-rentiva'); ?>
		</p>
	</div>

	<!-- ── Puan Widget ──────────────────────────────────── -->
	<div class="mhm-vendor-reliability__score-card">
		<div class="mhm-vendor-reliability__score-ring-wrap">
			<svg class="mhm-vendor-reliability__ring" viewBox="0 0 100 100" aria-hidden="true">
				<!-- Track -->
				<circle
					cx="50" cy="50" r="40"
					fill="none"
					stroke="#e4e8f2"
					stroke-width="8"
				/>
				<!-- Progress -->
				<circle
					cx="50" cy="50" r="40"
					fill="none"
					stroke="<?php echo esc_attr($score_color); ?>"
					stroke-width="8"
					stroke-linecap="round"
					stroke-dasharray="<?php echo esc_attr( (string) $circumference); ?>"
					stroke-dashoffset="<?php echo esc_attr(number_format($stroke_offset, 2, '.', '')); ?>"
					transform="rotate(-90 50 50)"
				/>
			</svg>
			<div class="mhm-vendor-reliability__ring-inner">
				<span class="mhm-vendor-reliability__ring-score" style="color:<?php echo esc_attr($score_color); ?>">
					<?php echo esc_html( (string) $score); ?>
				</span>
				<span class="mhm-vendor-reliability__ring-max"><?php esc_html_e('/ 100', 'mhm-rentiva'); ?></span>
			</div>
		</div>

		<div class="mhm-vendor-reliability__score-info">
			<div class="mhm-vendor-reliability__score-badge" style="background:<?php echo esc_attr($score_color); ?>20;color:<?php echo esc_attr($score_color); ?>;border-color:<?php echo esc_attr($score_color); ?>40;">
				<?php echo esc_html($score_label); ?>
			</div>
			<h3 class="mhm-vendor-reliability__score-title">
				<?php esc_html_e('Your Reliability Score', 'mhm-rentiva'); ?>
			</h3>
			<p class="mhm-vendor-reliability__score-desc">
				<?php esc_html_e('Calculated daily from your booking completions, cancellations, withdrawals, and pauses over the last 6–12 months. A higher score signals trustworthiness to customers and to Rentiva.', 'mhm-rentiva'); ?>
			</p>

			<!-- Tier göstergesi -->
			<div class="mhm-vendor-reliability__tiers">
				<?php
				$tiers = array(
					array(
						'min'   => 90,
						'label' => __('Excellent', 'mhm-rentiva'),
						'color' => '#28a745',
					),
					array(
						'min'   => 70,
						'label' => __('Good', 'mhm-rentiva'),
						'color' => '#17a2b8',
					),
					array(
						'min'   => 50,
						'label' => __('Fair', 'mhm-rentiva'),
						'color' => '#ffc107',
					),
					array(
						'min'   => 0,
						'label' => __('Poor', 'mhm-rentiva'),
						'color' => '#dc3545',
					),
				);

				foreach ($tiers as $tier) :
					$is_active = $score >= $tier['min'] && (
						$tier['min'] === 0
							? $score < 50
							: ( $tier['min'] === 50 ? ( $score >= 50 && $score < 70 ) : ( $tier['min'] === 70 ? ( $score >= 70 && $score < 90 ) : $score >= 90 ) )
					);
					// Simplify: active = label matches score_label.
					$is_active = ( $score_label === $tier['label'] );
					?>
					<div class="mhm-vendor-reliability__tier <?php echo $is_active ? 'is-active' : ''; ?>" style="<?php echo $is_active ? 'border-color:' . esc_attr($tier['color']) . ';background:' . esc_attr($tier['color']) . '15' : ''; ?>">
						<span class="mhm-vendor-reliability__tier-dot" style="background:<?php echo esc_attr($tier['color']); ?>"></span>
						<span class="mhm-vendor-reliability__tier-label"><?php echo esc_html($tier['label']); ?></span>
						<span class="mhm-vendor-reliability__tier-range">
							<?php
							if ($tier['min'] === 90) {
								echo esc_html__('90–100', 'mhm-rentiva');
							} elseif ($tier['min'] === 70) {
								echo esc_html__('70–89', 'mhm-rentiva');
							} elseif ($tier['min'] === 50) {
								echo esc_html__('50–69', 'mhm-rentiva');
							} else {
								echo esc_html__('0–49', 'mhm-rentiva');
							}
							?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- ── Puan Tablosu ─────────────────────────────────── -->
	<div class="mhm-vendor-reliability__section">
		<h3 class="mhm-vendor-reliability__section-title">
			<?php esc_html_e('How Your Score Is Calculated', 'mhm-rentiva'); ?>
		</h3>
		<p class="mhm-vendor-reliability__section-desc">
			<?php esc_html_e('Your score starts at 100 and is adjusted based on your actions. Scores are clamped between 0 and 100.', 'mhm-rentiva'); ?>
		</p>

		<div class="mhm-vendor-reliability__table-wrap">
			<table class="mhm-vendor-reliability__table">
				<thead>
					<tr>
						<th><?php esc_html_e('Action', 'mhm-rentiva'); ?></th>
						<th><?php esc_html_e('Window', 'mhm-rentiva'); ?></th>
						<th><?php esc_html_e('Effect', 'mhm-rentiva'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="is-bonus">
						<td>
							<span class="mhm-vendor-reliability__tag is-green"><?php esc_html_e('Bonus', 'mhm-rentiva'); ?></span>
							<?php esc_html_e('Completed booking', 'mhm-rentiva'); ?>
						</td>
						<td><?php esc_html_e('Last 6 months', 'mhm-rentiva'); ?></td>
						<td>
							<strong class="is-green">
								<?php
								/* translators: %d: points per completed booking */
								echo esc_html( sprintf( __( '+%1$d pts each (max +%2$d)', 'mhm-rentiva' ), $complete_pts, $max_bonus ) );
								?>
							</strong>
						</td>
					</tr>
					<tr class="is-penalty">
						<td>
							<span class="mhm-vendor-reliability__tag is-red"><?php esc_html_e('Penalty', 'mhm-rentiva'); ?></span>
							<?php esc_html_e('Vendor-initiated cancellation', 'mhm-rentiva'); ?>
						</td>
						<td><?php esc_html_e('Last 6 months', 'mhm-rentiva'); ?></td>
						<td>
							<strong class="is-red">
								<?php
								/* translators: %d: points deducted per cancellation */
								echo esc_html( sprintf( __( '-%d pts each', 'mhm-rentiva' ), $cancel_pts ) );
								?>
							</strong>
						</td>
					</tr>
					<tr class="is-penalty">
						<td>
							<span class="mhm-vendor-reliability__tag is-red"><?php esc_html_e('Penalty', 'mhm-rentiva'); ?></span>
							<?php esc_html_e('Vehicle withdrawal', 'mhm-rentiva'); ?>
						</td>
						<td><?php esc_html_e('Last 12 months', 'mhm-rentiva'); ?></td>
						<td>
							<strong class="is-red">
								<?php
								/* translators: %d: points deducted per withdrawal */
								echo esc_html( sprintf( __( '-%d pts each', 'mhm-rentiva' ), $withdraw_pts ) );
								?>
							</strong>
						</td>
					</tr>
					<tr class="is-penalty">
						<td>
							<span class="mhm-vendor-reliability__tag is-orange"><?php esc_html_e('Minor', 'mhm-rentiva'); ?></span>
							<?php esc_html_e('Vehicle pause', 'mhm-rentiva'); ?>
						</td>
						<td><?php esc_html_e('Last 6 months', 'mhm-rentiva'); ?></td>
						<td>
							<strong class="is-orange">
								<?php
								/* translators: %d: points deducted per pause */
								echo esc_html( sprintf( __( '-%d pts each', 'mhm-rentiva' ), $pause_pts ) );
								?>
							</strong>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- ── Ceza Kademeleri ──────────────────────────────── -->
	<div class="mhm-vendor-reliability__section">
		<h3 class="mhm-vendor-reliability__section-title">
			<?php esc_html_e('Withdrawal Penalty Tiers', 'mhm-rentiva'); ?>
		</h3>
		<p class="mhm-vendor-reliability__section-desc">
			<?php esc_html_e('Repeated vehicle withdrawals carry financial penalties based on your rolling 12-month history. Penalties are deducted from your next payout.', 'mhm-rentiva'); ?>
		</p>

		<div class="mhm-vendor-reliability__penalty-cards">
			<div class="mhm-vendor-reliability__penalty-card is-free">
				<div class="mhm-vendor-reliability__penalty-number">1</div>
				<div class="mhm-vendor-reliability__penalty-body">
					<div class="mhm-vendor-reliability__penalty-title"><?php esc_html_e('1st Withdrawal', 'mhm-rentiva'); ?></div>
					<div class="mhm-vendor-reliability__penalty-amount is-green"><?php esc_html_e('Free', 'mhm-rentiva'); ?></div>
					<div class="mhm-vendor-reliability__penalty-note"><?php esc_html_e('No financial penalty for your first withdrawal in any 12-month window.', 'mhm-rentiva'); ?></div>
				</div>
			</div>

			<div class="mhm-vendor-reliability__penalty-card is-moderate">
				<div class="mhm-vendor-reliability__penalty-number">2</div>
				<div class="mhm-vendor-reliability__penalty-body">
					<div class="mhm-vendor-reliability__penalty-title"><?php esc_html_e('2nd Withdrawal', 'mhm-rentiva'); ?></div>
					<div class="mhm-vendor-reliability__penalty-amount is-orange">
						<?php
						/* translators: %d: penalty percentage */
						echo esc_html( sprintf( __( '%d%% of monthly avg. revenue', 'mhm-rentiva' ), $tier2_pct ) );
						?>
					</div>
					<div class="mhm-vendor-reliability__penalty-note"><?php esc_html_e('Deducted from your ledger balance at time of withdrawal.', 'mhm-rentiva'); ?></div>
				</div>
			</div>

			<div class="mhm-vendor-reliability__penalty-card is-severe">
				<div class="mhm-vendor-reliability__penalty-number">3+</div>
				<div class="mhm-vendor-reliability__penalty-body">
					<div class="mhm-vendor-reliability__penalty-title"><?php esc_html_e('3rd+ Withdrawal', 'mhm-rentiva'); ?></div>
					<div class="mhm-vendor-reliability__penalty-amount is-red">
						<?php
						/* translators: %d: penalty percentage */
						echo esc_html( sprintf( __( '%d%% of monthly avg. revenue', 'mhm-rentiva' ), $tier3_pct ) );
						?>
					</div>
					<div class="mhm-vendor-reliability__penalty-note"><?php esc_html_e('Each subsequent withdrawal within the 12-month window carries this rate.', 'mhm-rentiva'); ?></div>
				</div>
			</div>
		</div>

		<p class="mhm-vendor-reliability__penalty-footnote">
			<?php
			/* translators: %d: rolling window in months */
			echo esc_html( sprintf( __( 'The withdrawal counter resets after %d months of no withdrawals.', 'mhm-rentiva' ), PenaltyCalculator::ROLLING_WINDOW_MONTHS ) );
			?>
		</p>
	</div>

	<!-- ── Neden Var? ───────────────────────────────────── -->
	<div class="mhm-vendor-reliability__section mhm-vendor-reliability__why">
		<h3 class="mhm-vendor-reliability__section-title">
			<?php esc_html_e('Why Does This System Exist?', 'mhm-rentiva'); ?>
		</h3>

		<div class="mhm-vendor-reliability__why-grid">
			<div class="mhm-vendor-reliability__why-card">
				<div class="mhm-vendor-reliability__why-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none">
						<path d="M12 3L4 7V12C4 16.4183 7.58172 20.3345 12 21C16.4183 20.3345 20 16.4183 20 12V7L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
						<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<h4><?php esc_html_e('Customer Trust', 'mhm-rentiva'); ?></h4>
				<p><?php esc_html_e('Customers rely on vehicles being available on the dates they book. Sudden withdrawals or cancellations erode trust in the entire marketplace.', 'mhm-rentiva'); ?></p>
			</div>

			<div class="mhm-vendor-reliability__why-card">
				<div class="mhm-vendor-reliability__why-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none">
						<path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="currentColor" stroke-width="1.5"/>
						<path d="M12 7V12L15 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</div>
				<h4><?php esc_html_e('Fair Competition', 'mhm-rentiva'); ?></h4>
				<p><?php esc_html_e('Vendors who consistently deliver receive better visibility. The score ensures that reliable vendors are rewarded and unreliable behaviour carries a proportionate cost.', 'mhm-rentiva'); ?></p>
			</div>

			<div class="mhm-vendor-reliability__why-card">
				<div class="mhm-vendor-reliability__why-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none">
						<path d="M7.5 11L12 5L16.5 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M7.5 17L12 11L16.5 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<h4><?php esc_html_e('Your Growth', 'mhm-rentiva'); ?></h4>
				<p><?php esc_html_e('A high score is your reputation on Rentiva. It signals to customers that you are a dependable partner, which translates directly into more bookings and revenue.', 'mhm-rentiva'); ?></p>
			</div>
		</div>
	</div>

	<!-- ── Puan Geçmişi ─────────────────────────────────── -->
	<?php
	$raw_history = get_user_meta($vendor_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_SCORE_HISTORY, true);
	$history     = is_array($raw_history) ? $raw_history : array();

	$event_labels = array(
		'pause'    => __('Vehicle paused', 'mhm-rentiva'),
		'withdraw' => __('Vehicle withdrawn', 'mhm-rentiva'),
		'cancel'   => __('Booking cancelled', 'mhm-rentiva'),
		'complete' => __('Booking completed', 'mhm-rentiva'),
		'cron'     => __('Daily recalculation', 'mhm-rentiva'),
	);

	$event_icons = array(
		'pause'    => 'pause',
		'withdraw' => 'remove',
		'cancel'   => 'cancel',
		'complete' => 'complete',
		'cron'     => 'cron',
	);
	?>

	<div class="mhm-vendor-reliability__section">
		<h3 class="mhm-vendor-reliability__section-title">
			<?php esc_html_e('Score History', 'mhm-rentiva'); ?>
		</h3>
		<p class="mhm-vendor-reliability__section-desc">
			<?php esc_html_e('A log of every event that affected your reliability score. Up to 50 entries are kept.', 'mhm-rentiva'); ?>
		</p>

		<?php if (empty($history)) : ?>
			<div class="mhm-vendor-reliability__history-empty">
				<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<p><?php esc_html_e('No score events recorded yet. Events appear here when your score changes.', 'mhm-rentiva'); ?></p>
			</div>
		<?php else : ?>
			<div class="mhm-vendor-reliability__table-wrap">
				<table class="mhm-vendor-reliability__table mhm-vendor-reliability__history-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Date', 'mhm-rentiva'); ?></th>
							<th><?php esc_html_e('Event', 'mhm-rentiva'); ?></th>
							<th><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></th>
							<th><?php esc_html_e('Change', 'mhm-rentiva'); ?></th>
							<th><?php esc_html_e('Score', 'mhm-rentiva'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
                        foreach ($history as $entry) :
							$event_type    = sanitize_key( (string) ( $entry['event_type'] ?? 'cron' ));
							$event_label   = $event_labels[ $event_type ] ?? ucfirst($event_type);
							$vehicle_title = (string) ( $entry['vehicle_title'] ?? '' );
							$vehicle_id_e  = (int) ( $entry['vehicle_id'] ?? 0 );
							$delta         = (int) ( $entry['delta'] ?? 0 );
							$score_after   = (int) ( $entry['score_after'] ?? 0 );
							$ts            = (string) ( $entry['ts'] ?? '' );
							$date_fmt      = $ts ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ts)) : '—';

							$is_positive = $delta > 0;
							$is_negative = $delta < 0;
							$delta_class = $is_positive ? 'is-green' : ( $is_negative ? 'is-red' : 'is-neutral' );
							$delta_sign  = $is_positive ? '+' : '';
							?>
							<tr>
								<td class="mhm-vendor-reliability__history-date"><?php echo esc_html($date_fmt); ?></td>
								<td>
									<span class="mhm-vendor-reliability__history-event is-<?php echo esc_attr($event_type); ?>">
										<?php echo esc_html($event_label); ?>
									</span>
								</td>
								<td class="mhm-vendor-reliability__history-vehicle">
									<?php if ($vehicle_id_e > 0 && $vehicle_title !== '') : ?>
										<?php echo esc_html($vehicle_title); ?>
									<?php else : ?>
										<span class="mhm-vendor-reliability__history-na">—</span>
									<?php endif; ?>
								</td>
								<td>
									<strong class="<?php echo esc_attr($delta_class); ?>">
										<?php echo esc_html($delta_sign . $delta . ' ' . __('pts', 'mhm-rentiva')); ?>
									</strong>
								</td>
								<td class="mhm-vendor-reliability__history-score">
									<?php echo esc_html( (string) $score_after); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

</div><!-- .mhm-vendor-reliability -->
