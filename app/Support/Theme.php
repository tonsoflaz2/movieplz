<?php

namespace App\Support;

/**
 * The app's color themes. Each person picks their own (clients.theme);
 * null is Classic, the red the app was designed in.
 *
 * Screens are written in Classic's colors, and ApplyTheme passes each HAM
 * response through apply(), which swaps Classic's color tokens for the
 * person's theme. So every screen, including new ones, is themed without
 * knowing about themes, as long as it uses the Classic palette below.
 *
 * Only color-carrying attributes are touched (style, the bar and config
 * colors, and an <icon>'s name), never text or other attributes, and only
 * Classic's exact tokens. Hex is matched in uppercase only: a color that
 * must stay as it is (the theme picker's swatches) is written in lowercase.
 */
class Theme
{
    public const CLASSIC = 'classic';

    /**
     * Each theme's roles:
     *   bg       screen background                    Classic #DC2626
     *   bar      app bar, tab bar, primary_color      #991B1B
     *   input    the search field                     #B91C1C
     *   barText  app bar title, selected tab          #FFFFFF
     *   navOff   unselected tabs                      #D4D4D4 / #FECACA
     *   text     body text (Material name)            white
     *   textHex  default_text_color                   #FFFFFF
     *   muted    secondary text                       red-100
     *   faint    tertiary text                        red-200, red-300
     *   outline  outlined and plain buttons           #FECACA
     *   fill     filled buttons                       #1F2937
     *   overlay  card tint, under bg-opacity-*        black
     *   success, successStrong, successBg             green-300; green-100/200; green-800
     *   mode     the engine's dark-mode               dark
     */
    public const THEMES = [
        'classic' => [
            'name' => 'Classic', 'dark' => true,
            'bg' => '#DC2626', 'bar' => '#991B1B', 'input' => '#B91C1C', 'barText' => '#FFFFFF', 'navOff' => '#D4D4D4',
            'text' => 'white', 'textHex' => '#FFFFFF', 'muted' => 'red-100', 'faint' => 'red-200',
            'outline' => '#FECACA', 'fill' => '#1F2937', 'overlay' => 'black',
            'success' => 'green-300', 'successStrong' => 'green-100', 'successBg' => 'green-800', 'mode' => 'dark',
        ],
        'midnight' => [
            'name' => 'Midnight', 'dark' => true,
            'bg' => '#0F172A', 'bar' => '#020617', 'input' => '#1E293B', 'barText' => '#FFFFFF', 'navOff' => '#94A3B8',
            'text' => 'white', 'textHex' => '#FFFFFF', 'muted' => 'indigo-100', 'faint' => 'indigo-200',
            'outline' => '#A5B4FC', 'fill' => '#4F46E5', 'overlay' => 'white',
            'success' => 'green-300', 'successStrong' => 'green-100', 'successBg' => 'green-800', 'mode' => 'dark',
        ],
        'cinema' => [
            'name' => 'Cinema', 'dark' => true,
            'bg' => '#0A0A0A', 'bar' => '#000000', 'input' => '#262626', 'barText' => '#FCD34D', 'navOff' => '#A3A3A3',
            'text' => 'white', 'textHex' => '#FFFFFF', 'muted' => 'amber-100', 'faint' => 'amber-200',
            'outline' => '#FCD34D', 'fill' => '#B45309', 'overlay' => 'white',
            'success' => 'green-300', 'successStrong' => 'green-100', 'successBg' => 'green-800', 'mode' => 'dark',
        ],
        'plum' => [
            'name' => 'Plum', 'dark' => true,
            'bg' => '#3B0764', 'bar' => '#2E1065', 'input' => '#581C87', 'barText' => '#FFFFFF', 'navOff' => '#D8B4FE',
            'text' => 'white', 'textHex' => '#FFFFFF', 'muted' => 'purple-100', 'faint' => 'purple-200',
            'outline' => '#E9D5FF', 'fill' => '#7E22CE', 'overlay' => 'black',
            'success' => 'green-300', 'successStrong' => 'green-100', 'successBg' => 'green-800', 'mode' => 'dark',
        ],
        'paper' => [
            'name' => 'Paper', 'dark' => false,
            'bg' => '#FAFAFA', 'bar' => '#DC2626', 'input' => '#E5E7EB', 'barText' => '#FFFFFF', 'navOff' => '#FECACA',
            'text' => 'black', 'textHex' => '#111111', 'muted' => 'red-900', 'faint' => 'red-800',
            'outline' => '#DC2626', 'fill' => '#1F2937', 'overlay' => 'black',
            'success' => 'green-800', 'successStrong' => 'green-900', 'successBg' => 'green-100', 'mode' => 'light',
        ],
        'sky' => [
            'name' => 'Sky', 'dark' => false,
            'bg' => '#EFF6FF', 'bar' => '#1D4ED8', 'input' => '#DBEAFE', 'barText' => '#FFFFFF', 'navOff' => '#BFDBFE',
            'text' => 'black', 'textHex' => '#111111', 'muted' => 'blue-900', 'faint' => 'blue-800',
            'outline' => '#1D4ED8', 'fill' => '#1E3A8A', 'overlay' => 'black',
            'success' => 'green-800', 'successStrong' => 'green-900', 'successBg' => 'green-100', 'mode' => 'light',
        ],
        'mint' => [
            'name' => 'Mint', 'dark' => false,
            'bg' => '#ECFDF5', 'bar' => '#047857', 'input' => '#D1FAE5', 'barText' => '#FFFFFF', 'navOff' => '#A7F3D0',
            'text' => 'black', 'textHex' => '#111111', 'muted' => 'teal-900', 'faint' => 'teal-800',
            'outline' => '#047857', 'fill' => '#064E3B', 'overlay' => 'black',
            'success' => 'green-800', 'successStrong' => 'green-900', 'successBg' => 'green-100', 'mode' => 'light',
        ],
        'popcorn' => [
            'name' => 'Popcorn', 'dark' => false,
            'bg' => '#FFFBEB', 'bar' => '#B45309', 'input' => '#FEF3C7', 'barText' => '#FFFFFF', 'navOff' => '#FDE68A',
            'text' => 'black', 'textHex' => '#111111', 'muted' => 'amber-900', 'faint' => 'amber-800',
            'outline' => '#B45309', 'fill' => '#78350F', 'overlay' => 'black',
            'success' => 'green-800', 'successStrong' => 'green-900', 'successBg' => 'green-100', 'mode' => 'light',
        ],
    ];

