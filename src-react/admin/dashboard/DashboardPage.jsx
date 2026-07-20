import { __ } from '@wordpress/i18n';
import StatsCards         from './components/StatsCards';
import QuickActions       from './components/QuickActions';
import RevenueChart       from './components/RevenueChart';
import RecentBookings     from './components/RecentBookings';
import UpcomingOperations from './components/UpcomingOperations';
import TransferWidget     from './components/TransferWidget';
import PendingPayments    from './components/PendingPayments';

export default function DashboardPage() {
	const data = window.mhmRentivaDashboard ?? {};

	const {
		metrics,
		revenue_data:                  revenueData,
		recent_bookings:               recentBookings       = [],
		recent_bookings_total_pages:   recentBookingsTotalPages = 1,
		transfer_stats:                transferStats,
		recent_transfers:              recentTransfers      = [],
		recent_transfers_total_pages:  recentTransfersTotalPages = 1,
		pending_payments:              pendingPayments,
		upcoming_initial:              upcomingInitial,
		currency  = '',
		admin_url: adminUrl = '',
		caps                = {},
	} = data;

	const bookingsInitial = {
		items:       recentBookings,
		total_pages: recentBookingsTotalPages,
		page:        1,
	};

	const transfersInitial = {
		items:       recentTransfers,
		total_pages: recentTransfersTotalPages,
		page:        1,
	};

	return (
		<div className="mhm-dashboard">

			{ /* Row 1: Stats — 4-col gradient cards */ }
			<div className="mhm-dashboard__row mhm-dashboard__row--1">
				<StatsCards metrics={ metrics } currency={ currency } />
			</div>

			{ /* Row 2: Quick Actions (left) + Upcoming Operations (right) */ }
			<div className="mhm-dashboard__row mhm-dashboard__row--2">
				<QuickActions adminUrl={ adminUrl } caps={ caps } />
				<UpcomingOperations initial={ upcomingInitial } />
			</div>

			{ /* Row 3: Recent Bookings (left) + Transfer Summary (right, Pro only) — symmetric KPI.
			     Lite localizes no transfer_stats (Task A5b seam inversion); Pro's
			     mhm_rentiva_dashboard_localize subscriber adds it back. */ }
			<div className={ `mhm-dashboard__row mhm-dashboard__row--3${ transferStats ? '' : ' mhm-dashboard__row--3-solo' }` }>
				<RecentBookings
					initial={ bookingsInitial }
					metrics={ metrics }
					currency={ currency }
					adminUrl={ adminUrl }
				/>
				{ transferStats && (
					<TransferWidget
						initial={ transfersInitial }
						stats={ transferStats }
						currency={ currency }
						adminUrl={ adminUrl }
					/>
				) }
			</div>

			{ /* Row 4: Pending Payments (left) + Revenue Chart (right) */ }
			<div className="mhm-dashboard__row mhm-dashboard__row--4">
				<PendingPayments payments={ pendingPayments } currency={ currency } adminUrl={ adminUrl } />
				<RevenueChart    revenueData={ revenueData }  currency={ currency } />
			</div>

		</div>
	);
}
