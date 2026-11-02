<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Assets\DatepickerAssets;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * View values of the review component (PR F): the summary, one card, who may post
 * and the answer to a submission. It reads no reviews: callers hand it rows, so the
 * rating form, the vehicle page and the testimonials draw the same thing from their
 * own data sources. The partials are templates/partials/rvs/review-*.php.
 *
 * @since 6.4.0
 */
final class ReviewsView {

	/** The form starts empty, for a first review. */
	public const FORM_NEW = 'new';

	/** The form waits, hidden, for the Edit button on the visitor's own card. */
	public const FORM_HIDDEN = 'hidden';

	/** No list, so no card to edit from: the form shows the visitor's review itself. */
	public const FORM_EDIT = 'edit';

	/** The visitor has a review and may not change it: no form at all. */
	public const FORM_NONE = 'none';

	/**
	 * How the form appears to a visitor who may post.
	 *
	 * @param bool $has_own   The visitor already has a review on this vehicle.
	 * @param bool $show_list The list, with its Edit button, is on the page.
	 * @param bool $can_edit  The site allows editing.
	 */
	public static function form_mode(bool $has_own, bool $show_list, bool $can_edit): string
	{
		if (! $has_own) {
			return self::FORM_NEW;
		}
		if (! $can_edit) {
			return self::FORM_NONE;
		}

		return $show_list ? self::FORM_HIDDEN : self::FORM_EDIT;
	}

	/**
	 * Average, count and the 5..1 distribution, or null without reviews.
	 *
	 * @param array<string, mixed> $rating       RatingHelper::get_rating().
	 * @param array<int, int>      $distribution RatingHelper::get_distribution().
	 * @return array{average: float, average_text: string, count: int, count_text: string, stars: int, stars_label: string, distribution: list<array{star: int, label: string, count: int, count_text: string, pct: int}>}|null
	 */
	public static function summary(array $rating, array $distribution): ?array
	{
		$count = (int) ( $rating['count'] ?? 0 );
		if ($count <= 0) {
			return null;
		}

		$average = (float) ( $rating['average'] ?? 0 );
		$total   = max(1, array_sum($distribution));
		$rows    = array();
		foreach (array( 5, 4, 3, 2, 1 ) as $star) {
			$n      = (int) ( $distribution[ $star ] ?? 0 );
			$rows[] = array(
				'star'       => $star,
				/* translators: %d: star rating, 1 to 5. */
				'label'      => sprintf(_n('%d star', '%d stars', $star, 'mhm-rentiva'), $star),
				'count'      => $n,
				'count_text' => number_format_i18n($n),
				'pct'        => (int) round($n * 100 / $total),
			);
		}

		$average_text = number_format_i18n($average, 1);

		return array(
			'average'      => $average,
			'average_text' => $average_text,
			'count'        => $count,
			/* translators: %s: number of reviews. */
			'count_text'   => sprintf(_n('%s review', '%s reviews', $count, 'mhm-rentiva'), number_format_i18n($count)),
			'stars'        => (int) round($average),
			/* translators: %s: average rating, for example 4.8. */
			'stars_label'  => sprintf(__('%s out of 5 stars', 'mhm-rentiva'), $average_text),
			'distribution' => $rows,
		);
	}

	/**
	 * One review card.
	 *
	 * Row keys: id, author, rating (0 = none), text, date (Y-m-d H:i:s, site time),
	 * and optional pending, verified, own, can_edit, can_delete, show_avatar,
	 * show_rating, vehicle, disabled.
	 *
	 * @param array<string, mixed> $row One review.
	 * @return array<string, mixed> What partials/rvs/review-card.php needs.
	 */
	public static function card(array $row): array
	{
		$name    = self::mask_name( (string) ( $row['author'] ?? '' ));
		$rating  = max(0, min(5, (int) ( $row['rating'] ?? 0 )));
		$pending = ! empty($row['pending']);
		$own     = ! empty($row['own']);

		return array(
			'id'          => (int) ( $row['id'] ?? 0 ),
			'name'        => $name,
			'initial'     => mhmrentiva_initial_avatar_letter($name),
			'show_avatar' => (bool) ( $row['show_avatar'] ?? true ),
			'rating'      => (bool) ( $row['show_rating'] ?? true ) ? $rating : 0,
			'raw_rating'  => $rating,
			'stars_label' => self::stars_label($rating),
			'date'        => self::date_text( (string) ( $row['date'] ?? '' )),
			'text'        => (string) ( $row['text'] ?? '' ),
			'vehicle'     => (string) ( $row['vehicle'] ?? '' ),
			'verified'    => ! empty($row['verified']),
			'pending'     => $pending,
			'can_edit'    => $own && ! empty($row['can_edit']),
			'can_delete'  => $own && ! empty($row['can_delete']),
			'disabled'    => ! empty($row['disabled']),
		);
	}

