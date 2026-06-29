<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Renders a payout statement as printable, self-contained HTML.
 */
final class PayoutStatementRenderer {

	public static function render(array $statement): string
	{
		$cur   = (string) ( $statement['currency'] ?? 'TRY' );
		$snap  = (array) ( $statement['vendor_snapshot'] ?? array() );
		$brand = PayoutStatementBranding::get();

		$money = static function ($n) use ($cur): string {
			return esc_html(number_format_i18n( (float) $n, 2) . ' ' . $cur);
		};

		ob_start();
		?>
		<div class="mhm-statement" style="max-width:760px;margin:0 auto;font-family:Arial,sans-serif;color:#1d2327;">
			<div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1d2327;padding-bottom:12px;">
				<div>
					<?php if ($brand['logo_url'] !== '') : ?>
						<img src="<?php echo esc_url($brand['logo_url']); ?>" alt="" style="max-height:60px;margin-bottom:6px;display:block;">
					<?php endif; ?>
					<h2 style="margin:0;"><?php echo esc_html($brand['company_name']); ?></h2>
					<?php if ($brand['address'] !== '') : ?>
						<div style="color:#646970;font-size:12px;"><?php echo nl2br(esc_html($brand['address'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html before nl2br. ?></div>
					<?php endif; ?>
					<?php
					$op_meta = array_filter(array(
						$brand['tax_office'] !== '' ? __('Tax office', 'mhm-rentiva') . ': ' . $brand['tax_office'] : '',
						$brand['tax_number'] !== '' ? __('Tax no', 'mhm-rentiva') . ': ' . $brand['tax_number'] : '',
						$brand['phone'] !== '' ? $brand['phone'] : '',
						$brand['email'] !== '' ? $brand['email'] : '',
					));
					?>
					<?php if (! empty($op_meta)) : ?>
						<div style="color:#646970;font-size:12px;"><?php echo esc_html(implode(' · ', $op_meta)); ?></div>
					<?php endif; ?>
					<div style="color:#646970;margin-top:4px;"><?php esc_html_e('Vendor Payout Statement', 'mhm-rentiva'); ?></div>
				</div>
				<div style="text-align:right;">
					<div><strong><?php echo esc_html( (string) ( $statement['number'] ?? '' )); ?></strong></div>
					<div style="color:#646970;"><?php echo esc_html( (string) ( $statement['generated_at'] ?? '' )); ?> UTC</div>
				</div>
			</div>

			<div style="margin:16px 0;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;">
				<strong><?php echo esc_html( (string) ( $snap['name'] ?? '' )); ?></strong><br>
				<?php esc_html_e('Tax office', 'mhm-rentiva'); ?>: <?php echo esc_html( (string) ( $snap['tax_office'] ?? '' )); ?> ·
				<?php esc_html_e('Tax no', 'mhm-rentiva'); ?>: <?php echo esc_html( (string) ( $snap['tax_number'] ?? '' )); ?><br>
				<?php esc_html_e('Account holder', 'mhm-rentiva'); ?>: <?php echo esc_html( (string) ( $snap['account_holder'] ?? '' )); ?><br>
				<?php esc_html_e('IBAN', 'mhm-rentiva'); ?>: <?php echo esc_html( (string) ( $snap['iban'] ?? '' )); ?>
			</div>

			<div style="color:#646970;margin-bottom:8px;">
				<?php esc_html_e('Period', 'mhm-rentiva'); ?>:
				<?php echo esc_html( (string) ( $statement['period_start'] ?? '—' )); ?> –
				<?php echo esc_html( (string) ( $statement['period_end'] ?? '—' )); ?>
			</div>

			<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
				<thead>
					<tr style="background:#f0f0f1;text-align:left;">
						<th style="padding:6px;border:1px solid #dcdcde;"><?php esc_html_e('Date', 'mhm-rentiva'); ?></th>
						<th style="padding:6px;border:1px solid #dcdcde;"><?php esc_html_e('Description', 'mhm-rentiva'); ?></th>
						<th style="padding:6px;border:1px solid #dcdcde;text-align:right;"><?php esc_html_e('Amount', 'mhm-rentiva'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($statement['lines'])) : ?>
						<tr><td colspan="3" style="padding:8px;border:1px solid #dcdcde;color:#646970;"><?php esc_html_e('No activity in this period.', 'mhm-rentiva'); ?></td></tr>
						<?php
                    else :
						foreach ( (array) $statement['lines'] as $line) :
							?>
						<tr>
							<td style="padding:6px;border:1px solid #dcdcde;"><?php echo esc_html( (string) ( $line['date'] ?? '' )); ?></td>
							<td style="padding:6px;border:1px solid #dcdcde;"><?php echo esc_html( (string) ( $line['description'] ?? '' )); ?></td>
							<td style="padding:6px;border:1px solid #dcdcde;text-align:right;"><?php echo $money($line['amount'] ?? 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
											<?php
                    endforeach;
endif;
					?>
				</tbody>
			</table>

			<table style="width:320px;margin-left:auto;border-collapse:collapse;">
				<tr><td style="padding:4px;"><?php esc_html_e('Period earnings', 'mhm-rentiva'); ?></td><td style="padding:4px;text-align:right;"><?php echo $money($statement['gross'] ?? 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
				<tr><td style="padding:4px;"><?php esc_html_e('Period penalties', 'mhm-rentiva'); ?></td><td style="padding:4px;text-align:right;">- <?php echo $money($statement['penalties'] ?? 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
				<tr style="border-top:1px solid #dcdcde;"><td style="padding:4px;"><strong><?php esc_html_e('Period net', 'mhm-rentiva'); ?></strong></td><td style="padding:4px;text-align:right;"><strong><?php echo $money($statement['net_activity'] ?? 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong></td></tr>
				<tr style="border-top:2px solid #1d2327;"><td style="padding:4px;"><strong><?php esc_html_e('Amount paid', 'mhm-rentiva'); ?></strong></td><td style="padding:4px;text-align:right;"><strong><?php echo $money($statement['paid'] ?? 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong></td></tr>
				<tr><td style="padding:4px;color:#646970;"><?php esc_html_e('Carried balance', 'mhm-rentiva'); ?></td><td style="padding:4px;text-align:right;color:#646970;"><?php echo $money($statement['carried_balance'] ?? 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
			</table>

			<?php if ($brand['footer_note'] !== '') : ?>
				<p style="margin-top:24px;color:#1d2327;font-size:12px;border-top:1px solid #dcdcde;padding-top:8px;">
					<?php echo nl2br(esc_html($brand['footer_note'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html before nl2br. ?>
				</p>
				<p style="color:#646970;font-size:12px;">
					<?php esc_html_e('This is a payment statement, not an official invoice.', 'mhm-rentiva'); ?>
				</p>
			<?php else : ?>
				<p style="margin-top:24px;color:#646970;font-size:12px;border-top:1px solid #dcdcde;padding-top:8px;">
					<?php esc_html_e('This is a payment statement, not an official invoice.', 'mhm-rentiva'); ?>
				</p>
			<?php endif; ?>

			<p class="mhm-statement__noprint" style="text-align:right;">
				<button type="button" class="button" onclick="window.print()"><?php esc_html_e('Print / Save as PDF', 'mhm-rentiva'); ?></button>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
