/**
 * RFC 6068: `?`, `&`, `%`, `=` in the address must be percent-encoded, or an
 * address like `a?cc=x@evil.com&y=@site` adds a CC recipient. The server also
 * marks such addresses non-linkable; this is the second line.
 *
 * @param {string} email   Recipient address.
 * @param {string} subject Reply subject line.
 * @return {string} A `mailto:` URI with both parts percent-encoded.
 */
export function buildMailto( email, subject ) {
	return `mailto:${ encodeURIComponent( email ) }?subject=${ encodeURIComponent( subject ) }`;
}
