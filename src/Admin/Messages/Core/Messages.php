<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Core;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Messaging core coordinates bounded mailbox/thread queries.



use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Core\Utilities\ErrorHandler;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Messages\Admin\MessageListTable;
use MHMRentiva\Admin\Messages\Settings\MessagesSettings;
use MHMRentiva\Admin\Messages\Core\MessageQueryHelper;
use MHMRentiva\Admin\Messages\Core\MessageCache;
use MHMRentiva\Admin\Messages\Core\MessageUrlHelper;



final class Messages {


	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	public static function register(): void
	{
		add_action('wp_dashboard_setup', array( self::class, 'add_dashboard_widget' ));

		// AJAX handlers
		add_action('wp_ajax_mhm_messages_bulk_action', array( self::class, 'handle_bulk_actions' ));
		add_action('wp_ajax_mhm_message_reply', array( self::class, 'ajax_reply_to_message' ));
		add_action('wp_ajax_mhm_message_status_update', array( self::class, 'ajax_update_status' ));
		add_action('wp_ajax_mhm_message_reopen', array( self::class, 'ajax_reopen_message' ));

		// Form handlers
		add_action('admin_init', array( self::class, 'handle_new_message_form' ));
		add_action('admin_init', array( self::class, 'handle_edit_message_form' ));

		// Add hooks for messages page
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_message_scripts' ));

		// Load frontend assets
		add_action('wp_enqueue_scripts', array( self::class, 'enqueue_frontend_assets' ));

		// Initialize MessagesSettings
		MessagesSettings::init();
	}

	public static function add_dashboard_widget(): void
	{
		// Check dashboard widget setting
		if (! MessagesSettings::get_setting('dashboard_widget_enabled', true)) {
			return;
		}

		wp_add_dashboard_widget(
			'mhm_messages_widget',
			__('Pending Messages', 'mhm-rentiva'),
			array( self::class, 'render_dashboard_widget' )
		);
	}

