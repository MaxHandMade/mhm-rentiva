import { __ } from '@wordpress/i18n';
import ChangelogList from './ChangelogList';

export default function SupportTab( { data } ) {
	return (
		<div className="mhm-about-support">
			<div className="mhm-about-support-cards">

				<div className="mhm-widget mhm-support-card">
					<h3>{ __( 'Documentation', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Detailed user guides, video tutorials and API documentation.', 'mhm-rentiva' ) }</p>
					<div className="mhm-support-links">
						<a href={ data.links.docs }     target="_blank" rel="noreferrer" className="button button-secondary">{ __( 'User Guide', 'mhm-rentiva' ) }</a>
						<a href={ data.links.api_docs } target="_blank" rel="noreferrer" className="button button-secondary">{ __( 'API Documentation', 'mhm-rentiva' ) }</a>
						<a href={ data.links.youtube }  target="_blank" rel="noreferrer" className="button button-secondary">{ __( 'Video Tutorials', 'mhm-rentiva' ) }</a>
					</div>
				</div>

				<div className="mhm-widget mhm-support-card">
					<h3>{ __( 'Support Channels', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Contact us for your questions.', 'mhm-rentiva' ) }</p>
					<div className="mhm-support-links">
						<a href={ data.links.contact_form } target="_blank" rel="noreferrer" className="button button-primary">{ __( 'Contact Form', 'mhm-rentiva' ) }</a>
						{ data.is_pro && (
							<a href={ `mailto:${ data.support_email }` } className="button button-secondary">{ __( 'Priority Support', 'mhm-rentiva' ) }</a>
						) }
					</div>
					<div className="mhm-contact-info">
						<p><strong>{ __( 'Email:', 'mhm-rentiva' ) }</strong> { data.support_email }</p>
						<p><strong>{ __( 'Phone:', 'mhm-rentiva' ) }</strong> { data.phone }</p>
					</div>
				</div>

				<div className="mhm-widget mhm-support-card">
					<h3>{ __( 'Community', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Share your experiences with other users.', 'mhm-rentiva' ) }</p>
					<div className="mhm-support-links">
						<a href={ data.links.wp_forum } target="_blank" rel="noreferrer" className="button button-secondary">{ __( 'WordPress Support Forum', 'mhm-rentiva' ) }</a>
					</div>
				</div>

				<div className="mhm-widget mhm-support-card">
					<h3>{ __( 'Bug Reports & Feature Requests', 'mhm-rentiva' ) }</h3>
					<p>{ __( 'Use GitHub Issues to report bugs or suggest new features.', 'mhm-rentiva' ) }</p>
					<div className="mhm-support-links">
						<a href={ data.links.github_issues } target="_blank" rel="noreferrer" className="button button-secondary dashicons-before dashicons-warning">{ __( 'GitHub Issues', 'mhm-rentiva' ) }</a>
					</div>
				</div>

			</div>

			<div className="mhm-widget mhm-about-changelog">
				<h3>{ __( 'Version History', 'mhm-rentiva' ) }</h3>
				<ChangelogList items={ data.changelog } />
			</div>
		</div>
	);
}
