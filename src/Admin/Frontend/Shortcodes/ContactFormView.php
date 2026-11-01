<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * View values of the contact form (PR F): topics, fields per topic, the visitor's
 * bookings, the information rows, consent, the attachment limits and the ids of
 * one instance. It reads nothing: ContactForm gathers the context.
 *
 * @since 6.4.0
 */
final class ContactFormView {

	/** The topics a visitor chooses between; `support` only comes from the attribute. */
	public const SELECTOR_TOPICS = array( 'general', 'booking', 'feedback' );

	/**
	 * The trap field's name. A person never sees it, so a value means a bot. No word
	 * browsers autofill (website, url, name, company...): autofill would make a real
	 * visitor's message vanish behind the success answer.
	 */
	public const HONEYPOT_FIELD = 'rvs_leave_blank';

	/**
	 * The types one form may submit, which is also what its token vouches for (r2 M1).
	 *
	 * @param string $type     The form's normalized type.
	 * @param bool   $selector Whether the visitor chooses the topic.
	 * @return list<string>
	 */
	public static function topic_types(string $type, bool $selector): array
	{
		if (! $selector) {
			return array( $type );
		}

		return in_array($type, self::SELECTOR_TOPICS, true) ? self::SELECTOR_TOPICS : array_merge(self::SELECTOR_TOPICS, array( $type ));
	}

	/**
	 * @param array<string,mixed> $atts    Canonical shortcode attributes.
	 * @param array<string,mixed> $context uid, logged_in, own_bookings
	 *                                     (list of {id, number, vehicle, dates}), has_vehicles,
	 *                                     required (type => field names), address, phone,
	 *                                     phone_note, hours, email, response_note,
	 *                                     consent_enabled, privacy_url, attachment_types,
	 *                                     max_bytes.
	 * @return array<string,mixed>
	 */
	public static function build(array $atts, array $context): array
	{
		$type     = (string) ( $atts['type'] ?? 'general' );
		$selector = self::on($atts, 'show_topic_selector');
		$topics   = self::topic_types($type, $selector);
		$bookings = ! empty($context['logged_in']) ? self::booking_options( (array) ( $context['own_bookings'] ?? array() )) : array();
		$consent  = self::consent(! empty($context['consent_enabled']), (string) ( $context['privacy_url'] ?? '' ));

		$mode          = self::booking_mode($selector, self::on($atts, 'show_booking_id_field'), array() !== $bookings);
		$booking_field = array(
			'mode'   => $mode,
			'topics' => '' === $mode ? array() : array( $selector ? 'booking' : $type ),
		);

		$fields = array();
		foreach ($topics as $topic) {
			$fields[ $topic ] = self::fields_for($topic, $atts, $selector, in_array($topic, $booking_field['topics'], true), $consent['enabled'], $context);
		}

		$labels = self::topic_labels();
		$rows   = array();
		foreach ($topics as $topic) {
			$rows[] = array(
				'value' => $topic,
				'label' => $labels[ $topic ] ?? $topic,
			);
		}

		$uid = (string) ( $context['uid'] ?? '' );

		return array(
			'uid'           => $uid,
			'ids'           => self::ids($uid),
			'selector'      => $selector,
			'topics'        => $rows,
			'selected'      => $type,
			'fields'        => $fields,
			'booking_field' => $booking_field,
			'bookings'      => $bookings,
			'sidebar'       => self::on($atts, 'show_company_info') ? self::sidebar($context) : array(),
			'consent'       => $consent,
			'response_note' => (string) ( $context['response_note'] ?? '' ),
			'attachment'    => self::attachment( (array) ( $context['attachment_types'] ?? array() ), (int) ( $context['max_bytes'] ?? 0 )),
			'honeypot'      => self::HONEYPOT_FIELD,
		);
	}

	/**
	 * @param array<string,mixed> $atts Attributes.
	 */
	private static function on(array $atts, string $key): bool
	{
		return '1' === (string) ( $atts[ $key ] ?? '0' );
	}

	/**
	 * R3 + r2 M3: `list` the visitor's own bookings, `number` the old box (a guest,
	 * or a member with nothing to list, can still type a number), '' no field.
	 */
	private static function booking_mode(bool $selector, bool $attribute, bool $has_list): string
	{
		if ($has_list && ( $selector || $attribute )) {
			return 'list';
		}

		return $attribute ? 'number' : '';
	}

	/**
	 * Field name => required, for one topic.
	 *
	 * @param array<string,mixed> $atts    Attributes.
	 * @param array<string,mixed> $context Context.
	 * @return array<string,bool>
	 */
	private static function fields_for(string $topic, array $atts, bool $selector, bool $has_booking, bool $consent, array $context): array
	{
		$required = (array) ( $context['required'][ $topic ] ?? array() );
		$wanted   = array(
			'name'           => true,
			'email'          => true,
			'phone'          => self::on($atts, 'show_phone'),
			'company'        => self::on($atts, 'show_company'),
			'vehicle_id'     => self::on($atts, 'show_vehicle_selector') && ! empty($context['has_vehicles']),
			'booking_id'     => $has_booking,
			// Not in the drawing's topics (r2 M3): these two stay on a fixed form.
			'preferred_date' => ! $selector && 'booking' === $topic,
			'rating'         => ! $selector && 'feedback' === $topic,
			'priority'       => self::on($atts, 'show_priority') || in_array('priority', $required, true),
			'message'        => true,
			'attachment'     => self::on($atts, 'show_attachment'),
			'consent'        => $consent,
		);

		$fields = array();
		foreach (array_keys(array_filter($wanted)) as $name) {
			$fields[ $name ] = in_array($name, $required, true) || 'consent' === $name;
		}

		return $fields;
	}

