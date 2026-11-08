/**
 * @jest-environment jsdom
 */
/**
 * Backslash class browser round (2026-11-08): a blocked-day note with a double
 * quote was drawn into `value="..."` by string concatenation; jQuery's .html()
 * escapes < > & but not ", so the input showed the note cut at the quote --
 * and the next Update read that input back and saved the cut note.
 */
describe( 'blocked dates: a note with a quote is drawn whole', () => {
	const NOTE = 'Bakım günü — "fren" kontrolü';

	const mount = async ( placeholder ) => {
		jest.resetModules();
		const $ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		const fp = () => ( { setDate() {}, clear() {}, destroy() {}, selectedDates: [] } );
		fp.formatDate = ( d ) => d.toISOString().slice( 0, 10 );
		global.flatpickr = fp;
		window.flatpickr = fp;
		window.mhmBlockedDatesL10n = { notePlaceholder: placeholder };
		document.body.innerHTML = `
			<form id="post">
				<div id="mhmrentiva_blocked_dates_picker"></div>
				<span id="mhm-blocked-count-badge"><span id="mhm-blocked-count-num"></span></span>
				<button type="button" id="mhm-clear-all-blocked"></button>
				<div id="mhm-blocked-dates-chips"><div id="mhm-blocked-empty"></div></div>
				<input type="hidden" id="mhmrentiva_blocked_dates_value" value='["2099-12-01"]'>
				<input type="hidden" id="mhmrentiva_blocked_dates_notes_value">
			</form>`;
		$( '#mhmrentiva_blocked_dates_notes_value' ).val( JSON.stringify( { '2099-12-01': NOTE } ) );
		require( '../../assets/js/admin/blocked-dates.js' );
		await new Promise( ( r ) => $( r ) );
		return $;
	};

	it( 'puts the whole note in the input', async () => {
		const $ = await mount( 'Not ekle' );
		expect( $( '.blocked-date-chip-note[data-date="2099-12-01"]' ).val() ).toBe( NOTE );
	} );

	it( 'keeps a placeholder with a quote whole', async () => {
		const $ = await mount( 'Not "isteğe bağlı"' );
		expect( $( '.blocked-date-chip-note' ).attr( 'placeholder' ) ).toBe( 'Not "isteğe bağlı"' );
	} );
} );