    /** Attributes whose value is colors, and which role a Classic #FFFFFF / #FECACA means there. */
    private const COLOR_ATTRIBUTES = 'style|bg_color|text_color|selected_color|unselected_color|primary_color|default_text_color|dark_bg_color|dark-mode';

    /** A theme's key, falling back to Classic for null or anything unknown. */
    public static function key(?string $theme): string
    {
        return isset(self::THEMES[$theme]) ? $theme : self::CLASSIC;
    }

    /** @return array<string, mixed> */
    public static function get(?string $theme): array
    {
        return self::THEMES[self::key($theme)];
    }

    /** Swap Classic's colors in [ham] for [theme]'s. Classic returns it untouched. */
    public static function apply(string $ham, ?string $theme): string
    {
        $key = self::key($theme);
        if ($key === self::CLASSIC) {
            return $ham;
        }
        $t = self::THEMES[$key];

        $ham = preg_replace_callback(
            '/(?<![\w-])('.self::COLOR_ATTRIBUTES.')="([^"]*)"/',
            fn ($m) => $m[1].'="'.self::recolor($m[2], $t, $m[1]).'"',
            $ham,
        );

        return preg_replace_callback(
            '/(<icon\b[^>]*?\bname=")([^"]*)(")/',
            fn ($m) => $m[1].self::recolor($m[2], $t, 'icon').$m[3],
            $ham,
        );
    }

    /** One attribute value's Classic tokens, in [t]'s colors. */
    private static function recolor(string $value, array $t, string $attribute): string
    {
        if ($attribute === 'dark-mode') {
            return $value === 'dark' ? $t['mode'] : $value;
        }

        return preg_replace_callback(
            '/(?<![\w#-])((?:bg-|text-|border-)?#[0-9A-F]{6}|(?:text-|bg-)?(?:red|green|white|black)-?\d{0,3})(?![\w-])/',
            function ($m) use ($t, $attribute) {
                $token = $m[1];
                // bg-#B91C1C and the like: recolor the hex, keep the prefix.
                if (preg_match('/^(bg-|text-|border-)(#[0-9A-F]{6})$/', $token, $hex)) {
                    return $hex[1].self::recolor($hex[2], $t, $attribute);
                }

                return match ($token) {
                    '#DC2626' => $t['bg'],
                    '#991B1B' => $t['bar'],
                    '#B91C1C' => $t['input'],
                    '#1F2937' => $t['fill'],
                    '#D4D4D4' => $t['navOff'],
                    '#FECACA' => $attribute === 'unselected_color' ? $t['navOff'] : $t['outline'],
                    '#FFFFFF' => in_array($attribute, ['text_color', 'selected_color'], true) ? $t['barText'] : $t['textHex'],
                    'text-white' => 'text-'.$t['text'],
                    'white' => $t['text'],
                    'bg-black' => 'bg-'.$t['overlay'],
                    'text-red-100' => 'text-'.$t['muted'],
                    'red-100' => $t['muted'],
                    'text-red-200', 'text-red-300' => 'text-'.$t['faint'],
                    'red-200', 'red-300' => $t['faint'],
                    'text-green-300' => 'text-'.$t['success'],
                    'green-300' => $t['success'],
                    'text-green-100', 'text-green-200' => 'text-'.$t['successStrong'],
                    'green-100', 'green-200' => $t['successStrong'],
                    'bg-green-800' => 'bg-'.$t['successBg'],
                    default => $token,
                };
            },
            $value,
        );
    }
}
