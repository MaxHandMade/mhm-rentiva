<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Helpers;

use WP_UnitTestCase;

/**
 * WP.org T7 Görev 5 (K2 — callback dönüşleri escape): before wrapping the
 * shortcode dispatcher and block render_callback returns in wp_kses(), the
 * allowlist those calls will use (Html::allowed_markup()) must be proven to
 * keep markup the plugin already ships and genuinely relies on.
 *
 * A prior architecture audit (2026-07-31) found that a naive wp_kses() wrap
 * would silently strip real attributes: WP core's wp_kses_allowed_html('post')
 * gives generic tags like `a` only a fixed, narrow global attribute set --
 * `tabindex` and wildcard `aria-*` are NOT in it. Two shipped templates
 * render exactly these on `<a>`:
 *
 *  - templates/shortcodes/vehicle-comparison.php:115,331 -- disabled-state
 *    booking link: `aria-disabled="true" tabindex="-1"`
 *  - templates/partials/vehicle-card.php:41 -- decorative image-wrap link:
 *    `aria-hidden="true" tabindex="-1"`
 *
 * If wp_kses() strips these, the accessibility markup for the "cannot be
 * booked" / "duplicate link, skip for screen readers" states disappears
 * silently -- the HTML still renders, so nothing looks broken, but assistive
 * tech loses the signal.
 *
 * @covers \MHMRentiva\Helpers\Html::allowed_markup
 */
final class HtmlAllowedMarkupTest extends WP_UnitTestCase
{
    public function test_allowed_markup_preserves_disabled_state_link_attributes(): void
    {
        $allowed = \MHMRentiva\Helpers\Html::allowed_markup();

        // vehicle-comparison.php:115/331 and vehicle-card.php:41 render exactly
        // this shape; if wp_kses() strips these, the disabled/decorative-link
        // accessibility markup vanishes silently.
        $this->assertArrayHasKey('tabindex', $allowed['a'] ?? [], "'a' loses tabindex");
        $this->assertArrayHasKey('aria-disabled', $allowed['a'] ?? [], "'a' loses aria-disabled");
        $this->assertArrayHasKey('aria-hidden', $allowed['a'] ?? [], "'a' loses aria-hidden");

        $kept = wp_kses('<a href="#" aria-disabled="true" tabindex="-1">X</a>', $allowed);
        $this->assertStringContainsString('tabindex="-1"', $kept);
        $this->assertStringContainsString('aria-disabled="true"', $kept);
    }

    /**
     * wp_kses_bad_protocol() unconditionally strips `javascript:` URLs. That is
     * a known and accepted behavior change from wp_kses() wrapping the
     * dispatcher/block returns -- but Adım 2b of the plan fixes the disabled
     * -state templates to use `href="#"` BEFORE the wp_kses() wrap exists, so
     * the two shipped templates never rely on wp_kses's side effect to begin
     * with. This test pins the wp_kses behavior itself (the reason the
     * templates cannot keep `javascript:void(0);`), independent of whether the
     * templates have been fixed.
     */
    public function test_wp_kses_strips_javascript_protocol_hrefs(): void
    {
        $allowed = \MHMRentiva\Helpers\Html::allowed_markup();
        $kept    = wp_kses('<a href="javascript:void(0);" aria-disabled="true" tabindex="-1">X</a>', $allowed);

        $this->assertStringNotContainsString('javascript:', $kept);
        // The attributes survive; only the dangerous protocol is removed.
        $this->assertStringContainsString('tabindex="-1"', $kept);
        $this->assertStringContainsString('aria-disabled="true"', $kept);
    }

