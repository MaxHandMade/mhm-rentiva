import { __ } from '@wordpress/i18n';
import OverviewTab  from './tabs/OverviewTab';
import RevenueTab   from './tabs/RevenueTab';
import BookingsTab  from './tabs/BookingsTab';
import VehiclesTab  from './tabs/VehiclesTab';
import CustomersTab from './tabs/CustomersTab';

const TAB_COMPONENTS = {
	overview:  OverviewTab,
	revenue:   RevenueTab,
	bookings:  BookingsTab,
	vehicles:  VehiclesTab,
	customers: CustomersTab,
};

export default function ReportContent( { tab, data, loading, error, currency } ) {
	if ( loading ) {
		return <div className="mhm-reports__loading">{ __( 'Loading…', 'mhm-rentiva' ) }</div>;
	}
	if ( error ) {
		return (
			<div className="mhm-reports__error">
				{ __( 'Failed to load report data.', 'mhm-rentiva' ) }
			</div>
		);
	}
	if ( ! data ) {
		return null;
	}

	const Component = TAB_COMPONENTS[ tab ];
	return Component ? <Component data={ data } currency={ currency } /> : null;
}
