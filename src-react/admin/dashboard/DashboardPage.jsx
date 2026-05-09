import { __ } from '@wordpress/i18n';
import StatsCards         from './components/StatsCards';
import QuickActions       from './components/QuickActions';
import RevenueChart       from './components/RevenueChart';
import RecentBookings     from './components/RecentBookings';
import UpcomingOperations from './components/UpcomingOperations';
import TransferWidget     from './components/TransferWidget';
import PendingPayments    from './components/PendingPayments';

const DEFAULT_ORDER = [
	'quick-actions',
	'upcoming-operations',
	'transfer-widget',
	'recent-bookings',
	'pending-payments',
	'revenue-chart',
];

export default function DashboardPage() {
	const data = window.mhmRentivaDashboard ?? {};

	const {
		metrics,
		revenue_data:    revenueData,
		recent_bookings: recentBookings,
		pending_payments: pendingPayments,
		transfer_stats:  transferStats,
		upcoming_initial: upcomingInitial,
		currency  = '',
		admin_url: adminUrl = '',
	} = data;

	const order = data.widget_order?.length ? data.widget_order : DEFAULT_ORDER;

	const widgets = {
		'quick-actions':       <QuickActions       adminUrl={ adminUrl } />,
		'upcoming-operations': <UpcomingOperations initial={ upcomingInitial } />,
		'transfer-widget':     <TransferWidget     transferStats={ transferStats } currency={ currency } />,
		'recent-bookings':     <RecentBookings     bookings={ recentBookings } adminUrl={ adminUrl } />,
		'pending-payments':    <PendingPayments    payments={ pendingPayments } adminUrl={ adminUrl } />,
		'revenue-chart':       <RevenueChart       revenueData={ revenueData } currency={ currency } />,
	};

	return (
		<div className="mhm-dashboard">
			<StatsCards metrics={ metrics } currency={ currency } />
			<div className="mhm-dashboard__widgets">
				{ order.map( ( key ) =>
					widgets[ key ]
						? <div key={ key } className="mhm-dashboard__widget">{ widgets[ key ] }</div>
						: null
				) }
			</div>
		</div>
	);
}
