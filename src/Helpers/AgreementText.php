<?php
declare(strict_types=1);

namespace MHMRentiva\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An agreement stored as plain text, shown with light formatting.
 *
 * Agreements are often pasted with light Markdown. Everything is escaped
 * first; then only these shapes become markup: `#`/`##`/`###` headings,
 * `**bold**`, a `---` rule and `- ` / `* ` list items. Other lines are
 * paragraphs, single line breaks kept. A long line starting with `#` (an
 * agreement pasted without line breaks) stays a paragraph.
 */
final class AgreementText {

	/**
	 * The markup to_html() produces; nothing else, no attributes.
	 */
	public const TAGS = array(
		'h3'     => array(),
		'h4'     => array(),
		'h5'     => array(),
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'ul'     => array(),
		'li'     => array(),
		'hr'     => array(),
	);

	/**
	 * A line longer than this that starts with `#` is not a heading.
	 */
	private const MAX_HEADING_LENGTH = 150;

	public static function to_html( string $text ): string {
		$html  = '';
		$block = '';      // 'p' or 'ul' while one is open.
		$lines = array(); // The open block's lines, already formatted.

		// UTF-8 mode: without /u, \R matches the 0x85 byte inside characters
		// such as Cyrillic "х" or Polish "ą" and splits them.
		foreach ( (array) preg_split( '/\R/u', trim( $text ) ) as $raw ) {
			$line = trim( (string) $raw );
			$next = '';
			$add  = '';
			$out  = '';

			if ( '' === $line ) {
				$next = '';
			} elseif ( preg_match( '/^#{1,6}\s+(.+)$/u', $line, $m ) && mb_strlen( $line ) > self::MAX_HEADING_LENGTH ) {
				$next = 'p';
				$add  = self::inline( $m[1] );
			} elseif ( preg_match( '/^(#{1,6})\s+(.+)$/u', $line, $m ) ) {
				$tag = 'h' . min( 5, strlen( $m[1] ) + 2 );
				$out = '<' . $tag . '>' . self::inline( rtrim( $m[2], ' #' ) ) . '</' . $tag . '>';
			} elseif ( preg_match( '/^([-*_])(\s*\1){2,}$/u', $line ) ) {
				$out = '<hr>';
			} elseif ( preg_match( '/^[-*•]\s+(.+)$/u', $line, $m ) ) {
				$next = 'ul';
				$add  = self::inline( $m[1] );
			} else {
				$next = 'p';
				$add  = self::inline( $line );
			}

			if ( $next !== $block ) {
				$html .= self::block( $block, $lines );
				$block = $next;
				$lines = array();
			}
			if ( '' !== $add ) {
				$lines[] = $add;
			}
			$html .= $out;
		}

		return $html . self::block( $block, $lines );
	}

	/**
	 * One escaped line with **bold** turned into <strong>.
	 */
	private static function inline( string $line ): string {
		return (string) preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', esc_html( $line ) );
	}

	/**
	 * One paragraph (lines joined by <br>) or one list.
	 *
	 * @param list<string> $lines Formatted lines.
	 */
	private static function block( string $block, array $lines ): string {
		if ( array() === $lines ) {
			return '';
		}

		return 'ul' === $block
			? '<ul><li>' . implode( '</li><li>', $lines ) . '</li></ul>'
			: '<p>' . implode( '<br>', $lines ) . '</p>';
	}
}
