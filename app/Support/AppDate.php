<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * One definition of how the app writes a moment in time for the user:
 * 08/09/2026 2:33 p.m.
 */
final class AppDate
{
    /**
     * Everything except the meridiem, which PHP only renders as "am"/"pm" while the
     * app writes it the Mexican way. Doing it by hand keeps ext-intl off the
     * dependency list for what amounts to two dots.
     */
    private const DATE_TIME = 'd/m/Y g:i';

    public static function dateTime(?CarbonInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $meridiem = $date->format('a') === 'am' ? 'a.m.' : 'p.m.';

        return $date->format(self::DATE_TIME).' '.$meridiem;
    }
}
