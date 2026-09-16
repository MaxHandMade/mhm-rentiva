<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

use MHMRentiva\Admin\Core\ListTable\ListScreenLayout;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Places admin notices below the page header before anything is painted, on
 * every Rentiva admin screen.
 *
 * WHY
 * ---
 * WordPress prints `admin_notices` above the screen's `.wrap`, and core's
 * common.js moves each notice below `.wp-header-end` only at DOMContentLoaded.
 * notice-placement.css keeps a notice hidden until that move, so it no longer
 * flashes above the title -- but the move still pushes the whole page down by
 * the notice's height once the screen has painted, and on a slow screen the
 * CSS fail-safe (2s) shows the notice at the top first. Measured 2026-09-17
 * on Additional Services: hidden at 363ms, placed at 384ms, page shifted.
 *
 * ListScreenLayout already solved this for the three transformed list
 * screens with a parse-time script printed inside `.wrap`. Custom screens and
 * core's own screens (post edit, taxonomy, contact list) have no single
 * render hook to print from, so this script is printed once, before
 * `#wpbody-content`, and watches the parser instead: the moment the first
 * `.wp-header-end` inside `.wrap` is inserted, it moves every top-level notice
 * directly below that marker. MutationObserver callbacks run at the
 * microtask checkpoint that ends the parser task, and rendering only happens
 * after it, so the notice is already in place in the first frame that shows
 * the header.
 *
 * Each placed notice gets `below-h2`, the class common.js excludes from its
 * own relocation pass, so nothing moves again at DOMContentLoaded. Only the
 * FIRST marker is used; common.js would otherwise clone a notice once per
 * marker (the Settings > Notification Templates tab printed two).
 *
 * WHAT IT LEAVES ALONE
 * --------------------
 * - Notices printed inside `.wrap` by the screen itself (settings_errors(),
 *   `inline` notices): they are already where the screen put them.
 * - Screens without `.wrap` (the React Dashboard): no marker ever appears, the
 *   observer disconnects at DOMContentLoaded and the notice stays where
 *   WordPress printed it, as before.
 * - The transformed list screens: ListScreenLayout places notices below the
 *   KPI band there, a different spot; this script is not printed on them.
 *
 * With JavaScript disabled nothing moves, exactly as on any other screen.
 */
final class NoticePlacement {

	public static function register(): void
	{
		add_action('in_admin_header', array( self::class, 'print_script' ));
	}

	/**
	 * Whether the current screen gets the placement script.
	 */
	public static function applies(): bool
	{
		if (! function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();
		if (! $screen) {
			return false;
		}

		if (ListScreenLayout::is_list_screen()) {
			return false;
		}

		return str_contains($screen->id, 'mhmrentiva')
			|| str_contains($screen->id, 'mhm-rentiva')
			|| str_contains((string) ( $screen->post_type ?? '' ), 'mhmrentiva')
			|| str_contains((string) ( $screen->taxonomy ?? '' ), 'mhmrentiva');
	}

	public static function print_script(): void
	{
		if (! self::applies()) {
			return;
		}

		wp_print_inline_script_tag(
			'(function(){'
			. 'if(!window.MutationObserver){return;}'
			. 'var done=false;'
			. 'function place(marker){'
			. 'var body=document.getElementById("wpbody-content");'
			. 'if(!body){return;}'
			. 'var at=marker,list=body.querySelectorAll(":scope > div.updated, :scope > div.error, :scope > div.notice");'
			. 'for(var i=0;i<list.length;i++){'
			. 'var el=list[i],c=" "+el.className+" ";'
			. 'if(c.indexOf(" inline ")>-1||c.indexOf(" below-h2 ")>-1){continue;}'
			. 'el.className=el.className+" below-h2";'
			. 'at.parentNode.insertBefore(el,at.nextSibling);'
			. 'at=el;'
			. '}}'
			. 'var mo=new MutationObserver(function(){'
			. 'if(done){return;}'
			. 'var m=document.querySelector(".wrap .wp-header-end");'
			. 'if(!m){return;}'
			. 'done=true;mo.disconnect();place(m);'
			. '});'
			. 'mo.observe(document.documentElement,{childList:true,subtree:true});'
			. 'document.addEventListener("DOMContentLoaded",function(){mo.disconnect();});'
			. '})();'
		);
	}
}