	/**
	 * @return array<string,string>
	 */
	private static function topic_labels(): array
	{
		return array(
			'general'  => __('General', 'mhm-rentiva'),
			'booking'  => __('My booking', 'mhm-rentiva'),
			'feedback' => __('Suggestion or complaint', 'mhm-rentiva'),
			'support'  => __('Technical support', 'mhm-rentiva'),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows {id, number, vehicle, dates}.
	 * @return list<array{id:int,label:string}>
	 */
	private static function booking_options(array $rows): array
	{
		$options = array();
		foreach ($rows as $row) {
			$parts     = array_filter(
				array(
					'#' . (string) ( $row['number'] ?? $row['id'] ?? '' ),
					(string) ( $row['vehicle'] ?? '' ),
					(string) ( $row['dates'] ?? '' ),
				),
				static fn(string $part): bool => '' !== $part
			);
			$options[] = array(
				'id'    => (int) ( $row['id'] ?? 0 ),
				'label' => implode(' · ', $parts),
			);
		}

		return $options;
	}

	/**
	 * The information rows that have something to say (R5).
	 *
	 * @param array<string,mixed> $context Context.
	 * @return list<array{key:string,label:string,value:string,href:string,note:string}>
	 */
	private static function sidebar(array $context): array
	{
		$address = (string) ( $context['address'] ?? '' );
		$phone   = (string) ( $context['phone'] ?? '' );
		$email   = (string) ( $context['email'] ?? '' );
		$hours   = (string) ( $context['hours'] ?? '' );

		$rows = array();
		if ('' !== $address) {
			$rows[] = self::row('address', __('Office', 'mhm-rentiva'), $address);
		}
		if ('' !== $phone) {
			$rows[] = self::row('phone', __('Phone', 'mhm-rentiva'), $phone, 'tel:' . preg_replace('/[^0-9+]/', '', $phone), (string) ( $context['phone_note'] ?? '' ));
		}
		if ('' !== $email) {
			$rows[] = self::row('email', __('Email', 'mhm-rentiva'), $email, 'mailto:' . $email);
		}
		if ('' !== $hours) {
			$rows[] = self::row('hours', __('Support hours', 'mhm-rentiva'), $hours);
		}

		return $rows;
	}

	/**
	 * @return array{key:string,label:string,value:string,href:string,note:string}
	 */
	private static function row(string $key, string $label, string $value, string $href = '', string $note = ''): array
	{
		return array(
			'key'   => $key,
			'label' => $label,
			'value' => $value,
			'href'  => $href,
			'note'  => $note,
		);
	}

	/**
	 * R6: `html` carries the link when there is a privacy page; escaped here, printed as markup.
	 *
	 * @return array{enabled:bool,url:string,text:string,html:string}
	 */
	private static function consent(bool $enabled, string $url): array
	{
		$text = __('I have read the privacy notice.', 'mhm-rentiva');
		$html = esc_html($text);
		if ('' !== $url) {
			$html = sprintf(
				/* translators: %s: privacy policy page URL. */
				__('I have read the <a href="%s" target="_blank" rel="noopener">privacy notice</a>.', 'mhm-rentiva'),
				esc_url($url)
			);
		}

		return array(
			'enabled' => $enabled,
			'url'     => $url,
			'text'    => $text,
			'html'    => $html,
		);
	}

	/**
	 * R18: the types the server really takes, not a shortened list.
	 *
	 * @param array<int,string> $types Extensions.
	 * @return array{accept:string,types:string,max_bytes:int,max_label:string,help:string}
	 */
	private static function attachment(array $types, int $max_bytes): array
	{
		$names = implode(', ', array_map('strtoupper', $types));
		$label = (string) size_format($max_bytes);

		return array(
			'accept'    => implode(',', array_map(static fn(string $ext): string => '.' . $ext, $types)),
			'types'     => $names,
			'max_bytes' => $max_bytes,
			'max_label' => $label,
			'help'      => sprintf(
				/* translators: 1: file types, 2: largest file size. */
				__('%1$s, up to %2$s', 'mhm-rentiva'),
				$names,
				$label
			),
		);
	}

	/**
	 * Every id of one instance, so two forms on a page never collide.
	 *
	 * @return array<string,string>
	 */
	private static function ids(string $uid): array
	{
		$ids = array();
		foreach (array( 'form', 'name', 'email', 'phone', 'company', 'vehicle', 'booking', 'preferred_date', 'priority', 'rating', 'message', 'attachment', 'consent', 'honeypot', 'status' ) as $key) {
			$ids[ $key ] = $uid . '-' . str_replace('_', '-', $key);
		}

		return $ids;
	}
}