	/**
	 * A person's public name: "John Doe" becomes "John D.". One word stays as it is.
	 */
	public static function mask_name(string $author): string
	{
		$author = trim($author);
		if ('' === $author) {
			return __('Anonymous', 'mhm-rentiva');
		}

		$parts = preg_split('/\s+/u', $author);
		if (! is_array($parts) || count($parts) < 2) {
			return $author;
		}

		return $parts[0] . ' ' . mb_substr( (string) end($parts), 0, 1, 'UTF-8') . '.';
	}

	/**
	 * "4 out of 5 stars", read out by the stars' role="img".
	 */
	public static function stars_label(int $rating): string
	{
		/* translators: %s: rating, 1 to 5. */
		return sprintf(__('%s out of 5 stars', 'mhm-rentiva'), number_format_i18n($rating));
	}

	/**
	 * A comment date in the site's date format, '' for no date.
	 *
	 * @param string $mysql Y-m-d H:i:s, site time.
	 */
	public static function date_text(string $mysql): string
	{
		return DatepickerAssets::display_date(substr($mysql, 0, 10));
	}

	/**
	 * Who may post: anyone logged in; a guest only where the settings allow it.
	 * The one rule of the screen and of the endpoint.
	 */
	public static function can_post(bool $logged_in, bool $require_login, bool $allow_guest): bool
	{
		return $logged_in || ( ! $require_login && $allow_guest );
	}

	/**
	 * What the visitor is told after sending, from the comment's real status.
	 * A guest's review waiting for approval is not listed for anyone, the guest
	 * included (r2 M5), so that sentence does not promise "only you can see it".
	 *
	 * @param bool $approved  The stored comment is approved.
	 * @param bool $is_update An existing review was changed.
	 * @param bool $is_guest  The sender is not logged in.
	 * @return array{state: string, title: string, message: string, reload: bool}
	 */
	public static function submission_answer(bool $approved, bool $is_update, bool $is_guest): array
	{
		if ($is_update) {
			return $approved
				? array(
					'state'   => 'updated',
					'title'   => __('Your review was updated', 'mhm-rentiva'),
					'message' => __('Thank you. The list shows your changes.', 'mhm-rentiva'),
					'reload'  => true,
				)
				: array(
					'state'   => 'updated_pending',
					'title'   => __('Your review was updated', 'mhm-rentiva'),
					'message' => __('It went back under review. Until it is approved, only you can see it.', 'mhm-rentiva'),
					'reload'  => true,
				);
		}

		if ($approved) {
			// A guest may be served the vehicle page from the HTML cache for up to five
			// minutes: reloading could show a list without the review, so nothing here
			// promises that it is already there.
			return array(
				'state'   => 'published',
				'title'   => __('Your review is published', 'mhm-rentiva'),
				'message' => $is_guest
					? __('Thank you. It appears in the list within a few minutes.', 'mhm-rentiva')
					: __('Thank you. Your review is in the list.', 'mhm-rentiva'),
				'reload'  => ! $is_guest,
			);
		}

		return array(
			'state'   => 'pending',
			'title'   => __('We received your review', 'mhm-rentiva'),
			'message' => $is_guest
				? __('It becomes visible to everyone once it is reviewed.', 'mhm-rentiva')
				: __('It becomes visible to everyone once it is reviewed. Until then, only you can see it.', 'mhm-rentiva'),
			'reload'  => ! $is_guest,
		);
	}
}
