/**
 * Builds the plain-text system report shown on the Developer tab.
 *
 * Composed entirely from the /about REST payload the page already fetched —
 * no extra endpoint. Row labels arrive localized from the backend, so the
 * report follows the admin language; the frame markers stay fixed so the
 * support team can recognize the format at a glance.
 */

const formatRow = ( row ) => {
	// Backend labels are inconsistent about trailing colons ("Versiyon:" vs
	// "Ad") — normalize so the report never prints a double colon.
	const label = String( row.label ).replace( /:\s*$/, '' );
	if ( row.boolean ) {
		return `${ label }: ${ row.value ? 'yes' : 'no' }`;
	}
	return `${ label }: ${ row.value }${ row.suffix ?? '' }`;
};

const section = ( title, rows ) => {
	if ( ! Array.isArray( rows ) || rows.length === 0 ) {
		return [];
	}
	return [ `## ${ title }`, ...rows.map( formatRow ), '' ];
};

export default function buildSystemReport( data ) {
	const lines = [ '### MHM Rentiva System Report', '' ];

	const general = data?.general ?? {};
	const system  = data?.system ?? {};

	lines.push( ...section( 'Plugin', general.plugin_info ) );
	lines.push( ...section( 'Compatibility', general.compatibility ) );
	lines.push( ...section( 'Statistics', general.stats ) );
	lines.push( ...section( 'WordPress', system.wordpress ) );
	lines.push( ...section( 'PHP', system.php ) );
	lines.push( ...section( 'Plugin Environment', system.plugin ) );
	lines.push( ...section( 'Database', system.database ) );

	return lines.join( '\n' ).trimEnd() + '\n';
}
