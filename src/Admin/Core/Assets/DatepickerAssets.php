<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Assets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Datepicker Assets Helper
 *
 * Centralizes datepicker script and style enqueuing across all components.
 * Ensures consistent CSS overrides and JS initialization dependencies.
 *
 * @since 4.20.x
 */
final class DatepickerAssets {

    /**
     * Enqueue datepicker dependencies, custom styles, and init script.
     */
    public static function enqueue(): void
    {
        // 1. Enqueue WordPress Core jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');

        // 2. Enqueue Global Datepicker Style (Core Variables)
        wp_enqueue_style('mhm-rentiva-css-variables');

        // 3. Enqueue Custom CSS Overrides (Glassmorphism / Premium UI)
        wp_enqueue_style(
            'mhm-rentiva-datepicker-custom',
            MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css',
            array( 'mhm-rentiva-css-variables' ),
            MHMRENTIVA_VERSION
        );

        // 4. Enqueue Centralized Init Script
        wp_enqueue_script(
            'mhm-rentiva-datepicker-init',
            MHMRENTIVA_PLUGIN_URL . 'assets/js/core/datepicker-init.js',
            array( 'jquery', 'jquery-ui-datepicker' ),
            MHMRENTIVA_VERSION,
            true
        );

        // 5. Pass localized datepicker options to JS
        wp_localize_script('mhm-rentiva-datepicker-init', 'mhmDatepickerL10n', self::get_localized_options());
    }

    /**
     * The value a date field submits: jQuery UI's `yy-mm-dd`, PHP's `Y-m-d`.
     */
    public const ISO_FORMAT = 'yy-mm-dd';

    /**
     * PHP date characters with a jQuery UI datepicker equivalent.
     */
    private const TOKEN_MAP = array(
        'd' => 'dd',
        'j' => 'd',
        'D' => 'D',
        'l' => 'DD',
        'm' => 'mm',
        'n' => 'm',
        'M' => 'M',
        'F' => 'MM',
        'Y' => 'yy',
        'y' => 'y',
    );

    /**
     * Time and timezone characters: a date field shows no time.
     */
    private const TIME_TOKENS = 'aABgGhHisuveIOPpTZ';

    /**
     * Date characters jQuery UI cannot show (ordinal suffix, ISO week/year, day
     * of year, days in month, leap year) and whole-moment formats (c, r, U).
     */
    private const UNSUPPORTED_TOKENS = 'SNwzWtLoXxcrU';

    /**
     * WordPress `date_format` (PHP date()) as a jQuery UI datepicker dateFormat.
     *
     * The one conversion for every front-end datepicker (the shared init and the
     * old booking form). Mapped: d j D l m n M F Y y. Dropped, because jQuery UI
     * has no equivalent: the ordinal suffix `S` (5th -> 5) and N w z W t L o X x;
     * time and timezone characters, with the literals that only lead into them
     * (`j F Y \a\t g:i a` -> `d MM yy`). Escaped characters and plain letters
     * become quoted jQuery UI literals. A format left without a day, a month and
     * a year (`F j`, `c`, `U`, '') cannot round-trip a date, so it falls back to
     * ISO_FORMAT.
     */
    public static function js_date_format(string $php_format): string
    {
        $parts  = array();
        $length = strlen($php_format);
        for ($i = 0; $i < $length; $i++) {
            $char = $php_format[ $i ];
            if ('\\' === $char) {
                ++$i;
                if ($i < $length) {
                    $parts[] = array( 'lit', $php_format[ $i ] );
                }
            } elseif (isset(self::TOKEN_MAP[ $char ])) {
                $parts[] = array( 'date', $char );
            } elseif (false !== strpos(self::TIME_TOKENS, $char)) {
                $parts[] = array( 'time', $char );
            } elseif (false !== strpos(self::UNSUPPORTED_TOKENS, $char)) {
                $parts[] = array( 'drop', $char );
            } else {
                $parts[] = array( 'lit', $char );
            }
        }

        $date_positions = array_keys(array_filter($parts, static fn(array $part): bool => 'date' === $part[0]));
        $has            = static fn(string $set): bool => array() !== array_filter($parts, static fn(array $part): bool => 'date' === $part[0] && false !== strpos($set, $part[1]));
        if (array() === $date_positions || ! $has('dj') || ! $has('mnMF') || ! $has('Yy')) {
            return self::ISO_FORMAT;
        }

        // A time before the first date character or after the last one takes the
        // literals that lead into it along: the field starts and ends on the date.
        $first  = (int) min($date_positions);
        $last   = (int) max($date_positions);
        $before = array_filter(array_slice($parts, 0, $first), static fn(array $part): bool => 'time' === $part[0]);
        $after  = array_filter(array_slice($parts, $last + 1), static fn(array $part): bool => 'time' === $part[0]);
        $start  = array() !== $before ? $first : 0;
        $end    = array() !== $after ? $last : count($parts) - 1;

        $out    = '';
        $quoted = '';
        foreach (array_slice($parts, $start, $end - $start + 1) as $part) {
            list( $type, $char ) = $part;
            if ('lit' === $type && 1 === preg_match('/[A-Za-z@!]/', $char)) {
                $quoted .= $char;
                continue;
            }
            if ('' !== $quoted) {
                $out   .= "'" . $quoted . "'";
                $quoted = '';
            }
            if ('date' === $type) {
                $out .= self::TOKEN_MAP[ $char ];
            } elseif ('lit' === $type) {
                $out .= "'" === $char ? "''" : $char;
            }
        }
        if ('' !== $quoted) {
            $out .= "'" . $quoted . "'";
        }

        $out = (string) preg_replace('/ {2,}/', ' ', $out);

        return trim($out);
    }

