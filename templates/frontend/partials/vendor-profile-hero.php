<?php
/**
 * Vendor Profile — hero partial.
 *
 * Available from including template: $data, $atts.
 *
 * @var array<string,mixed>  $data
 * @var array<string,string> $atts
 */
if (!defined('ABSPATH')) {
    exit;
}

$badge_status = $data['badge_status'] ?? 'none';
$rating       = $data['rating'];
?>
<div class="mhm-vendor-hero">
	<div class="mhm-vendor-avatar">
		<?php if (!empty($data['avatar_url'])) : ?>
			<img src="<?php echo esc_url($data['avatar_url']); ?>" alt="<?php echo esc_attr($data['display_name']); ?>" />
		<?php endif; ?>
	</div>
	<h1 class="mhm-vendor-name"><?php echo esc_html($data['display_name']); ?></h1>
	<div class="mhm-vendor-meta">
		<?php if ($data['city'] !== '') : ?>
			<span class="mhm-vendor-meta-city">📍 <?php echo esc_html($data['city']); ?></span>
		<?php endif; ?>
		<?php
		$approved_ts = $data['approved_at'] !== '' ? strtotime($data['approved_at']) : false;
		?>
		<?php if ($approved_ts !== false) : ?>
			<span class="mhm-vendor-meta-since">
				<?php
				/* translators: %s: 4-digit year */
				printf(esc_html__('Member %s', 'mhm-rentiva'), esc_html(gmdate('Y', $approved_ts)));
				?>
			</span>
		<?php endif; ?>
	</div>
	<?php if ($atts['show_badge'] === 'yes') : ?>
		<?php if ($badge_status === 'verified') : ?>
			<span class="mhm-vendor-badge mhm-vendor-badge--verified">✓ <?php esc_html_e('Verified Vendor', 'mhm-rentiva'); ?></span>
		<?php elseif ($badge_status === 'new') : ?>
			<span class="mhm-vendor-badge mhm-vendor-badge--new"><?php esc_html_e('New Vendor', 'mhm-rentiva'); ?></span>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ($atts['show_rating'] === 'yes' && $rating['count'] > 0) : ?>
		<div class="mhm-vendor-rating">
			<span class="mhm-vendor-rating-stars" aria-label="<?php echo esc_attr(sprintf('%s / 5', number_format_i18n($rating['average'], 1))); ?>">
				<?php echo esc_html(\MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile::stars_html( (float) $rating['average'])); ?>
			</span>
			<span class="mhm-vendor-rating-text">
				<?php echo esc_html(number_format_i18n($rating['average'], 1)); ?>
				<span class="mhm-vendor-rating-count">
					(
					<?php
					/* translators: %d: number of vehicle ratings (not WP review comments — this counts booking-time star ratings aggregated across the vendor's vehicles). */
					printf(esc_html(_n('%d rating', '%d ratings', $rating['count'], 'mhm-rentiva')), (int) $rating['count']);
					?>
					)
				</span>
			</span>
		</div>
	<?php endif; ?>
</div>
