import { __ } from '@wordpress/i18n';
import { fmtMoney } from '../../../shared/format';

export default function PaymentsSummary( { summary, currency } ) {
	const rows = [
		{ label: __( 'Pending payment', 'mhm-rentiva' ),  value: summary?.pending_total,  tone: 'warn' },
		{ label: __( 'Deposit (held)', 'mhm-rentiva' ),   value: summary?.deposit_blocked, tone: 'neutral' },
	];
	return (
		<div className="mhm-widget rv-pay-summary">
			<h3><span className="dashicons dashicons-money-alt" />{ __( 'Payments', 'mhm-rentiva' ) }</h3>
			<div className="rv-pay-summary__list">
				{ rows.map( ( r ) => (
					<div key={ r.label } className="rv-pay-summary__row">
						<span className="rv-pay-summary__label">{ r.label }</span>
						<span className={ `rv-pay-summary__value rv-pay-summary__value--${ r.tone }` }>
							{ fmtMoney( r.value ?? 0, currency, 0 ) }
						</span>
					</div>
				) ) }
			</div>
		</div>
	);
}
