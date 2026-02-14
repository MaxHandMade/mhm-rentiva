<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Shortcode Pages Admin Template
 *
 * @var array $pages Shortcode => Page ID mapping
 * @var array $shortcodes_config Shortcode configuration
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap mhm-rentiva-admin" id="mhm-shortcode-pages-container">
	<?php echo $header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
	?>
	<p class="description" id="shortcode-pages-desc">
		<?php esc_html_e('Below is a list of all MHM Rentiva shortcodes and which pages they are used on.', 'mhm-rentiva'); ?>
	</p>

	<div class="mhm-shortcode-pages-table-wrapper">
		<table class="wp-list-table widefat fixed striped" aria-describedby="shortcode-pages-desc">
			<thead>
				<tr>
					<th scope="col" id="col-shortcode"><?php esc_html_e('Shortcode', 'mhm-rentiva'); ?></th>
					<th scope="col" id="col-page"><?php esc_html_e('Page', 'mhm-rentiva'); ?></th>
					<th scope="col" id="col-url"><?php esc_html_e('URL', 'mhm-rentiva'); ?></th>
					<th scope="col" id="col-status"><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
					<th scope="col" id="col-actions"><?php esc_html_e('Actions', 'mhm-rentiva'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($shortcodes_config as $shortcode => $info) :
					$page_data = $pages[$shortcode] ?? null;
					$page_id   = $page_data['id'] ?? null;
					$url       = $page_data['url'] ?? '';
					$has_url   = $page_data['has_url'] ?? false;
					$page      = $page_id ? get_post($page_id) : null;
				?>
					<tr>
						<td>
							<code class="mhm-shortcode-tag"><?php echo esc_html($shortcode); ?></code>
							<br>
							<small class="mhm-shortcode-title"><?php echo esc_html($info['title']); ?></small>
						</td>
						<td>
							<?php if ($page) : ?>
								<strong class="mhm-page-title"><?php echo esc_html($page->post_title); ?></strong>
								<br>
								<small class="mhm-page-id"><?php esc_html_e('ID:', 'mhm-rentiva'); ?> <?php echo (int) $page_id; ?></small>
							<?php elseif ($has_url) : ?>
								<strong class="mhm-page-title" style="color: #0073aa;"><?php esc_html_e('Dynamic / Custom URL', 'mhm-rentiva'); ?></strong>
								<br>
								<small class="mhm-page-id"><?php esc_html_e('Managed by WooCommerce/Settings', 'mhm-rentiva'); ?></small>
							<?php else : ?>
								<span class="mhm-status-missing"><?php esc_html_e('Page not found', 'mhm-rentiva'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($has_url) : ?>
								<a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html($url); ?>
								</a>
							<?php else : ?>
								<span class="mhm-status-missing">-</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($page || $has_url) : ?>
								<span class="mhm-status-ok" aria-label="<?php esc_attr_e('Active', 'mhm-rentiva'); ?>"><?php esc_html_e('Active', 'mhm-rentiva'); ?></span>
							<?php else : ?>
								<span class="mhm-status-missing" aria-label="<?php esc_attr_e('Missing', 'mhm-rentiva'); ?>"><?php esc_html_e('Missing', 'mhm-rentiva'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="mhm-action-buttons">
								<?php if ($page) : ?>
									<a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $page_id . '&action=edit')); ?>" class="button button-small" aria-label="<?php esc_attr_e('Edit Page', 'mhm-rentiva'); ?>">
										<?php esc_html_e('Edit', 'mhm-rentiva'); ?>
									</a>
									<a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank" class="button button-small" rel="noopener noreferrer">
										<?php esc_html_e('View', 'mhm-rentiva'); ?>
									</a>
									<button type="button" class="button button-small button-link-delete mhm-btn-delete-page"
										data-page-id="<?php echo (int) $page_id; ?>"
										data-title="<?php echo esc_attr($page->post_title); ?>">
										<?php esc_html_e('Remove', 'mhm-rentiva'); ?>
									</button>
								<?php elseif ($has_url) : ?>
									<a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-small" rel="noopener noreferrer">
										<?php esc_html_e('View', 'mhm-rentiva'); ?>
									</a>
									<button type="button" class="button button-primary button-small mhm-btn-create-page"
										data-shortcode="<?php echo esc_attr($shortcode); ?>">
										<?php esc_html_e('Create Page', 'mhm-rentiva'); ?>
									</button>
								<?php else : ?>
									<button type="button" class="button button-primary button-small mhm-btn-create-page"
										data-shortcode="<?php echo esc_attr($shortcode); ?>">
										<?php esc_html_e('Create Page', 'mhm-rentiva'); ?>
									</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php
	$total_count   = count($shortcodes_config);
	$active_count  = 0;
	$missing_count = 0;

	foreach ($shortcodes_config as $shortcode => $info) {
		$has_url = $pages[$shortcode]['has_url'] ?? false;
		if ((isset($pages[$shortcode]['id']) && $pages[$shortcode]['id'] > 0) || $has_url) {
			$active_count++;
		} else {
			$missing_count++;
		}
	}
	?>

	<div class="mhm-rentiva-stats-container">
		<h3><?php esc_html_e('System Summary', 'mhm-rentiva'); ?></h3>
		<div class="mhm-stats-grid">
			<div class="card">
				<p><?php esc_html_e('Total Shortcodes', 'mhm-rentiva'); ?></p>
				<h4><?php echo (int) $total_count; ?></h4>
			</div>
			<div class="card">
				<p><?php esc_html_e('Active Pages', 'mhm-rentiva'); ?></p>
				<h4 style="color: #46b450;"><?php echo (int) $active_count; ?></h4>
			</div>
			<div class="card">
				<p><?php esc_html_e('Missing Pages', 'mhm-rentiva'); ?></p>
				<h4 style="color: #dc3232;"><?php echo (int) $missing_count; ?></h4>
			</div>
		</div>
	</div>

	<div class="mhm-shortcode-actions">
		<h3><?php esc_html_e('System Actions', 'mhm-rentiva'); ?></h3>
		<div class="mhm-action-group">
			<button type="button" class="button" id="mhm-btn-clear-cache">
				<?php esc_html_e('Clear Cache', 'mhm-rentiva'); ?>
			</button>
			<button type="button" class="button" id="mhm-btn-debug-search">
				<?php esc_html_e('Debug Search', 'mhm-rentiva'); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e('Clears shortcode page cache and performs a deep scan for shortcode placements. Use after manual page updates.', 'mhm-rentiva'); ?>
		</p>
	</div>
</div>