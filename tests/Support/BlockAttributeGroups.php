<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Blocks\BlockRegistry;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use MHMRentiva\Core\Attribute\Transformers;
use ReflectionMethod;
use RuntimeException;
use WP_Block_Type_Registry;

/**
 * Derives, from the live block registry, which block attributes land on the same
 * canonical shortcode attribute -- and probes whether an editor value survives the
 * block render path. Shared by the Lite and Pro suites (Pro require_once's it).
 *
 * Why derived rather than listed: two declared attributes that normalize to one
 * canonical key silently cancel each other (WP appends the unsaved one at its
 * default after the saved one, CAM keeps the later). A hand-kept list would miss
 * the next pair.
 */
final class BlockAttributeGroups
{
	/** Attributes core adds to every block type through `supports`; never schema keys. */
	public const SUPPORT_ATTRIBUTES = array(
		'style',
		'className',
		'lock',
		'metadata',
		'align',
		'anchor',
		'backgroundColor',
		'textColor',
		'gradient',
		'fontSize',
		'fontFamily',
	);

	/**
	 * Registered `mhm-rentiva/*` block slugs mapped to their shortcode tag.
	 *
	 * Starts from the registry, not from the config map: a registered block that
	 * the config does not know would otherwise be invisible to every gate.
	 *
	 * @param string[]|null $only_slugs Restrict to these slugs.
	 * @return array<string,string> slug => tag
	 */
	public static function block_tags(?array $only_slugs = null): array
	{
		$config = self::block_config();
		$tags   = array();

		foreach (array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()) as $name) {
			if (0 !== strpos($name, 'mhm-rentiva/')) {
				continue;
			}
			$slug = substr($name, strlen('mhm-rentiva/'));
			if (null !== $only_slugs && ! in_array($slug, $only_slugs, true)) {
				continue;
			}
			if (empty($config[ $slug ]['tag'])) {
				throw new RuntimeException(sprintf('Registered block "%s" has no shortcode tag in the block config.', $name));
			}
			$tags[ $slug ] = (string) $config[ $slug ]['tag'];
		}

