import { __ } from '@wordpress/i18n';

export default function TransferWidget( { transferStats, currency } ) {
	const s = transferStats ?? {};

	return (
		<div className="mhm-widget mhm-transfer-widget">
			<h3>{ __( 'Transfer Summary', 'mhm-rentiva' ) }</h3>
			<ul>
				<li>
					{ __( 'Total transfers:', 'mhm-rentiva' ) }{ ' ' }
					<strong>{ Number( s.total ?? 0 ).toLocaleString() }</strong>
				</li>
				<li>
					{ __( 'This month:', 'mhm-rentiva' ) }{ ' ' }
					<strong>{ Number( s.monthly ?? 0 ).toLocaleString() }</strong>
				</li>
				<li>
					{ __( 'Transfer revenue:', 'mhm-rentiva' ) }{ ' ' }
					<strong>{ currency }{ Number( s.revenue ?? 0 ).toFixed( 2 ) }</strong>
				</li>
			</ul>
		</div>
	);
}
