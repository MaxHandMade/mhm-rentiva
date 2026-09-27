/**
 * Whether the site keeps a trash (EMPTY_TRASH_DAYS > 0).
 *
 * wp_localize_script() casts every scalar to a string, so the PHP side sends
 * '1' / '0' and a bare `false` would arrive as ''. Accept all of those shapes;
 * an absent global means core's default (trash on). Call it inside components:
 * a module-level read runs before a test can set the global.
 *
 * @return {boolean} True when "Move to trash" is a real, recoverable action.
 */
export function isTrashEnabled() {
	const value = window.mhmRentivaContactMessages?.trashEnabled;
	return ! ( value === false || value === '' || value === '0' || value === 0 );
}
