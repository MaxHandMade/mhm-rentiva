/**
 * RFC 6068 (https://www.rfc-editor.org/rfc/rfc6068): a mailto URI's `to`
 * component is an addr-spec -- local part, a literal `@`, then domain -- not
 * an opaque string to percent-encode whole (that would also escape the `@`
 * itself, producing an address no mail client recognises). `?`, `&`, `%`, `=`
 * in the LOCAL part must still be percent-encoded, or an address like
 * `a?cc=x@evil.com&y=@site` adds a CC recipient; the server also marks such
 * addresses non-linkable, which is the second line of defence. The domain is
 * encoded separately and the split is on the LAST `@` (a valid local part may
 * itself contain `@` only when quoted, which this project's addresses never
 * are; splitting on the last `@` still finds the real separator).
 *
 * @param {string} email   Recipient address.
 * @param {string} subject Reply subject line.
 * @return {string} A `mailto:` URI with a literal `@` between the encoded
 *                  local part and the encoded domain, or '' when `email` has
 *                  no local part to address (no `@`, or `@` in the first
 *                  position) -- a fabricated `mailto:` for an address that was
 *                  never valid is worse than no link at all.
 */
export function buildMailto( email, subject ) {
	const at = email.lastIndexOf( '@' );
	if ( at < 1 ) {
		return '';
	}
	const local = email.slice( 0, at );
	const domain = email.slice( at + 1 );
	return `mailto:${ encodeURIComponent( local ) }@${ encodeURIComponent( domain ) }?subject=${ encodeURIComponent( subject ) }`;
}
