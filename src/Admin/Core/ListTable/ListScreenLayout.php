<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\ListTable;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Server-side layout seam for the transformed CPT list screens.
 *
 * WHY THIS EXISTS
 * ---------------
 * The Faz 1a/1b/2 blocks (view toggle, Pro toolbar row, KPI band, chip strip,
 * cards/occupancy faces) used to print from `admin_notices` and were dragged
 * into place afterwards by jQuery. `admin_notices` fires inside
 * `#wpbody-content` BEFORE `wp-admin/edit.php` opens `<div class="wrap">`, so
 * the first painted frame showed the raw stream order — every block above the
 * page `<h1>` — and the page visibly snapped into shape at DOMContentLoaded.
 * Measured on the Vehicles screen: the `<h1>` sat at y=297 pre-JS and at y=42
 * afterwards, a 255px jump on every load.
 *
 * The cure is to print in the right place to begin with. `edit.php` exposes
 * exactly two extension points inside `.wrap`:
 *
 *   - `views_edit-{$post_type}` — applied at the top of
 *     `WP_List_Table::views()`, i.e. AFTER `<h1>` + `<hr class="wp-header-end">`
 *     and BEFORE `<form id="posts-filter">`. This is the header slot. The hook
 *     is a filter, so the callback echoes and returns `$views` untouched; there
 *     is no action in that region of the file.
 *   - `manage_posts_extra_tablenav` with `$which === 'bottom'` — fires inside
 *     `.tablenav.bottom`, after the list table and still inside `.wrap`. This
 *     is the face slot (cards / occupancy matrix). It is inside
 *     `<form id="posts-filter">`, which is safe here: the face markup is links,
 *     a table and one `type="button"` close control — no nested form, no
 *     submitting control, no input names that could ride along with a filter
 *     submit.
 *
 * THE NOTICES
 * -----------
 * Core's `common.js` unconditionally relocates `div.updated, div.error,
 * div.notice` to sit immediately after `.wp-header-end` — but only at
 * DOMContentLoaded, long after the first frame. Admin notices print from
 * `admin_notices`, i.e. above `.wrap`, so left alone they still cost a visible
 * jump: measured here, the whole header block slid up 54px when the Pro license
 * warning left the top of the page.
 *
 * A parse-time inline script closes that. It prints at the end of the header
 * slot and does core's relocation early, with core's own selector, placing
 * each notice directly below this block — where the relocation script used
 * to leave them, under the chip strip. It runs while the parser is still
 * working, so nothing has painted and it costs no visible reflow.
 *
 * Each notice it places is stamped `below-h2` — the class core's `common.js`
 * documents as "here just for backward compatibility with plugins" and excludes
 * from its own pass. It carries no styling anywhere in core, so it is a pure
 * behavioural opt-out: the DOMContentLoaded relocation then finds nothing left
 * to move and the page does not shift. Note what is deliberately NOT done here:
 * `<hr class="wp-header-end">` stays exactly where `edit.php` printed it. It is
 * not only core's relocation anchor, it is also the block element that closes
 * the inline-block page title's line — move it and the view toggle rides up
 * next to the title.
 *
 * Capturing the notice stream in PHP instead would mean re-echoing third-party
 * markup, a shape gate G-A holds at zero `WordPress.Security.EscapeOutput`
 * findings under `--ignore-annotations`; moving the nodes is the cheaper
 * honest option. With JavaScript disabled nothing moves at all: our blocks are
 * already in their final server-rendered order and the notices simply stay
 * where WordPress printed them, exactly as on any other admin screen.
 *
 * OUR OWN FACE NOTICES STAY WITH THE FACE
 * ----------------------------------------
 * A second copy of the script above used to run again at the end of the face
 * slot, so the Bookings Calendar face's own explanation notices (empty /
 * row-cap / vehicleless — printed by `BookingColumns::render_calendar_view()`)
 * joined the same sweep. That was wrong for THIS specific case: those notices
 * are not a third party's, they are the face's own explanation of itself, and
 * hoisting them to just under the chip strip separated the explanation from
 * the empty area it explains — on an empty month the calendar band sat
 * ~180px below a notice about that same emptiness, and the screen read as
 * "something failed to load" rather than "this month is empty". Those three
 * notices now carry core's own `inline` class instead — the documented
 * relocation opt-out (`.not('.inline, .below-h2')` in
 * `wp-admin/js/common.js`) this class's own sweep script already honours, so
 * they render exactly where they are echoed, beside the matrix. With that
 * fixed, the second pass had nothing left to do: no other FACE_ACTION
 * subscriber in this codebase prints a `div.notice`, and FACE_ACTION is not a
 * documented public seam a Pro subscriber is invited to hang a notice off of
 * (unlike, for instance, the booking toolbar's neutral filter seam). The
 * second pass was removed rather than kept for a hypothetical future
 * consumer. Third-party notices are unaffected either way: they print from
 * `admin_notices`, above `.wrap`, so the header slot's single pass already
 * sweeps them before the face slot ever runs.
 */
final class ListScreenLayout {

	/**
	 * Header slot: view toggle / toolbar row, KPI band, chip strip.
	 *
	 * Fires after the page `<h1>`, before `<form id="posts-filter">`.
	 * Subscriber priority decides the visual order.
	 *
	 * The `do_action()` calls below spell these names out as literals rather
	 * than referencing the constants: WPCS cannot resolve a constant to check
	 * the plugin prefix on it and warns on every such call. ListScreenLayoutTest
	 * pins each constant against its literal so the two cannot drift.
	 */
	public const HEADER_ACTION = 'mhmrentiva_list_screen_header';

	/**
	 * Face slot: whichever screen face replaces the list table
	 * (Vehicles Cards / Calendar, Bookings Calendar).
	 *
	 * Fires after the list table, inside `.tablenav.bottom`.
	 */
	public const FACE_ACTION = 'mhmrentiva_list_screen_face';

	/**
	 * Post types whose list screen carries the transformed layout.
	 *
	 * `mhmrentiva_addon` has no face block (no cards/calendar view to
	 * replace the list table with) — it only uses the header slot, for its
	 * page-title block and its KPI band. It is still listed here rather than
	 * given a parallel mechanism: `is_list_screen()` gates both slots
	 * identically for all three screens, and a face-less screen simply gets
	 * a `do_action()` with no subscribers on FACE_ACTION, which is a no-op.
	 */
	private const SCREENS = array( 'mhmrentiva_vehicle', 'mhmrentiva_booking', 'mhmrentiva_addon' );

	public static function register(): void
	{
		foreach (self::SCREENS as $post_type) {
			add_filter("views_edit-{$post_type}", array( self::class, 'render_header' ));
		}

		add_action('manage_posts_extra_tablenav', array( self::class, 'render_face' ));
	}

	/**
	 * Header slot renderer.
	 *
	 * `views_edit-{$post_type}` is the only extension point `edit.php` offers
	 * between the page title and the posts form, and it is a filter — so this
	 * echoes as a side effect and hands `$views` back untouched. The status
	 * link list core builds from `$views` still renders directly below.
	 *
	 * `$views` is deliberately left untyped even though the file declares
	 * `strict_types=1`. `views_edit-{$post_type}` is a third-party-extensible
	 * filter: any plugin hooked at a lower priority than ours is free to
	 * return anything, and core itself only ever reads `$views` as an array
	 * without asserting it. An `array` type hint here would turn a
	 * misbehaving earlier subscriber into a TypeError that takes down the
	 * whole list screen. Non-array input is passed through unchanged instead
	 * of being coerced — coercing it would fabricate a shape core never
	 * produced and this callback never guaranteed.
	 *
	 * @param  mixed $views Status links core is about to print. Expected to
	 *                      be an array<string, string>, but not guaranteed.
	 * @return mixed The same value handed in, unmodified.
	 */
	public static function render_header($views)
	{
		if (! is_array($views) || ! self::is_list_screen()) {
			return $views;
		}

		do_action('mhmrentiva_list_screen_header');
		self::print_notice_placement();

		return $views;
	}

	/**
	 * Face slot renderer.
	 *
	 * No notice-placement pass here — see "OUR OWN FACE NOTICES STAY WITH
	 * THE FACE" in the class docblock. The Calendar face's own notices opt
	 * out of relocation with the `inline` class instead, and nothing else
	 * hanging off FACE_ACTION prints a notice.
	 *
	 * @param string $which Which tablenav is rendering ('top' or 'bottom').
	 */
	public static function render_face(string $which): void
	{
		if ('bottom' !== $which || ! self::is_list_screen()) {
			return;
		}

		do_action('mhmrentiva_list_screen_face');
	}

	/**
	 * Whether the current request is rendering one of the transformed
	 * list screens. Same `$pagenow`/`$post_type` idiom the block renderers
	 * themselves use, so a screen guard never disagrees with its own block.
	 */
	private static function is_list_screen(): bool
	{
		global $pagenow, $post_type;

		$screen_post_type = (string) $post_type;

		return 'edit.php' === $pagenow
			&& in_array($screen_post_type, self::SCREENS, true);
	}

	/**
	 * Print the parse-time notice placement script.
	 *
	 * See the class docblock for why this exists. Defines
	 * `window.mhmRentivaPlaceAdminNotices()` and calls it immediately; the
	 * script tag itself is the insertion cursor, which is why the notices
	 * land directly below the header block, in document order. Kept as a
	 * named global rather than an anonymous IIFE even though nothing in this
	 * codebase calls it a second time any more (see the class docblock for
	 * why the face slot's repeat call was removed): it costs nothing to
	 * leave callable, and it is what ListScreenLayoutTest's coverage of this
	 * script inspects for.
	 */
	private static function print_notice_placement(): void
	{
		wp_print_inline_script_tag(
			'(function(){'
			. 'var at=document.currentScript;'
			. 'if(!at||!at.parentNode){return;}'
			. 'window.mhmRentivaPlaceAdminNotices=function(){'
			. 'var a=document.querySelectorAll("div.updated, div.error, div.notice");'
			. 'for(var i=0;i<a.length;i++){'
			. 'var el=a[i],c=" "+el.className+" ";'
			. 'if(c.indexOf(" inline ")>-1||c.indexOf(" below-h2 ")>-1){continue;}'
			. 'el.className=el.className+" below-h2";'
			. 'at.parentNode.insertBefore(el,at.nextSibling);'
			. 'at=el;'
			. '}};'
			. 'window.mhmRentivaPlaceAdminNotices();'
			. '})();'
		);
	}
}
