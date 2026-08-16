import { __ } from '@wordpress/i18n';
import ChangelogList from './ChangelogList';

export default function SupportTab( { data } ) {
	return (
		<div className="mhm-about-support">
			<div className="mhm-about-support-cards">

				<div className="mhm-widget rv-abt-card mhm-support-card">
					<h3>{ __( 'Documentation', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Detailed user guides, video tutorials and API documentation.', 'mhm-rentiva' ) }</p>
					<div className="rv-abt-links">
						<a href={ data.links.docs }     target="_blank" rel="noreferrer" className="rv-abt-link">{ __( 'User Guide', 'mhm-rentiva' ) }</a>
						<a href={ data.links.api_docs } target="_blank" rel="noreferrer" className="rv-abt-link">{ __( 'API Documentation', 'mhm-rentiva' ) }</a>
						<a href={ data.links.youtube }  target="_blank" rel="noreferrer" className="rv-abt-link">{ __( 'Video Tutorials', 'mhm-rentiva' ) }</a>
					</div>
				</div>

				<div className="mhm-widget rv-abt-card mhm-support-card">
					<h3>{ __( 'Support Channels', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Contact us for your questions.', 'mhm-rentiva' ) }</p>
					<div className="rv-abt-links">
						<a href={ data.links.contact_form } target="_blank" rel="noreferrer" className="rv-abt-link is-primary">{ __( 'Contact Form', 'mhm-rentiva' ) }</a>
					</div>
					<div className="mhm-contact-info">
						<p><strong>{ __( 'Email:', 'mhm-rentiva' ) }</strong> { data.support_email }</p>
						<p><strong>{ __( 'Phone:', 'mhm-rentiva' ) }</strong> { data.phone }</p>
					</div>
				</div>

				<div className="mhm-widget rv-abt-card mhm-support-card">
					<h3>{ __( 'Community', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Share your experiences with other users.', 'mhm-rentiva' ) }</p>
					<div className="rv-abt-links">
						<a href={ data.links.wp_forum } target="_blank" rel="noreferrer" className="rv-abt-link">{ __( 'WordPress Support Forum', 'mhm-rentiva' ) }</a>
					</div>
				</div>

				<div className="mhm-widget rv-abt-card mhm-support-card">
					<h3>{ __( 'Bug Reports & Feature Requests', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Use GitHub Issues to report bugs or suggest new features.', 'mhm-rentiva' ) }</p>
					<div className="rv-abt-links">
						<a href={ data.links.github_issues } target="_blank" rel="noreferrer" className="rv-abt-link">{ __( 'GitHub Issues', 'mhm-rentiva' ) }</a>
					</div>
				</div>

				<div className="mhm-widget rv-abt-card mhm-support-card">
					<h3>{ __( 'Tests & Verification', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Every release ships with automated PHPUnit coverage and static analysis gates.', 'mhm-rentiva' ) }</p>
					<p className="mhm-support-card-meta">{ __( 'Tested with: WP 7.0, PHP 8.2', 'mhm-rentiva' ) }</p>
				</div>

			</div>

			<div className="mhm-widget rv-abt-card mhm-about-changelog">
				<h3>{ __( 'Version History', 'mhm-rentiva' ) }</h3>
				<ChangelogList items={ data.changelog } />
			</div>
		</div>
	);
}