	public static function render_dashboard_widget(): void
	{
		// Get optimized dashboard data
		$dashboard_data = MessageQueryHelper::get_message_stats();

		// Recent pending messages (number from settings)
		$max_messages    = MessagesSettings::get_setting('dashboard_widget_max_messages', 5);
		$recent_messages = MessageQueryHelper::get_messages_query(
			array(
				'posts_per_page' => $max_messages,
				'status_filter'  => 'pending',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		)['messages'];

		$pending_count = (int) $dashboard_data['pending_messages'];
		$badge_bg      = $pending_count > 0 ? '#e74c3c' : '#27ae60';
		?>
		<style>
			.mhm-msg-widget { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
			.mhm-msg-widget__header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
			.mhm-msg-widget__badge { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; border-radius: 10px; font-size: 18px; font-weight: 700; color: #fff; padding: 0 10px; }
			.mhm-msg-widget__header-text { font-size: 13px; color: #6b7280; line-height: 1.3; }
			.mhm-msg-widget__header-text strong { display: block; font-size: 14px; color: #1f2937; }
			.mhm-msg-widget__list { margin: 0; padding: 0; list-style: none; }
			.mhm-msg-widget__item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; margin-bottom: 6px; border-radius: 8px; background: #f9fafb; border: 1px solid #f3f4f6; transition: all 0.15s ease; text-decoration: none; color: inherit; }
			.mhm-msg-widget__item:hover { background: #eff6ff; border-color: #bfdbfe; }
			.mhm-msg-widget__avatar { flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; }
			.mhm-msg-widget__content { flex: 1; min-width: 0; }
			.mhm-msg-widget__sender { font-size: 13px; font-weight: 600; color: #1f2937; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.mhm-msg-widget__subject { font-size: 12px; color: #6b7280; margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.mhm-msg-widget__time { flex-shrink: 0; font-size: 11px; color: #9ca3af; white-space: nowrap; margin-top: 2px; }
			.mhm-msg-widget__empty { text-align: center; padding: 20px 10px; color: #9ca3af; font-size: 13px; }
			.mhm-msg-widget__empty .dashicons { font-size: 32px; width: 32px; height: 32px; display: block; margin: 0 auto 8px; color: #d1d5db; }
			.mhm-msg-widget__footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: center; }
			.mhm-msg-widget__footer a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; background: #2563eb; color: #fff; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; transition: background 0.15s; }
			.mhm-msg-widget__footer a:hover { background: #1d4ed8; color: #fff; }
		</style>
		<div class="mhm-msg-widget">
			<div class="mhm-msg-widget__header">
				<span class="mhm-msg-widget__badge" style="background:<?php echo esc_attr( $badge_bg ); ?>;">
					<?php echo esc_html( $pending_count ); ?>
				</span>
				<div class="mhm-msg-widget__header-text">
					<?php /* translators: %d: number of pending messages awaiting reply */ ?>
					<strong><?php echo esc_html( sprintf( _n( '%d Pending Message', '%d Pending Messages', $pending_count, 'mhm-rentiva' ), $pending_count ) ); ?></strong>
					<?php if ( $pending_count > 0 ) : ?>
						<?php esc_html_e( 'Awaiting your response', 'mhm-rentiva' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'All caught up!', 'mhm-rentiva' ); ?>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $recent_messages ) ) : ?>
				<ul class="mhm-msg-widget__list">
					<?php
                    foreach ( $recent_messages as $message ) :
						$name    = $message->customer_name ?: __( 'Unknown', 'mhm-rentiva' );
						$initial = mb_strtoupper( mb_substr( $name, 0, 1 ) );
						$url     = MessageUrlHelper::get_message_view_url( (int) $message->ID );
						$ago     = human_time_diff( strtotime( $message->post_date ), current_time( 'timestamp' ) );
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="mhm-msg-widget__item">
							<span class="mhm-msg-widget__avatar"><?php echo esc_html( $initial ); ?></span>
							<div class="mhm-msg-widget__content">
								<p class="mhm-msg-widget__sender"><?php echo esc_html( $name ); ?></p>
								<p class="mhm-msg-widget__subject"><?php echo esc_html( $message->post_title ); ?></p>
							</div>
							<span class="mhm-msg-widget__time"><?php echo esc_html( $ago ); ?></span>
						</a>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="mhm-msg-widget__empty">
					<span class="dashicons dashicons-email-alt"></span>
					<?php esc_html_e( 'No pending messages.', 'mhm-rentiva' ); ?>
				</div>
			<?php endif; ?>

			<div class="mhm-msg-widget__footer">
				<a href="<?php echo esc_url( MessageUrlHelper::get_messages_list_url() ); ?>">
					<span class="dashicons dashicons-email" style="font-size:16px;width:16px;height:16px;"></span>
					<?php esc_html_e( 'View All Messages', 'mhm-rentiva' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private static function render_messages_list(): void
	{
		// Always clear cache (for parent_only filter)
		MessageCache::flush();

		// Also clear WordPress object cache
		if (function_exists('wp_cache_flush_group')) {
			wp_cache_flush_group('mhm_messages');
		}

		$messages_table = new MessageListTable();
		$messages_table->prepare_items();

		?>
		<form method="post" action="">
			<input type="hidden" name="page" value="mhm-rentiva-messages">
			<?php wp_nonce_field('mhm_messages_bulk_action', 'mhm_messages_nonce'); ?>

			<?php $messages_table->display(); ?>
		</form>
		<?php
	}

	private static function render_message_thread(int $message_id): void
	{
		if (! $message_id) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Invalid message ID.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$message = get_post($message_id);
		if (! $message || $message->post_type !== Message::POST_TYPE) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Message not found.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$meta      = Message::get_message_meta($message_id);
		$thread_id = $meta['thread_id'] ?? $message_id;

		// Normalize thread_id to ensure proper matching
		$thread_id = (string) $thread_id;

		// Try to get thread messages - try multiple methods
		$thread_messages = Message::get_thread_messages($thread_id);

		// If still empty, try with message ID as thread_id
		if (empty($thread_messages)) {
			$thread_messages = Message::get_thread_messages( (string) $message_id);
		}

		// If thread is empty, at least show the main message
		if (empty($thread_messages)) {
			// Reload main message with all fields
			$main_message = get_post($message_id, ARRAY_A);
			if ($main_message) {
				// Convert to object
				$main_message_obj = (object) $main_message;
				// Ensure post_content is loaded
				if (empty($main_message_obj->post_content)) {
					$main_message_obj->post_content = get_post_field('post_content', $message_id);
				}
				// If still empty, try direct database query
				if (empty($main_message_obj->post_content)) {
					global $wpdb;
					$main_message_obj->post_content = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
							$message_id
						)
					);
				}
				$main_message_obj->meta = $meta;
				$thread_messages        = array( $main_message_obj );
			}
		} else {
			// Ensure all messages have content loaded
			foreach ($thread_messages as &$msg) {
				if ($msg && isset($msg->ID)) {
					if (empty($msg->post_content)) {
						$msg->post_content = get_post_field('post_content', $msg->ID);
					}
					// If still empty, try direct database query
					if (empty($msg->post_content)) {
						global $wpdb;
						$msg->post_content = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
								$msg->ID
							)
						);
					}
				}
			}
		}

		// Mark message as read
		if (! current_user_can('manage_options')) {
			Message::mark_as_read($message_id);
		}

		?>
		<div class="message-thread-header">
			<a href="<?php echo esc_url(MessageUrlHelper::get_messages_list_url()); ?>" class="button">
				← <?php esc_html_e('Back to All Messages', 'mhm-rentiva'); ?>
			</a>

			<div class="message-info">
				<h2><?php echo esc_html($message->post_title); ?></h2>
				<div class="message-meta">
					<span class="customer-name"><?php echo esc_html($meta['customer_name']); ?> (<?php echo esc_html($meta['customer_email']); ?>)</span>
					<span class="message-category"><?php echo esc_html(MessagesSettings::get_categories()[ $meta['category'] ] ?? $meta['category']); ?></span>
					<span class="message-priority priority-<?php echo esc_attr($meta['priority'] ?? 'normal'); ?>">
						<?php echo esc_html(MessagesSettings::get_priorities()[ $meta['priority'] ?? 'normal' ] ?? __('Normal', 'mhm-rentiva')); ?>
					</span>
					<span class="message-status status-<?php echo esc_attr($meta['status']); ?>">
						<?php echo esc_html(MessagesSettings::get_statuses()[ $meta['status'] ] ?? $meta['status']); ?>
					</span>
				</div>
			</div>

			<div class="message-actions">
				<select id="message-status-select" data-message-id="<?php echo esc_attr($message_id); ?>">
					<?php foreach (MessagesSettings::get_statuses() as $status_key => $status_label) : ?>
						<option value="<?php echo esc_attr($status_key); ?>" <?php selected($meta['status'], $status_key); ?>>
							<?php echo esc_html($status_label); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php if ($meta['status'] === 'closed') : ?>
					<button type="button" id="reopen-message-btn" class="button button-secondary" data-message-id="<?php echo esc_attr($message_id); ?>">
						<?php esc_html_e('Reopen Message', 'mhm-rentiva'); ?>
					</button>
					<span class="closed-notice" style="margin-left: 10px; color: #666; font-style: italic;">
						<?php esc_html_e('This conversation is closed.', 'mhm-rentiva'); ?>
					</span>
				<?php else : ?>
					<a href="<?php echo esc_url(MessageUrlHelper::get_message_reply_url($message_id)); ?>" class="button button-primary">
						<?php esc_html_e('Reply', 'mhm-rentiva'); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="message-thread">
			<?php
			$thread_count       = count($thread_messages);
			$last_message_index = $thread_count - 1;
			foreach ($thread_messages as $index => $thread_message) :
				// Skip if message is null or invalid
				if (! $thread_message || ! isset($thread_message->ID)) {
					continue;
				}

				// Ensure meta is set
				if (! isset($thread_message->meta)) {
					$thread_message->meta = Message::get_message_meta($thread_message->ID);
				}

				// Ensure post_content is loaded - try multiple methods
				$content = $thread_message->post_content ?? '';
				if (empty($content)) {
					$content = get_post_field('post_content', $thread_message->ID);
				}

				// If still empty, reload the entire post
				if (empty($content)) {
					$full_post = get_post($thread_message->ID);
					if ($full_post && ! empty($full_post->post_content)) {
						$content                      = $full_post->post_content;
						$thread_message->post_content = $content;
					}
				}

				$is_last_message     = ( $index === $last_message_index );
				$message_type        = $thread_message->meta['message_type'] ?? 'customer_to_admin';
				$is_customer_message = $message_type === 'customer_to_admin';
				// Show "New" badge if last message is from customer
				$show_new_badge = $is_last_message && $is_customer_message;
				?>
				<div class="message-item <?php echo esc_attr($message_type); ?>">
					<div class="message-header">
						<div class="message-author">
							<?php if ($is_customer_message) : ?>
								<strong><?php echo esc_html($thread_message->meta['customer_name'] ?? esc_html__('Customer', 'mhm-rentiva')); ?></strong>
								<span class="message-type customer"><?php esc_html_e('Customer', 'mhm-rentiva'); ?></span>
								<?php if ($show_new_badge) : ?>
									<span class="status-badge-new" style="background: #ff9800; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px; text-transform: uppercase;"><?php esc_html_e('New', 'mhm-rentiva'); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<strong><?php echo esc_html(get_the_author_meta('display_name', $thread_message->post_author)); ?></strong>
								<span class="message-type admin"><?php esc_html_e('Administrator', 'mhm-rentiva'); ?></span>
							<?php endif; ?>
						</div>
						<div class="message-date">
							<?php echo esc_html(human_time_diff(strtotime($thread_message->post_date), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'mhm-rentiva'); ?>
						</div>
					</div>

					<div class="message-content">
						<?php
						// Display content - use wpautop for formatting
						if (! empty($content)) {
							echo wp_kses_post(wpautop($content));
						} else {
							echo '<p class="no-content">' . esc_html__('No content available', 'mhm-rentiva') . '</p>';
						}
						?>
					</div>

					<?php if (! empty($thread_message->meta['attachments'])) : ?>
						<div class="message-attachments">
							<strong><?php esc_html_e('Attachments:', 'mhm-rentiva'); ?></strong>
							<?php foreach ($thread_message->meta['attachments'] as $attachment_id) : ?>
								<?php $attachment_url = wp_get_attachment_url($attachment_id); ?>
								<?php if ($attachment_url) : ?>
									<a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="attachment-link">
										<?php echo esc_html(get_the_title($attachment_id)); ?>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_reply_form(int $message_id): void
	{
		if (! $message_id) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Invalid message ID.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$message = get_post($message_id);
		if (! $message || $message->post_type !== Message::POST_TYPE) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Message not found.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$meta            = Message::get_message_meta($message_id);
		$thread_messages = Message::get_thread_messages($meta['thread_id']);

		?>
		<div class="message-reply-form">
			<a href="<?php echo esc_url(MessageUrlHelper::get_message_view_url($message_id)); ?>" class="button">
				← <?php esc_html_e('Back to Message', 'mhm-rentiva'); ?>
			</a>

			<h2><?php esc_html_e('Send Reply', 'mhm-rentiva'); ?></h2>

			<div class="reply-info">
				<p><strong><?php esc_html_e('Recipient:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($meta['customer_name']); ?> (<?php echo esc_html($meta['customer_email']); ?>)</p>
				<p><strong><?php esc_html_e('Subject:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($message->post_title); ?></p>
			</div>

			<?php if (count($thread_messages) > 0) : ?>
				<div class="message-thread-preview" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; font-weight: 600;">
						<?php esc_html_e('Previous Messages', 'mhm-rentiva'); ?>
					</h3>
					<div class="message-thread" style="max-height: 400px; overflow-y: auto;">
						<?php foreach ($thread_messages as $thread_message) : ?>
							<div class="message-item <?php echo esc_attr($thread_message->meta['message_type']); ?>" style="margin-bottom: 15px; padding: 12px; background: white; border-left: 3px solid <?php echo $thread_message->meta['message_type'] === 'customer_to_admin' ? '#2271b1' : '#00a32a'; ?>; border-radius: 3px;">
								<div class="message-header" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px;">
									<div class="message-author">
										<?php if ($thread_message->meta['message_type'] === 'customer_to_admin') : ?>
											<strong><?php echo esc_html($thread_message->meta['customer_name']); ?></strong>
											<span class="message-type customer" style="color: #2271b1; margin-left: 8px;"><?php esc_html_e('Customer', 'mhm-rentiva'); ?></span>
										<?php else : ?>
											<strong><?php echo esc_html(get_the_author_meta('display_name', $thread_message->post_author)); ?></strong>
											<span class="message-type admin" style="color: #00a32a; margin-left: 8px;"><?php esc_html_e('Administrator', 'mhm-rentiva'); ?></span>
										<?php endif; ?>
									</div>
									<div class="message-date" style="color: #666;">
										<?php echo esc_html(human_time_diff(strtotime($thread_message->post_date), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'mhm-rentiva'); ?>
										<br>
										<small><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($thread_message->post_date))); ?></small>
									</div>
								</div>
								<div class="message-content" style="font-size: 13px; line-height: 1.6; color: #333;">
									<?php echo wp_kses_post($thread_message->post_content ?? ''); ?>
								</div>
								<?php if (! empty($thread_message->meta['attachments'])) : ?>
									<div class="message-attachments" style="margin-top: 8px; font-size: 12px;">
										<strong><?php esc_html_e('Attachments:', 'mhm-rentiva'); ?></strong>
										<?php foreach ($thread_message->meta['attachments'] as $attachment_id) : ?>
											<?php $attachment_url = wp_get_attachment_url($attachment_id); ?>
											<?php if ($attachment_url) : ?>
												<a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="attachment-link" style="margin-left: 8px;">
													<?php echo esc_html(get_the_title($attachment_id)); ?>
												</a>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<form id="message-reply-form" method="post" action="">
				<?php wp_nonce_field('mhm_message_reply', 'mhm_message_reply_nonce'); ?>
				<input type="hidden" name="parent_message_id" value="<?php echo esc_attr($message_id); ?>">
				<input type="hidden" name="thread_id" value="<?php echo esc_attr($meta['thread_id']); ?>">
				<input type="hidden" name="customer_email" value="<?php echo esc_attr($meta['customer_email']); ?>">
				<input type="hidden" name="customer_name" value="<?php echo esc_attr($meta['customer_name']); ?>">

				<div class="form-field">
					<label for="reply_subject"><?php esc_html_e('Subject:', 'mhm-rentiva'); ?></label>
					<input type="text" id="reply_subject" name="subject" value="Re: <?php echo esc_attr($message->post_title); ?>" class="regular-text" required>
				</div>

				<div class="form-field">
					<label for="reply_message"><?php esc_html_e('Your Reply:', 'mhm-rentiva'); ?></label>
					<?php
					wp_editor(
						'',
						'reply_message',
						array(
							'textarea_name' => 'message',
							'textarea_rows' => 10,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
					?>
				</div>

				<div class="form-field">
					<label>
						<input type="checkbox" name="close_thread" value="1">
						<?php esc_html_e('Close conversation after this reply', 'mhm-rentiva'); ?>
					</label>
				</div>

				<div class="form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e('Send Reply', 'mhm-rentiva'); ?></button>
					<a href="<?php echo esc_url(MessageUrlHelper::get_message_view_url($message_id)); ?>" class="button"><?php esc_html_e('Cancel', 'mhm-rentiva'); ?></a>
				</div>
			</form>
		</div>
		<?php
	}

	public static function handle_bulk_actions(): void
	{
		if (! check_ajax_referer('mhm_messages_bulk_action', 'nonce', false)) {
			wp_send_json_error(__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}

		$action      = sanitize_key($_POST['action'] ?? '');
		$message_ids = isset($_POST['message_ids']) ? array_map('absint', $_POST['message_ids']) : array();

		if (empty($message_ids)) {
			wp_send_json_error(__('No messages selected.', 'mhm-rentiva'));
			return;
		}

		$success_count = 0;

		switch ($action) {
			case 'mark_read':
				foreach ($message_ids as $message_id) {
					if (Message::mark_as_read($message_id)) {
						++$success_count;
					}
				}
				break;

			case 'mark_unread':
				foreach ($message_ids as $message_id) {
					delete_post_meta($message_id, '_mhm_is_read');
					delete_post_meta($message_id, '_mhm_read_at');
					++$success_count;
				}
				break;

			case 'change_status':
				$new_status = sanitize_key($_POST['status'] ?? '');
				if (in_array($new_status, array_keys(MessagesSettings::get_statuses()), true)) {
					foreach ($message_ids as $message_id) {
						if (Message::update_message_status($message_id, $new_status)) {
							++$success_count;
						}
					}
				}
				break;

			case 'delete':
				foreach ($message_ids as $message_id) {
					if (wp_delete_post($message_id, true)) {
						++$success_count;
					}
				}
				break;
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of messages. */
				'message'   => sprintf(__('%d messages processed successfully.', 'mhm-rentiva'), $success_count),
				'processed' => $success_count,
				'total'     => count($message_ids),
			)
		);
	}

	public static function ajax_reply_to_message(): void
	{
		if (! check_ajax_referer('mhm_message_reply', 'nonce', false)) {
			wp_send_json_error(__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}

		$parent_message_id = self::post_int('parent_message_id');
		// thread_id can be UUID (string) or integer, so sanitize instead of absint
		$thread_id_raw   = self::post_text('thread_id');
		$thread_id       = is_numeric($thread_id_raw) ? absint($thread_id_raw) : $thread_id_raw;
		$subject         = self::post_text('subject');
		$message_content = wp_kses_post(self::post_textarea('message'));
		$customer_email  = self::post_email('customer_email');
		$customer_name   = self::post_text('customer_name');
		$close_thread    = self::post_text('close_thread') !== '';

		if (! $parent_message_id || ! $thread_id || empty($subject) || empty($message_content)) {
			wp_send_json_error(__('Required fields not filled.', 'mhm-rentiva'));
			return;
		}

		$reply_data = array(
			'subject'           => $subject,
			'message'           => $message_content,
			'message_type'      => 'admin_to_customer',
			'customer_email'    => $customer_email,
			'customer_name'     => $customer_name,
			'thread_id'         => $thread_id,
			'parent_message_id' => $parent_message_id,
			'sender_id'         => get_current_user_id(),
		);

		$reply_id = Message::create_message($reply_data);

		if (! $reply_id) {
			wp_send_json_error(__('Reply could not be sent.', 'mhm-rentiva'));
			return;
		}

		// Close thread or mark as answered
		if ($close_thread) {
			Message::update_message_status($parent_message_id, 'closed');
		} else {
			Message::update_message_status($parent_message_id, 'answered');
		}

		// Clear cache (for both parent and reply)
		MessageCache::clear_message_cache($parent_message_id);
		MessageCache::clear_message_cache($reply_id);

		// Also clear WordPress post cache
		clean_post_cache($parent_message_id);
		clean_post_cache($reply_id);

		wp_send_json_success(
			array(
				'message'  => __('Reply sent successfully.', 'mhm-rentiva'),
				'reply_id' => $reply_id,
			)
		);
	}

	public static function ajax_update_status(): void
	{
		if (! check_ajax_referer('mhm_message_status_update', 'nonce', false)) {
			wp_send_json_error(__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}

		$message_id = self::post_int('message_id');
		$status     = self::post_key('status');

		if (! $message_id || ! in_array($status, array_keys(MessagesSettings::get_statuses()), true)) {
			wp_send_json_error(__('Invalid parameters.', 'mhm-rentiva'));
			return;
		}

		if (Message::update_message_status($message_id, $status)) {
			wp_send_json_success(
				array(
					'message'      => __('Message status updated.', 'mhm-rentiva'),
					'status'       => $status,
					'status_label' => MessagesSettings::get_statuses()[ $status ],
				)
			);
		} else {
			wp_send_json_error(__('Status could not be updated.', 'mhm-rentiva'));
		}
	}

	/**
	 * Reopen closed message (AJAX)
	 */
	public static function ajax_reopen_message(): void
	{
		if (! check_ajax_referer('mhm_message_reopen', 'nonce', false)) {
			wp_send_json_error(__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}

		$message_id = self::post_int('message_id');

		if (! $message_id) {
			wp_send_json_error(__('Invalid message ID.', 'mhm-rentiva'));
			return;
		}

		$message = get_post($message_id);
		if (! $message || $message->post_type !== Message::POST_TYPE) {
			wp_send_json_error(__('Message not found.', 'mhm-rentiva'));
			return;
		}

		$meta = Message::get_message_meta($message_id);
		if ($meta['status'] !== 'closed') {
			wp_send_json_error(__('Message is not closed.', 'mhm-rentiva'));
			return;
		}

		// Reopen message (set status to 'answered' if there are admin replies, otherwise 'pending')
		$thread_messages = Message::get_thread_messages($meta['thread_id']);
		$has_admin_reply = false;
		foreach ($thread_messages as $thread_msg) {
			$thread_meta = Message::get_message_meta($thread_msg->ID);
			if ($thread_meta['message_type'] === 'admin_to_customer') {
				$has_admin_reply = true;
				break;
			}
		}

		$new_status = $has_admin_reply ? 'answered' : 'pending';

		if (Message::update_message_status($message_id, $new_status)) {
			// Clear cache
			MessageCache::clear_message_cache($message_id);
			MessageCache::flush();
			clean_post_cache($message_id);

			wp_send_json_success(
				array(
					'message'      => esc_html__('Message reopened successfully.', 'mhm-rentiva'),
					'status'       => $new_status,
					'status_label' => MessagesSettings::get_statuses()[ $new_status ],
				)
			);
		} else {
			wp_send_json_error(esc_html__('Message could not be reopened.', 'mhm-rentiva'));
		}
	}

	public function render_messages_page(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		$is_pro     = \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES);
		$action     = self::get_key('action', 'list');
		$message_id = self::get_int('id');

		echo '<div class="wrap mhm-messages-wrap">';

		$buttons = array();
		if ($is_pro) {
			$buttons[] = array(
				'text'  => esc_html__('New Message', 'mhm-rentiva'),
				'url'   => admin_url('admin.php?page=mhm-rentiva-messages&action=new-message'),
				'class' => 'button button-primary',
				'icon'  => 'dashicons-plus-alt',
			);
		}

		$buttons[] = array(
			'type' => 'documentation',
			'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
		);

		$this->render_admin_header( (string) get_admin_page_title(), $buttons);
		$this->render_developer_mode_banner();

		if (! $is_pro) {
			\MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice('messages');
			echo '</div>';
			return;
		}

		switch ($action) {
			case 'view':
				self::render_message_thread($message_id);
				break;
			case 'edit':
				self::render_edit_form($message_id);
				break;
			case 'reply':
				self::render_reply_form($message_id);
				break;
			case 'new-message':
				self::render_new_message_form();
				break;
			default:
				self::render_messages_list();
				break;
		}

		echo '</div>';
	}

	/**
	 * New message creation form
	 */
	private static function render_new_message_form(): void
	{
		// Get all WordPress users (customers)
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => -1, // Get all users
			)
		);

		$categories = MessagesSettings::get_categories();
		$priorities = MessagesSettings::get_priorities();

		?>
		<div class="mhm-new-message-form">
			<h2><?php esc_html_e('Create New Message', 'mhm-rentiva'); ?></h2>

			<form method="post" action="" class="mhm-message-form" id="mhm-new-message-form">
				<?php wp_nonce_field('mhm_new_message', 'mhm_new_message_nonce'); ?>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="customer_user_id"><?php esc_html_e('Customer', 'mhm-rentiva'); ?></label>
							</th>
							<td>
								<select id="customer_user_id" name="customer_user_id" class="regular-text" required style="width: 400px;">
									<option value=""><?php esc_html_e('Select a customer...', 'mhm-rentiva'); ?></option>
									<?php foreach ($users as $user) : ?>
										<option value="<?php echo esc_attr($user->ID); ?>"
											data-email="<?php echo esc_attr($user->user_email); ?>"
											data-name="<?php echo esc_attr($user->display_name ?: $user->user_login); ?>">
											<?php
											echo esc_html($user->display_name ?: $user->user_login);
											echo ' (' . esc_html($user->user_email) . ')';
											?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Select a registered customer from the site', 'mhm-rentiva'); ?></p>

								<!-- Hidden fields for email and name (populated via JavaScript) -->
								<input type="hidden" id="customer_email" name="customer_email" value="">
								<input type="hidden" id="customer_name" name="customer_name" value="">

								<!-- Display selected customer info -->
								<div id="customer-info" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px; display: none;">
									<strong><?php esc_html_e('Selected Customer:', 'mhm-rentiva'); ?></strong>
									<span id="customer-info-text"></span>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="subject"><?php esc_html_e('Subject', 'mhm-rentiva'); ?></label>
							</th>
							<td>
								<input type="text" id="subject" name="subject" class="regular-text" required>
								<p class="description"><?php esc_html_e('Message subject', 'mhm-rentiva'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="message_content"><?php esc_html_e('Message', 'mhm-rentiva'); ?></label>
							</th>
							<td>
								<textarea id="message_content" name="message_content" rows="10" cols="50" class="large-text" required></textarea>
								<p class="description"><?php esc_html_e('Message content to send', 'mhm-rentiva'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="category"><?php esc_html_e('Category', 'mhm-rentiva'); ?></label>
							</th>
							<td>
								<select id="category" name="category" class="regular-text">
									<?php foreach ($categories as $category_key => $category_label) : ?>
										<option value="<?php echo esc_attr($category_key); ?>" <?php selected($category_key, 'general'); ?>>
											<?php echo esc_html($category_label); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Message category', 'mhm-rentiva'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="priority"><?php esc_html_e('Priority', 'mhm-rentiva'); ?></label>
							</th>
							<td>
								<select id="priority" name="priority" class="regular-text">
									<?php foreach ($priorities as $priority_key => $priority_label) : ?>
										<option value="<?php echo esc_attr($priority_key); ?>" <?php selected($priority_key, 'normal'); ?>>
											<?php echo esc_html($priority_label); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Message priority level', 'mhm-rentiva'); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Send Message', 'mhm-rentiva'); ?>">
					<a href="<?php echo esc_url(MessageUrlHelper::get_messages_list_url()); ?>" class="button">
						<?php esc_html_e('Cancel', 'mhm-rentiva'); ?>
					</a>
				</p>
			</form>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var $customerSelect = $('#customer_user_id');
				var $customerEmail = $('#customer_email');
				var $customerName = $('#customer_name');
				var $customerInfo = $('#customer-info');
				var $customerInfoText = $('#customer-info-text');

				$customerSelect.on('change', function() {
					var $selectedOption = $(this).find('option:selected');
					var email = $selectedOption.data('email');
					var name = $selectedOption.data('name');

					if (email && name) {
						$customerEmail.val(email);
						$customerName.val(name);
						$customerInfoText.text(name + ' (' + email + ')');
						$customerInfo.show();
					} else {
						$customerEmail.val('');
						$customerName.val('');
						$customerInfo.hide();
					}
				});

				// Form submit validation
				$('#mhm-new-message-form').on('submit', function(e) {
					if (!$customerSelect.val()) {
						e.preventDefault();
						alert('<?php echo esc_js(__('Please select a customer.', 'mhm-rentiva')); ?>');
						return false;
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Message view page
	 */
	private static function render_message_view(int $message_id): void
	{
		if (! $message_id) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Invalid message ID.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$message = get_post($message_id);
		if (! $message || $message->post_type !== 'mhm_message') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Message not found.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$meta           = get_post_meta($message_id);
		$customer_email = $meta['_mhm_customer_email'][0] ?? '';
		$customer_name  = $meta['_mhm_customer_name'][0] ?? '';
		$category       = $meta['_mhm_message_category'][0] ?? 'general';
		$status         = $meta['_mhm_message_status'][0] ?? 'pending';

		?>
		<div class="mhm-message-view">
			<div class="message-header">
				<h2><?php echo esc_html($message->post_title); ?></h2>
				<div class="message-meta">
					<p><strong><?php esc_html_e('Customer:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($customer_name . ' (' . $customer_email . ')'); ?></p>
					<p><strong><?php esc_html_e('Category:', 'mhm-rentiva'); ?></strong> <?php echo esc_html(ucfirst($category)); ?></p>
					<p><strong><?php esc_html_e('Status:', 'mhm-rentiva'); ?></strong> <?php echo esc_html(ucfirst($status)); ?></p>
					<p><strong><?php esc_html_e('Date:', 'mhm-rentiva'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->post_date))); ?></p>
				</div>
			</div>

			<div class="message-content">
				<h3><?php esc_html_e('Message Content', 'mhm-rentiva'); ?></h3>
				<div class="message-text">
					<?php echo wp_kses_post(wpautop($message->post_content)); ?>
				</div>
			</div>

			<div class="message-actions">
				<a href="<?php echo esc_url(MessageUrlHelper::get_messages_list_url()); ?>" class="button">
					<?php esc_html_e('Back to Messages', 'mhm-rentiva'); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle new message form
	 */
	public static function handle_new_message_form(): void
	{
		if (! isset($_POST['submit']) || ! isset($_POST['mhm_new_message_nonce'])) {
			return;
		}

		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_new_message_nonce'])), 'mhm_new_message')) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission for this action.', 'mhm-rentiva'));
		}

		// Get form data
		$customer_user_id = self::post_int('customer_user_id');
		$customer_email   = self::post_email('customer_email');
		$customer_name    = self::post_text('customer_name');
		$subject          = self::post_text('subject');
		$content          = wp_kses_post(self::post_textarea('message_content'));
		$category         = self::post_text('category', 'general');
		$priority         = self::post_text('priority', 'normal');

		// If user_id is selected, get email and name from user
		if ($customer_user_id > 0) {
			$user = get_user_by('ID', $customer_user_id);
			if ($user) {
				$customer_email = $user->user_email;
				$customer_name  = $user->display_name ?: $user->user_login;
			}
		}

		// Validation
		if (empty($customer_user_id) || empty($customer_email) || empty($subject) || empty($content)) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__('Please fill in all required fields.', 'mhm-rentiva') . '</p></div>';
				}
			);
			return;
		}

		if (! is_email($customer_email)) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__('Please select a valid customer.', 'mhm-rentiva') . '</p></div>';
				}
			);
			return;
		}

		// Priority validation
		$valid_priorities = array_keys(MessagesSettings::get_priorities());
		if (! in_array($priority, $valid_priorities, true)) {
			$priority = 'normal';
		}

		// Create message
		$message_data = array(
			'post_title'   => $subject,
			'post_content' => $content,
			'post_type'    => 'mhm_message',
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
		);

		$message_id = wp_insert_post($message_data);

		if (is_wp_error($message_id)) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__('Error occurred while creating message.', 'mhm-rentiva') . '</p></div>';
				}
			);
			return;
		}

		// Save meta data
		update_post_meta($message_id, '_mhm_customer_email', $customer_email);
		update_post_meta($message_id, '_mhm_customer_name', $customer_name);
		update_post_meta($message_id, '_mhm_customer_user_id', $customer_user_id);
		update_post_meta($message_id, '_mhm_message_category', $category);
		update_post_meta($message_id, '_mhm_message_priority', $priority);
		update_post_meta($message_id, '_mhm_message_status', 'answered');
		update_post_meta($message_id, '_mhm_is_read', '1');
		update_post_meta($message_id, '_mhm_thread_id', wp_generate_uuid4());
		update_post_meta($message_id, '_mhm_message_type', 'admin_sent');

		// Send email
		$admin_name  = wp_get_current_user()->display_name;
		$admin_email = get_option('admin_email');

		/* translators: 1: Site name, 2: Subject */
		$email_subject = sprintf(__('[%1$s] %2$s', 'mhm-rentiva'), get_bloginfo('name'), $subject);
		/* translators: 1: Customer name, 2: Message content, 3: Admin name, 4: Site name */
		$email_message = sprintf(
			/* translators: 1: %1$s; 2: %2$s; 3: %3$s; 4: %4$s. */
			__("Hello %1\$s,\n\n%2\$s\n\nThis message was sent by %3\$s.\n\n---\n%4\$s", 'mhm-rentiva'),
			$customer_name ?: $customer_email,
			$content,
			$admin_name,
			get_bloginfo('name')
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>',
			'Reply-To: ' . $admin_email,
		);

		wp_mail($customer_email, $email_subject, $email_message, $headers);

		// Success message
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Message sent successfully.', 'mhm-rentiva') . '</p></div>';
			}
		);

		// Clear cache
		MessageCache::flush();
		MessageCache::clear_message_cache($message_id, $customer_email);

		// Redirect to messages page
		wp_safe_redirect(MessageUrlHelper::get_messages_list_url_with_filters(array( 'message' => 'sent' )));
		exit;
	}

	/**
	 * Handle edit message form
	 */
	public static function handle_edit_message_form(): void
	{
		if (! isset($_POST['mhm_edit_message']) || ! isset($_POST['mhm_edit_message_nonce'])) {
			return;
		}

		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_edit_message_nonce'])), 'mhm_edit_message')) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission for this action.', 'mhm-rentiva'));
		}

		$message_id = absint($_POST['message_id'] ?? 0);
		if (! $message_id) {
			return;
		}

		$message = get_post($message_id);
		if (! $message || $message->post_type !== Message::POST_TYPE) {
			return;
		}

		// Get form data
		$category   = sanitize_key($_POST['category'] ?? 'general');
		$status     = sanitize_key($_POST['status'] ?? 'pending');
		$booking_id = absint($_POST['booking_id'] ?? 0);

		// Validation
		$categories = MessagesSettings::get_categories();
		$statuses   = MessagesSettings::get_statuses();

		if (! array_key_exists($category, $categories)) {
			$category = 'general';
		}

		if (! array_key_exists($status, $statuses)) {
			$status = 'pending';
		}

		// Update meta information
		$category_updated = update_post_meta($message_id, '_mhm_message_category', $category);
		$status_updated   = update_post_meta($message_id, '_mhm_message_status', $status);
		$booking_updated  = update_post_meta($message_id, '_mhm_booking_id', $booking_id);

		// Clear cache - after each change
		if ($category_updated || $status_updated || $booking_updated) {
			MessageCache::clear_message_cache($message_id);
			// Also clear WordPress post meta cache
			clean_post_cache($message_id);
		}

		// Success message
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html__('Message updated successfully.', 'mhm-rentiva') . '</p>';
				echo '</div>';
			}
		);

		// Clear cache again (before redirect)
		MessageCache::flush();

		// Redirect
		wp_safe_redirect(MessageUrlHelper::get_messages_list_url_with_filters(array( 'updated' => '1' )));
		exit;
	}

	/**
	 * Render edit message form
	 */
	private static function render_edit_form(int $message_id): void
	{
		if (! $message_id) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Invalid message ID.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$message = get_post($message_id);
		if (! $message || $message->post_type !== Message::POST_TYPE) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Message not found.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$meta       = Message::get_message_meta($message_id);
		$categories = MessagesSettings::get_categories();
		$statuses   = MessagesSettings::get_statuses();

		// Get bookings list (for select)
		$bookings = get_posts(
			array(
				'post_type'      => 'vehicle_booking',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		?>
		<div class="message-edit-form">
			<a href="<?php echo esc_url(MessageUrlHelper::get_messages_list_url()); ?>" class="button">
				← <?php esc_html_e('Back to Messages', 'mhm-rentiva'); ?>
			</a>

			<h2><?php esc_html_e('Edit Message', 'mhm-rentiva'); ?></h2>

			<div class="message-info" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<p><strong><?php esc_html_e('Subject:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($message->post_title); ?></p>
				<p><strong><?php esc_html_e('Customer:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($meta['customer_name']); ?> (<?php echo esc_html($meta['customer_email']); ?>)</p>
				<p><strong><?php esc_html_e('Date:', 'mhm-rentiva'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->post_date))); ?></p>
				<p><strong><?php esc_html_e('Message Content:', 'mhm-rentiva'); ?></strong></p>
				<div style="margin-top: 10px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px;">
					<?php echo wp_kses_post($message->post_content); ?>
				</div>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field('mhm_edit_message', 'mhm_edit_message_nonce'); ?>
				<input type="hidden" name="mhm_edit_message" value="1">
				<input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="category"><?php esc_html_e('Category', 'mhm-rentiva'); ?></label>
						</th>
						<td>
							<select name="category" id="category" class="regular-text">
								<?php foreach ($categories as $cat_key => $cat_label) : ?>
									<option value="<?php echo esc_attr($cat_key); ?>" <?php selected($meta['category'], $cat_key); ?>>
										<?php echo esc_html($cat_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('Select the message category.', 'mhm-rentiva'); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="status"><?php esc_html_e('Status', 'mhm-rentiva'); ?></label>
						</th>
						<td>
							<select name="status" id="status" class="regular-text">
								<?php foreach ($statuses as $status_key => $status_label) : ?>
									<option value="<?php echo esc_attr($status_key); ?>" <?php selected($meta['status'], $status_key); ?>>
										<?php echo esc_html($status_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('Select the message status.', 'mhm-rentiva'); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="booking_id"><?php esc_html_e('Booking Association', 'mhm-rentiva'); ?></label>
						</th>
						<td>
							<select name="booking_id" id="booking_id" class="regular-text">
								<option value="0"><?php esc_html_e('No booking', 'mhm-rentiva'); ?></option>
								<?php foreach ($bookings as $booking) : ?>
									<option value="<?php echo esc_attr($booking->ID); ?>" <?php selected($meta['booking_id'], $booking->ID); ?>>
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: booking ID, 2: booking title. */
												__('Booking #%1$d - %2$s', 'mhm-rentiva'),
												$booking->ID,
												get_the_title($booking->ID)
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('Associate this message with a booking (optional).', 'mhm-rentiva'); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e('Update Message', 'mhm-rentiva'); ?></button>
					<a href="<?php echo esc_url(MessageUrlHelper::get_message_view_url($message_id)); ?>" class="button"><?php esc_html_e('Cancel', 'mhm-rentiva'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Load scripts and styles for messages page (old function - removed)
	 */

	/*
	 * Removed redundant add_message_stats_cards (handled by standardized header)
	 */

	/**
	 * Get message statistics
	 */
	private static function get_message_stats(): array
	{
		return MessageQueryHelper::get_message_stats();
	}

	/**
	 * Load CSS and JS for admin messages pages
	 */
	public static function enqueue_message_scripts(string $hook): void
	{
		// Load only on messages pages
		if (strpos($hook, 'mhm-rentiva-messages') !== false || strpos($hook, 'mhm-rentiva-messages-logs') !== false) {

			// Admin Messages CSS
			wp_enqueue_style(
				'mhm-messages-admin',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/messages-admin.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			// Stats Cards CSS (Modular)
			wp_enqueue_style(
				'mhm-stats-cards',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
				array( 'mhm-core-css' ),
				MHM_RENTIVA_VERSION
			);

			// Messages Settings CSS and JS
			if (strpos($hook, 'mhm-rentiva-messages-settings') !== false) {
				wp_enqueue_style(
					'mhm-messages-settings',
					MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/messages-settings.css',
					array(),
					MHM_RENTIVA_VERSION
				);

				wp_enqueue_script(
					'mhm-messages-settings',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/messages-settings.js',
					array( 'jquery' ),
					MHM_RENTIVA_VERSION,
					true
				);
			}

			// Monitoring CSS and JS
			if (strpos($hook, 'mhm-rentiva-messages-monitoring') !== false || strpos($hook, 'mhm-rentiva-messages-logs') !== false) {
				wp_enqueue_style(
					'mhm-messages-monitoring',
					MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/monitoring.css',
					array(),
					MHM_RENTIVA_VERSION
				);

				wp_enqueue_script(
					'mhm-messages-monitoring',
					MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/monitoring.js',
					array( 'jquery' ),
					MHM_RENTIVA_VERSION,
					true
				);

				// Localize JavaScript variables
				wp_localize_script(
					'mhm-messages-monitoring',
					'mhmMonitoring',
					array(
						'ajax_url' => MessageUrlHelper::get_ajax_url(),
						'nonce'    => wp_create_nonce('mhm_messages_performance'),
						'logs_url' => MessageUrlHelper::get_admin_page_url('mhm-rentiva-messages-logs'),
					)
				);
			}

			// Admin Messages JS
			wp_enqueue_script(
				'mhm-messages-admin',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/messages-admin.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			// Localize JavaScript variables
			wp_localize_script(
				'mhm-messages-admin',
				'mhmMessagesAdmin',
				array(
					'ajax_url'            => MessageUrlHelper::get_ajax_url(),
					'nonce'               => wp_create_nonce('mhm_message_reply'),
					'reopen_nonce'        => wp_create_nonce('mhm_message_reopen'),
					'status_update_nonce' => wp_create_nonce('mhm_message_status_update'),
					'strings'             => array(
						'confirm_delete'        => __('Are you sure you want to delete these messages?', 'mhm-rentiva'),
						/* translators: %d placeholder. */
						'confirm_mark_read'     => __('Are you sure you want to mark %d messages as read?', 'mhm-rentiva'),
						/* translators: %d placeholder. */
						'confirm_mark_unread'   => __('Are you sure you want to mark %d messages as unread?', 'mhm-rentiva'),
						'confirm_status_change' => __('Are you sure you want to change the status?', 'mhm-rentiva'),
						'loading'               => __('Loading...', 'mhm-rentiva'),
						'error'                 => __('An error occurred. Please try again.', 'mhm-rentiva'),
						'success'               => __('Operation successful.', 'mhm-rentiva'),
						'no_items_selected'     => __('Please select at least one message.', 'mhm-rentiva'),
						'processing'            => __('Processing...', 'mhm-rentiva'),
						'sendReply'             => __('Send Reply', 'mhm-rentiva'),
						'draftSaved'            => __('Draft saved', 'mhm-rentiva'),
						'process'               => __('Process', 'mhm-rentiva'),
					),
				)
			);
		}
	}

	/**
	 * Load CSS and JS for frontend
	 */
	public static function enqueue_frontend_assets(): void
	{
		// Load on My Account pages or messaging shortcodes
		if (! is_page()) {
			return;
		}

		global $post;
		if (! $post) {
			return;
		}

		// Check if we're on My Account messages page (messages endpoint)
		// In this case, assets are loaded in AccountRenderer, so skip here
		$endpoint = get_query_var('endpoint') ?: self::get_key('endpoint');
		if ($endpoint === 'messages') {
			return; // Assets loaded in AccountRenderer::render_messages()
		}

		$has_messages_shortcode = has_shortcode($post->post_content, 'rentiva_my_account') ||
			has_shortcode($post->post_content, 'customer-messages') ||
			strpos($post->post_content, 'customer-messages') !== false;

		if (! $has_messages_shortcode) {
			return;
		}

		// Customer Messages CSS
		wp_enqueue_style(
			'mhm-customer-messages',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/customer-messages.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Customer Messages Standalone CSS
		wp_enqueue_style(
			'mhm-customer-messages-standalone',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/customer-messages-standalone.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Customer Messages JS (only for standalone customer-messages shortcode, not My Account)
		wp_enqueue_script(
			'mhm-customer-messages',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/customer-messages.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Customer Messages Standalone JS
		wp_enqueue_script(
			'mhm-customer-messages-standalone',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/customer-messages-standalone.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localize JavaScript variables
		wp_localize_script(
			'mhm-customer-messages',
			'mhmCustomerMessages',
			array(
				'ajax_url'     => MessageUrlHelper::get_ajax_url(),
				'rest_url'     => MessageUrlHelper::get_rest_url(),
				'nonce'        => wp_create_nonce('mhm_customer_messages'),
				'rest_nonce'   => wp_create_nonce('wp_rest'),
				'is_logged_in' => is_user_logged_in(),
				'strings'      => array(
					'loading'      => __('Loading...', 'mhm-rentiva'),
					'error'        => __('An error occurred. Please try again.', 'mhm-rentiva'),
					'success'      => __('Operation successful.', 'mhm-rentiva'),
					'confirm_send' => __('Are you sure you want to send this message?', 'mhm-rentiva'),
					'please_login' => __('Please login to send a message.', 'mhm-rentiva'),
				),
			)
		);
	}

	private static function get_text(string $key, string $default = ''): string
	{
		$raw = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
		if (null === $raw || false === $raw) {
			return $default;
		}

		return sanitize_text_field( (string) $raw);
	}

	private static function get_key(string $key, string $default = ''): string
	{
		$value = self::get_text($key, $default);
		return '' === $value ? $default : sanitize_key($value);
	}

	private static function get_int(string $key, int $default = 0): int
	{
		$value = self::get_text($key, '');
		return '' === $value ? $default : absint($value);
	}

	private static function post_text(string $key, string $default = ''): string
	{
		$raw = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
		if (null === $raw || false === $raw) {
			return $default;
		}

		return sanitize_text_field( (string) $raw);
	}

	private static function post_textarea(string $key, string $default = ''): string
	{
		$raw = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
		if (null === $raw || false === $raw) {
			return $default;
		}

		return sanitize_textarea_field( (string) $raw);
	}

	private static function post_email(string $key, string $default = ''): string
	{
		return sanitize_email(self::post_text($key, $default));
	}

	private static function post_int(string $key, int $default = 0): int
	{
		$value = self::post_text($key, '');
		return '' === $value ? $default : absint($value);
	}

	private static function post_key(string $key, string $default = ''): string
	{
		$value = self::post_text($key, $default);
		return '' === $value ? $default : sanitize_key($value);
	}
}
