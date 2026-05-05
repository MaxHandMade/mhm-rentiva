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
		<?php
		// v4.37.3: VendorAvatarFallback may resolve to a `data:image/svg+xml`
		// URI (deterministic initials fallback for vendors without a custom
		// avatar and without a real Gravatar). esc_url() strips data: scheme
		// by default — WordPress whitelist excludes it — so the SVG would be
		// lost on output. Branch on the scheme: trust the SVG payload built
		// by our own class with esc_attr() (no remote content, no user
		// input), keep esc_url() for everything else.
		//
		// PHPCS lint fix from v4.37.2: the previous variant placed `<?php`
		// on the same line as a multi-statement body, which trips
		// Generic.PHP.LowerCaseKeyword's companion sniff "Opening PHP tag
		// must be on a line by itself" in CI.
		$mhm_avatar_url    = (string) ( $data['avatar_url'] ?? '' );
		$mhm_is_inline_svg = strpos($mhm_avatar_url, 'data:image/svg+xml') === 0;
		?>
		<?php if ($mhm_avatar_url !== '') : ?>
			<img src="<?php echo $mhm_is_inline_svg ? esc_attr($mhm_avatar_url) : esc_url($mhm_avatar_url); ?>" alt="<?php echo esc_attr($data['display_name']); ?>" />
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
	<?php
	// v4.37.2: hero CTA stack — primary "View vehicles" anchor (#mhm-vendor-vehicles)
	// always, secondary "Send message" only if a vendor messaging surface is wired
	// on this site (filter returns a non-empty URL). Filters expose the destination
	// + label so site owners can swap to an external booking link or custom CPT
	// archive without overriding the partial.
	$hero_primary_url   = (string) apply_filters(
		'mhm_rentiva_vendor_profile_primary_cta_url',
		'#mhm-vendor-vehicles',
		$data['user_id']
	);
	$hero_primary_label = (string) apply_filters(
		'mhm_rentiva_vendor_profile_primary_cta_label',
		__('View vehicles', 'mhm-rentiva'),
		$data['user_id']
	);
	$hero_message_url   = (string) apply_filters(
		'mhm_rentiva_vendor_profile_message_url',
		'',
		$data['user_id']
	);
	?>
	<?php if ($hero_primary_url !== '' && $hero_primary_label !== '') : ?>
		<div class="mhm-vendor-hero-cta">
			<a class="mhm-vendor-hero-cta-primary" href="<?php echo esc_url($hero_primary_url); ?>">
				<?php echo esc_html($hero_primary_label); ?>
			</a>
			<?php if ($hero_message_url !== '') : ?>
				<a class="mhm-vendor-hero-cta-secondary" href="<?php echo esc_url($hero_message_url); ?>">
					<?php esc_html_e('Send message', 'mhm-rentiva'); ?>
				</a>
			<?php endif; ?>
		</div>
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
