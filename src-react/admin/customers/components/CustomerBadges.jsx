/**
 * Badges an add-on reports for a customer (Pro: "Vendor", linking to the
 * vendor's page). PHP has already checked the shape and reduced `url` to
 * http(s) or '' (CustomerBadges::for_user()); a badge with no URL is plain text.
 *
 * Clicks stop here: inside a table row, the row's own click opens the panel.
 */
export default function CustomerBadges( { badges } ) {
	if ( ! Array.isArray( badges ) || badges.length === 0 ) {
		return null;
	}

	return (
		<span className="rv-cust-badges">
			{ badges.map( ( b ) =>
				b.url ? (
					<a
						key={ b.key }
						className={ `rv-cust-tag rv-cust-badge is-badge-${ b.key }` }
						href={ b.url }
						onClick={ ( e ) => e.stopPropagation() }
					>
						{ b.label }
					</a>
				) : (
					<span key={ b.key } className={ `rv-cust-tag rv-cust-badge is-badge-${ b.key }` }>
						{ b.label }
					</span>
				)
			) }
		</span>
	);
}