    /**
     * Found by the Adım 6 visual-parity sweep: Icons::get() puts
     * `stroke-linecap`/`stroke-linejoin` on the outer `<svg>` element itself
     * (src/Helpers/Icons.php:58), not only on the path/line children the
     * pre-existing `$svg_shape` override already covered. Before this test,
     * wp_kses()-through-the-dispatcher silently rounded every icon's stroke
     * caps back to the browser default on real, shipped icon markup.
     */
    public function test_allowed_markup_preserves_svg_stroke_linecap_and_linejoin(): void
    {
        $allowed = \MHMRentiva\Helpers\Html::allowed_markup();

        $this->assertArrayHasKey('stroke-linecap', $allowed['svg'] ?? [], "'svg' loses stroke-linecap");
        $this->assertArrayHasKey('stroke-linejoin', $allowed['svg'] ?? [], "'svg' loses stroke-linejoin");

        $kept = wp_kses(
            '<svg stroke-width="2" stroke-linecap="round" stroke-linejoin="round" overflow="visible"><circle cx="1" cy="1" r="1"/></svg>',
            $allowed
        );
        $this->assertStringContainsString('stroke-linecap="round"', $kept);
        $this->assertStringContainsString('stroke-linejoin="round"', $kept);
    }

    /**
     * Found by the Adım 6 visual-parity sweep: `'aria-*' => true` is NOT a
     * real wildcard for wp_kses() -- unlike `data-*`, WP core's kses.php has
     * no special-cased prefix handling for `aria-` (only 'data-*' gets that
     * treatment; grep wp-includes/kses.php to confirm). Every aria-* key that
     * used to sit only in the dead wildcard entry silently vanished from every
     * tag built from $common (form/input/select/option/optgroup/textarea/
     * button/label/fieldset/legend/progress/svg/svg-shapes) the moment the
     * dispatcher/block wrap started running real templates through wp_kses()
     * -- most visibly, `aria-label` on the icon-only favorite/compare buttons
     * in templates/partials/vehicle-card.php, an accessibility regression
     * with no visual symptom at all.
     */
    public function test_allowed_markup_preserves_real_aria_attributes_on_button(): void
    {
        $allowed = \MHMRentiva\Helpers\Html::allowed_markup();

        $this->assertArrayNotHasKey('aria-*', $allowed['button'] ?? [], "dead wildcard key should not linger");
        $this->assertArrayHasKey('aria-label', $allowed['button'] ?? [], "'button' loses aria-label");
        $this->assertArrayHasKey('aria-pressed', $allowed['button'] ?? [], "'button' loses aria-pressed");

        $kept = wp_kses(
            '<button type="button" aria-label="Add to Favorites" aria-pressed="false">Y</button>',
            $allowed
        );
        $this->assertStringContainsString('aria-label="Add to Favorites"', $kept);
        $this->assertStringContainsString('aria-pressed="false"', $kept);
    }

    /**
     * Found by the Adım 6 visual-parity sweep: WP core's wp_kses_allowed_html
     * ('post') gives `img` no `srcset`/`sizes` at all. templates/partials/
     * vehicle-card.php:51-57 sets both for retina/wide vehicle photos; without
     * this, every vehicle card image silently fell back to a single `src`.
     */
    public function test_allowed_markup_preserves_img_srcset_and_sizes(): void
    {
        $allowed = \MHMRentiva\Helpers\Html::allowed_markup();

        $this->assertArrayHasKey('srcset', $allowed['img'] ?? [], "'img' loses srcset");
        $this->assertArrayHasKey('sizes', $allowed['img'] ?? [], "'img' loses sizes");

        $kept = wp_kses(
            '<img src="a.png" alt="x" srcset="a.png 1024w, b.png 300w" sizes="(max-width: 782px) 100vw, 500px">',
            $allowed
        );
        $this->assertStringContainsString('srcset="a.png 1024w, b.png 300w"', $kept);
        $this->assertStringContainsString('sizes="(max-width: 782px) 100vw, 500px"', $kept);
    }

