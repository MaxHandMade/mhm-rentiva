import { __ } from '@wordpress/i18n';
import StatsCards       from './components/StatsCards';
import QuickActions     from './components/QuickActions';
import RevenueChart     from './components/RevenueChart';
import RecentBookings   from './components/RecentBookings';
import UpcomingOperations from './components/UpcomingOperations';
import StatusBreakdown  from './components/StatusBreakdown';
import PaymentsSummary  from './components/PaymentsSummary';

export default function DashboardPage() {
	const data = window.mhmRentivaDashboard ?? {};

	const {
		metrics,
		metric_deltas:                metricDeltas         = {},
		revenue_data:                 revenueData,
		recent_bookings:              recentBookings       = [],
		recent_bookings_total_pages:  recentBookingsTotalPages = 1,
		upcoming:                     upcoming             = [],
		upcoming_total_pages:         upcomingTotalPages   = 1,
		quick_actions_extra:          quickActionsExtra    = [],
		status_breakdown:             statusBreakdown      = [],
		payments_summary:             paymentsSummary,
		currency  = '',
		admin_url: adminUrl = '',
	} = data;

	const bookingsInitial = { items: recentBookings, total_pages: recentBookingsTotalPages, page: 1 };
	const upcomingInitial = { items: upcoming, total_pages: upcomingTotalPages, page: 1 };

	return (
		<div className="mhm-dashboard rv-dashboard mhmui-admin mhmui-admin-page">

			{ /* The page title is printed by PHP above this root
			   (DashboardPage::render()), so admin notices sit under it. */ }
			{ /* Stat cards */ }
			<div className="mhm-dashboard__row mhm-dashboard__row--1 mhmui-section">
				<StatsCards metrics={ metrics } deltas={ metricDeltas } currency={ currency } />
			</div>

			{ /* Two columns: left (chart + bookings) / right (actions + what is coming next + summaries) */ }
			<div className="rv-dash-cols mhmui-section">
				<div className="rv-dash-cols__left">
					<RevenueChart revenueData={ revenueData } currency={ currency } />
					<RecentBookings
						initial={ bookingsInitial }
						metrics={ metrics }
						currency={ currency }
						adminUrl={ adminUrl }
					/>
				</div>

				<div className="rv-dash-cols__right">
					<QuickActions adminUrl={ adminUrl } extra={ quickActionsExtra } />
					<UpcomingOperations initial={ upcomingInitial } />
					<StatusBreakdown items={ statusBreakdown } />
					<PaymentsSummary summary={ paymentsSummary } currency={ currency } />
				</div>
			</div>

			{ /* Footer */ }
			<div className="rv-dash-footer mhmui-section">
				<span>{ __( 'MHM Rentiva', 'mhm-rentiva' ) }</span>
				<span>{ __( 'Built with WordPress.', 'mhm-rentiva' ) }</span>
			</div>
		</div>
	);
}
