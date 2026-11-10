<?php
/**
 * The one answer to "has this customer consented to data processing?".
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two user metas have carried this answer: the My Account consent card writes
 * CARD_KEY, and a registration-time consent writes REGISTRATION_KEY. The booking
 * gate once read only REGISTRATION_KEY -- which no form sets to '1' -- so with
 * "data consent required" on, a customer who pressed Accept still could not book.
 *
 * Every reader asks is_given(); every writer goes through record() or
 * withdraw(). Either key counts, so a consent already stored under one of them
 * keeps counting; withdrawal clears both, so neither can bring it back.
 */
final class ConsentState {

	public const CARD_KEY         = 'mhmrentiva_gdpr_consent_given';
	public const REGISTRATION_KEY = 'mhmrentiva_data_consent_given';

	private const DATE_KEY            = 'mhmrentiva_gdpr_consent_date';
	private const WITHDRAWN_KEY       = 'mhmrentiva_gdpr_consent_withdrawn';
	private const WITHDRAWAL_DATE_KEY = 'mhmrentiva_gdpr_consent_withdrawal_date';

	public static function is_given( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		return '1' === get_user_meta( $user_id, self::CARD_KEY, true )
			|| '1' === get_user_meta( $user_id, self::REGISTRATION_KEY, true );
	}

	public static function record( int $user_id ): void {
		update_user_meta( $user_id, self::CARD_KEY, '1' );
		update_user_meta( $user_id, self::DATE_KEY, current_time( 'mysql' ) );
		delete_user_meta( $user_id, self::WITHDRAWN_KEY );
	}

	public static function withdraw( int $user_id ): void {
		delete_user_meta( $user_id, self::CARD_KEY );
		update_user_meta( $user_id, self::REGISTRATION_KEY, '0' );
		update_user_meta( $user_id, self::WITHDRAWN_KEY, '1' );
		update_user_meta( $user_id, self::WITHDRAWAL_DATE_KEY, current_time( 'mysql' ) );
	}
}