    /**
     * Found by re-running the Adım 6 visual-parity sweep (a single-request
     * raw-output-vs-escaped-output diff, not visible from reading
     * allowed_markup() alone): a bare `wp_kses($html, Html::allowed_markup())`
     * still silently drops `fill` from every `style="..."` value, because
     * wp_kses() filters style-attribute CONTENT through a completely separate
     * mechanism -- `safecss_filter_attr()`, driven by the `safe_style_css`
     * list -- that `allowed_markup()`'s tag/attribute array has no say over.
     * templates/shortcodes/vehicle-rating-form.php:275 and
     * templates/shortcodes/availability-calendar.php:177/185/194 set a rating
     * star's color via `style="fill: #hex; color: #hex"`; without `fill`
     * surviving, the star's own `fill="none"` attribute wins instead and the
     * star renders with no visible color at all.
     *
     * {@see \MHMRentiva\Helpers\Icons::echo_svg()} already solves this for its
     * OWN narrower pass by temporarily widening `safe_style_css` around a
     * single wp_kses() call; {@see \MHMRentiva\Helpers\Html::kses()} is the
     * same fix generalized to the dispatcher/block-callback return, which
     * runs a SECOND, wider wp_kses() pass over content Icons::echo_svg() may
     * have already escaped once.
     *
     * @covers \MHMRentiva\Helpers\Html::kses
     */
    public function test_kses_preserves_fill_in_style_attribute(): void
    {
        $html = '<svg fill="none" style="width: 14px; fill: #fbbf24; color: #fbbf24;"><polygon points="1,1 2,2"></polygon></svg>';

        $kept = \MHMRentiva\Helpers\Html::kses($html);

        $this->assertStringContainsString('fill: #fbbf24', $kept, 'Html::kses() must not strip fill from style=');
    }

    /**
     * Same finding, different properties: `pointer-events` (disabled-state
     * controls in templates/shortcodes/vehicle-comparison.php and
     * templates/account/payment-history.php) and `resize` (the cancellation
     * textarea in templates/account/booking-detail.php) are also missing from
     * wp_kses()'s default `safe_style_css` list. Neither is load-bearing today
     * (`.rv-btn-disabled` already sets `pointer-events: none !important` in
     * CSS; `resize` only controls a resize handle), but Html::kses() widens
     * both anyway rather than leave an unexplained diff between raw template
     * output and the escaped dispatcher/block-callback return.
     *
     * @covers \MHMRentiva\Helpers\Html::kses
     */
    public function test_kses_preserves_pointer_events_and_resize_in_style_attribute(): void
    {
        $html = '<div style="opacity: .6; pointer-events: none;"><textarea style="resize: vertical;"></textarea></div>';

        $kept = \MHMRentiva\Helpers\Html::kses($html);

        $this->assertStringContainsString('pointer-events: none', $kept, "Html::kses() must not strip pointer-events from style=");
        $this->assertStringContainsString('resize: vertical', $kept, 'Html::kses() must not strip resize from style=');
    }

    /**
     * `Html::kses()`'s style-property widening must not become a bigger
     * loophole than intended: outside of `fill`/`pointer-events`/`resize`, the
     * rest of wp_kses()'s default `safe_style_css` policy stays exactly as
     * restrictive as it is anywhere else in WordPress -- e.g. `expression()`
     * and other properties/values core already refuses stay refused.
     *
     * @covers \MHMRentiva\Helpers\Html::kses
     */
    public function test_kses_does_not_widen_style_filtering_beyond_the_documented_three_properties(): void
    {
        $html = '<div style="width: 1px; behavior: url(evil.htc);"></div>';

        $kept = \MHMRentiva\Helpers\Html::kses($html);

        $this->assertStringNotContainsString('behavior', $kept, 'Html::kses() must not have widened the safe_style_css list beyond fill/pointer-events/resize');
    }

    /**
     * `<select readonly>` isn't a real HTML attribute (the spec only defines
     * `readonly` for `input`/`textarea`; `disabled` already does the actual
     * work here), but templates/shortcodes/booking-form.php's dropoff-time
     * select emits it anyway, and wp_kses() would otherwise strip it -- found
     * by the same visual-parity sweep. Allowed for byte-parity with the
     * pre-wp_kses dispatcher rather than left as an unexplained (if harmless)
     * diff.
     */
    public function test_allowed_markup_preserves_readonly_on_select(): void
    {
        $allowed = \MHMRentiva\Helpers\Html::allowed_markup();

        $this->assertArrayHasKey('readonly', $allowed['select'] ?? [], "'select' loses readonly");

        $kept = wp_kses('<select disabled readonly><option value="a">a</option></select>', $allowed);
        $this->assertStringContainsString('readonly', $kept);
    }
}