		ksort($tags);
		return $tags;
	}

	/**
	 * @return array<string,array<string,mixed>> Registered attribute definitions minus supports.
	 */
	public static function attributes(string $slug): array
	{
		$type = WP_Block_Type_Registry::get_instance()->get_registered('mhm-rentiva/' . $slug);
		if (null === $type) {
			throw new RuntimeException(sprintf('Block "mhm-rentiva/%s" is not registered.', $slug));
		}

		return array_diff_key((array) $type->attributes, array_flip(self::SUPPORT_ATTRIBUTES));
	}

	/**
	 * @return array<string,string[]> canonical => attribute keys
	 */
	public static function groups(string $slug): array
	{
		$schema = AllowlistRegistry::get_schema(self::block_tags(array( $slug ))[ $slug ]);
		$groups = array();
		foreach (array_keys(self::attributes($slug)) as $key) {
			$groups[ KeyNormalizer::normalize((string) $key, $schema) ][] = (string) $key;
		}
		return $groups;
	}

	/**
	 * @param string[]|null $only_slugs
	 * @return array<string,string[]> "slug:canonical" => attribute keys, only groups of 2+.
	 */
	public static function collisions(?array $only_slugs = null): array
	{
		$out = array();
		foreach (array_keys(self::block_tags($only_slugs)) as $slug) {
			foreach (self::groups($slug) as $canonical => $keys) {
				if (count($keys) > 1) {
					$out[ $slug . ':' . $canonical ] = $keys;
				}
			}
		}
		return $out;
	}

	/**
	 * A value the editor could save for $key that survives both block.json validation
	 * and the CAM transform, and that differs from what the default produces.
	 *
	 * @return array{value:mixed,expected:string,canonical:string}|null Null when no
	 *         editor-valid value can be told apart from the default.
	 */
	public static function probe(string $slug, string $key): ?array
	{
		$tag       = self::block_tags(array( $slug ))[ $slug ];
		$schema    = AllowlistRegistry::get_schema($tag);
		$canonical = KeyNormalizer::normalize($key, $schema);
		$def       = self::attributes($slug)[ $key ] ?? null;
		if (null === $def || ! isset($schema[ $canonical ])) {
			return null;
		}

		$config   = $schema[ $canonical ];
		$default  = $def['default'] ?? '';
		$baseline = self::render_value(Transformers::transform($default, $config['type'] ?? 'string', $config));

		foreach (self::candidates($key, $def, $config) as $candidate) {
			$out = self::render_value(Transformers::transform($candidate, $config['type'] ?? 'string', $config));
			if (null !== $out && $out !== $baseline) {
				return array(
					'value'     => $candidate,
					'expected'  => $out,
					'canonical' => $canonical,
				);
			}
		}

		return null;
	}

	/**
	 * Attributes the shortcode callback received for one block render.
	 *
	 * The callback is swapped, not filtered: the HTML cache returns before the
	 * `_html` filter runs, and the swap needs no rendering side effects.
	 *
	 * @param array<string,mixed> $attrs Saved block attributes.
	 * @return array<string,mixed>
	 */
	public static function captured_atts(string $slug, array $attrs): array
	{
		global $shortcode_tags;

		$tag      = self::block_tags(array( $slug ))[ $slug ];
		$original = $shortcode_tags[ $tag ] ?? null;
		$received = null;

		$shortcode_tags[ $tag ] = static function ($atts) use (&$received) {
			$received = $atts;
			return '';
		};
		try {
			render_block(
				array(
					'blockName'    => 'mhm-rentiva/' . $slug,
					'attrs'        => $attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		} finally {
			if (null === $original) {
				unset($shortcode_tags[ $tag ]);
			} else {
				$shortcode_tags[ $tag ] = $original;
			}
		}

		if (! is_array($received)) {
			throw new RuntimeException(sprintf('The "%s" shortcode callback was never reached.', $tag));
		}

		return $received;
	}

	/**
	 * The full payload the editor preview sends: every registered attribute at its
	 * default, with $overrides applied.
	 *
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	public static function full_payload(string $slug, array $overrides): array
	{
		$payload = array();
		foreach (self::attributes($slug) as $key => $def) {
			if (array_key_exists('default', $def)) {
				$payload[ $key ] = $def['default'];
			}
		}
		return array_merge($payload, $overrides);
	}

	/**
	 * @param array<string,mixed> $def    block.json attribute definition.
	 * @param array<string,mixed> $config CAM schema entry.
	 * @return array<int,mixed>
	 */
	private static function candidates(string $key, array $def, array $config): array
	{
		$default = $def['default'] ?? null;
		$type    = $def['type'] ?? 'string';

		if ('boolean' === $type) {
			return array( ! (bool) $default );
		}
		if (! empty($def['enum'])) {
			return array_values(array_filter($def['enum'], static fn($v) => $v !== $default));
		}
		if (in_array($config['type'] ?? '', array( 'int', 'float' ), true)) {
			$next = ( '' === (string) $default ) ? 3 : ( (int) $default + 1 );
			return array( 'string' === $type ? (string) $next : $next );
		}
		if (! empty($config['values'])) {
			return array_values(array_filter($config['values'], static fn($v) => '' !== $v && $v !== $default));
		}
		if ('calendarHeight' === $key) {
			return array( '600px' );
		}
		return array( 't1b-probe' );
	}

	/**
	 * Mirrors BlockRegistry::render_callback(): booleans become '1'/'0', other
	 * scalars are cast to string, non-scalars are dropped.
	 *
	 * @param mixed $value
	 */
	private static function render_value($value): ?string
	{
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		return is_scalar($value) ? (string) $value : null;
	}

	/** @return array<string,array<string,mixed>> */
	private static function block_config(): array
	{
		$method = new ReflectionMethod(BlockRegistry::class, 'get_block_config');
		$method->setAccessible(true);
		return (array) $method->invoke(null);
	}
}