    /**
     * The site's date format as the customer date fields show it (jQuery UI
     * syntax): the one format the page renders with and the init receives.
     */
    public static function display_format(): string
    {
        return self::js_date_format( (string) get_option('date_format', 'Y-m-d'));
    }

    /**
     * A Y-m-d date as the field shows it, '' for no (or no real) date.
     *
     * The template renders the visible field with this, so the page shows the
     * site format before the script runs (no Y-m-d flash); the submitted value
     * stays Y-m-d in the hidden field next to it. Follows jQuery UI's
     * formatDate() on display_format() with the names the init receives, so
     * the init has nothing to rewrite.
     */
    public static function display_date(string $ymd): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new \DateTimeZone('UTC'));
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) || false === $date || $date->format('Y-m-d') !== $ymd) {
            return '';
        }

        $names  = self::names();
        $format = self::display_format();
        $length = strlen($format);
        $out    = '';
        $quoted = false;
        // One more of the same character makes the long form (jQuery UI lookAhead).
        $double = static function (string $char) use ($format, $length, &$i): bool {
            if ($i + 1 < $length && $format[ $i + 1 ] === $char) {
                ++$i;
                return true;
            }
            return false;
        };
        for ($i = 0; $i < $length; $i++) {
            $char = $format[ $i ];
            if ($quoted) {
                if ("'" === $char && ! $double("'")) {
                    $quoted = false;
                } else {
                    $out .= $char;
                }
                continue;
            }
            switch ($char) {
                case 'd':
                    $day  = (int) $date->format('j');
                    $out .= $double('d') ? sprintf('%02d', $day) : (string) $day;
                    break;
                case 'D':
                    $weekday = (int) $date->format('w');
                    $out    .= $double('D') ? $names['dayNames'][ $weekday ] : $names['dayNamesShort'][ $weekday ];
                    break;
                case 'm':
                    $month = (int) $date->format('n');
                    $out  .= $double('m') ? sprintf('%02d', $month) : (string) $month;
                    break;
                case 'M':
                    $month = (int) $date->format('n') - 1;
                    $out  .= $double('M') ? $names['monthNames'][ $month ] : $names['monthNamesShort'][ $month ];
                    break;
                case 'y':
                    $out .= $double('y') ? $date->format('Y') : $date->format('y');
                    break;
                case "'":
                    if ($double("'")) {
                        $out .= "'";
                    } else {
                        $quoted = true;
                    }
                    break;
                default:
                    $out .= $char;
            }
        }

        return $out;
    }

    /**
     * Month and day names the field may show: the init's (localized) and the
     * page's render (display_date()) use this one list.
     *
     * @return array{monthNames: list<string>, monthNamesShort: list<string>, dayNames: list<string>, dayNamesShort: list<string>}
     */
    private static function names(): array
    {
        $names = array(
            'monthNames'      => array(
                __('January', 'mhm-rentiva'),
                __('February', 'mhm-rentiva'),
                __('March', 'mhm-rentiva'),
                __('April', 'mhm-rentiva'),
                __('May', 'mhm-rentiva'),
                __('June', 'mhm-rentiva'),
                __('July', 'mhm-rentiva'),
                __('August', 'mhm-rentiva'),
                __('September', 'mhm-rentiva'),
                __('October', 'mhm-rentiva'),
                __('November', 'mhm-rentiva'),
                __('December', 'mhm-rentiva'),
            ),
            // jQuery UI's own English defaults when WordPress has no locale yet.
            'monthNamesShort' => array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ),
            'dayNames'        => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
            'dayNamesShort'   => array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ),
        );

        return array_merge($names, self::locale_names());
    }

    /**
     * Get localized datepicker options for jQuery UI Datepicker.
     *
     * @since 4.21.2
     * @return array<string, mixed>
     */
    private static function get_localized_options(): array
    {
        return array(
            'closeText'   => __('Close', 'mhm-rentiva'),
            'currentText' => __('Today', 'mhm-rentiva'),
            'dayNamesMin' => array(
                __('Su', 'mhm-rentiva'),
                __('Mo', 'mhm-rentiva'),
                __('Tu', 'mhm-rentiva'),
                __('We', 'mhm-rentiva'),
                __('Th', 'mhm-rentiva'),
                __('Fr', 'mhm-rentiva'),
                __('Sa', 'mhm-rentiva'),
            ),
            'firstDay'    => 1,
            'isRTL'       => is_rtl(),
            // The field shows the site's date format; it submits Y-m-d (altFormat).
            'dateFormat'  => self::display_format(),
            'altFormat'   => self::ISO_FORMAT,
        ) + self::names();
    }

    /**
     * Short month names and the day names a site format may show (M, D, l),
     * from WordPress's own translated locale -- no new strings.
     *
     * @return array<string, list<string>>
     */
    private static function locale_names(): array
    {
        global $wp_locale;
        if (! $wp_locale instanceof \WP_Locale) {
            return array();
        }

        $short = array();
        for ($month = 1; $month <= 12; $month++) {
            $short[] = (string) $wp_locale->get_month_abbrev($wp_locale->get_month($month));
        }
        $days       = array();
        $days_short = array();
        for ($day = 0; $day <= 6; $day++) {
            $name         = (string) $wp_locale->get_weekday($day);
            $days[]       = $name;
            $days_short[] = (string) $wp_locale->get_weekday_abbrev($name);
        }

        return array(
            'monthNamesShort' => $short,
            'dayNames'        => $days,
            'dayNamesShort'   => $days_short,
        );
    }
}
