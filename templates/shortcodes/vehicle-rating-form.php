<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Rating form and review list (PR F; drawings Puan-Formu, Puan-Formu-Mobil).
 *
 * Standalone: title, summary, list, form. Inside the vehicle page the page draws the
 * title and the summary: here only the list and the form, under no heading of its own.
 *
 * The controller decides everything (VehicleRatingForm::prepare_template_data()).
 * Script: vehicle-rating-form.js; one instance per [data-rvs-rating].
 *
 * @var int                       $vehicle_id
 * @var string                    $uid                 Unique id base of this instance.
 * @var bool                      $nested              Inside another rvs surface.
 * @var string                    $class               Extra classes.
 * @var array<string, mixed>|null $summary             ReviewsView::summary().
 * @var bool                      $show_rating_display
 * @var bool                      $show_ratings_list
 * @var bool                      $show_form
 * @var list<array<string,mixed>> $cards               ReviewsView::card().
 * @var bool                      $can_post
 * @var bool                      $has_own             The visitor already has a review here.
 * @var bool                      $guest_fields        Ask a guest's name and e-mail.
 * @var bool                      $has_dialog          A delete button is on the page.
 * @var int                       $min_length
 * @var int                       $max_length
 */

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Frontend\RenderContext;

if (! defined('ABSPATH')) {
	exit;
}

$vehicle_id = (int) ( $vehicle_id ?? 0 );

// The controller resolves the vehicle (VehicleRatingForm::resolve_vehicle()); the
// template never looks for an id of its own. No vehicle: a short, helpful state.
if ($vehicle_id <= 0) {
	$empty_text = RenderContext::current()->is_preview()
		? __('No vehicle selected. Set a vehicle ID in the widget settings, or place this form on a vehicle page.', 'mhm-rentiva')
		: __('Vehicle ratings are not available here.', 'mhm-rentiva');
	echo '<div class="rvs-reviews rvs-reviews--unavailable">' . esc_html($empty_text) . '</div>';
	return;
}

$uid        = (string) $uid;
$nested     = ! empty($nested);
$is_preview = RenderContext::current()->is_preview();
$inert      = $is_preview ? ' disabled' : '';
$min_length = (int) ( $min_length ?? 0 );
$max_length = (int) ( $max_length ?? 0 );
$classes    = trim('rvs-reviews' . ( $nested ? ' rvs-reviews--nested' : '' ) . ' ' . (string) ( $class ?? '' ));
?>
<div class="<?php echo esc_attr($classes); ?>" data-rvs-rating data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>">
	<?php if (! $nested) : ?>
		<h2 class="rvs-reviews__title"><?php esc_html_e('Reviews', 'mhm-rentiva'); ?></h2>
	<?php endif; ?>

	<?php if (! empty($show_rating_display) && ! empty($summary)) : ?>
		<?php
		Templates::render(
			'partials/rvs/review-summary',
			array(
				'summary'    => $summary,
				'show_stars' => true,
			)
		);
		?>
	<?php endif; ?>

	<?php if (! empty($show_ratings_list)) : ?>
		<?php if (! empty($cards)) : ?>
			<div class="rvs-reviews__list">
				<?php foreach ($cards as $card) : ?>
					<?php Templates::render('partials/rvs/review-card', array( 'review' => $card )); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="rvs-reviews__empty">
				<h3 class="rvs-reviews__box-title"><?php esc_html_e('No reviews yet.', 'mhm-rentiva'); ?></h3>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="rvs-reviews__status" data-rvs-rating-status role="status"></div>

	<?php if (! empty($show_form) && ! empty($can_post)) : ?>
		<form class="rvs-reviews__form" data-rvs-rating-form novalidate
			<?php if ($nested) : ?>
				aria-label="<?php esc_attr_e('Write a review', 'mhm-rentiva'); ?>"
			<?php else : ?>
				aria-labelledby="<?php echo esc_attr($uid . '-form-title'); ?>"
			<?php endif; ?>
			<?php echo ! empty($has_own) ? 'hidden' : ''; ?>>
			<?php if (! $nested) : ?>
				<h3 class="rvs-reviews__box-title" id="<?php echo esc_attr($uid . '-form-title'); ?>" data-rvs-rating-title><?php esc_html_e('Rate this vehicle', 'mhm-rentiva'); ?></h3>
			<?php endif; ?>

			<input type="hidden" name="vehicle_id" value="<?php echo esc_attr( (string) $vehicle_id); ?>">
			<?php RenderContext::the_nonce_input_line('mhmrentiva_rating_nonce', 'nonce'); ?>

			<?php if (! empty($guest_fields)) : ?>
				<div class="rvs-reviews__guest">
					<div class="rvs-field" data-rvs-field>
						<label for="<?php echo esc_attr($uid . '-name'); ?>"><?php esc_html_e('Your name', 'mhm-rentiva'); ?></label>
						<input type="text" class="rvs-input" id="<?php echo esc_attr($uid . '-name'); ?>" name="guest_name" autocomplete="name" required>
					</div>
					<div class="rvs-field" data-rvs-field>
						<label for="<?php echo esc_attr($uid . '-email'); ?>"><?php esc_html_e('Your email', 'mhm-rentiva'); ?></label>
						<input type="email" class="rvs-input" id="<?php echo esc_attr($uid . '-email'); ?>" name="guest_email" autocomplete="email" required>
					</div>
				</div>
			<?php endif; ?>

			<fieldset class="rvs-field" id="<?php echo esc_attr($uid . '-rating'); ?>" data-rvs-field data-rvs-rating-stars>
				<legend><?php esc_html_e('Your rating', 'mhm-rentiva'); ?></legend>
				<?php Templates::render('partials/rvs/star-input', array( 'disabled' => $is_preview )); ?>
			</fieldset>

			<div class="rvs-field" data-rvs-field>
				<label for="<?php echo esc_attr($uid . '-comment'); ?>"><?php esc_html_e('Your review', 'mhm-rentiva'); ?></label>
				<textarea name="comment" class="rvs-input rvs-reviews__comment" id="<?php echo esc_attr($uid . '-comment'); ?>" rows="4" aria-describedby="<?php echo esc_attr($uid . '-count'); ?>" data-min-length="<?php echo esc_attr( (string) $min_length); ?>" data-max-length="<?php echo esc_attr( (string) $max_length); ?>"></textarea>
				<span class="rvs-help" id="<?php echo esc_attr($uid . '-count'); ?>" data-rvs-rating-count aria-live="polite">
					<?php
					echo esc_html(
						$min_length > 0
							/* translators: 1: characters written, 2: most characters allowed, 3: fewest characters allowed. */
							? sprintf(__('%1$s / %2$s, at least %3$s characters', 'mhm-rentiva'), number_format_i18n(0), number_format_i18n($max_length), number_format_i18n($min_length))
							/* translators: 1: characters written, 2: most characters allowed. */
							: sprintf(__('%1$s / %2$s', 'mhm-rentiva'), number_format_i18n(0), number_format_i18n($max_length))
					);
					?>
				</span>
			</div>

			<div class="rvs-notice rvs-notice--alert" data-rvs-rating-alert role="alert" hidden></div>

			<div class="rvs-reviews__actions">
				<button type="submit" class="rvs-btn rvs-btn--primary"<?php echo esc_attr($inert); ?>><?php esc_html_e('Submit review', 'mhm-rentiva'); ?></button>
				<button type="button" class="rvs-btn rvs-btn--secondary" data-rvs-rating-cancel hidden><?php esc_html_e('Cancel', 'mhm-rentiva'); ?></button>
			</div>
		</form>
	<?php elseif (! empty($show_form) && empty($has_own)) : /* Form requested but the visitor may not post. With show_form off, neither branch renders. */ ?>
		<div class="rvs-reviews__login">
			<h3 class="rvs-reviews__box-title"><?php esc_html_e('Login required', 'mhm-rentiva'); ?></h3>
			<p><?php esc_html_e('Log in to rate this vehicle and write a review.', 'mhm-rentiva'); ?></p>
			<div class="rvs-reviews__actions">
				<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url(\MHMRentiva\Admin\Frontend\Account\AccountController::get_login_url()); ?>"><?php esc_html_e('Log in', 'mhm-rentiva'); ?></a>
				<?php $register_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_register_url(); ?>
				<?php if ('' !== $register_url) : /* Only when the site really offers registration. */ ?>
					<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url($register_url); ?>"><?php esc_html_e('Register', 'mhm-rentiva'); ?></a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if (! empty($has_dialog)) : ?>
		<?php Templates::render('partials/rvs/review-delete-dialog', array( 'dialog_id' => $uid . '-delete' )); ?>
	<?php endif; ?>
</div>
